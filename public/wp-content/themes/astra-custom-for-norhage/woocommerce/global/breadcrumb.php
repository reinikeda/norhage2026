<?php
/**
 * Shop breadcrumb
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/global/breadcrumb.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Norhage: product pages use a wrapped trail so a phone can show
 * “category / … / product” without removing crumbs from the HTML.
 * Every other view keeps WooCommerce’s markup.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     2.3.0
 * @see         woocommerce_breadcrumb()
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $breadcrumb ) ) {

	// Custom trail only for sane product crumbs. If the request is already
	// near the memory ceiling (e.g. heavy related-product work), fall back
	// to Woo's simple loop instead of building a second string buffer.
	$use_nh_trail = function_exists( 'is_product' )
		&& is_product()
		&& function_exists( 'nh_product_breadcrumb_html' )
		&& is_array( $breadcrumb )
		&& count( $breadcrumb ) > 0
		&& count( $breadcrumb ) <= 20;

	if ( $use_nh_trail && function_exists( 'memory_get_usage' ) && function_exists( 'ini_get' ) ) {
		$limit = ini_get( 'memory_limit' );
		if ( is_string( $limit ) && $limit !== '' && $limit !== '-1' ) {
			$unit  = strtolower( substr( $limit, -1 ) );
			$bytes = (int) $limit;
			if ( 'g' === $unit ) {
				$bytes = (int) $limit * 1024 * 1024 * 1024;
			} elseif ( 'm' === $unit ) {
				$bytes = (int) $limit * 1024 * 1024;
			} elseif ( 'k' === $unit ) {
				$bytes = (int) $limit * 1024;
			}
			// Leave headroom; OOM was previously reported on this echo.
			if ( $bytes > 0 && memory_get_usage( true ) > (int) ( $bytes * 0.85 ) ) {
				$use_nh_trail = false;
			}
		}
	}

	if ( $use_nh_trail ) {
		echo nh_product_breadcrumb_html( $breadcrumb ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	echo $wrap_before;

	foreach ( $breadcrumb as $key => $crumb ) {

		echo $before;

		if ( ! empty( $crumb[1] ) && sizeof( $breadcrumb ) !== $key + 1 ) {
			echo '<a href="' . esc_url( $crumb[1] ) . '">' . esc_html( $crumb[0] ) . '</a>';
		} else {
			echo esc_html( $crumb[0] );
		}

		echo $after;

		if ( sizeof( $breadcrumb ) !== $key + 1 ) {
			echo $delimiter;
		}
	}

	echo $wrap_after;

}
