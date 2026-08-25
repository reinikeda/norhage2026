<?php
/**
 * Order / email attributes display for Norhage.
 *
 * Handles:
 * - Normal variation attributes.
 * - Custom-cut Width, Length and Cutting fee rows for normal products.
 * - Sample order items: display only Cutting type and Dimensions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extract measured-sheet info (Width / Length / Fee) from order item meta.
 *
 * @param WC_Order_Item_Product $item Order item.
 * @return array{
 *     width: string,
 *     length: string,
 *     fee: mixed
 * }
 */
function nh_order_item_measured_meta( WC_Order_Item_Product $item ): array {
	$meta_data = $item->get_meta_data();

	$width_val  = '';
	$length_val = '';
	$fee_val    = '';

	foreach ( $meta_data as $meta ) {
		$key = (string) ( $meta->key ?? '' );
		$val = $meta->value ?? '';

		if ( '' === $val ) {
			continue;
		}

		switch ( $key ) {
			/*
			 * Technical keys.
			 */
			case 'cutting_width':
				$width_val = (string) $val;
				break;

			case 'cutting_height':
			case 'cutting_length_m':
			case 'nh_length_m':
			case '_nh_length_m':
				$length_val = (string) $val;

				if ( is_numeric( $length_val ) ) {
					$length_val .= ' m';
				}
				break;

			case 'cutting_fee':
			case 'cutting_fee_per_sheet':
			case 'cutting_fee_per_unit':
				$fee_val = $val;
				break;

			/*
			 * Older human-readable keys.
			 */
			case 'Width':
				if ( '' === $width_val ) {
					$width_val = (string) $val;
				}
				break;

			case 'Length':
				if ( '' === $length_val ) {
					$length_val = (string) $val;
				}
				break;

			case 'Cutting fee per sheet':
			case 'Cutting fee per unit':
				if ( '' === $fee_val ) {
					$fee_val = $val;
				}
				break;
		}
	}

	return array(
		'width'  => $width_val,
		'length' => $length_val,
		'fee'    => $fee_val,
	);
}

/**
 * Add custom-cut Width, Length and Cutting fee rows to an attribute pair array.
 *
 * Samples must never receive these rows.
 *
 * @param array                 $pairs Existing label/value pairs.
 * @param WC_Order_Item_Product $item  Order item.
 * @return array
 */
function nh_order_apply_measured_pairs( array $pairs, WC_Order_Item_Product $item ): array {

	/*
	 * Samples are intentionally plain. Their visible meta is controlled by
	 * nh_filter_order_item_formatted_meta() below.
	 */
	if ( nh_is_sample_order_item( $item ) ) {
		return $pairs;
	}

	$measured_meta = nh_order_item_measured_meta( $item );

	$width_val  = $measured_meta['width'] ?? '';
	$length_val = $measured_meta['length'] ?? '';
	$fee_val    = $measured_meta['fee'] ?? '';

	$is_measured_sheet = (
		'' !== $width_val ||
		'' !== $length_val ||
		'' !== $fee_val
	);

	if ( ! $is_measured_sheet ) {
		return $pairs;
	}

	if ( '' !== $width_val ) {
		$pairs[ __( 'Width', 'nh-theme' ) ] = $width_val;
	}

	if ( '' !== $length_val ) {
		$pairs[ __( 'Length', 'nh-theme' ) ] = $length_val;
	}

	if ( '' !== $fee_val ) {
		$product = $item->get_product();

		/*
		 * Fee can be a raw numeric amount or an already formatted string.
		 */
		if ( is_numeric( $fee_val ) ) {
			$amount = (float) $fee_val;

			if ( $product ) {
				$amount = wc_get_price_to_display(
					$product,
					array(
						'price' => $amount,
					)
				);
			}

			$fee_val = wp_strip_all_tags( wc_price( $amount ) );
		}

		$pairs[ __( 'Cutting fee per sheet', 'nh-theme' ) ] = (string) $fee_val;
	}

	return $pairs;
}

/**
 * Build label => value attribute pairs to print below an order item name.
 *
 * @param WC_Order_Item_Product $item Order item.
 * @return array
 */
function nh_order_item_attribute_pairs( WC_Order_Item_Product $item ): array {
	$pairs = array();

	/*
	 * Samples must not receive duplicate variation attributes or custom-cut
	 * rows here. Their only permitted visible fields are Cutting type and
	 * Dimensions from the WooCommerce formatted item meta.
	 */
	if ( nh_is_sample_order_item( $item ) ) {
		return $pairs;
	}

	$product = $item->get_product();

	if ( ! $product ) {
		return $pairs;
	}

	/*
	 * Variation product:
	 * Show only parent attributes that are enabled for variations.
	 */
	if ( $product->is_type( 'variation' ) ) {
		$variation_attributes = $product->get_attributes();
		$parent_product       = wc_get_product( $product->get_parent_id() );
		$parent_attributes    = $parent_product ? $parent_product->get_attributes() : array();
		$label_product        = $parent_product ? $parent_product : $product;

		foreach ( $variation_attributes as $attribute_slug => $value ) {
			if ( '' === $value ) {
				continue;
			}

			$is_variation_attribute = true;

			if (
				isset( $parent_attributes[ $attribute_slug ] ) &&
				$parent_attributes[ $attribute_slug ] instanceof WC_Product_Attribute
			) {
				$is_variation_attribute = (bool) $parent_attributes[ $attribute_slug ]->get_variation();
			}

			if ( ! $is_variation_attribute ) {
				continue;
			}

			$label = wc_attribute_label( $attribute_slug, $label_product );

			if ( '' === $label ) {
				continue;
			}

			$value = wc_clean( $value );

			if ( taxonomy_exists( $attribute_slug ) ) {
				$term = get_term_by( 'slug', $value, $attribute_slug );

				if ( $term && ! is_wp_error( $term ) ) {
					$value = $term->name;
				} else {
					$value = wc_clean( str_replace( '-', ' ', $value ) );
				}
			}

			if ( '' !== $value ) {
				$pairs[ $label ] = $value;
			}
		}

		return nh_order_apply_measured_pairs( $pairs, $item );
	}

	/*
	 * Simple products:
	 * Only custom-cut data is shown. Normal simple-product attributes are
	 * intentionally not shown in the extra custom block.
	 */
	return nh_order_apply_measured_pairs( $pairs, $item );
}

/**
 * Render label/value pairs as WooCommerce-style variation HTML.
 *
 * @param array $pairs Label/value pairs.
 * @return string
 */
function nh_order_render_dl_variation( array $pairs ): string {
	if ( empty( $pairs ) ) {
		return '';
	}

	$html = '<dl class="variation">';

	foreach ( $pairs as $label => $value ) {
		$class = 'variation-' . preg_replace(
			'/\s+/',
			'',
			ucwords( wp_strip_all_tags( $label ) )
		);

		$html .= '<dt class="' . esc_attr( $class ) . '">';
		$html .= esc_html( $label ) . ':';
		$html .= '</dt>';

		$html .= '<dd class="' . esc_attr( $class ) . '">';
		$html .= '<p>' . esc_html( $value ) . '</p>';
		$html .= '</dd>';
	}

	$html .= '</dl>';

	return $html;
}

/**
 * Print the custom attribute block below an order item name.
 *
 * @param WC_Order_Item_Product $item Order item.
 * @return void
 */
function nh_order_print_item_attributes_block( WC_Order_Item_Product $item ) {
	$pairs = nh_order_item_attribute_pairs( $item );

	if ( empty( $pairs ) ) {
		return;
	}

	echo nh_order_render_dl_variation( $pairs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Normalize an order-meta display label for comparisons.
 *
 * @param string $label Label to normalize.
 * @return string
 */
function nh_normalize_order_meta_label( $label ): string {
	$label = wp_strip_all_tags( (string) $label );
	$label = trim( $label );
	$label = rtrim( $label, ':' );

	return mb_strtolower( $label );
}

/**
 * Filter formatted order-item meta.
 *
 * Samples:
 * - Keep only Cutting type and Dimensions.
 * - Hide all variation attributes, Weight, Cutting fee, Width, Length,
 *   technical meta, and internal sample markers.
 *
 * Normal products:
 * - Hide duplicated variation attributes and technical custom-cut meta,
 *   because the custom display block prints the relevant data itself.
 *
 * This affects frontend order details, thank-you pages, emails, WooCommerce
 * admin order item output, and PDF invoice plugins that use
 * WC_Order_Item::get_formatted_meta_data().
 *
 * @param array                 $formatted_meta Formatted meta objects.
 * @param WC_Order_Item_Product $item           Order item.
 * @return array
 */
function nh_filter_order_item_formatted_meta( $formatted_meta, $item ) {
	if ( ! ( $item instanceof WC_Order_Item_Product ) ) {
		return $formatted_meta;
	}

	/*
	 * Sample order items: only these exact visible labels are allowed.
	 *
	 * This is deliberately before the admin/non-admin logic so samples are
	 * clean in WooCommerce Admin too, not only on the frontend and emails.
	 */
	if ( nh_is_sample_order_item( $item ) ) {
		$allowed_labels = array(
			nh_normalize_order_meta_label( __( 'Cutting type', 'nh-theme' ) ),
			nh_normalize_order_meta_label( __( 'Dimensions', 'nh-theme' ) ),
			nh_normalize_order_meta_label( 'Cutting type' ),
			nh_normalize_order_meta_label( 'Dimensions' ),
		);

		$filtered = array();

		foreach ( $formatted_meta as $meta_id => $meta ) {
			$display_key = isset( $meta->display_key ) ? $meta->display_key : '';
			$normalized  = nh_normalize_order_meta_label( $display_key );

			if ( in_array( $normalized, $allowed_labels, true ) ) {
				$filtered[ $meta_id ] = $meta;
			}
		}

		return $filtered;
	}

	$technical_keys = array(
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
		'_nh_sample',
	);

	/*
	 * Admin for normal products:
	 * Hide technical implementation values while allowing normal visible
	 * product meta to remain available for order management.
	 */
	if ( is_admin() ) {
		$filtered = array();

		foreach ( $formatted_meta as $meta_id => $meta ) {
			$meta_key = isset( $meta->key ) ? (string) $meta->key : '';

			if ( in_array( $meta_key, $technical_keys, true ) ) {
				continue;
			}

			$filtered[ $meta_id ] = $meta;
		}

		return $filtered;
	}

	/*
	 * Frontend and emails for normal products:
	 * Hide duplicate variation / custom-cut rows because the custom block
	 * below the product name renders them in a consistent format.
	 */
	$hidden_labels = array(
		'Width',
		'Length',
		'Cutting fee',
		'Cutting fee per sheet',
		'Cutting fee per unit',
	);

	$hidden_label_keys = array_map(
		'nh_normalize_order_meta_label',
		$hidden_labels
	);

	$product                = $item->get_product();
	$variation_label_keys   = array();

	if ( $product && $product->is_type( 'variation' ) ) {
		$parent_product = wc_get_product( $product->get_parent_id() );

		if ( $parent_product ) {
			foreach ( $parent_product->get_attributes() as $attribute_key => $attribute ) {
				$attribute_name = '';

				if ( $attribute instanceof WC_Product_Attribute ) {
					$attribute_name = $attribute->get_name();
				} elseif ( is_string( $attribute_key ) ) {
					$attribute_name = $attribute_key;
				}

				if ( '' === $attribute_name ) {
					continue;
				}

				$variation_label_keys[] = nh_normalize_order_meta_label(
					wc_attribute_label( $attribute_name, $parent_product )
				);
			}
		}
	}

	$filtered = array();

	foreach ( $formatted_meta as $meta_id => $meta ) {
		$meta_key     = isset( $meta->key ) ? (string) $meta->key : '';
		$display_key  = isset( $meta->display_key ) ? $meta->display_key : '';
		$normalized   = nh_normalize_order_meta_label( $display_key );

		if ( in_array( $meta_key, $technical_keys, true ) ) {
			continue;
		}

		if ( in_array( $normalized, $hidden_label_keys, true ) ) {
			continue;
		}

		if ( in_array( $normalized, $variation_label_keys, true ) ) {
			continue;
		}

		$filtered[ $meta_id ] = $meta;
	}

	return $filtered;
}

add_filter(
	'woocommerce_order_item_get_formatted_meta_data',
	'nh_filter_order_item_formatted_meta',
	999,
	2
);

/**
 * Print the custom attribute list below product names on:
 * - Thank-you page
 * - My Account > Order details
 * - HTML emails
 */
add_action(
	'woocommerce_order_item_meta_start',
	function ( $item_id, $item, $order, $plain_text ) {
		if ( $plain_text ) {
			return;
		}

		if ( ! ( $item instanceof WC_Order_Item_Product ) ) {
			return;
		}

		nh_order_print_item_attributes_block( $item );
	},
	10,
	4
);
