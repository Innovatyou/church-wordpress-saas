<?php
// Test only: fake Paystack, Flutterwave and Stripe API responses (the sandbox has no outbound access).
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$m = get_site_option( 'ks_test_mock', array() );
	if ( false !== strpos( $url, 'api.paystack.co/transaction/initialize' ) ) {
		$b = json_decode( $args['body'], true );
		update_site_option( 'ks_test_last_paystack', $b );
		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => true, 'data' => array( 'authorization_url' => 'https://checkout.paystack.com/fake' . $b['reference'] ) ) ) );
	}
	if ( false !== strpos( $url, 'api.paystack.co/transaction/verify/' ) ) {
		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => true, 'data' => array( 'status' => 'success', 'amount' => $m['amount'], 'currency' => $m['currency'], 'id' => 991 ) ) ) );
	}
	if ( false !== strpos( $url, 'api.flutterwave.com/v3/payments' ) ) {
		$b = json_decode( $args['body'], true );
		update_site_option( 'ks_test_last_flutterwave', $b + array( '_auth' => $args['headers']['Authorization'] ?? '' ) );
		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => 'success', 'data' => array( 'link' => 'https://checkout.flutterwave.com/v3/hosted/pay/fake' . $b['tx_ref'] ) ) ) );
	}
	if ( false !== strpos( $url, 'api.flutterwave.com/v3/transactions/verify_by_reference' ) ) {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => 'success', 'data' => array( 'status' => $m['status'] ?? 'successful', 'tx_ref' => $m['ref'] ?? $q['tx_ref'], 'amount' => $m['amount'], 'currency' => $m['currency'], 'id' => 7788 ) ) ) );
	}
	if ( false !== strpos( $url, 'api.stripe.com/v1/checkout/sessions' ) && 'POST' === $args['method'] ) {
		update_site_option( 'ks_test_last_stripe', $args['body'] );
		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123' ) ) );
	}
	if ( false !== strpos( $url, 'api.stripe.com/v1/checkout/sessions/' ) ) {
		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'id' => 'cs_test_123', 'payment_status' => 'paid', 'client_reference_id' => $m['ref'], 'amount_total' => $m['amount'], 'currency' => strtolower( $m['currency'] ), 'payment_intent' => 'pi_123' ) ) );
	}
	return $pre;
}, 10, 3 );

// Test only: fake DNS for *.test domains (TXT token + CNAME to the platform), and let the
// HTTP reachability check call them although they resolve to 127.0.0.1.
add_filter( 'ks_dns_records', function ( $records, $host, $type ) {
	if ( ! preg_match( '/\.test$/', $host ) ) {
		return $records;
	}
	global $wpdb;
	if ( DNS_TXT === $type ) {
		$apex  = preg_replace( '/^_ks-verify\./', '', $host );
		$token = $wpdb->get_var( $wpdb->prepare( 'SELECT token FROM ' . ks_table( 'domains' ) . ' WHERE domain IN (%s, %s) ORDER BY id DESC LIMIT 1', $apex, 'www.' . $apex ) );
		return $token ? array( array( 'host' => $host, 'type' => 'TXT', 'txt' => $token ) ) : array();
	}
	return array( array( 'host' => $host, 'type' => 'CNAME', 'target' => ks_get( 'cname_target' ) ? ks_get( 'cname_target' ) : ks_get( 'base_domain' ) ) );
}, 10, 3 );
add_filter( 'http_request_host_is_external', function ( $external, $host ) {
	return $external || (bool) preg_match( '/\.test$/', $host );
}, 10, 2 );
