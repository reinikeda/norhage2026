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

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
