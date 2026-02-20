<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Copy custom-cut POST fields into cart item so the calculator can read them.
 * Supports BOTH:
 * - nh_custom_cutting (theme)
 * - nh_custom_mode (legacy)
 *
 * Also supports height posted as:
 * - nh_length_mm (current expected)
 * - nh_height_mm (alternate used in some theme versions)
 */
add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id, $variation_id ){

	// Dimension keys (shared)
	$w_key = NHGP_Custom_Cut::WIDTH_KEY;   // nh_width_mm
	$h_key = NHGP_Custom_Cut::HEIGHT_KEY;  // nh_length_mm

	// Flags
	$new_flag = NHGP_Custom_Cut::FLAG_KEY;         // nh_custom_cutting
	$old_flag = NHGP_Custom_Cut::LEGACY_FLAG_KEY;  // nh_custom_mode

	// Width
	if ( isset( $_POST[ $w_key ] ) ) {
		$cart_item_data[ $w_key ] = (float) wc_clean( wp_unslash( $_POST[ $w_key ] ) );
	}

	// Height (primary key)
	if ( isset( $_POST[ $h_key ] ) ) {
		$cart_item_data[ $h_key ] = (float) wc_clean( wp_unslash( $_POST[ $h_key ] ) );
	}

	// Height (ALT key support): nh_height_mm -> store into nh_length_mm
	if ( ! isset( $_POST[ $h_key ] ) && isset( $_POST['nh_height_mm'] ) ) {
		$cart_item_data[ $h_key ] = (float) wc_clean( wp_unslash( $_POST['nh_height_mm'] ) );
	}

	// New custom-cut flag
	if ( isset( $_POST[ $new_flag ] ) ) {
		$cart_item_data[ $new_flag ] = (int) wc_clean( wp_unslash( $_POST[ $new_flag ] ) );
	}

	// Legacy flag (optional)
	if ( isset( $_POST[ $old_flag ] ) ) {
		$cart_item_data[ $old_flag ] = (int) wc_clean( wp_unslash( $_POST[ $old_flag ] ) );
	}

	return $cart_item_data;
}, 10, 3 );