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
			'default_support_mm'     => 50,
			'min_support_mm'         => 20,
			'max_support_mm'         => 200,
			'profile_gap_mm'         => 10,
			'default_overhang_mm'    => 50,
			'max_overhang_mm'        => 300,
			'min_sheet_mm'           => 100,
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
			'standard_sheets'        => self::standard_sheet_catalog(),
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
	 * Standard plates the customer can buy and cut.
	 *
	 * Each entry is the variable parent SKU from the Norhage catalog. Widths are
	 * the sold widths, and each width lists only the lengths sold for that width.
	 * A 1050 mm multiwall width is the half of a 2100 mm sheet. Other widths are
	 * stock sizes and are not halved. When Arla and Polygal both sell a thickness
	 * and colour, the Arla parent is stored. 16 mm clear is split by channel shape
	 * because 900, 980 and 1200 mm belong to the 5-wall sheet.
	 *
	 * Each width maps a stock length in millimetres to that variation's SKU.
	 * The SKU is empty until it is entered in the calculator settings.
	 *
	 * @return array<string, array<string, array<string, array<string, array{sku:string, channel:string, widths:array<string, array<string, string>}>}>>>
	 */
	public static function standard_sheet_catalog() {
		$to_6 = array( 1000, 2000, 3000, 4000, 6000 );
		$to_7 = array( 1000, 2000, 3000, 4000, 5000, 7000 );
		$solid = array( 1520, 3050 );

		return array(
			'multiwall' => array(
				'4'  => array(
					'clear' => array(
						'stock' => self::stock_sheet( '40101210060401P', 'stock', self::both_widths( $to_6 ) ),
					),
				),
				'6'  => array(
					'clear'  => array(
						'stock' => self::stock_sheet( '40101210060601P', 'stock', self::both_widths( $to_6 ) ),
					),
					'bronze' => array(
						'stock' => self::stock_sheet( '40101210060602P', 'stock', self::both_widths( $to_6 ) ),
					),
					'opal'   => array(
						'stock' => self::stock_sheet( '40101210070603P', 'stock', self::both_widths( $to_7 ) ),
					),
				),
				'8'  => array(
					'clear'  => array(
						'stock' => self::stock_sheet( '40101210070801P', 'stock', self::both_widths( $to_7 ) ),
					),
					'bronze' => array(
						'stock' => self::stock_sheet( '40101210060802P', 'stock', self::both_widths( $to_6 ) ),
					),
				),
				'10' => array(
					'clear'      => array(
						'stock' => self::stock_sheet(
							'40101210061001P',
							'stock',
							array(
								'1050' => $to_6,
								'1250' => $to_6,
								'2100' => $to_6,
							)
						),
					),
					'bronze'     => array(
						'stock' => self::stock_sheet( '40101210061002P', 'stock', self::both_widths( $to_6 ) ),
					),
					'opal'       => array(
						'stock' => self::stock_sheet(
							'40101210061003P',
							'stock',
							array(
								'1050' => $to_6,
								'1250' => array( 2000 ),
								'2100' => $to_6,
							)
						),
					),
					'anthracite' => array(
						'stock' => self::stock_sheet( '40101210071006P', 'stock', self::both_widths( $to_7 ) ),
					),
				),
				'16' => array(
					'clear'  => array(
						'6w' => self::stock_sheet( '40106210071601P', '6w', self::both_widths( $to_6 ) ),
						'5x' => self::stock_sheet(
							'40106210071601P',
							'5x',
							array(
								'900'  => $to_7,
								'980'  => $to_7,
								'1050' => $to_6,
								'1200' => array( 1000, 2000, 4000, 5000, 7500 ),
								'2100' => $to_6,
							)
						),
						'3w' => self::stock_sheet(
							'40106210071601P',
							'3w',
							array(
								'980' => array( 5000 ),
							)
						),
					),
					'bronze' => array(
						'stock' => self::stock_sheet( '40108210061602P', 'stock', self::both_widths( $to_6 ) ),
					),
					'opal'   => array(
						'stock' => self::stock_sheet(
							'40106210071603P',
							'stock',
							array(
								'1050' => $to_7,
								'1200' => array( 1000, 2000, 3000 ),
								'2100' => $to_7,
							)
						),
					),
				),
				'20' => array(
					'clear' => array(
						'stock' => self::stock_sheet( '40171210062001P', 'stock', self::both_widths( $to_6 ) ),
					),
					'opal'  => array(
						'stock' => self::stock_sheet( '40171210072003P', 'stock', self::both_widths( $to_7 ) ),
					),
				),
				'25' => array(
					'clear' => array(
						'stock' => self::stock_sheet( '40110210072501P', 'stock', self::both_widths( $to_7 ) ),
					),
					'opal'  => array(
						'stock' => self::stock_sheet( '40110210072503P', 'stock', self::both_widths( $to_7 ) ),
					),
				),
				'32' => array(
					'clear' => array(
						'stock' => self::stock_sheet( '40110210073201P', 'stock', self::both_widths( $to_7 ) ),
					),
					'opal'  => array(
						'stock' => self::stock_sheet( '40110210073203P', 'stock', self::both_widths( $to_7 ) ),
					),
				),
				'40' => array(
					'clear' => array(
						'stock' => self::stock_sheet(
							'40274123074001P',
							'stock',
							array(
								'1230' => $to_7,
							)
						),
					),
				),
			),
			'solid'     => array(
				'2'  => array(
					'clear' => array(
						'stock' => self::stock_sheet( '3010201', 'stock', array( '2050' => $solid ) ),
					),
				),
				'3'  => array(
					'clear'  => array(
						'stock' => self::stock_sheet( '3010301', 'stock', array( '2050' => $solid ) ),
					),
					'bronze' => array(
						'stock' => self::stock_sheet( '3010302', 'stock', array( '2050' => $solid ) ),
					),
					'opal'   => array(
						'stock' => self::stock_sheet( '3010303', 'stock', array( '2050' => $solid ) ),
					),
				),
				'4'  => array(
					'clear'  => array(
						'stock' => self::stock_sheet( '3010401', 'stock', array( '2050' => $solid ) ),
					),
					'bronze' => array(
						'stock' => self::stock_sheet( '3010402', 'stock', array( '2050' => $solid ) ),
					),
				),
				'5'  => array(
					'clear'  => array(
						'stock' => self::stock_sheet( '3010501', 'stock', array( '2050' => $solid ) ),
					),
					'bronze' => array(
						'stock' => self::stock_sheet( '3010502', 'stock', array( '2050' => $solid ) ),
					),
				),
				'6'  => array(
					'clear'  => array(
						'stock' => self::stock_sheet( '3010601', 'stock', array( '2050' => $solid ) ),
					),
					'bronze' => array(
						'stock' => self::stock_sheet( '3010602', 'stock', array( '2050' => $solid ) ),
					),
				),
				'8'  => array(
					'clear'  => array(
						'stock' => self::stock_sheet( '3010801', 'stock', array( '2050' => $solid ) ),
					),
					'bronze' => array(
						'stock' => self::stock_sheet( '3010802', 'stock', array( '2050' => $solid ) ),
					),
				),
				'10' => array(
					'clear' => array(
						'stock' => self::stock_sheet( '3011001', 'stock', array( '2050' => $solid ) ),
					),
				),
			),
		);
	}

	/**
	 * Shortest stock length that covers the needed run. When every stock length
	 * is shorter, the longest one is returned. Zero when there is no length.
	 *
	 * @param int[] $lengths
	 */
	public static function covering_stock_length( array $lengths, $need_mm ) {
		$clean = array();
		foreach ( $lengths as $length ) {
			$length = (int) $length;
			if ( $length > 0 ) {
				$clean[] = $length;
			}
		}
		sort( $clean, SORT_NUMERIC );
		$need = (int) $need_mm;
		foreach ( $clean as $length ) {
			if ( $length >= $need ) {
				return $length;
			}
		}
		return $clean ? $clean[ count( $clean ) - 1 ] : 0;
	}

	/**
	 * Channel groups for one thickness and colour. Empty when that sheet has no standard sizes.
	 *
	 * @return array<string, array{sku:string, channel:string, widths:array<string, array<string, string>>}>
	 */
	public static function standard_sheet_groups( $material, $thickness, $colour ) {
		$catalog = self::standard_sheet_catalog();
		$material = (string) $material;
		$thickness = (string) (int) $thickness;
		$colour = (string) $colour;
		if ( ! isset( $catalog[ $material ][ $thickness ][ $colour ] ) || ! is_array( $catalog[ $material ][ $thickness ][ $colour ] ) ) {
			return array();
		}
		return $catalog[ $material ][ $thickness ][ $colour ];
	}

	/**
	 * Lengths sold for one standard width, in millimetres.
	 *
	 * @return int[]
	 */
	public static function standard_sheet_lengths( $material, $thickness, $colour, $width_mm, $channel = 'stock' ) {
		$groups = self::standard_sheet_groups( $material, $thickness, $colour );
		$channel = (string) $channel;
		if ( ! isset( $groups[ $channel ]['widths'][ (string) (int) $width_mm ] ) ) {
			return array();
		}
		return array_map( 'intval', array_keys( self::length_sku_map( $groups[ $channel ]['widths'][ (string) (int) $width_mm ] ) ) );
	}

	/**
	 * Variation SKU for one stock size. Empty when that size has no SKU yet.
	 */
	public static function standard_sheet_variation_sku( $material, $thickness, $colour, $width_mm, $length_mm, $channel = 'stock' ) {
		$groups  = self::standard_sheet_groups( $material, $thickness, $colour );
		$channel = (string) $channel;
		$width   = (string) (int) $width_mm;
		if ( ! isset( $groups[ $channel ]['widths'][ $width ] ) ) {
			return '';
		}
		$map    = self::length_sku_map( $groups[ $channel ]['widths'][ $width ] );
		$length = (string) (int) $length_mm;
		return isset( $map[ $length ] ) ? $map[ $length ] : '';
	}

	/**
	 * Turn either a length list or a length-to-SKU map into length => SKU.
	 *
	 * @param mixed $lengths
	 * @return array<string, string>
	 */
	public static function length_sku_map( $lengths ) {
		if ( ! is_array( $lengths ) ) {
			return array();
		}
		$out = array();
		if ( self::is_assoc( $lengths ) ) {
			foreach ( $lengths as $length => $sku ) {
				$mm = (int) $length;
				if ( $mm <= 0 ) {
					continue;
				}
				$out[ (string) $mm ] = is_scalar( $sku ) ? trim( (string) $sku ) : '';
			}
		} else {
			foreach ( $lengths as $length ) {
				$mm = (int) $length;
				if ( $mm > 0 ) {
					$out[ (string) $mm ] = '';
				}
			}
		}
		ksort( $out, SORT_NUMERIC );
		return $out;
	}

	/**
	 * Normalize saved standard-sheet settings so every width is a length-to-SKU map.
	 *
	 * @param mixed $catalog
	 * @return array<string, mixed>
	 */
	public static function normalize_standard_sheets( $catalog ) {
		if ( ! is_array( $catalog ) ) {
			return array();
		}
		foreach ( $catalog as $material => $thicknesses ) {
			if ( ! is_array( $thicknesses ) ) {
				continue;
			}
			foreach ( $thicknesses as $thickness => $colours ) {
				if ( ! is_array( $colours ) ) {
					continue;
				}
				foreach ( $colours as $colour => $groups ) {
					if ( ! is_array( $groups ) ) {
						continue;
					}
					foreach ( $groups as $channel => $group ) {
						if ( ! is_array( $group ) || empty( $group['widths'] ) || ! is_array( $group['widths'] ) ) {
							continue;
						}
						foreach ( $group['widths'] as $width => $lengths ) {
							$catalog[ $material ][ $thickness ][ $colour ][ $channel ]['widths'][ (string) (int) $width ] = self::length_sku_map( $lengths );
						}
					}
				}
			}
		}
		return $catalog;
	}

	/**
	 * @param int[] $lengths
	 * @return array<string, int[]>
	 */
	private static function both_widths( array $lengths ) {
		return array(
			'1050' => $lengths,
			'2100' => $lengths,
		);
	}

	/**
	 * @param array<string, int[]|array<string, string>> $widths
	 * @return array{sku:string, channel:string, widths:array<string, array<string, string>>}
	 */
	private static function stock_sheet( $sku, $channel, array $widths ) {
		$clean = array();
		foreach ( $widths as $width => $lengths ) {
			$clean[ (string) (int) $width ] = self::length_sku_map( $lengths );
		}
		return array(
			'sku'     => (string) $sku,
			'channel' => (string) $channel,
			'widths'  => $clean,
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
	private static function is_assoc( array $arr ) {
		if ( array() === $arr ) {
			return true;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}
}
