<?php
/**
 * Astra Custom for Norhage Theme functions and definitions
 *
 * @package Astra Custom for Norhage
 * @since 1.0.0
 */

define( 'CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION', '1.0.0' );

function child_enqueue_styles() {
	// Inherit Astra parent styles
	wp_enqueue_style(
		'astra-custom-for-norhage-theme-css',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'astra-theme-css' ),
		CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION
	);

	// Your custom CSS
	wp_enqueue_style(
		'norhage-custom-style',
		get_stylesheet_directory_uri() . '/assets/css/style.css',
		array(),
		CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION
	);

	// Your custom JS
	wp_enqueue_script(
		'norhage-custom-js',
		get_stylesheet_directory_uri() . '/assets/js/script.js',
		array(),
		CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );

//Enqueues custom dark mode CSS and JS files
function child_enqueue_dark_mode_assets() {
    wp_enqueue_style(
        'dark-mode-css',
        get_stylesheet_directory_uri() . '/dark-mode.css',
        array(),
        null
    );

    wp_enqueue_script(
        'dark-mode-toggle-js',
        get_stylesheet_directory_uri() . '/dark-mode.js',
        array(),
        null,
        true
    );
}
add_action('wp_enqueue_scripts', 'child_enqueue_dark_mode_assets');

//Short description display next to product name in catalog
add_action('woocommerce_shop_loop_item_title', 'add_secondary_product_line', 11);

function add_secondary_product_line() {
    global $product;
    echo '<div class="secondary-title">' . $product->get_short_description() . '</div>';
}


