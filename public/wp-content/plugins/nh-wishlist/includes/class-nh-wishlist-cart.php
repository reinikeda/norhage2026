<?php
/**
 * Move wishlist lines into the WooCommerce basket, including custom-cut POST fields.
 *
 * @package nh-wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_WL_Cart {

	/**
	 * @param array<string,mixed> $item Stored item.
	 * @return string|WP_Error Cart item key.
	 */
	public static function add_item( $item ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return new WP_Error( 'cart', __( 'Could not add this product to the basket.', 'nh-wishlist' ) );
		}
		$product = wc_get_product( (int) $item['product_id'] );
		if ( ! $product ) {
			return new WP_Error( 'missing', __( 'This product is no longer available.', 'nh-wishlist' ) );
		}
		$flags = nh_wl_flags_for_product( $product );
		if ( nh_wl_item_needs_customize( $item, $flags ) ) {
			return new WP_Error( 'customize', nh_wl_customize_notice() );
		}
		$variation_id = (int) $item['variation_id'];
		$variation    = array();
		if ( $variation_id > 0 ) {
			$chosen = wc_get_product( $variation_id );
			if ( ! $chosen || (int) $chosen->get_parent_id() !== (int) $item['product_id'] ) {
				return new WP_Error( 'missing', __( 'This product is no longer available.', 'nh-wishlist' ) );
			}
			$variation = nh_wl_normalize_attributes( isset( $item['attributes'] ) ? $item['attributes'] : array() );
			if ( ! $variation && is_callable( array( $chosen, 'get_variation_attributes' ) ) ) {
				$variation = $chosen->get_variation_attributes();
			}
		}
		$post      = nh_wl_post_fields_for_cart( $item, $flags );
		$cart_data = array( 'nh_wishlist' => 1 );
		if ( ! empty( $flags['is_custom'] ) ) {
			$dims                 = nh_wl_normalize_dimensions( isset( $item['dimensions'] ) ? $item['dimensions'] : array() );
			$dims['unit']         = $flags['unit'];
			$dims['type']         = $flags['type'];
			$cart_data['nh_custom_size'] = $dims;
		}
		$key = nh_wl_with_post_fields(
			$post,
			static function () use ( $item, $variation_id, $variation, $cart_data ) {
				return WC()->cart->add_to_cart(
					(int) $item['product_id'],
					(int) $item['quantity'],
					$variation_id,
					$variation,
					$cart_data
				);
			}
		);
		if ( ! $key ) {
			return new WP_Error( 'cart', __( 'Could not add this product to the basket.', 'nh-wishlist' ) );
		}
		return (string) $key;
	}
}
