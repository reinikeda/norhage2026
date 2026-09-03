<?php
/**
 * Side cart AJAX: quantity, remove, shipping calculator, shipping method.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NH_Side_Cart_Ajax {

	public static function init() {
		add_action( 'wc_ajax_nh_sc_update', array( __CLASS__, 'update' ) );
		add_action( 'wc_ajax_nopriv_nh_sc_update', array( __CLASS__, 'update' ) );
	}

	public static function update() {
		check_ajax_referer( NH_Side_Cart::NONCE, 'nonce' );

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error(
				array(
					'message' => __( 'Basket is not available.', NH_SC_TD ),
				),
				400
			);
		}

		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';

		try {
			switch ( $op ) {
				case 'qty':
					self::update_qty();
					break;
				case 'remove':
					self::remove_item();
					break;
				case 'shipping':
					self::calculate_shipping();
					break;
				case 'method':
					self::choose_method();
					break;
				default:
					throw new Exception( __( 'Unknown basket action.', NH_SC_TD ) );
			}
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array() );

		wp_send_json_success(
			array(
				'fragments'  => $fragments,
				'cart_hash'  => WC()->cart->get_cart_hash(),
				'cart_count' => WC()->cart->get_cart_contents_count(),
			)
		);
	}

	private static function update_qty() {
		$key = isset( $_POST['key'] ) ? wc_clean( wp_unslash( $_POST['key'] ) ) : '';
		$qty = isset( $_POST['qty'] ) ? (float) wc_stock_amount( wp_unslash( $_POST['qty'] ) ) : 0;

		if ( $key === '' || ! WC()->cart->get_cart_item( $key ) ) {
			throw new Exception( __( 'Basket item not found.', NH_SC_TD ) );
		}

		$cart_item = WC()->cart->get_cart_item( $key );
		$product   = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		$locked    = $product instanceof WC_Product && $product->is_sold_individually();
		if ( function_exists( 'nh_is_sample_cart_item' ) && nh_is_sample_cart_item( $cart_item ) ) {
			$locked = true;
		}
		if ( $locked ) {
			return;
		}

		if ( $qty <= 0 ) {
			WC()->cart->remove_cart_item( $key );
			return;
		}

		$updated = WC()->cart->set_quantity( $key, $qty, true );
		if ( false === $updated ) {
			throw new Exception( __( 'Could not update the quantity.', NH_SC_TD ) );
		}
	}

	private static function remove_item() {
		$key = isset( $_POST['key'] ) ? wc_clean( wp_unslash( $_POST['key'] ) ) : '';
		if ( $key === '' || ! WC()->cart->remove_cart_item( $key ) ) {
			throw new Exception( __( 'Could not remove the item.', NH_SC_TD ) );
		}
	}

	/**
	 * Mirror WooCommerce cart shipping calculator, postcode-first.
	 *
	 * @throws Exception On invalid country/postcode.
	 */
	private static function calculate_shipping() {
		WC()->shipping()->reset_shipping();

		$country  = isset( $_POST['calc_shipping_country'] ) ? wc_clean( wp_unslash( $_POST['calc_shipping_country'] ) ) : '';
		$postcode = isset( $_POST['calc_shipping_postcode'] ) ? wc_clean( wp_unslash( $_POST['calc_shipping_postcode'] ) ) : '';
		$state    = isset( $_POST['calc_shipping_state'] ) ? wc_clean( wp_unslash( $_POST['calc_shipping_state'] ) ) : '';
		$city     = isset( $_POST['calc_shipping_city'] ) ? wc_clean( wp_unslash( $_POST['calc_shipping_city'] ) ) : '';

		$allowed = NH_Side_Cart::shipping_countries();
		if ( $country === '' ) {
			$country = NH_Side_Cart::default_shipping_country();
		}
		if ( $country === '' || ! isset( $allowed[ $country ] ) ) {
			throw new Exception( __( 'Please select a country / region.', NH_SC_TD ) );
		}

		if ( $postcode === '' ) {
			throw new Exception( __( 'Please enter a postcode.', NH_SC_TD ) );
		}

		if ( class_exists( 'WC_Validation' ) && ! WC_Validation::is_postcode( $postcode, $country ) ) {
			throw new Exception( __( 'Please enter a valid postcode / ZIP.', NH_SC_TD ) );
		}

		$postcode = wc_format_postcode( $postcode, $country );

		WC()->customer->set_billing_location( $country, $state, $postcode, $city );
		WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();

		wc_add_notice( __( 'Shipping costs updated.', NH_SC_TD ), 'success' );
	}

	private static function choose_method() {
		$posted = isset( $_POST['shipping_method'] ) ? wc_clean( wp_unslash( $_POST['shipping_method'] ) ) : array();
		if ( ! is_array( $posted ) ) {
			$posted = array( $posted );
		}

		$methods = array();
		foreach ( $posted as $index => $method ) {
			$methods[ (int) $index ] = (string) $method;
		}

		if ( empty( $methods ) ) {
			throw new Exception( __( 'Please choose a shipping method.', NH_SC_TD ) );
		}

		WC()->session->set( 'chosen_shipping_methods', $methods );
	}
}
