<?php
/**
 * Kustom Checkout template override.
 *
 * Uses the same classic checkout shell. The Kustom iframe loads when Kustom is the chosen method.
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
