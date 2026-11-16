<?php
/**
 * Defaults, option key, product meta.
 *
 * Rules mirror Plastena PVC strip calculator (e-plastena.lt/skaiciuokle):
 * face/soffit opening allowances, strip size vs height guidance, Extruflex-style
 * min/medium/max overlap percentages used by that family of calculators.
 *
 * @package nh-pvc-strip-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_PSC_Defaults {

	const OPTION_KEY   = 'nh_psc_settings';
	const META_ENABLED = '_nh_psc_enabled';
	const META_HIDE_ATC = '_nh_psc_hide_atc';

	/**
	 * @return array<string, mixed>
	 */
	public static function all() {
		return array(
			'min_width_mm'          => 600,
			'max_width_mm'          => 12000,
			'min_height_mm'         => 800,
			'max_height_mm'         => 8000,
			'step_mm'               => 10,
			'default_width_mm'      => 2000,
			'default_height_mm'     => 2200,
			'soffit_height_deduct_mm' => 45,
			'face_height_extra_mm'  => 30,
			'face_width_extra'      => array(
				'200' => 80,
				'300' => 90,
				'400' => 100,
			),
			'strip_thickness'       => array(
				'200' => 2,
				'300' => 3,
				'400' => 4,
			),
			// Extruflex / industry overlap fractions (Plastena min / medium / max).
			'overlap_pct'           => array(
				'200' => array(
					'min' => 0.36,
					'med' => 0.77,
					'max' => 0.98,
				),
				'300' => array(
					'min' => 0.36,
					'med' => 0.63,
					'max' => 0.91,
				),
				'400' => array(
					'min' => 0.57,
					'med' => 0.77,
					'max' => 0.98,
				),
			),
			'rail_stock_mm'         => 1500,
			'hanger_per_strip'      => 1,
			'show_postcode'         => 0,
			'display_mode'          => 'flagged',
			'display_product_ids'   => array(),
			'strips'                => array(
				// size => type => grade => product id / SKU
				'200' => array(
					'smooth'     => array( 'standard' => '', 'frost' => '' ),
					'ribbed'     => array( 'standard' => '', 'frost' => '' ),
					'antistatic' => array( 'standard' => '', 'frost' => '' ),
					'frosted'    => array( 'standard' => '', 'frost' => '' ),
				),
				'300' => array(
					'smooth'     => array( 'standard' => '', 'frost' => '' ),
					'ribbed'     => array( 'standard' => '', 'frost' => '' ),
					'antistatic' => array( 'standard' => '', 'frost' => '' ),
					'frosted'    => array( 'standard' => '', 'frost' => '' ),
				),
				'400' => array(
					'smooth'     => array( 'standard' => '', 'frost' => '' ),
					'ribbed'     => array( 'standard' => '', 'frost' => '' ),
					'antistatic' => array( 'standard' => '', 'frost' => '' ),
					'frosted'    => array( 'standard' => '', 'frost' => '' ),
				),
			),
			'hardware'              => array(
				'rail'     => '',
				'hanger'   => array(
					'200' => '',
					'300' => '',
					'400' => '',
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
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && self::is_assoc( $base[ $key ] ) && self::is_assoc( $value ) ) {
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
	public static function is_assoc( $arr ) {
		if ( ! is_array( $arr ) || ! $arr ) {
			return false;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}

	/**
	 * Recommended strip width (mm) for a clear opening height.
	 *
	 * @param int $height_mm
	 */
	public static function recommend_strip_width( $height_mm ) {
		$h = (int) $height_mm;
		if ( $h < 2500 ) {
			return 200;
		}
		if ( $h < 4000 ) {
			return 300;
		}
		return 400;
	}
}
