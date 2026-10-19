<?php
// inc/delivery-time.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
 * Language-specific WooCommerce attribute slugs for delivery time.
 * English default is pa_delivery-time; other shops often translate the attribute.
 *
 * @return array<string, string>
 */
function nhdt_attribute_slug_map() {
	return array(
		'lt'    => 'pa_pristatymo-laikas',
		'lt_LT' => 'pa_pristatymo-laikas',

		'nb'    => 'pa_leveringstid',
		'nb_NO' => 'pa_leveringstid',

		'nn'    => 'pa_leveringstid',
		'nn_NO' => 'pa_leveringstid',

		'da'    => 'pa_leveringstid',
		'da_DK' => 'pa_leveringstid',

		'sv'    => 'pa_leveranstid',
		'sv_SE' => 'pa_leveranstid',

		'de'    => 'pa_lieferzeit',
		'de_DE' => 'pa_lieferzeit',
		'de_AT' => 'pa_lieferzeit',
		'de_CH' => 'pa_lieferzeit',

		'fi'    => 'pa_toimitusaika',
		'fi_FI' => 'pa_toimitusaika',
	);
}

/**
 * Get attribute slug used to store delivery time for the current shop language.
 *
 * @return string
 */
function nhdt_get_attribute_slug() {
	$default_slug = 'pa_delivery-time';
	$lang_key     = nhdt_get_lang_key();
	$slug_map     = nhdt_attribute_slug_map();
	$slug         = $default_slug;

	if ( $lang_key && isset( $slug_map[ $lang_key ] ) ) {
		$slug = $slug_map[ $lang_key ];
	}

	return apply_filters( 'nhdt_attribute_slug', $slug, $lang_key );
}

/**
 * True when an attribute name/slug is a delivery-time field in any shop language.
 *
 * @param string $name Attribute taxonomy or label.
 * @return bool
 */
function nhdt_is_delivery_attribute_name( $name ) {
	$n = strtolower( str_replace( array( 'pa_', '_' ), array( '', '-' ), (string) $name ) );
	$needles = array(
		'delivery-time',
		'deliverytime',
		'leveringstid',
		'leveranstid',
		'lieferzeit',
		'toimitusaika',
		'pristatymo-laikas',
	);
	foreach ( $needles as $needle ) {
		if ( $n === $needle || strpos( $n, $needle ) !== false ) {
			return true;
		}
	}
	return false;
}

/**
 * Attribute slugs to read, current language first, then English, then the rest.
 *
 * @param object|null $product Product that may expose extra attribute names.
 * @return array<int, string>
 */
function nhdt_attribute_slugs_to_try( $product = null ) {
	$slugs = array( nhdt_get_attribute_slug(), 'pa_delivery-time' );
	foreach ( nhdt_attribute_slug_map() as $slug ) {
		$slugs[] = $slug;
	}

	if ( is_object( $product ) && is_callable( array( $product, 'get_attributes' ) ) ) {
		$attrs = $product->get_attributes();
		if ( is_array( $attrs ) ) {
			foreach ( $attrs as $key => $attr ) {
				$name = is_string( $key ) ? $key : '';
				if ( is_object( $attr ) && is_callable( array( $attr, 'get_name' ) ) ) {
					$name = (string) $attr->get_name();
				} elseif ( is_string( $attr ) && $name === '' ) {
					$name = $attr;
				}
				if ( $name !== '' && nhdt_is_delivery_attribute_name( $name ) ) {
					$slugs[] = $name;
				}
			}
		}
	}

	$slugs = array_values( array_unique( array_filter( $slugs ) ) );
	return apply_filters( 'nhdt_attribute_slugs', $slugs, $product );
}

/**
 * Get delivery time text for a product.
 *
 * @param mixed $product Product-like object with get_attribute().
 * @return string
 */
function nhdt_get_delivery_text( $product ) {
	if ( ! is_object( $product ) || ! is_callable( array( $product, 'get_attribute' ) ) ) {
		return '';
	}

	foreach ( nhdt_attribute_slugs_to_try( $product ) as $slug ) {
		$value = trim( wp_strip_all_tags( (string) $product->get_attribute( $slug ) ) );
		if ( $value !== '' ) {
			return $value;
		}
	}

	return '';
}

/**
 * Sortable length in days for a delivery-time string. Uses the largest number
 * in a range ("2-4 weeks" → 28). Unparsed text sorts as 0.
 *
 * @param string $text Attribute term.
 * @return float
 */
function nhdt_delivery_sort_key( $text ) {
	$t = strtolower( (string) $text );
	if ( $t === '' ) {
		return 0;
	}

	$multiplier = 1;
	if ( preg_match( '/week|uke|uger|veck|viikko|woche|savait/u', $t ) ) {
		$multiplier = 7;
	} elseif ( preg_match( '/month|m[aåä]ned|m[aå]nad|kuukaus|monat|m[eė]n/u', $t ) ) {
		$multiplier = 30;
	}

	if ( ! preg_match_all( '/\d+(?:[.,]\d+)?/', $t, $matches ) ) {
		return 0;
	}

	$nums = array();
	foreach ( $matches[0] as $raw ) {
		$nums[] = (float) str_replace( ',', '.', $raw );
	}

	return empty( $nums ) ? 0 : max( $nums ) * $multiplier;
}

/**
 * Delivery text for one cart line, including variation → parent fallback.
 *
 * @param array $cart_item Cart item.
 * @return string
 */
function nhdt_get_cart_item_delivery_text( $cart_item ) {
	$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
	$text    = nhdt_get_delivery_text( $product );
	if ( $text !== '' ) {
		return $text;
	}

	if ( is_object( $product ) && is_callable( array( $product, 'get_parent_id' ) ) ) {
		$parent_id = (int) $product->get_parent_id();
		if ( $parent_id > 0 && function_exists( 'wc_get_product' ) ) {
			return nhdt_get_delivery_text( wc_get_product( $parent_id ) );
		}
	}

	return '';
}

/**
 * Longest delivery estimate among cart items. Empty when no line has the attribute.
 *
 * @param object|null $cart Cart with get_cart().
 * @return string
 */
function nhdt_get_cart_estimated_delivery( $cart = null ) {
	if ( $cart === null && function_exists( 'WC' ) && WC() && WC()->cart ) {
		$cart = WC()->cart;
	}
	if ( ! is_object( $cart ) || ! is_callable( array( $cart, 'get_cart' ) ) ) {
		return '';
	}

	$best_text = '';
	$best_key  = -1.0;
	foreach ( $cart->get_cart() as $item ) {
		$text = nhdt_get_cart_item_delivery_text( is_array( $item ) ? $item : array() );
		if ( $text === '' ) {
			continue;
		}
		$key = nhdt_delivery_sort_key( $text );
		if ( $key > $best_key ) {
			$best_key  = $key;
			$best_text = $text;
		}
	}

	return $best_text;
}

/**
 * Muted "Estimated delivery: {value}" for side cart (not a totals table).
 *
 * @return string
 */
function nhdt_cart_estimate_markup() {
	$text = nhdt_get_cart_estimated_delivery();
	if ( $text === '' ) {
		return '';
	}

	return '<p class="nh-cart-delivery">'
		. '<span class="nh-cart-delivery__label">' . esc_html__( 'Estimated delivery:', 'nh-theme' ) . '</span> '
		. '<span class="nh-cart-delivery__value">' . esc_html( $text ) . '</span>'
		. '</p>';
}

/**
 * @return void
 */
function nhdt_print_cart_estimate() {
	echo nhdt_cart_estimate_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Basket / checkout totals table row, just above the order total.
 *
 * @return void
 */
function nhdt_print_totals_estimate_row() {
	$text = nhdt_get_cart_estimated_delivery();
	if ( $text === '' ) {
		return;
	}

	$label = __( 'Estimated delivery:', 'nh-theme' );
	echo '<tr class="nh-cart-delivery-row">';
	echo '<th>' . esc_html( $label ) . '</th>';
	echo '<td data-title="' . esc_attr( $label ) . '">' . esc_html( $text ) . '</td>';
	echo '</tr>';
}
add_action( 'woocommerce_cart_totals_before_order_total', 'nhdt_print_totals_estimate_row' );
add_action( 'woocommerce_review_order_before_order_total', 'nhdt_print_totals_estimate_row' );

/**
 * Output hidden/normal delivery block into summary.
 * JS will move it right after the actual .price element.
 */
function nhdt_output_delivery_placeholder() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
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

	// SVG icon (inherits color via CSS)
	echo '<span class="nh-delivery-icon" aria-hidden="true">';
	echo '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>';
	echo '</span>';

	echo '<span class="nh-delivery-time__label">' . esc_html__( 'Estimated delivery:', 'nh-theme' ) . '</span> ';
	echo '<span class="nh-delivery-time__value">' . esc_html( $delivery_text ) . '</span>';

	echo '</div>';
}
add_action( 'woocommerce_single_product_summary', 'nhdt_output_delivery_placeholder', 31 );

/**
 * Add delivery text to variation data so JS can update when variations change.
 */
function nhdt_add_variation_delivery_data( $data, $product, $variation ) {
	if ( ! $variation instanceof WC_Product_Variation ) {
		return $data;
	}

	$delivery_text = nhdt_get_delivery_text( $variation );

	if ( $delivery_text === '' && is_object( $product ) ) {
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

	$ver = norhage_asset_version( '/assets/js/delivery-time.js' );

	wp_enqueue_script(
		'nh-delivery-time',
		get_stylesheet_directory_uri() . '/assets/js/delivery-time.js',
		array( 'jquery', 'wc-add-to-cart-variation' ),
		$ver,
		true
	);
}

add_action( 'wp_enqueue_scripts', 'nhdt_enqueue_assets' );
