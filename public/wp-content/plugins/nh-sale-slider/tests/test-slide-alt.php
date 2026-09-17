<?php
/**
 * CLI tests: sale slider alt lookup helpers.
 *
 * Run: php public/wp-content/plugins/nh-sale-slider/tests/test-slide-alt.php
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

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return trim( strip_tags( (string) $string ) );
	}
}

$nhss_test_alts = array();

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key, $single = false ) {
		global $nhss_test_alts;
		unset( $single );
		if ( $key !== '_wp_attachment_image_alt' ) {
			return '';
		}
		return isset( $nhss_test_alts[ (int) $id ] ) ? $nhss_test_alts[ (int) $id ] : '';
	}
}

if ( ! function_exists( 'attachment_url_to_postid' ) ) {
	function attachment_url_to_postid( $url ) {
		$map = array(
			'https://norhage.eu/wp-content/uploads/2026/08/eu-multiwall-sale-20.webp' => 42,
		);
		return isset( $map[ $url ] ) ? $map[ $url ] : 0;
	}
}

require_once dirname( __DIR__ ) . '/nh-sale-slider.php';

$failures = 0;

function nhss_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

nhss_assert(
	'desktop original URL maps to uploads path',
	nhss_uploads_relative_path( 'https://norhage.eu/wp-content/uploads/2026/08/eu-multiwall-sale-20.webp' ) === '2026/08/eu-multiwall-sale-20.webp'
);

nhss_assert(
	'size suffix is stripped from uploads path',
	nhss_uploads_relative_path( 'https://norhage.eu/wp-content/uploads/2026/08/eu-multiwall-sale-20-800x600.webp' ) === '2026/08/eu-multiwall-sale-20.webp'
);

nhss_assert(
	'scaled suffix and query string are stripped',
	nhss_uploads_relative_path( 'https://norhage.eu/wp-content/uploads/2026/06/thunderstorm-scaled.svg?ver=1' ) === '2026/06/thunderstorm.svg'
);

nhss_assert(
	'non-upload URL returns empty path',
	nhss_uploads_relative_path( 'https://norhage.eu/wp-content/themes/astra-custom-for-norhage/assets/images/multiwall-width.svg' ) === ''
);

$nhss_test_alts[42] = '20% off multiwall sheets';
nhss_assert(
	'slide alt comes from media library via attachment ID',
	nhss_slide_alt( array( 'image_id' => 42, 'image' => '' ) ) === '20% off multiwall sheets'
);

nhss_assert(
	'slide alt comes from media library via stored URL',
	nhss_slide_alt( array(
		'image' => 'https://norhage.eu/wp-content/uploads/2026/08/eu-multiwall-sale-20.webp',
	) ) === '20% off multiwall sheets'
);

$nhss_test_alts[42] = '';
nhss_assert(
	'empty media alt falls back to translatable Sale',
	nhss_slide_alt( array( 'image_id' => 42 ) ) === 'Sale'
);

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll tests passed\n";
exit( $failures ? 1 : 0 );
