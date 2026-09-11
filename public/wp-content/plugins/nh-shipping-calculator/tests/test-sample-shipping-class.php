<?php
/**
 * CLI tests: samples always map to shipping class slug "xs".
 *
 * Run: php public/wp-content/plugins/nh-shipping-calculator/tests/test-sample-shipping-class.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_-]+/', '-', (string) $title ) );
	}
}

if ( ! function_exists( 'get_term_by' ) ) {
	function get_term_by( $field, $value, $taxonomy ) {
		unset( $field, $taxonomy );
		$slug = (string) $value;
		if ( $slug === '' ) {
			return false;
		}
		$term       = new stdClass();
		$term->slug = $slug;
		$term->term_id = ( 'xs' === $slug ) ? 11 : 99;
		return $term;
	}
}

if ( ! function_exists( 'get_term' ) ) {
	function get_term( $term_id, $taxonomy = '' ) {
		unset( $taxonomy );
		$term          = new stdClass();
		$term->term_id = (int) $term_id;
		$term->slug    = ( 11 === (int) $term_id ) ? 'xs' : 'xl';
		return $term;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_get_object_terms' ) ) {
	function wp_get_object_terms( $object_id, $taxonomy ) {
		unset( $object_id, $taxonomy );
		$term       = new stdClass();
		$term->slug = 'xl';
		return array( $term );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {}
}

require_once dirname( __DIR__ ) . '/includes/class-nhgp-custom-cut.php';

$failures = 0;

function nhgp_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "OK $label\n";
		return;
	}
	$failures++;
	fwrite( STDERR, "FAIL $label\n" );
}

$cs = array(
	'r1_w'          => '1050',
	'r1_h'          => '1000',
	'r1_class'      => 'xs',
	'r2_w'          => '2100',
	'r2_h'          => '2000',
	'r2_class'      => 's',
	'default_class' => 'xxxl',
);

nhgp_assert( 'shared slug is xs', NHGP_Custom_Cut::SAMPLE_SHIPPING_CLASS_SLUG === 'xs' );

$sample = array(
	'norhage_sample'   => true,
	'cutting_type'     => 'sample',
	'custom_width_mm'  => 100,
	'custom_length_mm' => 100,
	'nh_custom_size'   => array(
		'width_mm'  => 2000,
		'length_mm' => 2000,
	),
);

nhgp_assert( 'detect norhage_sample flag', NHGP_Custom_Cut::is_sample_item( $sample ) );
nhgp_assert( 'sample is not custom-cut', ! NHGP_Custom_Cut::is_custom_item( $sample, null, $cs ) );
nhgp_assert(
	'sample maps to xs even with large nh_custom_size',
	NHGP_Custom_Cut::mapped_class_slug_for_item( $sample, null, $cs ) === 'xs'
);

$cutting_type_only = array(
	'cutting_type'    => 'sample',
	'nh_custom_size'  => array(
		'width_mm'  => 3000,
		'length_mm' => 3000,
	),
);
nhgp_assert( 'detect cutting_type=sample', NHGP_Custom_Cut::is_sample_item( $cutting_type_only ) );
nhgp_assert(
	'cutting_type sample maps to xs',
	NHGP_Custom_Cut::mapped_class_slug_for_item( $cutting_type_only, null, $cs ) === 'xs'
);

$real_cut = array(
	'nh_custom_size' => array(
		'width_mm'  => 2000,
		'length_mm' => 2000,
	),
);
nhgp_assert( 'real custom-cut is custom', NHGP_Custom_Cut::is_custom_item( $real_cut, null, $cs ) );
nhgp_assert(
	'real custom-cut without editor class uses size rule s',
	NHGP_Custom_Cut::mapped_class_slug_for_item( $real_cut, null, $cs ) === 's'
);

$simple = array( 'product_id' => 1 );
nhgp_assert( 'plain cart line is not a sample', ! NHGP_Custom_Cut::is_sample_item( $simple ) );
nhgp_assert(
	'plain cart line has no mapped class',
	NHGP_Custom_Cut::mapped_class_slug_for_item( $simple, null, $cs ) === ''
);

if ( $failures ) {
	exit( 1 );
}

echo "All sample shipping-class tests passed.\n";
