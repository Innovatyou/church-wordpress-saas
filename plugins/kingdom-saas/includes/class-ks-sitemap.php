<?php
/**
 * Sitemap provider for the platform pages (landing + solution pages).
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Platform sitemap: /wp-sitemap-platform-1.xml.
 */
class KS_Sitemap extends WP_Sitemaps_Provider {

	/**
	 * Set up.
	 */
	public function __construct() {
		$this->name        = 'platform';
		$this->object_type = 'platform';
	}

	/**
	 * URLs.
	 *
	 * @param int    $page_num       Page.
	 * @param string $object_subtype Unused.
	 * @return array
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		if ( 1 !== (int) $page_num ) {
			return array();
		}
		$urls = array( array( 'loc' => ks_platform_url() ) );
		foreach ( KS_SEO::slugs() as $slug ) {
			$urls[] = array( 'loc' => ks_platform_url( $slug . '/' ) );
		}
		return $urls;
	}

	/**
	 * Pages.
	 *
	 * @param string $object_subtype Unused.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		return 1;
	}
}
