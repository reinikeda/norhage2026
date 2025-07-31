<?php
/**
 * Append swatches under every variation<select>:
 *  – Colour attributes become color bullets (hex from term description)
 *  – All other attributes become text buttons
 */
add_filter( 'woocommerce_dropdown_variation_attribute_options_html', 'nrh_all_attribute_swatches', 20, 2 );
function nrh_all_attribute_swatches( $html, $args ) {
    // Only on single product pages for variable products
    if ( ! is_product() ) {
        return $html;
    }
    global $product;
    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        return $html;
    }

    $attribute = $args['attribute']; // e.g. 'pa_colour' or 'pa_width'
    $name      = $args['name'];      // e.g. 'attribute_pa_colour'
    $options   = $args['options'];   // available slugs for this product

    // Fetch all terms for this attribute on the product
    $terms = wc_get_product_terms( $product->get_id(), $attribute, [
        'fields' => 'all',
    ] );
    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return $html;
    }

    // Start our swatches container
    $swatches  = '<div class="nrh-variation-swatches nrh-swatches-' . esc_attr( sanitize_title( $attribute ) ) . '">';
    // Hidden fallback so Woo still sees an input with the same name
    $swatches .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="" />';

    foreach ( $terms as $term ) {
        if ( ! in_array( $term->slug, $options, true ) ) {
            continue;
        }

        $slug  = esc_attr( $term->slug );
        $label = esc_html( $term->name );

        // Colour attribute? render a bullet
        if ( in_array( $attribute, [ 'pa_colour', 'pa_color' ], true ) ) {
            // use term->description as hex, fallback to grey
            $hex = trim( $term->description );
            if ( ! preg_match( '/^#[0-9A-Fa-f]{6}$/', $hex ) ) {
                $hex = '#ccc';
            }
            $swatches .= sprintf(
                '<button type="button" class="nrh-swatch nrh-swatch-color" data-value="%1$s" style="background:%2$s" aria-label="%3$s"></button>',
                $slug, $hex, $label
            );

        // Non-colour attribute? render text button
        } else {
            $swatches .= sprintf(
                '<button type="button" class="nrh-swatch nrh-swatch-text" data-value="%1$s">%2$s</button>',
                $slug, $label
            );
        }
    }

    $swatches .= '</div>';

    // Return the original <select> HTML + our swatches underneath
    return $html . $swatches;
}

// Enqueue the JS, after Woo’s variation script
add_action( 'wp_enqueue_scripts', function(){
    if ( is_product() ) {
        wp_enqueue_script(
            'nrh-color-swatches',
            get_stylesheet_directory_uri() . '/assets/js/variation-swatches.js',
            array( 'jquery', 'wc-add-to-cart-variation' ),
            '1.0',
            true
        );
    }
});

// Enqueue the JS to listen to updated variation proce
add_action( 'wp_enqueue_scripts', function(){
    if ( is_product() ) {
        wp_enqueue_script(
            'nrh-price-qty-sync',
            get_stylesheet_directory_uri() . '/assets/js/price-quantity-sync.js',
            array( 'jquery', 'wc-add-to-cart-variation' ),
            '1.0',
            true
        );
    }
});

// Remove default upsells after product description
remove_action('woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15);

// Product bundle block

// 1) AJAX callback for fetching variation price
add_action('wp_ajax_get_variation_id_from_attributes',       'get_variation_id_from_attributes_callback');
add_action('wp_ajax_nopriv_get_variation_id_from_attributes','get_variation_id_from_attributes_callback');
function get_variation_id_from_attributes_callback() {
    $product_id = absint( $_POST['product_id'] );
    $attributes = json_decode( stripslashes( $_POST['attributes'] ), true );

    if ( ! $product_id || empty( $attributes ) ) {
        wp_send_json_error('invalid data');
    }

    $product = wc_get_product( $product_id );
    if ( ! $product || ! $product->is_type('variable') ) {
        wp_send_json_error('not variable');
    }

    foreach ( $product->get_available_variations() as $variation ) {
        $match = true;
        foreach ( $attributes as $k => $v ) {
            if ( ! isset( $variation['attributes'][$k] ) || $variation['attributes'][$k] !== $v ) {
                $match = false; break;
            }
        }
        if ( $match ) {
            $price = isset($variation['display_price'])
                ? floatval($variation['display_price'])
                : floatval( $variation['display_regular_price'] );
            wp_send_json_success([ 'price' => $price ]);
        }
    }
    wp_send_json_error('no match');
}

// 2) Output our “Buy a complete set” block immediately after the main product form
add_action( 'woocommerce_after_add_to_cart_form', 'nc_complete_set_bundle' );
function nc_complete_set_bundle() {
    global $product;

    if ( ! is_product() ) {
        return;
    }

    // Gather upsell products
    $upsell_ids = $product->get_upsell_ids();
    if ( empty( $upsell_ids ) ) {
        return;
    }

    // Wrapper
    echo '<div id="nc-complete-set">';

      // Heading
      echo '<h3>Buy a complete set</h3>';

      // Start the bundle form
      echo '<form id="nc-bundle-form">';

        // Header row
        echo '<div class="bundle-row bundle-header">';
          echo '<div class="col-image">Image</div>';
          echo '<div class="col-title">Product</div>';
          echo '<div class="col-options">Options</div>';
          echo '<div class="col-qty">Qty</div>';
          echo '<div class="col-price">Price</div>';
        echo '</div>';

        // Loop through each upsell
        foreach ( $upsell_ids as $upsell_id ) {
          $upsell = wc_get_product( $upsell_id );
          if ( ! $upsell ) {
            continue;
          }

          echo '<div class="bundle-row" data-product-id="' . esc_attr( $upsell->get_id() ) . '">';

            // 1) Image
            echo '<div class="col-image">' . $upsell->get_image( 'thumbnail' ) . '</div>';

            // 2) Name
            echo '<div class="col-title">' . esc_html( $upsell->get_name() ) . '</div>';

            // 3) Attributes (if variable)
            echo '<div class="col-options">';
            if ( $upsell->is_type( 'variable' ) ) {
              $attributes = $upsell->get_variation_attributes();
              foreach ( $attributes as $attr_name => $options ) {
                echo '<select name="attribute_' . esc_attr( $upsell->get_id() ) . '[' . esc_attr( $attr_name ) . ']" class="bundle-variation">';
                  echo '<option value="">' . wc_attribute_label( $attr_name ) . '</option>';
                  foreach ( $options as $opt ) {
                    echo '<option value="' . esc_attr( $opt ) . '">' . esc_html( $opt ) . '</option>';
                  }
                echo '</select>';
              }
            }
            echo '</div>';

            // 4) Quantity
            echo '<div class="col-qty">';
              echo '<input type="number" name="quantity[' . esc_attr( $upsell->get_id() ) . ']" value="0" min="0" class="bundle-qty">';
            echo '</div>';

            // 5) Price (static base price for now)
            echo '<div class="col-price">';
              echo '<span class="bundle-price" data-base-price="' . esc_attr( $upsell->get_price() ) . '">';
                echo wc_price( $upsell->get_price() );
              echo '</span>';
            echo '</div>';

          echo '</div>'; // .bundle-row
        }

        // Footer: total & button
        echo '<div class="bundle-footer">';
          echo '<div id="bundle-total">Total: <span id="bundle-total-amount">€0.00</span></div>';
          echo '<button type="button" id="add-bundle-to-cart">Add All to Cart</button>';
        echo '</div>';

      echo '</form>';

    echo '</div>';
}

/**
 * Enqueue our bundle script on single-product pages
 */
add_action('wp_enqueue_scripts', function () {
    if ( is_product() ) {
        wp_enqueue_script(
            'bundle-add-to-cart',
            get_stylesheet_directory_uri() . '/assets/js/bundle-add-to-cart.js',
            [ 'jquery', 'wc-add-to-cart-variation' ],
            null,
            true
        );
        wp_localize_script('bundle-add-to-cart', 'bundle_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'cart_url' => wc_get_cart_url(),
        ]);
    }
});
