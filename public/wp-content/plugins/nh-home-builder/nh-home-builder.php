<?php
/**
 * Plugin Name: Home Builder
 * Description: One section type: "Offers Hero (slider + 2 promos)". Manage in wp-admin → Home Builder. Render with [nh_section id="123"].
 * Version: 0.1.1
 * Author: Daiva Reinike
 * Text Domain: nhhb
 */
if (!defined('ABSPATH')) exit;

define('NHHB_PATH', plugin_dir_path(__FILE__));
define('NHHB_URL',  plugin_dir_url(__FILE__));
define('NHHB_VER', '0.1.1');

/**
 * Shared photo helper. Do not name this nhhb_img — several section
 * templates previously defined competing nhhb_img() helpers, so feature
 * icons were rendered as 1×1 <img> tags instead of inline SVG.
 */
function nhhb_attachment_image($id, $size = 'woocommerce_thumbnail', $attrs = []) {
    if (!$id) {
        return '<div class="nhhb-ph-img" aria-hidden="true"></div>';
    }

    $attrs = array_merge(['loading' => 'lazy', 'decoding' => 'async', 'alt' => ''], $attrs);
    return wp_get_attachment_image((int) $id, $size, false, $attrs);
}

/**
 * Inline an SVG from the media library (feature icons).
 */
function nhhb_inline_svg($id) {
    if (!$id) {
        return '<div class="nhhb-ph-img" aria-hidden="true"></div>';
    }

    $path = get_attached_file($id);
    if (!$path || !file_exists($path)) {
        return nhhb_attachment_image($id, 'thumbnail', ['loading' => 'lazy']);
    }

    $mime = (string) get_post_mime_type($id);
    if ($mime && strpos($mime, 'svg') === false && strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'svg') {
        return nhhb_attachment_image($id, 'thumbnail', ['loading' => 'lazy']);
    }

    $svg = file_get_contents($path);
    if ($svg === false) {
        return '';
    }

    $svg = preg_replace('/<\?xml.*?\?>/i', '', $svg);
    $svg = preg_replace('/<!DOCTYPE.*?>/is', '', $svg);
    $svg = preg_replace('/\s(width|height)="[^"]*"/i', '', $svg);

    $allowed = [
        'svg'      => ['viewBox' => true, 'xmlns' => true, 'role' => true, 'aria-label' => true, 'fill' => true, 'stroke' => true],
        'g'        => ['fill' => true, 'stroke' => true, 'transform' => true],
        'path'     => ['d' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true],
        'rect'     => ['x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true],
        'circle'   => ['cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true],
        'ellipse'  => ['cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true],
        'line'     => ['x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true],
        'polyline' => ['points' => true, 'fill' => true, 'stroke' => true],
        'polygon'  => ['points' => true, 'fill' => true, 'stroke' => true],
        'title'    => [],
    ];

    return '<span class="nhhb-svg">' . wp_kses($svg, $allowed) . '</span>';
}

/**
 * Load plugin textdomain.
 */
function nhhb_load_textdomain() {
    load_plugin_textdomain(
        'nhhb',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages/'
    );
}
add_action( 'plugins_loaded', 'nhhb_load_textdomain' );

require_once NHHB_PATH . 'includes/class-admin.php';

function nhhb_render($section, $data = []) {
    if ($section === 'offers-hero') { $section = 'top-offers'; } // back-compat
    $file = NHHB_PATH . 'includes/render/' . $section . '.php';
    if (!file_exists($file)) return '';
    ob_start(); include $file; return ob_get_clean();
}

add_shortcode('nh_section', function ($atts) {
    $atts = shortcode_atts(['id' => 0], $atts);
    $id   = absint($atts['id']);
    if (!$id) return '';

    $type = get_post_meta($id, '_nhhb_type', true);
    $data = get_post_meta($id, '_nhhb_data', true) ?: [];

    return $type ? nhhb_render($type, $data) : '';
});
