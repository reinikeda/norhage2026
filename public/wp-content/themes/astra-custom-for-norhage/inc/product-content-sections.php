<?php
/**
 * Product page content sections (replaces tab UI).
 *
 * Renders the same WooCommerce tab callbacks in a scrollable stack with
 * jump links. Collapsed panels use <details>, so the HTML stays in the
 * page for search engines and AI crawlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a section starts open.
 *
 * @param string     $key     Tab key from woocommerce_product_tabs.
 * @param WC_Product $product Current product.
 */
function nh_pcs_section_default_open( $key, $product = null ) : bool {
	$key = (string) $key;

	switch ( $key ) {
		case 'description':
		case 'nrh_downloads':
		case 'nh_faq':
			return true;

		case 'reviews':
			if ( $product instanceof WC_Product ) {
				return (int) $product->get_review_count() > 0;
			}
			return false;

		case 'additional_information':
		case 'nrh_video':
		default:
			return false;
	}
}

/**
 * Whether the section uses a long-text clamp with Read more.
 *
 * @param string $key Tab key.
 */
function nh_pcs_section_uses_clamp( $key ) : bool {
	return 'description' === (string) $key;
}

/**
 * Sorted product tabs for the current product.
 *
 * @return array<string, array>
 */
function nh_pcs_get_product_tabs() : array {
	$tabs = apply_filters( 'woocommerce_product_tabs', array() );
	if ( ! is_array( $tabs ) || empty( $tabs ) ) {
		return array();
	}

	uasort(
		$tabs,
		static function ( $a, $b ) {
			$pa = isset( $a['priority'] ) ? (int) $a['priority'] : 10;
			$pb = isset( $b['priority'] ) ? (int) $b['priority'] : 10;
			return $pa <=> $pb;
		}
	);

	return $tabs;
}

/**
 * Plain title for jump links (strip review counts in parentheses).
 *
 * @param string $title Tab title HTML/text.
 */
function nh_pcs_nav_label( $title ) : string {
	$text = wp_strip_all_tags( (string) $title );
	$text = preg_replace( '/\s*\(\s*\d+\s*\)\s*$/u', '', $text );
	return trim( (string) $text );
}

/**
 * Enqueue section scripts on single products.
 */
function nh_pcs_enqueue_assets() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	wp_enqueue_script(
		'nh-product-content-sections',
		get_stylesheet_directory_uri() . '/assets/js/product-content-sections.js',
		array(),
		norhage_asset_version( '/assets/js/product-content-sections.js' ),
		function_exists( 'norhage_script_args' ) ? norhage_script_args() : true
	);

	wp_localize_script(
		'nh-product-content-sections',
		'nhPcs',
		array(
			'more' => __( 'Read more', 'nh-theme' ),
			'less' => __( 'Show less', 'nh-theme' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'nh_pcs_enqueue_assets', 30 );

/**
 * Mark the product page so Astra vertical-tab chrome can be neutralized.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function nh_pcs_body_class( $classes ) {
	if ( function_exists( 'is_product' ) && is_product() ) {
		$classes[] = 'nh-product-sections';
	}
	return $classes;
}
add_filter( 'body_class', 'nh_pcs_body_class' );
