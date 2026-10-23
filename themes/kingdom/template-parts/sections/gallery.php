<?php
/**
 * Church photo gallery: the photos the church uploaded (Church Admin → Pictures & text), laid out
 * to fit however many there are.
 *
 * @package Kingdom
 */

// Only the church's own photos: empty slots are skipped, and with no photos the section is hidden.
$kingdom_items = array();
for ( $kingdom_i = 1; $kingdom_i <= 6; $kingdom_i++ ) {
	$kingdom_photo = get_theme_mod( 'kingdom_gallery_' . $kingdom_i );
	if ( $kingdom_photo ) {
		$kingdom_items[] = array( $kingdom_photo, get_theme_mod( 'kingdom_gallery_' . $kingdom_i . '_caption', '' ) );
	}
}
if ( ! $kingdom_items ) {
	return;
}
$kingdom_count = count( $kingdom_items );
?>
<section class="k-section k-gallery-section">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Gallery', 'kingdom' ), get_theme_mod( 'kingdom_gallery_title', __( 'Life at church', 'kingdom' ) ) ); ?>
		<div class="k-gallery k-gallery--<?php echo (int) $kingdom_count; ?><?php echo $kingdom_count % 2 ? ' k-gallery--odd' : ''; ?>">
			<?php foreach ( $kingdom_items as $kingdom_n => $kingdom_item ) : ?>
				<figure class="k-gallery__item k-gallery__item--<?php echo (int) $kingdom_n + 1; ?>" data-tilt data-reveal>
					<img src="<?php echo esc_url( $kingdom_item[0] ); ?>" alt="<?php echo esc_attr( $kingdom_item[1] ? $kingdom_item[1] : get_bloginfo( 'name' ) ); ?>" loading="lazy">
					<?php if ( $kingdom_item[1] ) : ?><figcaption><?php echo esc_html( $kingdom_item[1] ); ?></figcaption><?php endif; ?>
				</figure>
			<?php endforeach; ?>
		</div>
		<?php if ( current_user_can( 'customize' ) ) : ?>
			<p class="k-center k-mt"><a class="k-link" href="<?php echo esc_url( kingdom_plugin_active() && function_exists( 'kcs_page_url' ) && kcs_get_option( 'page_church_admin' ) ? add_query_arg( 'view', 'content', kcs_page_url( 'church_admin' ) ) . '#group-pictures' : admin_url( 'customize.php?autofocus[section]=kingdom_images' ) ); ?>"><?php esc_html_e( 'Change these photos', 'kingdom' ); ?> →</a></p>
		<?php endif; ?>
	</div>
</section>
