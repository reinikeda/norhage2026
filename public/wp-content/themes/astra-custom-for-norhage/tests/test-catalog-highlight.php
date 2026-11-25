<?php
/**
 * CLI tests: catalog product highlight helpers and sort clauses.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-catalog-highlight.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

$nh_highlight_meta = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key, $single = false ) {
		global $nh_highlight_meta;
		unset( $single );
		if ( ! isset( $nh_highlight_meta[ $post_id ][ $key ] ) ) {
			return '';
		}
		return $nh_highlight_meta[ $post_id ][ $key ];
	}
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = '' ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = '' ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		unset( $domain );
		return $text;
	}
}

require_once dirname( __DIR__ ) . '/inc/catalog-highlight.php';

$failures = 0;

function nh_hl_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

nh_hl_assert( 'meta key is stable', '_nh_catalog_highlight' === nh_catalog_highlight_meta_key() );

global $nh_highlight_meta;
$nh_highlight_meta[ 10 ][ '_nh_catalog_highlight' ] = 'yes';
$nh_highlight_meta[ 11 ][ '_nh_catalog_highlight' ] = 'no';

nh_hl_assert( 'yes meta is highlighted', true === nh_product_is_catalog_highlight( 10 ) );
nh_hl_assert( 'no meta is not highlighted', false === nh_product_is_catalog_highlight( 11 ) );
nh_hl_assert( 'missing meta is not highlighted', false === nh_product_is_catalog_highlight( 12 ) );
nh_hl_assert( 'zero id is not highlighted', false === nh_product_is_catalog_highlight( 0 ) );

$product = new class {
	public function get_id() {
		return 10;
	}
};
nh_hl_assert( 'product object uses get_id', true === nh_product_is_catalog_highlight( $product ) );

$classes = nh_catalog_highlight_post_class( array( 'product' ), $product );
nh_hl_assert( 'post class adds nh-catalog-highlight', in_array( 'nh-catalog-highlight', $classes, true ) );

$plain = new class {
	public function get_id() {
		return 11;
	}
};
$classes_plain = nh_catalog_highlight_post_class( array( 'product' ), $plain );
nh_hl_assert( 'non-highlight keeps classes', array( 'product' ) === $classes_plain );

$wpdb = new class {
	public $posts    = 'wp_posts';
	public $postmeta = 'wp_postmeta';

	public function prepare( $query, ...$args ) {
		foreach ( $args as $arg ) {
			$query = preg_replace( '/%s/', "'" . addslashes( (string) $arg ) . "'", $query, 1 );
		}
		return $query;
	}
};

$clauses = array(
	'join'    => '',
	'orderby' => 'wp_posts.menu_order ASC, wp_posts.post_title ASC',
);
$out = nh_catalog_highlight_append_sort_clauses( $clauses, $wpdb );

nh_hl_assert(
	'sort joins nh_highlight postmeta',
	false !== strpos( $out['join'], 'AS nh_highlight' )
		&& false !== strpos( $out['join'], "_nh_catalog_highlight" )
);
nh_hl_assert(
	'sort puts highlight first when no stock clause',
	0 === strpos( trim( $out['orderby'] ), "CASE WHEN nh_highlight.meta_value = 'yes' THEN 0 ELSE 1 END ASC" )
);

$with_stock = array(
	'join'    => " LEFT JOIN wp_wc_product_meta_lookup AS nh_stock ON (wp_posts.ID = nh_stock.product_id) ",
	'orderby' => " CASE nh_stock.stock_status WHEN 'outofstock' THEN 1 ELSE 0 END ASC, wp_posts.menu_order ASC",
);
$combined = nh_catalog_highlight_append_sort_clauses( $with_stock, $wpdb );
nh_hl_assert(
	'highlight sorts after stock, before menu_order',
	false !== strpos(
		$combined['orderby'],
		"CASE nh_stock.stock_status WHEN 'outofstock' THEN 1 ELSE 0 END ASC, CASE WHEN nh_highlight.meta_value = 'yes' THEN 0 ELSE 1 END ASC, wp_posts.menu_order ASC"
	)
);

$again = nh_catalog_highlight_append_sort_clauses( $combined, $wpdb );
nh_hl_assert(
	'second pass does not duplicate highlight order',
	1 === substr_count( $again['orderby'], 'nh_highlight.meta_value' )
);
nh_hl_assert(
	'second pass does not duplicate highlight join',
	1 === substr_count( $again['join'], 'AS nh_highlight' )
);

$cols = nh_catalog_highlight_column(
	array(
		'cb'   => '',
		'name' => 'Name',
		'sku'  => 'SKU',
		'price'=> 'Price',
	)
);
nh_hl_assert( 'admin column sits after SKU', array_keys( $cols )[3] === 'nh_catalog_highlight' );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll tests passed\n";
exit( $failures ? 1 : 0 );
