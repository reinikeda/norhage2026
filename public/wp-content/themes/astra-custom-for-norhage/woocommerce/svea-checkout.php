<?php
/**
 * Svea Checkout template override.
 *
 * Uses the same Woo form as classic checkout so customers fill details first.
 * The Svea iframe is printed in the payment section after the method radios.
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

do_action( 'woocommerce_sco_after_checkout_page' );
