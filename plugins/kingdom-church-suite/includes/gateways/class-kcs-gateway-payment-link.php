<?php
/**
 * Payment link: the church pastes a hosted payment page from its provider (for example a Zenith
 * GlobalPay payment form, a Paystack page or a Flutterwave payment link). Givers open it, pay
 * there, then tell the church they have paid; the payment waits in "Needs review" until the
 * church confirms it, exactly like a bank transfer (the website cannot see these payments).
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
	public function __construct() {
		$this->id    = 'payment_link';
		$this->title = __( 'Payment link (GlobalPay, Paystack page…)', 'kingdom-church-suite' );
	}

	/**
	 * Name givers see (the church's own, e.g. "Zenith GlobalPay").
	 *
	 * @return string
	 */
	public function display_title() {
		$t = trim( (string) $this->opt( 'display_title' ) );
		return '' !== $t && $t !== $this->title ? $t : __( 'Pay online', 'kingdom-church-suite' );
	}

	/**
	 * Settings: the title field gets a helpful default and example.
	 *
	 * @return array
	 */
	public function settings_fields() {
		$fields = parent::settings_fields();
		unset( $fields[ $this->id . '_webhook_info' ] ); // The provider does not tell this site about link payments.
		$fields[ $this->id . '_display_title' ]['default'] = 'Zenith GlobalPay';
		$fields[ $this->id . '_display_title' ]['desc']    = __( 'For example Zenith GlobalPay or Pay with card.', 'kingdom-church-suite' );
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
				'desc'  => __( 'Create a payment page or payment link in your provider’s dashboard (for example Zenith GlobalPay → Payment form) and paste it here. Givers open it, pay, then press “I have paid”. Check the payment in your provider’s dashboard and confirm it in Church Admin → Confirm payments; the receipt is then sent.', 'kingdom-church-suite' ),
			),
			'url'             => array( 'label' => __( 'Payment link', 'kingdom-church-suite' ), 'type' => 'url', 'desc' => __( 'For example https://checkout.globalpay.ng/payment-form/…', 'kingdom-church-suite' ) ),
			'currencies'      => array( 'label' => __( 'Currencies this link accepts (optional)', 'kingdom-church-suite' ), 'type' => 'text', 'default' => 'NGN', 'desc' => __( 'Comma separated, e.g. NGN or NGN,USD. Empty: any.', 'kingdom-church-suite' ) ),
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
		$list = array_filter( array_map( 'strtoupper', array_map( 'trim', explode( ',', (string) $this->opt( 'currencies', 'NGN' ) ) ) ) );
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
