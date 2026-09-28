<?php
/**
 * CLI tests: PDF downloads CSV export/import helpers.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-downloads-csv.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

$nh_filters = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		global $nh_filters;
		$nh_filters[] = array( $hook, $callback, (int) $priority, (int) $accepted_args );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		add_filter( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		$url = trim( (string) $url );
		if ( 0 !== stripos( $url, 'http://' ) && 0 !== stripos( $url, 'https://' ) ) {
			return '';
		}
		return $url;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key, $single = false ) {
		unset( $post_id, $key, $single );
		return '';
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		public $id   = 0;
		public $type = 'simple';
		public $meta = array();

		public function get_id() {
			return $this->id;
		}

		public function is_type( $type ) {
			return $this->type === $type;
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

require_once dirname( __DIR__ ) . '/inc/downloads-csv.php';

$failures = 0;

function nrh_dl_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$parsed = nrh_downloads_csv_parse_items(
	"Installation manual; https://cdn.example.com/manual.pdf\nDatasheet; https://cdn.example.com/data.pdf"
);
nrh_dl_assert( 'two files parse from two lines', 2 === count( $parsed ) );
nrh_dl_assert( 'first label is kept', 'Installation manual' === $parsed[0]['label'] );
nrh_dl_assert( 'first URL is kept', 'https://cdn.example.com/manual.pdf' === $parsed[0]['url'] );
nrh_dl_assert( 'second label is kept', 'Datasheet' === $parsed[1]['label'] );

$pipe = nrh_downloads_csv_parse_items(
	'Manual; https://cdn.example.com/a.pdf | Guide; https://cdn.example.com/b.pdf'
);
nrh_dl_assert( 'pipe also splits files', 2 === count( $pipe ) );

$keys = nrh_downloads_csv_parse_items( 'label=Montageanleitung; url=https://cdn.example.com/de.pdf' );
nrh_dl_assert( 'label= and url= keys are accepted', 'Montageanleitung' === $keys[0]['label'] );
nrh_dl_assert( 'keyed URL is kept', 'https://cdn.example.com/de.pdf' === $keys[0]['url'] );

$semi = nrh_downloads_csv_parse_items( 'Manual; German; https://cdn.example.com/de.pdf' );
nrh_dl_assert( 'a label may contain a semicolon', 'Manual; German' === $semi[0]['label'] );

$skip = nrh_downloads_csv_parse_items( "Broken row without url\nOK; https://cdn.example.com/ok.pdf" );
nrh_dl_assert( 'rows without a URL are skipped', 1 === count( $skip ) && 'OK' === $skip[0]['label'] );

$formatted = nrh_downloads_csv_format_items(
	array(
		array( 'label' => 'Installation manual', 'url' => 'https://cdn.example.com/manual.pdf' ),
		array( 'label' => 'Datasheet', 'url' => 'https://cdn.example.com/data.pdf' ),
	)
);
nrh_dl_assert(
	'round-trip text keeps label and URL',
	"Installation manual; https://cdn.example.com/manual.pdf\nDatasheet; https://cdn.example.com/data.pdf" === $formatted
);
nrh_dl_assert( 'formatted text parses back', 2 === count( nrh_downloads_csv_parse_items( $formatted ) ) );

$product = new WC_Product();
$product->id   = 50;
$product->type = 'simple';
$product->meta['_nrh_downloads'] = array(
	array( 'label' => 'Manual', 'url' => 'https://cdn.example.com/manual.pdf' ),
);
nrh_dl_assert(
	'export column prints the saved files',
	'Manual; https://cdn.example.com/manual.pdf' === nrh_downloads_csv_export_column( '', $product )
);

$imported = nrh_downloads_csv_apply_import(
	$product,
	array(
		'nrh_downloads' => "Guide; https://cdn.example.com/guide.pdf\nSpec; https://cdn.example.com/spec.pdf",
	)
);
nrh_dl_assert( 'import writes two files', 2 === count( $imported->meta['_nrh_downloads'] ) );
nrh_dl_assert( 'imported label is Guide', 'Guide' === $imported->meta['_nrh_downloads'][0]['label'] );

$cleared = nrh_downloads_csv_apply_import( $imported, array( 'nrh_downloads' => '' ) );
nrh_dl_assert( 'empty PDF downloads clears the list', ! isset( $cleared->meta['_nrh_downloads'] ) );

$variation = new WC_Product();
$variation->type = 'variation';
$variation->meta['_nrh_downloads'] = array( array( 'label' => 'Keep', 'url' => 'https://cdn.example.com/keep.pdf' ) );
$variation = nrh_downloads_csv_apply_import(
	$variation,
	array( 'nrh_downloads' => 'New; https://cdn.example.com/new.pdf' )
);
nrh_dl_assert( 'variation rows do not replace parent downloads', 'Keep' === $variation->meta['_nrh_downloads'][0]['label'] );

$untouched = new WC_Product();
$untouched->meta['_nrh_downloads'] = array( array( 'label' => 'Keep', 'url' => 'https://cdn.example.com/keep.pdf' ) );
$untouched = nrh_downloads_csv_apply_import( $untouched, array( 'sku' => 'PARENT' ) );
nrh_dl_assert( 'CSV without PDF downloads leaves files alone', 'Keep' === $untouched->meta['_nrh_downloads'][0]['label'] );

$dl_meta = nrh_downloads_csv_export_meta_value(
	array( array( 'label' => 'Manual', 'url' => 'https://cdn.example.com/manual.pdf' ) ),
	(object) array( 'key' => '_nrh_downloads' )
);
nrh_dl_assert( 'custom meta export turns downloads into text', 'Manual; https://cdn.example.com/manual.pdf' === $dl_meta );

$from_meta = new WC_Product();
$from_meta->type = 'simple';
$from_meta->meta['_nrh_downloads'] = 'Guide; https://cdn.example.com/guide.pdf';
$from_meta = nrh_downloads_csv_apply_import( $from_meta, array( 'sku' => 'PARENT' ) );
nrh_dl_assert( 'Meta: downloads text is turned back into files', 'Guide' === $from_meta->meta['_nrh_downloads'][0]['label'] );

$names = nrh_downloads_csv_column_names( array() );
nrh_dl_assert( 'export header stays PDF downloads', 'PDF downloads' === $names['nrh_downloads'] );

$source    = file_get_contents( dirname( __DIR__ ) . '/inc/downloads-csv.php' );
$functions = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
nrh_dl_assert( 'theme loads the downloads CSV helpers', false !== strpos( $functions, 'downloads-csv.php' ) );
nrh_dl_assert( 'column id is not WooCommerce Downloads', false !== strpos( $source, "'nrh_downloads'" ) );
nrh_dl_assert( 'column label is PDF downloads', false !== strpos( $source, 'PDF downloads' ) );

global $nh_filters;
$hooks = array();
foreach ( $nh_filters as $row ) {
	$hooks[] = $row[0];
}
nrh_dl_assert( 'export column names are hooked', in_array( 'woocommerce_product_export_column_names', $hooks, true ) );
nrh_dl_assert( 'custom meta export is hooked', in_array( 'woocommerce_product_export_meta_value', $hooks, true ) );
nrh_dl_assert( 'import mapping options are hooked', in_array( 'woocommerce_csv_product_import_mapping_options', $hooks, true ) );

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
