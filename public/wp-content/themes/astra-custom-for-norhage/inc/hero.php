<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'nhhb_get_hero_title' ) ) {
	function nhhb_get_hero_title() {
		if ( is_front_page() ) {
			$front_id = (int) get_option( 'page_on_front' );

			if ( ! $front_id ) {
				$front_id = get_queried_object_id();
			}

			$title = $front_id ? get_the_title( $front_id ) : '';

			return $title ? $title : get_bloginfo( 'name' );
		}

		if ( is_home() ) {
			$page_for_posts = (int) get_option( 'page_for_posts' );
			return $page_for_posts ? get_the_title( $page_for_posts ) : __( 'Blog', 'nh-theme' );
		}

		if ( is_singular() ) {
			$title = get_the_title();
			return $title ? $title : get_bloginfo( 'name' );
		}

		if ( is_search() ) {
			return sprintf( __( 'Search results for “%s”', 'nh-theme' ), get_search_query() );
		}

		if ( is_archive() ) {
			$title = get_the_archive_title();
			return $title ? $title : get_bloginfo( 'name' );
		}

		return get_bloginfo( 'name' );
	}
}

if ( ! function_exists( 'nhhb_get_hero_image_url' ) ) {
	function nhhb_get_hero_image_url() {
		if ( is_singular() ) {
			$id  = get_queried_object_id();
			$url = get_the_post_thumbnail_url( $id, 'full' );

			if ( $url ) {
				return $url;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'nhhb_get_home_hero_slides' ) ) {
	function nhhb_get_home_hero_slides() {
		$fallback      = trailingslashit( get_stylesheet_directory_uri() ) . 'assets/images/header-1920.jpg';
		$front_page_id = (int) get_option( 'page_on_front' );
		$rows          = $front_page_id ? get_post_meta( $front_page_id, '_nh_home_hero_slides', true ) : [];
		$slides        = [];

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$image_id = isset( $row['image_id'] ) ? absint( $row['image_id'] ) : 0;
				$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
				$title    = isset( $row['title'] ) ? trim( (string) $row['title'] ) : '';
				$text     = isset( $row['text'] ) ? trim( (string) $row['text'] ) : '';

				if ( $image === '' && $title === '' && $text === '' ) {
					continue;
				}

				$slides[] = [
					'image' => $image ? $image : $fallback,
					'title' => $title,
					'text'  => $text,
				];
			}
		}

		if ( empty( $slides ) ) {
			$slides[] = [
				'image' => $fallback,
				'title' => nhhb_get_hero_title(),
				'text'  => '',
			];
		}

		return array_slice( $slides, 0, 5 );
	}
}
