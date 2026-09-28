<?php
/**
 * Attribute chips under the category description.
 *
 * Only categories with no subcategories show a row. The catalog filter
 * screen chooses which attribute belongs on which category.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NHF_OPTION_CHIPS', 'nhf_chip_filters' );

/**
 * Category IDs that have no children.
 *
 * @param array<int, object> $terms Term objects with term_id and parent.
 * @return int[]
 */
function nhf_leaf_ids_from_terms( $terms ) {
	if ( ! is_array( $terms ) ) {
		return array();
	}

	$ids     = array();
	$parents = array();
	foreach ( $terms as $term ) {
		if ( ! is_object( $term ) || empty( $term->term_id ) ) {
			continue;
		}
		$id       = (int) $term->term_id;
		$ids[]    = $id;
		$parent   = isset( $term->parent ) ? (int) $term->parent : 0;
		if ( $parent ) {
			$parents[ $parent ] = true;
		}
	}

	$leaves = array();
	foreach ( $ids as $id ) {
		if ( empty( $parents[ $id ] ) ) {
			$leaves[] = $id;
		}
	}

	return array_values( array_unique( $leaves ) );
}

/**
 * Leaf product categories on this shop.
 *
 * @return int[]
 */
function nhf_get_leaf_category_ids() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	if ( ! function_exists( 'get_terms' ) ) {
		$cache = array();
		return $cache;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		$cache = array();
		return $cache;
	}

	$cache = nhf_leaf_ids_from_terms( $terms );
	return $cache;
}

/**
 * Whether this category has no subcategories.
 *
 * @param int $term_id Category ID.
 */
function nhf_category_is_leaf( $term_id ) {
	$term_id = (int) $term_id;
	if ( ! $term_id ) {
		return false;
	}

	return in_array( $term_id, nhf_get_leaf_category_ids(), true );
}

/**
 * Saved chip rows.
 *
 * @return array<int, array{attribute:string,categories:int[]}>
 */
function nhf_get_chip_rows() {
	$saved = get_option( NHF_OPTION_CHIPS, array() );
	if ( ! is_array( $saved ) || empty( $saved['rows'] ) || ! is_array( $saved['rows'] ) ) {
		return array();
	}

	return $saved['rows'];
}

/**
 * Attribute slug for a category, or an empty string.
 *
 * @param int        $term_id Category ID.
 * @param array|null $rows    Saved rows. Null reads the option.
 */
function nhf_chip_attribute_for_category( $term_id, $rows = null ) {
	$term_id = (int) $term_id;
	if ( ! $term_id ) {
		return '';
	}

	if ( null === $rows ) {
		$rows = nhf_get_chip_rows();
	}
	if ( ! is_array( $rows ) ) {
		return '';
	}

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) || empty( $row['attribute'] ) || empty( $row['categories'] ) || ! is_array( $row['categories'] ) ) {
			continue;
		}
		$cats = array_map( 'intval', $row['categories'] );
		if ( in_array( $term_id, $cats, true ) ) {
			return sanitize_title( (string) $row['attribute'] );
		}
	}

	return '';
}

/**
 * Sanitize the chip-filter settings form.
 *
 * A category keeps the first attribute it was given.
 *
 * @param mixed $input Posted value.
 * @return array{saved:int,rows:array<int, array{attribute:string,categories:int[]}>}
 */
function nhf_sanitize_chip_filters( $input ) {
	$rows_in = array();
	if ( is_array( $input ) && isset( $input['rows'] ) && is_array( $input['rows'] ) ) {
		$rows_in = $input['rows'];
	}

	$valid_attrs = function_exists( 'nhf_get_all_attribute_slugs' ) ? nhf_get_all_attribute_slugs() : array();
	$valid_cats  = nhf_get_leaf_category_ids();
	$seen_cats   = array();
	$rows        = array();

	foreach ( $rows_in as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$attribute = sanitize_title( (string) ( $row['attribute'] ?? '' ) );
		if ( '' === $attribute ) {
			continue;
		}
		if ( ! empty( $valid_attrs ) && ! in_array( $attribute, $valid_attrs, true ) ) {
			continue;
		}

		$cats = array();
		$raw  = isset( $row['categories'] ) && is_array( $row['categories'] ) ? $row['categories'] : array();
		foreach ( $raw as $id ) {
			$id = absint( $id );
			if ( ! $id || isset( $seen_cats[ $id ] ) ) {
				continue;
			}
			if ( ! empty( $valid_cats ) && ! in_array( $id, $valid_cats, true ) ) {
				continue;
			}
			$seen_cats[ $id ] = true;
			$cats[]           = $id;
		}
		if ( empty( $cats ) ) {
			continue;
		}

		$rows[] = array(
			'attribute'  => $attribute,
			'categories' => $cats,
		);
	}

	return array(
		'saved' => 1,
		'rows'  => $rows,
	);
}

/**
 * Query args after toggling one chip.
 *
 * @param string   $param         Clean attribute query key.
 * @param string   $slug          Term slug.
 * @param string[] $selected      Currently selected slugs.
 * @param array    $current_args  Other query args to keep.
 * @return array<string, string>
 */
function nhf_chip_query_args( $param, $slug, array $selected, array $current_args ) {
	$param = sanitize_title( (string) $param );
	$slug  = sanitize_title( (string) $slug );
	$clean = array();
	foreach ( $selected as $item ) {
		$item = sanitize_title( (string) $item );
		if ( '' !== $item ) {
			$clean[] = $item;
		}
	}
	$clean  = array_values( array_unique( $clean ) );
	$was_on = ( '' !== $slug && in_array( $slug, $clean, true ) );
	$next   = $was_on ? array_values( array_diff( $clean, array( $slug ) ) ) : $clean;
	if ( ! $was_on && '' !== $slug ) {
		$next[] = $slug;
	}
	if ( ! empty( $next ) ) {
		sort( $next, SORT_NATURAL );
	}

	$args = array();
	foreach ( $current_args as $key => $value ) {
		$key = (string) $key;
		if ( $key === $param || 'paged' === $key || is_array( $value ) ) {
			continue;
		}
		if ( ! is_scalar( $value ) ) {
			continue;
		}
		$args[ $key ] = (string) $value;
	}
	if ( ! empty( $next ) ) {
		$args[ $param ] = implode( ',', $next );
	}

	return $args;
}

/**
 * "Parent / Child" label so leaf categories stay recognizable.
 *
 * @param object             $term  Category term.
 * @param array<int, object> $by_id Terms keyed by ID.
 */
function nhf_category_path_label( $term, array $by_id ) {
	if ( ! is_object( $term ) || ! isset( $term->name ) ) {
		return '';
	}

	$parts  = array( (string) $term->name );
	$parent = isset( $term->parent ) ? (int) $term->parent : 0;
	$guard  = 0;
	while ( $parent && isset( $by_id[ $parent ] ) && $guard < 8 ) {
		array_unshift( $parts, (string) $by_id[ $parent ]->name );
		$parent = isset( $by_id[ $parent ]->parent ) ? (int) $by_id[ $parent ]->parent : 0;
		$guard++;
	}

	return implode( ' / ', $parts );
}

/**
 * Print the chip row for the current category.
 */
function nhf_render_category_chips() {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return;
	}

	$term = get_queried_object();
	if ( ! is_object( $term ) || empty( $term->term_id ) ) {
		return;
	}
	if ( ! nhf_category_is_leaf( (int) $term->term_id ) ) {
		return;
	}

	$attribute = nhf_chip_attribute_for_category( (int) $term->term_id );
	if ( '' === $attribute || ! function_exists( 'wc_attribute_taxonomy_name' ) ) {
		return;
	}

	$tax = wc_attribute_taxonomy_name( $attribute );
	if ( ! taxonomy_exists( $tax ) ) {
		return;
	}

	$parent_ids = function_exists( 'nhf_get_archive_parent_ids' ) ? nhf_get_archive_parent_ids( 2000 ) : array();
	$object_ids = function_exists( 'nhf_expand_with_variations' ) ? nhf_expand_with_variations( $parent_ids ) : $parent_ids;
	$term_args  = array(
		'taxonomy'   => $tax,
		'hide_empty' => true,
		'orderby'    => 'name',
		'order'      => 'ASC',
	);
	if ( ! empty( $object_ids ) ) {
		$term_args['object_ids'] = $object_ids;
	}

	$terms = get_terms( $term_args );
	if ( empty( $terms ) || is_wp_error( $terms ) || count( $terms ) < 2 ) {
		return;
	}

	$attr_row = null;
	if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
		foreach ( (array) wc_get_attribute_taxonomies() as $attr ) {
			if ( is_object( $attr ) && isset( $attr->attribute_name ) && sanitize_title( $attr->attribute_name ) === $attribute ) {
				$attr_row = $attr;
				break;
			}
		}
	}
	if ( $attr_row && function_exists( 'nhf_attribute_uses_range' ) && nhf_attribute_uses_range( $attr_row ) && function_exists( 'nhf_numeric_terms' ) ) {
		$numeric = nhf_numeric_terms( $terms );
		if ( ! empty( $numeric ) ) {
			$terms = array();
			foreach ( $numeric as $row ) {
				$terms[] = $row['term'];
			}
		}
	}

	$param    = function_exists( 'nhf_get_filter_param_for_tax' ) ? nhf_get_filter_param_for_tax( $tax ) : $attribute;
	$selected = function_exists( 'nhf_get_selected_attr_slugs' ) ? nhf_get_selected_attr_slugs( $tax ) : array();
	$label    = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $tax ) : $attribute;
	$base     = function_exists( 'nhf_current_archive_url' ) ? nhf_current_archive_url() : '';
	$current  = array();
	foreach ( $_GET as $key => $value ) {
		if ( is_array( $value ) ) {
			continue;
		}
		$current[ $key ] = sanitize_text_field( wp_unslash( $value ) );
	}

	echo '<section class="nhf-chips" aria-label="' . esc_attr( $label ) . '">';
	echo '<p class="nhf-chips__label">' . esc_html( $label ) . '</p>';
	echo '<div class="nhf-chips__row">';
	foreach ( $terms as $chip ) {
		if ( ! is_object( $chip ) || empty( $chip->slug ) ) {
			continue;
		}
		$args   = nhf_chip_query_args( $param, $chip->slug, $selected, $current );
		$url    = function_exists( 'add_query_arg' ) ? add_query_arg( $args, $base ) : $base;
		$active = in_array( $chip->slug, $selected, true );
		echo '<a class="nhf-chip' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $active ? ' aria-current="true"' : '' ) . '>';
		echo esc_html( $chip->name );
		echo '</a>';
	}
	echo '</div></section>';
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'woocommerce_archive_description', 'nhf_render_category_chips', 20 );
}
