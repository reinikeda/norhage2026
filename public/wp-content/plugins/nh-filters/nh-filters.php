<?php
/**
 * Plugin Name: Custom Filters
 * Description: Custom WooCommerce sidebar with accordion Product Categories + real Filters (attributes, stock, sale) pruned to current archive. Use [nh_filters_sidebar] in any sidebar widget area.
 * Author: Daiva Reinike
 * Version: 1.6.0
 * Requires Plugins: woocommerce
 * Text Domain: nhf
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ------------------------------------------------------------
 *  Enqueue (only on product archives)
 * ------------------------------------------------------------ */
add_action( 'wp_enqueue_scripts', function() {
	if ( ! function_exists( 'is_shop' ) ) return;
	if ( ! ( is_shop() || is_product_taxonomy() || is_product_tag() ) ) return;

	wp_register_style( 'nhf-styles', plugins_url( 'assets/css/nhf.css', __FILE__ ), [], '1.6.0' );
	wp_enqueue_style( 'nhf-styles' );

	wp_register_script( 'nhf-script', plugins_url( 'assets/js/nhf.js', __FILE__ ), [], '1.6.0', true );
	wp_enqueue_script( 'nhf-script' );
});


/* ------------------------------------------------------------
 *  Helpers
 * ------------------------------------------------------------ */

/** Base URL of current archive (no query string) */
function nhf_current_archive_url(): string {
	if ( is_shop() ) {
		$url = wc_get_page_permalink( 'shop' );
	} elseif ( is_product_taxonomy() || is_product_tag() ) {
		$url = get_term_link( get_queried_object() );
	} else {
		$url = get_post_type_archive_link( 'product' );
	}
	return is_wp_error( $url ) ? home_url( '/' ) : $url;
}

/** Selected slugs for an attribute tax (e.g. "pa_length") */
function nhf_get_selected_attr_slugs( string $tax ): array {
	$key = 'attr_' . $tax;
	if ( empty( $_GET[ $key ] ) ) return [];
	$vals = $_GET[ $key ];
	if ( ! is_array( $vals ) ) $vals = explode( ',', (string) $vals );
	$vals = array_filter( array_map( 'sanitize_title', $vals ) );
	return array_values( array_unique( $vals ) );
}

/**
 * Get product (parent) IDs for the CURRENT ARCHIVE.
 * Soft-limited for performance.
 */
function nhf_get_archive_parent_ids( int $max = 2000 ): array {
	$args = [
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'posts_per_page' => $max,
		'no_found_rows'  => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	];

	// Constrain to current taxonomy archive if present
	if ( is_product_taxonomy() ) {
		$obj = get_queried_object();
		if ( isset( $obj->taxonomy, $obj->term_id ) ) {
			$args['tax_query'] = [[
				'taxonomy' => $obj->taxonomy,
				'field'    => 'term_id',
				'terms'    => (int) $obj->term_id,
			]];
		}
	}

	$q = new WP_Query( $args );
	return $q->posts ? array_map( 'absint', $q->posts ) : [];
}

/**
 * Include child VARIATION IDs for variable products so that
 * term pruning also “sees” terms attached only to variations.
 */
function nhf_expand_with_variations( array $parent_ids ): array {
	if ( empty( $parent_ids ) ) return [];
	$all = $parent_ids;

	foreach ( $parent_ids as $pid ) {
		$product = wc_get_product( $pid );
		if ( $product && $product->is_type( 'variable' ) ) {
			$children = $product->get_children();
			if ( $children ) {
				foreach ( $children as $vid ) {
					$all[] = (int) $vid;
				}
			}
		}
	}

	return array_values( array_unique( array_map( 'absint', $all ) ) );
}

/* ------------------------------------------------------------
 *  Category Tree (unchanged)
 * ------------------------------------------------------------ */
function nhf_render_categories() {
	$current_term_id = 0;
	if ( is_product_taxonomy() ) {
		$obj = get_queried_object();
		if ( isset( $obj->term_id ) ) $current_term_id = (int) $obj->term_id;
	}

	$categories = get_terms([
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'parent'     => 0,
	]);

	if ( empty( $categories ) || is_wp_error( $categories ) ) {
		echo '<p>' . esc_html__( 'No categories found.', 'nhf' ) . '</p>';
		return;
	}

	echo '<ul class="nhf-cat-list">';

	foreach ( $categories as $cat ) {
		$is_current  = ( $cat->term_id === $current_term_id );
		$is_ancestor = ( $current_term_id && term_is_ancestor_of( $cat->term_id, $current_term_id, 'product_cat' ) );
		$is_open     = ( $is_current || $is_ancestor );

		$toggle_state = $is_open ? 'true' : 'false';
		$open_class   = $is_open ? ' is-open' : '';

		echo '<li class="nhf-cat-item' . esc_attr( $open_class ) . '">';
		echo '<button class="nhf-cat-toggle" aria-expanded="' . esc_attr( $toggle_state ) . '" aria-controls="sub-' . esc_attr( $cat->slug ) . '">';
		echo '<span class="nhf-cat-name">' . esc_html( $cat->name ) . '</span>';
		echo '<span class="nhf-icon" aria-hidden="true"></span>';
		echo '</button>';

		$children = get_terms([
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => $cat->term_id,
		]);

		if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
			echo '<ul id="sub-' . esc_attr( $cat->slug ) . '" class="nhf-cat-sub" aria-hidden="' . ( $is_open ? 'false' : 'true' ) . '">';

			echo '<li class="nhf-all"><a href="' . esc_url( get_term_link( $cat ) ) . '">' . esc_html__( 'All ', 'nhf' ) . esc_html( $cat->name ) . '</a></li>';

			foreach ( $children as $child ) {
				$child_active = ( $child->term_id === $current_term_id ) ? ' class="is-active"' : '';
				echo '<li' . $child_active . '>';
				echo '<a href="' . esc_url( get_term_link( $child ) ) . '">' . esc_html( $child->name ) . '</a>';
				echo '</li>';
			}
			echo '</ul>';
		}

		echo '</li>';
	}

	echo '</ul>';
}

/* ------------------------------------------------------------
 *  Shortcode: [nh_filters_sidebar]
 *  - Categories
 *  - REAL Filters form (GET)
 *  - PRUNED attributes & values to current archive (parent+variations)
 *  - Price disabled (for now)
 * ------------------------------------------------------------ */
add_shortcode( 'nh_filters_sidebar', function() {

	if ( ! function_exists( 'is_shop' ) ) return '';
	if ( ! ( is_shop() || is_product_taxonomy() || is_product_tag() ) ) return '';

	ob_start();

	echo '<div id="nhf-sidebar" class="nhf-sidebar-block">';
	echo '<h3 class="nhf-heading">' . esc_html__( 'Product Categories', 'nhf' ) . '</h3>';
	nhf_render_categories();

	$action      = nhf_current_archive_url();
	$parent_ids  = nhf_get_archive_parent_ids( 2000 );
	$object_ids  = nhf_expand_with_variations( $parent_ids ); // includes variations for pruning

	echo '<div class="nhf-filters">';
	echo '<h3 class="nhf-heading">' . esc_html__( 'Filter Products', 'nhf' ) . '</h3>';
	echo '<form class="nhf-form" method="get" action="' . esc_url( $action ) . '">';

	// Preserve non-filter args (orderby, paged, etc.)
	foreach ( $_GET as $k => $v ) {
		if ( 0 === strpos( $k, 'attr_' ) ) continue;
		if ( in_array( $k, [ 'price_min','price_max','instock','onsale' ], true ) ) continue; // price disabled
		if ( is_array( $v ) ) continue;
		echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}

	/* ========== Toggles (In stock / On sale) ========== */
	$instock = isset($_GET['instock']) ? (int) $_GET['instock'] : 0;
	$onsale  = isset($_GET['onsale'])  ? (int) $_GET['onsale']  : 0;

	echo '<section class="nhf-filter nhf-filter--toggles is-open">';
	echo '  <div class="nhf-filter-body" aria-hidden="false">';
	echo '    <label><input type="checkbox" name="instock" value="1" ' . checked( $instock, 1, false ) . '> ' . esc_html__( 'In stock only', 'nhf' ) . '</label>';
	echo '    <label><input type="checkbox" name="onsale" value="1" ' . checked( $onsale, 1, false ) . '> ' . esc_html__( 'On sale', 'nhf' ) . '</label>';
	echo '  </div>';
	echo '</section>';

	/* ========== Attribute groups (auto + pruned) ========== */
	$attrs = wc_get_attribute_taxonomies();
	if ( $attrs ) {
		foreach ( $attrs as $attr ) {
			$tax = wc_attribute_taxonomy_name( $attr->attribute_name ); // e.g. pa_length
			if ( ! taxonomy_exists( $tax ) ) continue;

			// Prune to only terms actually used by products in this archive
			$term_args = [
				'taxonomy'   => $tax,
				'hide_empty' => true,   // hide terms unused globally
				'orderby'    => 'name',
				'order'      => 'ASC',
			];

			// Further limit to terms attached to current archive parents/variations
			if ( ! empty( $object_ids ) ) {
				$term_args['object_ids'] = $object_ids;
			}

			$terms = get_terms( $term_args );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				// If no terms are relevant for this archive, skip the whole attribute group
				continue;
			}

			$selected = nhf_get_selected_attr_slugs( $tax );
			$label    = wc_attribute_label( $tax );

			echo '<section class="nhf-filter nhf-filter--attribute">';
			echo '  <button type="button" class="nhf-filter-toggle" aria-expanded="false">' . esc_html( $label ) . ' <span class="nhf-icon"></span></button>';
			echo '  <div class="nhf-filter-body" aria-hidden="true">';

			foreach ( $terms as $t ) {
				// (Terms returned here are guaranteed to be used by at least one product/variation in this archive)
				$checked = in_array( $t->slug, $selected, true ) ? ' checked' : '';
				echo '<label>';
				echo '  <input type="checkbox" name="attr_' . esc_attr( $tax ) . '[]" value="' . esc_attr( $t->slug ) . '"' . $checked . '>';
				echo    esc_html( $t->name );
				echo '</label>';
			}

			echo '  </div>';
			echo '</section>';
		}
	}

	/* ========== Apply / Reset ========== */
	echo '<div class="nhf-applybar">';
	echo '  <button type="submit" class="nhf-applybtn">' . esc_html__( 'Apply filters', 'nhf' ) . '</button>';
	echo '  <a class="nhf-reset" href="' . esc_url( $action ) . '">' . esc_html__( 'Reset filters', 'nhf' ) . '</a>';
	echo '</div>';

	echo '</form>';
	echo '</div>'; // .nhf-filters
	echo '</div>'; // .nhf-sidebar-block

	return ob_get_clean();
});

/* ------------------------------------------------------------
 *  Apply GET filters to WooCommerce archive queries (no price)
 * ------------------------------------------------------------ */
add_action( 'pre_get_posts', function( $q ) {
	if ( ! function_exists( 'is_shop' ) ) return;
	if ( is_admin() || ! $q->is_main_query() ) return;
	if ( ! ( is_shop() || is_product_taxonomy() || is_product_tag() ) ) return;

	$tax_query  = (array) $q->get( 'tax_query', [] );
	$meta_query = (array) $q->get( 'meta_query', [] );

	// Make relations explicit
	if ( empty($tax_query)  || ! isset($tax_query['relation']) )  $tax_query['relation']  = 'AND';
	if ( empty($meta_query) || ! isset($meta_query['relation']) ) $meta_query['relation'] = 'AND';

	// On sale
	if ( isset($_GET['onsale']) && (int) $_GET['onsale'] === 1 ) {
		$ids = wc_get_product_ids_on_sale();
		$q->set( 'post__in', ! empty( $ids ) ? array_map( 'absint', $ids ) : [0] );
	}

	// In stock
	if ( isset($_GET['instock']) && (int) $_GET['instock'] === 1 ) {
		$meta_query[] = [
			'key'     => '_stock_status',
			'value'   => 'instock',
			'compare' => '='
		];
	}

	// Attributes
	$attrs = wc_get_attribute_taxonomies();
	if ( $attrs ) {
		foreach ( $attrs as $attr ) {
			$tax = wc_attribute_taxonomy_name( $attr->attribute_name );
			if ( ! taxonomy_exists( $tax ) ) continue;

			$key  = 'attr_' . $tax;
			if ( ! isset($_GET[$key]) ) continue;

			$vals = $_GET[$key];
			if ( ! is_array( $vals ) ) $vals = explode( ',', (string) $vals );
			$vals = array_filter( array_map( 'sanitize_title', $vals ) );
			if ( ! $vals ) continue;

			$tax_query[] = [
				'taxonomy' => $tax,
				'field'    => 'slug',
				'terms'    => $vals,
				'operator' => 'IN',
			];
		}
	}

	$q->set( 'tax_query',  $tax_query );
	$q->set( 'meta_query', $meta_query );
});
