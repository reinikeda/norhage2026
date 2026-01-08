<?php
/**
 * Plugin Name: Tax Switcher
 * Description: Per-visitor VAT (inc/excl) switcher for WooCommerce. Appears only on product category archives and single product pages. Updates both native Woo prices and custom amounts without reload.
 * Author: Daiva Reinike
 * Version: 1.1.4
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

final class NHTaxSwitcher {
    const OPT_ENABLED   = 'nh_tax_switcher_enabled';
    const OPT_LABEL_IN  = 'nh_tax_switcher_label_incl';
    const OPT_LABEL_EX  = 'nh_tax_switcher_label_excl';
    const COOKIE_KEY    = 'nh_tax_display';              // 'incl' | 'excl'
    const ASSET_VER     = '1.1.4';
    const NONCE_ACTION  = 'nh-tax-switcher';

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);

        // Admin settings
        add_action('admin_init',  [$this, 'register_settings']);
        add_action('admin_menu',  [$this, 'add_settings_page']);

        // Front assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Archive placement (your CSS handles row layout)
        add_action('woocommerce_before_shop_loop', [$this, 'render_archive_switch'], 40);

        // SINGLE placement: inject switch right after <p class="price">...</p>
        add_action('wp', [$this, 'setup_single_injection']);

        // Hard override Woo option on allowed contexts (ensures numbers really change)
        add_filter('pre_option_woocommerce_tax_display_shop',  [$this, 'force_option_display_shop'],  9999);
        add_filter('pre_option_woocommerce_tax_display_cart',  [$this, 'force_option_display_cart'],  9999);

        // Compatibility helpers
        foreach (['woocommerce_tax_display_shop', 'wc_tax_display_shop'] as $f) {
            add_filter($f, [$this, 'filter_tax_display'], 9999, 1);
        }
        foreach (['woocommerce_tax_display_cart', 'wc_tax_display_cart'] as $f) {
            add_filter($f, [$this, 'filter_tax_display'], 9999, 1);
        }

        // Wrap Woo price html so we can swap via AJAX
        add_filter('woocommerce_get_price_html',            [$this, 'wrap_price_html'], 999, 2);
        add_filter('woocommerce_variable_price_html',       [$this, 'wrap_price_html'], 999, 2);
        add_filter('woocommerce_variable_sale_price_html',  [$this, 'wrap_price_html'], 999, 2);
        add_filter('woocommerce_get_variation_price_html',  [$this, 'wrap_price_html'], 999, 2);
        add_filter('woocommerce_variation_sale_price_html', [$this, 'wrap_price_html'], 999, 2);
        add_filter('woocommerce_variation_price_html',      [$this, 'wrap_price_html'], 999, 2);

        // Optional suffix (not cart/checkout)
        add_filter('woocommerce_get_price_suffix', [$this, 'maybe_add_price_suffix'], 10, 4);

        // AJAX
        add_action('wp_ajax_nh_tax_get_prices',        [$this, 'ajax_get_prices']);
        add_action('wp_ajax_nopriv_nh_tax_get_prices', [$this, 'ajax_get_prices']);
        add_action('wp_ajax_nh_tax_format_amounts',        [$this, 'ajax_format_amounts']);
        add_action('wp_ajax_nopriv_nh_tax_format_amounts', [$this, 'ajax_format_amounts']);
    }

    /** ===== Helpers ===== */

    public static function is_enabled(): bool {
        return (bool) get_option(self::OPT_ENABLED, true);
    }
    public static function label_in(): string {
        $d = __('Including VAT', 'nh-tax-switcher');
        return (string) get_option(self::OPT_LABEL_IN, $d);
    }
    public static function label_ex(): string {
        $d = __('Excluding VAT', 'nh-tax-switcher');
        return (string) get_option(self::OPT_LABEL_EX, $d);
    }
    public static function current_mode(): string {
        if (!empty($_COOKIE[self::COOKIE_KEY])) {
            return $_COOKIE[self::COOKIE_KEY] === 'excl' ? 'excl' : 'incl';
        }
        return 'incl';
    }

    /** Only category archives, single product, or our AJAX (+ Woo variation AJAX) */
    private static function is_allowed_context(): bool {
        if (is_admin()) return false;

        if (function_exists('is_product') && is_product()) return true;
        if (function_exists('is_product_category') && is_product_category()) return true;

        // WP admin-ajax.php
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';
            if ($action === 'nh_tax_get_prices' || $action === 'nh_tax_format_amounts') return true;

            // ✅ Woo variation ajax via admin-ajax
            if ($action === 'woocommerce_get_variation') return true;
        }

        // Woo wc-ajax endpoint (?wc-ajax=get_variation)
        if (defined('WC_DOING_AJAX') && WC_DOING_AJAX) {
            $wc_ajax = isset($_REQUEST['wc-ajax']) ? sanitize_text_field(wp_unslash($_REQUEST['wc-ajax'])) : '';
            if ($wc_ajax === 'get_variation') return true;
        }

        return false;
    }

    /** ===== Activation defaults ===== */
    public function activate() {
        if (get_option(self::OPT_ENABLED, null) === null) update_option(self::OPT_ENABLED, 1);
        if (get_option(self::OPT_LABEL_IN, null) === null) update_option(self::OPT_LABEL_IN, __('Including VAT', 'nh-tax-switcher'));
        if (get_option(self::OPT_LABEL_EX, null) === null) update_option(self::OPT_LABEL_EX, __('Excluding VAT', 'nh-tax-switcher'));
    }

    /** ===== Settings ===== */
    public function register_settings() {
        register_setting('nh_tax_switcher', self::OPT_ENABLED,  ['type'=>'boolean','default'=>true]);
        register_setting('nh_tax_switcher', self::OPT_LABEL_IN, ['type'=>'string','default'=>__('Including VAT','nh-tax-switcher')]);
        register_setting('nh_tax_switcher', self::OPT_LABEL_EX, ['type'=>'string','default'=>__('Excluding VAT','nh-tax-switcher')]);

        add_settings_section('nh_tax_switcher_main', __('Display', 'nh-tax-switcher'), function(){}, 'nh_tax_switcher');

        add_settings_field(self::OPT_ENABLED, __('Enable switcher', 'nh-tax-switcher'), function(){
            $val = self::is_enabled() ? 'checked' : '';
            echo '<label><input type="checkbox" name="'.esc_attr(self::OPT_ENABLED).'" value="1" '.$val.'> '.__('Show VAT switch on product category & single product pages','nh-tax-switcher').'</label>';
        }, 'nh_tax_switcher', 'nh_tax_switcher_main');

        add_settings_field(self::OPT_LABEL_IN, __('“Including VAT” text', 'nh-tax-switcher'), function(){
            printf('<input type="text" name="%s" value="%s" class="regular-text">', esc_attr(self::OPT_LABEL_IN), esc_attr(self::label_in()));
        }, 'nh_tax_switcher', 'nh_tax_switcher_main');

        add_settings_field(self::OPT_LABEL_EX, __('“Excluding VAT” text', 'nh-tax-switcher'), function(){
            printf('<input type="text" name="%s" value="%s" class="regular-text">', esc_attr(self::OPT_LABEL_EX), esc_attr(self::label_ex()));
        }, 'nh_tax_switcher', 'nh_tax_switcher_main');
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('NH Tax Switcher', 'nh-tax-switcher'),
            __('NH Tax Switcher', 'nh-tax-switcher'),
            'manage_woocommerce',
            'nh-tax-switcher',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() { ?>
        <div class="wrap">
            <h1><?php esc_html_e('NH Tax Switcher', 'nh-tax-switcher'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('nh_tax_switcher');
                do_settings_sections('nh_tax_switcher');
                submit_button();
                ?>
            </form>
        </div>
    <?php }

    /** ===== Front assets ===== */
    public function enqueue_assets() {
        if (!class_exists('WooCommerce')) return;
        if (!self::is_enabled()) return;
        if (!self::is_allowed_context()) return;

        wp_enqueue_style('nh-tax-switcher', plugins_url('assets/css/style.css', __FILE__), [], self::ASSET_VER);
        wp_enqueue_script('nh-tax-switcher', plugins_url('assets/js/switch.js', __FILE__), [], self::ASSET_VER, true);

        wp_localize_script('nh-tax-switcher', 'NHTaxSwitcher', [
            'cookieKey'    => self::COOKIE_KEY,
            'mode'         => self::current_mode(),
            'label_in'     => self::label_in(),
            'label_ex'     => self::label_ex(),
            'enabled'      => self::is_enabled(),
            'reload'       => false,
            'cookie_days'  => 365,
            'ajax_url'     => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce(self::NONCE_ACTION),
        ]);
    }

    /** ===== Archive injector ===== */
    public function render_archive_switch() {
        if (!self::is_enabled() || !function_exists('is_product_category') || !is_product_category()) return;
        echo $this->switch_markup('archive');
    }

    /** ===== SINGLE: buffer summary and inject switch after price ===== */
    public function setup_single_injection() {
        if (!function_exists('is_product') || !is_product()) return;
        if (!self::is_enabled()) return;

        // prevent any old placements
        foreach ([5,10,11,15,20,21,25,30,40,50,60,70,80,90,100] as $p) {
            remove_action('woocommerce_single_product_summary', [$this, 'render_single_switch'], $p);
        }

        // Buffer ONLY summary area
        add_action('woocommerce_before_single_product_summary', [$this, 'single_summary_buffer_start'], 0);
        add_action('woocommerce_after_single_product_summary',  [$this, 'single_summary_buffer_end'],   9999);
    }

    public function single_summary_buffer_start() {
        ob_start();
    }

    public function single_summary_buffer_end() {
        $html = ob_get_clean();

        $switch = $this->switch_markup('single');

        // Insert right after the first <p class="price">...</p>
        $new = preg_replace(
            '#(<p[^>]*class="price"[^>]*>.*?</p>)#s',
            '$1' . $switch,
            $html,
            1,
            $count
        );

        if (!$count) {
            // fallback if theme doesn’t output <p class="price"> in summary
            $new = $html . $switch;
        }

        echo $new;
    }

    // kept for compatibility with remove_action
    public function render_single_switch() {
        if (!self::is_enabled() || !function_exists('is_product') || !is_product()) return;
        echo $this->switch_markup('single');
    }

    private function switch_markup($context = 'archive'): string {
        $mode = self::current_mode();
        ob_start(); ?>
        <div class="nh-tax-bar nh-tax-bar--<?php echo esc_attr($context); ?>">
            <div class="nh-tax-switch-wrap" role="group" aria-label="<?php esc_attr_e('VAT display switch','nh-tax-switcher'); ?>">
                <span class="nh-tax-label"><?php esc_html_e('Show prices with VAT:', 'nh-tax-switcher'); ?></span>
                <button type="button"
                        class="nh-tax-switch"
                        aria-pressed="<?php echo $mode === 'incl' ? 'true' : 'false'; ?>"
                        data-current="<?php echo esc_attr($mode); ?>"
                        data-on="<?php echo esc_attr(self::label_in()); ?>"
                        data-off="<?php echo esc_attr(self::label_ex()); ?>">
                    <span class="nh-tax-slider" aria-hidden="true"></span>
                    <span class="nh-tax-visuallyhidden"><?php echo $mode === 'incl' ? esc_html(self::label_in()) : esc_html(self::label_ex()); ?></span>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /** ===== Hard option override ===== */
    public function force_option_display_shop($value) {
        if (!self::is_allowed_context()) return $value;
        return (self::current_mode() === 'incl') ? 'incl' : 'excl';
    }
    public function force_option_display_cart($value) {
        if (!self::is_allowed_context()) return $value;
        return (self::current_mode() === 'incl') ? 'incl' : 'excl';
    }
    public function filter_tax_display($display) {
        if (!self::is_allowed_context()) return $display;
        return (self::current_mode() === 'incl') ? 'incl' : 'excl';
    }

    /** Wrap Woo price HTML */
    public function wrap_price_html($price_html, $product) {
        if (!self::is_allowed_context()) return $price_html;
        if (!is_object($product) || !method_exists($product, 'get_id')) return $price_html;
        if (strpos($price_html, 'class="nh-tax-price"') !== false) return $price_html;

        $id = $product->get_id();
        return '<span class="nh-tax-price" data-product-id="'.esc_attr($id).'">'.$price_html.'</span>';
    }

    /** Optional suffix */
    public function maybe_add_price_suffix($suffix, $product, $price, $qty) {
        if (is_cart() || is_checkout()) return $suffix;
        if (!self::is_allowed_context()) return $suffix;

        $mode  = self::current_mode();
        $label = ($mode === 'incl') ? self::label_in() : self::label_ex();

        if (strip_tags($suffix) === '') {
            $suffix = ' <small class="price-tax-note">(' . esc_html($label) . ')</small>';
        }
        return $suffix;
    }

    /** AJAX: prices */
    public function ajax_get_prices() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $mode = (isset($_POST['mode']) && $_POST['mode'] === 'excl') ? 'excl' : 'incl';
        $ids  = isset($_POST['ids']) ? array_map('absint', (array) $_POST['ids']) : [];

        if (empty($ids)) wp_send_json_success(['prices' => []]);

        $force = function() use ($mode){ return $mode; };
        add_filter('pre_option_woocommerce_tax_display_shop', $force, 9999);
        add_filter('pre_option_woocommerce_tax_display_cart', $force, 9999);

        $out = [];
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if (!$product) continue;

            $html = wc_get_price_html($product);

            if (strpos($html, 'class="nh-tax-price"') !== false) {
                $html = preg_replace('#^<span class="nh-tax-price"[^>]*>(.*)</span>$#s', '$1', $html);
            }

            $out[$id] = $html;
        }

        remove_filter('pre_option_woocommerce_tax_display_shop', $force, 9999);
        remove_filter('pre_option_woocommerce_tax_display_cart', $force, 9999);

        wp_send_json_success(['prices' => $out]);
    }

    /** AJAX: custom amounts */
    public function ajax_format_amounts() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $mode  = (isset($_POST['mode']) && $_POST['mode'] === 'excl') ? 'excl' : 'incl';
        $items = isset($_POST['items']) ? wp_unslash($_POST['items']) : '[]';
        if (is_string($items)) $items = json_decode($items, true);
        if (!is_array($items)) $items = [];

        $force = function() use ($mode){ return $mode; };
        add_filter('pre_option_woocommerce_tax_display_shop', $force, 9999);
        add_filter('pre_option_woocommerce_tax_display_cart', $force, 9999);

        $out = [];

        foreach ($items as $row) {
            $key   = isset($row['id']) ? sanitize_text_field($row['id']) : '';
            $pid   = isset($row['product_id']) ? absint($row['product_id']) : 0;
            $price = isset($row['price']) ? floatval($row['price']) : 0.0;

            if ($key === '' || !$pid) continue;

            $product = wc_get_product($pid);
            if (!$product) continue;

            $display = wc_get_price_to_display($product, ['price' => $price]);
            $out[$key] = wc_price($display);
        }

        remove_filter('pre_option_woocommerce_tax_display_shop', $force, 9999);
        remove_filter('pre_option_woocommerce_tax_display_cart', $force, 9999);

        wp_send_json_success(['formatted' => $out]);
    }
}

new NHTaxSwitcher();
