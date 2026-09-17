<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Woo/Astra mark catalog add-to-cart controls as rel="nofollow".
 * Those links now go to the product page, so nofollow is leftover
 * and site audits flag it as blocked internal linking.
 *
 * @param string $html
 * @return string
 */
function nh_strip_rel_nofollow( $html ) {
	if ( ! is_string( $html ) || $html === '' ) {
		return $html;
	}

	return str_replace(
		array( ' rel="nofollow"', " rel='nofollow'", ' rel=nofollow' ),
		'',
		$html
	);
}

/**
 * Point Astra's on-image bag button at the product permalink.
 *
 * For simple products Woo's add_to_cart_url() is the current archive URL plus
 * ?add-to-cart=ID. Crawlers then hit category/?add-to-cart=627 and Woo 302s.
 *
 * @param string $html
 * @param string $permalink
 * @return string
 */
function nh_rewrite_on_card_button_href( $html, $permalink ) {
	if ( ! is_string( $html ) || $html === '' || strpos( $html, 'ast-on-card-button' ) === false ) {
		return $html;
	}

	$permalink = (string) $permalink;
	if ( $permalink === '' ) {
		return $html;
	}

	return preg_replace_callback(
		'/<a\b[^>]*\bast-on-card-button\b[^>]*>/i',
		static function ( $match ) use ( $permalink ) {
			$tag = $match[0];
			return preg_replace_callback(
				'/\bhref=(["\'])[^"\']*\1/',
				static function ( $href ) use ( $permalink ) {
					return 'href=' . $href[1] . $permalink . $href[1];
				},
				$tag,
				1
			);
		},
		$html
	);
}

function nh_filter_astra_shop_card_buttons_html( $html, $product = null ) {
	$html = nh_strip_rel_nofollow( $html );
	if ( $product instanceof WC_Product ) {
		$html = nh_rewrite_on_card_button_href( $html, $product->get_permalink() );
	}
	return $html;
}

/**
 * Simple/grouped add-to-cart URLs are the current page + ?add-to-cart=ID.
 * Catalog should never emit those; send shoppers to the product instead.
 *
 * @param string     $url
 * @param WC_Product $product
 * @return string
 */
function nh_catalog_add_to_cart_url( $url, $product ) {
	if ( ! $product instanceof WC_Product ) {
		return $url;
	}
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $url;
	}
	if ( $product->is_type( 'external' ) ) {
		return $url;
	}
	if ( $product->is_type( 'simple' ) || $product->is_type( 'grouped' ) ) {
		$permalink = $product->get_permalink();
		if ( $permalink ) {
			return $permalink;
		}
	}
	return $url;
}

add_filter( 'woocommerce_loop_add_to_cart_link', 'nh_strip_rel_nofollow', 9999 );

add_filter( 'woocommerce_loop_add_to_cart_args', function( $args ) {
	if ( isset( $args['attributes']['rel'] ) && $args['attributes']['rel'] === 'nofollow' ) {
		unset( $args['attributes']['rel'] );
	}
	return $args;
}, 99 );

add_filter( 'astra_addon_shop_cards_buttons_html', 'nh_filter_astra_shop_card_buttons_html', 20, 2 );

add_filter( 'woocommerce_product_add_to_cart_url', 'nh_catalog_add_to_cart_url', 20, 2 );
