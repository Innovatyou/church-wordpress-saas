<?php
// Custom domain tests (run with wp eval-file on the subdomain test network; needs the
// gateway-mock mu-plugin for fake DNS). The church must only move to its domain once the
// domain really opens the site — DNS alone is not enough on shared hosting.
global $wpdb;
$ok   = function ( $c, $m ) { echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; };
$hosts = function ( $on ) {
	$h = preg_replace( '/^127\.0\.0\.1 gracechapel\.test www\.gracechapel\.test\n/m', '', file_get_contents( '/etc/hosts' ) );
	file_put_contents( '/etc/hosts', $h . ( $on ? "127.0.0.1 gracechapel.test www.gracechapel.test\n" : '' ) );
};
$mails = function () { $f = WP_CONTENT_DIR . '/mail.log'; return file_exists( $f ) ? count( file( $f ) ) : 0; };
$last  = function () { $l = file( WP_CONTENT_DIR . '/mail.log' ); return json_decode( end( $l ), true ); };
$row   = $wpdb->get_row( 'SELECT * FROM ' . ks_table( 'domains' ) . " WHERE domain = 'gracechapel.test'" );
$blog  = (int) $row->blog_id;
$addr  = function () use ( $blog ) { clean_blog_cache( $blog ); return get_site( $blog )->domain; };
delete_site_option( 'ks_domain_notices' );
KS_Domains::make_primary( $row->id );

// 1. Domain is the main address but the hosting no longer serves it → back to the platform address.
$hosts( false );
$n = $mails();
$r = KS_Domains::verify( $row->id );
$d = KS_Domains::get( $row->id );
$ok( is_wp_error( $r ) && 'ks_hosting' === $r->get_error_code(), 'DNS right but domain not served → waiting for hosting' );
$ok( 'pending' === $d->status && ! (int) $d->is_primary, 'domain no longer the main address' );
$ok( 'gracechapel.test' !== $addr(), 'church back on its platform address (' . $addr() . ')' );
$ok( $mails() === $n + 1 && false !== strpos( $last()['subject'], 'add gracechapel.test to your hosting' ), 'platform owner emailed: add as parked domain' );
KS_Domains::verify( $row->id );
$ok( $mails() === $n + 1, 'email sent only once' );

// 2. Hosting now serves it → connects (http scheme on this network).
$hosts( true );
$r = KS_Domains::verify( $row->id );
$d = KS_Domains::get( $row->id );
$ok( true === $r && 'verified' === $d->status && (int) $d->is_primary, 'served by hosting → verified and main address' );
$ok( 'gracechapel.test' === $addr(), 'site address is the domain' );

// 3. https platform but no certificate → verified (so a TLS proxy may issue one) but not switched.
$https = ks_get( 'https' );
ks_set( array( 'https' => '1' ) );
$n = $mails();
$r = KS_Domains::verify( $row->id );
$d = KS_Domains::get( $row->id );
$ok( is_wp_error( $r ) && 'ks_ssl' === $r->get_error_code(), 'no SSL yet → waiting for certificate' );
$ok( 'verified' === $d->status && ! (int) $d->is_primary && 'gracechapel.test' !== $addr(), 'verified but church stays on platform address' );
$ok( $mails() === $n + 1 && false !== strpos( $last()['subject'], 'SSL certificate' ), 'platform owner emailed: install SSL' );
$ok( KS_Domains::tls_allowed( 'gracechapel.test' ), 'TLS proxy may issue the certificate' );
KS_Domains::recheck_pending();
$ok( KS_Domains::get( $row->id ) && ! (int) KS_Domains::get( $row->id )->is_primary, 'scheduled check keeps waiting, does not drop it' );

// 4. Back to http (certificate "installed") → the scheduled check finishes the switch.
ks_set( array( 'https' => $https ) );
KS_Domains::recheck_pending();
$ok( (int) KS_Domains::get( $row->id )->is_primary && 'gracechapel.test' === $addr(), 'scheduled check switches once it works' );
