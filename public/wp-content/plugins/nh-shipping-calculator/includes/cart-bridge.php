<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Copy custom-cut POST fields into cart item so the calculator can read them.
 * Keys are configurable in the Custom Cutting tab.
 */
add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id, $variation_id ){
	$cs = NHGP_Defaults::custom_get();
	$w_key = $cs['width_key'];   // e.g. nh_width_mm
	$h_key = $cs['height_key'];  // e.g. nh_length_mm
	$f_key = $cs['flag_key'];    // e.g. nh_custom_mode

	if ( isset( $_POST[ $w_key ] ) ) $cart_item_data[ $w_key ] = wc_clean( wp_unslash( $_POST[ $w_key ] ) );
	if ( isset( $_POST[ $h_key ] ) ) $cart_item_data[ $h_key ] = wc_clean( wp_unslash( $_POST[ $h_key ] ) );
	if ( isset( $_POST[ $f_key ] ) ) $cart_item_data[ $f_key ] = wc_clean( wp_unslash( $_POST[ $f_key ] ) );

	return $cart_item_data;
}, 10, 3 );
