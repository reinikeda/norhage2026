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
