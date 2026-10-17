<?php
/**
 * Partnership plans.
 *
 * @package Kingdom
 */

// Needs the Ministry OS Church Suite plugin (plans, payments, prayer and live stream).
if ( ! kingdom_plugin_active() ) {
	return;
}
?>
<section class="k-section k-section--alt k-partnership" id="partnership">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Partnership', 'kingdom' ), get_theme_mod( 'kingdom_partnership_title', __( 'Partner with the vision', 'kingdom' ) ), get_theme_mod( 'kingdom_partnership_text', __( 'Your monthly partnership helps us reach the lost, care for families and raise disciples across the nations.', 'kingdom' ) ) ); ?>
		<div data-reveal><?php echo kingdom_shortcode( '[kcs_partnership_plans]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	</div>
</section>
