<?php
/**
 * Order / email attributes display
 * - Show product attributes under the product name (same idea as cart).
 * - Works for simple + variable products.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build attribute label => value pairs for an order line item.
 */
function nh_order_item_attribute_pairs( WC_Order_Item_Product $item ): array {
	$pairs   = [];
	$product = $item->get_product();

	if ( ! $product ) {
		return $pairs;
	}

	/* ---------------------------------------------------------
	 * CASE A: variation product → show ONLY attributes that are
	 *         flagged "Used for variations" on the parent.
	 * -------------------------------------------------------*/
	if ( $product->is_type( 'variation' ) ) {

		// Chosen attribute values for this variation: [ 'pa_length' => '6-m', ... ]
		$var_attrs = $product->get_attributes();

		if ( empty( $var_attrs ) ) {
			return $pairs;
		}

		$parent         = wc_get_product( $product->get_parent_id() );
		$parent_attrs   = $parent ? $parent->get_attributes() : [];
		$product_for_lb = $parent ?: $product;

		foreach ( $var_attrs as $attr_slug => $val ) {
			if ( $val === '' ) {
				continue;
			}

			// Is this attribute actually used for variations on the parent?
			$is_variation_attr = true;
			if ( isset( $parent_attrs[ $attr_slug ] ) && $parent_attrs[ $attr_slug ] instanceof WC_Product_Attribute ) {
				$is_variation_attr = (bool) $parent_attrs[ $attr_slug ]->get_variation();
			}

			if ( ! $is_variation_attr ) {
				continue;
			}

			$label = wc_attribute_label( $attr_slug, $product_for_lb );
			if ( $label === '' ) {
				continue;
			}

			$val = wc_clean( $val );

			if ( taxonomy_exists( $attr_slug ) ) {
				$term = get_term_by( 'slug', $val, $attr_slug );
				if ( $term && ! is_wp_error( $term ) ) {
					$val = $term->name;
				} else {
					$val = wc_clean( str_replace( '-', ' ', $val ) );
				}
			}

			if ( $val !== '' ) {
				$pairs[ $label ] = $val;
			}
		}

		return $pairs;
	}

	/* ---------------------------------------------------------
	 * CASE B: simple product → show all product attributes
	 * -------------------------------------------------------*/
	$attributes = $product->get_attributes();

	if ( empty( $attributes ) ) {
		return $pairs;
	}

	foreach ( $attributes as $attr_key => $attr ) {
		$attr_name = '';
		$options   = [];
		$is_tax    = false;

		// New-style: WC_Product_Attribute object.
		if ( $attr instanceof WC_Product_Attribute ) {
			$attr_name = $attr->get_name();
			$options   = $attr->get_options();
			$is_tax    = $attr->is_taxonomy();
		} else {
			// Old-style / edge cases: array or string.
			$attr_name = is_string( $attr_key ) ? $attr_key : '';
			if ( is_array( $attr ) && isset( $attr['options'] ) ) {
				$options = (array) $attr['options'];
			} else {
				$options = is_array( $attr ) ? $attr : (array) $attr;
			}
			$is_tax = $attr_name && taxonomy_exists( $attr_name );
		}

		if ( ! $attr_name ) {
			continue;
		}

		$label = wc_attribute_label( $attr_name, $product );

		if ( $is_tax ) {
			$names = [];
			foreach ( $options as $term_id ) {
				if ( is_numeric( $term_id ) ) {
					$term = get_term( (int) $term_id );
				} else {
					$term = get_term_by( 'slug', $term_id, $attr_name );
				}
				if ( $term && ! is_wp_error( $term ) ) {
					$names[] = $term->name;
				}
			}
			$value = implode( ', ', $names );
		} else {
			$value = implode( ', ', array_map( 'wc_clean', $options ) );
		}

		if ( $label && $value !== '' ) {
			$pairs[ $label ] = $value;
		}
	}

	return $pairs;
}

/**
 * Render pairs as <dl class="variation">…</dl>.
 */
function nh_order_render_dl_variation( array $pairs ): string {
	if ( empty( $pairs ) ) {
		return '';
	}

	$html = '<dl class="variation">';

	foreach ( $pairs as $label => $val ) {
		$cls   = 'variation-' . preg_replace( '/\s+/', '', ucwords( wp_strip_all_tags( $label ) ) );
		$html .= '<dt class="' . esc_attr( $cls ) . '">' . esc_html( $label ) . ':</dt>';
		$html .= '<dd class="' . esc_attr( $cls ) . '"><p>' . esc_html( $val ) . '</p></dd>';
	}

	$html .= '</dl>';

	return $html;
}

/**
 * Small helper so we can reuse rendering.
 */
function nh_order_print_item_attributes_block( WC_Order_Item_Product $item ) {
	$pairs = nh_order_item_attribute_pairs( $item );
	if ( empty( $pairs ) ) {
		return;
	}

	echo nh_order_render_dl_variation( $pairs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * For variations: hide Woo's default attribute meta where we output our own
 * block (frontend + emails). **Do not** change admin order screen.
 */
add_filter( 'woocommerce_order_item_get_formatted_meta_data', function ( $formatted_meta, $item ) {
	if ( ! ( $item instanceof WC_Order_Item_Product ) ) {
		return $formatted_meta;
	}

	// Important: keep admin order items untouched so attributes show
	// in the compact "order items" table.
	if ( is_admin() ) {
		return $formatted_meta;
	}

	$product = $item->get_product();

	if ( $product && $product->is_type( 'variation' ) ) {
		$parent = wc_get_product( $product->get_parent_id() );
		if ( $parent ) {
			$attr_labels = [];

			foreach ( $parent->get_attributes() as $key => $attr ) {
				if ( $attr instanceof WC_Product_Attribute ) {
					$name = $attr->get_name();
				} else {
					$name = is_string( $key ) ? $key : '';
				}
				if ( ! $name ) {
					continue;
				}
				$attr_labels[] = wc_attribute_label( $name, $parent );
			}

			$filtered = [];
			foreach ( $formatted_meta as $fm ) {
				if ( ! in_array( $fm->display_key, $attr_labels, true ) ) {
					$filtered[] = $fm;
				}
			}

			return $filtered;
		}
	}

	return $formatted_meta;
}, 10, 2 );

/**
 * FRONTEND + EMAILS:
 * Print attribute list under product name in:
 * - thank-you page
 * - My Account → View order
 * - emails
 */
add_action( 'woocommerce_order_item_meta_start', function ( $item_id, $item, $order, $plain_text ) {
	if ( $plain_text ) {
		return;
	}

	if ( ! ( $item instanceof WC_Order_Item_Product ) ) {
		return;
	}

	nh_order_print_item_attributes_block( $item );
}, 10, 4 );
