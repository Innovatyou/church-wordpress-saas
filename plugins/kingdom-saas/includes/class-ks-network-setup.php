<?php
/**
 * One-click platform setup on a normal (single) WordPress site.
 *
 * Kingdom Cloud needs WordPress Multisite (church subfolders or subdomains). Instead of editing wp-config.php and
 * .htaccess by hand, Tools → Kingdom Cloud setup does it all, the same way WordPress's own
 * Network Setup and `wp core multisite-convert` do:
 *
 * 1. backs up wp-config.php and .htaccess;
 * 2. creates the network tables;
 * 3. writes the network lines to wp-config.php and the rewrite rules to .htaccess;
 * 4. installs sunrise.php (custom domains);
 * 5. network-activates Kingdom Cloud and Kingdom Church Suite and network-enables the theme.
 *
 * When a file cannot be written, the page shows the exact text to paste instead.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Single-site to platform conversion.
 */
class KS_Network_Setup {

	/**
	 * Constants the conversion manages in wp-config.php.
	 *
	 * @var string[]
	 */
	const CONSTANTS = array( 'WP_ALLOW_MULTISITE', 'MULTISITE', 'SUBDOMAIN_INSTALL', 'DOMAIN_CURRENT_SITE', 'PATH_CURRENT_SITE', 'SITE_ID_CURRENT_SITE', 'BLOG_ID_CURRENT_SITE', 'SUNRISE' );

	/**
	 * Hooks (only while the site is not yet a network).
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_ks_convert', array( __CLASS__, 'convert' ) );
	}

	/**
	 * Tools → Kingdom Cloud setup.
	 */
	public static function menu() {
		add_management_page( __( 'Kingdom Cloud setup', 'kingdom-saas' ), __( 'Kingdom Cloud setup', 'kingdom-saas' ), 'manage_options', 'kingdom-cloud-setup', array( __CLASS__, 'render' ) );
	}

	/**
	 * Point admins to the setup page.
	 */
	public static function notice() {
		if ( ! current_user_can( 'manage_options' ) || ( isset( $_GET['page'] ) && 'kingdom-cloud-setup' === $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Kingdom Cloud is almost ready.', 'kingdom-saas' ) . '</strong> ' . esc_html__( 'It needs to turn this site into a network of church websites. This takes one click.', 'kingdom-saas' ) . ' <a class="button button-primary" href="' . esc_url( admin_url( 'tools.php?page=kingdom-cloud-setup' ) ) . '">' . esc_html__( 'Set up my platform', 'kingdom-saas' ) . '</a></p></div>';
	}

	/**
	 * The platform's address: the site's host without "www." (church sites become name.domain).
	 *
	 * @return string
	 */
	public static function domain() {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		return preg_replace( '/^www\./', '', $host );
	}

	/**
	 * The wp-config.php in use (WordPress allows it one folder above).
	 *
	 * @return string
	 */
	public static function config_path() {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}
		return dirname( ABSPATH ) . '/wp-config.php';
	}

	/**
	 * Is the web server nginx (no .htaccess)?
	 *
	 * @return bool
	 */
	public static function is_nginx() {
		return false !== stripos( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' ), 'nginx' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}

	/**
	 * Checks before converting: array of array( ok|warn|fail, text ).
	 *
	 * @return array
	 */
	public static function checks() {
		$domain = self::domain();
		$port   = wp_parse_url( home_url(), PHP_URL_PORT );
		$path   = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		$out    = array();
		$real   = $domain && false !== strpos( $domain, '.' ) && ! filter_var( $domain, FILTER_VALIDATE_IP ) && ! preg_match( '/(^|\.)localhost$/', $domain );
		/* translators: %s: domain */
		$out[] = $real ? array( 'ok', sprintf( __( 'Platform address: %s', 'kingdom-saas' ), $domain ) ) : array( 'fail', __( 'The site must use a real domain (not localhost or an IP address). Set it under Settings → General.', 'kingdom-saas' ) );
		$out[] = $port ? array( 'fail', __( 'The site address must not include a port number (like :8080).', 'kingdom-saas' ) ) : array( 'ok', __( 'No port number in the address', 'kingdom-saas' ) );
		$out[] = '' === $path ? array( 'ok', __( 'WordPress is at the root of the domain', 'kingdom-saas' ) ) : array( 'fail', __( 'WordPress must be installed at the root of the domain (not in a folder) for church subdomains.', 'kingdom-saas' ) );
		$config = self::config_path();
		$out[]  = wp_is_writable( $config ) ? array( 'ok', __( 'wp-config.php can be updated automatically', 'kingdom-saas' ) ) : array( 'warn', __( 'wp-config.php cannot be written: you will be shown the lines to paste.', 'kingdom-saas' ) );
		if ( self::is_nginx() ) {
			$out[] = array( 'warn', __( 'nginx server: add the rules from deploy/nginx.conf to your server (there is no .htaccess).', 'kingdom-saas' ) );
		} else {
			$ht    = ABSPATH . '.htaccess';
			$out[] = ( file_exists( $ht ) ? wp_is_writable( $ht ) : wp_is_writable( ABSPATH ) ) ? array( 'ok', __( '.htaccess can be updated automatically', 'kingdom-saas' ) ) : array( 'warn', __( '.htaccess cannot be written: you will be shown the rules to paste.', 'kingdom-saas' ) );
		}
		$out[] = file_exists( WP_PLUGIN_DIR . '/kingdom-church-suite/kingdom-church-suite.php' ) ? array( 'ok', __( 'Kingdom Church Suite plugin is uploaded', 'kingdom-saas' ) ) : array( 'warn', __( 'Upload the Kingdom Church Suite plugin too (Plugins → Add New → Upload). You can do it after setup in Network Admin → Plugins.', 'kingdom-saas' ) );
		$out[] = self::theme_ok() ? array( 'ok', __( 'Kingdom Church theme is uploaded', 'kingdom-saas' ) ) : array( 'warn', __( 'Upload the Kingdom Church theme too (Appearance → Themes → Add New → Upload). You can do it after setup in Network Admin → Themes.', 'kingdom-saas' ) );
		return $out;
	}

	/**
	 * The lines for wp-config.php.
	 *
	 * @param bool $subdomain Church subdomains (true) or subfolders (false).
	 * @return string
	 */
	public static function config_block( $subdomain = true ) {
		$domain = self::domain();
		return "/* Kingdom Cloud network (added by Tools → Kingdom Cloud setup) */\n"
			. "define( 'WP_ALLOW_MULTISITE', true );\n"
			. "define( 'MULTISITE', true );\n"
			. "define( 'SUBDOMAIN_INSTALL', " . ( $subdomain ? 'true' : 'false' ) . " );\n"
			. "define( 'DOMAIN_CURRENT_SITE', '" . addslashes( $domain ) . "' );\n"
			. "define( 'PATH_CURRENT_SITE', '/' );\n"
			. "define( 'SITE_ID_CURRENT_SITE', 1 );\n"
			. "define( 'BLOG_ID_CURRENT_SITE', 1 );\n"
			. "define( 'SUNRISE', true );\n"
			. "/* End Kingdom Cloud network */\n";
	}

	/**
	 * The WordPress rewrite rules for a network.
	 *
	 * @param bool $subdomain Church subdomains (true) or subfolders (false).
	 * @return string[]
	 */
	public static function htaccess_rules( $subdomain = true ) {
		if ( ! $subdomain ) {
			// Subfolder networks: /church/wp-admin, /church/wp-content… are the main WordPress files.
			return array(
				'<IfModule mod_rewrite.c>',
				'RewriteEngine On',
				'RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]',
				'RewriteBase /',
				'RewriteRule ^index\.php$ - [L]',
				'',
				'# add a trailing slash to /wp-admin',
				'RewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin$ $1wp-admin/ [R=301,L]',
				'',
				'RewriteCond %{REQUEST_FILENAME} -f [OR]',
				'RewriteCond %{REQUEST_FILENAME} -d',
				'RewriteRule ^ - [L]',
				'RewriteRule ^([_0-9a-zA-Z-]+/)?(wp-(content|admin|includes).*) $2 [L]',
				'RewriteRule ^([_0-9a-zA-Z-]+/)?(.*\.php)$ $2 [L]',
				'RewriteRule . index.php [L]',
				'</IfModule>',
			);
		}
		return array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]',
			'RewriteBase /',
			'RewriteRule ^index\.php$ - [L]',
			'',
			'# add a trailing slash to /wp-admin',
			'RewriteRule ^wp-admin$ wp-admin/ [R=301,L]',
			'',
			'RewriteCond %{REQUEST_FILENAME} -f [OR]',
			'RewriteCond %{REQUEST_FILENAME} -d',
			'RewriteRule ^ - [L]',
			'RewriteRule ^(wp-(content|admin|includes).*) $1 [L]',
			'RewriteRule ^(.*\.php)$ $1 [L]',
			'RewriteRule . index.php [L]',
			'</IfModule>',
		);
	}

	/**
	 * wp-config.php text with the network block (old copies of the same constants removed).
	 *
	 * @param string $config    Current file.
	 * @param bool   $subdomain Church subdomains (true) or subfolders (false).
	 * @return string|false New file, or false when there is nowhere safe to insert.
	 */
	public static function patch_config( $config, $subdomain = true ) {
		// Remove an earlier block and any hand-added copies of the constants.
		$config = preg_replace( "#/\* Kingdom Cloud network.*?/\* End Kingdom Cloud network \*/\R?(\R)?#s", "", $config );
		foreach ( self::CONSTANTS as $c ) {
			$config = preg_replace( '/^[ \t]*define\(\s*[\'"]' . $c . '[\'"]\s*,[^;]*\);[^\r\n]*\R?/m', '', $config );
		}
		$block = self::config_block( $subdomain ) . "\n";
		if ( preg_match( '#^[ \t]*/\*.*That.s all, stop editing.*\*/#mi', $config, $m, PREG_OFFSET_CAPTURE ) || preg_match( '#^[ \t]*/\*.*stop editing.*\*/#mi', $config, $m, PREG_OFFSET_CAPTURE ) ) {
			return substr( $config, 0, $m[0][1] ) . $block . substr( $config, $m[0][1] );
		}
		if ( preg_match( '#^[ \t]*require_once[^;]*wp-settings\.php#m', $config, $m, PREG_OFFSET_CAPTURE ) ) {
			return substr( $config, 0, $m[0][1] ) . $block . substr( $config, $m[0][1] );
		}
		return false;
	}

	/**
	 * .htaccess text with one clean WordPress block (stray or duplicated markers removed).
	 *
	 * @param string $ht        Current file ('' when missing).
	 * @param bool   $subdomain Church subdomains (true) or subfolders (false).
	 * @return string
	 */
	public static function patch_htaccess( $ht, $subdomain = true ) {
		$start = strpos( $ht, '# BEGIN WordPress' );
		$end   = strrpos( $ht, '# END WordPress' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$ht = substr( $ht, 0, $start ) . substr( $ht, $end + strlen( '# END WordPress' ) );
		}
		$ht = rtrim( preg_replace( "/\n{3,}/", "\n\n", $ht ) );
		return ( '' !== $ht ? $ht . "\n\n" : '' ) . "# BEGIN WordPress\n" . implode( "\n", self::htaccess_rules( $subdomain ) ) . "\n# END WordPress\n";
	}

	/**
	 * Is our Kingdom Church theme installed (not another theme that uses the same "kingdom" folder)?
	 *
	 * @return bool
	 */
	public static function theme_ok() {
		$theme = wp_get_theme( 'kingdom' );
		return $theme->exists() && 'Kingdom Church' === $theme->get( 'Name' );
	}

	/**
	 * Keep the Kingdom Cloud admin pages inside a phone screen: WordPress buttons never wrap,
	 * so a long label (such as an address) would otherwise widen the whole page.
	 */
	public static function phone_css() {
		echo '<style>.ks-wrap .button{white-space:normal;height:auto;min-height:30px;line-height:1.4;padding-top:6px;padding-bottom:6px;max-width:100%;text-align:center;overflow-wrap:anywhere}.ks-wrap .button-hero{padding-top:10px;padding-bottom:10px}.ks-wrap .card,.ks-wrap .widefat,.ks-wrap fieldset,.ks-wrap textarea{max-width:100%;box-sizing:border-box}.ks-wrap td,.ks-wrap label,.ks-wrap p,.ks-wrap li,.ks-wrap code{overflow-wrap:anywhere}.ks-wrap textarea.code{white-space:pre;overflow:auto}@media (max-width:782px){.ks-wrap .button-hero{display:block;margin:0 0 8px}}</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Setup page.
	 */
	public static function render() {
		$checks = self::checks();
		$fail   = (bool) array_filter( $checks, function ( $c ) { return 'fail' === $c[0]; } );
		$result = get_transient( 'ks_convert_result' );
		$icons  = array(
			'ok'   => array( 'dashicons-yes-alt', '#00a32a' ),
			'warn' => array( 'dashicons-warning', '#dba617' ),
			'fail' => array( 'dashicons-dismiss', '#d63638' ),
		);
		?>
		<div class="wrap ks-wrap">
			<?php self::phone_css(); ?>
			<h1><?php esc_html_e( 'Kingdom Cloud setup', 'kingdom-saas' ); ?></h1>
			<?php if ( is_array( $result ) && ! empty( $result['manual'] ) ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'The network was created, but some files could not be written. Paste the text below, save, then log in again.', 'kingdom-saas' ); ?></p></div>
				<?php foreach ( $result['manual'] as $file => $text ) : ?>
					<h2><?php echo esc_html( $file ); ?></h2>
					<textarea class="large-text code" rows="<?php echo (int) min( 22, substr_count( $text, "\n" ) + 2 ); ?>" readonly onclick="this.select()"><?php echo esc_textarea( $text ); ?></textarea>
				<?php endforeach; ?>
				<p><a class="button button-primary" href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'I pasted it — log in again', 'kingdom-saas' ); ?></a></p>
			<?php elseif ( is_array( $result ) && ! empty( $result['error'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $result['error'] ); ?></p></div>
			<?php endif; ?>

			<p style="max-width:760px;font-size:15px"><?php esc_html_e( 'This turns this WordPress site into your church-website platform: your sales page and platform dashboard on the main address, and a website for every church that signs up.', 'kingdom-saas' ); ?></p>
			<h2><?php esc_html_e( 'Before we start', 'kingdom-saas' ); ?></h2>
			<table class="widefat striped" style="max-width:820px"><tbody>
				<?php foreach ( $checks as $c ) : ?>
					<tr><td style="width:2em"><span class="dashicons <?php echo esc_attr( $icons[ $c[0] ][0] ); ?>" style="color:<?php echo esc_attr( $icons[ $c[0] ][1] ); ?>"></span></td><td><?php echo esc_html( $c[1] ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
			<h2><?php esc_html_e( 'What happens when you click the button', 'kingdom-saas' ); ?></h2>
			<ol style="max-width:760px">
				<li><?php esc_html_e( 'wp-config.php and .htaccess are backed up next to the originals (names ending in .kc-backup).', 'kingdom-saas' ); ?></li>
				<li><?php esc_html_e( 'WordPress Multisite is switched on, exactly as Tools → Network Setup would — no copying code.', 'kingdom-saas' ); ?></li>
				<li><?php esc_html_e( 'Custom domains are prepared (sunrise.php), Kingdom Cloud and Kingdom Church Suite are activated for every church, and the Kingdom Church theme is enabled.', 'kingdom-saas' ); ?></li>
				<li><?php esc_html_e( 'You log in again and land on Network Admin → Kingdom Cloud, which checks the rest (addresses, payments, HTTPS).', 'kingdom-saas' ); ?></li>
			</ol>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Turn this site into your Kingdom Cloud platform now?', 'kingdom-saas' ) ); ?>');">
				<input type="hidden" name="action" value="ks_convert">
				<h2><?php esc_html_e( 'Church website addresses', 'kingdom-saas' ); ?></h2>
				<?php $kc_domain = self::domain(); ?>
				<fieldset style="max-width:760px">
					<label style="display:block;margin:.4rem 0 .8rem"><input type="radio" name="ks_mode" value="subfolder" checked> <strong><?php echo esc_html( $kc_domain . '/gracechapel' ); ?></strong> — <?php esc_html_e( 'subfolder (recommended for shared hosting such as Hostinger, cPanel or Plesk: works everywhere, no extra DNS, your SSL already covers it)', 'kingdom-saas' ); ?></label>
					<label style="display:block;margin:.4rem 0"><input type="radio" name="ks_mode" value="subdomain"> <strong><?php echo esc_html( 'gracechapel.' . $kc_domain ); ?></strong> — <?php esc_html_e( 'subdomain (needs a server that accepts every *.yourdomain: a VPS, or hosting with wildcard subdomains, plus a "*" DNS record and SSL for *.yourdomain)', 'kingdom-saas' ); ?></label>
				</fieldset>
				<p class="description" style="max-width:760px"><?php esc_html_e( 'Either way, churches can connect their own domain (gracechapel.org) later. You can switch style later while no churches have signed up.', 'kingdom-saas' ); ?></p>
				<?php wp_nonce_field( 'ks_convert' ); ?>
				<p><button class="button button-primary button-hero" <?php disabled( $fail ); ?>><?php esc_html_e( 'Set up my platform now', 'kingdom-saas' ); ?></button></p>
				<?php if ( $fail ) : ?><p class="description" style="color:#d63638"><?php esc_html_e( 'Fix the red items above first.', 'kingdom-saas' ); ?></p><?php endif; ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Do the conversion.
	 */
	public static function convert() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ks_convert' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'kingdom-saas' ), 403 );
		}
		$back = admin_url( 'tools.php?page=kingdom-cloud-setup' );
		$fail = array_filter( self::checks(), function ( $c ) { return 'fail' === $c[0]; } );
		if ( is_multisite() || $fail ) {
			set_transient( 'ks_convert_result', array( 'error' => __( 'This site cannot be converted yet. Fix the red items first.', 'kingdom-saas' ) ), 600 );
			wp_safe_redirect( $back );
			exit;
		}
		global $wpdb;
		$domain    = self::domain();
		$subdomain = 'subdomain' === sanitize_key( wp_unslash( $_POST['ks_mode'] ?? 'subfolder' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$scheme    = is_ssl() || 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) ? 'https' : 'http';

		// 1. Use the bare domain (no www.), with pretty links.
		update_option( 'home', $scheme . '://' . $domain );
		update_option( 'siteurl', $scheme . '://' . $domain );
		if ( ! get_option( 'permalink_structure' ) ) {
			update_option( 'permalink_structure', '/%postname%/' );
		}

		// 2. Back up the files we will touch.
		$config = self::config_path();
		$ht     = ABSPATH . '.htaccess';
		$stamp  = gmdate( 'Ymd-His' );
		if ( file_exists( $config ) ) {
			@copy( $config, $config . '.kc-backup-' . $stamp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( file_exists( $ht ) ) {
			@copy( $ht, $ht . '.kc-backup-' . $stamp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		// 3. Network tables, as WordPress's Network Setup and `wp core multisite-convert` do.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		require_once ABSPATH . 'wp-admin/includes/network.php';
		foreach ( $wpdb->tables( 'ms_global' ) as $table => $prefixed ) {
			$wpdb->$table = $prefixed;
		}
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->site ) ) ) && $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->site}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $exists ) {
			// A network from an earlier attempt: point it at this domain instead of failing.
			$wpdb->update( $wpdb->site, array( 'domain' => $domain, 'path' => '/' ), array( 'id' => 1 ) );
			$wpdb->update( $wpdb->blogs, array( 'domain' => $domain, 'path' => '/' ), array( 'blog_id' => 1 ) );
			$wpdb->delete( $wpdb->sitemeta, array( 'site_id' => 1, 'meta_key' => 'subdomain_install' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$wpdb->insert( $wpdb->sitemeta, array( 'site_id' => 1, 'meta_key' => 'subdomain_install', 'meta_value' => $subdomain ? 1 : 0 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		} else {
			install_network();
			$result = populate_network( 1, $domain, get_option( 'admin_email' ), get_option( 'blogname' ), '/', $subdomain );
			if ( is_wp_error( $result ) && ! in_array( $result->get_error_code(), array( 'no_wildcard_dns', 'siteid_exists' ), true ) ) {
				set_transient( 'ks_convert_result', array( 'error' => $result->get_error_message() ), 600 );
				wp_safe_redirect( $back );
				exit;
			}
		}

		// 4. Activate for every church and enable the theme (network options).
		$plugins = array( plugin_basename( KS_FILE ) => time() );
		if ( file_exists( WP_PLUGIN_DIR . '/kingdom-church-suite/kingdom-church-suite.php' ) ) {
			$plugins['kingdom-church-suite/kingdom-church-suite.php'] = time();
		}
		$meta = function ( $key ) use ( $wpdb ) {
			return maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE site_id = 1 AND meta_key = %s", $key ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		};
		$set  = function ( $key, $value ) use ( $wpdb ) {
			$wpdb->delete( $wpdb->sitemeta, array( 'site_id' => 1, 'meta_key' => $key ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$wpdb->insert( $wpdb->sitemeta, array( 'site_id' => 1, 'meta_key' => $key, 'meta_value' => maybe_serialize( $value ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		};
		$set( 'active_sitewide_plugins', array_merge( (array) $meta( 'active_sitewide_plugins' ), $plugins ) );
		$themes = (array) $meta( 'allowedthemes' );
		if ( self::theme_ok() ) {
			$themes['kingdom'] = true;
		}
		$set( 'allowedthemes', $themes );
		update_option( 'active_plugins', array_values( array_diff( (array) get_option( 'active_plugins', array() ), array_keys( $plugins ) ) ) );

		// The platform takes the site's name and protocol (settings live in the network options).
		$settings = (array) $meta( 'ks_settings' );
		$settings += array(
			'platform_name' => get_option( 'blogname' ),
			'https'         => 'https' === $scheme ? '1' : '0',
		);
		$set( 'ks_settings', $settings );

		// 5. Custom domains helper.
		$sunrise = WP_CONTENT_DIR . '/sunrise.php';
		if ( ! file_exists( $sunrise ) ) {
			@copy( KS_PATH . 'deploy/sunrise.php', $sunrise ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		// 6. wp-config.php and .htaccess (or show the text to paste).
		$manual  = array();
		$current = file_exists( $config ) ? (string) file_get_contents( $config ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$patched = $current ? self::patch_config( $current, $subdomain ) : false;
		if ( false === $patched || ! wp_is_writable( $config ) || false === file_put_contents( $config, $patched ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$manual['wp-config.php — add above "That\'s all, stop editing!" (remove any older MULTISITE lines)'] = self::config_block( $subdomain );
		}
		if ( ! self::is_nginx() ) {
			$ht_now = file_exists( $ht ) ? (string) file_get_contents( $ht ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$ht_new = self::patch_htaccess( $ht_now, $subdomain );
			$ok     = ( file_exists( $ht ) ? wp_is_writable( $ht ) : wp_is_writable( ABSPATH ) ) && false !== file_put_contents( $ht, $ht_new ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( ! $ok ) {
				$manual['.htaccess — replace the whole file with'] = $ht_new;
			}
		}
		if ( ! file_exists( $sunrise ) ) {
			$manual['wp-content/sunrise.php — copy this file from wp-content/plugins/kingdom-saas/deploy/sunrise.php'] = '';
		}
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $config, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( $manual ) {
			set_transient( 'ks_convert_result', array( 'manual' => $manual ), DAY_IN_SECONDS );
			wp_safe_redirect( $back );
			exit;
		}
		delete_transient( 'ks_convert_result' );
		// Done: log in again (the network uses new log-in cookies) and land on the setup check.
		wp_clear_auth_cookie();
		wp_redirect( $scheme . '://' . $domain . '/wp-login.php?redirect_to=' . rawurlencode( $scheme . '://' . $domain . '/wp-admin/network/admin.php?page=kingdom-cloud' ) . '&ks_ready=1' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}
}
