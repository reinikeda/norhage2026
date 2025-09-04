<?php
/**
 * Plugin Name: Custom FAQ
 * Description: Simple, fast WooCommerce FAQ plugin with Global & Product scopes + FAQPage schema.
 * Version: 1.0.0
 * Author: Daiva Reinike
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'NH_FAQ_VERSION', '1.0.0' );
define( 'NH_FAQ_PATH', plugin_dir_path( __FILE__ ) );
define( 'NH_FAQ_URL',  plugin_dir_url( __FILE__ ) );

require_once NH_FAQ_PATH . 'includes/class-nh-faq-cpt.php';
require_once NH_FAQ_PATH . 'includes/class-nh-faq-metaboxes.php';
require_once NH_FAQ_PATH . 'includes/class-nh-faq-render.php';
require_once NH_FAQ_PATH . 'includes/class-nh-faq-woocommerce.php';

register_activation_hook( __FILE__, function() {
    NH_FAQ_CPT::register();
    flush_rewrite_rules();
});
register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
});

add_action( 'init', ['NH_FAQ_CPT', 'register'] );
add_action( 'plugins_loaded', function() {
    new NH_FAQ_Metaboxes();
    new NH_FAQ_Render();
    new NH_FAQ_Woo();
});

/**
 * Admin + front assets
 */
add_action( 'wp_enqueue_scripts', function() {
    wp_register_style( 'nh-faq', NH_FAQ_URL . 'assets/css/faq.css', [], NH_FAQ_VERSION );
    wp_register_script( 'nh-faq', NH_FAQ_URL . 'assets/js/faq.js', [], NH_FAQ_VERSION, true );
});

add_action( 'admin_enqueue_scripts', function( $hook ) {
    $screen = get_current_screen();
    if ( $screen && 'nh_faq' === $screen->post_type ) {
        wp_enqueue_style( 'nh-faq-admin', NH_FAQ_URL . 'assets/css/faq.css', [], NH_FAQ_VERSION );
        wp_enqueue_script( 'nh-faq-admin', NH_FAQ_URL . 'assets/js/faq.js', ['jquery'], NH_FAQ_VERSION, true );
        wp_localize_script( 'nh-faq-admin', 'NHFAQ', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nh_faq_nonce'),
        ]);
    }
});

/**
 * AJAX: product search for the metabox selector
 */
add_action('wp_ajax_nh_faq_product_search', function() {
    if ( ! current_user_can('edit_posts') ) wp_send_json_error('forbidden', 403);
    check_ajax_referer('nh_faq_nonce','nonce');

    $term = isset($_GET['q']) ? sanitize_text_field( wp_unslash($_GET['q']) ) : '';
    $results = [];

    if ( $term ) {
        $q = new WP_Query([
            'post_type'      => 'product',
            's'              => $term,
            'posts_per_page' => 20,
            'post_status'    => ['publish','private'],
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        foreach ( $q->posts as $p ) {
            $results[] = ['id' => $p->ID, 'text' => html_entity_decode(get_the_title($p))];
        }
    }
    wp_send_json(['results' => $results]);
});

// in nh-faq.php, after you require the class files:
add_filter('nh_faq_tab_title', function(){
    return __('Questions & Answers', 'nh-faq');
});

require_once NH_FAQ_PATH . 'includes/class-nh-faq-topics-order.php';
add_action('plugins_loaded', function(){ new NH_FAQ_Topics_Order(); });
