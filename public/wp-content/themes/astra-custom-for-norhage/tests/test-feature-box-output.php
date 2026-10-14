<?php
/**
 * CLI tests: out-of-stock feature box must not consume the page buffer.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-feature-box-output.php
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
		add_action( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		public function is_in_stock() {
			return false;
		}

		public function get_id() {
			return 2022;
		}
	}
}

if ( ! function_exists( 'is_product' ) ) {
	function is_product() {
		return true;
	}
}

if ( ! function_exists( 'nh_get_feature_box_html' ) ) {
	function nh_get_feature_box_html( $product_id = 0 ) {
		unset( $product_id );
		return '<section class="nhf-box"></section>';
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $html ) {
		return is_string( $html ) ? $html : '';
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		unset( $args );
		if ( 'nh_after_feature_box' === $hook ) {
			echo '<p class="nh-after-feature">note</p>';
		}
	}
}

require_once dirname( __DIR__ ) . '/inc/feature-box-output.php';

$failures = 0;

function nh_fb_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

function nh_fb_reset() {
	global $nh_feature_box_rendered, $product;
	$nh_feature_box_rendered = false;
	$product                 = new WC_Product();
}

/**
 * Same name WooCommerce uses when formatting variation descriptions.
 *
 * @param string $desc Description passed through the short-description filter.
 * @return string
 */
function wc_format_content( $desc ) {
	return nh_append_feature_box_to_short_desc( $desc );
}

nh_fb_reset();
ob_start();
echo '<!DOCTYPE html><script src="jquery.min.js"></script>';
$formatted = wc_format_content( '' );
$page      = ob_get_clean();

nh_fb_assert(
	'variation formatting leaves the page buffer alone',
	$page === '<!DOCTYPE html><script src="jquery.min.js"></script>'
);
nh_fb_assert(
	'variation formatting does not receive the feature box',
	$formatted === ''
);
nh_fb_assert(
	'variation formatting does not mark the feature box as rendered',
	$GLOBALS['nh_feature_box_rendered'] === false
);

nh_fb_reset();
ob_start();
echo '<!DOCTYPE html><script src="jquery.min.js"></script>';
$with_box = nh_append_feature_box_to_short_desc( '<p>Short</p>' );
$page     = ob_get_clean();

nh_fb_assert(
	'short description keeps the document head in the page buffer',
	$page === '<!DOCTYPE html><script src="jquery.min.js"></script>'
);
nh_fb_assert(
	'short description appends the feature box and hooked note',
	$with_box === '<p>Short</p><section class="nhf-box"></section><p class="nh-after-feature">note</p>'
);
nh_fb_assert(
	'appended markup does not contain the page head',
	strpos( $with_box, 'jquery.min.js' ) === false
);

$again = nh_append_feature_box_to_short_desc( '<p>Short</p>' );
nh_fb_assert(
	'feature box is appended once',
	$again === '<p>Short</p>'
);

nh_fb_reset();
$product = new class() extends WC_Product {
	public function is_in_stock() {
		return true;
	}
};
ob_start();
echo 'HEAD';
$instock = nh_append_feature_box_to_short_desc( '<p>In stock</p>' );
$head    = ob_get_clean();
nh_fb_assert( 'in-stock descriptions are unchanged', $instock === '<p>In stock</p>' && $head === 'HEAD' );

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
