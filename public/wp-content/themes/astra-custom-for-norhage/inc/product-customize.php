<?php
/**
 * Custom Cutting — Product-level logic (SIMPLE products with _nh_cc_enabled = true)
 * - Shows W/L inputs in mm on the custom-cut product
 * - Calculates price = (area × value/m²) + cut fee
 * - Handles cart/checkout metadata and validation
 */

if (!defined('ABSPATH')) exit;

/* ============================================================================
 * BODY CLASS — flag custom-cut simple product pages
 * ========================================================================== */
add_filter('body_class', function ($classes) {
    if (!function_exists('is_product') || !is_product()) return $classes;

    // Prefer queried object; fallback to global
    $product_obj = wc_get_product(get_queried_object_id());
    if (!$product_obj instanceof WC_Product) {
        global $product;
        if ($product instanceof WC_Product) $product_obj = $product;
    }

    if ($product_obj instanceof WC_Product && $product_obj->is_type('simple')) {
        if ((bool) get_post_meta($product_obj->get_id(), '_nh_cc_enabled', true)) {
            $classes[] = 'nh-has-custom-cut';
        }
    }
    return $classes;
});

/* ============================================================================
 * FRONTEND — Custom inputs (ONLY on the custom-cut SIMPLE product)
 * ========================================================================== */
add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;
    if (!$product instanceof WC_Product) return;
    if (!$product->is_type('simple')) return;
    if (!(bool) get_post_meta($product->get_id(), '_nh_cc_enabled', true)) return;

    $pid   = $product->get_id();

    // Read meta as strings to preserve empties
    $min_w = get_post_meta($pid, '_nh_cc_min_w', true);
    $max_w = get_post_meta($pid, '_nh_cc_max_w', true);
    $min_l = get_post_meta($pid, '_nh_cc_min_l', true);
    $max_l = get_post_meta($pid, '_nh_cc_max_l', true);
    $step  = get_post_meta($pid, '_nh_cc_step_mm', true);

    // Step attribute (>=1)
    $step_attr = ((string)$step !== '' && (int)$step > 0) ? (int)$step : 1;

    // Origins (anchor the step). Default to mins if not explicitly set.
    $origin_w_meta = get_post_meta($pid, '_nh_cc_step_origin_w', true);
    $origin_l_meta = get_post_meta($pid, '_nh_cc_step_origin_l', true);
    $origin_w = ($origin_w_meta !== '' ? (int)$origin_w_meta : ($min_w !== '' ? (int)$min_w : 0));
    $origin_l = ($origin_l_meta !== '' ? (int)$origin_l_meta : ($min_l !== '' ? (int)$min_l : 0));

    // Placeholders
    $ph_w = ($min_w !== '' && $max_w !== '') ? sprintf('%d–%d mm', (int)$min_w, (int)$max_w)
          : ($min_w !== '' ? sprintf('≥ %d mm', (int)$min_w)
          : ($max_w !== '' ? sprintf('≤ %d mm', (int)$max_w) : ''));
    $ph_l = ($min_l !== '' && $max_l !== '') ? sprintf('%d–%d mm', (int)$min_l, (int)$max_l)
          : ($min_l !== '' ? sprintf('≥ %d mm', (int)$min_l)
          : ($max_l !== '' ? sprintf('≤ %d mm', (int)$max_l) : ''));

    // Hidden flag: marks this form post as a custom-cut submission
    echo '<input type="hidden" name="nh_custom_cutting" value="1">';

    echo '<div id="nh-custom-size-wrap" class="nh-size-ui"'
       . ' data-step-mm="'.esc_attr($step_attr).'"'
       . ' data-origin-w="'.esc_attr($origin_w).'"'
       . ' data-origin-l="'.esc_attr($origin_l).'">';

    echo '  <table class="variations" cellspacing="0"><tbody>';

    // Width
    echo '    <tr class="nh-row-width"><td class="label"><label for="nh_width_mm">'.esc_html__('Width (mm)','nh').'</label></td>';
    echo '      <td class="value"><div class="quantity buttons_added nh-mm-qty" data-field="width">';
    echo '        <a href="#" class="minus" aria-label="'.esc_attr__('Decrease width','nh').'">-</a>';
    echo '        <input id="nh_width_mm" name="nh_width_mm" class="input-text nh-mm-input text"'
       . ' type="number" inputmode="numeric" pattern="[0-9]*"'
       . ' step="'.esc_attr($step_attr).'"'
       . (($min_w !== '') ? ' min="'.esc_attr((int)$min_w).'"' : '')
       . (($max_w !== '') ? ' max="'.esc_attr((int)$max_w).'"' : '')
       . ' data-min="'.esc_attr($min_w).'" data-max="'.esc_attr($max_w).'"'
       . ' placeholder="'.esc_attr($ph_w).'" autocomplete="off">';
    echo '        <a href="#" class="plus" aria-label="'.esc_attr__('Increase width','nh').'">+</a>';
    echo '      </div></td></tr>';

    // Length
    echo '    <tr class="nh-row-length"><td class="label"><label for="nh_length_mm">'.esc_html__('Length (mm)','nh').'</label></td>';
    echo '      <td class="value"><div class="quantity buttons_added nh-mm-qty" data-field="length">';
    echo '        <a href="#" class="minus" aria-label="'.esc_attr__('Decrease length','nh').'">-</a>';
    echo '        <input id="nh_length_mm" name="nh_length_mm" class="input-text nh-mm-input text"'
       . ' type="number" inputmode="numeric" pattern="[0-9]*"'
       . ' step="'.esc_attr($step_attr).'"'
       . (($min_l !== '') ? ' min="'.esc_attr((int)$min_l).'"' : '')
       . (($max_l !== '') ? ' max="'.esc_attr((int)$max_l).'"' : '')
       . ' data-min="'.esc_attr($min_l).'" data-max="'.esc_attr($max_l).'"'
       . ' placeholder="'.esc_attr($ph_l).'" autocomplete="off">';
    echo '        <a href="#" class="plus" aria-label="'.esc_attr__('Increase length','nh').'">+</a>';
    echo '      </div></td></tr>';

    echo '  </tbody></table>';

    // Single hint shown once, under the table
    if ($step_attr > 1) {
        echo '  <p class="nh-cc-hint-single">'.sprintf(esc_html__('Step: %d mm', 'nh'), $step_attr).'</p>';
    }

    echo '</div>';

}, 10);

/* ============================================================================
 * FRONTEND — Price Summary (always present; rows shown/hidden by CSS/JS)
 * ========================================================================== */
add_action('woocommerce_before_add_to_cart_button', function () {
    ?>
    <div id="nh-price-summary" class="nh-price-summary" aria-live="polite">
      <div class="nh-ps-title"><?php esc_html_e('Your selection','nh'); ?></div>
      <ul class="nh-ps-list">
        <li class="nh-ps-row nh-ps-perm2">
          <span><?php esc_html_e('Price per m²','nh'); ?></span>
          <span class="nh-ps-val" data-ps="perm2">—</span>
        </li>
        <li class="nh-ps-row nh-ps-cutfee">
          <span><?php esc_html_e('Cutting fee per sheet','nh'); ?></span>
          <span class="nh-ps-val" data-ps="cutfee">—</span>
        </li>
        <li class="nh-ps-row">
          <span><?php esc_html_e('Unit price','nh'); ?></span>
          <span class="nh-ps-val" data-ps="unit">—</span>
        </li>
      </ul>
      <div class="nh-ps-sep" role="separator"></div>
      <div class="nh-ps-total">
        <span class="nh-ps-total-label"><?php esc_html_e('Total','nh'); ?></span>
        <span class="nh-ps-total-val" data-ps="total">—</span>
      </div>
    </div>
    <?php
}, 11);

/* ============================================================================
 * ASSETS
 *  - Core summary helper for ALL products
 *  - custom-cutting.js ONLY on custom-cut SIMPLE product
 *  - Paint perm² + cut fee immediately via NHPriceSummary core (no dependency)
 * ========================================================================== */
add_action('wp_enqueue_scripts', function () {
    if ( ! is_product() ) return;

    // ----- Detect current product (queried first, then global)
    $product_obj = wc_get_product(get_queried_object_id());
    if ( ! $product_obj instanceof WC_Product ) {
        global $product;
        if ( $product instanceof WC_Product ) $product_obj = $product;
    }

    // ===== 1) Price summary core (ALWAYS) =====
    wp_register_script('nh-price-summary-core', '', [], '1.0.4', true);
    wp_enqueue_script('nh-price-summary-core');

    $fmt = [
        'symbol'   => get_woocommerce_currency_symbol(),
        'pos'      => get_option('woocommerce_currency_pos','right_space'),
        'decs'     => wc_get_price_decimals(),
        'thousand' => wc_get_price_thousand_separator(),
        'decimal'  => wc_get_price_decimal_separator(),
    ];
    wp_localize_script('nh-price-summary-core', 'NH_PRICE_FMT', $fmt);

    $helper = <<< 'JS'
    (function($){
      // Woo-like price formatter (HTML), no hard-coded fallback symbol
      function fmt(n){
        const F  = window.NH_PRICE_FMT || {};
        const sym = F.symbol || '';
        const pos = F.pos || 'right_space';
        const d   = (typeof F.decs === 'number') ? F.decs : 2;
        const th  = F.thousand || '.';
        const dc  = F.decimal  || ',';

        // format number per locale
        n = Number(n || 0);
        const parts = n.toFixed(d).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, th);
        const num = d ? parts[0] + dc + parts[1] : parts[0];

        const nbsp = '\u00A0';
        let before = '', after = '';

        switch (pos) {
          case 'left':        before = sym; break;
          case 'left_space':  before = sym + nbsp; break;
          case 'right':       after  = sym; break;
          default:            after  = (sym ? nbsp + sym : ''); break; // right_space
        }

        // Return Woo-like markup so theme styles apply identically
        return (
          '<span class="woocommerce-Price-amount amount"><bdi>' +
            (before ? '<span class="woocommerce-Price-currencySymbol">'+ before +'</span>' : '') +
            num +
            (after  ? '<span class="woocommerce-Price-currencySymbol">'+ after  +'</span>' : '') +
          '</bdi></span>'
        );
      }

      // Numeric parser
      function parse(t){
        if (!t) return 0;
        // Normalize spaces (regular + NBSP + NNBSP)
        var s = (''+t).replace(/[\u00A0\u202F]/g, ''); // ← remove non-breaking/narrow spaces
        // Keep only digits, separators, minus
        s = s.replace(/[^0-9,.\-]/g, '');
        var c = s.lastIndexOf(','), d = s.lastIndexOf('.');
        var sep = c > d ? ',' : '.';
        var n = s.replace(new RegExp('[^0-9\\' + sep + '\\-]', 'g'), '');
        if (sep === ',') n = n.replace(',', '.');
        n = parseFloat(n);
        return isNaN(n) ? 0 : n;
      }

      window.NHPriceSummary = window.NHPriceSummary || {
        update:function(data){
          var $b=$('#nh-price-summary'); if(!$b.length) return;
          if('unit_html' in data){ $b.find('[data-ps="unit"]').html(data.unit_html||'—').attr('data-mode','html'); }
          if('total_html' in data){ $b.find('[data-ps="total"]').html(data.total_html||'—').attr('data-mode','html'); }
          if('unit' in data && ($b.find('[data-ps="unit"]').attr('data-mode')||'text')!=='html'){ $b.find('[data-ps="unit"]').text(data.unit); }
          if('total' in data && ($b.find('[data-ps="total"]').attr('data-mode')||'text')!=='html'){ $b.find('[data-ps="total"]').text(data.total); }
          if('perm2_html' in data){ $b.find('[data-ps="perm2"]').html(data.perm2_html||'—').attr('data-mode','html'); }
          else if('perm2' in data){ $b.find('[data-ps="perm2"]').text(data.perm2); }
          if('cutfee_html' in data){ $b.find('[data-ps="cutfee"]').html(data.cutfee_html||'—').attr('data-mode','html'); }
          else if('cutfee' in data){ $b.find('[data-ps="cutfee"]').text(data.cutfee); }
        },
        fmt:fmt,
        parse:parse
      };
      document.dispatchEvent(new CustomEvent('nh:price-summary-ready'));
    })(jQuery);
    JS;

    wp_add_inline_script('nh-price-summary-core', $helper, 'after');

    // ===== 2) Custom-cut SIMPLE branch =====
    if ( $product_obj instanceof WC_Product
        && $product_obj->is_type('simple')
        && (bool) get_post_meta($product_obj->get_id(), '_nh_cc_enabled', true) ) {

        $pid = $product_obj->get_id();

        $reg_raw  = $product_obj->get_regular_price();
        $sale_raw = $product_obj->get_sale_price();
        $base_raw = $product_obj->get_price(); // value/m²

        $reg_d  = ($reg_raw  !== '' ? wc_get_price_to_display($product_obj, ['price' => (float)$reg_raw ]) : 0);
        $sale_d = ($sale_raw !== '' ? wc_get_price_to_display($product_obj, ['price' => (float)$sale_raw]) : 0);

        if ($reg_d <= 0 && $sale_d <= 0) $reg_d = wc_get_price_to_display($product_obj, ['price' => (float)$base_raw]);
        if ($reg_d <= 0 && $sale_d > 0)  $reg_d = $sale_d;

        $fee = (float) get_post_meta($pid, '_nh_cc_cut_fee', true);

        $paint = "
        jQuery(function($){
          if (!window.NHPriceSummary) return;
          var F = (NHPriceSummary.fmt || function(n){ return String(n); });
          var rg = ".json_encode((float)$reg_d).";
          var sl = ".json_encode((float)$sale_d).";
          var fee = ".json_encode((float)$fee).";
          var perm2HTML =
            (rg>0 && sl>0 && sl<rg) ? ('<del>'+F(rg)+'</del> <ins>'+F(sl)+'</ins>') :
            (sl>0 ? ('<ins>'+F(sl)+'</ins>') :
            (rg>0 ? ('<ins>'+F(rg)+'</ins>') : '—'));
          var cutHTML = fee>0 ? F(fee) : '—';
          NHPriceSummary.update({ perm2_html: perm2HTML, cutfee_html: cutHTML, unit: '—', total: '—' });
          $('#nh-price-summary .nh-ps-perm2').css('display','flex');
          $('#nh-price-summary .nh-ps-cutfee').css('display', fee>0 ? 'flex' : '');
        });
        ";
        wp_add_inline_script('nh-price-summary-core', $paint, 'after');

        // --- NEW: pull weight per m² (kg) and localize it to JS ---
        $kg_per_m2 = (float) get_post_meta($pid, '_nh_cc_weight_per_m2', true);

        // custom-cut JS config
        $cc = [
          'enabled'         => true,
          'price_per_m2'    => (float) $base_raw,
          'cut_fee'         => (float) $fee,
          'min_w'           => ($v=get_post_meta($pid,'_nh_cc_min_w',true)) === '' ? '' : (int)$v,
          'max_w'           => ($v=get_post_meta($pid,'_nh_cc_max_w',true)) === '' ? '' : (int)$v,
          'min_l'           => ($v=get_post_meta($pid,'_nh_cc_min_l',true)) === '' ? '' : (int)$v,
          'max_l'           => ($v=get_post_meta($pid,'_nh_cc_max_l',true)) === '' ? '' : (int)$v,
          'step'            => ($v=get_post_meta($pid,'_nh_cc_step_mm',true)) === '' ? 1 : (int)$v,
          'perm2_reg_disp'  => (float) $reg_d,
          'perm2_sale_disp' => (float) $sale_d,

          // >>> IMPORTANT: these two lines feed custom-cutting.js
          'kg_per_m2'       => $kg_per_m2,          // JS reads this
          'weight_per_m2'   => $kg_per_m2,          // fallback alias in JS
        ];

        wp_enqueue_script(
          'custom-cutting',
          get_stylesheet_directory_uri().'/assets/js/custom-cutting.js',
          ['jquery','nh-price-summary-core'],
          '3.0.4',
          true
        );
        wp_localize_script('custom-cutting', 'NH_CC', $cc);
    }

}, 98);

/* ============================================================================
 * ADD-TO-CART VALIDATION (ONLY when a custom-cut request is being posted)
 * ========================================================================== */
add_filter('woocommerce_add_to_cart_validation', 'nh_cc_validate_custom_cut', 10, 4);
function nh_cc_validate_custom_cut( $passed, $product_id, $qty = 0, $variation_id = 0 ) {

    // Run only for simple products with CC enabled
    $p = wc_get_product( $product_id );
    if ( ! $p || ! $p->is_type('simple') ) return $passed;
    if ( ! (bool) get_post_meta( $product_id, '_nh_cc_enabled', true ) ) return $passed;

    // Detect if this POST is actually a custom-cut request
    $w_raw = isset($_POST['nh_width_mm'])  ? trim( wp_unslash($_POST['nh_width_mm']) )  : '';
    $l_raw = isset($_POST['nh_length_mm']) ? trim( wp_unslash($_POST['nh_length_mm']) ) : '';
    $flag  = isset($_POST['nh_custom_cutting']) && $_POST['nh_custom_cutting'] === '1';

    // If no flag AND no custom fields present, it's a normal add — skip validation
    if ( ! $flag && $w_raw === '' && $l_raw === '' ) {
        return $passed;
    }

    // From here on, treat it as a custom-cut add
    $w = absint( $w_raw );
    $l = absint( $l_raw );

    if ( $w <= 0 || $l <= 0 ) {
        wc_add_notice( __( 'Please enter width and length (mm).', 'nh' ), 'error' );
        return false;
    }

    $min_w = absint( get_post_meta($product_id, '_nh_cc_min_w', true) );
    $max_w = absint( get_post_meta($product_id, '_nh_cc_max_w', true) );
    $min_l = absint( get_post_meta($product_id, '_nh_cc_min_l', true) );
    $max_l = absint( get_post_meta($product_id, '_nh_cc_max_l', true) );

    if ( $min_w && $w < $min_w ) { wc_add_notice( sprintf( __( 'Minimum width is %d mm.', 'nh' ),  $min_w ), 'error' ); return false; }
    if ( $max_w && $w > $max_w ) { wc_add_notice( sprintf( __( 'Maximum width is %d mm.', 'nh' ),  $max_w ), 'error' ); return false; }
    if ( $min_l && $l < $min_l ) { wc_add_notice( sprintf( __( 'Minimum length is %d mm.', 'nh' ), $min_l ), 'error' ); return false; }
    if ( $max_l && $l > $max_l ) { wc_add_notice( sprintf( __( 'Maximum length is %d mm.', 'nh' ), $max_l ), 'error' ); return false; }

    // --- UPDATED: origin-anchored step validation ---
    $step = absint( get_post_meta($product_id, '_nh_cc_step_mm', true) );
    if ( $step > 0 ) {
        // Anchor steps to the first allowed value:
        // by default we use min_w/min_l, but you can override with explicit metas.
        $origin_w = (int) get_post_meta($product_id, '_nh_cc_step_origin_w', true);
        $origin_l = (int) get_post_meta($product_id, '_nh_cc_step_origin_l', true);
        if ( ! $origin_w ) $origin_w = $min_w ?: 0;
        if ( ! $origin_l ) $origin_l = $min_l ?: 0;

        if ( (($w - $origin_w) % $step) !== 0 ) {
            wc_add_notice(
                sprintf( __( 'Width must align to %1$d mm steps starting at %2$d mm.', 'nh' ), $step, $origin_w ),
                'error'
            );
            return false;
        }

        if ( (($l - $origin_l) % $step) !== 0 ) {
            wc_add_notice(
                sprintf( __( 'Length must align to %1$d mm steps starting at %2$d mm.', 'nh' ), $step, $origin_l ),
                'error'
            );
            return false;
        }
    }
    // --- end updated block ---

    return $passed;
}

/* ============================================================================
 * PRG after add-to-cart — avoid browser form re-submission on refresh
 * ========================================================================== */
add_filter('woocommerce_add_to_cart_redirect', function ($redirect_url = '', $product = null) {
    // Do not change behavior for AJAX adds (mini-cart, bundles, etc.)
    if (wp_doing_ajax()) {
        return $redirect_url;
    }

    // Honor Woo setting "Redirect to the cart page after successful addition"
    if ('yes' === get_option('woocommerce_cart_redirect_after_add', 'no')) {
        return wc_get_cart_url();
    }

    // Prefer coming back to the product page; fall back to cart
    $target = wp_get_referer();
    if (!$target && $product instanceof WC_Product) {
        $target = get_permalink($product->get_id());
    }
    if (!$target) {
        $target = wc_get_cart_url();
    }

    // Clean query args so the return is an idempotent GET (prevents resubmission)
    $target = remove_query_arg(array(
        'add-to-cart', 'quantity', 'variation_id',
        '_wpnonce', '_wp_http_referer',
        // common attribute param names
        'attribute_pa_width', 'attribute_pa_length', 'attribute_width', 'attribute_length'
    ), $target);

    // (Optional) tag the URL if your theme anchors to notices
    // $target = add_query_arg('added', '1', $target);

    return $target;
}, 10, 2);

/* ============================================================================
 * ADD CUSTOM DATA TO CART / ORDER (ONLY for custom-cut SIMPLE product)
 * ========================================================================== */
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id){
    $p = wc_get_product($product_id);
    if (!$p || !$p->is_type('simple')) return $cart_item_data;
    if (!(bool) get_post_meta($product_id, '_nh_cc_enabled', true)) return $cart_item_data;

    $w = (int) ($_POST['nh_width_mm'] ?? 0);
    $l = (int) ($_POST['nh_length_mm'] ?? 0);
    $cart_item_data['nh_custom_size'] = ['width_mm' => $w, 'length_mm' => $l];
    $cart_item_data['nh_unique']      = md5($product_id.'|'.$w.'x'.$l.'|'.microtime(true));
    return $cart_item_data;
}, 10, 2);

add_filter('woocommerce_get_item_data', function($item_data, $cart_item){
    if (empty($cart_item['nh_custom_size'])) return $item_data;
    $w = (int) $cart_item['nh_custom_size']['width_mm'];
    $l = (int) $cart_item['nh_custom_size']['length_mm'];
    if ($w) $item_data[] = ['name'=>__('Width','nh'),'value'=>$w.' mm'];
    if ($l) $item_data[] = ['name'=>__('Length','nh'),'value'=>$l.' mm'];
    $pid = (int) ($cart_item['product_id'] ?? 0);
    $fee = (float) get_post_meta($pid, '_nh_cc_cut_fee', true);
    if ($fee > 0) $item_data[] = ['name'=>__('Cutting fee per sheet','nh'),'value'=>wc_price($fee)];
    return $item_data;
}, 10, 2);

add_action('woocommerce_checkout_create_order_line_item', function($item, $key, $values){
    if (empty($values['nh_custom_size'])) return;
    $w = (int) ($values['nh_custom_size']['width_mm'] ?? 0);
    $l = (int) ($values['nh_custom_size']['length_mm'] ?? 0);
    $pid = (int) ($values['product_id'] ?? 0);
    $fee = (float) get_post_meta($pid, '_nh_cc_cut_fee', true);
    if ($w)  $item->add_meta_data(__('Width','nh'),  $w.' mm', true);
    if ($l)  $item->add_meta_data(__('Length','nh'), $l.' mm', true);
    if ($fee) $item->add_meta_data(__('Cutting fee per sheet','nh'), wc_price($fee), true);
}, 10, 3);

/* ============================================================================
 * CUSTOM PRICING — area × value/m² + fee (ONLY for custom-cut SIMPLE product)
 * ========================================================================== */
add_action('woocommerce_before_calculate_totals', function($cart){
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (empty($cart)) return;

    foreach ($cart->get_cart() as $item) {
        if (empty($item['nh_custom_size'])) continue;

        /** @var WC_Product $product */
        $product = $item['data'];
        if (!$product instanceof WC_Product || !$product->is_type('simple')) continue;
        if (!(bool) get_post_meta($product->get_id(), '_nh_cc_enabled', true)) continue;

        // value/m² is stored as the product price
        $price_per_m2 = (float) $product->get_price();
        $fee          = (float) get_post_meta($product->get_id(), '_nh_cc_cut_fee', true);

        $wmm = (int) ($item['nh_custom_size']['width_mm']  ?? 0);
        $lmm = (int) ($item['nh_custom_size']['length_mm'] ?? 0);
        if ($price_per_m2 <= 0 || $wmm <= 0 || $lmm <= 0) continue;

        $area = ($wmm / 1000) * ($lmm / 1000);
        $unit = $area * $price_per_m2 + max(0, $fee);
        $product->set_price( wc_format_decimal($unit, wc_get_price_decimals()) );
    }
}, 20);

/* ============================================================================
 * STANDARD VARIATIONS → populate Unit/Total in the summary
 * (works on standard variable/simple products; SKIP custom-cut simple)
 * ========================================================================== */
add_action('wp_footer', function () {
    if ( ! is_product() ) return;

    global $product;

    // Skip custom-cut simple
    $is_custom_simple = (
        $product instanceof WC_Product
        && $product->is_type('simple')
        && (bool) get_post_meta($product->get_id(), '_nh_cc_enabled', true)
    );
    if ($is_custom_simple) return;

    // For SIMPLE (non-custom) products, compute display prices server-side.
    $simple_reg_d = 0;
    $simple_sale_d = 0;
    if ($product instanceof WC_Product && $product->is_type('simple')) {
        $reg  = $product->get_regular_price();
        $sale = $product->get_sale_price();
        $curr = $product->get_price();

        $simple_reg_d  = ($reg  !== '') ? wc_get_price_to_display($product, ['price' => (float) $reg])  : 0;
        $simple_sale_d = ($sale !== '') ? wc_get_price_to_display($product, ['price' => (float) $sale]) : 0;

        if ($simple_reg_d <= 0 && $simple_sale_d <= 0) {
            $simple_reg_d = wc_get_price_to_display($product, ['price' => (float) $curr]);
        }
        if ($simple_reg_d <= 0 && $simple_sale_d > 0) {
            $simple_reg_d = $simple_sale_d;
        }
    }
    ?>
    <script>
    (function($){
      function fmt(n){ return (window.NHPriceSummary && NHPriceSummary.fmt) ? NHPriceSummary.fmt(n) : (''+n); }
      function parse(t){ return (window.NHPriceSummary && NHPriceSummary.parse) ? NHPriceSummary.parse(t) : 0; }

      function makePair(reg, sale){
        reg = Number(reg||0); sale = Number(sale||0);
        if (reg > 0 && sale > 0 && sale < reg){
          return '<del>'+fmt(reg)+'</del> <ins>'+fmt(sale)+'</ins>';
        }
        var v = sale>0 ? sale : reg;
        return v>0 ? '<ins>'+fmt(v)+'</ins>' : '—';
      }

      function recomputeTotal(){
        var $box = $('#nh-price-summary'); if(!$box.length) return;
        var qty = parseFloat($('form.cart .quantity input.qty').val()) || 1;

        var $unit = $box.find('[data-ps="unit"]');
        var ins = $unit.find('ins').text().trim();
        var del = $unit.find('del').text().trim();
        var sale = ins ? parse(ins) : parse($unit.text());
        var reg  = del ? parse(del) : sale;

        if (sale > 0){
          var regTotal  = (reg || sale) * qty;
          var saleTotal = sale * qty;
          var html = (reg && sale < reg)
            ? '<del>'+fmt(regTotal)+'</del> <ins>'+fmt(saleTotal)+'</ins>'
            : '<ins>'+fmt(saleTotal)+'</ins>';
          NHPriceSummary.update({ total_html: html });
        } else {
          NHPriceSummary.update({ total: '—' });
        }
      }

      $(function(){
        var $vf = $('form.variations_form');

        if ($vf.length){
          // VARIABLE products (use Woo’s event payload)
          $vf.on('found_variation', function(_e, v){
            if (!v) return;
            var reg  = parseFloat(v.display_regular_price || 0);
            var sale = parseFloat(v.display_price || 0);
            NHPriceSummary.update({ unit_html: makePair(reg, sale) });
            recomputeTotal();
          });
          $vf.on('hide_variation reset_data', function(){
            NHPriceSummary.update({ unit_html: '—', total_html: '—' });
          });
        } else {
          // SIMPLE (non-custom) product — use server values (no DOM scraping!)
          var REG  = <?php echo json_encode((float)$simple_reg_d); ?>;
          var SALE = <?php echo json_encode((float)$simple_sale_d); ?>;
          NHPriceSummary.update({ unit_html: makePair(REG, SALE) });
          recomputeTotal();
        }

        $(document).on('input change', 'form.cart .quantity input.qty', recomputeTotal);
      });
    })(jQuery);
    </script>
    <?php
});
