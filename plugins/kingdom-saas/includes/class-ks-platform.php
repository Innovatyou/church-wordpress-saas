<?php
/**
 * The platform (main) site: landing & pricing page, church sign-up, test checkout and the
 * platform owner's dashboard at /platform/.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Platform site.
 */
class KS_Platform {

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( ! is_main_site() ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'rewrites' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'route' ), 5 );
		// Our own log-in page instead of the WordPress one.
		add_filter( 'login_url', array( __CLASS__, 'login_url' ), 20, 2 );
		add_action( 'login_init', array( __CLASS__, 'leave_wp_login' ), 1 );
		add_action( 'login_head', array( __CLASS__, 'login_style' ), 20 );
		add_filter( 'kcs_replace_wp_login', '__return_false' );
		add_filter( 'login_headerurl', 'ks_platform_url', 20, 0 );
		add_filter(
			'login_headertext',
			function () {
				return ks_get( 'platform_name' );
			},
			20
		);
	}

	/**
	 * The platform log-in page.
	 *
	 * @param string $url      Default URL.
	 * @param string $redirect Where to go after logging in.
	 * @return string
	 */
	public static function login_url( $url, $redirect = '' ) {
		$login = ks_platform_url( 'login/' );
		return $redirect ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $login ) : $login;
	}

	/**
	 * Visitors who open wp-login.php to log in go to the platform log-in page. Password resets,
	 * log-out and the "session expired" pop-up in wp-admin keep using wp-login.php (restyled below).
	 */
	public static function leave_wp_login() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : 'login';
		if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || 'login' !== $action || isset( $_GET['interim-login'] ) || isset( $_GET['checkemail'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}
		$to = ks_platform_url( 'login/' );
		if ( ! empty( $_GET['redirect_to'] ) ) {
			$to = add_query_arg( 'redirect_to', rawurlencode( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) ), $to );
		}
		if ( ! empty( $_GET['loggedout'] ) ) {
			$to = add_query_arg( 'ks_out', 1, $to );
		}
		// phpcs:enable
		wp_safe_redirect( $to );
		exit;
	}

	/**
	 * Platform look for the remaining wp-login.php screens (forgot / reset password).
	 */
	public static function login_style() {
		$color = sanitize_hex_color( ks_get( 'brand_color' ) ) ? sanitize_hex_color( ks_get( 'brand_color' ) ) : '#5b2a86';
		if ( class_exists( 'KCS_Portal' ) ) {
			echo '<style>' . KCS_Portal::login_screen_css( $color, 'light' === ks_get( 'color_mode' ) ? 'light' : 'dark' ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Where a user goes after logging in on the platform.
	 *
	 * @param WP_User $user     User.
	 * @param string  $redirect Requested page.
	 * @return string
	 */
	public static function home_for( $user, $redirect = '' ) {
		if ( is_super_admin( $user->ID ) ) {
			return $redirect ? wp_validate_redirect( $redirect, ks_platform_url( 'platform/' ) ) : ks_platform_url( 'platform/' );
		}
		// A church owner or staff member: open their church's dashboard (logged in there too).
		global $wpdb;
		$blog = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT blog_id FROM ' . ks_table( 'subscriptions' ) . ' WHERE owner_id = %d ORDER BY id DESC LIMIT 1', $user->ID ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $blog ) {
			foreach ( array_keys( get_blogs_of_user( $user->ID ) ) as $id ) {
				if ( ! is_main_site( $id ) ) {
					$blog = (int) $id;
					break;
				}
			}
		}
		if ( $blog ) {
			return KS_Login::link( $user->ID, $blog );
		}
		return $redirect ? wp_validate_redirect( $redirect, ks_platform_url() ) : ks_platform_url();
	}

	/**
	 * Log in from the platform log-in page.
	 *
	 * @param array $d Posted data (unslashed).
	 */
	private static function login( $d ) {
		$redirect = esc_url_raw( (string) ( $d['redirect_to'] ?? '' ) );
		$back     = $redirect ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), ks_platform_url( 'login/' ) ) : ks_platform_url( 'login/' );
		if ( ! isset( $d['_ks'] ) || ! wp_verify_nonce( sanitize_key( $d['_ks'] ), 'ks_login' ) ) {
			self::back( __( 'Your session expired. Please try again.', 'kingdom-saas' ), 'error', $back );
		}
		if ( ! ks_rate_limit( 'login', 10, 10 * MINUTE_IN_SECONDS ) ) {
			self::back( __( 'Too many attempts. Please wait a few minutes and try again.', 'kingdom-saas' ), 'error', $back );
		}
		$login = sanitize_text_field( (string) ( $d['log'] ?? '' ) );
		if ( is_email( $login ) ) {
			$u     = get_user_by( 'email', $login );
			$login = $u ? $u->user_login : $login;
		}
		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => (string) ( $d['pwd'] ?? '' ),
				'remember'      => ! empty( $d['rememberme'] ),
			),
			is_ssl()
		);
		if ( is_wp_error( $user ) ) {
			self::back( __( 'That email/username or password is not right.', 'kingdom-saas' ), 'error', $back, array( 'log' => sanitize_text_field( (string) ( $d['log'] ?? '' ) ) ) );
		}
		add_filter( 'allowed_redirect_hosts', array( 'KS_REST', 'allow_site_host' ) );
		wp_safe_redirect( self::home_for( $user, $redirect ) );
		exit;
	}

	/**
	 * Pretty URLs.
	 */
	public static function rewrites() {
		add_rewrite_rule( '^signup/?$', 'index.php?ks_route=signup', 'top' );
		add_rewrite_rule( '^platform/?$', 'index.php?ks_route=platform', 'top' );
		add_rewrite_rule( '^billing/test-checkout/?$', 'index.php?ks_route=test-checkout', 'top' );
		add_rewrite_rule( '^login/?$', 'index.php?ks_route=login', 'top' );
		$rules = get_option( 'rewrite_rules' );
		if ( get_site_option( 'ks_flush_rewrites' ) || ( is_array( $rules ) && ! isset( $rules['^login/?$'] ) ) ) {
			delete_site_option( 'ks_flush_rewrites' );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Query vars.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'ks_route';
		return $vars;
	}

	/**
	 * Everything the platform search box can jump to.
	 *
	 * @return array[] t (title), d (where), u (link), k (other words).
	 */
	public static function search_index() {
		$u    = function ( $tab, $hash = '' ) {
			return add_query_arg( 'tab', $tab, ks_platform_url( 'platform/' ) ) . ( $hash ? '#' . $hash : '' );
		};
		$set  = __( 'Settings', 'kingdom-saas' );
		$rows = array(
			array( __( 'Overview', 'kingdom-saas' ), __( 'Churches, monthly revenue and who needs attention', 'kingdom-saas' ), $u( 'overview' ), 'dashboard stats mrr revenue' ),
			array( __( 'Churches', 'kingdom-saas' ), __( 'Every church: plan, status, open its admin, extend, suspend', 'kingdom-saas' ), $u( 'churches' ), 'tenants sites customers suspend extend trial' ),
			array( __( 'Add a church', 'kingdom-saas' ), __( 'Create a church site yourself (offline sales)', 'kingdom-saas' ), $u( 'new' ), 'create new site manual' ),
			array( __( 'Payments', 'kingdom-saas' ), __( 'Bank transfers to confirm and all invoices', 'kingdom-saas' ), $u( 'invoices' ), 'invoices billing confirm transfer receipt' ),
			array( __( 'Domains', 'kingdom-saas' ), __( 'Church custom domains, DNS checks, parked domain steps', 'kingdom-saas' ), $u( 'domains' ), 'custom domain dns parked ssl' ),
			array( __( 'Plans & pricing', 'kingdom-saas' ), __( 'Plan names, prices in USD and local currency, limits', 'kingdom-saas' ), $u( 'plans' ), 'price pricing naira dollars limits starter growth ministry' ),
			array( __( 'Server IP & CNAME for custom domains', 'kingdom-saas' ), __( 'Domains → Domain settings', 'kingdom-saas' ), $u( 'domains', 'domain-settings' ), 'dns a record ip address cname' ),
			array( __( 'Platform name, tagline & brand colour', 'kingdom-saas' ), $set . ' → ' . __( 'Brand', 'kingdom-saas' ), $u( 'settings', 'set-brand' ), 'logo name colour color tagline' ),
			array( __( 'Dark or light mode', 'kingdom-saas' ), $set . ' → ' . __( 'Brand', 'kingdom-saas' ), $u( 'settings', 'set-brand' ), 'theme dark light colours mode' ),
			array( __( 'Support email', 'kingdom-saas' ), $set . ' → ' . __( 'Brand', 'kingdom-saas' ), $u( 'settings', 'set-brand' ), 'email sender contact' ),
			array( __( 'Platform domain & reserved addresses', 'kingdom-saas' ), $set . ' → ' . __( 'Domains', 'kingdom-saas' ), $u( 'settings', 'set-domains' ), 'https reserved names subdomain' ),
			array( __( 'Free trial, grace and reminder days', 'kingdom-saas' ), $set . ' → ' . __( 'Trials & renewals', 'kingdom-saas' ), $u( 'settings', 'set-trials' ), 'trial days grace reminder suspend' ),
			array( __( 'Local currency', 'kingdom-saas' ), $set . ' → ' . __( 'Trials & renewals', 'kingdom-saas' ), $u( 'settings', 'set-trials' ), 'ngn naira currency local label' ),
			array( __( 'Paystack keys', 'kingdom-saas' ), $set . ' → ' . __( 'Payments', 'kingdom-saas' ), $u( 'settings', 'set-payments' ), 'paystack secret key gateway' ),
			array( __( 'Flutterwave keys', 'kingdom-saas' ), $set . ' → ' . __( 'Payments', 'kingdom-saas' ), $u( 'settings', 'set-payments' ), 'flutterwave public secret hash webhook' ),
			array( __( 'Stripe keys', 'kingdom-saas' ), $set . ' → ' . __( 'Payments', 'kingdom-saas' ), $u( 'settings', 'set-payments' ), 'stripe secret webhook card dollars' ),
			array( __( 'Bot protection (Cloudflare Turnstile)', 'kingdom-saas' ), $set . ' → ' . __( 'Payments', 'kingdom-saas' ), $u( 'settings', 'set-payments' ), 'captcha spam turnstile signup' ),
			array( __( 'Test mode', 'kingdom-saas' ), $set . ' → ' . __( 'Payments', 'kingdom-saas' ), $u( 'settings', 'set-payments' ), 'test simulated payment sandbox' ),
			array( __( 'Bank accounts for bank transfer', 'kingdom-saas' ), $set . ' → ' . __( 'Bank transfer', 'kingdom-saas' ), $u( 'settings', 'set-bank' ), 'bank account number transfer offline' ),
			array( __( 'Search & sharing (SEO)', 'kingdom-saas' ), $set . ' → ' . __( 'Search & sharing (SEO)', 'kingdom-saas' ), $u( 'settings', 'set-seo' ), 'seo google search console bing analytics ga4 sitemap sharing image social' ),
			array( __( 'Setup check', 'kingdom-saas' ), __( 'Network Admin → Ministry OS Cloud', 'kingdom-saas' ), network_admin_url( 'admin.php?page=kingdom-cloud' ), 'setup check health problems' ),
		);
		$out = array();
		foreach ( $rows as $r ) {
			$out[] = array( 't' => $r[0], 'd' => $r[1], 'u' => $r[2], 'k' => $r[3] );
		}
		return (array) apply_filters( 'ks_platform_search_index', $out );
	}

	/**
	 * Full search: pages and settings, churches, invoices and domains.
	 *
	 * @param string $q Search words.
	 * @return array|null Rows per kind (t, d, u, s), or null when the search is too short.
	 */
	public static function search( $q ) {
		global $wpdb;
		$q = trim( (string) $q );
		if ( mb_strlen( $q ) < 2 ) {
			return null;
		}
		$words = preg_split( '/\s+/', mb_strtolower( $q ) );
		$pages = array();
		foreach ( self::search_index() as $i ) {
			$hay = mb_strtolower( $i['t'] . ' ' . $i['d'] . ' ' . $i['k'] );
			if ( count( array_filter( $words, function ( $w ) use ( $hay ) { return false !== mb_strpos( $hay, $w ); } ) ) === count( $words ) ) {
				$pages[] = array( 't' => $i['t'], 'd' => $i['d'], 'u' => $i['u'] );
			}
		}
		$churches = array();
		foreach ( array_slice( KS_Tenants::all( array( 'search' => $q ) ), 0, 15 ) as $s ) {
			$churches[] = array( 't' => $s->church_name, 'd' => $s->subdomain . ' · ' . $s->owner_email . ' · ' . ( KS_Plans::get( $s->plan )['name'] ?? $s->plan ), 'u' => add_query_arg( array( 'tab' => 'churches', 's' => $s->owner_email ), ks_platform_url( 'platform/' ) ), 's' => $s->status );
		}
		$like     = '%' . $wpdb->esc_like( $q ) . '%';
		$invoices = array();
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT i.reference, i.amount, i.currency, i.status, i.created_at, i.gateway, s.church_name FROM ' . ks_table( 'invoices' ) . ' i LEFT JOIN ' . ks_table( 'subscriptions' ) . ' s ON s.blog_id = i.blog_id WHERE i.reference LIKE %s OR i.gateway_ref LIKE %s OR s.church_name LIKE %s OR s.owner_email LIKE %s ORDER BY i.id DESC LIMIT 15', $like, $like, $like, $like ) );
		foreach ( $rows as $r ) {
			$invoices[] = array( 't' => ( $r->church_name ? $r->church_name : '—' ) . ' — ' . ks_money( $r->amount, $r->currency ), 'd' => $r->reference . ' · ' . $r->gateway . ' · ' . date_i18n( 'j M Y', strtotime( $r->created_at . ' UTC' ) ), 'u' => add_query_arg( 'tab', 'invoices', ks_platform_url( 'platform/' ) ), 's' => $r->status );
		}
		$domains = array();
		$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT d.domain, d.status, s.church_name FROM ' . ks_table( 'domains' ) . ' d LEFT JOIN ' . ks_table( 'subscriptions' ) . ' s ON s.blog_id = d.blog_id WHERE d.domain LIKE %s OR s.church_name LIKE %s ORDER BY d.id DESC LIMIT 15', $like, $like ) );
		// phpcs:enable
		foreach ( $rows as $r ) {
			$domains[] = array( 't' => $r->domain, 'd' => (string) $r->church_name, 'u' => add_query_arg( 'tab', 'domains', ks_platform_url( 'platform/' ) ), 's' => $r->status );
		}
		return array( 'pages' => array_slice( $pages, 0, 12 ), 'churches' => $churches, 'invoices' => $invoices, 'domains' => $domains );
	}

	/**
	 * Render platform pages.
	 */
	public static function route() {
		$route = get_query_var( 'ks_route' );
		if ( ! $route && is_front_page() && ! is_paged() ) {
			$route = 'landing';
		}
		// Only known pages; anything else (including "./platform" or "../x") is a normal 404.
		if ( ! $route || ! in_array( $route, array( 'landing', 'signup', 'platform', 'test-checkout', 'login', 'solution' ), true ) ) {
			return;
		}
		$solution = '';
		if ( 'solution' === $route ) {
			$solution  = sanitize_key( get_query_var( 'ks_solution' ) );
			$solutions = KS_SEO::solutions();
			if ( ! isset( $solutions[ $solution ] ) ) {
				return;
			}
		}
		if ( 'login' === $route && is_user_logged_in() ) {
			add_filter( 'allowed_redirect_hosts', array( 'KS_REST', 'allow_site_host' ) );
			wp_safe_redirect( self::home_for( wp_get_current_user(), isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			exit;
		}
		if ( 'platform' === $route && ! is_super_admin() ) {
			wp_safe_redirect( wp_login_url( ks_platform_url( 'platform/' ) ) );
			exit;
		}
		$vars = array( 'flash' => self::flash() );
		if ( $solution ) {
			$vars['slug']     = $solution;
			$vars['solution'] = $solutions[ $solution ];
		}
		if ( 'test-checkout' === $route ) {
			$vars['invoice'] = KS_Billing::invoice( sanitize_text_field( wp_unslash( $_GET['reference'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! ks_test_mode() || ! $vars['invoice'] ) {
				wp_die( esc_html__( 'This checkout link is not valid.', 'kingdom-saas' ), '', 404 );
			}
		}
		status_header( 200 );
		nocache_headers();
		echo ks_template( 'page', array( 'route' => $route, 'vars' => $vars ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Per-visitor flash key: the user ID, or a random browser cookie for visitors (never the IP,
	 * which many visitors can share behind a proxy).
	 *
	 * @param bool $create Create the cookie if missing.
	 * @return string
	 */
	private static function flash_key( $create ) {
		if ( get_current_user_id() ) {
			return 'ks_flash_' . get_current_user_id();
		}
		$token = isset( $_COOKIE['ks_flash'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) wp_unslash( $_COOKIE['ks_flash'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( strlen( $token ) !== 32 ) {
			if ( ! $create ) {
				return '';
			}
			$token = wp_generate_password( 32, false, false );
			setcookie( 'ks_flash', $token, array( 'expires' => time() + 600, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		}
		return 'ks_flash_v' . md5( $token );
	}

	/**
	 * Read and clear the flash message.
	 *
	 * @return array|null array( message, type, old input ).
	 */
	private static function flash() {
		$key = self::flash_key( false );
		if ( ! $key ) {
			return null;
		}
		$f   = get_site_transient( $key );
		delete_site_transient( $key );
		return $f ? $f : null;
	}

	/**
	 * Store a flash message and go back.
	 *
	 * @param string $msg  Message.
	 * @param string $type success|error.
	 * @param string $to   URL.
	 * @param array  $old  Old input to refill the form.
	 */
	private static function back( $msg, $type, $to, $old = array() ) {
		set_site_transient( self::flash_key( true ), array( $msg, $type, $old ), 120 );
		wp_safe_redirect( $to );
		exit;
	}

	/**
	 * Handle forms posted to the platform.
	 */
	public static function handle() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['ks_do'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
			return;
		}
		$d  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$do = sanitize_key( $d['ks_do'] );
		if ( 'signup' === $do ) {
			self::signup( $d );
			return;
		}
		if ( 'test_pay' === $do ) {
			self::test_pay( $d );
			return;
		}
		if ( 'login' === $do ) {
			self::login( $d );
			return;
		}
		if ( ! is_super_admin() || ! isset( $d['_ks'] ) || ! wp_verify_nonce( sanitize_key( $d['_ks'] ), 'ks_platform' ) ) {
			wp_die( esc_html__( 'Your session expired. Please go back and try again.', 'kingdom-saas' ), 403 );
		}
		$tab = sanitize_key( $d['tab'] ?? 'churches' );
		// Return to the same list (keeping search and filters) after an action.
		$ref = wp_validate_redirect( wp_get_raw_referer(), '' );
		$to  = $ref && false !== strpos( $ref, '/platform/' ) ? $ref : add_query_arg( 'tab', $tab, ks_platform_url( 'platform/' ) );
		$bid = absint( $d['blog_id'] ?? 0 );
		$msg = __( 'Saved.', 'kingdom-saas' );
		switch ( $do ) {
			case 'suspend':
				KS_Tenants::update( $bid, array( 'status' => 'suspended', 'last_notice' => 'admin_blocked' ) );
				$msg = __( 'Church suspended. Visitors now see the paused page.', 'kingdom-saas' );
				break;
			case 'reactivate':
				$sub  = KS_Tenants::get( $bid );
				$paid = $sub && $sub->current_period_end && strtotime( $sub->current_period_end . ' UTC' ) > time();
				KS_Tenants::update( $bid, $paid ? array( 'status' => 'active', 'last_notice' => '' ) : array( 'status' => 'trialing', 'last_notice' => '', 'trial_ends_at' => gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS ) ) );
				$msg = $paid ? __( 'Church reactivated.', 'kingdom-saas' ) : __( 'Church reactivated with a 7-day grace trial.', 'kingdom-saas' );
				break;
			case 'extend':
				$sub  = KS_Tenants::get( $bid );
				$days = max( 1, absint( $d['days'] ?? 14 ) );
				if ( $sub ) {
					$field = 'trialing' === $sub->status || ! $sub->current_period_end ? 'trial_ends_at' : 'current_period_end';
					$from  = max( time(), $sub->$field ? strtotime( $sub->$field . ' UTC' ) : 0 );
					KS_Tenants::update( $bid, array( $field => gmdate( 'Y-m-d H:i:s', $from + $days * DAY_IN_SECONDS ), 'status' => 'trial_ends_at' === $field ? 'trialing' : 'active', 'last_notice' => '' ) );
					/* translators: %d: days */
					$msg = sprintf( __( 'Extended by %d days.', 'kingdom-saas' ), $days );
				}
				break;
			case 'plan':
				if ( KS_Plans::get( sanitize_key( $d['plan'] ?? '' ) ) ) {
					KS_Tenants::update( $bid, array( 'plan' => sanitize_key( $d['plan'] ) ) );
					KS_Domains::enforce_plan( $bid );
					$msg = __( 'Plan changed.', 'kingdom-saas' );
				}
				break;
			case 'invoice_paid':
				$inv = KS_Billing::invoice( sanitize_text_field( $d['reference'] ?? '' ) );
				if ( $inv ) {
					KS_Billing::mark_paid( $inv, 'manual' );
					$msg = __( 'Payment confirmed and the church notified.', 'kingdom-saas' );
				}
				break;
			case 'invoice_reject':
				$inv = KS_Billing::invoice( sanitize_text_field( $d['reference'] ?? '' ) );
				if ( $inv && KS_Bank::reject( $inv, (string) ( $d['reason'] ?? '' ) ) ) {
					$msg = __( 'Transfer rejected. The church has been emailed.', 'kingdom-saas' );
				} else {
					self::back( __( 'This payment was already handled.', 'kingdom-saas' ), 'error', $to );
				}
				break;
			case 'save_domain_settings':
				$ip = trim( sanitize_text_field( (string) ( $d['server_ip'] ?? '' ) ) );
				if ( '' !== $ip && ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					self::back( __( 'That server IP does not look right. Use the numbers only, for example 203.0.113.10.', 'kingdom-saas' ), 'error', $to );
				}
				$cname = strtolower( trim( preg_replace( '#^https?://#', '', sanitize_text_field( (string) ( $d['cname_target'] ?? '' ) ) ), '/ ' ) );
				ks_set( array( 'server_ip' => $ip, 'cname_target' => $cname ) );
				$msg = __( 'Domain settings saved.', 'kingdom-saas' );
				break;
			case 'domain_verify':
				$r   = KS_Domains::verify( absint( $d['id'] ?? 0 ) );
				$msg = is_wp_error( $r ) ? $r->get_error_message() : __( 'Domain connected.', 'kingdom-saas' );
				break;
			case 'domain_remove':
				KS_Domains::remove( absint( $d['id'] ?? 0 ) );
				$msg = __( 'Domain removed.', 'kingdom-saas' );
				break;
			case 'delete':
				if ( $bid && ! is_main_site( $bid ) && 'DELETE' === ( $d['confirm'] ?? '' ) ) {
					require_once ABSPATH . 'wp-admin/includes/ms.php';
					wpmu_delete_blog( $bid, true );
					$msg = __( 'Church site deleted.', 'kingdom-saas' );
				} else {
					self::back( __( 'Type DELETE to confirm.', 'kingdom-saas' ), 'error', $to );
				}
				break;
			case 'run_lifecycle':
				$c = KS_Lifecycle::run();
				KS_Domains::recheck_pending();
				/* translators: 1: past due, 2: suspended, 3: reminders */
				$msg = sprintf( __( 'Checked all churches: %1$d payment due, %2$d suspended, %3$d reminders sent.', 'kingdom-saas' ), $c['past_due'], $c['suspended'], $c['reminded'] );
				break;
			case 'save_plans':
				KS_Plans::save( (array) ( $d['plans'] ?? array() ) );
				foreach ( KS_Tenants::all() as $s ) {
					KS_Domains::enforce_plan( $s->blog_id );
				}
				$msg = __( 'Plans saved.', 'kingdom-saas' );
				break;
			case 'save_settings':
				$keys = array_keys( ks_default_settings() );
				$vals = array();
				foreach ( $keys as $k ) {
					if ( in_array( $k, array( 'https', 'test_mode' ), true ) ) {
						$vals[ $k ] = empty( $d[ $k ] ) ? '0' : '1';
					} elseif ( isset( $d[ $k ] ) ) {
						$vals[ $k ] = in_array( $k, array( 'bank_details', 'tagline', 'seo_description', 'seo_profiles' ), true ) ? sanitize_textarea_field( $d[ $k ] ) : sanitize_text_field( $d[ $k ] );
					}
				}
				if ( isset( $vals['color_mode'] ) && ! in_array( $vals['color_mode'], array( 'dark', 'light', 'auto' ), true ) ) {
					$vals['color_mode'] = 'dark';
				}
				if ( isset( $vals['brand_color'] ) ) {
					$vals['brand_color'] = sanitize_hex_color( $vals['brand_color'] ) ? sanitize_hex_color( $vals['brand_color'] ) : '#5b2a86';
				}
				$vals['bank_accounts'] = KS_Bank::sanitize_accounts( $d['bank_accounts'] ?? array() );
				ks_set( $vals );
				$msg = __( 'Settings saved.', 'kingdom-saas' );
				break;
			case 'create':
				$r = KS_Provisioner::create_church(
					array(
						'church_name' => $d['church_name'] ?? '',
						'subdomain'   => $d['subdomain'] ?? '',
						'name'        => $d['name'] ?? '',
						'email'       => $d['email'] ?? '',
						'plan'        => $d['plan'] ?? '',
						'interval'    => $d['interval'] ?? 'month',
						'region'      => $d['region'] ?? 'international',
						'status'      => $d['status'] ?? 'trialing',
						'trial_days'  => absint( $d['trial_days'] ?? ks_get( 'trial_days' ) ),
						'by_admin'    => true,
					)
				);
				if ( is_wp_error( $r ) ) {
					self::back( $r->get_error_message(), 'error', add_query_arg( 'tab', 'new', ks_platform_url( 'platform/' ) ), $d );
				}
				$msg = __( 'Church created. The owner has been emailed their login details.', 'kingdom-saas' );
				$to  = add_query_arg( 'tab', 'churches', ks_platform_url( 'platform/' ) );
				break;
		}
		self::back( $msg, 'success', $to );
	}

	/**
	 * Check the Cloudflare Turnstile answer (bot protection on sign-up, when keys are set).
	 *
	 * @param string $token Widget response.
	 * @return bool
	 */
	private static function turnstile_ok( $token ) {
		if ( '' === $token ) {
			return false;
		}
		$res = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body'    => array( 'secret' => ks_get( 'turnstile_secret' ), 'response' => $token, 'remoteip' => ks_client_ip() ),
			)
		);
		$body = is_wp_error( $res ) ? array() : json_decode( wp_remote_retrieve_body( $res ), true );
		return ! empty( $body['success'] );
	}

	/**
	 * Church sign-up.
	 *
	 * @param array $d Posted data.
	 */
	private static function signup( $d ) {
		$back = add_query_arg( 'plan', sanitize_key( $d['plan'] ?? '' ), ks_platform_url( 'signup/' ) );
		$old  = array_diff_key( $d, array_flip( array( 'password', '_ks' ) ) );
		if ( ! isset( $d['_ks'] ) || ! wp_verify_nonce( sanitize_key( $d['_ks'] ), 'ks_signup' ) ) {
			self::back( __( 'Your session expired. Please try again.', 'kingdom-saas' ), 'error', $back, $old );
		}
		if ( ! empty( $d['website'] ) ) {
			self::back( __( 'Something went wrong. Please try again.', 'kingdom-saas' ), 'error', $back, $old );
		}
		if ( ! ks_rate_limit( 'signup', 5, HOUR_IN_SECONDS ) ) {
			self::back( __( 'Too many sign-ups from your network. Please try again later.', 'kingdom-saas' ), 'error', $back, $old );
		}
		if ( empty( $d['terms'] ) ) {
			self::back( __( 'Please accept the terms to continue.', 'kingdom-saas' ), 'error', $back, $old );
		}
		if ( ks_get( 'turnstile_secret' ) && ! self::turnstile_ok( (string) ( $d['cf-turnstile-response'] ?? '' ) ) ) {
			self::back( __( 'Please confirm you are not a robot and try again.', 'kingdom-saas' ), 'error', $back, $old );
		}
		$r = KS_Provisioner::create_church(
			array(
				'church_name' => $d['church_name'] ?? '',
				'subdomain'   => $d['subdomain'] ?? '',
				'name'        => $d['name'] ?? '',
				'email'       => $d['email'] ?? '',
				'password'    => $d['password'] ?? '',
				'plan'        => $d['plan'] ?? 'growth',
				'interval'    => $d['interval'] ?? 'month',
				'region'      => $d['region'] ?? 'international',
				'palette'     => $d['palette'] ?? '',
			)
		);
		if ( is_wp_error( $r ) ) {
			self::back( $r->get_error_message(), 'error', $back, $old );
		}
		add_filter( 'allowed_redirect_hosts', array( 'KS_REST', 'allow_site_host' ) );
		wp_safe_redirect( $r['login_url'] );
		exit;
	}

	/**
	 * Test checkout (test mode only): simulate the gateway result.
	 *
	 * @param array $d Posted data.
	 */
	private static function test_pay( $d ) {
		$inv = KS_Billing::invoice( sanitize_text_field( $d['reference'] ?? '' ) );
		if ( ! ks_test_mode() || ! $inv || ! isset( $d['_ks'] ) || ! wp_verify_nonce( sanitize_key( $d['_ks'] ), 'ks_test_pay_' . $inv->reference ) ) {
			wp_die( esc_html__( 'This checkout link is not valid.', 'kingdom-saas' ), '', 403 );
		}
		if ( 'success' === ( $d['result'] ?? '' ) ) {
			KS_Billing::mark_paid( $inv, 'TEST-' . strtoupper( wp_generate_password( 6, false, false ) ) );
			$paid = 1;
		} else {
			KS_Billing::mark_failed( $inv, 'Declined (test)' );
			$paid = 0;
		}
		add_filter( 'allowed_redirect_hosts', array( 'KS_REST', 'allow_site_host' ) );
		wp_safe_redirect( add_query_arg( array( 'view' => 'subscription', 'ks_paid' => $paid ), KS_Tenants::admin_url( $inv->blog_id ) ) );
		exit;
	}

	/**
	 * Platform dashboard figures.
	 *
	 * @return array
	 */
	public static function stats() {
		$subs = KS_Tenants::all();
		$out  = array( 'total' => count( $subs ), 'by_status' => array_fill_keys( KS_Tenants::statuses(), 0 ), 'mrr' => array(), 'revenue_30' => array() );
		foreach ( $subs as $s ) {
			++$out['by_status'][ $s->status ];
			if ( 'active' === $s->status ) {
				$p   = KS_Plans::price( $s->plan, 'month', $s->region );
				$cur = $p['currency'];
				$out['mrr'][ $cur ] = ( $out['mrr'][ $cur ] ?? 0 ) + $p['amount'];
			}
		}
		foreach ( KS_Billing::invoices( 0, 500 ) as $inv ) {
			if ( 'paid' === $inv->status && strtotime( $inv->paid_at . ' UTC' ) > time() - 30 * DAY_IN_SECONDS ) {
				$out['revenue_30'][ $inv->currency ] = ( $out['revenue_30'][ $inv->currency ] ?? 0 ) + $inv->amount;
			}
		}
		return $out;
	}
}
