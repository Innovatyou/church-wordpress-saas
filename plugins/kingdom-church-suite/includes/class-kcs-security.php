<?php
/**
 * Website security against bots and card testing.
 *
 * - Every public form (giving, prayer, stories, contact, visits, newsletter, partner sign-up)
 *   carries an invisible bot check: a signed token the page fetches with JavaScript, which must
 *   be a few seconds old when the form is sent. Optional Cloudflare Turnstile adds a visible
 *   "I am human" check on giving forms or on all forms.
 * - Giving: a device may start only a few checkouts in a short time and per day, an email
 *   only a few per day, and a device whose checkouts keep failing (the sign of stolen cards
 *   being tested) is paused from giving online for a day.
 * - Logins: five wrong passwords lock that device out for 30 minutes.
 * - Hardening: XML-RPC off, user names hidden from bots, safe browser headers.
 *
 * Blocked attempts are listed in Church Admin → Security.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Security.
 */
class KCS_Security {

	const LOG = 'kcs_security_log';

	/**
	 * Public form endpoints that need the bot check: route regex => 'payments' | 'forms'.
	 *
	 * @return array
	 */
	private static function protected_routes() {
		return array(
			'#^/kcs/v1/payments/initialize$#'                  => 'payments',
			'#^/kcs/v1/prayer-requests$#'                      => 'forms',
			'#^/kcs/v1/prayer-requests/\d+/encouragements$#'   => 'forms',
			'#^/kcs/v1/stories$#'                              => 'forms',
			'#^/kcs/v1/testimonies$#'                          => 'forms',
			'#^/kcs/v1/visits$#'                               => 'forms',
			'#^/kcs/v1/visit-questionnaire$#'                  => 'forms',
			'#^/kcs/v1/contact$#'                              => 'forms',
			'#^/kcs/v1/subscribe$#'                            => 'forms',
		);
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'check_rest' ), 5, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 11 );
		// Logins.
		add_filter( 'authenticate', array( __CLASS__, 'login_gate' ), 30, 1 );
		add_action( 'wp_login_failed', array( __CLASS__, 'login_failed' ) );
		add_action( 'wp_login', array( __CLASS__, 'login_ok' ) );
		// Hardening. XML-RPC is an old remote-publishing door bots use to guess passwords.
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && ! apply_filters( 'kcs_allow_xmlrpc', false ) ) {
			status_header( 403 );
			exit;
		}
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		add_filter( 'wp_headers', array( __CLASS__, 'headers' ) );
		add_filter( 'rest_endpoints', array( __CLASS__, 'hide_users' ) );
		add_action( 'template_redirect', array( __CLASS__, 'no_author_scan' ), 1 );
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ---------------------------------------------------------------- */

	/**
	 * Current settings.
	 *
	 * @return array
	 */
	public static function settings() {
		$ks = function ( $k ) {
			return function_exists( 'ks_get' ) ? (string) ks_get( $k ) : '';
		};
		$site   = trim( (string) kcs_get_option( 'turnstile_site', '' ) );
		$secret = trim( (string) kcs_get_option( 'turnstile_secret', '' ) );
		if ( '' === $site || '' === $secret ) {
			// The platform's keys (Ministry OS Cloud → Settings → Payments) cover every church.
			$site   = $ks( 'turnstile_site' );
			$secret = $ks( 'turnstile_secret' );
		}
		return array(
			'turnstile'      => in_array( kcs_get_option( 'turnstile_mode', 'payments' ), array( 'off', 'payments', 'all' ), true ) ? kcs_get_option( 'turnstile_mode', 'payments' ) : 'payments',
			'site_key'       => $site,
			'secret'         => $secret,
			'own_keys'       => '' !== trim( (string) kcs_get_option( 'turnstile_site', '' ) ),
			'pay_per_15'     => max( 1, (int) kcs_get_option( 'sec_pay_15min', 4 ) ),
			'pay_per_day'    => max( 1, (int) kcs_get_option( 'sec_pay_day', 12 ) ),
			'email_per_day'  => max( 1, (int) kcs_get_option( 'sec_email_day', 6 ) ),
			'fails_to_block' => max( 2, (int) kcs_get_option( 'sec_fail_block', 3 ) ),
			'block_hours'    => max( 1, (int) kcs_get_option( 'sec_block_hours', 24 ) ),
		);
	}

	/**
	 * Turnstile is switched on for this kind of form.
	 *
	 * @param string $level payments|forms.
	 * @return bool
	 */
	public static function turnstile_on( $level ) {
		$s = self::settings();
		if ( '' === $s['site_key'] || '' === $s['secret'] || 'off' === $s['turnstile'] ) {
			return false;
		}
		return 'all' === $s['turnstile'] || 'payments' === $level;
	}

	/* ------------------------------------------------------------------
	 * Device identity and the log
	 * ---------------------------------------------------------------- */

	/**
	 * A short, salted hash of the visitor's IP (the IP itself is not stored).
	 *
	 * @param string $ip IP (default: current visitor).
	 * @return string
	 */
	public static function ip_hash( $ip = '' ) {
		return substr( hash_hmac( 'sha256', $ip ? $ip : kcs_client_ip(), wp_salt( 'auth' ) ), 0, 16 );
	}

	/**
	 * Record a blocked attempt for Church Admin → Security.
	 *
	 * @param string $type   pay_limit|pay_block|email_limit|bot|login.
	 * @param string $detail Detail.
	 */
	public static function log( $type, $detail = '' ) {
		$log = (array) get_option( self::LOG, array() );
		array_unshift(
			$log,
			array(
				'time'   => current_time( 'mysql' ),
				'type'   => $type,
				'device' => self::ip_hash(),
				'detail' => mb_substr( wp_strip_all_tags( $detail ), 0, 200 ),
			)
		);
		update_option( self::LOG, array_slice( $log, 0, 100 ), false );
	}

	/**
	 * Devices currently paused from giving online.
	 *
	 * @return array device => array( until, reason ).
	 */
	public static function blocked() {
		$all = (array) get_option( 'kcs_blocked_devices', array() );
		$now = time();
		$out = array_filter(
			$all,
			function ( $b ) use ( $now ) {
				return is_array( $b ) && (int) $b['until'] > $now;
			}
		);
		if ( count( $out ) !== count( $all ) ) {
			update_option( 'kcs_blocked_devices', $out, false );
		}
		return $out;
	}

	/**
	 * Pause a device from giving online.
	 *
	 * @param string $device Device hash.
	 * @param string $reason Reason.
	 */
	public static function block( $device, $reason ) {
		$all            = self::blocked();
		$all[ $device ] = array( 'until' => time() + self::settings()['block_hours'] * HOUR_IN_SECONDS, 'reason' => $reason, 'since' => current_time( 'mysql' ) );
		update_option( 'kcs_blocked_devices', $all, false );
	}

	/**
	 * Let a device give again.
	 *
	 * @param string $device Device hash.
	 */
	public static function unblock( $device ) {
		$all = self::blocked();
		unset( $all[ $device ] );
		update_option( 'kcs_blocked_devices', $all, false );
	}

	/* ------------------------------------------------------------------
	 * Invisible bot check
	 * ---------------------------------------------------------------- */

	/**
	 * GET /kcs/v1/guard — a signed, time-stamped token for this visitor.
	 */
	public static function routes() {
		register_rest_route(
			'kcs/v1',
			'/guard',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					$t   = time();
					$res = rest_ensure_response( array( 'token' => $t . '.' . self::sign( $t ) ) );
					$res->header( 'Cache-Control', 'no-store, private' );
					return $res;
				},
			)
		);
	}

	/**
	 * Signature for a token time.
	 *
	 * @param int $t Time.
	 * @return string
	 */
	private static function sign( $t ) {
		return substr( hash_hmac( 'sha256', $t . '|' . kcs_client_ip() . '|' . get_current_blog_id(), wp_salt( 'nonce' ) ), 0, 32 );
	}

	/**
	 * Is the token genuine, from this visitor, and old enough to come from a person?
	 *
	 * @param string $token Token.
	 * @return bool
	 */
	public static function token_ok( $token ) {
		$parts = explode( '.', (string) $token );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
			return false;
		}
		$age = time() - (int) $parts[0];
		return $age >= 2 && $age <= 6 * HOUR_IN_SECONDS && hash_equals( self::sign( (int) $parts[0] ), $parts[1] );
	}

	/**
	 * Ask Cloudflare whether the Turnstile answer is good.
	 *
	 * @param string $answer Turnstile response.
	 * @return bool
	 */
	public static function turnstile_ok( $answer ) {
		if ( '' === $answer ) {
			return false;
		}
		$res = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body'    => array( 'secret' => self::settings()['secret'], 'response' => $answer, 'remoteip' => kcs_client_ip() ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return true; // Cloudflare unreachable: do not stop real givers; the other checks still apply.
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return is_array( $data ) && ! empty( $data['success'] );
	}

	/**
	 * Check protected REST requests before they run.
	 *
	 * @param mixed           $result  Result so far.
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function check_rest( $result, $server, $request ) {
		if ( null !== $result || 'POST' !== $request->get_method() ) {
			return $result;
		}
		$level = '';
		foreach ( self::protected_routes() as $re => $l ) {
			if ( preg_match( $re, $request->get_route() ) ) {
				$level = $l;
				break;
			}
		}
		if ( '' === $level || current_user_can( 'manage_kcs' ) ) {
			return $result;
		}
		if ( ! self::token_ok( (string) $request->get_header( 'x_kcs_guard' ) ) ) {
			self::log( 'bot', $request->get_route() );
			return new WP_Error( 'kcs_guard', __( 'Please wait a moment and try again. If this keeps happening, refresh the page.', 'kingdom-church-suite' ), array( 'status' => 403 ) );
		}
		if ( self::turnstile_on( $level ) && ! self::turnstile_ok( (string) $request->get_header( 'x_kcs_turnstile' ) ) ) {
			self::log( 'bot', __( 'Human check not passed', 'kingdom-church-suite' ) . ' · ' . $request->get_route() );
			return new WP_Error( 'kcs_turnstile', __( 'Please complete the “I am human” check and try again.', 'kingdom-church-suite' ), array( 'status' => 403 ) );
		}
		if ( 'payments' === $level ) {
			$why = self::payment_gate( (string) $request->get_param( 'email' ), (string) $request->get_param( 'gateway' ) );
			if ( $why ) {
				return new WP_Error( 'kcs_pay_limit', $why, array( 'status' => 429 ) );
			}
		}
		return $result;
	}

	/**
	 * Card-testing protection: may this device / email start another checkout?
	 *
	 * @param string $email   Email.
	 * @param string $gateway Payment method chosen.
	 * @return string Reason to stop, or ''.
	 */
	public static function payment_gate( $email, $gateway = '' ) {
		global $wpdb;
		$s      = self::settings();
		$device = self::ip_hash();
		$t      = kcs_table( 'payments' );
		$stop   = __( 'Online giving is paused on this device for a while after several unsuccessful payments. Please contact the church, or give by bank transfer.', 'kingdom-church-suite' );
		// Bank transfer and payment links cannot test cards: a paused device may still use them.
		$manual = in_array( sanitize_key( $gateway ), KCS_Transfers::MANUAL, true );
		$cards  = "gateway NOT IN ('" . implode( "','", array_map( 'esc_sql', KCS_Transfers::MANUAL ) ) . "')";
		if ( ! $manual && isset( self::blocked()[ $device ] ) ) {
			self::log( 'pay_block', __( 'Paused device tried again', 'kingdom-church-suite' ) );
			return $stop;
		}
		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$q15 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE ip_hash = %s AND created_at >= %s", $device, gmdate( 'Y-m-d H:i:s', $now - 15 * MINUTE_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$day = gmdate( 'Y-m-d H:i:s', $now - DAY_IN_SECONDS );
		$qd  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE ip_hash = %s AND created_at >= %s", $device, $day ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// Unsuccessful card checkouts: failed, cancelled, or never paid after 15 minutes (bank transfers wait longer, so they do not count).
		$bad = $manual ? 0 : (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE ip_hash = %s AND $cards AND created_at >= %s AND ( status IN ('failed','cancelled') OR ( status = 'pending' AND created_at < %s ) )", $device, $day, gmdate( 'Y-m-d H:i:s', $now - 15 * MINUTE_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $bad >= $s['fails_to_block'] ) {
			/* translators: %d: count */
			$reason = sprintf( __( '%d unsuccessful payments in 24 hours (possible card testing)', 'kingdom-church-suite' ), $bad );
			self::block( $device, $reason );
			self::log( 'pay_block', $reason );
			self::alert( $reason );
			return $stop;
		}
		if ( $q15 >= $s['pay_per_15'] || $qd >= $s['pay_per_day'] ) {
			/* translators: 1: last 15 minutes, 2: last day */
			self::log( 'pay_limit', sprintf( __( '%1$d checkouts in 15 minutes, %2$d today', 'kingdom-church-suite' ), $q15, $qd ) );
			return __( 'Too many payment attempts from this device. Please wait a little and try again.', 'kingdom-church-suite' );
		}
		$email = sanitize_email( $email );
		if ( $email ) {
			$qe = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE email = %s AND created_at >= %s AND status <> 'completed'", $email, $day ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $qe >= $s['email_per_day'] ) {
				self::log( 'email_limit', $email );
				return __( 'Too many unfinished payments with this email today. Please try again tomorrow or contact the church.', 'kingdom-church-suite' );
			}
		}
		return '';
	}

	/**
	 * Email the church once a day when a device is paused.
	 *
	 * @param string $reason Reason.
	 */
	private static function alert( $reason ) {
		if ( get_transient( 'kcs_sec_alert' ) ) {
			return;
		}
		set_transient( 'kcs_sec_alert', 1, DAY_IN_SECONDS );
		KCS_Email::send(
			KCS_Email::admin_recipients(),
			__( 'Security: suspicious payments were stopped', 'kingdom-church-suite' ),
			'<p>' . esc_html__( 'A device on your website made several unsuccessful payments in a short time — usually someone testing stolen cards. It has been paused from giving online.', 'kingdom-church-suite' ) . '</p><p><strong>' . esc_html( $reason ) . '</strong></p><p>' . esc_html__( 'No action is needed. You can see and undo blocks in Church Admin → Security.', 'kingdom-church-suite' ) . '</p><p><a class="btn" href="' . esc_url( KCS_Church_Admin::url( 'security' ) ) . '">' . esc_html__( 'Open Security', 'kingdom-church-suite' ) . '</a></p>'
		);
	}

	/**
	 * Unsuccessful checkouts per device today, for Giving received.
	 *
	 * @param string[] $devices Device hashes.
	 * @return array device => count.
	 */
	public static function attempts( $devices ) {
		global $wpdb;
		$devices = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $devices ) ) ) );
		if ( ! $devices ) {
			return array();
		}
		$in  = implode( ',', array_fill( 0, count( $devices ), '%s' ) );
		$out = array();
		$sql = 'SELECT ip_hash, COUNT(*) c FROM ' . kcs_table( 'payments' ) . " WHERE ip_hash IN ($in) AND gateway NOT IN ('" . implode( "','", array_map( 'esc_sql', KCS_Transfers::MANUAL ) ) . "') AND created_at >= %s AND status <> 'completed' GROUP BY ip_hash";
		foreach ( $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $devices, array( gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS ) ) ) ) ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DateTime.CurrentTimeTimestamp.Requested
			$out[ $r->ip_hash ] = (int) $r->c;
		}
		return $out;
	}

	/**
	 * Front end: the bot check script settings, and Turnstile when on.
	 */
	public static function assets() {
		$s = self::settings();
		wp_localize_script(
			'kcs-public',
			'KCS_GUARD',
			array(
				'site'  => $s['site_key'] && $s['secret'] && 'off' !== $s['turnstile'] ? $s['site_key'] : '',
				'mode'  => $s['turnstile'],
				'url'   => esc_url_raw( rest_url( 'kcs/v1/guard' ) ),
			)
		);
		if ( self::turnstile_on( 'payments' ) ) {
			wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
	}

	/* ------------------------------------------------------------------
	 * Logins
	 * ---------------------------------------------------------------- */

	/**
	 * Refuse logins from a locked-out device.
	 *
	 * @param WP_User|WP_Error|null $user User.
	 * @return WP_User|WP_Error|null
	 */
	public static function login_gate( $user ) {
		$until = (int) get_site_transient( 'kcs_login_lock_' . self::ip_hash() );
		if ( $until > time() ) {
			/* translators: %d: minutes */
			return new WP_Error( 'kcs_locked', sprintf( __( 'Too many wrong passwords. For your safety, please wait %d minutes and try again, or reset your password.', 'kingdom-church-suite' ), max( 1, (int) ceil( ( $until - time() ) / 60 ) ) ) );
		}
		return $user;
	}

	/**
	 * Count a wrong password; lock the device after five.
	 *
	 * @param string $username User name tried.
	 */
	public static function login_failed( $username ) {
		$key   = 'kcs_login_fail_' . self::ip_hash();
		$fails = (int) get_site_transient( $key ) + 1;
		set_site_transient( $key, $fails, 15 * MINUTE_IN_SECONDS );
		if ( $fails >= 5 ) {
			set_site_transient( 'kcs_login_lock_' . self::ip_hash(), time() + 30 * MINUTE_IN_SECONDS, 30 * MINUTE_IN_SECONDS );
			delete_site_transient( $key );
			/* translators: %s: user name */
			self::log( 'login', sprintf( __( 'Locked for 30 minutes after 5 wrong passwords (last tried “%s”)', 'kingdom-church-suite' ), sanitize_user( (string) $username ) ) );
		}
	}

	/**
	 * Clear the count after a good login.
	 */
	public static function login_ok() {
		delete_site_transient( 'kcs_login_fail_' . self::ip_hash() );
	}

	/* ------------------------------------------------------------------
	 * Hardening
	 * ---------------------------------------------------------------- */

	/**
	 * Safe browser headers.
	 *
	 * @param array $headers Headers.
	 * @return array
	 */
	public static function headers( $headers ) {
		if ( is_admin() ) {
			return $headers;
		}
		$headers['X-Content-Type-Options'] = 'nosniff';
		$headers['X-Frame-Options']        = 'SAMEORIGIN';
		$headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';
		$headers['Permissions-Policy']     = 'camera=(), microphone=(), interest-cohort=()';
		return $headers;
	}

	/**
	 * Hide the user list from visitors (bots read it to guess logins).
	 *
	 * @param array $endpoints Endpoints.
	 * @return array
	 */
	public static function hide_users( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			foreach ( array_keys( $endpoints ) as $route ) {
				if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
					unset( $endpoints[ $route ] );
				}
			}
		}
		return $endpoints;
	}

	/**
	 * Stop ?author=1 scans that reveal user names.
	 */
	public static function no_author_scan() {
		if ( ! is_user_logged_in() && isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}
}
