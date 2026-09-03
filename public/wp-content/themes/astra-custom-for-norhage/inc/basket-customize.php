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

			// Custom-cut lines already add Width/Length (and cutting fee) in product-customize.php.
			if ( ! empty( $cart_item['nh_custom_size'] ) || ! empty( $cart_item['custom_cut_data'] ) ) {
				return $item_data;
			}

			if ( empty( $cart_item['data'] ) || ! ( $cart_item['data'] instanceof WC_Product ) ) {
				return $item_data;
			}

			$item_data = self::ensure_variation_attribute_rows( $item_data, $cart_item );

			$product = $cart_item['data'];

			if ( ! empty( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
				return $item_data;
			}

			if ( $product->is_type( 'variation' ) ) {
				return $item_data;
			}

			$length_value = $product->get_attribute( 'pa_length' );

			if ( '' === $length_value ) {
				$length_value = $product->get_attribute( 'length' );
			}

			if ( '' === $length_value ) {
				return $item_data;
			}

			if ( ! self::item_data_has_label( $item_data, __( 'Length', 'nh-theme' ) ) ) {
				$item_data[] = array(
					'key'     => __( 'Length', 'nh-theme' ),
					'value'   => wp_kses_post( $length_value ),
					'display' => wp_kses_post( $length_value ),
				);
			}

			return $item_data;
		}

		/**
		 * Woo hides variation attributes that already appear in the product title
		 * (e.g. "Tape – 38mm, 25 m" or "… – Bronze"). Always list the selected
		 * options under the name in cart / mini-cart / checkout.
		 *
		 * @param array $item_data Existing item data rows.
		 * @param array $cart_item Cart item.
		 * @return array
		 */
		protected static function ensure_variation_attribute_rows( $item_data, $cart_item ) {
			$product = isset( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product
				? $cart_item['data']
				: null;

			$pairs = array();
			if ( ! empty( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
				$pairs = $cart_item['variation'];
			} elseif ( $product && $product->is_type( 'variation' ) ) {
				$pairs = $product->get_variation_attributes();
			}

			if ( empty( $pairs ) ) {
				return $item_data;
			}

			foreach ( $pairs as $name => $value ) {
				if ( '' === $value || null === $value ) {
					continue;
				}

				$taxonomy = wc_attribute_taxonomy_name( str_replace( 'attribute_', '', wc_sanitize_taxonomy_name( $name ) ) );
				$label    = $name;
				$display  = $value;

				if ( taxonomy_exists( $taxonomy ) ) {
					$term = get_term_by( 'slug', $value, $taxonomy );
					if ( ( ! $term || is_wp_error( $term ) ) && is_numeric( $value ) ) {
						$term = get_term_by( 'id', (int) $value, $taxonomy );
					}
					$label = wc_attribute_label( $taxonomy, $product );
					if ( $term && ! is_wp_error( $term ) && $term->name ) {
						$display = $term->name;
					}
				} else {
					$attr_key = str_replace( 'attribute_', '', $name );
					$label    = wc_attribute_label( $attr_key, $product );
					$display  = apply_filters( 'woocommerce_variation_option_name', $value, null, $attr_key, $product );
				}

				if ( '' === $label || '' === $display ) {
					continue;
				}

				if ( self::item_data_has_label( $item_data, $label ) ) {
					continue;
				}

				$item_data[] = array(
					'key'     => $label,
					'value'   => $display,
					'display' => $display,
				);
			}

			return $item_data;
		}

		/**
		 * @param array  $item_data Item data rows.
		 * @param string $label     Label to look for.
		 * @return bool
		 */
		protected static function item_data_has_label( $item_data, $label ) {
			$needle = mb_strtolower( wp_strip_all_tags( (string) $label ) );
			foreach ( $item_data as $row ) {
				$hay = '';
				if ( ! empty( $row['key'] ) ) {
					$hay = $row['key'];
				} elseif ( ! empty( $row['name'] ) ) {
					$hay = $row['name'];
				}
				if ( $hay !== '' && mb_strtolower( wp_strip_all_tags( (string) $hay ) ) === $needle ) {
					return true;
				}
			}
			return false;
		}

		public static function hide_variation_for_custom_cut( $cart_item, $values, $key ) {

			if ( function_exists( 'nh_is_sample_cart_item' ) && nh_is_sample_cart_item( $cart_item ) ) {
				$cart_item['variation'] = array();
				return $cart_item;
			}

			// Only strip variation attributes on lines that are actually custom-cut.
			// Products with custom-cut *enabled* can still be bought as a normal
			// variation (e.g. Colour: Bronze) and those options must stay visible.
			if ( ! empty( $cart_item['nh_custom_size'] ) || ! empty( $cart_item['custom_cut_data'] ) ) {
				$cart_item['variation'] = array();
			}

			return $cart_item;
		}
	}

	NH_Basket_Customize::init();
}
