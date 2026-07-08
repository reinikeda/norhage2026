<?php
/**
 * NH Feature Box — Frontend Output
 *
 * Mirrors the original plugin render logic exactly:
 * - In-stock: before add-to-cart (so custom-cut hook fires after).
 * - Out-of-stock: appended to short description.
 *
 * Text domain: nh-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Guard against double render. */
$nh_feature_box_rendered = false;

/**
 * IN-STOCK: render before add-to-cart / custom-cut form.
 */
add_action( 'woocommerce_before_add_to_cart_form', 'nh_render_feature_box_before_cart', 1 );
function nh_render_feature_box_before_cart() {
    global $nh_feature_box_rendered, $product;

    if ( $nh_feature_box_rendered || ! is_product() ) {
        return;
    }
    if ( ! $product instanceof WC_Product ) {
        return;
    }
    if ( ! $product->is_in_stock() ) {
        return;
    }

    $html = nh_get_feature_box_html( $product->get_id() );
    if ( empty( $html ) ) {
        return;
    }

    echo $html;
    do_action( 'nh_after_feature_box', $product->get_id() );
    $nh_feature_box_rendered = true;
}

/**
 * OUT-OF-STOCK: append to short description.
 */
add_filter( 'woocommerce_short_description', 'nh_append_feature_box_to_short_desc', 20 );
function nh_append_feature_box_to_short_desc( $desc ) {
    global $nh_feature_box_rendered, $product;

    if ( $nh_feature_box_rendered || ! is_product() ) {
        return $desc;
    }
    if ( ! $product instanceof WC_Product ) {
        return $desc;
    }
    if ( $product->is_in_stock() ) {
        return $desc;
    }

    $html = nh_get_feature_box_html( $product->get_id() );
    if ( empty( $html ) ) {
        return $desc;
    }

    $nh_feature_box_rendered = true;
    return $desc . $html . wp_kses_post( ob_get_clean() . do_action( 'nh_after_feature_box', $product->get_id() ) );
}

/**
 * Enqueue frontend CSS on product pages only.
 */
add_action( 'wp_enqueue_scripts', 'nh_enqueue_feature_box_front_assets' );
function nh_enqueue_feature_box_front_assets() {
    if ( ! is_product() ) {
        return;
    }
    wp_enqueue_style(
        'nh-feature-box-front',
        get_stylesheet_directory_uri() . '/assets/css/feature-box-front.css',
        array(),
        '2.0.0'
    );
}
