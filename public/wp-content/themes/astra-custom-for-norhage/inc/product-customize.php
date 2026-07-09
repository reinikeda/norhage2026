<?php
/**
 * Custom Cutting — Product-level logic
 * Supports SIMPLE + VARIABLE products when _nh_cc_enabled = true
 *
 * Weight model (UPDATED):
 * - Uses WooCommerce "Weight (kg)" as kg per 1 m²
 *   - SIMPLE: product weight = kg/m²
 *   - VARIABLE: variation weight = kg/m² (fallback to parent weight if variation weight empty)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================================
 * BUNDLE BACK LINK (shown only when arriving from bundle)
 * Expects URL format from bundle-box.php:
 *   /bundle-item/?bundle_parent=123#nc-complete-set
 * ========================================================================== */
add_action( 'woocommerce_single_product_summary', function () {

	// Only on product pages
	if ( ! function_exists( 'is_product' ) || ! is_product() ) return;

	// Show only when coming from a bundle click
	if ( empty( $_GET['bundle_parent'] ) ) return;

	$parent_id = absint( wp_unslash( $_GET['bundle_parent'] ) );
	if ( ! $parent_id ) return;

	// Ensure parent exists and is a product
	$parent = wc_get_product( $parent_id );
	if ( ! $parent instanceof WC_Product ) return;

	$back_url = get_permalink( $parent_id ) . '#nc-complete-set';

	echo '<a class="nh-bundle-back-link" href="' . esc_url( $back_url ) . '" aria-label="' . esc_attr__( 'Back to bundle', 'nh-theme' ) . '">';
	echo '← ' . esc_html__( 'Back to bundle', 'nh-theme' );
	echo '</a>';

}, 1 );

/* ============================================================================
 * BUNDLE BACK LINK (secondary) — below the entire add-to-cart form (new line)
 * ========================================================================== */
add_action( 'woocommerce_after_add_to_cart_form', function () {

	if ( ! function_exists( 'is_product' ) || ! is_product() ) return;
	if ( empty( $_GET['bundle_parent'] ) ) return;

	$parent_id = absint( wp_unslash( $_GET['bundle_parent'] ) );
	if ( ! $parent_id ) return;

	$parent = wc_get_product( $parent_id );
	if ( ! $parent instanceof WC_Product ) return;

	$back_url = get_permalink( $parent_id ) . '#nc-complete-set';

	echo '<div class="nh-bundle-back-link--after-form">';
	echo '<a class="nh-bundle-back-link" href="' . esc_url( $back_url ) . '">← ' . esc_html__( 'Back to bundle', 'nh-theme' ) . '</a>';
	echo '</div>';

}, 5 );

add_filter( 'wpseo_canonical', function( $canonical ) {
    if ( isset( $_GET['bundle_parent'] ) ) {
        return strtok( $canonical, '?' ); // remove parameters
    }
    return $canonical;
});

/* ============================================================================
 * HELPERS
 * ========================================================================== */
function nh_cc_is_enabled_product( $product_id ) : bool {
	return (bool) get_post_meta( (int) $product_id, '_nh_cc_enabled', true );
}

/**
 * Get display regular/sale prices for "price per m²" for a given product object (simple or variation)
 * Returns array: [ 'reg' => float, 'sale' => float ]
 */
function nh_cc_get_perm2_display_prices( WC_Product $p ) : array {
	$reg_raw  = $p->get_regular_price();
	$sale_raw = $p->get_sale_price();
	$base_raw = $p->get_price();

	$reg_d  = ( $reg_raw  !== '' ) ? wc_get_price_to_display( $p, [ 'price' => (float) $reg_raw ] ) : 0;
	$sale_d = ( $sale_raw !== '' ) ? wc_get_price_to_display( $p, [ 'price' => (float) $sale_raw ] ) : 0;

	if ( $reg_d <= 0 && $sale_d <= 0 ) {
		$reg_d = wc_get_price_to_display( $p, [ 'price' => (float) $base_raw ] );
	}
	if ( $reg_d <= 0 && $sale_d > 0 ) {
		$reg_d = $sale_d;
	}

	return [
		'reg'  => (float) $reg_d,
		'sale' => (float) $sale_d,
	];
}

/**
 * Get kg per m² from Woo "weight" field.
 * - For a variation: use variation weight if set, else fallback to parent weight if set
 * - For a simple product: use its own weight
 */
function nh_cc_get_kg_per_m2_from_product( WC_Product $p ) : float {
	$w = (float) $p->get_weight();

	if ( $w > 0 ) return $w;

	// Fallback for variations: use parent weight if provided
	if ( $p->is_type( 'variation' ) ) {
		$parent = wc_get_product( $p->get_parent_id() );
		if ( $parent instanceof WC_Product ) {
			$pw = (float) $parent->get_weight();
			if ( $pw > 0 ) return $pw;
		}
	}

	return 0.0;
}

/* ============================================================================
 * BODY CLASS — flag custom-cut product pages (simple OR variable)
 * ========================================================================== */
add_filter( 'body_class', function ( $classes ) {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) return $classes;

	$product_obj = wc_get_product( get_queried_object_id() );
	if ( ! $product_obj instanceof WC_Product ) {
		global $product;
		if ( $product instanceof WC_Product ) $product_obj = $product;
	}

	if (
		$product_obj instanceof WC_Product
		&& ( $product_obj->is_type( 'simple' ) || $product_obj->is_type( 'variable' ) )
		&& nh_cc_is_enabled_product( $product_obj->get_id() )
	) {
		$classes[] = 'nh-has-custom-cut';
	}

	return $classes;
} );

/* ============================================================================
 * FRONTEND — Custom inputs (ONLY on the custom-cut product)
 * Supports: SIMPLE + VARIABLE parent
 * ========================================================================== */
add_action( 'woocommerce_before_add_to_cart_button', function () {
	global $product;
	if ( ! $product instanceof WC_Product ) return;
	if ( ! ( $product->is_type( 'simple' ) || $product->is_type( 'variable' ) ) ) return;
	if ( ! nh_cc_is_enabled_product( $product->get_id() ) ) return;

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

	// Width (mm)
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

	// Length (mm)
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

	// Length in metres (for linear products). Hidden by default; JS shows when product is configured as linear+m.
	$min_l_m = get_post_meta( $pid, '_nh_cc_min_l', true );
	$max_l_m = get_post_meta( $pid, '_nh_cc_max_l', true );
	$step_m  = get_post_meta( $pid, '_nh_cc_step_m', true );
	$step_m_attr = ( (string) $step_m !== '' && is_numeric( $step_m ) ) ? $step_m : '0.01';

	echo '    <tr class="nh-row-length-m" style="display:none;"><td class="label"><label for="nh_length_m">' . esc_html__( 'Length (m)', 'nh-theme' ) . '</label></td>';
	echo '      <td class="value"><div class="quantity buttons_added nh-m-qty" data-field="length_m">';
	echo '        <a href="#" class="minus" aria-label="' . esc_attr__( 'Decrease length', 'nh-theme' ) . '">-</a>';
	echo '        <input id="nh_length_m" name="nh_length_m" class="input-text nh-m-input text" type="number" inputmode="decimal"'
		. ' step="' . esc_attr( $step_m_attr ) . '"'
		. ( ( $min_l_m !== '' ) ? ' min="' . esc_attr( $min_l_m ) . '"' : '' )
		. ( ( $max_l_m !== '' ) ? ' max="' . esc_attr( $max_l_m ) . '"' : '' )
		. ' data-min="' . esc_attr( $min_l_m ) . '" data-max="' . esc_attr( $max_l_m ) . '"'
		. ' placeholder="' . esc_attr( $min_l_m !== '' ? ( $min_l_m . '–' . $max_l_m . ' m' ) : '' ) . '" autocomplete="off">';
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

		<!-- Price per metre (for linear products). Hidden by default; JS will show it for linear products -->
		<li class="nh-ps-row nh-ps-perm" style="display:none;">
		  <span><?php esc_html_e( 'Price per m', 'nh-theme' ); ?></span>
		  <span class="nh-ps-val" data-ps="perm">—</span>
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
 * VARIABLE SUPPORT — expose custom-cut pricing info per variation
 * The selected variation price is "price per m²" or price per metre depending on admin config.
 * Weight per m² comes from Woo weight:
 * - For planar: kg per m² is used
 * - For linear: JS/PHP expects product price to be price per metre
 * ========================================================================== */
add_filter( 'woocommerce_available_variation', function ( $data, $product, $variation ) {

	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) return $data;
	if ( ! nh_cc_is_enabled_product( $product->get_id() ) ) return $data;
	if ( ! $variation instanceof WC_Product_Variation ) return $data;

	$perm2 = nh_cc_get_perm2_display_prices( $variation );

	$fee_raw  = (float) get_post_meta( $product->get_id(), '_nh_cc_cut_fee', true );
	$fee_disp = $fee_raw > 0 ? wc_get_price_to_display( $variation, [ 'price' => $fee_raw ] ) : 0;

	$kg_per_m2 = nh_cc_get_kg_per_m2_from_product( $variation ); // UPDATED

	// Also expose parent unit/type to variation data for JS
	$parent_unit = get_post_meta( $product->get_id(), '_nh_cc_unit', true ) ?: 'mm';
	$parent_type = get_post_meta( $product->get_id(), '_nh_cc_type', true ) ?: 'planar';
	$step_m = get_post_meta( $product->get_id(), '_nh_cc_step_m', true ) ?: 0.01;

	$data['nh_cc_enabled']         = true;
	$data['nh_cc_perm2_reg_disp']  = (float) $perm2['reg'];
	$data['nh_cc_perm2_sale_disp'] = (float) $perm2['sale'];
	$data['nh_cc_cut_fee_disp']    = (float) $fee_disp;
	$data['nh_cc_kg_per_m2']       = (float) $kg_per_m2;
	$data['nh_cc_unit']            = sanitize_text_field( $parent_unit );
	$data['nh_cc_type']            = sanitize_text_field( $parent_type );
	$data['nh_cc_step_m']          = (float) $step_m;

	return $data;
}, 10, 3 );

/* ============================================================================
 * ASSETS
 * ========================================================================== */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_product() ) return;

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

		wp_localize_script(
			'nh-attr-buttons',
			'NH_ATTR_I18N',
			[
				'all_oos_msg' => __( 'Sorry, all combinations are unavailable.', 'nh-theme' ),
			]
		);
	}

	$is_custom_cut = (
		$product_obj instanceof WC_Product
		&& ( $product_obj->is_type( 'simple' ) || $product_obj->is_type( 'variable' ) )
		&& nh_cc_is_enabled_product( $product_obj->get_id() )
	);

	if ( $is_custom_cut ) {

		$pid = $product_obj->get_id();

		// Fee display (from parent meta)
		$fee_raw  = (float) get_post_meta( $pid, '_nh_cc_cut_fee', true );
		$fee_disp = $fee_raw > 0 ? wc_get_price_to_display( $product_obj, [ 'price' => $fee_raw ] ) : 0;

		// Weight per m² from Woo weight:
		// - SIMPLE: we can pass now
		// - VARIABLE: pass 0, JS fills from selected variation via woocommerce_available_variation data
		$kg_per_m2 = 0.0;
		if ( $product_obj->is_type( 'simple' ) ) {
			$kg_per_m2 = nh_cc_get_kg_per_m2_from_product( $product_obj );
		}

		// For SIMPLE we can set perm² immediately; for VARIABLE JS will fill after variation selection
		$perm2_reg  = 0.0;
		$perm2_sale = 0.0;
		if ( $product_obj->is_type( 'simple' ) ) {
			$perm2       = nh_cc_get_perm2_display_prices( $product_obj );
			$perm2_reg   = (float) $perm2['reg'];
			$perm2_sale  = (float) $perm2['sale'];
		}

		wp_enqueue_script(
			'custom-cutting',
			$theme_uri . '/assets/js/custom-cutting.js',
			[ 'jquery', 'nh-price-summary-core', 'wc-add-to-cart-variation' ],
			filemtime( $theme_dir . '/assets/js/custom-cutting.js' ),
			true
		);

		wp_localize_script( 'custom-cutting', 'NH_CC', [
			'enabled'         => true,
			'perm2_reg_disp'  => (float) $perm2_reg,
			'perm2_sale_disp' => (float) $perm2_sale,
			'cut_fee'         => (float) $fee_disp,
			'min_w'           => ( $v = get_post_meta( $pid, '_nh_cc_min_w', true ) ) === '' ? '' : (int) $v,
			'max_w'           => ( $v = get_post_meta( $pid, '_nh_cc_max_w', true ) ) === '' ? '' : (int) $v,
			'min_l'           => ( $v = get_post_meta( $pid, '_nh_cc_min_l', true ) ) === '' ? '' : $v,
			'max_l'           => ( $v = get_post_meta( $pid, '_nh_cc_max_l', true ) ) === '' ? '' : $v,
			'step'            => ( $v = get_post_meta( $pid, '_nh_cc_step_mm', true ) ) === '' ? 1 : (int) $v,
			'step_m'          => ( $v = get_post_meta( $pid, '_nh_cc_step_m', true ) ) === '' ? 0.01 : (float) $v,
			'kg_per_m2'       => (float) $kg_per_m2,
			'weight_per_m2'   => (float) $kg_per_m2,
			'unit'            => ( $v = get_post_meta( $pid, '_nh_cc_unit', true ) ) === '' ? 'mm' : sanitize_text_field( $v ),
			'type'            => ( $v = get_post_meta( $pid, '_nh_cc_type', true ) ) === '' ? 'planar' : sanitize_text_field( $v ),
		] );

	} else {
		// Non custom-cut products use your normal summary handler
		wp_enqueue_script(
			'nh-summary-variable',
			$theme_uri . '/assets/js/nh-summary-variable.js',
			[ 'jquery', 'nh-price-summary-core', 'wc-add-to-cart-variation' ],
			filemtime( $theme_dir . '/assets/js/nh-summary-variable.js' ),
			true
		);

		// defaults for SIMPLE non-custom
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
 * ADD-TO-CART VALIDATION (custom-cut simple OR variable parent)
 * ========================================================================== */
add_filter( 'woocommerce_add_to_cart_validation', 'nh_cc_validate_custom_cut', 10, 4 );
function nh_cc_validate_custom_cut( $passed, $product_id, $qty = 0, $variation_id = 0 ) {

	$p = wc_get_product( $product_id );
	if ( ! $p instanceof WC_Product ) return $passed;

	// Only when enabled on the parent (simple or variable parent)
	if ( ! ( $p->is_type( 'simple' ) || $p->is_type( 'variable' ) ) ) return $passed;
	if ( ! nh_cc_is_enabled_product( $product_id ) ) return $passed;

	$unit = get_post_meta( $product_id, '_nh_cc_unit', true ) ?: 'mm';
	$type = get_post_meta( $product_id, '_nh_cc_type', true ) ?: 'planar';

	$w_raw = isset( $_POST['nh_width_mm'] )  ? trim( wp_unslash( $_POST['nh_width_mm'] ) )  : '';
	$l_raw = isset( $_POST['nh_length_mm'] ) ? trim( wp_unslash( $_POST['nh_length_mm'] ) ) : '';
	$l_m_raw = isset( $_POST['nh_length_m'] ) ? trim( wp_unslash( $_POST['nh_length_m'] ) ) : '';
	$flag  = isset( $_POST['nh_custom_cutting'] ) && $_POST['nh_custom_cutting'] === '1';

	if ( ! $flag && $w_raw === '' && $l_raw === '' && $l_m_raw === '' ) return $passed;

	// If variable: require a variation to be selected (Woo will also validate, but this improves messaging)
	if ( $p->is_type( 'variable' ) && (int) $variation_id <= 0 ) {
		wc_add_notice( esc_html__( 'Please choose product options (variation) before entering dimensions.', 'nh-theme' ), 'error' );
		return false;
	}

	// Planar mm flow (backwards-compatible)
	if ( $type === 'planar' && $unit === 'mm' ) {

		$w = absint( $w_raw );
		$l = absint( $l_raw );
		if ( $w <= 0 || $l <= 0 ) {
			wc_add_notice( esc_html__( 'Please enter width and length (mm).', 'nh-theme' ), 'error' );
			return false;
		}

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
				wc_add_notice( sprintf( esc_html__( 'Width must align to %1$d mm steps starting at %2$d mm.', 'nh-theme' ), $step, $origin_w ), 'error' );
				return false;
			}
			if ( ( ( $l - $origin_l ) % $step ) !== 0 ) {
				wc_add_notice( sprintf( esc_html__( 'Length must align to %1$d mm steps starting at %2$d mm.', 'nh-theme' ), $step, $origin_l ), 'error' );
				return false;
			}
		}

		return $passed;
	}

	// Linear metre flow (new)
	if ( $type === 'linear' && $unit === 'm' ) {
		// allow decimal metres
		if ( $l_m_raw === '' ) {
			wc_add_notice( esc_html__( 'Please enter length (m).', 'nh-theme' ), 'error' );
			return false;
		}
		$l_m = floatval( str_replace( ',', '.', $l_m_raw ) );
		if ( $l_m <= 0 ) {
			wc_add_notice( esc_html__( 'Please enter a valid length in metres.', 'nh-theme' ), 'error' );
			return false;
		}

		$min_l = get_post_meta( $product_id, '_nh_cc_min_l', true );
		$max_l = get_post_meta( $product_id, '_nh_cc_max_l', true );
		if ( $min_l !== '' && $min_l !== null ) {
			$min_l_f = floatval( $min_l );
			if ( $l_m < $min_l_f ) { wc_add_notice( sprintf( esc_html__( 'Minimum length is %s m.', 'nh-theme' ),  $min_l_f ), 'error' ); return false; }
		}
		if ( $max_l !== '' && $max_l !== null ) {
			$max_l_f = floatval( $max_l );
			if ( $l_m > $max_l_f ) { wc_add_notice( sprintf( esc_html__( 'Maximum length is %s m.', 'nh-theme' ),  $max_l_f ), 'error' ); return false; }
		}

		$step_m = get_post_meta( $product_id, '_nh_cc_step_m', true );
		if ( $step_m !== '' && is_numeric( $step_m ) ) {
			$origin_l = (float) get_post_meta( $product_id, '_nh_cc_step_origin_l', true );
			if ( ! $origin_l ) $origin_l = ( $min_l !== '' ? (float) $min_l : 0.0 );
			// Check alignment to float step: compute rounded ratio
			$ratio = ( $l_m - $origin_l ) / (float) $step_m;
			if ( abs( $ratio - round( $ratio ) ) > 0.0000001 ) {
				wc_add_notice( sprintf( esc_html__( 'Length must align to %1$s m steps starting at %2$s m.', 'nh-theme' ), $step_m, $origin_l ), 'error' );
				return false;
			}
		}

		return $passed;
	}

	// Fallback: keep previous behavior if unknown config
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
 * ADD CUSTOM DATA TO CART / ORDER (custom-cut simple OR variable parent)
 * Store base price-per-m² or price-per-m (for linear) so cart totals are stable even after set_price()
 * Weight model updated: store kg per m² from Woo weight (variation or product)
 * ========================================================================== */
add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id ){

	$parent = wc_get_product( $product_id );
	if ( ! $parent instanceof WC_Product ) return $cart_item_data;
	if ( ! ( $parent->is_type( 'simple' ) || $parent->is_type( 'variable' ) ) ) return $cart_item_data;
	if ( ! nh_cc_is_enabled_product( $product_id ) ) return $cart_item_data;

	$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
	$chosen = $variation_id ? wc_get_product( $variation_id ) : $parent;
	if ( ! $chosen instanceof WC_Product ) $chosen = $parent;

	// Read unit/type from parent meta
	$unit = get_post_meta( $product_id, '_nh_cc_unit', true ) ?: 'mm';
	$type = get_post_meta( $product_id, '_nh_cc_type', true ) ?: 'planar';

	$w_mm = isset( $_POST['nh_width_mm'] ) ? absint( $_POST['nh_width_mm'] ) : 0;
	$l_mm = isset( $_POST['nh_length_mm'] ) ? absint( $_POST['nh_length_mm'] ) : 0;
	$l_m  = isset( $_POST['nh_length_m'] )  ? floatval( str_replace( ',', '.', wp_unslash( $_POST['nh_length_m'] ) ) ) : 0.0;

	$cart_item_data['nh_custom_size'] = [
		'unit'      => $unit,
		'type'      => $type,
		'width_mm'  => $w_mm,
		'length_mm' => $l_mm,
		'length_m'  => $l_m,
	];

	// Base price from the selected product/variation (interpreted according to product type)
	$cart_item_data['nh_cc_base_price'] = (float) $chosen->get_price();

	// Fee from PARENT meta (shared across variations)
	$cart_item_data['nh_cc_cut_fee_raw'] = (float) get_post_meta( $product_id, '_nh_cc_cut_fee', true );

	// kg per m² from Woo "weight" on chosen (variation or simple)
	$cart_item_data['nh_cc_kg_per_m2'] = (float) nh_cc_get_kg_per_m2_from_product( $chosen );

	$cart_item_data['nh_unique'] = md5( $product_id . '|' . $variation_id . '|' . $w_mm . 'x' . $l_mm . '|' . $l_m . '|' . microtime( true ) );

	if ( isset( $_POST['nh_custom_unit_kg'] ) ) {
		$cart_item_data['nh_custom_unit_kg'] = (float) wc_clean( wp_unslash( $_POST['nh_custom_unit_kg'] ) );
	}

	return $cart_item_data;
}, 10, 2 );

add_filter( 'woocommerce_get_item_data', function( $item_data, $cart_item ){

	if ( empty( $cart_item['nh_custom_size'] ) ) return $item_data;

	$size = $cart_item['nh_custom_size'];

	$w = isset( $size['width_mm'] ) ? (int) $size['width_mm'] : 0;
	$lmm = isset( $size['length_mm'] ) ? (int) $size['length_mm'] : 0;
	$lm = isset( $size['length_m'] ) ? floatval( $size['length_m'] ) : 0;

	if ( $w ) $item_data[] = [ 'name' => __( 'Width', 'nh-theme' ),  'value' => $w . ' mm' ];
	if ( $lmm ) $item_data[] = [ 'name' => __( 'Length', 'nh-theme' ), 'value' => $lmm . ' mm' ];
	if ( $lm ) $item_data[] = [ 'name' => __( 'Length', 'nh-theme' ), 'value' => rtrim( rtrim( number_format( $lm, 3, '.', '' ), '0' ), '.' ) . ' m' ];

	$fee_raw = isset( $cart_item['nh_cc_cut_fee_raw'] ) ? (float) $cart_item['nh_cc_cut_fee_raw'] : 0;
	if ( $fee_raw > 0 ) {
		$product = $cart_item['data'] ?? null;
		$fee_disp = $fee_raw;
		if ( $product instanceof WC_Product ) {
			$fee_disp = wc_get_price_to_display( $product, [ 'price' => $fee_raw ] );
		}

		// label depends on unit/type
		$label = __( 'Cutting fee', 'nh-theme' );
		$unit = isset( $cart_item['nh_custom_size']['unit'] ) ? $cart_item['nh_custom_size']['unit'] : 'mm';
		if ( $unit === 'm' ) {
			$label .= ' ' . __( 'per metre', 'nh-theme' );
		} else {
			$label .= ' ' . __( 'per sheet', 'nh-theme' );
		}

		$item_data[] = [
			'name'  => $label,
			'value' => wc_price( $fee_disp ),
		];
	}

	return $item_data;
}, 10, 2 );

add_action( 'woocommerce_checkout_create_order_line_item', function( $item, $cart_item_key, $values ) {

	if ( empty( $values['nh_custom_size'] ) ) return;

	$size = $values['nh_custom_size'];
	$wmm = (int) ( $size['width_mm'] ?? 0 );
	$lmm = (int) ( $size['length_mm'] ?? 0 );
	$lm  = isset( $size['length_m'] ) ? floatval( $size['length_m'] ) : 0.0;
	$unit = isset( $size['unit'] ) ? $size['unit'] : 'mm';

	/** @var WC_Product|null $product */
	$product = $item->get_product();
	if ( ! $product instanceof WC_Product ) return;

	$price_base = isset( $values['nh_cc_base_price'] ) ? (float) $values['nh_cc_base_price'] : (float) $product->get_price();
	$fee        = isset( $values['nh_cc_cut_fee_raw'] ) ? (float) $values['nh_cc_cut_fee_raw'] : 0;

	// --- Technical meta (canonical keys) ----
	if ( $wmm ) {
		$item->add_meta_data( 'cutting_width',  $wmm . ' mm', true );
	}
	if ( $lmm ) {
		$item->add_meta_data( 'cutting_height', $lmm . ' mm', true );
	}

	// Planar mm price
	if ( $wmm && $lmm && $price_base > 0 && $unit === 'mm' ) {
		$area      = ( $wmm / 1000 ) * ( $lmm / 1000 );
		$unit_base = $area * $price_base; // without fee
		$item->add_meta_data( 'unit_price', wc_format_decimal( $unit_base, wc_get_price_decimals() ), true );
	}

	// Linear metre price + technical length
	if ( $lm > 0 && $price_base > 0 && $unit === 'm' ) {
		$unit_base = $lm * $price_base; // price_base interpreted as price per metre
		$item->add_meta_data( 'unit_price', wc_format_decimal( $unit_base, wc_get_price_decimals() ), true );

		// store canonical length meta for mapping/display
		$lm_display = rtrim( rtrim( number_format( $lm, 3, '.', '' ), '0' ), '.' ) . ' m';
		$item->add_meta_data( 'cutting_length_m', $lm_display, true );
	}

	// Technical cutting fee (raw decimal)
	if ( $fee > 0 ) {
		$item->add_meta_data( 'cutting_fee', wc_format_decimal( $fee, wc_get_price_decimals() ), true );
	}

	// ---- Add human-readable meta only if not already present ----
	// This ensures order views/emails show friendly labels but avoids duplicates.

	// Width (human)
	if ( $wmm ) {
		$existing_width = $item->get_meta( __( 'Width', 'nh-theme' ), true );
		if ( empty( $existing_width ) ) {
			$item->add_meta_data( __( 'Width', 'nh-theme' ), $wmm . ' mm', true );
		}
	}

	// Length (human) — prefer metre display when unit === 'm'
	if ( $unit === 'm' && $lm > 0 ) {
		$existing_length = $item->get_meta( __( 'Length', 'nh-theme' ), true );
		if ( empty( $existing_length ) ) {
			$item->add_meta_data( __( 'Length', 'nh-theme' ), $lm_display, true );
		}
	} elseif ( $lmm ) {
		$existing_length = $item->get_meta( __( 'Length', 'nh-theme' ), true );
		if ( empty( $existing_length ) ) {
			$item->add_meta_data( __( 'Length', 'nh-theme' ), $lmm . ' mm', true );
		}
	}

	// Cutting fee (human)
	if ( $fee > 0 ) {
		$existing_fee = $item->get_meta( __( 'Cutting fee', 'nh-theme' ), true );
		if ( empty( $existing_fee ) ) {
			$fee_disp = wc_get_price_to_display( $product, [ 'price' => $fee ] );
			$item->add_meta_data( __( 'Cutting fee', 'nh-theme' ), wc_price( $fee_disp ), true );
		}
	}

}, 10, 3 );

/* ============================================================================
 * CUSTOM PRICING — area × price_per_m² + fee OR length × price_per_m + fee
 * Works for cart items that are variations too
 * Weight model updated: kg per m² from Woo weight stored per cart line
 * ========================================================================== */
add_action( 'woocommerce_before_calculate_totals', function( $cart ){

	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	if ( empty( $cart ) ) return;

	foreach ( $cart->get_cart() as $cart_item_key => $item ) {

		if ( empty( $item['nh_custom_size'] ) ) continue;

		/** @var WC_Product $product */
		$product = $item['data'];
		if ( ! $product instanceof WC_Product ) continue;

		// Enabled on parent product id stored in cart item
		$parent_id = (int) ( $item['product_id'] ?? 0 );
		if ( $parent_id <= 0 || ! nh_cc_is_enabled_product( $parent_id ) ) continue;

		$size = $item['nh_custom_size'];
		$unit = $size['unit'] ?? 'mm';

		// Planar mm flow
		if ( $unit === 'mm' ) {
			$wmm = (int) ( $size['width_mm'] ?? 0 );
			$lmm = (int) ( $size['length_mm'] ?? 0 );
			if ( $wmm <= 0 || $lmm <= 0 ) continue;

			$area = ( $wmm / 1000 ) * ( $lmm / 1000 );

			$price_per_m2 = isset( $item['nh_cc_base_price'] )
				? (float) $item['nh_cc_base_price']
				: (float) $product->get_price();

			$fee = isset( $item['nh_cc_cut_fee_raw'] )
				? (float) $item['nh_cc_cut_fee_raw']
				: (float) get_post_meta( $parent_id, '_nh_cc_cut_fee', true );

			// Planar (mm) price calculation — replace existing set_price() block with this
			if ( $price_per_m2 > 0 ) {
				$unit_price = $area * $price_per_m2 + max( 0, $fee );

				// Prevent Woo showing a "You saved" value unless the product actually has a sale price.
				$has_sale_price = $product->get_sale_price() !== '' && $product->get_sale_price() !== null;
				if ( ! $has_sale_price && is_callable( array( $product, 'set_regular_price' ) ) ) {
					// Set the in-memory regular price equal to the calculated price so Woo doesn't display a crossed-out price.
					$product->set_regular_price( wc_format_decimal( $unit_price, wc_get_price_decimals() ) );
				}

				$product->set_price( wc_format_decimal( $unit_price, wc_get_price_decimals() ) );
			}

			// Weight (kg per m²)
			$kg_per_m2 = isset( $item['nh_cc_kg_per_m2'] ) ? (float) $item['nh_cc_kg_per_m2'] : 0.0;
			if ( $kg_per_m2 <= 0 ) {
				$kg_per_m2 = nh_cc_get_kg_per_m2_from_product( $product );
			}
			if ( $kg_per_m2 > 0 ) {
				$unit_kg = $area * $kg_per_m2;
				$product->set_weight( wc_format_decimal( $unit_kg, 4 ) );
			}

			continue;
		}

		// Linear metre flow
		if ( $unit === 'm' ) {
			$lm = isset( $size['length_m'] ) ? floatval( $size['length_m'] ) : 0.0;
			if ( $lm <= 0 ) continue;

			$price_per_m = isset( $item['nh_cc_base_price'] ) ? (float) $item['nh_cc_base_price'] : (float) $product->get_price();

			$fee = isset( $item['nh_cc_cut_fee_raw'] )
				? (float) $item['nh_cc_cut_fee_raw']
				: (float) get_post_meta( $parent_id, '_nh_cc_cut_fee', true );
				
			// Linear (m) price calculation — replace existing set_price() block with this
			if ( $price_per_m > 0 ) {
				$unit_price = $lm * $price_per_m + max( 0, $fee );

				// Prevent Woo showing a "You saved" value unless the product actually has a sale price.
				$has_sale_price = $product->get_sale_price() !== '' && $product->get_sale_price() !== null;
				if ( ! $has_sale_price && is_callable( array( $product, 'set_regular_price' ) ) ) {
					$product->set_regular_price( wc_format_decimal( $unit_price, wc_get_price_decimals() ) );
				}

				$product->set_price( wc_format_decimal( $unit_price, wc_get_price_decimals() ) );
			}

			// Weight: we currently don't auto-convert kg/m² to linear weight.
			// If you need weight per metre, set product/variation weight accordingly and store it in nh_cc_kg_per_m2 or a new meta.
			continue;
		}
	}
}, 20 );

/* ============================================================================
 * PRICE DISPLAY — add "/ m²" for custom-cut planar products and "/ m" for linear
 * Applies on archives + single product pages.
 * Also supports variable products and selected variations.
 * ========================================================================== */

/**
 * Helper: read meta for product (variation first, then parent) with default.
 */
function nh_cc_get_effective_meta( $product, $meta_key, $default = '' ) {
	if ( ! $product instanceof WC_Product ) {
		return $default;
	}

	$id = $product->get_id();
	$val = get_post_meta( $id, $meta_key, true );

	// If variation and meta not set on variation, fall back to parent product
	if ( ( $val === '' || $val === false ) && $product->is_type( 'variation' ) ) {
		$parent_id = $product->get_parent_id();
		if ( $parent_id ) {
			$val = get_post_meta( $parent_id, $meta_key, true );
		}
	}

	return ( $val === '' || $val === false ) ? $default : $val;
}

/**
 * Return the appropriate price unit suffix HTML for a product.
 * - linear products -> " / m"
 * - planar/mm products -> " / m²"
 * Returns empty string if product is not a custom-cut product.
 */
function nh_cc_get_price_unit_suffix_html_for_product( $product ) : string {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	// Only handle when custom-cut is enabled (check variation then parent).
	$enabled = nh_cc_get_effective_meta( $product, '_nh_cc_enabled', '' );
	if ( empty( $enabled ) ) {
		return '';
	}

	// Prefer explicit product type if present; fall back to unit.
	$type = strtolower( (string) nh_cc_get_effective_meta( $product, '_nh_cc_type', '' ) );
	$unit = strtolower( (string) nh_cc_get_effective_meta( $product, '_nh_cc_unit', 'mm' ) );

	// Determine suffix: linear OR unit == 'm' => per-m; otherwise per-m²
	if ( 'linear' === $type || 'm' === $unit ) {
		// Translatable
		$suffix = ' / m';
		$suffix_html = '<span class="nh-price-unit">' . esc_html_x( $suffix, 'price unit', 'nh-theme' ) . '</span>';
	} else {
		$suffix = ' / m²';
		$suffix_html = '<span class="nh-price-unit">' . esc_html_x( $suffix, 'price unit', 'nh-theme' ) . '</span>';
	}

	return $suffix_html;
}

/**
 * Append unit suffix to Woo price HTML for custom-cut products where applicable.
 */
add_filter( 'woocommerce_get_price_html', function ( $price_html, $product ) {

	// Don't change admin displays.
	if ( is_admin() ) {
		return $price_html;
	}

	if ( ! $product instanceof WC_Product ) {
		return $price_html;
	}

	// Do not modify empty price html
	if ( trim( wp_strip_all_tags( $price_html ) ) === '' ) {
		return $price_html;
	}

	// Get suffix for this product (if any)
	$suffix_html = nh_cc_get_price_unit_suffix_html_for_product( $product );
	if ( empty( $suffix_html ) ) {
		return $price_html;
	}

	// Avoid double suffix
	if ( strpos( $price_html, 'nh-price-unit' ) !== false ) {
		return $price_html;
	}

	return $price_html . ' ' . $suffix_html;

}, 20, 2 );

/**
 * Ensure selected variation price on single product page also gets the unit suffix.
 * Woo outputs variation price_html from variation data (available_variation).
 */
add_filter( 'woocommerce_available_variation', function ( $data, $product, $variation ) {

	if ( ! $variation instanceof WC_Product_Variation ) {
		return $data;
	}

	// Do not modify empty price html
	if ( empty( $data['price_html'] ) || trim( wp_strip_all_tags( $data['price_html'] ) ) === '' ) {
		return $data;
	}

	// Determine suffix for this variation (uses variation meta first, then parent)
	$suffix_html = nh_cc_get_price_unit_suffix_html_for_product( $variation );
	if ( empty( $suffix_html ) ) {
		return $data;
	}

	// Avoid double suffix
	if ( strpos( $data['price_html'], 'nh-price-unit' ) === false ) {
		$data['price_html'] .= ' ' . $suffix_html;
	}

	return $data;

}, 20, 3 );
