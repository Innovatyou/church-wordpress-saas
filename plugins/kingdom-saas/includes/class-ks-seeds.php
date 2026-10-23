<?php
/**
 * One-time content for particular churches (wording they sent us), applied on their own site.
 *
 * Each file in includes/seeds/ returns:
 *   match        Lower-case text the church's site name must contain.
 *   mods         Theme settings to fill in. Ones the church has already set are left alone.
 *   charity_page Optional: title of a Charity page to create (under About in the main menu).
 *
 * A seed runs once per site; afterwards the church edits everything in Church Admin.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Seeds.
 */
class KS_Seeds {

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( ! is_main_site() ) {
			add_action( 'init', array( __CLASS__, 'maybe_apply' ), 20 );
		}
	}

	/**
	 * Apply the seeds meant for this site that have not run yet.
	 */
	public static function maybe_apply() {
		if ( 'kingdom' !== get_template() ) {
			return;
		}
		$done = (array) get_option( 'ks_seeds_done', array() );
		$name = strtolower( (string) get_option( 'blogname' ) );
		foreach ( glob( KS_PATH . 'includes/seeds/*.php' ) as $file ) {
			$slug = basename( $file, '.php' );
			if ( in_array( $slug, $done, true ) ) {
				continue;
			}
			$seed = include $file;
			if ( ! is_array( $seed ) || empty( $seed['match'] ) || false === strpos( $name, (string) $seed['match'] ) ) {
				continue;
			}
			// Marked done first, so two requests at the same moment can't both apply it.
			$done[] = $slug;
			update_option( 'ks_seeds_done', $done, false );
			self::apply( $seed );
		}
	}

	/**
	 * Apply one seed.
	 *
	 * @param array $seed Seed.
	 */
	private static function apply( $seed ) {
		foreach ( (array) ( $seed['mods'] ?? array() ) as $key => $value ) {
			if ( null === get_theme_mod( $key, null ) ) {
				set_theme_mod( $key, $value );
			}
		}
		if ( ! empty( $seed['charity_page'] ) ) {
			self::charity_page( (string) $seed['charity_page'] );
		}
	}

	/**
	 * Create the Charity page (unless one exists) and add it under About in the main menu.
	 *
	 * @param string $title Page title.
	 */
	private static function charity_page( $title ) {
		$template = 'page-templates/template-charity.php';
		$existing = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_wp_page_template', 'meta_value' => $template ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( $existing ) {
			return;
		}
		$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => sanitize_title( $title ), 'page_template' => $template ) );
		if ( ! $page || is_wp_error( $page ) ) {
			return;
		}
		$locations = (array) get_theme_mod( 'nav_menu_locations', array() );
		if ( empty( $locations['primary'] ) ) {
			return;
		}
		$about  = get_posts( array( 'post_type' => 'page', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_wp_page_template', 'meta_value' => 'page-templates/template-about.php' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$parent = 0;
		foreach ( (array) wp_get_nav_menu_items( $locations['primary'] ) as $item ) {
			if ( $about && 'page' === $item->object && (int) $item->object_id === (int) $about[0] && ! $item->menu_item_parent ) {
				$parent = (int) $item->ID;
				break;
			}
		}
		wp_update_nav_menu_item(
			$locations['primary'],
			0,
			array(
				'menu-item-object-id' => $page,
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-parent-id' => $parent,
				'menu-item-status'    => 'publish',
			)
		);
	}
}
