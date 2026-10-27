<?php
/**
 * Payment result page.
 *
 * @package KingdomChurchSuite
 * @var object|null $payment Payment.
 * @var bool        $offline Offline (bank transfer).
 */

defined( 'ABSPATH' ) || exit;

$kcs_icons = array(
	'check'   => '<svg viewBox="0 0 52 52" aria-hidden="true"><circle class="kcs-result__ring" cx="26" cy="26" r="24"/><path class="kcs-result__tick" d="M15 27l7 7 15-16"/></svg>',
	'clock'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
	'card'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/></svg>',
	'bank'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10l9-6 9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 20h18"/></svg>',
	'cross'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg>',
	'search'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>',
);

if ( ! $payment ) {
	?>
	<div class="kcs-result kcs-result--neutral">
		<div class="kcs-result__card">
			<div class="kcs-result__top">
				<div class="kcs-result__icon"><?php echo $kcs_icons['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<h2 class="kcs-result__title"><?php esc_html_e( 'Thank you!', 'kingdom-church-suite' ); ?></h2>
				<p class="kcs-result__lead"><?php esc_html_e( 'We could not find that payment reference.', 'kingdom-church-suite' ); ?></p>
			</div>
			<div class="kcs-result__actions"><a class="kcs-btn kcs-btn--lg" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to homepage', 'kingdom-church-suite' ); ?></a></div>
		</div>
	</div>
	<?php
	return;
}

$gateway  = KCS_Payments::gateway( $payment->gateway );
$amount   = kcs_format_money( $payment->amount, $payment->currency );
$back     = $payment->partner_id ? kcs_page_url( 'partner_dashboard' ) : home_url( '/' );
$again    = $payment->partner_id ? add_query_arg( 'kcs_action', 'pay', kcs_page_url( 'partner_dashboard' ) ) : kcs_page_url( 'give' );
$purposes = kcs_all_purposes();
$purpose  = isset( $purposes[ $payment->purpose ] ) ? $purposes[ $payment->purpose ] : ucwords( str_replace( array( '-', '_' ), ' ', (string) $payment->purpose ) );
$manual   = KCS_Transfers::is_manual( $payment );
$kcs_copy = function ( $value ) {
	return '<button type="button" class="kcs-copy" data-kcs-copy="' . esc_attr( $value ) . '" data-done="' . esc_attr__( 'Copied', 'kingdom-church-suite' ) . '">' . esc_html__( 'Copy', 'kingdom-church-suite' ) . '</button>';
};

// Which state this page shows: tone (colour), icon, small label, title.
if ( 'completed' === $payment->status ) {
	$state = array( 'success', 'check', __( 'Gift received', 'kingdom-church-suite' ), __( 'Thank you for your generosity!', 'kingdom-church-suite' ) );
} elseif ( $manual && 'review' === $payment->status ) {
	$state = array( 'pending', 'clock', __( 'Being checked', 'kingdom-church-suite' ), 'bank_transfer' !== $payment->gateway ? __( 'Thank you! We are checking your payment', 'kingdom-church-suite' ) : __( 'Thank you! We are checking your transfer', 'kingdom-church-suite' ) );
} elseif ( $gateway instanceof KCS_Gateway_Payment_Link && 'pending' === $payment->status ) {
	/* translators: %s: payment method name, e.g. Zenith GlobalPay */
	$state = array( 'action', 'card', __( 'One more step', 'kingdom-church-suite' ), sprintf( __( 'Pay with %s', 'kingdom-church-suite' ), $gateway->display_title() ) );
} elseif ( 'bank_transfer' === $payment->gateway && 'pending' === $payment->status ) {
	$state = array( 'action', 'bank', __( 'One more step', 'kingdom-church-suite' ), __( 'Complete your bank transfer', 'kingdom-church-suite' ) );
} elseif ( $manual && 'failed' === $payment->status ) {
	$state = array( 'error', 'cross', __( 'Not confirmed', 'kingdom-church-suite' ), 'bank_transfer' !== $payment->gateway ? __( 'We could not confirm this payment', 'kingdom-church-suite' ) : __( 'We could not confirm this transfer', 'kingdom-church-suite' ) );
} elseif ( in_array( $payment->status, array( 'pending', 'review' ), true ) ) {
	$state = array( 'pending', 'clock', __( 'Please wait', 'kingdom-church-suite' ), __( 'Confirming your payment…', 'kingdom-church-suite' ) );
} else {
	$state = array( 'error', 'cross', 'cancelled' === $payment->status ? __( 'Cancelled', 'kingdom-church-suite' ) : __( 'Declined', 'kingdom-church-suite' ), __( 'Payment not completed', 'kingdom-church-suite' ) );
}
$own_summary = 'action' === $state[0]; // Transfer and payment-link pages show their own amount box.
?>
<div class="kcs-result kcs-result--<?php echo esc_attr( $payment->status ); ?> kcs-result--<?php echo esc_attr( $state[0] ); ?>" data-kcs-payment-status="<?php echo esc_attr( $payment->reference ); ?>" data-status="<?php echo esc_attr( $payment->status ); ?>">
	<div class="kcs-result__card">
	<div class="kcs-result__top">
		<div class="kcs-result__icon<?php echo 'pending' === $state[0] ? ' kcs-result__icon--wait' : ''; ?>"><?php echo $kcs_icons[ $state[1] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<p class="kcs-result__eyebrow"><?php echo esc_html( $state[2] ); ?></p>
		<h2 class="kcs-result__title"><?php echo esc_html( $state[3] ); ?></h2>
		<?php if ( ! $own_summary ) : ?><p class="kcs-result__amount"><?php echo esc_html( $amount ); ?></p><?php endif; ?>
	</div>
	<div class="kcs-result__body">
	<?php if ( 'completed' === $payment->status ) : ?>
		<p class="kcs-result__lead"><?php esc_html_e( 'Your gift was received successfully. A receipt has been sent to your email.', 'kingdom-church-suite' ); ?></p>
		<blockquote class="kcs-verse"><p>“<?php esc_html_e( 'Give, and it will be given to you. A good measure, pressed down, shaken together and running over…', 'kingdom-church-suite' ); ?>”</p><cite>Luke 6:38</cite></blockquote>
	<?php elseif ( KCS_Transfers::is_manual( $payment ) && 'review' === $payment->status ) : ?>
		<?php $kcs_tr = KCS_Transfers::meta( $payment )['transfer'] ?? array(); ?>
		<p><?php /* translators: %s: amount */ printf( esc_html__( 'You told us you sent %s. We will check our account and email your receipt as soon as it is confirmed.', 'kingdom-church-suite' ), '<strong>' . esc_html( $amount ) . '</strong>' ); ?></p>
		<?php if ( $kcs_tr ) : ?>
			<p class="kcs-muted"><?php /* translators: 1: payer name, 2: date */ printf( esc_html__( 'Sent by %1$s on %2$s.', 'kingdom-church-suite' ), esc_html( $kcs_tr['payer'] ), esc_html( mysql2date( get_option( 'date_format' ), $kcs_tr['date'] ) ) ); ?></p>
		<?php endif; ?>
	<?php elseif ( $gateway instanceof KCS_Gateway_Payment_Link && 'pending' === $payment->status ) : ?>
		<?php
		$kcs_tn   = isset( $_GET['kcs_tn'] ) ? sanitize_key( $_GET['kcs_tn'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
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
		?>
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
		<?php if ( ! empty( $kcs_rej['reason'] ) ) : ?><p><?php echo esc_html( $kcs_rej['reason'] ); ?></p><?php endif; ?>
		<p><?php esc_html_e( 'If you have already sent the money, please reply to our email with your transfer receipt.', 'kingdom-church-suite' ); ?></p>
	<?php elseif ( in_array( $payment->status, array( 'pending', 'review' ), true ) ) : ?>
		<p><?php esc_html_e( 'This usually takes a few seconds. This page will update automatically.', 'kingdom-church-suite' ); ?></p>
		<?php if ( 'pending' === $payment->status && ! KCS_Transfers::is_manual( $payment ) ) : ?>
			<p class="kcs-muted"><?php esc_html_e( 'Did not finish paying, or saw an error on the payment page? Nothing was taken: you can try again or choose another payment method.', 'kingdom-church-suite' ); ?></p>
			<p><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( $again ); ?>"><?php esc_html_e( 'Try again', 'kingdom-church-suite' ); ?></a></p>
		<?php endif; ?>
	<?php else : ?>
		<?php if ( 'cancelled' === $payment->status ) : ?>
			<p><?php esc_html_e( 'The payment was cancelled before it was finished, so no money was taken. You can try again whenever you are ready.', 'kingdom-church-suite' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Your bank or card provider did not approve this payment, so no money was taken. Please try again, or use another card or payment method.', 'kingdom-church-suite' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( ! $own_summary ) : ?>
		<dl class="kcs-result__receipt">
			<div><dt><?php esc_html_e( 'For', 'kingdom-church-suite' ); ?></dt><dd><?php echo esc_html( $purpose ); ?></dd></div>
			<?php if ( $gateway ) : ?><div><dt><?php esc_html_e( 'Payment method', 'kingdom-church-suite' ); ?></dt><dd><?php echo esc_html( $gateway->display_title() ); ?></dd></div><?php endif; ?>
			<div><dt><?php echo 'completed' === $payment->status ? esc_html__( 'Paid on', 'kingdom-church-suite' ) : esc_html__( 'Date', 'kingdom-church-suite' ); ?></dt><dd><?php echo esc_html( kcs_datetime( $payment->paid_at ? $payment->paid_at : $payment->created_at ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Reference', 'kingdom-church-suite' ); ?></dt><dd><code><?php echo esc_html( $payment->reference ); ?></code> <?php echo $kcs_copy( $payment->reference ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div>
		</dl>
	<?php endif; ?>
	</div>
	<div class="kcs-result__actions">
		<?php if ( in_array( $payment->status, array( 'failed', 'cancelled' ), true ) && ! $manual ) : ?>
			<a class="kcs-btn kcs-btn--lg" href="<?php echo esc_url( $again ); ?>"><?php esc_html_e( 'Try again', 'kingdom-church-suite' ); ?></a>
			<a class="kcs-btn kcs-btn--lg kcs-btn--ghost" href="<?php echo esc_url( $back ); ?>"><?php echo $payment->partner_id ? esc_html__( 'Back to my dashboard', 'kingdom-church-suite' ) : esc_html__( 'Back to homepage', 'kingdom-church-suite' ); ?></a>
		<?php else : ?>
			<a class="kcs-btn kcs-btn--lg" href="<?php echo esc_url( $back ); ?>"><?php echo $payment->partner_id ? esc_html__( 'Back to my dashboard', 'kingdom-church-suite' ) : esc_html__( 'Back to homepage', 'kingdom-church-suite' ); ?></a>
			<?php if ( 'completed' === $payment->status ) : ?>
				<a class="kcs-btn kcs-btn--lg kcs-btn--ghost" href="<?php echo esc_url( KCS_Receipts::url( $payment ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View receipt', 'kingdom-church-suite' ); ?></a>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	</div>
</div>
