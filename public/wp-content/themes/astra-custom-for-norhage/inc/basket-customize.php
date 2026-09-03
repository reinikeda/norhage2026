<?php
/**
 * inc/basket-customize.php
 *
 * Basket / cart customizations for Norhage.
 *
 * OWNERSHIP NOTE: This file is the SOLE owner of woocommerce_get_item_data
 * (what rows show in cart/mini-cart/checkout) and
 * woocommerce_get_cart_item_from_session (Blocks variation rows).
 * sample-order.php does NOT hook these — see its header note.
 *
 * Cart page layout / shipping calculator UX lives in inc/cart-ux.php.
 *
 * Handles:
 * - Sample cart items: show only Cutting type + Dimensions (full replace).
 * - Custom-cut items: leave their own display logic untouched.
 * - Normal simple products: show their Length attribute where needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'NH_Basket_Customize' ) ) {

	class NH_Basket_Customize {

		const PRIORITY = 999;

		public static function init() {

			add_filter(
				'woocommerce_get_item_data',
				array( __CLASS__, 'filter_cart_item_data' ),
				self::PRIORITY,
				2
			);

			add_filter(
				'woocommerce_get_cart_item_from_session',
				array( __CLASS__, 'hide_variation_for_custom_cut' ),
				self::PRIORITY,
				3
			);
		}

		protected static function product_is_custom_cut( $product ) {
			if ( empty( $product ) || ! ( $product instanceof WC_Product ) ) {
				return false;
			}

			$product_id = $product->get_id();

			if ( $product->is_type( 'variation' ) ) {
				$parent_id = $product->get_parent_id();

				if ( $parent_id ) {
					$product_id = $parent_id;
				}
			}

			if ( ! $product_id ) {
				return false;
			}

			return (bool) get_post_meta( $product_id, '_nh_cc_enabled', true );
		}

		/**
		 * The ONLY two rows a sample is ever allowed to show, anywhere.
		 */
		protected static function get_sample_item_data( $cart_item ) {
			$item_data = array(
				array(
					'key'   => __( 'Cutting type', 'nh-theme' ),
					'value' => __( 'Sample', 'nh-theme' ),
				),
			);

			$width  = isset( $cart_item['custom_width_mm'] ) ? $cart_item['custom_width_mm'] : '';
			$length = isset( $cart_item['custom_length_mm'] ) ? $cart_item['custom_length_mm'] : '';

			if ( '' !== $width ) {
				$item_data[] = array(
					'key'     => __( 'Width', 'nh-theme' ),
					'value'   => $width . ' mm',
					'display' => $width . ' mm',
				);
			}

			if ( '' !== $length ) {
				$item_data[] = array(
					'key'     => __( 'Length', 'nh-theme' ),
					'value'   => $length . ' mm',
					'display' => $length . ' mm',
				);
			}

			return $item_data;
		}

		public static function filter_cart_item_data( $item_data, $cart_item ) {

			// Samples: full replace, discard anything any other filter added.
			if ( function_exists( 'nh_is_sample_cart_item' ) && nh_is_sample_cart_item( $cart_item ) ) {
				return self::get_sample_item_data( $cart_item );
			}

			// Custom-cut items: leave their own logic untouched.
			if ( ! empty( $cart_item['nh_custom_size'] ) || ! empty( $cart_item['custom_cut_data'] ) ) {
				return $item_data;
			}

			if ( empty( $cart_item['data'] ) || ! ( $cart_item['data'] instanceof WC_Product ) ) {
				return $item_data;
			}

			$product = $cart_item['data'];

			if ( self::product_is_custom_cut( $product ) ) {
				return $item_data;
			}

			if ( ! empty( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
				return $item_data;
			}

			$length_value = $product->get_attribute( 'pa_length' );

			if ( '' === $length_value ) {
				$length_value = $product->get_attribute( 'length' );
			}

			if ( '' === $length_value ) {
				return $item_data;
			}

			$already_has_length = false;

			foreach ( $item_data as $row ) {
				if (
					isset( $row['key'] ) &&
					mb_strtolower( wp_strip_all_tags( $row['key'] ) ) ===
					mb_strtolower( __( 'Length', 'nh-theme' ) )
				) {
					$already_has_length = true;
					break;
				}
			}

			if ( ! $already_has_length ) {
				$item_data[] = array(
					'key'     => __( 'Length', 'nh-theme' ),
					'value'   => wp_kses_post( $length_value ),
					'display' => wp_kses_post( $length_value ),
				);
			}

			return $item_data;
		}

		public static function hide_variation_for_custom_cut( $cart_item, $values, $key ) {

			if ( function_exists( 'nh_is_sample_cart_item' ) && nh_is_sample_cart_item( $cart_item ) ) {
				$cart_item['variation'] = array();
				return $cart_item;
			}

			if ( ! empty( $cart_item['nh_custom_size'] ) || ! empty( $cart_item['custom_cut_data'] ) ) {
				$cart_item['variation'] = array();
				return $cart_item;
			}

			if ( ! empty( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ) {
				if ( self::product_is_custom_cut( $cart_item['data'] ) ) {
					$cart_item['variation'] = array();
				}
			}

			return $cart_item;
		}
	}

	NH_Basket_Customize::init();
}
