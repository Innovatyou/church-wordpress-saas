<?php
/**
 * Zenith Bank GlobalPay (hosted checkout, verified with GlobalPay's API).
 *
 * Start: POST /paymentgateway/generate-payment-link (header "apikey") returns data.checkoutUrl.
 * Check: GET /paymentgateway/query-single-transaction-by-merchant-reference/{our reference}
 * returns a list; a payment counts only when transactionStatus is "Successful" and the amount
 * and currency match. GlobalPay does not document a signed webhook, so payments are confirmed by
 * asking GlobalPay (on return, while the thank-you page waits, and from "Check gateway").
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * GlobalPay gateway.
 */
class KCS_Gateway_GlobalPay extends KCS_Gateway {

	/**
	 * API base.
	 */
	const API = 'https://paygw.globalpay.com.ng/globalpay-paymentgateway/api/paymentgateway';

	/**
	 * GlobalPay needs the giver's phone number.
	 *
	 * @var bool
	 */
	public $requires_phone = true;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id         = 'globalpay';
		$this->title      = 'Zenith GlobalPay';
		$this->currencies = array( 'NGN', 'USD', 'EUR', 'GBP' );
	}

	/**
	 * Fields.
	 *
	 * @return array
	 */
	protected function fields() {
		return array(
			'api_key'    => array( 'label' => __( 'API key (public key)', 'kingdom-church-suite' ), 'type' => 'password', 'keep' => true, 'desc' => __( 'GlobalPay dashboard → Settings → API keys. Used to start and check payments.', 'kingdom-church-suite' ) ),
			'currencies' => array( 'label' => __( 'Currencies enabled on your GlobalPay account', 'kingdom-church-suite' ), 'type' => 'text', 'default' => 'NGN', 'desc' => __( 'Comma separated: NGN, USD, EUR or GBP.', 'kingdom-church-suite' ) ),
			'gp_info'    => array( 'label' => __( 'Good to know', 'kingdom-church-suite' ), 'type' => 'info', 'desc' => __( 'Givers pay on GlobalPay’s page and come straight back; each payment is confirmed with GlobalPay before it counts. GlobalPay asks for the giver’s phone number. Your secret key is not needed.', 'kingdom-church-suite' ) ),
		);
	}

	/**
	 * No webhook (GlobalPay does not document a signed one).
	 *
	 * @return array
	 */
	public function settings_fields() {
		$fields = parent::settings_fields();
		unset( $fields[ $this->id . '_webhook_info' ] );
		return $fields;
	}

	/**
	 * On only with a key.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return parent::is_enabled() && '' !== trim( (string) $this->opt( 'api_key' ) );
	}

	/**
	 * Currencies the church's account takes.
	 *
	 * @return array
	 */
	public function currency_list() {
		$list = array_intersect( array_filter( array_map( 'strtoupper', array_map( 'trim', explode( ',', (string) $this->opt( 'currencies', 'NGN' ) ) ) ) ), $this->currencies );
		return $list ? array_values( $list ) : array( 'NGN' );
	}

	/**
	 * Currency accepted?
	 *
	 * @param string $currency Code.
	 * @return bool
	 */
	public function supports_currency( $currency ) {
		return in_array( strtoupper( $currency ), $this->currency_list(), true );
	}

	/**
	 * Headers.
	 *
	 * @return array
	 */
	private function headers() {
		return array(
			'apikey'   => trim( (string) $this->opt( 'api_key' ) ),
			'language' => 'en',
		);
	}

	/**
	 * Start a payment: GlobalPay returns its checkout page.
	 *
	 * @param object $payment Payment.
	 * @return array|WP_Error
	 */
	public function initialize( $payment ) {
		$phone = preg_replace( '/[^0-9+]/', '', (string) $payment->phone );
		if ( strlen( $phone ) < 7 ) {
			return new WP_Error( 'kcs_globalpay_phone', __( 'Please enter your phone number to pay with Zenith GlobalPay.', 'kingdom-church-suite' ) );
		}
		$parts = preg_split( '/\s+/', trim( (string) $payment->name ), 2 );
		$res   = $this->http(
			'POST',
			self::API . '/generate-payment-link',
			array(
				'headers'      => $this->headers(),
				'allow_errors' => true,
				'body'         => array(
					'amount'                       => round( (float) $payment->amount, 2 ),
					'merchantTransactionReference' => $payment->reference,
					'redirectUrl'                  => $this->callback_url( $payment ),
					'customer'                     => array(
						'firstName'    => $parts[0] ?? '',
						'lastName'     => $parts[1] ?? '',
						'currency'     => strtoupper( $payment->currency ),
						'phoneNumber'  => $phone,
						'address'      => '',
						'emailAddress' => $payment->email,
					),
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$url = (string) kcs_array_get( $res, 'data.checkoutUrl' );
		if ( true !== kcs_array_get( $res, 'isSuccessful' ) || 0 !== strpos( $url, 'https://' ) ) {
			$this->log( 'generate-payment-link: ' . wp_json_encode( $res ) );
			$why = (string) kcs_array_get( $res, 'error.responseDescription' );
			/* translators: %s: reason from GlobalPay */
			return new WP_Error( 'kcs_globalpay', $why ? sprintf( __( 'Zenith GlobalPay could not start the payment: %s', 'kingdom-church-suite' ), $why ) : __( 'Could not start Zenith GlobalPay checkout. Please try again or choose another method.', 'kingdom-church-suite' ) );
		}
		return array( 'redirect_url' => $url, 'txn_id' => (string) kcs_array_get( $res, 'data.transactionReference' ) );
	}

	/**
	 * Ask GlobalPay how the payment went (by our reference).
	 *
	 * @param object $payment Payment.
	 * @param array  $params  Return parameters (not trusted).
	 * @return array|WP_Error
	 */
	public function verify( $payment, $params ) {
		$res = $this->http( 'GET', self::API . '/query-single-transaction-by-merchant-reference/' . rawurlencode( $payment->reference ), array( 'headers' => $this->headers(), 'allow_errors' => true ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( true !== kcs_array_get( $res, 'isSuccessful' ) ) {
			return array( 'status' => 'pending' ); // Not found yet (e.g. the giver has not paid).
		}
		$rows = kcs_array_get( $res, 'data' );
		$rows = isset( $rows['merchantTxnref'] ) ? array( $rows ) : (array) $rows;
		$seen = 'pending';
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || (string) ( $row['merchantTxnref'] ?? '' ) !== (string) $payment->reference ) {
				continue;
			}
			$status = strtolower( (string) ( $row['transactionStatus'] ?? '' ) );
			if ( 'successful' === $status ) {
				// amountFromMerchant is what we asked for; with "ChargeCustomer" the giver also paid the fee.
				return array(
					'status'   => 'completed',
					'txn_id'   => (string) ( $row['retrievalReferenceNumber'] ?? '' ),
					'amount'   => (float) ( $row['amountFromMerchant'] ?? 0 ),
					'currency' => strtoupper( (string) ( $row['currency'] ?? '' ) ),
				);
			}
			if ( in_array( $status, array( 'failed', 'declined', 'cancelled', 'reversed' ), true ) ) {
				$seen = 'failed';
			}
		}
		return array( 'status' => $seen );
	}
}
