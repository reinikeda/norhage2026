<?php
/**
 * Svea Checkout template override.
 *
 * Uses the same classic checkout shell. The Svea iframe loads when Svea is the chosen method.
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
