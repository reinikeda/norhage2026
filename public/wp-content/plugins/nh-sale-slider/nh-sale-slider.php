<?php
/**
 * Plugin Name: Sale Slider
 * Description: Admin-managed date-ranged image slider shown on WooCommerce product category archives.
 * Version: 1.4.0
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

/**
 * Relative uploads path from a full attachment URL.
 * Strips query strings and WordPress size suffixes (-800x600, -scaled).
 *
 * @param string $url
 * @return string
 */
function nhss_uploads_relative_path($url) {
    $url = (string) $url;
    $qpos = strpos($url, '?');
    if ($qpos !== false) {
        $url = substr($url, 0, $qpos);
    }
    if ($url === '') {
        return '';
    }

    if (!preg_match('#/uploads/(.+)$#', $url, $m)) {
        return '';
    }

    $path = ltrim(rawurldecode($m[1]), '/');
    $path = preg_replace('/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', $path);
    $path = preg_replace('/-scaled(?=\.[a-z0-9]+$)/i', '', $path);

    return $path ?: '';
}

/**
 * Resolve a media-library attachment ID from a stored image URL.
 *
 * @param string $url
 * @return int
 */
function nhss_attachment_id_from_url($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return 0;
    }

    $id = attachment_url_to_postid($url);
    if ($id) {
        return (int) $id;
    }

    $path = nhss_uploads_relative_path($url);
    if ($path === '') {
        return 0;
    }

    global $wpdb;
    $found = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
        $path
    ));

    return $found ? (int) $found : 0;
}

/**
 * Media-library alt for a slide, with a translatable fallback.
 *
 * @param array $slide
 * @return string
 */
function nhss_slide_alt($slide) {
    $id = !empty($slide['image_id']) ? (int) $slide['image_id'] : 0;
    if (!$id && !empty($slide['image'])) {
        $id = nhss_attachment_id_from_url($slide['image']);
    }

    if ($id) {
        $alt = trim(wp_strip_all_tags((string) get_post_meta($id, '_wp_attachment_image_alt', true)));
        if ($alt !== '') {
            return $alt;
        }
    }

    return __('Sale', 'nh-sale-slider');
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
            $image   = esc_url_raw($slide['image']   ?? '');
            $image_m = esc_url_raw($slide['image_m'] ?? '');
            $image_id   = absint($slide['image_id']   ?? 0);
            $image_m_id = absint($slide['image_m_id'] ?? 0);

            if ($image && !$image_id) {
                $image_id = nhss_attachment_id_from_url($image);
            }
            if ($image_m && !$image_m_id) {
                $image_m_id = nhss_attachment_id_from_url($image_m);
            }
            if ($image === '') {
                $image_id = 0;
            }
            if ($image_m === '') {
                $image_m_id = 0;
            }

            $clean[$i] = [
                'image'      => $image,
                'image_m'    => $image_m,
                'image_id'   => $image_id,
                'image_m_id' => $image_m_id,
                'url'        => esc_url_raw($slide['url'] ?? ''),
                'start'      => preg_replace('~[^0-9\-]~', '', $slide['start'] ?? ''),
                'end'        => preg_replace('~[^0-9\-]~', '', $slide['end']   ?? ''),
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
                    $defaults = ['image'=>'','image_m'=>'','image_id'=>0,'image_m_id'=>0,'url'=>'','start'=>'','end'=>''];
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
                                <input class="nhss-id" type="hidden" name="sale_slider_data[<?php echo $i; ?>][image_id]" value="<?php echo esc_attr((int) $s['image_id']); ?>" />
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
                                <input class="nhss-id" type="hidden" name="sale_slider_data[<?php echo $i; ?>][image_m_id]" value="<?php echo esc_attr((int) $s['image_m_id']); ?>" />
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
add_action('woocommerce_after_shop_loop', function () {
    if (!function_exists('is_product_category') || !is_product_category()) return;

    $active = nhss_get_active_slides();
    if (empty($active)) return;

    echo '<div class="nh-sale-slider sale-slider-container swiper">';
    echo   '<div class="swiper-wrapper">';

    foreach ($active as $s) {
        $url        = !empty($s['url']) ? esc_url($s['url']) : '#';
        $img_desktop= esc_url($s['image'] ?? '');
        $img_mobile = !empty($s['image_m']) ? esc_url($s['image_m']) : '';
        $alt        = esc_attr(nhss_slide_alt($s));

        echo '<div class="swiper-slide">';
        echo   '<a class="sale-slide-link" href="'.$url.'">';
        echo     '<picture>';
        if ($img_mobile) echo '<source media="(max-width: 640px)" srcset="'.$img_mobile.'">';
        echo       '<img src="'.$img_desktop.'" alt="'.$alt.'" loading="lazy" decoding="async" />';
        echo     '</picture>';
        echo   '</a>';
        echo '</div>';
    }

    echo   '</div>';
    echo   '<div class="swiper-pagination"></div>';
    echo '</div>';
}, 20);

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
