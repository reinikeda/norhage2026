<?php
/**
 * NH Important Notes - Frontend Output
 *
 * Appends notes to the product description tab content.
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
