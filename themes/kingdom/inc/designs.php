<?php
/**
 * Website design templates: complete looks a church can switch between
 * (Church Admin → Design & live stream → Website design, or Customize → Website design).
 *
 * A design restyles every page with its own stylesheet (assets/css/designs/{slug}.css)
 * and interactive effects (assets/js/designs.js). Colours and fonts stay the church's own;
 * each design suggests a palette and font pairing that can be applied in one click.
 *
 * @package Kingdom
 */

defined( 'ABSPATH' ) || exit;

/**
 * Available designs: slug => [label, short description, suggested palette, suggested font pair, preview colours [bg, surface, ink]].
 *
 * @return array
 */
function kingdom_designs() {
	return apply_filters(
		'kingdom_designs',
		array(
			'classic'   => array( __( 'Classic', 'kingdom' ), __( 'The original Ministry OS look: clean, bright and friendly.', 'kingdom' ), 'royal', 'classic', array( '#f6f3fa', '#ffffff', '#1f1b24' ) ),
			'aurora'    => array( __( 'Aurora Glass', 'kingdom' ), __( 'Flowing northern-lights colour, frosted glass cards and a soft light that follows the cursor.', 'kingdom' ), 'ocean', 'fresh', array( '#0b1220', '#1c2a4a', '#e8f1ff' ) ),
			'sanctuary' => array( __( 'Sanctuary', 'kingdom' ), __( 'Cathedral elegance: arched pictures, gold details, light rays and slow parallax.', 'kingdom' ), 'heritage', 'elegant', array( '#faf6ee', '#fffdf8', '#2a1d12' ) ),
			'pulse'     => array( __( 'Pulse Night', 'kingdom' ), __( 'Bold and dark for youth churches: neon edges, huge type and buttons that pull toward the cursor.', 'kingdom' ), 'midnight', 'youth', array( '#07070c', '#12121c', '#f4f4ff' ) ),
			'horizon'   => array( __( 'Horizon Editorial', 'kingdom' ), __( 'Magazine-style and calm: large headlines that rise into view and pictures that unveil as you scroll.', 'kingdom' ), 'jordan', 'editorial', array( '#ffffff', '#f4f4f1', '#111111' ) ),
			'ember'     => array( __( 'Ember Community', 'kingdom' ), __( 'Warm and welcoming: soft living shapes, wavy section edges and layered paper cards.', 'kingdom' ), 'sunrise', 'warm', array( '#fff7ef', '#ffffff', '#2b1a10' ) ),
			'cosmos'    => array( __( 'Cosmos 3D', 'kingdom' ), __( 'Immersive depth: a starfield that moves with the visitor, glowing orbits and cards that rise in 3D.', 'kingdom' ), 'royal', 'modern', array( '#05030d', '#141028', '#efeaff' ) ),
		)
	);
}

/**
 * The design shown on this request (an administrator can preview one with ?kingdom_design=slug).
 *
 * @return string
 */
function kingdom_design() {
	static $design = null;
	if ( null !== $design && did_action( 'wp' ) ) {
		return $design;
	}
	$designs = kingdom_designs();
	$design  = (string) get_theme_mod( 'kingdom_design', 'classic' );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview for people who can change the design.
	$preview = isset( $_GET['kingdom_design'] ) ? sanitize_key( wp_unslash( $_GET['kingdom_design'] ) ) : '';
	if ( $preview && isset( $designs[ $preview ] ) && ( current_user_can( 'edit_theme_options' ) || current_user_can( 'manage_options' ) ) ) {
		$design = $preview;
	}
	// Dashboards (Church Admin, partner portal) keep the calm classic look.
	if ( ! isset( $designs[ $design ] ) || ( function_exists( 'kingdom_dashboard_type' ) && kingdom_dashboard_type() ) ) {
		$design = 'classic';
	}
	return $design;
}

/**
 * Link that previews a design on the homepage.
 *
 * @param string $slug Design.
 * @return string
 */
function kingdom_design_preview_url( $slug ) {
	return add_query_arg( 'kingdom_design', rawurlencode( $slug ), home_url( '/' ) );
}

/**
 * <html data-design="…"> so a design can set colour variables at the root (plugin styles read them there).
 *
 * @param string $output Attributes.
 * @return string
 */
function kingdom_design_html_attr( $output ) {
	if ( is_admin() ) {
		return $output;
	}
	return $output . ' data-design="' . esc_attr( kingdom_design() ) . '"';
}
add_filter( 'language_attributes', 'kingdom_design_html_attr' );

/**
 * Body class for the design.
 *
 * @param array $classes Classes.
 * @return array
 */
function kingdom_design_body_class( $classes ) {
	$classes[] = 'k-design-' . kingdom_design();
	return $classes;
}
add_filter( 'body_class', 'kingdom_design_body_class' );

/**
 * Load the design's stylesheet and the shared effects script.
 */
function kingdom_design_assets() {
	$design = kingdom_design();
	if ( 'classic' === $design || ! file_exists( KINGDOM_DIR . '/assets/css/designs/' . $design . '.css' ) ) {
		return;
	}
	wp_enqueue_style( 'kingdom-design-base', KINGDOM_URI . '/assets/css/designs/base.css', array( 'kingdom-main' ), KINGDOM_VERSION );
	wp_enqueue_style( 'kingdom-design', KINGDOM_URI . '/assets/css/designs/' . $design . '.css', array( 'kingdom-design-base' ), KINGDOM_VERSION );
	wp_enqueue_script( 'kingdom-design', KINGDOM_URI . '/assets/js/designs.js', array(), KINGDOM_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'kingdom_design_assets', 20 );

/**
 * Apply a design's suggested palette and fonts.
 *
 * @param string $slug Design.
 */
function kingdom_apply_design_style( $slug ) {
	$designs = kingdom_designs();
	if ( ! isset( $designs[ $slug ] ) ) {
		return;
	}
	if ( isset( kingdom_palettes()[ $designs[ $slug ][2] ] ) ) {
		set_theme_mod( 'kingdom_palette', $designs[ $slug ][2] );
	}
	if ( isset( kingdom_font_pairs()[ $designs[ $slug ][3] ] ) ) {
		set_theme_mod( 'kingdom_font_pair', $designs[ $slug ][3] );
	}
}

/**
 * Church Admin saved the design: apply its colours and fonts when asked (a one-time action).
 *
 * @param array $groups Saved groups.
 */
function kingdom_design_saved( $groups ) {
	if ( ! in_array( 'design', (array) $groups, true ) ) {
		return;
	}
	if ( get_theme_mod( 'kingdom_design_match', false ) ) {
		kingdom_apply_design_style( (string) get_theme_mod( 'kingdom_design', 'classic' ) );
		set_theme_mod( 'kingdom_design_match', false );
	}
}
add_action( 'kcs_website_saved', 'kingdom_design_saved' );

/**
 * Picker data for Church Admin: slug => [label, description, preview colours, preview URL].
 *
 * @return array
 */
function kingdom_design_cards() {
	$out = array();
	foreach ( kingdom_designs() as $slug => $d ) {
		$out[ $slug ] = array(
			'label'   => $d[0],
			'desc'    => $d[1],
			'colours' => $d[4],
			'preview' => kingdom_design_preview_url( $slug ),
		);
	}
	return $out;
}
