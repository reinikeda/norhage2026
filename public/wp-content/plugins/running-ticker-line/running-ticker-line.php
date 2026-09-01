<?php
/*
Plugin Name: Running Ticker Line
Description: Displays a scrolling ticker line across the top of the site, configurable via admin panel.
Version: 1.0
Author: Daiva Reinike
*/

defined('ABSPATH') or die('No script kiddies please.');

// Include admin and functions
require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/ticker-functions.php';

// Enqueue frontend styles and scripts only when the ticker has messages.
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    if (!function_exists('rtl_get_active_messages') || empty(rtl_get_active_messages())) {
        return;
    }
    wp_enqueue_style('rtl-ticker-style', plugin_dir_url(__FILE__) . 'css/ticker-style.css', [], '1.0.1');
    wp_enqueue_script('rtl-ticker-script', plugin_dir_url(__FILE__) . 'js/ticker-script.js', [], '1.0.1', true);
});

// Display ticker on frontend
// add_action('get_header', 'rtl_hook_into_header');

function rtl_hook_into_header() {
    add_action('wp_head', 'rtl_output_buffer_start');
    add_action('wp_footer', 'rtl_output_buffer_end');
}

function rtl_output_buffer_start() {
    ob_start();
}

function rtl_output_buffer_end() {
    $html = ob_get_clean();
    $ticker = rtl_capture_ticker_html();
    
    // Find header close tag and inject ticker right before it
    $html = preg_replace('/(<\/header>)/i', $ticker . '$1', $html);
    echo $html;
}

function rtl_capture_ticker_html() {
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/ticker-display.php';
    return ob_get_clean();
}

function rtl_display_ticker() {
    if (!is_admin()) {
        include plugin_dir_path(__FILE__) . 'templates/ticker-display.php';
    }
}
