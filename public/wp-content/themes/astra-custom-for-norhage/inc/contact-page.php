<?php
/**
 * Contact page template selection.
 *
 * The hero already prints the H1. This does not add another one.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/contact-structure.php';

add_filter( 'template_include', 'nh_contact_template_include' );
add_filter( 'body_class', 'nh_contact_body_class' );
add_filter( 'wpseo_schema_webpage', 'nh_contact_schema_webpage', 20 );

/**
 * @return bool
 */
function nh_contact_is_contact_page() {
	if ( ! is_page() ) {
		return false;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return false;
	}
	return in_array( (string) $post->post_name, nh_contact_page_slugs(), true );
}

/**
 * Company and address already used in the footer. Phone and email stay in the page body.
 *
 * @return array<string,string>
 */
function nh_contact_place_details() {
	return array(
		'company_label' => __( 'Company:', 'nh-theme' ),
		'company'       => __( 'Tehi UG', 'nh-theme' ),
		'address_label' => __( 'Address:', 'nh-theme' ),
		'address'       => __( 'Adolfstraße 1, Wiesbaden, 65185 HE, Germany', 'nh-theme' ),
	);
}

/**
 * @param string $template Current template.
 * @return string
 */
function nh_contact_template_include( $template ) {
	if ( ! nh_contact_is_contact_page() ) {
		return $template;
	}
	$custom = get_stylesheet_directory() . '/page-contact.php';
	return is_readable( $custom ) ? $custom : $template;
}

/**
 * @param string[] $classes Body classes.
 * @return string[]
 */
function nh_contact_body_class( $classes ) {
	if ( nh_contact_is_contact_page() ) {
		$classes[] = 'nh-contact-page';
	}
	return $classes;
}

/**
 * The contact screen is a ContactPage, not a generic article.
 *
 * @param array<string,mixed> $data Webpage schema.
 * @return array<string,mixed>
 */
function nh_contact_schema_webpage( $data ) {
	if ( ! is_array( $data ) || ! nh_contact_is_contact_page() ) {
		return $data;
	}
	$data['@type'] = 'ContactPage';
	return $data;
}
