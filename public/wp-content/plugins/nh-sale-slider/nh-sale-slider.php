<?php
/**
 * Plugin Name: Sale Slider
 * Description: Admin-managed date-ranged image slider shown on WooCommerce product category archives.
 * Version: 1.3.0
 * Author: Daiva Reinike
 * Text Domain: nh-sale-slider
 */

if (!defined('ABSPATH')) exit;

define('NH_SALE_SLIDER_OPT', 'nh_sale_slider_data');

/* -----------------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------------- */
function nhss_today() {
    return current_time('Y-m-d'); // respects WP timezone
}
function nhss_plugin_url($path = '') {
    return plugin_dir_url(__FILE__) . ltrim($path, '/');
}
function nhss_plugin_path($path = '') {
    return plugin_dir_path(__FILE__) . ltrim($path, '/');
}
function nhss_version($rel_path) {
    $file = nhss_plugin_path($rel_path);
    return file_exists($file) ? filemtime($file) : '1.0';
}
function nhss_get_slides_raw() {
    $slides = get_option(NH_SALE_SLIDER_OPT, []);
    return is_array($slides) ? $slides : [];
}
function nhss_get_active_slides() {
    $slides = nhss_get_slides_raw();
    if (empty($slides)) return [];
    $today = nhss_today();

    $active = array_filter($slides, function ($s) use ($today) {
        $img   = isset($s['image']) && $s['image'] !== '' ? $s['image'] : '';
        if ($img === '') return false;

        $start = isset($s['start']) ? trim($s['start']) : '';
        $end   = isset($s['end'])   ? trim($s['end'])   : '';

        if ($start !== '' && $today < $start) return false;
        if ($end   !== '' && $today > $end)   return false;
        return true;
    });

    return array_values($active);
}

/* -----------------------------------------------------------------------
 * Admin Page
 * --------------------------------------------------------------------- */
add_action('admin_menu', function () {
    add_menu_page(
        __('Sale Slider', 'nh-sale-slider'),
        __('Sale Slider', 'nh-sale-slider'),
        'manage_options',
        'nh-sale-slider',
        'nhss_settings_page',
        'dashicons-images-alt2',
        20
    );
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_nh-sale-slider') return;

    // WP Media modal
    wp_enqueue_media();

    // Our admin assets
    wp_enqueue_style(
        'nhss-admin',
        nhss_plugin_url('assets/css/admin.css'),
        [],
        nhss_version('assets/css/admin.css')
    );
    wp_enqueue_script(
        'nhss-admin',
        nhss_plugin_url('assets/js/admin.js'),
        ['jquery'],
        nhss_version('assets/js/admin.js'),
        true
    );
});

function nhss_settings_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['nh_sale_slider_nonce']) && wp_verify_nonce($_POST['nh_sale_slider_nonce'], 'nh_sale_slider_save')) {
        $raw = isset($_POST['sale_slider_data']) && is_array($_POST['sale_slider_data']) ? $_POST['sale_slider_data'] : [];
        $clean = [];

        foreach (range(0, 4) as $i) {
            $slide = isset($raw[$i]) && is_array($raw[$i]) ? $raw[$i] : [];
            $clean[$i] = [
                'image'   => esc_url_raw($slide['image']   ?? ''),
                'image_m' => esc_url_raw($slide['image_m'] ?? ''), // optional
                'url'     => esc_url_raw($slide['url']     ?? ''),
                'start'   => preg_replace('~[^0-9\-]~', '', $slide['start'] ?? ''),
                'end'     => preg_replace('~[^0-9\-]~', '', $slide['end']   ?? ''),
            ];
        }

        update_option(NH_SALE_SLIDER_OPT, $clean, false);
        echo '<div class="updated"><p>'.esc_html__('Slider saved!', 'nh-sale-slider').'</p></div>';
    }

    $slides = nhss_get_slides_raw();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Sale Slider', 'nh-sale-slider'); ?></h1>
        <form method="post">
            <?php wp_nonce_field('nh_sale_slider_save', 'nh_sale_slider_nonce'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                <?php for ($i = 0; $i < 5; $i++):
                    $defaults = ['image'=>'','image_m'=>'','url'=>'','start'=>'','end'=>''];
                    $s = isset($slides[$i]) && is_array($slides[$i]) ? array_merge($defaults, $slides[$i]) : $defaults;

                    $desk = esc_attr($s['image']);
                    $mob  = esc_attr($s['image_m']);
                ?>
                    <tr><th colspan="2"><h2><?php echo sprintf(esc_html__('Slide %d', 'nh-sale-slider'), $i+1); ?></h2></th></tr>
                    <tr>
                        <td style="width:50%; vertical-align:top;">
                            <div class="nhss-media-wrap">
                                <label><strong><?php esc_html_e('Image URL (Desktop, 3:1)', 'nh-sale-slider'); ?></strong></label><br>
                                <input class="nhss-url" type="text" name="sale_slider_data[<?php echo $i; ?>][image]" value="<?php echo $desk; ?>" style="width:100%;" />
                                <div class="nhss-controls">
                                    <button type="button" class="button nhss-media-select"><?php esc_html_e('Select image', 'nh-sale-slider'); ?></button>
                                    <button type="button" class="button nhss-media-remove"><?php esc_html_e('Remove', 'nh-sale-slider'); ?></button>
                                    <span class="description"><?php esc_html_e('Recommended: 1200×400 px (3:1)', 'nh-sale-slider'); ?></span>
                                </div>
                                <img class="nhss-preview" src="<?php echo $desk ? esc_url($s['image']) : ''; ?>" alt="" <?php echo $desk ? '' : 'style="display:none"'; ?> />
                            </div>

                            <br>

                            <div class="nhss-media-wrap">
                                <label><strong><?php esc_html_e('Image URL (Mobile, optional, 4:3)', 'nh-sale-slider'); ?></strong></label><br>
                                <input class="nhss-url" type="text" name="sale_slider_data[<?php echo $i; ?>][image_m]" value="<?php echo $mob; ?>" style="width:100%;" />
                                <div class="nhss-controls">
                                    <button type="button" class="button nhss-media-select"><?php esc_html_e('Select image', 'nh-sale-slider'); ?></button>
                                    <button type="button" class="button nhss-media-remove"><?php esc_html_e('Remove', 'nh-sale-slider'); ?></button>
                                    <span class="description"><?php esc_html_e('Recommended: 800×600 px (4:3). If empty, desktop is used.', 'nh-sale-slider'); ?></span>
                                </div>
                                <img class="nhss-preview" src="<?php echo $mob ? esc_url($s['image_m']) : ''; ?>" alt="" <?php echo $mob ? '' : 'style="display:none"'; ?> />
                            </div>
                        </td>
                        <td style="vertical-align:top;">
                            <label><strong><?php esc_html_e('Link URL', 'nh-sale-slider'); ?></strong></label><br>
                            <input type="url" name="sale_slider_data[<?php echo $i; ?>][url]" value="<?php echo esc_attr($s['url']); ?>" style="width:100%;"><br><br>

                            <label><strong><?php esc_html_e('Start Date', 'nh-sale-slider'); ?></strong></label><br>
                            <input type="date" name="sale_slider_data[<?php echo $i; ?>][start]" value="<?php echo esc_attr($s['start']); ?>"><br><br>

                            <label><strong><?php esc_html_e('End Date', 'nh-sale-slider'); ?></strong></label><br>
                            <input type="date" name="sale_slider_data[<?php echo $i; ?>][end]" value="<?php echo esc_attr($s['end']); ?>">
                        </td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
            <p><input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Slider', 'nh-sale-slider'); ?>"></p>
        </form>
    </div>
    <?php
}

/* -----------------------------------------------------------------------
 * Front-end render
 * --------------------------------------------------------------------- */
add_action('woocommerce_before_shop_loop', function () {
    if (!function_exists('is_product_category') || !is_product_category()) return;

    $active = nhss_get_active_slides();
    if (empty($active)) return;

    echo '<div class="nh-sale-slider sale-slider-container swiper">';
    echo   '<div class="swiper-wrapper">';

    foreach ($active as $s) {
        $url        = !empty($s['url']) ? esc_url($s['url']) : '#';
        $img_desktop= esc_url($s['image'] ?? '');
        $img_mobile = !empty($s['image_m']) ? esc_url($s['image_m']) : '';

        echo '<div class="swiper-slide">';
        echo   '<a class="sale-slide-link" href="'.$url.'" aria-label="'.esc_attr__('Sale slide', 'nh-sale-slider').'">';
        echo     '<picture>';
        if ($img_mobile) echo '<source media="(max-width: 640px)" srcset="'.$img_mobile.'">';
        echo       '<img src="'.$img_desktop.'" alt="" loading="lazy" decoding="async" />';
        echo     '</picture>';
        echo   '</a>';
        echo '</div>';
    }

    echo   '</div>';
    echo   '<div class="swiper-pagination"></div>';
    echo '</div>';
}, 5);

/* -----------------------------------------------------------------------
 * Assets
 * --------------------------------------------------------------------- */
add_action('wp_enqueue_scripts', function () {
    if (!function_exists('is_product_category') || !is_product_category()) return;
    if (empty(nhss_get_active_slides())) return;

    // Swiper CDN
    wp_enqueue_style('swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], null);
    wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], null, true);

    // Our frontend assets
    wp_enqueue_style(
        'nhss-frontend',
        nhss_plugin_url('assets/css/frontend.css'),
        ['swiper-css'],
        nhss_version('assets/css/frontend.css')
    );
    wp_enqueue_script(
        'nhss-frontend',
        nhss_plugin_url('assets/js/frontend.js'),
        ['swiper-js'],
        nhss_version('assets/js/frontend.js'),
        true
    );
});
