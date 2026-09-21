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

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $text ) {
		return trim( (string) $text );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
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
nh_iframe_first_assert( 'terms restore helper is registered', function_exists( 'nh_checkout_restore_woo_terms_hooks' ) );

$ship_html = nh_checkout_format_shipping_summary_html( 'Flat sats (Medium):', '<span class="woocommerce-Price-amount">99&nbsp;kr</span>' );
nh_iframe_first_assert( 'shipping summary keeps the method name', strpos( $ship_html, 'Flat sats (Medium)' ) !== false && strpos( $ship_html, 'Flat sats (Medium):' ) === false );
nh_iframe_first_assert( 'shipping summary keeps the price html', strpos( $ship_html, 'woocommerce-Price-amount' ) !== false && strpos( $ship_html, '99' ) !== false );

$copied = nh_checkout_copy_billing_to_shipping_if_empty(
	array(
		'billing_first_name' => 'Ola',
		'billing_last_name'  => 'Nordmann',
		'billing_postcode'   => '0150',
		'billing_city'       => 'Oslo',
		'shipping_city'      => '',
	)
);
nh_iframe_first_assert( 'empty shipping first name is copied from billing', $copied['shipping_first_name'] === 'Ola' );
nh_iframe_first_assert( 'empty shipping postcode is copied from billing', $copied['shipping_postcode'] === '0150' );

$posted = nh_checkout_posted_data_prefer_iframe(
	array(
		'payment_method'            => 'svea_checkout',
		'ship_to_different_address' => 1,
		'billing_first_name'        => 'Kari',
		'billing_address_1'         => 'Karl Johans gate 1',
	)
);
nh_iframe_first_assert( 'snippet checkout does not ship to a different address', empty( $posted['ship_to_different_address'] ) );
nh_iframe_first_assert( 'snippet checkout copies billing street to shipping', $posted['shipping_address_1'] === 'Karl Johans gate 1' );

nh_iframe_first_assert( 'live kustom order id is kept', ! nh_checkout_kustom_should_drop_session_order( 'abc123' ) );
nh_iframe_first_assert( 'empty kustom order id may be created', nh_checkout_kustom_should_drop_session_order( '' ) );
nh_iframe_first_assert( 'leaving kustom resets the iframe session', nh_checkout_should_reset_snippet_sessions( true, 'bacs' ) );
nh_iframe_first_assert( 'staying on kustom keeps the iframe session', ! nh_checkout_should_reset_snippet_sessions( true, 'kco' ) );
nh_iframe_first_assert( 'bacs checkout does not reset a kustom session that was never ready', ! nh_checkout_should_reset_snippet_sessions( false, 'bacs' ) );
nh_iframe_first_assert( 'kustom sync helper exists', function_exists( 'nh_checkout_kustom_sync_live_order' ) );
nh_iframe_first_assert( 'kustom sync without Woo session is a no-op', nh_checkout_kustom_sync_live_order() === false );

$_REQUEST['wc-ajax'] = 'nh_snippet_apply_zip';
nh_iframe_first_assert( 'zip ajax is treated as checkout for kustom', nh_checkout_kustom_ajax_is_checkout( false ) === true );
$_REQUEST['wc-ajax'] = 'update_order_review';
nh_iframe_first_assert( 'other ajax does not fake checkout', nh_checkout_kustom_ajax_is_checkout( false ) === false );
unset( $_REQUEST['wc-ajax'] );

$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/checkout-ux.js' );
nh_iframe_first_assert( 'kustom zip does not replace klarna api.on handlers', strpos( $js, 'shipping_address_change: onKlarnaAddr' ) === false );
nh_iframe_first_assert(
	'kustom zip success triggers update_checkout',
	strpos( $js, 'if (/kco|kustom|klarna/.test(method))' ) !== false
	&& strpos( $js, "trigger('update_checkout', { update_shipping_method: true })" ) !== false
);
nh_iframe_first_assert( 'svea zip still refreshes the svea snippet', strpos( $js, "trigger('sco_refresh_data')" ) !== false );

if ( $failures > 0 ) {
	exit( 1 );
}

echo "All tests passed.\n";
