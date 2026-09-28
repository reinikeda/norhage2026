<?php
/**
 * Category cards open the product page. One short label for every product.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Catalog button label.
 *
 * Theme translations win. If the .mo file has not been rebuilt yet, the shop
 * locales still resolve.
 *
 * @return string
 */
function nh_catalog_button_label() {
	$translated = __( 'Select', 'nh-theme' );
	if ( is_string( $translated ) && '' !== $translated && 'Select' !== $translated ) {
		return $translated;
	}

	$locale = '';
	if ( function_exists( 'determine_locale' ) ) {
		$locale = (string) determine_locale();
	} elseif ( function_exists( 'get_locale' ) ) {
		$locale = (string) get_locale();
	}

	$map = array(
		'nb_NO' => 'Velg',
		'nn_NO' => 'Velg',
		'sv_SE' => 'Välj',
		'da_DK' => 'Vælg',
		'de_DE' => 'Auswählen',
		'de_AT' => 'Auswählen',
		'de_CH' => 'Auswählen',
		'fi'    => 'Valitse',
		'fi_FI' => 'Valitse',
		'lt_LT' => 'Pasirinkti',
	);

	if ( isset( $map[ $locale ] ) ) {
		return $map[ $locale ];
	}

	$lang = strtolower( substr( $locale, 0, 2 ) );
	foreach ( $map as $code => $label ) {
		if ( strtolower( substr( $code, 0, 2 ) ) === $lang ) {
			return $label;
		}
	}

	return 'Select';
}

/**
 * Every catalog button opens the product. Simple products are not added from the card.
 *
 * @param string     $html    Button HTML.
 * @param WC_Product $product Product.
 * @param array      $args    Loop button args.
 * @return string
 */
function nh_catalog_button_link( $html, $product, $args ) {
	if ( ! $product instanceof WC_Product ) {
		return $html;
	}

	$class = isset( $args['class'] ) ? $args['class'] : 'button';

	return sprintf(
		'<a href="%s" class="%s">%s</a>',
		esc_url( $product->get_permalink() ),
		esc_attr( $class ),
		esc_html( nh_catalog_button_label() )
	);
}
add_filter( 'woocommerce_loop_add_to_cart_link', 'nh_catalog_button_link', 999, 3 );
