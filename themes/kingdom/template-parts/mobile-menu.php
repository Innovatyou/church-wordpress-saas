<?php
/**
 * Full-screen mobile menu: brand colours, sliding sub-menus, quick actions, contact and social.
 *
 * Opened by any [data-nav-toggle] button (header and phone tab bar). See main.js.
 *
 * @package Kingdom
 */

defined( 'ABSPATH' ) || exit;

$kingdom_tree     = kingdom_menu_tree( 'primary' );
$kingdom_social   = kingdom_social_links();
$kingdom_services = kingdom_service_times();
$kingdom_phone    = get_theme_mod( 'kingdom_phone' );
$kingdom_email    = get_theme_mod( 'kingdom_email' );
$kingdom_address  = get_theme_mod( 'kingdom_address' );
$kingdom_plugin   = kingdom_plugin_active();
$kingdom_cta_text = get_theme_mod( 'kingdom_header_cta_text', __( 'Give', 'kingdom' ) );
$kingdom_cta_url  = get_theme_mod( 'kingdom_header_cta_url' );
if ( ! $kingdom_cta_url && $kingdom_plugin ) {
	$kingdom_cta_url = kcs_page_url( 'give' );
}
$kingdom_watch   = $kingdom_plugin ? kcs_page_url( 'watch_live' ) : '';
$kingdom_ytlive  = get_option( 'kingdom_ytlive_status_store' );
$kingdom_is_live = is_array( $kingdom_ytlive ) && 'live' === ( $kingdom_ytlive['state'] ?? '' );

?>
<div class="k-drawer" id="k-drawer" data-drawer role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Menu', 'kingdom' ); ?>" hidden>
	<div class="k-drawer__top">
		<a class="k-drawer__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php
			$kingdom_light = get_theme_mod( 'kingdom_logo_light' );
			$kingdom_logo  = $kingdom_light ? $kingdom_light : ( has_custom_logo() ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' ) : '' );
			if ( $kingdom_logo ) :
				?>
				<span class="k-drawer__logo<?php echo $kingdom_light ? ' is-light' : ''; ?>"><img src="<?php echo esc_url( $kingdom_logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></span>
			<?php else : ?>
				<span class="k-brand__mark" aria-hidden="true"></span><span class="k-drawer__name"><?php bloginfo( 'name' ); ?></span>
			<?php endif; ?>
		</a>
		<button type="button" class="k-drawer__close" data-drawer-close aria-label="<?php esc_attr_e( 'Close menu', 'kingdom' ); ?>"><?php echo kingdom_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
	</div>

	<div class="k-drawer__panels" data-drawer-panels>
		<section class="k-drawer__panel is-active" id="k-drawer-root" data-drawer-panel aria-label="<?php esc_attr_e( 'Main menu', 'kingdom' ); ?>">
			<ul class="k-drawer__menu">
				<?php foreach ( $kingdom_tree as $kingdom_item ) : ?>
					<?php kingdom_drawer_item( $kingdom_item, 'k-drawer-root' ); ?>
				<?php endforeach; ?>
			</ul>

			<div class="k-drawer__actions">
				<?php if ( $kingdom_watch ) : ?>
					<a class="k-drawer__btn" href="<?php echo esc_url( $kingdom_watch ); ?>">
						<?php if ( $kingdom_is_live ) : ?><span class="k-drawer__live"><?php esc_html_e( 'Live', 'kingdom' ); ?></span><?php else : ?><?php echo kingdom_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
						<?php echo $kingdom_is_live ? esc_html__( 'Watch now', 'kingdom' ) : esc_html__( 'Watch live', 'kingdom' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $kingdom_plugin && current_user_can( 'manage_kcs' ) ) : ?>
					<a class="k-drawer__btn" href="<?php echo esc_url( kcs_page_url( 'church_admin' ) ); ?>"><?php echo kingdom_icon( 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Church admin', 'kingdom' ); ?></a>
				<?php endif; ?>
				<?php if ( $kingdom_plugin ) : ?>
					<?php $kingdom_partner = is_user_logged_in() && kcs_is_partner(); ?>
					<a class="k-drawer__btn" href="<?php echo esc_url( $kingdom_partner ? kcs_page_url( 'partner_dashboard' ) : kcs_page_url( 'partner_login' ) ); ?>"><?php echo kingdom_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $kingdom_partner ? esc_html__( 'My partner dashboard', 'kingdom' ) : esc_html__( 'Partner login', 'kingdom' ); ?></a>
				<?php endif; ?>
				<?php if ( class_exists( 'KCS_PWA' ) && KCS_PWA::enabled() ) : ?>
					<button type="button" class="k-drawer__btn" data-kcs-install hidden><?php echo kingdom_icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Install our app', 'kingdom' ); ?></button>
					<button type="button" class="k-drawer__btn" data-kcs-push hidden data-off-label="<?php esc_attr_e( 'Turn on notifications', 'kingdom' ); ?>" data-on-label="<?php esc_attr_e( 'Notifications on', 'kingdom' ); ?>"><?php echo kingdom_icon( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span data-kcs-push-label><?php esc_html_e( 'Turn on notifications', 'kingdom' ); ?></span></button>
				<?php endif; ?>
				<?php if ( $kingdom_cta_url ) : ?>
					<a class="k-drawer__btn k-drawer__btn--primary" href="<?php echo esc_url( $kingdom_cta_url ); ?>"><?php echo kingdom_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $kingdom_cta_text ); ?></a>
				<?php endif; ?>
			</div>

			<?php if ( $kingdom_phone || $kingdom_email || $kingdom_address || $kingdom_services ) : ?>
				<div class="k-drawer__block">
					<h2 class="k-drawer__heading"><?php esc_html_e( 'Contact us', 'kingdom' ); ?></h2>
					<ul class="k-drawer__contact">
						<?php if ( $kingdom_services ) : ?>
							<li><?php echo kingdom_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span>
								<?php
								foreach ( array_slice( $kingdom_services, 0, 3 ) as $kingdom_s ) {
									echo '<span class="k-drawer__service">' . esc_html( ( $kingdom_s['name'] ? $kingdom_s['name'] . ' · ' : '' ) . $kingdom_s['day'] . ' ' . $kingdom_s['time'] ) . '</span>';
								}
								?>
							</span></li>
						<?php endif; ?>
						<?php if ( $kingdom_phone ) : ?>
							<li><?php echo kingdom_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $kingdom_phone ) ); ?>"><?php echo esc_html( $kingdom_phone ); ?></a></li>
						<?php endif; ?>
						<?php if ( $kingdom_address ) : ?>
							<li><?php echo kingdom_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $kingdom_address ) ); ?>" target="_blank" rel="noopener"><?php echo nl2br( esc_html( $kingdom_address ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></li>
						<?php endif; ?>
						<?php if ( $kingdom_email ) : ?>
							<li><?php echo kingdom_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="mailto:<?php echo esc_attr( antispambot( $kingdom_email ) ); ?>"><?php echo esc_html( antispambot( $kingdom_email ) ); ?></a></li>
						<?php endif; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $kingdom_social ) : ?>
				<div class="k-drawer__block">
					<h2 class="k-drawer__heading"><?php esc_html_e( 'Follow us', 'kingdom' ); ?></h2>
					<div class="k-drawer__social">
						<?php foreach ( $kingdom_social as $kingdom_net => $kingdom_url ) : ?>
							<a href="<?php echo esc_url( $kingdom_url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( ucfirst( $kingdom_net ) ); ?>"><?php echo kingdom_icon( $kingdom_net ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( get_theme_mod( 'kingdom_palette_switcher', true ) ) : ?>
				<details class="k-drawer__block k-drawer__colours">
					<summary class="k-drawer__heading"><?php esc_html_e( 'Site colours', 'kingdom' ); ?><?php echo kingdom_icon( 'chevron-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></summary>
					<div class="k-nav__palette">
						<div class="k-palette__swatches">
							<?php foreach ( kingdom_palettes() as $kingdom_slug => $kingdom_p ) : ?>
								<button type="button" data-palette="<?php echo esc_attr( $kingdom_slug ); ?>" title="<?php echo esc_attr( $kingdom_p[0] ); ?>" style="--sw-a:<?php echo esc_attr( $kingdom_p[1] ); ?>;--sw-b:<?php echo esc_attr( $kingdom_p[2] ); ?>"><span class="screen-reader-text"><?php echo esc_html( $kingdom_p[0] ); ?></span></button>
							<?php endforeach; ?>
						</div>
						<?php kingdom_custom_colour_fields( 'k-nav-pal' ); ?>
					</div>
				</details>
			<?php endif; ?>
		</section>

		<?php
		foreach ( $kingdom_tree as $kingdom_item ) {
			if ( $kingdom_item['children'] ) {
				kingdom_drawer_panel( $kingdom_item, 'k-drawer-root' );
			}
		}
		?>
	</div>
</div>
