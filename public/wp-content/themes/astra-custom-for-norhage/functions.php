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
        get_stylesheet_directory_uri() . '/assets/css/dark-mode.css',
        array(),
        null
    );

    wp_enqueue_script(
        'dark-mode-toggle-js',
        get_stylesheet_directory_uri() . '/assets/js/dark-mode.js',
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

// Displays upsell products in the sidebar on single product pages.
add_action('wp_enqueue_scripts', function () {
    if (!is_product()) return;

    wp_enqueue_script(
        'bundle-add-to-cart',
        get_stylesheet_directory_uri() . '/assets/js/bundle-add-to-cart.js',
        ['jquery'],
        null,
        true
    );

    wp_localize_script('bundle-add-to-cart', 'bundle_ajax', [
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
});

add_shortcode('sidebar_upsell_products', function () {
    if (!is_product()) return;

    global $product;
    $upsells = $product->get_upsell_ids();
    if (empty($upsells)) return;

    $output = '<div class="sidebar-upsell-products"><h3>Frequently Bought Together</h3>';
    $output .= '<div id="bundle-products">';

    foreach ($upsells as $upsell_id) {
        $upsell_product = wc_get_product($upsell_id);

        $output .= '<div class="bundle-product" data-product-id="' . esc_attr($upsell_product->get_id()) . '" data-product-type="' . esc_attr($upsell_product->get_type()) . '">';
        $output .= '<a href="' . get_permalink($upsell_id) . '">' . get_the_post_thumbnail($upsell_id, 'thumbnail') . '</a>';
        $output .= '<h4>' . esc_html($upsell_product->get_name()) . '</h4>';
        $output .= '<p class="price">' . $upsell_product->get_price_html() . '</p>';

        // Variation dropdowns
        if ($upsell_product->is_type('variable')) {
            $attributes = $upsell_product->get_variation_attributes();
            $default_attributes = $upsell_product->get_default_attributes();

            $output .= '<div class="variation-row">';
            foreach ($attributes as $attribute_name => $options) {
                $output .= '<select name="attribute_' . esc_attr(sanitize_title($attribute_name)) . '">';
                $output .= '<option value="">' . wc_attribute_label($attribute_name) . '</option>';
                foreach ($options as $option) {
                    $selected = selected($default_attributes[$attribute_name] ?? '', $option, false);
                    $output .= '<option value="' . esc_attr($option) . '" ' . $selected . '>' . esc_html($option) . '</option>';
                }
                $output .= '</select>';
            }
            $output .= '</div>';
        }

        // Quantity field
        $output .= '<input type="number" class="bundle-qty" value="0" min="0" />';

        // Placeholder for subtotal
        $output .= '<div class="product-subtotal" style="margin-top: 5px;"></div>';

        $output .= '</div>'; // .bundle-product
    }

    $output .= '</div>'; // #bundle-products

    // Total bundle price display
    $output .= '<div id="bundle-total" style="margin-top:10px;"></div>';

    $output .= '<button id="add-bundle-to-cart" class="button">Add All to Basket</button>';
    $output .= '<div id="bundle-result" style="margin-top:10px;"></div>';
    $output .= '</div>'; // .sidebar-upsell-products

    return $output;
});

// Remove default upsell products from below product description
remove_action('woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15);
