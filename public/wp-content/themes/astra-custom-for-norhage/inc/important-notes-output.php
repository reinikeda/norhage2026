<?php
/**
 * NH Important Notes - Frontend Output
 *
 * Appends notes to the product description, and prints a quiet cutting
 * tolerance line under the Your selection box.
 *
 * Text domain: nh-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Append notes to the product description (the_content).
 *
 * @param string $content Post content.
 * @return string
 */
add_filter( 'the_content', 'nh_append_important_notes_to_description', 20 );
function nh_append_important_notes_to_description( $content ) {
    if ( is_admin() || ! is_product() || ! is_main_query() || ! in_the_loop() ) {
        return $content;
    }

    $notes_html = nh_get_important_notes_html();
    if ( empty( $notes_html ) ) {
        return $content;
    }

    return $content . $notes_html;
}

/**
 * Cutting tolerance, directly under the Your selection box.
 *
 * The selection box is the same hook at priority 11. Quantity and the
 * add to cart button follow this line.
 */
add_action( 'woocommerce_before_add_to_cart_button', 'nh_output_cutting_tolerance_callout', 12 );
function nh_output_cutting_tolerance_callout() {
    if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
        return;
    }

    global $product;

    if ( ! $product instanceof WC_Product ) {
        return;
    }

    $main_product_id = (int) get_queried_object_id();
    if ( $main_product_id <= 0 || (int) $product->get_id() !== $main_product_id ) {
        return;
    }

    $html = nh_get_cutting_tolerance_callout_html( $main_product_id );
    if ( '' === $html ) {
        return;
    }

    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the builder.
}
