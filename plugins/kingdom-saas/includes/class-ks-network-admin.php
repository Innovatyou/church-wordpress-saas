<?php
/**
 * Network Admin → Kingdom Cloud: links to the platform dashboard and a setup check that says,
 * in plain words, what is still missing (plugins, theme, sunrise.php, wildcard DNS, cache…).
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Network admin page.
 */
class KS_Network_Admin {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'network_admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 35 );
		add_action( 'admin_init', array( __CLASS__, 'refresh_sunrise' ) );
		add_action( 'admin_post_ks_switch_mode', array( __CLASS__, 'switch_mode' ) );
	}

	/**
	 * Number of church sites (everything except the main platform site).
	 *
	 * @return int
	 */
	public static function church_count() {
		return max( 0, (int) get_sites( array( 'count' => true, 'network_id' => get_current_network_id() ) ) - 1 );
	}

	/**
	 * Switch church addresses between subdomains and subfolders (only before any church exists).
	 */
	public static function switch_mode() {
		if ( ! current_user_can( 'manage_network' ) || ! check_admin_referer( 'ks_switch_mode' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'kingdom-saas' ), 403 );
		}
		$back = network_admin_url( 'admin.php?page=kingdom-cloud' );
		if ( self::church_count() > 0 ) {
			set_site_transient( 'ks_switch_result', array( 'error' => __( 'Churches have already signed up, so the address style can no longer be switched here.', 'kingdom-saas' ) ), 600 );
			wp_safe_redirect( $back );
			exit;
		}
		$subdomain = 'subdomain' === sanitize_key( wp_unslash( $_POST['ks_mode'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$config    = KS_Network_Setup::config_path();
		$stamp     = gmdate( 'Ymd-His' );
		$manual    = array();
		@copy( $config, $config . '.kc-backup-' . $stamp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$current = (string) file_get_contents( $config ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$patched = KS_Network_Setup::patch_config( $current, $subdomain );
		if ( false === $patched || ! wp_is_writable( $config ) || false === file_put_contents( $config, $patched ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$manual['wp-config.php — replace the Kingdom Cloud / MULTISITE lines with'] = KS_Network_Setup::config_block( $subdomain );
		}
		if ( ! KS_Network_Setup::is_nginx() ) {
			$ht = ABSPATH . '.htaccess';
			if ( file_exists( $ht ) ) {
				@copy( $ht, $ht . '.kc-backup-' . $stamp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			$ht_new = KS_Network_Setup::patch_htaccess( file_exists( $ht ) ? (string) file_get_contents( $ht ) : '', $subdomain ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( ! ( file_exists( $ht ) ? wp_is_writable( $ht ) : wp_is_writable( ABSPATH ) ) || false === file_put_contents( $ht, $ht_new ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$manual['.htaccess — replace the whole file with'] = $ht_new;
			}
		}
		update_site_option( 'subdomain_install', $subdomain ? 1 : 0 );
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $config, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		set_site_transient( 'ks_switch_result', $manual ? array( 'manual' => $manual ) : array( 'done' => $subdomain ? 'subdomain' : 'subfolder' ), 600 );
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Keep wp-content/sunrise.php in step with the plugin after updates (only our own file).
	 */
	public static function refresh_sunrise() {
		if ( ! is_super_admin() ) {
			return;
		}
		$live   = WP_CONTENT_DIR . '/sunrise.php';
		$bundle = KS_PATH . 'deploy/sunrise.php';
		if ( ! file_exists( $live ) || ! wp_is_writable( $live ) || md5_file( $live ) === md5_file( $bundle ) ) {
			return;
		}
		if ( false !== strpos( (string) file_get_contents( $live, false, null, 0, 400 ), 'Kingdom Cloud — custom domain helper' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			@copy( $bundle, $live ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Menu item.
	 */
	public static function menu() {
		add_menu_page( __( 'Kingdom Cloud', 'kingdom-saas' ), __( 'Kingdom Cloud', 'kingdom-saas' ), 'manage_network', 'kingdom-cloud', array( __CLASS__, 'render' ), 'dashicons-cloud', 3 );
	}

	/**
	 * "Platform dashboard" link in the admin bar for super admins.
	 *
	 * @param WP_Admin_Bar $bar Admin bar.
	 */
	public static function admin_bar( $bar ) {
		if ( ! is_super_admin() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'ks-platform',
				'title' => __( 'Platform dashboard', 'kingdom-saas' ),
				'href'  => ks_platform_url( 'platform/' ),
			)
		);
	}

	/**
	 * Setup checks: array of array( ok|warn|fail, title, help ).
	 *
	 * @return array
	 */
	public static function checks() {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$out  = array();
		$base = strtolower( (string) ks_get( 'base_domain' ) );

		$out[] = is_subdomain_install()
			? array( 'ok', sprintf( /* translators: %s: example address */ __( 'Multisite is on — church addresses like %s', 'kingdom-saas' ), ks_address( 'gracechapel' ) ), '' )
			: array( 'ok', sprintf( /* translators: %s: example address */ __( 'Multisite is on — church addresses like %s (subfolders, no wildcard DNS needed)', 'kingdom-saas' ), ks_address( 'gracechapel' ) ), '' );

		$out[] = is_plugin_active_for_network( plugin_basename( KS_FILE ) )
			? array( 'ok', __( 'Kingdom Cloud is network-activated', 'kingdom-saas' ), '' )
			: array( 'fail', __( 'Kingdom Cloud is not network-activated', 'kingdom-saas' ), __( 'Network Admin → Plugins → Kingdom Cloud → Network Activate. Activating it on one site only is not enough.', 'kingdom-saas' ) );

		$out[] = is_plugin_active_for_network( 'kingdom-church-suite/kingdom-church-suite.php' )
			? array( 'ok', __( 'Kingdom Church Suite is network-activated', 'kingdom-saas' ), '' )
			: array( 'fail', __( 'Kingdom Church Suite is not network-activated', 'kingdom-saas' ), __( 'Network Admin → Plugins → Kingdom Church Suite → Network Activate, so every church gets partnerships, giving and Church Admin.', 'kingdom-saas' ) );

		$allowed = array_keys( (array) WP_Theme::get_allowed_on_network() );
		$out[]   = in_array( 'kingdom', $allowed, true )
			? array( 'ok', __( 'Kingdom Church theme is enabled for the network', 'kingdom-saas' ), '' )
			: array( 'fail', __( 'Kingdom Church theme is not enabled for the network', 'kingdom-saas' ), __( 'Network Admin → Themes → Kingdom Church → Network Enable. New church sites use it automatically.', 'kingdom-saas' ) );

		$out[] = defined( 'SUNRISE' ) && SUNRISE && file_exists( WP_CONTENT_DIR . '/sunrise.php' )
			? array( 'ok', __( 'Custom domains are set up (sunrise.php)', 'kingdom-saas' ), '' )
			: array( 'warn', __( 'Custom domains are not set up yet', 'kingdom-saas' ), __( 'Copy wp-content/plugins/kingdom-saas/deploy/sunrise.php to wp-content/sunrise.php and add define( \'SUNRISE\', true ); to wp-config.php.', 'kingdom-saas' ) );

		// Church subdomains: DNS, then whether the host really sends them to WordPress, then HTTPS.
		if ( $base && is_subdomain_install() && ! preg_match( '/(^|\.)localhost$/', $base ) ) {
			$probe = 'ks-check-' . strtolower( wp_generate_password( 8, false, false ) ) . '.' . $base;
			$dns   = function_exists( 'gethostbyname' ) && gethostbyname( $probe ) !== $probe;
			$http  = wp_remote_get( 'http://' . $probe . '/.well-known/ks-probe', array( 'timeout' => 10, 'sslverify' => false, 'redirection' => 3 ) );
			$works = ! is_wp_error( $http ) && false !== strpos( (string) wp_remote_retrieve_body( $http ), 'ks-probe-ok' );
			if ( $works ) {
				$out[] = array( 'ok', __( 'Church subdomains reach this WordPress', 'kingdom-saas' ), '' );
				$tls   = wp_remote_get( 'https://' . $probe . '/.well-known/ks-probe', array( 'timeout' => 10, 'sslverify' => true, 'redirection' => 0 ) );
				$out[] = ! is_wp_error( $tls ) && false !== strpos( (string) wp_remote_retrieve_body( $tls ), 'ks-probe-ok' )
					? array( 'ok', __( 'Church subdomains have a valid HTTPS certificate', 'kingdom-saas' ), '' )
					: array( 'warn', __( 'Church subdomains have no valid HTTPS certificate yet', 'kingdom-saas' ), sprintf( /* translators: %s: domain */ __( 'Your SSL covers %s and www only. Use a certificate that also covers *.%s, for example free Cloudflare (proxied "*" DNS record, SSL mode "Full"), or untick HTTPS in Platform → Settings until then.', 'kingdom-saas' ), $base, $base ) );
			} elseif ( $dns ) {
				$out[] = array( 'fail', __( 'Church subdomains reach your server, but not this WordPress', 'kingdom-saas' ), sprintf( /* translators: %s: domain */ __( 'DNS is correct, but your hosting does not send every subdomain (*.%s) to this WordPress. Shared hosting often cannot. Switch church addresses to subfolders below (no wildcard needed), or ask your host for a wildcard subdomain, or use a VPS.', 'kingdom-saas' ), $base ) );
			} else {
				$out[] = array( 'fail', __( 'Church subdomains do not reach this server yet', 'kingdom-saas' ), sprintf( /* translators: %s: domain */ __( 'Add a DNS A record named "*" (for *.%s) pointing to your server IP. DNS can take a few hours.', 'kingdom-saas' ), $base ) );
			}
		}

		// The platform home page should be the sales landing page (not a cached church page).
		$res  = wp_remote_get( add_query_arg( 'ks_check', wp_generate_password( 6, false, false ), ks_platform_url() ), array( 'timeout' => 12, 'sslverify' => false, 'redirection' => 3 ) );
		$body = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
		if ( is_wp_error( $res ) ) {
			$out[] = array( 'warn', __( 'Could not load your home page to check it', 'kingdom-saas' ), $res->get_error_message() );
		} elseif ( false !== strpos( $body, 'ks--landing' ) ) {
			$out[] = array( 'ok', __( 'Your home page shows the platform landing page', 'kingdom-saas' ), '' );
		} else {
			$out[] = array( 'fail', __( 'Your home page is not showing the platform landing page', 'kingdom-saas' ), __( 'Usually an old cached copy: purge your cache (LiteSpeed Cache → Purge All, and your host\'s cache, e.g. hPanel → Cache Manager). Also check Kingdom Cloud is network-activated above.', 'kingdom-saas' ) );
		}

		$keys  = ks_get( 'paystack_secret' ) || ks_get( 'stripe_secret' ) || KS_Bank::available();
		$out[] = $keys
			? array( 'ok', __( 'A payment method is set up', 'kingdom-saas' ), '' )
			: array( 'warn', __( 'No payment method yet', 'kingdom-saas' ), __( 'Add Paystack or Stripe keys, or your bank accounts (Platform → Settings → Bank transfer).', 'kingdom-saas' ) );
		if ( ks_test_mode() ) {
			$out[] = array( 'warn', __( 'Test mode is on', 'kingdom-saas' ), __( 'Churches can "pay" with a fake test payment. Turn it off in Platform → Settings before going live.', 'kingdom-saas' ) );
		}
		$https = 'https' === wp_parse_url( network_home_url(), PHP_URL_SCHEME );
		if ( $https ) {
			$out[] = array( 'ok', __( 'HTTPS is on', 'kingdom-saas' ), '' );
		} else {
			$out[] = array( 'warn', __( 'HTTPS is off', 'kingdom-saas' ), __( 'Payments and the church app need HTTPS. Turn on SSL in your hosting panel (also for *.yourdomain), then change the site address to https:// in Network Admin → Sites → main site → Settings.', 'kingdom-saas' ) );
			if ( '1' === (string) ks_get( 'https' ) ) {
				$out[] = array( 'warn', __( 'New church sites are set to open with https://', 'kingdom-saas' ), __( 'Until SSL works for church subdomains, untick "HTTPS" in Platform → Settings, or new church sites will not open.', 'kingdom-saas' ) );
			}
		}
		return $out;
	}

	/**
	 * "Church website addresses" box with the switch button.
	 */
	private static function render_switch() {
		$sub     = is_subdomain_install();
		$base    = strtolower( (string) ks_get( 'base_domain' ) );
		$churches = self::church_count();
		?>
		<h2><?php esc_html_e( 'Church website addresses', 'kingdom-saas' ); ?></h2>
		<div class="card" style="max-width:860px">
			<p><?php echo esc_html( sprintf( /* translators: %s: example address */ __( 'New churches get addresses like %s.', 'kingdom-saas' ), ks_address( 'gracechapel' ) ) ); ?></p>
			<?php if ( $churches ) : ?>
				<p class="description"><?php echo esc_html( sprintf( /* translators: %d: number of churches */ _n( '%d church has signed up, so the address style is fixed. Churches can still connect their own domains.', '%d churches have signed up, so the address style is fixed. Churches can still connect their own domains.', $churches, 'kingdom-saas' ), $churches ) ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ks_switch_mode">
					<input type="hidden" name="ks_mode" value="<?php echo $sub ? 'subfolder' : 'subdomain'; ?>">
					<?php wp_nonce_field( 'ks_switch_mode' ); ?>
					<?php if ( $sub ) : ?>
						<p><?php echo esc_html( sprintf( /* translators: %s: example address */ __( 'Shared hosting (Hostinger, cPanel, Plesk) usually cannot send every subdomain to WordPress. Subfolder addresses like %s work on any hosting, need no extra DNS and are covered by your existing SSL.', 'kingdom-saas' ), $base . '/gracechapel' ) ); ?></p>
						<p><button class="button button-primary"><?php echo esc_html( sprintf( /* translators: %s: example address */ __( 'Switch to subfolder addresses (%s)', 'kingdom-saas' ), $base . '/gracechapel' ) ); ?></button></p>
					<?php else : ?>
						<p><?php esc_html_e( 'Subdomain addresses need a server that sends every *.yourdomain to WordPress (a VPS, or hosting with wildcard subdomains), a "*" DNS record and SSL for *.yourdomain.', 'kingdom-saas' ); ?></p>
						<p><button class="button"><?php echo esc_html( sprintf( /* translators: %s: example address */ __( 'Switch to subdomain addresses (%s)', 'kingdom-saas' ), 'gracechapel.' . $base ) ); ?></button></p>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'wp-config.php and .htaccess are backed up and updated for you.', 'kingdom-saas' ); ?></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Page.
	 */
	public static function render() {
		$checks = self::checks();
		$icons  = array(
			'ok'   => array( 'dashicons-yes-alt', '#00a32a', __( 'OK', 'kingdom-saas' ) ),
			'warn' => array( 'dashicons-warning', '#dba617', __( 'Check', 'kingdom-saas' ) ),
			'fail' => array( 'dashicons-dismiss', '#d63638', __( 'Needs fixing', 'kingdom-saas' ) ),
		);
		$switched = get_site_transient( 'ks_switch_result' );
		delete_site_transient( 'ks_switch_result' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Kingdom Cloud', 'kingdom-saas' ); ?></h1>
			<?php if ( ! empty( $switched['done'] ) ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( sprintf( /* translators: %s: example address */ __( 'Done. New churches now get addresses like %s.', 'kingdom-saas' ), ks_address( 'gracechapel' ) ) ); ?></p></div>
			<?php elseif ( ! empty( $switched['error'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $switched['error'] ); ?></p></div>
			<?php elseif ( ! empty( $switched['manual'] ) ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Almost done: these files could not be written. Paste the text below and save.', 'kingdom-saas' ); ?></p></div>
				<?php foreach ( $switched['manual'] as $file => $text ) : ?>
					<h2><?php echo esc_html( $file ); ?></h2>
					<textarea class="large-text code" rows="<?php echo (int) min( 22, substr_count( $text, "\n" ) + 2 ); ?>" readonly onclick="this.select()"><?php echo esc_textarea( $text ); ?></textarea>
				<?php endforeach; ?>
			<?php endif; ?>
			<p><?php esc_html_e( 'Your platform is managed from its own dashboard on the website (not inside wp-admin): churches, plans and prices, payments and settings.', 'kingdom-saas' ); ?></p>
			<p>
				<a class="button button-primary button-hero" href="<?php echo esc_url( ks_platform_url( 'platform/' ) ); ?>"><?php esc_html_e( 'Open platform dashboard', 'kingdom-saas' ); ?></a>
				<a class="button button-hero" href="<?php echo esc_url( add_query_arg( 'tab', 'settings', ks_platform_url( 'platform/' ) ) ); ?>"><?php esc_html_e( 'Platform settings', 'kingdom-saas' ); ?></a>
				<a class="button button-hero" href="<?php echo esc_url( ks_platform_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View landing page', 'kingdom-saas' ); ?></a>
			</p>
			<h2><?php esc_html_e( 'Setup check', 'kingdom-saas' ); ?></h2>
			<table class="widefat striped" style="max-width:900px">
				<tbody>
					<?php foreach ( $checks as $c ) : ?>
						<tr>
							<td style="width:2em"><span class="dashicons <?php echo esc_attr( $icons[ $c[0] ][0] ); ?>" style="color:<?php echo esc_attr( $icons[ $c[0] ][1] ); ?>" title="<?php echo esc_attr( $icons[ $c[0] ][2] ); ?>"></span><span class="screen-reader-text"><?php echo esc_html( $icons[ $c[0] ][2] ); ?></span></td>
							<td><strong><?php echo esc_html( $c[1] ); ?></strong><?php if ( $c[2] ) : ?><br><span class="description"><?php echo esc_html( $c[2] ); ?></span><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Reload this page after each change to check again.', 'kingdom-saas' ); ?></p>
			<?php self::render_switch(); ?>
		</div>
		<?php
	}
}
