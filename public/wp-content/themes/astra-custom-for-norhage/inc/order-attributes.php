<?php
/**
 * inc/order-attributes.php
 *
 * Order / email attributes display for Norhage.
 *
 * OWNERSHIP NOTE: This file is the SOLE owner of
 * woocommerce_order_item_get_formatted_meta_data (what's shown in order
 * details, emails, thank-you page, admin order edit, and any PDF plugin
 * that reads via WC_Order_Item::get_formatted_meta_data()).
 *
 * De-duplication strategy changed: instead of only matching translated
 * display labels against a hardcoded English list (which breaks on
 * non-English sites/translated meta keys), we now ALSO match against the
 * raw, untranslated meta key. This is more reliable because translations
 * can change the display label, but the underlying key does not.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

function nh_order_apply_measured_pairs( array $pairs, WC_Order_Item_Product $item ): array {

	if ( nh_is_sample_order_item( $item ) ) {
		return $pairs;
	}

	$measured_meta = nh_order_item_measured_meta( $item );

	$width_val  = $measured_meta['width'] ?? '';
	$length_val = $measured_meta['length'] ?? '';
	$fee_val    = $measured_meta['fee'] ?? '';

	$is_measured_sheet = ( '' !== $width_val || '' !== $length_val || '' !== $fee_val );

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

		if ( is_numeric( $fee_val ) ) {
			$amount = (float) $fee_val;

			if ( $product ) {
				$amount = wc_get_price_to_display( $product, array( 'price' => $amount ) );
			}

			$fee_val = wp_strip_all_tags( wc_price( $amount ) );
		}

		$pairs[ __( 'Cutting fee per sheet', 'nh-theme' ) ] = (string) $fee_val;
	}

	return $pairs;
}

function nh_order_item_attribute_pairs( WC_Order_Item_Product $item ): array {
	$pairs = array();

	if ( nh_is_sample_order_item( $item ) ) {
		return $pairs;
	}

	$product = $item->get_product();

	if ( ! $product ) {
		return $pairs;
	}

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

	return nh_order_apply_measured_pairs( $pairs, $item );
}

function nh_order_render_dl_variation( array $pairs ): string {
	if ( empty( $pairs ) ) {
		return '';
	}

	$html = '<dl class="variation">';

	foreach ( $pairs as $label => $value ) {
		$class = 'variation-' . preg_replace( '/\s+/', '', ucwords( wp_strip_all_tags( $label ) ) );

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

function nh_order_print_item_attributes_block( WC_Order_Item_Product $item ) {
	$pairs = nh_order_item_attribute_pairs( $item );

	if ( empty( $pairs ) ) {
		return;
	}

	echo nh_order_render_dl_variation( $pairs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function nh_normalize_order_meta_label( $label ): string {
	$label = wp_strip_all_tags( (string) $label );
	$label = trim( $label );
	$label = rtrim( $label, ':' );

	return mb_strtolower( $label );
}

/**
 * Raw meta KEYS (not translated display labels) that are always
 * considered "technical" or "duplicated by our custom render block" and
 * must never appear a second time via WooCommerce's default meta table.
 *
 * IMPORTANT: If your custom-cut save code (the file that writes Width /
 * Length / Cutting fee meta for NORMAL, non-sample custom-cut order
 * items) uses different raw keys than 'Width' / 'Length', add them here.
 */
function nh_order_duplicate_meta_keys(): array {
	return array(
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
		'Width',
		'Length',
		'Cutting fee',
		'Cutting fee per sheet',
		'Cutting fee per unit',
	);
}

function nh_filter_order_item_formatted_meta( $formatted_meta, $item ) {
	if ( ! ( $item instanceof WC_Order_Item_Product ) ) {
		return $formatted_meta;
	}

	// Samples: allow-list only, everywhere (admin, frontend, emails, PDF).
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

	$duplicate_keys      = nh_order_duplicate_meta_keys();
	$duplicate_key_lower = array_map( 'mb_strtolower', $duplicate_keys );

	$hidden_labels = array(
		'Width',
		'Length',
		'Cutting fee',
		'Cutting fee per sheet',
		'Cutting fee per unit',
	);

	// Also hide the currently active-language translations of the same labels,
	// so this works regardless of site language.
	$hidden_labels[] = __( 'Width', 'nh-theme' );
	$hidden_labels[] = __( 'Length', 'nh-theme' );
	$hidden_labels[] = __( 'Cutting fee per sheet', 'nh-theme' );
	$hidden_labels[] = __( 'Cutting fee per unit', 'nh-theme' );

	$hidden_label_keys = array_unique( array_map( 'nh_normalize_order_meta_label', $hidden_labels ) );

	$product              = $item->get_product();
	$variation_label_keys = array();

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
		$meta_key    = isset( $meta->key ) ? (string) $meta->key : '';
		$display_key = isset( $meta->display_key ) ? $meta->display_key : '';
		$normalized  = nh_normalize_order_meta_label( $display_key );
		$key_lower   = mb_strtolower( $meta_key );

		// Always strip technical keys, both in admin and frontend, by RAW key.
		if ( in_array( $key_lower, $duplicate_key_lower, true ) ) {
			// In admin we still want to SEE Width/Length/Fee once (no custom
			// block runs there), so only drop the truly technical ones here.
			$purely_technical = array(
				'cutting_width', 'cutting_height', 'cutting_length_m', 'nh_length_m',
				'_nh_length_m', 'unit_price', 'cutting_fee', 'cutting_fee_per_sheet',
				'cutting_fee_per_unit', 'nh_custom_unit_kg', 'nh_custom_total_kg', '_nh_sample',
			);

			if ( is_admin() && ! in_array( $key_lower, array_map( 'mb_strtolower', $purely_technical ), true ) ) {
				// Human-readable raw key (e.g. "Width") on admin: keep it, fall through.
			} else {
				continue;
			}
		}

		if ( is_admin() ) {
			$filtered[ $meta_id ] = $meta;
			continue;
		}

		// Frontend/emails: drop anything matching our hidden labels or
		// variation attribute labels — the custom block already renders these.
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
