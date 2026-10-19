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
 * Short catalog "Add to cart" labels that fit one line on a product card.
 *
 * @return array<string, string>
 */
function nh_catalog_button_label_map() {
	return array(
		'nb_NO' => 'Legg i kurv',
		'nn_NO' => 'Legg i kurv',
		'sv_SE' => 'Lägg i korg',
		'da_DK' => 'Læg i kurv',
		'de_DE' => 'In den Korb',
		'de_AT' => 'In den Korb',
		'de_CH' => 'In den Korb',
		'fi'    => 'Lisää koriin',
		'fi_FI' => 'Lisää koriin',
		'lt_LT' => 'Į krepšelį',
	);
}

/**
 * Woo’s official strings are too long for catalog cards. Keep the meaning.
 *
 * @param string $text Label.
 * @return string
 */
function nh_catalog_shorten_add_to_cart_label( $text ) {
	$long = array(
		'Legg i handlekurv'  => 'Legg i kurv',
		'Lägg i varukorg'    => 'Lägg i korg',
		'In den Warenkorb'   => 'In den Korb',
		'Lisää ostoskoriin'  => 'Lisää koriin',
	);
	return isset( $long[ $text ] ) ? $long[ $text ] : $text;
}

/**
 * One-line Add to cart label for the current shop language.
 *
 * @return string
 */
function nh_catalog_button_label() {
	$translated = __( 'Add to cart', 'woocommerce' );
	if ( function_exists( 'nh_shorten_finnish_add_to_cart_text' ) ) {
		$translated = nh_shorten_finnish_add_to_cart_text( $translated );
	}
	$translated = nh_catalog_shorten_add_to_cart_label( is_string( $translated ) ? $translated : '' );

	$locale = '';
	if ( function_exists( 'determine_locale' ) ) {
		$locale = (string) determine_locale();
	} elseif ( function_exists( 'get_locale' ) ) {
		$locale = (string) get_locale();
	}

	$map = nh_catalog_button_label_map();
	if ( $translated !== '' && $translated !== 'Add to cart' ) {
		return $translated;
	}

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
 * Same cart glyph as the header, sized for the catalog button.
 *
 * @return string
 */
function nh_catalog_button_icon_html() {
	return '<svg class="nh-catalog-atc__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">'
		. '<circle cx="9" cy="21" r="1" stroke-width="2" fill="none" stroke="currentColor"></circle>'
		. '<circle cx="19" cy="21" r="1" stroke-width="2" fill="none" stroke="currentColor"></circle>'
		. '<path d="M2 3h3l3.6 12.6a2 2 0 0 0 2 1.4h7.8a2 2 0 0 0 2-1.6l1.5-8.4H6" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"></path>'
		. '</svg>';
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
	if ( strpos( ' ' . $class . ' ', ' nh-catalog-atc ' ) === false ) {
		$class .= ' nh-catalog-atc';
	}

	return sprintf(
		'<a href="%s" class="%s">%s<span class="nh-catalog-atc__label">%s</span></a>',
		esc_url( $product->get_permalink() ),
		esc_attr( $class ),
		nh_catalog_button_icon_html(),
		esc_html( nh_catalog_button_label() )
	);
}
add_filter( 'woocommerce_loop_add_to_cart_link', 'nh_catalog_button_link', 999, 3 );
