<?php
/**
 * One-time content for particular churches (wording they sent us), applied on their own site.
 *
 * Each file in includes/seeds/ returns:
 *   match        Text the church's site name or address must contain (letters only are compared).
 *   mods         Theme settings to fill in. Ones the church has already set are left alone.
 *   charity_page Optional: title of a Charity page to create (under About in the main menu).
 *   replace      Optional: setting => array( old, new ), changed only while it still has the old value.
 *   dedupe_charity Optional: remove extra empty Charity pages (keeps the oldest).
 *   regex        Optional: setting => array( pattern, replacement ), corrections inside a text.
 *   beliefs_page Optional: array( title, slug, html ): the Statement of Faith page. An existing
 *                page that still has the theme's sample text (or none) is renamed and filled in;
 *                otherwise a new page is added under About.
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
		$done  = (array) get_option( 'ks_seeds_done', array() );
		// Letters only, so "Road Map to Jesus", "Roadmap to Jesus" and roadmaptojesus.com all match.
		$name  = self::letters( get_option( 'blogname' ) ) . '|' . self::letters( home_url() );
		$files = (array) glob( KS_PATH . 'includes/seeds/*.php' );
		// Nothing new since the last check (same site name, same seeds): skip reading the files.
		$stamp = md5( $name . '|' . implode( ',', array_map( 'basename', $files ) ) . '|' . implode( ',', $done ) );
		if ( get_option( 'ks_seeds_checked' ) === $stamp ) {
			return;
		}
		foreach ( $files as $file ) {
			$slug = basename( $file, '.php' );
			if ( in_array( $slug, $done, true ) ) {
				continue;
			}
			$seed = include $file;
			if ( ! is_array( $seed ) || empty( $seed['match'] ) || false === strpos( $name, self::letters( $seed['match'] ) ) ) {
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
		update_option( 'ks_seeds_checked', md5( $name . '|' . implode( ',', array_map( 'basename', $files ) ) . '|' . implode( ',', $done ) ), false );
	}

	/**
	 * Lower-case letters only.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function letters( $text ) {
		return (string) preg_replace( '/[^a-z]/', '', strtolower( (string) $text ) );
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
		foreach ( (array) ( $seed['regex'] ?? array() ) as $key => $pair ) {
			$value = get_theme_mod( $key, null );
			if ( is_string( $value ) ) {
				$fixed = preg_replace( $pair[0], $pair[1], $value );
				if ( is_string( $fixed ) && $fixed !== $value ) {
					set_theme_mod( $key, $fixed );
				}
			}
		}
		if ( ! empty( $seed['beliefs_page'] ) ) {
			self::beliefs_page( (array) $seed['beliefs_page'] );
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
		if ( $page && ! is_wp_error( $page ) ) {
			self::add_under_about( $page );
		}
	}

	/**
	 * The Statement of Faith page.
	 *
	 * @param array $spec array( title, slug, html ).
	 */
	private static function beliefs_page( $spec ) {
		list( $title, $slug, $html ) = $spec;
		$template = 'page-templates/template-beliefs.php';
		$pages    = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'meta_key' => '_wp_page_template', 'meta_value' => $template ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$sample   = function_exists( 'kingdom_starter_pages' ) ? (string) ( kingdom_starter_pages()['beliefs'][2] ?? '' ) : '';
		if ( $pages && ( '' === trim( $pages[0]->post_content ) || trim( $pages[0]->post_content ) === trim( $sample ) ) ) {
			if ( class_exists( 'KCS_Pages' ) ) {
				KCS_Pages::rename( $pages[0]->ID, $title, $slug );
			} else {
				wp_update_post( array( 'ID' => $pages[0]->ID, 'post_title' => $title, 'post_name' => $slug ) );
			}
			wp_update_post( array( 'ID' => $pages[0]->ID, 'post_content' => $html ) );
			return;
		}
		$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $html, 'page_template' => $template ) );
		if ( $page && ! is_wp_error( $page ) ) {
			self::add_under_about( $page );
		}
	}

	/**
	 * Add a page under About in the main menu (or at the top level when there is no About item).
	 *
	 * @param int $page Page ID.
	 */
	private static function add_under_about( $page ) {
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
