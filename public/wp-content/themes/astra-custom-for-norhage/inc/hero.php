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
			$url = get_the_post_thumbnail_url( $id, '1536x1536' );
			if ( ! $url ) {
				$url = get_the_post_thumbnail_url( $id, 'large' );
			}

			if ( $url ) {
				return $url;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'nhhb_get_hero_fallback_image_url' ) ) {
	/**
	 * Theme-bundled page-hero image when the page has no featured image.
	 *
	 * @return string
	 */
	function nhhb_get_hero_fallback_image_url() {
		$dir = get_stylesheet_directory();
		$uri = trailingslashit( get_stylesheet_directory_uri() );
		$rel = array(
			'assets/images/hero-fallback.webp',
			'assets/images/hero-fallback.jpg',
			'assets/images/header-1920.jpg',
		);

		foreach ( $rel as $path ) {
			if ( file_exists( $dir . '/' . $path ) ) {
				return $uri . $path;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'nhhb_get_home_hero_slides' ) ) {
	function nhhb_get_home_hero_slides() {
		$fallback      = trailingslashit( get_stylesheet_directory_uri() ) . 'assets/images/header-1920.jpg';
		$front_page_id = (int) get_option( 'page_on_front' );
		$rows   = $front_page_id ? get_post_meta( $front_page_id, '_nh_home_hero_slides', true ) : [];
		$slides = [];

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$image_id = isset( $row['image_id'] ) ? absint( $row['image_id'] ) : 0;
				$image    = '';
				$srcset   = '';
				if ( $image_id ) {
					$image = wp_get_attachment_image_url( $image_id, '1536x1536' );
					if ( ! $image ) {
						$image = wp_get_attachment_image_url( $image_id, 'large' );
					}
					$srcset = (string) wp_get_attachment_image_srcset( $image_id, '1536x1536' );
				}
				$title = isset( $row['title'] ) ? trim( (string) $row['title'] ) : '';
				$text  = isset( $row['text'] ) ? trim( (string) $row['text'] ) : '';

				if ( $image === '' && $title === '' && $text === '' ) {
					continue;
				}

				$slides[] = [
					'image_id' => $image_id,
					'image'    => $image ? $image : $fallback,
					'srcset'   => $srcset,
					'title'    => $title,
					'text'     => $text,
				];
			}
		}

		if ( empty( $slides ) ) {
			$slides[] = [
				'image_id' => 0,
				'image'    => $fallback,
				'srcset'   => '',
				'title'    => nhhb_get_hero_title(),
				'text'     => '',
			];
		}

		return array_slice( $slides, 0, 5 );
	}
}

/**
 * Autoptimize on .eu turns CSS background-image into an empty SVG + data-bg.
 * Exclude hero media so the page header image and title stay visible.
 *
 * @param string $exclude Comma-separated class/filename excludes.
 * @return string
 */
function nhhb_autoptimize_skip_hero_lazyload( $exclude ) {
	$exclude = is_string( $exclude ) ? $exclude : '';
	$skip    = 'nhhb-hero, nhhb-hero__media, nhhb-hero-slide, skip-lazy, no-lazyload';
	return $exclude === '' ? $skip : $exclude . ', ' . $skip;
}
add_filter( 'autoptimize_filter_imgopt_lazyload_exclude', 'nhhb_autoptimize_skip_hero_lazyload' );
add_filter( 'autoptimize_extra_filter_imagelazyload_exclude', 'nhhb_autoptimize_skip_hero_lazyload' );
