<?php
// inc/delivery-time.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme textdomain used for translations.
 */
$nhdt_theme_textdomain = 'nh-theme';

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
 */
function nhdt_output_delivery_placeholder() {
	if ( ! is_product() ) return;
	global $product;
    if ( ! $product instanceof WC_Product ) return;

	$delivery_text = nhdt_get_delivery_text( $product );
	$style = $delivery_text === '' ? ' style="display:none;"' : '';
	global $nhdt_theme_textdomain;

	echo '<div class="nh-delivery-time-under" data-nhdt="1"' . $style . '>';
    
    // Clean SVG icon (inherits --nh-green from CSS)
    echo '<span class="nh-delivery-icon">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>';
    echo '</span>';

	echo '<span class="nh-delivery-time__label">' . esc_html__( 'Estimated delivery:', $nhdt_theme_textdomain ) . '</span>';
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
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$ver = '1.5.2';

	wp_enqueue_style(
		'nh-delivery-time',
		get_stylesheet_directory_uri() . '/assets/css/product-page.css',
		array(),
		$ver
	);

	wp_enqueue_script(
		'nh-delivery-time',
		get_stylesheet_directory_uri() . '/assets/js/delivery-time.js',
		array( 'jquery', 'wc-add-to-cart-variation' ),
		$ver,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'nhdt_enqueue_assets' );
