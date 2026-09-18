<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once NHHB_PATH . 'includes/admin-fields.php';

class NHHB_Admin {
    public static function init() {
        add_action('init', [__CLASS__, 'register_section_cpt']);
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'maybe_save']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'front_assets']);
    }

    public static function register_section_cpt() {
        register_post_type('nh_section', [
            'labels' => [
                'name'          => 'Sections',
                'singular_name' => 'Section',
            ],
            'public'       => false,
            'show_ui'      => false,
            'show_in_menu' => false,
            'supports'     => ['title'],
        ]);
    }

    public static function add_menu() {
        add_menu_page(
            __('Home Builder', 'nhhb'),
            __('Home Builder', 'nhhb'),
            'edit_pages',
            'nhhb-home',
            [__CLASS__, 'render_page'],
            'dashicons-layout',
            3
        );
    }

    public static function maybe_save() {
        if (!isset($_POST['nhhb_save_layout'])) {
            return;
        }
        if (!isset($_POST['nhhb_nonce']) || !wp_verify_nonce($_POST['nhhb_nonce'], 'nhhb_save_layout')) {
            return;
        }
        if (!current_user_can('edit_pages')) {
            return;
        }

        $raw = isset($_POST['nhhb']) && is_array($_POST['nhhb']) ? wp_unslash($_POST['nhhb']) : [];
        $layout = [
            'inject'  => empty($raw['inject']) ? 0 : 1,
            'order'   => isset($raw['order']) && is_array($raw['order']) ? $raw['order'] : nhhb_default_order(),
            'enabled' => isset($raw['enabled']) && is_array($raw['enabled']) ? $raw['enabled'] : [],
            'data'    => isset($raw['data']) && is_array($raw['data']) ? $raw['data'] : [],
        ];
        update_option('nhhb_home', nhhb_normalize_layout($layout), false);

        wp_safe_redirect(add_query_arg([
            'page'    => 'nhhb-home',
            'updated' => '1',
        ], admin_url('admin.php')));
        exit;
    }

    public static function render_page() {
        if (!current_user_can('edit_pages')) {
            return;
        }

        $layout = nhhb_get_layout();
        $labels = nhhb_section_labels();
        echo '<div class="wrap nhhb-admin">';
        echo '<h1>' . esc_html__('Home Builder', 'nhhb') . '</h1>';
        if (!empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Homepage layout saved.', 'nhhb') . '</p></div>';
        }
        echo '<p>' . esc_html__('Sections are inserted on the homepage automatically, in this order. Titles and chrome use plugin translations unless you override them. Campaign images still need to be set per shop.', 'nhhb') . '</p>';

        echo '<form method="post">';
        wp_nonce_field('nhhb_save_layout', 'nhhb_nonce');
        echo '<p><label><input type="checkbox" name="nhhb[inject]" value="1" ' . checked(!empty($layout['inject']), true, false) . '> ';
        echo esc_html__('Insert these sections on the homepage automatically (no shortcodes needed).', 'nhhb') . '</label></p>';
        echo '<p><button type="button" class="button nhhb-reset-order">' . esc_html__('Reset to default order', 'nhhb') . '</button></p>';

        echo '<div class="nhhb-section-list" data-default-order="' . esc_attr(implode(',', nhhb_default_order())) . '">';
        foreach ($layout['order'] as $type) {
            $label = $labels[$type] ?? $type;
            $on = !empty($layout['enabled'][$type]);
            $data = $layout['data'][$type] ?? [];
            echo '<details class="nhhb-section-card" data-type="' . esc_attr($type) . '">';
            echo '<summary class="nhhb-section-head">';
            echo '<span class="nhhb-section-move">';
            echo '<button type="button" class="button nhhb-move-up" aria-label="' . esc_attr__('Move up', 'nhhb') . '">&uarr;</button>';
            echo '<button type="button" class="button nhhb-move-down" aria-label="' . esc_attr__('Move down', 'nhhb') . '">&darr;</button>';
            echo '</span>';
            echo '<label class="nhhb-section-enable" onclick="event.stopPropagation();">';
            echo '<input type="hidden" name="nhhb[enabled][' . esc_attr($type) . ']" value="0">';
            echo '<input type="checkbox" name="nhhb[enabled][' . esc_attr($type) . ']" value="1" ' . checked($on, true, false) . '> ';
            echo esc_html__('Enabled', 'nhhb');
            echo '</label>';
            echo '<strong class="nhhb-section-label">' . esc_html($label) . '</strong>';
            echo '<input type="hidden" class="nhhb-order-input" name="nhhb[order][]" value="' . esc_attr($type) . '">';
            echo '</summary>';
            echo '<div class="nhhb-section-body">';
            nhhb_admin_section_fields($type, $data);
            echo '</div></details>';
        }
        echo '</div>';

        submit_button(__('Save homepage', 'nhhb'), 'primary', 'nhhb_save_layout');
        echo '</form></div>';
    }

    public static function admin_assets($hook) {
        if ($hook !== 'toplevel_page_nhhb-home') {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        wp_enqueue_style('nhhb-admin', NHHB_URL . 'assets/css/admin.css', [], NHHB_VER);
        wp_enqueue_script('nhhb-admin', NHHB_URL . 'assets/js/admin.js', ['jquery'], NHHB_VER, true);
    }

    public static function front_assets() {
        wp_register_style('nhhb-core', NHHB_URL . 'assets/css/core.css', [], NHHB_VER);
        wp_register_style('nhhb-top-offers', NHHB_URL . 'assets/css/top-offers.css', ['nhhb-core'], NHHB_VER);
        wp_register_script('nhhb-top-offers', NHHB_URL . 'assets/js/top-offers.js', [], NHHB_VER, true);
        wp_register_style('nhhb-top-features', NHHB_URL . 'assets/css/top-features.css', ['nhhb-core'], NHHB_VER);
        wp_register_style('nhhb-browse-cats', NHHB_URL . 'assets/css/browse-cats.css', ['nhhb-core'], NHHB_VER);
        wp_register_script('nhhb-browse-cats', NHHB_URL . 'assets/js/browse-cats.js', [], NHHB_VER, true);
        wp_register_style('nhhb-new-arrivals', NHHB_URL . 'assets/css/new-arrivals.css', ['nhhb-core'], NHHB_VER);
        wp_register_style('nhhb-promo-trio', NHHB_URL . 'assets/css/promo-trio.css', ['nhhb-core'], NHHB_VER);
        wp_register_style('nhhb-newsletter', NHHB_URL . 'assets/css/newsletter.css', ['nhhb-core'], NHHB_VER);
        wp_register_style('nhhb-services', NHHB_URL . 'assets/css/services-slider.css', ['nhhb-core'], NHHB_VER);
        wp_register_script('nhhb-services', NHHB_URL . 'assets/js/services-slider.js', [], NHHB_VER, true);
        wp_register_style('nhhb-b2b', NHHB_URL . 'assets/css/b2b-banner.css', ['nhhb-core'], NHHB_VER);
        wp_register_style('nhhb-reviews', NHHB_URL . 'assets/css/reviews-slider.css', ['nhhb-core'], NHHB_VER);
        wp_register_script('nhhb-reviews', NHHB_URL . 'assets/js/reviews-slider.js', [], NHHB_VER, true);

        if (!is_front_page()) {
            return;
        }
        $layout = nhhb_get_layout();
        if (empty($layout['inject'])) {
            return;
        }
        wp_enqueue_style('nhhb-core');
        $map = [
            'top-offers'      => ['style' => 'nhhb-top-offers', 'script' => 'nhhb-top-offers'],
            'top-features'    => ['style' => 'nhhb-top-features'],
            'browse-cats'     => ['style' => 'nhhb-browse-cats', 'script' => 'nhhb-browse-cats'],
            'new-arrivals'    => ['style' => 'nhhb-new-arrivals'],
            'reviews-slider'  => ['style' => 'nhhb-reviews', 'script' => 'nhhb-reviews'],
            'promo-trio'      => ['style' => 'nhhb-promo-trio'],
            'services-slider' => ['style' => 'nhhb-services', 'script' => 'nhhb-services'],
            'newsletter'      => ['style' => 'nhhb-newsletter'],
            'b2b-banner'      => ['style' => 'nhhb-b2b'],
        ];
        foreach ($layout['order'] as $type) {
            if (empty($layout['enabled'][$type]) || empty($map[$type])) {
                continue;
            }
            if (!empty($map[$type]['style'])) {
                wp_enqueue_style($map[$type]['style']);
            }
            if (!empty($map[$type]['script'])) {
                wp_enqueue_script($map[$type]['script']);
            }
        }
    }
}

NHHB_Admin::init();
