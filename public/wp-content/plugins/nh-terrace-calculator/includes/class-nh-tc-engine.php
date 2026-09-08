<?php
/**
 * Pure geometry / BOM engine. No WooCommerce dependency.
 *
 * Quantities follow the Norhage terrace-roof spreadsheet:
 * - Per-CC layout: one sheet per rafter spacing, width = CC.
 * - Clamping profiles between sheets, one stock length covering the run.
 * - F-profiles on the three free edges of a single-slope roof (2 × length + drip edge).
 * - Wall profiles cover the wall width in 2.2 m pieces.
 * - Screws: one every 200 mm along each connecting profile, sold in packs of 50.
 *
 * @package nh-terrace-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_TC_Engine {

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	public static function calculate( array $input, array $settings ) {
		$errors = self::validate( $input, $settings );
		if ( $errors ) {
			return array(
				'ok'     => false,
				'errors' => $errors,
			);
		}

		$width  = (int) $input['width_mm'];
		$length = (int) $input['length_mm'];
		$cc     = (int) $input['cc_mm'];
		$thk    = (int) $input['thickness'];
		$layout = (string) $input['sheet_layout'];
		$const  = (string) $input['construction'];

		$recommended_cc = self::recommended_cc( $thk, $settings );
		if ( $cc <= 0 ) {
			$cc = isset( $settings['default_cc_mm'] ) ? (int) $settings['default_cc_mm'] : 600;
			if ( $cc <= 0 ) {
				$cc = 600;
			}
		}

		$sheets = ( 'overlap' === $layout )
			? self::overlap_sheets( $width, $length, $settings )
			: self::per_cc_sheets( $width, $length, $cc );

		$sheet_count = 0;
		foreach ( $sheets as $sheet ) {
			$sheet_count += (int) $sheet['qty'];
		}

		$connecting_count = max( 0, $sheet_count - 1 );
		$beam_count       = ( 'gable' === $const ) ? $sheet_count + 2 : $sheet_count + 1;

		$finish_runs = ( 'gable' === $const )
			? array( $length, $length, $width, $width )
			: array( $length, $length, $width );

		$connecting_stock_mm = self::smallest_stock( $length, $settings['profile_stock_mm'] );
		$screws_raw          = $connecting_count * (int) ceil( $connecting_stock_mm / max( 1, (int) $settings['screw_spacing_mm'] ) );
		$pack_size           = max( 1, (int) $settings['screw_pack_size'] );
		$screw_packs         = (int) ceil( $screws_raw / $pack_size );

		$end_width_mm = 0;
		foreach ( $sheets as $sheet ) {
			$end_width_mm += (int) $sheet['width_mm'] * (int) $sheet['qty'];
		}

		$perimeter_mm = 2 * ( $width + $length );
		$gasket_m     = (int) ceil( $perimeter_mm / 1000 );
		$silicon_qty  = max( 1, (int) ceil( ( $perimeter_mm / 1000 ) / max( 1, (float) $settings['silicon_metres_per_tube'] ) ) );
		$tape_width   = self::tape_width_mm( $thk );
		$tape_rolls   = max( 1, (int) ceil( $end_width_mm / max( 1, (int) $settings['tape_roll_mm'] ) ) );
		$wall_qty     = (int) ceil( $width / max( 1, (int) $settings['wall_profile_mm'] ) );

		$finish_packed = array();
		foreach ( $finish_runs as $run_mm ) {
			foreach ( self::pack_finish_run( $run_mm ) as $slug => $qty ) {
				$finish_packed[ $slug ] = ( $finish_packed[ $slug ] ?? 0 ) + $qty;
			}
		}

		$lines = array();

		foreach ( $sheets as $sheet ) {
			$lines[] = array(
				'role' => 'sheet',
				'qty'  => (int) $sheet['qty'],
				'cut'  => array(
					'width_mm'  => (int) $sheet['width_mm'],
					'length_mm' => (int) $sheet['length_mm'],
				),
			);
		}

		if ( $screw_packs > 0 ) {
			$lines[] = array(
				'role'      => 'screw',
				'qty'       => $screw_packs,
				'qty_raw'   => $screws_raw,
				'pack_size' => $pack_size,
				'attrs'     => array(
					'dimensions' => self::screw_size( $thk, $settings ),
				),
			);
		}

		if ( $connecting_count > 0 ) {
			$lines[] = array(
				'role'      => 'connecting',
				'qty'       => $connecting_count,
				'length_mm' => $length,
				'stock_mm'  => $connecting_stock_mm,
				'attrs'     => array(
					'length' => self::mm_to_length_slug( $connecting_stock_mm ),
				),
			);
		}

		if ( $finish_packed ) {
			$lines[] = array(
				'role'   => 'finish',
				'packed' => $finish_packed,
				'runs'   => $finish_runs,
				'attrs'  => array(
					'thickness' => $thk . '-mm',
				),
			);
		}

		if ( 'gable' === $const ) {
			$lines[] = array(
				'role' => 'ridge',
				'qty'  => $wall_qty,
			);
		} else {
			$lines[] = array(
				'role' => 'wall',
				'qty'  => $wall_qty,
			);
		}

		$lines[] = array(
			'role'         => 'vent_tape',
			'qty'          => $tape_rolls,
			'cover_mm'     => $end_width_mm,
			'tape_width_mm'=> $tape_width,
			'attrs'        => array(
				'width'  => $tape_width . '-mm',
				'length' => '5-m',
			),
		);

		$lines[] = array(
			'role'         => 'iso_tape',
			'qty'          => $tape_rolls,
			'cover_mm'     => $end_width_mm,
			'tape_width_mm'=> $tape_width,
			'attrs'        => array(
				'width'  => $tape_width . '-mm',
				'length' => '5-m',
			),
		);

		if ( $connecting_count > 0 && 'h_plastic' !== (string) $input['connecting_profile'] ) {
			$lines[] = array(
				'role' => 'end_cap',
				'qty'  => $connecting_count * 2,
			);
		}

		$lines[] = array(
			'role'    => 'gasket',
			'qty'     => $gasket_m,
			'unit'    => 'm',
			'metres'  => $gasket_m,
		);

		$lines[] = array(
			'role' => 'silicon',
			'qty'  => $silicon_qty,
		);

		return array(
			'ok'   => true,
			'meta' => array(
				'width_mm'             => $width,
				'length_mm'            => $length,
				'cc_mm'                => $cc,
				'recommended_cc_mm'    => $recommended_cc,
				'thickness'            => $thk,
				'construction'         => $const,
				'material'             => (string) $input['material'],
				'colour'               => (string) $input['colour'],
				'connecting_profile'   => (string) $input['connecting_profile'],
				'connecting_color'     => (string) $input['connecting_color'],
				'finish_profile'       => (string) $input['finish_profile'],
				'finish_color'         => (string) $input['finish_color'],
				'sheet_layout'         => $layout,
				'sheet_count'          => $sheet_count,
				'overlap_sheet_count'  => self::overlap_sheet_count( $width, $settings ),
				'beam_count'           => $beam_count,
				'connecting_count'     => $connecting_count,
				'screws_raw'           => $screws_raw,
				'tape_width_mm'        => $tape_width,
				'gasket_m'             => $gasket_m,
				'postcode'             => isset( $input['postcode'] ) ? (string) $input['postcode'] : '',
				'discount_pct'         => isset( $input['discount_pct'] ) ? (float) $input['discount_pct'] : 0.0,
			),
			'lines' => $lines,
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $settings
	 * @return string[]
	 */
	public static function validate( array $input, array $settings ) {
		$errors = array();
		$width  = isset( $input['width_mm'] ) ? (int) $input['width_mm'] : 0;
		$length = isset( $input['length_mm'] ) ? (int) $input['length_mm'] : 0;
		$min_w  = (int) $settings['min_width_mm'];
		$max_w  = (int) $settings['max_width_mm'];
		$min_l  = (int) $settings['min_length_mm'];
		$max_l  = (int) $settings['max_length_mm'];

		if ( $width < $min_w || $width > $max_w ) {
			$errors[] = 'width';
		}
		if ( $length < $min_l || $length > $max_l ) {
			$errors[] = 'length';
		}

		$thk = isset( $input['thickness'] ) ? (int) $input['thickness'] : 0;
		if ( $thk <= 0 ) {
			$errors[] = 'thickness';
		}

		$cc = isset( $input['cc_mm'] ) ? (int) $input['cc_mm'] : 0;
		if ( $cc > 0 && ( $cc < 200 || $cc > 2000 ) ) {
			$errors[] = 'cc';
		}

		return $errors;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function recommended_cc( $thickness, array $settings ) {
		$key = (string) (int) $thickness;
		$map = $settings['recommended_cc'];
		if ( isset( $map[ $key ] ) ) {
			return (int) $map[ $key ];
		}
		return 600;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function screw_size( $thickness, array $settings ) {
		$key = (string) (int) $thickness;
		$map = $settings['screw_size'];
		return isset( $map[ $key ] ) ? (string) $map[ $key ] : '5-mm-x-60-mm';
	}

	public static function tape_width_mm( $thickness ) {
		$thk = (int) $thickness;
		if ( $thk <= 10 ) {
			return 25;
		}
		if ( $thk <= 25 ) {
			return 38;
		}
		return 60;
	}

	/**
	 * @return array<int, array{width_mm:int,length_mm:int,qty:int}>
	 */
	public static function per_cc_sheets( $width, $length, $cc ) {
		$cc    = max( 1, (int) $cc );
		$count = (int) ceil( $width / $cc );
		if ( $count < 1 ) {
			$count = 1;
		}

		$full = $count - 1;
		$last = (int) ( $width - ( $full * $cc ) );
		if ( $last <= 0 || $last === $cc ) {
			return array(
				array(
					'width_mm'  => $cc,
					'length_mm' => (int) $length,
					'qty'       => $count,
				),
			);
		}

		$out = array();
		if ( $full > 0 ) {
			$out[] = array(
				'width_mm'  => $cc,
				'length_mm' => (int) $length,
				'qty'       => $full,
			);
		}
		$out[] = array(
			'width_mm'  => $last,
			'length_mm' => (int) $length,
			'qty'       => 1,
		);
		return $out;
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<int, array{width_mm:int,length_mm:int,qty:int}>
	 */
	public static function overlap_sheets( $width, $length, array $settings ) {
		$count = self::overlap_sheet_count( $width, $settings );
		$std   = (int) $settings['standard_sheet_width_mm'];
		return array(
			array(
				'width_mm'  => $std,
				'length_mm' => (int) $length,
				'qty'       => $count,
			),
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function overlap_sheet_count( $width, array $settings ) {
		$std      = max( 1, (int) $settings['standard_sheet_width_mm'] );
		$overlap  = max( 0, (int) $settings['overlap_mm'] );
		$effective = max( 1, $std - $overlap );
		return max( 1, (int) ceil( $width / $effective ) );
	}

	/**
	 * Round a run up to the next metre, then split into 3 m + 2 m (+ 1 m) pieces.
	 * Matches the spreadsheet F-profile split (4.2 m and 4.7 m → 2 m + 3 m).
	 *
	 * @return array<string, int> length slug => qty
	 */
	public static function pack_finish_run( $needed_mm ) {
		$needed = max( 0, (int) $needed_mm );
		$target = (int) ( ceil( $needed / 1000 ) * 1000 );
		if ( $target < $needed ) {
			$target += 1000;
		}
		if ( $target <= 0 ) {
			return array();
		}

		$counts = array();
		while ( $target >= 3000 ) {
			$counts['3-m'] = ( $counts['3-m'] ?? 0 ) + 1;
			$target       -= 3000;
		}
		while ( $target >= 2000 ) {
			$counts['2-m'] = ( $counts['2-m'] ?? 0 ) + 1;
			$target       -= 2000;
		}
		while ( $target >= 1500 ) {
			$counts['15-m'] = ( $counts['15-m'] ?? 0 ) + 1;
			$target        -= 1500;
		}
		while ( $target >= 1000 ) {
			$counts['1-m'] = ( $counts['1-m'] ?? 0 ) + 1;
			$target       -= 1000;
		}
		return $counts;
	}

	/**
	 * @param int[] $stock
	 */
	public static function smallest_stock( $needed_mm, array $stock ) {
		$needed = (int) $needed_mm;
		$best   = 0;
		foreach ( $stock as $mm ) {
			$mm = (int) $mm;
			if ( $mm >= $needed && ( 0 === $best || $mm < $best ) ) {
				$best = $mm;
			}
		}
		if ( $best > 0 ) {
			return $best;
		}
		return $stock ? (int) max( $stock ) : $needed;
	}

	public static function mm_to_length_slug( $mm ) {
		$map = array(
			1000 => '1-m',
			1050 => '105-m',
			1500 => '15-m',
			2000 => '2-m',
			2100 => '21-m',
			2200 => '22-m',
			3000 => '3-m',
			4000 => '4-m',
			5000 => '5-m',
			6000 => '6-m',
		);
		$mm = (int) $mm;
		return isset( $map[ $mm ] ) ? $map[ $mm ] : (string) $mm;
	}

	/**
	 * Parse a WooCommerce length/width term name to millimetres.
	 * "1,5 m" → 1500, "5 m" → 5000, "25 mm" → 25.
	 */
	public static function parse_size_to_mm( $name ) {
		$raw = strtolower( trim( (string) $name ) );
		$raw = str_replace( ',', '.', $raw );
		$raw = preg_replace( '/\s+/', '', $raw );
		if ( preg_match( '/^([\d.]+)mm/', $raw, $m ) ) {
			return (int) round( (float) $m[1] );
		}
		if ( preg_match( '/^([\d.]+)m/', $raw, $m ) ) {
			return (int) round( (float) $m[1] * 1000 );
		}
		return 0;
	}
}
