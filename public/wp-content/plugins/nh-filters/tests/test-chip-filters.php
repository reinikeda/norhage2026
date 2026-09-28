<?php
/**
 * CLI tests: category chip filters.
 *
 * Run: php public/wp-content/plugins/nh-filters/tests/test-chip-filters.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

$nhf_options = array();
$nhf_attrs   = array();
$nhf_terms   = array();

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strtolower( (string) $title );
		$title = preg_replace( '/[^a-z0-9_-]+/', '-', $title );
		return trim( (string) $title, '-' );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $num ) {
		return abs( (int) $num );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		unset( $thing );
		return false;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		global $nhf_options;
		return array_key_exists( $key, $nhf_options ) ? $nhf_options[ $key ] : $default;
	}
}

if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
	function wc_get_attribute_taxonomies() {
		global $nhf_attrs;
		return $nhf_attrs;
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = array() ) {
		global $nhf_terms;
		unset( $args );
		return $nhf_terms;
	}
}

require_once dirname( __DIR__ ) . '/includes/attributes.php';
require_once dirname( __DIR__ ) . '/includes/chips.php';

$failures = 0;

function nhf_chip_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

global $nhf_attrs, $nhf_terms, $nhf_options;

$polycarbonate = (object) array(
	'term_id' => 10,
	'parent'  => 0,
	'name'    => 'Polycarbonate',
);
$multiwall = (object) array(
	'term_id' => 24,
	'parent'  => 10,
	'name'    => 'Polycarbonate Multiwall Sheets',
);
$fasteners = (object) array(
	'term_id' => 30,
	'parent'  => 0,
	'name'    => 'Fasteners',
);
$nhf_terms = array( $polycarbonate, $multiwall, $fasteners );
$nhf_attrs = array(
	(object) array(
		'attribute_name'  => 'thickness',
		'attribute_label' => 'Thickness',
	),
	(object) array(
		'attribute_name'  => 'color',
		'attribute_label' => 'Color',
	),
);

$leaves = nhf_leaf_ids_from_terms( $nhf_terms );
nhf_chip_assert( 'a parent category is not a leaf', ! in_array( 10, $leaves, true ) );
nhf_chip_assert( 'a category with no children is a leaf', in_array( 24, $leaves, true ) && in_array( 30, $leaves, true ) );
nhf_chip_assert( 'leaf lookup matches the term list', nhf_category_is_leaf( 24 ) );
nhf_chip_assert( 'a parent is not treated as a leaf', ! nhf_category_is_leaf( 10 ) );

$by_id = array(
	10 => $polycarbonate,
	24 => $multiwall,
);
nhf_chip_assert(
	'leaf labels include the parent name',
	'Polycarbonate / Polycarbonate Multiwall Sheets' === nhf_category_path_label( $multiwall, $by_id )
);

$saved = nhf_sanitize_chip_filters(
	array(
		'rows' => array(
			array(
				'attribute'  => 'thickness',
				'categories' => array( '24', '10', '24' ),
			),
			array(
				'attribute'  => 'color',
				'categories' => array( 24, 30 ),
			),
			array(
				'attribute'  => 'not-real',
				'categories' => array( 30 ),
			),
		),
	)
);
nhf_chip_assert( 'chip save keeps the leaf category on the first attribute', array( 24 ) === $saved['rows'][0]['categories'] );
nhf_chip_assert( 'chip save drops the parent category', 'thickness' === $saved['rows'][0]['attribute'] );
nhf_chip_assert( 'a later row cannot take a category already assigned', array( 30 ) === $saved['rows'][1]['categories'] );
nhf_chip_assert( 'unknown attributes are dropped', 2 === count( $saved['rows'] ) );

$nhf_options[ NHF_OPTION_CHIPS ] = $saved;
nhf_chip_assert( 'multiwall sheets use thickness', 'thickness' === nhf_chip_attribute_for_category( 24 ) );
nhf_chip_assert( 'fasteners use color', 'color' === nhf_chip_attribute_for_category( 30 ) );
nhf_chip_assert( 'an unassigned category has no chip filter', '' === nhf_chip_attribute_for_category( 10 ) );

$added = nhf_chip_query_args( 'thickness', '10-mm', array(), array( 'orderby' => 'price', 'paged' => '2' ) );
nhf_chip_assert( 'choosing a thickness adds that value', '10-mm' === $added['thickness'] );
nhf_chip_assert( 'other catalog args stay', 'price' === $added['orderby'] );
nhf_chip_assert( 'paging starts over', ! isset( $added['paged'] ) );

$toggled = nhf_chip_query_args( 'thickness', '16-mm', array( '10-mm' ), array() );
nhf_chip_assert( 'a second thickness stays selected with the first', '10-mm,16-mm' === $toggled['thickness'] );

$cleared = nhf_chip_query_args( 'thickness', '10-mm', array( '10-mm' ), array( 'instock' => '1' ) );
nhf_chip_assert( 'choosing the active thickness clears it', ! isset( $cleared['thickness'] ) );
nhf_chip_assert( 'stock stays selected', '1' === $cleared['instock'] );

$plugin = file_get_contents( dirname( __DIR__ ) . '/nh-filters.php' );
nhf_chip_assert( 'chips load with the filter plugin', false !== strpos( $plugin, '/includes/chips.php' ) );
$chips = file_get_contents( dirname( __DIR__ ) . '/includes/chips.php' );
nhf_chip_assert( 'chips print under the category description', false !== strpos( $chips, "add_action( 'woocommerce_archive_description', 'nhf_render_category_chips', 20 )" ) );
$admin = file_get_contents( dirname( __DIR__ ) . '/includes/admin.php' );
nhf_chip_assert( 'filter admin assigns chip categories', false !== strpos( $admin, 'nhf_render_chip_settings' ) );

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
