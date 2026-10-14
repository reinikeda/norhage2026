<?php
/**
 * NH Feature Box — Frontend Output
 *
 * - In stock: below the add-to-cart button, above Ask an expert.
 * - Out of stock: appended to the short description, unchanged.
 *
 * Text domain: nh-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Guard against double render. */
$nh_feature_box_rendered = false;

/**
 * In stock: after the cart form (priority 8), before Ask an expert (12)
 * and the bundle box (20). The function name is kept so existing
 * remove_action() calls still find it.
 */
add_action( 'woocommerce_after_add_to_cart_form', 'nh_render_feature_box_before_cart', 8 );
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
    $nh_feature_box_rendered = true;
}

/**
 * OUT-OF-STOCK: append to short description.
 *
 * WooCommerce also runs this filter from wc_format_content() while it builds
 * variation JSON, before the add-to-cart form is printed. That is not the
 * short description block. Closing the page buffer there drops the document
 * head — including jQuery — and leaves the rest of the product page broken.
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
    if ( nh_feature_box_filter_is_variation_format() ) {
        return $desc;
    }

    $html = nh_get_feature_box_html( $product->get_id() );
    if ( empty( $html ) ) {
        return $desc;
    }

    $nh_feature_box_rendered = true;

    $level = ob_get_level();
    ob_start();
    do_action( 'nh_after_feature_box', $product->get_id() );
    $after = '';
    if ( ob_get_level() > $level ) {
        $after = ob_get_clean();
    }
    if ( ! is_string( $after ) ) {
        $after = '';
    }

    return $desc . $html . wp_kses_post( $after );
}

/**
 * Variation descriptions are formatted with wc_format_content(), which applies
 * woocommerce_short_description. The product short description template does not.
 *
 * @return bool
 */
function nh_feature_box_filter_is_variation_format() {
    $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 15 );
    foreach ( $trace as $frame ) {
        if ( isset( $frame['function'] ) && 'wc_format_content' === $frame['function'] ) {
            return true;
        }
    }

    return false;
}
