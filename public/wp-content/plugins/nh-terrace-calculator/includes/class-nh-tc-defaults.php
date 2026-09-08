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
			'show_discount'          => 0,
			'show_postcode'          => 1,
			'profile_stock_mm'       => array( 1000, 1500, 2000, 3000, 4000, 5000, 6000 ),
			'recommended_cc'         => array(
				'4'  => 400,
				'6'  => 500,
				'8'  => 550,
				'10' => 600,
				'16' => 700,
				'20' => 800,
			),
			'screw_size'             => array(
				'4'  => '5-mm-x-50-mm',
				'6'  => '5-mm-x-50-mm',
				'8'  => '5-mm-x-60-mm',
				'10' => '5-mm-x-60-mm',
				'16' => '6-mm-x-80-mm',
				'20' => '6-mm-x-80-mm',
			),
			'sheets'                 => self::sheet_skus(),
			'connecting'             => array(
				'clamping' => array(
					'silver'     => '70371704C',
					'brown'      => '70371613',
					'anthracite' => '70371806',
				),
				'h_plastic' => array(
					'clear'  => '70421501C',
					'bronze' => '70421502C',
				),
			),
			'finish'                 => array(
				'f_profile' => array(
					'silver' => '70381404C',
					'brown'  => '70381413C',
				),
				'u_plastic' => array(
					'clear'  => '70391501C',
					'bronze' => '70391502C',
				),
				'u_aluminium' => array(
					'silver' => '703914',
				),
				'l_aluminium' => array(
					'silver' => '70431404',
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
	 * Custom-cut multiwall sheet SKUs (…C suffix).
	 *
	 * @return array<string, array<string, array<string, string>>>
	 */
	public static function sheet_skus() {
		return array(
			'multiwall' => array(
				'4'  => array( 'clear' => '4010401C' ),
				'6'  => array(
					'clear'  => '4010601C',
					'bronze' => '4010602C',
					'opal'   => '4010603C',
				),
				'8'  => array(
					'clear'  => '4010801C',
					'bronze' => '4010802C',
				),
				'10' => array(
					'clear'      => '4011001C',
					'bronze'     => '4011002C',
					'opal'       => '4011003C',
					'anthracite' => '4011006C',
				),
				'16' => array(
					'clear'  => '4011601C',
					'bronze' => '4011602C',
					'opal'   => '4011603C',
				),
				'20' => array(
					'clear' => '4002001C',
					'opal'  => '4012003C',
				),
			),
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
