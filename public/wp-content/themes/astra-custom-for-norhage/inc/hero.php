<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** ===== Hero title resolver (used by your header template) ===== */
if ( ! function_exists( 'nhhb_get_hero_title' ) ) {
	function nhhb_get_hero_title() {
		if ( is_front_page() ) {
			return get_the_title( get_queried_object_id() );
		}
		if ( is_home() ) {
			$page_for_posts = (int) get_option( 'page_for_posts' );
			return $page_for_posts ? get_the_title( $page_for_posts ) : __( 'Blog', 'nh-theme' );
		}
		if ( is_singular() ) {
			return get_the_title();
		}
		if ( is_search() ) {
			return sprintf( __( 'Search results for “%s”', 'nh-theme' ), get_search_query() );
		}
		if ( is_archive() ) {
			return get_the_archive_title();
		}
		return get_bloginfo( 'name' );
	}
}

/** ===== Hero image resolver (kept minimal; your Customizer bits can live here later) ===== */
if ( ! function_exists( 'nhhb_get_hero_image_url' ) ) {
	function nhhb_get_hero_image_url() {
		// Singles: use that post’s featured image
		if ( is_singular() ) {
			$id  = get_queried_object_id();
			$url = get_the_post_thumbnail_url( $id, 'full' );
			if ( $url ) return $url;
		}
		return '';
	}
}
