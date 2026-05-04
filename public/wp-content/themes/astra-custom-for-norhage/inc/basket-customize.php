<?php
/**
 * Basket / cart customizations for Norhage.
 *
 * Updated for WooCommerce Blocks (Cart & Checkout blocks).
 * Shipping calculator removed — not supported in Blocks.
 * Shipping is calculated at checkout after address is entered.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'NH_Basket_Customize' ) ) {

	class NH_Basket_Customize {

		public static function init() {

			// Classic cart fallback: simple-product Length attribute.
			// Variable product attributes are shown natively by Blocks — do NOT add manually.
			add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'filter_cart_item_data' ), 20, 2 );

			// Blocks fix: custom-cut products already show Width / Length / Cutting fee
			// via their own plugin. Wipe variation array so Blocks doesn't add a duplicate row.
			add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'hide_variation_for_custom_cut' ), 20, 3 );
		}

		/* --------------------------------------------------------------------
		 * 1) Classic cart — cart item attribute display
		 * ------------------------------------------------------------------ */

		/**
		 * For classic (shortcode) cart only.
		 *
		 * - Variable products: do nothing — Blocks renders variation attributes natively.
		 * - Custom-cut products: skip — separate plugin handles their meta.
		 * - Simple products: add Length attribute if present (aluminium profiles etc.).
		 *
		 * @param array $item_data Existing item meta rows.
		 * @param array $cart_item The cart item array.
		 * @return array
		 */
		public static function filter_cart_item_data( $item_data, $cart_item ) {

			// Skip custom-cut products — they render their own meta.
			if ( ! empty( $cart_item['nh_custom_size'] ) ) {
				return $item_data;
			}

			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
				return $item_data;
			}

			$product = $cart_item['data'];

			// Variable products: Blocks already shows variation attributes — do nothing.
			if ( ! empty( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
				return $item_data;
			}

			// Simple products: add Length if the product has that attribute.
			$length_value = $product->get_attribute( 'pa_length' );
			if ( '' === $length_value ) {
				$length_value = $product->get_attribute( 'length' );
			}

			if ( '' !== $length_value ) {

				// Avoid duplicates.
				$already = false;
				foreach ( $item_data as $row ) {
					if (
						isset( $row['key'] ) &&
						mb_strtolower( wp_strip_all_tags( $row['key'] ) ) === mb_strtolower( __( 'Length', 'astra-custom-for-norhage' ) )
					) {
						$already = true;
						break;
					}
				}

				if ( ! $already ) {
					$item_data[] = array(
						'key'     => __( 'Length', 'astra-custom-for-norhage' ),
						'value'   => wp_kses_post( $length_value ),
						'display' => wp_kses_post( $length_value ),
					);
				}
			}

			return $item_data;
		}

		/* --------------------------------------------------------------------
		 * 2) Blocks — suppress duplicate variation rows for custom-cut items
		 * ------------------------------------------------------------------ */

		/**
		 * Custom-cut products already display Width / Length / Cutting fee
		 * from their own plugin. Clearing ['variation'] prevents Blocks from
		 * also rendering the base variation attributes as a second row.
		 *
		 * Does NOT affect variation_id, order data, or anything in the database.
		 *
		 * @param array  $cart_item Cart item data.
		 * @param array  $values    Raw session values.
		 * @param string $key       Cart item key.
		 * @return array
		 */
		public static function hide_variation_for_custom_cut( $cart_item, $values, $key ) {

			if ( ! empty( $cart_item['nh_custom_size'] ) ) {
				$cart_item['variation'] = array();
			}

			return $cart_item;
		}
	}

	NH_Basket_Customize::init();
}
