<?php
/**
 * Classic cart UX: mobile-first layout and a visible shipping calculator.
 *
 * Uses WooCommerce's own shipping calculator (country + postcode only).
 * Item-data display stays in basket-customize.php.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shipping countries available on this shop.
 *
 * @return array<string, string>
 */
function nh_cart_shipping_countries() {
	if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
		return array();
	}
	$countries = WC()->countries->get_shipping_countries();
	return is_array( $countries ) ? $countries : array();
}

/**
 * Country to submit when the selector is hidden (single shipping country).
 *
 * @return string
 */
function nh_cart_default_shipping_country() {
	$countries = nh_cart_shipping_countries();
	$current   = ( function_exists( 'WC' ) && WC()->customer ) ? (string) WC()->customer->get_shipping_country() : '';

	if ( $current !== '' && isset( $countries[ $current ] ) ) {
		return $current;
	}

	$base = function_exists( 'WC' ) && WC()->countries ? (string) WC()->countries->get_base_country() : '';
	if ( $base !== '' && isset( $countries[ $base ] ) ) {
		return $base;
	}

	$keys = array_keys( $countries );
	return isset( $keys[0] ) ? (string) $keys[0] : '';
}

function nh_cart_ux_init() {
	add_filter( 'woocommerce_shipping_calculator_enable_city', '__return_false' );
	add_filter( 'woocommerce_shipping_calculator_enable_state', '__return_false' );
	add_filter( 'woocommerce_shipping_calculator_enable_postcode', '__return_true' );

	add_action( 'wp_enqueue_scripts', 'nh_cart_ux_assets', 100 );
	add_action( 'wp_footer', 'nh_cart_ux_layout_lock_css', 1 );
	add_action( 'woocommerce_before_cart', 'nh_cart_ux_layout_open', 1 );
	add_action( 'woocommerce_before_cart', 'nh_cart_ux_layout_main_open', 15 );
	add_action( 'woocommerce_before_cart_collaterals', 'nh_cart_ux_render_cross_sells', 0 );
	add_action( 'woocommerce_before_cart_collaterals', 'nh_cart_ux_layout_side_open', 1 );
	add_action( 'woocommerce_after_cart', 'nh_cart_ux_layout_side_close', 5 );
	add_action( 'woocommerce_after_cart', 'nh_cart_ux_sticky_bar', 20 );
	add_action( 'woocommerce_after_cart', 'nh_cart_ux_layout_close', 99 );
	add_action( 'wp', 'nh_cart_ux_relocate_cross_sells', 99 );
	add_filter( 'woocommerce_cross_sells_columns', 'nh_cart_ux_cross_sells_columns' );
	add_filter( 'woocommerce_cross_sells_total', 'nh_cart_ux_cross_sells_total' );
	add_filter( 'body_class', 'nh_cart_ux_body_class' );
}
add_action( 'init', 'nh_cart_ux_init' );

/**
 * @param array $classes Body classes.
 * @return array
 */
function nh_cart_ux_body_class( $classes ) {
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		$classes[] = 'nh-cart-ux';
		if ( count( nh_cart_shipping_countries() ) <= 1 ) {
			$classes[] = 'nh-cart-ux--single-country';
		}
	}
	return $classes;
}

function nh_cart_ux_assets() {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}

	$deps = array( 'jquery' );
	if ( wp_script_is( 'wc-cart', 'registered' ) || wp_script_is( 'wc-cart', 'enqueued' ) ) {
		$deps[] = 'wc-cart';
	}

	wp_enqueue_script(
		'nh-cart-ux',
		get_stylesheet_directory_uri() . '/assets/js/cart-ux.js',
		$deps,
		norhage_asset_version( '/assets/js/cart-ux.js' ),
		function_exists( 'norhage_script_args' ) ? norhage_script_args() : true
	);

	wp_localize_script(
		'nh-cart-ux',
		'nhCartUx',
		array(
			'couponLabel' => __( 'Have a coupon?', 'nh-theme' ),
		)
	);

	// Reload after Astra / Woo layout CSS so floats cannot cover the product table.
	$style_deps = array( 'astra-custom-for-norhage-theme-css' );
	foreach ( array(
		'woocommerce-layout',
		'woocommerce-general',
		'woocommerce-smallscreen',
		'astra-theme-css',
		'astra-addon-css',
		'woocommerce-inline',
		'wc-blocks-style',
	) as $handle ) {
		if ( wp_style_is( $handle, 'registered' ) || wp_style_is( $handle, 'enqueued' ) ) {
			$style_deps[] = $handle;
		}
	}
	wp_dequeue_style( 'custom-basket-css' );
	wp_enqueue_style(
		'custom-basket-css',
		get_stylesheet_directory_uri() . '/assets/css/basket.css',
		$style_deps,
		norhage_asset_version( '/assets/css/basket.css' )
	);
}

/**
 * Last-resort layout lock. Customizer CSS prints at ~101; this wins on cascade.
 */
function nh_cart_ux_layout_lock_css() {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}
	echo '<style id="nh-cart-layout-lock">'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout{display:flex!important;flex-direction:column;flex-wrap:wrap;width:100%!important;max-width:100%!important;float:none!important}'
		. '@media(min-width:960px){'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout{flex-direction:row!important;flex-wrap:wrap!important;align-items:flex-start!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout__main,'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout>.woocommerce-cart-form{flex:1 1 0%!important;min-width:0!important;overflow:visible!important;width:auto!important;max-width:calc(100% - 432px)!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout__side,'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout>.cart-collaterals{flex:0 0 400px!important;width:400px!important;max-width:400px!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout .woocommerce-cart-form{float:left!important;width:calc(100% - 432px)!important;max-width:calc(100% - 432px)!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout .cart-collaterals,'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout .cart_totals{float:right!important;width:400px!important;max-width:400px!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout__main .woocommerce-cart-form{float:none!important;width:100%!important;max-width:100%!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout__side .cart-collaterals,'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout__side .cart_totals{float:none!important;width:100%!important;max-width:100%!important}'
		. '}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout>.woocommerce-notices-wrapper,'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout>.nh-cart-sticky-bar{flex:1 0 100%;width:100%!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout .quantity,'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout .quantity.buttons_added{display:inline-flex!important;flex-wrap:nowrap!important;justify-content:flex-start!important;align-items:stretch!important;gap:0!important;width:max-content!important;min-width:0!important;padding:0!important;margin:0!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout .quantity .minus,'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout .quantity .plus,'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout .quantity .qty{flex:0 0 auto!important;margin:0!important;float:none!important;position:static!important;transform:none!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout .quantity .minus,'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout .quantity .plus{width:40px!important;min-width:40px!important;max-width:40px!important;border-radius:0!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout .quantity .qty{width:2.75rem!important;min-width:2.75rem!important;max-width:2.75rem!important;padding:0!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout table.shop_table.cart{overflow:visible!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout td.product-remove{position:relative!important;overflow:hidden!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout .product-remove a.remove{position:relative!important;display:inline-flex!important;inset:auto!important;float:none!important;width:36px!important;height:36px!important;min-width:36px!important;max-width:36px!important;min-height:36px!important;max-height:36px!important;margin:0!important;padding:0!important;font-size:16px!important;overflow:hidden!important;border-radius:999px!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .nh-cart-layout .product-remove a.remove svg{width:16px!important;height:16px!important;max-width:16px!important;max-height:16px!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .cross-sells{margin:.75rem 0 0!important;padding:8px!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .cross-sells>h2{margin:0 0 6px!important;padding:0!important;font-size:.95rem!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .cross-sells ul.products{display:grid!important;grid-template-columns:1fr;gap:6px!important;margin:0!important;padding:0!important;width:100%!important;float:none!important}'
		. '@media(min-width:720px){html body.woocommerce-cart.nh-cart-ux .cross-sells ul.products{grid-template-columns:1fr 1fr!important}}'
		. 'html body.woocommerce-cart.nh-cart-ux .cross-sells li.product{display:flex!important;flex-direction:row!important;flex-wrap:wrap!important;align-items:center!important;float:none!important;width:auto!important;max-width:none!important;margin:0!important;padding:6px!important;text-align:left!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .cross-sells .astra-shop-thumbnail-wrap,html body.woocommerce-cart.nh-cart-ux .cross-sells li.product img{width:48px!important;max-width:48px!important;height:auto!important;margin:0!important;padding:0!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .cross-sells li.product img{height:48px!important;object-fit:cover}'
		. 'html body.woocommerce-cart.nh-cart-ux .cross-sells .astra-shop-summary-wrap,html body.woocommerce-cart.nh-cart-ux .cross-sells .woocommerce-loop-product__title{margin:0!important;padding:0!important;text-align:left!important}'
		. 'html body.woocommerce-cart.nh-cart-ux .cross-sells .button{margin:0!important;min-height:32px!important;padding:0 8px!important}'
		. '@media(max-width:959px){'
		. 'html body.woocommerce-cart .site-content>.ast-container,'
		. 'html body.woocommerce-cart.ast-separate-container .ast-container,'
		. 'html body.woocommerce-cart.ast-plain-container .ast-container{padding-left:10px!important;padding-right:10px!important}'
		. 'html body.woocommerce-cart #primary,'
		. 'html body.woocommerce-cart .ast-article-single,'
		. 'html body.woocommerce-cart .entry-content,'
		. 'html body.woocommerce-cart .woocommerce{padding-left:0!important;padding-right:0!important;margin-left:0!important;margin-right:0!important}'
		. '}'
		. '</style>' . "\n";
}

/**
 * Two-column shell: products | shipping + totals.
 *
 * Separate column wrappers create block formatting contexts so Astra/Woo
 * `float:right` on `.cart-collaterals` cannot sit on top of the product table.
 *
 * Cross-sells sit in the first column under the product list so the totals
 * column stays a focused checkout card.
 */
function nh_cart_ux_relocate_cross_sells() {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}
	remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
	remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display', 10 );
	remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display', 20 );
}

/**
 * First column, under the product list — totals stay focused in the sidebar.
 */
function nh_cart_ux_render_cross_sells() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	$ids = WC()->cart->get_cross_sells();
	if ( empty( $ids ) ) {
		return;
	}
	woocommerce_cross_sell_display( 4, 2 );
}

/**
 * @param int $columns Woo default.
 * @return int
 */
function nh_cart_ux_cross_sells_columns( $columns ) {
	return 2;
}

/**
 * @param int $total Woo default.
 * @return int
 */
function nh_cart_ux_cross_sells_total( $total ) {
	return 4;
}

function nh_cart_ux_layout_open() {
	echo '<div class="nh-cart-layout">';
}

function nh_cart_ux_layout_main_open() {
	echo '<div class="nh-cart-layout__main">';
}

function nh_cart_ux_layout_side_open() {
	echo '</div><div class="nh-cart-layout__side">';
}

function nh_cart_ux_layout_side_close() {
	echo '</div>';
}

function nh_cart_ux_layout_close() {
	echo '</div>';
}

/**
 * Mobile sticky checkout bar. Hidden on desktop (summary column is enough).
 */
function nh_cart_ux_sticky_bar() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return;
	}

	$checkout = wc_get_checkout_url();
	?>
	<div class="nh-cart-sticky-bar" role="region" aria-label="<?php echo esc_attr__( 'Proceed to checkout', 'woocommerce' ); ?>">
		<div class="nh-cart-sticky-bar__total">
			<span class="nh-cart-sticky-bar__label"><?php esc_html_e( 'Total', 'woocommerce' ); ?></span>
			<span class="nh-cart-sticky-bar__amount"><?php echo wp_kses_post( WC()->cart->get_total() ); ?></span>
		</div>
		<a class="nh-cart-sticky-bar__cta checkout-button button alt" href="<?php echo esc_url( $checkout ); ?>">
			<?php esc_html_e( 'Proceed to checkout', 'woocommerce' ); ?>
		</a>
	</div>
	<?php
}
