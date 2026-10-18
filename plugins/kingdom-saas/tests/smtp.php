<?php
$ok = function ( $c, $m ) { echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; };
$S = ( getenv( 'SMTP_LOG_DIR' ) ? rtrim( getenv( 'SMTP_LOG_DIR' ), '/' ) . '/' : '/tmp/' );
$last = function ( $f ) use ( $S ) { clearstatcache(); if ( ! file_exists( $S . $f ) ) { return null; } $l = file( $S . $f ); return json_decode( end( $l ), true ); };
$hdr = function ( $rec, $h ) { return preg_match( '/^' . $h . ':\s*(.+)$/mi', $rec['data'], $m ) ? trim( $m[1] ) : ''; };
remove_all_filters( 'pre_wp_mail' );
// Platform mailbox.
ks_set( array( 'smtp_host' => '127.0.0.1', 'smtp_port' => '2525', 'smtp_secure' => 'none', 'smtp_user' => 'mailer@ministryos.test', 'smtp_pass' => 'p<w>d&1', 'smtp_from' => 'no-reply@ministryos.test' ) );
$o = get_option( 'kcs_settings' ); $o['smtp_enabled'] = '0'; $o['from_email'] = 'pastor@roadmaptojesus.com'; $o['from_name'] = 'Road Map to Jesus'; update_option( 'kcs_settings', $o );
$sent = KCS_Email::send( 'member@example.com', 'Receipt', '<p>Thanks</p>' );
$r = $last( 'platform.log' );
$ok( $sent && $r, 'church email sent through the platform mailbox' . ( $sent ? '' : ' (' . KCS_Mailer::$last_error . ')' ) );
$ok( $r && 'mailer@ministryos.test' === $r['auth'], 'logged in to the mailbox as mailer@ministryos.test' );
$ok( $r && false !== strpos( $r['mail_from'], 'no-reply@ministryos.test' ), 'envelope sender is the platform address' );
$ok( $r && false !== strpos( $hdr( $r, 'From' ), 'Road Map to Jesus' ) && false !== strpos( $hdr( $r, 'From' ), 'no-reply@ministryos.test' ), 'From: church name <platform address> (' . ( $r ? $hdr( $r, 'From' ) : '' ) . ')' );
$ok( $r && false !== strpos( $hdr( $r, 'Reply-To' ), 'pastor@roadmaptojesus.com' ), 'Reply-To: the church (' . ( $r ? $hdr( $r, 'Reply-To' ) : '' ) . ')' );
// Church's own mailbox wins.
$o['smtp_enabled'] = '1'; $o['smtp_host'] = '127.0.0.1'; $o['smtp_port'] = '2526'; $o['smtp_secure'] = 'none'; $o['smtp_user'] = 'pastor@roadmaptojesus.com'; $o['smtp_pass'] = 'secret'; update_option( 'kcs_settings', $o );
$n = $S . 'platform.log'; $before = file_exists( $n ) ? count( file( $n ) ) : 0;
$sent = KCS_Email::send( 'member@example.com', 'Receipt 2', '<p>Thanks</p>' );
$r = $last( 'church.log' );
$ok( $sent && $r && 'pastor@roadmaptojesus.com' === $r['auth'], 'church mailbox used when switched on' );
$ok( count( file( $n ) ) === $before, 'platform mailbox not used then' );
$ok( $r && false !== strpos( $hdr( $r, 'From' ), 'pastor@roadmaptojesus.com' ) && '' === $hdr( $r, 'Reply-To' ), 'From is the church address, no Reply-To needed' );
// Wrong server → clear error.
$o['smtp_port'] = '2599'; update_option( 'kcs_settings', $o );
$sent = KCS_Email::send( 'member@example.com', 'X', '<p>x</p>' );
$ok( ! $sent && '' !== KCS_Mailer::$last_error, 'wrong port → error message: ' . KCS_Mailer::$last_error );
// Password kept when the field is left empty; never printed back.
KCS_Settings::save_tab( 'email', array_merge( array_map( 'strval', array_intersect_key( $o, array_flip( array( 'from_name', 'from_email', 'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user' ) ) ) ), array( 'smtp_enabled' => '1', 'smtp_pass' => '' ) ) );
$ok( 'secret' === kcs_get_option( 'smtp_pass' ), 'empty password field keeps the saved password' );
wp_set_current_user( get_user_by( 'email', 'road@example.com' )->ID ); $_GET = array( 'view' => 'settings', 'tab' => 'email' );
$h = KCS_Church_Admin::render();
$ok( false === strpos( $h, 'value="secret"' ) && false !== strpos( $h, 'saved — leave empty to keep' ), 'saved password not shown in Church Admin' );
// Clean up.
$o = get_option( 'kcs_settings' ); $o['smtp_enabled'] = '0'; $o['smtp_pass'] = ''; update_option( 'kcs_settings', $o );
ks_set( array( 'smtp_host' => '', 'smtp_pass' => '' ) );
