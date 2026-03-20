<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================================
 * NH Bundle Box — renderer + single-request bundle add-to-cart endpoint
 * ========================================================================== */

if ( ! defined( 'NH_BUNDLE_META_KEY' ) ) {
	define( 'NH_BUNDLE_META_KEY', '_nh_bundle_items_v2' );
}
if ( ! defined( 'NH_BUNDLE_META_KEY_COMPAT' ) ) {
	define( 'NH_BUNDLE_META_KEY_COMPAT', '_nc_bundle_items_v2' );
}
if ( ! defined( 'NH_BUNDLE_IDS_LEGACY' ) ) {
	define( 'NH_BUNDLE_IDS_LEGACY', '_nc_bundle_item_ids' );
}

if ( ! function_exists( 'nh_bundle_normalize_row' ) ) {
	function nh_bundle_normalize_row( $raw ) {
		$row = [
			'product_id'  => 0,
			'qty'         => 1,
			'label'       => '',
			'description' => '',
			'required'    => false,
		];

		if ( is_numeric( $raw ) ) {
			$row['product_id'] = absint( $raw );
			return $row;
		}

		if ( ! is_array( $raw ) ) {
			return $row;
		}

		foreach ( [ 'product_id', 'id', 'pid', 'item_id' ] as $k ) {
			if ( ! empty( $raw[ $k ] ) ) {
				$row['product_id'] = absint( $raw[ $k ] );
				break;
			}
		}

		foreach ( [ 'qty', 'quantity', 'default_qty' ] as $k ) {
			if ( isset( $raw[ $k ] ) && $raw[ $k ] !== '' ) {
				$row['qty'] = max( 0, absint( $raw[ $k ] ) );
				break;
			}
		}

		foreach ( [ 'label', 'title', 'name' ] as $k ) {
			if ( ! empty( $raw[ $k ] ) ) {
				$row['label'] = sanitize_text_field( $raw[ $k ] );
				break;
			}
		}

		foreach ( [ 'description', 'desc', 'note' ] as $k ) {
			if ( ! empty( $raw[ $k ] ) ) {
				$row['description'] = wp_kses_post( $raw[ $k ] );
				break;
			}
		}

		$row['required'] = ! empty( $raw['required'] );

		return $row;
	}
}

if ( ! function_exists( 'nh_bundle_get_items' ) ) {
	function nh_bundle_get_items( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) return [];

		$raw = get_post_meta( $product_id, NH_BUNDLE_META_KEY, true );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			$raw = get_post_meta( $product_id, NH_BUNDLE_META_KEY_COMPAT, true );
		}

		$out = [];

		if ( is_array( $raw ) && ! empty( $raw ) ) {
			foreach ( $raw as $item ) {
				$row = nh_bundle_normalize_row( $item );
				if ( ! empty( $row['product_id'] ) ) {
					$out[] = $row;
				}
			}
		}

		if ( empty( $out ) ) {
			$legacy = get_post_meta( $product_id, NH_BUNDLE_IDS_LEGACY, true );

			if ( is_array( $legacy ) && ! empty( $legacy ) ) {
				foreach ( $legacy as $pid ) {
					$row = nh_bundle_normalize_row( $pid );
					if ( ! empty( $row['product_id'] ) ) {
						$out[] = $row;
					}
				}
			}
		}

		return $out;
	}
}

if ( ! function_exists( 'nh_bundle_has_items' ) ) {
	function nh_bundle_has_items( $product_id ) {
		return ! empty( nh_bundle_get_items( $product_id ) );
	}
}

if ( ! function_exists( 'nh_bundle_get_product_link' ) ) {
	function nh_bundle_get_product_link( $product_id, $bundle_parent_id ) {
		$url = get_permalink( $product_id );
		if ( ! $url ) return '';

		$url = add_query_arg( 'bundle_parent', absint( $bundle_parent_id ), $url );
		$url .= '#nc-complete-set';

		return $url;
	}
}

if ( ! function_exists( 'nh_bundle_get_display_price' ) ) {
	function nh_bundle_get_display_price( WC_Product $product ) {
		$price = $product->get_price();
		if ( $price === '' ) return 0.0;

		return (float) wc_get_price_to_display( $product, [ 'price' => (float) $price ] );
	}
}

if ( ! function_exists( 'nh_bundle_get_price_html_pair' ) ) {
	function nh_bundle_get_price_html_pair( WC_Product $product ) {
		$reg_raw  = $product->get_regular_price();
		$sale_raw = $product->get_sale_price();
		$curr_raw = $product->get_price();

		$reg  = ( $reg_raw  !== '' ) ? (float) wc_get_price_to_display( $product, [ 'price' => (float) $reg_raw ] )  : 0.0;
		$sale = ( $sale_raw !== '' ) ? (float) wc_get_price_to_display( $product, [ 'price' => (float) $sale_raw ] ) : 0.0;
		$curr = ( $curr_raw !== '' ) ? (float) wc_get_price_to_display( $product, [ 'price' => (float) $curr_raw ] ) : 0.0;

		if ( $sale > 0 && $reg > $sale ) {
			return '<del>' . wc_price( $reg ) . '</del> <ins>' . wc_price( $sale ) . '</ins>';
		}

		if ( $curr > 0 ) {
			return wc_price( $curr );
		}

		return '—';
	}
}

if ( ! function_exists( 'nh_bundle_format_attribute_option_label' ) ) {
	function nh_bundle_format_attribute_option_label( $taxonomy, $option ) {
		if ( taxonomy_exists( $taxonomy ) ) {
			$term = get_term_by( 'slug', $option, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term->name;
			}
		}

		return (string) $option;
	}
}

if ( ! function_exists( 'nh_bundle_get_variation_payload' ) ) {
	function nh_bundle_get_variation_payload( WC_Product_Variable $product ) {
		$available = $product->get_available_variations();
		$out = [];

		foreach ( $available as $var ) {
			$out[] = [
				'variation_id'          => isset( $var['variation_id'] ) ? (int) $var['variation_id'] : 0,
				'attributes'            => isset( $var['attributes'] ) && is_array( $var['attributes'] ) ? array_map( 'strval', $var['attributes'] ) : [],
				'display_price'         => isset( $var['display_price'] ) ? (float) $var['display_price'] : 0.0,
				'display_regular_price' => isset( $var['display_regular_price'] ) ? (float) $var['display_regular_price'] : 0.0,
				'is_in_stock'           => ! empty( $var['is_in_stock'] ),
				'is_purchasable'        => ! empty( $var['is_purchasable'] ),
				'variation_is_visible'  => ! empty( $var['variation_is_visible'] ),
			];
		}

		return $out;
	}
}

if ( ! function_exists( 'nh_bundle_render_qty_control' ) ) {
	function nh_bundle_render_qty_control( WC_Product $product, array $row, $disabled = false ) {
		$raw_max = $product->get_max_purchase_quantity();

		// Woo may return -1 for "unlimited"
		$has_real_max  = is_numeric( $raw_max ) && (int) $raw_max > 0;
		$effective_max = $has_real_max ? (int) $raw_max : '';

		$qty_disabled = $disabled ? ' disabled' : '';
		$max_attr     = $has_real_max ? ' max="' . esc_attr( $effective_max ) . '"' : '';
		$data_max     = $has_real_max ? ' data-maxqty="' . esc_attr( $effective_max ) . '"' : '';
		$step_attr    = ' step="1"';
		$input_id     = 'bundle-qty-' . esc_attr( $product->get_id() );

		echo '<div class="quantity buttons_added">';
		echo '  <a href="#" class="minus"' . ( $qty_disabled ? ' aria-disabled="true"' : '' ) . '>-</a>';
		echo '  <label class="screen-reader-text" for="' . esc_attr( $input_id ) . '">' . esc_html( $product->get_name() ) . '</label>';
		echo '  <input'
			. ' type="number"'
			. ' id="' . esc_attr( $input_id ) . '"'
			. ' name="bundle_qty[' . esc_attr( $product->get_id() ) . ']"'
			. ' class="input-text qty text"'
			. ' value="0"'
			. ' min="0"'
			. $max_attr
			. $step_attr
			. $data_max
			. ' data-default-qty="' . esc_attr( max( 1, (int) $row['qty'] ) ) . '"'
			. ' inputmode="numeric"'
			. ' pattern="[0-9]*"'
			. ' autocomplete="off"'
			. $qty_disabled
			. '>';
		echo '  <a href="#" class="plus"' . ( $qty_disabled ? ' aria-disabled="true"' : '' ) . '>+</a>';
		echo '</div>';
	}
}

if ( ! function_exists( 'nh_bundle_render_row' ) ) {
	function nh_bundle_render_row( WC_Product $product, array $row, $bundle_parent_id ) {
		$product_id   = $product->get_id();
		$is_variable  = $product->is_type( 'variable' );
		$is_in_stock  = $product->is_in_stock();
		$link         = nh_bundle_get_product_link( $product_id, $bundle_parent_id );
		$price_html   = $product->get_price_html();
		$price_html   = $price_html !== '' ? $price_html : '—';
		$price_attr   = $is_variable ? 0 : nh_bundle_get_display_price( $product );

		$row_classes = [ 'nc-bundle-row' ];
		if ( $is_variable ) {
			$row_classes[] = 'is-variable';
		}

		$variations_json_attr = '';
		if ( $is_variable && $product instanceof WC_Product_Variable ) {
			$variations_json_attr = ' data-variations="' . esc_attr( wp_json_encode( nh_bundle_get_variation_payload( $product ) ) ) . '"';
		}

		echo '<div class="' . esc_attr( implode( ' ', $row_classes ) ) . '" role="row" data-product-id="' . esc_attr( $product_id ) . '" data-base-price="' . esc_attr( $price_attr ) . '" data-initial-price-html="' . esc_attr( $price_html ) . '"' . $variations_json_attr . '>';

		echo '  <div class="nc-col nc-col-image" role="cell">';
		if ( $link ) {
			echo '    <a href="' . esc_url( $link ) . '" class="nc-thumb" aria-label="' . esc_attr( $product->get_name() ) . '">' . $product->get_image( 'woocommerce_thumbnail' ) . '</a>';
		} else {
			echo '    <span class="nc-thumb">' . $product->get_image( 'woocommerce_thumbnail' ) . '</span>';
		}
		echo '  </div>';

		echo '  <div class="nc-col nc-col-title" role="cell">';
		if ( $link ) {
			echo '    <a class="nc-title" href="' . esc_url( $link ) . '">' . esc_html( $row['label'] ? $row['label'] : $product->get_name() ) . '</a>';
		} else {
			echo '    <span class="nc-title">' . esc_html( $row['label'] ? $row['label'] : $product->get_name() ) . '</span>';
		}

		echo $is_in_stock
			? '    <span class="nc-pill nc-pill--instock">' . esc_html__( 'In stock', 'nh-theme' ) . '</span>'
			: '    <span class="nc-pill nc-pill--oos">' . esc_html__( 'Out of stock', 'nh-theme' ) . '</span>';

		if ( ! empty( $row['description'] ) ) {
			echo '    <div class="nc-bundle-desc">' . wp_kses_post( $row['description'] ) . '</div>';
		}

		if ( $is_variable && $product instanceof WC_Product_Variable ) {
			$variation_attributes = $product->get_variation_attributes();

			echo '    <div class="nc-bundle-variations">';

			foreach ( $variation_attributes as $taxonomy => $options ) {
				echo '      <div class="nc-bundle-variation-field">';
				echo '        <label>' . esc_html( wc_attribute_label( $taxonomy ) ) . '</label>';
				echo '        <select class="bundle-variation" name="bundle_attr[' . esc_attr( $taxonomy ) . ']">';
				echo '          <option value="">' . esc_html__( 'Choose an option', 'nh-theme' ) . '</option>';

				foreach ( $options as $option ) {
					echo '          <option value="' . esc_attr( $option ) . '">' . esc_html( nh_bundle_format_attribute_option_label( $taxonomy, $option ) ) . '</option>';
				}

				echo '        </select>';
				echo '      </div>';
			}

			echo '      <input type="hidden" class="selected-variation-id" value="0">';
			echo '    </div>';
		} else {
			echo '    <input type="hidden" class="selected-variation-id" value="0">';
		}

		echo '    <div class="nc-price-mobile" aria-hidden="true">' . wp_kses_post( $price_html ) . '</div>';
		echo '  </div>';

		echo '  <div class="nc-col nc-col-qty" role="cell">';
		nh_bundle_render_qty_control( $product, $row, $is_variable || ! $is_in_stock || ! $product->is_purchasable() );
		echo '  </div>';

		echo '  <div class="nc-col nc-col-price" role="cell"><span class="nc-price-desktop">' . wp_kses_post( $price_html ) . '</span></div>';

		echo '</div>';
	}
}

if ( ! function_exists( 'nh_render_bundle_box' ) ) {
	function nh_render_bundle_box() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) return;

		global $product;
		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( get_the_ID() );
		}
		if ( ! $product instanceof WC_Product ) return;

		$rows = nh_bundle_get_items( $product->get_id() );
		if ( empty( $rows ) ) return;

		$currency_symbol = get_woocommerce_currency_symbol();
		$price_pos       = get_option( 'woocommerce_currency_pos', 'right_space' );
		$decimals        = wc_get_price_decimals();
		$thousand_sep    = wc_get_price_thousand_separator();
		$decimal_sep     = wc_get_price_decimal_separator();

		echo '<section id="nc-complete-set" class="nc-bundle card" aria-labelledby="nc-bundle-title">';
		echo '<div class="nc-bundle-head"><h3 id="nc-bundle-title">' . esc_html__( 'Complete set', 'nh-theme' ) . '</h3></div>';

		echo '<form id="nc-bundle-form" class="nc-bundle-form" role="group" aria-label="' . esc_attr__( 'Complete set add-ons', 'nh-theme' ) . '"'
			. ' data-currency-symbol="' . esc_attr( $currency_symbol ) . '"'
			. ' data-currency-pos="' . esc_attr( $price_pos ) . '"'
			. ' data-decimals="' . esc_attr( $decimals ) . '"'
			. ' data-thousand="' . esc_attr( $thousand_sep ) . '"'
			. ' data-decimal="' . esc_attr( $decimal_sep ) . '">';

		echo '<div class="nc-bundle-row nc-bundle-header" role="row">';
		echo '  <div class="nc-col nc-col-image">' . esc_html__( 'Image', 'nh-theme' ) . '</div>';
		echo '  <div class="nc-col nc-col-title">' . esc_html__( 'Product', 'nh-theme' ) . '</div>';
		echo '  <div class="nc-col nc-col-qty">' . esc_html__( 'Qty', 'nh-theme' ) . '</div>';
		echo '  <div class="nc-col nc-col-price">' . esc_html__( 'Price', 'nh-theme' ) . '</div>';
		echo '</div>';

		foreach ( $rows as $r ) {
			$p = wc_get_product( $r['product_id'] );
			if ( ! $p instanceof WC_Product ) continue;

			nh_bundle_render_row( $p, $r, $product->get_id() );
		}

		echo '<div class="nc-bundle-footer">';
		echo '  <div class="nc-total">' . esc_html__( 'Total:', 'nh-theme' ) . ' <strong id="bundle-total-amount">' . wp_kses_post( wc_price( 0 ) ) . '</strong></div>';
		echo '  <button type="button" id="add-bundle-to-cart" class="button alt nc-bundle-btn nh-bundle-add-all is-disabled" disabled aria-disabled="true">'
			. esc_html__( 'Add all to basket', 'nh-theme' ) .
			'</button>';
		echo '</div>';

		echo '</form>';
		echo '</section>';
	}
}

add_action( 'woocommerce_after_add_to_cart_form', 'nh_render_bundle_box', 20 );

/* ============================================================================
 * AJAX helpers
 * ========================================================================== */

if ( ! function_exists( 'nh_bundle_normalize_attributes' ) ) {
	function nh_bundle_normalize_attributes( $attrs ) {
		$out = [];

		if ( ! is_array( $attrs ) ) {
			return $out;
		}

		foreach ( $attrs as $k => $v ) {
			$key = sanitize_text_field( (string) $k );
			$val = wc_clean( wp_unslash( $v ) );

			if ( $key === '' || $val === '' ) continue;

			if ( strpos( $key, 'attribute_' ) !== 0 ) {
				$key = 'attribute_' . ltrim( $key, '_' );
			}

			$out[ $key ] = $val;
		}

		return $out;
	}
}

if ( ! function_exists( 'nh_bundle_find_matching_variation_id' ) ) {
	function nh_bundle_find_matching_variation_id( WC_Product $product, array $attrs ) {
		if ( ! $product->is_type( 'variable' ) || empty( $attrs ) ) {
			return 0;
		}

		if ( ! class_exists( 'WC_Data_Store' ) ) {
			return 0;
		}

		$data_store = WC_Data_Store::load( 'product' );
		if ( ! $data_store || ! is_callable( [ $data_store, 'find_matching_product_variation' ] ) ) {
			return 0;
		}

		return (int) $data_store->find_matching_product_variation( $product, $attrs );
	}
}

if ( ! function_exists( 'nh_bundle_prepare_request_data' ) ) {
	function nh_bundle_prepare_request_data( array $line, $is_main = false ) {
		$product_id   = ! empty( $line['product_id'] ) ? absint( $line['product_id'] ) : 0;
		$quantity     = isset( $line['quantity'] ) ? max( 1, absint( $line['quantity'] ) ) : 1;
		$variation_id = ! empty( $line['variation_id'] ) ? absint( $line['variation_id'] ) : 0;
		$attributes   = nh_bundle_normalize_attributes( $line['attributes'] ?? [] );

		if ( ! $product_id ) {
			return new WP_Error( 'invalid_product', __( 'Invalid bundle product.', 'nh-theme' ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			return new WP_Error( 'missing_product', __( 'Bundle product could not be loaded.', 'nh-theme' ) );
		}

		if ( $product->is_type( 'variable' ) && ! $variation_id ) {
			$variation_id = nh_bundle_find_matching_variation_id( $product, $attributes );
		}

		$request_data = [
			'add-to-cart' => $product_id,
			'product_id'  => $product_id,
			'quantity'    => $quantity,
		];

		foreach ( $attributes as $k => $v ) {
			$request_data[ $k ] = $v;
		}

		if ( $variation_id > 0 ) {
			$request_data['variation_id'] = $variation_id;
		}

		if ( $is_main && ! empty( $line['form_data'] ) && is_array( $line['form_data'] ) ) {
			foreach ( $line['form_data'] as $k => $v ) {
				$key = sanitize_text_field( (string) $k );

				if ( $key === '' ) continue;
				if ( in_array( $key, [ 'action', 'security', '_wpnonce', '_wp_http_referer' ], true ) ) continue;
				if ( is_array( $v ) ) continue;

				$request_data[ $key ] = wc_clean( wp_unslash( $v ) );
			}
		}

		return [
			'product'      => $product,
			'product_id'   => $product_id,
			'quantity'     => $quantity,
			'variation_id' => $variation_id,
			'attributes'   => $attributes,
			'request_data' => $request_data,
			'name'         => $product->get_name(),
		];
	}
}

if ( ! function_exists( 'nh_bundle_build_success_notice_text' ) ) {
	function nh_bundle_build_success_notice_text( array $prepared_lines ) {
		$parts = [];

		foreach ( $prepared_lines as $line ) {
			$name = isset( $line['name'] ) ? $line['name'] : __( 'Item', 'nh-theme' );
			$qty  = isset( $line['quantity'] ) ? max( 1, absint( $line['quantity'] ) ) : 1;
			$parts[] = sprintf( '%s × %d', $name, $qty );
		}

		return sprintf(
			/* translators: %s = list of added items */
			__( 'Bundle added to basket: %s', 'nh-theme' ),
			implode( ', ', $parts )
		);
	}
}

if ( ! function_exists( 'nh_bundle_build_success_notice_html' ) ) {
	function nh_bundle_build_success_notice_html( array $prepared_lines ) {
		$text = nh_bundle_build_success_notice_text( $prepared_lines );

		return '<div class="woocommerce-message" role="alert">' . esc_html( $text ) . '</div>';
	}
}

if ( ! function_exists( 'nh_bundle_get_fragments_payload' ) ) {
	function nh_bundle_get_fragments_payload() {
		ob_start();
		woocommerce_mini_cart();
		$mini_cart = ob_get_clean();

		$fragments = apply_filters(
			'woocommerce_add_to_cart_fragments',
			[
				'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
			]
		);

		return [
			'fragments' => $fragments,
			'cart_hash' => WC()->cart ? WC()->cart->get_cart_hash() : '',
		];
	}
}

if ( ! function_exists( 'nh_bundle_send_json_error_notice' ) ) {
	function nh_bundle_send_json_error_notice( $message, $status = 400 ) {
		if ( ! wc_notice_count( 'error' ) ) {
			wc_add_notice( $message, 'error' );
		}

		$notices_html = wc_print_notices( true );

		wp_send_json_error(
			[
				'message'      => $message,
				'notices_html' => $notices_html,
			],
			$status
		);
	}
}

add_action( 'wc_ajax_nh_add_bundle_to_cart', 'nh_ajax_add_bundle_to_cart' );
add_action( 'wc_ajax_nopriv_nh_add_bundle_to_cart', 'nh_ajax_add_bundle_to_cart' );

if ( ! function_exists( 'nh_ajax_add_bundle_to_cart' ) ) {
	function nh_ajax_add_bundle_to_cart() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error(
				[
					'message' => __( 'Cart is not available.', 'nh-theme' ),
				],
				500
			);
		}

		check_ajax_referer( 'nh_bundle_add_to_cart', 'security' );

		$main_raw   = isset( $_POST['main'] ) ? json_decode( wp_unslash( $_POST['main'] ), true ) : [];
		$addons_raw = isset( $_POST['addons'] ) ? json_decode( wp_unslash( $_POST['addons'] ), true ) : [];

		if ( ! is_array( $main_raw ) || empty( $main_raw ) ) {
			nh_bundle_send_json_error_notice( __( 'Main product data is missing.', 'nh-theme' ) );
		}

		if ( ! is_array( $addons_raw ) ) {
			$addons_raw = [];
		}

		$prepared_lines = [];

		$main_prepared = nh_bundle_prepare_request_data( $main_raw, true );
		if ( is_wp_error( $main_prepared ) ) {
			nh_bundle_send_json_error_notice( $main_prepared->get_error_message() );
		}
		$prepared_lines[] = $main_prepared;

		foreach ( $addons_raw as $addon_raw ) {
			if ( ! is_array( $addon_raw ) ) continue;

			$prepared = nh_bundle_prepare_request_data( $addon_raw, false );
			if ( is_wp_error( $prepared ) ) {
				nh_bundle_send_json_error_notice( $prepared->get_error_message() );
			}

			$prepared_lines[] = $prepared;
		}

		$added_keys        = [];
		$original_post     = $_POST;
		$original_request  = $_REQUEST;

		wc_clear_notices();

		try {
			foreach ( $prepared_lines as $line ) {
				$_POST    = $line['request_data'];
				$_REQUEST = array_merge( $original_request, $line['request_data'] );

				$cart_item_key = WC()->cart->add_to_cart(
					$line['product_id'],
					$line['quantity'],
					$line['variation_id'],
					$line['attributes'],
					[]
				);

				if ( ! $cart_item_key ) {
					foreach ( array_reverse( $added_keys ) as $key ) {
						WC()->cart->remove_cart_item( $key );
					}

					$message = __( 'Unable to add one of the bundle items to the basket.', 'nh-theme' );

					if ( wc_notice_count( 'error' ) ) {
						nh_bundle_send_json_error_notice( $message );
					}

					wc_add_notice( $message, 'error' );
					nh_bundle_send_json_error_notice( $message );
				}

				$added_keys[] = $cart_item_key;

				// Remove per-line notices so we only show one final bundle notice.
				wc_clear_notices();
			}
		} finally {
			$_POST    = $original_post;
			$_REQUEST = $original_request;
		}

		if ( method_exists( WC()->cart, 'calculate_totals' ) ) {
			WC()->cart->calculate_totals();
		}

		$fragments_payload = nh_bundle_get_fragments_payload();

		wp_send_json_success(
			[
				'fragments'    => $fragments_payload['fragments'],
				'cart_hash'    => $fragments_payload['cart_hash'],
				'notices_html' => nh_bundle_build_success_notice_html( $prepared_lines ),
			]
		);
	}
}

/* ============================================================================
 * Assets
 * ========================================================================== */

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_product() ) return;

	$product_id = get_queried_object_id();
	if ( ! $product_id || ! nh_bundle_has_items( $product_id ) ) return;

	$path = get_stylesheet_directory() . '/assets/js/bundle-add-to-cart.js';
	$ver  = file_exists( $path ) ? filemtime( $path ) : '1.0.0';

	wp_enqueue_script(
		'nh-bundle-add',
		get_stylesheet_directory_uri() . '/assets/js/bundle-add-to-cart.js',
		[ 'jquery' ],
		$ver,
		true
	);

	wp_localize_script( 'nh-bundle-add', 'bundle_ajax', [
		'cart_url'       => wc_get_cart_url(),
		'add_bundle_url' => WC_AJAX::get_endpoint( 'nh_add_bundle_to_cart' ),
		'nonce'          => wp_create_nonce( 'nh_bundle_add_to_cart' ),
	] );
}, 50 );
