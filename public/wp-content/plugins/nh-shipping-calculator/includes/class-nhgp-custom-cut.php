<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NHGP_Custom_Cut {

	/* ------------------------------------------------------------
	 * Cart/meta keys
	 * ------------------------------------------------------------ */

	// NEW primary flag used by your theme
	const FLAG_KEY          = 'nh_custom_cutting';

	// Legacy flag used by older plugin/theme versions
	const LEGACY_FLAG_KEY   = 'nh_custom_mode';

	// Dimension keys (match your theme + current JS)
	const WIDTH_KEY         = 'nh_width_mm';
	const HEIGHT_KEY        = 'nh_length_mm';

	// ALT height key (some theme versions)
	const HEIGHT_KEY_ALT    = 'nh_height_mm';

	/* ------------------------------------------------------------
	 * Check if item is custom-cut
	 * ------------------------------------------------------------ */
	public static function is_custom_item( $item, $product, $cs ) {

		// 1) Theme's structured custom size: nh_custom_size[width_mm/length_mm OR height_mm]
		if ( ! empty( $item['nh_custom_size'] ) && is_array( $item['nh_custom_size'] ) ) {
			$w = (int) ( $item['nh_custom_size']['width_mm']  ?? 0 );
			$h = (int) ( $item['nh_custom_size']['length_mm'] ?? ( $item['nh_custom_size']['height_mm'] ?? 0 ) );
			if ( $w > 0 && $h > 0 ) {
				return true;
			}
		}

		// 2) Flat dimension keys on cart item (nh_width_mm / nh_length_mm OR nh_height_mm)
		$w_item = isset( $item[ self::WIDTH_KEY ] ) ? (float) $item[ self::WIDTH_KEY ] : 0;

		$h_item = 0;
		if ( isset( $item[ self::HEIGHT_KEY ] ) ) {
			$h_item = (float) $item[ self::HEIGHT_KEY ];
		} elseif ( isset( $item[ self::HEIGHT_KEY_ALT ] ) ) {
			$h_item = (float) $item[ self::HEIGHT_KEY_ALT ];
		}

		if ( $w_item > 0 && $h_item > 0 ) {
			return true;
		}

		// 3) Cart item flag (new + legacy)
		if ( ! empty( $item[ self::FLAG_KEY ] ) || ! empty( $item[ self::LEGACY_FLAG_KEY ] ) ) {
			return true;
		}

		// 4) Product meta flags (new + legacy, with/without underscore)
		if ( $product ) {
			// new
			if ( (int) $product->get_meta( '_' . self::FLAG_KEY, true ) === 1 ) return true;
			if ( (int) $product->get_meta( self::FLAG_KEY, true ) === 1 ) return true;

			// legacy
			if ( (int) $product->get_meta( '_' . self::LEGACY_FLAG_KEY, true ) === 1 ) return true;
			if ( (int) $product->get_meta( self::LEGACY_FLAG_KEY, true ) === 1 ) return true;
		}

		return false;
	}

	/* ------------------------------------------------------------
	 * Extract dimensions (width mm, height mm)
	 * ------------------------------------------------------------ */
	public static function get_dims( $item, $product, $cs ) {

		// 1) Prefer structured custom size from theme: nh_custom_size[width_mm/length_mm OR height_mm]
		if ( ! empty( $item['nh_custom_size'] ) && is_array( $item['nh_custom_size'] ) ) {
			$w = (float) ( $item['nh_custom_size']['width_mm']  ?? 0 );
			$h = (float) ( $item['nh_custom_size']['length_mm'] ?? ( $item['nh_custom_size']['height_mm'] ?? 0 ) );
			if ( $w > 0 || $h > 0 ) {
				return array( $w, $h );
			}
		}

		// 2) Fallback: flat keys on cart item
		$w = isset( $item[ self::WIDTH_KEY ] ) ? $item[ self::WIDTH_KEY ] : 0;

		if ( isset( $item[ self::HEIGHT_KEY ] ) ) {
			$h = $item[ self::HEIGHT_KEY ];
		} elseif ( isset( $item[ self::HEIGHT_KEY_ALT ] ) ) {
			$h = $item[ self::HEIGHT_KEY_ALT ];
		} else {
			$h = 0;
		}

		$w = (float) str_replace( ',', '.', (string) $w );
		$h = (float) str_replace( ',', '.', (string) $h );

		return array( $w, $h );
	}

	/* ------------------------------------------------------------
	 * Map width + height to a shipping class slug using rules
	 *  - 0 (empty) limit means "no limit" for that dimension
	 * ------------------------------------------------------------ */
	public static function map_to_class_slug( $w, $h, $cs ) {

		$w = (float) $w;
		$h = (float) $h;

		// Build rules from settings (r1…r7)
		$rules = array();

		for ( $i = 1; $i <= 7; $i++ ) {
			$rw = isset( $cs["r{$i}_w"] )      ? (float) $cs["r{$i}_w"]      : 0;
			$rh = isset( $cs["r{$i}_h"] )      ? (float) $cs["r{$i}_h"]      : 0;
			$cl = isset( $cs["r{$i}_class"] )  ? (string) $cs["r{$i}_class"] : '';

			// Valid row: has a class AND at least one limit set
			if ( $cl !== '' && ( $rw > 0 || $rh > 0 ) ) {
				$rules[] = array( $rw, $rh, $cl );
			}
		}

		// Check each rule in order:
		// - if rw > 0 then must satisfy w <= rw
		// - if rh > 0 then must satisfy h <= rh
		// - if rw/rh is 0 => ignore that dimension
		foreach ( $rules as $r ) {
			$rw = (float) $r[0];
			$rh = (float) $r[1];
			$cl = (string) $r[2];

			$ok_w = ( $rw <= 0 ) ? true : ( $w <= $rw );
			$ok_h = ( $rh <= 0 ) ? true : ( $h <= $rh );

			if ( $ok_w && $ok_h ) {
				return $cl;
			}
		}

		// Fallback: default class from settings
		return ! empty( $cs['default_class'] ) ? (string) $cs['default_class'] : '';
	}
}