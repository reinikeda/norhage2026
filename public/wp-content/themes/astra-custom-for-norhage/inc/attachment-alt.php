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
}
