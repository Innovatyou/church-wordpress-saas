<?php
/**
 * Login card for partners and church staff (the same account system; staff go to Church Admin).
 *
 * @package KingdomChurchSuite
 * @var string $notice      Optional notice.
 * @var string $redirect_to Where to go after logging in.
 * @var string $mode        partner|staff.
 */

defined( 'ABSPATH' ) || exit;

$mode   = ( isset( $mode ) && 'staff' === $mode ) ? 'staff' : 'partner';
$name   = kcs_get_option( 'church_name', get_bloginfo( 'name' ) );
$logo   = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' ) : '';
$logo   = $logo ? $logo : ( class_exists( 'KCS_PWA' ) ? KCS_PWA::url( 'icon', array( 'size' => 192, 'v' => KCS_PWA::version() ) ) : '' );
$admin  = kcs_page_url( 'church_admin' );
$target = ! empty( $redirect_to ) ? $redirect_to : ( isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : ( 'staff' === $mode ? $admin : '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="kcs-auth kcs-auth--<?php echo esc_attr( $mode ); ?>">
	<div class="kcs-auth__card">
		<div class="kcs-auth__brand">
			<?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt="" class="kcs-auth__logo"><?php endif; ?>
			<span><?php echo esc_html( $name ); ?></span>
		</div>
		<nav class="kcs-auth__tabs" aria-label="<?php esc_attr_e( 'Who is logging in', 'kingdom-church-suite' ); ?>">
			<a href="<?php echo esc_url( kcs_page_url( 'partner_login' ) ); ?>"<?php echo 'partner' === $mode ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Partner', 'kingdom-church-suite' ); ?></a>
			<a href="<?php echo esc_url( $admin ); ?>"<?php echo 'staff' === $mode ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Church staff', 'kingdom-church-suite' ); ?></a>
		</nav>
		<?php if ( ! empty( $notice ) ) : ?><div class="kcs-alert kcs-alert--info"><?php echo esc_html( $notice ); ?></div><?php endif; ?>
		<?php if ( ! empty( $_GET['kcs_out'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?><div class="kcs-alert kcs-alert--success" role="status"><?php esc_html_e( 'You are logged out.', 'kingdom-church-suite' ); ?></div><?php endif; ?>
		<form method="post" class="kcs-form kcs-auth__form">
			<h2 class="kcs-form__title"><?php echo 'staff' === $mode ? esc_html__( 'Church Admin login', 'kingdom-church-suite' ) : esc_html__( 'Welcome back, partner', 'kingdom-church-suite' ); ?></h2>
			<p class="kcs-muted kcs-auth__sub"><?php echo 'staff' === $mode ? esc_html__( 'For pastors, administrators and church staff. You will go straight to your Church Admin dashboard.', 'kingdom-church-suite' ) : esc_html__( 'See your giving, your partnership plan and updates from the church.', 'kingdom-church-suite' ); ?></p>
			<label><?php esc_html_e( 'Email or username', 'kingdom-church-suite' ); ?><input type="text" name="log" required autocomplete="username"></label>
			<label><?php esc_html_e( 'Password', 'kingdom-church-suite' ); ?><input type="password" name="pwd" required autocomplete="current-password"></label>
			<label class="kcs-check"><input type="checkbox" name="rememberme" value="1"> <?php esc_html_e( 'Remember me', 'kingdom-church-suite' ); ?></label>
			<input type="hidden" name="kcs_form" value="login">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $target ); ?>">
			<input type="text" name="kcs_website" class="kcs-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
			<?php wp_nonce_field( 'kcs_login', '_kcs_nonce' ); ?>
			<button type="submit" class="kcs-btn kcs-btn--block"><?php echo 'staff' === $mode ? esc_html__( 'Log in to Church Admin', 'kingdom-church-suite' ) : esc_html__( 'Log in', 'kingdom-church-suite' ); ?></button>
			<p class="kcs-auth__links">
				<a href="<?php echo esc_url( wp_lostpassword_url( 'staff' === $mode ? $admin : kcs_page_url( 'partner_login' ) ) ); ?>"><?php esc_html_e( 'Forgot password?', 'kingdom-church-suite' ); ?></a>
				<?php if ( 'partner' === $mode ) : ?>· <a href="<?php echo esc_url( kcs_page_url( 'partner_register' ) ); ?>"><?php esc_html_e( 'Become a partner', 'kingdom-church-suite' ); ?></a><?php endif; ?>
			</p>
		</form>
	</div>
</div>
