<?php
/**
 * Default SKUs, stock lengths and geometry constants.
 *
 * SKUs match the shared Norhage catalog (same codes on .no / .se / .dk / …).
 * Override any value from WooCommerce → Terrace calculator.
 *
 * @package nh-terrace-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_TC_Defaults {

	const OPTION_KEY = 'nh_tc_settings';
	const META_ENABLED = '_nh_terrace_calculator';
	const META_HIDE_ATC = '_nh_terrace_hide_atc';

	/**
	 * @return array<string, mixed>
	 */
	public static function all() {
		return array(
			'min_width_mm'           => 600,
			'max_width_mm'           => 12000,
			'min_length_mm'          => 500,
			'max_length_mm'          => 7000,
			'step_mm'                => 10,
			'default_width_mm'       => 4200,
			'default_length_mm'      => 4700,
			'overlap_mm'             => 100,
			'standard_sheet_width_mm'=> 2100,
			'wall_profile_mm'        => 2200,
			'screw_spacing_mm'       => 200,
			'screw_pack_size'        => 50,
			'silicon_metres_per_tube'=> 10,
			'tape_roll_mm'           => 5000,
			'show_postcode'          => 0,
			'default_cc_mm'          => 600,
			'display_mode'           => 'all_products',
			'display_product_ids'    => array(),
			'profile_stock_mm'       => array( 1000, 1500, 2000, 3000, 4000, 5000, 6000 ),
			'recommended_cc'         => array(
				'2'  => 400,
				'3'  => 400,
				'4'  => 400,
				'5'  => 450,
				'6'  => 500,
				'8'  => 550,
				'10' => 600,
				'12' => 650,
				'16' => 700,
				'20' => 800,
				'25' => 800,
				'32' => 900,
				'40' => 1000,
			),
			'screw_size'             => array(
				'2'  => '5-mm-x-50-mm',
				'3'  => '5-mm-x-50-mm',
				'4'  => '5-mm-x-50-mm',
				'5'  => '5-mm-x-50-mm',
				'6'  => '5-mm-x-50-mm',
				'8'  => '5-mm-x-60-mm',
				'10' => '5-mm-x-60-mm',
				'12' => '5-mm-x-60-mm',
				'16' => '6-mm-x-80-mm',
				'20' => '6-mm-x-80-mm',
				'25' => '6-mm-x-80-mm',
				'32' => '6-mm-x-80-mm',
				'40' => '6-mm-x-80-mm',
			),
			'sheets'                 => self::sheet_skus(),
			'connecting'             => array(
				'clamping' => array(
					'silver' => '70371704C',
					'clear'  => '',
					'brown'  => '70371613',
				),
				'clamping_lid' => array(
					'silver' => '',
					'clear'  => '',
					'brown'  => '70371813',
				),
				'h_plastic' => array(
					'silver' => '',
					'clear'  => '70421501C',
					'brown'  => '70421502C',
				),
			),
			'finish'                 => array(
				'f_aluminium' => array(
					'silver' => '70381404C',
					'brown'  => '70381413C',
					'clear'  => '',
				),
				'f_profile' => array(
					'silver' => '70381404C',
					'brown'  => '70381413C',
					'clear'  => '',
				),
				'u_plastic' => array(
					'silver' => '',
					'brown'  => '',
					'clear'  => '70391501C',
				),
				'u_aluminium' => array(
					'silver' => '703914',
					'brown'  => '',
					'clear'  => '',
				),
				'l_aluminium' => array(
					'silver' => '70431404',
					'brown'  => '',
					'clear'  => '',
				),
			),
			'hardware'               => array(
				'screws'      => '9058',
				'wall'        => '70492305',
				'ridge'       => '70492305',
				'vent_tape'   => '804420',
				'iso_tape'    => '804419',
				'end_cap'     => array(
					'silver' => '704113',
					'brown'  => '704105',
					'grey'   => '704113',
				),
				'gasket'      => '704615',
				'silicon'     => '8056',
			),
		);
	}

	/**
	 * Thicknesses offered in the form (2–40 mm). Empty admin slots stay hidden on the front.
	 *
	 * @return int[]
	 */
	public static function thicknesses() {
		return array( 2, 3, 4, 5, 6, 8, 10, 12, 16, 20, 25, 32, 40 );
	}

	/**
	 * @return string[]
	 */
	public static function colours() {
		return array( 'clear', 'bronze', 'opal', 'anthracite' );
	}

	/**
	 * Custom-cut sheet SKUs. Every thickness/colour slot exists so admin can pick a product.
	 *
	 * @return array<string, array<string, array<string, string>>>
	 */
	public static function sheet_skus() {
		$empty = array(
			'clear'      => '',
			'bronze'     => '',
			'opal'       => '',
			'anthracite' => '',
		);
		$multi = array();
		$solid = array();
		foreach ( self::thicknesses() as $thk ) {
			$multi[ (string) $thk ] = $empty;
			$solid[ (string) $thk ] = $empty;
		}

		$multi['4']['clear']       = '4010401C';
		$multi['6']['clear']       = '4010601C';
		$multi['6']['bronze']      = '4010602C';
		$multi['6']['opal']        = '4010603C';
		$multi['8']['clear']       = '4010801C';
		$multi['8']['bronze']      = '4010802C';
		$multi['10']['clear']      = '4011001C';
		$multi['10']['bronze']     = '4011002C';
		$multi['10']['opal']       = '4011003C';
		$multi['10']['anthracite'] = '4011006C';
		$multi['16']['clear']      = '4011601C';
		$multi['16']['bronze']     = '4011602C';
		$multi['16']['opal']       = '4011603C';
		$multi['20']['clear']      = '4002001C';
		$multi['20']['opal']       = '4012003C';

		$solid['2']['clear']  = '3010201C';
		$solid['3']['clear']  = '3010301C';
		$solid['3']['bronze'] = '3050302C';
		$solid['3']['opal']   = '3010303C';
		$solid['4']['clear']  = '3010401C';
		$solid['4']['bronze'] = '3050402C';
		$solid['5']['clear']  = '3010501C';
		$solid['5']['bronze'] = '3050502C';
		$solid['6']['clear']  = '3010601C';
		$solid['6']['bronze'] = '3050602C';
		$solid['8']['clear']  = '3010801C';
		$solid['8']['bronze'] = '3050802C';
		$solid['10']['clear'] = '3011001C';

		return array(
			'multiwall' => $multi,
			'solid'     => $solid,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return self::merge_deep( self::all(), $saved );
	}

	/**
	 * @param array<string, mixed> $base
	 * @param array<string, mixed> $over
	 * @return array<string, mixed>
	 */
	public static function merge_deep( $base, $over ) {
		foreach ( $over as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && self::is_assoc( $base[ $key ] ) ) {
				$base[ $key ] = self::merge_deep( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * @param array<mixed> $arr
	 */
	private static function is_assoc( array $arr ) {
		if ( array() === $arr ) {
			return true;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}
}
