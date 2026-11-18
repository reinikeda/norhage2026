<?php
/**
 * One MP4 reel per product, shown as the next gallery image.
 *
 * The editor box sits after WooCommerce's Product gallery. The reel stays
 * paused until the customer opens that slide, then plays muted.
 *
 * @package Astra Custom for Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product meta key for the gallery video attachment.
 *
 * @return string
 */
function nh_gallery_video_meta_key() {
	return '_nh_gallery_video_id';
}

/**
 * Soft size guide for a 20–30 second reel. Larger files still save.
 *
 * @return int
 */
function nh_gallery_video_soft_limit() {
	return 15 * 1024 * 1024;
}

/**
 * Whether the attachment mime is the one format the product page plays.
 *
 * @param string $mime MIME type.
 * @return bool
 */
function nh_gallery_video_is_mp4( $mime ) {
	return 'video/mp4' === strtolower( trim( (string) $mime ) );
}

/**
 * Keep a positive attachment id only when it is an MP4.
 *
 * @param mixed  $attachment_id Posted or stored id.
 * @param string $mime          Attachment MIME type.
 * @return int
 */
function nh_gallery_video_sanitize_id( $attachment_id, $mime ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id < 1 || ! nh_gallery_video_is_mp4( $mime ) ) {
		return 0;
	}
	return $attachment_id;
}

/**
 * Whether the file is past the size we ask editors to stay under.
 *
 * @param int $bytes File size.
 * @return bool
 */
function nh_gallery_video_is_heavy( $bytes ) {
	return (int) $bytes > nh_gallery_video_soft_limit();
}

/**
 * Inline icon for the sound control. The name is one of muted, sound, play.
 *
 * @param string $name Icon name.
 * @return string
 */
function nh_gallery_video_icon( $name ) {
	$icons = array(
		'muted' => 'M4 9h3.2L11 5.8v12.4L7.2 15H4V9zm12.1 3 2.2-2.2 1.2 1.2L17.3 12l2.2 2.2-1.2 1.2-2.2-2.2-2.2 2.2-1.2-1.2L14.9 12l-2.2-2.2 1.2-1.2 2.2 2.2z',
		'sound' => 'M4 9h3.2L11 5.8v12.4L7.2 15H4V9zm10.1 3a3.2 3.2 0 0 0-1.6-2.8v5.6A3.2 3.2 0 0 0 14.1 12zm0-6.2v1.7a5.5 5.5 0 0 1 0 9v1.7a7.2 7.2 0 0 0 0-12.4z',
		'play'  => 'M8 5.5v13l11-6.5L8 5.5z',
	);

	if ( ! isset( $icons[ $name ] ) ) {
		return '';
	}

	return '<svg class="nh-gallery-video__icon nh-gallery-video__icon--' . $name . '" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="' . $icons[ $name ] . '"/></svg>';
}

/**
 * Markup for the reel as the last product-gallery slide.
 *
 * The slide stays paused until the customer opens it. The muted attribute
 * lets that later play start without sound.
 *
 * @param array $video url, mime, poster, thumb, label, width, height.
 * @return string
 */
function nh_gallery_video_html( array $video ) {
	$url  = isset( $video['url'] ) ? (string) $video['url'] : '';
	$mime = isset( $video['mime'] ) ? (string) $video['mime'] : '';
	if ( '' === $url || ! nh_gallery_video_is_mp4( $mime ) ) {
		return '';
	}

	$label = isset( $video['label'] ) ? trim( (string) $video['label'] ) : '';
	if ( '' === $label ) {
		$label = __( 'Product video', 'nh-theme' );
	}

	$poster = isset( $video['poster'] ) ? (string) $video['poster'] : '';
	$thumb  = isset( $video['thumb'] ) ? (string) $video['thumb'] : $poster;
	$width  = isset( $video['width'] ) ? (int) $video['width'] : 0;
	$height = isset( $video['height'] ) ? (int) $video['height'] : 0;

	$attrs = array(
		'class'              => 'nh-gallery-video__media',
		'playsinline'        => true,
		'webkit-playsinline' => true,
		'loop'               => true,
		'muted'              => true,
		'preload'            => 'metadata',
		'aria-label'         => $label,
	);
	if ( '' !== $poster ) {
		$attrs['poster'] = $poster;
	}
	if ( $width > 0 ) {
		$attrs['width'] = (string) $width;
	}
	if ( $height > 0 ) {
		$attrs['height'] = (string) $height;
	}

	$attr_html = '';
	foreach ( $attrs as $name => $value ) {
		if ( true === $value ) {
			$attr_html .= ' ' . $name;
			continue;
		}
		$attr_html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
	}

	$sound_on  = __( 'Sound on', 'nh-theme' );
	$sound_off = __( 'Sound off', 'nh-theme' );
	$play      = __( 'Play video', 'nh-theme' );

	$poster_html = '';
	if ( '' !== $poster ) {
		$poster_html = '<img class="nh-gallery-video__poster" src="' . esc_url( $poster ) . '" alt="" />';
	}

	$thumb_attr = '';
	if ( '' !== $thumb ) {
		$thumb_attr = ' data-thumb="' . esc_url( $thumb ) . '"';
	}

	return '<div class="woocommerce-product-gallery__image nh-gallery-video-slide"' . $thumb_attr . '>'
		. '<div class="nh-gallery-video">'
		. $poster_html
		. '<video' . $attr_html . '>'
		. '<source src="' . esc_url( $url ) . '" type="video/mp4" />'
		. '</video>'
		. '<button type="button" class="nh-gallery-video__sound" data-mode="play" aria-pressed="false"'
		. ' data-label-on="' . esc_attr( $sound_on ) . '"'
		. ' data-label-off="' . esc_attr( $sound_off ) . '"'
		. ' data-label-play="' . esc_attr( $play ) . '"'
		. ' aria-label="' . esc_attr( $play ) . '">'
		. nh_gallery_video_icon( 'muted' )
		. nh_gallery_video_icon( 'sound' )
		. nh_gallery_video_icon( 'play' )
		. '</button>'
		. '</div>'
		. '</div>';
}

/**
 * Reel markup for one product, or an empty string.
 *
 * @param int $product_id Product id.
 * @return string
 */
function nh_gallery_video_for_product( $product_id ) {
	$product_id    = (int) $product_id;
	$attachment_id = (int) get_post_meta( $product_id, nh_gallery_video_meta_key(), true );
	if ( $attachment_id < 1 ) {
		return '';
	}

	$mime = (string) get_post_mime_type( $attachment_id );
	$url  = (string) wp_get_attachment_url( $attachment_id );
	$id   = nh_gallery_video_sanitize_id( $attachment_id, $mime );
	if ( ! $id || '' === $url ) {
		return '';
	}

	$poster   = '';
	$thumb    = '';
	$image_id = (int) get_post_thumbnail_id( $product_id );
	if ( $image_id ) {
		$poster = (string) wp_get_attachment_image_url( $image_id, 'woocommerce_single' );
		$thumb  = (string) wp_get_attachment_image_url( $image_id, 'woocommerce_gallery_thumbnail' );
		if ( '' === $thumb ) {
			$thumb = $poster;
		}
	}

	$meta   = wp_get_attachment_metadata( $attachment_id );
	$width  = ( is_array( $meta ) && ! empty( $meta['width'] ) ) ? (int) $meta['width'] : 0;
	$height = ( is_array( $meta ) && ! empty( $meta['height'] ) ) ? (int) $meta['height'] : 0;
	$title  = trim( (string) get_the_title( $attachment_id ) );

	return nh_gallery_video_html(
		array(
			'url'    => $url,
			'mime'   => $mime,
			'poster' => $poster,
			'thumb'  => $thumb,
			'label'  => '' !== $title ? $title : __( 'Product video', 'nh-theme' ),
			'width'  => $width,
			'height' => $height,
		)
	);
}

/**
 * Print the reel as the last slide inside the product gallery.
 */
function nh_gallery_video_render() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$product_id = (int) get_the_ID();
	if ( $product_id < 1 ) {
		return;
	}

	$html = nh_gallery_video_for_product( $product_id );
	if ( '' === $html ) {
		return;
	}

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in nh_gallery_video_html().
}
add_action( 'woocommerce_product_thumbnails', 'nh_gallery_video_render', 30 );

/**
 * Sidebar box directly after Product gallery.
 */
function nh_gallery_video_add_metabox() {
	add_meta_box(
		'nh_gallery_video_box',
		__( 'Video gallery', 'nh-theme' ),
		'nh_gallery_video_metabox_html',
		'product',
		'side',
		'low'
	);
}
add_action( 'add_meta_boxes', 'nh_gallery_video_add_metabox', 40 );

/**
 * Editor field: one MP4 from the media library.
 *
 * @param WP_Post $post Product being edited.
 */
function nh_gallery_video_metabox_html( $post ) {
	$attachment_id = (int) get_post_meta( $post->ID, nh_gallery_video_meta_key(), true );
	$mime          = $attachment_id ? (string) get_post_mime_type( $attachment_id ) : '';
	$attachment_id = nh_gallery_video_sanitize_id( $attachment_id, $mime );
	$url           = $attachment_id ? (string) wp_get_attachment_url( $attachment_id ) : '';
	$filename      = $url ? wp_basename( $url ) : '';
	$bytes         = 0;

	if ( $attachment_id ) {
		$path = get_attached_file( $attachment_id );
		if ( $path && file_exists( $path ) ) {
			$size = filesize( $path );
			$bytes = false === $size ? 0 : (int) $size;
		}
	}

	wp_nonce_field( 'nh_gallery_video_save', 'nh_gallery_video_nonce' );
	?>
	<div id="nh-gallery-video-field">
		<input type="hidden" id="nh_gallery_video_id" name="nh_gallery_video_id" value="<?php echo esc_attr( (string) $attachment_id ); ?>" />
		<p class="nh-gallery-video-admin__file" <?php echo $filename ? '' : 'hidden'; ?>>
			<strong class="nh-gallery-video-admin__name"><?php echo esc_html( $filename ); ?></strong>
			<?php if ( $bytes > 0 ) : ?>
				<span><?php echo esc_html( size_format( $bytes ) ); ?></span>
			<?php endif; ?>
		</p>
		<?php if ( nh_gallery_video_is_heavy( $bytes ) ) : ?>
			<p class="description nh-gallery-video-admin__heavy">
				<?php esc_html_e( 'This file is over 15 MB. The product page will load more slowly. A compressed MP4 under 15 MB is a better fit.', 'nh-theme' ); ?>
			</p>
		<?php endif; ?>
		<p>
			<button type="button" class="button" id="nh-gallery-video-select">
				<?php echo esc_html( $attachment_id ? __( 'Replace video', 'nh-theme' ) : __( 'Select video', 'nh-theme' ) ); ?>
			</button>
			<button type="button" class="button" id="nh-gallery-video-remove" <?php echo $attachment_id ? '' : 'hidden'; ?>>
				<?php esc_html_e( 'Remove', 'nh-theme' ); ?>
			</button>
		</p>
		<p class="description">
			<?php esc_html_e( 'One MP4, about 6–12 MB for a 20–30 second reel. It appears as the next image in the product gallery and stays paused until that image is opened.', 'nh-theme' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Media picker on the product editor.
 *
 * @param string $hook Current admin page.
 */
function nh_gallery_video_admin_assets( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'product' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_media();

	wp_enqueue_script(
		'nh-gallery-video-admin',
		get_stylesheet_directory_uri() . '/assets/js/product-gallery-video-admin.js',
		array( 'jquery' ),
		function_exists( 'norhage_asset_version' ) ? norhage_asset_version( '/assets/js/product-gallery-video-admin.js' ) : '1.0.0',
		true
	);

	wp_localize_script(
		'nh-gallery-video-admin',
		'nhGalleryVideo',
		array(
			'title'   => __( 'Video gallery', 'nh-theme' ),
			'button'  => __( 'Use this video', 'nh-theme' ),
			'select'  => __( 'Select video', 'nh-theme' ),
			'replace' => __( 'Replace video', 'nh-theme' ),
			'onlyMp4' => __( 'Choose an MP4 file.', 'nh-theme' ),
			'heavy'   => __( 'This file is over 15 MB. The product page will load more slowly. A compressed MP4 under 15 MB is a better fit.', 'nh-theme' ),
			'limit'   => nh_gallery_video_soft_limit(),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'nh_gallery_video_admin_assets' );

/**
 * Save the selected attachment, or clear it.
 *
 * @param int $post_id Product id.
 */
function nh_gallery_video_save( $post_id ) {
	if ( ! isset( $_POST['nh_gallery_video_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['nh_gallery_video_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'nh_gallery_video_save' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$raw  = isset( $_POST['nh_gallery_video_id'] ) ? wp_unslash( $_POST['nh_gallery_video_id'] ) : 0;
	$raw  = (int) $raw;
	$mime = $raw > 0 ? (string) get_post_mime_type( $raw ) : '';
	$id   = nh_gallery_video_sanitize_id( $raw, $mime );

	if ( ! $id ) {
		delete_post_meta( $post_id, nh_gallery_video_meta_key() );
		return;
	}

	update_post_meta( $post_id, nh_gallery_video_meta_key(), $id );
}
add_action( 'save_post_product', 'nh_gallery_video_save' );
