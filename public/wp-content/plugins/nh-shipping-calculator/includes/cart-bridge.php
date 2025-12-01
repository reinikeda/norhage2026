<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Copy custom-cut POST fields into cart item so the calculator can read them.
 * Keys are now hard-coded to match NHGP_Custom_Cut.
 */
add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id, $variation_id ){

	// Hard-coded meta keys used throughout plugin
	$w_key = NHGP_Custom_Cut::WIDTH_KEY;   // nh_width_mm
	$h_key = NHGP_Custom_Cut::HEIGHT_KEY;  // nh_length_mm
	$f_key = NHGP_Custom_Cut::FLAG_KEY;    // nh_custom_mode

	// Width
	if ( isset( $_POST[ $w_key ] ) ) {
		$cart_item_data[ $w_key ] = (float) wc_clean( wp_unslash( $_POST[ $w_key ] ) );
	}

	// Height
	if ( isset( $_POST[ $h_key ] ) ) {
		$cart_item_data[ $h_key ] = (float) wc_clean( wp_unslash( $_POST[ $h_key ] ) );
	}

	// Custom-cut flag
	if ( isset( $_POST[ $f_key ] ) ) {
		$cart_item_data[ $f_key ] = (int) wc_clean( wp_unslash( $_POST[ $f_key ] ) );
	}

	return $cart_item_data;
}, 10, 3 );
