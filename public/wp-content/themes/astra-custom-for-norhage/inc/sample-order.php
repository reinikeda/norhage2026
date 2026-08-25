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
 * - sample price, quantity, shipping class
 * - saving sample order-item meta at checkout
 * - stripping weight from samples (cart, order, PDF)
 *
 * It does NOT touch woocommerce_get_item_data (cart display) or
 * woocommerce_order_item_get_formatted_meta_data (order display).
 * Those are owned exclusively by basket-customize.php and
 * order-attributes.php respectively, to avoid two files fighting
 * over the same filter.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

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
        '1.0',
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
 * 5. Helper: resolve a real, purchasable variation ID for a variable
 *    product, so the sample can be added without the customer manually
 *    choosing options.
 * ------------------------------------------------------------------ */
function norhage_get_default_variation_id( $product ) {
    if ( ! $product->is_type( 'variable' ) ) {
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

/** ------------------------------------------------------------------
 * 6. AJAX handler: add the REAL product (or a real variation) to cart
 *    as a sample. Empty variation attributes array is intentional —
 *    see the file header note.
 * ------------------------------------------------------------------ */
add_action( 'wp_ajax_norhage_add_sample', 'norhage_add_sample_to_cart' );
add_action( 'wp_ajax_nopriv_norhage_add_sample', 'norhage_add_sample_to_cart' );

function norhage_add_sample_to_cart() {
    check_ajax_referer( 'norhage_add_sample', 'nonce' );

    $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    $product    = wc_get_product( $product_id );

    if ( ! $product ) {
        wp_send_json_error( array(
            'message' => __( 'The selected product could not be found.', 'nh-theme' ),
        ) );
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

    $variation_id = 0;

    if ( $product->is_type( 'variable' ) ) {
        $variation_id = norhage_get_default_variation_id( $product );

        if ( ! $variation_id ) {
            wp_send_json_error( array(
                'message' => __( 'No purchasable variation was found for this product.', 'nh-theme' ),
            ) );
        }
    }

    $cart_item_data = array(
        'norhage_sample'   => true,
        'cutting_type'     => 'sample',
        'custom_width_mm'  => (float) $width,
        'custom_length_mm' => (float) $length,
        'custom_area_m2'   => ( (float) $width * (float) $length ) / 1000000,
        'sample_price'     => (float) wc_format_decimal( $price ),
    );

    $cart_item_key = WC()->cart->add_to_cart(
        $product_id,
        1,
        $variation_id,
        array(),
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

    foreach ( $cart->get_cart() as $cart_item ) {
        if ( nh_is_sample_cart_item( $cart_item ) && isset( $cart_item['sample_price'] ) ) {
            $sample_price = (float) $cart_item['sample_price'];

            $cart_item['data']->set_price( $sample_price );
            $cart_item['data']->set_regular_price( $sample_price );
            $cart_item['data']->set_sale_price( '' );
        }
    }
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
 * ------------------------------------------------------------------ */
function norhage_get_xs_shipping_class_id() {
    static $term_id = null;

    if ( null !== $term_id ) {
        return $term_id;
    }

    $term = get_term_by( 'slug', 'xs', 'product_shipping_class' );

    $term_id = ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;

    return $term_id;
}

add_action( 'woocommerce_before_calculate_totals', 'norhage_set_sample_shipping_class', 999 );

function norhage_set_sample_shipping_class( $cart ) {
    if ( is_admin() && ! wp_doing_ajax() ) {
        return;
    }

    if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
        return;
    }

    $xs_term_id = norhage_get_xs_shipping_class_id();

    if ( ! $xs_term_id ) {
        return;
    }

    foreach ( $cart->get_cart() as $cart_item ) {
        if ( nh_is_sample_cart_item( $cart_item ) && is_callable( array( $cart_item['data'], 'set_shipping_class_id' ) ) ) {
            $cart_item['data']->set_shipping_class_id( $xs_term_id );
        }
    }
}

add_filter( 'woocommerce_cart_item_shipping_class', 'norhage_filter_sample_shipping_class_slug', 999, 3 );

function norhage_filter_sample_shipping_class_slug( $shipping_class, $cart_item, $cart_item_key ) {
    if ( nh_is_sample_cart_item( $cart_item ) ) {
        return 'xs';
    }

    return $shipping_class;
}

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
