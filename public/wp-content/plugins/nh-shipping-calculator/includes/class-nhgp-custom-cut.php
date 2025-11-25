<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NHGP_Custom_Cut {

	/* ------------------------------------------------------------
	 * Hard-coded meta keys (no more user-configurable keys)
	 * ------------------------------------------------------------ */
	const FLAG_KEY   = 'nh_custom_mode';
	const WIDTH_KEY  = 'nh_width_mm';
	const HEIGHT_KEY = 'nh_length_mm';

	/* ------------------------------------------------------------
	 * Check if item is custom-cut
	 * ------------------------------------------------------------ */
	public static function is_custom_item( $item, $product, $cs ) {

		// 1) Theme's structured custom size: nh_custom_size[width_mm/length_mm]
		if ( ! empty( $item['nh_custom_size'] ) && is_array( $item['nh_custom_size'] ) ) {
			$w = (int) ( $item['nh_custom_size']['width_mm']  ?? 0 );
			$h = (int) ( $item['nh_custom_size']['length_mm'] ?? 0 );
			if ( $w > 0 && $h > 0 ) {
				return true;
			}
		}

		// 2) Flat dimension keys on cart item (nh_width_mm / nh_length_mm)
		$w_item = isset( $item[ self::WIDTH_KEY ] )  ? (float) $item[ self::WIDTH_KEY ]  : 0;
		$h_item = isset( $item[ self::HEIGHT_KEY ] ) ? (float) $item[ self::HEIGHT_KEY ] : 0;
		if ( $w_item > 0 && $h_item > 0 ) {
			return true;
		}

		// 3) Cart item flag (nh_custom_mode)
		if ( ! empty( $item[ self::FLAG_KEY ] ) ) {
			return true;
		}

		// 4) Product meta (_nh_custom_mode or nh_custom_mode)
		if ( $product ) {
			if ( (int) $product->get_meta( '_' . self::FLAG_KEY, true ) === 1 ) {
				return true;
			}
			if ( (int) $product->get_meta( self::FLAG_KEY, true ) === 1 ) {
				return true;
			}
		}

		return false;
	}

	/* ------------------------------------------------------------
	 * Extract dimensions (width mm, height mm)
	 * ------------------------------------------------------------ */
	public static function get_dims( $item, $product, $cs ) {

		// 1) Prefer structured custom size from theme: nh_custom_size[width_mm/length_mm]
		if ( ! empty( $item['nh_custom_size'] ) && is_array( $item['nh_custom_size'] ) ) {
			$w = (float) ( $item['nh_custom_size']['width_mm']  ?? 0 );
			$h = (float) ( $item['nh_custom_size']['length_mm'] ?? 0 );
			if ( $w > 0 || $h > 0 ) {
				return array( $w, $h );
			}
		}

		// 2) Fallback: flat keys on cart item
		if ( isset( $item[ self::WIDTH_KEY ] ) || isset( $item[ self::HEIGHT_KEY ] ) ) {
			$w = isset( $item[ self::WIDTH_KEY ] )  ? $item[ self::WIDTH_KEY ]  : 0;
			$h = isset( $item[ self::HEIGHT_KEY ] ) ? $item[ self::HEIGHT_KEY ] : 0;

			$w = (float) str_replace( ',', '.', (string) $w );
			$h = (float) str_replace( ',', '.', (string) $h );

			return array( $w, $h );
		}

		// 3) Last resort: product meta (for backwards compatibility)
		$w = $product
			? $product->get_meta( '_' . self::WIDTH_KEY, true )
			: null;

		$h = $product
			? $product->get_meta( '_' . self::HEIGHT_KEY, true )
			: null;

		$w = (float) str_replace( ',', '.', (string) $w );
		$h = (float) str_replace( ',', '.', (string) $h );

		return array( $w, $h );
	}

	/* ------------------------------------------------------------
	 * Map width + height to one of 7 size classes
	 * ------------------------------------------------------------ */
	public static function map_to_class_slug( $w, $h, $cs ) {

		// Build 7 rules dynamically from settings (r1…r7)
		$rules = array();

		for ( $i = 1; $i <= 7; $i++ ) {
			$rw = isset( $cs["r{$i}_w"] )     ? (float) $cs["r{$i}_w"]     : 0;
			$rh = isset( $cs["r{$i}_h"] )     ? (float) $cs["r{$i}_h"]     : 0;
			$cl = isset( $cs["r{$i}_class"] ) ?        $cs["r{$i}_class"]  : '';

			// Only add valid rows (non-empty class, at least one non-zero dimension)
			if ( $cl !== '' && ( $rw > 0 || $rh > 0 ) ) {
				$rules[] = array( $rw, $rh, $cl );
			}
		}

		// Check each rule in order
		foreach ( $rules as $r ) {
			if ( $w <= $r[0] && $h <= $r[1] ) {
				return $r[2];
			}
		}

		// Fallback: default class from settings
		return ! empty( $cs['default_class'] ) ? $cs['default_class'] : '';
	}
}
