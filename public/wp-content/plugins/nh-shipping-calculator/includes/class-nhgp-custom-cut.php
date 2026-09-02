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
			if ( ! empty( $item['nh_custom_size']['length_m'] ) && (float) $item['nh_custom_size']['length_m'] > 0 ) {
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
			if ( ! empty( $item['nh_custom_size']['length_m'] ) && (float) $item['nh_custom_size']['length_m'] > 0 ) {
				return array( $w, (float) $item['nh_custom_size']['length_m'] * 1000 );
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
	 *
	 *  - A 0 (empty) limit means "no limit" for that side.
	 *  - Width and height are interchangeable for cut sheets.
	 *  - Rules are sorted smallest-to-largest so the tightest fit wins.
	 * ------------------------------------------------------------ */
	public static function map_to_class_slug( $w, $h, $cs ) {

		$w = (float) $w;
		$h = (float) $h;

		// Sort item sides ascending so orientation does not matter.
		$item = array( $w, $h );
		sort( $item, SORT_NUMERIC );

		$rules = array();

		for ( $i = 1; $i <= 7; $i++ ) {
			$rw = isset( $cs["r{$i}_w"] )     ? (float) $cs["r{$i}_w"]     : 0;
			$rh = isset( $cs["r{$i}_h"] )     ? (float) $cs["r{$i}_h"]     : 0;
			$cl = isset( $cs["r{$i}_class"] ) ? (string) $cs["r{$i}_class"] : '';

			// Valid row: has a class AND at least one finite limit.
			if ( $cl === '' || ( $rw <= 0 && $rh <= 0 ) ) {
				continue;
			}

			// Sort rule sides ascending as well.
			$r_dims = array( $rw, $rh );
			sort( $r_dims, SORT_NUMERIC );

			// For sorting, a 0 limit means "no limit" -> treat as very large.
			$effective_max = max( $rw, $rh );
			if ( $effective_max <= 0 ) {
				$effective_max = PHP_FLOAT_MAX;
			}

			$rules[] = array(
				'class' => $cl,
				'dims'  => $r_dims,
				'sort'  => $effective_max,
			);
		}

		// Sort from smallest bounding box to largest so the tightest match wins.
		usort(
			$rules,
			static function( $a, $b ) {
				return $a['sort'] <=> $b['sort'];
			}
		);

		foreach ( $rules as $r ) {
			$rd = $r['dims'];

			// 0 means no limit for that side.
			$ok_side0 = ( $rd[0] <= 0 ) ? true : ( $item[0] <= $rd[0] );
			$ok_side1 = ( $rd[1] <= 0 ) ? true : ( $item[1] <= $rd[1] );

			if ( $ok_side0 && $ok_side1 ) {
				return $r['class'];
			}
		}

		// Fallback: default class from settings.
		return ! empty( $cs['default_class'] ) ? (string) $cs['default_class'] : '';
	}

	/**
	 * Shipping class slug this cart line should use (custom-cut rules, else empty).
	 *
	 * @param array            $item    Cart item.
	 * @param WC_Product|null  $product Line product.
	 * @param array            $cs      Custom-cut settings.
	 * @return string
	 */
	public static function mapped_class_slug_for_item( $item, $product, $cs ) {
		if ( ! self::is_custom_item( $item, $product, $cs ) ) {
			return '';
		}

		list( $w, $h ) = self::get_dims( $item, $product, $cs );

		$slug = '';
		if ( $w > 0 || $h > 0 ) {
			$slug = self::map_to_class_slug( $w, $h, $cs );
		}

		if ( $slug === '' && ! empty( $cs['default_class'] ) ) {
			$slug = (string) $cs['default_class'];
		}

		return self::normalize_class_slug( $slug );
	}

	/**
	 * Turn a stored class value (slug or term ID) into a shipping-class slug.
	 *
	 * @param string|int $value Raw setting / map result.
	 * @return string
	 */
	public static function normalize_class_slug( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '';
		}

		if ( ctype_digit( $value ) ) {
			$term = get_term( (int) $value, 'product_shipping_class' );
			if ( $term && ! is_wp_error( $term ) ) {
				return (string) $term->slug;
			}
			return '';
		}

		$slug = sanitize_title( $value );
		$term = get_term_by( 'slug', $slug, 'product_shipping_class' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (string) $term->slug;
		}

		return $slug;
	}

	/**
	 * Term ID for a shipping-class slug.
	 *
	 * @param string $slug Class slug.
	 * @return int
	 */
	public static function term_id_from_slug( $slug ) {
		$slug = self::normalize_class_slug( $slug );
		if ( $slug === '' ) {
			return 0;
		}

		$term = get_term_by( 'slug', $slug, 'product_shipping_class' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		return 0;
	}
}
