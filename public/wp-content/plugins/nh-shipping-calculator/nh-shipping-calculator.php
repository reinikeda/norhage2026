<?php
/**
 * Plugin Name: Universal Shipping Calculator (Heavy + Custom Cutting)
 * Description: One Flat rate in the zone. Tiered heavy override (5 levels) + Custom Cutting rules that map width/height to size shipping classes (XS–XXXL). Label shows "(Heavy Lx)" or "(Class)" accordingly.
 * Author: Daiva Reinike
 * Version: 7.3.4
 * Text Domain: nh-heavy-parcel
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */
define( 'NHGP_VERSION',    '7.3.4' );
define( 'NHGP_TEXTDOMAIN', 'nh-heavy-parcel' );
define( 'NHGP_DIR',        plugin_dir_path( __FILE__ ) );
define( 'NHGP_URL',        plugin_dir_url( __FILE__ ) );

/* -------------------------------------------------------------------------
 * Includes
 * ---------------------------------------------------------------------- */
require_once NHGP_DIR . 'includes/class-nhgp-defaults.php';
require_once NHGP_DIR . 'includes/class-nhgp-admin.php';
require_once NHGP_DIR . 'includes/class-nhgp-session.php';
require_once NHGP_DIR . 'includes/class-nhgp-custom-cut.php';
require_once NHGP_DIR . 'includes/class-nhgp-overrides.php';
require_once NHGP_DIR . 'includes/cart-bridge.php';

/* -------------------------------------------------------------------------
 * Textdomain
 * ---------------------------------------------------------------------- */
add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain(
			NHGP_TEXTDOMAIN,
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
	}
);

/* -------------------------------------------------------------------------
 * Boot core classes
 * ---------------------------------------------------------------------- */
add_action(
	'plugins_loaded',
	function () {
		// Ensure Woo is loaded.
		if ( class_exists( 'NHGP_Admin' ) ) {
			NHGP_Admin::init();
		}
		if ( class_exists( 'NHGP_Session' ) ) {
			NHGP_Session::init();
		}
		if ( class_exists( 'NHGP_Overrides' ) ) {
			NHGP_Overrides::init();
		}
	}
);

/* -------------------------------------------------------------------------
 * Ensure Norhage size-based shipping classes exist
 * ---------------------------------------------------------------------- */
add_action( 'init', 'nhgp_register_size_shipping_classes' );

function nhgp_register_size_shipping_classes() {
	// Make sure WooCommerce + taxonomy are available.
	if ( ! function_exists( 'wc_get_page_id' ) || ! taxonomy_exists( 'product_shipping_class' ) ) {
		return;
	}

	// slug => Name (keep slug stable)
	$classes = array(
		'xs'   => 'Extra Small',
		's'    => 'Small',
		'm'    => 'Medium',
		'l'    => 'Large',
		'xl'   => 'Extra Large',
		'xxl'  => 'Oversize',
		'xxxl' => 'Ultra Oversize',
	);

	foreach ( $classes as $slug => $label ) {
		$term = get_term_by( 'slug', $slug, 'product_shipping_class' );

		if ( ! $term || is_wp_error( $term ) ) {
			wp_insert_term(
				$label,
				'product_shipping_class',
				array(
					'slug' => $slug,
				)
			);
		}
	}
}

/* -------------------------------------------------------------------------
 * Front-end assets (CSS + JS)
 * ---------------------------------------------------------------------- */
function nhgp_enqueue_frontend_assets() {

	// Only load on Cart / Checkout pages to keep things light.
	$is_cart     = function_exists( 'is_cart' ) ? is_cart() : false;
	$is_checkout = function_exists( 'is_checkout' ) ? is_checkout() : false;

	if ( ! $is_cart && ! $is_checkout ) {
		return;
	}

	// CSS.
	wp_enqueue_style(
		'nhgp-frontend',
		NHGP_URL . 'assets/css/frontend.css',
		array(),
		NHGP_VERSION
	);

	// JS – depends on wp-data so we can read the Woo Blocks cart store for live weight updates.
	wp_enqueue_script(
		'nhgp-cart-weight',
		NHGP_URL . 'assets/js/cart-weight.js',
		array( 'wp-data' ),
		NHGP_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'nhgp_enqueue_frontend_assets' );

/* -------------------------------------------------------------------------
 * Cart total weight display (shortcode)
 * Shortcode: [nhgp_cart_total_weight]
 * ---------------------------------------------------------------------- */

/**
 * Get cart total weight in store units (kg/g/lbs/oz).
 *
 * IMPORTANT:
 * - Prefer custom-cut per-line weights (nh_custom_unit_kg / nh_custom_total_kg)
 *   because variable products can have different dimensions per cart line.
 * - Fall back to Woo weight if custom values are missing.
 */
function nhgp_get_cart_total_weight() : float {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0.0;
	}

	$unit = get_option( 'woocommerce_weight_unit', 'kg' );

	$total_in_store_unit = 0.0;
	$total_in_kg_custom  = 0.0;
	$has_custom_kg        = false;

	foreach ( WC()->cart->get_cart() as $cart_item ) {

		$qty = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
		if ( $qty < 1 ) {
			$qty = 1;
		}

		// 1) Prefer custom-cut explicit weights if present
		// nh_custom_total_kg is already total for the line (unit * qty), if your JS sets it.
		if ( isset( $cart_item['nh_custom_total_kg'] ) && is_numeric( $cart_item['nh_custom_total_kg'] ) ) {
			$kg = (float) $cart_item['nh_custom_total_kg'];
			if ( $kg > 0 ) {
				$total_in_kg_custom += $kg;
				$has_custom_kg = true;
				continue;
			}
		}

		if ( isset( $cart_item['nh_custom_unit_kg'] ) && is_numeric( $cart_item['nh_custom_unit_kg'] ) ) {
			$unit_kg = (float) $cart_item['nh_custom_unit_kg'];
			if ( $unit_kg > 0 ) {
				$total_in_kg_custom += ( $unit_kg * $qty );
				$has_custom_kg = true;
				continue;
			}
		}

		// 2) Fallback to Woo product weight (already in store units)
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		if ( $product instanceof WC_Product ) {
			$w = (float) $product->get_weight();
			if ( $w > 0 ) {
				$total_in_store_unit += ( $w * $qty );
			}
		}
	}

	// If we had any custom kg values, convert them to store units and add to total
	if ( $has_custom_kg && $total_in_kg_custom > 0 ) {
		if ( function_exists( 'wc_get_weight' ) ) {
			$total_in_store_unit += (float) wc_get_weight( $total_in_kg_custom, $unit, 'kg' );
		} else {
			// If Woo helper isn't available, assume store unit is kg
			$total_in_store_unit += $total_in_kg_custom;
		}
	}

	return (float) $total_in_store_unit;
}

/**
 * Shortcode renderer for [nhgp_cart_total_weight].
 */
function nhgp_cart_total_weight_shortcode() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return '';
	}

	$total = nhgp_get_cart_total_weight();
	if ( $total <= 0 ) {
		return '';
	}

	$unit = get_option( 'woocommerce_weight_unit', 'kg' );

	$formatted_number = number_format(
		$total,
		2,
		',',
		''
	);
	$formatted = $formatted_number . ' ' . $unit;

	ob_start(); ?>
	<div class="wc-block-components-totals-wrapper nhgp-cart-total-weight-wrapper">
		<div class="wc-block-components-totals-item">
			<span class="wc-block-components-totals-item__label">
				<?php esc_html_e( 'Total weight', NHGP_TEXTDOMAIN ); ?>
			</span>
			<span
				class="wc-block-components-totals-item__value nhgp-cart-total-weight"
				data-unit="<?php echo esc_attr( $unit ); ?>"
				data-weight="<?php echo esc_attr( $total ); ?>"
			>
				<?php echo esc_html( $formatted ); ?>
			</span>
			<div class="wc-block-components-totals-item__description"></div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'nhgp_cart_total_weight', 'nhgp_cart_total_weight_shortcode' );

/* -------------------------------------------------------------------------
 * Cart + Checkout: show total weight row in totals table (classic templates)
 * ---------------------------------------------------------------------- */

/**
 * Format the cart total weight into a readable string (e.g. "12,50 kg").
 */
function nhgp_format_cart_weight_for_display() {
	$total = nhgp_get_cart_total_weight();
	if ( $total <= 0 ) {
		return '';
	}

	$unit = get_option( 'woocommerce_weight_unit', 'kg' );
	$formatted_number = number_format(
		$total,
		2,
		',',
		''
	);

	return $formatted_number . ' ' . $unit;
}

/**
 * Output weight row in Cart totals (classic cart).
 */
function nhgp_output_cart_weight_row_cart() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	$formatted = nhgp_format_cart_weight_for_display();
	if ( $formatted === '' ) {
		return;
	}

	echo '<tr class="nhgp-cart-total-weight-row">';
	echo '<th>' . esc_html__( 'Total weight', NHGP_TEXTDOMAIN ) . '</th>';
	echo '<td data-title="' . esc_attr__( 'Total weight', NHGP_TEXTDOMAIN ) . '">'
		 . esc_html( $formatted ) .
		 '</td>';
	echo '</tr>';
}
add_action( 'woocommerce_cart_totals_before_order_total', 'nhgp_output_cart_weight_row_cart' );

/**
 * Output weight row in Checkout order review (classic checkout).
 */
function nhgp_output_cart_weight_row_checkout() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	$formatted = nhgp_format_cart_weight_for_display();
	if ( $formatted === '' ) {
		return;
	}

	echo '<tr class="nhgp-checkout-total-weight-row">';
	echo '<th>' . esc_html__( 'Total weight', NHGP_TEXTDOMAIN ) . '</th>';
	echo '<td data-title="' . esc_attr__( 'Total weight', NHGP_TEXTDOMAIN ) . '">'
		 . esc_html( $formatted ) .
		 '</td>';
	echo '</tr>';
}
add_action( 'woocommerce_review_order_before_order_total', 'nhgp_output_cart_weight_row_checkout' );

// WC-AJAX endpoint for Woo Blocks JS: returns authoritative cart weight (custom-cut aware)
add_action( 'wc_ajax_nhgp_get_cart_weight', 'nhgp_ajax_get_cart_weight' );
add_action( 'wc_ajax_nopriv_nhgp_get_cart_weight', 'nhgp_ajax_get_cart_weight' );

function nhgp_ajax_get_cart_weight() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json_success( [ 'weight' => 0, 'unit' => get_option( 'woocommerce_weight_unit', 'kg' ) ] );
	}

	$unit = get_option( 'woocommerce_weight_unit', 'kg' );
	$w    = function_exists( 'nhgp_get_cart_total_weight' ) ? (float) nhgp_get_cart_total_weight() : (float) WC()->cart->get_cart_contents_weight();

	wp_send_json_success( [
		'weight' => $w,
		'unit'   => $unit,
	] );
}
