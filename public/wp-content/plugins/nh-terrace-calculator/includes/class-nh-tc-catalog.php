<?php
/**
 * Resolve abstract BOM lines to live WooCommerce products / variations / prices.
 *
 * @package nh-terrace-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_TC_Catalog {

	/**
	 * @param array<string, mixed> $bom
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	public static function price_bom( array $bom, array $settings ) {
			$meta    = $bom['meta'];
			$offer   = array();
			$missing = array();
			$sub_ex  = 0.0;
			$sub_in  = 0.0;

			foreach ( $bom['lines'] as $line ) {
				$resolved = self::resolve_line( $line, $meta, $settings );
				foreach ( $resolved as $item ) {
					if ( empty( $item['product_id'] ) ) {
						$missing[] = $item;
						continue;
					}
					$offer[] = $item;
					$sub_ex += (float) $item['line_ex'];
					$sub_in += (float) $item['line_inc'];
				}
			}

			return array(
				'ok'          => true,
				'meta'        => $meta,
				'items'       => $offer,
				'missing'     => $missing,
				'totals'      => array(
					'ex'  => $sub_ex,
					'inc' => $sub_in,
					'tax' => $sub_in - $sub_ex,
				),
				'currency'    => self::currency_payload(),
				'tax_display' => get_option( 'woocommerce_tax_display_shop', 'incl' ),
			);
		}

	/**
	 * @param array<string, mixed> $line
	 * @param array<string, mixed> $meta
	 * @param array<string, mixed> $settings
	 * @return array<int, array<string, mixed>>
	 */
	public static function resolve_line( array $line, array $meta, array $settings ) {
		$role = (string) $line['role'];

		switch ( $role ) {
			case 'sheet':
				return array( self::resolve_sheet( $line, $meta, $settings ) );
			case 'screw':
				return array( self::resolve_simple_variable( $line, $settings['hardware']['screws'], $line['attrs'] ?? array(), __( 'Wood screws with washers', NH_TC_TD ) ) );
			case 'connecting':
				$sku = self::map_sku( $settings['connecting'], $meta['connecting_profile'], $meta['connecting_color'] );
				return array( self::resolve_simple_variable( $line, $sku, $line['attrs'] ?? array(), __( 'Connecting profile', NH_TC_TD ) ) );
			case 'finish':
				return self::resolve_finish( $line, $meta, $settings );
			case 'wall':
				return array( self::resolve_simple_variable( $line, $settings['hardware']['wall'], array(), __( 'Wall profile 2.2 m', NH_TC_TD ) ) );
			case 'ridge':
				return array( self::resolve_simple_variable( $line, $settings['hardware']['ridge'], array(), __( 'Ridge profile 2.2 m', NH_TC_TD ) ) );
			case 'vent_tape':
				return array( self::resolve_simple_variable( $line, $settings['hardware']['vent_tape'], $line['attrs'] ?? array(), __( 'Ventilation sealing tape', NH_TC_TD ) ) );
			case 'iso_tape':
				return array( self::resolve_simple_variable( $line, $settings['hardware']['iso_tape'], $line['attrs'] ?? array(), __( 'Insulation sealing tape', NH_TC_TD ) ) );
			case 'end_cap':
				$sku = self::map_sku( $settings['hardware']['end_cap'], $meta['connecting_color'] );
				if ( ! $sku ) {
					$sku = $settings['hardware']['end_cap']['silver'] ?? '';
				}
				return array( self::resolve_simple_variable( $line, $sku, array(), __( 'Clamping profile end cap', NH_TC_TD ) ) );
			case 'gasket':
				return array( self::resolve_simple_variable( $line, $settings['hardware']['gasket'], array(), __( 'EPDM rubber gasket 50 mm', NH_TC_TD ) ) );
			case 'silicon':
				return array( self::resolve_simple_variable( $line, $settings['hardware']['silicon'], array(), __( 'Neutral silicone Silirub PC', NH_TC_TD ) ) );
		}

		return array();
	}

	/**
	 * @param array<string, mixed> $line
	 * @param array<string, mixed> $meta
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private static function resolve_sheet( array $line, array $meta, array $settings ) {
		$sku = self::sheet_sku( $meta['material'], (string) $meta['thickness'], $meta['colour'], $settings );
		$cut = $line['cut'];
		$qty = (int) $line['qty'];

		$empty = self::empty_item( 'sheet', $sku, $qty, __( 'Polycarbonate sheet', NH_TC_TD ) );
		$empty['cut'] = $cut;

		if ( ! $sku ) {
			$empty['error'] = 'sku_missing';
			return $empty;
		}

		$product = self::product_by_sku( $sku );
		if ( ! $product ) {
			$empty['error'] = 'not_found';
			return $empty;
		}

		// Sheet products are priced per 1 m²; the kit line is cut to size.
		$priced = self::custom_cut_unit_price( $product, (int) $cut['width_mm'], (int) $cut['length_mm'] );

		$item = self::build_item( $product, 0, array(), $qty, 'sheet', __( 'Polycarbonate sheet', NH_TC_TD ) );
		$item['cut']        = $cut;
		$item['custom_cut'] = 1;
		$item['area_m2']    = $priced['area'];
		$item['unit_raw']   = $priced['raw'];
		$item['spec']       = sprintf(
			/* translators: 1: cut width in mm, 2: cut length in mm, 3: area in square metres */
			__( '%1$d × %2$d mm (%3$s m²)', NH_TC_TD ),
			(int) $cut['width_mm'],
			(int) $cut['length_mm'],
			number_format_i18n( $priced['area'], 2 )
		);

		$item['unit_ex']  = $priced['ex'];
		$item['unit_inc'] = $priced['inc'];
		$item['line_ex']  = $priced['ex'] * $qty;
		$item['line_inc'] = $priced['inc'] * $qty;
		$item['price_html'] = wc_price( 'incl' === get_option( 'woocommerce_tax_display_shop', 'incl' ) ? $item['line_inc'] : $item['line_ex'] );

		return $item;
	}

	/**
	 * @param array<string, mixed> $line
	 * @param array<string, mixed> $meta
	 * @param array<string, mixed> $settings
	 * @return array<int, array<string, mixed>>
	 */
	private static function resolve_finish( array $line, array $meta, array $settings ) {
		$sku = self::map_sku( $settings['finish'], $meta['finish_profile'], $meta['finish_color'] );
		$out = array();
		$thickness = isset( $line['attrs']['thickness'] ) ? $line['attrs']['thickness'] : ( $meta['thickness'] . '-mm' );

		foreach ( $line['packed'] as $length_slug => $qty ) {
			$attrs = array(
				'thickness' => $thickness,
				'length'    => $length_slug,
			);
			$one = self::resolve_simple_variable(
				array(
					'role' => 'finish',
					'qty'  => (int) $qty,
				),
				$sku,
				$attrs,
				__( 'Finish profile', NH_TC_TD )
			);
			$one['spec'] = $length_slug . ' × ' . (int) $qty;
			$out[] = $one;
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $line
	 * @param array<string, string> $attrs wanted value slugs
	 * @return array<string, mixed>
	 */
	private static function resolve_simple_variable( array $line, $sku, array $attrs, $fallback_name ) {
		$qty   = (int) $line['qty'];
		$empty = self::empty_item( $line['role'], $sku, $qty, $fallback_name );

		if ( ! $sku ) {
			$empty['error'] = 'sku_missing';
			return $empty;
		}

		$product = self::product_by_sku( $sku );
		if ( ! $product ) {
			$empty['error'] = 'not_found';
			return $empty;
		}

		$variation_id = 0;
		$variation    = null;
		$matched_atts = array();

		if ( $product->is_type( 'variable' ) ) {
			$found = self::find_variation( $product, $attrs );
			if ( ! $found ) {
				$empty['error'] = 'variation_missing';
				$empty['wanted_attrs'] = $attrs;
				$empty['name'] = $product->get_name();
				$empty['permalink'] = $product->get_permalink();
				return $empty;
			}
			$variation_id = (int) $found['variation_id'];
			$variation    = $found['product'];
			$matched_atts = $found['attributes'];
		}

		$sell = $variation instanceof WC_Product ? $variation : $product;
		$item = self::build_item( $product, $variation_id, $matched_atts, $qty, $line['role'], $fallback_name, $sell );

		if ( ! empty( $line['qty_raw'] ) ) {
			$item['qty_raw'] = (int) $line['qty_raw'];
			$item['spec']    = sprintf(
				/* translators: 1: raw screw count, 2: pack size */
				__( '%1$d pcs (%2$d / pack)', NH_TC_TD ),
				(int) $line['qty_raw'],
				isset( $line['pack_size'] ) ? (int) $line['pack_size'] : 50
			);
		}

		if ( ! empty( $line['attrs']['length'] ) && empty( $item['spec'] ) ) {
			$item['spec'] = (string) $line['attrs']['length'];
		}

		if ( ! empty( $line['unit'] ) && 'm' === $line['unit'] ) {
			$item['spec'] = sprintf(
				/* translators: metres */
				__( '%s m', NH_TC_TD ),
				(string) $qty
			);
		}

		return $item;
	}

	/**
	 * @param array<string, string> $wanted value slugs keyed by logical name (length, thickness, width, dimensions)
	 * @return array{variation_id:int,product:WC_Product,attributes:array<string,string>}|null
	 */
	public static function find_variation( WC_Product $product, array $wanted ) {
		$wanted_values = array_filter( array_map( 'strval', $wanted ) );
		if ( ! $wanted_values ) {
			$children = $product->get_children();
			if ( $children ) {
				$vid = (int) $children[0];
				$vp  = wc_get_product( $vid );
				if ( $vp instanceof WC_Product ) {
					return array(
						'variation_id' => $vid,
						'product'      => $vp,
						'attributes'   => $vp->get_variation_attributes(),
					);
				}
			}
			return null;
		}

		$best = null;
		$best_score = -1;

		foreach ( $product->get_children() as $vid ) {
			$vp = wc_get_product( $vid );
			if ( ! $vp instanceof WC_Product_Variation || ! $vp->exists() ) {
				continue;
			}

			$attrs = $vp->get_variation_attributes();
			$values = array_map( 'strval', array_values( $attrs ) );
			$score  = 0;
			$ok     = true;

			foreach ( $wanted_values as $logical => $want ) {
				$logical_exists = false;
				foreach ( $attrs as $key => $val ) {
					if ( self::attr_key_matches( strtolower( (string) $key ), (string) $logical ) ) {
						$logical_exists = true;
						break;
					}
				}
				if ( ! $logical_exists ) {
					continue;
				}

				$hit = false;
				foreach ( $attrs as $key => $val ) {
					$val   = (string) $val;
					$key_l = strtolower( $key );
					if ( $val !== $want && $val !== str_replace( '_', '-', $want ) ) {
						continue;
					}
					if ( self::attr_key_matches( $key_l, (string) $logical ) ) {
						$hit    = true;
						$score += 2;
						break;
					}
					$hit    = true;
					$score += 1;
				}
				if ( ! $hit && in_array( $want, $values, true ) ) {
					$hit    = true;
					$score += 1;
				}
				if ( ! $hit ) {
					$ok = false;
					break;
				}
			}

			if ( $ok && $score > $best_score ) {
				$best_score = $score;
				$best       = array(
					'variation_id' => (int) $vid,
					'product'      => $vp,
					'attributes'   => $attrs,
				);
			}
		}

		return $best;
	}

	private static function attr_key_matches( $key, $logical ) {
		$logical = strtolower( $logical );
		$map     = array(
			'length'     => array( 'lengde', 'langd', 'length', 'lange', 'pituus' ),
			'width'      => array( 'bredde', 'bredd', 'width', 'breite', 'plotis', 'leveys' ),
			'thickness'  => array( 'tykkelse', 'tjocklek', 'thickness', 'dicke', 'storis', 'paksuus' ),
			'dimensions' => array( 'dimensjon', 'dimension', 'storlek', 'size' ),
		);
		$needles = isset( $map[ $logical ] ) ? $map[ $logical ] : array( $logical );
		foreach ( $needles as $n ) {
			if ( false !== strpos( $key, $n ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $attributes
	 * @return array<string, mixed>
	 */
	private static function build_item( WC_Product $parent, $variation_id, array $attributes, $qty, $role, $fallback_name, $sell = null ) {
		$sell = $sell instanceof WC_Product ? $sell : $parent;
		$qty  = max( 1, (int) $qty );

		$unit_ex  = (float) wc_get_price_excluding_tax( $sell, array( 'qty' => 1 ) );
		$unit_inc = (float) wc_get_price_including_tax( $sell, array( 'qty' => 1 ) );
		$display  = (float) wc_get_price_to_display( $sell, array( 'qty' => 1 ) );

		$image_id = $sell->get_image_id();
		if ( ! $image_id ) {
			$image_id = $parent->get_image_id();
		}

		$atts_for_cart = array();
		foreach ( $attributes as $key => $value ) {
			$k = 0 === strpos( $key, 'attribute_' ) ? $key : 'attribute_' . $key;
			$atts_for_cart[ $k ] = $value;
		}

		return array(
			'role'         => $role,
			'sku'          => $sell->get_sku() ? $sell->get_sku() : $parent->get_sku(),
			'product_id'   => $parent->get_id(),
			'variation_id' => (int) $variation_id,
			'attributes'   => $atts_for_cart,
			'qty'          => $qty,
			'name'         => $parent->get_name(),
			'label'        => $fallback_name,
			'spec'         => $variation_id ? wc_get_formatted_variation( $sell, true, false, true ) : '',
			'permalink'    => $parent->get_permalink(),
			'image'        => $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_gallery_thumbnail' ) : '',
			'unit_ex'      => $unit_ex,
			'unit_inc'     => $unit_inc,
			'line_ex'      => $unit_ex * $qty,
			'line_inc'     => $unit_inc * $qty,
			'price_html'   => wc_price( $display * $qty ),
			'in_stock'     => $sell->is_in_stock(),
			'purchasable'  => $sell->is_purchasable(),
		);
	}

	/**
	 * @return array{ex:float,inc:float,raw:float,area:float}
	 */
	public static function custom_cut_unit_price( WC_Product $product, $width_mm, $length_mm ) {
		$area = ( (float) $width_mm / 1000 ) * ( (float) $length_mm / 1000 );
		$base = (float) $product->get_price(); // Catalogue price = price per 1 m².
		$fee  = (float) get_post_meta( $product->get_id(), '_nh_cc_cut_fee', true );
		$raw  = ( $area * $base ) + max( 0, $fee );
		$raw  = round( $raw, (int) wc_get_price_decimals() );

		$ex  = (float) wc_get_price_excluding_tax( $product, array( 'price' => $raw, 'qty' => 1 ) );
		$inc = (float) wc_get_price_including_tax( $product, array( 'price' => $raw, 'qty' => 1 ) );

		return array(
			'ex'   => $ex,
			'inc'  => $inc,
			'raw'  => $raw,
			'area' => $area,
		);
	}

	/**
	 * @param array<string, mixed> $map
	 */
	public static function map_sku( $map, $key1, $key2 = null ) {
		if ( ! is_array( $map ) ) {
			return '';
		}
		if ( 'f_profile' === $key1 && ! isset( $map[ $key1 ] ) && isset( $map['f_aluminium'] ) ) {
			$key1 = 'f_aluminium';
		}
		if ( null === $key2 ) {
			return ( isset( $map[ $key1 ] ) && is_string( $map[ $key1 ] ) ) ? $map[ $key1 ] : '';
		}
		if ( isset( $map[ $key1 ] ) && is_array( $map[ $key1 ] ) ) {
			$inner = $map[ $key1 ];
			if ( isset( $inner[ $key2 ] ) && $inner[ $key2 ] !== '' ) {
				return (string) $inner[ $key2 ];
			}
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function sheet_sku( $material, $thickness, $colour, array $settings ) {
		$sheets = $settings['sheets'];
		if ( ! isset( $sheets[ $material ][ $thickness ] ) ) {
			return '';
		}
		$row = $sheets[ $material ][ $thickness ];
		if ( isset( $row[ $colour ] ) && $row[ $colour ] !== '' ) {
			return (string) $row[ $colour ];
		}
		return '';
	}

	/**
	 * Resolve a saved SKU or product ID to a WooCommerce product.
	 *
	 * @return WC_Product|null
	 */
	public static function product_by_ref( $ref ) {
		$ref = trim( (string) $ref );
		if ( $ref === '' ) {
			return null;
		}
		$id = wc_get_product_id_by_sku( $ref );
		if ( $id ) {
			$p = wc_get_product( $id );
			return $p instanceof WC_Product ? $p : null;
		}
		if ( ctype_digit( $ref ) ) {
			$p = wc_get_product( (int) $ref );
			return $p instanceof WC_Product ? $p : null;
		}
		return null;
	}

	/**
	 * @return WC_Product|null
	 */
	public static function product_by_sku( $sku ) {
		return self::product_by_ref( $sku );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function empty_item( $role, $sku, $qty, $name ) {
		return array(
			'role'         => $role,
			'sku'          => (string) $sku,
			'product_id'   => 0,
			'variation_id' => 0,
			'attributes'   => array(),
			'qty'          => max( 1, (int) $qty ),
			'name'         => $name,
			'label'        => $name,
			'spec'         => '',
			'permalink'    => '',
			'image'        => '',
			'unit_ex'      => 0,
			'unit_inc'     => 0,
			'line_ex'      => 0,
			'line_inc'     => 0,
			'price_html'   => '',
			'in_stock'     => false,
			'purchasable'  => false,
			'error'        => 'unresolved',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function currency_payload() {
		return array(
			'symbol'   => get_woocommerce_currency_symbol(),
			'code'     => get_woocommerce_currency(),
			'pos'      => get_option( 'woocommerce_currency_pos', 'right_space' ),
			'decimals' => wc_get_price_decimals(),
			'thousand' => wc_get_price_thousand_separator(),
			'decimal'  => wc_get_price_decimal_separator(),
		);
	}

	/**
	 * Colour / thickness options actually present in the configured sheet map.
	 *
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	public static function option_tree( array $settings ) {
		$tree = array();
		foreach ( $settings['sheets'] as $material => $thicknesses ) {
			$tree[ $material ] = array();
			if ( ! is_array( $thicknesses ) ) {
				continue;
			}
			foreach ( $thicknesses as $thk => $colours ) {
				if ( ! is_array( $colours ) ) {
					continue;
				}
				$available = array();
				foreach ( $colours as $colour => $ref ) {
					if ( '' !== trim( (string) $ref ) ) {
						$available[] = (string) $colour;
					}
				}
				if ( $available ) {
					$tree[ $material ][ (string) $thk ] = $available;
				}
			}
		}
		return $tree;
	}

	/**
	 * @param array<string, mixed> $map
	 * @return array<string, string[]>
	 */
	public static function color_tree( array $map ) {
		$tree = array();
		foreach ( $map as $type => $colours ) {
			if ( ! is_array( $colours ) ) {
				continue;
			}
			$available = array();
			foreach ( $colours as $colour => $ref ) {
				if ( '' !== trim( (string) $ref ) ) {
					$available[] = (string) $colour;
				}
			}
			if ( $available ) {
				$tree[ (string) $type ] = $available;
			}
		}
		return $tree;
	}
}
