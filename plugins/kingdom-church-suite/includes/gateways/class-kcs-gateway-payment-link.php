<?php
/**
 * Payment link: the church pastes a payment page or payment link from any provider (Paystack,
 * Flutterwave, Zenith GlobalPay, Remita, Opay, Moniepoint, Stripe, PayPal…). Up to three links,
 * each shown to givers as its own method. Givers open it, pay there, then tell the church they
 * have paid; the payment waits in "Needs review" until the church confirms it, exactly like a
 * bank transfer (the website cannot see these payments).
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Payment link gateway.
 */
class KCS_Gateway_Payment_Link extends KCS_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct( $slot = 1 ) {
		$slot        = max( 1, (int) $slot );
		$this->id    = 1 === $slot ? 'payment_link' : 'payment_link_' . $slot;
		/* translators: %d: 1, 2 or 3 */
		$this->title = sprintf( __( 'Payment link %d (any provider)', 'kingdom-church-suite' ), $slot );
	}

	/**
	 * Provider name worked out from the link (shown when the church gives no name).
	 *
	 * @return string
	 */
	public function provider() {
		$host = strtolower( (string) wp_parse_url( $this->link(), PHP_URL_HOST ) );
		foreach ( array(
			'globalpay'   => 'Zenith GlobalPay',
			'paystack'    => 'Paystack',
			'flutterwave' => 'Flutterwave',
			'flw.page'    => 'Flutterwave',
			'bachs'       => 'Bachs',
			'remita'      => 'Remita',
			'opay'        => 'Opay',
			'moniepoint'  => 'Moniepoint',
			'monnify'     => 'Monnify',
			'interswitch' => 'Interswitch',
			'quickteller' => 'Quickteller',
			'squadco'     => 'Squad',
			'selar'       => 'Selar',
			'paypal'      => 'PayPal',
			'stripe'      => 'Stripe',
			'buy.stripe'  => 'Stripe',
			'korapay'     => 'Korapay',
			'paga'        => 'Paga',
		) as $needle => $name ) {
			if ( false !== strpos( $host, $needle ) ) {
				return $name;
			}
		}
		return '';
	}

	/**
	 * Name givers see (the church's own, e.g. "Zenith GlobalPay").
	 *
	 * @return string
	 */
	public function display_title() {
		$t = trim( (string) $this->opt( 'display_title' ) );
		if ( '' !== $t && $t !== $this->title ) {
			return $t;
		}
		$name = $this->provider();
		if ( ! $name ) {
			return __( 'Pay online', 'kingdom-church-suite' );
		}
		// Do not look the same as an automatic gateway with that name (e.g. Paystack).
		foreach ( KCS_Payments::gateways() as $g ) {
			if ( ! $g instanceof self && $g->is_enabled() && 0 === strcasecmp( $g->display_title(), $name ) ) {
				/* translators: %s: provider name */
				return sprintf( __( '%s (payment page)', 'kingdom-church-suite' ), $name );
			}
		}
		return $name;
	}

	/**
	 * Settings: the title field gets a helpful default and example.
	 *
	 * @return array
	 */
	public function settings_fields() {
		$fields = parent::settings_fields();
		unset( $fields[ $this->id . '_webhook_info' ] ); // The provider does not tell this site about link payments.
		$fields[ $this->id . '_display_title' ]['default'] = '';
		$fields[ $this->id . '_display_title' ]['desc']    = __( 'Optional. Empty: worked out from the link (Paystack, Flutterwave, Zenith GlobalPay, Remita…), otherwise “Pay online”.', 'kingdom-church-suite' );
		return $fields;
	}

	/**
	 * Fields.
	 *
	 * @return array
	 */
	protected function fields() {
		return array(
			'link_info'       => array(
				'label' => __( 'How it works', 'kingdom-church-suite' ),
				'type'  => 'info',
				'desc'  => __( 'Paste a payment page or payment link from any provider — Paystack, Flutterwave, Zenith GlobalPay, Remita, Opay, Moniepoint, Stripe, PayPal and others. Givers open it, pay, then press “I have paid”. Check the payment in that provider’s dashboard and confirm it in Church Admin → Confirm payments; the receipt is then sent. You can add up to three links.', 'kingdom-church-suite' ),
			),
			'url'             => array( 'label' => __( 'Payment link', 'kingdom-church-suite' ), 'type' => 'url', 'desc' => __( 'The full https:// link, for example https://paystack.com/pay/yourchurch or https://checkout.globalpay.ng/payment-form/…', 'kingdom-church-suite' ) ),
			'currencies'      => array( 'label' => __( 'Currencies this link accepts (optional)', 'kingdom-church-suite' ), 'type' => 'text', 'default' => '', 'desc' => __( 'Optional, comma separated, e.g. NGN or USD. Empty: any currency.', 'kingdom-church-suite' ) ),
			'for_partnership' => array( 'label' => __( 'Allow for partnership', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1' ),
			'for_giving'      => array( 'label' => __( 'Allow for tithes, offerings and other giving', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1' ),
			'note'            => array( 'label' => __( 'Note for givers (optional)', 'kingdom-church-suite' ), 'type' => 'textarea', 'desc' => __( 'For example: enter the amount shown below and put your reference in the description.', 'kingdom-church-suite' ) ),
		);
	}

	/**
	 * The church's payment link (https only).
	 *
	 * @return string
	 */
	public function link() {
		$url = esc_url_raw( trim( (string) $this->opt( 'url' ) ), array( 'https' ) );
		return wp_http_validate_url( $url ) || preg_match( '#^https://[^/\s]+\.[^/\s]+/#', $url ) ? $url : '';
	}

	/**
	 * On only with a link.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return parent::is_enabled() && '' !== $this->link();
	}

	/**
	 * Currencies (empty = any).
	 *
	 * @return array
	 */
	public function currency_list() {
		$list = array_filter( array_map( 'strtoupper', array_map( 'trim', explode( ',', (string) $this->opt( 'currencies', '' ) ) ) ) );
		return array_values( array_intersect( $list, array_keys( kcs_currencies() ) ) );
	}

	/**
	 * Currency accepted?
	 *
	 * @param string $currency Code.
	 * @return bool
	 */
	public function supports_currency( $currency ) {
		$list = $this->currency_list();
		return ! $list || in_array( strtoupper( $currency ), $list, true );
	}

	/**
	 * Offered for this purpose?
	 *
	 * @param string $purpose Purpose.
	 * @return bool
	 */
	public function allows_purpose( $purpose ) {
		return 'partnership' === $purpose ? '0' !== $this->opt( 'for_partnership', '1' ) : '0' !== $this->opt( 'for_giving', '1' );
	}

	/**
	 * Initialize: the payment page shows the link, amount and reference, and the "I have paid" form.
	 *
	 * @param object $payment Payment.
	 * @return array
	 */
	public function initialize( $payment ) {
		return array(
			'redirect_url' => add_query_arg(
				array(
					'reference'   => rawurlencode( $payment->reference ),
					'kcs_offline' => 1,
				),
				kcs_page_url( 'thank_you' )
			),
		);
	}

	/**
	 * Link payments stay pending until the church confirms them.
	 *
	 * @param object $payment Payment.
	 * @param array  $params  Params.
	 * @return array
	 */
	public function verify( $payment, $params ) {
		return array( 'status' => 'pending' );
	}
}
