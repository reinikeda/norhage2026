<?php
/**
 * Basket / cart customizations for Norhage.
 *
 * Handles:
 * - Sample cart items: show only Cutting type + Dimensions.
 * - Custom-cut items: suppress duplicate variation attributes in Blocks.
 * - Normal simple products: show their Length attribute where needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'NH_Basket_Customize' ) ) {

	class NH_Basket_Customize {

		/**
		 * Register cart filters.
		 *
		 * Priority 999 makes this file run after normal product/custom-cut display
		 * filters, so samples can replace unwanted Width, Length and cutting-fee rows.
		 *
		 * @return void
		 */
		public static function init() {

			add_filter(
				'woocommerce_get_item_data',
				array( __CLASS__, 'filter_cart_item_data' ),
				999,
				2
			);

			add_filter(
				'woocommerce_get_cart_item_from_session',
				array( __CLASS__, 'hide_variation_for_custom_cut' ),
				999,
				3
			);
		}

		/* --------------------------------------------------------------------
		 * Helpers
		 * ------------------------------------------------------------------ */

		/**
		 * Determine whether a product, including a variation's parent product,
		 * is configured as custom-cut.
		 *
		 * @param WC_Product|null $product Product object.
		 * @return bool
		 */
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
		 * Get the two display rows allowed for a sample.
		 *
		 * @param array $cart_item Cart item data.
		 * @return array
		 */
		protected static function get_sample_item_data( $cart_item ) {
			$item_data = array(
				array(
					'key'   => __( 'Cutting type', 'astra-custom-for-norhage' ),
					'value' => __( 'Sample', 'astra-custom-for-norhage' ),
				),
			);

			$width  = isset( $cart_item['custom_width_mm'] ) ? $cart_item['custom_width_mm'] : '';
			$length = isset( $cart_item['custom_length_mm'] ) ? $cart_item['custom_length_mm'] : '';

			if ( '' !== $width && '' !== $length ) {
				$dimensions = $width . ' × ' . $length . ' mm';

				$item_data[] = array(
					'key'     => __( 'Dimensions', 'astra-custom-for-norhage' ),
					'value'   => $dimensions,
					'display' => $dimensions,
				);
			}

			return $item_data;
		}

		/* --------------------------------------------------------------------
		 * 1) Classic cart / cart-item meta display
		 * ------------------------------------------------------------------ */

		/**
		 * Control visible cart item data.
		 *
		 * Samples are handled first and their rows are fully replaced. This prevents
		 * Width, Length, variation attributes, and cutting-fee data from appearing.
		 *
		 * @param array $item_data Existing item meta rows.
		 * @param array $cart_item Cart item data.
		 * @return array
		 */
		public static function filter_cart_item_data( $item_data, $cart_item ) {

			/*
			 * Samples must remain plain:
			 * - Cutting type: Sample
			 * - Dimensions: 300 × 300 mm
			 *
			 * Nothing else is returned, even if another custom-cut plugin added it.
			 */
			if ( function_exists( 'nh_is_sample_cart_item' ) && nh_is_sample_cart_item( $cart_item ) ) {
				return self::get_sample_item_data( $cart_item );
			}

			/*
			 * Keep the pre-existing custom-cut behaviour unchanged for all normal
			 * custom-cut items.
			 */
			if ( ! empty( $cart_item['nh_custom_size'] ) || ! empty( $cart_item['custom_cut_data'] ) ) {
				return $item_data;
			}

			if ( empty( $cart_item['data'] ) || ! ( $cart_item['data'] instanceof WC_Product ) ) {
				return $item_data;
			}

			$product = $cart_item['data'];

			/*
			 * Custom-cut products are handled by their separate custom-cut logic.
			 */
			if ( self::product_is_custom_cut( $product ) ) {
				return $item_data;
			}

			/*
			 * Variable products already have their variation attributes rendered
			 * natively by WooCommerce Blocks.
			 */
			if ( ! empty( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
				return $item_data;
			}

			/*
			 * For normal simple products, add Length where it exists.
			 */
			$length_value = $product->get_attribute( 'pa_length' );

			if ( '' === $length_value ) {
				$length_value = $product->get_attribute( 'length' );
			}

			if ( '' === $length_value ) {
				return $item_data;
			}

			/*
			 * Avoid adding a second Length row if another source already added one.
			 */
			$already_has_length = false;

			foreach ( $item_data as $row ) {
				if (
					isset( $row['key'] ) &&
					mb_strtolower( wp_strip_all_tags( $row['key'] ) ) ===
					mb_strtolower( __( 'Length', 'astra-custom-for-norhage' ) )
				) {
					$already_has_length = true;
					break;
				}
			}

			if ( ! $already_has_length ) {
				$item_data[] = array(
					'key'     => __( 'Length', 'astra-custom-for-norhage' ),
					'value'   => wp_kses_post( $length_value ),
					'display' => wp_kses_post( $length_value ),
				);
			}

			return $item_data;
		}

		/* --------------------------------------------------------------------
		 * 2) WooCommerce Blocks variation display
		 * ------------------------------------------------------------------ */

		/**
		 * Remove variation data from samples and custom-cut products.
		 *
		 * This only changes the live cart display data. It does not change product
		 * records, variation IDs, or stored order data.
		 *
		 * @param array  $cart_item Cart item data.
		 * @param array  $values    Raw session values.
		 * @param string $key       Cart item key.
		 * @return array
		 */
		public static function hide_variation_for_custom_cut( $cart_item, $values, $key ) {

			/*
			 * Samples must never show native WooCommerce variation rows such as
			 * Width or Length.
			 */
			if ( function_exists( 'nh_is_sample_cart_item' ) && nh_is_sample_cart_item( $cart_item ) ) {
				$cart_item['variation'] = array();

				return $cart_item;
			}

			/*
			 * Preserve the existing custom-cut behaviour.
			 */
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
