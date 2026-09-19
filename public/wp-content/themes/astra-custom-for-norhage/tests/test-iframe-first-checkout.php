<?php
/**
 * CLI tests: iframe-first default gateway helpers.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-iframe-first-checkout.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return (string) $text;
	}
}

if ( ! function_exists( 'translate' ) ) {
	function translate( $text, $domain = 'default' ) {
		unset( $domain );
		return (string) $text;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return trim( strip_tags( (string) $string ) );
	}
}

require_once dirname( __DIR__ ) . '/inc/checkout-ux.php';

$failures = 0;

function nh_iframe_first_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "OK $label\n";
		return;
	}
	$failures++;
	fwrite( STDERR, "FAIL $label\n" );
}

$kco              = new stdClass();
$bacs             = new stdClass();
$svea             = new stdClass();
$gateways         = array(
	'kco'  => $kco,
	'bacs' => $bacs,
);

nh_iframe_first_assert( 'first gateway is Woo settings order', nh_checkout_first_gateway_id( $gateways ) === 'kco' );
nh_iframe_first_assert( 'empty list has no first gateway', nh_checkout_first_gateway_id( array() ) === '' );
nh_iframe_first_assert( 'kco is a snippet gateway', nh_checkout_is_snippet_gateway( 'kco' ) );
nh_iframe_first_assert( 'svea_checkout is a snippet gateway', nh_checkout_is_snippet_gateway( 'svea_checkout' ) );
nh_iframe_first_assert( 'bacs is not a snippet gateway', ! nh_checkout_is_snippet_gateway( 'bacs' ) );

$svea_first = array(
	'svea_checkout' => $svea,
	'bacs'          => $bacs,
);
nh_iframe_first_assert( 'svea-first shop defaults to svea', nh_checkout_first_gateway_id( $svea_first ) === 'svea_checkout' );

$bacs_only = array( 'bacs' => $bacs );
nh_iframe_first_assert( 'bacs-only shop defaults to bacs', nh_checkout_first_gateway_id( $bacs_only ) === 'bacs' );

if ( $failures > 0 ) {
	exit( 1 );
}

echo "All tests passed.\n";
