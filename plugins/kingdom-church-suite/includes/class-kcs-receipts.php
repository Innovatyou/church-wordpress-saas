<?php
/**
 * Payment receipts (email + a printable receipt page the church can customise) and the
 * "your payment did not go through, please try again" email.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Receipts.
 */
class KCS_Receipts {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'page' ), 0 );
	}

	/**
	 * Private link to a payment's receipt page.
	 *
	 * @param object $payment Payment.
	 * @return string
	 */
	public static function url( $payment ) {
		return add_query_arg( array( 'kcs_receipt' => rawurlencode( $payment->reference ), 'k' => self::key( $payment->reference ) ), home_url( '/' ) );
	}

	/**
	 * Signature for a receipt link (nobody can guess another giver's receipt).
	 *
	 * @param string $reference Reference.
	 * @return string
	 */
	private static function key( $reference ) {
		return substr( hash_hmac( 'sha256', 'receipt|' . $reference, wp_salt( 'auth' ) ), 0, 20 );
	}

	/**
	 * Receipt settings with defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		return array(
			'title'     => (string) kcs_get_option( 'receipt_title', __( 'Official Receipt', 'kingdom-church-suite' ) ),
			'message'   => (string) kcs_get_option( 'receipt_message', __( 'Thank you for your generosity. May God bless you richly.', 'kingdom-church-suite' ) ),
			'footer'    => (string) kcs_get_option( 'receipt_footer', '' ),
			'signatory' => (string) kcs_get_option( 'receipt_signatory', '' ),
			'role'      => (string) kcs_get_option( 'receipt_signatory_role', '' ),
			'logo'      => '1' === (string) kcs_get_option( 'receipt_logo', '1' ),
		);
	}

	/**
	 * Save the receipt settings from Church Admin.
	 *
	 * @param array $d Posted data.
	 */
	public static function save_settings( $d ) {
		$s                           = get_option( 'kcs_settings', array() );
		$s['receipt_title']          = sanitize_text_field( $d['receipt_title'] ?? '' );
		$s['receipt_message']        = sanitize_textarea_field( $d['receipt_message'] ?? '' );
		$s['receipt_footer']         = sanitize_textarea_field( $d['receipt_footer'] ?? '' );
		$s['receipt_signatory']      = sanitize_text_field( $d['receipt_signatory'] ?? '' );
		$s['receipt_signatory_role'] = sanitize_text_field( $d['receipt_signatory_role'] ?? '' );
		$s['receipt_logo']           = empty( $d['receipt_logo'] ) ? '0' : '1';
		$s['auto_retry_email']       = empty( $d['auto_retry_email'] ) ? '0' : '1';
		update_option( 'kcs_settings', $s );
	}

	/**
	 * Email the receipt (the "Payment receipt" email template), with a link to the printable receipt.
	 *
	 * @param object $payment Payment.
	 * @return bool
	 */
	public static function send( $payment ) {
		if ( ! $payment || 'completed' !== $payment->status || ! is_email( $payment->email ) ) {
			return false;
		}
		$vars = KCS_Payments::email_vars( $payment );
		$t    = KCS_Email::render_template( 'payment_receipt', $vars );
		$body = $t['body'];
		if ( false === strpos( $body, 'kcs_receipt=' ) ) {
			$body .= '<p><a class="btn" href="' . esc_url( $vars['receipt_url'] ) . '">' . esc_html__( 'View / print your receipt', 'kingdom-church-suite' ) . '</a></p>';
		}
		$sent = KCS_Email::send( $payment->email, $t['subject'], $body );
		if ( $sent ) {
			self::stamp( $payment, 'receipt_sent' );
		}
		return $sent;
	}

	/**
	 * Email a giver whose payment did not go through, asking them to try again.
	 *
	 * @param object $payment Payment.
	 * @return bool
	 */
	public static function send_retry( $payment ) {
		if ( ! $payment || ! in_array( $payment->status, array( 'failed', 'pending', 'cancelled' ), true ) || ! is_email( $payment->email ) ) {
			return false;
		}
		$sent = KCS_Email::send_template( $payment->email, 'payment_failed', KCS_Payments::email_vars( $payment ) );
		if ( $sent ) {
			self::stamp( $payment, 'retry_sent' );
		}
		return $sent;
	}

	/**
	 * When a payment fails and the church turned on automatic retry emails, send one (once).
	 *
	 * @param object $payment Payment (now failed).
	 */
	public static function maybe_auto_retry( $payment ) {
		if ( '1' !== (string) kcs_get_option( 'auto_retry_email', '0' ) || ! empty( KCS_Transfers::meta( $payment )['retry_sent'] ) ) {
			return;
		}
		self::send_retry( $payment );
	}

	/**
	 * Remember when a receipt or retry email went out (payment meta).
	 *
	 * @param object $payment Payment.
	 * @param string $key     receipt_sent|retry_sent.
	 */
	private static function stamp( $payment, $key ) {
		global $wpdb;
		$fresh         = KCS_Payments::get( $payment->id );
		$meta          = KCS_Transfers::meta( $fresh ? $fresh : $payment );
		$meta[ $key ]  = current_time( 'mysql' );
		$wpdb->update( kcs_table( 'payments' ), array( 'meta' => wp_json_encode( $meta ) ), array( 'id' => $payment->id ) );
	}

	/**
	 * The printable receipt page (?kcs_receipt=REF&k=…).
	 */
	public static function page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['kcs_receipt'] ) ) {
			return;
		}
		$ref = sanitize_text_field( wp_unslash( $_GET['kcs_receipt'] ) );
		$key = sanitize_text_field( wp_unslash( $_GET['k'] ?? '' ) );
		// phpcs:enable
		$payment = KCS_Payments::get_by_reference( $ref );
		if ( ! $payment || 'completed' !== $payment->status || ! hash_equals( self::key( $ref ), $key ) ) {
			wp_die( esc_html__( 'This receipt link is not valid.', 'kingdom-church-suite' ), esc_html__( 'Receipt', 'kingdom-church-suite' ), array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		$s      = self::settings();
		$v      = KCS_Payments::email_vars( $payment );
		$church = kcs_get_option( 'church_name', get_bloginfo( 'name' ) );
		$logo   = $s['logo'] ? ( kcs_get_option( 'church_logo' ) ? kcs_get_option( 'church_logo' ) : ( get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' ) : '' ) ) : '';
		$color  = kcs_get_option( 'email_color', '#5b2a86' );
		$rows   = array(
			__( 'Receipt number', 'kingdom-church-suite' ) => $payment->reference,
			__( 'Date', 'kingdom-church-suite' )           => $v['date'],
			__( 'Received from', 'kingdom-church-suite' )  => trim( $payment->name . ( $payment->email ? ' (' . $payment->email . ')' : '' ) ),
			__( 'For', 'kingdom-church-suite' )            => $v['purpose'],
			__( 'Payment method', 'kingdom-church-suite' ) => $v['gateway'],
		);
		if ( $payment->gateway_txn_id ) {
			$rows[ __( 'Transaction ID', 'kingdom-church-suite' ) ] = $payment->gateway_txn_id;
		}
		?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?php echo esc_html( $s['title'] . ' ' . $payment->reference . ' — ' . $church ); ?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f3f1f6;font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#1f1b24}
.r{max-width:720px;margin:2rem auto;background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.08);overflow:hidden}
.r__head{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1.75rem 2rem;border-bottom:4px solid <?php echo esc_attr( $color ); ?>}
.r__head img{max-height:64px;max-width:200px}.r__church{font-weight:700;font-size:1.1rem}.r__addr{color:#6b6475;font-size:.88rem;white-space:pre-line}
.r__title{text-align:right}.r__title h1{margin:0;font-size:1.4rem;color:<?php echo esc_attr( $color ); ?>;text-transform:uppercase;letter-spacing:.04em}
.r__body{padding:1.75rem 2rem}.r__amount{margin:0 0 1.25rem;padding:1.1rem 1.25rem;border-radius:12px;background:#f7f4fb;display:flex;justify-content:space-between;align-items:center;gap:1rem}
.r__amount strong{font-size:1.8rem;color:<?php echo esc_attr( $color ); ?>}table{width:100%;border-collapse:collapse}td{padding:.6rem 0;border-bottom:1px solid #eee;vertical-align:top}td:first-child{color:#6b6475;width:40%}
.r__msg{margin:1.5rem 0 0}.r__sign{margin-top:2.5rem;display:inline-block;min-width:220px;border-top:1px solid #1f1b24;padding-top:.4rem}.r__sign small{display:block;color:#6b6475}
.r__foot{padding:1rem 2rem 1.5rem;color:#6b6475;font-size:.85rem;white-space:pre-line}.r__paid{display:inline-block;padding:.2rem .7rem;border-radius:999px;background:#e5f6ec;color:#1e8e5a;font-weight:700;font-size:.8rem}
.r__actions{max-width:720px;margin:0 auto 2rem;text-align:center}.r__actions button{padding:.7rem 1.4rem;border:0;border-radius:999px;background:<?php echo esc_attr( $color ); ?>;color:#fff;font:600 .95rem system-ui,sans-serif;cursor:pointer}
@media (max-width:600px){.r{margin:0;border-radius:0}.r__head{flex-direction:column;align-items:flex-start}.r__title{text-align:left}.r__amount{flex-direction:column;align-items:flex-start}}
@media print{body{background:#fff}.r{box-shadow:none;margin:0;max-width:none}.r__actions{display:none}}
</style>
</head>
<body>
<div class="r">
	<div class="r__head">
		<div>
			<?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $church ); ?>"><?php endif; ?>
			<div class="r__church"><?php echo esc_html( $church ); ?></div>
			<?php if ( kcs_get_option( 'church_address' ) ) : ?><div class="r__addr"><?php echo esc_html( kcs_get_option( 'church_address' ) ); ?></div><?php endif; ?>
		</div>
		<div class="r__title"><h1><?php echo esc_html( $s['title'] ); ?></h1><span class="r__paid"><?php esc_html_e( 'PAID', 'kingdom-church-suite' ); ?></span></div>
	</div>
	<div class="r__body">
		<div class="r__amount"><span><?php esc_html_e( 'Amount received', 'kingdom-church-suite' ); ?></span><strong><?php echo esc_html( $v['amount'] ); ?></strong></div>
		<table>
			<?php foreach ( $rows as $label => $value ) : ?>
				<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( $value ); ?></td></tr>
			<?php endforeach; ?>
		</table>
		<?php if ( $s['message'] ) : ?><p class="r__msg"><?php echo nl2br( esc_html( $s['message'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p><?php endif; ?>
		<?php if ( $s['signatory'] ) : ?><div class="r__sign"><?php echo esc_html( $s['signatory'] ); ?><?php if ( $s['role'] ) : ?><small><?php echo esc_html( $s['role'] ); ?></small><?php endif; ?></div><?php endif; ?>
	</div>
	<?php if ( $s['footer'] ) : ?><div class="r__foot"><?php echo esc_html( $s['footer'] ); ?></div><?php endif; ?>
</div>
<div class="r__actions"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print or save as PDF', 'kingdom-church-suite' ); ?></button></div>
</body>
</html>
		<?php
		exit;
	}
}
