<?php
/**
 * Partner login form.
 *
 * @package KingdomChurchSuite
 * @var string $notice Optional notice.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="kcs-auth">
	<?php if ( ! empty( $notice ) ) : ?><div class="kcs-alert kcs-alert--info"><?php echo esc_html( $notice ); ?></div><?php endif; ?>
	<?php if ( ! empty( $_GET['kcs_out'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?><div class="kcs-alert kcs-alert--success" role="status"><?php esc_html_e( 'You are logged out.', 'kingdom-church-suite' ); ?></div><?php endif; ?>
	<form method="post" class="kcs-form kcs-auth__form">
		<h3 class="kcs-form__title"><?php esc_html_e( 'Log in', 'kingdom-church-suite' ); ?></h3>
		<p class="kcs-muted kcs-auth__sub"><?php esc_html_e( 'For partners and church staff.', 'kingdom-church-suite' ); ?></p>
		<label><?php esc_html_e( 'Email or username', 'kingdom-church-suite' ); ?><input type="text" name="log" required autocomplete="username"></label>
		<label><?php esc_html_e( 'Password', 'kingdom-church-suite' ); ?><input type="password" name="pwd" required autocomplete="current-password"></label>
		<label class="kcs-check"><input type="checkbox" name="rememberme" value="1"> <?php esc_html_e( 'Remember me', 'kingdom-church-suite' ); ?></label>
		<input type="hidden" name="kcs_form" value="login">
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr( ! empty( $redirect_to ) ? $redirect_to : ( isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
		<input type="text" name="kcs_website" class="kcs-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
		<?php wp_nonce_field( 'kcs_login', '_kcs_nonce' ); ?>
		<button type="submit" class="kcs-btn kcs-btn--block"><?php esc_html_e( 'Log in', 'kingdom-church-suite' ); ?></button>
		<p class="kcs-auth__links">
			<a href="<?php echo esc_url( wp_lostpassword_url( kcs_page_url( 'partner_login' ) ) ); ?>"><?php esc_html_e( 'Forgot password?', 'kingdom-church-suite' ); ?></a>
			· <a href="<?php echo esc_url( kcs_page_url( 'partner_register' ) ); ?>"><?php esc_html_e( 'Become a partner', 'kingdom-church-suite' ); ?></a>
		</p>
	</form>
</div>
