<?php
/**
 * Quote + add-to-cart AJAX.
 *
 * @package nh-terrace-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_TC_Ajax {

	public static function init() {
		add_action( 'wc_ajax_nh_tc_quote', array( __CLASS__, 'quote' ) );
		add_action( 'wc_ajax_nopriv_nh_tc_quote', array( __CLASS__, 'quote' ) );
		add_action( 'wc_ajax_nh_tc_add_to_cart', array( __CLASS__, 'add_to_cart' ) );
		add_action( 'wc_ajax_nopriv_nh_tc_add_to_cart', array( __CLASS__, 'add_to_cart' ) );
	}

	public static function quote() {
		check_ajax_referer( 'nh_tc', 'security' );

		$settings = NH_TC_Defaults::settings();
		$input    = self::read_input();
		$bom      = NH_TC_Engine::calculate( $input, $settings );

		if ( empty( $bom['ok'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a valid width and length.', NH_TC_TD ),
					'errors'  => $bom['errors'],
				),
				400
			);
		}

		$priced = NH_TC_Catalog::price_bom( $bom, $settings );
		wp_send_json_success( $priced );
	}

	public static function add_to_cart() {
		check_ajax_referer( 'nh_tc', 'security' );

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart is not available.', NH_TC_TD ) ), 500 );
		}

		$settings = NH_TC_Defaults::settings();
		$input    = self::read_input();
		$bom      = NH_TC_Engine::calculate( $input, $settings );

		if ( empty( $bom['ok'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a valid width and length.', NH_TC_TD ),
					'errors'  => $bom['errors'],
				),
				400
			);
		}

		$priced = NH_TC_Catalog::price_bom( $bom, $settings );
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
				array( 'message' => __( 'No matching products were found for this configuration. Check the SKU mapping in Terrace calculator settings.', NH_TC_TD ) ),
				400
			);
		}

		$postcode = isset( $input['postcode'] ) ? wc_format_postcode( wc_clean( $input['postcode'] ), WC()->customer ? WC()->customer->get_shipping_country() : '' ) : '';
		if ( $postcode && WC()->customer ) {
			WC()->customer->set_shipping_postcode( $postcode );
			WC()->customer->set_billing_postcode( $postcode );
			WC()->customer->save();
		}

		$kit_id      = wp_generate_uuid4();
		$added_keys  = array();
		$orig_post   = $_POST;
		$orig_req    = $_REQUEST;

		wc_clear_notices();

		try {
			foreach ( $items as $item ) {
				$request = array(
					'quantity' => (int) $item['qty'],
				);
				if ( ! empty( $item['variation_id'] ) ) {
					$request['variation_id'] = (int) $item['variation_id'];
				}
				foreach ( $item['attributes'] as $key => $value ) {
					$request[ $key ] = $value;
				}
				if ( ! empty( $item['custom_cut'] ) && ! empty( $item['cut'] ) ) {
					$request['nh_custom_cutting'] = '1';
					$request['nh_width_mm']       = (int) $item['cut']['width_mm'];
					$request['nh_length_mm']      = (int) $item['cut']['length_mm'];
				}

				$_POST    = $request;
				$_REQUEST = array_merge( $orig_req, $request );

				$cart_item_data = array(
					'nh_terrace_kit' => array(
						'id'     => $kit_id,
						'width'  => (int) $bom['meta']['width_mm'],
						'length' => (int) $bom['meta']['length_mm'],
						'role'   => $item['role'],
					),
				);

				$key = WC()->cart->add_to_cart(
					(int) $item['product_id'],
					(int) $item['qty'],
					(int) $item['variation_id'],
					self::variation_attributes_for_cart( $item['attributes'] ),
					$cart_item_data
				);

				if ( ! $key ) {
					foreach ( array_reverse( $added_keys ) as $added ) {
						WC()->cart->remove_cart_item( $added );
					}
					$message = __( 'Unable to add one of the kit items to the basket.', NH_TC_TD );
					if ( wc_notice_count( 'error' ) ) {
						$printed = wc_print_notices( true );
						wp_send_json_error(
							array(
								'message'      => $message,
								'notices_html' => $printed,
							),
							400
						);
					}
					wc_add_notice( $message, 'error' );
					wp_send_json_error( array( 'message' => $message, 'notices_html' => wc_print_notices( true ) ), 400 );
				}

				$added_keys[] = $key;
				wc_clear_notices();
			}
		} finally {
			$_POST    = $orig_post;
			$_REQUEST = $orig_req;
		}

		if ( method_exists( WC()->cart, 'calculate_totals' ) ) {
			WC()->cart->calculate_totals();
		}

		$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array() );

		wp_send_json_success(
			array(
				'fragments'    => $fragments,
				'cart_hash'    => WC()->cart ? WC()->cart->get_cart_hash() : '',
				'count'        => count( $added_keys ),
				'cart_url'     => wc_get_cart_url(),
				'notices_html' => '<div class="woocommerce-message" role="alert">' . esc_html__( 'Terrace roof kit added to basket.', NH_TC_TD ) . '</div>',
			)
		);
	}

	/**
	 * @param array<string, string> $attributes
	 * @return array<string, string>
	 */
	private static function variation_attributes_for_cart( array $attributes ) {
		$out = array();
		foreach ( $attributes as $key => $value ) {
			$k         = 0 === strpos( $key, 'attribute_' ) ? $key : 'attribute_' . $key;
			$out[ $k ] = $value;
		}
		return $out;
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
			'width_mm'            => isset( $src['width_mm'] ) ? absint( $src['width_mm'] ) : 0,
			'length_mm'           => isset( $src['length_mm'] ) ? absint( $src['length_mm'] ) : 0,
			'cc_mm'               => isset( $src['cc_mm'] ) ? absint( $src['cc_mm'] ) : 0,
			'construction'        => self::pick( $src, 'construction', array( 'single_slope', 'gable' ), 'single_slope' ),
			'material'            => self::pick( $src, 'material', array( 'multiwall', 'solid' ), 'multiwall' ),
			'thickness'           => isset( $src['thickness'] ) ? absint( $src['thickness'] ) : 10,
			'colour'              => self::pick( $src, 'colour', array( 'clear', 'bronze', 'opal', 'anthracite' ), 'clear' ),
			'connecting_profile'  => self::pick( $src, 'connecting_profile', array( 'clamping', 'clamping_lid', 'h_plastic' ), 'clamping' ),
			'connecting_color'    => self::pick( $src, 'connecting_color', array( 'silver', 'brown', 'anthracite', 'clear', 'bronze' ), 'silver' ),
			'finish_profile'      => self::pick( $src, 'finish_profile', array( 'f_aluminium', 'f_profile', 'u_plastic', 'u_aluminium', 'l_aluminium' ), 'f_aluminium' ),
			'finish_color'        => self::pick( $src, 'finish_color', array( 'silver', 'brown', 'clear', 'bronze' ), 'silver' ),
			'sheet_layout'        => self::pick( $src, 'sheet_layout', array( 'per_cc', 'overlap' ), 'per_cc' ),
			'postcode'            => isset( $src['postcode'] ) ? sanitize_text_field( $src['postcode'] ) : '',
			'discount_pct'        => isset( $src['discount_pct'] ) ? (float) $src['discount_pct'] : 0.0,
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
