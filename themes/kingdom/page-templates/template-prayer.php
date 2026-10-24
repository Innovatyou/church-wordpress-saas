<?php
/**
 * Template Name: Prayer Request
 * Template Post Type: page
 *
 * The community prayer wall (with its request form).
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
if ( kingdom_content_has( 'kcs_prayer_wall' ) ) {
	kingdom_section( 'page-content' );
} else {
	// The prayer wall brings its own request form: hide a second one in the page text.
	add_filter( 'kcs_hide_prayer_form', '__return_true' );
	kingdom_section( 'page-content' );
	echo '<div class="k-container k-section">' . kingdom_shortcode( '[kcs_prayer_wall]' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
get_footer();
