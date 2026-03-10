<?php
/**
 * Plugin Name: Custom Filters
 * Description: Custom WooCommerce sidebar with accordion Product Categories + real Filters (attributes, stock, sale) pruned to current archive. Use [nh_filters_sidebar] in any sidebar widget area.
 * Author: Daiva Reinike
 * Version: 1.7.0
 * Requires Plugins: woocommerce
 * Text Domain: nhf
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Load plugin textdomain
 */
function nhf_load_textdomain() {
	load_plugin_textdomain(
		'nhf',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages/'
	);
}
add_action( 'plugins_loaded', 'nhf_load_textdomain' );

/* ------------------------------------------------------------
 *  Enqueue (only on product archives)
 * ------------------------------------------------------------ */
add_action( 'wp_enqueue_scripts', function() {
	if ( ! function_exists( 'is_shop' ) ) return;
	if ( ! ( is_shop() || is_product_taxonomy() || is_product_tag() ) ) return;

	wp_register_style(
		'nhf-styles',
		plugins_url( 'assets/css/nhf.css', __FILE__ ),
		[],
		'1.7.0'
	);
	wp_enqueue_style( 'nhf-styles' );

	wp_register_script(
		'nhf-script',
		plugins_url( 'assets/js/nhf.js', __FILE__ ),
		[],
		'1.7.0',
		true
	);

	// Make mobile UI texts translatable in JS
	wp_localize_script(
		'nhf-script',
		'nhfL10n',
		[
			'categories'     => __( 'Categories', 'nhf' ),
			'filters'        => __( 'Filters', 'nhf' ),
			'filterProducts' => __( 'Filter Products', 'nhf' ),
			'reset'          => __( 'Reset', 'nhf' ),
			'apply'          => __( 'Apply', 'nhf' ),
			'close'          => __( 'Close', 'nhf' ),
		]
	);

	wp_enqueue_script( 'nhf-script' );
} );

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

/**
 * Map attribute taxonomy => clean query key
 * Example: pa_paksuus => paksuus
 */
function nhf_get_filter_param_map(): array {
	$map   = [];
	$attrs = wc_get_attribute_taxonomies();

	if ( ! $attrs ) {
		return $map;
	}

	foreach ( $attrs as $attr ) {
		$tax = wc_attribute_taxonomy_name( $attr->attribute_name ); // pa_paksuus
		$key = sanitize_title( $attr->attribute_name );             // paksuus
		$map[ $tax ] = $key;
	}

	return $map;
}

/**
 * Get clean query key for taxonomy
 */
function nhf_get_filter_param_for_tax( string $tax ): string {
	$map = nhf_get_filter_param_map();
	return $map[ $tax ] ?? $tax;
}

/**
 * Normalize raw selected values into unique sorted slugs
 */
function nhf_normalize_filter_values( $vals ): array {
	if ( ! is_array( $vals ) ) {
		$vals = explode( ',', (string) $vals );
	}

	$vals = array_filter( array_map( 'sanitize_title', $vals ) );
	$vals = array_values( array_unique( $vals ) );

	if ( ! empty( $vals ) ) {
		sort( $vals, SORT_NATURAL );
	}

	return $vals;
}

/**
 * Get selected slugs for attribute taxonomy from clean URL param.
 * Supports both new format (?paksuus=10-mm,16-mm)
 * and legacy format (?attr_pa_paksuus[]=10-mm)
 */
function nhf_get_selected_attr_slugs( string $tax ): array {
	$clean_key  = nhf_get_filter_param_for_tax( $tax );
	$legacy_key = 'attr_' . $tax;

	if ( isset( $_GET[ $clean_key ] ) ) {
		return nhf_normalize_filter_values( wp_unslash( $_GET[ $clean_key ] ) );
	}

	if ( isset( $_GET[ $legacy_key ] ) ) {
		return nhf_normalize_filter_values( wp_unslash( $_GET[ $legacy_key ] ) );
	}

	return [];
}

/**
 * Get product (parent) IDs for the CURRENT ARCHIVE.
 * Soft-limited for performance.
 */
function nhf_get_archive_parent_ids( int $max = 2000 ): array {
	$args = [
		'post_type'              => 'product',
		'post_status'            => 'publish',
		'fields'                 => 'ids',
		'posts_per_page'         => $max,
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	];

	// Constrain to current taxonomy archive if present.
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

/**
 * Helper: get current "Sale" category slug from locale
 */
function nhf_get_sale_slug(): string {
	$locale = get_locale();

	$map = [
		'lt_LT' => 'ispardavimas',
		'nb_NO' => 'salg',
		'sv_SE' => 'rea',
		'fi_FI' => 'ale',
		'de_DE' => 'sale',
	];

	if ( isset( $map[ $locale ] ) ) {
		return $map[ $locale ];
	}

	return 'sale';
}

/* ------------------------------------------------------------
 *  Normalize filter URLs
 *  - legacy: ?attr_pa_paksuus[]=10-mm
 *  - array:  ?paksuus[]=10-mm&paksuus[]=16-mm
 *  - clean:  ?paksuus=10-mm,16-mm
 * ------------------------------------------------------------ */
add_action( 'template_redirect', function() {
	if ( is_admin() ) return;
	if ( ! function_exists( 'is_shop' ) ) return;
	if ( ! ( is_shop() || is_product_taxonomy() || is_product_tag() ) ) return;
	if ( empty( $_GET ) ) return;

	$map = nhf_get_filter_param_map();
	if ( empty( $map ) ) return;

	$clean_keys   = array_values( $map );
	$current_url  = nhf_current_archive_url();
	$new_args     = [];
	$changed      = false;

	foreach ( $_GET as $key => $value ) {
		// Legacy keys like attr_pa_paksuus[]
		if ( 0 === strpos( $key, 'attr_' ) ) {
			$tax = substr( $key, 5 );

			if ( isset( $map[ $tax ] ) ) {
				$vals = nhf_normalize_filter_values( wp_unslash( $value ) );

				if ( ! empty( $vals ) ) {
					$new_args[ $map[ $tax ] ] = implode( ',', $vals );
				}

				$changed = true;
				continue;
			}
		}

		// Clean keys submitted as arrays: paksuus[]=10-mm&paksuus[]=16-mm
		if ( in_array( $key, $clean_keys, true ) ) {
			if ( is_array( $value ) ) {
				$vals = nhf_normalize_filter_values( wp_unslash( $value ) );

				if ( ! empty( $vals ) ) {
					$new_args[ $key ] = implode( ',', $vals );
				}

				$changed = true;
			} else {
				$new_args[ $key ] = sanitize_text_field( wp_unslash( $value ) );
			}
			continue;
		}

		// Preserve normal args
		if ( is_array( $value ) ) {
			continue;
		}

		$new_args[ $key ] = sanitize_text_field( wp_unslash( $value ) );
	}

	if ( ! $changed ) return;

	$target = add_query_arg( $new_args, $current_url );
	wp_safe_redirect( $target, 301 );
	exit;
}, 1 );

/* ------------------------------------------------------------
 *  Category Tree
 * ------------------------------------------------------------ */
function nhf_render_categories() {
	$current_term_id = 0;

	if ( is_product_taxonomy() ) {
		$obj = get_queried_object();

		if ( isset( $obj->term_id ) ) {
			$current_term_id = (int) $obj->term_id;
		}
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

	$sale_slug = nhf_get_sale_slug();

	echo '<ul class="nhf-cat-list">';

	foreach ( $categories as $cat ) {
		if ( $sale_slug && $cat->slug === $sale_slug ) {
			continue;
		}

		$is_current  = ( $cat->term_id === $current_term_id );
		$is_ancestor = ( $current_term_id && term_is_ancestor_of( $cat->term_id, $current_term_id, 'product_cat' ) );
		$is_open     = ( $is_current || $is_ancestor );

		$children = get_terms([
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => $cat->term_id,
		]);

		$has_children = ( ! empty( $children ) && ! is_wp_error( $children ) );
		$open_class   = ( $has_children && $is_open ) ? ' is-open' : '';

		echo '<li class="nhf-cat-item' . esc_attr( $open_class ) . '">';

		// If no children: normal link
		if ( ! $has_children ) {
			echo '<a class="nhf-cat-link" href="' . esc_url( get_term_link( $cat ) ) . '">';
			echo '<span class="nhf-cat-name">' . esc_html( $cat->name ) . '</span>';
			echo '</a>';
			echo '</li>';
			continue;
		}

		$toggle_state = $is_open ? 'true' : 'false';

		echo '<button class="nhf-cat-toggle" aria-expanded="' . esc_attr( $toggle_state ) . '" aria-controls="sub-' . esc_attr( $cat->slug ) . '">';
		echo '<span class="nhf-cat-name">' . esc_html( $cat->name ) . '</span>';
		echo '<span class="nhf-icon" aria-hidden="true"></span>';
		echo '</button>';

		echo '<ul id="sub-' . esc_attr( $cat->slug ) . '" class="nhf-cat-sub" aria-hidden="' . ( $is_open ? 'false' : 'true' ) . '">';

		echo '<li class="nhf-all"><a href="' . esc_url( get_term_link( $cat ) ) . '">'
			. esc_html( $cat->name )
			. ' &ndash; '
			. esc_html__( 'All products', 'nhf' )
			. '</a></li>';

		foreach ( $children as $child ) {
			$child_active = ( $child->term_id === $current_term_id ) ? ' class="is-active"' : '';
			echo '<li' . $child_active . '>';
			echo '<a href="' . esc_url( get_term_link( $child ) ) . '">' . esc_html( $child->name ) . '</a>';
			echo '</li>';
		}

		echo '</ul>';
		echo '</li>';
	}

	echo '</ul>';
}

/* ------------------------------------------------------------
 *  Shortcode: [nh_filters_sidebar]
 * ------------------------------------------------------------ */
add_shortcode( 'nh_filters_sidebar', function() {
	if ( ! function_exists( 'is_shop' ) ) return '';
	if ( ! ( is_shop() || is_product_taxonomy() || is_product_tag() ) ) return '';

	ob_start();

	$action              = nhf_current_archive_url();
	$parent_ids          = nhf_get_archive_parent_ids( 2000 );
	$object_ids          = nhf_expand_with_variations( $parent_ids );
	$filter_param_map    = nhf_get_filter_param_map();
	$filter_param_values = array_values( $filter_param_map );

	echo '<div id="nhf-sidebar" class="nhf-sidebar-block">';
	echo '<h3 class="nhf-heading">' . esc_html__( 'Product Categories', 'nhf' ) . '</h3>';
	nhf_render_categories();

	echo '<div class="nhf-filters">';
	echo '<h3 class="nhf-heading">' . esc_html__( 'Filter Products', 'nhf' ) . '</h3>';
	echo '<form class="nhf-form" method="get" action="' . esc_url( $action ) . '">';

	// Preserve non-filter args
	foreach ( $_GET as $k => $v ) {
		if ( 0 === strpos( $k, 'attr_' ) ) continue;
		if ( in_array( $k, $filter_param_values, true ) ) continue;
		if ( in_array( $k, [ 'price_min', 'price_max', 'instock', 'onsale' ], true ) ) continue;
		if ( is_array( $v ) ) continue;

		echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( wp_unslash( $v ) ) . '">';
	}

	/* ========== Attribute groups (auto + pruned) ========== */
	$attrs = wc_get_attribute_taxonomies();

	if ( $attrs ) {
		foreach ( $attrs as $attr ) {
			$tax       = wc_attribute_taxonomy_name( $attr->attribute_name );
			$param_key = nhf_get_filter_param_for_tax( $tax );

			if ( ! taxonomy_exists( $tax ) ) continue;

			$selected = nhf_get_selected_attr_slugs( $tax );
			$label    = wc_attribute_label( $tax );

			$term_args = [
				'taxonomy'   => $tax,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			];

			if ( ! empty( $object_ids ) ) {
				$term_args['object_ids'] = $object_ids;
			}

			$terms = get_terms( $term_args );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			// Hide group if only 0 or 1 available value,
			// but preserve active selected filter.
			if ( count( $terms ) <= 1 ) {
				if ( ! empty( $selected ) ) {
					echo '<input type="hidden" name="' . esc_attr( $param_key ) . '" value="' . esc_attr( implode( ',', $selected ) ) . '">';
				}
				continue;
			}

			echo '<section class="nhf-filter nhf-filter--attribute">';
			echo '  <button type="button" class="nhf-filter-toggle" aria-expanded="false">' . esc_html( $label ) . ' <span class="nhf-icon"></span></button>';
			echo '  <div class="nhf-filter-body" aria-hidden="true">';

			foreach ( $terms as $t ) {
				$checked = in_array( $t->slug, $selected, true ) ? ' checked' : '';
				echo '<label>';
				echo '  <input type="checkbox" name="' . esc_attr( $param_key ) . '[]" value="' . esc_attr( $t->slug ) . '"' . $checked . '>';
				echo    esc_html( $t->name );
				echo '</label>';
			}

			echo '  </div>';
			echo '</section>';
		}
	}

	/* ========== Toggles ========== */
	$instock = isset( $_GET['instock'] ) ? (int) $_GET['instock'] : 0;
	$onsale  = isset( $_GET['onsale'] )  ? (int) $_GET['onsale']  : 0;

	echo '<section class="nhf-filter nhf-filter--toggles is-open">';
	echo '  <div class="nhf-filter-body" aria-hidden="false">';
	echo '    <label><input type="checkbox" name="instock" value="1" ' . checked( $instock, 1, false ) . '> ' . esc_html__( 'In stock only', 'nhf' ) . '</label>';
	echo '    <label><input type="checkbox" name="onsale" value="1" ' . checked( $onsale, 1, false ) . '> ' . esc_html__( 'On sale', 'nhf' ) . '</label>';
	echo '  </div>';
	echo '</section>';

	/* ========== Apply / Reset ========== */
	echo '<div class="nhf-applybar">';
	echo '  <button type="submit" class="nhf-applybtn">' . esc_html__( 'Apply filters', 'nhf' ) . '</button>';
	echo '  <a class="nhf-reset" href="' . esc_url( $action ) . '">' . esc_html__( 'Reset filters', 'nhf' ) . '</a>';
	echo '</div>';

	echo '</form>';
	echo '</div>';
	echo '</div>';

	return ob_get_clean();
} );

/* ------------------------------------------------------------
 *  Apply GET filters to WooCommerce archive queries
 * ------------------------------------------------------------ */
add_action( 'pre_get_posts', function( $q ) {
	if ( ! function_exists( 'is_shop' ) ) return;
	if ( is_admin() || ! $q->is_main_query() ) return;
	if ( ! ( is_shop() || is_product_taxonomy() || is_product_tag() ) ) return;

	$tax_query  = (array) $q->get( 'tax_query', [] );
	$meta_query = (array) $q->get( 'meta_query', [] );

	if ( empty( $tax_query ) || ! isset( $tax_query['relation'] ) ) {
		$tax_query['relation'] = 'AND';
	}

	if ( empty( $meta_query ) || ! isset( $meta_query['relation'] ) ) {
		$meta_query['relation'] = 'AND';
	}

	// On sale
	if ( isset( $_GET['onsale'] ) && (int) $_GET['onsale'] === 1 ) {
		$on_sale_ids = array_map( 'absint', wc_get_product_ids_on_sale() );

		if ( empty( $on_sale_ids ) ) {
			$q->set( 'post__in', [0] );
		} else {
			$existing_in = (array) $q->get( 'post__in', [] );

			if ( ! empty( $existing_in ) ) {
				$intersect = array_values( array_intersect( $existing_in, $on_sale_ids ) );
				$q->set( 'post__in', $intersect ? $intersect : [0] );
			} else {
				$q->set( 'post__in', $on_sale_ids );
			}
		}
	}

	// In stock
	if ( isset( $_GET['instock'] ) && (int) $_GET['instock'] === 1 ) {
		$meta_query[] = [
			'key'     => '_stock_status',
			'value'   => 'instock',
			'compare' => '=',
		];
	}

	// Attributes
	$attrs = wc_get_attribute_taxonomies();

	if ( $attrs ) {
		foreach ( $attrs as $attr ) {
			$tax = wc_attribute_taxonomy_name( $attr->attribute_name );
			if ( ! taxonomy_exists( $tax ) ) continue;

			$clean_key  = nhf_get_filter_param_for_tax( $tax );
			$legacy_key = 'attr_' . $tax;

			$vals = null;

			if ( isset( $_GET[ $clean_key ] ) ) {
				$vals = wp_unslash( $_GET[ $clean_key ] );
			} elseif ( isset( $_GET[ $legacy_key ] ) ) {
				$vals = wp_unslash( $_GET[ $legacy_key ] );
			} else {
				continue;
			}

			$vals = nhf_normalize_filter_values( $vals );
			if ( empty( $vals ) ) continue;

			$tax_query[] = [
				'taxonomy' => $tax,
				'field'    => 'slug',
				'terms'    => $vals,
				'operator' => 'IN',
			];
		}
	}

	$q->set( 'tax_query', $tax_query );
	$q->set( 'meta_query', $meta_query );
} );

// Robust "On sale" constraint
add_action( 'woocommerce_product_query', function( $q ) {
	if ( is_admin() ) return;
	if ( ! ( is_shop() || is_product_taxonomy() || is_product_tag() ) ) return;
	if ( ! isset( $_GET['onsale'] ) || (string) $_GET['onsale'] !== '1' ) return;

	$on_sale_ids = array_map( 'absint', wc_get_product_ids_on_sale() );

	if ( empty( $on_sale_ids ) ) {
		$q->set( 'post__in', [0] );
		return;
	}

	$existing_in = (array) $q->get( 'post__in', [] );

	if ( ! empty( $existing_in ) ) {
		$intersect = array_values( array_intersect( $existing_in, $on_sale_ids ) );
		$q->set( 'post__in', $intersect ? $intersect : [0] );
	} else {
		$q->set( 'post__in', $on_sale_ids );
	}
}, 20 );
