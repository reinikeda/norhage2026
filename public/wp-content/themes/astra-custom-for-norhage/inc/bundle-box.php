<?php
if (!defined('ABSPATH')) exit;

/* ============================================================================
 * NH Bundle Box — renderer + (optional) combined notice endpoint
 * ========================================================================== */

/** Meta keys (your v2 structure + compat + legacy) */
if (!defined('NH_BUNDLE_META_KEY'))        define('NH_BUNDLE_META_KEY', '_nh_bundle_items_v2');
if (!defined('NH_BUNDLE_META_KEY_COMPAT')) define('NH_BUNDLE_META_KEY_COMPAT', '_nc_bundle_items_v2');
if (!defined('NH_BUNDLE_IDS_LEGACY'))      define('NH_BUNDLE_IDS_LEGACY', '_nc_bundle_item_ids');

/** Read bundle rows from meta */
if (!function_exists('nh_get_bundle_rows')) {
function nh_get_bundle_rows($product_id) {
    $rows = get_post_meta($product_id, NH_BUNDLE_META_KEY, true);
    if (!is_array($rows) || empty($rows)) $rows = get_post_meta($product_id, NH_BUNDLE_META_KEY_COMPAT, true);

    // Legacy: IDs only -> rows with max 99
    if (!is_array($rows) || empty($rows)) {
        $ids = get_post_meta($product_id, NH_BUNDLE_IDS_LEGACY, true);
        if (is_array($ids) && !empty($ids)) {
            $rows = array_map(function($id){ return ['id'=>(int)$id, 'max'=>99]; }, $ids);
        }
    }

    $out = [];
    if (is_array($rows)) {
        foreach ($rows as $r) {
            $id = isset($r['id']) ? (int) $r['id'] : 0;
            if ($id <= 0) continue;

            $row = ['id' => $id];
            if (array_key_exists('max', $r) && $r['max'] !== '') {
                $m = max(1, (int)$r['max']);
                if ($m > 0) $row['max'] = $m;
            }
            $out[] = $row;
        }
    }
    return $out;
}}

/** (Optional) AJAX: create one combined Woo notice after adds (your older flow) */
add_action('wc_ajax_nh_bundle_notice',        'nh_ajax_bundle_notice');
add_action('wc_ajax_nopriv_nh_bundle_notice', 'nh_ajax_bundle_notice');

if (!function_exists('nh_ajax_bundle_notice')) {
function nh_ajax_bundle_notice() {
    $raw   = isset($_POST['items']) ? wp_unslash($_POST['items']) : '[]';
    $items = json_decode($raw, true);
    if (!is_array($items)) $items = [];

    $parts      = [];   // lines: "4 × “Name”"
    $total_qty  = 0;    // N = total units
    $prod_count = 0;    // M = distinct products

    foreach ($items as $line) {
        $pid = isset($line['product_id']) ? absint($line['product_id']) : 0;
        $qty = isset($line['quantity'])   ? max(1, absint($line['quantity'])) : 1;
        if (!$pid) continue;

        $p = wc_get_product($pid);
        if ($p) {
            $prod_count++;
            $total_qty += $qty;
            $parts[] = sprintf('%d × “%s”', $qty, esc_html($p->get_name()));
        }
    }

    if (!empty($parts)) {
        // Build lead: "5 items have been added to your basket (2 products):"
        $lead_items = sprintf(
            esc_html( _n('%d item has been added to your basket', '%d items have been added to your basket', $total_qty, 'woocommerce') ),
            $total_qty
        );
        $lead_products = sprintf(
            esc_html( _n('(%d product):', '(%d products):', $prod_count, 'woocommerce') ),
            $prod_count
        );

        // Standard Woo forward button
        $button = sprintf(
            '<a href="%s" class="button wc-forward">%s</a>',
            esc_url(wc_get_cart_url()),
            esc_html__('View basket', 'woocommerce')
        );

        $msg = $button . ' ' . $lead_items . ' ' . $lead_products . '<br>' . implode('<br>', $parts);
        wc_add_notice($msg, 'success');
    }

    // Render notices to HTML and return
    ob_start();
    wc_print_notices(); // prints & clears
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}}

/** Render the bundle box under the product form */
add_action('woocommerce_after_add_to_cart_form', 'nh_render_bundle_box', 15);
if (!function_exists('nh_render_bundle_box')) {
function nh_render_bundle_box() {
    if (!is_product()) return;
    global $product; if (!$product instanceof WC_Product) return;

    $rows = nh_get_bundle_rows($product->get_id());
    if (empty($rows)) return;

    // Currency formatting data for your JS (totals)
    $currency_symbol = get_woocommerce_currency_symbol();
    $price_pos       = get_option('woocommerce_currency_pos', 'right_space');
    $decimals        = wc_get_price_decimals();
    $thousand_sep    = wc_get_price_thousand_separator();
    $decimal_sep     = wc_get_price_decimal_separator();

    echo '<section id="nc-complete-set" class="nc-bundle card" aria-labelledby="nc-bundle-title">';
    echo '<div class="nc-bundle-head"><h3 id="nc-bundle-title">'.esc_html__('Buy a complete set','nc').'</h3></div>';

    echo '<form id="nc-bundle-form" class="nc-bundle-form" role="group" aria-label="'.esc_attr__('Complete set add-ons','nc').'"'
        .' data-currency-symbol="'.esc_attr($currency_symbol).'"'
        .' data-currency-pos="'.esc_attr($price_pos).'"'
        .' data-decimals="'.esc_attr($decimals).'"'
        .' data-thousand="'.esc_attr($thousand_sep).'"'
        .' data-decimal="'.esc_attr($decimal_sep).'">';

    echo '<div class="nc-bundle-row nc-bundle-header" role="row">';
    echo '  <div class="nc-col nc-col-image">'.esc_html__('Image','nc').'</div>';
    echo '  <div class="nc-col nc-col-title">'.esc_html__('Product','nc').'</div>';
    echo '  <div class="nc-col nc-col-qty">'.esc_html__('Qty','nc').'</div>';
    echo '  <div class="nc-col nc-col-price">'.esc_html__('Price','nc').'</div>';
    echo '</div>';

    foreach ($rows as $r) {
        $p = wc_get_product($r['id']);
        if (!$p) continue;

        $price_html = $p->get_price_html();
        $price_num  = wc_get_price_to_display($p);
        $price_attr = wc_format_decimal($price_num, wc_get_price_decimals());
        $link       = get_permalink($p->get_id());

        $has_meta_max  = array_key_exists('max', $r) && $r['max'] !== '' && (int)$r['max'] > 0;
        $meta_max      = $has_meta_max ? (int)$r['max'] : null;

        $effective_max = is_null($meta_max) ? PHP_INT_MAX : $meta_max;
        if ($p->is_sold_individually()) $effective_max = min($effective_max, 1);

        if (!$p->backorders_allowed()) {
            if (!$p->is_in_stock()) {
                $effective_max = 0;
            } elseif ($p->managing_stock()) {
                $stock_qty = (int) max(0, (int) $p->get_stock_quantity());
                $effective_max = $stock_qty > 0 ? min($effective_max, $stock_qty) : 0;
            }
        }

        $effective_max = (int) max(0, $effective_max);
        $qty_disabled  = $effective_max <= 0 ? ' disabled' : '';
        $input_id      = 'bundle-qty-'.esc_attr($p->get_id());
        $maxAttr       = ($effective_max !== PHP_INT_MAX) ? ' max="'.esc_attr($effective_max).'"' : '';
        $dataMax       = ($effective_max !== PHP_INT_MAX) ? ' data-maxqty="'.esc_attr($effective_max).'"' : '';

        echo '<div class="nc-bundle-row" role="row" data-product-id="'.esc_attr($p->get_id()).'" data-base-price="'.esc_attr($price_attr).'">';
        echo '  <div class="nc-col nc-col-image" role="cell"><a href="'.esc_url($link).'" class="nc-thumb" aria-label="'.esc_attr($p->get_name()).'">'.$p->get_image('woocommerce_thumbnail').'</a></div>';
        echo '  <div class="nc-col nc-col-title" role="cell">';
        echo '      <a class="nc-title" href="'.esc_url($link).'">'.esc_html($p->get_name()).'</a>';
        echo        ($p->is_in_stock() ? '<span class="nc-pill nc-pill--instock">'.esc_html__('In stock','nc').'</span>' : '<span class="nc-pill nc-pill--oos">'.esc_html__('Out of stock','nc').'</span>');
        echo '      <div class="nc-price-mobile" aria-hidden="true">'.$p->get_price_html().'</div>';
        if ($has_meta_max) {
            echo '  <div class="nc-meta-small">'. sprintf(esc_html__('Max per bundle: %d','nc'), (int)$meta_max) . '</div>';
        }
        echo '  </div>';
        echo '  <div class="nc-col nc-col-qty" role="cell">';
        echo '    <div class="quantity buttons_added nh-bundle-qty-wrap'.($effective_max<=0?' is-disabled':'').'">';
        echo '      <span class="screen-reader-text">'.esc_html__('Minus Quantity','nc').'</span>';
        echo '      <a href="#" class="minus"'.($effective_max<=0?' aria-disabled="true"':'').'>-</a>';
        echo '      <label class="screen-reader-text" for="'.$input_id.'">'.esc_html__('Quantity','nc').'</label>';
        echo '      <input'.$qty_disabled.' id="'.$input_id.'" type="number" inputmode="numeric" min="0"'
                . $maxAttr
                . ' step="1" value="0" name="quantity['.esc_attr($p->get_id()).']"'
                . ' class="input-text qty text"'.$dataMax.' autocomplete="off">';
        echo '      <span class="screen-reader-text">'.esc_html__('Plus Quantity','nc').'</span>';
        echo '      <a href="#" class="plus"'.($effective_max<=0?' aria-disabled="true"':'').'>+</a>';
        echo '    </div>';
        echo '  </div>';
        echo '  <div class="nc-col nc-col-price" role="cell"><span class="nc-price-desktop">'.$price_html.'</span></div>';
        echo '</div>';
    }

    echo '<div class="nc-bundle-footer">';
    echo '  <div class="nc-total">'.esc_html__('Total:','nc').' <strong id="bundle-total-amount">'.wc_price(0).'</strong></div>';
    echo '  <button type="button" id="add-bundle-to-cart" class="button alt nc-bundle-btn">'. esc_html__('Add all to basket','nc') .'</button>';
    echo '</div>';

    echo '</form>';
    echo '</section>';
}}
 
add_action('wp_enqueue_scripts', function () {
    if (!is_product()) return;

    wp_enqueue_script(
        'nh-bundle-add',
        get_stylesheet_directory_uri() . '/assets/js/bundle-add-to-cart.js',
        ['jquery'],
        '1.0.0',
        true
    );

    // Optional: pass a cart URL so your JS can redirect after adding
    wp_localize_script('nh-bundle-add', 'bundle_ajax', [
        'cart_url' => wc_get_cart_url(),
    ]);
}, 50);
