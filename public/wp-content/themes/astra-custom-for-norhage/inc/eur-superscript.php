<?php
/**
 * EUR storefront prices: raise the cents on catalog and product pages.
 *
 * A price of 10.49 is shown as 10,<sup>49</sup> €. Cart, checkout, order
 * emails and invoices stay 10,49 €. The amount itself is unchanged.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether this shop charges in euro.
 *
 * @param string $currency Currency code. Empty uses the active shop currency.
 * @return bool
 */
function nh_eur_currency_is_euro( $currency = '' ) {
	if ( '' === $currency && function_exists( 'get_woocommerce_currency' ) ) {
		$currency = get_woocommerce_currency();
	}

	return 'EUR' === strtoupper( (string) $currency );
}

/**
 * Cart and checkout requests keep the normal 10,49 € format.
 *
 * @return bool
 */
function nh_eur_request_is_cart_or_checkout() {
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		return true;
	}
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return true;
	}
	if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		return true;
	}

	$wc_ajax = '';
	if ( isset( $_REQUEST['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wc_ajax = sanitize_key( wp_unslash( $_REQUEST['wc-ajax'] ) );
	}

	return in_array(
		$wc_ajax,
		array(
			'update_order_review',
			'checkout',
			'get_refreshed_fragments',
			'apply_coupon',
			'remove_coupon',
			'update_shipping_method',
			'get_cart_totals',
			'remove_from_cart',
			'add_to_cart',
		),
		true
	);
}

/**
 * Catalog and single-product views use the raised cents. Cart and checkout do not.
 *
 * @return bool
 */
function nh_eur_use_superscript() {
	if ( ! nh_eur_currency_is_euro() ) {
		return false;
	}
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}
	if ( nh_eur_request_is_cart_or_checkout() ) {
		return false;
	}

	foreach ( array( 'woocommerce_email_header', 'woocommerce_email_order_details', 'wpo_wcpdf_before_document' ) as $hook ) {
		if ( did_action( $hook ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Wrap the cents of each formatted amount in a superscript.
 *
 * 10,49 € becomes 10,<sup class="nh-eur-cents">49</sup> €. Numbers inside
 * HTML tags are left alone, and a second pass does not wrap them again.
 *
 * @param string $html     Price HTML or a plain formatted amount.
 * @param string $decimal  Decimal separator used in the formatted amount.
 * @param int    $decimals Number of decimal digits to raise.
 * @return string
 */
function nh_eur_superscript_html( $html, $decimal = ',', $decimals = 2 ) {
	if ( ! is_string( $html ) || '' === $html ) {
		return $html;
	}

	$decimals = (int) $decimals;
	$decimal  = (string) $decimal;
	if ( $decimals < 1 || '' === $decimal ) {
		return $html;
	}
	if ( false !== strpos( $html, 'nh-eur-cents' ) ) {
		return $html;
	}

	$pattern = '/(<[^>]+>)|(' . preg_quote( $decimal, '/' ) . ')(\d{' . $decimals . '})(?!\d)/u';

	$formatted = preg_replace_callback(
		$pattern,
		static function ( $match ) {
			if ( ! empty( $match[1] ) ) {
				return $match[1];
			}

			return $match[2] . '<sup class="nh-eur-cents">' . $match[3] . '</sup>';
		},
		$html
	);

	return is_string( $formatted ) ? $formatted : $html;
}

/**
 * Superscript a price when this request should show raised euro cents.
 *
 * @param string $html Price HTML.
 * @return string
 */
function nh_eur_format_display( $html ) {
	if ( ! is_string( $html ) || ! nh_eur_use_superscript() ) {
		return $html;
	}

	$decimal  = function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : ',';
	$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;

	return nh_eur_superscript_html( $html, $decimal, $decimals );
}

/**
 * Keep the superscript class when price HTML is passed through wp_kses_post().
 *
 * @param array<string, mixed> $tags    Allowed tags.
 * @param string               $context Kses context.
 * @return array<string, mixed>
 */
function nh_eur_allow_superscript_in_kses( $tags, $context ) {
	if ( 'post' !== $context || ! is_array( $tags ) ) {
		return $tags;
	}
	if ( ! isset( $tags['sup'] ) || ! is_array( $tags['sup'] ) ) {
		$tags['sup'] = array();
	}
	$tags['sup']['class'] = true;

	return $tags;
}
add_filter( 'wp_kses_allowed_html', 'nh_eur_allow_superscript_in_kses', 10, 2 );

/**
 * Product price HTML used by the catalog, the product page, search and related cards.
 *
 * @param string $price_html Price HTML.
 * @return string
 */
function nh_eur_filter_price_html( $price_html ) {
	return nh_eur_format_display( $price_html );
}
add_filter( 'woocommerce_get_price_html', 'nh_eur_filter_price_html', 40 );
