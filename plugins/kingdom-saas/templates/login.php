<?php
/**
 * Platform log-in (replaces the WordPress login screen on the platform site).
 *
 * @package KingdomSaaS
 * @var array|null $flash Flash message.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
$out      = ! empty( $_GET['ks_out'] );
// phpcs:enable
?>
<section class="ks-band ks-login">
	<div class="ks-wrap ks-login__in">
		<form method="post" class="ks-card ks-form ks-login__card">
			<input type="hidden" name="ks_do" value="login">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>">
			<?php wp_nonce_field( 'ks_login', '_ks' ); ?>
			<div class="ks-login__head">
				<span class="ks-logo__mark" aria-hidden="true">✚</span>
				<h1><?php esc_html_e( 'Welcome back', 'kingdom-saas' ); ?></h1>
				<p class="ks-muted"><?php esc_html_e( 'Log in to manage your church website.', 'kingdom-saas' ); ?></p>
			</div>
			<?php if ( $flash ) : ?><div class="ks-alert ks-alert--<?php echo esc_attr( $flash[1] ); ?>" role="alert"><?php echo esc_html( $flash[0] ); ?></div><?php endif; ?>
			<?php if ( $out ) : ?><div class="ks-alert ks-alert--success" role="status"><?php esc_html_e( 'You are logged out.', 'kingdom-saas' ); ?></div><?php endif; ?>
			<label><?php esc_html_e( 'Email or username', 'kingdom-saas' ); ?><input name="log" required autocomplete="username" autocapitalize="none" spellcheck="false" value="<?php echo esc_attr( $flash[2]['log'] ?? '' ); ?>"></label>
			<label><?php esc_html_e( 'Password', 'kingdom-saas' ); ?><input type="password" name="pwd" required autocomplete="current-password"></label>
			<label class="ks-check"><input type="checkbox" name="rememberme" value="1" checked> <?php esc_html_e( 'Keep me logged in', 'kingdom-saas' ); ?></label>
			<button class="ks-btn ks-btn--block"><?php esc_html_e( 'Log in', 'kingdom-saas' ); ?></button>
			<p class="ks-small ks-center"><a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgot your password?', 'kingdom-saas' ); ?></a></p>
			<p class="ks-small ks-center"><?php esc_html_e( 'New here?', 'kingdom-saas' ); ?> <a href="<?php echo esc_url( ks_platform_url( 'signup/' ) ); ?>"><?php esc_html_e( 'Start your free trial', 'kingdom-saas' ); ?></a></p>
		</form>
	</div>
</section>
