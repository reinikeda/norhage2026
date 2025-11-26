<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NH_FAQ_Woo {

    public function __construct() {
        if ( class_exists( 'WooCommerce' ) ) {
            // Add the FAQ tab to single product pages (no reordering logic)
            add_filter( 'woocommerce_product_tabs', [ $this, 'add_faq_tab' ], 98 );
        }
    }

    /**
     * Add "Questions & Answers" tab if the product has FAQs.
     * Does not rearrange other tabs; uses a fixed, sensible priority.
     *
     * @param array $tabs
     * @return array
     */
    public function add_faq_tab( $tabs ) {
        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return $tabs;
        }

        $product_id = $product->get_id();

        // Only add the tab if there are FAQs for this product
        if ( ! $this->has_product_faqs( $product_id ) ) {
            return $tabs;
        }

        // Fixed priority (between Additional Info ~20 and Reviews ~30). Change via filter if needed.
        $priority = apply_filters( 'nh_faq_tab_priority', 25, $tabs );

        $tabs['nh_faq'] = [
            'title'    => apply_filters(
                'nh_faq_tab_title',
                __( 'Questions & Answers', 'nh-faq' )
            ),
            'priority' => $priority,
            'callback' => [ $this, 'render_faq_tab' ],
        ];

        return $tabs;
    }

    /**
     * Render the tab contents using the plugin's renderer (accordion + schema).
     */
    public function render_faq_tab() {
        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $renderer = new NH_FAQ_Render();

        echo $renderer->shortcode( [
            'scope'      => 'product',
            'product_id' => $product->get_id(),
            'accordion'  => '1',
            'schema'     => '1',
            'limit'      => 'all',
        ] );
    }

    /**
     * Check if at least one FAQ is attached to this product.
     * Uses the serialized array pattern for meta LIKE match: i:{ID};
     *
     * @param int $product_id
     * @return bool
     */
    private function has_product_faqs( $product_id ) {
        $product_id = absint( $product_id );
        if ( ! $product_id ) {
            return false;
        }

        $ids = get_posts( [
            'post_type'      => 'nh_faq',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'numberposts'    => 1,        // just need to know if at least one exists
            'no_found_rows'  => true,
            'meta_query'     => [[
                'key'     => 'nh_products',
                'value'   => 'i:' . $product_id . ';', // match serialized integer in array
                'compare' => 'LIKE',
            ]],
        ] );

        return ! empty( $ids );
    }
}
