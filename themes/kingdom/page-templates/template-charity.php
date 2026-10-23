<?php
/**
 * Template Name: Charity
 * Template Post Type: page
 *
 * The church's humanitarian outreach, the page's own content and online giving.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header( '', '', has_post_thumbnail() ? '' : kingdom_image( 'kingdom_gallery_3' ) );
kingdom_section( 'charity' );
kingdom_section( 'page-content' );
kingdom_section( 'give' );
get_footer();
