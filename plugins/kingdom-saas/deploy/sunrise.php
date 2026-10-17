<?php
/**
 * Kingdom Cloud — custom domain helper.
 *
 * Copy this file to wp-content/sunrise.php and add this line to wp-config.php
 * (above "That's all, stop editing!"):
 *
 *     define( 'SUNRISE', true );
 *
 * It runs before WordPress picks a site and:
 * 1. answers /.well-known/ks-domain-check on any host with that domain's token (used to verify DNS),
 *    and /.well-known/ks-probe (used by the setup check to test church subdomains);
 * 2. redirects www.<domain> ↔ <domain> to the address the church connected;
 * 3. redirects a church's original platform address (church.platform.com, or platform.com/church on
 *    subfolder networks) to its custom domain, and a disconnected custom domain back to the church's
 *    platform address;
 * 4. keeps log-in cookies on the church's own domain.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

( static function () {
	global $wpdb;
	if ( empty( $_SERVER['HTTP_HOST'] ) ) {
		return;
	}
	$host    = strtolower( preg_replace( '/:\d+$/', '', $_SERVER['HTTP_HOST'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$uri     = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$path    = (string) parse_url( $uri, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	$domains = $wpdb->base_prefix . 'ks_domains';
	$subs    = $wpdb->base_prefix . 'ks_subscriptions';
	$bare    = preg_replace( '/^www\./', '', $host );
	$quiet   = $wpdb->suppress_errors( true );

	// On a church's own domain, log-in cookies must belong to that domain, not the platform's.
	$network = defined( 'DOMAIN_CURRENT_SITE' ) ? strtolower( DOMAIN_CURRENT_SITE ) : '';
	if ( $network && ! defined( 'COOKIE_DOMAIN' ) && $host !== $network && substr( $host, -strlen( '.' . $network ) ) !== '.' . $network ) {
		define( 'COOKIE_DOMAIN', '' );
	}

	// Setup check (Network Admin → Kingdom Cloud): proves a random subdomain reaches this WordPress.
	if ( '/.well-known/ks-probe' === $path ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		echo 'ks-probe-ok';
		exit;
	}

	if ( '/.well-known/ks-domain-check' === $path ) {
		$token = $wpdb->get_var( $wpdb->prepare( "SELECT token FROM $domains WHERE domain IN (%s, %s, %s) LIMIT 1", $host, $bare, 'www.' . $bare ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		echo $token ? $token : 'unknown'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	$https  = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] );
	$scheme = $https ? 'https' : 'http';

	// Subfolder networks (platform.com/church): a church that moved to its own domain is
	// redirected there from its old platform.com/church/... address.
	if ( defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL && $host === $network && preg_match( '#^/([a-z0-9-]+)(/.*)?$#', $path, $seg ) ) {
		$moved = $wpdb->get_row( $wpdb->prepare( "SELECT b.domain, b.path FROM $subs s JOIN {$wpdb->blogs} b ON b.blog_id = s.blog_id WHERE s.subdomain = %s LIMIT 1", $network . '/' . $seg[1] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $moved && $moved->domain !== $network ) {
			$rest = substr( $uri, strlen( '/' . $seg[1] ) );
			$wpdb->suppress_errors( $quiet );
			header( 'Location: ' . $scheme . '://' . $moved->domain . ( '' === $rest ? '/' : $rest ), true, 301 );
			exit;
		}
	}

	if ( $wpdb->get_var( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = %s LIMIT 1", $host ) ) ) {
		$wpdb->suppress_errors( $quiet );
		return;
	}
	$target = $wpdb->get_row( $wpdb->prepare( "SELECT domain, path FROM {$wpdb->blogs} WHERE domain IN (%s, %s) LIMIT 1", $bare, 'www.' . $bare ) );
	if ( ! $target ) {
		$target = $wpdb->get_row( $wpdb->prepare( "SELECT b.domain, b.path FROM $subs s JOIN {$wpdb->blogs} b ON b.blog_id = s.blog_id WHERE s.subdomain = %s LIMIT 1", $host ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	if ( ! $target ) {
		// A custom domain that is not (or no longer) connected: send visitors to the church's current address.
		$target = $wpdb->get_row( $wpdb->prepare( "SELECT b.domain, b.path FROM $domains d JOIN {$wpdb->blogs} b ON b.blog_id = d.blog_id WHERE d.domain IN (%s, %s, %s) LIMIT 1", $host, $bare, 'www.' . $bare ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	$wpdb->suppress_errors( $quiet );
	if ( $target && ( $target->domain !== $host || '/' !== $target->path ) ) {
		// Keep the visitor's page: /about/ on the old address → /church/about/ (or /about/) on the new one.
		header( 'Location: ' . $scheme . '://' . $target->domain . rtrim( $target->path, '/' ) . $uri, true, 301 );
		exit;
	}
} )();
