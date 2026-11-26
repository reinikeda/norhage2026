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
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return is_string( $str ) ? trim( $str ) : '';
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return is_string( $string ) ? strip_tags( $string ) : '';
	}
}
if ( ! function_exists( 'wc_attribute_label' ) ) {
	function wc_attribute_label( $name, $product = null ) {
		unset( $product );
		$map = array(
			'pa_thickness' => 'Thickness',
			'pa_width'     => 'Width',
		);
		return isset( $map[ $name ] ) ? $map[ $name ] : $name;
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
nh_hl_assert( 'attrs meta key is stable', '_nh_catalog_highlight_attrs' === nh_catalog_highlight_attrs_meta_key() );

global $nh_highlight_meta;
$nh_highlight_meta[ 10 ][ '_nh_catalog_highlight' ] = 'yes';
$nh_highlight_meta[ 11 ][ '_nh_catalog_highlight' ] = 'no';
$nh_highlight_meta[ 10 ][ '_nh_catalog_highlight_attrs' ] = array( 'pa_thickness', 'pa_width', '', 'pa_thickness' );

nh_hl_assert( 'yes meta is highlighted', true === nh_product_is_catalog_highlight( 10 ) );
nh_hl_assert( 'no meta is not highlighted', false === nh_product_is_catalog_highlight( 11 ) );
nh_hl_assert( 'missing meta is not highlighted', false === nh_product_is_catalog_highlight( 12 ) );
nh_hl_assert( 'zero id is not highlighted', false === nh_product_is_catalog_highlight( 0 ) );

$selected = nh_catalog_highlight_selected_attr_names( 10 );
nh_hl_assert( 'selected attrs drop empties and duplicates', array( 'pa_thickness', 'pa_width' ) === $selected );
nh_hl_assert( 'missing attrs meta is empty list', array() === nh_catalog_highlight_selected_attr_names( 12 ) );

$product = new class {
	public function get_id() {
		return 10;
	}
	public function get_attribute( $name ) {
		$map = array(
			'pa_thickness' => '8 mm',
			'pa_width'     => '980 mm',
		);
		return isset( $map[ $name ] ) ? $map[ $name ] : '';
	}
};
nh_hl_assert( 'product object uses get_id', true === nh_product_is_catalog_highlight( $product ) );

$rows = nh_catalog_highlight_attr_rows( $product );
nh_hl_assert( 'attr rows include thickness', isset( $rows[0] ) && 'Thickness' === $rows[0]['label'] && '8 mm' === $rows[0]['value'] );
nh_hl_assert( 'attr rows include width', isset( $rows[1] ) && 'Width' === $rows[1]['label'] && '980 mm' === $rows[1]['value'] );

$plain = new class {
	public function get_id() {
		return 11;
	}
	public function get_attribute( $name ) {
		unset( $name );
		return '8 mm';
	}
};
nh_hl_assert( 'non-highlight product has no attr rows', array() === nh_catalog_highlight_attr_rows( $plain ) );

$classes = nh_catalog_highlight_post_class( array( 'product' ), $product );
nh_hl_assert( 'post class adds nh-catalog-highlight', in_array( 'nh-catalog-highlight', $classes, true ) );

$classes_plain = nh_catalog_highlight_post_class( array( 'product' ), $plain );
nh_hl_assert( 'non-highlight keeps classes', array( 'product' ) === $classes_plain );

$from_post = nh_catalog_highlight_attrs_from_request(
	array(
		'attribute_names'           => array( 'pa_thickness', 'Colour', 'pa_width' ),
		'nh_catalog_highlight_attr' => array( 0 => '1', 2 => '1' ),
	)
);
nh_hl_assert(
	'request maps ticks to attribute names by index',
	array( 'pa_thickness', 'pa_width' ) === $from_post
);

$quick_edit = nh_catalog_highlight_attrs_from_request( array( 'post_ID' => 10 ) );
nh_hl_assert( 'quick edit request leaves attrs untouched', null === $quick_edit );

$cleared = nh_catalog_highlight_attrs_from_request(
	array(
		'attribute_names' => array( 'pa_thickness', 'pa_width' ),
	)
);
nh_hl_assert( 'unticked attributes clear the list', array() === $cleared );

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
		'cb'    => '',
		'name'  => 'Name',
		'sku'   => 'SKU',
		'price' => 'Price',
	)
);
nh_hl_assert( 'admin column sits after SKU', array_keys( $cols )[3] === 'nh_catalog_highlight' );

$admin_js = file_get_contents( dirname( __DIR__ ) . '/assets/js/catalog-highlight-admin.js' );
nh_hl_assert( 'quick edit admin script exists', false !== strpos( $admin_js, 'nh-catalog-highlight-quick-edit' ) );
nh_hl_assert( 'quick edit hooks inlineEditPost.edit', false !== strpos( $admin_js, 'inlineEditPost.edit' ) );

$source = file_get_contents( dirname( __DIR__ ) . '/inc/catalog-highlight.php' );
nh_hl_assert(
	'attrs render on Astra title_after (summary wrap), not Woo after_shop_loop_item_title',
	false !== strpos( $source, "add_action( 'astra_woo_shop_title_after'" )
		&& false === strpos( $source, "add_action( 'woocommerce_after_shop_loop_item_title'" )
);
nh_hl_assert(
	'quick edit save persists with update_post_meta',
	false !== strpos( $source, 'function nh_catalog_highlight_quick_edit_save' )
		&& false !== strpos( $source, 'update_post_meta' )
);

$css = file_get_contents( dirname( __DIR__ ) . '/style.css' );
nh_hl_assert(
	'catalog main title is ordered before secondary title',
	preg_match( '/\.ast-loop-product__link\s*\{\s*order:\s*1/s', $css )
		&& preg_match( '/\.product-secondary-title\s*\{\s*order:\s*2/s', $css )
);
nh_hl_assert(
	'highlight card has no outer padding (image flush)',
	preg_match(
		'/\.woocommerce ul\.products li\.product\.nh-catalog-highlight\s*\{[^}]*padding:\s*0/s',
		$css
	)
);
nh_hl_assert(
	'highlight summary has no side padding (bars stay flush)',
	preg_match(
		'/\.nh-catalog-highlight \.astra-shop-summary-wrap\s*\{[^}]*padding:\s*0\s+0\s+/s',
		$css
	)
);
nh_hl_assert(
	'highlight copy uses tight child padding inset',
	false !== strpos( $css, '.astra-shop-summary-wrap > :not(.nhg-labels--below)' )
		&& false !== strpos( $css, '--nh-highlight-inset: 0.35rem' )
		&& false !== strpos( $css, 'padding-left: var(--nh-highlight-inset)' )
);
nh_hl_assert(
	'highlight under-image bars are full width',
	false !== strpos( $css, '.astra-shop-summary-wrap > .nhg-labels--below' )
		&& false !== strpos( $css, 'width: 100% !important' )
);
nh_hl_assert(
	'highlight title wraps inside the card',
	false !== strpos( $css, 'overflow-wrap: anywhere' )
		&& false !== strpos( $css, 'white-space: normal' )
);
nh_hl_assert(
	'catalog product images are display:block to kill img descender gap',
	false !== strpos( $css, '.astra-shop-thumbnail-wrap img' )
		&& false !== strpos( $css, 'display: block !important' )
);
nh_hl_assert(
	'catalog star ratings reset left margin',
	false !== strpos( $css, 'li.product .star-rating' )
		|| false !== strpos( $css, 'li.product .review-rating' )
);
nh_hl_assert(
	'catalog star rating uses widened 5.8em strip',
	false !== strpos( $css, 'width: 5.8em !important' )
);

// Simulate Quick Edit save request.
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		global $nh_highlight_meta;
		$nh_highlight_meta[ (int) $post_id ][ $key ] = $value;
		return true;
	}
}
$qe_product = new class( 77 ) {
	public $id;
	public $meta = array();
	public $saved = false;
	public function __construct( $id ) {
		$this->id = (int) $id;
	}
	public function get_id() {
		return $this->id;
	}
	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}
	public function save() {
		$this->saved = true;
	}
};
$_REQUEST[ '_nh_catalog_highlight' ] = 'yes';
nh_catalog_highlight_quick_edit_save( $qe_product );
nh_hl_assert( 'quick edit save writes yes meta', 'yes' === $nh_highlight_meta[ 77 ][ '_nh_catalog_highlight' ] );
nh_hl_assert( 'quick edit save calls product->save()', true === $qe_product->saved );
unset( $_REQUEST[ '_nh_catalog_highlight' ] );
nh_catalog_highlight_quick_edit_save( $qe_product );
nh_hl_assert( 'quick edit unchecked clears to no', 'no' === $nh_highlight_meta[ 77 ][ '_nh_catalog_highlight' ] );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll tests passed\n";
exit( $failures ? 1 : 0 );
