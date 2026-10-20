<?php
/**
 * Offline bank transfer (confirmed manually by the church).
 *
 * The church adds its bank accounts in Church Admin → Bank transfers. Givers see the account
 * for their currency and the reference to use, then tell the church they have paid ("I have
 * made this transfer", optionally with proof). The payment waits in "Needs review" until a
 * church administrator confirms it; the receipt is then sent and partners are renewed.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bank transfer gateway.
 */
class KCS_Gateway_Bank_Transfer extends KCS_Gateway {

	/**
	 * Most accounts a church can list.
	 */
	const MAX_ACCOUNTS = 3;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'bank_transfer';
		$this->title = __( 'Bank Transfer / Offline', 'kingdom-church-suite' );
	}

	/**
	 * Fields.
	 *
	 * @return array
	 */
	protected function fields() {
		return array(
			'accounts_info'   => array(
				'label' => __( 'Bank accounts', 'kingdom-church-suite' ),
				'type'  => 'info',
				'desc'  => did_action( 'init' ) && function_exists( 'kcs_page_url' ) ? sprintf(
					/* translators: %s: link to Church Admin */
					__( 'Add your account details in %s. Givers see them after choosing bank transfer.', 'kingdom-church-suite' ),
					'<a href="' . esc_url( add_query_arg( 'view', 'transfers', kcs_page_url( 'church_admin' ) ) ) . '">' . esc_html__( 'Church Admin → Confirm payments', 'kingdom-church-suite' ) . '</a>'
				) : '',
			),
			'for_partnership' => array( 'label' => __( 'Allow for partnership', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1' ),
			'for_giving'      => array( 'label' => __( 'Allow for tithes, offerings and other giving', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1' ),
			'confirm_by'      => array(
				'label'   => __( 'Who can confirm transfers', 'kingdom-church-suite' ),
				'type'    => 'select',
				'default' => 'admins',
				'options' => array(
					'admins' => __( 'Church administrators only', 'kingdom-church-suite' ),
					'staff'  => __( 'All church staff', 'kingdom-church-suite' ),
				),
			),
			'note'            => array(
				'label' => __( 'Extra note for givers (optional)', 'kingdom-church-suite' ),
				'type'  => 'textarea',
			),
			'instructions'    => array(
				'label'   => __( 'Instructions (used only when no accounts are added)', 'kingdom-church-suite' ),
				'type'    => 'editor',
				'default' => '<p><strong>Bank:</strong> Your Bank<br><strong>Account name:</strong> Your Church<br><strong>Account number:</strong> 0000000000</p><p>Please use your payment reference as the transfer narration.</p>',
			),
		);
	}

	/**
	 * Settings fields (no webhook for offline payments).
	 *
	 * @return array
	 */
	public function settings_fields() {
		$fields = parent::settings_fields();
		unset( $fields[ $this->id . '_webhook_info' ] );
		return $fields;
	}

	/**
	 * The church's bank accounts.
	 *
	 * @return array[] Each: bank, name, number, currency, sort_code, iban, swift.
	 */
	public function accounts() {
		$list = kcs_get_option( $this->id . '_accounts', array() );
		return is_array( $list ) ? array_values( array_filter( $list, function ( $a ) {
			return is_array( $a ) && ! empty( $a['number'] );
		} ) ) : array();
	}

	/**
	 * Clean account rows from a form.
	 *
	 * @param array $rows Raw rows.
	 * @return array[]
	 */
	public static function sanitize_accounts( $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$a = array();
			foreach ( array( 'bank', 'name', 'number', 'sort_code', 'iban', 'swift' ) as $k ) {
				$a[ $k ] = substr( sanitize_text_field( (string) ( $row[ $k ] ?? '' ) ), 0, 100 );
			}
			$cur           = strtoupper( sanitize_text_field( (string) ( $row['currency'] ?? '' ) ) );
			$a['currency'] = array_key_exists( $cur, kcs_currencies() ) ? $cur : '';
			if ( '' !== $a['number'] || '' !== $a['iban'] ) {
				if ( '' === $a['number'] ) {
					$a['number'] = $a['iban'];
				}
				$out[] = $a;
			}
			if ( count( $out ) >= self::MAX_ACCOUNTS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Accounts that accept a currency (accounts without a currency accept any).
	 *
	 * @param string $currency Currency.
	 * @return array[]
	 */
	public function accounts_for( $currency ) {
		return array_values( array_filter( $this->accounts(), function ( $a ) use ( $currency ) {
			return '' === $a['currency'] || strtoupper( $currency ) === $a['currency'];
		} ) );
	}

	/**
	 * Currencies the accounts accept (empty = any).
	 *
	 * @return array
	 */
	public function currency_list() {
		$codes = array();
		foreach ( $this->accounts() as $a ) {
			if ( '' === $a['currency'] ) {
				return array();
			}
			$codes[] = $a['currency'];
		}
		return array_values( array_unique( $codes ) );
	}

	/**
	 * Does an account accept this currency?
	 *
	 * @param string $currency Code.
	 * @return bool
	 */
	public function supports_currency( $currency ) {
		$list = $this->currency_list();
		return ! $list || in_array( strtoupper( $currency ), $list, true );
	}

	/**
	 * Is bank transfer offered for this purpose?
	 *
	 * @param string $purpose Purpose.
	 * @return bool
	 */
	public function allows_purpose( $purpose ) {
		return 'partnership' === $purpose ? '0' !== $this->opt( 'for_partnership', '1' ) : '0' !== $this->opt( 'for_giving', '1' );
	}

	/**
	 * Can the current user confirm or reject transfers?
	 *
	 * @return bool
	 */
	public function can_confirm() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return 'staff' === $this->opt( 'confirm_by', 'admins' ) && current_user_can( 'manage_kcs' );
	}

	/**
	 * Initialize: show the account details on the result page.
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
	 * Offline payments remain pending until the church confirms them.
	 *
	 * @param object $payment Payment.
	 * @param array  $params  Params.
	 * @return array
	 */
	public function verify( $payment, $params ) {
		return array( 'status' => 'pending' );
	}
}
