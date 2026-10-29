<?php
// Bachs gateway tests (wp eval-file on a church site). Bachs's replies are simulated in the shapes
// its docs show; the keys below are fake and only decide sandbox vs live.
$ok = function ( $c, $m ) { echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; };
$GLOBALS['bx_reply'] = null;
$GLOBALS['bx_sent']  = array();
// Start clean: earlier runs reuse the same fake checkout IDs.
$GLOBALS['wpdb']->query( "DELETE FROM " . kcs_table( 'payments' ) . " WHERE reference LIKE 'BXTEST-%'" );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false === strpos( $url, 'bachs.io' ) || null === $GLOBALS['bx_reply'] ) { return $pre; }
	$GLOBALS['bx_sent'][] = array( 'url' => $url, 'args' => $args );
	return array( 'headers' => array(), 'body' => wp_json_encode( $GLOBALS['bx_reply'][1] ), 'response' => array( 'code' => $GLOBALS['bx_reply'][0], 'message' => 'x' ), 'cookies' => array() );
}, 10, 3 );
$secret = 'whsec_unit_test_only';
$o = get_option( 'kcs_settings' ); $o['bachs_enabled'] = '1'; $o['bachs_secret_key'] = 'sk_sandbox_unit_test_only'; $o['bachs_webhook_secret'] = $secret; $o['bachs_methods'] = 'ngn_card, NGN_BANK_TRANSFER, bad-one'; update_option( 'kcs_settings', $o );
$g = KCS_Payments::gateway( 'bachs' );
$mk = function ( $amount, $cur = 'NGN', $txn = '' ) {
	global $wpdb;
	$wpdb->insert( kcs_table( 'payments' ), array( 'reference' => 'BXTEST-' . wp_generate_password( 10, false, false ), 'gateway' => 'bachs', 'gateway_txn_id' => $txn, 'amount' => $amount, 'currency' => $cur, 'email' => 'ada@example.com', 'name' => 'Ada Giver', 'phone' => '', 'purpose' => 'tithe', 'status' => 'pending', 'created_at' => current_time( 'mysql', true ) ) );
	return KCS_Payments::get( $wpdb->insert_id );
};
$session = function ( $status, $pay, $amount, $cur = 'NGN', $charge = null ) {
	return array( 200, array( 'checkout_id' => 'chk_T1', 'status' => $status, 'payment_status' => $pay, 'amount' => $amount, 'currency' => $cur, 'charge' => $charge ) );
};

$ok( 'test' === $g->mode(), 'sk_sandbox_ key → test mode' );

// Start: sandbox URL, decimal-string money, reference, idempotency key, methods filtered.
$p = $mk( 5000 ); $GLOBALS['bx_reply'] = array( 201, array( 'checkout_id' => 'chk_NEW1', 'checkout_url' => 'https://checkout.bachs.io/c/abc', 'status' => 'open' ) );
$r = $g->initialize( $p ); $sent = end( $GLOBALS['bx_sent'] ); $body = json_decode( $sent['args']['body'], true );
$ok( ! is_wp_error( $r ) && 'https://checkout.bachs.io/c/abc' === $r['redirect_url'] && 'chk_NEW1' === $r['txn_id'], 'start → checkout_url + checkout_id' );
$ok( 'https://sandbox-api.bachs.io/v1/checkout-sessions' === $sent['url'], 'sandbox key → sandbox API' );
$ok( '5000.00' === $body['pricing']['amount'] && 'NGN' === $body['pricing']['currency'], 'amount sent as "5000.00" NGN' );
$ok( $p->reference === $body['reference'] && 'kcs-' . $p->reference === $sent['args']['headers']['Idempotency-Key'], 'reference + idempotency key' );
$ok( array( 'NGN_CARD', 'NGN_BANK_TRANSFER' ) === $body['payment_method_types'], 'payment methods cleaned' );
$ok( ! isset( $body['customer']['phone_number'] ) && 'ada@example.com' === $body['customer']['email'], 'empty phone left out' );
$ok( false !== strpos( $body['cancel_url'], 'status=cancelled' ), 'cancel_url marks cancelled' );
$p = $mk( 1000, 'XOF' ); $g->initialize( $p ); $sent = end( $GLOBALS['bx_sent'] ); $body = json_decode( $sent['args']['body'], true );
$ok( '1000' === $body['pricing']['amount'], 'zero-decimal currency → "1000"' );
$GLOBALS['bx_reply'] = array( 422, array( 'error' => array( 'message' => 'reference already used' ) ) );
$ok( is_wp_error( $g->initialize( $mk( 10 ) ) ), 'API error → checkout does not start' );

// Verify.
$p = $mk( 5000, 'NGN', 'chk_T1' ); $GLOBALS['bx_reply'] = $session( 'completed', 'paid', '5000.00' ); KCS_Payments::verify_and_update( $p ); $q = KCS_Payments::get( $p->id );
$ok( 'completed' === $q->status && 'chk_T1' === $q->gateway_txn_id, 'completed + paid → completed (' . $q->status . ')' );
$p = $mk( 5000, 'NGN', 'chk_T1' ); $GLOBALS['bx_reply'] = $session( 'completed', 'paid', '50.00' ); KCS_Payments::verify_and_update( $p ); $q = KCS_Payments::get( $p->id );
$ok( 'review' === $q->status, 'paid less → needs review' );
$p = $mk( 20, 'USD', 'chk_T1' ); $GLOBALS['bx_reply'] = array( 200, array( 'status' => 'completed', 'charge' => array( 'status' => 'succeeded', 'amount' => '20.00', 'currency' => 'USD' ) ) ); KCS_Payments::verify_and_update( $p ); $q = KCS_Payments::get( $p->id );
$ok( 'completed' === $q->status, 'charge succeeded (amount on charge) → completed' );
$p = $mk( 5000, 'NGN', 'chk_T1' ); $GLOBALS['bx_reply'] = $session( 'open', 'unpaid', '5000.00' ); KCS_Payments::verify_and_update( $p ); $q = KCS_Payments::get( $p->id );
$ok( 'pending' === $q->status && false !== strpos( $q->note, 'TEST' ), 'open → pending, note says TEST: ' . $q->note );
$p = $mk( 5000, 'NGN', 'chk_T1' ); $GLOBALS['bx_reply'] = $session( 'expired', 'unpaid', '5000.00' ); KCS_Payments::verify_and_update( $p ); $q = KCS_Payments::get( $p->id );
$ok( 'cancelled' === $q->status, 'expired → cancelled' );
$p = $mk( 5000, 'NGN', 'chk_T1' ); $GLOBALS['bx_reply'] = $session( 'open', 'unpaid', '5000.00' ); KCS_Payments::verify_and_update( $p, array( 'status' => 'cancelled' ) ); $q = KCS_Payments::get( $p->id );
$ok( 'cancelled' === $q->status, 'giver pressed cancel → cancelled' );
$p = $mk( 5000, 'NGN', 'chk_T1' ); $GLOBALS['bx_reply'] = array( 401, array( 'message' => 'Unauthorized' ) ); KCS_Payments::verify_and_update( $p ); $q = KCS_Payments::get( $p->id );
$ok( 'pending' === $q->status && false !== strpos( $q->note, 'refused' ), 'bad key → pending, note: ' . $q->note );
$p = $mk( 5000 ); $GLOBALS['bx_reply'] = $session( 'completed', 'paid', '5000.00' ); KCS_Payments::verify_and_update( $p, array( 'checkout_id' => 'chk_T1' ) ); $q = KCS_Payments::get( $p->id );
$ok( 'completed' === $q->status, 'no saved checkout id → uses ?checkout_id= from the return' );

// Webhooks.
$hook = function ( $event, $headers ) {
	$req = new WP_REST_Request( 'POST', '/kcs/v1/webhooks/bachs' );
	$req->set_body( $event );
	foreach ( $headers as $k => $v ) { $req->set_header( $k, $v ); }
	$req->set_url_params( array( 'gateway' => 'bachs' ) );
	return KCS_REST_API::webhook( $req );
};
$p = $mk( 5000, 'NGN', 'chk_T1' );
$event = wp_json_encode( array( 'id' => 'evt_1', 'type' => 'collection.succeeded', 'data' => array( 'checkout_id' => 'chk_T1', 'reference' => $p->reference, 'status' => 'SUCCEEDED', 'amount' => '5000.00', 'currency' => 'NGN' ) ) );
$t = (string) time();
$res = $hook( $event, array( 'X-Bachs-Signature-V2' => 't=' . $t . ',v1=' . str_repeat( '0', 64 ) ) );
$ok( is_wp_error( $res ) && 400 === $res->get_error_data()['status'], 'wrong signature → 400' );
$res = $hook( $event, array( 'X-Bachs-Signature-V2' => 't=' . ( time() - 900 ) . ',v1=' . hash_hmac( 'sha256', ( time() - 900 ) . '.' . $event, $secret ) ) );
$ok( is_wp_error( $res ), 'old timestamp (replay) → refused' );
$GLOBALS['bx_reply'] = $session( 'completed', 'paid', '5000.00' );
$res = $hook( $event, array( 'X-Bachs-Signature-V2' => 't=' . $t . ',v1=' . str_repeat( 'a', 64 ) . ',v1=' . hash_hmac( 'sha256', $t . '.' . $event, $secret ) ) );
$ok( ! is_wp_error( $res ) && 'completed' === KCS_Payments::get( $p->id )->status, 'signed collection.succeeded (2nd v1 during rotation) → completed' );
$p = $mk( 5000, 'NGN', 'chk_T2' );
$event = wp_json_encode( array( 'id' => 'evt_2', 'type' => 'collection.failed', 'data' => array( 'checkout_id' => 'chk_T2', 'reference' => null, 'status' => 'FAILED', 'reason' => 'Insufficient funds' ) ) );
$GLOBALS['bx_reply'] = $session( 'open', 'unpaid', '5000.00' );
$res = $hook( $event, array( 'X-Bachs-Timestamp' => $t, 'X-Bachs-Signature' => hash_hmac( 'sha256', $t . '.' . $event, $secret ) ) );
$q = KCS_Payments::get( $p->id );
$ok( 'failed' === $q->status && false !== strpos( $q->note, 'Insufficient funds' ), 'collection.failed (found by checkout id, v1 headers) → failed: ' . $q->note );
KCS_Payments::verify_and_update( $q ); $q = KCS_Payments::get( $p->id );
$ok( false !== strpos( $q->note, 'Insufficient funds' ), 'later check keeps the decline reason' );
$GLOBALS['bx_reply'] = $session( 'completed', 'paid', '5000.00' ); KCS_Payments::verify_and_update( $q ); $q = KCS_Payments::get( $p->id );
$ok( 'completed' === $q->status, 'failed try, then paid on the same checkout → completed' );
// Spoofed failure through the public return URL does nothing.
$p = $mk( 5000, 'NGN', 'chk_T3' ); $GLOBALS['bx_reply'] = $session( 'open', 'unpaid', '5000.00' ); KCS_Payments::verify_and_update( $p, array( 'type' => 'collection.failed' ) );
$ok( 'pending' === KCS_Payments::get( $p->id )->status, 'return-URL params cannot fake a failure' );

$o['bachs_secret_key'] = 'sk_live_unit_test_only'; update_option( 'kcs_settings', $o );
$ok( 'live' === $g->mode(), 'sk_live_ key → live mode' );
$GLOBALS['bx_reply'] = array( 201, array( 'checkout_id' => 'chk_L', 'checkout_url' => 'https://checkout.bachs.io/c/l' ) ); $g->initialize( $mk( 10 ) ); $sent = end( $GLOBALS['bx_sent'] );
$ok( 0 === strpos( $sent['url'], 'https://api.bachs.io/' ), 'live key → live API' );
$GLOBALS['bx_reply'] = null;
