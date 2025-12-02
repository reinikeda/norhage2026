<?php
/**
 * Plugin Name: Norhage – Delivery Time Under Price
 * Description: Appends delivery time (from a product attribute) just under the price on single product pages.
 * Author: Norhage 2026
 * Version: 1.3.1
 * Text Domain: nh-delivery-time
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load text domain for translations.
 */
function nhdt_load_textdomain() {
	load_plugin_textdomain(
		'nh-delivery-time',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'nhdt_load_textdomain' );

/**
 * Get attribute slug used to store delivery time.
 *
 * Default is "pa_delivery-time". For multilingual setups we map
 * by language code (WPML / Polylang) and still allow overriding via filter.
 *
 * @return string
 */
function nhdt_get_attribute_slug() {
	// Fallback / single-language default (your main attribute).
	$default_slug = 'pa_delivery-time';

	// Detect current language.
	$lang = null;

	// WPML.
	if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
		$lang = ICL_LANGUAGE_CODE; // e.g. 'lt', 'nb', 'sv', 'de', 'fi'
	}
	// Polylang.
	elseif ( function_exists( 'pll_current_language' ) ) {
		$lang = pll_current_language( 'slug' ); // usually same codes
	}

	// Map of language => attribute taxonomy slug.
	// Make sure these exist as product attributes in WooCommerce.
	$slug_map = array(
		'lt' => 'pa_pristatymo-laikas',
		'nb' => 'pa_leveringstid',
		'sv' => 'pa_leveranstid',
		'de' => 'pa_lieferzeit',
		'fi' => 'pa_toimitusaika',
	);

	$slug = $default_slug;

	if ( $lang && isset( $slug_map[ $lang ] ) ) {
		$slug = $slug_map[ $lang ];
	}

	/**
	 * Filter the attribute slug used to fetch the delivery time.
	 *
	 * @param string $slug Resolved slug, e.g. 'pa_delivery-time'.
	 * @param string $lang Language code, e.g. 'lt', 'nb', 'sv', 'de', 'fi'.
	 */
	return apply_filters( 'nhdt_attribute_slug', $slug, $lang );
}

/**
 * Get delivery time text for a product (or empty string if none).
 *
 * @param WC_Product $product
 * @return string
 */
function nhdt_get_delivery_text( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	$attr_slug = nhdt_get_attribute_slug();

	// Attribute text, e.g. "1–3 weeks".
	$delivery_attr = $product->get_attribute( $attr_slug );

	if ( ! $delivery_attr ) {
		return '';
	}

	$text = trim( wp_strip_all_tags( $delivery_attr ) );

	return $text;
}

/**
 * Append delivery time under price, inside the same <p class="price">.
 *
 * @param string     $price_html
 * @param WC_Product $product
 *
 * @return string
 */
function nhdt_append_delivery_to_price_html( $price_html, $product ) {
	// Only on single product page.
	if ( ! is_product() ) {
		return $price_html;
	}

	$delivery_text = nhdt_get_delivery_text( $product );

	if ( $delivery_text === '' ) {
		return $price_html;
	}

	$delivery_html  = '<span class="nh-delivery-time-under">';
	$delivery_html .= '<span class="nh-delivery-time__label">' . esc_html__( 'Estimated delivery:', 'nh-delivery-time' ) . '</span> ';
	$delivery_html .= '<span class="nh-delivery-time__value">' . esc_html( $delivery_text ) . '</span>';
	$delivery_html .= '</span>';

	// Price + delivery.
	return $price_html . $delivery_html;
}
add_filter( 'woocommerce_get_price_html', 'nhdt_append_delivery_to_price_html', 20, 2 );

/**
 * Enqueue plugin CSS (only on single product pages).
 */
function nhdt_enqueue_styles() {
	if ( ! is_product() ) {
		return;
	}

	wp_enqueue_style(
		'nh-delivery-time',
		plugin_dir_url( __FILE__ ) . 'assets/css/nh-delivery-time.css',
		array(),
		'1.3.1'
	);
}
add_action( 'wp_enqueue_scripts', 'nhdt_enqueue_styles' );
