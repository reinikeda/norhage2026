<?php
/**
 * Single product summary order helpers.
 *
 * Astra prints the short description from its own structure list
 * (short_desc), not from WooCommerce's priority 20 hook. Removing only
 * that hook leaves the original block under the price and delivery line.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drop the short description from Astra's single-product structure.
 *
 * The description is printed once, after the bundle box.
 *
 * @param mixed $structure Astra structure keys, or whatever a later filter returned.
 * @return mixed
 */
function nh_strip_short_desc_from_astra_structure( $structure ) {
	if ( ! is_array( $structure ) ) {
		return $structure;
	}

	$kept = array();
	foreach ( $structure as $part ) {
		if ( 'short_desc' === $part ) {
			continue;
		}
		$kept[] = $part;
	}

	return $kept;
}
