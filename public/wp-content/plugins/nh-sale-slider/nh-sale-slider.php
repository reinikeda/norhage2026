<?php
/**
 * Plugin Name: Sale Slider
 * Description: Admin-managed date-ranged image slider shown on product category archives.
 * Version: 1.0.0
 * Author: Daiva Reinike
 */

if (!defined('ABSPATH')) exit;

const NH_SALE_SLIDER_OPT = 'nh_sale_slider_data';

add_action('admin_menu', function () {
    add_menu_page(
        'Sale Slider',
        'Sale Slider',
        'manage_options',
        'nh-sale-slider',
        'nh_sale_slider_settings_page',
        'dashicons-images-alt2',
        20
    );
});

function nh_sale_slider_settings_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['nh_sale_slider_nonce']) && wp_verify_nonce($_POST['nh_sale_slider_nonce'], 'nh_sale_slider_save')) {
        $raw = $_POST['sale_slider_data'] ?? [];
        $clean = [];

        foreach (range(0, 4) as $i) {
            $slide = $raw[$i] ?? [];
            $clean[$i] = [
                'image' => esc_url_raw($slide['image'] ?? ''),
                'url'   => esc_url_raw($slide['url'] ?? ''),
                'start' => preg_replace('~[^0-9\-]~', '', $slide['start'] ?? ''), // YYYY-MM-DD
                'end'   => preg_replace('~[^0-9\-]~', '', $slide['end'] ?? ''),
            ];
        }

        update_option(NH_SALE_SLIDER_OPT, $clean, false); // autoload = false
        echo '<div class="updated"><p>Slider saved!</p></div>';
    }

    $slides = get_option(NH_SALE_SLIDER_OPT, []);
    ?>
    <div class="wrap">
        <h1>Sale Slider</h1>
        <form method="post">
            <?php wp_nonce_field('nh_sale_slider_save', 'nh_sale_slider_nonce'); ?>
            <table class="form-table">
                <tbody>
                <?php for ($i = 0; $i < 5; $i++):
                    $slide = $slides[$i] ?? ['image'=>'','url'=>'','start'=>'','end'=>''];
                ?>
                    <tr><th colspan="2"><h2>Slide <?php echo $i+1; ?></h2></th></tr>
                    <tr>
                        <td style="width:50%;">
                            <label>Image URL</label><br>
                            <input type="text" name="sale_slider_data[<?php echo $i; ?>][image]" value="<?php echo esc_attr($slide['image']); ?>" style="width:100%;">
                            <br><small><a href="<?php echo esc_url(admin_url('media-new.php')); ?>" target="_blank">Upload image</a></small>
                        </td>
                        <td>
                            <label>Link URL</label><br>
                            <input type="url" name="sale_slider_data[<?php echo $i; ?>][url]" value="<?php echo esc_attr($slide['url']); ?>" style="width:100%;"><br>
                            <label>Start Date</label><br>
                            <input type="date" name="sale_slider_data[<?php echo $i; ?>][start]" value="<?php echo esc_attr($slide['start']); ?>"><br>
                            <label>End Date</label><br>
                            <input type="date" name="sale_slider_data[<?php echo $i; ?>][end]" value="<?php echo esc_attr($slide['end']); ?>">
                        </td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
            <p><input type="submit" class="button button-primary" value="Save Slider"></p>
        </form>
    </div>
    <?php
}

/** Front-end render on product category archives */
add_action('woocommerce_before_shop_loop', function () {
    if (!function_exists('is_product_category') || !is_product_category()) return;

    $slides = get_option(NH_SALE_SLIDER_OPT, []);
    if (empty($slides)) return;

    $today = current_time('Y-m-d'); // honors WP timezone setting
    $active = array_filter($slides, function ($s) use ($today) {
        if (empty($s['image'])) return false;
        if (!empty($s['start']) && $today < $s['start']) return false;
        if (!empty($s['end'])   && $today > $s['end'])   return false;
        return true;
    });

    if (!$active) return;

    echo '<div class="sale-slider-container swiper"><div class="swiper-wrapper">';
    foreach ($active as $s) {
        $url = !empty($s['url']) ? esc_url($s['url']) : '#';
        echo '<div class="swiper-slide"><a href="'.$url.'"><img src="'.esc_url($s['image']).'" alt="" /></a></div>';
    }
    echo '</div><div class="swiper-pagination"></div></div>';
}, 5);

/** Enqueue Swiper only on product category archives where we render */
add_action('wp_enqueue_scripts', function () {
    if (!function_exists('is_product_category') || !is_product_category()) return;

    wp_enqueue_style('swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], null);
    wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], null, true);

    wp_add_inline_script('swiper-js', 'document.addEventListener("DOMContentLoaded",function(){if(document.querySelector(".sale-slider-container")){new Swiper(".sale-slider-container",{loop:true,autoplay:{delay:4000},slidesPerView:1,pagination:{el:".swiper-pagination",clickable:true},spaceBetween:20});}});');
});
