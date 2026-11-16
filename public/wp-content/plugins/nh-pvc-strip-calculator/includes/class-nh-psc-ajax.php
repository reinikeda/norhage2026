<?php
/**
 * Quote + add-to-cart AJAX for PVC strip curtains.
 *
 * @package nh-pvc-strip-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_PSC_Ajax {

	public static function init() {
		add_action( 'wc_ajax_nh_psc_quote', array( __CLASS__, 'quote' ) );
		add_action( 'wc_ajax_nopriv_nh_psc_quote', array( __CLASS__, 'quote' ) );
		add_action( 'wc_ajax_nh_psc_add_to_cart', array( __CLASS__, 'add_to_cart' ) );
		add_action( 'wc_ajax_nopriv_nh_psc_add_to_cart', array( __CLASS__, 'add_to_cart' ) );
	}

	public static function quote() {
		check_ajax_referer( 'nh_psc', 'security' );

		$settings = NH_PSC_Defaults::settings();
		$input    = self::read_input();
		$bom      = NH_PSC_Engine::calculate( $input, $settings );

		if ( empty( $bom['ok'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a valid opening width and height.', NH_PSC_TD ),
					'error'   => $bom['error'] ?? 'size',
				),
				400
			);
		}

		wp_send_json_success( NH_PSC_Catalog::price_bom( $bom, $settings ) );
	}

	public static function add_to_cart() {
		check_ajax_referer( 'nh_psc', 'security' );

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart is not available.', NH_PSC_TD ) ), 500 );
		}

		$settings = NH_PSC_Defaults::settings();
		$input    = self::read_input();
		$bom      = NH_PSC_Engine::calculate( $input, $settings );

		if ( empty( $bom['ok'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid opening width and height.', NH_PSC_TD ) ), 400 );
		}

		$priced = NH_PSC_Catalog::price_bom( $bom, $settings );
		$items  = array_values(
			array_filter(
				$priced['items'],
				static function ( $item ) {
					return ! empty( $item['product_id'] ) && empty( $item['error'] );
				}
			)
		);

		if ( ! $items ) {
			wp_send_json_error(
				array( 'message' => __( 'No matching products were found. Map SKUs in PVC strip calculator settings.', NH_PSC_TD ) ),
				400
			);
		}

		$postcode = isset( $input['postcode'] ) ? wc_format_postcode( wc_clean( $input['postcode'] ), WC()->customer ? WC()->customer->get_shipping_country() : '' ) : '';
		if ( $postcode && WC()->customer ) {
			WC()->customer->set_shipping_postcode( $postcode );
			WC()->customer->set_billing_postcode( $postcode );
			WC()->customer->save();
		}

		$kit_id     = wp_generate_uuid4();
		$added_keys = array();
		wc_clear_notices();

		foreach ( $items as $item ) {
			$cart_item_data = array(
				'nh_pvc_strip_kit' => array(
					'id'     => $kit_id,
					'width'  => (int) $bom['meta']['width_mm'],
					'height' => (int) $bom['meta']['height_mm'],
					'role'   => $item['role'],
				),
			);

			$atts = array();
			foreach ( (array) $item['attributes'] as $key => $value ) {
				$k         = 0 === strpos( $key, 'attribute_' ) ? $key : 'attribute_' . $key;
				$atts[ $k ] = $value;
			}

			$key = WC()->cart->add_to_cart(
				(int) $item['product_id'],
				(float) $item['qty'],
				(int) $item['variation_id'],
				$atts,
				$cart_item_data
			);

			if ( ! $key ) {
				foreach ( array_reverse( $added_keys ) as $added ) {
					WC()->cart->remove_cart_item( $added );
				}
				$message = __( 'Unable to add one of the kit items to the basket.', NH_PSC_TD );
				wp_send_json_error( array( 'message' => $message ), 400 );
			}
			$added_keys[] = $key;
			wc_clear_notices();
		}

		if ( method_exists( WC()->cart, 'calculate_totals' ) ) {
			WC()->cart->calculate_totals();
		}

		wp_send_json_success(
			array(
				'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ),
				'cart_hash' => WC()->cart ? WC()->cart->get_cart_hash() : '',
				'count'     => count( $added_keys ),
				'cart_url'  => wc_get_cart_url(),
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function read_input() {
		$src = wp_unslash( $_POST );
		if ( isset( $src['config'] ) && is_string( $src['config'] ) ) {
			$decoded = json_decode( $src['config'], true );
			if ( is_array( $decoded ) ) {
				$src = array_merge( $src, $decoded );
			}
		}

		return array(
			'width_mm'       => isset( $src['width_mm'] ) ? absint( $src['width_mm'] ) : 0,
			'height_mm'      => isset( $src['height_mm'] ) ? absint( $src['height_mm'] ) : 0,
			'mounting'       => self::pick( $src, 'mounting', array( 'face', 'soffit' ), 'face' ),
			'strip_width_mm' => isset( $src['strip_width_mm'] ) ? absint( $src['strip_width_mm'] ) : 200,
			'overlap'        => self::pick( $src, 'overlap', array( 'min', 'med', 'max' ), 'med' ),
			'strip_type'     => self::pick( $src, 'strip_type', array( 'smooth', 'ribbed', 'antistatic', 'frosted' ), 'smooth' ),
			'grade'          => self::pick( $src, 'grade', array( 'standard', 'frost' ), 'standard' ),
			'red_edges'      => ! empty( $src['red_edges'] ),
			'postcode'       => isset( $src['postcode'] ) ? sanitize_text_field( $src['postcode'] ) : '',
		);
	}

	/**
	 * @param array<string, mixed> $src
	 * @param string[]             $allowed
	 */
	private static function pick( array $src, $key, array $allowed, $default ) {
		if ( ! isset( $src[ $key ] ) ) {
			return $default;
		}
		$val = sanitize_key( (string) $src[ $key ] );
		return in_array( $val, $allowed, true ) ? $val : $default;
	}
}
