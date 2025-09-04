<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NH_FAQ_CPT {

    public static function register() {
        // CPT: nh_faq
        $labels = [
            'name'               => 'FAQs',
            'singular_name'      => 'FAQ',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New FAQ',
            'edit_item'          => 'Edit FAQ',
            'new_item'           => 'New FAQ',
            'view_item'          => 'View FAQ',
            'search_items'       => 'Search FAQs',
            'not_found'          => 'No FAQs found',
            'menu_name'          => 'FAQs',
        ];
        register_post_type('nh_faq', [
            'labels'       => $labels,
            'public'       => false,
            'show_ui'      => true,
            'menu_icon'    => 'dashicons-editor-help',
            'supports'     => ['title','editor','revisions'],
            'show_in_rest' => true,
        ]);

        // Optional taxonomy for topics (you can use or ignore)
        register_taxonomy('nh_faq_topic', 'nh_faq', [
            'label'            => 'FAQ Topics',
            'public'           => false,
            'show_ui'          => true,
            'hierarchical'     => true,
            'show_in_rest'     => true,
            'show_admin_column'=> true, // <— so Topics show as a column
        ]);
    }
}
