<?php
/**
 * CLI tests: catalog filter attribute allow-list.
 *
 * Run: php public/wp-content/plugins/nh-filters/tests/test-filter-attributes.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

$nhf_option = false;
$nhf_attrs  = array();

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strtolower( (string) $title );
		$title = preg_replace( '/[^a-z0-9_-]+/', '-', $title );
		return trim( (string) $title, '-' );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		global $nhf_option;
		unset( $key );
		return ( false === $nhf_option ) ? $default : $nhf_option;
	}
}

if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
	function wc_get_attribute_taxonomies() {
		global $nhf_attrs;
		return $nhf_attrs;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

require_once dirname( __DIR__ ) . '/includes/attributes.php';

$failures = 0;

function nhf_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

global $nhf_option, $nhf_attrs;
$nhf_attrs = array(
	(object) array(
		'attribute_name'  => 'bredde',
		'attribute_label' => 'Bredde',
	),
	(object) array(
		'attribute_name'  => 'farge',
		'attribute_label' => 'Farge',
	),
	(object) array(
		'attribute_name'  => 'leveringstid',
		'attribute_label' => 'Leveringstid',
	),
);

nhf_assert( 'unsaved settings keep every attribute', null === nhf_get_visible_attribute_slugs() );
nhf_assert( 'unsaved settings still show width', nhf_attribute_is_visible( 'bredde' ) );
nhf_assert( 'unsaved settings still show delivery time', nhf_attribute_is_visible( 'leveringstid' ) );

$saved = nhf_sanitize_filter_attributes( array( 'bredde', 'farge', '' ) );
nhf_assert( 'save marks the list as configured', ! empty( $saved['saved'] ) );
nhf_assert( 'save keeps the ticked slugs', array( 'bredde', 'farge' ) === $saved['slugs'] );
nhf_assert( 'save drops unknown slugs', array( 'saved' => 1, 'slugs' => array( 'bredde' ) ) === nhf_sanitize_filter_attributes( array( 'bredde', 'not-an-attribute' ) ) );
nhf_assert( 'empty save stores an empty list', array( 'saved' => 1, 'slugs' => array() ) === nhf_sanitize_filter_attributes( array( '' ) ) );

$nhf_option = $saved;
nhf_assert( 'saved list returns the ticked slugs', array( 'bredde', 'farge' ) === nhf_get_visible_attribute_slugs() );
nhf_assert( 'saved list shows width', nhf_attribute_is_visible( 'bredde' ) );
nhf_assert( 'saved list hides delivery time', ! nhf_attribute_is_visible( 'leveringstid' ) );

$nhf_option = array(
	'saved' => 1,
	'slugs' => array(),
);
nhf_assert( 'an empty saved list hides every attribute', array() === nhf_get_visible_attribute_slugs() );
nhf_assert( 'an empty saved list hides width', ! nhf_attribute_is_visible( 'bredde' ) );

$plugin = file_get_contents( dirname( __DIR__ ) . '/nh-filters.php' );
nhf_assert( 'sidebar skips attributes that are not ticked', false !== strpos( $plugin, 'nhf_attribute_is_visible' ) );
nhf_assert( 'admin screen is loaded in wp-admin', false !== strpos( $plugin, '/includes/admin.php' ) );
nhf_assert( 'sidebar renders a range for numeric attributes', false !== strpos( $plugin, 'nhf_render_range_filter' ) );

nhf_assert( 'comma decimals parse as fractions', 1.05 === nhf_parse_numeric_value( '1,05 m' ) );
nhf_assert( 'dot decimals still parse', 2.1 === nhf_parse_numeric_value( '2.1 m' ) );
nhf_assert( 'whole millimetres parse', 10.0 === nhf_parse_numeric_value( '10 mm' ) );
nhf_assert( 'plain text has no number', null === nhf_parse_numeric_value( 'Klar' ) );

$width = (object) array( 'attribute_orderby' => 'name_num' );
$color = (object) array( 'attribute_orderby' => 'name' );
nhf_assert( 'Name (numeric) uses a range', nhf_attribute_uses_range( $width ) );
nhf_assert( 'Name order stays as checkboxes', ! nhf_attribute_uses_range( $color ) );

$term_a = (object) array( 'name' => '2,1 m', 'slug' => '2-1-m' );
$term_b = (object) array( 'name' => '0,9 m', 'slug' => '0-9-m' );
$term_c = (object) array( 'name' => '1,05 m', 'slug' => '1-05-m' );
$sorted = nhf_numeric_terms( array( $term_a, $term_b, $term_c ) );
nhf_assert( 'numeric terms sort from smallest', '0-9-m' === $sorted[0]['term']->slug );
nhf_assert( 'numeric terms sort the middle value', '1-05-m' === $sorted[1]['term']->slug );
nhf_assert( 'numeric terms sort the largest last', '2-1-m' === $sorted[2]['term']->slug );

$full = nhf_range_selection( $sorted, array() );
nhf_assert( 'an empty selection covers the full span', array( 0, 2, false ) === $full );
$part = nhf_range_selection( $sorted, array( '1-05-m', '2-1-m' ) );
nhf_assert( 'a partial selection starts at the first hit', 1 === $part[0] && 2 === $part[1] && true === $part[2] );

$mixed = nhf_numeric_terms(
	array(
		$term_a,
		(object) array( 'name' => 'Klar', 'slug' => 'klar' ),
	)
);
nhf_assert( 'a mixed list falls back to checkboxes', array() === $mixed );

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
