<?php
/**
 * Blog helpers with no WordPress hooks.
 *
 * Author and date visibility come from Appearance → Customize → Blog → Meta.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace Astra's English Previous / Next labels.
 *
 * Single posts print string-previous-text and string-next-text.
 * Archive paging prints the same words inside the arrow strings.
 *
 * @param mixed  $strings  Astra default string map.
 * @param string $previous Translated previous label.
 * @param string $next     Translated next label.
 * @return mixed
 */
function nh_blog_navigation_strings( $strings, $previous, $next ) {
	if ( ! is_array( $strings ) ) {
		return $strings;
	}

	$previous = (string) $previous;
	$next     = (string) $next;

	$strings['string-previous-text'] = $previous;
	$strings['string-next-text']     = $next;

	if ( isset( $strings['string-blog-navigation-previous'] ) ) {
		$strings['string-blog-navigation-previous'] = '<span class="ast-left-arrow" aria-hidden="true">&larr;</span> ' . $previous;
	}
	if ( isset( $strings['string-blog-navigation-next'] ) ) {
		$strings['string-blog-navigation-next'] = $next . ' <span class="ast-right-arrow" aria-hidden="true">&rarr;</span>';
	}
	if ( isset( $strings['string-single-navigation-previous'] ) ) {
		$strings['string-single-navigation-previous'] = '<span class="ast-left-arrow" aria-hidden="true">&larr;</span> ' . $previous . ' %s';
	}
	if ( isset( $strings['string-single-navigation-next'] ) ) {
		$strings['string-single-navigation-next'] = $next . ' %s <span class="ast-right-arrow" aria-hidden="true">&rarr;</span>';
	}

	return $strings;
}
