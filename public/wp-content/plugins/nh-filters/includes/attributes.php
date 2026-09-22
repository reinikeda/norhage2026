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
