<?php
/**
 * Template Name: Plan Your Visit
 * Template Post Type: page
 *
 * Visitor information and questions, the visitor questionnaire, what to expect, service times
 * and map.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header( '', __( 'We cannot wait to meet you!', 'kingdom' ) );
kingdom_section( 'visit-info' );
kingdom_section( 'visit' );
kingdom_section( 'service-times' );
kingdom_section( 'page-content' );
kingdom_section( 'contact' );
get_footer();
