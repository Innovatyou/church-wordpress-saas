<?php
/**
 * Partner portal: login, registration, profile updates and access control.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Portal.
 */
class KCS_Portal {

	/**
	 * Flash messages for the current request.
	 *
	 * @var array
	 */
	private static $messages = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_forms' ) );
		add_action( 'template_redirect', array( __CLASS__, 'partner_home' ), 20 );
		// The church's own log-in page replaces the WordPress login screen.
		add_filter( 'login_url', array( __CLASS__, 'login_url' ), 10, 2 );
		add_action( 'login_init', array( __CLASS__, 'leave_wp_login' ), 1 );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_style' ) );
		add_filter( 'login_headerurl', array( __CLASS__, 'login_logo_url' ) );
		add_filter( 'login_headertext', array( __CLASS__, 'login_logo_text' ) );
		add_action( 'admin_init', array( __CLASS__, 'block_admin' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
	}

	/**
	 * Add a message.
	 *
	 * @param string $text Text.
	 * @param string $type success|error|info.
	 */
	public static function add_message( $text, $type = 'error' ) {
		self::$messages[] = array( 'text' => $text, 'type' => $type );
	}

	/**
	 * Render messages.
	 *
	 * @return string
	 */
	public static function messages() {
		$out = '';
		foreach ( self::$messages as $m ) {
			$out .= '<div class="kcs-alert kcs-alert--' . esc_attr( $m['type'] ) . '" role="alert">' . esc_html( $m['text'] ) . '</div>';
		}
		return $out;
	}

	/**
	 * Is the user only a partner (no editing capabilities)?
	 *
	 * @param WP_User|null $user User.
	 * @return bool
	 */
	private static function is_partner_only( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		return $user && $user->exists() && in_array( 'kcs_partner', (array) $user->roles, true ) && ! user_can( $user, 'edit_posts' );
	}

	/**
	 * Keep partners out of wp-admin.
	 */
	public static function block_admin() {
		if ( wp_doing_ajax() || ! self::is_partner_only() ) {
			return;
		}
		wp_safe_redirect( kcs_page_url( 'partner_dashboard' ) );
		exit;
	}

	/**
	 * Hide the toolbar for partners.
	 *
	 * @param bool $show Show.
	 * @return bool
	 */
	public static function admin_bar( $show ) {
		return self::is_partner_only() ? false : $show;
	}

	/**
	 * Send partners to their dashboard after wp-login.
	 *
	 * @param string           $redirect  Redirect.
	 * @param string           $requested Requested.
	 * @param WP_User|WP_Error $user      User.
	 * @return string
	 */
	public static function login_redirect( $redirect, $requested, $user ) {
		if ( $user instanceof WP_User && ( self::is_partner_only( $user ) || ( KCS_Partners::get_by_user( $user->ID ) && self::is_entry_page( $requested ) ) ) ) {
			return kcs_page_url( 'partner_dashboard' );
		}
		return $redirect;
	}

	/**
	 * Use the church's log-in page instead of wp-login.php?
	 *
	 * @return bool
	 */
	public static function replace_wp_login() {
		$page = (int) kcs_get_option( 'page_partner_login', 0 );
		return (bool) apply_filters( 'kcs_replace_wp_login', $page && 'publish' === get_post_status( $page ) );
	}

	/**
	 * Log-in links point to the church's log-in page.
	 *
	 * @param string $url      Default URL.
	 * @param string $redirect Where to go after logging in.
	 * @return string
	 */
	public static function login_url( $url, $redirect = '' ) {
		if ( ! self::replace_wp_login() ) {
			return $url;
		}
		$login = kcs_page_url( 'partner_login' );
		return $redirect ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $login ) : $login;
	}

	/**
	 * Opening wp-login.php to log in goes to the church's log-in page. Password resets, log-out and
	 * the "session expired" pop-up in wp-admin keep using wp-login.php (restyled with the church's look).
	 */
	public static function leave_wp_login() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : 'login';
		if ( ! self::replace_wp_login() || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || 'login' !== $action || isset( $_GET['interim-login'] ) || isset( $_GET['checkemail'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}
		$to = kcs_page_url( 'partner_login' );
		if ( ! empty( $_GET['redirect_to'] ) ) {
			$to = add_query_arg( 'redirect_to', rawurlencode( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) ), $to );
		}
		if ( ! empty( $_GET['loggedout'] ) ) {
			$to = add_query_arg( 'kcs_out', 1, $to );
		}
		// phpcs:enable
		wp_safe_redirect( $to );
		exit;
	}

	/**
	 * CSS that gives the remaining wp-login.php screens (forgot / reset password) a branded look.
	 *
	 * @param string $color Brand colour.
	 * @param string $mode  dark|light.
	 * @param string $logo  Optional logo URL (otherwise the name from login_headertext is shown).
	 * @return string
	 */
	public static function login_screen_css( $color, $mode = 'light', $logo = '' ) {
		$color = sanitize_hex_color( $color ) ? sanitize_hex_color( $color ) : '#5b2a86';
		$dark  = 'dark' === $mode;
		$bg    = $dark ? '#120f17' : '#f6f3fa';
		$card  = $dark ? '#1c1723' : '#ffffff';
		$text  = $dark ? '#f3eff8' : '#1d1724';
		$muted = $dark ? '#b3abbe' : '#6b6476';
		$line  = $dark ? '#332a3f' : '#e7e1ee';
		$css   = 'body.login{background:radial-gradient(circle at 20% 0%,' . $color . '33,transparent 55%),' . $bg . ';color:' . $text . ';font-family:"Plus Jakarta Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
			. '.login h1 a{background:none!important;width:auto!important;height:auto!important;text-indent:0!important;font-size:1.5rem;font-weight:800;color:' . $text . '!important;line-height:1.2;overflow:visible}'
			. ( $logo ? '.login h1 a{background:url(' . esc_url( $logo ) . ') center top/contain no-repeat!important;height:80px!important;width:100%!important;text-indent:-9999px!important}' : '' )
			. '.login form{background:' . $card . ';border:1px solid ' . $line . ';border-radius:18px;box-shadow:0 24px 50px -30px rgba(0,0,0,.45);padding:26px}'
			. '.login label,.login .message,.login .notice{color:' . $text . '}'
			. '.login form .input,.login input[type=text],.login input[type=password],.login input[type=email]{background:' . ( $dark ? '#261f30' : '#fff' ) . ';color:' . $text . ';border:1px solid ' . $line . ';border-radius:10px;font-size:16px;padding:8px 12px}'
			. '.login .message,.login .notice,.login .success{background:' . $card . ';border-left-color:' . $color . '!important;border-radius:12px}'
			. '.login input:focus{border-color:' . $color . '!important;box-shadow:0 0 0 1px ' . $color . '!important;outline:0}'
			. '.wp-core-ui .button-primary{background:' . $color . '!important;border-color:' . $color . '!important;border-radius:999px!important;padding:2px 20px!important;font-weight:700}'
			. '.login #nav a,.login #backtoblog a,.login .privacy-policy-page-link a{color:' . $muted . '!important}.login #nav a:hover,.login #backtoblog a:hover{color:' . $text . '!important}'
			. '.login .language-switcher{display:none}.login .button.wp-hide-pw .dashicons{color:' . $muted . '}';
		return $css;
	}

	/**
	 * Church look for the remaining wp-login.php screens.
	 */
	public static function login_style() {
		if ( ! self::replace_wp_login() ) {
			return;
		}
		$logo = has_custom_logo() ? (string) wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' ) : (string) kcs_get_option( 'church_logo', '' );
		echo '<style>' . self::login_screen_css( get_theme_mod( 'kingdom_color_primary', '#5b2a86' ), 'light', $logo ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * The logo on wp-login.php links to the church website.
	 *
	 * @return string
	 */
	public static function login_logo_url() {
		return home_url( '/' );
	}

	/**
	 * The logo text on wp-login.php is the church's name.
	 *
	 * @return string
	 */
	public static function login_logo_text() {
		return kcs_get_option( 'church_name', get_bloginfo( 'name' ) );
	}

	/**
	 * Is this URL the Become a Partner or Partner Login page (a partner has nothing to do there)?
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_entry_page( $url ) {
		$path = untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		foreach ( array( 'partner_register', 'partner_login' ) as $key ) {
			if ( (int) kcs_get_option( 'page_' . $key, 0 ) && untrailingslashit( (string) wp_parse_url( kcs_page_url( $key ), PHP_URL_PATH ) ) === $path ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Logged-in partners who open Become a Partner or Partner Login go straight to their dashboard.
	 */
	public static function partner_home() {
		if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! is_user_logged_in() || ! is_page() || ! kcs_is_partner() ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}
		$id = get_queried_object_id();
		if ( in_array( $id, array( (int) kcs_get_option( 'page_partner_register', 0 ), (int) kcs_get_option( 'page_partner_login', 0 ) ), true ) && $id ) {
			wp_safe_redirect( kcs_page_url( 'partner_dashboard' ) );
			exit;
		}
	}

	/**
	 * Process portal form posts.
	 */
	public static function handle_forms() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['kcs_form'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}
		$form = sanitize_key( $_POST['kcs_form'] );
		if ( ! isset( $_POST['_kcs_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_kcs_nonce'] ), 'kcs_' . $form ) ) {
			self::add_message( __( 'Your session expired. Please try again.', 'kingdom-church-suite' ) );
			return;
		}
		if ( ! empty( $_POST['kcs_website'] ) ) { // Honeypot.
			return;
		}
		$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field downstream.

		switch ( $form ) {
			case 'login':
				self::login( $data );
				break;
			case 'register':
				self::register( $data );
				break;
			case 'profile':
				self::profile( $data );
				break;
			case 'password':
				self::password( $data );
				break;
			case 'change_plan':
				self::change_plan( $data );
				break;
		}
	}

	/**
	 * Login.
	 *
	 * @param array $data Data.
	 */
	private static function login( $data ) {
		$login = sanitize_text_field( $data['log'] ?? '' );
		// Per account (stops password guessing) and a looser per-network limit (church Wi-Fi is shared).
		if ( ! kcs_rate_limit( 'login_' . md5( strtolower( $login ) ), 10, 900 ) || ! kcs_rate_limit( 'login', 60, 900 ) ) {
			self::add_message( __( 'Too many attempts. Please wait a few minutes.', 'kingdom-church-suite' ) );
			return;
		}
		if ( is_email( $login ) ) {
			$u     = get_user_by( 'email', $login );
			$login = $u ? $u->user_login : $login;
		}
		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => (string) ( $data['pwd'] ?? '' ),
				'remember'      => ! empty( $data['rememberme'] ),
			),
			is_ssl()
		);
		if ( is_wp_error( $user ) ) {
			self::add_message( __( 'Invalid email/username or password.', 'kingdom-church-suite' ) );
			return;
		}
		// Church staff go to the Church Admin, partners to their dashboard.
		$home     = user_can( $user, 'manage_kcs' ) && ! KCS_Partners::get_by_user( $user->ID ) ? kcs_page_url( 'church_admin' ) : kcs_page_url( 'partner_dashboard' );
		$redirect = ! empty( $data['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( $data['redirect_to'] ), $home ) : $home;
		if ( KCS_Partners::get_by_user( $user->ID ) && self::is_entry_page( $redirect ) ) {
			$redirect = kcs_page_url( 'partner_dashboard' );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Registration.
	 *
	 * @param array $data Data.
	 */
	private static function register( $data ) {
		if ( ! kcs_rate_limit( 'register', 20, 3600 ) ) {
			self::add_message( __( 'Too many attempts. Please try again later.', 'kingdom-church-suite' ) );
			return;
		}
		$logged_in = is_user_logged_in();
		if ( $logged_in ) {
			if ( kcs_is_partner() ) {
				wp_safe_redirect( kcs_page_url( 'partner_dashboard' ) );
				exit;
			}
			$data['email'] = wp_get_current_user()->user_email;
		} else {
			$pass = (string) ( $data['password'] ?? '' );
			if ( strlen( $pass ) < 8 ) {
				self::add_message( __( 'Password must be at least 8 characters.', 'kingdom-church-suite' ) );
				return;
			}
			if ( $pass !== (string) ( $data['password2'] ?? '' ) ) {
				self::add_message( __( 'Passwords do not match.', 'kingdom-church-suite' ) );
				return;
			}
		}
		if ( empty( $data['first_name'] ) ) {
			self::add_message( __( 'Please enter your first name.', 'kingdom-church-suite' ) );
			return;
		}

		$plan = ! empty( $data['plan_id'] ) ? KCS_Plans::get( (int) $data['plan_id'] ) : null;
		if ( $plan && ! $plan->is_active ) {
			$plan = null;
		}
		$args = array(
			'first_name'   => $data['first_name'] ?? '',
			'last_name'    => $data['last_name'] ?? '',
			'email'        => $data['email'] ?? '',
			'phone'        => $data['phone'] ?? '',
			'country'      => $data['country'] ?? '',
			'city'         => $data['city'] ?? '',
			'plan_id'      => $plan ? $plan->id : 0,
			'amount'       => $data['amount'] ?? '',
			'region'       => kcs_sanitize_region( $data['region'] ?? 'international' ),
			'frequency'    => ( ! $plan || $plan->allow_custom_amount ) ? ( $data['frequency'] ?? '' ) : '',
			'password'     => $data['password'] ?? '',
			'email_opt_in' => ! empty( $data['email_opt_in'] ),
		);
		$id   = KCS_Partners::create( $args, true );
		if ( is_wp_error( $id ) ) {
			self::add_message( $id->get_error_message() );
			return;
		}
		if ( ! $logged_in ) {
			$partner = KCS_Partners::get( $id );
			wp_set_current_user( $partner->user_id );
			wp_set_auth_cookie( $partner->user_id, true, is_ssl() );
		}
		wp_safe_redirect( add_query_arg( array( 'welcome' => 1, 'kcs_action' => 'pay' ), kcs_page_url( 'partner_dashboard' ) ) );
		exit;
	}

	/**
	 * Profile update.
	 *
	 * @param array $data Data.
	 */
	private static function profile( $data ) {
		$partner = KCS_Partners::get_by_user( get_current_user_id() );
		if ( ! $partner ) {
			return;
		}
		$update = array(
			'first_name'   => $data['first_name'] ?? $partner->first_name,
			'last_name'    => $data['last_name'] ?? $partner->last_name,
			'phone'        => $data['phone'] ?? '',
			'country'      => $data['country'] ?? '',
			'city'         => $data['city'] ?? '',
			'address'      => $data['address'] ?? '',
			'email_opt_in' => ! empty( $data['email_opt_in'] ),
		);
		// Partners may change their commitment (amount/frequency/plan).
		if ( isset( $data['plan_id'] ) ) {
			$plan = KCS_Plans::get( (int) $data['plan_id'] );
			if ( $plan && $plan->is_active ) {
				$update['plan_id'] = $plan->id;
				if ( ! KCS_Plans::is_custom( $plan ) ) {
					$price               = KCS_Plans::price( $plan, kcs_currency_region( $partner->currency ) );
					$update['amount']    = $price['amount'];
					$update['currency']  = $price['currency'];
					$update['frequency'] = $plan->frequency;
				}
			}
		}
		if ( isset( $data['amount'] ) && ( empty( $update['plan_id'] ) || ! isset( $update['amount'] ) ) ) {
			$amount = (float) $data['amount'];
			if ( $amount > 0 ) {
				$update['amount'] = $amount;
			}
			if ( ! empty( $data['frequency'] ) ) {
				$update['frequency'] = $data['frequency'];
			}
			if ( ! empty( $data['region'] ) ) {
				$update['currency'] = kcs_region_currency( $data['region'] );
			}
		}
		if ( 'paused' === $partner->status && ! empty( $data['resume'] ) ) {
			$update['status'] = 'active';
		} elseif ( ! empty( $data['pause'] ) && in_array( $partner->status, array( 'active', 'overdue' ), true ) ) {
			$update['status'] = 'paused';
		}
		if ( isset( $update['amount'] ) || isset( $update['plan_id'] ) ) {
			KCS_Partners::track_upgrade( $partner, $update['amount'] ?? $partner->amount, $update['frequency'] ?? $partner->frequency, $update['currency'] ?? $partner->currency, $update['plan_id'] ?? $partner->plan_id );
		}
		KCS_Partners::update( $partner->id, $update );
		wp_update_user(
			array(
				'ID'         => get_current_user_id(),
				'first_name' => sanitize_text_field( $update['first_name'] ),
				'last_name'  => sanitize_text_field( $update['last_name'] ),
			)
		);
		if ( $update['email_opt_in'] ) {
			KCS_Newsletter::subscribe( $partner->email, trim( $update['first_name'] . ' ' . $update['last_name'] ), 'partner', true );
		} else {
			$sub = KCS_Newsletter::get_by_email( $partner->email );
			if ( $sub ) {
				KCS_Newsletter::unsubscribe( $sub->token );
			}
		}
		self::add_message( __( 'Your profile has been updated.', 'kingdom-church-suite' ), 'success' );
	}

	/**
	 * Upgrade / downgrade / switch plan from the dashboard.
	 *
	 * @param array $data Data.
	 */
	private static function change_plan( $data ) {
		$partner = KCS_Partners::get_by_user( get_current_user_id() );
		if ( ! $partner ) {
			return;
		}
		$result = KCS_Partners::change_plan( $partner, absint( $data['plan_id'] ?? 0 ), (float) ( $data['amount'] ?? 0 ), sanitize_key( $data['frequency'] ?? '' ), sanitize_key( $data['region'] ?? '' ) );
		if ( is_wp_error( $result ) ) {
			self::add_message( $result->get_error_message() );
			return;
		}
		wp_safe_redirect( add_query_arg( array( 'kcs_action' => 'pay', 'plan_changed' => $result ), kcs_page_url( 'partner_dashboard' ) ) );
		exit;
	}

	/**
	 * Password change.
	 *
	 * @param array $data Data.
	 */
	private static function password( $data ) {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return;
		}
		if ( ! wp_check_password( (string) ( $data['current'] ?? '' ), $user->user_pass, $user->ID ) ) {
			self::add_message( __( 'Your current password is incorrect.', 'kingdom-church-suite' ) );
			return;
		}
		$new = (string) ( $data['new'] ?? '' );
		if ( strlen( $new ) < 8 || $new !== (string) ( $data['new2'] ?? '' ) ) {
			self::add_message( __( 'New passwords must match and be at least 8 characters.', 'kingdom-church-suite' ) );
			return;
		}
		wp_set_password( $new, $user->ID );
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		self::add_message( __( 'Password changed successfully.', 'kingdom-church-suite' ), 'success' );
	}
}
