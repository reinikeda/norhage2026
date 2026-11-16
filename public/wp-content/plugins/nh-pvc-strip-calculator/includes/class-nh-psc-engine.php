<?php
/**
 * Pure BOM engine for PVC strip curtains (Plastena rules).
 *
 * @package nh-pvc-strip-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_PSC_Engine {

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	public static function calculate( array $input, array $settings ) {
		$mount = isset( $input['mounting'] ) ? (string) $input['mounting'] : 'face';
		if ( ! in_array( $mount, array( 'face', 'soffit' ), true ) ) {
			$mount = 'face';
		}

		$width  = (int) ( $input['width_mm'] ?? 0 );
		$height = (int) ( $input['height_mm'] ?? 0 );
		$min_w  = (int) ( $settings['min_width_mm'] ?? 600 );
		$max_w  = (int) ( $settings['max_width_mm'] ?? 12000 );
		$min_h  = (int) ( $settings['min_height_mm'] ?? 800 );
		$max_h  = (int) ( $settings['max_height_mm'] ?? 8000 );

		if ( $width < $min_w || $width > $max_w || $height < $min_h || $height > $max_h ) {
			return array(
				'ok'    => false,
				'error' => 'size',
				'meta'  => array(
					'width_mm'  => $width,
					'height_mm' => $height,
				),
			);
		}

		$strip_w = (int) ( $input['strip_width_mm'] ?? self::recommend_strip_width( $height, $settings ) );
		if ( ! in_array( $strip_w, array( 200, 300, 400 ), true ) ) {
			$strip_w = self::recommend_strip_width( $height, $settings );
		}

		$overlap = isset( $input['overlap'] ) ? (string) $input['overlap'] : 'med';
		if ( ! in_array( $overlap, array( 'min', 'med', 'max' ), true ) ) {
			$overlap = 'med';
		}

		$type = isset( $input['strip_type'] ) ? (string) $input['strip_type'] : 'smooth';
		if ( ! in_array( $type, array( 'smooth', 'ribbed', 'antistatic', 'frosted' ), true ) ) {
			$type = 'smooth';
		}

		$grade = isset( $input['grade'] ) ? (string) $input['grade'] : 'standard';
		if ( ! in_array( $grade, array( 'standard', 'frost' ), true ) ) {
			$grade = 'standard';
		}

		$dims     = self::calc_dims( $mount, $width, $height, $strip_w, $settings );
		$calc_w   = (int) $dims['width_mm'];
		$calc_h   = (int) $dims['height_mm'];
		if ( $calc_h < 100 ) {
			return array(
				'ok'    => false,
				'error' => 'height',
				'meta'  => array(
					'width_mm'  => $width,
					'height_mm' => $height,
					'mounting'  => $mount,
				),
			);
		}

		$ot = self::overlap_fraction( $strip_w, $overlap, $settings );
		// Plastena / Extruflex simplified strip count: ceil(width × (1 + overlap) / strip_width).
		$strip_count = max( 1, (int) ceil( $calc_w * ( 1 + $ot ) / $strip_w ) );

		$thickness   = self::strip_thickness( $strip_w, $settings );
		$metres_each = $calc_h / 1000;
		$total_m     = round( $strip_count * $metres_each, 3 );
		$area_m2     = round( ( $calc_w * $calc_h ) / 1000000, 3 );

		$rail_stock = max( 500, (int) ( $settings['rail_stock_mm'] ?? 1500 ) );
		$rail_qty   = max( 1, (int) ceil( $calc_w / $rail_stock ) );
		$hanger_qty = $strip_count * max( 1, (int) ( $settings['hanger_per_strip'] ?? 1 ) );

		$lines = array(
			array(
				'role'            => 'strip',
				'qty'             => $total_m,
				'unit'            => 'm',
				'strip_count'     => $strip_count,
				'strip_width_mm'  => $strip_w,
				'strip_thickness' => $thickness,
				'length_mm'       => $calc_h,
				'attrs'           => array(
					'size'  => $strip_w . 'x' . $thickness,
					'type'  => $type,
					'grade' => $grade,
				),
			),
			array(
				'role'     => 'rail',
				'qty'      => $rail_qty,
				'cover_mm' => $calc_w,
				'stock_mm' => $rail_stock,
			),
			array(
				'role'           => 'hanger',
				'qty'            => $hanger_qty,
				'strip_width_mm' => $strip_w,
			),
		);

		return array(
			'ok'    => true,
			'meta'  => array(
				'mounting'             => $mount,
				'width_mm'             => $width,
				'height_mm'            => $height,
				'calc_width_mm'        => $calc_w,
				'calc_height_mm'       => $calc_h,
				'strip_width_mm'       => $strip_w,
				'strip_thickness_mm'   => $thickness,
				'overlap'              => $overlap,
				'overlap_pct'          => (int) round( $ot * 100 ),
				'strip_type'           => $type,
				'grade'                => $grade,
				'strip_count'          => $strip_count,
				'total_metres'         => $total_m,
				'area_m2'              => $area_m2,
				'recommended_width_mm' => self::recommend_strip_width( $height, $settings ),
				'rail_qty'             => $rail_qty,
				'hanger_qty'           => $hanger_qty,
			),
			'lines' => $lines,
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array{width_mm:int,height_mm:int}
	 */
	public static function calc_dims( $mount, $width, $height, $strip_w, array $settings ) {
		$width  = (int) $width;
		$height = (int) $height;
		$strip  = (string) (int) $strip_w;

		if ( 'soffit' === $mount ) {
			$deduct = (int) ( $settings['soffit_height_deduct_mm'] ?? 45 );
			return array(
				'width_mm'  => $width,
				'height_mm' => $height - $deduct,
			);
		}

		$extras = isset( $settings['face_width_extra'] ) && is_array( $settings['face_width_extra'] )
			? $settings['face_width_extra']
			: array( '200' => 80, '300' => 90, '400' => 100 );
		$extra  = isset( $extras[ $strip ] ) ? (int) $extras[ $strip ] : 80;
		$add_h  = (int) ( $settings['face_height_extra_mm'] ?? 30 );

		return array(
			'width_mm'  => $width + $extra,
			'height_mm' => $height + $add_h,
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function overlap_fraction( $strip_w, $overlap, array $settings ) {
		$key  = (string) (int) $strip_w;
		$rows = isset( $settings['overlap_pct'][ $key ] ) && is_array( $settings['overlap_pct'][ $key ] )
			? $settings['overlap_pct'][ $key ]
			: array( 'min' => 0.36, 'med' => 0.77, 'max' => 0.98 );
		$level = isset( $rows[ $overlap ] ) ? (float) $rows[ $overlap ] : (float) ( $rows['med'] ?? 0.77 );
		return max( 0.05, min( 0.99, $level ) );
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function strip_thickness( $strip_w, array $settings ) {
		$key = (string) (int) $strip_w;
		$map = isset( $settings['strip_thickness'] ) && is_array( $settings['strip_thickness'] )
			? $settings['strip_thickness']
			: array( '200' => 2, '300' => 3, '400' => 4 );
		return isset( $map[ $key ] ) ? (int) $map[ $key ] : 2;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function recommend_strip_width( $height_mm, array $settings = array() ) {
		unset( $settings );
		return NH_PSC_Defaults::recommend_strip_width( $height_mm );
	}
}
