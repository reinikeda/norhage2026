<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NHGP_Overrides {

	public static function init() {
		add_filter( 'woocommerce_package_rates', array( __CLASS__, 'apply' ), PHP_INT_MAX, 2 );
	}

	public static function apply( $rates, $package ) {

		$heavy  = NHGP_Defaults::heavy_get();
		$custom = NHGP_Defaults::custom_get();

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $rates;
		}

		// Always treat custom feature as enabled internally (keys are hard-coded now)
		$custom['enabled'] = true;

		/* ================= OVERSIZE RULE (LT + big item => ONLY local pickup) ================= */

		$destination_country = '';
		if ( ! empty( $package['destination']['country'] ) ) {
			$destination_country = strtoupper( (string) $package['destination']['country'] );
		}

		$cart_items   = WC()->cart->get_cart();
		$has_oversize = self::cart_has_oversize_item( $cart_items );

		// Only affect Lithuania – other countries keep normal behaviour
		if ( $destination_country === 'LT' && $has_oversize ) {

			// Keep only local pickup, remove all other methods (flat rate, etc.)
			foreach ( $rates as $key => $rate ) {
				if ( ! is_object( $rate ) || ! method_exists( $rate, 'get_method_id' ) ) {
					continue;
				}

				if ( $rate->get_method_id() !== 'local_pickup' ) {
					unset( $rates[ $key ] );
				}
			}

			// Oversize rule wins; skip heavy tiers / class dominance.
			return $rates;
		}

		/* ================= HEAVY TIERS (cart-wide) ================= */

		$total_weight = (float) WC()->cart->get_cart_contents_weight();

		// Build up to 12 tiers from settings (Weight ≥)
		$tiers = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$w = isset( $heavy[ "t{$i}_weight" ] ) ? (float) $heavy[ "t{$i}_weight" ] : 0;
			$a = isset( $heavy[ "t{$i}_amount" ] ) ? (float) $heavy[ "t{$i}_amount" ] : 0;

			// Ignore empty / invalid levels
			if ( $w <= 0 || $a <= 0 ) {
				continue;
			}

			$tiers[] = array(
				'w'     => $w,
				'a'     => $a,
				'label' => 'L' . $i,
			);
		}

		// Sort ascending by weight (safety – admin already sorts)
		if ( ! empty( $tiers ) ) {
			usort(
				$tiers,
				static function( $A, $B ) {
					return $A['w'] <=> $B['w'];
				}
			);
		}

		$chosen_heavy = null;

		// Behaviour: last tier with weight ≤ cart weight wins
		// => orders below the smallest tier are NOT counted by weight.
		foreach ( $tiers as $t ) {
			if ( $total_weight >= $t['w'] ) {
				$chosen_heavy = $t;
			}
		}

		/* ================= APPLY PER RATE ================= */
		foreach ( $rates as $key => $rate ) {

			if ( ! is_object( $rate ) || ! method_exists( $rate, 'get_method_id' ) ) {
				continue;
			}
			if ( $rate->get_method_id() !== 'flat_rate' ) {
				continue;
			}

			// Clean any previous suffix: remove "(Heavy Lx)" and any trailing "(...)"
			if ( method_exists( $rate, 'get_label' ) && method_exists( $rate, 'set_label' ) ) {
				$clean = preg_replace( '/\s*\(Heavy\s+L\d+\)\s*$/i', '', $rate->get_label() );
				$clean = preg_replace( '/\s*\([^)]+\)\s*$/', '', $clean );
				$rate->set_label( $clean );
			}

			// Current base cost of this flat rate
			$current_cost = method_exists( $rate, 'get_cost' ) ? (float) $rate->get_cost() : (float) $rate->cost;

			// Heavy tier amount for this cart (if any)
			$heavy_amount = $chosen_heavy ? (float) $chosen_heavy['a'] : 0.0;

			/* ------------ CLASS DOMINANCE (build best class cost) ------------ */
			$instance_id = method_exists( $rate, 'get_instance_id' ) ? (int) $rate->get_instance_id() : 0;

			$best_tid  = null;
			$best_cost = -1; // stays -1 if no class dominance

			if ( $instance_id > 0 ) {

				$settings = get_option( 'woocommerce_flat_rate_' . $instance_id . '_settings', array() );

				// Build term_id => class cost map
				$class_costs = array();
				foreach ( (array) $settings as $k => $v ) {
					if ( strpos( $k, 'class_cost_' ) === 0 ) {
						$tid = (int) str_replace( 'class_cost_', '', $k );
						$class_costs[ $tid ] = (float) str_replace( ',', '.', (string) $v );
					}
				}

				// Gather present classes from NON custom-cut items
				$present = array();
				foreach ( WC()->cart->get_cart() as $item ) {
					if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
						continue;
					}
					$p = $item['data'];

					if ( NHGP_Custom_Cut::is_custom_item( $item, $p, $custom ) ) {
						continue;
					}

					$tid = (int) $p->get_shipping_class_id();
					if ( $tid > 0 ) {
						$present[ $tid ] = true;
					}
				}

				// Add mapped classes from custom-cut items
				foreach ( WC()->cart->get_cart() as $item ) {
					if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
						continue;
					}
					$p = $item['data'];

					if ( ! NHGP_Custom_Cut::is_custom_item( $item, $p, $custom ) ) {
						continue;
					}

					list( $w, $h ) = NHGP_Custom_Cut::get_dims( $item, $p, $custom );
					if ( $w > 0 && $h > 0 ) {
						$slug = NHGP_Custom_Cut::map_to_class_slug( $w, $h, $custom );
						if ( $slug ) {
							$term = get_term_by( 'slug', $slug, 'product_shipping_class' );
							if ( $term && ! is_wp_error( $term ) ) {
								$present[ (int) $term->term_id ] = true;
							}
						}
					}
				}

				// If still nothing, try default custom-cut class
				if ( empty( $present ) && ! empty( $custom['default_class'] ) ) {
					$term = get_term_by( 'slug', $custom['default_class'], 'product_shipping_class' );
					if ( $term && ! is_wp_error( $term ) ) {
						$present[ (int) $term->term_id ] = true;
					}
				}

				// Pick the present class with the highest configured cost
				foreach ( $present as $tid => $_ ) {
					if ( isset( $class_costs[ $tid ] ) && $class_costs[ $tid ] > $best_cost ) {
						$best_cost = $class_costs[ $tid ];
						$best_tid  = $tid;
					}
				}
			}

			/* ------------ DECIDE FINAL COST: max(base, heavy, class) ------------ */

			$target_cost = $current_cost;
			$use_heavy   = false;
			$use_class   = false;

			// Compare heavy
			if ( $heavy_amount > $target_cost ) {
				$target_cost = $heavy_amount;
				$use_heavy   = true;
				$use_class   = false;
			}

			// Compare class dominance
			if ( $best_tid && $best_cost > $target_cost ) {
				$target_cost = $best_cost;
				$use_heavy   = false;
				$use_class   = true;
			}

			// Apply new cost if it is higher than the original flat rate
			if ( $target_cost > $current_cost ) {

				if ( method_exists( $rate, 'set_cost' ) ) {
					$rate->set_cost( $target_cost );
				} else {
					$rate->cost = $target_cost;
				}

				if ( method_exists( $rate, 'set_taxes' ) ) {
					$rate->set_taxes( WC_Tax::calc_shipping_tax( $target_cost, WC_Tax::get_shipping_tax_rates() ) );
				}
			}

			/* ------------ LABELS ------------ */

			if ( method_exists( $rate, 'get_label' ) && method_exists( $rate, 'set_label' ) ) {
				$label = $rate->get_label();

				if ( $use_heavy && $chosen_heavy ) {
					// Translatable "Heavy %s"
					$suffix = sprintf( __( 'Heavy %s', NHGP_TEXTDOMAIN ), $chosen_heavy['label'] );
					$label .= ' (' . $suffix . ')';
				} elseif ( $use_class && $best_tid ) {
					$term = get_term( $best_tid, 'product_shipping_class' );
					if ( $term && ! is_wp_error( $term ) ) {
						$label .= ' (' . $term->name . ')';
					}
				}

				$rate->set_label( $label );
			}
		}

		return $rates;
	}

	/**
	 * Detect if the cart contains at least one oversize item.
	 *
	 * Condition:
	 *  - any product width > 150 cm OR length > 300 cm
	 *    using:
	 *      - custom-cut size stored on the cart item (mm), OR
	 *      - product dimensions (length/width) in WooCommerce dimension unit.
	 */
	protected static function cart_has_oversize_item( $cart_items ) {

		if ( empty( $cart_items ) ) {
			return false;
		}

		// --- thresholds ---

		// Custom-cut thresholds in mm.
		$threshold_width_mm  = 1500; // 150 cm
		$threshold_length_mm = 3000; // 300 cm

		// Product dimension thresholds based on store unit.
		$dimension_unit = get_option( 'woocommerce_dimension_unit', 'cm' );

		// Base thresholds in metres.
		$base_width_m  = 1.5; // 150 cm
		$base_length_m = 3.0; // 300 cm

		switch ( $dimension_unit ) {
			case 'mm':
				$th_w = $base_width_m * 1000;  // 1500
				$th_l = $base_length_m * 1000; // 3000
				break;

			case 'cm':
				$th_w = $base_width_m * 100;   // 150
				$th_l = $base_length_m * 100;  // 300
				break;

			case 'm':
				$th_w = $base_width_m;         // 1.5
				$th_l = $base_length_m;        // 3
				break;

			case 'in':
				// 1 m = 39.3701 in
				$th_w = $base_width_m  * 39.3701;
				$th_l = $base_length_m * 39.3701;
				break;

			case 'yd':
				// 1 m = 1.09361 yd
				$th_w = $base_width_m  * 1.09361;
				$th_l = $base_length_m * 1.09361;
				break;

			default:
				// Fallback: assume cm
				$th_w = $base_width_m * 100;
				$th_l = $base_length_m * 100;
				break;
		}

		foreach ( $cart_items as $item ) {

			if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				continue;
			}

			/** @var WC_Product $product */
			$product = $item['data'];

			// 1) CUSTOM-CUT SIZE FROM CART (mm)
			$w_mm = 0;
			$h_mm = 0;

			// Theme structure: nh_custom_size[width_mm/length_mm]
			if ( ! empty( $item['nh_custom_size'] ) && is_array( $item['nh_custom_size'] ) ) {
				$w_mm = (float) ( $item['nh_custom_size']['width_mm']  ?? 0 );
				$h_mm = (float) ( $item['nh_custom_size']['length_mm'] ?? 0 );
			}

			// Flat keys: nh_width_mm / nh_length_mm
			if ( $w_mm <= 0 && isset( $item[ NHGP_Custom_Cut::WIDTH_KEY ] ) ) {
				$w_mm = (float) $item[ NHGP_Custom_Cut::WIDTH_KEY ];
			}
			if ( $h_mm <= 0 && isset( $item[ NHGP_Custom_Cut::HEIGHT_KEY ] ) ) {
				$h_mm = (float) $item[ NHGP_Custom_Cut::HEIGHT_KEY ];
			}

			if ( $w_mm > 0 || $h_mm > 0 ) {
				// We have an actual custom size in mm on the cart item – use that only.
				if ( $w_mm > $threshold_width_mm || $h_mm > $threshold_length_mm ) {
					return true;
				}

				// We do NOT also check product dimensions for this item.
				continue;
			}

			// 2) NORMAL PRODUCT DIMENSIONS IN STORE UNIT
			$length = (float) $product->get_length();
			$width  = (float) $product->get_width();

			if ( $width <= 0 && $length <= 0 ) {
				continue;
			}

			if ( $width > $th_w || $length > $th_l ) {
				return true;
			}
		}

		return false;
	}
}
