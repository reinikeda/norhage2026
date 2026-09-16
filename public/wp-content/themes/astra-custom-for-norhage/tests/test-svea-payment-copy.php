<?php
/**
 * CLI tests: SVEA checkout payment box copy.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-svea-payment-copy.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
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
		return trim( wp_strip_tags( (string) $string ) );
	}
}

if ( ! function_exists( 'wp_strip_tags' ) ) {
	function wp_strip_tags( $string ) {
		return strip_tags( (string) $string );
	}
}

require_once dirname( __DIR__ ) . '/inc/checkout-ux.php';

$failures = 0;

function nh_svea_copy_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "OK $label\n";
		return;
	}
	$failures++;
	fwrite( STDERR, "FAIL $label\n" );
}

$expected = 'You will continue securely to SVEA to complete payment.';
$plugin   = 'Pay with Svea Checkout. Redirecting…';

nh_svea_copy_assert( 'svea copy helper', nh_checkout_svea_payment_copy() === $expected );
nh_svea_copy_assert(
	'svea_checkout description is continue copy',
	nh_checkout_gateway_description( $plugin, 'svea_checkout' ) === $expected
);
nh_svea_copy_assert(
	'sco description is continue copy',
	nh_checkout_gateway_description( $plugin, 'sco' ) === $expected
);
nh_svea_copy_assert(
	'bacs description is unchanged',
	nh_checkout_gateway_description( 'Pay by bank transfer.', 'bacs' ) === 'Pay by bank transfer.'
);
nh_svea_copy_assert(
	'kustom description is unchanged',
	nh_checkout_gateway_description( 'Pay with Kustom.', 'kco' ) === 'Pay with Kustom.'
);

$svea            = new stdClass();
$svea->id        = 'svea_checkout';
$svea->description = $plugin;
$svea->order_button_text = '';

$bacs            = new stdClass();
$bacs->id        = 'bacs';
$bacs->description = 'Pay by bank transfer.';
$bacs->order_button_text = '';

$prepared = nh_checkout_prepare_payment_gateway_copy(
	array(
		'svea_checkout' => $svea,
		'bacs'          => $bacs,
	)
);

nh_svea_copy_assert( 'prepare overwrites svea description', $prepared['svea_checkout']->description === $expected );
nh_svea_copy_assert( 'prepare sets svea continue button', $prepared['svea_checkout']->order_button_text === 'Continue to SVEA' );
nh_svea_copy_assert( 'prepare leaves bacs description', $prepared['bacs']->description === 'Pay by bank transfer.' );
nh_svea_copy_assert( 'prepare leaves bacs button empty', $prepared['bacs']->order_button_text === '' );

$blurb_gateway     = new stdClass();
$blurb_gateway->id = 'svea_checkout';
nh_svea_copy_assert( 'svea card blurb matches payment box', nh_checkout_gateway_blurb( $blurb_gateway ) === $expected );

if ( $failures > 0 ) {
	exit( 1 );
}

echo "All tests passed.\n";
