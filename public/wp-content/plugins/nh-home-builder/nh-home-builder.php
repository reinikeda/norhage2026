<?php
/**
 * Plugin Name: Home Builder
 * Description: One section type: "Offers Hero (slider + 2 promos)". Manage in wp-admin → Home Builder. Render with [nh_section id="123"].
 * Version: 0.1.0
 * Author: Daiva Reinike
 * Text Domain: nhhb
 */
if (!defined('ABSPATH')) exit;

define('NHHB_PATH', plugin_dir_path(__FILE__));
define('NHHB_URL',  plugin_dir_url(__FILE__));
define('NHHB_VER', '0.1.0');

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
