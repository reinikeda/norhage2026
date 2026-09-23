<?php
/**
 * Which attributes appear in the catalog sidebar.
 *
 * Until the settings page is saved, every attribute is shown.
 * After save, only the ticked slugs appear.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NHF_OPTION_ATTRIBUTES', 'nhf_filter_attributes' );

/**
 * Attribute slugs (no pa_ prefix) from WooCommerce.
 *
 * @return string[]
 */
function nhf_get_all_attribute_slugs() {
	$slugs = array();
	if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
		return $slugs;
	}

	$attrs = wc_get_attribute_taxonomies();
	if ( ! $attrs ) {
		return $slugs;
	}

	foreach ( $attrs as $attr ) {
		if ( empty( $attr->attribute_name ) ) {
			continue;
		}
		$slugs[] = sanitize_title( $attr->attribute_name );
	}

	return array_values( array_unique( $slugs ) );
}

/**
 * Slugs from a stored option value.
 *
 * @param mixed $saved Option value.
 * @return string[]
 */
function nhf_normalize_saved_slugs( $saved ) {
	if ( is_array( $saved ) && isset( $saved['slugs'] ) && is_array( $saved['slugs'] ) ) {
		$saved = $saved['slugs'];
	}

	if ( ! is_array( $saved ) ) {
		return array();
	}

	$slugs = array();
	foreach ( $saved as $slug ) {
		if ( ! is_scalar( $slug ) ) {
			continue;
		}
		$slug = sanitize_title( (string) $slug );
		if ( '' !== $slug ) {
			$slugs[] = $slug;
		}
	}

	return array_values( array_unique( $slugs ) );
}

/**
 * Saved slugs, or null when the shop has not saved the settings page yet.
 *
 * @return string[]|null
 */
function nhf_get_visible_attribute_slugs() {
	$saved = get_option( NHF_OPTION_ATTRIBUTES, false );
	if ( false === $saved ) {
		return null;
	}

	if ( is_array( $saved ) && ! empty( $saved['saved'] ) ) {
		return nhf_normalize_saved_slugs( $saved );
	}

	return null;
}

/**
 * Whether this attribute group should render in the sidebar.
 *
 * @param string $attribute_name Slug without pa_.
 */
function nhf_attribute_is_visible( $attribute_name ) : bool {
	$allowed = nhf_get_visible_attribute_slugs();
	if ( null === $allowed ) {
		return true;
	}

	return in_array( sanitize_title( (string) $attribute_name ), $allowed, true );
}

/**
 * Sanitize the settings form. Marks the list as saved even when empty.
 *
 * @param mixed $input Posted value.
 * @return array{saved:int,slugs:string[]}
 */
function nhf_sanitize_filter_attributes( $input ) {
	if ( is_array( $input ) && isset( $input['slugs'] ) ) {
		$input = $input['slugs'];
	}

	$slugs = array();
	if ( is_array( $input ) ) {
		$valid = nhf_get_all_attribute_slugs();
		foreach ( $input as $slug ) {
			if ( ! is_scalar( $slug ) ) {
				continue;
			}
			$slug = sanitize_title( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			if ( ! empty( $valid ) && ! in_array( $slug, $valid, true ) ) {
				continue;
			}
			$slugs[] = $slug;
		}
		$slugs = array_values( array_unique( $slugs ) );
	}

	return array(
		'saved' => 1,
		'slugs' => $slugs,
	);
}

/**
 * First number in a term label. Comma and dot both count as decimals.
 *
 * @param string $text Term name.
 * @return float|null
 */
function nhf_parse_numeric_value( $text ) {
	$text = trim( wp_strip_all_tags( (string) $text ) );
	if ( '' === $text ) {
		return null;
	}

	if ( ! preg_match( '/[-+]?\d+(?:[.,]\d+)?/', $text, $match ) ) {
		return null;
	}

	$raw = str_replace( ',', '.', $match[0] );
	if ( ! is_numeric( $raw ) ) {
		return null;
	}

	return (float) $raw;
}

/**
 * Whether this attribute uses a from–to range in the catalog.
 *
 * @param object $attr Attribute taxonomy row from WooCommerce.
 */
function nhf_attribute_uses_range( $attr ) : bool {
	return is_object( $attr )
		&& isset( $attr->attribute_orderby )
		&& 'name_num' === $attr->attribute_orderby;
}

/**
 * Sort terms by numeric value. Empty when any name cannot be parsed.
 *
 * @param WP_Term[] $terms Attribute terms.
 * @return array<int, array{term:WP_Term,value:float}>
 */
function nhf_numeric_terms( $terms ) {
	if ( ! is_array( $terms ) || count( $terms ) < 2 ) {
		return array();
	}

	$rows = array();
	foreach ( $terms as $term ) {
		if ( ! is_object( $term ) || ! isset( $term->name, $term->slug ) ) {
			return array();
		}
		$value = nhf_parse_numeric_value( $term->name );
		if ( null === $value ) {
			return array();
		}
		$rows[] = array(
			'term'  => $term,
			'value' => $value,
		);
	}

	usort(
		$rows,
		static function ( $a, $b ) {
			if ( $a['value'] === $b['value'] ) {
				return strnatcasecmp( (string) $a['term']->name, (string) $b['term']->name );
			}
			return $a['value'] <=> $b['value'];
		}
	);

	return $rows;
}

/**
 * Indexes covered by the current selection. Full span when nothing is selected.
 *
 * @param array<int, array{term:WP_Term,value:float}> $rows
 * @param string[]                                    $selected Slugs.
 * @return array{0:int,1:int,2:bool} From index, to index, whether the range is active.
 */
function nhf_range_selection( array $rows, array $selected ) : array {
	$last = count( $rows ) - 1;
	if ( $last < 1 ) {
		return array( 0, 0, false );
	}

	if ( empty( $selected ) ) {
		return array( 0, $last, false );
	}

	$hits = array();
	foreach ( $rows as $index => $row ) {
		if ( in_array( $row['term']->slug, $selected, true ) ) {
			$hits[] = $index;
		}
	}

	if ( empty( $hits ) ) {
		return array( 0, $last, false );
	}

	$from   = min( $hits );
	$to     = max( $hits );
	$active = ( 0 !== $from || $to !== $last || count( $hits ) !== count( $rows ) );

	return array( $from, $to, $active );
}

/**
 * How many checkbox values stay visible before "Show more".
 *
 * @return int
 */
function nhf_checkbox_preview_count() {
	return 6;
}

/**
 * Split terms into the first visible rows and the rest.
 *
 * @param array $terms Term objects.
 * @return array{0:array,1:array}
 */
function nhf_split_checkbox_terms( $terms ) {
	if ( ! is_array( $terms ) ) {
		return array( array(), array() );
	}

	$preview = nhf_checkbox_preview_count();
	return array(
		array_slice( $terms, 0, $preview ),
		array_slice( $terms, $preview ),
	);
}

/**
 * Whether any of these terms is already selected.
 *
 * @param array    $terms    Term objects.
 * @param string[] $selected Slugs.
 */
function nhf_terms_have_selection( $terms, array $selected ) : bool {
	if ( empty( $terms ) || empty( $selected ) ) {
		return false;
	}

	foreach ( $terms as $term ) {
		if ( is_object( $term ) && isset( $term->slug ) && in_array( $term->slug, $selected, true ) ) {
			return true;
		}
	}

	return false;
}
