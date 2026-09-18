<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alt text from the media library, with an optional fallback.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $fallback      Used when the media alt field is empty.
 * @return string
 */
function nh_get_attachment_alt( $attachment_id, $fallback = '' ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id <= 0 ) {
		return (string) $fallback;
	}

	$alt = trim( wp_strip_all_tags( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) );
	return $alt !== '' ? $alt : (string) $fallback;
}

/**
 * Fill empty alt attributes on content images from the media library.
 *
 * Gutenberg blocks (media & text, image) store alt="" in post content even
 * after the attachment alt is updated in Woo/media.
 *
 * @param string $html
 * @return string
 */
function nh_fill_empty_img_alts_in_html( $html ) {
	if ( ! is_string( $html ) || $html === '' || stripos( $html, '<img' ) === false ) {
		return $html;
	}

	return preg_replace_callback(
		'/<img\b[^>]*>/i',
		static function ( $match ) {
			$tag = $match[0];

			if ( preg_match( '/\salt\s*=\s*(["\'])(.*?)\1/is', $tag, $alt_match ) && trim( html_entity_decode( $alt_match[2], ENT_QUOTES, 'UTF-8' ) ) !== '' ) {
				return $tag;
			}

			if ( ! preg_match( '/wp-image-(\d+)/', $tag, $id_match ) ) {
				return $tag;
			}

			$alt = nh_get_attachment_alt( (int) $id_match[1] );
			if ( $alt === '' ) {
				return $tag;
			}

			$esc = function_exists( 'esc_attr' ) ? esc_attr( $alt ) : htmlspecialchars( $alt, ENT_QUOTES, 'UTF-8' );

			if ( preg_match( '/\salt\s*=\s*(["\'])(.*?)\1/is', $tag ) ) {
				return preg_replace( '/\salt\s*=\s*(["\'])(.*?)\1/is', ' alt="' . $esc . '"', $tag, 1 );
			}

			return preg_replace( '/<img\b/i', '<img alt="' . $esc . '"', $tag, 1 );
		},
		$html
	);
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'the_content', 'nh_fill_empty_img_alts_in_html', 20 );
	add_filter( 'widget_text_content', 'nh_fill_empty_img_alts_in_html', 20 );
	add_filter( 'widget_block_content', 'nh_fill_empty_img_alts_in_html', 20 );
	add_filter( 'wp_content_img_tag', 'nh_fill_empty_img_alts_in_html', 10 );
	add_filter( 'get_avatar_data', 'nh_filter_avatar_data_alt', 20, 2 );
	add_filter( 'get_avatar', 'nh_filter_avatar_html_alt', 20, 5 );
}

/**
 * Display name for a get_avatar() identity (comment, user, or email).
 *
 * @param mixed $id_or_email
 * @return string
 */
function nh_avatar_identity_name( $id_or_email ) {
	if ( is_object( $id_or_email ) ) {
		if ( ! empty( $id_or_email->comment_author ) ) {
			return trim( wp_strip_all_tags( (string) $id_or_email->comment_author ) );
		}
		if ( ! empty( $id_or_email->display_name ) ) {
			return trim( wp_strip_all_tags( (string) $id_or_email->display_name ) );
		}
		if ( ! empty( $id_or_email->user_id ) && function_exists( 'get_user_by' ) ) {
			$user = get_user_by( 'id', (int) $id_or_email->user_id );
			if ( $user && ! empty( $user->display_name ) ) {
				return trim( wp_strip_all_tags( (string) $user->display_name ) );
			}
		}
	}

	if ( is_numeric( $id_or_email ) && function_exists( 'get_user_by' ) ) {
		$user = get_user_by( 'id', (int) $id_or_email );
		if ( $user && ! empty( $user->display_name ) ) {
			return trim( wp_strip_all_tags( (string) $user->display_name ) );
		}
	}

	return '';
}

/**
 * Prefer an existing alt, then the reviewer/user name.
 *
 * @param string $existing_alt
 * @param string $name
 * @param string $fallback
 * @return string
 */
function nh_avatar_alt( $existing_alt, $name, $fallback = '' ) {
	$existing_alt = trim( wp_strip_all_tags( (string) $existing_alt ) );
	if ( $existing_alt !== '' ) {
		return $existing_alt;
	}

	$name = trim( wp_strip_all_tags( (string) $name ) );
	if ( $name !== '' ) {
		return $name;
	}

	return (string) $fallback;
}

/**
 * Set alt on an img tag when it is missing or empty.
 *
 * @param string $tag
 * @param string $alt
 * @return string
 */
function nh_set_img_tag_alt( $tag, $alt ) {
	if ( ! is_string( $tag ) || $alt === '' ) {
		return $tag;
	}

	$esc = function_exists( 'esc_attr' ) ? esc_attr( $alt ) : htmlspecialchars( $alt, ENT_QUOTES, 'UTF-8' );

	if ( preg_match( '/\salt\s*=\s*(["\'])(.*?)\1/is', $tag, $alt_match ) && trim( html_entity_decode( $alt_match[2], ENT_QUOTES, 'UTF-8' ) ) !== '' ) {
		return $tag;
	}

	if ( preg_match( '/\salt\s*=\s*(["\'])(.*?)\1/is', $tag ) ) {
		return preg_replace( '/\salt\s*=\s*(["\'])(.*?)\1/is', ' alt="' . $esc . '"', $tag, 1 );
	}

	return preg_replace( '/<img\b/i', '<img alt="' . $esc . '"', $tag, 1 );
}

/**
 * @param array $args
 * @param mixed $id_or_email
 * @return array
 */
function nh_filter_avatar_data_alt( $args, $id_or_email ) {
	if ( ! is_array( $args ) ) {
		return $args;
	}

	$fallback = function_exists( '__' ) ? __( 'Reviewer', 'nh-theme' ) : 'Reviewer';
	$args['alt'] = nh_avatar_alt(
		isset( $args['alt'] ) ? $args['alt'] : '',
		nh_avatar_identity_name( $id_or_email ),
		$fallback
	);

	return $args;
}

/**
 * @param string $avatar
 * @param mixed  $id_or_email
 * @param int    $size
 * @param string $default
 * @param string $alt
 * @return string
 */
function nh_filter_avatar_html_alt( $avatar, $id_or_email, $size = 96, $default = '', $alt = '' ) {
	unset( $size, $default );

	if ( ! is_string( $avatar ) || $avatar === '' ) {
		return $avatar;
	}

	$fallback = function_exists( '__' ) ? __( 'Reviewer', 'nh-theme' ) : 'Reviewer';
	$resolved = nh_avatar_alt( $alt, nh_avatar_identity_name( $id_or_email ), $fallback );

	return nh_set_img_tag_alt( $avatar, $resolved );
}
