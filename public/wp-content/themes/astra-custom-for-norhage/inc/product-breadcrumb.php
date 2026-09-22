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
 * Decide which crumbs stay visible on small screens.
 *
 * Four or more crumbs collapse to: first category / … / current page.
 * Shorter trails stay intact and ellipsize only the current page.
 *
 * @param array<int, array{0: string, 1?: string}> $breadcrumb Crumbs from WooCommerce.
 * @return array{count: int, truncate: bool, segments: array<int, array<string, mixed>>}
 */
function nh_product_breadcrumb_plan( array $breadcrumb ) {
	$count    = count( $breadcrumb );
	$truncate = $count >= 4;
	$segments = array();

	foreach ( $breadcrumb as $key => $crumb ) {
		$is_last = ( $key === $count - 1 );
		$keep    = ! $truncate || 1 === $key || $is_last;

		$segments[] = array(
			'type'      => 'item',
			'collapsed' => ! $keep,
			'current'   => $is_last,
			'label'     => isset( $crumb[0] ) ? (string) $crumb[0] : '',
			'url'       => isset( $crumb[1] ) ? (string) $crumb[1] : '',
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
