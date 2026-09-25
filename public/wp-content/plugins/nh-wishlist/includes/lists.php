<?php
/**
 * Wishlist data rules: lists, custom-cut dimensions, guest cookie payload.
 *
 * This file does not call WordPress. Storage and pages sit on top of it.
 *
 * @package nh-wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fresh wishlist with the default list.
 *
 * @return array{active:string,lists:array<int,array<string,mixed>>}
 */
function nh_wl_empty_state() {
	return array(
		'active' => 'default',
		'lists'  => array(
			array(
				'id'    => 'default',
				'name'  => 'Default',
				'items' => array(),
			),
		),
	);
}

/**
 * @param mixed $value Raw metres.
 * @return float
 */
function nh_wl_parse_metres( $value ) {
	if ( is_string( $value ) ) {
		$value = str_replace( ',', '.', trim( $value ) );
	}
	$number = (float) $value;
	if ( $number < 0 ) {
		$number = 0;
	}
	if ( $number > 10000 ) {
		$number = 10000;
	}
	return round( $number, 3 );
}

/**
 * Stable metre text so 2.5 and 2.50 share one wishlist key.
 *
 * @param mixed $metres Metres.
 * @return string
 */
function nh_wl_format_metres( $metres ) {
	$metres = nh_wl_parse_metres( $metres );
	if ( $metres <= 0 ) {
		return '0';
	}
	$text = number_format( $metres, 3, '.', '' );
	return rtrim( rtrim( $text, '0' ), '.' );
}

/**
 * @param mixed $dimensions Dimensions from the browser or storage.
 * @return array{unit:string,type:string,width_mm:int,length_mm:int,length_m:float}
 */
function nh_wl_normalize_dimensions( $dimensions ) {
	$dimensions = is_array( $dimensions ) ? $dimensions : array();
	$unit       = ( isset( $dimensions['unit'] ) && 'm' === $dimensions['unit'] ) ? 'm' : 'mm';
	$type       = ( isset( $dimensions['type'] ) && 'linear' === $dimensions['type'] ) ? 'linear' : 'planar';
	$width      = isset( $dimensions['width_mm'] ) ? (int) $dimensions['width_mm'] : 0;
	$length     = isset( $dimensions['length_mm'] ) ? (int) $dimensions['length_mm'] : 0;
	if ( $width < 0 ) {
		$width = 0;
	}
	if ( $length < 0 ) {
		$length = 0;
	}
	if ( $width > 100000 ) {
		$width = 100000;
	}
	if ( $length > 100000 ) {
		$length = 100000;
	}
	return array(
		'unit'      => $unit,
		'type'      => $type,
		'width_mm'  => $width,
		'length_mm' => $length,
		'length_m'  => nh_wl_parse_metres( isset( $dimensions['length_m'] ) ? $dimensions['length_m'] : 0 ),
	);
}

/**
 * @param mixed $attributes Variation attributes.
 * @return array<string,string>
 */
function nh_wl_normalize_attributes( $attributes ) {
	$attributes = is_array( $attributes ) ? $attributes : array();
	$clean      = array();
	foreach ( $attributes as $key => $value ) {
		$key = (string) $key;
		if ( ! preg_match( '/^attribute_[a-z0-9_-]+$/i', $key ) ) {
			continue;
		}
		$value = trim( (string) $value );
		if ( strlen( $value ) > 80 ) {
			$value = substr( $value, 0, 80 );
		}
		$clean[ $key ] = $value;
		if ( count( $clean ) >= 20 ) {
			break;
		}
	}
	ksort( $clean, SORT_STRING );
	return $clean;
}

/**
 * @param int                  $product_id Parent product.
 * @param int                  $variation_id Variation, or 0.
 * @param array<string,mixed>  $dimensions Dimensions.
 * @param array<string,string> $attributes Attributes.
 * @return string
 */
function nh_wl_item_key( $product_id, $variation_id, $dimensions, $attributes ) {
	$dimensions = nh_wl_normalize_dimensions( $dimensions );
	$attributes = nh_wl_normalize_attributes( $attributes );
	$payload    = json_encode(
		array(
			(int) $product_id,
			(int) $variation_id,
			(int) $dimensions['width_mm'],
			(int) $dimensions['length_mm'],
			nh_wl_format_metres( $dimensions['length_m'] ),
			$attributes,
		)
	);
	return substr( sha1( (string) $payload ), 0, 16 );
}

/**
 * Custom-cut lines need a full size. Linear products need metres.
 *
 * @param array<string,mixed> $dimensions Dimensions.
 * @return bool
 */
function nh_wl_dimensions_complete( $dimensions ) {
	$dimensions = nh_wl_normalize_dimensions( $dimensions );
	if ( 'm' === $dimensions['unit'] || 'linear' === $dimensions['type'] ) {
		return $dimensions['length_m'] > 0;
	}
	return $dimensions['width_mm'] > 0 && $dimensions['length_mm'] > 0;
}

/**
 * Variable products need a variation. Custom-cut products need dimensions.
 *
 * @param array<string,mixed> $context Flags and the saved selection.
 * @return bool
 */
function nh_wl_needs_customize( $context ) {
	$context      = is_array( $context ) ? $context : array();
	$is_variable  = ! empty( $context['is_variable'] );
	$is_custom    = ! empty( $context['is_custom'] );
	$variation_id = isset( $context['variation_id'] ) ? (int) $context['variation_id'] : 0;
	$dimensions   = nh_wl_normalize_dimensions( isset( $context['dimensions'] ) ? $context['dimensions'] : array() );
	if ( $is_variable && $variation_id <= 0 ) {
		return true;
	}
	if ( $is_custom && ! nh_wl_dimensions_complete( $dimensions ) ) {
		return true;
	}
	return false;
}

/**
 * SKU shown on the list, PDF, and quote.
 * An unfinished product uses the parent SKU. A finished selection uses its own SKU.
 *
 * @param string $parent_sku Parent SKU.
 * @param string $selected_sku Variation or simple-product SKU.
 * @param bool   $needs_customize Whether a variation or size is still missing.
 * @return string
 */
function nh_wl_display_sku( $parent_sku, $selected_sku, $needs_customize ) {
	$parent   = trim( (string) $parent_sku );
	$selected = trim( (string) $selected_sku );
	if ( $needs_customize || '' === $selected ) {
		return $parent;
	}
	return $selected;
}

/**
 * Recompute the notice against the live product type and cut settings.
 *
 * @param array<string,mixed> $item Stored item.
 * @param array<string,mixed> $flags Live product flags.
 * @return bool
 */
function nh_wl_item_needs_customize( $item, $flags ) {
	$item  = is_array( $item ) ? $item : array();
	$flags = is_array( $flags ) ? $flags : array();
	$dims  = nh_wl_normalize_dimensions( isset( $item['dimensions'] ) ? $item['dimensions'] : array() );
	if ( ! empty( $flags['is_custom'] ) ) {
		$dims['unit'] = ( isset( $flags['unit'] ) && 'm' === $flags['unit'] ) ? 'm' : 'mm';
		$dims['type'] = ( isset( $flags['type'] ) && 'linear' === $flags['type'] ) ? 'linear' : 'planar';
	}
	return nh_wl_needs_customize(
		array(
			'is_variable'  => ! empty( $flags['is_variable'] ),
			'is_custom'    => ! empty( $flags['is_custom'] ),
			'variation_id' => isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0,
			'dimensions'   => $dims,
		)
	);
}

/**
 * Area price or length price, plus the cutting fee. Raw shop currency, before display tax.
 *
 * @param float               $base_price Price per m² or per metre.
 * @param float               $fee Cutting fee.
 * @param array<string,mixed> $dimensions Dimensions.
 * @return float
 */
function nh_wl_custom_unit_price( $base_price, $fee, $dimensions ) {
	$dimensions = nh_wl_normalize_dimensions( $dimensions );
	$base_price = (float) $base_price;
	$fee        = max( 0, (float) $fee );
	if ( $base_price <= 0 || ! nh_wl_dimensions_complete( $dimensions ) ) {
		return 0.0;
	}
	if ( 'm' === $dimensions['unit'] || 'linear' === $dimensions['type'] ) {
		return ( $dimensions['length_m'] * $base_price ) + $fee;
	}
	$area = ( $dimensions['width_mm'] / 1000 ) * ( $dimensions['length_mm'] / 1000 );
	return ( $area * $base_price ) + $fee;
}

/**
 * POST fields the custom-cut cart filter reads.
 *
 * @param array<string,mixed> $item Stored item.
 * @param array<string,mixed> $flags Live product flags.
 * @return array<string,string>
 */
function nh_wl_post_fields_for_cart( $item, $flags ) {
	$item  = is_array( $item ) ? $item : array();
	$flags = is_array( $flags ) ? $flags : array();
	$dims  = nh_wl_normalize_dimensions( isset( $item['dimensions'] ) ? $item['dimensions'] : array() );
	$fields = array(
		'variation_id' => (string) (int) ( isset( $item['variation_id'] ) ? $item['variation_id'] : 0 ),
	);
	if ( ! empty( $flags['is_custom'] ) ) {
		$fields['nh_custom_cutting'] = '1';
		$fields['nh_width_mm']       = (string) (int) $dims['width_mm'];
		$fields['nh_length_mm']      = (string) (int) $dims['length_mm'];
		$fields['nh_length_m']       = nh_wl_format_metres( $dims['length_m'] );
	}
	if ( ! empty( $item['attributes'] ) && is_array( $item['attributes'] ) ) {
		foreach ( nh_wl_normalize_attributes( $item['attributes'] ) as $key => $value ) {
			$fields[ $key ] = $value;
		}
	}
	return $fields;
}

/**
 * @param array<string,mixed> $input Selection.
 * @return array<string,mixed>
 */
function nh_wl_make_item( $input ) {
	$input = is_array( $input ) ? $input : array();
	$dims  = nh_wl_normalize_dimensions( isset( $input['dimensions'] ) ? $input['dimensions'] : array() );
	if ( array_key_exists( 'is_custom', $input ) && empty( $input['is_custom'] ) ) {
		$dims = nh_wl_normalize_dimensions( array() );
	}
	$variation_id = isset( $input['variation_id'] ) ? (int) $input['variation_id'] : 0;
	if ( $variation_id < 0 ) {
		$variation_id = 0;
	}
	$attributes = $variation_id > 0 ? nh_wl_normalize_attributes( isset( $input['attributes'] ) ? $input['attributes'] : array() ) : array();
	$quantity   = isset( $input['quantity'] ) ? (int) $input['quantity'] : 1;
	if ( $quantity < 1 ) {
		$quantity = 1;
	}
	if ( $quantity > 999 ) {
		$quantity = 999;
	}
	$product_id = isset( $input['product_id'] ) ? (int) $input['product_id'] : 0;
	if ( $product_id < 0 ) {
		$product_id = 0;
	}
	$item = array(
		'product_id'   => $product_id,
		'variation_id' => $variation_id,
		'quantity'     => $quantity,
		'attributes'   => $attributes,
		'dimensions'   => $dims,
		'added'        => isset( $input['added'] ) ? (int) $input['added'] : time(),
	);
	$item['key'] = nh_wl_item_key( $product_id, $variation_id, $dims, $attributes );
	return $item;
}

/**
 * @param mixed $item Stored item.
 * @return array<string,mixed>
 */
function nh_wl_normalize_item( $item ) {
	$item = is_array( $item ) ? $item : array();
	return nh_wl_make_item(
		array(
			'product_id'   => isset( $item['product_id'] ) ? $item['product_id'] : 0,
			'variation_id' => isset( $item['variation_id'] ) ? $item['variation_id'] : 0,
			'quantity'     => isset( $item['quantity'] ) ? $item['quantity'] : 1,
			'attributes'   => isset( $item['attributes'] ) ? $item['attributes'] : array(),
			'dimensions'   => isset( $item['dimensions'] ) ? $item['dimensions'] : array(),
			'is_custom'    => true,
			'added'        => isset( $item['added'] ) ? $item['added'] : time(),
		)
	);
}

/**
 * @param string $name List name.
 * @return string
 */
function nh_wl_clean_list_name( $name ) {
	$name = trim( (string) $name );
	$name = preg_replace( '/\s+/', ' ', $name );
	$name = str_replace( array( '<', '>' ), '', (string) $name );
	if ( function_exists( 'nh_wl_utf8_limit' ) ) {
		$name = nh_wl_utf8_limit( $name, 80 );
	} elseif ( strlen( $name ) > 80 ) {
		$name = substr( $name, 0, 80 );
	}
	return trim( $name );
}

/**
 * @param string        $name Name.
 * @param array<string> $existing_ids Existing ids.
 * @return string
 */
function nh_wl_list_id_from_name( $name, $existing_ids ) {
	$existing_ids = is_array( $existing_ids ) ? $existing_ids : array();
	$base         = 'l_' . substr( md5( strtolower( $name ) ), 0, 8 );
	$id           = $base;
	$n            = 2;
	while ( in_array( $id, $existing_ids, true ) || 'default' === $id ) {
		$id = $base . '_' . $n;
		++$n;
	}
	return $id;
}

/**
 * @param mixed $state Stored state.
 * @return array{active:string,lists:array<int,array<string,mixed>>}
 */
function nh_wl_normalize_state( $state ) {
	if ( ! is_array( $state ) ) {
		return nh_wl_empty_state();
	}
	$lists = array();
	$seen  = array();
	$raw   = isset( $state['lists'] ) && is_array( $state['lists'] ) ? $state['lists'] : array();
	foreach ( $raw as $list ) {
		if ( ! is_array( $list ) ) {
			continue;
		}
		$id = isset( $list['id'] ) ? (string) $list['id'] : '';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $id ) || strlen( $id ) > 32 ) {
			continue;
		}
		if ( isset( $seen[ $id ] ) ) {
			continue;
		}
		$name = nh_wl_clean_list_name( isset( $list['name'] ) ? $list['name'] : '' );
		if ( '' === $name ) {
			$name = 'default' === $id ? 'Default' : $id;
		}
		$items = array();
		if ( isset( $list['items'] ) && is_array( $list['items'] ) ) {
			foreach ( $list['items'] as $item ) {
				$normalized = nh_wl_normalize_item( $item );
				if ( $normalized['product_id'] <= 0 ) {
					continue;
				}
				$items[ $normalized['key'] ] = $normalized;
				if ( count( $items ) >= 100 ) {
					break;
				}
			}
		}
		$lists[]    = array(
			'id'    => $id,
			'name'  => $name,
			'items' => array_values( $items ),
		);
		$seen[ $id ] = true;
		if ( count( $lists ) >= 20 ) {
			break;
		}
	}
	if ( ! $lists ) {
		return nh_wl_empty_state();
	}
	$active = isset( $state['active'] ) ? (string) $state['active'] : $lists[0]['id'];
	$ids    = array_column( $lists, 'id' );
	if ( ! in_array( $active, $ids, true ) ) {
		$active = $lists[0]['id'];
	}
	return array(
		'active' => $active,
		'lists'  => $lists,
	);
}

/**
 * @param array<string,mixed> $state State.
 * @param string              $list_id List id.
 * @return array<string,mixed>|null
 */
function nh_wl_find_list( $state, $list_id ) {
	$state = nh_wl_normalize_state( $state );
	foreach ( $state['lists'] as $list ) {
		if ( $list['id'] === $list_id ) {
			return $list;
		}
	}
	return null;
}

/**
 * @param array<string,mixed> $state State.
 * @param string              $list_id List id.
 * @param array<string,mixed> $item Item from nh_wl_make_item().
 * @return array{ok:bool,error:string,state:array<string,mixed>,item:array<string,mixed>}
 */
function nh_wl_add_item( $state, $list_id, $item ) {
	$state = nh_wl_normalize_state( $state );
	$item  = nh_wl_make_item( $item );
	if ( $item['product_id'] <= 0 ) {
		return array(
			'ok'    => false,
			'error' => 'invalid_product',
			'state' => $state,
			'item'  => $item,
		);
	}
	$found = false;
	foreach ( $state['lists'] as $index => $list ) {
		if ( $list['id'] !== $list_id ) {
			continue;
		}
		$found = true;
		$next  = array();
		$kept  = false;
		foreach ( $list['items'] as $existing ) {
			if ( $existing['key'] === $item['key'] ) {
				$item['added'] = $existing['added'];
				$next[]        = $item;
				$kept          = true;
				continue;
			}
			$next[] = $existing;
		}
		if ( ! $kept ) {
			if ( count( $next ) >= 100 ) {
				return array(
					'ok'    => false,
					'error' => 'list_full',
					'state' => $state,
					'item'  => $item,
				);
			}
			$next[] = $item;
		}
		$state['lists'][ $index ]['items'] = $next;
		$state['active']                   = $list_id;
		break;
	}
	if ( ! $found ) {
		return array(
			'ok'    => false,
			'error' => 'invalid_list',
			'state' => $state,
			'item'  => $item,
		);
	}
	return array(
		'ok'    => true,
		'error' => '',
		'state' => nh_wl_normalize_state( $state ),
		'item'  => $item,
	);
}

/**
 * @param array<string,mixed> $state State.
 * @param string              $list_id List id.
 * @param string              $key Item key.
 * @return array{ok:bool,error:string,state:array<string,mixed>}
 */
function nh_wl_remove_item( $state, $list_id, $key ) {
	$state = nh_wl_normalize_state( $state );
	$key   = (string) $key;
	$found = false;
	foreach ( $state['lists'] as $index => $list ) {
		if ( $list['id'] !== $list_id ) {
			continue;
		}
		$found = true;
		$next  = array();
		foreach ( $list['items'] as $item ) {
			if ( $item['key'] !== $key ) {
				$next[] = $item;
			}
		}
		$state['lists'][ $index ]['items'] = $next;
		break;
	}
	return array(
		'ok'    => $found,
		'error' => $found ? '' : 'invalid_list',
		'state' => nh_wl_normalize_state( $state ),
	);
}

/**
 * @param array<string,mixed> $state State.
 * @param string              $list_id List id.
 * @param string              $key Item key.
 * @param int                 $quantity Quantity.
 * @return array{ok:bool,error:string,state:array<string,mixed>}
 */
function nh_wl_update_quantity( $state, $list_id, $key, $quantity ) {
	$state    = nh_wl_normalize_state( $state );
	$quantity = (int) $quantity;
	if ( $quantity < 1 ) {
		$quantity = 1;
	}
	if ( $quantity > 999 ) {
		$quantity = 999;
	}
	$found = false;
	foreach ( $state['lists'] as $index => $list ) {
		if ( $list['id'] !== $list_id ) {
			continue;
		}
		foreach ( $list['items'] as $item_index => $item ) {
			if ( $item['key'] !== $key ) {
				continue;
			}
			$found = true;
			$state['lists'][ $index ]['items'][ $item_index ]['quantity'] = $quantity;
			break 2;
		}
	}
	return array(
		'ok'    => $found,
		'error' => $found ? '' : 'invalid_list',
		'state' => nh_wl_normalize_state( $state ),
	);
}

/**
 * @param array<int,array<string,mixed>> $lists Lists.
 * @param string                         $name Name.
 * @param string                         $ignore_id List id to ignore.
 * @return bool
 */
function nh_wl_list_name_taken( $lists, $name, $ignore_id = '' ) {
	$needle = strtolower( $name );
	foreach ( $lists as $list ) {
		if ( $ignore_id !== '' && $list['id'] === $ignore_id ) {
			continue;
		}
		if ( strtolower( (string) $list['name'] ) === $needle ) {
			return true;
		}
	}
	return false;
}

/**
 * @param array<string,mixed> $state State.
 * @param string              $name Name.
 * @return array{ok:bool,error:string,state:array<string,mixed>,list_id:string}
 */
function nh_wl_create_list( $state, $name ) {
	$state = nh_wl_normalize_state( $state );
	$name  = nh_wl_clean_list_name( $name );
	if ( '' === $name ) {
		return array(
			'ok'      => false,
			'error'   => 'empty_name',
			'state'   => $state,
			'list_id' => '',
		);
	}
	if ( count( $state['lists'] ) >= 20 ) {
		return array(
			'ok'      => false,
			'error'   => 'too_many',
			'state'   => $state,
			'list_id' => '',
		);
	}
	if ( nh_wl_list_name_taken( $state['lists'], $name ) ) {
		return array(
			'ok'      => false,
			'error'   => 'duplicate_name',
			'state'   => $state,
			'list_id' => '',
		);
	}
	$ids = array_column( $state['lists'], 'id' );
	$id  = nh_wl_list_id_from_name( $name, $ids );
	$state['lists'][] = array(
		'id'    => $id,
		'name'  => $name,
		'items' => array(),
	);
	$state['active'] = $id;
	return array(
		'ok'      => true,
		'error'   => '',
		'state'   => nh_wl_normalize_state( $state ),
		'list_id' => $id,
	);
}

/**
 * @param array<string,mixed> $state State.
 * @param string              $list_id List id.
 * @param string              $name Name.
 * @return array{ok:bool,error:string,state:array<string,mixed>}
 */
function nh_wl_rename_list( $state, $list_id, $name ) {
	$state = nh_wl_normalize_state( $state );
	$name  = nh_wl_clean_list_name( $name );
	if ( '' === $name ) {
		return array(
			'ok'    => false,
			'error' => 'empty_name',
			'state' => $state,
		);
	}
	if ( nh_wl_list_name_taken( $state['lists'], $name, $list_id ) ) {
		return array(
			'ok'    => false,
			'error' => 'duplicate_name',
			'state' => $state,
		);
	}
	$found = false;
	foreach ( $state['lists'] as $index => $list ) {
		if ( $list['id'] !== $list_id ) {
			continue;
		}
		$found                        = true;
		$state['lists'][ $index ]['name'] = $name;
		break;
	}
	return array(
		'ok'    => $found,
		'error' => $found ? '' : 'invalid_list',
		'state' => nh_wl_normalize_state( $state ),
	);
}

/**
 * @param array<string,mixed> $state State.
 * @param string              $list_id List id.
 * @return array{ok:bool,error:string,state:array<string,mixed>}
 */
function nh_wl_delete_list( $state, $list_id ) {
	$state = nh_wl_normalize_state( $state );
	if ( count( $state['lists'] ) < 2 ) {
		return array(
			'ok'    => false,
			'error' => 'last_list',
			'state' => $state,
		);
	}
	$next  = array();
	$found = false;
	foreach ( $state['lists'] as $list ) {
		if ( $list['id'] === $list_id ) {
			$found = true;
			continue;
		}
		$next[] = $list;
	}
	if ( ! $found ) {
		return array(
			'ok'    => false,
			'error' => 'invalid_list',
			'state' => $state,
		);
	}
	$state['lists'] = $next;
	if ( $state['active'] === $list_id ) {
		$state['active'] = $next[0]['id'];
	}
	return array(
		'ok'    => true,
		'error' => '',
		'state' => nh_wl_normalize_state( $state ),
	);
}

/**
 * @param array<string,mixed> $base Account state.
 * @param array<string,mixed> $extra Guest state.
 * @return array{active:string,lists:array<int,array<string,mixed>>}
 */
function nh_wl_merge_states( $base, $extra ) {
	$base  = nh_wl_normalize_state( $base );
	$extra = nh_wl_normalize_state( $extra );
	$by_id = array();
	foreach ( $base['lists'] as $list ) {
		$by_id[ $list['id'] ] = $list;
	}
	foreach ( $extra['lists'] as $list ) {
		$has_items = ! empty( $list['items'] );
		if ( ! $has_items && isset( $by_id[ $list['id'] ] ) ) {
			continue;
		}
		if ( ! isset( $by_id[ $list['id'] ] ) ) {
			$names = array();
			foreach ( $by_id as $existing ) {
				$names[] = $existing;
			}
			$suffix = 2;
			$name   = $list['name'];
			while ( nh_wl_list_name_taken( $names, $name ) ) {
				$name = nh_wl_clean_list_name( $list['name'] . ' ' . $suffix );
				++$suffix;
			}
			$list['name']           = $name;
			$by_id[ $list['id'] ]   = $list;
			continue;
		}
		if ( ! $has_items ) {
			continue;
		}
		$items = array();
		foreach ( $by_id[ $list['id'] ]['items'] as $item ) {
			$items[ $item['key'] ] = $item;
		}
		foreach ( $list['items'] as $item ) {
			if ( isset( $items[ $item['key'] ] ) ) {
				$items[ $item['key'] ]['quantity'] = max( (int) $items[ $item['key'] ]['quantity'], (int) $item['quantity'] );
				continue;
			}
			if ( count( $items ) >= 100 ) {
				break;
			}
			$items[ $item['key'] ] = $item;
		}
		$by_id[ $list['id'] ]['items'] = array_values( $items );
	}
	$base['lists'] = array_values( $by_id );
	return nh_wl_normalize_state( $base );
}

/**
 * @param array<string,mixed> $state State.
 * @return string
 */
function nh_wl_encode_cookie( $state ) {
	$json = json_encode( nh_wl_normalize_state( $state ) );
	return base64_encode( (string) $json );
}

/**
 * @param mixed $raw Cookie value.
 * @return array{active:string,lists:array<int,array<string,mixed>>}
 */
function nh_wl_decode_cookie( $raw ) {
	if ( ! is_string( $raw ) || '' === $raw ) {
		return nh_wl_empty_state();
	}
	$json = base64_decode( $raw, true );
	if ( false === $json ) {
		return nh_wl_empty_state();
	}
	$data = json_decode( $json, true );
	if ( ! is_array( $data ) ) {
		return nh_wl_empty_state();
	}
	return nh_wl_normalize_state( $data );
}

/**
 * Browsers keep about 4KB per cookie. Leave room for the cookie name and attributes.
 *
 * @param string $encoded Encoded payload.
 * @return bool
 */
function nh_wl_cookie_fits( $encoded ) {
	return strlen( (string) $encoded ) <= 3500;
}

/**
 * @param array<string,mixed> $state State.
 * @return int
 */
function nh_wl_count_items( $state ) {
	$state = nh_wl_normalize_state( $state );
	$count = 0;
	foreach ( $state['lists'] as $list ) {
		$count += count( $list['items'] );
	}
	return $count;
}

/**
 * @param array<string,mixed> $state State.
 * @param int                 $product_id Product id.
 * @return bool
 */
function nh_wl_product_is_saved( $state, $product_id ) {
	$state      = nh_wl_normalize_state( $state );
	$product_id = (int) $product_id;
	foreach ( $state['lists'] as $list ) {
		foreach ( $list['items'] as $item ) {
			if ( (int) $item['product_id'] === $product_id ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Rows the heart script uses to mark saved products.
 *
 * @param array<string,mixed> $state State.
 * @return array<int,array<string,mixed>>
 */
function nh_wl_saved_index( $state ) {
	$state = nh_wl_normalize_state( $state );
	$rows  = array();
	foreach ( $state['lists'] as $list ) {
		foreach ( $list['items'] as $item ) {
			$dims   = $item['dimensions'];
			$rows[] = array(
				'key'          => $item['key'],
				'list'         => $list['id'],
				'product_id'   => (int) $item['product_id'],
				'variation_id' => (int) $item['variation_id'],
				'width_mm'     => (int) $dims['width_mm'],
				'length_mm'    => (int) $dims['length_mm'],
				'length_m'     => nh_wl_format_metres( $dims['length_m'] ),
			);
		}
	}
	return $rows;
}

/**
 * UTF-8 codepoints. Wishlist text is inside the basic multilingual plane.
 *
 * @param string $text Text.
 * @return array<int,int>
 */
function nh_wl_utf8_codepoints( $text ) {
	$text = (string) $text;
	$out  = array();
	$len  = strlen( $text );
	$i    = 0;
	while ( $i < $len ) {
		$c = ord( $text[ $i ] );
		if ( $c < 0x80 ) {
			$out[] = $c;
			++$i;
		} elseif ( ( $c & 0xE0 ) === 0xC0 && $i + 1 < $len ) {
			$out[] = ( ( $c & 0x1F ) << 6 ) | ( ord( $text[ $i + 1 ] ) & 0x3F );
			$i    += 2;
		} elseif ( ( $c & 0xF0 ) === 0xE0 && $i + 2 < $len ) {
			$out[] = ( ( $c & 0x0F ) << 12 ) | ( ( ord( $text[ $i + 1 ] ) & 0x3F ) << 6 ) | ( ord( $text[ $i + 2 ] ) & 0x3F );
			$i    += 3;
		} elseif ( ( $c & 0xF8 ) === 0xF0 && $i + 3 < $len ) {
			$out[] = ( ( $c & 0x07 ) << 18 ) | ( ( ord( $text[ $i + 1 ] ) & 0x3F ) << 12 ) | ( ( ord( $text[ $i + 2 ] ) & 0x3F ) << 6 ) | ( ord( $text[ $i + 3 ] ) & 0x3F );
			$i    += 4;
		} else {
			$out[] = 0xFFFD;
			++$i;
		}
	}
	return $out;
}

/**
 * @param string $text Text.
 * @param int    $limit Codepoint limit.
 * @return string
 */
function nh_wl_utf8_limit( $text, $limit ) {
	$codes = nh_wl_utf8_codepoints( $text );
	if ( count( $codes ) <= $limit ) {
		return (string) $text;
	}
	$codes = array_slice( $codes, 0, $limit );
	$out   = '';
	foreach ( $codes as $code ) {
		$out .= nh_wl_codepoint_utf8( $code );
	}
	return $out;
}

/**
 * @param int $code Codepoint.
 * @return string
 */
function nh_wl_codepoint_utf8( $code ) {
	$code = (int) $code;
	if ( $code < 0 || $code > 0x10FFFF ) {
		$code = 0xFFFD;
	}
	if ( $code < 0x80 ) {
		return chr( $code );
	}
	if ( $code < 0x800 ) {
		return chr( 0xC0 | ( $code >> 6 ) ) . chr( 0x80 | ( $code & 0x3F ) );
	}
	if ( $code < 0x10000 ) {
		return chr( 0xE0 | ( $code >> 12 ) ) . chr( 0x80 | ( ( $code >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $code & 0x3F ) );
	}
	return chr( 0xF0 | ( $code >> 18 ) ) . chr( 0x80 | ( ( $code >> 12 ) & 0x3F ) ) . chr( 0x80 | ( ( $code >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $code & 0x3F ) );
}

/**
 * Run a callback while selected POST fields are set, then restore them.
 *
 * @param array<string,string> $fields Fields.
 * @param callable             $callback Callback.
 * @return mixed
 */
function nh_wl_with_post_fields( $fields, $callback ) {
	$backup = array();
	foreach ( $fields as $key => $value ) {
		$backup[ $key ] = array(
			'had'   => array_key_exists( $key, $_POST ),
			'value' => array_key_exists( $key, $_POST ) ? $_POST[ $key ] : null,
		);
		$_POST[ $key ] = $value;
	}
	try {
		return $callback();
	} finally {
		foreach ( $backup as $key => $previous ) {
			if ( $previous['had'] ) {
				$_POST[ $key ] = $previous['value'];
			} else {
				unset( $_POST[ $key ] );
			}
		}
	}
}
