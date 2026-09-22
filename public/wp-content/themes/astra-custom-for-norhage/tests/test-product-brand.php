<?php
/**
 * CLI tests: product brand line above the title.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-product-brand.php
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
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {}
}

class NH_Brand_Test_Product extends WC_Product {
	public function get_id() {
		return 42;
	}
}

$nh_brand_terms = array();

if ( ! function_exists( 'wp_get_post_terms' ) ) {
	function wp_get_post_terms( $post_id, $taxonomy ) {
		global $nh_brand_terms;
		unset( $post_id, $taxonomy );
		return $nh_brand_terms;
	}
}

if ( ! function_exists( 'get_term_link' ) ) {
	function get_term_link( $term ) {
		$slug = isset( $term->slug ) ? $term->slug : '';
		return 'https://norhage.no/merke/' . $slug . '/';
	}
}

require_once dirname( __DIR__ ) . '/inc/product-brand.php';

$failures = 0;

function nh_brand_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

nh_brand_assert( 'slug norhage is the house brand', norhage_brand_is_house( 'norhage', 'Norhage' ) );
nh_brand_assert( 'name Norhage is the house brand', norhage_brand_is_house( 'something', 'Norhage' ) );
nh_brand_assert( 'Arla is a supplier brand', ! norhage_brand_is_house( 'arla', 'Arla' ) );
nh_brand_assert( 'Polygal is a supplier brand', ! norhage_brand_is_house( 'polygal', 'Polygal' ) );

$arla = norhage_brand_link_html( 'Arla', 'https://norhage.no/merke/arla/' );
nh_brand_assert( 'supplier link shows the brand name', false !== strpos( $arla, '>Arla<' ) );
nh_brand_assert( 'supplier link points at the brand archive', false !== strpos( $arla, 'href="https://norhage.no/merke/arla/"' ) );
nh_brand_assert( 'supplier link has an accessible name', false !== strpos( $arla, 'aria-label="View products by Arla"' ) );
nh_brand_assert( 'supplier link is not an image', false === strpos( $arla, '<img' ) );
nh_brand_assert( 'empty brand name prints nothing', '' === norhage_brand_link_html( '  ', 'https://example.test/' ) );

$plain = norhage_brand_link_html( 'Polygal', '' );
nh_brand_assert( 'missing archive still shows the name', false !== strpos( $plain, '>Polygal<' ) );
nh_brand_assert( 'missing archive is not a link', false === strpos( $plain, '<a ' ) );

function nh_brand_capture() {
	ob_start();
	norhage_output_product_brand_logo();
	return ob_get_clean();
}

global $product, $nh_brand_terms;
$product        = new NH_Brand_Test_Product();
$nh_brand_terms = array(
	(object) array(
		'name' => 'Norhage',
		'slug' => 'norhage',
	),
);
nh_brand_assert( 'a Norhage product prints no brand line', '' === nh_brand_capture() );

$nh_brand_terms = array(
	(object) array(
		'name' => 'Norhage',
		'slug' => 'norhage',
	),
	(object) array(
		'name' => 'Arla',
		'slug' => 'arla',
	),
);
$mixed = nh_brand_capture();
nh_brand_assert( 'a mixed product skips Norhage', false === strpos( $mixed, '>Norhage<' ) );
nh_brand_assert( 'a mixed product shows the supplier', false !== strpos( $mixed, '>Arla<' ) );

$nh_brand_terms = array(
	(object) array(
		'name' => 'Polygal',
		'slug' => 'polygal',
	),
);
$polygal = nh_brand_capture();
nh_brand_assert( 'Polygal links to its archive', false !== strpos( $polygal, 'href="https://norhage.no/merke/polygal/"' ) );

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
