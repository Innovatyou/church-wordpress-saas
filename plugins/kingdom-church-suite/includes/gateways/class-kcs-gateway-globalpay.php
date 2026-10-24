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
	 * First and last name as GlobalPay accepts them: letters only, both filled.
	 * Titles ("Pastor", "Dr.") are dropped; a single name is used for both.
	 *
	 * @param string $name  Name typed by the giver.
	 * @param string $email Email (fallback).
	 * @return array [ first, last ]
	 */
	public static function names( $name, $email = '' ) {
		$titles = array( 'mr', 'mrs', 'ms', 'miss', 'dr', 'prof', 'pastor', 'pst', 'rev', 'revd', 'bishop', 'apostle', 'evangelist', 'evang', 'prophet', 'prophetess', 'deacon', 'deaconess', 'elder', 'bro', 'brother', 'sis', 'sister', 'chief', 'engr', 'barr', 'hon', 'sir', 'madam', 'mummy', 'daddy' );
		$clean  = function ( $text ) {
			$text = str_replace( array( "'", '’' ), '', remove_accents( $text ) );
			return trim( preg_replace( '/[^A-Za-z]+/', ' ', $text ) );
		};
		$words = array();
		foreach ( explode( ' ', $clean( $name ) ) as $w ) {
			if ( '' !== $w && ! in_array( strtolower( $w ), $titles, true ) ) {
				$words[] = $w;
			}
		}
		if ( ! $words ) {
			$local = $clean( str_replace( array( '.', '_', '-' ), ' ', strstr( $email, '@', true ) ?: '' ) );
			$words = '' !== $local ? array_map( 'ucfirst', explode( ' ', $local ) ) : array( 'Church', 'Giver' );
		}
		$first = substr( array_shift( $words ), 0, 50 );
		$last  = $words ? substr( implode( ' ', $words ), 0, 50 ) : $first;
		return array( $first, $last );
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
		$parts = self::names( (string) $payment->name, (string) $payment->email );
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
						'firstName'    => $parts[0],
						'lastName'     => $parts[1],
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
			$this->last_said = __( 'GlobalPay could not be reached', 'kingdom-church-suite' ) . ': ' . $res->get_error_message();
			return $res;
		}
		// GlobalPay documents two reply shapes: { isSuccessful, data: {…} } and
		// { responseCode: "0000", data: [ {…} ] }; reference, amount and currency field names differ too.
		$code = (string) kcs_array_get( $res, 'responseCode' );
		$ok   = true === kcs_array_get( $res, 'isSuccessful' ) || in_array( $code, array( '0000', '00' ), true );
		if ( ! $ok ) {
			$why  = (string) ( kcs_array_get( $res, 'error.responseDescription' ) ? kcs_array_get( $res, 'error.responseDescription' ) : kcs_array_get( $res, 'responseDescription' ) );
			$err  = (string) ( kcs_array_get( $res, 'error.responseCode' ) ? kcs_array_get( $res, 'error.responseCode' ) : $code );
			$this->last_said = 'ZEN0112' === $err
				? __( 'GlobalPay rejected the API key: paste the current live API key in Settings → Payments → Zenith GlobalPay', 'kingdom-church-suite' )
				: __( 'No payment found at GlobalPay yet: the giver has not completed the checkout', 'kingdom-church-suite' ) . ( $why ? ' (' . $why . ')' : '' );
			$this->log( 'query ' . $payment->reference . ': ' . wp_json_encode( $res ) );
			return array( 'status' => 'pending' );
		}
		$rows = kcs_array_get( $res, 'data' );
		$rows = is_array( $rows ) && isset( $rows['transactionStatus'] ) ? array( $rows ) : (array) $rows;
		$rows = array_values( array_filter( $rows, 'is_array' ) );
		$seen = 'pending';
		$said = array();
		foreach ( $rows as $row ) {
			$ref = (string) ( $row['merchantTxnref'] ?? $row['merchantTransactionReference'] ?? $row['merchantTransRef'] ?? '' );
			// We asked by our own reference, so a row without one (only one row) is ours.
			if ( '' !== $ref ? $ref !== (string) $payment->reference : count( $rows ) > 1 ) {
				continue;
			}
			$status = strtolower( trim( (string) ( $row['transactionStatus'] ?? $row['status'] ?? '' ) ) );
			$said[] = (string) ( $row['transactionStatus'] ?? $row['status'] ?? '?' );
			if ( in_array( $status, array( 'successful', 'success', 'approved', 'completed' ), true ) ) {
				$this->last_said = __( 'GlobalPay: Successful', 'kingdom-church-suite' );
				// amountFromMerchant is what we asked for; with "ChargeCustomer" the giver also paid the fee.
				$currency = strtoupper( (string) ( $row['currency'] ?? $row['transactionCurrency'] ?? '' ) );
				return array(
					'status'   => 'completed',
					'txn_id'   => (string) ( $row['retrievalReferenceNumber'] ?? $row['transactionReference'] ?? '' ),
					'amount'   => (float) ( $row['amountFromMerchant'] ?? $row['amount'] ?? $row['amountPaid'] ?? 0 ),
					// The reply may leave the currency out: the reference is ours, so it is the one we sent.
					'currency' => '' !== $currency ? $currency : strtoupper( $payment->currency ),
				);
			}
			if ( in_array( $status, array( 'failed', 'declined', 'cancelled', 'canceled', 'reversed', 'expired' ), true ) ) {
				$seen = 'failed';
			}
		}
		$this->last_said = $said
			/* translators: %s: status from GlobalPay */
			? sprintf( __( 'GlobalPay: %s', 'kingdom-church-suite' ), implode( ', ', array_unique( $said ) ) )
			: __( 'No payment found at GlobalPay yet: the giver has not completed the checkout', 'kingdom-church-suite' );
		return array( 'status' => $seen );
	}
}
