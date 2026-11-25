<?php
/**
 * Catalog product highlight.
 *
 * Merchandisers tick "Highlight in catalog" on a product. Highlighted cards
 * get a soft yellow block in shop/category grids and sort ahead of other
 * in-stock products (out-of-stock items still sink via the existing sort).
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post meta key for the catalog highlight flag.
 *
 * @return string
 */
function nh_catalog_highlight_meta_key() {
	return '_nh_catalog_highlight';
}

/**
 * Whether a product is marked to highlight in the catalog.
 *
 * @param int|\WC_Product|null $product Product ID or object.
 * @return bool
 */
function nh_product_is_catalog_highlight( $product ) {
	$id = 0;
	if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
		$id = (int) $product->get_id();
	} else {
		$id = absint( $product );
	}
	if ( ! $id ) {
		return false;
	}
	return 'yes' === get_post_meta( $id, nh_catalog_highlight_meta_key(), true );
}

/**
 * Product Data → General: highlight checkbox.
 */
add_action( 'woocommerce_product_options_general_product_data', 'nh_catalog_highlight_field' );
function nh_catalog_highlight_field() {
	echo '<div class="options_group">';
	woocommerce_wp_checkbox(
		array(
			'id'          => nh_catalog_highlight_meta_key(),
			'label'       => __( 'Highlight in catalog', 'nh-theme' ),
			'description' => __( 'Show this product with a highlighted card and place it first in category and shop grids.', 'nh-theme' ),
		)
	);
	echo '</div>';
}

/**
 * Persist the highlight flag.
 *
 * @param int $post_id Product ID.
 */
add_action( 'woocommerce_process_product_meta', 'nh_catalog_highlight_save' );
function nh_catalog_highlight_save( $post_id ) {
	$key   = nh_catalog_highlight_meta_key();
	$value = isset( $_POST[ $key ] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	update_post_meta( (int) $post_id, $key, $value );
}

/**
 * Add CSS class on catalog product cards.
 *
 * @param string[]   $classes Class list.
 * @param \WC_Product $product Product.
 * @return string[]
 */
add_filter( 'woocommerce_post_class', 'nh_catalog_highlight_post_class', 10, 2 );
function nh_catalog_highlight_post_class( $classes, $product ) {
	if ( nh_product_is_catalog_highlight( $product ) ) {
		$classes[] = 'nh-catalog-highlight';
	}
	return $classes;
}

/**
 * Products list column so highlights are visible without opening each product.
 *
 * @param array<string,string> $columns Columns.
 * @return array<string,string>
 */
add_filter( 'manage_edit-product_columns', 'nh_catalog_highlight_column', 20 );
function nh_catalog_highlight_column( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'sku' === $key || ( 'name' === $key && ! isset( $columns['sku'] ) ) ) {
			$new['nh_catalog_highlight'] = __( 'Highlight', 'nh-theme' );
		}
	}
	if ( ! isset( $new['nh_catalog_highlight'] ) ) {
		$new['nh_catalog_highlight'] = __( 'Highlight', 'nh-theme' );
	}
	return $new;
}

/**
 * Render the products-list highlight column.
 *
 * @param string $column Column key.
 */
add_action( 'manage_product_posts_custom_column', 'nh_catalog_highlight_column_content', 10, 1 );
function nh_catalog_highlight_column_content( $column ) {
	if ( 'nh_catalog_highlight' !== $column ) {
		return;
	}
	global $post;
	if ( ! $post || ! nh_product_is_catalog_highlight( $post->ID ) ) {
		echo '<span class="nh-catalog-highlight-col nh-catalog-highlight-col--no" aria-hidden="true">—</span>';
		return;
	}
	echo '<span class="nh-catalog-highlight-col nh-catalog-highlight-col--yes" title="' . esc_attr__( 'Highlighted in catalog', 'nh-theme' ) . '">' . esc_html__( 'Yes', 'nh-theme' ) . '</span>';
}

/**
 * Narrow the admin column.
 */
add_action( 'admin_head', 'nh_catalog_highlight_admin_css' );
function nh_catalog_highlight_admin_css() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit-product' !== $screen->id ) {
		return;
	}
	echo '<style>
		.wp-list-table .column-nh_catalog_highlight { width: 5.5em; text-align: center; }
		.nh-catalog-highlight-col--yes { font-weight: 600; color: #1E3932; }
	</style>';
}

/**
 * Pin highlighted products ahead of other in-stock items on catalog queries.
 *
 * Runs after out-of-stock sorting so the final order is:
 * in-stock highlighted → other in-stock → out of stock.
 *
 * @param array    $posts_clauses Query clauses.
 * @param WP_Query $query        Query.
 * @return array
 */
add_filter( 'posts_clauses', 'nh_catalog_highlight_posts_clauses', 2001, 2 );
function nh_catalog_highlight_posts_clauses( $posts_clauses, $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return $posts_clauses;
	}
	if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy() ) ) {
		return $posts_clauses;
	}

	global $wpdb;
	return nh_catalog_highlight_append_sort_clauses( $posts_clauses, $wpdb );
}

/**
 * Append highlight JOIN + ORDER BY fragment (testable without WP query context).
 *
 * @param array  $posts_clauses Query clauses.
 * @param object $wpdb         WordPress DB API.
 * @return array
 */
function nh_catalog_highlight_append_sort_clauses( $posts_clauses, $wpdb ) {
	$meta_key = nh_catalog_highlight_meta_key();
	$join     = isset( $posts_clauses['join'] ) ? (string) $posts_clauses['join'] : '';

	if ( ! preg_match( '/\bnh_highlight\b/', $join ) ) {
		$posts_clauses['join'] = $join . $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} AS nh_highlight ON ({$wpdb->posts}.ID = nh_highlight.post_id AND nh_highlight.meta_key = %s) ",
			$meta_key
		);
	}

	$highlight_order = "CASE WHEN nh_highlight.meta_value = 'yes' THEN 0 ELSE 1 END ASC";
	$orderby         = isset( $posts_clauses['orderby'] ) ? (string) $posts_clauses['orderby'] : '';

	if ( preg_match( '/nh_highlight\.meta_value/', $orderby ) ) {
		return $posts_clauses;
	}

	$stock_fragment = "CASE nh_stock.stock_status WHEN 'outofstock' THEN 1 ELSE 0 END ASC";
	if ( false !== strpos( $orderby, $stock_fragment ) ) {
		$posts_clauses['orderby'] = str_replace(
			$stock_fragment,
			$stock_fragment . ', ' . $highlight_order,
			$orderby
		);
	} else {
		$posts_clauses['orderby'] = $highlight_order . ( $orderby !== '' ? ', ' . $orderby : '' );
	}

	return $posts_clauses;
}
