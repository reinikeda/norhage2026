<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NHGP_Overrides {
	public static function init(){
		add_filter( 'woocommerce_package_rates', array( __CLASS__, 'apply' ), PHP_INT_MAX, 2 );
	}

	public static function apply( $rates, $package ){
		$heavy  = NHGP_Defaults::heavy_get();
		$custom = NHGP_Defaults::custom_get();

		if ( ! function_exists('WC') || ! WC()->cart ) return $rates;

		$total_weight = (float) WC()->cart->get_cart_contents_weight();

		// Pick heavy tier (highest level that matches)
		$tiers = array(
			array( 'w'=>(float)$heavy['t1_weight'], 'a'=>(float)$heavy['t1_amount'], 'label'=>'L1' ),
			array( 'w'=>(float)$heavy['t2_weight'], 'a'=>(float)$heavy['t2_amount'], 'label'=>'L2' ),
			array( 'w'=>(float)$heavy['t3_weight'], 'a'=>(float)$heavy['t3_amount'], 'label'=>'L3' ),
		);
		usort( $tiers, fn($A,$B)=>$A['w']<=>$B['w'] );
		$chosen = null;
		if ( ! empty( $heavy['enabled'] ) ) {
			foreach ( $tiers as $t ) if ( $total_weight >= $t['w'] && $t['w'] > 0 ) $chosen = $t;
		}

		foreach ( $rates as $key => $rate ){
			if ( ! is_object( $rate ) || ! method_exists( $rate, 'get_method_id' ) ) continue;
			if ( $rate->get_method_id() !== 'flat_rate' ) continue;

			// Clean any previous suffix
			if ( method_exists( $rate, 'get_label' ) && method_exists( $rate, 'set_label' ) ) {
				$clean = preg_replace( '/\s*\(Heavy\s+L[123]\)\s*$/i', '', $rate->get_label() );
				$clean = preg_replace( '/\s*\([^)]+\)\s*$/', '', $clean );
				$rate->set_label( $clean );
			}

			// Heavy override
			if ( $chosen ) {
				$amount = (float) $chosen['a'];
				if ( method_exists( $rate, 'set_cost' ) ) $rate->set_cost( $amount ); else $rate->cost = $amount;
				if ( method_exists( $rate, 'get_taxes' ) && method_exists( $rate, 'set_taxes' ) ) {
					$rate->set_taxes( WC_Tax::calc_shipping_tax( $amount, WC_Tax::get_shipping_tax_rates() ) );
				}
				if ( ! empty( $heavy['append_label'] ) && method_exists( $rate, 'get_label' ) && method_exists( $rate, 'set_label' ) ) {
					$rate->set_label( $rate->get_label() . ' (Heavy ' . $chosen['label'] . ')' );
				}
				continue;
			}

			// ---- Under Level 1: choose dominant class (includes custom-cut mapping)
			$instance_id = method_exists( $rate, 'get_instance_id' ) ? (int) $rate->get_instance_id() : 0;
			if ( $instance_id <= 0 ) continue;

			$settings = get_option( 'woocommerce_flat_rate_' . $instance_id . '_settings', array() );

			// Build term_id => class cost map
			$class_costs = array();
			foreach ( (array) $settings as $k => $v ){
				if ( strpos( $k, 'class_cost_' ) === 0 ) {
					$tid = (int) str_replace( 'class_cost_', '', $k );
					$class_costs[ $tid ] = (float) str_replace( ',', '.', (string) $v );
				}
			}

			// Gather present classes from NON custom-cut items only
			$present = array();
			foreach ( WC()->cart->get_cart() as $item ){
				if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) continue;
				$p = $item['data'];
				if ( ! empty( $custom['enabled'] ) && NHGP_Custom_Cut::is_custom_item( $item, $p, $custom ) ) {
					continue; // custom-cut items handled below
				}
				$tid = (int) $p->get_shipping_class_id();
				if ( $tid > 0 ) $present[ $tid ] = true;
			}

			// Add mapped classes from custom-cut items
			if ( ! empty( $custom['enabled'] ) ){
				foreach ( WC()->cart->get_cart() as $item ){
					if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) continue;
					$p = $item['data'];
					if ( ! NHGP_Custom_Cut::is_custom_item( $item, $p, $custom ) ) continue;

					list($w,$h) = NHGP_Custom_Cut::get_dims( $item, $p, $custom );
					if ( $w > 0 && $h > 0 ){
						$slug = NHGP_Custom_Cut::map_to_class_slug( $w, $h, $custom );
						if ( $slug ){
							$term = get_term_by( 'slug', $slug, 'product_shipping_class' );
							if ( $term && ! is_wp_error( $term ) ) $present[ (int) $term->term_id ] = true;
						}
					}
				}
			}

			// Pick the present class with the highest configured cost
			$best_tid = null; $best_cost = -1;
			foreach ( $present as $tid => $_ ){
				if ( isset( $class_costs[ $tid ] ) && $class_costs[ $tid ] > $best_cost ){
					$best_cost = $class_costs[ $tid ];
					$best_tid  = $tid;
				}
			}

			// Bump up to the dominant class cost if needed
			$current_cost = method_exists( $rate, 'get_cost' ) ? (float) $rate->get_cost() : (float) $rate->cost;
			if ( $best_tid && $best_cost > $current_cost ){
				if ( method_exists( $rate, 'set_cost' ) ) $rate->set_cost( $best_cost ); else $rate->cost = $best_cost;
				if ( method_exists( $rate, 'get_taxes' ) && method_exists( $rate, 'set_taxes' ) ) {
					$rate->set_taxes( WC_Tax::calc_shipping_tax( $best_cost, WC_Tax::get_shipping_tax_rates() ) );
				}
			}

			// Append class label
			if ( $best_tid && ! empty( $custom['show_base_label'] )
				&& method_exists( $rate, 'get_label' ) && method_exists( $rate, 'set_label' ) ) {
				$term = get_term( $best_tid, 'product_shipping_class' );
				if ( $term && ! is_wp_error( $term ) ) {
					$rate->set_label( $rate->get_label() . ' (' . $term->name . ')' );
				}
			}
		}

		return $rates;
	}
}
