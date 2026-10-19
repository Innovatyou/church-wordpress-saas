<?php
/**
 * Footer.
 *
 * @package Kingdom
 */

defined( 'ABSPATH' ) || exit;

$services = kingdom_service_times();
$social   = kingdom_social_links();
?>
</main>

<?php
$kingdom_layout  = get_theme_mod( 'kingdom_footer_layout', 'columns' );
$kingdom_colour  = get_theme_mod( 'kingdom_footer_colour', 'dark' );
$kingdom_show    = array(
	'about'   => get_theme_mod( 'kingdom_footer_show_about', true ),
	'times'   => get_theme_mod( 'kingdom_footer_show_times', true ) && $services,
	'links'   => get_theme_mod( 'kingdom_footer_show_links', true ) && has_nav_menu( 'footer' ),
	'contact' => get_theme_mod( 'kingdom_footer_show_contact', true ) && ( get_theme_mod( 'kingdom_address' ) || get_theme_mod( 'kingdom_phone' ) || get_theme_mod( 'kingdom_email' ) ),
	'bottom'  => get_theme_mod( 'kingdom_footer_show_bottom', true ),
);
$kingdom_widgets = 'columns' === $kingdom_layout && ( is_active_sidebar( 'footer-1' ) || is_active_sidebar( 'footer-2' ) || is_active_sidebar( 'footer-3' ) || is_active_sidebar( 'footer-4' ) );
$kingdom_menu    = function ( $class ) {
	wp_nav_menu(
		array(
			'theme_location' => 'footer',
			'container'      => false,
			'menu_class'     => $class,
			'depth'          => 1,
			'fallback_cb'    => false,
		)
	);
};
$kingdom_socials = function () use ( $social ) {
	if ( ! $social ) {
		return;
	}
	echo '<div class="k-social">';
	foreach ( $social as $net => $url ) {
		echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( ucfirst( $net ) ) . '">' . kingdom_icon( $net ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</div>';
};
// The platform's "Powered by" credit (plans without white label) stays visible even when the copyright line or footer is off.
$kingdom_credit  = (string) apply_filters( 'kingdom_footer_credit', '' );
$kingdom_copy    = get_theme_mod( 'kingdom_copyright' ) ? get_theme_mod( 'kingdom_copyright' ) : '© ' . gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ) . '. ' . __( 'All rights reserved.', 'kingdom' );
?>
<?php if ( ! get_theme_mod( 'kingdom_footer_show', true ) && '' !== trim( wp_strip_all_tags( $kingdom_credit ) ) ) : ?>
<footer class="k-footer k-footer--credit k-footer--<?php echo esc_attr( $kingdom_colour ); ?>"><div class="k-footer__bottom"><div class="k-container"><p><?php echo wp_kses_post( preg_replace( '/^\s*·\s*/u', '', $kingdom_credit ) ); ?></p></div></div></footer>
<?php elseif ( get_theme_mod( 'kingdom_footer_show', true ) ) : ?>
<footer class="k-footer k-footer--<?php echo esc_attr( $kingdom_layout ); ?> k-footer--<?php echo esc_attr( $kingdom_colour ); ?>">
	<?php if ( get_theme_mod( 'kingdom_footer_newsletter', true ) && kingdom_plugin_active() && 'minimal' !== $kingdom_layout ) : ?>
		<div class="k-footer__newsletter">
			<div class="k-container">
				<?php echo do_shortcode( '[kcs_newsletter style="inline" title="' . esc_attr__( 'Get weekly encouragement', 'kingdom' ) . '" text="' . esc_attr__( 'Sermons, devotionals and church news — straight to your inbox.', 'kingdom' ) . '"]' ); ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( 'minimal' === $kingdom_layout ) : ?>
		<div class="k-container k-footer__slim">
			<?php if ( $kingdom_show['about'] ) : ?><strong class="k-footer__brand"><?php bloginfo( 'name' ); ?></strong><?php endif; ?>
			<?php
			if ( $kingdom_show['links'] ) {
				$kingdom_menu( 'k-footer__inline' );
			}
			if ( $kingdom_show['about'] ) {
				$kingdom_socials();
			}
			?>
			<?php if ( $kingdom_show['bottom'] ) : ?><p class="k-footer__copy"><?php echo esc_html( $kingdom_copy ); ?><?php echo wp_kses_post( $kingdom_credit ); ?></p><?php elseif ( $kingdom_credit ) : ?><p class="k-footer__copy"><?php echo wp_kses_post( preg_replace( '/^\s*·\s*/u', '', $kingdom_credit ) ); ?></p><?php endif; ?>
		</div>
	<?php elseif ( 'centered' === $kingdom_layout ) : ?>
		<div class="k-container k-footer__center">
			<?php if ( $kingdom_show['about'] ) : ?>
				<h3 class="k-footer__brand"><?php bloginfo( 'name' ); ?></h3>
				<?php if ( get_theme_mod( 'kingdom_brand_motto' ) ) : ?><p class="k-footer__motto"><?php echo esc_html( get_theme_mod( 'kingdom_brand_motto' ) ); ?></p><?php endif; ?>
				<p class="k-footer__about"><?php echo esc_html( get_theme_mod( 'kingdom_footer_about', __( 'Raising a generation of believers who love God, love people and transform their world.', 'kingdom' ) ) ); ?></p>
			<?php endif; ?>
			<?php
			if ( $kingdom_show['links'] ) {
				$kingdom_menu( 'k-footer__inline' );
			}
			?>
			<?php if ( $kingdom_show['times'] ) : ?>
				<p class="k-footer__times">
					<?php foreach ( $services as $s ) : ?>
						<span><strong><?php echo esc_html( $s['name'] ); ?></strong> <?php echo esc_html( $s['day'] . ' · ' . $s['time'] ); ?></span>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>
			<?php if ( $kingdom_show['contact'] ) : ?>
				<p class="k-footer__inline-contact">
					<?php if ( get_theme_mod( 'kingdom_address' ) ) : ?><span><?php echo kingdom_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( preg_replace( '/\s*,?\s*\n\s*/', ', ', trim( get_theme_mod( 'kingdom_address' ) ) ) ); ?></span><?php endif; ?>
					<?php if ( get_theme_mod( 'kingdom_phone' ) ) : ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', get_theme_mod( 'kingdom_phone' ) ) ); ?>"><?php echo kingdom_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( get_theme_mod( 'kingdom_phone' ) ); ?></a><?php endif; ?>
					<?php if ( get_theme_mod( 'kingdom_email' ) ) : ?><a href="mailto:<?php echo esc_attr( get_theme_mod( 'kingdom_email' ) ); ?>"><?php echo kingdom_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( get_theme_mod( 'kingdom_email' ) ); ?></a><?php endif; ?>
				</p>
			<?php endif; ?>
			<?php
			if ( $kingdom_show['about'] ) {
				$kingdom_socials();
			}
			?>
		</div>
	<?php elseif ( $kingdom_widgets || array_filter( array( $kingdom_show['about'], $kingdom_show['times'], $kingdom_show['links'], $kingdom_show['contact'] ) ) ) : ?>
		<?php $kingdom_cols = $kingdom_widgets ? 4 : count( array_filter( array( $kingdom_show['about'], $kingdom_show['times'], $kingdom_show['links'], $kingdom_show['contact'] ) ) ); ?>
		<div class="k-container k-footer__grid k-footer__grid--<?php echo (int) $kingdom_cols; ?>">
			<?php if ( $kingdom_widgets ) : ?>
				<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
					<div class="k-footer__col"><?php dynamic_sidebar( 'footer-' . $i ); ?></div>
				<?php endfor; ?>
			<?php else : ?>
				<?php if ( $kingdom_show['about'] ) : ?>
					<div class="k-footer__col">
						<h3 class="k-footer__brand"><?php bloginfo( 'name' ); ?></h3>
						<?php if ( get_theme_mod( 'kingdom_brand_motto' ) ) : ?><p class="k-footer__motto"><?php echo esc_html( get_theme_mod( 'kingdom_brand_motto' ) ); ?></p><?php endif; ?>
						<p><?php echo esc_html( get_theme_mod( 'kingdom_footer_about', __( 'Raising a generation of believers who love God, love people and transform their world.', 'kingdom' ) ) ); ?></p>
						<?php $kingdom_socials(); ?>
					</div>
				<?php endif; ?>
				<?php if ( $kingdom_show['times'] ) : ?>
					<div class="k-footer__col">
						<h4><?php esc_html_e( 'Service times', 'kingdom' ); ?></h4>
						<ul class="k-footer__list">
							<?php foreach ( $services as $s ) : ?>
								<li><strong><?php echo esc_html( $s['name'] ); ?></strong><br><?php echo esc_html( $s['day'] . ' · ' . $s['time'] ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<?php if ( $kingdom_show['links'] ) : ?>
					<div class="k-footer__col">
						<h4><?php esc_html_e( 'Quick links', 'kingdom' ); ?></h4>
						<?php $kingdom_menu( 'k-footer__list' ); ?>
					</div>
				<?php endif; ?>
				<?php if ( $kingdom_show['contact'] ) : ?>
					<div class="k-footer__col">
						<h4><?php esc_html_e( 'Visit us', 'kingdom' ); ?></h4>
						<ul class="k-footer__list k-footer__contact">
							<?php if ( get_theme_mod( 'kingdom_address' ) ) : ?><li><?php echo kingdom_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span><?php echo nl2br( esc_html( get_theme_mod( 'kingdom_address' ) ) ); ?></span></li><?php endif; ?>
							<?php if ( get_theme_mod( 'kingdom_phone' ) ) : ?><li><?php echo kingdom_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', get_theme_mod( 'kingdom_phone' ) ) ); ?>"><?php echo esc_html( get_theme_mod( 'kingdom_phone' ) ); ?></a></li><?php endif; ?>
							<?php if ( get_theme_mod( 'kingdom_email' ) ) : ?><li><?php echo kingdom_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="mailto:<?php echo esc_attr( get_theme_mod( 'kingdom_email' ) ); ?>"><?php echo esc_html( get_theme_mod( 'kingdom_email' ) ); ?></a></li><?php endif; ?>
						</ul>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ( $kingdom_show['bottom'] || $kingdom_credit ) && 'minimal' !== $kingdom_layout ) : ?>
		<div class="k-footer__bottom">
			<div class="k-container">
				<?php if ( $kingdom_show['bottom'] ) : ?>
					<p><?php echo esc_html( $kingdom_copy ); ?><?php echo wp_kses_post( $kingdom_credit ); ?></p>
					<?php if ( function_exists( 'the_privacy_policy_link' ) ) { the_privacy_policy_link(); } ?>
					<?php if ( kingdom_plugin_active() && ! is_user_logged_in() ) : ?><a class="k-footer__staff" href="<?php echo esc_url( kcs_page_url( 'church_admin' ) ); ?>"><?php esc_html_e( 'Church staff login', 'kingdom' ); ?></a><?php endif; ?>
				<?php else : ?>
					<p><?php echo wp_kses_post( preg_replace( '/^\s*·\s*/u', '', $kingdom_credit ) ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</footer>
<?php endif; ?>

<?php $kingdom_wa = kingdom_whatsapp_chat(); ?>
<?php if ( $kingdom_wa ) : ?>
	<div class="k-wa" data-wa>
		<div class="k-wa__panel" id="k-wa-panel" role="dialog" aria-label="<?php esc_attr_e( 'Chat with us on WhatsApp', 'kingdom' ); ?>" hidden>
			<div class="k-wa__head">
				<span class="k-wa__avatar" aria-hidden="true"><?php echo kingdom_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span><strong><?php echo esc_html( $kingdom_wa['title'] ); ?></strong><small><?php esc_html_e( 'Typically replies within a few hours', 'kingdom' ); ?></small></span>
				<button type="button" class="k-wa__close" data-wa-toggle aria-label="<?php esc_attr_e( 'Close chat', 'kingdom' ); ?>">×</button>
			</div>
			<div class="k-wa__body">
				<p class="k-wa__bubble"><?php echo esc_html( $kingdom_wa['greeting'] ); ?></p>
				<?php foreach ( $kingdom_wa['contacts'] as $kingdom_c ) : ?>
					<a class="k-wa__contact" href="<?php echo esc_url( $kingdom_c['url'] ); ?>" target="_blank" rel="noopener"><span><?php echo esc_html( $kingdom_c['label'] ); ?></span><b><?php esc_html_e( 'Start chat', 'kingdom' ); ?> →</b></a>
				<?php endforeach; ?>
				<?php if ( ! empty( $kingdom_wa['hours'] ) ) : ?><p class="k-wa__hours"><?php echo esc_html( $kingdom_wa['hours'] ); ?></p><?php endif; ?>
			</div>
		</div>
		<button type="button" class="k-wa__btn" data-wa-toggle aria-expanded="false" aria-controls="k-wa-panel">
			<?php echo kingdom_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="k-wa__label"><?php esc_html_e( 'Chat with us', 'kingdom' ); ?></span>
		</button>
	</div>
<?php endif; ?>
<?php if ( get_theme_mod( 'kingdom_mobile_nav', true ) ) : ?>
	<?php
	$kingdom_tabs = array(
		array( 'home', __( 'Home', 'kingdom' ), home_url( '/' ) ),
		array( 'play', __( 'Watch', 'kingdom' ), kingdom_plugin_active() ? kcs_page_url( 'watch_live' ) : kingdom_url( '', 'template-watch-live.php' ) ),
		array( 'heart', __( 'Give', 'kingdom' ), kingdom_plugin_active() ? kcs_page_url( 'give' ) : kingdom_url( '', 'template-give.php' ) ),
		array( 'hands', __( 'Prayer', 'kingdom' ), kingdom_plugin_active() ? kcs_page_url( 'prayer' ) : kingdom_url( '', 'template-prayer.php' ) ),
	);
	?>
	<nav class="k-tabbar" aria-label="<?php esc_attr_e( 'Quick navigation', 'kingdom' ); ?>">
		<?php foreach ( $kingdom_tabs as $kingdom_tab ) : ?>
			<a href="<?php echo esc_url( $kingdom_tab[2] ); ?>"<?php echo untrailingslashit( $kingdom_tab[2] ) === untrailingslashit( home_url( add_query_arg( array() ) ) ) ? ' aria-current="page"' : ''; ?>><?php echo kingdom_icon( $kingdom_tab[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $kingdom_tab[1] ); ?></span></a>
		<?php endforeach; ?>
		<button type="button" data-nav-toggle aria-controls="k-drawer" aria-expanded="false"><?php echo kingdom_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Menu', 'kingdom' ); ?></span></button>
	</nav>
<?php endif; ?>

<?php if ( get_theme_mod( 'kingdom_palette_switcher', true ) ) : ?>
	<div class="k-palette" data-palette-picker>
		<button type="button" class="k-palette__toggle" aria-expanded="false" aria-label="<?php esc_attr_e( 'Choose site colours', 'kingdom' ); ?>"><?php echo kingdom_icon( 'palette' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		<div class="k-palette__panel" hidden>
			<p><?php esc_html_e( 'Site colours', 'kingdom' ); ?></p>
			<div class="k-palette__swatches">
				<?php foreach ( kingdom_palettes() as $kingdom_slug => $kingdom_p ) : ?>
					<button type="button" data-palette="<?php echo esc_attr( $kingdom_slug ); ?>" title="<?php echo esc_attr( $kingdom_p[0] ); ?>" style="--sw-a:<?php echo esc_attr( $kingdom_p[1] ); ?>;--sw-b:<?php echo esc_attr( $kingdom_p[2] ); ?>"><span class="screen-reader-text"><?php echo esc_html( $kingdom_p[0] ); ?></span></button>
				<?php endforeach; ?>
			</div>
			<?php kingdom_custom_colour_fields( 'k-pal' ); ?>
			<button type="button" class="k-palette__reset" data-palette=""><?php esc_html_e( 'Reset to church colours', 'kingdom' ); ?></button>
		</div>
	</div>
<?php endif; ?>

<?php if ( get_theme_mod( 'kingdom_to_top', true ) ) : ?>
	<button type="button" class="k-to-top" data-to-top aria-label="<?php esc_attr_e( 'Back to top', 'kingdom' ); ?>">↑</button>
<?php endif; ?>
<div class="k-modal" data-video-modal role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Video', 'kingdom' ); ?>" hidden><button type="button" class="k-modal__close" data-modal-close aria-label="<?php esc_attr_e( 'Close', 'kingdom' ); ?>">×</button><div class="k-modal__body"></div></div>

<?php wp_footer(); ?>
</body>
</html>
