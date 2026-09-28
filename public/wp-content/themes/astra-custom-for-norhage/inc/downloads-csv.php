<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product PDF downloads as a WooCommerce CSV column.
 *
 * Hidden meta (_nrh_downloads) is skipped by WooCommerce export.
 * The column is named "PDF downloads" so it does not clash with WooCommerce's
 * own Downloads column (purchasable files).
 *
 * Cell format, files separated with " || ":
 *   Installation manual | https://cdn.example.com/manual.pdf
 *
 * Newlines and the older "Label; https://..." form still import.
 * If Excel flattens line breaks, each https:// URL becomes its own file.
 */

if ( ! defined( 'NRH_DOWNLOADS_META_KEY' ) ) {
	define( 'NRH_DOWNLOADS_META_KEY', '_nrh_downloads' );
}

if ( ! function_exists( 'nrh_downloads_csv_extract_url' ) ) {
	function nrh_downloads_csv_extract_url( $text ) {
		$text = trim( (string) $text );
		if ( $text === '' ) {
			return '';
		}

		if ( preg_match( '#https?://[^\s]+#i', $text, $match ) ) {
			return rtrim( $match[0], '.,);' );
		}

		return '';
	}
}

if ( ! function_exists( 'nrh_downloads_csv_sanitize_row' ) ) {
	function nrh_downloads_csv_sanitize_row( $label, $url ) {
		$label = sanitize_text_field( $label );
		$url   = function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url;

		if ( $label === '' || $url === '' ) {
			return null;
		}

		return array(
			'label' => $label,
			'url'   => $url,
		);
	}
}

if ( ! function_exists( 'nrh_downloads_csv_parse_line' ) ) {
	/**
	 * @return array{label:string,url:string}|null
	 */
	function nrh_downloads_csv_parse_line( $line ) {
		$rows = nrh_downloads_csv_parse_chunk( $line );
		return empty( $rows ) ? null : $rows[0];
	}
}

if ( ! function_exists( 'nrh_downloads_csv_parse_chunk' ) ) {
	/**
	 * @return array<int,array{label:string,url:string}>
	 */
	function nrh_downloads_csv_parse_chunk( $line ) {
		$line = trim( (string) $line );
		if ( $line === '' || 0 === strpos( $line, '#' ) ) {
			return array();
		}

		if ( false !== stripos( $line, 'url=' ) || false !== stripos( $line, 'label=' ) ) {
			$label = '';
			$url   = '';
			$parts = array_map( 'trim', explode( ';', str_replace( ' | ', '; ', $line ) ) );
			foreach ( $parts as $part ) {
				if ( false === strpos( $part, '=' ) ) {
					continue;
				}
				$bits  = explode( '=', $part, 2 );
				$key   = strtolower( trim( $bits[0] ) );
				$value = isset( $bits[1] ) ? trim( $bits[1] ) : '';
				if ( $key === 'label' ) {
					$label = $value;
				} elseif ( $key === 'url' ) {
					$url = $value;
				}
			}
			$row = nrh_downloads_csv_sanitize_row( $label, $url );
			return $row ? array( $row ) : array();
		}

		if ( ! preg_match_all( '#https?://[^\s]+#i', $line, $matches, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		$found = $matches[0];
		$rows  = array();
		$count = count( $found );

		for ( $i = 0; $i < $count; $i++ ) {
			$url        = rtrim( $found[ $i ][0], '.,);' );
			$url_start  = $found[ $i ][1];
			$label_from = ( 0 === $i ) ? 0 : ( $found[ $i - 1 ][1] + strlen( $found[ $i - 1 ][0] ) );
			$label      = trim( substr( $line, $label_from, $url_start - $label_from ) );
			$label      = trim( $label, " \t;|," );
			$row        = nrh_downloads_csv_sanitize_row( $label, $url );
			if ( $row ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}
}

if ( ! function_exists( 'nrh_downloads_csv_parse_items' ) ) {
	/**
	 * @param string $text CSV cell.
	 * @return array<int,array{label:string,url:string}>
	 */
	function nrh_downloads_csv_parse_items( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$text = str_replace( array( ' || ', '||' ), "\n", $text );
		$out  = array();

		foreach ( preg_split( "/\n+/", $text ) as $line ) {
			foreach ( nrh_downloads_csv_parse_chunk( $line ) as $row ) {
				$out[] = $row;
			}
		}

		return $out;
	}
}

if ( ! function_exists( 'nrh_downloads_csv_format_items' ) ) {
	function nrh_downloads_csv_format_items( $rows ) {
		if ( ! is_array( $rows ) ) {
			return '';
		}

		$lines = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
			$url   = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';
			if ( $label === '' || $url === '' ) {
				continue;
			}
			$lines[] = $label . ' | ' . $url;
		}

		return implode( ' || ', $lines );
	}
}

if ( ! function_exists( 'nrh_downloads_csv_column_names' ) ) {
	function nrh_downloads_csv_column_names( $columns ) {
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		$columns['nrh_downloads'] = 'PDF downloads';

		return $columns;
	}
}

if ( ! function_exists( 'nrh_downloads_csv_export_column' ) ) {
	function nrh_downloads_csv_export_column( $value, $product ) {
		unset( $value );

		if ( ! $product instanceof WC_Product || $product->is_type( 'variation' ) ) {
			return '';
		}

		$rows = $product->get_meta( NRH_DOWNLOADS_META_KEY, true );
		if ( ! is_array( $rows ) ) {
			$rows = get_post_meta( $product->get_id(), NRH_DOWNLOADS_META_KEY, true );
		}

		return nrh_downloads_csv_format_items( $rows );
	}
}

if ( ! function_exists( 'nrh_downloads_csv_mapping_options' ) ) {
	function nrh_downloads_csv_mapping_options( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$options['nrh_downloads'] = __( 'PDF downloads', 'nh-theme' );

		return $options;
	}
}

if ( ! function_exists( 'nrh_downloads_csv_mapping_defaults' ) ) {
	function nrh_downloads_csv_mapping_defaults( $columns ) {
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		$columns[ __( 'PDF downloads', 'nh-theme' ) ] = 'nrh_downloads';
		$columns['PDF downloads']                     = 'nrh_downloads';
		$columns['Download links']                    = 'nrh_downloads';
		$columns['Meta: PDF downloads']               = 'nrh_downloads';
		$columns['Meta: _nrh_downloads']              = 'nrh_downloads';

		return $columns;
	}
}

if ( ! function_exists( 'nrh_downloads_csv_apply_import' ) ) {
	/**
	 * @param WC_Product $product Product being imported.
	 * @param array      $data    Mapped CSV columns.
	 * @return WC_Product
	 */
	function nrh_downloads_csv_apply_import( $product, $data ) {
		if ( ! $product instanceof WC_Product || ! is_array( $data ) ) {
			return $product;
		}

		if ( array_key_exists( 'nrh_downloads', $data ) ) {
			if ( $product->is_type( 'variation' ) ) {
				return $product;
			}

			$raw = is_string( $data['nrh_downloads'] ) ? $data['nrh_downloads'] : '';
			if ( trim( $raw ) === '' ) {
				$product->delete_meta_data( NRH_DOWNLOADS_META_KEY );
				return $product;
			}

			$rows = nrh_downloads_csv_parse_items( $raw );
			if ( empty( $rows ) ) {
				$product->delete_meta_data( NRH_DOWNLOADS_META_KEY );
				return $product;
			}

			$product->update_meta_data( NRH_DOWNLOADS_META_KEY, $rows );

			return $product;
		}

		nrh_downloads_csv_hydrate_string_meta( $product );

		return $product;
	}
}

if ( ! function_exists( 'nrh_downloads_csv_hydrate_string_meta' ) ) {
	function nrh_downloads_csv_hydrate_string_meta( $product ) {
		if ( ! $product instanceof WC_Product || $product->is_type( 'variation' ) ) {
			return;
		}

		$raw = $product->get_meta( NRH_DOWNLOADS_META_KEY, true );
		if ( ! is_string( $raw ) || trim( $raw ) === '' ) {
			return;
		}

		$rows = nrh_downloads_csv_parse_items( $raw );
		if ( empty( $rows ) ) {
			return;
		}

		$product->update_meta_data( NRH_DOWNLOADS_META_KEY, $rows );
	}
}

if ( ! function_exists( 'nrh_downloads_csv_export_meta_value' ) ) {
	function nrh_downloads_csv_export_meta_value( $value, $meta ) {
		$key = ( is_object( $meta ) && isset( $meta->key ) ) ? $meta->key : '';
		if ( $key !== NRH_DOWNLOADS_META_KEY ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			return nrh_downloads_csv_format_items( $value );
		}

		return $value;
	}
}

add_filter( 'woocommerce_product_export_column_names', 'nrh_downloads_csv_column_names' );
add_filter( 'woocommerce_product_export_product_default_columns', 'nrh_downloads_csv_column_names' );
add_filter( 'woocommerce_product_export_product_column_nrh_downloads', 'nrh_downloads_csv_export_column', 10, 2 );
add_filter( 'woocommerce_product_export_meta_value', 'nrh_downloads_csv_export_meta_value', 10, 2 );
add_filter( 'woocommerce_csv_product_import_mapping_options', 'nrh_downloads_csv_mapping_options' );
add_filter( 'woocommerce_csv_product_import_mapping_default_columns', 'nrh_downloads_csv_mapping_defaults' );
add_filter( 'woocommerce_product_import_pre_insert_product_object', 'nrh_downloads_csv_apply_import', 10, 2 );
