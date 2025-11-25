<?php
/**
 * Plugin Name: Universal Shipping Calculator (Heavy + Custom Cutting)
 * Description: One Flat rate in the zone. Tiered heavy override (5 levels) + Custom Cutting rules that map width/height to size shipping classes (XS–XXXL). Label shows "(Heavy Lx)" or "(Class)" accordingly.
 * Author: Daiva Reinike
 * Version: 7.0.0
 * Text Domain: nh-heavy-parcel
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'NHGP_VERSION', '7.0.0' );
define( 'NHGP_TEXTDOMAIN', 'nh-heavy-parcel' );
define( 'NHGP_DIR', plugin_dir_path( __FILE__ ) );
define( 'NHGP_URL', plugin_dir_url( __FILE__ ) );

// ---- Includes
require_once NHGP_DIR . 'includes/class-nhgp-defaults.php';
require_once NHGP_DIR . 'includes/class-nhgp-admin.php';
require_once NHGP_DIR . 'includes/class-nhgp-session.php';
require_once NHGP_DIR . 'includes/class-nhgp-custom-cut.php'; // make sure filename matches exactly
require_once NHGP_DIR . 'includes/class-nhgp-overrides.php';
require_once NHGP_DIR . 'includes/cart-bridge.php';

// ---- Boot
add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( NHGP_TEXTDOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
} );

NHGP_Admin::init();
NHGP_Session::init();
NHGP_Overrides::init();

/**
 * Ensure Norhage size-based shipping classes exist.
 * Lives inside the Universal Shipping Calculator plugin.
 */
add_action( 'init', 'nhgp_register_size_shipping_classes' );

function nhgp_register_size_shipping_classes() {
	// Make sure WooCommerce + taxonomy are available
	if ( ! function_exists( 'wc_get_page_id' ) || ! taxonomy_exists( 'product_shipping_class' ) ) {
		return;
	}

	// slug => Name (you can translate/change the Name later; keep slug stable!)
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
