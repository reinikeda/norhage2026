<?php
/**
 * Kustom Checkout template override.
 *
 * Uses the same Woo form as classic checkout so customers fill details first.
 * kco_wc_show_snippet() still runs in the payment section (required by KCO).
 *
 * @package astra-custom-for-norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $checkout ) ) {
	$checkout = WC()->checkout();
}

include get_stylesheet_directory() . '/woocommerce/checkout/form-checkout.php';
