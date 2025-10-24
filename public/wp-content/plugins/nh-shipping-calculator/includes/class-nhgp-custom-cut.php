<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NHGP_Custom_Cut {
	public static function is_custom_item( $item, $product, $cs ){
		$flag_key = $cs['flag_key'];
		$flag = ! empty( $item[ $flag_key ] )
			|| ( $product && (int) $product->get_meta( '_' . $flag_key, true ) === 1 )
			|| ( $product && (int) $product->get_meta( $flag_key, true ) === 1 );
		return (bool) $flag;
	}
	public static function get_dims( $item, $product, $cs ){
		$w = isset( $item[ $cs['width_key'] ] )  ? $item[ $cs['width_key'] ]  : ( $product ? $product->get_meta( '_' . $cs['width_key'],  true ) : null );
		$h = isset( $item[ $cs['height_key'] ] ) ? $item[ $cs['height_key'] ] : ( $product ? $product->get_meta( '_' . $cs['height_key'], true ) : null );
		$w = (float) str_replace( ',', '.', (string) $w );
		$h = (float) str_replace( ',', '.', (string) $h );
		return array( $w, $h );
	}
	public static function map_to_class_slug( $w, $h, $cs ){
		$rules = array(
			array( (float)$cs['r1_w'], (float)$cs['r1_h'], $cs['r1_class'] ),
			array( (float)$cs['r2_w'], (float)$cs['r2_h'], $cs['r2_class'] ),
			array( (float)$cs['r3_w'], (float)$cs['r3_h'], $cs['r3_class'] ),
		);
		foreach ( $rules as $r ){
			if ( $w <= $r[0] && $h <= $r[1] && ! empty( $r[2] ) ) return $r[2];
		}
		return ! empty( $cs['default_class'] ) ? $cs['default_class'] : '';
	}
}
