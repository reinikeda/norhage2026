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
 * Byte length of a balanced element starting at an opening tag.
 *
 * @param string $html   Full HTML.
 * @param int    $offset Offset of the opening "<".
 * @return array{0:int,1:int}|null Start and end offsets, end exclusive.
 */
function nh_pcs_balanced_element_range( $html, $offset ) {
	$slice = substr( $html, $offset );
	if ( ! preg_match( '/\A<([a-z0-9]+)\b[^>]*>/i', $slice, $open ) ) {
		return null;
	}

	$tag   = strtolower( $open[1] );
	$depth = 1;
	$i     = $offset + strlen( $open[0] );
	$len   = strlen( $html );

	while ( $i < $len && $depth > 0 ) {
		$next_open  = stripos( $html, '<' . $tag, $i );
		$next_close = stripos( $html, '</' . $tag, $i );

		if ( false === $next_close ) {
			return null;
		}

		$open_is_tag = false;
		if ( false !== $next_open && $next_open < $next_close ) {
			$after = substr( $html, $next_open + strlen( $tag ) + 1, 1 );
			$open_is_tag = ( '' === $after || '>' === $after || '/' === $after || ctype_space( $after ) );
		}

		if ( $open_is_tag ) {
			$depth++;
			$i = $next_open + strlen( $tag ) + 1;
			continue;
		}

		$gt = strpos( $html, '>', $next_close );
		if ( false === $gt ) {
			return null;
		}

		$depth--;
		$i = $gt + 1;
	}

	if ( $depth !== 0 ) {
		return null;
	}

	return array( $offset, $i );
}

/**
 * Pull important notes and the use-cases block out of the description HTML.
 *
 * The sales copy stays in "body" so Read more can clamp it. The two blocks
 * stay in "highlights", in their original order, so they remain visible.
 *
 * @param string $html Description panel HTML.
 * @return array{body:string,highlights:string}
 */
function nh_pcs_split_description_highlights( $html ) : array {
	$html = (string) $html;
	$empty = array(
		'body'       => $html,
		'highlights' => '',
	);

	if ( '' === $html ) {
		return $empty;
	}

	if ( false === strpos( $html, 'nh-important-notes' ) && false === strpos( $html, 'nh-mb-product-extra' ) ) {
		return $empty;
	}

	$pattern = '/<(div|section)\b[^>]*class=(["\'])[^"\']*\b(?:nh-important-notes|nh-mb-product-extra)\b[^"\']*\2[^>]*>/i';
	if ( ! preg_match_all( $pattern, $html, $matches, PREG_OFFSET_CAPTURE ) ) {
		return $empty;
	}

	$ranges = array();
	foreach ( $matches[0] as $match ) {
		$start = (int) $match[1];
		$range = nh_pcs_balanced_element_range( $html, $start );
		if ( null === $range ) {
			continue;
		}

		$inside = false;
		foreach ( $ranges as $existing ) {
			if ( $start > $existing[0] && $start < $existing[1] ) {
				$inside = true;
				break;
			}
		}
		if ( $inside ) {
			continue;
		}

		$ranges[] = $range;
	}

	if ( empty( $ranges ) ) {
		return $empty;
	}

	usort(
		$ranges,
		static function ( $a, $b ) {
			return $a[0] <=> $b[0];
		}
	);

	$highlights = '';
	foreach ( $ranges as $range ) {
		$highlights .= substr( $html, $range[0], $range[1] - $range[0] );
	}

	$body = $html;
	foreach ( array_reverse( $ranges ) as $range ) {
		$body = substr( $body, 0, $range[0] ) . substr( $body, $range[1] );
	}

	return array(
		'body'       => $body,
		'highlights' => $highlights,
	);
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
