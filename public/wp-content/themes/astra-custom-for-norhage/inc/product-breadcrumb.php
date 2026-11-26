<?php
/**
 * Product-page breadcrumb markup.
 *
 * Shop and category breadcrumbs stay on WooCommerce's default template.
 * On a phone, a long product trail collapses to the first category,
 * an ellipsis, and the product name. Every crumb stays in the HTML.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hard cap — pathological trails (or a near-OOM request) must not build
 * unbounded HTML. Normal product crumbs are Home + cats + product (≤ ~8).
 */
define( 'NH_PRODUCT_BREADCRUMB_MAX_CRUMBS', 12 );

/**
 * Normalize one Woo crumb to a short scalar pair.
 *
 * @param mixed $crumb Raw crumb.
 * @return array{0: string, 1: string}
 */
function nh_product_breadcrumb_normalize_crumb( $crumb ) {
	$label = '';
	$url   = '';

	if ( is_array( $crumb ) ) {
		if ( isset( $crumb[0] ) && is_scalar( $crumb[0] ) ) {
			$label = (string) $crumb[0];
		}
		if ( isset( $crumb[1] ) && is_scalar( $crumb[1] ) ) {
			$url = (string) $crumb[1];
		}
	}

	// Guard against a single enormous title blowing memory during concat.
	if ( strlen( $label ) > 200 ) {
		$label = substr( $label, 0, 200 );
	}
	if ( strlen( $url ) > 2048 ) {
		$url = substr( $url, 0, 2048 );
	}

	return array( $label, $url );
}

/**
 * Decide which crumbs stay visible on small screens.
 *
 * Four or more crumbs collapse to: first category / … / current page.
 * Shorter trails stay intact and ellipsize only the current page.
 *
 * @param array<int, array{0: string, 1?: string}> $breadcrumb Crumbs from WooCommerce.
 * @return array{count: int, truncate: bool, segments: array<int, array<string, mixed>>}
 */
function nh_product_breadcrumb_plan( array $breadcrumb ) {
	if ( count( $breadcrumb ) > NH_PRODUCT_BREADCRUMB_MAX_CRUMBS ) {
		$breadcrumb = array_slice( $breadcrumb, 0, NH_PRODUCT_BREADCRUMB_MAX_CRUMBS );
	}

	$count    = count( $breadcrumb );
	$truncate = $count >= 4;
	$segments = array();

	foreach ( $breadcrumb as $key => $crumb ) {
		$crumb   = nh_product_breadcrumb_normalize_crumb( $crumb );
		$is_last = ( $key === $count - 1 );
		$keep    = ! $truncate || 1 === $key || $is_last;

		$segments[] = array(
			'type'      => 'item',
			'collapsed' => ! $keep,
			'current'   => $is_last,
			'label'     => $crumb[0],
			'url'       => $crumb[1],
		);

		if ( $is_last ) {
			continue;
		}

		$segments[] = array(
			'type'      => 'sep',
			'collapsed' => $truncate,
		);

		if ( $truncate && 1 === $key ) {
			$segments[] = array(
				'type' => 'short',
			);
		}
	}

	return array(
		'count'    => $count,
		'truncate' => $truncate,
		'segments' => $segments,
	);
}

/**
 * Render the single-product breadcrumb.
 *
 * @param array<int, array{0: string, 1?: string}> $breadcrumb Crumbs from WooCommerce.
 * @return string Escaped HTML, or an empty string when there is nothing to show.
 */
function nh_product_breadcrumb_html( array $breadcrumb ) {
	if ( empty( $breadcrumb ) ) {
		return '';
	}

	$plan = nh_product_breadcrumb_plan( $breadcrumb );
	$html = '<nav class="woocommerce-breadcrumb nh-bc" aria-label="' . esc_attr__( 'Breadcrumb', 'woocommerce' ) . '" data-count="' . (int) $plan['count'] . '"';

	if ( $plan['truncate'] ) {
		$html .= ' data-nh-truncate="1"';
	}

	$html .= '>';

	foreach ( $plan['segments'] as $segment ) {
		if ( 'short' === $segment['type'] ) {
			$html .= '<span class="nh-bc-short" aria-hidden="true"><span class="nh-bc-sep"> / </span>…<span class="nh-bc-sep"> / </span></span>';
			continue;
		}

		if ( 'sep' === $segment['type'] ) {
			$class = 'nh-bc-sep' . ( ! empty( $segment['collapsed'] ) ? ' is-collapsed' : '' );
			$html .= '<span class="' . esc_attr( $class ) . '" aria-hidden="true"> / </span>';
			continue;
		}

		$class = 'nh-bc-item';
		if ( ! empty( $segment['current'] ) ) {
			$class .= ' is-current';
		}
		if ( ! empty( $segment['collapsed'] ) ) {
			$class .= ' is-collapsed';
		}

		$html .= '<span class="' . esc_attr( $class ) . '">';

		$label  = esc_html( $segment['label'] );
		$linked = '' !== $segment['url'] && empty( $segment['current'] );

		if ( $linked ) {
			$html .= '<a href="' . esc_url( $segment['url'] ) . '">' . $label . '</a>';
		} else {
			$html .= $label;
		}

		$html .= '</span>';
	}

	$html .= '</nav>';

	return $html;
}
