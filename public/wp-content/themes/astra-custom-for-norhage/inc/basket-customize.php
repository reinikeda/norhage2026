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

			// 1) Cart item meta: show selected attributes + Length for profiles,
			//    but do not duplicate / break custom-cut products.
			add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'filter_cart_item_data' ), 20, 2 );

			// 2) Shipping calculator fields @ cart: only Country + Postcode (when enabled).
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

		/**
		 * Ensure selected attributes of variable products are visible in cart,
		 * and add a Length line for simple products like aluminium profiles.
		 *
		 * Custom-cut products (nh_custom_size) are left untouched, they already
		 * print Width, Length & Cutting fee from a different plugin.
		 *
		 * @param array $item_data Existing item meta rows.
		 * @param array $cart_item The cart item array.
		 * @return array
		 */
		public static function filter_cart_item_data( $item_data, $cart_item ) {

			// 0) Skip custom-cut sheets completely – they render their own meta.
			if ( ! empty( $cart_item['nh_custom_size'] ) ) {
				return $item_data;
			}

			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
				return $item_data;
			}

			$product = $cart_item['data'];

			/*
			 * A) VARIABLE PRODUCTS: force all selected attributes to show
			 *    (Colour, Width, Length, etc.), even if Woo doesn't add them.
			 */
			if ( ! empty( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {

				foreach ( $cart_item['variation'] as $attr_name => $attr_value ) {

					if ( '' === $attr_value ) {
						continue;
					}

					// Normalise the taxonomy name.
					if ( 0 === strpos( $attr_name, 'attribute_' ) ) {
						$taxonomy = substr( $attr_name, strlen( 'attribute_' ) );
					} else {
						$taxonomy = $attr_name;
					}

					$label = '';
					$value = $attr_value;

					if ( taxonomy_exists( $taxonomy ) ) {
						$label = wc_attribute_label( $taxonomy );
						$term  = get_term_by( 'slug', $attr_value, $taxonomy );
						if ( $term && ! is_wp_error( $term ) ) {
							$value = $term->name;
						}
					} else {
						// Non-taxonomy attribute (rare, but possible).
						$label = wc_attribute_label( $taxonomy );
					}

					if ( '' === $label ) {
						continue;
					}

					// Avoid duplicates: if a row with same label already exists, skip.
					$already = false;
					foreach ( $item_data as $row ) {
						if ( isset( $row['key'] ) && mb_strtolower( wp_strip_all_tags( $row['key'] ) ) === mb_strtolower( $label ) ) {
							$already = true;
							break;
						}
					}
					if ( $already ) {
						continue;
					}

					$item_data[] = array(
						'key'     => $label,
						'value'   => wp_kses_post( $value ),
						'display' => wp_kses_post( $value ),
					);
				}

				// For variable products, we don't need the special "Length" fallback –
				// it comes from the variation attributes above.
				return $item_data;
			}

			/*
			 * B) SIMPLE PRODUCTS: add a Length line if the product has that attribute.
			 *    This is mainly for the aluminium profiles (2 m, 3 m, 6 m, etc.).
			 */
			$length_value = $product->get_attribute( 'pa_length' );
			if ( '' === $length_value ) {
				$length_value = $product->get_attribute( 'length' );
			}

			if ( '' !== $length_value ) {
				$item_data[] = array(
					'key'     => __( 'Length', 'astra-custom-for-norhage' ),
					'value'   => wp_kses_post( $length_value ),
					'display' => wp_kses_post( $length_value ),
				);
			}

			return $item_data;
		}

		/* --------------------------------------------------------------------
		 * 2) Shipping calculator toggle for local pickup
		 * ------------------------------------------------------------------ */

		/**
		 * Dynamically disable the cart shipping calculator when local pickup is selected.
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
			echo '<style>.cart_totals .woocommerce-shipping-destination{display:none!important;}</style>';

			// Wrapper for styling (optional but recommended)
			echo '<div class="nh-pickup-destination">';

			// Header
			echo '<h3 class="nh-pickup-title">'
				. esc_html__( 'Pickup from Lithuania warehouse', 'astra-custom-for-norhage' )
				. '</h3>';

			// Address paragraph
			echo '<p class="nh-pickup-address">'
				. esc_html__( 'Address: Tiekėjų g. 19E, 97123 Kretinga.', 'astra-custom-for-norhage' )
				. '</p>';

			echo '</div>';
		}
	}

	NH_Basket_Customize::init();
}
