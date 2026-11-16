<?php
/**
 * Resolve PVC strip BOM lines to WooCommerce products.
 *
 * @package nh-pvc-strip-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_PSC_Catalog {

	/**
	 * @param array<string, mixed> $bom
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	public static function price_bom( array $bom, array $settings ) {
		$meta     = $bom['meta'];
		$offer    = array();
		$missing  = array();
		$sub_ex   = 0.0;
		$sub_in   = 0.0;
		$tax_mode = get_option( 'woocommerce_tax_display_shop', 'incl' );

		foreach ( $bom['lines'] as $line ) {
			$resolved = self::resolve_line( $line, $meta, $settings );
			foreach ( $resolved as $item ) {
				$item['unit_display'] = self::format_money( 'excl' === $tax_mode ? $item['unit_ex'] : $item['unit_inc'] );
				$item['line_display'] = self::format_money( 'excl' === $tax_mode ? $item['line_ex'] : $item['line_inc'] );
				if ( empty( $item['product_id'] ) ) {
					$missing[] = $item;
					continue;
				}
				$offer[] = $item;
				$sub_ex += (float) $item['line_ex'];
				$sub_in += (float) $item['line_inc'];
			}
		}

		$tax   = $sub_in - $sub_ex;
		$total = ( 'excl' === $tax_mode ) ? $sub_ex : $sub_in;

		return array(
			'ok'          => true,
			'meta'        => $meta,
			'items'       => $offer,
			'missing'     => $missing,
			'totals'      => array(
				'ex'              => $sub_ex,
				'inc'             => $sub_in,
				'tax'             => $tax,
				'tax_formatted'   => self::format_money( $tax ),
				'total_formatted' => self::format_money( $total ),
			),
			'currency'    => self::currency_payload(),
			'tax_display' => $tax_mode,
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
		$w    = (string) (int) ( $line['strip_width_mm'] ?? $meta['strip_width_mm'] ?? 200 );

		switch ( $role ) {
			case 'strip':
				$type  = (string) ( $meta['strip_type'] ?? 'smooth' );
				$grade = (string) ( $meta['grade'] ?? 'standard' );
				$sku   = self::strip_sku( $settings, $w, $type, $grade );
				$item  = self::resolve_product(
					$line,
					$sku,
					sprintf(
						/* translators: 1: strip width mm, 2: thickness mm */
						__( 'PVC strip %1$d × %2$d mm', NH_PSC_TD ),
						(int) $w,
						(int) ( $line['strip_thickness'] ?? 2 )
					)
				);
				$item['spec'] = sprintf(
					/* translators: 1: strip pieces, 2: metres */
					__( '%1$d strips · %2$s m', NH_PSC_TD ),
					(int) ( $line['strip_count'] ?? 0 ),
					self::fmt_m( $line['qty'] ?? 0 )
				);
				return array( $item );

			case 'red_strip':
				$sku  = isset( $settings['red_strips'][ $w ] ) ? $settings['red_strips'][ $w ] : '';
				$item = self::resolve_product(
					$line,
					$sku,
					sprintf(
						/* translators: %d: strip width mm */
						__( 'Red edge strip %d mm', NH_PSC_TD ),
						(int) $w
					)
				);
				$item['spec'] = sprintf(
					/* translators: 1: strip pieces, 2: metres */
					__( '%1$d strips · %2$s m', NH_PSC_TD ),
					(int) ( $line['strip_count'] ?? 0 ),
					self::fmt_m( $line['qty'] ?? 0 )
				);
				return array( $item );

			case 'rail':
				$sku  = isset( $settings['hardware']['rail'] ) ? $settings['hardware']['rail'] : '';
				$item = self::resolve_product( $line, $sku, __( 'Hanging rail / bracket', NH_PSC_TD ) );
				$item['spec'] = sprintf(
					/* translators: 1: cover mm, 2: stock piece mm */
					__( 'Covers %1$d mm · %2$d mm pieces', NH_PSC_TD ),
					(int) ( $line['cover_mm'] ?? 0 ),
					(int) ( $line['stock_mm'] ?? 1500 )
				);
				return array( $item );

			case 'hanger':
				$sku  = isset( $settings['hardware']['hanger'][ $w ] ) ? $settings['hardware']['hanger'][ $w ] : '';
				$item = self::resolve_product(
					$line,
					$sku,
					sprintf(
						/* translators: %d: strip width mm */
						__( 'Strip hanger %d mm', NH_PSC_TD ),
						(int) $w
					)
				);
				$item['spec'] = sprintf(
					/* translators: %d: quantity */
					__( '%d pcs', NH_PSC_TD ),
					(int) ( $line['qty'] ?? 0 )
				);
				return array( $item );
		}

		return array();
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function strip_sku( array $settings, $width, $type, $grade ) {
		$w = (string) (int) $width;
		if ( empty( $settings['strips'][ $w ][ $type ][ $grade ] ) ) {
			return '';
		}
		return (string) $settings['strips'][ $w ][ $type ][ $grade ];
	}

	/**
	 * @param array<string, mixed> $line
	 * @return array<string, mixed>
	 */
	private static function resolve_product( array $line, $ref, $fallback_name ) {
		$qty   = isset( $line['qty'] ) ? (float) $line['qty'] : 1;
		$qty   = $qty > 0 ? $qty : 1;
		$empty = self::empty_item( $line['role'], $ref, $qty, $fallback_name );

		if ( ! $ref ) {
			$empty['error'] = 'sku_missing';
			return $empty;
		}

		$product = self::product_by_ref( $ref );
		if ( ! $product ) {
			$empty['error'] = 'not_found';
			return $empty;
		}

		$sell     = $product;
		$parent   = $product;
		$var_id   = 0;
		$matched  = array();

		if ( $product->is_type( 'variation' ) ) {
			$var_id = $product->get_id();
			$parent = wc_get_product( $product->get_parent_id() );
			if ( ! $parent ) {
				$parent = $product;
			}
			$matched = $product->get_attributes();
		}

		$unit_ex  = (float) wc_get_price_excluding_tax( $sell, array( 'qty' => 1 ) );
		$unit_inc = (float) wc_get_price_including_tax( $sell, array( 'qty' => 1 ) );
		$unit     = self::whole_line( $unit_ex, $unit_inc, $sell );
		$unit_ex  = $unit['ex'];
		$unit_inc = $unit['inc'];
		$image_id = $sell->get_image_id() ? $sell->get_image_id() : $parent->get_image_id();

		$cart_qty = ( 'm' === ( $line['unit'] ?? '' ) ) ? round( $qty, 2 ) : max( 1, (int) round( $qty ) );
		$line     = self::whole_line( $unit_ex * $cart_qty, $unit_inc * $cart_qty, $sell );

		return array(
			'role'         => $line['role'],
			'sku'          => $sell->get_sku() ? $sell->get_sku() : $parent->get_sku(),
			'product_id'   => $parent->get_id(),
			'variation_id' => (int) $var_id,
			'attributes'   => $matched,
			'qty'          => $cart_qty,
			'name'         => $parent->get_name(),
			'label'        => $fallback_name,
			'spec'         => '',
			'permalink'    => $parent->get_permalink(),
			'image'        => $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_gallery_thumbnail' ) : '',
			'unit_ex'      => $unit_ex,
			'unit_inc'     => $unit_inc,
			'line_ex'      => $line['ex'],
			'line_inc'     => $line['inc'],
			'price_html'   => wc_price( 'incl' === get_option( 'woocommerce_tax_display_shop', 'incl' ) ? $line['inc'] : $line['ex'] ),
			'in_stock'     => $sell->is_in_stock(),
			'purchasable'  => $sell->is_purchasable(),
		);
	}

	/**
	 * @return WC_Product|null
	 */
	public static function product_by_ref( $ref ) {
		$ref = trim( (string) $ref );
		if ( $ref === '' ) {
			return null;
		}
		if ( ctype_digit( $ref ) ) {
			$p = wc_get_product( (int) $ref );
			return $p instanceof WC_Product ? $p : null;
		}
		$id = wc_get_product_id_by_sku( $ref );
		if ( ! $id ) {
			return null;
		}
		$p = wc_get_product( $id );
		return $p instanceof WC_Product ? $p : null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function empty_item( $role, $sku, $qty, $name ) {
		$q = is_float( $qty ) ? max( 0.01, round( $qty, 2 ) ) : max( 1, (int) $qty );
		return array(
			'role'         => $role,
			'sku'          => (string) $sku,
			'product_id'   => 0,
			'variation_id' => 0,
			'attributes'   => array(),
			'qty'          => $q,
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
		$code = get_woocommerce_currency();
		return array(
			'code'        => $code,
			'symbol'      => html_entity_decode( wp_strip_all_tags( (string) get_woocommerce_currency_symbol( $code ) ), ENT_QUOTES, 'UTF-8' ),
			'pos'         => get_option( 'woocommerce_currency_pos', 'right_space' ),
			'decimals'    => function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2,
			'trim'        => function_exists( 'nh_ig_currency_is_whole' ) && nh_ig_currency_is_whole( $code ),
			'superscript' => function_exists( 'nh_eur_use_superscript' ) && nh_eur_use_superscript(),
			'thousand'    => function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : ' ',
			'decimal'     => function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : ',',
		);
	}

	/**
	 * @param float|int|string $amount
	 */
	/**
	 * On NOK, SEK and DKK, the amount including VAT is a whole krone.
	 *
	 * @param WC_Product|null $product
	 * @return array{ex: float, inc: float}
	 */
	private static function whole_line( $ex, $inc, $product = null ) {
		if ( function_exists( 'nh_ig_round_display_pair' ) ) {
			return nh_ig_round_display_pair( $ex, $inc, $product );
		}
		return array(
			'ex'  => (float) $ex,
			'inc' => (float) $inc,
		);
	}

	public static function format_money( $amount ) {
		if ( ! function_exists( 'wc_price' ) ) {
			return '';
		}
		$html = wc_price( (float) $amount, array( 'currency' => get_woocommerce_currency() ) );
		$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
		return trim( (string) preg_replace( '/[ \t\f\v]+/u', ' ', $text ) );
	}

	private static function fmt_m( $n ) {
		$n = (float) $n;
		$s = rtrim( rtrim( number_format( $n, 3, '.', '' ), '0' ), '.' );
		return $s === '' ? '0' : $s;
	}
}
