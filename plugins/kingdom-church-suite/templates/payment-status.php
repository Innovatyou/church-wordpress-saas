<?php
/**
 * Payment result page.
 *
 * @package KingdomChurchSuite
 * @var object|null $payment Payment.
 * @var bool        $offline Offline (bank transfer).
 */

defined( 'ABSPATH' ) || exit;

if ( ! $payment ) {
	echo '<div class="kcs-result"><h2>' . esc_html__( 'Thank you!', 'kingdom-church-suite' ) . '</h2><p>' . esc_html__( 'We could not find that payment reference.', 'kingdom-church-suite' ) . '</p></div>';
	return;
}

$gateway = KCS_Payments::gateway( $payment->gateway );
$amount  = kcs_format_money( $payment->amount, $payment->currency );
$back    = $payment->partner_id ? kcs_page_url( 'partner_dashboard' ) : home_url( '/' );
?>
<div class="kcs-result kcs-result--<?php echo esc_attr( $payment->status ); ?>" data-kcs-payment-status="<?php echo esc_attr( $payment->reference ); ?>" data-status="<?php echo esc_attr( $payment->status ); ?>">
	<?php if ( 'completed' === $payment->status ) : ?>
		<div class="kcs-result__icon">✓</div>
		<h2><?php esc_html_e( 'Thank you for your generosity!', 'kingdom-church-suite' ); ?></h2>
		<p><?php /* translators: %s: amount */ printf( esc_html__( 'Your gift of %s was received successfully. A receipt has been sent to your email.', 'kingdom-church-suite' ), '<strong>' . esc_html( $amount ) . '</strong>' ); ?></p>
		<blockquote class="kcs-verse"><p>“<?php esc_html_e( 'Give, and it will be given to you. A good measure, pressed down, shaken together and running over…', 'kingdom-church-suite' ); ?>”</p><cite>Luke 6:38</cite></blockquote>
	<?php elseif ( KCS_Transfers::is_manual( $payment ) && 'review' === $payment->status ) : ?>
		<?php $kcs_tr = KCS_Transfers::meta( $payment )['transfer'] ?? array(); ?>
		<div class="kcs-result__icon">✓</div>
		<h2><?php echo 'bank_transfer' !== $payment->gateway ? esc_html__( 'Thank you! We are checking your payment', 'kingdom-church-suite' ) : esc_html__( 'Thank you! We are checking your transfer', 'kingdom-church-suite' ); ?></h2>
		<p><?php /* translators: %s: amount */ printf( esc_html__( 'You told us you sent %s. We will check our account and email your receipt as soon as it is confirmed.', 'kingdom-church-suite' ), '<strong>' . esc_html( $amount ) . '</strong>' ); ?></p>
		<?php if ( $kcs_tr ) : ?>
			<p class="kcs-muted"><?php /* translators: 1: payer name, 2: date */ printf( esc_html__( 'Sent by %1$s on %2$s.', 'kingdom-church-suite' ), esc_html( $kcs_tr['payer'] ), esc_html( mysql2date( get_option( 'date_format' ), $kcs_tr['date'] ) ) ); ?></p>
		<?php endif; ?>
	<?php elseif ( $gateway instanceof KCS_Gateway_Payment_Link && 'pending' === $payment->status ) : ?>
		<?php
		$kcs_tn   = isset( $_GET['kcs_tn'] ) ? sanitize_key( $_GET['kcs_tn'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$kcs_copy = function ( $value ) {
			return '<button type="button" class="kcs-copy" data-kcs-copy="' . esc_attr( $value ) . '" data-done="' . esc_attr__( 'Copied', 'kingdom-church-suite' ) . '">' . esc_html__( 'Copy', 'kingdom-church-suite' ) . '</button>';
		};
		?>
		<div class="kcs-result__icon">💳</div>
		<h2><?php /* translators: %s: payment method name, e.g. Zenith GlobalPay */ printf( esc_html__( 'Pay with %s', 'kingdom-church-suite' ), esc_html( $gateway->display_title() ) ); ?></h2>
		<p><?php esc_html_e( 'Open the payment page, enter the exact amount below and, if there is a description or narration box, your reference. Then come back here and tell us you have paid.', 'kingdom-church-suite' ); ?></p>
		<?php if ( 'missing' === $kcs_tn ) : ?><div class="kcs-alert kcs-alert--error" role="alert"><?php esc_html_e( 'Please enter the name you paid with.', 'kingdom-church-suite' ); ?></div><?php elseif ( 'file' === $kcs_tn ) : ?><div class="kcs-alert kcs-alert--error" role="alert"><?php esc_html_e( 'The proof must be a JPG, PNG or PDF file under 5 MB.', 'kingdom-church-suite' ); ?></div><?php endif; ?>
		<dl class="kcs-transfer__sum">
			<div><dt><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?></dt><dd><strong><?php echo esc_html( $amount ); ?></strong> <?php echo $kcs_copy( number_format( (float) $payment->amount, in_array( $payment->currency, kcs_zero_decimal_currencies(), true ) ? 0 : 2, '.', '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div>
			<div><dt><?php esc_html_e( 'Reference / description', 'kingdom-church-suite' ); ?></dt><dd><code><?php echo esc_html( $payment->reference ); ?></code> <?php echo $kcs_copy( $payment->reference ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div>
		</dl>
		<p><a class="kcs-btn kcs-btn--lg kcs-btn--block" href="<?php echo esc_url( $gateway->link() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the payment page ↗', 'kingdom-church-suite' ); ?></a></p>
		<?php if ( $gateway->opt( 'note' ) ) : ?><p class="kcs-transfer__note"><?php echo nl2br( esc_html( $gateway->opt( 'note' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p><?php endif; ?>
		<form class="kcs-form kcs-transfer__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="kcs_transfer_notice">
			<input type="hidden" name="reference" value="<?php echo esc_attr( $payment->reference ); ?>">
			<?php wp_nonce_field( KCS_Transfers::nonce_action( $payment->reference ), '_kcs_tn' ); ?>
			<h3><?php esc_html_e( 'Paid? Let us know', 'kingdom-church-suite' ); ?></h3>
			<div class="kcs-grid-2">
				<label><?php esc_html_e( 'Name you paid with', 'kingdom-church-suite' ); ?><input name="payer" required maxlength="120" value="<?php echo esc_attr( $payment->name ); ?>"></label>
				<label><?php esc_html_e( 'Transaction ID from the payment page (optional)', 'kingdom-church-suite' ); ?><input name="payer_bank" maxlength="120"></label>
				<label><?php esc_html_e( 'Date paid', 'kingdom-church-suite' ); ?><input type="date" name="paid_on" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></label>
				<label><?php esc_html_e( 'Receipt or screenshot (optional)', 'kingdom-church-suite' ); ?><input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"><small class="kcs-muted"><?php esc_html_e( 'JPG, PNG or PDF, up to 5 MB.', 'kingdom-church-suite' ); ?></small></label>
			</div>
			<label><?php esc_html_e( 'Note (optional)', 'kingdom-church-suite' ); ?><textarea name="transfer_note" rows="2" maxlength="1000"></textarea></label>
			<button class="kcs-btn kcs-btn--lg kcs-btn--block kcs-btn--ghost"><?php esc_html_e( 'I have paid', 'kingdom-church-suite' ); ?></button>
		</form>
	<?php elseif ( 'bank_transfer' === $payment->gateway && 'pending' === $payment->status ) : ?>
		<?php
		$kcs_accounts = $gateway instanceof KCS_Gateway_Bank_Transfer ? $gateway->accounts_for( $payment->currency ) : array();
		$kcs_all      = $gateway instanceof KCS_Gateway_Bank_Transfer ? $gateway->accounts() : array();
		$kcs_tn       = isset( $_GET['kcs_tn'] ) ? sanitize_key( $_GET['kcs_tn'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$kcs_errors   = array(
			'missing' => __( 'Please enter the name the transfer was sent from.', 'kingdom-church-suite' ),
			'file'    => __( 'The proof must be a JPG, PNG or PDF file under 5 MB.', 'kingdom-church-suite' ),
		);
		$kcs_copy     = function ( $value ) {
			return '<button type="button" class="kcs-copy" data-kcs-copy="' . esc_attr( $value ) . '" data-done="' . esc_attr__( 'Copied', 'kingdom-church-suite' ) . '">' . esc_html__( 'Copy', 'kingdom-church-suite' ) . '</button>';
		};
		?>
		<div class="kcs-result__icon">🏦</div>
		<h2><?php esc_html_e( 'Complete your bank transfer', 'kingdom-church-suite' ); ?></h2>
		<p><?php esc_html_e( 'Send the exact amount below to our account and use the reference as the transfer description, so we can match your gift.', 'kingdom-church-suite' ); ?></p>
		<?php if ( isset( $kcs_errors[ $kcs_tn ] ) ) : ?><div class="kcs-alert kcs-alert--error" role="alert"><?php echo esc_html( $kcs_errors[ $kcs_tn ] ); ?></div><?php endif; ?>
		<dl class="kcs-transfer__sum">
			<div><dt><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?></dt><dd><strong><?php echo esc_html( $amount ); ?></strong> <?php echo $kcs_copy( number_format( (float) $payment->amount, in_array( $payment->currency, kcs_zero_decimal_currencies(), true ) ? 0 : 2, '.', '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div>
			<div><dt><?php esc_html_e( 'Reference / description', 'kingdom-church-suite' ); ?></dt><dd><code><?php echo esc_html( $payment->reference ); ?></code> <?php echo $kcs_copy( $payment->reference ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div>
		</dl>
		<?php if ( $kcs_accounts ) : ?>
			<div class="kcs-transfer__accounts">
				<?php foreach ( $kcs_accounts as $kcs_a ) : ?>
					<div class="kcs-transfer__account">
						<p class="kcs-transfer__bank"><strong><?php echo esc_html( $kcs_a['bank'] ); ?></strong><?php if ( $kcs_a['currency'] ) : ?> <span class="kcs-badge"><?php echo esc_html( $kcs_a['currency'] ); ?></span><?php endif; ?></p>
						<dl>
							<?php if ( $kcs_a['name'] ) : ?><div><dt><?php esc_html_e( 'Account name', 'kingdom-church-suite' ); ?></dt><dd><?php echo esc_html( $kcs_a['name'] ); ?></dd></div><?php endif; ?>
							<div><dt><?php esc_html_e( 'Account number', 'kingdom-church-suite' ); ?></dt><dd><strong class="kcs-transfer__num"><?php echo esc_html( $kcs_a['number'] ); ?></strong> <?php echo $kcs_copy( $kcs_a['number'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div>
							<?php if ( $kcs_a['sort_code'] ) : ?><div><dt><?php esc_html_e( 'Sort code / routing', 'kingdom-church-suite' ); ?></dt><dd><?php echo esc_html( $kcs_a['sort_code'] ); ?> <?php echo $kcs_copy( $kcs_a['sort_code'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div><?php endif; ?>
							<?php if ( $kcs_a['iban'] && $kcs_a['iban'] !== $kcs_a['number'] ) : ?><div><dt>IBAN</dt><dd><?php echo esc_html( $kcs_a['iban'] ); ?> <?php echo $kcs_copy( $kcs_a['iban'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div><?php endif; ?>
							<?php if ( $kcs_a['swift'] ) : ?><div><dt>SWIFT / BIC</dt><dd><?php echo esc_html( $kcs_a['swift'] ); ?> <?php echo $kcs_copy( $kcs_a['swift'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div><?php endif; ?>
						</dl>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $gateway->opt( 'note' ) ) : ?><p class="kcs-transfer__note"><?php echo nl2br( esc_html( $gateway->opt( 'note' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p><?php endif; ?>
		<?php elseif ( ! $kcs_all ) : ?>
			<div class="kcs-bank-details"><?php echo wp_kses_post( $gateway ? $gateway->opt( 'instructions' ) : '' ); ?></div>
		<?php else : ?>
			<div class="kcs-alert kcs-alert--info"><?php esc_html_e( 'Please contact the church for account details in this currency.', 'kingdom-church-suite' ); ?></div>
		<?php endif; ?>

		<form class="kcs-form kcs-transfer__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="kcs_transfer_notice">
			<input type="hidden" name="reference" value="<?php echo esc_attr( $payment->reference ); ?>">
			<?php wp_nonce_field( KCS_Transfers::nonce_action( $payment->reference ), '_kcs_tn' ); ?>
			<h3><?php esc_html_e( 'Done the transfer? Let us know', 'kingdom-church-suite' ); ?></h3>
			<div class="kcs-grid-2">
				<label><?php esc_html_e( 'Name on the sending account', 'kingdom-church-suite' ); ?><input name="payer" required maxlength="120" value="<?php echo esc_attr( $payment->name ); ?>"></label>
				<label><?php esc_html_e( 'Your bank (optional)', 'kingdom-church-suite' ); ?><input name="payer_bank" maxlength="120"></label>
				<label><?php esc_html_e( 'Date sent', 'kingdom-church-suite' ); ?><input type="date" name="paid_on" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></label>
				<label><?php esc_html_e( 'Receipt or screenshot (optional)', 'kingdom-church-suite' ); ?><input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"><small class="kcs-muted"><?php esc_html_e( 'JPG, PNG or PDF, up to 5 MB.', 'kingdom-church-suite' ); ?></small></label>
			</div>
			<label><?php esc_html_e( 'Note (optional)', 'kingdom-church-suite' ); ?><textarea name="transfer_note" rows="2" maxlength="1000"></textarea></label>
			<button class="kcs-btn kcs-btn--lg kcs-btn--block"><?php esc_html_e( 'I have made this transfer', 'kingdom-church-suite' ); ?></button>
		</form>
	<?php elseif ( KCS_Transfers::is_manual( $payment ) && 'failed' === $payment->status ) : ?>
		<?php $kcs_rej = KCS_Transfers::meta( $payment )['rejected'] ?? array(); ?>
		<div class="kcs-result__icon">!</div>
		<h2><?php echo 'bank_transfer' !== $payment->gateway ? esc_html__( 'We could not confirm this payment', 'kingdom-church-suite' ) : esc_html__( 'We could not confirm this transfer', 'kingdom-church-suite' ); ?></h2>
		<?php if ( ! empty( $kcs_rej['reason'] ) ) : ?><p><?php echo esc_html( $kcs_rej['reason'] ); ?></p><?php endif; ?>
		<p><?php esc_html_e( 'If you have already sent the money, please reply to our email with your transfer receipt.', 'kingdom-church-suite' ); ?></p>
	<?php elseif ( in_array( $payment->status, array( 'pending', 'review' ), true ) ) : ?>
		<div class="kcs-result__icon kcs-spin">⏳</div>
		<h2><?php esc_html_e( 'Confirming your payment…', 'kingdom-church-suite' ); ?></h2>
		<p><?php esc_html_e( 'This usually takes a few seconds. This page will update automatically.', 'kingdom-church-suite' ); ?></p>
		<?php if ( 'pending' === $payment->status && ! KCS_Transfers::is_manual( $payment ) ) : ?>
			<p class="kcs-muted"><?php esc_html_e( 'Did not finish paying, or saw an error on the payment page? Nothing was taken: you can try again or choose another payment method.', 'kingdom-church-suite' ); ?></p>
			<p><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( $payment->partner_id ? add_query_arg( 'kcs_action', 'pay', kcs_page_url( 'partner_dashboard' ) ) : kcs_page_url( 'give' ) ); ?>"><?php esc_html_e( 'Try again', 'kingdom-church-suite' ); ?></a></p>
		<?php endif; ?>
	<?php else : ?>
		<div class="kcs-result__icon">!</div>
		<h2><?php esc_html_e( 'Payment not completed', 'kingdom-church-suite' ); ?></h2>
		<p><?php esc_html_e( 'Your payment was cancelled or could not be completed. No money was taken. Please try again.', 'kingdom-church-suite' ); ?></p>
	<?php endif; ?>
	<p class="kcs-result__ref"><?php esc_html_e( 'Reference', 'kingdom-church-suite' ); ?>: <code><?php echo esc_html( $payment->reference ); ?></code></p>
	<p><a class="kcs-btn" href="<?php echo esc_url( $back ); ?>"><?php echo $payment->partner_id ? esc_html__( 'Back to my dashboard', 'kingdom-church-suite' ) : esc_html__( 'Back to homepage', 'kingdom-church-suite' ); ?></a></p>
</div>
