<?php
/**
 * One-time content for particular churches (wording they sent us), applied on their own site.
 *
 * Each file in includes/seeds/ returns:
 *   match        Lower-case text the church's site name must contain.
 *   mods         Theme settings to fill in. Ones the church has already set are left alone.
 *   charity_page Optional: title of a Charity page to create (under About in the main menu).
 *   replace      Optional: setting => array( old, new ), changed only while it still has the old value.
 *   dedupe_charity Optional: remove extra empty Charity pages (keeps the oldest).
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
			// add_option() is a single INSERT, so only one of two requests arriving at the same moment
			// gets the lock and applies the seed.
			if ( ! add_option( 'ks_seed_lock_' . $slug, time(), '', false ) ) {
				continue;
			}
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
		foreach ( (array) ( $seed['replace'] ?? array() ) as $key => $pair ) {
			if ( get_theme_mod( $key, null ) === $pair[0] ) {
				set_theme_mod( $key, $pair[1] );
			}
		}
		if ( ! empty( $seed['dedupe_charity'] ) ) {
			self::dedupe_charity();
		}
		if ( ! empty( $seed['charity_page'] ) ) {
			self::charity_page( (string) $seed['charity_page'] );
		}
	}

	/**
	 * Charity pages (template or slug), oldest first.
	 *
	 * @return WP_Post[]
	 */
	private static function charity_pages() {
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 20, 'orderby' => 'ID', 'order' => 'ASC', 'meta_key' => '_wp_page_template', 'meta_value' => 'page-templates/template-charity.php' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$slug  = get_page_by_path( 'charity' );
		if ( $slug && ! in_array( $slug->ID, wp_list_pluck( $pages, 'ID' ), true ) ) {
			$pages[] = $slug;
		}
		return $pages;
	}

	/**
	 * Keep one Charity page: extra ones that are still empty go to the bin with their menu items.
	 */
	private static function dedupe_charity() {
		$pages = array_values(
			array_filter(
				self::charity_pages(),
				function ( $p ) {
					return 'page-templates/template-charity.php' === get_page_template_slug( $p ) && '' === trim( $p->post_content );
				}
			)
		);
		foreach ( array_slice( $pages, 1 ) as $page ) {
			foreach ( wp_get_associated_nav_menu_items( $page->ID, 'post_type', 'page' ) as $item ) {
				wp_delete_post( $item, true );
			}
			wp_trash_post( $page->ID );
		}
	}

	/**
	 * Create the Charity page (unless one exists) and add it under About in the main menu.
	 *
	 * @param string $title Page title.
	 */
	private static function charity_page( $title ) {
		$template = 'page-templates/template-charity.php';
		if ( self::charity_pages() ) {
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
