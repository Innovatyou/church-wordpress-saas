<?php
// Zenith GlobalPay verification tests (wp eval-file on a church site). GlobalPay's replies are
// simulated in the exact shape its live API returns (data is a list for merchant-reference lookups).
$ok = function ( $c, $m ) { echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; };
$GLOBALS['gp_reply'] = null;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false === strpos( $url, 'paygw.globalpay.com.ng' ) || null === $GLOBALS['gp_reply'] ) { return $pre; }
	return array( 'headers' => array(), 'body' => wp_json_encode( $GLOBALS['gp_reply'][1] ), 'response' => array( 'code' => $GLOBALS['gp_reply'][0], 'message' => 'x' ), 'cookies' => array() );
}, 10, 3 );
$o = get_option( 'kcs_settings' ); $o['globalpay_enabled'] = '1'; $o['globalpay_api_key'] = $o['globalpay_api_key'] ?? 'TESTKEY'; update_option( 'kcs_settings', $o );
$mk = function ( $amount ) {
	global $wpdb;
	$wpdb->insert( kcs_table( 'payments' ), array( 'reference' => 'GPTEST-' . wp_generate_password( 10, false, false ), 'gateway' => 'globalpay', 'amount' => $amount, 'currency' => 'NGN', 'email' => 'ada@example.com', 'name' => 'Ada Giver', 'phone' => '08031234567', 'purpose' => 'tithe', 'status' => 'pending', 'created_at' => current_time( 'mysql', true ) ) );
	return KCS_Payments::get( $wpdb->insert_id );
};
$row = function ( $p, $status, $amount, $cur = 'NGN' ) {
	return array( 200, array( 'data' => array( array( 'amountFromMerchant' => $amount, 'amountPaid' => $amount + 1.5, 'merchantTxnref' => $p->reference, 'transactionStatus' => $status, 'retrievalReferenceNumber' => '5080788986779182', 'chargeBearer' => 'ChargeCustomer', 'totalCharge' => 1.5, 'currency' => $cur ) ), 'responseCode' => '0000', 'isSuccessful' => true, 'error' => null ) );
};
$p = $mk( 5000 ); $GLOBALS['gp_reply'] = $row( $p, 'Successful', 5000.00 ); KCS_Payments::verify_and_update( $p ); $q = KCS_Payments::get( $p->id );
$ok( 'completed' === $q->status, 'Successful + right amount → completed (' . $q->status . ', txn ' . $q->gateway_txn_id . ')' );
$p = $mk( 5000 ); $GLOBALS['gp_reply'] = $row( $p, 'Successful', 50.00 ); KCS_Payments::verify_and_update( $p ); $q = KCS_Payments::get( $p->id );
$ok( 'review' === $q->status, 'Successful but less money → needs review (' . $q->status . ')' );
$p = $mk( 5000 ); $GLOBALS['gp_reply'] = $row( $p, 'Successful', 5000.00, 'USD' ); KCS_Payments::verify_and_update( $p ); $q = KCS_Payments::get( $p->id );
$ok( 'review' === $q->status, 'Successful in another currency → needs review (' . $q->status . ')' );
$p = $mk( 5000 ); $GLOBALS['gp_reply'] = $row( $p, 'pending', 5000.00 ); KCS_Payments::verify_and_update( $p ); $q = KCS_Payments::get( $p->id );
$ok( 'pending' === $q->status, 'pending → stays pending' );
$p = $mk( 5000 ); $GLOBALS['gp_reply'] = $row( $p, 'Failed', 5000.00 ); KCS_Payments::verify_and_update( $p ); $q = KCS_Payments::get( $p->id );
$ok( 'failed' === $q->status, 'Failed → failed' );
$p = $mk( 5000 ); $other = $row( $p, 'Successful', 5000.00 ); $other[1]['data'][0]['merchantTxnref'] = 'SOMEONE-ELSE'; $GLOBALS['gp_reply'] = $other; KCS_Payments::verify_and_update( $p ); $q = KCS_Payments::get( $p->id );
$ok( 'pending' === $q->status, 'a successful row for another reference is ignored' );
$p = $mk( 5000 ); $GLOBALS['gp_reply'] = array( 400, array( 'data' => null, 'isSuccessful' => false, 'error' => array( 'responseCode' => 'ZEN0049', 'responseDescription' => 'Invalid merchant' ) ) ); KCS_Payments::verify_and_update( $p ); $q = KCS_Payments::get( $p->id );
$ok( 'pending' === $q->status, 'not found at GlobalPay → stays pending' );
$GLOBALS['gp_reply'] = null;
$p = $mk( 100 ); $p->phone = ''; $r = KCS_Payments::gateway( 'globalpay' )->initialize( $p );
$ok( is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'phone' ), 'no phone → clear message: ' . ( is_wp_error( $r ) ? $r->get_error_message() : '' ) );
$ok( array( 'Seyi', 'Seyi' ) === KCS_Gateway_GlobalPay::names( 'Seyi' ), 'one name → used as first and last name' );
$ok( array( 'Seyi', 'Ade' ) === KCS_Gateway_GlobalPay::names( 'Pastor Seyi Ade' ), 'titles dropped' );
$ok( array( 'Chukwuemeka', 'ONeil Obi' ) === KCS_Gateway_GlobalPay::names( 'Dr. Chukwuemeka O’Neil-Obi' ), 'letters only' );
$ok( array( 'John', 'Doe' ) === KCS_Gateway_GlobalPay::names( '123', 'john.doe@example.com' ), 'no usable name → from email' );
