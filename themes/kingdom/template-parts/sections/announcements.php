<?php
/**
 * Announcement slider under the homepage banner (Church Admin → Announcement slider).
 *
 * @package Kingdom
 */

if ( ! shortcode_exists( 'kcs_announcements' ) ) {
	return;
}
$kingdom_slider = do_shortcode( '[kcs_announcements where="home"]' );
if ( '' === trim( $kingdom_slider ) ) {
	return;
}
?>
<section class="k-announcements" aria-label="<?php esc_attr_e( 'Announcements', 'kingdom' ); ?>">
	<?php echo $kingdom_slider; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</section>
