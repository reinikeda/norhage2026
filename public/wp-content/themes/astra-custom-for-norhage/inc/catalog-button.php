<?php
/**
 * Category cards use an Add to cart label and always open the product page.
 * Variable and custom-cut products need the page to choose options; simple
 * products open it too so customers see details before buying.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce "Add to cart" in the shop language, with a short Finnish form.
 *
 * @return string
 */
function nh_catalog_button_label() {
	$translated = __( 'Add to cart', 'woocommerce' );
	if ( function_exists( 'nh_shorten_finnish_add_to_cart_text' ) ) {
		$translated = nh_shorten_finnish_add_to_cart_text( $translated );
	}
	if ( is_string( $translated ) && '' !== $translated && 'Add to cart' !== $translated ) {
		return $translated;
	}

	$locale = '';
	if ( function_exists( 'determine_locale' ) ) {
		$locale = (string) determine_locale();
	} elseif ( function_exists( 'get_locale' ) ) {
		$locale = (string) get_locale();
	}

	$map = array(
		'nb_NO' => 'Legg i handlekurv',
		'nn_NO' => 'Legg i handlekurv',
		'sv_SE' => 'Lägg i varukorg',
		'da_DK' => 'Læg i kurv',
		'de_DE' => 'In den Warenkorb',
		'de_AT' => 'In den Warenkorb',
		'de_CH' => 'In den Warenkorb',
		'fi'    => 'Lisää koriin',
		'fi_FI' => 'Lisää koriin',
		'lt_LT' => 'Į krepšelį',
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

	return 'Add to cart';
}

/**
 * Catalog cards never add from the listing.
 * Variable and custom-cut products need the page to customise; simple
 * products open it too so customers see details first.
 *
 * @param mixed $product Product.
 * @return bool
 */
function nh_catalog_product_opens_page( $product = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	return true;
}

/**
 * Every catalog button opens the product page.
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
