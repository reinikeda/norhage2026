<?php
/**
 * Brand line above the product title.
 *
 * The house brand (Norhage) is already in the header and in product meta,
 * so it is not repeated here. Supplier brands stay as one text link.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * House brand is identified by slug or name, so a translated term still matches.
 *
 * @param string $slug Term slug.
 * @param string $name Term name.
 */
function norhage_brand_is_house( $slug, $name ) : bool {
	$slug = strtolower( trim( (string) $slug ) );
	$name = strtolower( trim( wp_strip_all_tags( (string) $name ) ) );

	return 'norhage' === $slug || 'norhage' === $name;
}

/**
 * Compact brand link. Markup is escaped here.
 *
 * @param string $name Brand name.
 * @param string $url  Brand archive URL, or empty.
 */
function norhage_brand_link_html( $name, $url ) : string {
	$name = trim( wp_strip_all_tags( (string) $name ) );
	if ( '' === $name ) {
		return '';
	}

	$label = esc_html( $name );
	$aria  = esc_attr(
		sprintf(
			/* translators: %s: brand name */
			__( 'View products by %s', 'nh-theme' ),
			$name
		)
	);

	if ( $url ) {
		return sprintf(
			'<div class="norhage-product-brand"><a class="norhage-product-brand-link" href="%1$s" aria-label="%2$s">%3$s</a></div>',
			esc_url( $url ),
			$aria,
			$label
		);
	}

	return '<div class="norhage-product-brand"><span class="norhage-product-brand-link">' . $label . '</span></div>';
}

add_action( 'woocommerce_single_product_summary', 'norhage_output_product_brand_logo', 4 );

/**
 * First supplier brand above the title. Norhage prints nothing.
 */
function norhage_output_product_brand_logo() {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$brand_terms = wp_get_post_terms( $product->get_id(), 'product_brand' );
	if ( is_wp_error( $brand_terms ) || empty( $brand_terms ) ) {
		return;
	}

	foreach ( $brand_terms as $brand_term ) {
		if ( ! isset( $brand_term->name ) ) {
			continue;
		}

		$slug = isset( $brand_term->slug ) ? $brand_term->slug : '';
		if ( norhage_brand_is_house( $slug, $brand_term->name ) ) {
			continue;
		}

		$brand_url = '';
		if ( function_exists( 'get_term_link' ) ) {
			$link = get_term_link( $brand_term );
			if ( ! is_wp_error( $link ) && is_string( $link ) ) {
				$brand_url = $link;
			}
		}

		$html = norhage_brand_link_html( $brand_term->name, $brand_url );
		if ( '' === $html ) {
			continue;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in norhage_brand_link_html.
		return;
	}
}
