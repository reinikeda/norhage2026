<?php
/**
 * About page template selection.
 *
 * The hero already prints the H1. This does not add another one.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/about-structure.php';

add_filter( 'template_include', 'nh_about_template_include' );
add_filter( 'body_class', 'nh_about_body_class' );
add_filter( 'wpseo_schema_webpage', 'nh_about_schema_webpage', 20 );

/**
 * @return bool
 */
function nh_about_is_about_page() {
	if ( ! is_page() ) {
		return false;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return false;
	}
	return in_array( (string) $post->post_name, nh_about_page_slugs(), true );
}

/**
 * @param string $template Current template.
 * @return string
 */
function nh_about_template_include( $template ) {
	if ( ! nh_about_is_about_page() ) {
		return $template;
	}
	$custom = get_stylesheet_directory() . '/page-about.php';
	return is_readable( $custom ) ? $custom : $template;
}

/**
 * @param string[] $classes Body classes.
 * @return string[]
 */
function nh_about_body_class( $classes ) {
	if ( nh_about_is_about_page() ) {
		$classes[] = 'nh-about-page';
	}
	return $classes;
}

/**
 * The about screen is an AboutPage, not a generic article.
 *
 * @param array<string,mixed> $data Webpage schema.
 * @return array<string,mixed>
 */
function nh_about_schema_webpage( $data ) {
	if ( ! is_array( $data ) || ! nh_about_is_about_page() ) {
		return $data;
	}
	$data['@type'] = 'AboutPage';
	return $data;
}
