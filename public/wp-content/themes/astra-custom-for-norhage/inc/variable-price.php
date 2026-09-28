<?php
/**
 * Variable products: show "From {lowest price}" instead of a min–max range.
 *
 * Category cards and the single product summary both use
 * WC_Product_Variable::get_price_html(). The product page keeps "From" until
 * a variation is selected, then nh-variable-price.js swaps in that variation's
 * price. Woo's own variation price is hidden by the theme.
 *
 * Offer JSON-LD is not touched. WooCommerce and Yoast read
 * get_variation_prices() / get_price() for lowPrice, highPrice, and price.
 * Those stay numeric. This file only filters the display HTML.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether two variation prices are different amounts.
 *
 * @param mixed $min Lowest active price.
 * @param mixed $max Highest active price.
 * @return bool
 */
function nh_variable_prices_differ( $min, $max ) {
	if ( is_numeric( $min ) && is_numeric( $max ) ) {
		return (float) $min !== (float) $max;
	}

	return (string) $min !== (string) $max;
}

/**
 * Label in front of the lowest price.
 *
 * WooCommerce already translates "From:" (context min_price) in its language
 * packs. If that string is missing, the theme translation is used. If neither
 * catalog is loaded, the shop locales still resolve: Fra, Från, Ab, Alkaen, Nuo.
 *
 * @return string
 */
function nh_variable_price_from_label() {
	$woo = _x( 'From:', 'min_price', 'woocommerce' );
	if ( is_string( $woo ) && '' !== $woo && 'From:' !== $woo ) {
		return $woo;
	}

	$theme = _x( 'From:', 'min_price', 'nh-theme' );
	if ( is_string( $theme ) && '' !== $theme && 'From:' !== $theme ) {
		return $theme;
	}

	$locale = '';
	if ( function_exists( 'determine_locale' ) ) {
		$locale = (string) determine_locale();
	} elseif ( function_exists( 'get_locale' ) ) {
		$locale = (string) get_locale();
	}

	$map = array(
		'nb_NO' => 'Fra:',
		'nn_NO' => 'Frå:',
		'sv_SE' => 'Från:',
		'da_DK' => 'Fra:',
		'de_DE' => 'Ab:',
		'de_AT' => 'Ab:',
		'de_CH' => 'Ab:',
		'fi'    => 'Alkaen:',
		'fi_FI' => 'Alkaen:',
		'lt_LT' => 'Nuo:',
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

	return 'From:';
}

/**
 * Replace a variable price range with the lowest price.
 *
 * Equal min and max keep Woo's original HTML, including a single sale price.
 * A second pass does not add another "From".
 *
 * @param mixed  $min            Lowest active price.
 * @param mixed  $max            Highest active price.
 * @param string $formatted_min  Lowest price, already passed through wc_price().
 * @param string $suffix         WooCommerce price suffix for that amount.
 * @param string $original_html  Range HTML WooCommerce built.
 * @param string $from_label     Translated prefix, without HTML.
 * @return string
 */
function nh_format_variable_from_price( $min, $max, $formatted_min, $suffix, $original_html, $from_label ) {
	if ( ! is_string( $original_html ) ) {
		return '';
	}

	if ( ! nh_variable_prices_differ( $min, $max ) || ! is_string( $formatted_min ) || '' === $formatted_min ) {
		return $original_html;
	}

	if ( false !== strpos( $original_html, 'class="from"' ) ) {
		return $original_html;
	}

	$label = trim( wp_strip_all_tags( (string) $from_label ) );
	if ( '' === $label ) {
		$label = 'From:';
	}

	$suffix = is_string( $suffix ) ? $suffix : '';

	return '<span class="from">' . esc_html( $label ) . ' </span>' . $formatted_min . $suffix;
}

/**
 * Display filter for variable product price HTML.
 *
 * Runs before the tax-switcher wrapper (priority 999) and before the
 * custom-cut unit suffix on woocommerce_get_price_html.
 *
 * @param string     $price_html Range HTML, already including the Woo suffix.
 * @param WC_Product $product    Variable product.
 * @return string
 */
function nh_variable_price_from_html( $price_html, $product ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $price_html;
	}

	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
		return $price_html;
	}

	$prices = $product->get_variation_prices( true );
	if ( empty( $prices['price'] ) || ! is_array( $prices['price'] ) ) {
		return $price_html;
	}

	$min = current( $prices['price'] );
	$max = end( $prices['price'] );

	$formatted = function_exists( 'wc_price' ) ? wc_price( $min ) : '';
	$suffix    = is_callable( array( $product, 'get_price_suffix' ) ) ? $product->get_price_suffix( $min ) : '';

	return nh_format_variable_from_price(
		$min,
		$max,
		$formatted,
		$suffix,
		$price_html,
		nh_variable_price_from_label()
	);
}
add_filter( 'woocommerce_variable_price_html', 'nh_variable_price_from_html', 20, 2 );
add_filter( 'woocommerce_variable_sale_price_html', 'nh_variable_price_from_html', 20, 2 );

/**
 * On the product page, replace "From" with the selected variation price.
 */
function nh_variable_price_assets() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_queried_object_id() ) : null;
	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
		return;
	}

	$relative = '/assets/js/nh-variable-price.js';
	$args     = function_exists( 'norhage_script_args' ) ? norhage_script_args() : true;

	wp_enqueue_script(
		'nh-variable-price',
		get_stylesheet_directory_uri() . $relative,
		array( 'jquery', 'wc-add-to-cart-variation' ),
		function_exists( 'norhage_asset_version' ) ? norhage_asset_version( $relative ) : null,
		$args
	);
}
add_action( 'wp_enqueue_scripts', 'nh_variable_price_assets', 30 );
