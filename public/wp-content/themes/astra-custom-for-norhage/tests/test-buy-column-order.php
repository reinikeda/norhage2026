<?php
/**
 * CLI tests: stage 2 buy-column hook order.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-buy-column-order.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

$nh_hooks = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		global $nh_hooks;
		unset( $callback, $accepted_args );
		$nh_hooks[] = array( $hook, (int) $priority );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		add_action( $hook, $callback, $priority, $accepted_args );
	}
}

require_once dirname( __DIR__ ) . '/inc/feature-box-output.php';
require_once dirname( __DIR__, 3 ) . '/plugins/nh-cutting-toggle/nh-cutting-toggle.php';

$failures = 0;

function nh_order_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

function nh_order_has( $hook, $priority ) {
	global $nh_hooks;
	foreach ( $nh_hooks as $row ) {
		if ( $row[0] === $hook && $row[1] === $priority ) {
			return true;
		}
	}
	return false;
}

nh_order_assert(
	'feature chips render after the cart form',
	nh_order_has( 'woocommerce_after_add_to_cart_form', 8 )
);
nh_order_assert(
	'feature chips are not hooked before the cart form',
	! nh_order_has( 'woocommerce_before_add_to_cart_form', 1 )
);
nh_order_assert(
	'cutting toggle renders before the cart form',
	nh_order_has( 'woocommerce_before_add_to_cart_form', 5 )
);
nh_order_assert(
	'cutting toggle is not tied to the feature box',
	! nh_order_has( 'nh_after_feature_box', 10 )
);
nh_order_assert(
	'out-of-stock chips still append to the short description',
	nh_order_has( 'woocommerce_short_description', 20 )
);

$functions = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
nh_order_assert(
	'ask an expert is priority 12',
	(bool) preg_match( '/add_action\(\s*\'woocommerce_after_add_to_cart_form\', function \(\) \{.*?\}, 12 \);/s', $functions )
);
nh_order_assert(
	'short description moves below the bundle',
	strpos( $functions, "add_action( 'woocommerce_after_add_to_cart_form', 'woocommerce_template_single_excerpt', 25 )" ) !== false
);

$bundle = file_get_contents( dirname( __DIR__ ) . '/inc/bundle-box.php' );
nh_order_assert(
	'bundle box stays after the cart form at priority 20',
	strpos( $bundle, "add_action( 'woocommerce_after_add_to_cart_form', 'nh_render_bundle_box', 20 )" ) !== false
);

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
