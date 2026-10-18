<?php
/**
 * Custom domains: add, verify, connect (becomes the site's main address), remove.
 *
 * Verification passes when http://domain/.well-known/ks-domain-check answers with the
 * domain's token (the domain already points at this server), or when a TXT record
 * _ks-verify.domain holds the token and the domain's CNAME/A record points here.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Domains.
 */
class KS_Domains {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'ks_daily', array( __CLASS__, 'recheck_pending' ) );
	}

	/**
	 * Domains of a site.
	 *
	 * @param int $blog_id Site.
	 * @return array
	 */
	public static function for_site( $blog_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . ks_table( 'domains' ) . ' WHERE blog_id = %d ORDER BY id DESC', $blog_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * All domains.
	 *
	 * @return array
	 */
	public static function all() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . ks_table( 'domains' ) . ' ORDER BY id DESC' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * One domain.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ks_table( 'domains' ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Clean a typed domain: "https://www.Grace.org/about" → "www.grace.org".
	 *
	 * @param string $raw Input.
	 * @return string
	 */
	public static function normalize( $raw ) {
		$d = strtolower( trim( (string) $raw ) );
		$d = preg_replace( '#^[a-z]+://#', '', $d );
		$d = preg_replace( '#[/?\#].*$#', '', $d );
		$d = preg_replace( '/:\d+$/', '', $d );
		return rtrim( $d, '.' );
	}

	/**
	 * Add a domain to a site.
	 *
	 * @param int    $blog_id Site.
	 * @param string $raw     Domain.
	 * @return object|WP_Error
	 */
	public static function add( $blog_id, $raw ) {
		global $wpdb;
		if ( ! KS_Tenants::feature( 'custom_domain', $blog_id ) ) {
			return new WP_Error( 'ks_plan', __( 'Custom domains are included on the Growth and Ministry plans. Upgrade to connect your own domain.', 'kingdom-saas' ) );
		}
		$domain = self::normalize( $raw );
		$base   = strtolower( ks_get( 'base_domain' ) );
		if ( ! preg_match( '/^(?=.{4,190}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}$/', $domain ) ) {
			return new WP_Error( 'ks_domain', __( 'Please enter a domain like yourchurch.org or www.yourchurch.org.', 'kingdom-saas' ) );
		}
		if ( $domain === $base || substr( $domain, -strlen( '.' . $base ) ) === '.' . $base ) {
			return new WP_Error( 'ks_domain', __( 'That is a platform address. Please enter a domain you own.', 'kingdom-saas' ) );
		}
		foreach ( array_filter( array_map( 'strtolower', array( ks_get( 'cname_target' ), defined( 'DOMAIN_CURRENT_SITE' ) ? DOMAIN_CURRENT_SITE : '' ) ) ) as $own ) {
			if ( $domain === $own || preg_replace( '/^www\./', '', $domain ) === preg_replace( '/^www\./', '', $own ) ) {
				return new WP_Error( 'ks_domain', __( 'That is a platform address. Please enter a domain you own.', 'kingdom-saas' ) );
			}
		}
		if ( ! ks_rate_limit( 'domain_add_' . (int) $blog_id, 10, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'ks_domain', __( 'Too many attempts. Please try again in an hour.', 'kingdom-saas' ) );
		}
		$bare = preg_replace( '/^www\./', '', $domain );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, blog_id, status FROM ' . ks_table( 'domains' ) . ' WHERE domain IN (%s, %s)', $bare, 'www.' . $bare ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $rows as $row ) {
			if ( (int) $row->blog_id === (int) $blog_id ) {
				continue;
			}
			if ( 'verified' === $row->status ) {
				return new WP_Error( 'ks_domain', __( 'That domain is already connected to another church.', 'kingdom-saas' ) );
			}
			// Someone else's unverified claim never blocks the real owner: verification needs the
			// DNS TXT record, which only the domain's owner can publish.
			$wpdb->delete( ks_table( 'domains' ), array( 'id' => (int) $row->id ) );
		}
		if ( ! $rows && domain_exists( $domain, '/' ) ) {
			return new WP_Error( 'ks_domain', __( 'That domain is already connected to another church.', 'kingdom-saas' ) );
		}
		// One custom domain per church: replace any previous one.
		foreach ( self::for_site( $blog_id ) as $old ) {
			self::remove( $old->id );
		}
		$wpdb->insert(
			ks_table( 'domains' ),
			array(
				'blog_id'    => $blog_id,
				'domain'     => $domain,
				'token'      => wp_generate_password( 32, false, false ),
				'status'     => 'pending',
				'created_at' => current_time( 'mysql', true ),
			)
		);
		return self::get( $wpdb->insert_id );
	}

	/**
	 * DNS records the church needs to add.
	 *
	 * @param object $d Domain row.
	 * @return array Rows of array( type, host, value ).
	 */
	public static function records( $d ) {
		$target = ks_get( 'cname_target' ) ? ks_get( 'cname_target' ) : ks_get( 'base_domain' );
		$apex   = 0 === strpos( $d->domain, 'www.' ) ? substr( $d->domain, 4 ) : $d->domain;
		$is_sub = substr_count( $apex, '.' ) > 1 && 0 !== strpos( $d->domain, 'www.' );
		$rows   = array();
		if ( $is_sub || 0 === strpos( $d->domain, 'www.' ) ) {
			$rows[] = array( 'CNAME', $d->domain, $target );
		} elseif ( ks_get( 'server_ip' ) ) {
			$rows[] = array( 'A', $d->domain, ks_get( 'server_ip' ) );
			$rows[] = array( 'CNAME', 'www.' . $d->domain, $target );
		} else {
			$rows[] = array( 'CNAME / ALIAS', $d->domain, $target );
		}
		$rows[] = array( 'TXT', '_ks-verify.' . $apex, $d->token );
		return $rows;
	}

	/**
	 * Check DNS and connect the domain when it is ready.
	 *
	 * @param int $id Domain ID.
	 * @return true|WP_Error
	 */
	public static function verify( $id ) {
		global $wpdb;
		$d = self::get( $id );
		if ( ! $d ) {
			return new WP_Error( 'ks_domain', __( 'Domain not found.', 'kingdom-saas' ) );
		}
		if ( ! KS_Tenants::feature( 'custom_domain', $d->blog_id ) ) {
			return new WP_Error( 'ks_plan', __( 'Your plan does not include custom domains.', 'kingdom-saas' ) );
		}
		// Ownership (TXT token) is always required, and the domain must really open this WordPress:
		// DNS alone is not enough on shared hosting, where the domain must also be added to the
		// hosting account (parked domain) before it serves anything.
		$owner = self::txt_check( $d );
		$reach = $owner && self::http_check( $d );
		$wpdb->update( ks_table( 'domains' ), array( 'checked_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
		if ( ! $owner || ( ! $reach && ! self::points_here( $d->domain ) ) ) {
			$msg = __( 'We could not see both DNS records yet (the TXT record and the address pointing to us). DNS changes can take up to 24 hours — we check again automatically.', 'kingdom-saas' );
			self::step_back( $d );
			$wpdb->update( ks_table( 'domains' ), array( 'status' => 'pending', 'last_error' => $msg ), array( 'id' => $id ) );
			return new WP_Error( 'ks_dns', $msg );
		}
		if ( ! $reach ) {
			$msg = __( 'DNS is correct. The platform is now adding the domain to its hosting — this usually takes less than a day, and the domain connects automatically.', 'kingdom-saas' );
			self::step_back( $d );
			$wpdb->update( ks_table( 'domains' ), array( 'status' => 'pending', 'last_error' => $msg ), array( 'id' => $id ) );
			self::tell_platform( $d, 'hosting' );
			return new WP_Error( 'ks_hosting', $msg );
		}
		// Owned and reaching us: verified (so a TLS proxy may issue its certificate).
		$wpdb->update( ks_table( 'domains' ), array( 'status' => 'verified', 'last_error' => '', 'verified_at' => $d->verified_at ? $d->verified_at : current_time( 'mysql', true ) ), array( 'id' => $id ) );
		if ( 'https' === ks_scheme() && ! self::https_check( $d ) ) {
			$msg = __( 'Domain connected — waiting for its SSL certificate (https). Your platform address keeps working until then; we switch automatically.', 'kingdom-saas' );
			self::step_back( $d );
			$wpdb->update( ks_table( 'domains' ), array( 'last_error' => $msg ), array( 'id' => $id ) );
			self::tell_platform( $d, 'ssl' );
			return new WP_Error( 'ks_ssl', $msg );
		}
		if ( (int) $d->is_primary ) {
			return true;
		}
		self::make_primary( $id );
		$sub = KS_Tenants::get( $d->blog_id );
		if ( $sub ) {
			KS_Email::owner(
				$sub,
				/* translators: %s: domain */
				sprintf( __( '%s is now connected', 'kingdom-saas' ), $d->domain ),
				/* translators: %s: domain */
				'<p>' . esc_html( sprintf( __( 'Your website now opens at %s. Your old address redirects there automatically.', 'kingdom-saas' ), $d->domain ) ) . '</p>',
				array( __( 'Visit your website', 'kingdom-saas' ), ks_scheme() . '://' . $d->domain )
			);
		}
		return true;
	}

	/**
	 * Does the domain already reach this server?
	 *
	 * @param object $d Domain row.
	 * @return bool
	 */
	private static function http_check( $d ) {
		foreach ( array( 'http', 'https' ) as $scheme ) {
			$res = wp_safe_remote_get( $scheme . '://' . $d->domain . '/.well-known/ks-domain-check', array( 'timeout' => 10, 'redirection' => 0, 'sslverify' => false ) );
			if ( ! is_wp_error( $res ) && trim( wp_remote_retrieve_body( $res ) ) === $d->token ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * What a domain is waiting for from the platform owner, if anything.
	 *
	 * @param object $d Domain row.
	 * @return string hosting|ssl|'' .
	 */
	public static function waiting_on_platform( $d ) {
		if ( (int) $d->is_primary ) {
			return '';
		}
		$sent = (array) get_site_option( 'ks_domain_notices', array() );
		$step = 'verified' === $d->status ? 'ssl' : 'hosting';
		return isset( $sent[ $d->id . ':' . $d->domain . ':' . $step ] ) ? $step : '';
	}

	/**
	 * A main-address domain that stopped working: put the church back on its platform address
	 * so visitors are not sent to a domain that does not open.
	 *
	 * @param object $d Domain row.
	 */
	private static function step_back( $d ) {
		global $wpdb;
		if ( (int) $d->is_primary ) {
			self::revert( $d->blog_id );
			$wpdb->update( ks_table( 'domains' ), array( 'is_primary' => 0 ), array( 'id' => (int) $d->id ) );
		}
	}

	/**
	 * Does the domain open this WordPress over https with a valid certificate?
	 *
	 * @param object $d Domain row.
	 * @return bool
	 */
	private static function https_check( $d ) {
		$res = wp_safe_remote_get( 'https://' . $d->domain . '/.well-known/ks-domain-check', array( 'timeout' => 10, 'redirection' => 0, 'sslverify' => true ) );
		return ! is_wp_error( $res ) && trim( wp_remote_retrieve_body( $res ) ) === $d->token;
	}

	/**
	 * Email the platform owner (once per domain and step) when a domain needs adding to the
	 * hosting account or its SSL certificate.
	 *
	 * @param object $d    Domain row.
	 * @param string $step hosting|ssl.
	 */
	private static function tell_platform( $d, $step ) {
		$sent = (array) get_site_option( 'ks_domain_notices', array() );
		$key  = $d->id . ':' . $d->domain . ':' . $step;
		if ( isset( $sent[ $key ] ) ) {
			return;
		}
		$sent[ $key ] = time();
		update_site_option( 'ks_domain_notices', array_slice( $sent, -200, null, true ) );
		$sub    = KS_Tenants::get( $d->blog_id );
		$church = $sub ? $sub->church_name : '#' . $d->blog_id;
		$bare   = preg_replace( '/^www\./', '', $d->domain );
		if ( 'hosting' === $step ) {
			/* translators: 1: domain, 2: church */
			$subject = sprintf( __( 'Action needed: add %1$s to your hosting (%2$s)', 'kingdom-saas' ), $d->domain, $church );
			/* translators: 1: church, 2: domain */
			$body = '<p>' . esc_html( sprintf( __( '%1$s pointed %2$s to your platform and the DNS is correct, but your hosting does not serve that domain yet.', 'kingdom-saas' ), $church, $d->domain ) ) . '</p>'
				/* translators: 1: domain, 2: www domain */
				. '<p>' . esc_html( sprintf( __( 'Add %1$s as a parked (alias) domain of your platform, pointing to the same folder, then install the free SSL certificate for %1$s and %2$s. Hostinger: hPanel → Websites → your platform → Domains → Parked domains, then Security → SSL.', 'kingdom-saas' ), $bare, 'www.' . $bare ) ) . '</p>'
				. '<p>' . esc_html__( 'The church keeps its platform address until then, and the domain connects automatically within 12 hours (or press Check DNS in Platform → Domains).', 'kingdom-saas' ) . '</p>';
		} else {
			/* translators: 1: domain, 2: church */
			$subject = sprintf( __( 'Action needed: SSL certificate for %1$s (%2$s)', 'kingdom-saas' ), $d->domain, $church );
			/* translators: 1: domain, 2: www domain */
			$body = '<p>' . esc_html( sprintf( __( '%1$s reaches your platform, but https does not work for it yet. Install the free SSL certificate for %1$s and %2$s (Hostinger: hPanel → Security → SSL).', 'kingdom-saas' ), $bare, 'www.' . $bare ) ) . '</p>'
				. '<p>' . esc_html__( 'The church keeps its platform address until https works, then switches automatically.', 'kingdom-saas' ) . '</p>';
		}
		KS_Email::send( get_site_option( 'admin_email' ), $subject, $body, array( __( 'Open Domains', 'kingdom-saas' ), ks_platform_url( 'platform/?tab=domains' ) ) );
	}

	/**
	 * Is the TXT token published?
	 *
	 * @param object $d Domain row.
	 * @return bool
	 */
	private static function txt_check( $d ) {
		$apex    = preg_replace( '/^www\./', '', $d->domain );
		$records = apply_filters( 'ks_dns_records', @dns_get_record( '_ks-verify.' . $apex, DNS_TXT ), '_ks-verify.' . $apex, DNS_TXT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		foreach ( (array) $records as $r ) {
			if ( isset( $r['txt'] ) && trim( $r['txt'] ) === $d->token ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Does the domain's CNAME or A record point at the platform?
	 *
	 * @param string $domain Domain.
	 * @return bool
	 */
	private static function points_here( $domain ) {
		$target  = strtolower( ks_get( 'cname_target' ) ? ks_get( 'cname_target' ) : ks_get( 'base_domain' ) );
		$ip      = ks_get( 'server_ip' );
		$records = apply_filters( 'ks_dns_records', @dns_get_record( $domain, DNS_CNAME | DNS_A ), $domain, DNS_CNAME | DNS_A ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		foreach ( (array) $records as $r ) {
			if ( ( isset( $r['target'] ) && rtrim( strtolower( $r['target'] ), '.' ) === $target ) || ( $ip && isset( $r['ip'] ) && $r['ip'] === $ip ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Use the domain as the site's main address.
	 *
	 * @param int $id Domain ID.
	 */
	public static function make_primary( $id ) {
		global $wpdb;
		$d = self::get( $id );
		if ( ! $d || 'verified' !== $d->status ) {
			return;
		}
		self::set_site_domain( $d->blog_id, $d->domain );
		$wpdb->update( ks_table( 'domains' ), array( 'is_primary' => 0 ), array( 'blog_id' => $d->blog_id ) );
		$wpdb->update( ks_table( 'domains' ), array( 'is_primary' => 1 ), array( 'id' => $id ) );
	}

	/**
	 * Remove a domain (the church goes back to its platform address).
	 *
	 * @param int $id Domain ID.
	 */
	public static function remove( $id ) {
		global $wpdb;
		$d = self::get( $id );
		if ( ! $d ) {
			return;
		}
		if ( $d->is_primary ) {
			self::revert( $d->blog_id );
		}
		$wpdb->delete( ks_table( 'domains' ), array( 'id' => $id ) );
	}

	/**
	 * Point a site back at its platform address (subdomain or subfolder).
	 *
	 * @param int $blog_id Site.
	 */
	public static function revert( $blog_id ) {
		$sub = KS_Tenants::get( $blog_id );
		if ( $sub && $sub->subdomain ) {
			self::set_site_domain( $blog_id, $sub->subdomain );
		}
	}

	/**
	 * Disconnect custom domains when the plan no longer includes them.
	 *
	 * @param int $blog_id Site.
	 */
	public static function enforce_plan( $blog_id ) {
		global $wpdb;
		if ( KS_Tenants::feature( 'custom_domain', $blog_id ) ) {
			return;
		}
		foreach ( self::for_site( $blog_id ) as $d ) {
			if ( $d->is_primary ) {
				self::revert( $blog_id );
			}
			$wpdb->update( ks_table( 'domains' ), array( 'status' => 'pending', 'is_primary' => 0, 'last_error' => __( 'Your current plan does not include custom domains.', 'kingdom-saas' ) ), array( 'id' => $d->id ) );
		}
	}

	/**
	 * Change a site's address (wp_blogs row and its home/siteurl).
	 *
	 * @param int    $blog_id Site.
	 * @param string $domain  Domain.
	 */
	private static function set_site_domain( $blog_id, $domain ) {
		// A custom domain, or the church's platform address ("church.platform.com" or "platform.com/church").
		list( $host, $path ) = ks_address_parts( $domain );
		wp_update_site( $blog_id, array( 'domain' => $host, 'path' => $path ) );
		$url = ks_scheme() . '://' . $host . rtrim( $path, '/' );
		update_blog_option( $blog_id, 'home', $url );
		update_blog_option( $blog_id, 'siteurl', $url );
		clean_blog_cache( $blog_id );
	}

	/**
	 * Daily: try pending domains again.
	 */
	public static function recheck_pending() {
		global $wpdb;
		foreach ( self::all() as $d ) {
			// Pending claims, and verified domains still waiting for https before they switch.
			if ( 'pending' !== $d->status && ! ( 'verified' === $d->status && ! (int) $d->is_primary ) ) {
				continue;
			}
			// Claims whose owner never proved it (TXT record) within 7 days are dropped.
			if ( 'pending' === $d->status && strtotime( $d->created_at . ' UTC' ) < time() - 7 * DAY_IN_SECONDS && ! self::txt_check( $d ) ) {
				$wpdb->delete( ks_table( 'domains' ), array( 'id' => (int) $d->id ) );
				continue;
			}
			if ( KS_Tenants::feature( 'custom_domain', $d->blog_id ) ) {
				self::verify( $d->id );
			}
		}
	}

	/**
	 * May the TLS proxy issue a certificate for this host? (Caddy on-demand TLS "ask".)
	 *
	 * @param string $host Host.
	 * @return bool
	 */
	public static function tls_allowed( $host ) {
		global $wpdb;
		$host = self::normalize( $host );
		if ( ! $host ) {
			return false;
		}
		$bare = preg_replace( '/^www\./', '', $host );
		// Certificates only for verified domains (and the platform's own church addresses).
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . ks_table( 'domains' ) . " WHERE status = 'verified' AND domain IN (%s, %s, %s)", $host, $bare, 'www.' . $bare ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return true;
		}
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = %s AND deleted = 0", $host ) ) ) {
			return true;
		}
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . ks_table( 'subscriptions' ) . ' WHERE subdomain = %s', $host ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
