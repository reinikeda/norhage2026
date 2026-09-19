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

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
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
nh_iframe_first_assert( 'terms helper is registered', function_exists( 'nh_checkout_render_terms' ) );

$ship_html = nh_checkout_format_shipping_summary_html( 'Flat sats (Medium):', '<span class="woocommerce-Price-amount">99&nbsp;kr</span>' );
nh_iframe_first_assert( 'shipping summary keeps the method name', strpos( $ship_html, 'Flat sats (Medium)' ) !== false && strpos( $ship_html, 'Flat sats (Medium):' ) === false );
nh_iframe_first_assert( 'shipping summary keeps the price html', strpos( $ship_html, 'woocommerce-Price-amount' ) !== false && strpos( $ship_html, '99' ) !== false );

if ( $failures > 0 ) {
	exit( 1 );
}

echo "All tests passed.\n";
