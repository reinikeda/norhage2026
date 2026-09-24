<?php
/**
 * Wishlist pages, hearts, email, and PDF.
 *
 * @package nh-wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param int $product_id Product id.
 * @return bool
 */
function nh_wl_product_is_custom( $product_id ) {
	$product_id = (int) $product_id;
	if ( function_exists( 'nh_cc_is_enabled_product' ) ) {
		return nh_cc_is_enabled_product( $product_id );
	}
	return (bool) get_post_meta( $product_id, '_nh_cc_enabled', true );
}

/**
 * @param WC_Product $product Product.
 * @return array{product_id:int,is_variable:bool,is_custom:bool,unit:string,type:string}
 */
function nh_wl_flags_for_product( $product ) {
	$id = $product->get_id();
	if ( $product->is_type( 'variation' ) ) {
		$id      = $product->get_parent_id();
		$parent  = wc_get_product( $id );
		$product = $parent instanceof WC_Product ? $parent : $product;
	}
	$is_custom = nh_wl_product_is_custom( $id );
	$unit      = 'mm';
	$type      = 'planar';
	if ( $is_custom ) {
		$unit = ( 'm' === get_post_meta( $id, '_nh_cc_unit', true ) ) ? 'm' : 'mm';
		$type = ( 'linear' === get_post_meta( $id, '_nh_cc_type', true ) ) ? 'linear' : 'planar';
	}
	return array(
		'product_id'  => (int) $id,
		'is_variable' => $product->is_type( 'variable' ),
		'is_custom'   => $is_custom,
		'unit'        => $unit,
		'type'        => $type,
	);
}

/**
 * @return string
 */
function nh_wl_customize_notice() {
	return __( 'This product must be customized before it can be added to the basket.', 'nh-wishlist' );
}

/**
 * @param string $code Error code.
 * @return string
 */
function nh_wl_error_message( $code ) {
	switch ( $code ) {
		case 'empty_name':
			return __( 'Enter a list name.', 'nh-wishlist' );
		case 'duplicate_name':
			return __( 'That name is already used.', 'nh-wishlist' );
		case 'last_list':
			return __( 'The only wishlist cannot be deleted.', 'nh-wishlist' );
		case 'too_many':
			return __( 'You can create up to 20 wishlists.', 'nh-wishlist' );
		case 'list_full':
			return __( 'This wishlist is full.', 'nh-wishlist' );
		case 'invalid_list':
			return __( 'Invalid wishlist.', 'nh-wishlist' );
		case 'missing':
			return __( 'This product is no longer available.', 'nh-wishlist' );
		default:
			return __( 'Please try again.', 'nh-wishlist' );
	}
}

/**
 * @param array<string,mixed> $list List.
 * @return string
 */
function nh_wl_list_label( $list ) {
	if ( isset( $list['id'], $list['name'] ) && 'default' === $list['id'] && 'Default' === $list['name'] ) {
		return __( 'Default', 'nh-wishlist' );
	}
	return isset( $list['name'] ) ? (string) $list['name'] : '';
}

/**
 * @param string $list_id List id.
 * @return string
 */
function nh_wl_page_url( $list_id = '' ) {
	if ( get_option( 'permalink_structure' ) ) {
		$url = home_url( user_trailingslashit( 'wishlist' ) );
	} else {
		$url = add_query_arg( 'nh_wishlist', '1', home_url( '/' ) );
	}
	if ( '' !== $list_id ) {
		$url = add_query_arg( 'list', $list_id, $url );
	}
	return $url;
}

/**
 * Account endpoint for signed-in customers, public page for guests.
 *
 * @param string $list_id List id.
 * @return string
 */
function nh_wl_view_url( $list_id = '' ) {
	if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() && function_exists( 'wc_get_account_endpoint_url' ) ) {
		$url = wc_get_account_endpoint_url( 'wishlist' );
		if ( '' !== $list_id ) {
			$url = add_query_arg( 'list', $list_id, $url );
		}
		return $url;
	}
	return nh_wl_page_url( $list_id );
}

/**
 * @return string
 */
function nh_wl_service_email() {
	$email = apply_filters( 'nh_wl_customer_service_email', 'info@norhage.eu' );
	$email = sanitize_email( (string) $email );
	if ( ! is_email( $email ) ) {
		$email = sanitize_email( (string) get_option( 'admin_email' ) );
	}
	return $email;
}

/**
 * @param string $text Comment.
 * @return string
 */
function nh_wl_sanitize_comment( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	$text = preg_replace( "/\r\n?/", "\n", $text );
	return nh_wl_utf8_limit( trim( (string) $text ), 2000 );
}

/**
 * @param array<string,mixed> $dimensions Dimensions.
 * @return array<int,array{label:string,value:string}>
 */
function nh_wl_dimension_lines( $dimensions ) {
	$dimensions = nh_wl_normalize_dimensions( $dimensions );
	$lines      = array();
	$linear     = ( 'm' === $dimensions['unit'] || 'linear' === $dimensions['type'] );
	if ( ! $linear && $dimensions['width_mm'] > 0 ) {
		$lines[] = array(
			'label' => __( 'Width', 'nh-wishlist' ),
			'value' => $dimensions['width_mm'] . ' mm',
		);
	}
	if ( $linear && $dimensions['length_m'] > 0 ) {
		$lines[] = array(
			'label' => __( 'Length', 'nh-wishlist' ),
			'value' => nh_wl_format_metres( $dimensions['length_m'] ) . ' m',
		);
	} elseif ( ! $linear && $dimensions['length_mm'] > 0 ) {
		$lines[] = array(
			'label' => __( 'Length', 'nh-wishlist' ),
			'value' => $dimensions['length_mm'] . ' mm',
		);
	}
	return $lines;
}

/**
 * @param array<string,string> $attributes Attributes.
 * @return array<int,array{label:string,value:string}>
 */
function nh_wl_attribute_lines( $attributes ) {
	$lines = array();
	foreach ( nh_wl_normalize_attributes( $attributes ) as $key => $value ) {
		$taxonomy = rawurldecode( str_replace( 'attribute_', '', $key ) );
		$label    = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $taxonomy ) : $taxonomy;
		$text     = $value;
		if ( taxonomy_exists( $taxonomy ) ) {
			$term = get_term_by( 'slug', $value, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$text = $term->name;
			}
		}
		$lines[] = array(
			'label' => $label,
			'value' => $text,
		);
	}
	return $lines;
}

/**
 * @param array<string,mixed> $state State.
 * @return string
 */
function nh_wl_requested_list_id( $state ) {
	$state     = nh_wl_normalize_state( $state );
	$requested = isset( $_GET['list'] ) ? sanitize_key( wp_unslash( $_GET['list'] ) ) : '';
	if ( '' !== $requested && nh_wl_find_list( $state, $requested ) ) {
		return $requested;
	}
	return $state['active'];
}

/**
 * @param array<string,mixed> $state State.
 * @param string              $list_id List id.
 * @param string              $key Item key.
 * @return array<string,mixed>|null
 */
function nh_wl_stored_item( $state, $list_id, $key ) {
	$list = nh_wl_find_list( $state, $list_id );
	if ( ! $list ) {
		return null;
	}
	foreach ( $list['items'] as $item ) {
		if ( $item['key'] === $key ) {
			return $item;
		}
	}
	return null;
}

/**
 * @param WC_Product          $product Product.
 * @param array<string,mixed> $request Request fields.
 * @param string              $context loop|single.
 * @return array<string,mixed>
 */
function nh_wl_item_from_request( $product, $request, $context ) {
	if ( $product->is_type( 'variation' ) ) {
		$parent = wc_get_product( $product->get_parent_id() );
		if ( $parent instanceof WC_Product ) {
			$product = $parent;
		}
	}
	$flags         = nh_wl_flags_for_product( $product );
	$product_id    = (int) $product->get_id();
	$variation_id  = 0;
	$attributes    = array();
	$dimensions    = array(
		'unit'      => $flags['unit'],
		'type'      => $flags['type'],
		'width_mm'  => 0,
		'length_mm' => 0,
		'length_m'  => 0,
	);
	if ( 'loop' !== $context ) {
		$variation_id = isset( $request['variation_id'] ) ? absint( $request['variation_id'] ) : 0;
		if ( $variation_id > 0 ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation && (int) $variation->get_parent_id() === $product_id && is_callable( array( $variation, 'get_variation_attributes' ) ) ) {
				$attributes = $variation->get_variation_attributes();
			} else {
				$variation_id = 0;
			}
		}
		if ( ! empty( $flags['is_custom'] ) ) {
			$dimensions['width_mm']  = isset( $request['width_mm'] ) ? absint( $request['width_mm'] ) : 0;
			$dimensions['length_mm'] = isset( $request['length_mm'] ) ? absint( $request['length_mm'] ) : 0;
			$dimensions['length_m']  = isset( $request['length_m'] ) ? wp_unslash( $request['length_m'] ) : 0;
		}
	}
	return nh_wl_make_item(
		array(
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'quantity'     => isset( $request['quantity'] ) ? absint( $request['quantity'] ) : 1,
			'attributes'   => $attributes,
			'dimensions'   => $dimensions,
			'is_custom'    => ! empty( $flags['is_custom'] ),
		)
	);
}

/**
 * @param array<string,mixed> $state State.
 * @param string              $list_id List id.
 * @return array<string,mixed>
 */
function nh_wl_prepare_view( $state, $list_id ) {
	$state = nh_wl_normalize_state( $state );
	$list  = nh_wl_find_list( $state, $list_id );
	if ( ! $list ) {
		$list    = $state['lists'][0];
		$list_id = $list['id'];
	}
	$items = array();
	foreach ( $list['items'] as $item ) {
		$items[] = nh_wl_prepare_item( $item );
	}
	$lists = array();
	foreach ( $state['lists'] as $one ) {
		$lists[] = array(
			'id'      => $one['id'],
			'label'   => nh_wl_list_label( $one ),
			'count'   => count( $one['items'] ),
			'url'     => nh_wl_view_url( $one['id'] ),
			'current' => $one['id'] === $list_id,
		);
	}
	$date = function_exists( 'wp_date' ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : gmdate( 'Y-m-d' );
	return array(
		'list_id'   => $list_id,
		'label'     => nh_wl_list_label( $list ),
		'date'      => $date,
		'site_name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'logged_in' => is_user_logged_in(),
		'login_url' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url(),
		'shop_url'  => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ),
		'lists'     => $lists,
		'items'     => $items,
		'can_delete'=> count( $state['lists'] ) > 1,
		'ready'     => count(
			array_filter(
				$items,
				static function ( $item ) {
					return ! empty( $item['available'] ) && empty( $item['needs_customize'] );
				}
			)
		),
	);
}

/**
 * @param array<string,mixed> $item Stored item.
 * @return array<string,mixed>
 */
function nh_wl_prepare_item( $item ) {
	$product   = wc_get_product( (int) $item['product_id'] );
	$available = $product instanceof WC_Product;
	$flags     = $available ? nh_wl_flags_for_product( $product ) : array(
		'is_variable' => false,
		'is_custom'   => false,
		'unit'        => 'mm',
		'type'        => 'planar',
	);
	$needs     = $available ? nh_wl_item_needs_customize( $item, $flags ) : false;
	$chosen    = $product;
	if ( $available && ! empty( $item['variation_id'] ) ) {
		$variation = wc_get_product( (int) $item['variation_id'] );
		if ( $variation && (int) $variation->get_parent_id() === (int) $item['product_id'] ) {
			$chosen = $variation;
		} else {
			$available = false;
			$needs     = true;
		}
	}
	$name  = $available ? $product->get_name() : __( 'This product is no longer available.', 'nh-wishlist' );
	$url   = '';
	$image = '';
	$price_html = '';
	$price_text = '';
	if ( $product instanceof WC_Product ) {
		$url   = $chosen instanceof WC_Product ? $chosen->get_permalink() : $product->get_permalink();
		$image = $chosen instanceof WC_Product ? $chosen->get_image( 'woocommerce_thumbnail', array( 'class' => 'nh-wl-card__img' ) ) : '';
		if ( '' === $image ) {
			$image = $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'nh-wl-card__img' ) );
		}
	}
	$lines = array();
	if ( $chosen instanceof WC_Product && $chosen->get_sku() ) {
		$lines[] = array(
			'label' => __( 'SKU', 'nh-wishlist' ),
			'value' => $chosen->get_sku(),
		);
	}
	$lines = array_merge( $lines, nh_wl_attribute_lines( isset( $item['attributes'] ) ? $item['attributes'] : array() ) );
	$lines = array_merge( $lines, nh_wl_dimension_lines( isset( $item['dimensions'] ) ? $item['dimensions'] : array() ) );
	if ( $available && ! $needs && $chosen instanceof WC_Product ) {
		$price_html = nh_wl_item_price_html( $product, $chosen, $item, $flags );
		$price_text = trim( wp_strip_all_tags( html_entity_decode( $price_html, ENT_QUOTES, 'UTF-8' ) ) );
	}
	return array(
		'key'              => $item['key'],
		'name'             => $name,
		'url'              => $url,
		'image'            => $image,
		'quantity'         => (int) $item['quantity'],
		'lines'            => $lines,
		'notice'           => ( $available && $needs ) ? nh_wl_customize_notice() : '',
		'available'        => $available && ! $needs,
		'needs_customize'  => (bool) $needs,
		'price_html'       => $price_html,
		'price_text'       => $price_text,
		'qty_label'        => sprintf( __( 'Quantity: %d', 'nh-wishlist' ), (int) $item['quantity'] ),
	);
}

/**
 * @param WC_Product          $parent Parent.
 * @param WC_Product          $chosen Chosen product or variation.
 * @param array<string,mixed> $item Item.
 * @param array<string,mixed> $flags Flags.
 * @return string
 */
function nh_wl_item_price_html( $parent, $chosen, $item, $flags ) {
	if ( ! empty( $flags['is_custom'] ) ) {
		$base = (float) $chosen->get_price();
		$fee  = (float) get_post_meta( $parent->get_id(), '_nh_cc_cut_fee', true );
		$dims = nh_wl_normalize_dimensions( isset( $item['dimensions'] ) ? $item['dimensions'] : array() );
		$dims['unit'] = $flags['unit'];
		$dims['type'] = $flags['type'];
		$raw  = nh_wl_custom_unit_price( $base, $fee, $dims );
		if ( $raw <= 0 ) {
			return '';
		}
		$display = function_exists( 'wc_get_price_to_display' ) ? wc_get_price_to_display( $chosen, array( 'price' => $raw ) ) : $raw;
		return wc_price( $display );
	}
	return $chosen->get_price_html();
}

/**
 * @param array<string,mixed> $view View.
 * @return array<string,mixed>
 */
function nh_wl_pdf_document( $view ) {
	$blocks = array();
	foreach ( $view['items'] as $item ) {
		$lines   = array();
		$lines[] = $item['qty_label'];
		foreach ( $item['lines'] as $line ) {
			$lines[] = $line['label'] . ': ' . $line['value'];
		}
		if ( '' !== $item['price_text'] ) {
			$lines[] = $item['price_text'];
		}
		if ( '' !== $item['notice'] ) {
			$lines[] = $item['notice'];
		}
		if ( empty( $item['available'] ) && '' === $item['notice'] ) {
			$lines[] = __( 'This product is no longer available.', 'nh-wishlist' );
		}
		$blocks[] = array(
			'heading' => $item['name'],
			'lines'   => $lines,
		);
	}
	if ( ! $blocks ) {
		$blocks[] = array(
			'heading' => '',
			'lines'   => array( __( 'Your wishlist is empty.', 'nh-wishlist' ) ),
		);
	}
	return array(
		'title'    => __( 'Wishlist', 'nh-wishlist' ),
		'subtitle' => $view['label'],
		'meta'     => $view['date'],
		'footer'   => $view['site_name'],
		'blocks'   => $blocks,
	);
}

/**
 * @param string $label List label.
 * @return string
 */
function nh_wl_pdf_filename( $label ) {
	$slug = strtolower( remove_accents( (string) $label ) );
	$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
	$slug = trim( (string) $slug, '-' );
	if ( '' === $slug ) {
		$slug = 'wishlist';
	}
	return 'wishlist-' . $slug . '.pdf';
}

/**
 * @return array<string,mixed>
 */
function nh_wl_script_data() {
	$state = NH_WL_Store::state();
	$lists = array();
	foreach ( $state['lists'] as $list ) {
		$lists[] = array(
			'id'   => $list['id'],
			'name' => nh_wl_list_label( $list ),
		);
	}
	return array(
		'ajax'   => admin_url( 'admin-ajax.php' ),
		'nonce'  => wp_create_nonce( 'nh_wl' ),
		'lists'  => $lists,
		'active' => $state['active'],
		'saved'  => nh_wl_saved_index( $state ),
		'count'  => nh_wl_count_items( $state ),
		'i18n'   => array(
			'add'          => __( 'Add to wishlist', 'nh-wishlist' ),
			'inWishlist'   => __( 'In wishlist', 'nh-wishlist' ),
			'choose'       => __( 'Choose a wishlist', 'nh-wishlist' ),
			'create'       => __( 'Create a list', 'nh-wishlist' ),
			'createButton' => __( 'Create', 'nh-wishlist' ),
			'listName'     => __( 'List name', 'nh-wishlist' ),
			'close'        => __( 'Close', 'nh-wishlist' ),
			'saveTo'       => __( 'Save to %s', 'nh-wishlist' ),
			'removeFrom'   => __( 'Remove from %s', 'nh-wishlist' ),
			'savedOptions' => __( 'Saved with selected options', 'nh-wishlist' ),
			'customize'    => nh_wl_customize_notice(),
			'tryAgain'     => __( 'Please try again.', 'nh-wishlist' ),
			'savedTo'      => __( 'Saved to %s.', 'nh-wishlist' ),
			'removed'      => __( 'Removed from the wishlist.', 'nh-wishlist' ),
		),
	);
}

/**
 * @return string
 */
function nh_wl_heart_svg() {
	return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 20.5s-6.4-4.1-8.8-7.7C1.4 10.2 2 6.9 4.5 5.6 6.6 4.5 8.7 5.1 12 8.2c3.3-3.1 5.4-3.7 7.5-2.6 2.5 1.3 3.1 4.6 1.3 7.2-2.4 3.6-8.8 7.7-8.8 7.7z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"></path></svg>';
}

/**
 * @param WC_Product $product Product.
 * @param string     $context loop|single.
 * @return string
 */
function nh_wl_heart_html( $product, $context ) {
	if ( $product->is_type( 'variation' ) ) {
		$parent = wc_get_product( $product->get_parent_id() );
		if ( $parent instanceof WC_Product ) {
			$product = $parent;
		}
	}
	$state = NH_WL_Store::state();
	$flags = nh_wl_flags_for_product( $product );
	$pressed = false;
	if ( 'loop' === $context ) {
		$pressed = nh_wl_product_is_saved( $state, $product->get_id() );
	} else {
		$bare = nh_wl_make_item(
			array(
				'product_id' => $product->get_id(),
				'is_custom'  => ! empty( $flags['is_custom'] ),
				'dimensions' => array(
					'unit' => $flags['unit'],
					'type' => $flags['type'],
				),
			)
		);
		foreach ( nh_wl_saved_index( $state ) as $row ) {
			if ( $row['key'] === $bare['key'] ) {
				$pressed = true;
				break;
			}
		}
	}
	$label = $pressed ? __( 'In wishlist', 'nh-wishlist' ) : __( 'Add to wishlist', 'nh-wishlist' );
	$class = 'nh-wl-heart nh-wl-heart--' . $context . ( $pressed ? ' is-saved' : '' );
	$html  = '<button type="button" class="' . esc_attr( $class ) . '" data-context="' . esc_attr( $context ) . '" data-product-id="' . esc_attr( (string) $product->get_id() ) . '" data-custom="' . ( ! empty( $flags['is_custom'] ) ? '1' : '0' ) . '" data-variable="' . ( ! empty( $flags['is_variable'] ) ? '1' : '0' ) . '" aria-pressed="' . ( $pressed ? 'true' : 'false' ) . '" aria-haspopup="dialog" aria-label="' . esc_attr( $label ) . '">';
	$html .= nh_wl_heart_svg();
	if ( 'single' === $context ) {
		$html .= '<span class="nh-wl-heart__text">' . esc_html( $label ) . '</span>';
	} else {
		$html .= '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
	}
	$html .= '</button>';
	return $html;
}

/**
 * @param string $class Extra class.
 * @return void
 */
function nh_wl_header_link( $class = '' ) {
	$count = nh_wl_count_items( NH_WL_Store::state() );
	$classes = trim( 'nh-wl-header ' . $class );
	echo '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( nh_wl_view_url() ) . '" aria-label="' . esc_attr__( 'View wishlist', 'nh-wishlist' ) . '">';
	echo '<span class="nh-wl-header__icon">' . nh_wl_heart_svg();
	echo '<span class="nh-wl-badge" data-count="' . esc_attr( (string) $count ) . '">' . esc_html( (string) $count ) . '</span>';
	echo '</span>';
	echo '<span class="screen-reader-text">' . esc_html__( 'Wishlist', 'nh-wishlist' ) . '</span>';
	echo '</a>';
}

/**
 * @param string $context page|account.
 * @return void
 */
function nh_wl_render_wishlist( $context ) {
	$state = NH_WL_Store::state();
	$view  = nh_wl_prepare_view( $state, nh_wl_requested_list_id( $state ) );
	$view['context'] = $context;
	$view['action']  = admin_url( 'admin-post.php' );
	if ( function_exists( 'wc_print_notices' ) ) {
		wc_print_notices();
	}
	include NH_WL_DIR . 'templates/wishlist.php';
}

/**
 * @return bool
 */
function nh_wl_is_public_page() {
	$value = get_query_var( 'nh_wishlist' );
	return '1' === (string) $value;
}

/**
 * @param array<string,mixed> $state State.
 * @return array<string,mixed>
 */
function nh_wl_ajax_state( $state ) {
	$data = array(
		'lists'  => array(),
		'active' => $state['active'],
		'saved'  => nh_wl_saved_index( $state ),
		'count'  => nh_wl_count_items( $state ),
	);
	foreach ( $state['lists'] as $list ) {
		$data['lists'][] = array(
			'id'   => $list['id'],
			'name' => nh_wl_list_label( $list ),
		);
	}
	return $data;
}

/**
 * Form posts and heart AJAX.
 */
class NH_WL_Actions {

	public static function init() {
		add_action( 'admin_post_nh_wl', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nopriv_nh_wl', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nh_wl_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_nopriv_nh_wl_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_nh_wl_create', array( __CLASS__, 'ajax_create' ) );
		add_action( 'wp_ajax_nopriv_nh_wl_create', array( __CLASS__, 'ajax_create' ) );
		add_action( 'wp_ajax_nh_wl_state', array( __CLASS__, 'ajax_state' ) );
		add_action( 'wp_ajax_nopriv_nh_wl_state', array( __CLASS__, 'ajax_state' ) );
	}

	public static function handle() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'nh_wl' ) ) {
			wp_die( esc_html__( 'Please try again.', 'nh-wishlist' ), '', array( 'response' => 403 ) );
		}
		$do      = isset( $_POST['nh_wl_do'] ) ? sanitize_key( wp_unslash( $_POST['nh_wl_do'] ) ) : '';
		$list_id = isset( $_POST['list_id'] ) ? sanitize_key( wp_unslash( $_POST['list_id'] ) ) : '';
		$state   = NH_WL_Store::state();
		if ( '' === $list_id || ! nh_wl_find_list( $state, $list_id ) ) {
			$list_id = $state['active'];
		}
		if ( 'pdf' === $do ) {
			self::send_pdf( $state, $list_id );
		}
		switch ( $do ) {
			case 'create':
				$result = nh_wl_create_list( $state, isset( $_POST['list_name'] ) ? wp_unslash( $_POST['list_name'] ) : '' );
				self::finish_list_change( $result, __( 'List created.', 'nh-wishlist' ) );
				break;
			case 'rename':
				$result = nh_wl_rename_list( $state, $list_id, isset( $_POST['list_name'] ) ? wp_unslash( $_POST['list_name'] ) : '' );
				self::finish_list_change( $result, __( 'List renamed.', 'nh-wishlist' ) );
				break;
			case 'delete':
				$result = nh_wl_delete_list( $state, $list_id );
				if ( $result['ok'] ) {
					$list_id = $result['state']['active'];
				}
				self::finish_list_change( $result, __( 'List deleted.', 'nh-wishlist' ), $list_id );
				break;
			case 'qty':
				$key    = isset( $_POST['item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['item_key'] ) ) : '';
				$result = nh_wl_update_quantity( $state, $list_id, $key, isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1 );
				if ( $result['ok'] ) {
					NH_WL_Store::save( $result['state'] );
					wc_add_notice( __( 'Quantity updated.', 'nh-wishlist' ), 'success' );
				} else {
					wc_add_notice( nh_wl_error_message( $result['error'] ), 'error' );
				}
				break;
			case 'remove':
				$key    = isset( $_POST['item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['item_key'] ) ) : '';
				$result = nh_wl_remove_item( $state, $list_id, $key );
				if ( $result['ok'] ) {
					NH_WL_Store::save( $result['state'] );
					wc_add_notice( __( 'Removed from the wishlist.', 'nh-wishlist' ), 'success' );
				} else {
					wc_add_notice( nh_wl_error_message( $result['error'] ), 'error' );
				}
				break;
			case 'cart':
				self::add_one( $state, $list_id );
				break;
			case 'cart_all':
				self::add_all( $state, $list_id );
				break;
			case 'email':
				self::send_email( $state, $list_id );
				break;
			default:
				wc_add_notice( __( 'Please try again.', 'nh-wishlist' ), 'error' );
				break;
		}
		self::redirect( $list_id );
	}

	/**
	 * @param array<string,mixed> $result List mutation result.
	 * @param string              $success Success message.
	 * @param string              $list_id Redirect list.
	 * @return void
	 */
	private static function finish_list_change( $result, $success, $list_id = '' ) {
		if ( ! $result['ok'] ) {
			wc_add_notice( nh_wl_error_message( $result['error'] ), 'error' );
			self::redirect( $list_id );
		}
		NH_WL_Store::save( $result['state'] );
		wc_add_notice( $success, 'success' );
		if ( '' === $list_id && ! empty( $result['list_id'] ) ) {
			$list_id = $result['list_id'];
		}
		self::redirect( $list_id );
	}

	/**
	 * @param array<string,mixed> $state State.
	 * @param string              $list_id List id.
	 * @return void
	 */
	private static function add_one( $state, $list_id ) {
		$key = isset( $_POST['item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['item_key'] ) ) : '';
		$qty = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;
		if ( $qty > 0 ) {
			$updated = nh_wl_update_quantity( $state, $list_id, $key, $qty );
			if ( $updated['ok'] ) {
				$state = $updated['state'];
				NH_WL_Store::save( $state );
			}
		}
		$item = nh_wl_stored_item( $state, $list_id, $key );
		if ( ! $item ) {
			wc_add_notice( __( 'Invalid wishlist.', 'nh-wishlist' ), 'error' );
			return;
		}
		$result = NH_WL_Cart::add_item( $item );
		if ( is_wp_error( $result ) ) {
			if ( 'customize' === $result->get_error_code() ) {
				wc_add_notice( nh_wl_customize_notice(), 'notice' );
			} elseif ( ! function_exists( 'wc_notice_count' ) || ! wc_notice_count( 'error' ) ) {
				wc_add_notice( $result->get_error_message(), 'error' );
			}
			return;
		}
		wc_add_notice( __( 'Added to the basket.', 'nh-wishlist' ), 'success' );
	}

	/**
	 * @param array<string,mixed> $state State.
	 * @param string              $list_id List id.
	 * @return void
	 */
	private static function add_all( $state, $list_id ) {
		$list = nh_wl_find_list( $state, $list_id );
		if ( ! $list ) {
			wc_add_notice( __( 'Invalid wishlist.', 'nh-wishlist' ), 'error' );
			return;
		}
		$added   = 0;
		$skipped = 0;
		$failed  = 0;
		foreach ( $list['items'] as $item ) {
			$result = NH_WL_Cart::add_item( $item );
			if ( is_wp_error( $result ) ) {
				if ( 'customize' === $result->get_error_code() ) {
					++$skipped;
				} else {
					++$failed;
				}
				continue;
			}
			++$added;
		}
		if ( 1 === $added ) {
			wc_add_notice( __( 'Added to the basket.', 'nh-wishlist' ), 'success' );
		} elseif ( $added > 1 ) {
			wc_add_notice( sprintf( __( 'Added %d products to the basket.', 'nh-wishlist' ), $added ), 'success' );
		}
		if ( $skipped > 0 ) {
			wc_add_notice( sprintf( __( '%d products must be customized before they can be added to the basket.', 'nh-wishlist' ), $skipped ), 'notice' );
		}
		if ( $failed > 0 && ( ! function_exists( 'wc_notice_count' ) || ! wc_notice_count( 'error' ) ) ) {
			wc_add_notice( __( 'Could not add this product to the basket.', 'nh-wishlist' ), 'error' );
		}
	}

	/**
	 * @param array<string,mixed> $state State.
	 * @param string              $list_id List id.
	 * @return void
	 */
	private static function send_email( $state, $list_id ) {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-fA-F:.]/', '', (string) $_SERVER['REMOTE_ADDR'] ) : '0';
		$key   = 'nh_wl_mail_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 8 ) {
			wc_add_notice( __( 'Could not send the email. Please try again.', 'nh-wishlist' ), 'error' );
			return;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		$target  = isset( $_POST['target'] ) ? sanitize_key( wp_unslash( $_POST['target'] ) ) : 'recipient';
		$comment = nh_wl_sanitize_comment( isset( $_POST['comment'] ) ? wp_unslash( $_POST['comment'] ) : '' );
		$view    = nh_wl_prepare_view( $state, $list_id );
		if ( 'service' === $target ) {
			$to      = nh_wl_service_email();
			$subject = __( 'Wishlist for customer service', 'nh-wishlist' );
			$intro   = __( 'A customer sent this wishlist.', 'nh-wishlist' );
		} else {
			$to = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			if ( ! is_email( $to ) ) {
				wc_add_notice( __( 'Enter an email address.', 'nh-wishlist' ), 'error' );
				return;
			}
			$subject = __( 'A wishlist for you', 'nh-wishlist' );
			$intro   = '';
		}
		$rows = array();
		foreach ( $view['items'] as $item ) {
			$rows[] = array(
				'name'      => $item['name'],
				'url'       => $item['url'],
				'qty_label' => $item['qty_label'],
				'lines'     => $item['lines'],
				'notice'    => $item['notice'],
			);
		}
		$html    = nh_wl_email_html(
			array(
				'site_name'     => $view['site_name'],
				'heading'       => __( 'Wishlist', 'nh-wishlist' ),
				'list_name'     => $view['label'],
				'intro'         => $intro,
				'comment'       => $comment,
				'comment_label' => __( 'Comment:', 'nh-wishlist' ),
				'open_label'    => __( 'Open product', 'nh-wishlist' ),
				'items'         => $rows,
			)
		);
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( $user instanceof WP_User && is_email( $user->user_email ) ) {
				$headers[] = 'Reply-To: ' . $user->user_email;
			}
		}
		$sent = wp_mail( $to, $subject, $html, $headers );
		if ( ! $sent ) {
			wc_add_notice( __( 'Could not send the email. Please try again.', 'nh-wishlist' ), 'error' );
			return;
		}
		wc_add_notice( __( 'Wishlist sent.', 'nh-wishlist' ), 'success' );
	}

	/**
	 * @param array<string,mixed> $state State.
	 * @param string              $list_id List id.
	 * @return void
	 */
	private static function send_pdf( $state, $list_id ) {
		$view = nh_wl_prepare_view( $state, $list_id );
		$pdf  = nh_wl_pdf_render( nh_wl_pdf_document( $view ), NH_WL_DIR . 'assets/fonts/LiberationSans-Regular.ttf' );
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . nh_wl_pdf_filename( $view['label'] ) . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * @param string $list_id List id.
	 * @return void
	 */
	private static function redirect( $list_id = '' ) {
		$ref = wp_get_referer();
		if ( $ref ) {
			$ref = remove_query_arg( 'list', $ref );
			if ( '' !== $list_id ) {
				$ref = add_query_arg( 'list', $list_id, $ref );
			}
			wp_safe_redirect( $ref );
			exit;
		}
		wp_safe_redirect( nh_wl_view_url( $list_id ) );
		exit;
	}

	public static function ajax_state() {
		check_ajax_referer( 'nh_wl', 'nonce' );
		wp_send_json_success( nh_wl_ajax_state( NH_WL_Store::state() ) );
	}

	public static function ajax_create() {
		check_ajax_referer( 'nh_wl', 'nonce' );
		$result = nh_wl_create_list( NH_WL_Store::state(), isset( $_POST['list_name'] ) ? wp_unslash( $_POST['list_name'] ) : '' );
		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'message' => nh_wl_error_message( $result['error'] ) ), 400 );
		}
		$state = NH_WL_Store::save( $result['state'] );
		wp_send_json_success(
			array_merge(
				nh_wl_ajax_state( $state ),
				array(
					'list_id' => $result['list_id'],
					'message' => __( 'List created.', 'nh-wishlist' ),
				)
			)
		);
	}

	public static function ajax_save() {
		check_ajax_referer( 'nh_wl', 'nonce' );
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product    = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'This product is no longer available.', 'nh-wishlist' ) ), 404 );
		}
		$context = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : 'single';
		if ( 'loop' !== $context ) {
			$context = 'single';
		}
		$state   = NH_WL_Store::state();
		$list_id = isset( $_POST['list_id'] ) ? sanitize_key( wp_unslash( $_POST['list_id'] ) ) : '';
		if ( '' === $list_id || ! nh_wl_find_list( $state, $list_id ) ) {
			$list_id = $state['active'];
		}
		$item = nh_wl_item_from_request( $product, wp_unslash( $_POST ), $context );
		if ( ! empty( $_POST['remove'] ) ) {
			$result = nh_wl_remove_item( $state, $list_id, $item['key'] );
			if ( ! $result['ok'] ) {
				wp_send_json_error( array( 'message' => nh_wl_error_message( $result['error'] ) ), 400 );
			}
			$state = NH_WL_Store::save( $result['state'] );
			wp_send_json_success(
				array_merge(
					nh_wl_ajax_state( $state ),
					array(
						'message'          => __( 'Removed from the wishlist.', 'nh-wishlist' ),
						'needs_customize'  => false,
					)
				)
			);
		}
		$result = nh_wl_add_item( $state, $list_id, $item );
		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'message' => nh_wl_error_message( $result['error'] ) ), 400 );
		}
		$state = NH_WL_Store::save( $result['state'] );
		$list  = nh_wl_find_list( $state, $list_id );
		$flags_product = $product;
		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent instanceof WC_Product ) {
				$flags_product = $parent;
			}
		}
		$flags = nh_wl_flags_for_product( $flags_product );
		$needs = nh_wl_item_needs_customize( $result['item'], $flags );
		$message = sprintf( __( 'Saved to %s.', 'nh-wishlist' ), $list ? nh_wl_list_label( $list ) : '' );
		if ( $needs ) {
			$message .= ' ' . nh_wl_customize_notice();
		}
		wp_send_json_success(
			array_merge(
				nh_wl_ajax_state( $state ),
				array(
					'message'         => $message,
					'needs_customize' => $needs,
				)
			)
		);
	}
}
