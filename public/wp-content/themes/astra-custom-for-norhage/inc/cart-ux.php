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
	add_action( 'woocommerce_before_cart', 'nh_cart_ux_layout_open', 1 );
	add_action( 'woocommerce_after_cart', 'nh_cart_ux_sticky_bar', 20 );
	add_action( 'woocommerce_after_cart', 'nh_cart_ux_layout_close', 99 );
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
	foreach ( array( 'woocommerce-layout', 'woocommerce-general', 'woocommerce-smallscreen', 'astra-theme-css' ) as $handle ) {
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
 * Two-column shell: products | shipping + totals.
 */
function nh_cart_ux_layout_open() {
	echo '<div class="nh-cart-layout">';
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
