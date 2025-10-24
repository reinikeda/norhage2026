<?php
/**
 * Plugin Name: Cutting Type Toggle
 * Description: Adds the "Standard / Cut to custom size" toggle between linked products.
 * Version: 1.3
 * Author: Daiva Reinike
 */

if (!defined('ABSPATH')) exit;

/** ------------------------------------------------------------------------
 *  Find linked counterpart via upsell or reverse lookup.
 * --------------------------------------------------------------------- */
function nhctt_get_linked_product_id( WC_Product $product ) : int {
    $linked_id = 0;

    // Prefer the FIRST upsell (your pairing convention).
    $upsells = $product->get_upsell_ids();
    if (!empty($upsells)) {
        $linked_id = (int) $upsells[0];
    } else {
        // Reverse lookup: find a product that lists THIS one as an upsell.
        $maybe_parent = get_posts([
            'post_type'      => 'product',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [[
                'key'     => '_upsell_ids',
                'value'   => '"' . $product->get_id() . '"',
                'compare' => 'LIKE',
            ]],
        ]);
        if (!empty($maybe_parent)) {
            $linked_id = (int) $maybe_parent[0];
        }
    }
    return $linked_id;
}

/** ------------------------------------------------------------------------
 *  Decide which product ID is "standard" and which is "custom".
 * --------------------------------------------------------------------- */
function nhctt_detect_roles( WC_Product $current, int $linked_id ) : array {
    $roles = ['standard_id' => 0, 'custom_id' => 0];
    if (!$linked_id) return $roles;

    $linked = wc_get_product($linked_id);
    if (!$linked) return $roles;

    $cur_is_custom    = (bool) get_post_meta($current->get_id(), '_nh_cc_enabled', true);
    $linked_is_custom = (bool) get_post_meta($linked_id,           '_nh_cc_enabled', true);

    // Primary rule: explicit meta decides.
    if ($cur_is_custom !== $linked_is_custom) {
        $roles['custom_id']   = $cur_is_custom ? $current->get_id() : $linked_id;
        $roles['standard_id'] = $cur_is_custom ? $linked_id : $current->get_id();
        return $roles;
    }

    // Fallback rule when both look the same:
    // variable ⇒ standard; simple ⇒ custom.
    $cur_is_variable    = $current->is_type('variable');
    $linked_is_variable = $linked->is_type('variable');

    if ($cur_is_variable && !$linked_is_variable) {
        $roles['standard_id'] = $current->get_id();
        $roles['custom_id']   = $linked_id;
    } elseif (!$cur_is_variable && $linked_is_variable) {
        $roles['standard_id'] = $linked_id;
        $roles['custom_id']   = $current->get_id();
    } else {
        // Last resort: keep current as standard, linked as custom.
        $roles['standard_id'] = $current->get_id();
        $roles['custom_id']   = $linked_id;
    }

    return $roles;
}

/** ------------------------------------------------------------------------
 *  Render the toggle (echoes markup once per page).
 * --------------------------------------------------------------------- */
function nhctt_render_toggle() : void {
    if (!is_product()) return;
    global $product;
    if (!$product instanceof WC_Product) return;

    static $printed = false;
    if ($printed) return;

    $linked_id = nhctt_get_linked_product_id($product);
    if (!$linked_id) return;

    $roles = nhctt_detect_roles($product, $linked_id);
    if (!$roles['standard_id'] || !$roles['custom_id']) return;

    $current_id        = (int) $product->get_id();
    $is_current_std    = ($current_id === (int) $roles['standard_id']);
    $is_current_custom = ($current_id === (int) $roles['custom_id']);

    // stable anchor id used for scrolling after navigation
    $anchor   = 'cutting-type';
    $std_url  = get_permalink($roles['standard_id']) . '#' . $anchor;
    $cus_url  = get_permalink($roles['custom_id'])   . '#' . $anchor;

    $label_text = apply_filters('nhctt_label_text', __('Cutting type', 'nh'));
    $label_id   = 'nh-cutting-label-' . $current_id;
    ?>
    <div id="<?php echo esc_attr($anchor); ?>" class="nh-cutting-group" role="group" aria-labelledby="<?php echo esc_attr($label_id); ?>">
        <div id="<?php echo esc_attr($label_id); ?>" class="nh-cutting-label">
            <?php echo esc_html($label_text); ?>
        </div>

        <div class="nh-cut-toggle">
            <?php if ($is_current_std): ?>
                <span class="nh-cut-btn is-active"><?php esc_html_e('Standard sizes','nh'); ?></span>
            <?php else: ?>
                <a class="nh-cut-btn" href="<?php echo esc_url($std_url); ?>"><?php esc_html_e('Standard sizes','nh'); ?></a>
            <?php endif; ?>

            <?php if ($is_current_custom): ?>
                <span class="nh-cut-btn is-active"><?php esc_html_e('Cut to custom size','nh'); ?></span>
            <?php else: ?>
                <a class="nh-cut-btn" href="<?php echo esc_url($cus_url); ?>"><?php esc_html_e('Cut to custom size','nh'); ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $printed = true;
}

/** Place immediately after the features box when available */
add_action('nh_after_feature_box', function () {
    nhctt_render_toggle();
}, 10);

/** Fallback: inside the summary, between excerpt (20) and add-to-cart (30) */
add_action('woocommerce_single_product_summary', function () {
    nhctt_render_toggle();
}, 26);

/** ------------------------------------------------------------------------
 *  Styles
 * --------------------------------------------------------------------- */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('nh-cutting-toggle', plugin_dir_url(__FILE__) . 'css/cutting-toggle.css', [], '1.3');
});
