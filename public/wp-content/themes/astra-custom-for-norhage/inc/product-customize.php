<?php
/**
 * Custom Cutting — Product-level logic (SIMPLE products with _nh_cc_enabled = true)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================================
 * BODY CLASS — flag custom-cut simple product pages
 * ========================================================================== */
add_filter( 'body_class', function ( $classes ) {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) return $classes;

	$product_obj = wc_get_product( get_queried_object_id() );
	if ( ! $product_obj instanceof WC_Product ) {
		global $product;
		if ( $product instanceof WC_Product ) $product_obj = $product;
	}
	if ( $product_obj instanceof WC_Product && $product_obj->is_type( 'simple' ) ) {
		if ( (bool) get_post_meta( $product_obj->get_id(), '_nh_cc_enabled', true ) ) {
			$classes[] = 'nh-has-custom-cut';
		}
	}
	return $classes;
} );

/* ============================================================================
 * FRONTEND — Custom inputs (ONLY on the custom-cut SIMPLE product)
 * ========================================================================== */
add_action( 'woocommerce_before_add_to_cart_button', function () {
	global $product;
	if ( ! $product instanceof WC_Product ) return;
	if ( ! $product->is_type( 'simple' ) ) return;
	if ( ! (bool) get_post_meta( $product->get_id(), '_nh_cc_enabled', true ) ) return;

	$pid = $product->get_id();
	$min_w = get_post_meta( $pid, '_nh_cc_min_w', true );
	$max_w = get_post_meta( $pid, '_nh_cc_max_w', true );
	$min_l = get_post_meta( $pid, '_nh_cc_min_l', true );
	$max_l = get_post_meta( $pid, '_nh_cc_max_l', true );
	$step  = get_post_meta( $pid, '_nh_cc_step_mm', true );

	$step_attr = ( (string) $step !== '' && (int) $step > 0 ) ? (int) $step : 1;

	$origin_w_meta = get_post_meta( $pid, '_nh_cc_step_origin_w', true );
	$origin_l_meta = get_post_meta( $pid, '_nh_cc_step_origin_l', true );
	$origin_w      = ( $origin_w_meta !== '' ? (int) $origin_w_meta : ( $min_w !== '' ? (int) $min_w : 0 ) );
	$origin_l      = ( $origin_l_meta !== '' ? (int) $origin_l_meta : ( $min_l !== '' ? (int) $min_l : 0 ) );

	$ph_w = ( $min_w !== '' && $max_w !== '' )
		? sprintf( esc_html__( '%1$d–%2$d mm', 'nh-theme' ), (int) $min_w, (int) $max_w )
		: ( $min_w !== '' ? sprintf( esc_html__( '≥ %d mm', 'nh-theme' ), (int) $min_w )
		: ( $max_w !== '' ? sprintf( esc_html__( '≤ %d mm', 'nh-theme' ), (int) $max_w ) : '' ) );

	$ph_l = ( $min_l !== '' && $max_l !== '' )
		? sprintf( esc_html__( '%1$d–%2$d mm', 'nh-theme' ), (int) $min_l, (int) $max_l )
		: ( $min_l !== '' ? sprintf( esc_html__( '≥ %d mm', 'nh-theme' ), (int) $min_l )
		: ( $max_l !== '' ? sprintf( esc_html__( '≤ %d mm', 'nh-theme' ), (int) $max_l ) : '' ) );

	echo '<input type="hidden" name="nh_custom_cutting" value="1">';

	echo '<div id="nh-custom-size-wrap" class="nh-size-ui"'
		. ' data-step-mm="' . esc_attr( $step_attr ) . '"'
		. ' data-origin-w="' . esc_attr( $origin_w ) . '"'
		. ' data-origin-l="' . esc_attr( $origin_l ) . '">';

	echo '  <table class="variations" cellspacing="0"><tbody>';

	// Width
	echo '    <tr class="nh-row-width"><td class="label"><label for="nh_width_mm">' . esc_html__( 'Width (mm)', 'nh-theme' ) . '</label></td>';
	echo '      <td class="value"><div class="quantity buttons_added nh-mm-qty" data-field="width">';
	echo '        <a href="#" class="minus" aria-label="' . esc_attr__( 'Decrease width', 'nh-theme' ) . '">-</a>';
	echo '        <input id="nh_width_mm" name="nh_width_mm" class="input-text nh-mm-input text" type="number" inputmode="numeric" pattern="[0-9]*"'
		. ' step="' . esc_attr( $step_attr ) . '"'
		. ( ( $min_w !== '' ) ? ' min="' . esc_attr( (int) $min_w ) . '"' : '' )
		. ( ( $max_w !== '' ) ? ' max="' . esc_attr( (int) $max_w ) . '"' : '' )
		. ' data-min="' . esc_attr( $min_w ) . '" data-max="' . esc_attr( $max_w ) . '"'
		. ' placeholder="' . esc_attr( $ph_w ) . '" autocomplete="off">';
	echo '        <a href="#" class="plus" aria-label="' . esc_attr__( 'Increase width', 'nh-theme' ) . '">+</a>';
	echo '      </div></td></tr>';

	// Length
	echo '    <tr class="nh-row-length"><td class="label"><label for="nh_length_mm">' . esc_html__( 'Length (mm)', 'nh-theme' ) . '</label></td>';
	echo '      <td class="value"><div class="quantity buttons_added nh-mm-qty" data-field="length">';
	echo '        <a href="#" class="minus" aria-label="' . esc_attr__( 'Decrease length', 'nh-theme' ) . '">-</a>';
	echo '        <input id="nh_length_mm" name="nh_length_mm" class="input-text nh-mm-input text" type="number" inputmode="numeric" pattern="[0-9]*"'
		. ' step="' . esc_attr( $step_attr ) . '"'
		. ( ( $min_l !== '' ) ? ' min="' . esc_attr( (int) $min_l ) . '"' : '' )
		. ( ( $max_l !== '' ) ? ' max="' . esc_attr( (int) $max_l ) . '"' : '' )
		. ' data-min="' . esc_attr( $min_l ) . '" data-max="' . esc_attr( $max_l ) . '"'
		. ' placeholder="' . esc_attr( $ph_l ) . '" autocomplete="off">';
	echo '        <a href="#" class="plus" aria-label="' . esc_attr__( 'Increase length', 'nh-theme' ) . '">+</a>';
	echo '      </div></td></tr>';

	echo '  </tbody></table>';

	if ( $step_attr > 1 ) {
		echo '  <p class="nh-cc-hint-single">' . sprintf( esc_html__( 'Step: %d mm', 'nh-theme' ), $step_attr ) . '</p>';
	}
	echo '</div>';
}, 10 );

/* ============================================================================
 * FRONTEND — Price Summary (always present; rows shown/hidden by CSS/JS)
 * ========================================================================== */
add_action( 'woocommerce_before_add_to_cart_button', function () {
	?>
	<div id="nh-price-summary" class="nh-price-summary" aria-live="polite">
	  <div class="nh-ps-title"><?php esc_html_e( 'Your selection', 'nh-theme' ); ?></div>
	  <ul class="nh-ps-list">
		<li class="nh-ps-row nh-ps-perm2">
		  <span><?php esc_html_e( 'Price per m²', 'nh-theme' ); ?></span>
		  <span class="nh-ps-val" data-ps="perm2">—</span>
		</li>
		<li class="nh-ps-row nh-ps-cutfee">
		  <span><?php esc_html_e( 'Cutting fee per sheet', 'nh-theme' ); ?></span>
		  <span class="nh-ps-val" data-ps="cutfee">—</span>
		</li>
		<li class="nh-ps-row">
		  <span><?php esc_html_e( 'Unit price', 'nh-theme' ); ?></span>
		  <span class="nh-ps-val" data-ps="unit">—</span>
		</li>
	  </ul>
	  <div class="nh-ps-sep" role="separator"></div>
	  <div class="nh-ps-total">
		<span class="nh-ps-total-label"><?php esc_html_e( 'Total', 'nh-theme' ); ?></span>
		<span class="nh-ps-total-val" data-ps="total">—</span>
	  </div>
	</div>
	<?php
}, 11 );

/* ============================================================================
 * ASSETS (moved JS to files)
 * ========================================================================== */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_product() ) return;

	// Detect current product (queried first, then global)
	$product_obj = wc_get_product( get_queried_object_id() );
	if ( ! $product_obj instanceof WC_Product ) {
		global $product;
		if ( $product instanceof WC_Product ) $product_obj = $product;
	}

	$theme_dir = get_stylesheet_directory();
	$theme_uri = get_stylesheet_directory_uri();

	// 1) Price summary core (always)
	wp_enqueue_script(
		'nh-price-summary-core',
		$theme_uri . '/assets/js/nh-price-summary-core.js',
		[ 'jquery' ],
		filemtime( $theme_dir . '/assets/js/nh-price-summary-core.js' ),
		true
	);
	wp_localize_script( 'nh-price-summary-core', 'NH_PRICE_FMT', [
		'symbol'   => get_woocommerce_currency_symbol(),
		'pos'      => get_option( 'woocommerce_currency_pos', 'right_space' ),
		'decs'     => wc_get_price_decimals(),
		'thousand' => wc_get_price_thousand_separator(),
		'decimal'  => wc_get_price_decimal_separator(),
	] );

	// 2) Attribute buttons (for selects → buttons)
	wp_enqueue_script( 'wc-add-to-cart-variation' );
	if ( file_exists( $theme_dir . '/assets/js/nh-attr-buttons.js' ) ) {
		wp_enqueue_script(
			'nh-attr-buttons',
			$theme_uri . '/assets/js/nh-attr-buttons.js',
			[ 'jquery', 'wc-add-to-cart-variation' ],
			filemtime( $theme_dir . '/assets/js/nh-attr-buttons.js' ),
			true
		);
	}

	// Determine custom-cut simple vs others
	$is_custom_simple = (
		$product_obj instanceof WC_Product
		&& $product_obj->is_type( 'simple' )
		&& (bool) get_post_meta( $product_obj->get_id(), '_nh_cc_enabled', true )
	);

	if ( $is_custom_simple ) {

        // --- Custom-cut SIMPLE: enqueue custom-cutting + summary init

        $pid      = $product_obj->get_id();
        $reg_raw  = $product_obj->get_regular_price();
        $sale_raw = $product_obj->get_sale_price();
        $base_raw = $product_obj->get_price(); // value/m² (net)

        // Display prices (follow Woo "shop" tax setting, usually incl. VAT)
        $reg_d  = ( $reg_raw  !== '' ? wc_get_price_to_display( $product_obj, [ 'price' => (float) $reg_raw ] ) : 0 );
        $sale_d = ( $sale_raw !== '' ? wc_get_price_to_display( $product_obj, [ 'price' => (float) $sale_raw ] ) : 0 );
        if ( $reg_d <= 0 && $sale_d <= 0 ) {
            $reg_d = wc_get_price_to_display( $product_obj, [ 'price' => (float) $base_raw ] );
        }
        if ( $reg_d <= 0 && $sale_d > 0 ) {
            $reg_d = $sale_d;
        }

        // Cutting fee: get raw value from meta, then convert to display price (incl. VAT)
        $fee_raw  = (float) get_post_meta( $pid, '_nh_cc_cut_fee', true );
        $fee_disp = $fee_raw > 0
            ? wc_get_price_to_display( $product_obj, [ 'price' => $fee_raw ] )
            : 0;

		// custom-cutting.js config
		$kg_per_m2 = (float) get_post_meta( $pid, '_nh_cc_weight_per_m2', true );
		wp_enqueue_script(
			'custom-cutting',
			$theme_uri . '/assets/js/custom-cutting.js',
			[ 'jquery', 'nh-price-summary-core' ],
			filemtime( $theme_dir . '/assets/js/custom-cutting.js' ),
			true
		);
		wp_localize_script( 'custom-cutting', 'NH_CC', [
			'enabled'         => true,
			'price_per_m2'    => (float) $base_raw,
			'cut_fee'         => (float) $fee_disp,
			'min_w'           => ( $v = get_post_meta( $pid, '_nh_cc_min_w', true ) ) === '' ? '' : (int) $v,
			'max_w'           => ( $v = get_post_meta( $pid, '_nh_cc_max_w', true ) ) === '' ? '' : (int) $v,
			'min_l'           => ( $v = get_post_meta( $pid, '_nh_cc_min_l', true ) ) === '' ? '' : (int) $v,
			'max_l'           => ( $v = get_post_meta( $pid, '_nh_cc_max_l', true ) ) === '' ? '' : (int) $v,
			'step'            => ( $v = get_post_meta( $pid, '_nh_cc_step_mm', true ) ) === '' ? 1 : (int) $v,
			'perm2_reg_disp'  => (float) $reg_d,
			'perm2_sale_disp' => (float) $sale_d,
			'kg_per_m2'       => $kg_per_m2,
			'weight_per_m2'   => $kg_per_m2,
		] );

		// summary init for custom-cut
		wp_enqueue_script(
			'nh-summary-customcut-init',
			$theme_uri . '/assets/js/nh-summary-customcut-init.js',
			[ 'jquery', 'nh-price-summary-core' ],
			filemtime( $theme_dir . '/assets/js/nh-summary-customcut-init.js' ),
			true
		);
		wp_localize_script( 'nh-summary-customcut-init', 'NH_PS_INIT', [
			'perm2_reg'  => (float) $reg_d,
			'perm2_sale' => (float) $sale_d,
			'cut_fee'    => (float) $fee_disp,
		] );

	} else {
		// --- VARIABLE products + SIMPLE (non-custom): summary handler
		wp_enqueue_script(
			'nh-summary-variable',
			$theme_uri . '/assets/js/nh-summary-variable.js',
			[ 'jquery', 'nh-price-summary-core', 'wc-add-to-cart-variation' ],
			filemtime( $theme_dir . '/assets/js/nh-summary-variable.js' ),
			true
		);

		// Provide defaults for SIMPLE non-custom so Unit/Total render immediately
		if ( $product_obj instanceof WC_Product && $product_obj->is_type( 'simple' ) ) {
			$reg  = $product_obj->get_regular_price();
			$sale = $product_obj->get_sale_price();
			$curr = $product_obj->get_price();

			$simple_reg_d  = ( $reg  !== '' ) ? wc_get_price_to_display( $product_obj, [ 'price' => (float) $reg ] )  : 0;
			$simple_sale_d = ( $sale !== '' ) ? wc_get_price_to_display( $product_obj, [ 'price' => (float) $sale ] ) : 0;
			if ( $simple_reg_d <= 0 && $simple_sale_d <= 0 ) $simple_reg_d = wc_get_price_to_display( $product_obj, [ 'price' => (float) $curr ] );
			if ( $simple_reg_d <= 0 && $simple_sale_d > 0 ) $simple_reg_d = $simple_sale_d;

			wp_localize_script( 'nh-summary-variable', 'NH_SIMPLE_DEFAULT', [
				'reg'  => (float) $simple_reg_d,
				'sale' => (float) $simple_sale_d,
			] );
		}
	}
}, 98 );

/* ============================================================================
 * ADD-TO-CART VALIDATION (ONLY when a custom-cut request is being posted)
 * ========================================================================== */
add_filter( 'woocommerce_add_to_cart_validation', 'nh_cc_validate_custom_cut', 10, 4 );
function nh_cc_validate_custom_cut( $passed, $product_id, $qty = 0, $variation_id = 0 ) {
	$p = wc_get_product( $product_id );
	if ( ! $p || ! $p->is_type( 'simple' ) ) return $passed;
	if ( ! (bool) get_post_meta( $product_id, '_nh_cc_enabled', true ) ) return $passed;

	$w_raw = isset( $_POST['nh_width_mm'] )  ? trim( wp_unslash( $_POST['nh_width_mm'] ) )  : '';
	$l_raw = isset( $_POST['nh_length_mm'] ) ? trim( wp_unslash( $_POST['nh_length_mm'] ) ) : '';
	$flag  = isset( $_POST['nh_custom_cutting'] ) && $_POST['nh_custom_cutting'] === '1';
	if ( ! $flag && $w_raw === '' && $l_raw === '' ) return $passed;

	$w = absint( $w_raw );
	$l = absint( $l_raw );
	if ( $w <= 0 || $l <= 0 ) { wc_add_notice( esc_html__( 'Please enter width and length (mm).', 'nh-theme' ), 'error' ); return false; }

	$min_w = absint( get_post_meta( $product_id, '_nh_cc_min_w', true ) );
	$max_w = absint( get_post_meta( $product_id, '_nh_cc_max_w', true ) );
	$min_l = absint( get_post_meta( $product_id, '_nh_cc_min_l', true ) );
	$max_l = absint( get_post_meta( $product_id, '_nh_cc_max_l', true ) );

	if ( $min_w && $w < $min_w ) { wc_add_notice( sprintf( esc_html__( 'Minimum width is %d mm.', 'nh-theme' ),  $min_w ), 'error' ); return false; }
	if ( $max_w && $w > $max_w ) { wc_add_notice( sprintf( esc_html__( 'Maximum width is %d mm.', 'nh-theme' ),  $max_w ), 'error' ); return false; }
	if ( $min_l && $l < $min_l ) { wc_add_notice( sprintf( esc_html__( 'Minimum length is %d mm.', 'nh-theme' ), $min_l ), 'error' ); return false; }
	if ( $max_l && $l > $max_l ) { wc_add_notice( sprintf( esc_html__( 'Maximum length is %d mm.', 'nh-theme' ), $max_l ), 'error' ); return false; }

	$step = absint( get_post_meta( $product_id, '_nh_cc_step_mm', true ) );
	if ( $step > 0 ) {
		$origin_w = (int) get_post_meta( $product_id, '_nh_cc_step_origin_w', true );
		$origin_l = (int) get_post_meta( $product_id, '_nh_cc_step_origin_l', true );
		if ( ! $origin_w ) $origin_w = $min_w ?: 0;
		if ( ! $origin_l ) $origin_l = $min_l ?: 0;

		if ( ( ( $w - $origin_w ) % $step ) !== 0 ) {
			wc_add_notice( sprintf( esc_html__( 'Width must align to %1$d mm steps starting at %2$d mm.', 'nh-theme' ), $step, $origin_w ), 'error' ); return false;
		}
		if ( ( ( $l - $origin_l ) % $step ) !== 0 ) {
			wc_add_notice( sprintf( esc_html__( 'Length must align to %1$d mm steps starting at %2$d mm.', 'nh-theme' ), $step, $origin_l ), 'error' ); return false;
		}
	}
	return $passed;
}

/* ============================================================================
 * PRG after add-to-cart — avoid browser form re-submission on refresh
 * ========================================================================== */
add_filter( 'woocommerce_add_to_cart_redirect', function ( $redirect_url = '', $product = null ) {
	if ( wp_doing_ajax() ) return $redirect_url;
	if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add', 'no' ) ) return wc_get_cart_url();

	$target = wp_get_referer();
	if ( ! $target && $product instanceof WC_Product ) $target = get_permalink( $product->get_id() );
	if ( ! $target ) $target = wc_get_cart_url();

	$target = remove_query_arg( array(
		'add-to-cart', 'quantity', 'variation_id', '_wpnonce', '_wp_http_referer',
		'attribute_pa_width', 'attribute_pa_length', 'attribute_width', 'attribute_length',
	), $target );

	return $target;
}, 10, 2 );

/* ============================================================================
 * ADD CUSTOM DATA TO CART / ORDER (ONLY for custom-cut SIMPLE product)
 * ========================================================================== */
add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id ){
	$p = wc_get_product( $product_id );
	if ( ! $p || ! $p->is_type( 'simple' ) ) return $cart_item_data;
	if ( ! (bool) get_post_meta( $product_id, '_nh_cc_enabled', true ) ) return $cart_item_data;

	$w = (int) ( $_POST['nh_width_mm'] ?? 0 );
	$l = (int) ( $_POST['nh_length_mm'] ?? 0 );
	$cart_item_data['nh_custom_size'] = [ 'width_mm' => $w, 'length_mm' => $l ];
	$cart_item_data['nh_unique']      = md5( $product_id . '|' . $w . 'x' . $l . '|' . microtime( true ) );
	return $cart_item_data;
}, 10, 2 );

add_filter( 'woocommerce_get_item_data', function( $item_data, $cart_item ){
	if ( empty( $cart_item['nh_custom_size'] ) ) return $item_data;
	$w = (int) $cart_item['nh_custom_size']['width_mm'];
	$l = (int) $cart_item['nh_custom_size']['length_mm'];
	if ( $w ) $item_data[] = [ 'name' => __( 'Width', 'nh-theme' ),  'value' => $w . ' mm' ];
	if ( $l ) $item_data[] = [ 'name' => __( 'Length', 'nh-theme' ), 'value' => $l . ' mm' ];
	$pid = (int) ( $cart_item['product_id'] ?? 0 );
	$fee = (float) get_post_meta( $pid, '_nh_cc_cut_fee', true );
	if ( $fee > 0 ) $item_data[] = [ 'name' => __( 'Cutting fee per sheet', 'nh-theme' ), 'value' => wc_price( $fee ) ];
	return $item_data;
}, 10, 2 );

add_action( 'woocommerce_checkout_create_order_line_item', function( $item, $key, $values ){
	if ( empty( $values['nh_custom_size'] ) ) return;
	$w   = (int) ( $values['nh_custom_size']['width_mm'] ?? 0 );
	$l   = (int) ( $values['nh_custom_size']['length_mm'] ?? 0 );
	$pid = (int) ( $values['product_id'] ?? 0 );
	$fee = (float) get_post_meta( $pid, '_nh_cc_cut_fee', true );
	if ( $w )  $item->add_meta_data( __( 'Width', 'nh-theme' ),  $w . ' mm', true );
	if ( $l )  $item->add_meta_data( __( 'Length', 'nh-theme' ), $l . ' mm', true );
	if ( $fee ) $item->add_meta_data( __( 'Cutting fee per sheet', 'nh-theme' ), wc_price( $fee ), true );
}, 10, 3 );

/* ============================================================================
 * CUSTOM PRICING — area × value/m² + fee (ONLY for custom-cut SIMPLE product)
 * ========================================================================== */
add_action( 'woocommerce_before_calculate_totals', function( $cart ){
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	if ( empty( $cart ) ) return;

	foreach ( $cart->get_cart() as $item ) {
		if ( empty( $item['nh_custom_size'] ) ) continue;

		/** @var WC_Product $product */
		$product = $item['data'];
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'simple' ) ) continue;
		if ( ! (bool) get_post_meta( $product->get_id(), '_nh_cc_enabled', true ) ) continue;

		$price_per_m2 = (float) $product->get_price();
		$fee          = (float) get_post_meta( $product->get_id(), '_nh_cc_cut_fee', true );

		$wmm = (int) ( $item['nh_custom_size']['width_mm'] ?? 0 );
		$lmm = (int) ( $item['nh_custom_size']['length_mm'] ?? 0 );
		if ( $price_per_m2 <= 0 || $wmm <= 0 || $lmm <= 0 ) continue;

		$area = ( $wmm / 1000 ) * ( $lmm / 1000 );
		$unit = $area * $price_per_m2 + max( 0, $fee );
		$product->set_price( wc_format_decimal( $unit, wc_get_price_decimals() ) );
	}
}, 20 );
