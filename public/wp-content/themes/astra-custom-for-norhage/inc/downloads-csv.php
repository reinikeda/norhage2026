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
 * Cell format, one file per line:
 *   Installation manual; https://cdn.example.com/manual.pdf
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
		$line = trim( (string) $line );
		if ( $line === '' || 0 === strpos( $line, '#' ) ) {
			return null;
		}

		$label = '';
		$url   = '';

		if ( false !== stripos( $line, 'url=' ) || false !== stripos( $line, 'label=' ) ) {
			$parts = array_map( 'trim', explode( ';', $line ) );
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
		}

		if ( $url === '' ) {
			$url = nrh_downloads_csv_extract_url( $line );
			if ( $url !== '' && $label === '' ) {
				$label = trim( str_ireplace( $url, '', $line ) );
				$label = trim( $label, " \t;|," );
			}
		}

		return nrh_downloads_csv_sanitize_row( $label, $url );
	}
}

if ( ! function_exists( 'nrh_downloads_csv_parse_items' ) ) {
	/**
	 * @param string $text CSV cell.
	 * @return array<int,array{label:string,url:string}>
	 */
	function nrh_downloads_csv_parse_items( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$text = str_replace( ' | ', "\n", $text );
		$out  = array();

		foreach ( preg_split( "/\n+/", $text ) as $line ) {
			$row = nrh_downloads_csv_parse_line( $line );
			if ( null !== $row ) {
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
			$lines[] = $label . '; ' . $url;
		}

		return implode( "\n", $lines );
	}
}

if ( ! function_exists( 'nrh_downloads_csv_column_names' ) ) {
	function nrh_downloads_csv_column_names( $columns ) {
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		$columns['nrh_downloads'] = __( 'PDF downloads', 'nh-theme' );

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

		if ( ! array_key_exists( 'nrh_downloads', $data ) ) {
			return $product;
		}

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
}

add_filter( 'woocommerce_product_export_column_names', 'nrh_downloads_csv_column_names' );
add_filter( 'woocommerce_product_export_product_default_columns', 'nrh_downloads_csv_column_names' );
add_filter( 'woocommerce_product_export_product_column_nrh_downloads', 'nrh_downloads_csv_export_column', 10, 2 );
add_filter( 'woocommerce_csv_product_import_mapping_options', 'nrh_downloads_csv_mapping_options' );
add_filter( 'woocommerce_csv_product_import_mapping_default_columns', 'nrh_downloads_csv_mapping_defaults' );
add_filter( 'woocommerce_product_import_pre_insert_product_object', 'nrh_downloads_csv_apply_import', 10, 2 );
