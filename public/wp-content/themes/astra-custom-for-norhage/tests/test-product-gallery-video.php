<?php
/**
 * CLI tests: product gallery video.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-product-gallery-video.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return str_replace( '&', '&amp;', (string) $url );
	}
}

require_once dirname( __DIR__ ) . '/inc/product-gallery-video.php';

$failures = 0;

function nh_vg_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

nh_vg_assert( 'meta key is the gallery video id', '_nh_gallery_video_id' === nh_gallery_video_meta_key() );
nh_vg_assert( 'mp4 is accepted', nh_gallery_video_is_mp4( 'video/mp4' ) );
nh_vg_assert( 'mp4 mime is case-insensitive', nh_gallery_video_is_mp4( 'VIDEO/MP4' ) );
nh_vg_assert( 'webm is rejected', ! nh_gallery_video_is_mp4( 'video/webm' ) );
nh_vg_assert( 'image is rejected', ! nh_gallery_video_is_mp4( 'image/jpeg' ) );

nh_vg_assert( 'mp4 id is kept', 42 === nh_gallery_video_sanitize_id( '42', 'video/mp4' ) );
nh_vg_assert( 'non-mp4 id is dropped', 0 === nh_gallery_video_sanitize_id( 42, 'video/quicktime' ) );
nh_vg_assert( 'empty id is dropped', 0 === nh_gallery_video_sanitize_id( 0, 'video/mp4' ) );
nh_vg_assert( 'negative id is dropped', 0 === nh_gallery_video_sanitize_id( -3, 'video/mp4' ) );

$limit = nh_gallery_video_soft_limit();
nh_vg_assert( 'soft limit is 15 MB', 15 * 1024 * 1024 === $limit );
nh_vg_assert( '14 MB is not heavy', ! nh_gallery_video_is_heavy( 14 * 1024 * 1024 ) );
nh_vg_assert( 'exactly 15 MB is not heavy', ! nh_gallery_video_is_heavy( $limit ) );
nh_vg_assert( '16 MB is heavy', nh_gallery_video_is_heavy( $limit + 1 ) );

nh_vg_assert( 'empty url renders nothing', '' === nh_gallery_video_html( array( 'url' => '', 'mime' => 'video/mp4' ) ) );
nh_vg_assert( 'webm renders nothing', '' === nh_gallery_video_html( array( 'url' => 'https://cdn.example/a.webm', 'mime' => 'video/webm' ) ) );

$html = nh_gallery_video_html(
	array(
		'url'    => 'https://cdn.example/reel.mp4?x=1&y=2',
		'mime'   => 'video/mp4',
		'poster' => 'https://cdn.example/poster.jpg',
		'label'  => 'Greenhouse reel',
		'width'  => 1080,
		'height' => 1920,
	)
);

nh_vg_assert( 'markup is a video block', false !== strpos( $html, 'class="nh-gallery-video"' ) );
nh_vg_assert( 'file is an mp4 source', false !== strpos( $html, 'type="video/mp4"' ) );
nh_vg_assert( 'query string is escaped', false !== strpos( $html, 'x=1&amp;y=2' ) );
nh_vg_assert( 'video starts muted', false !== strpos( $html, ' muted' ) );
nh_vg_assert( 'video loops', false !== strpos( $html, ' loop' ) );
nh_vg_assert( 'video plays inline', false !== strpos( $html, ' playsinline' ) );
nh_vg_assert( 'autoplay is left to script', false === strpos( $html, 'autoplay' ) );
nh_vg_assert( 'poster is the product image', false !== strpos( $html, 'poster="https://cdn.example/poster.jpg"' ) );
nh_vg_assert( 'label is on the video', false !== strpos( $html, 'aria-label="Greenhouse reel"' ) );
nh_vg_assert( 'sound button starts muted', false !== strpos( $html, 'data-mode="muted"' ) );
nh_vg_assert( 'sound on label is present', false !== strpos( $html, 'data-label-on="Sound on"' ) );
nh_vg_assert( 'sound off label is present', false !== strpos( $html, 'data-label-off="Sound off"' ) );
nh_vg_assert( 'play label is present', false !== strpos( $html, 'data-label-play="Play video"' ) );
nh_vg_assert( 'intrinsic size is kept', false !== strpos( $html, 'width="1080"' ) && false !== strpos( $html, 'height="1920"' ) );

$quoted = nh_gallery_video_html(
	array(
		'url'   => 'https://cdn.example/reel.mp4',
		'mime'  => 'video/mp4',
		'label' => 'Say "hello"',
	)
);
nh_vg_assert( 'label quotes are escaped', false !== strpos( $quoted, 'aria-label="Say &quot;hello&quot;"' ) );
nh_vg_assert( 'missing poster omits the attribute', false === strpos( $quoted, 'poster=' ) );

$php = file_get_contents( dirname( __DIR__ ) . '/inc/product-gallery-video.php' );
nh_vg_assert( 'box is titled Video gallery', false !== strpos( $php, "__( 'Video gallery', 'nh-theme' )" ) );
nh_vg_assert( 'box is in the sidebar', false !== strpos( $php, "'side'" ) );
nh_vg_assert( 'box is registered after the product gallery', false !== strpos( $php, "'nh_gallery_video_add_metabox', 40" ) );
nh_vg_assert( 'reel prints after the gallery hook slot', false !== strpos( $php, "'nh_gallery_video_render', 22" ) );

$functions = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
nh_vg_assert(
	'functions.php loads the video module',
	false !== strpos( $functions, "/inc/product-gallery-video.php'" )
);

$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/product-gallery-note.js' );
nh_vg_assert( 'script places the reel with the photo note', false !== strpos( $js, 'function placeGalleryTail' ) );
nh_vg_assert( 'script matches a short clip to the photo height', false !== strpos( $js, 'Math.max(galleryHeight' ) );
nh_vg_assert( 'script respects reduced motion', false !== strpos( $js, 'prefers-reduced-motion' ) );
nh_vg_assert( 'script starts muted', false !== strpos( $js, 'function playMuted' ) );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/product-page.css' );
nh_vg_assert( 'short clip covers the photo frame', false !== strpos( $css, 'object-fit: cover' ) );

$admin = file_get_contents( dirname( __DIR__ ) . '/assets/js/product-gallery-video-admin.js' );
nh_vg_assert( 'picker accepts mp4', false !== strpos( $admin, "type: 'video/mp4'" ) );
nh_vg_assert( 'picker sits after Product gallery', false !== strpos( $admin, 'woocommerce-product-images' ) );

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
