<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** ===== Blog top category nav (archives only) ===== */
add_action( 'astra_primary_content_top', function () {
	if ( is_home() || is_category() ) {
		$current_cat_id = get_queried_object_id();
		echo '<div class="blog-category-nav"><ul>';

		$all_class  = is_home() ? 'active' : '';
		$posts_page = (int) get_option( 'page_for_posts' );
		echo '<li><a class="' . esc_attr( $all_class ) . '" href="' . esc_url( $posts_page ? get_permalink( $posts_page ) : home_url('/') ) . '">' . esc_html__( 'All', 'nh-theme' ) . '</a></li>';

		foreach ( get_categories( array( 'orderby' => 'name', 'hide_empty' => true ) ) as $cat ) {
			$active = ( $current_cat_id === $cat->term_id ) ? 'active' : '';
			echo '<li><a class="' . esc_attr( $active ) . '" href="' . esc_url( get_category_link( $cat->term_id ) ) . '">' . esc_html( $cat->name ) . '</a></li>';
		}

		echo '</ul></div>';
	}
}, 10 );

/** ===== Blog archive hero titles ===== */
add_filter( 'get_the_archive_title', function( $title ){
	if ( is_home() )             return __( 'Blog', 'nh-theme' );
	if ( is_category() )         return sprintf( __( 'Blog: %s', 'nh-theme' ), single_cat_title( '', false ) );
	if ( is_tag() )              return sprintf( __( 'Blog: %s', 'nh-theme' ), single_tag_title( '', false ) );
	if ( is_author() )           return sprintf( __( 'Blog: %s', 'nh-theme' ), get_the_author() );
	if ( is_year() )             return sprintf( __( 'Blog: %s', 'nh-theme' ), get_the_date( 'Y' ) );
	if ( is_month() )            return sprintf( __( 'Blog: %s', 'nh-theme' ), get_the_date( 'F Y' ) );
	if ( is_day() )              return sprintf( __( 'Blog: %s', 'nh-theme' ), get_the_date( get_option('date_format') ) );
	return $title;
}, 20 );

/** Remove Astra duplicate white header on blog archives */
add_action( 'wp', function () {
	if ( is_home() || is_category() || is_tag() || is_author() || is_date() ) {
		remove_action( 'astra_archive_header', 'astra_archive_page_title' );
		remove_action( 'astra_archive_header', 'astra_archive_description' );
	}
} );

/** ===== Blog meta: hide author (archive + single) ===== */
add_filter( 'astra_post_meta', function( $meta ){
	if ( is_home() || is_category() || is_tag() || is_author() || is_date() || is_singular('post') ) {
		$meta = array_diff( (array) $meta, array( 'author' ) );
	}
	return array_values( (array) $meta );
}, 25 );

add_filter( 'astra_single_post_meta', function( $markup ){
	if ( is_singular( 'post' ) ) {
		// remove the author chunk (Astra prints "posted-by")
		$markup = preg_replace( '/<span[^>]*class="[^"]*posted-by[^"]*"[^>]*>.*?<\/span>\s*/i', '', (string) $markup );
	}
	return $markup;
}, 25 );

/** ===== Blog archive: change "Read Post »" → "Read More »" ===== */
add_filter( 'astra_post_read_more', function( $label ){
	if ( is_home() || is_category() || is_tag() || is_author() || is_date() ) {
		return __( 'Read More »', 'nh-theme' );
	}
	return $label;
}, 10);

/* Fallback if any template prints the raw string */
add_filter( 'gettext', function( $translated, $text, $domain ){
	if ( ( is_home() || is_category() || is_tag() || is_author() || is_date() ) && $text === 'Read Post »' ) {
		return __( 'Read More »', 'nh-theme' );
	}
	return $translated;
}, 10, 3 );

/** ===== Blog single: back link + related posts ===== */
add_action( 'astra_primary_content_top', function () {
	if ( ! is_singular( 'post' ) ) return;

	$posts_page_id = (int) get_option( 'page_for_posts' );
	$back_url  = $posts_page_id ? get_permalink( $posts_page_id ) : home_url( '/' );
	$back_text = __( 'Back to Blog', 'nh-theme' );

	$cats = get_the_category();
	if ( ! empty( $cats ) ) {
		$cat = $cats[0];
		$back_url  = get_category_link( $cat->term_id );
		$back_text = sprintf( __( 'Back to %s', 'nh-theme' ), $cat->name );
	}
	echo '<nav class="nh-backbar"><a class="nh-backbar__link" href="' . esc_url( $back_url ) . '">← ' . esc_html( $back_text ) . '</a></nav>';
}, 5 );

add_action( 'astra_primary_content_bottom', function () {
	if ( ! is_singular( 'post' ) ) return;
	global $post;

	$cats = wp_get_post_categories( $post->ID, array( 'fields' => 'ids' ) );
	$args = array(
		'post_type'           => 'post',
		'posts_per_page'      => 3,
		'post__not_in'        => array( $post->ID ),
		'ignore_sticky_posts' => true,
	);
	if ( ! empty( $cats ) ) $args['category__in'] = $cats;

	$q = new WP_Query( $args );
	if ( $q->have_posts() ) {
		echo '<section class="nh-related"><h3>' . esc_html__( 'Related posts', 'nh-theme' ) . '</h3><div class="nh-related__grid">';
		while ( $q->have_posts() ) { $q->the_post();
			echo '<article class="nh-related__item"><a class="nh-related__thumb" href="' . esc_url( get_permalink() ) . '">';
			if ( has_post_thumbnail() ) the_post_thumbnail( 'medium' );
			echo '</a><h4 class="nh-related__title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h4></article>';
		}
		echo '</div></section>';
		wp_reset_postdata();
	}
}, 20 );
