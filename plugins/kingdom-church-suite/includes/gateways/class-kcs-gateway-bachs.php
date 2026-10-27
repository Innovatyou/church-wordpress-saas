<?php
/**
 * Bachs (hosted Checkout Sessions, v1 API). https://docs.bachs.io
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bachs gateway.
 */
class KCS_Gateway_Bachs extends KCS_Gateway {

	/**
	 * The signed webhook event being handled (set by parse_webhook, read by verify).
	 *
	 * @var array
	 */
	private $event = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'bachs';
		$this->title = 'Bachs';
	}

	/**
	 * Fields.
	 *
	 * @return array
	 */
	protected function fields() {
		return array(
			'secret_key'     => array( 'label' => __( 'Secret API key', 'kingdom-church-suite' ), 'type' => 'password', 'desc' => __( 'From Bachs Developer Portal → API keys. A key starting sk_sandbox_ runs test payments; sk_live_ takes real money.', 'kingdom-church-suite' ) ),
			'webhook_secret' => array( 'label' => __( 'Webhook signing secret', 'kingdom-church-suite' ), 'type' => 'password', 'desc' => __( 'Starts with whsec_. In Bachs Developer Portal → Webhooks add the URL below, choose checkout.completed, checkout.expired, collection.succeeded and collection.failed, then copy the signing secret here.', 'kingdom-church-suite' ) ),
			'methods'        => array( 'label' => __( 'Payment methods (optional)', 'kingdom-church-suite' ), 'type' => 'text', 'desc' => __( 'Leave empty to offer every method on your Bachs account, or list some separated by commas, e.g. NGN_CARD, NGN_BANK_TRANSFER, USD_CARD.', 'kingdom-church-suite' ) ),
		);
	}

	/**
	 * API base URL: the key decides sandbox or live.
	 *
	 * @return string
	 */
	private function base() {
		return 'test' === $this->mode() ? 'https://sandbox-api.bachs.io' : 'https://api.bachs.io';
	}

	/**
	 * Auth headers.
	 *
	 * @return array
	 */
	private function headers() {
		return array( 'Authorization' => 'Bearer ' . trim( (string) $this->opt( 'secret_key' ) ) );
	}

	/**
	 * Money as Bachs wants it: a decimal string at the currency's precision.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency.
	 * @return string
	 */
	private function money( $amount, $currency ) {
		return number_format( (float) $amount, in_array( strtoupper( $currency ), $this->zero_decimal, true ) ? 0 : 2, '.', '' );
	}

	/**
	 * Initialize.
	 *
	 * @param object $payment Payment.
	 * @return array|WP_Error
	 */
	public function initialize( $payment ) {
		$customer = array_filter(
			array(
				'email'        => (string) $payment->email,
				'name'         => (string) $payment->name,
				'phone_number' => (string) $payment->phone,
			)
		);
		$body     = array(
			'pricing'     => array(
				'currency' => strtoupper( $payment->currency ),
				'amount'   => $this->money( $payment->amount, $payment->currency ),
			),
			'success_url' => $this->callback_url( $payment ),
			'cancel_url'  => add_query_arg( 'status', 'cancelled', $this->callback_url( $payment ) ),
			'reference'   => mb_substr( (string) $payment->reference, 0, 128 ),
			'metadata'    => array(
				'purpose'    => (string) $payment->purpose,
				'partner_id' => (string) (int) $payment->partner_id,
				'church'     => mb_substr( (string) kcs_get_option( 'church_name', get_bloginfo( 'name' ) ), 0, 100 ),
			),
		);
		if ( $customer ) {
			$body['customer'] = $customer;
		}
		$methods = array_values( array_filter( array_map( 'strtoupper', preg_split( '/[\s,]+/', (string) $this->opt( 'methods' ) ) ), static function ( $m ) {
			return (bool) preg_match( '/^[A-Z0-9_]+$/', $m );
		} ) );
		if ( $methods ) {
			$body['payment_method_types'] = $methods;
		}

		$res = $this->http(
			'POST',
			$this->base() . '/v1/checkout-sessions',
			array(
				// One key per payment, so a retried request never opens a second checkout.
				'headers' => $this->headers() + array( 'Idempotency-Key' => 'kcs-' . $payment->reference ),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$url = kcs_array_get( $res, 'checkout_url' );
		if ( ! $url ) {
			return new WP_Error( 'kcs_bachs', __( 'Could not start Bachs checkout.', 'kingdom-church-suite' ) );
		}
		return array(
			'redirect_url' => $url,
			'txn_id'       => (string) kcs_array_get( $res, 'checkout_id' ),
		);
	}

	/**
	 * Verify by asking Bachs for the checkout session.
	 *
	 * @param object $payment Payment.
	 * @param array  $params  Params.
	 * @return array|WP_Error
	 */
	public function verify( $payment, $params ) {
		$event = $this->event;
		$this->event = array();

		$id = (string) $payment->gateway_txn_id;
		if ( 0 !== strpos( $id, 'chk_' ) && ! empty( $params['checkout_id'] ) && preg_match( '/^chk_[A-Za-z0-9]+$/', (string) $params['checkout_id'] ) ) {
			$id = (string) $params['checkout_id'];
		}
		if ( 0 !== strpos( $id, 'chk_' ) ) {
			$this->said( __( 'not paid yet', 'kingdom-church-suite' ), __( 'no Bachs checkout was opened for this payment', 'kingdom-church-suite' ) );
			return array( 'status' => 'pending' );
		}

		$res = $this->http( 'GET', $this->base() . '/v1/checkout-sessions/' . rawurlencode( $id ), array( 'headers' => $this->headers(), 'allow_errors' => true ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code     = (int) ( $res['_code'] ?? 200 );
		$status   = strtolower( (string) kcs_array_get( $res, 'status' ) );
		$paid     = strtolower( (string) kcs_array_get( $res, 'payment_status' ) );
		$charge   = strtolower( (string) kcs_array_get( $res, 'charge.status' ) );
		$amount   = kcs_array_get( $res, 'amount' );
		$currency = kcs_array_get( $res, 'currency' );
		if ( null === $amount || '' === $amount ) {
			$amount   = kcs_array_get( $res, 'charge.amount' );
			$currency = kcs_array_get( $res, 'charge.currency' );
		}

		if ( $code < 300 && ( 'paid' === $paid || in_array( $charge, array( 'succeeded', 'accepted', 'overpaid' ), true ) ) ) {
			return array(
				'status'   => 'completed',
				'txn_id'   => $id,
				'amount'   => (float) $amount,
				'currency' => (string) $currency,
			);
		}
		if ( 401 === $code || 403 === $code ) {
			$this->said( __( 'your secret key was refused', 'kingdom-church-suite' ), __( 'save your current Bachs secret key in Settings → Payments', 'kingdom-church-suite' ) );
			return array( 'status' => 'pending' );
		}
		if ( 404 === $code ) {
			$this->said( __( 'checkout not found', 'kingdom-church-suite' ), __( 'the saved secret key may be from another Bachs account, or sandbox instead of live', 'kingdom-church-suite' ) );
			return array( 'status' => 'pending' );
		}
		if ( 'expired' === $status || 'checkout.expired' === ( $event['type'] ?? '' ) ) {
			$this->said( __( 'checkout expired', 'kingdom-church-suite' ), __( 'the giver did not pay before the Bachs checkout closed', 'kingdom-church-suite' ) );
			return array( 'status' => 'cancelled' );
		}
		if ( 'collection.failed' === ( $event['type'] ?? '' ) || in_array( $charge, array( 'failed', 'declined' ), true ) ) {
			// The checkout stays open, so a later successful try still completes this payment.
			$reason = (string) ( kcs_array_get( $event, 'data.reason' ) ? kcs_array_get( $event, 'data.reason' ) : kcs_array_get( $res, 'charge.failure_reason' ) );
			$this->said( __( 'payment failed', 'kingdom-church-suite' ), $reason );
			return array( 'status' => 'failed' );
		}
		if ( isset( $params['status'] ) && 'cancelled' === $params['status'] ) {
			$this->said( __( 'cancelled', 'kingdom-church-suite' ), __( 'the giver closed or cancelled the Bachs checkout without paying', 'kingdom-church-suite' ) );
			return array( 'status' => 'cancelled' );
		}
		if ( $code >= 400 ) {
			$this->said( __( 'could not check', 'kingdom-church-suite' ), (string) ( kcs_array_get( $res, 'message' ) ? kcs_array_get( $res, 'message' ) : kcs_array_get( $res, 'error.message' ) ) );
			return array( 'status' => 'pending' );
		}
		if ( 'failed' !== $payment->status ) {
			// A failed payment keeps the decline reason in its note while the checkout stays open.
			$this->said( __( 'not paid yet', 'kingdom-church-suite' ), $status ? $status : '' );
		}
		return array( 'status' => 'pending' );
	}

	/**
	 * Webhook: HMAC-SHA256 of "{timestamp}.{raw body}" with the endpoint's signing secret.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function parse_webhook( $request ) {
		$secret = trim( (string) $this->opt( 'webhook_secret' ) );
		$body   = $request->get_body();
		$sigs   = array();
		$t      = '';
		$v2     = (string) $request->get_header( 'x_bachs_signature_v2' );
		if ( '' !== $v2 ) {
			// t=<timestamp>,v1=<sig>[,v1=<sig>] — more than one v1 while a secret is being rotated.
			foreach ( explode( ',', $v2 ) as $part ) {
				$kv = array_map( 'trim', explode( '=', $part, 2 ) );
				if ( 2 === count( $kv ) && 't' === $kv[0] ) {
					$t = $kv[1];
				} elseif ( 2 === count( $kv ) && 'v1' === $kv[0] ) {
					$sigs[] = $kv[1];
				}
			}
		} else {
			$t      = (string) $request->get_header( 'x_bachs_timestamp' );
			$sigs[] = (string) $request->get_header( 'x_bachs_signature' );
		}
		$ok = false;
		if ( $secret && ctype_digit( $t ) && abs( time() - (int) $t ) <= 300 ) {
			$expected = hash_hmac( 'sha256', $t . '.' . $body, $secret );
			foreach ( $sigs as $sig ) {
				if ( '' !== $sig && hash_equals( $expected, strtolower( $sig ) ) ) {
					$ok = true;
				}
			}
		}
		if ( ! $ok ) {
			return new WP_Error( 'kcs_bad_signature', 'Invalid signature', array( 'status' => 400 ) );
		}

		$event       = json_decode( $body, true );
		$event       = is_array( $event ) ? $event : array();
		$this->event = $event;
		$ref         = (string) kcs_array_get( $event, 'data.reference' );
		$checkout    = (string) kcs_array_get( $event, 'data.checkout_id' );
		if ( '' === $ref && 0 === strpos( $checkout, 'chk_' ) ) {
			global $wpdb;
			$table = kcs_table( 'payments' );
			$ref   = (string) $wpdb->get_var( $wpdb->prepare( "SELECT reference FROM $table WHERE gateway = %s AND gateway_txn_id = %s LIMIT 1", $this->id, $checkout ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		}
		return array( 'reference' => $ref, 'params' => 0 === strpos( $checkout, 'chk_' ) ? array( 'checkout_id' => $checkout ) : array() );
	}

	/**
	 * Test or live, from the secret key.
	 *
	 * @return string
	 */
	public function mode() {
		$key = trim( (string) $this->opt( 'secret_key' ) );
		if ( '' === $key ) {
			return '';
		}
		return 0 === strpos( $key, 'sk_sandbox_' ) || 0 === strpos( $key, 'sk_test_' ) ? 'test' : 'live';
	}
}
