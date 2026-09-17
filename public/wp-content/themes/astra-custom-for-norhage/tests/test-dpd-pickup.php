<?php
/**
 * CLI tests: DPD pickup markup stays out of the shipping <li> list.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-dpd-pickup.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return (string) $text;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		unset( $domain );
		return esc_html( $text );
	}
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		unset( $domain );
		return esc_attr( $text );
	}
}

require_once dirname( __DIR__ ) . '/inc/checkout-ux.php';

$failures = 0;
function nh_dpd_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "OK $label\n";
		return;
	}
	$failures++;
	fwrite( STDERR, "FAIL $label\n" );
}

nh_dpd_assert( 'parcels method', nh_checkout_dpd_is_parcels_method( 'dpd_parcels:7' ) );
nh_dpd_assert( 'sameday parcels method', nh_checkout_dpd_is_parcels_method( 'dpd_sameday_parcels:3' ) );
nh_dpd_assert( 'home delivery is not parcels', ! nh_checkout_dpd_is_parcels_method( 'dpd_home_delivery:4' ) );
nh_dpd_assert( 'parcels field name', nh_checkout_dpd_field_name( 'dpd_parcels:7' ) === 'wc_shipping_dpd_parcels_terminal' );
nh_dpd_assert( 'sameday field name', nh_checkout_dpd_field_name( 'dpd_sameday_parcels:3' ) === 'wc_shipping_dpd_sameday_parcels_terminal' );

$terminal = (object) array(
	'parcelshop_id' => 'LT100',
	'company'       => 'Narva MAXIMA',
	'street'        => 'Taikos pr. 1',
	'city'          => 'Klaipėda',
	'pcode'         => '91100',
	'cod'           => '1',
	'status'        => '1',
);
$html = nh_checkout_dpd_points_html( array( $terminal ), 'LT100' );
nh_dpd_assert( 'points use div not li', strpos( $html, '<li' ) === false && strpos( $html, '<div class="pudo is-selected"' ) !== false );
nh_dpd_assert( 'points include city group', strpos( $html, 'Klaipėda' ) !== false );

$pickup = nh_checkout_dpd_pickup_markup( 'dpd_parcels:7', 'LT100' );
nh_dpd_assert( 'pickup markup uses divs not lis', strpos( $pickup, '<li' ) === false && strpos( $pickup, '<ul' ) === false );
nh_dpd_assert( 'pickup markup keeps selected-option in flow', strpos( $pickup, 'selected-option' ) !== false && strpos( $pickup, 'nh-dpd-pickup' ) !== false );

$template = file_get_contents( dirname( __DIR__ ) . '/woocommerce/cart/cart-shipping.php' );
nh_dpd_assert( 'shipping template wraps radio and label', strpos( $template, 'nh-shipping-method-row' ) !== false );
nh_dpd_assert( 'shipping template prints DPD picker after the method li', strpos( $template, 'nh_checkout_dpd_pickup_under_method' ) !== false && strpos( $template, '</li>' ) !== false );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/checkout.css' );
nh_dpd_assert( 'checkout css stacks methods in a column', strpos( $css, 'flex-direction: column' ) !== false && strpos( $css, 'nh-shipping-method-row' ) !== false );
nh_dpd_assert( 'checkout css kills dpd height 100 collapse', strpos( $css, 'height: auto !important' ) !== false );
nh_dpd_assert( 'checkout css spaces sibling pickup block', strpos( $css, '> ul > .nh-dpd-pickup' ) !== false );

$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/checkout-ux.js' );
nh_dpd_assert( 'js stops dpd plugin from double-toggling the list', strpos( $js, 'stopImmediatePropagation' ) !== false );
nh_dpd_assert( 'js places pickup after the method row', strpos( $js, '$li.after($own)' ) !== false );

if ( $failures > 0 ) {
	exit( 1 );
}
echo "All tests passed.\n";
