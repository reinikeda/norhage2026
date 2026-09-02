<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NHGP_Overrides {

	public static function init() {
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'assign_cart_shipping_classes' ), 30, 1 );
		add_filter( 'woocommerce_cart_shipping_packages', array( __CLASS__, 'stamp_packages' ), 20 );
		add_filter( 'woocommerce_cart_item_shipping_class', array( __CLASS__, 'filter_cart_item_shipping_class' ), 30, 3 );
		add_filter( 'woocommerce_find_shipping_classes', array( __CLASS__, 'remap_found_shipping_classes' ), 20, 2 );
		add_filter( 'woocommerce_package_rates', array( __CLASS__, 'apply' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Put the calculator's shipping class on the in-memory cart product
	 * BEFORE WooCommerce evaluates flat-rate "class cost" vs "no class cost".
	 *
	 * Custom-cut products have no class in the catalog. Without this, live
	 * shops charge the zone's "No shipping class cost".
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function assign_cart_shipping_classes( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
			return;
		}

		$custom = NHGP_Defaults::custom_get();
		$custom['enabled'] = true;

		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				continue;
			}
			if ( ! is_callable( array( $item['data'], 'set_shipping_class_id' ) ) ) {
				continue;
			}

			$slug = NHGP_Custom_Cut::mapped_class_slug_for_item( $item, $item['data'], $custom );
			if ( $slug === '' ) {
				continue;
			}

			$term_id = NHGP_Custom_Cut::term_id_from_slug( $slug );
			if ( $term_id > 0 ) {
				$item['data']->set_shipping_class_id( $term_id );
			}
		}
	}

	/**
	 * Include mapped classes in the package hash so session/object-cache
	 * cannot reuse a previous "no class" rate for the same cart.
	 *
	 * @param array $packages Packages.
	 * @return array
	 */
	public static function stamp_packages( $packages ) {
		if ( ! is_array( $packages ) ) {
			return $packages;
		}

		$custom = NHGP_Defaults::custom_get();
		$custom['enabled'] = true;
		$ver = (string) get_option( 'nhgp_rates_version', '' );

		foreach ( $packages as $i => $package ) {
			$map = array();
			$contents = isset( $package['contents'] ) && is_array( $package['contents'] ) ? $package['contents'] : array();

			foreach ( $contents as $key => $item ) {
				$product = isset( $item['data'] ) ? $item['data'] : null;
				if ( ! $product instanceof WC_Product ) {
					continue;
				}

				$slug = NHGP_Custom_Cut::mapped_class_slug_for_item( $item, $product, $custom );
				if ( $slug === '' ) {
					continue;
				}

				$map[ $key ] = $slug;

				$term_id = NHGP_Custom_Cut::term_id_from_slug( $slug );
				if ( $term_id > 0 && is_callable( array( $product, 'set_shipping_class_id' ) ) ) {
					$product->set_shipping_class_id( $term_id );
					$packages[ $i ]['contents'][ $key ]['data'] = $product;
				}
			}

			$packages[ $i ]['nhgp_rates_version'] = $ver;
			$packages[ $i ]['nhgp_cut_classes']   = $map;
		}

		return $packages;
	}

	/**
	 * @param string $shipping_class Current class slug.
	 * @param array  $cart_item      Cart item.
	 * @param string $cart_item_key  Key.
	 * @return string
	 */
	public static function filter_cart_item_shipping_class( $shipping_class, $cart_item, $cart_item_key ) {
		unset( $cart_item_key );
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		$custom  = NHGP_Defaults::custom_get();
		$custom['enabled'] = true;
		$mapped  = NHGP_Custom_Cut::mapped_class_slug_for_item( $cart_item, $product, $custom );
		return $mapped !== '' ? $mapped : $shipping_class;
	}

	/**
	 * Woo Flat Rate groups package lines by product->get_shipping_class().
	 * Custom-cut catalog products have no class, so they land in the empty
	 * bucket and get "No shipping class cost". Re-bucket from cut rules.
	 *
	 * @param array $found   slug => items.
	 * @param array $package Shipping package.
	 * @return array
	 */
	public static function remap_found_shipping_classes( $found, $package ) {
		if ( ! is_array( $found ) ) {
			$found = array();
		}

		$contents = ( isset( $package['contents'] ) && is_array( $package['contents'] ) )
			? $package['contents']
			: array();

		if ( empty( $contents ) ) {
			return $found;
		}

		$custom = NHGP_Defaults::custom_get();
		$custom['enabled'] = true;

		foreach ( $contents as $item_id => $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			$slug    = NHGP_Custom_Cut::mapped_class_slug_for_item( $item, $product, $custom );
			if ( $slug === '' ) {
				continue;
			}

			foreach ( $found as $class => $items ) {
				if ( isset( $items[ $item_id ] ) ) {
					unset( $found[ $class ][ $item_id ] );
					if ( empty( $found[ $class ] ) ) {
						unset( $found[ $class ] );
					}
				}
			}

			if ( ! isset( $found[ $slug ] ) ) {
				$found[ $slug ] = array();
			}
			$found[ $slug ][ $item_id ] = $item;
		}

		return $found;
	}

	/**
	 * Flat-rate class costs keyed by slug. Woo stores keys as class_cost_{term_id}
	 * and/or class_cost_{slug}; (int) "xs" === 0 and must not be treated as no-class.
	 *
	 * @param array $settings Instance settings.
	 * @return array<string, float>
	 */
	protected static function class_costs_by_slug( $settings ) {
		$costs = array();

		foreach ( (array) $settings as $k => $v ) {
			$kind = self::class_cost_key_kind( $k );
			if ( $kind === '' ) {
				continue;
			}

			$suffix = substr( $k, strlen( 'class_cost_' ) );
			$cost   = self::parse_amount( $v );

			if ( $kind === 'term_id' ) {
				$term = get_term( (int) $suffix, 'product_shipping_class' );
				if ( $term && ! is_wp_error( $term ) ) {
					$costs[ $term->slug ] = $cost;
				}
				continue;
			}

			$slug = NHGP_Custom_Cut::normalize_class_slug( $suffix );
			if ( $slug !== '' ) {
				$costs[ $slug ] = $cost;
			}
		}

		return $costs;
	}

	/**
	 * Classify a flat-rate settings key.
	 *
	 * Live Woo often stores class_cost_xs (slug). Casting that suffix to int
	 * yields 0 and must NOT be treated as "no class".
	 *
	 * @param string $key Settings key.
	 * @return string '' | 'term_id' | 'slug'
	 */
	public static function class_cost_key_kind( $key ) {
		$key = (string) $key;
		if ( strpos( $key, 'class_cost_' ) !== 0 ) {
			return '';
		}

		$suffix = substr( $key, strlen( 'class_cost_' ) );
		if ( $suffix === '' || $suffix === '0' ) {
			return '';
		}

		return ctype_digit( $suffix ) ? 'term_id' : 'slug';
	}

	/**
	 * @param mixed $value Raw setting.
	 * @return float
	 */
	protected static function parse_amount( $value ) {
		return (float) str_replace( ',', '.', (string) $value );
	}

	/**
	 * True when Woo's already-calculated flat rate matches "No shipping class cost"
	 * (optionally plus the method's base cost).
	 *
	 * @param float $current_cost Current rate cost.
	 * @param array $settings     Instance settings.
	 * @return bool
	 */
	protected static function cost_matches_no_class( $current_cost, $settings ) {
		$no_class = self::parse_amount( $settings['no_class_cost'] ?? '' );
		$base     = self::parse_amount( $settings['cost'] ?? '' );

		foreach ( array( $no_class, $no_class + $base ) as $candidate ) {
			if ( abs( (float) $current_cost - (float) $candidate ) < 0.009 ) {
				return true;
			}
		}

		return false;
	}

	public static function apply( $rates, $package ) {

		$heavy  = NHGP_Defaults::heavy_get();
		$custom = NHGP_Defaults::custom_get();

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $rates;
		}

		// Heavy parcels are disabled by default unless explicitly enabled.
		$heavy_enabled = ! empty( $heavy['enabled'] );

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

		$tiers        = array();
		$chosen_heavy = null;

		/*
		 * Build and apply heavy tiers only when the feature is enabled.
		 * When disabled, $tiers remains empty and no heavy amount is applied.
		 */
		if ( $heavy_enabled ) {

			// Build up to 12 tiers from settings (Weight ≥)
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

			// Behaviour: last tier with weight ≤ cart weight wins
			foreach ( $tiers as $t ) {
				if ( $total_weight >= $t['w'] ) {
					$chosen_heavy = $t;
				}
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

			// Heavy tier amount for this cart, only when Heavy parcels is enabled
			$heavy_amount = ( $heavy_enabled && $chosen_heavy ) ? (float) $chosen_heavy['a'] : 0.0;

			/* ------------ CLASS DOMINANCE (build best class cost) ------------ */

			$instance_id = method_exists( $rate, 'get_instance_id' ) ? (int) $rate->get_instance_id() : 0;

			$best_slug = null;
			$best_cost = -1;

			$settings          = array();
			$looks_like_no_class = false;

			if ( $instance_id > 0 ) {

				$settings = get_option( 'woocommerce_flat_rate_' . $instance_id . '_settings', array() );
				if ( ! is_array( $settings ) ) {
					$settings = array();
				}

				// Build slug => class cost map (term ID keys and slug keys).
				$class_costs = self::class_costs_by_slug( $settings );

				$line_items = ( ! empty( $package['contents'] ) && is_array( $package['contents'] ) )
					? $package['contents']
					: WC()->cart->get_cart();

				// Gather present class slugs from NON custom-cut items
				$present = array();

				foreach ( $line_items as $item ) {
					if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
						continue;
					}

					$p = $item['data'];

					if ( NHGP_Custom_Cut::is_custom_item( $item, $p, $custom ) ) {
						continue;
					}

					$slug = $p->get_shipping_class();
					if ( $slug ) {
						$present[ $slug ] = true;
					}
				}

				// Add mapped classes from custom-cut items
				foreach ( $line_items as $item ) {
					if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
						continue;
					}

					$p = $item['data'];

					$slug = NHGP_Custom_Cut::mapped_class_slug_for_item( $item, $p, $custom );
					if ( $slug ) {
						$present[ $slug ] = true;
					}
				}

				// Pick the present class with the highest configured cost
				foreach ( $present as $slug => $_ ) {
					if ( isset( $class_costs[ $slug ] ) && $class_costs[ $slug ] > $best_cost ) {
						$best_cost = $class_costs[ $slug ];
						$best_slug = $slug;
					}
				}

				$looks_like_no_class = self::cost_matches_no_class( $current_cost, $settings );
			}

			/* ------------ DECIDE FINAL COST ------------ */

			$target_cost = $current_cost;
			$use_heavy   = false;
			$use_class   = false;

			// Compare heavy only when enabled
			if ( $heavy_enabled && $heavy_amount > $target_cost ) {
				$target_cost = $heavy_amount;
				$use_heavy   = true;
				$use_class   = false;
			}

			if ( $best_slug && $best_cost >= 0 ) {
				// Live shops often have a real "No shipping class cost". Woo bills
				// that first; we must replace it with the calculator class even
				// when the class amount is lower.
				$replace_no_class = $looks_like_no_class
					&& ! ( $heavy_enabled && $heavy_amount > $best_cost );

				if ( $replace_no_class || $best_cost > $target_cost ) {
					$target_cost = $best_cost;
					$use_heavy   = false;
					$use_class   = true;
				}
			}

			if ( abs( $target_cost - $current_cost ) > 0.001 ) {

				if ( method_exists( $rate, 'set_cost' ) ) {
					$rate->set_cost( $target_cost );
				} else {
					$rate->cost = $target_cost;
				}

				if ( method_exists( $rate, 'set_taxes' ) ) {
					$rate->set_taxes(
						WC_Tax::calc_shipping_tax(
							$target_cost,
							WC_Tax::get_shipping_tax_rates()
						)
					);
				}
			}

			/* ------------ LABELS ------------ */

			if ( method_exists( $rate, 'get_label' ) && method_exists( $rate, 'set_label' ) ) {
				$label = $rate->get_label();

				if ( $use_heavy && $chosen_heavy ) {
					$suffix = sprintf(
						__( 'Heavy %s', NHGP_TEXTDOMAIN ),
						$chosen_heavy['label']
					);

					$label .= ' (' . $suffix . ')';

				} elseif ( $best_slug && ! $use_heavy ) {
					$term = get_term_by( 'slug', $best_slug, 'product_shipping_class' );

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

		// Custom-cut thresholds in mm.
		$threshold_width_mm  = 1500; // 150 cm
		$threshold_length_mm = 3000; // 300 cm

		// Product dimension thresholds based on store unit.
		$dimension_unit = get_option( 'woocommerce_dimension_unit', 'cm' );

		// Base thresholds in metres.
		$base_width_m  = 1.5;
		$base_length_m = 3.0;

		switch ( $dimension_unit ) {
			case 'mm':
				$th_w = $base_width_m * 1000;
				$th_l = $base_length_m * 1000;
				break;

			case 'cm':
				$th_w = $base_width_m * 100;
				$th_l = $base_length_m * 100;
				break;

			case 'm':
				$th_w = $base_width_m;
				$th_l = $base_length_m;
				break;

			case 'in':
				$th_w = $base_width_m  * 39.3701;
				$th_l = $base_length_m * 39.3701;
				break;

			case 'yd':
				$th_w = $base_width_m  * 1.09361;
				$th_l = $base_length_m * 1.09361;
				break;

			default:
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

			if ( ! empty( $item['nh_custom_size'] ) && is_array( $item['nh_custom_size'] ) ) {
				$w_mm = (float) ( $item['nh_custom_size']['width_mm']  ?? 0 );
				$h_mm = (float) (
					$item['nh_custom_size']['length_mm']
					?? ( $item['nh_custom_size']['height_mm'] ?? 0 )
				);
			}

			if ( $w_mm <= 0 && isset( $item[ NHGP_Custom_Cut::WIDTH_KEY ] ) ) {
				$w_mm = (float) $item[ NHGP_Custom_Cut::WIDTH_KEY ];
			}

			if ( $h_mm <= 0 && isset( $item[ NHGP_Custom_Cut::HEIGHT_KEY ] ) ) {
				$h_mm = (float) $item[ NHGP_Custom_Cut::HEIGHT_KEY ];
			}

			// ALT height key support: nh_height_mm
			if ( $h_mm <= 0 && isset( $item['nh_height_mm'] ) ) {
				$h_mm = (float) $item['nh_height_mm'];
			}

			if ( $w_mm > 0 || $h_mm > 0 ) {
				if ( $w_mm > $threshold_width_mm || $h_mm > $threshold_length_mm ) {
					return true;
				}

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
