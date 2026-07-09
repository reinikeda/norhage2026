<?php
/**
 * Order / email attributes display
 * - Show product attributes under the product name (same idea as cart).
 * - Works for simple + variable products.
 * - NEW: Also shows custom-cut Width/Length/Fee for variations (previously missing).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extract measured-sheet info (Width/Length/Fee) from order item meta.
 * Returns array [ 'width' => string, 'length' => string, 'fee' => mixed ]
 */
function nh_order_item_measured_meta( WC_Order_Item_Product $item ): array {
	$meta_data = $item->get_meta_data();

	$width_val  = '';
	$length_val = '';
	$fee_val    = '';

	foreach ( $meta_data as $meta ) {
		$key = (string) ( $meta->key ?? '' );
		$val = $meta->value ?? '';

		if ( $val === '' ) {
			continue;
		}

		switch ( $key ) {
			// Technical keys (preferred)
			case 'cutting_width':
				$width_val = (string) $val;
				break;

			case 'cutting_height':
			case 'cutting_length_m':
			case 'nh_length_m': // Add this to capture the linear length
			case '_nh_length_m': // And the hidden version
				$length_val = (string) $val;
				// If it's just a number, append " m" for display
				if ( is_numeric($length_val) ) {
					$length_val .= ' m';
				}
				break;

			case 'cutting_fee':
			case 'cutting_fee_per_sheet':
			case 'cutting_fee_per_unit':
				$fee_val = $val;
				break;

			// Backwards compatibility / Human keys
			case 'Width':
				if ( $width_val === '' ) {
					$width_val = (string) $val;
				}
				break;

			case 'Length':
				// If we already found a technical length, don't overwrite it with the same value
				if ( $length_val === '' ) {
					$length_val = (string) $val;
				}
				break;

			case 'Cutting fee per sheet':
			case 'Cutting fee per unit':
				if ( $fee_val === '' ) {
					$fee_val = $val;
				}
				break;
		}
	}

	return [
		'width'  => $width_val,
		'length' => $length_val,
		'fee'    => $fee_val,
	];
}

/**
 * Apply measured-sheet meta to pairs array (Width/Length/Fee) if present.
 */
function nh_order_apply_measured_pairs( array $pairs, WC_Order_Item_Product $item ): array {
	$m = nh_order_item_measured_meta( $item );

	$width_val  = $m['width'] ?? '';
	$length_val = $m['length'] ?? '';
	$fee_val    = $m['fee'] ?? '';

	$is_measured_sheet = ( $width_val !== '' || $length_val !== '' || $fee_val !== '' );

	if ( ! $is_measured_sheet ) {
		return $pairs;
	}

	if ( $width_val !== '' ) {
		$pairs[ __( 'Width', 'nh-theme' ) ] = $width_val;
	}

	if ( $length_val !== '' ) {
		$pairs[ __( 'Length', 'nh-theme' ) ] = $length_val;
	}

	if ( $fee_val !== '' ) {
		$product = $item->get_product();

		// Fee can be numeric (raw) or already formatted. If numeric, format as display price.
		if ( is_numeric( $fee_val ) ) {
			$amount = (float) $fee_val;
			if ( $product ) {
				$amount = wc_get_price_to_display( $product, [ 'price' => $amount ] );
			}
			$fee_val = wp_strip_all_tags( wc_price( $amount ) );
		}

		$pairs[ __( 'Cutting fee per sheet', 'nh-theme' ) ] = (string) $fee_val;
	}

	return $pairs;
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
	 *         PLUS measured-sheet meta (Width/Length/Fee) if present.
	 * -------------------------------------------------------*/
	if ( $product->is_type( 'variation' ) ) {

		$var_attrs = $product->get_attributes();
		$parent         = wc_get_product( $product->get_parent_id() );
		$parent_attrs   = $parent ? $parent->get_attributes() : [];
		$product_for_lb = $parent ?: $product;

		if ( ! empty( $var_attrs ) ) {
			foreach ( $var_attrs as $attr_slug => $val ) {
				if ( $val === '' ) {
					continue;
				}

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
		}

		// NEW: also show measured-sheet meta for variations
		$pairs = nh_order_apply_measured_pairs( $pairs, $item );

		return $pairs;
	}

	/* ---------------------------------------------------------
	 * CASE B: simple product (custom-cut vs normal)
	 * -------------------------------------------------------*/

	// Measured sheet? If yes, show width/length/fee and return.
	$pairs = nh_order_apply_measured_pairs( $pairs, $item );
	if ( ! empty( $pairs ) ) {
		return $pairs;
	}

	// Normal simple products → show nothing (attributes are informational only).
	return $pairs;

	if ( empty( $attributes ) ) {
		return $pairs;
	}

	foreach ( $attributes as $attr_key => $attr ) {
		$attr_name = '';
		$options   = [];
		$is_tax    = false;

		if ( $attr instanceof WC_Product_Attribute ) {
			$attr_name = $attr->get_name();
			$options   = $attr->get_options();
			$is_tax    = $attr->is_taxonomy();
		} else {
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
 * Hide Woo's default formatted meta for:
 * - variation attributes (we show our own)  [frontend + emails only]
 * - our measured-sheet meta (Width, Length, Cutting fee per sheet)
 * - technical keys (cutting_width, cutting_height, unit_price, cutting_fee, cutting_fee_per_sheet)
 *
 * Improved matching: normalize display keys (lowercase, trim colon) so
 * "length:", "Length" and "length" are all caught and hidden on frontend.
 */
add_filter( 'woocommerce_order_item_get_formatted_meta_data', function ( $formatted_meta, $item ) {
	if ( ! ( $item instanceof WC_Order_Item_Product ) ) {
		return $formatted_meta;
	}

	$technical_keys = [
			'cutting_width',
			'cutting_height',
			'cutting_length_m',
			'nh_length_m',
			'_nh_length_m',
			'unit_price',
			'cutting_fee',
			'cutting_fee_per_sheet',
			'cutting_fee_per_unit',
			'nh_custom_unit_kg',
			'nh_custom_total_kg',
		];

	// Admin: hide only the technical keys to keep admin debugging useful
	if ( is_admin() ) {
		$filtered = [];
		foreach ( $formatted_meta as $fm ) {
			if ( in_array( (string) $fm->key, $technical_keys, true ) ) {
				continue;
			}
			$filtered[] = $fm;
		}
		return $filtered;
	}

	/*
	 * Frontend / emails: build a normalized list of display keys we want hidden.
	 * We'll normalize both the incoming display_key and our hide labels:
	 *  - lowercase
	 *  - trim whitespace
	 *  - trim trailing colon
	 */
	$hide_labels = [
		'Width',
		'Length',
		'Cutting fee per sheet',
		'Cutting fee per unit',
		// Add common lowercase/alternate variants to be safe
		'width',
		'length',
		'cutting fee',
		'cutting fee per sheet',
		'cutting fee per unit',
	];

	// Include translations (if any)
	$translated = array_filter( [
		__( 'Width', 'nh-theme' ),
		__( 'Length', 'nh-theme' ),
		__( 'Cutting fee per sheet', 'nh-theme' ),
		__( 'Cutting fee per unit', 'nh-theme' ),
	] );
	$hide_labels = array_unique( array_merge( $hide_labels, $translated ) );

	// Normalized set for fast checks
	$bad_display = array_map( function ( $s ) {
		$s = (string) $s;
		$s = trim( $s );
		$s = rtrim( $s, ':' );
		$s = mb_strtolower( $s );
		return $s;
	}, $hide_labels );

	$attr_labels = [];
	$product     = $item->get_product();

	if ( $product && $product->is_type( 'variation' ) ) {
		$parent = wc_get_product( $product->get_parent_id() );

		if ( $parent ) {
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
		}
	}

	// Normalize attribute labels too
	$attr_labels_normalized = array_map( function ( $s ) {
		return mb_strtolower( trim( rtrim( (string) $s, ':' ) ) );
	}, $attr_labels );

	$filtered = [];

	foreach ( $formatted_meta as $fm ) {

		// Hide technical meta by key
		if ( in_array( (string) $fm->key, $technical_keys, true ) ) {
			continue;
		}

		// Normalize incoming display key for comparison
		$display_key_norm = mb_strtolower( trim( rtrim( (string) $fm->display_key, ':' ) ) );

		// Hide if it matches our bad display keys (covers 'length', 'Length:', 'length:' etc)
		if ( in_array( $display_key_norm, $bad_display, true ) ) {
			continue;
		}

		// Hide if it's one of the parent's variation attribute labels (we render them ourselves)
		if ( ! empty( $attr_labels_normalized ) && in_array( $display_key_norm, $attr_labels_normalized, true ) ) {
			continue;
		}

		$filtered[] = $fm;
	}

	return $filtered;
}, 20, 2 );

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
