<?php
/**
 * CLI tests: media-library alt helper.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-attachment-alt.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

$nh_test_alts = array(
	12  => 'Thunderstorm icon',
	13  => '  ',
	633 => 'Trustpilot logo',
);

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key, $single = false ) {
		global $nh_test_alts;
		unset( $single );
		if ( $key !== '_wp_attachment_image_alt' ) {
			return '';
		}
		return isset( $nh_test_alts[ (int) $id ] ) ? $nh_test_alts[ (int) $id ] : '';
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return trim( strip_tags( (string) $string ) );
	}
}

require_once dirname( __DIR__ ) . '/inc/attachment-alt.php';

$failures = 0;

function nh_alt_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

nh_alt_assert(
	'media library alt is returned',
	nh_get_attachment_alt( 12, 'Storm warranty' ) === 'Thunderstorm icon'
);

nh_alt_assert(
	'empty media alt uses fallback',
	nh_get_attachment_alt( 13, 'Storm warranty' ) === 'Storm warranty'
);

nh_alt_assert(
	'missing attachment uses fallback',
	nh_get_attachment_alt( 0, 'Storm warranty' ) === 'Storm warranty'
);

$trustpilot = '<div class="wp-block-media-text"><figure class="wp-block-media-text__media"><img fetchpriority="high" decoding="async" width="500" height="281" src="https://norhage.eu/wp-content/uploads/2026/07/trustpilot-logo.png" alt="" class="wp-image-633 size-full"></figure></div>';
$filled     = nh_fill_empty_img_alts_in_html( $trustpilot );
nh_alt_assert(
	'empty Gutenberg alt is replaced from media library',
	strpos( $filled, 'alt="Trustpilot logo"' ) !== false
);

$kept = nh_fill_empty_img_alts_in_html( '<img class="wp-image-633" alt="Custom caption" src="x.png">' );
nh_alt_assert(
	'non-empty block alt is left unchanged',
	strpos( $kept, 'alt="Custom caption"' ) !== false
);

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll tests passed\n";
exit( $failures ? 1 : 0 );
