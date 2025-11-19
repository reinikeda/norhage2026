<?php
/**
 * Order / email attribute display
 *
 * Shows the same attribute list you see in the cart
 * under each line item on:
 * - Order received ("thank you") page
 * - My Account → View order
 * - All HTML WooCommerce emails
 * - Admin single order screen
 *
 * Uses nh_render_dl_variation() and nh_is_custom_cut_item()
 * defined in basket-customize.php.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Build label => value pairs for an order item.
 *
 * 1) First tries attributes actually saved on the order item
 *    (variation items: attribute_pa_xxx etc.).
 * 2) If nothing is found, falls back to the product's own attributes
 *    (simple products with attributes).
 */
function nh_order_item_attribute_pairs( WC_Order_Item_Product $item ) : array {
	$pairs   = [];
	$product = $item->get_product();

	if ( ! $product instanceof WC_Product ) {
		return $pairs;
	}

	// --- 1) Attributes saved on the order item (variations etc.) ---
	foreach ( $item->get_meta_data() as $m ) {
		$key = $m->key ?? '';
		if ( strpos( $key, 'attribute_' ) === 0 ) {
			$attr_slug = substr( $key, strlen( 'attribute_' ) );
			$label     = wc_attribute_label( $attr_slug, $product );
			$val       = wc_clean( $m->value );

			if ( taxonomy_exists( $attr_slug ) ) {
				$term = get_term_by( 'slug', $val, $attr_slug );
				if ( $term && ! is_wp_error( $term ) ) {
					$val = $term->name;
				}
			}

			if ( $label && $val !== '' ) {
				$pairs[ $label ] = $val;
			}
		}
	}

	// --- 2) Fallback: live attributes from the product (simple products etc.) ---
	if ( empty( $pairs ) ) {
		$attributes = $product->get_attributes();

		foreach ( $attributes as $attr ) {
			if ( ! $attr ) {
				continue;
			}

			$name   = $attr->get_name(); // pa_length or custom name
			$label  = wc_attribute_label( $name, $product );
			$values = $attr->get_options();

			if ( $attr->is_taxonomy() ) {
				$names = [];
				foreach ( $values as $term_id ) {
					$term = get_term( $term_id );
					if ( $term && ! is_wp_error( $term ) ) {
						$names[] = $term->name;
					}
				}
				$value = implode( ', ', $names );
			} else {
				$value = implode( ', ', $values );
			}

			if ( $label && $value !== '' ) {
				$pairs[ $label ] = $value;
			}
		}
	}

	return $pairs;
}

/**
 * Remove Woo's own attribute meta rows so we don't get duplicates.
 */
add_filter( 'woocommerce_order_item_get_formatted_meta_data', function ( $formatted_meta, $item ) {

	if ( ! ( $item instanceof WC_Order_Item_Product ) ) {
		return $formatted_meta;
	}

	// Custom-cut items handle their own meta elsewhere.
	if ( function_exists( 'nh_is_custom_cut_item' ) && nh_is_custom_cut_item( $item ) ) {
		return $formatted_meta;
	}

	$product = $item->get_product();
	if ( ! $product instanceof WC_Product ) {
		return $formatted_meta;
	}

	// Collect attribute labels for this product.
	$attr_labels = [];
	foreach ( $product->get_attributes() as $attr_key => $attr_obj ) {
		$attr_name   = is_string( $attr_key ) ? $attr_key : $attr_obj->get_name();
		$attr_labels[] = wc_attribute_label( $attr_name, $product );
	}

	if ( empty( $attr_labels ) ) {
		return $formatted_meta;
	}

	$filtered = [];
	foreach ( $formatted_meta as $fm ) {
		// Skip meta rows that are just Woo's own attribute output.
		if ( in_array( $fm->display_key, $attr_labels, true ) ) {
			continue;
		}
		$filtered[] = $fm;
	}

	return $filtered;

}, 10, 2 );

/**
 * Print `<dl class="variation">` with attributes under the line item name.
 */
add_action( 'woocommerce_order_item_meta_start', function ( $item_id, $item, $order, $plain_text ) {

	// Only HTML (frontend + HTML emails)
	if ( $plain_text ) {
		return;
	}

	if ( ! ( $item instanceof WC_Order_Item_Product ) ) {
		return;
	}

	// Custom-cut line items already output their own block.
	if ( function_exists( 'nh_is_custom_cut_item' ) && nh_is_custom_cut_item( $item ) ) {
		return;
	}

	$pairs = nh_order_item_attribute_pairs( $item );
	if ( empty( $pairs ) ) {
		return;
	}

	// Use the same renderer as cart/checkout if available.
	if ( function_exists( 'nh_render_dl_variation' ) ) {
		echo nh_render_dl_variation( $pairs );
	} else {
		// Safety fallback – still output something if the helper is missing.
		echo '<dl class="variation">';
		foreach ( $pairs as $label => $val ) {
			echo '<dt>' . esc_html( $label ) . ':</dt><dd><p>' . esc_html( $val ) . '</p></dd>';
		}
		echo '</dl>';
	}

}, 10, 4 );
