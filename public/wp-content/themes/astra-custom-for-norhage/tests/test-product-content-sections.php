<?php
/**
 * CLI tests: product content sections helpers.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-product-content-sections.php
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
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		protected $reviews = 0;

		public function __construct( $reviews = 0 ) {
			$this->reviews = (int) $reviews;
		}

		public function get_review_count() {
			return $this->reviews;
		}
	}
}

require_once dirname( __DIR__ ) . '/inc/product-content-sections.php';

$failures = 0;

function nh_pcs_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$with_reviews    = new WC_Product( 2 );
$without_reviews = new WC_Product( 0 );

nh_pcs_assert( 'description starts open', nh_pcs_section_default_open( 'description', $with_reviews ) );
nh_pcs_assert( 'downloads start open', nh_pcs_section_default_open( 'nrh_downloads', $with_reviews ) );
nh_pcs_assert( 'faq starts open', nh_pcs_section_default_open( 'nh_faq', $with_reviews ) );
nh_pcs_assert( 'specs start closed', ! nh_pcs_section_default_open( 'additional_information', $with_reviews ) );
nh_pcs_assert( 'video starts closed', ! nh_pcs_section_default_open( 'nrh_video', $with_reviews ) );
nh_pcs_assert( 'reviews open when count > 0', nh_pcs_section_default_open( 'reviews', $with_reviews ) );
nh_pcs_assert( 'reviews closed when empty', ! nh_pcs_section_default_open( 'reviews', $without_reviews ) );
nh_pcs_assert( 'unknown keys start closed', ! nh_pcs_section_default_open( 'custom_tab', $with_reviews ) );

nh_pcs_assert( 'description uses clamp', nh_pcs_section_uses_clamp( 'description' ) );
nh_pcs_assert( 'specs do not use clamp', ! nh_pcs_section_uses_clamp( 'additional_information' ) );

nh_pcs_assert( 'nav label strips review count', 'Omtaler' === nh_pcs_nav_label( 'Omtaler (1)' ) );
nh_pcs_assert( 'nav label keeps plain titles', 'Beskrivelse' === nh_pcs_nav_label( 'Beskrivelse' ) );
nh_pcs_assert( 'nav label strips tags', 'Nedlastinger' === nh_pcs_nav_label( '<span>Nedlastinger</span>' ) );

$template = file_get_contents( dirname( __DIR__ ) . '/woocommerce/single-product/tabs/tabs.php' );
nh_pcs_assert( 'template uses details for collapsible sections', false !== strpos( $template, '<details' ) );
nh_pcs_assert( 'template keeps tab ids for deep links', false !== strpos( $template, 'id="tab-' ) );
nh_pcs_assert( 'template prints a jump nav', false !== strpos( $template, 'nh-pcs-toc' ) );
nh_pcs_assert( 'template keeps full panel HTML in the page', false !== strpos( $template, '$panel_html' ) );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/product-page.css' );
nh_pcs_assert( 'css hides the old tab list', false !== strpos( $css, 'ul.tabs' ) );
nh_pcs_assert( 'css clamps long description copy', false !== strpos( $css, 'nh-pcs-clamp' ) );

$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/product-content-sections.js' );
nh_pcs_assert( 'js opens sections from hash links', false !== strpos( $js, 'openSectionByHash' ) );
nh_pcs_assert( 'js expands the description clamp', false !== strpos( $js, 'is-expanded' ) );

$functions = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
nh_pcs_assert(
	'functions.php loads the sections module',
	false !== strpos( $functions, "/inc/product-content-sections.php'" )
);

$sample = '<p>Intro copy about the greenhouse.</p>'
	. '<div class="nh-important-notes"><div class="nh-in-txt"><strong>UV</strong> Merk UV-siden.</div></div>'
	. '<section id="nh-mb-extra-1" class="nh-mb-product-extra"><h2>Bruksområder og kompatibilitet</h2><div class="nh-mb-columns"><div class="nh-mb-column">Ideell</div></div></section>';

$split = nh_pcs_split_description_highlights( $sample );
nh_pcs_assert( 'sales copy stays in the description body', false !== strpos( $split['body'], 'Intro copy' ) );
nh_pcs_assert( 'important notes leave the clamped body', false === strpos( $split['body'], 'nh-important-notes' ) );
nh_pcs_assert( 'use cases leave the clamped body', false === strpos( $split['body'], 'nh-mb-product-extra' ) );
nh_pcs_assert( 'important notes stay in highlights', false !== strpos( $split['highlights'], 'Merk UV-siden' ) );
nh_pcs_assert( 'use cases stay in highlights', false !== strpos( $split['highlights'], 'Bruksområder og kompatibilitet' ) );
nh_pcs_assert(
	'important notes stay before use cases',
	strpos( $split['highlights'], 'nh-important-notes' ) < strpos( $split['highlights'], 'nh-mb-product-extra' )
);

$plain = '<p>Only the story.</p>';
$plain_split = nh_pcs_split_description_highlights( $plain );
nh_pcs_assert( 'description without those blocks is unchanged', $plain === $plain_split['body'] && '' === $plain_split['highlights'] );

nh_pcs_assert(
	'template prints highlights outside the clamp',
	false !== strpos( $template, 'nh-pcs-highlights' )
);

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
