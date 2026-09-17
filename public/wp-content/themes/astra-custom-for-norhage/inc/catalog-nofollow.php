<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Woo/Astra mark catalog add-to-cart controls as rel="nofollow".
 * Those links now go to the product page, so nofollow is leftover
 * and site audits flag it as blocked internal linking.
 *
 * @param string $html
 * @return string
 */
function nh_strip_rel_nofollow( $html ) {
	if ( ! is_string( $html ) || $html === '' ) {
		return $html;
	}

	return str_replace(
		array( ' rel="nofollow"', " rel='nofollow'", ' rel=nofollow' ),
		'',
		$html
	);
}

add_filter( 'woocommerce_loop_add_to_cart_link', 'nh_strip_rel_nofollow', 9999 );

add_filter( 'woocommerce_loop_add_to_cart_args', function( $args ) {
	if ( isset( $args['attributes']['rel'] ) && $args['attributes']['rel'] === 'nofollow' ) {
		unset( $args['attributes']['rel'] );
	}
	return $args;
}, 99 );

add_filter( 'astra_addon_shop_cards_buttons_html', 'nh_strip_rel_nofollow', 20 );
