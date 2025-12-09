<?php
/**
 * Plugin Name: Universal Shipping Calculator (Heavy + Custom Cutting)
 * Description: One Flat rate in the zone. Tiered heavy override (5 levels) + Custom Cutting rules that map width/height to size shipping classes (XS–XXXL). Label shows "(Heavy Lx)" or "(Class)" accordingly.
 * Author: Daiva Reinike
 * Version: 7.3.3
 * Text Domain: nh-heavy-parcel
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */
define( 'NHGP_VERSION',    '7.3.3' );
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
NHGP_Admin::init();
NHGP_Session::init();
NHGP_Overrides::init();

/* -------------------------------------------------------------------------
 * Ensure Norhage size-based shipping classes exist
 * ---------------------------------------------------------------------- */
add_action( 'init', 'nhgp_register_size_shipping_classes' );

function nhgp_register_size_shipping_classes() {
	// Make sure WooCommerce + taxonomy are available.
	if ( ! function_exists( 'wc_get_page_id' ) || ! taxonomy_exists( 'product_shipping_class' ) ) {
		return;
	}

	// slug => Name (you can translate/change the Name later; keep slug stable!).
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
 */
function nhgp_get_cart_total_weight() : float {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0.0;
	}

	return (float) WC()->cart->get_cart_contents_weight();
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

	// Simple, clean formatting:
	// - 2 decimals
	// - comma as decimal separator
	// - no thousands separator
	// Example: 12.5 -> "12,50 kg".
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
