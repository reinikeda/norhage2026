<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NH_FAQ_CPT {

    public static function register() {

        /**
         * ------------------------------------------------------------------
         * Custom, translatable slug
         * ------------------------------------------------------------------
         * Same style we used for your SALE and SERVICES CPT slugs.
         * You can override this slug using:
         *
         * add_filter('nh_faq_slug', function(){ return 'klausimai'; });
         *
         * Slug becomes fully translatable through .po file.
         */
        $slug = apply_filters(
            'nh_faq_slug',
            sanitize_title( _x( 'faq', 'Default FAQ slug', 'nh-faq' ) )
        );

        /**
         * ------------------------------------------------------------------
         * CPT labels (translatable)
         * ------------------------------------------------------------------
         */
        $labels = [
            'name'               => __( 'FAQs', 'nh-faq' ),
            'singular_name'      => __( 'FAQ', 'nh-faq' ),
            'add_new'            => __( 'Add New', 'nh-faq' ),
            'add_new_item'       => __( 'Add New FAQ', 'nh-faq' ),
            'edit_item'          => __( 'Edit FAQ', 'nh-faq' ),
            'new_item'           => __( 'New FAQ', 'nh-faq' ),
            'view_item'          => __( 'View FAQ', 'nh-faq' ),
            'search_items'       => __( 'Search FAQs', 'nh-faq' ),
            'not_found'          => __( 'No FAQs found', 'nh-faq' ),
            'menu_name'          => __( 'FAQs', 'nh-faq' ),
        ];

        register_post_type( 'nh_faq', [
            'labels'        => $labels,
            'public'        => true,
            'show_ui'       => true,
            'menu_icon'     => 'dashicons-editor-help',
            'supports'      => [ 'title', 'editor', 'revisions' ],
            'show_in_rest'  => true,

            // Add slug here just like SALE / SERVICES CPT
            'rewrite'       => [
                'slug'       => $slug,
                'with_front' => false,
            ],
            'has_archive'   => false,
            'publicly_queryable' => true,
        ]);


        /**
         * ------------------------------------------------------------------
         * Optional taxonomy for Topics (translatable)
         * ------------------------------------------------------------------
         */
        $topic_labels = [
            'name'              => __( 'FAQ Topics', 'nh-faq' ),
            'singular_name'     => __( 'FAQ Topic', 'nh-faq' ),
            'search_items'      => __( 'Search Topics', 'nh-faq' ),
            'all_items'         => __( 'All Topics', 'nh-faq' ),
            'edit_item'         => __( 'Edit Topic', 'nh-faq' ),
            'update_item'       => __( 'Update Topic', 'nh-faq' ),
            'add_new_item'      => __( 'Add New Topic', 'nh-faq' ),
            'new_item_name'     => __( 'New Topic Name', 'nh-faq' ),
            'menu_name'         => __( 'FAQ Topics', 'nh-faq' ),
        ];

        register_taxonomy( 'nh_faq_topic', 'nh_faq', [
            'labels'            => $topic_labels,
            'public'            => false,
            'show_ui'           => true,
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
        ] );
    }
}
