<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NH_FAQ_Topics_Order {
    const META_KEY = 'nh_order';

    public function __construct() {
        add_action('init', [$this,'register_term_meta']);

        // Admin list: add "Order" column with a drag handle
        add_filter('manage_edit-nh_faq_topic_columns', [$this,'add_column']);
        add_action('manage_nh_faq_topic_custom_column', [$this,'print_column'], 10, 3);

        // Assets + sortable JS
        add_action('admin_enqueue_scripts', [$this,'enqueue_admin_assets']);

        // Save order
        add_action('wp_ajax_nh_faq_sort_topics', [$this,'ajax_save_order']);

        // Use this order by default everywhere
        add_filter('get_terms_args', [$this,'force_terms_order'], 10, 2);
    }

    public function register_term_meta() {
        register_term_meta('nh_faq_topic', self::META_KEY, [
            'type' => 'integer', 'single' => true, 'default' => 0,
            'show_in_rest' => true, 'auth_callback' => '__return_true',
        ]);
    }

    public function add_column( $cols ) {
        // Put "Order" first
        return ['nh_order' => __('Order','nh-faq')] + $cols;
    }

    public function print_column( $content, $column, $term_id ) {
        if ( $column !== 'nh_order' ) return;
        echo '<span class="nh-faq-term-handle dashicons dashicons-move" title="'.esc_attr__('Drag to reorder','nh-faq').'"></span>';
        echo '<input type="hidden" class="nh-faq-term-order" value="'.esc_attr((int)get_term_meta($term_id,self::META_KEY,true)).'">';
    }

    public function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'edit-tags.php' ) return;
        if ( empty($_GET['taxonomy']) || $_GET['taxonomy'] !== 'nh_faq_topic' ) return;

        wp_enqueue_script('jquery-ui-sortable');

        // Inline CSS
        add_action('admin_head', function () {
            echo '<style>
                .column-nh_order{width:70px}
                .nh-faq-term-handle{cursor:move;opacity:.8}
                #the-list tr.ui-sortable-helper{background:#fff7e6}
                #the-list tr.placeholder-row td{background:#f0f6fc;border-top:2px dashed #c4d2e7}
            </style>';
        });

        // Inline JS (nowdoc to avoid PHP variable interpolation)
        $nonce = wp_create_nonce('nh_faq_order_terms');
        $js = <<<'JS'
        jQuery(function($){
          var $tbody = $('#the-list');
          if (!$tbody.length) return;
          if ($tbody.data('nhFaqSortable')) return;
          $tbody.data('nhFaqSortable', true);

          function fixWidthHelper(e, tr){
            var $orig = tr.children(), $h = tr.clone();
            $h.children().each(function(i){ $(this).width($orig.eq(i).width()); });
            return $h;
          }

          $tbody.sortable({
            items: '> tr',
            handle: '.nh-faq-term-handle',
            helper: fixWidthHelper,
            placeholder: 'placeholder-row',
            forcePlaceholderSize: true,
            axis: 'y',
            update: function(){
              var order = [];
              $tbody.find('> tr').each(function(){
                var id = $(this).attr('id'); // e.g. tag-123
                if (id) {
                  var termId = id.replace('tag-','');
                  if ($.isNumeric(termId)) order.push(termId);
                }
              });
              $.post(ajaxurl, {
                action: 'nh_faq_sort_topics',
                nonce: '__NONCE__',
                order: order
              });
            }
          }).disableSelection();
        });
        JS;
        $js = str_replace('__NONCE__', $nonce, $js);
        wp_add_inline_script('jquery-ui-sortable', $js);
    }

    public function ajax_save_order() {
        if ( ! current_user_can('manage_categories') ) wp_send_json_error('forbidden', 403);
        check_ajax_referer('nh_faq_order_terms','nonce');

        $order = isset($_POST['order']) ? (array) $_POST['order'] : [];
        $i = 1;
        foreach ( $order as $term_id ) {
            $tid = (int) $term_id;
            if ( $tid > 0 ) update_term_meta( $tid, self::META_KEY, $i++ );
        }
        wp_send_json_success(['updated' => count($order)]);
    }

    public function force_terms_order( $args, $taxonomies ) {
        if ( ! in_array('nh_faq_topic', (array) $taxonomies, true) ) return $args;
        if ( is_admin() && ! empty($_GET['orderby']) ) return $args; // respect manual sorting in admin
        if ( empty($args['orderby']) ) {
            $args['meta_key'] = self::META_KEY;
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'ASC';
        }
        return $args;
    }
}
