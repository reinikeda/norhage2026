<?php
/**
 * Basket/checkout/email display customizations
 * - Use <dl class="variation"> for variable product attributes.
 * - Include custom-cut item weights in Woo total weight.
 * - Show a "Total weight" row in Cart/Checkout totals.
 */

if (!defined('ABSPATH')) exit;

/* ===========================================================
 * Helpers
 * ========================================================= */
function nh_is_custom_cut_item($meta_src) : bool {
    // Be generous: accept several hints that this is a custom cut
    $has = function($arr, $k){ return is_array($arr) && array_key_exists($k, $arr) && $arr[$k] !== '' && $arr[$k] !== null; };

    if (is_array($meta_src)) {
        return
            !empty($meta_src['nh_is_custom_cut']) ||
            $has($meta_src,'nh_custom_mode') ||
            $has($meta_src,'nh_cc_width') || $has($meta_src,'nh_cc_length') ||
            $has($meta_src,'nh_width_mm') || $has($meta_src,'nh_length_mm') ||
            $has($meta_src,'nh_custom_unit_kg') || $has($meta_src,'nh_custom_total_kg');
    }
    if ($meta_src instanceof WC_Order_Item) {
        $w = $meta_src->get_meta('nh_cc_width', true);
        $l = $meta_src->get_meta('nh_cc_length', true);
        $w2= $meta_src->get_meta('nh_width_mm', true);
        $l2= $meta_src->get_meta('nh_length_mm', true);
        $m = $meta_src->get_meta('nh_custom_mode', true);
        $u = $meta_src->get_meta('nh_custom_unit_kg', true);
        return ($m === '1' || $w !== '' || $l !== '' || $w2 !== '' || $l2 !== '' || (float)$u > 0);
    }
    return false;
}

function nh_format_variation_kv_pairs($variation, $product) : array {
    if (empty($variation) || !$product) return [];
    $pairs = [];
    foreach ($variation as $key => $value) {
        if (strpos($key, 'attribute_') !== 0) continue;
        $attr_slug = substr($key, strlen('attribute_')); // pa_color / size …
        $label     = wc_attribute_label($attr_slug, $product);
        if ($value === '') continue;

        if (taxonomy_exists($attr_slug)) {
            $term   = get_term_by('slug', $value, $attr_slug);
            $pretty = $term && !is_wp_error($term) ? $term->name : wc_clean(str_replace('-', ' ', $value));
        } else {
            $pretty = wc_clean($value);
        }
        $pairs[$label] = $pretty;
    }
    return $pairs;
}

function nh_render_dl_variation(array $pairs) : string {
    if (empty($pairs)) return '';
    $html = '<dl class="variation">';
    foreach ($pairs as $label => $val) {
        $cls  = 'variation-' . preg_replace('/\s+/', '', ucwords(wp_strip_all_tags($label)));
        $html .= '<dt class="'. esc_attr($cls) .'">'. esc_html($label) .':</dt>';
        $html .= '<dd class="'. esc_attr($cls) .'"><p>'. esc_html($val) .'</p></dd>';
    }
    $html .= '</dl>';
    return $html;
}

/* ===========================================================
 * CART & CHECKOUT: remove Woo default attribute rows for variations
 * (avoid duplicates with our <dl>)
 * ========================================================= */
add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    if (nh_is_custom_cut_item($cart_item)) return $item_data;

    $product = $cart_item['data'] ?? null;
    if ($product instanceof WC_Product_Variation || ($product instanceof WC_Product && $product->is_type('variation'))) {
        $kv = !empty($cart_item['variation']) ? nh_format_variation_kv_pairs($cart_item['variation'], $product) : [];
        if (empty($kv)) return $item_data;

        $filtered = [];
        foreach ($item_data as $row) {
            $keep = true;
            if (isset($row['key']) && array_key_exists($row['key'], $kv)) {
                $keep = false; // drop Woo's attribute rows
            }
            if ($keep) $filtered[] = $row;
        }
        return $filtered;
    }
    return $item_data;
}, 10, 2);

/* ===========================================================
 * CART & CHECKOUT: append our <dl class="variation"> for normal variations
 * (custom-cut items have their own list printed already)
 * ========================================================= */
add_filter('woocommerce_cart_item_name', function ($name, $cart_item) {
    if (nh_is_custom_cut_item($cart_item)) return $name;

    $product = $cart_item['data'] ?? null;
    if (!($product instanceof WC_Product)) return $name;

    if ($product->is_type('variation') || (!empty($cart_item['variation']) && !empty($product->get_id()))) {
        $pairs = nh_format_variation_kv_pairs($cart_item['variation'], $product);
        if (!empty($pairs)) {
            $name .= nh_render_dl_variation($pairs);
        }
    }
    return $name;
}, 10, 2);

/* ===========================================================
 * EMAILS / ORDER VIEW: hide defaults + add same <dl> for variations
 * ========================================================= */
add_filter('woocommerce_order_item_get_formatted_meta_data', function ($formatted_meta, $item) {
    if (!($item instanceof WC_Order_Item_Product)) return $formatted_meta;
    if (nh_is_custom_cut_item($item)) return $formatted_meta;

    $product = $item->get_product();
    if ($product && $product->is_type('variation')) {
        $parent = wc_get_product($product->get_parent_id());
        if ($parent) {
            $attr_labels = [];
            foreach ($parent->get_attributes() as $attr_key => $attr_obj) {
                $attr_labels[] = wc_attribute_label($attr_key, $parent);
            }
            $filtered = [];
            foreach ($formatted_meta as $fm) {
                if (!in_array($fm->display_key, $attr_labels, true)) {
                    $filtered[] = $fm;
                }
            }
            return $filtered;
        }
    }
    return $formatted_meta;
}, 10, 2);

add_action('woocommerce_order_item_meta_start', function ($item_id, $item, $order, $plain_text) {
    if ($plain_text) return;
    if (!($item instanceof WC_Order_Item_Product)) return;
    if (nh_is_custom_cut_item($item)) return;

    $product = $item->get_product();
    if (!$product || !$product->is_type('variation')) return;

    $pairs = [];
    foreach ($item->get_meta_data() as $m) {
        $k = $m->key ?? '';
        if (strpos($k, 'attribute_') === 0) {
            $attr_slug = substr($k, strlen('attribute_'));
            $label     = wc_attribute_label($attr_slug, $product);
            $val       = wc_clean($m->value);
            if (taxonomy_exists($attr_slug)) {
                $term = get_term_by('slug', $val, $attr_slug);
                $val  = $term && !is_wp_error($term) ? $term->name : wc_clean(str_replace('-', ' ', $val));
            }
            $pairs[$label] = $val;
        }
    }
    if (!empty($pairs)) echo nh_render_dl_variation($pairs);
}, 10, 4);

/* ===========================================================
 * CUSTOM-CUT WEIGHT PIPELINE (capture → restore → apply)
 * ========================================================= */

/** Capture posted weights (per-unit in KG) from custom-cutting.js */
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id){
    $unit = isset($_POST['nh_custom_unit_kg'])  ? (float) wc_clean( wp_unslash($_POST['nh_custom_unit_kg']) )  : 0;
    $tot  = isset($_POST['nh_custom_total_kg']) ? (float) wc_clean( wp_unslash($_POST['nh_custom_total_kg']) ) : 0;
    $is_custom = isset($_POST['nh_custom_mode']) && $_POST['nh_custom_mode'] === '1';

    if ( $is_custom || $unit > 0 || $tot > 0 ) {
        $cart_item_data['nh_is_custom_cut']   = true;
        $cart_item_data['nh_custom_unit_kg']  = max(0, (float) $unit); // per-unit kg
        $cart_item_data['nh_custom_total_kg'] = max(0, (float) $tot);  // unit * qty (info only)
    }
    return $cart_item_data;
}, 10, 2);

/** Restore from session (page reloads) */
add_filter('woocommerce_get_cart_item_from_session', function($cart_item, $values){
    foreach (['nh_is_custom_cut','nh_custom_unit_kg','nh_custom_total_kg'] as $k) {
        if ( isset($values[$k]) ) $cart_item[$k] = $values[$k];
    }
    return $cart_item;
}, 10, 2);

/** Override per-unit line weight BEFORE totals/shipping (priority 5) */
add_action('woocommerce_before_calculate_totals', function($cart){
    if ( is_admin() && ! defined('DOING_AJAX') ) return;
    if ( ! $cart || ! is_object($cart) ) return;

    $store_unit = get_option('woocommerce_weight_unit', 'kg'); // 'kg','g','lbs','oz'

    foreach ( $cart->get_cart() as $cart_item ) {
        if ( empty($cart_item['nh_custom_unit_kg']) || empty($cart_item['data']) ) continue;

        $product = $cart_item['data'];
        if ( ! $product instanceof WC_Product ) continue;

        $unit_kg       = max(0, (float) $cart_item['nh_custom_unit_kg']);
        $unit_in_store = wc_get_weight( $unit_kg, $store_unit, 'kg' );

        $product->set_weight( (string) $unit_in_store );      // affects totals/shipping
        if ( method_exists($product, 'set_virtual') ) {
            $product->set_virtual( false );
        }
    }
}, 5);

/* LOCAL PICKUP → styled info block (uses Norhage palette) */
add_action('woocommerce_after_shipping_rate', function( $rate, $index ) {

    if ( ! $rate instanceof WC_Shipping_Rate ) return;
    if ( $rate->get_method_id() !== 'local_pickup' ) return;

    $input_id = 'shipping_method_' . $index . '_' . sanitize_title( $rate->get_id() );
    $title_id = 'nh-pickup-title-' . sanitize_html_class( $input_id );

    $company  = 'UAB Tehis';
    $address  = 'Tiekėjų g. 19E, Kretinga';
    $hours    = __('Pickup hours: Mon–Fri 8:30–16:30', 'norhage');
    $leadtime = __('You’ll receive an SMS/email.', 'norhage');

    static $printed = false;
    if ( ! $printed ) {
        $printed = true;
    }

    echo '<div class="nh-pickup-details" role="region" aria-labelledby="'. esc_attr($title_id) .'">
            <strong id="'. esc_attr($title_id) .'">'. esc_html($company) .'</strong>
            <div>'. esc_html($address) .'</div>
            <div class="nh-note">'. esc_html($hours) .'</div>
            <div class="nh-note">'. esc_html($leadtime) .'</div>
          </div>';

}, 10, 2);


/* =========================================================
 * CART → Simplified shipping calculator
 * - Hide State/County and City
 * - Keep Country and Postcode
 * - Preselect country
 * - Ensure postcode-only submissions populate the address
 * ======================================================= */

/* Hide fields we don't need */
add_filter('woocommerce_shipping_calculator_enable_state', '__return_false');
add_filter('woocommerce_shipping_calculator_enable_city',  '__return_false');

/* Keep postcode + country visible */
add_filter('woocommerce_shipping_calculator_enable_postcode', '__return_true');
add_filter('woocommerce_shipping_calculator_enable_country',  '__return_true');

/* Preselect a country on the cart if none is set yet */
add_action('wp', function () {
    if ( ! function_exists('is_cart') || ! is_cart() ) return;
    if ( ! WC()->customer || ! WC()->countries ) return;

    $customer = WC()->customer;
    if ( $customer->get_shipping_country() ) return;

    $ships_to = WC()->countries->get_shipping_countries();
    if ( is_array($ships_to) && count($ships_to) === 1 ) {
        $customer->set_shipping_country( array_key_first($ships_to) );
    } else {
        $customer->set_shipping_country( WC()->countries->get_base_country() );
    }
});

/* When the calculator runs, accept postcode-only and persist location */
add_action('woocommerce_calculated_shipping', function () {
    if ( ! WC()->customer ) return;

    // Read submitted values; allow missing city/state
    $country  = isset($_POST['calc_shipping_country'])  ? wc_clean( wp_unslash($_POST['calc_shipping_country']) )  : WC()->customer->get_shipping_country();
    $state    = isset($_POST['calc_shipping_state'])    ? wc_clean( wp_unslash($_POST['calc_shipping_state']) )    : '';
    $postcode = isset($_POST['calc_shipping_postcode']) ? wc_clean( wp_unslash($_POST['calc_shipping_postcode']) ) : '';
    $city     = isset($_POST['calc_shipping_city'])     ? wc_clean( wp_unslash($_POST['calc_shipping_city']) )     : '';

    // Persist location (Woo uses this to match zones)
    WC()->customer->set_shipping_location( $country, $state, $postcode, $city );

    // Recalculate totals so “Shipping to …” appears as usual
    WC()->cart && WC()->cart->calculate_totals();
}, 20);

/* Optional: tidy the "Shipping to ..." display when city/state are empty */
add_filter('woocommerce_formatted_address_replacements', function ($repl, $args) {
    // If city/state are blank, avoid extra commas/spaces in Woo formatted address
    if ( empty($args['city']) ) {
        $repl['{city}'] = '';
    }
    if ( empty($args['state']) ) {
        $repl['{state}'] = '';
    }

    // Also normalize postcode formatting if it's set alone
    if ( ! empty($args['postcode']) && empty($args['city']) && empty($args['state']) ) {
        $repl['{postcode}'] = trim($args['postcode']);
    }

    return $repl;
}, 10, 2);

/* Delivery destination card that reuses warehouse styles and hides the default sentence.
   - Shows for ALL non-pickup methods.
   - If a package has only one rate (Woo uses a hidden input, no label), the card is always visible.
   - If multiple rates, the card appears only when that rate’s radio is selected. */
add_action('woocommerce_after_shipping_rate', function (WC_Shipping_Rate $rate, $index) {

    if ( $rate->get_method_id() === 'local_pickup' ) {
        return; // pickup uses the warehouse block elsewhere
    }

    // Build "postcode, Country" from the current customer/session
    $countries = WC()->countries;
    $customer  = WC()->customer;

    $postcode  = $customer ? ( $customer->get_shipping_postcode() ?: $customer->get_billing_postcode() ) : '';
    $country_c = $customer ? ( $customer->get_shipping_country()  ?: $customer->get_billing_country()  ) : '';
    if ( ! $country_c ) {
        $country_c = $countries->get_base_country();
    }
    $country_n = $countries->countries[ $country_c ] ?? $country_c;
    $line      = $postcode ? sprintf('%s, %s', $postcode, $country_n) : $country_n;

    // Woo input id for this rate (present only when radios are used)
    $input_id = 'shipping_method_' . $index . '_' . sanitize_title( $rate->id );
    $title_id = 'nh-ship-dest-title-' . $input_id;

    // Hide Woo default sentence once
    static $did_css = false;
    if ( ! $did_css ) {
        $did_css = true;
        echo '<style>.woocommerce-shipping-destination{display:none}</style>';
    }

    // Detect if this package has a single rate (Woo uses hidden input without label)
    $packages  = WC()->shipping()->get_packages();
    $is_single = isset($packages[$index]['rates']) && count($packages[$index]['rates']) === 1;

    // If multiple rates, reveal only when this radio is checked; if single, always visible
    if ( ! $is_single ) {
        echo '<style>#' . esc_attr( $input_id ) . ':checked + label + .nh-pickup-details{display:block}</style>';
        $style = ''; // CSS handles visibility
    } else {
        $style = ' style="display:block"';
    }

    // Card markup (uses same .nh-pickup-details styles as the warehouse block)
    echo '<div class="nh-pickup-details"' . $style . ' role="region" aria-labelledby="' . esc_attr($title_id) . '">
            <strong id="' . esc_attr($title_id) . '">' . esc_html__( 'Shipping to', 'norhage' ) . '</strong>
            <div>' . esc_html( $line ) . '</div>
          </div>';

}, 10, 2);


/* ===========================================================
 * TOTAL WEIGHT ROW (robust sum of normal + custom-cut items)
 * ========================================================= */
function nh_cart_total_weight_strict() : float {
    if ( ! WC()->cart ) return 0.0;

    $store_unit = get_option('woocommerce_weight_unit', 'kg');
    $sum_store  = 0.0;

    foreach ( WC()->cart->get_cart() as $item ) {
        $qty = isset($item['quantity']) ? (int) $item['quantity'] : 1;

        if ( ! empty($item['nh_custom_unit_kg']) ) {
            $unit_kg       = max(0, (float) $item['nh_custom_unit_kg']);
            $unit_in_store = (float) wc_get_weight( $unit_kg, $store_unit, 'kg' );
            $sum_store    += $unit_in_store * max(1, $qty);
        } else {
            $product = $item['data'] ?? null;
            if ( $product instanceof WC_Product ) {
                $unit_in_store = (float) $product->get_weight(); // store unit already
                if ( $unit_in_store > 0 ) {
                    $sum_store += $unit_in_store * max(1, $qty);
                }
            }
        }
    }
    return $sum_store;
}

function nh_show_total_cart_weight_row() {
    if ( ! WC()->cart ) return;

    $total_store = nh_cart_total_weight_strict();

    // Format using WooCommerce global separators for consistency with prices
    $formatted = number_format(
        $total_store,
        wc_get_price_decimals(),
        wc_get_price_decimal_separator(),
        wc_get_price_thousand_separator()
    ) . ' ' . get_option('woocommerce_weight_unit', 'kg');
    ?>
    <tr class="nh-cart-total-weight">
        <th><?php esc_html_e( 'Total weight', 'nh-heavy-parcel' ); ?></th>
        <td data-title="<?php esc_attr_e( 'Total weight', 'nh-heavy-parcel' ); ?>">
            <?php echo esc_html( $formatted ); ?>
        </td>
    </tr>
    <?php
}

add_action( 'woocommerce_cart_totals_after_shipping',   'nh_show_total_cart_weight_row' );
add_action( 'woocommerce_review_order_after_shipping', 'nh_show_total_cart_weight_row' );
