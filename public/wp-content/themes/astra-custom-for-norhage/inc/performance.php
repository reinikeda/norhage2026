<?php
/**
 * Front-end performance helpers for the seven live Norhage shops.
 *
 * Goals: less CSS/JS on first mobile paint, faster LCP, fewer unused Woo assets.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared enqueue args: footer + defer (WP 6.3+).
 *
 * @return array
 */
function norhage_script_args() {
	return array(
		'in_footer' => true,
		'strategy'  => 'defer',
	);
}

/**
 * Decode images asynchronously unless the caller set a value.
 *
 * @param array $attr
 * @return array
 */
function norhage_attachment_image_attrs( $attr ) {
	if ( empty( $attr['decoding'] ) ) {
		$attr['decoding'] = 'async';
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'norhage_attachment_image_attrs' );

/**
 * Drop Woo gallery scripts/styles on pages that never show the product gallery.
 * Live homepage currently loads photoswipe CSS/JS for no reason.
 */
function norhage_dequeue_unused_woo_assets() {
	if ( function_exists( 'is_product' ) && is_product() ) {
		return;
	}

	wp_dequeue_script( 'zoom' );
	wp_dequeue_script( 'flexslider' );
	wp_dequeue_script( 'photoswipe' );
	wp_dequeue_script( 'photoswipe-ui-default' );
	wp_dequeue_style( 'photoswipe' );
	wp_dequeue_style( 'photoswipe-default-skin' );
	wp_dequeue_style( 'photoswipe-default-skin-css' );
}
add_action( 'wp_enqueue_scripts', 'norhage_dequeue_unused_woo_assets', 99 );
