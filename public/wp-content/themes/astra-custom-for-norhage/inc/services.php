<?php
/**
 * Services custom post type.
 *
 * Rendered by archive-service.php and single-service.php.
 * Author and date stay on blog posts; this post type does not print them.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/service-structure.php';
require_once __DIR__ . '/service-order.php';

/** ===== Services CPT (with localized slug) ===== */
if ( ! function_exists( 'nh_get_services_slug' ) ) {
	function nh_get_services_slug(): string {
		if ( defined( 'NH_SERVICES_SLUG' ) && NH_SERVICES_SLUG ) {
			return sanitize_title( NH_SERVICES_SLUG );
		}
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		switch ( $locale ) {
			case 'lt_LT':
				return 'paslaugos';
			case 'nb_NO':
				return 'tjenester';
			case 'sv_SE':
				return 'tjanster';
			case 'de_DE':
				return 'leistungen';
			case 'fi':
				return 'palvelut';
			case 'da_DK':
				return 'tjenester';
			default:
				return 'services';
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
		'hierarchical' => false,
		'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
		'menu_icon'    => 'dashicons-admin-tools',
		'has_archive'  => $slug,
		'rewrite'      => array( 'slug' => $slug, 'with_front' => false ),
	) );
} );

/** Flush rewrites on theme switch (kept close to CPT) */
add_action( 'after_switch_theme', function () {
	flush_rewrite_rules();
} );

/**
 * Archive label used as the H1 (via the page hero) and as the document title fallback.
 *
 * @return string
 */
function nh_service_archive_label() {
	$pt = get_post_type_object( 'service' );
	if ( $pt && ! empty( $pt->labels->name ) ) {
		return (string) $pt->labels->name;
	}
	return __( 'Services', 'nh-theme' );
}

/**
 * Stable landing-page order. Blog archives keep their own date order.
 *
 * @param WP_Query $query Query.
 */
function nh_service_archive_query( $query ) {
	if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
		return;
	}
	if ( ! $query->is_post_type_archive( 'service' ) ) {
		return;
	}

	$query->set( 'posts_per_page', 24 );
	$query->set( 'orderby', nh_service_orderby() );
}
add_action( 'pre_get_posts', 'nh_service_archive_query' );

/**
 * Use the service title when a featured image has no media-library alt.
 */
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
	if ( ! empty( $attr['alt'] ) ) {
		return $attr;
	}

	$post_id = get_the_ID();
	if ( ! $post_id || get_post_type( $post_id ) !== 'service' ) {
		return $attr;
	}

	$attachment_id = is_object( $attachment ) ? (int) $attachment->ID : (int) $attachment;
	if ( $attachment_id <= 0 || (int) get_post_thumbnail_id( $post_id ) !== $attachment_id ) {
		return $attr;
	}

	$title = get_the_title( $post_id );
	if ( $title !== '' ) {
		$attr['alt'] = $title;
	}

	return $attr;
}, 20, 2 );

/** ===== Services archive title + remove Astra duplicate header ===== */
add_filter( 'get_the_archive_title', function ( $title ) {
	if ( is_post_type_archive( 'service' ) ) {
		return nh_service_archive_label();
	}
	return $title;
}, 20 );

add_action( 'wp', function () {
	if ( is_post_type_archive( 'service' ) ) {
		remove_action( 'astra_archive_header', 'astra_archive_page_title' );
		remove_action( 'astra_archive_header', 'astra_archive_description' );
	}
} );

/**
 * True on a service screen or while the loop is rendering a service.
 *
 * Blog posts (`post`) are not included, so their dates stay.
 *
 * @return bool
 */
function nh_service_is_service_view() {
	if ( is_singular( 'service' ) || is_post_type_archive( 'service' ) ) {
		return true;
	}
	return nh_service_post_type_hides_meta( (string) get_post_type() );
}

/** ===== Services meta: hide author + date. Blog posts keep the date. ===== */
add_filter( 'astra_post_meta', function ( $meta ) {
	if ( nh_service_is_service_view() ) {
		return array();
	}
	return $meta;
}, 25 );

add_filter( 'astra_single_post_meta', function ( $markup ) {
	if ( nh_service_is_service_view() ) {
		return '';
	}
	return $markup;
}, 25 );

add_filter( 'astra_blog_post_meta', function ( $markup ) {
	if ( nh_service_is_service_view() ) {
		return '';
	}
	return $markup;
}, 25 );

add_filter( 'astra_post_date', function ( $output ) {
	if ( nh_service_post_type_hides_meta( (string) get_post_type() ) ) {
		return '';
	}
	return $output;
} );

add_filter( 'astra_post_author', function ( $output ) {
	if ( nh_service_post_type_hides_meta( (string) get_post_type() ) ) {
		return '';
	}
	return $output;
} );

/** ===== Services archive: change "Read Post »" to "Read More »" ===== */
add_filter( 'astra_post_read_more', function ( $label ) {
	if ( is_post_type_archive( 'service' ) ) {
		return __( 'Read More »', 'nh-theme' );
	}
	return $label;
}, 10 );

/* Fallback in case the theme prints the string directly */
add_filter( 'gettext', function ( $translated, $text, $domain ) {
	unset( $domain );
	if ( ( is_post_type_archive( 'service' ) || is_singular( 'service' ) ) && $text === 'Read Post »' ) {
		return __( 'Read More »', 'nh-theme' );
	}
	return $translated;
}, 10, 3 );

/**
 * Contact details already translated for the footer.
 *
 * @return array<string,string>
 */
function nh_service_contact_details() {
	$phone = __( '+49 176 65 10 6609', 'nh-theme' );
	$email = __( 'info@norhage.eu', 'nh-theme' );

	return array(
		'heading'      => __( 'Contact', 'nh-theme' ),
		'phone_label'  => __( 'Phone:', 'nh-theme' ),
		'phone'        => $phone,
		'phone_href'   => 'tel:' . preg_replace( '/[^\d+]/', '', $phone ),
		'email_label'  => __( 'Email:', 'nh-theme' ),
		'email'        => $email,
		'email_href'   => 'mailto:' . $email,
		'cta'          => __( 'Contact', 'nh-theme' ),
		'cta_href'     => nh_service_contact_url(),
	);
}

/**
 * Contact page on this shop, with a slug fallback per language.
 *
 * @return string
 */
function nh_service_contact_url() {
	$slugs = array( 'contacts', 'contact', 'kontakt', 'kontakter', 'yhteystiedot', 'kontaktai' );
	foreach ( $slugs as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof WP_Post && $page->post_status === 'publish' ) {
			$link = get_permalink( $page );
			if ( $link ) {
				return $link;
			}
		}
	}

	return home_url( '/contacts/' );
}

/**
 * Card data for one service. No author and no date.
 *
 * @param WP_Post $post  Service.
 * @param int     $index Position, used for image loading.
 * @return array<string,mixed>
 */
function nh_service_card_from_post( WP_Post $post, $index = 0 ) {
	$title = get_the_title( $post );
	$thumb = (int) get_post_thumbnail_id( $post );
	$image = '';

	if ( $thumb > 0 ) {
		$alt = function_exists( 'nh_get_attachment_alt' ) ? nh_get_attachment_alt( $thumb, $title ) : $title;
		$image = wp_get_attachment_image(
			$thumb,
			'large',
			false,
			array(
				'class'    => 'nh-service-band__img',
				'alt'      => $alt,
				'sizes'    => '(max-width: 799px) 100vw, 560px',
				'loading'  => (int) $index === 0 ? 'eager' : 'lazy',
				'decoding' => 'async',
			)
		);
	}

	return array(
		'title'       => $title,
		'url'         => get_permalink( $post ),
		'anchor'      => 'service-' . $post->post_name,
		'teaser'      => nh_service_teaser( $post->post_excerpt, $post->post_content, 200 ),
		'summary'     => nh_service_plain_summary( $post->post_excerpt, $post->post_content, 300 ),
		'image_html'  => $image,
		'image_url'   => $thumb ? (string) wp_get_attachment_image_url( $thumb, 'large' ) : '',
		'cta'         => __( 'View Service', 'nh-theme' ),
	);
}

/**
 * Sectioned content + contents list for the single template.
 *
 * @param int $post_id Service ID.
 * @return array{html:string,toc:array}
 */
function nh_service_prepare_post_content( $post_id ) {
	$post = get_post( $post_id );
	$raw  = $post instanceof WP_Post ? $post->post_content : '';
	$html = apply_filters( 'the_content', $raw );
	return nh_service_sectionize_html( $html );
}

/**
 * Other services, ordered the same way as the archive.
 *
 * @param int $current_id Current service.
 * @return string
 */
function nh_service_related_markup( $current_id ) {
	$q = new WP_Query( array(
		'post_type'           => 'service',
		'posts_per_page'      => 3,
		'post__not_in'        => array( (int) $current_id ),
		'orderby'             => nh_service_orderby(),
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	) );

	if ( ! $q->have_posts() ) {
		return '';
	}

	$html  = '<section class="nh-service-related" aria-labelledby="nh-service-related-title">';
	$html .= '<h2 id="nh-service-related-title">' . esc_html__( 'Other services', 'nh-theme' ) . '</h2>';
	$html .= '<div class="nh-service-related__grid">';

	$index = 0;
	while ( $q->have_posts() ) {
		$q->the_post();
		$card = nh_service_card_from_post( get_post(), $index + 1 );
		$card['image_html'] = nh_service_related_image_html( get_post() );
		$html .= nh_service_related_card_markup( $card );
		$index++;
	}

	$html .= '</div></section>';
	wp_reset_postdata();

	return $html;
}

/**
 * Smaller image for related cards.
 *
 * @param WP_Post $post Service.
 * @return string
 */
function nh_service_related_image_html( WP_Post $post ) {
	$thumb = (int) get_post_thumbnail_id( $post );
	if ( $thumb <= 0 ) {
		return '';
	}

	$title = get_the_title( $post );
	$alt   = function_exists( 'nh_get_attachment_alt' ) ? nh_get_attachment_alt( $thumb, $title ) : $title;

	return wp_get_attachment_image(
		$thumb,
		'medium_large',
		false,
		array(
			'class'    => 'nh-service-card__img',
			'alt'      => $alt,
			'sizes'    => '(max-width: 699px) 100vw, 360px',
			'loading'  => 'lazy',
			'decoding' => 'async',
		)
	);
}
