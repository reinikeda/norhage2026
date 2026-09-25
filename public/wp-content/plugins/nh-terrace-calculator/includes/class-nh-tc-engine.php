<?php
/**
 * Pure geometry / BOM engine. No WooCommerce dependency.
 *
 * Quantities follow the Norhage terrace-roof spreadsheet:
 * - Sheets meet on the centre of a support. A joint leaves a gap (standard
 *   10 mm) for the connecting profile, so each sheet loses half of that gap.
 * - A connecting profile sits on every rafter. Each bay is its own sheet.
 *   Outer sheets run to the end of the frame, so they are wider by half the
 *   support width plus the half-gap the middle sheets give up.
 * - Multiwall pieces are cut from 2100 mm stock. Solid pieces are cut from a
 *   2050 × 3050 mm blank, turned either way, and split along the length when
 *   the run is longer than the blank.
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
		$material  = isset( $input['material'] ) ? (string) $input['material'] : 'multiwall';
		$max_sheet = self::max_sheet_width( $settings );
		$min_sheet = isset( $settings['min_sheet_mm'] ) ? (int) $settings['min_sheet_mm'] : 100;
		if ( $min_sheet < 1 ) {
			$min_sheet = 100;
		}
		$blank = self::solid_blank( $settings );

		$advice = self::recommended_support( $material, $thk, $width, $support );
		if ( $cc <= 0 ) {
			$cc = (int) $advice['cc_mm'];
		}

		$sheet_length = $length + $overhang;
		if ( 'overlap' === $layout ) {
			$across = self::overlap_plan( $width, $sheet_length, $settings );
		} else {
			$across = self::framed_sheet_plan( $width, $length, $cc, $support, $gap, $overhang );
		}

		foreach ( $across as $sheet ) {
			$cut_w = (int) $sheet['width_mm'];
			if ( $cut_w < $min_sheet ) {
				return array(
					'ok'     => false,
					'errors' => array( 'sheet_narrow' ),
				);
			}
			if ( 'solid' === $material ) {
				if ( $cut_w > (int) $blank['long_mm'] ) {
					return array(
						'ok'     => false,
						'errors' => array( 'solid_sheet' ),
					);
				}
			} elseif ( $cut_w > $max_sheet ) {
				return array(
					'ok'     => false,
					'errors' => array( 'sheet_width' ),
				);
			}
		}

		$pieces        = array();
		$length_pieces = 1;
		foreach ( $across as $sheet ) {
			$lengths = array( (int) $sheet['length_mm'] );
			if ( 'solid' === $material ) {
				$lengths = self::solid_length_pieces( (int) $sheet['width_mm'], (int) $sheet['length_mm'], $settings );
				if ( null === $lengths ) {
					return array(
						'ok'     => false,
						'errors' => array( 'solid_sheet' ),
					);
				}
			}
			$length_pieces = max( $length_pieces, count( $lengths ) );
			foreach ( $lengths as $piece_length ) {
				$pieces[] = array(
					'width_mm'  => (int) $sheet['width_mm'],
					'length_mm' => (int) $piece_length,
					'edge'      => $sheet['edge'],
				);
			}
		}

		$plan   = $across;
		$sheets = self::group_sheets( $pieces );

		$supply = isset( $input['sheet_supply'] ) ? (string) $input['sheet_supply'] : 'custom';
		$choice = null;
		if ( 'standard' === $supply ) {
			$choice = self::standard_sheet_choice( $input, $settings, $sheet_length );
			if ( ! $choice ) {
				return array(
					'ok'     => false,
					'errors' => array( 'standard_sheet' ),
				);
			}
		}
		$stock_cover = $choice
			? self::stock_sheet_qty( $width, $sheet_length, (int) $choice['width_mm'], (int) $choice['length_mm'] )
			: null;

		$sheet_count = 0;
		foreach ( $sheets as $sheet ) {
			$sheet_count += (int) $sheet['qty'];
		}

		$connecting_count = max( 0, count( $across ) - 1 );
		$beam_count       = ( 'overlap' === $layout )
			? ( ( 'gable' === $const ) ? $sheet_count + 2 : $sheet_count + 1 )
			: self::beam_count( $width, $cc, $support, $const );

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

		if ( $choice && $stock_cover ) {
			$lines[] = array(
				'role'       => 'sheet',
				'qty'        => (int) $stock_cover['qty'],
				'stock'      => 1,
				'sku'        => (string) $choice['sku'],
				'parent_sku' => (string) $choice['parent_sku'],
				'cut'        => array(
					'width_mm'  => (int) $choice['width_mm'],
					'length_mm' => (int) $choice['length_mm'],
				),
			);
		} else {
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

		if ( 'multiwall' === $material ) {
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
		}

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
				'standard_sheet_mm'    => 'solid' === $material ? (int) $blank['long_mm'] : $max_sheet,
				'blank_short_mm'       => 'solid' === $material ? (int) $blank['short_mm'] : $max_sheet,
				'blank_long_mm'        => 'solid' === $material ? (int) $blank['long_mm'] : 0,
				'length_pieces'        => $length_pieces,
				'sheet_plan'           => $plan,
				'recommended_cc_mm'    => (int) $advice['cc_mm'],
				'recommended_min_mm'   => (int) $advice['min_mm'],
				'recommended_max_mm'   => (int) $advice['max_mm'],
				'rafter_count'         => self::rafter_count( $width, $cc, $support ),
				'thickness'            => $thk,
				'construction'         => $const,
				'material'             => (string) $input['material'],
				'colour'               => (string) $input['colour'],
				'connecting_profile'   => (string) $input['connecting_profile'],
				'connecting_color'     => (string) $input['connecting_color'],
				'finish_profile'       => (string) $input['finish_profile'],
				'finish_color'         => (string) $input['finish_color'],
				'sheet_layout'         => $layout,
				'sheet_count'          => ( $stock_cover ) ? (int) $stock_cover['qty'] : $sheet_count,
				'sheet_supply'         => $choice ? 'standard' : 'custom',
				'stock_width_mm'       => $choice ? (int) $choice['width_mm'] : 0,
				'stock_length_mm'      => $choice ? (int) $choice['length_mm'] : 0,
				'stock_across'         => $stock_cover ? (int) $stock_cover['across'] : 0,
				'stock_along'          => $stock_cover ? (int) $stock_cover['along'] : 0,
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
	 * How many whole stock sheets cover the frame. Sheets tile across the width
	 * and, when one stock length is shorter than the run, along the length too.
	 *
	 * @return array{across:int,along:int,qty:int}
	 */
	public static function stock_sheet_qty( $frame_width, $need_length, $stock_width, $stock_length ) {
		$across = (int) ceil( max( 1, (int) $frame_width ) / max( 1, (int) $stock_width ) );
		$along  = (int) ceil( max( 1, (int) $need_length ) / max( 1, (int) $stock_length ) );
		return array(
			'across' => $across,
			'along'  => $along,
			'qty'    => $across * $along,
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $settings
	 * @return array{channel:string,width_mm:int,length_mm:int,sku:string,parent_sku:string}|null
	 */
	private static function standard_sheet_choice( array $input, array $settings, $need_mm ) {
		$catalog = ( isset( $settings['standard_sheets'] ) && is_array( $settings['standard_sheets'] ) ) ? $settings['standard_sheets'] : null;
		if ( is_array( $catalog ) && class_exists( 'NH_TC_Defaults' ) ) {
			return NH_TC_Defaults::pick_standard_sheet(
				$catalog,
				isset( $input['material'] ) ? $input['material'] : 'multiwall',
				isset( $input['thickness'] ) ? $input['thickness'] : 0,
				isset( $input['colour'] ) ? $input['colour'] : '',
				isset( $input['stock_channel'] ) ? $input['stock_channel'] : '',
				isset( $input['stock_width_mm'] ) ? $input['stock_width_mm'] : 0,
				$need_mm
			);
		}
		$width  = isset( $input['stock_width_mm'] ) ? (int) $input['stock_width_mm'] : 0;
		$length = isset( $input['stock_length_mm'] ) ? (int) $input['stock_length_mm'] : 0;
		if ( $width < 1 || $length < 1 ) {
			return null;
		}
		return array(
			'channel'    => isset( $input['stock_channel'] ) ? (string) $input['stock_channel'] : 'stock',
			'width_mm'   => $width,
			'length_mm'  => $length,
			'sku'        => isset( $input['stock_sku'] ) ? (string) $input['stock_sku'] : '',
			'parent_sku' => '',
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
	 * A missing or empty flag means a connecting profile on every beam.
	 *
	 * @param array<string, mixed> $input
	 */
	public static function joints_on_every_beam( array $input ) {
		if ( ! array_key_exists( 'joint_every_beam', $input ) || null === $input['joint_every_beam'] || '' === $input['joint_every_beam'] ) {
			return true;
		}
		return ! in_array( (string) $input['joint_every_beam'], array( '0', 'false', 'no' ), true );
	}

	/**
	 * Supports along the slope: one more than the number of bays. A gable adds the ridge beam.
	 */
	public static function beam_count( $width, $cc, $support, $construction ) {
		$inner = (int) $width - max( 0, (int) $support );
		$cc    = max( 1, (int) $cc );
		$bays  = ( $inner <= $cc ) ? 1 : (int) ceil( $inner / $cc );
		return ( 'gable' === (string) $construction ) ? $bays + 2 : $bays + 1;
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
	 * Solid blanks can be turned, so either side may be the width.
	 *
	 * @param array<string, mixed> $settings
	 * @return array{short_mm:int,long_mm:int}
	 */
	public static function solid_blank( array $settings ) {
		$short = 2050;
		$long  = 3050;
		if ( isset( $settings['solid_sheet_mm'] ) && is_array( $settings['solid_sheet_mm'] ) ) {
			$a = isset( $settings['solid_sheet_mm'][0] ) ? (int) $settings['solid_sheet_mm'][0] : 0;
			$b = isset( $settings['solid_sheet_mm'][1] ) ? (int) $settings['solid_sheet_mm'][1] : 0;
			if ( $a > 0 && $b > 0 ) {
				$short = min( $a, $b );
				$long  = max( $a, $b );
			}
		}
		return array(
			'short_mm' => $short,
			'long_mm'  => $long,
		);
	}

	/**
	 * Split a solid run so every piece fits on a 2050 × 3050 mm sheet, either way up.
	 *
	 * @param array<string, mixed> $settings
	 * @return int[]|null Null when the cut is wider than the longer side.
	 */
	public static function solid_length_pieces( $width, $length, array $settings ) {
		$blank  = self::solid_blank( $settings );
		$width  = (int) $width;
		$length = max( 1, (int) $length );
		if ( $width > (int) $blank['long_mm'] ) {
			return null;
		}
		$max_len = ( $width <= (int) $blank['short_mm'] ) ? (int) $blank['long_mm'] : (int) $blank['short_mm'];
		if ( $length <= $max_len ) {
			return array( $length );
		}
		$count = (int) ceil( $length / $max_len );
		$base  = intdiv( $length, $count );
		$extra = $length % $count;
		$parts = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$parts[] = $base + ( $i < $extra ? 1 : 0 );
		}
		return $parts;
	}

	/**
	 * Recommended centre spacing, in millimetres, by material and thickness.
	 *
	 * @return array<string, array<string, int[]>>
	 */
	public static function support_range_tables() {
		return array(
			'multiwall' => array(
				'4'  => array( 350, 400 ),
				'6'  => array( 400, 500 ),
				'10' => array( 500, 600 ),
				'16' => array( 700, 800 ),
				'20' => array( 800, 1000 ),
				'25' => array( 800, 1000 ),
				'32' => array( 1000, 1200 ),
				'40' => array( 1000, 1200 ),
			),
			'solid'     => array(
				'2'  => array( 250, 350 ),
				'3'  => array( 350, 450 ),
				'4'  => array( 450, 550 ),
				'5'  => array( 550, 650 ),
				'6'  => array( 650, 750 ),
				'8'  => array( 750, 900 ),
				'10' => array( 900, 1100 ),
			),
		);
	}

	/**
	 * The listed range for this thickness, or the next thinner listed sheet.
	 *
	 * @return array{min_mm:int,max_mm:int}
	 */
	public static function support_range( $material, $thickness ) {
		$tables   = self::support_range_tables();
		$material = isset( $tables[ $material ] ) ? (string) $material : 'multiwall';
		$table    = $tables[ $material ];
		$chosen   = null;
		foreach ( $table as $thk => $range ) {
			if ( (int) $thk <= (int) $thickness ) {
				$chosen = $range;
			}
		}
		if ( null === $chosen ) {
			$chosen = reset( $table );
		}
		return array(
			'min_mm' => (int) $chosen[0],
			'max_mm' => (int) $chosen[1],
		);
	}

	/**
	 * Even rafter spacing for this roof, kept inside the recommended range.
	 *
	 * @return array{min_mm:int,max_mm:int,cc_mm:int,bays:int,rafters:int}
	 */
	public static function recommended_support( $material, $thickness, $width, $support ) {
		$range = self::support_range( $material, $thickness );
		$inner = max( 1, (int) $width - max( 0, (int) $support ) );
		$max   = max( 1, (int) $range['max_mm'] );
		if ( $inner <= $max ) {
			$bays = 1;
			$cc   = $inner;
		} else {
			$bays = (int) ceil( $inner / $max );
			// Ceil keeps every full bay inside the range and stops a 1 mm remainder
			// from opening an extra bay in the framed sheet plan.
			$cc = (int) ceil( $inner / $bays );
			if ( $cc > $max ) {
				++$bays;
				$cc = (int) ceil( $inner / $bays );
			}
		}
		if ( $cc < 1 ) {
			$cc = $max;
		}
		return array(
			'min_mm'  => (int) $range['min_mm'],
			'max_mm'  => $max,
			'cc_mm'   => (int) $cc,
			'bays'    => (int) $bays,
			'rafters' => (int) $bays + 1,
		);
	}

	/**
	 * Supports across the roof, including both ends.
	 */
	public static function rafter_count( $width, $cc, $support ) {
		$inner = max( 0, (int) $width - max( 0, (int) $support ) );
		$cc    = max( 1, (int) $cc );
		$bays  = ( $inner <= $cc ) ? 1 : (int) ceil( $inner / $cc );
		return $bays + 1;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function recommended_cc( $thickness, array $settings ) {
		unset( $settings );
		$range = self::support_range( 'multiwall', $thickness );
		return (int) $range['max_mm'];
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
	 * Span as many bays as the stock width allows. Joints stay on beam centres.
	 *
	 * From the left edge, each sheet runs to the furthest beam that still keeps
	 * the cut within the standard width. If that leaves a sliver at the end,
	 * the last joint moves back one beam at a time until the end piece is wide
	 * enough, or until it cannot move without making the previous piece too narrow.
	 *
	 * @return array<int, array{width_mm:int,length_mm:int,edge:string}>
	 */
	public static function stock_sheet_plan( $width, $length, $cc, $support, $gap, $overhang, $max_sheet, $min_sheet = 100 ) {
		$width     = (int) $width;
		$support   = max( 0, (int) $support );
		$gap       = max( 0, (int) $gap );
		$cc        = max( 1, (int) $cc );
		$max_sheet = max( 1, (int) $max_sheet );
		$min_sheet = max( 1, (int) $min_sheet );
		$length_mm = (int) $length + max( 0, (int) $overhang );
		$half_gap  = intdiv( $gap, 2 );
		$half_l    = intdiv( $support, 2 );
		$inner     = $width - $support;

		if ( $inner <= $cc ) {
			return array(
				array(
					'width_mm'  => $width,
					'length_mm' => $length_mm,
					'edge'      => 'side',
				),
			);
		}

		$bays    = (int) ceil( $inner / $cc );
		$centers = array();
		for ( $i = 0; $i < $bays; $i++ ) {
			$centers[] = $half_l + ( $i * $cc );
		}
		$centers[] = $half_l + $inner;

		$sheet_width = static function ( $from, $to ) use ( $centers, $width, $gap, $half_gap, $bays ) {
			$start = ( 0 === (int) $from ) ? 0 : ( $centers[ (int) $from ] + ( $gap - $half_gap ) );
			$end   = ( (int) $to === $bays ) ? $width : ( $centers[ (int) $to ] - $half_gap );
			return (int) ( $end - $start );
		};

		$segments = array();
		$from     = 0;
		$guard    = 0;
		while ( $from < $bays && $guard < 100 ) {
			++$guard;
			$best = null;
			for ( $to = $from + 1; $to <= $bays; $to++ ) {
				if ( $sheet_width( $from, $to ) <= $max_sheet ) {
					$best = $to;
				} else {
					break;
				}
			}
			if ( null === $best ) {
				$segments[] = array( $from, $from + 1 );
				$from       = $from + 1;
				continue;
			}
			$segments[] = array( $from, $best );
			$from       = $best;
		}

		$relax = 0;
		while ( $relax < 40 && count( $segments ) >= 2 ) {
			++$relax;
			$last_i = count( $segments ) - 1;
			$prev   = $segments[ $last_i - 1 ];
			$last   = $segments[ $last_i ];
			if ( $sheet_width( $last[0], $last[1] ) >= $min_sheet ) {
				break;
			}
			if ( $prev[1] - 1 <= $prev[0] ) {
				break;
			}
			$joint  = $prev[1] - 1;
			$prev_w = $sheet_width( $prev[0], $joint );
			if ( $prev_w < $min_sheet || $prev_w > $max_sheet ) {
				break;
			}
			$segments[ $last_i - 1 ] = array( $prev[0], $joint );
			$segments[ $last_i ]     = array( $joint, $last[1] );
		}

		$plan = array();
		$last_i = count( $segments ) - 1;
		foreach ( $segments as $index => $segment ) {
			$edge = ( 0 === $index || $index === $last_i ) ? 'side' : 'middle';
			$plan[] = array(
				'width_mm'  => $sheet_width( $segment[0], $segment[1] ),
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
