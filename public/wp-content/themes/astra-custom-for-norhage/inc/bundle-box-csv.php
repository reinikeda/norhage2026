<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bundle box rows as a WooCommerce product CSV column.
 *
 * Hidden meta (_nc_bundle_items_v2) is skipped by WooCommerce export.
 * This file adds a readable "Bundle items" column and writes the same
 * meta back on import.
 *
 * Cell format, add-ons separated with " || ":
 *   SKU | max=2 | free=1 | pa_width=10-mm || OTHER-SKU | pa_width=25-mm
 *
 * Newlines and the older "SKU; pa_width=10-mm" form still import.
 */

if ( ! defined( 'NH_BUNDLE_META_KEY' ) ) {
	define( 'NH_BUNDLE_META_KEY', '_nh_bundle_items_v2' );
}
if ( ! defined( 'NH_BUNDLE_META_KEY_COMPAT' ) ) {
	define( 'NH_BUNDLE_META_KEY_COMPAT', '_nc_bundle_items_v2' );
}
if ( ! defined( 'NH_BUNDLE_NAME_META_KEY' ) ) {
	define( 'NH_BUNDLE_NAME_META_KEY', '_bundle_box_name' );
}

if ( ! function_exists( 'nh_bundle_csv_reserved_keys' ) ) {
	function nh_bundle_csv_reserved_keys() {
		return array( 'sku', 'id', 'max', 'free', 'qty', 'quantity' );
	}
}

if ( ! function_exists( 'nh_bundle_csv_normalize_attr_key' ) ) {
	/**
	 * @param string $key width, pa_width, or attribute_pa_width.
	 */
	function nh_bundle_csv_normalize_attr_key( $key ) {
		$key = sanitize_key( (string) $key );
		if ( 0 === strpos( $key, 'attribute_' ) ) {
			$key = substr( $key, 10 );
		}

		if ( $key === '' ) {
			return '';
		}

		if ( 0 === strpos( $key, 'pa_' ) ) {
			return $key;
		}

		$prefixed = 'pa_' . $key;
		if ( function_exists( 'taxonomy_exists' ) ) {
			if ( taxonomy_exists( $prefixed ) ) {
				return $prefixed;
			}
			if ( taxonomy_exists( $key ) ) {
				return $key;
			}
			return $key;
		}

		return $prefixed;
	}
}

if ( ! function_exists( 'nh_bundle_csv_resolve_attr_value' ) ) {
	/**
	 * Keep a matching term slug. If the CSV has a visible name, use that term.
	 *
	 * @param string $taxonomy pa_width.
	 * @param string $value    Slug or name.
	 */
	function nh_bundle_csv_resolve_attr_value( $taxonomy, $value ) {
		$value = wc_clean( wp_unslash( (string) $value ) );
		if ( $value === '' ) {
			return '';
		}

		if ( ! function_exists( 'get_term_by' ) || ! $taxonomy ) {
			return $value;
		}

		$by_slug = get_term_by( 'slug', $value, $taxonomy );
		if ( $by_slug && ! is_wp_error( $by_slug ) && ! empty( $by_slug->slug ) ) {
			return (string) $by_slug->slug;
		}

		$by_name = get_term_by( 'name', $value, $taxonomy );
		if ( $by_name && ! is_wp_error( $by_name ) && ! empty( $by_name->slug ) ) {
			return (string) $by_name->slug;
		}

		return $value;
	}
}

if ( ! function_exists( 'nh_bundle_csv_parse_bool' ) ) {
	function nh_bundle_csv_parse_bool( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( '1', 'yes', 'true', 'on' ), true );
	}
}

if ( ! function_exists( 'nh_bundle_csv_looks_like_sku' ) ) {
	function nh_bundle_csv_looks_like_sku( $token ) {
		$token = trim( (string) $token );
		if ( $token === '' || 0 === strcasecmp( $token, 'free' ) ) {
			return false;
		}

		if ( 0 === stripos( $token, 'id:' ) ) {
			return true;
		}

		if ( preg_match( '/^\d{5,}$/', $token ) ) {
			return true;
		}

		if ( preg_match( '/-(mm|cm|m|ml)$/i', $token ) ) {
			return false;
		}

		if ( 0 === strpos( $token, 'pa_' ) || false !== strpos( $token, '=' ) ) {
			return false;
		}

		return (bool) preg_match( '/^[A-Z][A-Z0-9._-]{2,}$/i', $token );
	}
}

if ( ! function_exists( 'nh_bundle_csv_peel_trailing_skus' ) ) {
	/**
	 * @return array{0:string,1:string[]}
	 */
	function nh_bundle_csv_peel_trailing_skus( $value ) {
		$tokens = preg_split( '/\s+/', trim( (string) $value ) );
		$skus   = array();

		while ( count( $tokens ) > 1 && nh_bundle_csv_looks_like_sku( end( $tokens ) ) ) {
			array_unshift( $skus, array_pop( $tokens ) );
		}

		return array( implode( ' ', $tokens ), $skus );
	}
}

if ( ! function_exists( 'nh_bundle_csv_blank_item' ) ) {
	function nh_bundle_csv_blank_item() {
		return array(
			'sku'          => '',
			'max'          => '',
			'free'         => 0,
			'locked_attrs' => array(),
		);
	}
}

if ( ! function_exists( 'nh_bundle_csv_parse_line' ) ) {
	/**
	 * @return array<int,array{sku:string,max:string,free:int,locked_attrs:array}>
	 */
	function nh_bundle_csv_parse_line( $line ) {
		$line = trim( (string) $line );
		if ( $line === '' || 0 === strpos( $line, '#' ) ) {
			return array();
		}

		$line = str_replace( ' | ', '; ', $line );
		$items = array();
		$cur   = nh_bundle_csv_blank_item();

		foreach ( explode( ';', $line ) as $part ) {
			$part = trim( $part );
			if ( $part === '' ) {
				continue;
			}

			if ( false === strpos( $part, '=' ) ) {
				if ( 0 === strcasecmp( $part, 'free' ) ) {
					$cur['free'] = 1;
					continue;
				}

				foreach ( preg_split( '/\s+/', $part ) as $token ) {
					if ( $token === '' ) {
						continue;
					}
					if ( $cur['sku'] !== '' ) {
						$items[] = $cur;
						$cur     = nh_bundle_csv_blank_item();
					}
					$cur['sku'] = $token;
				}
				continue;
			}

			$bits  = explode( '=', $part, 2 );
			$key   = strtolower( trim( $bits[0] ) );
			$value = isset( $bits[1] ) ? trim( $bits[1] ) : '';
			list( $value, $extra_skus ) = nh_bundle_csv_peel_trailing_skus( $value );

			if ( $key === 'sku' ) {
				if ( $cur['sku'] !== '' && $value !== $cur['sku'] ) {
					$items[] = $cur;
					$cur     = nh_bundle_csv_blank_item();
				}
				$cur['sku'] = $value;
			} elseif ( $key === 'id' ) {
				if ( $cur['sku'] === '' && $value !== '' ) {
					$cur['sku'] = ( 0 === stripos( $value, 'id:' ) ) ? $value : ( 'id:' . $value );
				}
			} elseif ( $key === 'max' ) {
				$cur['max'] = $value;
			} elseif ( $key === 'free' ) {
				$cur['free'] = nh_bundle_csv_parse_bool( $value ) ? 1 : 0;
			} elseif ( ! in_array( $key, nh_bundle_csv_reserved_keys(), true ) ) {
				$taxonomy = nh_bundle_csv_normalize_attr_key( $key );
				if ( $taxonomy !== '' && $value !== '' ) {
					$cur['locked_attrs'][ $taxonomy ] = $value;
				}
			}

			foreach ( $extra_skus as $sku ) {
				if ( $cur['sku'] !== '' ) {
					$items[] = $cur;
					$cur     = nh_bundle_csv_blank_item();
				}
				$cur['sku'] = $sku;
			}
		}

		if ( $cur['sku'] !== '' ) {
			$items[] = $cur;
		}

		return $items;
	}
}

if ( ! function_exists( 'nh_bundle_csv_parse_items' ) ) {
	/**
	 * @param string $text CSV cell.
	 * @return array<int,array{sku:string,max:string,free:int,locked_attrs:array}>
	 */
	function nh_bundle_csv_parse_items( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$text = str_replace( array( ' || ', '||' ), "\n", $text );
		$out  = array();

		foreach ( preg_split( "/\n+/", $text ) as $line ) {
			foreach ( nh_bundle_csv_parse_line( $line ) as $parsed ) {
				$out[] = $parsed;
			}
		}

		return $out;
	}
}

if ( ! function_exists( 'nh_bundle_csv_format_line' ) ) {
	function nh_bundle_csv_format_line( array $row ) {
		$sku = isset( $row['sku'] ) ? trim( (string) $row['sku'] ) : '';
		if ( $sku === '' ) {
			return '';
		}

		$parts = array( $sku );

		if ( isset( $row['max'] ) && $row['max'] !== '' && (int) $row['max'] > 0 ) {
			$parts[] = 'max=' . (int) $row['max'];
		}

		if ( ! empty( $row['free'] ) ) {
			$parts[] = 'free=1';
		}

		if ( ! empty( $row['locked_attrs'] ) && is_array( $row['locked_attrs'] ) ) {
			foreach ( $row['locked_attrs'] as $taxonomy => $value ) {
				$taxonomy = sanitize_key( (string) $taxonomy );
				$value    = trim( (string) $value );
				if ( $taxonomy === '' || $value === '' ) {
					continue;
				}
				$parts[] = $taxonomy . '=' . $value;
			}
		}

		return implode( ' | ', $parts );
	}
}

if ( ! function_exists( 'nh_bundle_csv_format_items' ) ) {
	function nh_bundle_csv_format_items( array $rows ) {
		$lines = array();

		foreach ( $rows as $row ) {
			$line = nh_bundle_csv_format_line( $row );
			if ( $line !== '' ) {
				$lines[] = $line;
			}
		}

		return implode( ' || ', $lines );
	}
}

if ( ! function_exists( 'nh_bundle_csv_find_product_id' ) ) {
	function nh_bundle_csv_find_product_id( $sku ) {
		$sku = trim( (string) $sku );
		if ( $sku === '' ) {
			return 0;
		}

		if ( 0 === stripos( $sku, 'id:' ) ) {
			return absint( substr( $sku, 3 ) );
		}

		if ( function_exists( 'wc_get_product_id_by_sku' ) ) {
			return absint( wc_get_product_id_by_sku( $sku ) );
		}

		return 0;
	}
}

if ( ! function_exists( 'nh_bundle_csv_product_ref' ) ) {
	function nh_bundle_csv_product_ref( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return '';
		}

		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$sku = $product->get_sku();
				if ( is_string( $sku ) && $sku !== '' ) {
					return $sku;
				}
			}
		}

		return 'id:' . $product_id;
	}
}

if ( ! function_exists( 'nh_bundle_csv_parsed_to_meta' ) ) {
	/**
	 * @param array $parsed Output of nh_bundle_csv_parse_items().
	 * @return array<int,array>
	 */
	function nh_bundle_csv_parsed_to_meta( array $parsed ) {
		$out = array();

		foreach ( $parsed as $item ) {
			$id = nh_bundle_csv_find_product_id( isset( $item['sku'] ) ? $item['sku'] : '' );
			if ( $id <= 0 ) {
				continue;
			}

			$row = array( 'id' => $id );

			if ( isset( $item['max'] ) && trim( (string) $item['max'] ) !== '' ) {
				$row['max'] = max( 1, (int) $item['max'] );
			}

			$row['free'] = ! empty( $item['free'] ) ? 1 : 0;

			if ( ! empty( $item['locked_attrs'] ) && is_array( $item['locked_attrs'] ) ) {
				$locked = array();
				foreach ( $item['locked_attrs'] as $taxonomy => $value ) {
					$taxonomy = nh_bundle_csv_normalize_attr_key( $taxonomy );
					$value    = nh_bundle_csv_resolve_attr_value( $taxonomy, $value );
					if ( $taxonomy !== '' && $value !== '' ) {
						$locked[ $taxonomy ] = $value;
					}
				}
				if ( ! empty( $locked ) ) {
					$row['locked_attrs'] = $locked;
				}
			}

			$out[] = $row;
		}

		return $out;
	}
}

if ( ! function_exists( 'nh_bundle_csv_stored_to_rows' ) ) {
	/**
	 * @param mixed $raw Stored `_nh_bundle_items_v2` value.
	 * @return array<int,array>
	 */
	function nh_bundle_csv_stored_to_rows( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$rows = array();
		foreach ( $raw as $item ) {
			$id = 0;
			if ( is_numeric( $item ) ) {
				$id = absint( $item );
			} elseif ( is_array( $item ) ) {
				foreach ( array( 'id', 'product_id', 'pid', 'item_id' ) as $key ) {
					if ( ! empty( $item[ $key ] ) ) {
						$id = absint( $item[ $key ] );
						break;
					}
				}
			}

			if ( $id <= 0 ) {
				continue;
			}

			$row = array(
				'sku'          => nh_bundle_csv_product_ref( $id ),
				'max'          => '',
				'free'         => 0,
				'locked_attrs' => array(),
			);

			if ( is_array( $item ) ) {
				if ( isset( $item['max'] ) && $item['max'] !== '' ) {
					$row['max'] = (int) $item['max'];
				}
				$row['free'] = ! empty( $item['free'] ) ? 1 : 0;
				if ( isset( $item['locked_attrs'] ) && is_array( $item['locked_attrs'] ) ) {
					$row['locked_attrs'] = $item['locked_attrs'];
				} elseif ( isset( $item['locked'] ) && is_array( $item['locked'] ) ) {
					$row['locked_attrs'] = $item['locked'];
				}
			}

			$rows[] = $row;
		}

		return $rows;
	}
}

if ( ! function_exists( 'nh_bundle_csv_read_export_rows' ) ) {
	function nh_bundle_csv_read_export_rows( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return array();
		}

		$raw = get_post_meta( $product_id, NH_BUNDLE_META_KEY, true );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			$raw = get_post_meta( $product_id, NH_BUNDLE_META_KEY_COMPAT, true );
		}

		return nh_bundle_csv_stored_to_rows( $raw );
	}
}

if ( ! function_exists( 'nh_bundle_csv_column_names' ) ) {
	function nh_bundle_csv_column_names( $columns ) {
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		$columns['nh_bundle_items']    = 'Bundle items';
		$columns['nh_bundle_box_name'] = 'Bundle box name';

		return $columns;
	}
}

if ( ! function_exists( 'nh_bundle_csv_export_items_column' ) ) {
	function nh_bundle_csv_export_items_column( $value, $product ) {
		unset( $value );

		if ( ! $product instanceof WC_Product || $product->is_type( 'variation' ) ) {
			return '';
		}

		return nh_bundle_csv_format_items( nh_bundle_csv_read_export_rows( $product->get_id() ) );
	}
}

if ( ! function_exists( 'nh_bundle_csv_export_name_column' ) ) {
	function nh_bundle_csv_export_name_column( $value, $product ) {
		unset( $value );

		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$name = $product->get_meta( NH_BUNDLE_NAME_META_KEY, true );
		return is_string( $name ) ? $name : '';
	}
}

if ( ! function_exists( 'nh_bundle_csv_mapping_options' ) ) {
	function nh_bundle_csv_mapping_options( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$options['nh_bundle_items']    = __( 'Bundle items', 'nh-theme' );
		$options['nh_bundle_box_name'] = __( 'Bundle box name', 'nh-theme' );

		return $options;
	}
}

if ( ! function_exists( 'nh_bundle_csv_mapping_defaults' ) ) {
	function nh_bundle_csv_mapping_defaults( $columns ) {
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		$columns[ __( 'Bundle items', 'nh-theme' ) ]    = 'nh_bundle_items';
		$columns['Bundle items']                        = 'nh_bundle_items';
		$columns['Meta: Bundle items']                  = 'nh_bundle_items';
		$columns['Meta: nh_bundle_items']               = 'nh_bundle_items';
		$columns[ __( 'Bundle box name', 'nh-theme' ) ] = 'nh_bundle_box_name';
		$columns['Bundle box name']                     = 'nh_bundle_box_name';
		$columns['Meta: Bundle box name']               = 'nh_bundle_box_name';
		$columns['Meta: _bundle_box_name']              = 'nh_bundle_box_name';
		$columns['Meta: _nc_bundle_items_v2']           = 'nh_bundle_items';
		$columns['Meta: _nh_bundle_items_v2']           = 'nh_bundle_items';

		return $columns;
	}
}

if ( ! function_exists( 'nh_bundle_csv_apply_import' ) ) {
	/**
	 * @param WC_Product $product Product being imported.
	 * @param array      $data    Mapped CSV columns.
	 * @return WC_Product
	 */
	function nh_bundle_csv_apply_import( $product, $data ) {
		if ( ! $product instanceof WC_Product || ! is_array( $data ) ) {
			return $product;
		}

		if ( array_key_exists( 'nh_bundle_box_name', $data ) ) {
			$name = sanitize_text_field( (string) $data['nh_bundle_box_name'] );
			if ( $name === '' ) {
				$product->delete_meta_data( NH_BUNDLE_NAME_META_KEY );
			} else {
				$product->update_meta_data( NH_BUNDLE_NAME_META_KEY, $name );
			}
		}

		if ( array_key_exists( 'nh_bundle_items', $data ) ) {
			if ( $product->is_type( 'variation' ) ) {
				return $product;
			}

			$raw = is_string( $data['nh_bundle_items'] ) ? $data['nh_bundle_items'] : '';
			if ( trim( $raw ) === '' ) {
				$product->delete_meta_data( NH_BUNDLE_META_KEY );
				$product->delete_meta_data( NH_BUNDLE_META_KEY_COMPAT );
				return $product;
			}

			$rows = nh_bundle_csv_parsed_to_meta( nh_bundle_csv_parse_items( $raw ) );
			if ( empty( $rows ) ) {
				$product->delete_meta_data( NH_BUNDLE_META_KEY );
				$product->delete_meta_data( NH_BUNDLE_META_KEY_COMPAT );
				return $product;
			}

			$product->update_meta_data( NH_BUNDLE_META_KEY, $rows );
			$product->update_meta_data( NH_BUNDLE_META_KEY_COMPAT, $rows );

			return $product;
		}

		nh_bundle_csv_hydrate_string_meta( $product );

		return $product;
	}
}

if ( ! function_exists( 'nh_bundle_csv_hydrate_string_meta' ) ) {
	function nh_bundle_csv_hydrate_string_meta( $product ) {
		if ( ! $product instanceof WC_Product || $product->is_type( 'variation' ) ) {
			return;
		}

		foreach ( array( NH_BUNDLE_META_KEY, NH_BUNDLE_META_KEY_COMPAT ) as $key ) {
			$raw = $product->get_meta( $key, true );
			if ( ! is_string( $raw ) || trim( $raw ) === '' ) {
				continue;
			}

			$rows = nh_bundle_csv_parsed_to_meta( nh_bundle_csv_parse_items( $raw ) );
			if ( empty( $rows ) ) {
				continue;
			}

			$product->update_meta_data( NH_BUNDLE_META_KEY, $rows );
			$product->update_meta_data( NH_BUNDLE_META_KEY_COMPAT, $rows );
			return;
		}
	}
}

if ( ! function_exists( 'nh_bundle_csv_export_meta_value' ) ) {
	function nh_bundle_csv_export_meta_value( $value, $meta ) {
		$key = ( is_object( $meta ) && isset( $meta->key ) ) ? $meta->key : '';
		if ( ! in_array( $key, array( NH_BUNDLE_META_KEY, NH_BUNDLE_META_KEY_COMPAT ), true ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			return nh_bundle_csv_format_items( nh_bundle_csv_stored_to_rows( $value ) );
		}

		return $value;
	}
}

if ( ! function_exists( 'nh_product_csv_export_notice' ) ) {
	function nh_product_csv_export_notice() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'product_exporter' !== $page ) {
			return;
		}

		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'Norhage columns are at the end of the Columns list: Bundle items, Bundle box name, and PDF downloads. You can also tick Yes, export all custom meta — those values then appear as Meta: _nc_bundle_items_v2 and Meta: _nrh_downloads.', 'nh-theme' );
		echo '</p></div>';
	}
}

add_filter( 'woocommerce_product_export_column_names', 'nh_bundle_csv_column_names' );
add_filter( 'woocommerce_product_export_product_default_columns', 'nh_bundle_csv_column_names' );
add_filter( 'woocommerce_product_export_product_column_nh_bundle_items', 'nh_bundle_csv_export_items_column', 10, 2 );
add_filter( 'woocommerce_product_export_product_column_nh_bundle_box_name', 'nh_bundle_csv_export_name_column', 10, 2 );
add_filter( 'woocommerce_product_export_meta_value', 'nh_bundle_csv_export_meta_value', 10, 2 );
add_filter( 'woocommerce_csv_product_import_mapping_options', 'nh_bundle_csv_mapping_options' );
add_filter( 'woocommerce_csv_product_import_mapping_default_columns', 'nh_bundle_csv_mapping_defaults' );
add_filter( 'woocommerce_product_import_pre_insert_product_object', 'nh_bundle_csv_apply_import', 10, 2 );
add_action( 'admin_notices', 'nh_product_csv_export_notice' );
