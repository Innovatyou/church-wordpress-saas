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
			? array( 'ok', __( 'Multisite with subdomains is on', 'kingdom-saas' ), '' )
			: array( 'fail', __( 'Multisite must use subdomains', 'kingdom-saas' ), __( 'In wp-config.php, SUBDOMAIN_INSTALL must be true.', 'kingdom-saas' ) );

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

		// Wildcard DNS: a random subdomain should resolve to the same server as the platform.
		if ( $base && function_exists( 'gethostbyname' ) && ! preg_match( '/(^|\.)localhost$/', $base ) ) {
			$probe = 'ks-check-' . strtolower( wp_generate_password( 8, false, false ) ) . '.' . $base;
			$ip    = gethostbyname( $probe );
			$main  = gethostbyname( $base );
			$out[] = $ip !== $probe && $ip === $main
				? array( 'ok', __( 'Church subdomains reach this server (wildcard DNS)', 'kingdom-saas' ), '' )
				: array( 'fail', __( 'Church subdomains do not reach this server yet', 'kingdom-saas' ), sprintf( /* translators: %s: domain */ __( 'Add a DNS A record for *.%s pointing to your server IP, and on shared hosting a "*" subdomain pointing to your WordPress folder. DNS can take a few hours.', 'kingdom-saas' ), $base ) );
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

		$keys  = ks_get( 'paystack_secret' ) || ks_get( 'stripe_secret' ) || ks_get( 'bank_details' );
		$out[] = $keys
			? array( 'ok', __( 'A payment method is set up', 'kingdom-saas' ), '' )
			: array( 'warn', __( 'No payment method yet', 'kingdom-saas' ), __( 'Add Paystack or Stripe keys, or bank details, in Platform → Settings.', 'kingdom-saas' ) );
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
	 * Page.
	 */
	public static function render() {
		$checks = self::checks();
		$icons  = array(
			'ok'   => array( 'dashicons-yes-alt', '#00a32a', __( 'OK', 'kingdom-saas' ) ),
			'warn' => array( 'dashicons-warning', '#dba617', __( 'Check', 'kingdom-saas' ) ),
			'fail' => array( 'dashicons-dismiss', '#d63638', __( 'Needs fixing', 'kingdom-saas' ) ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Kingdom Cloud', 'kingdom-saas' ); ?></h1>
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
		</div>
		<?php
	}
}
