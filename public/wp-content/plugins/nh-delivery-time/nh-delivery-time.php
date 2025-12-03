<?php
/**
 * Plugin Name: Delivery Time Under Price
 * Description: Appends delivery time (from a product attribute) just under the price on single product pages.
 * Author: Daiva Reinike
 * Version: 1.4.0
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
 * Resolve current language / locale key.
 *
 * Priority:
 *  - WPML: ICL_LANGUAGE_CODE (lt, nb, sv, de, fi, …)
 *  - Polylang: pll_current_language('slug')
 *  - Fallback: get_locale() (lt_LT, nb_NO, sv_SE, de_DE, fi_FI, en_US, …)
 *
 * @return string|null
 */
function nhdt_get_lang_key() {
	// WPML.
	if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
		return ICL_LANGUAGE_CODE;
	}

	// Polylang.
	if ( function_exists( 'pll_current_language' ) ) {
		$pll = pll_current_language( 'slug' );
		if ( $pll ) {
			return $pll;
		}
	}

	// Fallback: plain WP locale (WooCommerce language setting).
	$locale = get_locale();
	return $locale ? $locale : null;
}

/**
 * Get attribute slug used to store delivery time.
 *
 * Default is English "delivery-time" (taxonomy "pa_delivery-time").
 * For other languages we map based on language code / locale,
 * but still allow overriding via the nhdt_attribute_slug filter.
 *
 * @return string
 */
function nhdt_get_attribute_slug() {
	// Fallback / default: English attribute "Delivery time" (slug "delivery-time").
	$default_slug = 'pa_delivery-time';

	// What language / locale are we in?
	$lang_key = nhdt_get_lang_key();

	/**
	 * Map of language/locale -> attribute taxonomy slug.
	 *
	 * IMPORTANT: these slugs must match the *actual* WooCommerce attribute slugs
	 * you have created in Products → Attributes.
	 *
	 * Examples (change if you use different slugs):
	 *   - LT attribute "Pristatymo laikas"  -> pristatymo-laikas
	 *   - NO attribute "Leveringstid"       -> leveringstid
	 *   - SV attribute "Leveranstid"        -> leveranstid
	 *   - DE attribute "Lieferzeit"         -> lieferzeit
	 *   - FI attribute "Toimitusaika"       -> toimitusaika
	 */
	$slug_map = array(
		// Lithuanian.
		'lt'    => 'pa_pristatymo-laikas',
		'lt_LT' => 'pa_pristatymo-laikas',

		// Norwegian Bokmål / generic / Nynorsk.
		'nb'    => 'pa_leveringstid',
		'nb_NO' => 'pa_leveringstid',

		// Swedish.
		'sv'    => 'pa_leveranstid',
		'sv_SE' => 'pa_leveranstid',

		// German.
		'de'    => 'pa_lieferzeit',
		'de_DE' => 'pa_lieferzeit',

		// Finnish.
		'fi'    => 'pa_toimitusaika',
		'fi_FI' => 'pa_toimitusaika',
	);

	$slug = $default_slug;

	if ( $lang_key && isset( $slug_map[ $lang_key ] ) ) {
		$slug = $slug_map[ $lang_key ];
	}

	/**
	 * Filter the attribute slug used to fetch the delivery time.
	 *
	 * @param string      $slug     Resolved slug, e.g. 'pa_delivery-time' or 'pa_pristatymo-laikas'.
	 * @param string|null $lang_key Language/locale key, e.g. 'lt', 'lt_LT', 'sv_SE', etc.
	 */
	return apply_filters( 'nhdt_attribute_slug', $slug, $lang_key );
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

	// Attribute text, e.g. "1–3 weeks" / "2–4 savaitės".
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
		'1.4.0'
	);
}
add_action( 'wp_enqueue_scripts', 'nhdt_enqueue_styles' );
