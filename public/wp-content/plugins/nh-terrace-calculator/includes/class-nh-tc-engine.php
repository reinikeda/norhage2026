<?php
/**
 * Pure geometry / BOM engine. No WooCommerce dependency.
 *
 * Quantities follow the Norhage terrace-roof spreadsheet:
 * - Sheets meet on the centre of each support. A joint leaves a gap (standard
 *   10 mm) for the connecting profile, so each sheet loses half of that gap.
 * - Outer sheets run to the end of the frame, so they are wider by half the
 *   support width plus the half-gap the middle sheets give up.
 * - Sheet length is the frame length plus a drip overhang (standard 50 mm).
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

		$width    = (int) $input['width_mm'];
		$length   = (int) $input['length_mm'];
		$cc       = (int) $input['cc_mm'];
		$thk      = (int) $input['thickness'];
		$layout   = (string) $input['sheet_layout'];
		$const    = (string) $input['construction'];
		$support  = self::support_width( $input, $settings );
		$overhang = self::sheet_overhang( $input, $settings );
		$gap       = self::profile_gap( $settings );
		$max_sheet = self::max_sheet_width( $settings );
		$min_sheet = isset( $settings['min_sheet_mm'] ) ? (int) $settings['min_sheet_mm'] : 100;
		if ( $min_sheet < 1 ) {
			$min_sheet = 100;
		}

		$recommended_cc = self::recommended_cc( $thk, $settings );
		if ( $cc <= 0 ) {
			$cc = isset( $settings['default_cc_mm'] ) ? (int) $settings['default_cc_mm'] : 600;
			if ( $cc <= 0 ) {
				$cc = 600;
			}
		}

		$sheet_length = $length + $overhang;
		$plan         = ( 'overlap' === $layout )
			? self::overlap_plan( $width, $sheet_length, $settings )
			: self::framed_sheet_plan( $width, $length, $cc, $support, $gap, $overhang );

		foreach ( $plan as $sheet ) {
			$cut_w = (int) $sheet['width_mm'];
			if ( $cut_w < $min_sheet ) {
				return array(
					'ok'     => false,
					'errors' => array( 'sheet_narrow' ),
				);
			}
			if ( $cut_w > $max_sheet ) {
				return array(
					'ok'     => false,
					'errors' => array( 'sheet_width' ),
				);
			}
		}

		$sheets = self::group_sheets( $plan );

		$sheet_count = 0;
		foreach ( $sheets as $sheet ) {
			$sheet_count += (int) $sheet['qty'];
		}

		$connecting_count = max( 0, $sheet_count - 1 );
		$beam_count       = ( 'gable' === $const ) ? $sheet_count + 2 : $sheet_count + 1;

		$finish_runs = ( 'gable' === $const )
			? array( $sheet_length, $sheet_length, $width, $width )
			: array( $sheet_length, $sheet_length, $width );

		$connecting_stock_mm = self::smallest_stock( $sheet_length, $settings['profile_stock_mm'] );
		$screws_raw          = $connecting_count * (int) ceil( $connecting_stock_mm / max( 1, (int) $settings['screw_spacing_mm'] ) );
		$pack_size           = max( 1, (int) $settings['screw_pack_size'] );
		$screw_packs         = (int) ceil( $screws_raw / $pack_size );

		$end_width_mm = 0;
		foreach ( $sheets as $sheet ) {
			$end_width_mm += (int) $sheet['width_mm'] * (int) $sheet['qty'];
		}

		$perimeter_mm = 2 * ( $width + $sheet_length );
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
				'length_mm' => $sheet_length,
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
				'support_mm'           => $support,
				'overhang_mm'          => $overhang,
				'profile_gap_mm'       => $gap,
				'sheet_length_mm'      => $sheet_length,
				'side_extra_mm'        => self::side_extra_mm( $support, $gap ),
				'standard_sheet_mm'    => $max_sheet,
				'sheet_plan'           => $plan,
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

		$support = self::support_width( $input, $settings );
		$min_s   = isset( $settings['min_support_mm'] ) ? (int) $settings['min_support_mm'] : 20;
		$max_s   = isset( $settings['max_support_mm'] ) ? (int) $settings['max_support_mm'] : 200;
		if ( $support < $min_s || $support > $max_s ) {
			$errors[] = 'support';
		}

		$overhang = self::sheet_overhang( $input, $settings );
		$max_oh   = isset( $settings['max_overhang_mm'] ) ? (int) $settings['max_overhang_mm'] : 300;
		if ( $overhang < 0 || $overhang > $max_oh ) {
			$errors[] = 'overhang';
		}

		return $errors;
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $settings
	 */
	public static function support_width( array $input, array $settings ) {
		$support = isset( $input['support_mm'] ) ? (int) $input['support_mm'] : 0;
		if ( $support <= 0 ) {
			$support = isset( $settings['default_support_mm'] ) ? (int) $settings['default_support_mm'] : 50;
		}
		if ( $support <= 0 ) {
			$support = 50;
		}
		return $support;
	}

	/**
	 * Missing overhang uses the standard drip allowance. An explicit 0 is kept.
	 *
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $settings
	 */
	public static function sheet_overhang( array $input, array $settings ) {
		if ( ! array_key_exists( 'overhang_mm', $input ) || null === $input['overhang_mm'] || '' === $input['overhang_mm'] ) {
			$overhang = isset( $settings['default_overhang_mm'] ) ? (int) $settings['default_overhang_mm'] : 50;
			return $overhang >= 0 ? $overhang : 50;
		}
		return (int) $input['overhang_mm'];
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function profile_gap( array $settings ) {
		$gap = isset( $settings['profile_gap_mm'] ) ? (int) $settings['profile_gap_mm'] : 10;
		return $gap >= 0 ? $gap : 10;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function max_sheet_width( array $settings ) {
		$max = isset( $settings['standard_sheet_width_mm'] ) ? (int) $settings['standard_sheet_width_mm'] : 2100;
		return $max > 0 ? $max : 2100;
	}

	/**
	 * How much wider a full outer sheet is than a full middle sheet.
	 */
	public static function side_extra_mm( $support, $gap ) {
		$support  = (int) $support;
		$gap      = (int) $gap;
		$half_gap = intdiv( $gap, 2 );
		$half_b   = intdiv( $support, 2 );
		return $half_b + ( $gap - $half_gap );
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
	 * One entry per sheet. Joints sit on beam centres.
	 *
	 * The distance between the outer beam centres is width − support. That span
	 * is split into bays of CC (the last bay takes the remainder).
	 * A middle sheet is CC minus the joint gap. An outer sheet reaches the
	 * outer face of the end beam and is only shortened on its joint side.
	 *
	 * @return array<int, array{width_mm:int,length_mm:int,edge:string}>
	 */
	public static function framed_sheet_plan( $width, $length, $cc, $support, $gap, $overhang ) {
		$width    = (int) $width;
		$support  = max( 0, (int) $support );
		$gap      = max( 0, (int) $gap );
		$cc       = max( 1, (int) $cc );
		$length_mm = (int) $length + max( 0, (int) $overhang );
		$half_gap = intdiv( $gap, 2 );
		$half_l   = intdiv( $support, 2 );
		$half_r   = $support - $half_l;
		$inner    = $width - $support;

		if ( $inner <= $cc ) {
			return array(
				array(
					'width_mm'  => $width,
					'length_mm' => $length_mm,
					'edge'      => 'side',
				),
			);
		}

		$bays = (int) ceil( $inner / $cc );
		$full = $bays - 1;
		$plan = array();

		for ( $i = 0; $i < $bays; $i++ ) {
			$bay = ( $i < $full ) ? $cc : ( $inner - ( $full * $cc ) );
			if ( 0 === $i ) {
				$cut  = $bay + $half_l - $half_gap;
				$edge = 'side';
			} elseif ( $i === $full ) {
				$cut  = $bay + $half_r - $half_gap;
				$edge = 'side';
			} else {
				$cut  = $bay - $gap;
				$edge = 'middle';
			}
			$plan[] = array(
				'width_mm'  => (int) $cut,
				'length_mm' => $length_mm,
				'edge'      => $edge,
			);
		}

		return $plan;
	}

	/**
	 * @param array<int, array{width_mm:int,length_mm:int}> $plan
	 * @return array<int, array{width_mm:int,length_mm:int,qty:int}>
	 */
	public static function group_sheets( array $plan ) {
		$groups = array();
		foreach ( $plan as $sheet ) {
			$key = (int) $sheet['width_mm'] . 'x' . (int) $sheet['length_mm'];
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'width_mm'  => (int) $sheet['width_mm'],
					'length_mm' => (int) $sheet['length_mm'],
					'qty'       => 0,
				);
			}
			$groups[ $key ]['qty']++;
		}
		return array_values( $groups );
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<int, array{width_mm:int,length_mm:int,edge:string}>
	 */
	public static function overlap_plan( $width, $sheet_length, array $settings ) {
		$count = self::overlap_sheet_count( $width, $settings );
		$std   = (int) $settings['standard_sheet_width_mm'];
		$plan  = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$plan[] = array(
				'width_mm'  => $std,
				'length_mm' => (int) $sheet_length,
				'edge'      => 'middle',
			);
		}
		return $plan;
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
