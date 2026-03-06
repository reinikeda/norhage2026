<?php
/**
 * Plugin Name: Delivery Time Under Price
 * Description: Shows delivery time (from a product attribute) directly under the main product price on single product pages.
 * Author: Daiva Reinike
 * Version: 1.5.2
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
 * @return string|null
 */
function nhdt_get_lang_key() {
	if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
		return ICL_LANGUAGE_CODE;
	}

	if ( function_exists( 'pll_current_language' ) ) {
		$pll = pll_current_language( 'slug' );
		if ( $pll ) {
			return $pll;
		}
	}

	$locale = get_locale();
	return $locale ? $locale : null;
}

/**
 * Get attribute slug used to store delivery time.
 *
 * @return string
 */
function nhdt_get_attribute_slug() {
	$default_slug = 'pa_delivery-time';
	$lang_key     = nhdt_get_lang_key();

	$slug_map = array(
		'lt'    => 'pa_pristatymo-laikas',
		'lt_LT' => 'pa_pristatymo-laikas',

		'nb'    => 'pa_leveringstid',
		'nb_NO' => 'pa_leveringstid',

		'sv'    => 'pa_leveranstid',
		'sv_SE' => 'pa_leveranstid',

		'de'    => 'pa_lieferzeit',
		'de_DE' => 'pa_lieferzeit',

		'fi'    => 'pa_toimitusaika',
		'fi_FI' => 'pa_toimitusaika',
	);

	$slug = $default_slug;

	if ( $lang_key && isset( $slug_map[ $lang_key ] ) ) {
		$slug = $slug_map[ $lang_key ];
	}

	return apply_filters( 'nhdt_attribute_slug', $slug, $lang_key );
}

/**
 * Get delivery time text for a product.
 *
 * @param WC_Product $product
 * @return string
 */
function nhdt_get_delivery_text( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	$attr_slug = nhdt_get_attribute_slug();
	$value     = $product->get_attribute( $attr_slug );

	if ( ! $value ) {
		return '';
	}

	return trim( wp_strip_all_tags( $value ) );
}

/**
 * Output hidden/normal delivery block into summary.
 * JS will move it right after the actual .price element.
 */
function nhdt_output_delivery_placeholder() {
	if ( ! is_product() ) {
		return;
	}

	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$main_product_id = (int) get_queried_object_id();
	if ( $main_product_id <= 0 || (int) $product->get_id() !== $main_product_id ) {
		return;
	}

	$delivery_text = nhdt_get_delivery_text( $product );
	$style         = $delivery_text === '' ? ' style="display:none;"' : '';

	echo '<div class="nh-delivery-time-under" data-nhdt="1"' . $style . '>';
	echo '<span class="nh-delivery-time__label">' . esc_html__( 'Estimated delivery:', 'nh-delivery-time' ) . '</span> ';
	echo '<span class="nh-delivery-time__value">' . esc_html( $delivery_text ) . '</span>';
	echo '</div>';
}
add_action( 'woocommerce_single_product_summary', 'nhdt_output_delivery_placeholder', 31 );

/**
 * Add delivery text to variation data.
 */
function nhdt_add_variation_delivery_data( $data, $product, $variation ) {
	if ( ! $variation instanceof WC_Product_Variation ) {
		return $data;
	}

	$delivery_text = nhdt_get_delivery_text( $variation );

	if ( $delivery_text === '' && $product instanceof WC_Product ) {
		$delivery_text = nhdt_get_delivery_text( $product );
	}

	$data['nh_delivery_text'] = $delivery_text;

	return $data;
}
add_filter( 'woocommerce_available_variation', 'nhdt_add_variation_delivery_data', 10, 3 );

/**
 * Enqueue assets on single product pages only.
 */
function nhdt_enqueue_assets() {
	if ( ! is_product() ) {
		return;
	}

	wp_enqueue_style(
		'nh-delivery-time',
		plugin_dir_url( __FILE__ ) . 'assets/css/nh-delivery-time.css',
		array(),
		'1.5.2'
	);

	wp_enqueue_script(
		'nh-delivery-time',
		plugin_dir_url( __FILE__ ) . 'assets/js/nh-delivery-time.js',
		array( 'jquery', 'wc-add-to-cart-variation' ),
		'1.5.2',
		true
	);
}
add_action( 'wp_enqueue_scripts', 'nhdt_enqueue_assets' );
