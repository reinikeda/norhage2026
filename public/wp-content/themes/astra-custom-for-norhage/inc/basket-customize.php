<?php
/**
 * Basket / cart customizations for Norhage.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'NH_Basket_Customize' ) ) {

	class NH_Basket_Customize {

		public static function init() {

			// 1) Cart item meta: show Length for profiles, but not for custom-cut products.
			add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'add_length_attribute_to_item_data' ), 20, 2 );

			// 2) Shipping calculator fields @ cart: only Country + Postcode.
			add_filter( 'woocommerce_shipping_calculator_enable_state', '__return_false' );
			add_filter( 'woocommerce_shipping_calculator_enable_city', '__return_false' );
			add_filter( 'woocommerce_shipping_calculator_enable_address', '__return_false' );

			// 3) When local pickup is selected on cart:
			//    - hide the shipping calculator entirely (no "Change address")
			//    - hide Woo's "Shipping to ..." text and show warehouse address instead.
			add_filter( 'option_woocommerce_enable_shipping_calc', array( __CLASS__, 'maybe_disable_shipping_calc_for_local_pickup' ) );
			add_action( 'woocommerce_cart_totals_after_shipping', array( __CLASS__, 'maybe_output_pickup_warehouse_notice' ) );
		}

		/* --------------------------------------------------------------------
		 * Helper: detect if local pickup is currently chosen
		 * ------------------------------------------------------------------ */

		protected static function is_local_pickup_chosen() {

			if ( ! function_exists( 'WC' ) || ! WC()->session ) {
				return false;
			}

			$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

			if ( empty( $chosen_methods ) || ! is_array( $chosen_methods ) ) {
				return false;
			}

			foreach ( $chosen_methods as $method ) {
				if ( is_string( $method ) && 0 === strpos( $method, 'local_pickup' ) ) {
					return true;
				}
			}

			return false;
		}

		/* --------------------------------------------------------------------
		 * 1) Cart item attributes
		 * ------------------------------------------------------------------ */

		public static function add_length_attribute_to_item_data( $item_data, $cart_item ) {

			// Skip for custom-cut sheets (they already show Width / Length / Cutting fee).
			if ( ! empty( $cart_item['nh_custom_size'] ) ) {
				return $item_data;
			}

			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
				return $item_data;
			}

			$product      = $cart_item['data'];
			$length_value = '';

			// 1) Variation attributes (for variable products).
			if ( ! empty( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
				foreach ( $cart_item['variation'] as $attr_name => $attr_value ) {

					if ( false === stripos( $attr_name, 'length' ) ) {
						continue;
					}

					$taxonomy = str_replace( 'attribute_', '', $attr_name );

					if ( taxonomy_exists( $taxonomy ) ) {
						$term         = get_term_by( 'slug', $attr_value, $taxonomy );
						$length_value = $term ? $term->name : $attr_value;
					} else {
						$length_value = $attr_value;
					}

					break;
				}
			}

			// 2) Fallback: product attributes.
			if ( '' === $length_value ) {
				$length_value = $product->get_attribute( 'pa_length' );
				if ( '' === $length_value ) {
					$length_value = $product->get_attribute( 'length' );
				}
			}

			if ( '' === $length_value ) {
				return $item_data;
			}

			// Avoid duplicate "Length" key.
			foreach ( $item_data as $row ) {
				if ( isset( $row['key'] ) && false !== stripos( $row['key'], 'length' ) ) {
					return $item_data;
				}
			}

			$item_data[] = array(
				'key'     => __( 'Length', 'astra-custom-for-norhage' ),
				'value'   => wp_kses_post( $length_value ),
				'display' => wp_kses_post( $length_value ),
			);

			return $item_data;
		}

		/* --------------------------------------------------------------------
		 * 2) Shipping calculator toggle for local pickup
		 * ------------------------------------------------------------------ */

		/**
		 * Dynamically disable the cart shipping calculator when local pickup is selected.
		 *
		 * This filter runs whenever Woo gets the "enable shipping calc" option.
		 * If we are on the cart and local pickup is the chosen method, return "no"
		 * so WooCommerce will not output the calculator at all (no "Change address").
		 *
		 * @param mixed $value Original option value ("yes" / "no").
		 * @return mixed
		 */
		public static function maybe_disable_shipping_calc_for_local_pickup( $value ) {

			if ( is_cart() && self::is_local_pickup_chosen() ) {
				return 'no';
			}

			return $value;
		}

		/**
		 * After the shipping row in cart totals, hide Woo's destination text
		 * and output our warehouse pickup address when local pickup is chosen.
		 */
		public static function maybe_output_pickup_warehouse_notice() {

			if ( ! is_cart() || ! self::is_local_pickup_chosen() ) {
				return;
			}

			// Hide WooCommerce default "Shipping to ..." paragraph in cart totals.
			// (We print our own clean text below.)
			echo '<style>.cart_totals .woocommerce-shipping-destination{display:none!important;}</style>';

			echo '<p class="nh-pickup-destination">';
			echo wp_kses_post(
				__( 'Pickup from Lithuania warehouse<br>Address: Tiekėjų g. 19E, 97123 Kretinga.', 'astra-custom-for-norhage' )
			);
			echo '</p>';
		}
	}

	NH_Basket_Customize::init();
}
