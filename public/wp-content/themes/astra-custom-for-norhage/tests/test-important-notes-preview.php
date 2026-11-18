<?php
/**
 * CLI tests: important notes preview.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-important-notes-preview.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		unset( $hook );
		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( __( $text, $domain ), ENT_QUOTES, 'UTF-8' );
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

if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
		return 1;
	}
}

$nh_note_keys = '';

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key, $single = false ) {
		global $nh_note_keys;
		unset( $post_id, $key, $single );
		return $nh_note_keys;
	}
}

require_once dirname( __DIR__ ) . '/inc/important-notes.php';

$failures = 0;

function nh_notes_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

nh_notes_assert( 'preview shows three notes', 3 === nh_important_notes_preview_count() );

global $nh_note_keys;
$nh_note_keys = 'color_variations, uv_protection';
$short        = nh_get_important_notes_html( 1 );
nh_notes_assert( 'short list has no expander', false === strpos( $short, 'nh-in-more' ) );
nh_notes_assert( 'short list keeps both notes', substr_count( $short, 'nh-in-item' ) === 2 );

$nh_note_keys = 'color_variations, internal_structure, warranty_10_year, uv_protection, storage';
$long         = nh_get_important_notes_html( 1 );
nh_notes_assert( 'long list adds an expander', false !== strpos( $long, '<details class="nh-in-more">' ) );
nh_notes_assert( 'long list offers the hidden count', false !== strpos( $long, 'Show 2 more' ) );
nh_notes_assert( 'every note stays in the html', substr_count( $long, 'class="nh-in-item"' ) === 5 );

$visible = strstr( $long, '<details', true );
nh_notes_assert( 'only three notes sit above the expander', substr_count( $visible, 'class="nh-in-item"' ) === 3 );

$nh_note_keys = 'color_variations, internal_structure, warranty_10_year, uv_protection, cutting_tolerance';
$pinned       = nh_get_important_notes_html( 1 );
$pinned_open  = strstr( $pinned, '<details', true );
nh_notes_assert( 'cutting tolerance stays above the expander', false !== strpos( $pinned_open, 'Cutting Tolerance' ) );
nh_notes_assert( 'cutting tolerance leads the visible notes', strpos( $pinned_open, 'Cutting Tolerance' ) < strpos( $pinned_open, 'Color Variations' ) );
nh_notes_assert( 'pinned list still shows three notes', substr_count( $pinned_open, 'class="nh-in-item' ) === 3 );
nh_notes_assert( 'cutting tolerance row keeps the normal note style', false === strpos( $pinned_open, 'nh-in-item--emphasis' ) );
nh_notes_assert( 'hidden count ignores the pinned note', false !== strpos( $pinned, 'Show 2 more' ) );

$nh_note_keys = 'uv_protection, storage';
nh_notes_assert( 'callout is empty without the tolerance note', '' === nh_get_cutting_tolerance_callout_html( 1 ) );

$nh_note_keys = 'storage, cutting_tolerance';
$callout      = nh_get_cutting_tolerance_callout_html( 1 );
nh_notes_assert( 'callout uses the tolerance note', false !== strpos( $callout, 'class="nh-cutting-tolerance"' ) && false !== strpos( $callout, 'maximum allowable cutting deviation' ) );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/product-page.css' );
nh_notes_assert(
	'open notes move the collapse control below the list',
	false !== strpos( $css, '.nh-in-more[open] > .nh-in-more__summary' ) && false !== strpos( $css, 'order: 2' )
);
nh_notes_assert(
	'callout is small grey text without a box',
	false !== strpos( $css, '.nh-cutting-tolerance' )
		&& false !== strpos( $css, 'background: transparent' )
		&& false !== strpos( $css, 'font-size: 12px' )
		&& false === strpos( $css, '#f4efe6' )
);

$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/delivery-time.js' );
nh_notes_assert( 'delivery script leaves the tolerance line where it is printed', false === strpos( $js, 'nh-cutting-tolerance' ) );

$output = file_get_contents( dirname( __DIR__ ) . '/inc/important-notes-output.php' );
nh_notes_assert(
	'callout prints under the selection box',
	false !== strpos( $output, "add_action( 'woocommerce_before_add_to_cart_button', 'nh_output_cutting_tolerance_callout', 12 )" )
);

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
