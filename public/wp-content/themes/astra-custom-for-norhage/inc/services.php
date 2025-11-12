<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** ===== Services CPT (with localized slug) ===== */
if ( ! function_exists( 'nh_get_services_slug' ) ) {
	function nh_get_services_slug(): string {
		if ( defined('NH_SERVICES_SLUG') && NH_SERVICES_SLUG ) {
			return sanitize_title( NH_SERVICES_SLUG );
		}
		$locale = function_exists('determine_locale') ? determine_locale() : get_locale();
		switch ( $locale ) {
			case 'lt_LT': return 'paslaugos';
			case 'nb_NO': return 'tjenester';
			case 'sv_SE': return 'tjanster';
			case 'de_DE': return 'leistungen';
			case 'fi_FI': return 'palvelut';
			default:      return 'services';
		}
	}
}

add_action( 'init', function () {
	$labels = array(
		'name'          => _x( 'Services', 'Post Type General Name', 'nh-theme' ),
		'singular_name' => _x( 'Service', 'Post Type Singular Name', 'nh-theme' ),
		'menu_name'     => __( 'Services', 'nh-theme' ),
		'add_new_item'  => __( 'Add New Service', 'nh-theme' ),
		'edit_item'     => __( 'Edit Service', 'nh-theme' ),
		'view_item'     => __( 'View Service', 'nh-theme' ),
		'all_items'     => __( 'All Services', 'nh-theme' ),
	);
	$slug = apply_filters( 'nh/services_slug', nh_get_services_slug() );
	register_post_type( 'service', array(
		'labels'       => $labels,
		'public'       => true,
		'show_in_rest' => true,
		'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'menu_icon'    => 'dashicons-admin-tools',
		'has_archive'  => $slug,
		'rewrite'      => array( 'slug' => $slug, 'with_front' => false ),
	) );
} );

/** Flush rewrites on theme switch (kept close to CPT) */
add_action( 'after_switch_theme', function(){ flush_rewrite_rules(); } );

/** ===== Services archive title + remove Astra duplicate header ===== */
add_filter( 'get_the_archive_title', function( $title ){
	if ( is_post_type_archive( 'service' ) ) {
		$pt = get_post_type_object( 'service' );
		return $pt && ! empty( $pt->labels->name ) ? esc_html( $pt->labels->name ) : __( 'Services', 'nh-theme' );
	}
	return $title;
}, 20 );

add_action( 'wp', function () {
	if ( is_post_type_archive( 'service' ) ) {
		remove_action( 'astra_archive_header', 'astra_archive_page_title' );
		remove_action( 'astra_archive_header', 'astra_archive_description' );
	}
} );

/** ===== Services meta: hide author + date everywhere (archive + single) ===== */
add_filter( 'astra_post_meta', function( $meta ){
	if ( is_post_type_archive( 'service' ) || is_singular( 'service' ) ) {
		return array(); // remove all meta items (author, date, etc.)
	}
	return $meta;
}, 25 );

add_filter( 'astra_single_post_meta', function( $markup ){
	if ( is_singular( 'service' ) ) {
		return ''; // remove meta block on single service
	}
	return $markup;
}, 25 );

/** ===== Services archive: change "Read Post »" to "Read More »" ===== */
add_filter( 'astra_post_read_more', function( $label ){
	if ( is_post_type_archive( 'service' ) ) {
		return __( 'Read More »', 'nh-theme' );
	}
	return $label;
}, 10 );

/* Fallback in case the theme prints the string directly */
add_filter( 'gettext', function( $translated, $text, $domain ){
	if ( ( is_post_type_archive( 'service' ) || is_singular( 'service' ) ) && $text === 'Read Post »' ) {
		return __( 'Read More »', 'nh-theme' );
	}
	return $translated;
}, 10, 3 );

/** ===== Services single: back link + related ===== */
add_action( 'astra_primary_content_top', function () {
	if ( ! is_singular( 'service' ) ) return;
	if ( $url = get_post_type_archive_link( 'service' ) ) {
		echo '<nav class="nh-backbar"><a class="nh-backbar__link" href="' . esc_url( $url ) . '">← ' . esc_html__( 'Back to Services', 'nh-theme' ) . '</a></nav>';
	}
}, 5 );

add_action( 'astra_primary_content_bottom', function () {
	if ( ! is_singular( 'service' ) ) return;
	global $post;
	$q = new WP_Query( array(
		'post_type'      => 'service',
		'posts_per_page' => 3,
		'post__not_in'   => array( $post->ID ),
	) );
	if ( $q->have_posts() ) {
		echo '<section class="nh-related"><h3>' . esc_html__( 'Other services', 'nh-theme' ) . '</h3><div class="nh-related__grid">';
		while ( $q->have_posts() ) { $q->the_post();
			echo '<article class="nh-related__item"><a class="nh-related__thumb" href="' . esc_url( get_permalink() ) . '">';
			if ( has_post_thumbnail() ) the_post_thumbnail( 'medium' );
			echo '</a><h4 class="nh-related__title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h4></article>';
		}
		echo '</div></section>';
		wp_reset_postdata();
	}
}, 20 );
