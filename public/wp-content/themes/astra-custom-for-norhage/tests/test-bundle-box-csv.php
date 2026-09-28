<?php
/**
 * CLI tests: bundle box CSV export/import helpers.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-bundle-box-csv.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

$nh_sku_map   = array(
	'SEAL-10' => 101,
	'PROFILE' => 202,
);
$nh_term_map  = array(
	'pa_width|slug|10-mm'   => '10-mm',
	'pa_width|name|10 mm'   => '10-mm',
	'pa_width|slug|1-05-m'  => '1-05-m',
	'pa_width|name|1,05 m'  => '1-05-m',
	'pa_color|slug|klar'    => 'klar',
	'pa_color|name|Klar'    => 'klar',
);
$nh_meta      = array();
$nh_filters   = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		global $nh_filters;
		$nh_filters[] = array( $hook, $callback, (int) $priority, (int) $accepted_args );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wc_clean' ) ) {
	function wc_clean( $var ) {
		if ( is_array( $var ) ) {
			return array_map( 'wc_clean', $var );
		}
		return trim( strip_tags( (string) $var ) );
	}
}

if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( $taxonomy ) {
		return in_array( $taxonomy, array( 'pa_width', 'pa_color' ), true );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'get_term_by' ) ) {
	function get_term_by( $field, $value, $taxonomy ) {
		global $nh_term_map;
		$key = $taxonomy . '|' . $field . '|' . $value;
		if ( ! isset( $nh_term_map[ $key ] ) ) {
			return false;
		}
		return (object) array( 'slug' => $nh_term_map[ $key ] );
	}
}

if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
	function wc_get_product_id_by_sku( $sku ) {
		global $nh_sku_map;
		return isset( $nh_sku_map[ $sku ] ) ? $nh_sku_map[ $sku ] : 0;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key, $single = false ) {
		global $nh_meta;
		unset( $single );
		if ( isset( $nh_meta[ $post_id ][ $key ] ) ) {
			return $nh_meta[ $post_id ][ $key ];
		}
		return '';
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		public $id = 0;
		public $type = 'simple';
		public $sku = '';
		public $meta = array();

		public function get_id() {
			return $this->id;
		}

		public function is_type( $type ) {
			return $this->type === $type;
		}

		public function get_sku() {
			return $this->sku;
		}

		public function get_meta( $key, $single = true ) {
			unset( $single );
			return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : '';
		}

		public function update_meta_data( $key, $value ) {
			$this->meta[ $key ] = $value;
		}

		public function delete_meta_data( $key ) {
			unset( $this->meta[ $key ] );
		}
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $product_id ) {
		$product_id = (int) $product_id;
		if ( 101 === $product_id ) {
			$p       = new WC_Product();
			$p->id   = 101;
			$p->sku  = 'SEAL-10';
			return $p;
		}
		if ( 202 === $product_id ) {
			$p       = new WC_Product();
			$p->id   = 202;
			$p->sku  = 'PROFILE';
			return $p;
		}
		if ( 303 === $product_id ) {
			$p      = new WC_Product();
			$p->id  = 303;
			$p->sku = '';
			return $p;
		}
		return false;
	}
}

require_once dirname( __DIR__ ) . '/inc/bundle-box-csv.php';

$failures = 0;

function nh_csv_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$parsed = nh_bundle_csv_parse_items( "SEAL-10; max=2; pa_width=10-mm\nPROFILE; free=1; pa_color=klar" );
nh_csv_assert( 'two add-ons parse from two lines', 2 === count( $parsed ) );
nh_csv_assert( 'first SKU is the add-on SKU', 'SEAL-10' === $parsed[0]['sku'] );
nh_csv_assert( 'max quantity is kept', '2' === $parsed[0]['max'] );
nh_csv_assert( 'fixed width uses pa_width', isset( $parsed[0]['locked_attrs']['pa_width'] ) && '10-mm' === $parsed[0]['locked_attrs']['pa_width'] );
nh_csv_assert( 'second add-on is free', 1 === $parsed[1]['free'] );

$pipe = nh_bundle_csv_parse_items( 'SEAL-10; max=2; width=10-mm | PROFILE; free' );
nh_csv_assert( 'pipe also splits add-ons', 2 === count( $pipe ) );
nh_csv_assert( 'width without pa_ prefix is normalised', '10-mm' === $pipe[0]['locked_attrs']['pa_width'] );
nh_csv_assert( 'bare free flag is true', 1 === $pipe[1]['free'] );

$custom_attr = nh_bundle_csv_parse_items( 'SEAL-10; finish=matt' );
nh_csv_assert( 'custom attributes keep their own key', isset( $custom_attr[0]['locked_attrs']['finish'] ) && 'matt' === $custom_attr[0]['locked_attrs']['finish'] );

$sku_key = nh_bundle_csv_parse_items( 'sku=SEAL-10; id=101; max=1' );
nh_csv_assert( 'sku= prefix is accepted', 'SEAL-10' === $sku_key[0]['sku'] );

$meta = nh_bundle_csv_parsed_to_meta( $parsed );
nh_csv_assert( 'SKU SEAL-10 becomes product 101', 101 === $meta[0]['id'] );
nh_csv_assert( 'SKU PROFILE becomes product 202', 202 === $meta[1]['id'] );
nh_csv_assert( 'imported max is an integer', 2 === $meta[0]['max'] );
nh_csv_assert( 'imported free stays 1', 1 === $meta[1]['free'] );

$named = nh_bundle_csv_parsed_to_meta(
	nh_bundle_csv_parse_items( 'SEAL-10; pa_width=10 mm' )
);
nh_csv_assert( 'visible term name resolves to the slug', '10-mm' === $named[0]['locked_attrs']['pa_width'] );

$comma = nh_bundle_csv_parsed_to_meta(
	nh_bundle_csv_parse_items( 'SEAL-10; pa_width=1,05 m' )
);
nh_csv_assert( 'comma decimal names keep the real slug', '1-05-m' === $comma[0]['locked_attrs']['pa_width'] );

$missing = nh_bundle_csv_parsed_to_meta(
	nh_bundle_csv_parse_items( "MISSING; pa_width=10-mm\nSEAL-10; pa_width=10-mm" )
);
nh_csv_assert( 'unknown SKUs are skipped', 1 === count( $missing ) && 101 === $missing[0]['id'] );

$id_ref = nh_bundle_csv_parsed_to_meta( nh_bundle_csv_parse_items( 'id:303; max=4' ) );
nh_csv_assert( 'id: fallback finds the product', 303 === $id_ref[0]['id'] && 4 === $id_ref[0]['max'] );

$formatted = nh_bundle_csv_format_items(
	array(
		array(
			'sku'          => 'SEAL-10',
			'max'          => 2,
			'free'         => 0,
			'locked_attrs' => array( 'pa_width' => '10-mm' ),
		),
		array(
			'sku'          => 'PROFILE',
			'max'          => '',
			'free'         => 1,
			'locked_attrs' => array( 'pa_color' => 'klar' ),
		),
	)
);
nh_csv_assert(
	'round-trip text keeps SKU, max, free and attributes',
	"SEAL-10; max=2; pa_width=10-mm\nPROFILE; free=1; pa_color=klar" === $formatted
);

$round = nh_bundle_csv_parse_items( $formatted );
nh_csv_assert( 'formatted text parses back to two rows', 2 === count( $round ) );

global $nh_meta;
$nh_meta[ 50 ][ '_nh_bundle_items_v2' ] = array(
	array(
		'id'           => 101,
		'max'          => 2,
		'free'         => 0,
		'locked_attrs' => array( 'pa_width' => '10-mm' ),
	),
);
$exported = nh_bundle_csv_read_export_rows( 50 );
nh_csv_assert( 'export rows use the add-on SKU', 'SEAL-10' === $exported[0]['sku'] );
nh_csv_assert( 'export rows keep locked attributes', '10-mm' === $exported[0]['locked_attrs']['pa_width'] );

$parent = new WC_Product();
$parent->id   = 50;
$parent->type = 'simple';
$parent       = nh_bundle_csv_apply_import(
	$parent,
	array(
		'nh_bundle_items'    => "SEAL-10; max=2; pa_width=10-mm\nPROFILE; free=1",
		'nh_bundle_box_name' => 'Sealing tape 10 mm',
	)
);
nh_csv_assert( 'import writes bundle meta on the parent', isset( $parent->meta['_nh_bundle_items_v2'] ) );
nh_csv_assert( 'import writes the compat bundle meta key', isset( $parent->meta['_nc_bundle_items_v2'] ) );
nh_csv_assert( 'import writes two add-ons', 2 === count( $parent->meta['_nh_bundle_items_v2'] ) );
nh_csv_assert( 'import writes the bundle display name', 'Sealing tape 10 mm' === $parent->meta['_bundle_box_name'] );

$cleared = nh_bundle_csv_apply_import(
	$parent,
	array(
		'nh_bundle_items'    => '',
		'nh_bundle_box_name' => '',
	)
);
nh_csv_assert( 'empty Bundle items clears the list', ! isset( $cleared->meta['_nh_bundle_items_v2'] ) );
nh_csv_assert( 'empty Bundle box name clears the name', ! isset( $cleared->meta['_bundle_box_name'] ) );

$variation = new WC_Product();
$variation->type = 'variation';
$variation->meta['_nh_bundle_items_v2'] = array( array( 'id' => 101 ) );
$variation = nh_bundle_csv_apply_import(
	$variation,
	array( 'nh_bundle_items' => 'SEAL-10; pa_width=10-mm' )
);
nh_csv_assert( 'variation rows do not replace parent extras', 1 === count( $variation->meta['_nh_bundle_items_v2'] ) );

$untouched = new WC_Product();
$untouched->meta['_nh_bundle_items_v2'] = array( array( 'id' => 101 ) );
$untouched = nh_bundle_csv_apply_import( $untouched, array( 'sku' => 'PARENT' ) );
nh_csv_assert( 'CSV without Bundle items leaves extras alone', 1 === count( $untouched->meta['_nh_bundle_items_v2'] ) );

$source = file_get_contents( dirname( __DIR__ ) . '/inc/bundle-box-csv.php' );
$functions = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
nh_csv_assert( 'theme loads the bundle CSV helpers', false !== strpos( $functions, 'bundle-box-csv.php' ) );
nh_csv_assert(
	'WooCommerce export registers Bundle items',
	false !== strpos( $source, 'woocommerce_product_export_product_column_nh_bundle_items' )
);
nh_csv_assert(
	'WooCommerce import writes the bundle meta',
	false !== strpos( $source, 'woocommerce_product_import_pre_insert_product_object' )
);

global $nh_filters;
$hooks = array();
foreach ( $nh_filters as $row ) {
	$hooks[] = $row[0];
}
nh_csv_assert( 'export column names are hooked', in_array( 'woocommerce_product_export_column_names', $hooks, true ) );
nh_csv_assert( 'import mapping options are hooked', in_array( 'woocommerce_csv_product_import_mapping_options', $hooks, true ) );

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
