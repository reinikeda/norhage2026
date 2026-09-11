<?php
/**
 * inc/sample-order.php
 *
 * Sample ordering feature for sheet products.
 * Adds meta fields, front-end strip, and cart/price override logic.
 *
 * OWNERSHIP NOTE:
 * This file is the single source of truth for:
 * - detecting samples (nh_is_sample_cart_item / nh_is_sample_order_item)
 * - sample price, quantity, shipping class (always slug "xs" on every shop)
 * - applying sample price on add-to-cart and session restore (side cart line price)
 * - saving sample order-item meta at checkout
 * - stripping weight from samples (cart, order, PDF)
 *
 * Simple, variable, and custom-cut products can all offer samples.
 * Custom-cut cart/price logic in product-customize.php must skip samples.
 *
 * It does NOT touch woocommerce_get_item_data (cart display) or
 * woocommerce_order_item_get_formatted_meta_data (order display).
 * Those are owned exclusively by basket-customize.php and
 * order-attributes.php respectively, to avoid two files fighting
 * over the same filter.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shipping class slug forced onto every sample cart line.
 * All shops share this slug (term IDs differ per site).
 */
if ( ! defined( 'NH_SAMPLE_SHIPPING_CLASS_SLUG' ) ) {
    define( 'NH_SAMPLE_SHIPPING_CLASS_SLUG', 'xs' );
}

/** ------------------------------------------------------------------
 * 0. Shared helpers — single source of truth for "is this a sample?"
 * ------------------------------------------------------------------ */
function nh_is_sample_cart_item( array $cart_item ): bool {
    return ! empty( $cart_item['norhage_sample'] );
}

function nh_is_sample_order_item( $item ): bool {
    return $item instanceof WC_Order_Item_Product && 'yes' === $item->get_meta( '_nh_sample' );
}

/** ------------------------------------------------------------------
 * 1. Add "Sample settings" fields to Product Data > General tab
 * ------------------------------------------------------------------ */
add_action( 'woocommerce_product_options_general_product_data', 'norhage_sample_fields' );
function norhage_sample_fields() {
    echo '<div class="options_group">';

    woocommerce_wp_checkbox( array(
        'id'          => '_sample_enabled',
        'label'       => __( 'Enable sample ordering', 'nh-theme' ),
        'description' => __( 'Show "Order a sample" strip on the front end.', 'nh-theme' ),
    ) );

    woocommerce_wp_text_input( array(
        'id'                => '_sample_width_mm',
        'label'             => __( 'Sample width (mm)', 'nh-theme' ),
        'type'              => 'number',
        'custom_attributes' => array( 'step' => '1', 'min' => '0' ),
    ) );

    woocommerce_wp_text_input( array(
        'id'                => '_sample_length_mm',
        'label'             => __( 'Sample length (mm)', 'nh-theme' ),
        'type'              => 'number',
        'custom_attributes' => array( 'step' => '1', 'min' => '0' ),
    ) );

    woocommerce_wp_text_input( array(
        'id'                => '_sample_price',
        'label'             => __( 'Sample price (€)', 'nh-theme' ),
        'type'              => 'number',
        'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
    ) );

    echo '</div>';
}

/** ------------------------------------------------------------------
 * 2. Save the meta fields
 * ------------------------------------------------------------------ */
add_action( 'woocommerce_process_product_meta', 'norhage_save_sample_fields' );
function norhage_save_sample_fields( $post_id ) {
    $enabled = isset( $_POST['_sample_enabled'] ) ? 'yes' : 'no';
    update_post_meta( $post_id, '_sample_enabled', $enabled );

    if ( isset( $_POST['_sample_width_mm'] ) ) {
        update_post_meta( $post_id, '_sample_width_mm', wc_clean( $_POST['_sample_width_mm'] ) );
    }
    if ( isset( $_POST['_sample_length_mm'] ) ) {
        update_post_meta( $post_id, '_sample_length_mm', wc_clean( $_POST['_sample_length_mm'] ) );
    }
    if ( isset( $_POST['_sample_price'] ) ) {
        update_post_meta( $post_id, '_sample_price', wc_format_decimal( $_POST['_sample_price'] ) );
    }
}

/** ------------------------------------------------------------------
 * 3. Front-end: render the "Order a sample" inline strip
 * ------------------------------------------------------------------ */
add_action( 'woocommerce_after_add_to_cart_form', 'norhage_render_sample_strip', 5 );
function norhage_render_sample_strip() {
    global $product;

    if ( ! $product ) return;

    $enabled = get_post_meta( $product->get_id(), '_sample_enabled', true );
    $width   = get_post_meta( $product->get_id(), '_sample_width_mm', true );
    $length  = get_post_meta( $product->get_id(), '_sample_length_mm', true );
    $price   = get_post_meta( $product->get_id(), '_sample_price', true );

    if ( $enabled !== 'yes' || ! $width || ! $length || $price === '' ) return;

    $display_price = wc_get_price_to_display( $product, array(
        'price' => (float) $price,
        'qty'   => 1,
    ) );

    static $swatch_icon = null;

    if ( null === $swatch_icon ) {
        $icon_path = get_stylesheet_directory() . '/assets/icons/swatch-book.svg';

        $swatch_icon = file_exists( $icon_path )
            ? file_get_contents( $icon_path )
            : '';
    }
    ?>
    <div class="norhage-sample-strip">
        <span class="norhage-sample-info">
            <?php if ( $swatch_icon ) : ?>
                <span class="norhage-sample-icon" aria-hidden="true"><?php echo $swatch_icon; ?></span>
            <?php endif; ?>
            <?php esc_html_e( 'Order a sample', 'nh-theme' ); ?>
            (<?php echo esc_html( $width . ' × ' . $length . ' mm' ); ?>)
            · <span class="norhage-sample-price"><?php echo wc_price( $display_price ); ?></span>
        </span>
        <button type="button"
                class="button norhage-add-sample"
                data-product_id="<?php echo esc_attr( $product->get_id() ); ?>">
            <?php esc_html_e( 'Add sample', 'nh-theme' ); ?>
        </button>
    </div>
    <?php
}

/** ------------------------------------------------------------------
 * 4. Enqueue JS for the AJAX add-to-cart button
 * ------------------------------------------------------------------ */
add_action( 'wp_enqueue_scripts', 'norhage_sample_assets' );
function norhage_sample_assets() {
    if ( ! is_product() ) return;

    wp_enqueue_script(
        'norhage-sample-order',
        get_stylesheet_directory_uri() . '/assets/js/sample-order.js',
        array( 'jquery' ),
        '1.2',
        true
    );

    wp_localize_script( 'norhage-sample-order', 'norhageSample', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'norhage_add_sample' ),
        'i18n'     => array(
            'adding'        => __( 'Adding...', 'nh-theme' ),
            'added'         => __( 'Added', 'nh-theme' ),
            'add_sample'    => __( 'Add sample', 'nh-theme' ),
            'error_generic' => __( 'Could not add sample to cart.', 'nh-theme' ),
            'error_connect' => __( 'Could not connect to WooCommerce. Please try again.', 'nh-theme' ),
        ),
    ) );
}

/** ------------------------------------------------------------------
 * 5. Helpers: resolve a real, purchasable variation for a variable
 *    product (simple or custom-cut), so the sample can be added
 *    without the customer filling the custom-cut form.
 * ------------------------------------------------------------------ */
function norhage_get_default_variation_id( $product ) {
    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        return 0;
    }

    $default_attributes = $product->get_default_attributes();
    $children            = $product->get_children();

    if ( ! empty( $default_attributes ) ) {
        foreach ( $children as $child_id ) {
            $variation = wc_get_product( $child_id );

            if ( ! $variation || ! $variation->exists() || ! $variation->is_purchasable() ) {
                continue;
            }

            $variation_attrs = $variation->get_variation_attributes();
            $is_match         = true;

            foreach ( $default_attributes as $attr_name => $attr_value ) {
                $key = 'attribute_' . sanitize_title( $attr_name );

                if ( isset( $variation_attrs[ $key ] ) && $variation_attrs[ $key ] !== '' && $variation_attrs[ $key ] !== $attr_value ) {
                    $is_match = false;
                    break;
                }
            }

            if ( $is_match ) {
                return $child_id;
            }
        }
    }

    foreach ( $children as $child_id ) {
        $variation = wc_get_product( $child_id );

        if ( $variation && $variation->exists() && $variation->is_purchasable() && $variation->is_in_stock() ) {
            return $child_id;
        }
    }

    foreach ( $children as $child_id ) {
        $variation = wc_get_product( $child_id );

        if ( $variation && $variation->exists() && $variation->is_purchasable() ) {
            return $child_id;
        }
    }

    return 0;
}

/**
 * Variation attributes Woo needs to accept add_to_cart().
 * Empty/"any" attributes are filled from the parent's first option.
 *
 * @param WC_Product $parent    Variable parent.
 * @param WC_Product $variation Variation.
 * @return array<string, string>
 */
function norhage_sample_variation_attributes( $parent, $variation ) {
    $attributes = array();

    if ( ! $parent || ! $variation || ! is_callable( array( $variation, 'get_variation_attributes' ) ) ) {
        return $attributes;
    }

    $variation_attrs = $variation->get_variation_attributes();
    $parent_attrs    = is_callable( array( $parent, 'get_variation_attributes' ) )
        ? $parent->get_variation_attributes()
        : array();

    foreach ( $parent_attrs as $attribute_name => $options ) {
        $key   = 'attribute_' . sanitize_title( $attribute_name );
        $value = isset( $variation_attrs[ $key ] ) ? (string) $variation_attrs[ $key ] : '';

        if ( '' === $value && is_array( $options ) && ! empty( $options ) ) {
            $value = (string) reset( $options );
        }

        if ( '' !== $value ) {
            $attributes[ $key ] = $value;
        }
    }

    if ( ! empty( $attributes ) ) {
        return $attributes;
    }

    foreach ( (array) $variation_attrs as $key => $value ) {
        if ( '' !== $value && null !== $value ) {
            $attributes[ $key ] = $value;
        }
    }

    return $attributes;
}

/**
 * Strip variation attributes from the stored sample line so cart/order
 * views do not show Width/Length/Colour rows. Woo still received them
 * during add_to_cart() validation.
 *
 * @param array $cart_item Cart item.
 * @return array
 */
function norhage_strip_sample_variation_attributes( $cart_item ) {
    if ( nh_is_sample_cart_item( $cart_item ) ) {
        $cart_item['variation'] = array();
    }

    return $cart_item;
}
add_filter( 'woocommerce_add_cart_item', 'norhage_strip_sample_variation_attributes', 999 );

/**
 * Put the sample price on the in-memory product immediately.
 *
 * Cart totals already use woocommerce_before_calculate_totals. Line HTML
 * (side cart, mini-cart) often reads $product->get_price() on a later
 * fragment request, after session restore recreates the catalog product.
 *
 * @param array $cart_item Cart item.
 * @return array
 */
function norhage_apply_sample_price_to_cart_item( $cart_item ) {
    if ( ! is_array( $cart_item ) || ! nh_is_sample_cart_item( $cart_item ) || ! isset( $cart_item['sample_price'] ) ) {
        return $cart_item;
    }

    if ( empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
        return $cart_item;
    }

    $sample_price = (float) $cart_item['sample_price'];
    $product      = $cart_item['data'];

    if ( is_callable( array( $product, 'set_price' ) ) ) {
        $product->set_price( $sample_price );
    }
    if ( is_callable( array( $product, 'set_regular_price' ) ) ) {
        $product->set_regular_price( $sample_price );
    }
    if ( is_callable( array( $product, 'set_sale_price' ) ) ) {
        $product->set_sale_price( '' );
    }

    return $cart_item;
}
add_filter( 'woocommerce_add_cart_item', 'norhage_apply_sample_price_to_cart_item', 20 );

/**
 * Session restore rebuilds the product from the catalog. Re-apply sample
 * price so fragment refreshes do not flash the parent product price.
 *
 * @param array $cart_item Cart item.
 * @param array $values    Session values.
 * @return array
 */
function norhage_apply_sample_price_from_session( $cart_item, $values ) {
    unset( $values );
    return norhage_apply_sample_price_to_cart_item( $cart_item );
}
add_filter( 'woocommerce_get_cart_item_from_session', 'norhage_apply_sample_price_from_session', 20, 2 );

/** ------------------------------------------------------------------
 * 6. AJAX handler: add the REAL product (or a real variation) to cart
 *    as a sample. Works for simple, variable, and custom-cut products.
 *    Variation attributes are passed so Woo accepts the line, then
 *    stripped from the stored cart item for display.
 * ------------------------------------------------------------------ */
add_action( 'wp_ajax_norhage_add_sample', 'norhage_add_sample_to_cart' );
add_action( 'wp_ajax_nopriv_norhage_add_sample', 'norhage_add_sample_to_cart' );

function norhage_add_sample_to_cart() {
    check_ajax_referer( 'norhage_add_sample', 'nonce' );

    $product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    $variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
    $product      = wc_get_product( $product_id );

    if ( ! $product ) {
        wp_send_json_error( array(
            'message' => __( 'The selected product could not be found.', 'nh-theme' ),
        ) );
    }

    if ( $product->is_type( 'variation' ) ) {
        $variation_id = $product->get_id();
        $product_id   = $product->get_parent_id();
        $product      = wc_get_product( $product_id );

        if ( ! $product ) {
            wp_send_json_error( array(
                'message' => __( 'The selected product could not be found.', 'nh-theme' ),
            ) );
        }
    }

    $enabled = get_post_meta( $product_id, '_sample_enabled', true );
    $width   = get_post_meta( $product_id, '_sample_width_mm', true );
    $length  = get_post_meta( $product_id, '_sample_length_mm', true );
    $price   = get_post_meta( $product_id, '_sample_price', true );

    if ( 'yes' !== $enabled ) {
        wp_send_json_error( array(
            'message' => __( 'Sample ordering is not enabled for this product.', 'nh-theme' ),
        ) );
    }

    if ( '' === $width || '' === $length || '' === $price ) {
        wp_send_json_error( array(
            'message' => __( 'Sample width, length, or price has not been configured.', 'nh-theme' ),
        ) );
    }

    if ( (float) $width <= 0 || (float) $length <= 0 || (float) $price < 0 ) {
        wp_send_json_error( array(
            'message' => __( 'The sample settings are not valid.', 'nh-theme' ),
        ) );
    }

    $variation_attributes = array();

    if ( $product->is_type( 'variable' ) ) {
        if ( $variation_id ) {
            $chosen = wc_get_product( $variation_id );
            if ( ! $chosen || (int) $chosen->get_parent_id() !== (int) $product_id || ! $chosen->is_purchasable() ) {
                $variation_id = 0;
            }
        }

        if ( ! $variation_id ) {
            $variation_id = norhage_get_default_variation_id( $product );
        }

        if ( ! $variation_id ) {
            wp_send_json_error( array(
                'message' => __( 'No purchasable variation was found for this product.', 'nh-theme' ),
            ) );
        }

        $variation = wc_get_product( $variation_id );
        $variation_attributes = norhage_sample_variation_attributes( $product, $variation );
    }

    $cart_item_data = array(
        'norhage_sample'   => true,
        'cutting_type'     => 'sample',
        'custom_width_mm'  => (float) $width,
        'custom_length_mm' => (float) $length,
        'custom_area_m2'   => ( (float) $width * (float) $length ) / 1000000,
        'sample_price'     => (float) wc_format_decimal( $price ),
        'sample_shipping_class' => NH_SAMPLE_SHIPPING_CLASS_SLUG,
    );

    $cart_item_key = WC()->cart->add_to_cart(
        $product_id,
        1,
        $variation_id,
        $variation_attributes,
        $cart_item_data
    );

    if ( $cart_item_key ) {
        WC()->cart->set_quantity( $cart_item_key, 1, false );
        WC()->cart->calculate_totals();
    }

    if ( ! $cart_item_key ) {
        $messages = wc_get_notices( 'error' );
        wc_clear_notices();

        $message = __( 'WooCommerce rejected this sample item.', 'nh-theme' );

        if ( ! empty( $messages[0]['notice'] ) ) {
            $message = wp_strip_all_tags( $messages[0]['notice'] );
        }

        wp_send_json_error( array(
            'message' => $message,
        ) );
    }

    wp_send_json_success( array(
        'message'       => __( 'Sample added to cart.', 'nh-theme' ),
        'cart_item_key' => $cart_item_key,
        'fragments'     => apply_filters( 'woocommerce_add_to_cart_fragments', array() ),
        'cart_hash'     => WC()->cart->get_cart_hash(),
    ) );
}

/** ------------------------------------------------------------------
 * 7. Override price for sample cart items (server-side, authoritative)
 * ------------------------------------------------------------------ */
add_action( 'woocommerce_before_calculate_totals', 'norhage_set_sample_price', 999 );

function norhage_set_sample_price( $cart ) {
    if ( is_admin() && ! wp_doing_ajax() ) {
        return;
    }

    if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
        return;
    }

    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        unset( $cart_item_key );
        norhage_apply_sample_price_to_cart_item( $cart_item );
    }
}

add_filter( 'woocommerce_cart_item_price', 'norhage_filter_sample_cart_item_price', 20, 3 );
add_filter( 'woocommerce_cart_item_subtotal', 'norhage_filter_sample_cart_item_subtotal', 20, 3 );

/**
 * @param string $html       Price HTML.
 * @param array  $cart_item  Cart item.
 * @param string $cart_item_key Key.
 * @return string
 */
function norhage_filter_sample_cart_item_price( $html, $cart_item, $cart_item_key ) {
    unset( $cart_item_key );
    $cart_item = norhage_apply_sample_price_to_cart_item( $cart_item );
    if ( ! nh_is_sample_cart_item( $cart_item ) || empty( $cart_item['data'] ) || ! WC()->cart ) {
        return $html;
    }
    return WC()->cart->get_product_price( $cart_item['data'] );
}

/**
 * @param string $html       Subtotal HTML.
 * @param array  $cart_item  Cart item.
 * @param string $cart_item_key Key.
 * @return string
 */
function norhage_filter_sample_cart_item_subtotal( $html, $cart_item, $cart_item_key ) {
    unset( $cart_item_key );
    $cart_item = norhage_apply_sample_price_to_cart_item( $cart_item );
    if ( ! nh_is_sample_cart_item( $cart_item ) || empty( $cart_item['data'] ) || ! WC()->cart ) {
        return $html;
    }
    $qty = isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1;
    return WC()->cart->get_product_subtotal( $cart_item['data'], $qty );
}

/** ------------------------------------------------------------------
 * 8. Save clean order item meta at checkout.
 *
 *    NOTE: This is the ONLY place that writes order-item meta for
 *    samples. Display (what's actually visible) is controlled
 *    separately by order-attributes.php's formatted-meta filter —
 *    this just writes the two allowed rows plus the internal marker.
 * ------------------------------------------------------------------ */
add_action( 'woocommerce_checkout_create_order_line_item', 'norhage_save_sample_order_item_meta', 100, 4 );

function norhage_save_sample_order_item_meta( $item, $cart_item_key, $values, $order ) {
    if ( empty( $values['norhage_sample'] ) ) {
        return;
    }

    $item->add_meta_data( '_nh_sample', 'yes', true );

    $product = $item->get_product();

    if ( $product ) {
        $parent_id = $product->get_parent_id();
        $title     = $parent_id ? get_the_title( $parent_id ) : $product->get_name();

        if ( $title ) {
            $item->set_name( $title );
        }
    }

    $item->add_meta_data(
        __( 'Cutting type', 'nh-theme' ),
        __( 'Sample', 'nh-theme' ),
        true
    );

    $item->add_meta_data(
        __( 'Width', 'nh-theme' ),
        $values['custom_width_mm'] . ' mm',
        true
    );

    $item->add_meta_data(
        __( 'Length', 'nh-theme' ),
        $values['custom_length_mm'] . ' mm',
        true
    );
}

/** ------------------------------------------------------------------
 * 9. Sample quantity: always 1 and not editable
 * ------------------------------------------------------------------ */
add_action( 'woocommerce_before_calculate_totals', 'norhage_force_sample_quantity_one', 5 );

function norhage_force_sample_quantity_one( $cart ) {
    if ( is_admin() && ! wp_doing_ajax() ) {
        return;
    }

    if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
        return;
    }

    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( nh_is_sample_cart_item( $cart_item ) && 1 !== (int) $cart_item['quantity'] ) {
            $cart->set_quantity( $cart_item_key, 1, false );
        }
    }
}

add_filter( 'woocommerce_cart_item_quantity', 'norhage_sample_cart_item_quantity', 999, 3 );

function norhage_sample_cart_item_quantity( $product_quantity, $cart_item_key, $cart_item ) {
    if ( ! nh_is_sample_cart_item( $cart_item ) ) {
        return $product_quantity;
    }

    return sprintf(
        '<input type="hidden" name="cart[%1$s][qty]" value="1" />' .
        '<span class="norhage-sample-fixed-quantity">%2$s</span>',
        esc_attr( $cart_item_key ),
        esc_html__( 'Qty: 1', 'nh-theme' )
    );
}

add_filter( 'woocommerce_cart_item_class', 'norhage_add_sample_cart_item_class', 20, 3 );

function norhage_add_sample_cart_item_class( $class, $cart_item, $cart_item_key ) {
    if ( nh_is_sample_cart_item( $cart_item ) ) {
        $class .= ' norhage-sample-cart-item';
    }

    return $class;
}

/** ------------------------------------------------------------------
 * 10. Force "xs" shipping class on sample cart items
 *
 * All shops use the same slug (xs). Term IDs differ per site, so look
 * up by slug. This must win over the shipping calculator, which would
 * otherwise assign the parent product's editor class to custom-cut
 * samples.
 * ------------------------------------------------------------------ */
function norhage_get_xs_shipping_class_id() {
    static $term_id = null;

    if ( null !== $term_id ) {
        return $term_id;
    }

    $slug = NH_SAMPLE_SHIPPING_CLASS_SLUG;
    if ( class_exists( 'NHGP_Custom_Cut' ) ) {
        $slug = NHGP_Custom_Cut::SAMPLE_SHIPPING_CLASS_SLUG;
    }

    if ( class_exists( 'NHGP_Custom_Cut' ) && is_callable( array( 'NHGP_Custom_Cut', 'term_id_from_slug' ) ) ) {
        $term_id = (int) NHGP_Custom_Cut::term_id_from_slug( $slug );
        if ( $term_id > 0 ) {
            return $term_id;
        }
    }

    $term = get_term_by( 'slug', $slug, 'product_shipping_class' );

    $term_id = ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;

    return $term_id;
}

function norhage_apply_xs_shipping_class_to_product( $product ) {
    if ( ! $product || ! is_callable( array( $product, 'set_shipping_class_id' ) ) ) {
        return $product;
    }

    $xs_term_id = norhage_get_xs_shipping_class_id();
    if ( $xs_term_id ) {
        $product->set_shipping_class_id( $xs_term_id );
    }

    return $product;
}

add_action( 'woocommerce_before_calculate_totals', 'norhage_set_sample_shipping_class', 999 );

function norhage_set_sample_shipping_class( $cart ) {
    if ( is_admin() && ! wp_doing_ajax() ) {
        return;
    }

    if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
        return;
    }

    foreach ( $cart->get_cart() as $cart_item ) {
        if ( nh_is_sample_cart_item( $cart_item ) ) {
            norhage_apply_xs_shipping_class_to_product( $cart_item['data'] );
        }
    }
}

add_filter( 'woocommerce_cart_item_shipping_class', 'norhage_filter_sample_shipping_class_slug', 999, 3 );

function norhage_filter_sample_shipping_class_slug( $shipping_class, $cart_item, $cart_item_key ) {
    unset( $cart_item_key );

    if ( nh_is_sample_cart_item( $cart_item ) ) {
        return NH_SAMPLE_SHIPPING_CLASS_SLUG;
    }

    return $shipping_class;
}

/**
 * Re-apply xs after the shipping calculator stamps package lines
 * (it runs on this filter at priority 20 and would otherwise restore
 * the catalog/editor class on custom-cut samples).
 *
 * @param array $packages Packages.
 * @return array
 */
function norhage_stamp_sample_shipping_class_on_packages( $packages ) {
    if ( ! is_array( $packages ) ) {
        return $packages;
    }

    foreach ( $packages as $i => $package ) {
        $contents = isset( $package['contents'] ) && is_array( $package['contents'] )
            ? $package['contents']
            : array();

        foreach ( $contents as $key => $item ) {
            if ( ! nh_is_sample_cart_item( $item ) ) {
                continue;
            }

            if ( empty( $item['data'] ) ) {
                continue;
            }

            $packages[ $i ]['contents'][ $key ]['data'] = norhage_apply_xs_shipping_class_to_product( $item['data'] );
        }
    }

    return $packages;
}
add_filter( 'woocommerce_cart_shipping_packages', 'norhage_stamp_sample_shipping_class_on_packages', 999 );

/**
 * Keep Woo Flat Rate's class buckets on xs for sample lines.
 *
 * @param array $found   slug => items.
 * @param array $package Shipping package.
 * @return array
 */
function norhage_remap_sample_shipping_classes( $found, $package ) {
    if ( ! is_array( $found ) ) {
        $found = array();
    }

    $contents = ( isset( $package['contents'] ) && is_array( $package['contents'] ) )
        ? $package['contents']
        : array();

    $slug = NH_SAMPLE_SHIPPING_CLASS_SLUG;

    foreach ( $contents as $item_id => $item ) {
        if ( ! nh_is_sample_cart_item( $item ) ) {
            continue;
        }

        foreach ( $found as $class => $items ) {
            if ( isset( $items[ $item_id ] ) ) {
                unset( $found[ $class ][ $item_id ] );
                if ( empty( $found[ $class ] ) ) {
                    unset( $found[ $class ] );
                }
            }
        }

        if ( ! isset( $found[ $slug ] ) ) {
            $found[ $slug ] = array();
        }
        $found[ $slug ][ $item_id ] = $item;
    }

    return $found;
}
add_filter( 'woocommerce_find_shipping_classes', 'norhage_remap_sample_shipping_classes', 999, 2 );

/** ------------------------------------------------------------------
 * 11. Weight: zero out for samples (cart, order, PDF)
 * ------------------------------------------------------------------ */
add_action( 'woocommerce_before_calculate_totals', 'nh_zero_weight_for_sample_cart_item', 20 );

function nh_zero_weight_for_sample_cart_item( $cart ) {
    if ( ! $cart instanceof WC_Cart ) {
        return;
    }

    foreach ( $cart->get_cart() as $cart_item ) {
        if ( nh_is_sample_cart_item( $cart_item ) && is_callable( array( $cart_item['data'], 'set_weight' ) ) ) {
            $cart_item['data']->set_weight( '' );
        }
    }
}

add_filter( 'woocommerce_order_item_product', 'nh_strip_weight_for_sample_order_item', 999, 2 );
function nh_strip_weight_for_sample_order_item( $product, $item ) {
    if ( ! $product instanceof WC_Product || ! ( $item instanceof WC_Order_Item_Product ) ) {
        return $product;
    }

    if ( nh_is_sample_order_item( $item ) ) {
        $clone = clone $product;
        $clone->set_weight( '' );
        return $clone;
    }

    return $product;
}

/** ------------------------------------------------------------------
 * 12. Hide the internal "_nh_sample" marker in WooCommerce Admin
 *     order-item meta table.
 * ------------------------------------------------------------------ */
add_filter( 'woocommerce_hidden_order_itemmeta', 'norhage_hide_sample_internal_order_meta' );

function norhage_hide_sample_internal_order_meta( $hidden_meta ) {
    $hidden_meta[] = '_nh_sample';

    return array_unique( $hidden_meta );
}

/** ------------------------------------------------------------------
 * 13. PDF Invoices & Packing Slips (WPO WCPDF): hide weight for samples
 * ------------------------------------------------------------------ */
add_filter( 'wpo_wcpdf_item_weight', 'nh_hide_pdf_invoice_sample_weight', 999, 3 );
function nh_hide_pdf_invoice_sample_weight( $weight, $item, $document ) {
    if ( nh_is_sample_order_item( $item ) ) {
        return '';
    }
    return $weight;
}

add_filter( 'wpo_wcpdf_order_item_data', 'nh_clean_sample_order_item_for_pdf', 999, 3 );
function nh_clean_sample_order_item_for_pdf( $data, $order, $document_type ) {
    if ( empty( $data['item'] ) || ! ( $data['item'] instanceof WC_Order_Item_Product ) ) {
        return $data;
    }

    if ( nh_is_sample_order_item( $data['item'] ) ) {
        if ( isset( $data['weight'] ) ) {
            $data['weight'] = '';
        }
    }

    return $data;
}
