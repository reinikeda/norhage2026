<?php
/**
 * NH Important Notes - Frontend Output
 *
 * Appends notes to the product description, and prints the cutting
 * tolerance callout in the buy column under the delivery line.
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
 * Cutting tolerance, under the estimated delivery line.
 *
 * The delivery script moves this node to sit directly after that line
 * once the line itself has been placed under the price.
 */
add_action( 'woocommerce_single_product_summary', 'nh_output_cutting_tolerance_callout', 32 );
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
