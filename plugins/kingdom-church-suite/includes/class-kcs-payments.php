<?php
/**
 * Payments service: create, initialize, verify, complete.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Payments.
 */
class KCS_Payments {

	/**
	 * Cached gateways.
	 *
	 * @var KCS_Gateway[]|null
	 */
	private static $gateways = null;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'kcs_payment_completed', array( __CLASS__, 'notify_completed' ), 10, 1 );
		add_action( 'kcs_recheck_payments', array( __CLASS__, 'recheck_pending' ) );
		if ( ! wp_next_scheduled( 'kcs_recheck_payments' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'kcs_recheck_payments' );
		}
	}

	/**
	 * Hourly: ask the payment providers about online payments still pending, in case the giver paid
	 * but closed the page before coming back. After two days with nothing paid, the payment is marked
	 * failed (it can still be confirmed later with "Check gateway" or "Mark received").
	 */
	public static function recheck_pending() {
		global $wpdb;
		$table  = kcs_table( 'payments' );
		$manual = "'" . implode( "','", array_map( 'esc_sql', KCS_Transfers::MANUAL ) ) . "'";
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = 'pending' AND gateway NOT IN ($manual) AND created_at < %s ORDER BY id DESC LIMIT 40", gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 10 * MINUTE_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DateTime.CurrentTimeTimestamp.Requested
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 2 * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		foreach ( $rows as $p ) {
			if ( 'pending' === self::verify_and_update( $p ) && $p->created_at < $cutoff ) {
				$wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'failed', note = CONCAT(%s, IFNULL(note, '')) WHERE id = %d AND status = 'pending'", __( 'Not paid within 2 days. ', 'kingdom-church-suite' ), $p->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				KCS_Receipts::maybe_auto_retry( self::get( $p->id ) );
			}
		}
	}

	/**
	 * Statuses.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'pending'   => __( 'Pending', 'kingdom-church-suite' ),
			'completed' => __( 'Completed', 'kingdom-church-suite' ),
			'failed'    => __( 'Failed', 'kingdom-church-suite' ),
			'review'    => __( 'Needs review', 'kingdom-church-suite' ),
			'refunded'  => __( 'Refunded', 'kingdom-church-suite' ),
			'cancelled' => __( 'Cancelled', 'kingdom-church-suite' ),
		);
	}

	/**
	 * All registered gateways.
	 *
	 * @return KCS_Gateway[]
	 */
	public static function gateways() {
		if ( null === self::$gateways ) {
			$list = array(
				'paystack'      => new KCS_Gateway_Paystack(),
				'flutterwave'   => new KCS_Gateway_Flutterwave(),
				'globalpay'     => new KCS_Gateway_GlobalPay(),
				'paypal'        => new KCS_Gateway_PayPal(),
				'stripe'        => new KCS_Gateway_Stripe(),
				'razorpay'      => new KCS_Gateway_Razorpay(),
				'bank_transfer' => new KCS_Gateway_Bank_Transfer(),
				'payment_link'   => new KCS_Gateway_Payment_Link( 1 ),
				'payment_link_2' => new KCS_Gateway_Payment_Link( 2 ),
				'payment_link_3' => new KCS_Gateway_Payment_Link( 3 ),
				'custom'        => new KCS_Gateway_Custom(),
			);
			self::$gateways = array_filter(
				apply_filters( 'kcs_payment_gateways', $list ),
				function ( $g ) {
					return $g instanceof KCS_Gateway;
				}
			);
		}
		return self::$gateways;
	}

	/**
	 * Enabled gateways, optionally filtered by currency.
	 *
	 * @param string $currency Currency.
	 * @return KCS_Gateway[]
	 */
	public static function enabled_gateways( $currency = '' ) {
		return array_filter(
			self::ordered(),
			function ( $g ) use ( $currency ) {
				return $g->is_enabled() && ( ! $currency || $g->supports_currency( $currency ) );
			}
		);
	}

	/**
	 * All gateways in the order the church chose (Settings → Payments → Order of payment
	 * methods); ones not yet placed keep their usual order after those.
	 *
	 * @return KCS_Gateway[]
	 */
	public static function ordered() {
		$all   = self::gateways();
		$order = array_filter( array_map( 'sanitize_key', explode( ',', (string) kcs_get_option( 'gateway_order', '' ) ) ) );
		$out   = array();
		foreach ( $order as $id ) {
			if ( isset( $all[ $id ] ) ) {
				$out[ $id ] = $all[ $id ];
			}
		}
		return $out + $all;
	}

	/**
	 * Save the order of payment methods.
	 *
	 * @param string[] $ids Gateway IDs, first first.
	 */
	public static function save_order( $ids ) {
		$all = self::gateways();
		$ids = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $ids ), function ( $id ) use ( $all ) { return isset( $all[ $id ] ); } ) ) );
		kcs_update_option( 'gateway_order', implode( ',', $ids ) );
	}

	/**
	 * A gateway by ID.
	 *
	 * @param string $id ID.
	 * @return KCS_Gateway|null
	 */
	public static function gateway( $id ) {
		$all = self::gateways();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Currencies offered to givers.
	 *
	 * @return array
	 */
	public static function allowed_currencies() {
		$codes = array_filter( array_map( 'trim', explode( ',', strtoupper( (string) kcs_get_option( 'allowed_currencies', 'USD' ) ) ) ) );
		$all   = kcs_currencies();
		$out   = array();
		foreach ( $codes as $c ) {
			if ( isset( $all[ $c ] ) ) {
				$out[ $c ] = $all[ $c ];
			}
		}
		// The international (default) and local currencies are always available.
		$local = kcs_local_currency();
		if ( $local && ! isset( $out[ $local ] ) ) {
			$out = array( $local => $all[ $local ] ) + $out;
		}
		$default = kcs_get_option( 'currency', 'USD' );
		if ( isset( $all[ $default ] ) ) {
			unset( $out[ $default ] );
			$out = array( $default => $all[ $default ] ) + $out;
		}
		return $out;
	}

	/**
	 * Get payment by reference.
	 *
	 * @param string $reference Reference.
	 * @return object|null
	 */
	public static function get_by_reference( $reference ) {
		global $wpdb;
		$table = kcs_table( 'payments' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE reference = %s", $reference ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get payment by ID.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = kcs_table( 'payments' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Start a payment: validate, store a pending row and ask the gateway for a checkout URL.
	 *
	 * @param array $args gateway, amount, currency, purpose, name, email, phone, partner_id, plan_id, note.
	 * @return array|WP_Error ['reference','redirect_url'].
	 */
	public static function start( $args ) {
		global $wpdb;
		$gateway = self::gateway( $args['gateway'] ?? '' );
		if ( ! $gateway || ! $gateway->is_enabled() ) {
			return new WP_Error( 'kcs_gateway', __( 'Please choose a valid payment method.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		$currency = strtoupper( (string) ( $args['currency'] ?? kcs_get_option( 'currency', 'USD' ) ) );
		// Currencies without cents (XOF, UGX, RWF…) are charged in whole units.
		$amount   = round( (float) ( $args['amount'] ?? 0 ), in_array( $currency, kcs_zero_decimal_currencies(), true ) ? 0 : 2 );
		$min      = (float) kcs_get_option( 'min_amount', 1 );
		if ( $amount <= 0 || $amount < $min ) {
			/* translators: %s: minimum amount */
			return new WP_Error( 'kcs_amount', sprintf( __( 'The minimum amount is %s.', 'kingdom-church-suite' ), kcs_format_money( $min, $currency ) ), array( 'status' => 400 ) );
		}
		if ( ! array_key_exists( $currency, kcs_currencies() ) ) {
			return new WP_Error( 'kcs_currency', __( 'Unsupported currency.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		if ( ! $gateway->supports_currency( $currency ) ) {
			/* translators: 1: gateway, 2: currency */
			return new WP_Error( 'kcs_currency', sprintf( __( '%1$s does not support %2$s.', 'kingdom-church-suite' ), $gateway->display_title(), $currency ), array( 'status' => 400 ) );
		}
		if ( ! $gateway->allows_purpose( sanitize_key( $args['purpose'] ?? 'donation' ) ) ) {
			/* translators: %s: payment method */
			return new WP_Error( 'kcs_gateway', sprintf( __( '%s is not available for this kind of gift. Please choose another payment method.', 'kingdom-church-suite' ), $gateway->display_title() ), array( 'status' => 400 ) );
		}
		$email = sanitize_email( $args['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'kcs_email', __( 'Please enter a valid email address.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}

		$reference = kcs_generate_reference( 'partnership' === ( $args['purpose'] ?? '' ) ? 'PTN' : 'GIV' );
		$wpdb->insert(
			kcs_table( 'payments' ),
			array(
				'reference'  => $reference,
				'partner_id' => (int) ( $args['partner_id'] ?? 0 ),
				'user_id'    => get_current_user_id(),
				'plan_id'    => (int) ( $args['plan_id'] ?? 0 ),
				'name'       => sanitize_text_field( $args['name'] ?? '' ),
				'email'      => $email,
				'phone'      => sanitize_text_field( $args['phone'] ?? '' ),
				'amount'     => $amount,
				'currency'   => $currency,
				'gateway'    => $gateway->id,
				'purpose'    => sanitize_key( $args['purpose'] ?? 'donation' ),
				'status'     => 'pending',
				'note'       => sanitize_textarea_field( $args['note'] ?? '' ),
				'created_at' => current_time( 'mysql' ),
			)
		);
		$payment = self::get_by_reference( $reference );
		if ( ! $payment ) {
			return new WP_Error( 'kcs_db', __( 'Could not create payment.', 'kingdom-church-suite' ), array( 'status' => 500 ) );
		}

		$result = $gateway->initialize( $payment );
		if ( is_wp_error( $result ) ) {
			$wpdb->update( kcs_table( 'payments' ), array( 'status' => 'failed', 'note' => $result->get_error_message() ), array( 'id' => $payment->id ) );
			return $result;
		}
		if ( ! empty( $result['txn_id'] ) ) {
			$wpdb->update( kcs_table( 'payments' ), array( 'gateway_txn_id' => $result['txn_id'] ), array( 'id' => $payment->id ) );
		}
		do_action( 'kcs_payment_started', $payment );
		return array(
			'reference'    => $reference,
			'redirect_url' => $result['redirect_url'],
		);
	}

	/**
	 * Verify a payment with its gateway and update it.
	 *
	 * @param object $payment Payment.
	 * @param array  $params  Params from callback/webhook.
	 * @return string Final status.
	 */
	public static function verify_and_update( $payment, $params = array() ) {
		global $wpdb;
		// Only open payments are checked: never charge or change cancelled, refunded or completed ones.
		if ( ! in_array( $payment->status, array( 'pending', 'failed', 'review' ), true ) ) {
			return $payment->status;
		}
		$gateway = self::gateway( $payment->gateway );
		if ( ! $gateway ) {
			return $payment->status;
		}
		$result = $gateway->verify( $payment, $params );
		if ( '' !== $gateway->last_said ) {
			// Keep what the provider said, for Church Admin (a mismatch note from complete() wins).
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . kcs_table( 'payments' ) . " SET note = %s WHERE id = %d AND status IN ('pending','failed')", $gateway->last_said . ' · ' . current_time( 'j M H:i' ), $payment->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( is_wp_error( $result ) ) {
			return $payment->status;
		}
		if ( 'completed' === $result['status'] ) {
			return self::complete( $payment, $result['txn_id'] ?? '', (float) ( $result['amount'] ?? 0 ), (string) ( $result['currency'] ?? '' ) );
		}
		if ( 'failed' === $result['status'] && 'pending' === $payment->status ) {
			$wpdb->update( kcs_table( 'payments' ), array( 'status' => 'failed' ), array( 'id' => $payment->id ) );
			KCS_Receipts::maybe_auto_retry( self::get( $payment->id ) );
			return 'failed';
		}
		return $payment->status;
	}

	/**
	 * Mark a payment completed (idempotent) after validating amount & currency.
	 *
	 * @param object $payment  Payment.
	 * @param string $txn_id   Provider transaction ID.
	 * @param float  $amount   Amount actually paid.
	 * @param string $currency Currency actually paid.
	 * @param bool   $manual   Confirmed manually by an admin.
	 * @return string Resulting status.
	 */
	public static function complete( $payment, $txn_id, $amount, $currency, $manual = false ) {
		global $wpdb;
		$table = kcs_table( 'payments' );

		if ( ! $manual && ( strtoupper( $currency ) !== strtoupper( $payment->currency ) || $amount + 0.01 < (float) $payment->amount ) ) {
			// Keep gateway_txn_id as is (re-verifying needs the original checkout ID); note the provider's ID.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $table SET status = 'review', note = %s WHERE id = %d AND status IN ('pending','failed','review')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					/* translators: 1: paid amount, 2: expected amount, 3: provider transaction ID */
					sprintf( __( 'Amount mismatch: received %1$s, expected %2$s. Provider reference: %3$s', 'kingdom-church-suite' ), $amount . ' ' . $currency, $payment->amount . ' ' . $payment->currency, $txn_id ),
					$payment->id
				)
			);
			return self::get( $payment->id )->status;
		}

		// Atomic transition so callbacks and webhooks racing each other only complete once.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $table SET status = 'completed', gateway_txn_id = %s, paid_at = %s WHERE id = %d AND status IN ('pending','failed','review')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$txn_id ? $txn_id : $payment->gateway_txn_id,
				current_time( 'mysql' ),
				$payment->id
			)
		);
		if ( ! $updated ) {
			return self::get( $payment->id )->status;
		}
		$payment = self::get( $payment->id );

		if ( $payment->partner_id ) {
			$partner = KCS_Partners::get( $payment->partner_id );
			if ( $partner ) {
				KCS_Partners::apply_payment( $partner, $payment );
			}
		}
		do_action( 'kcs_payment_completed', $payment );
		KCS_Email::webhook( 'payment.completed', self::to_array( $payment ) );
		return 'completed';
	}

	/**
	 * Receipts & admin notifications.
	 *
	 * @param object $payment Payment.
	 */
	public static function notify_completed( $payment ) {
		$vars = self::email_vars( $payment );
		if ( '1' === kcs_get_option( 'send_receipts', '1' ) && $payment->email ) {
			KCS_Receipts::send( $payment );
		}
		if ( '1' === kcs_get_option( 'notify_admin_payment', '1' ) ) {
			KCS_Email::send_template( KCS_Email::admin_recipients(), 'admin_new_payment', $vars );
		}
	}

	/**
	 * Template variables.
	 *
	 * @param object $payment Payment.
	 * @return array
	 */
	public static function email_vars( $payment ) {
		$purposes = kcs_all_purposes();
		$gateway  = self::gateway( $payment->gateway );
		return array(
			'name'      => $payment->name ? $payment->name : $payment->email,
			'email'     => $payment->email,
			'amount'    => kcs_format_money( $payment->amount, $payment->currency ),
			'purpose'   => isset( $purposes[ $payment->purpose ] ) ? $purposes[ $payment->purpose ] : ucwords( str_replace( array( '-', '_' ), ' ', $payment->purpose ) ),
			'reference' => $payment->reference,
			'date'      => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $payment->paid_at ? $payment->paid_at : $payment->created_at ) ),
			'gateway'   => $gateway ? $gateway->display_title() : $payment->gateway,
			'admin_url' => admin_url( 'admin.php?page=kcs-payments&s=' . rawurlencode( $payment->reference ) ),
			'receipt_url' => KCS_Receipts::url( $payment ),
			'retry_url' => $payment->partner_id ? add_query_arg( 'kcs_action', 'pay', kcs_page_url( 'partner_dashboard' ) ) : kcs_page_url( 'give' ),
		);
	}

	/**
	 * Query payments.
	 *
	 * @param array $args search,status,gateway,partner_id,purpose,date_from,date_to,per_page,page.
	 * @return array{items:array,total:int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'search'     => '',
				'status'     => '',
				'gateway'    => '',
				'purpose'    => '',
				'partner_id' => 0,
				'user_id'    => 0,
				'date_from'  => '',
				'date_to'    => '',
				'per_page'   => 20,
				'page'       => 1,
			)
		);
		$table = kcs_table( 'payments' );
		$where = array( '1=1' );
		$vals  = array();
		if ( $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(reference LIKE %s OR email LIKE %s OR name LIKE %s OR gateway_txn_id LIKE %s)';
			array_push( $vals, $like, $like, $like, $like );
		}
		foreach ( array( 'status', 'gateway', 'purpose' ) as $col ) {
			if ( is_array( $args[ $col ] ) && $args[ $col ] ) {
				$where[] = "$col IN (" . implode( ',', array_fill( 0, count( $args[ $col ] ), '%s' ) ) . ')';
				$vals    = array_merge( $vals, array_values( $args[ $col ] ) );
			} elseif ( $args[ $col ] ) {
				$where[] = "$col = %s";
				$vals[]  = $args[ $col ];
			}
		}
		if ( $args['partner_id'] ) {
			$where[] = 'partner_id = %d';
			$vals[]  = (int) $args['partner_id'];
		}
		if ( $args['user_id'] ) {
			$where[] = 'user_id = %d';
			$vals[]  = (int) $args['user_id'];
		}
		if ( $args['date_from'] ) {
			$where[] = 'created_at >= %s';
			$vals[]  = $args['date_from'] . ' 00:00:00';
		}
		if ( $args['date_to'] ) {
			$where[] = 'created_at <= %s';
			$vals[]  = $args['date_to'] . ' 23:59:59';
		}
		$sql_w  = implode( ' AND ', $where );
		$limit  = max( 1, (int) $args['per_page'] );
		$offset = ( max( 1, (int) $args['page'] ) - 1 ) * $limit;

		// phpcs:disable WordPress.DB.PreparedSQL
		$count_sql = "SELECT COUNT(*) FROM $table WHERE $sql_w";
		$items_sql = "SELECT * FROM $table WHERE $sql_w ORDER BY id DESC LIMIT $limit OFFSET $offset";
		$total     = (int) ( $vals ? $wpdb->get_var( $wpdb->prepare( $count_sql, $vals ) ) : $wpdb->get_var( $count_sql ) );
		$items     = $vals ? $wpdb->get_results( $wpdb->prepare( $items_sql, $vals ) ) : $wpdb->get_results( $items_sql );
		// phpcs:enable

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * API representation.
	 *
	 * @param object $p Payment.
	 * @return array
	 */
	public static function to_array( $p ) {
		return array(
			'id'             => (int) $p->id,
			'reference'      => $p->reference,
			'partner_id'     => (int) $p->partner_id,
			'plan_id'        => (int) $p->plan_id,
			'name'           => $p->name,
			'email'          => $p->email,
			'amount'         => (float) $p->amount,
			'currency'       => $p->currency,
			'gateway'        => $p->gateway,
			'gateway_txn_id' => $p->gateway_txn_id,
			'purpose'        => $p->purpose,
			'status'         => $p->status,
			'note'           => $p->note,
			'created_at'     => $p->created_at,
			'paid_at'        => $p->paid_at,
		);
	}
}
