<?php
/**
 * Plugin Name: Norhage Side Cart
 * Description: Mobile-first WooCommerce side cart with a postcode shipping calculator. Replaces the header basket link and opens after add to cart. No floating cart icon.
 * Author: Daiva Reinike
 * Version: 1.0.1
 * Requires Plugins: woocommerce
 * Text Domain: nh-side-cart
 * Domain Path: /languages
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NH_SC_VERSION', '1.0.1' );
define( 'NH_SC_FILE', __FILE__ );
define( 'NH_SC_DIR', plugin_dir_path( __FILE__ ) );
define( 'NH_SC_URL', plugin_dir_url( __FILE__ ) );
define( 'NH_SC_TD', 'nh-side-cart' );

require_once NH_SC_DIR . 'includes/class-nh-side-cart-render.php';
require_once NH_SC_DIR . 'includes/class-nh-side-cart-ajax.php';
require_once NH_SC_DIR . 'includes/class-nh-side-cart.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NH_SC_FILE, true );
		}
	}
);

/**
 * Load plugin translations from this plugin folder.
 * WP_LANG_DIR copies (Loco etc.) can be older and miss plural strings.
 */
function nh_sc_load_textdomain() {
	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
	$candidates = array( $locale );
	if ( strpos( $locale, '_' ) !== false ) {
		$candidates[] = strtok( $locale, '_' );
	}

	foreach ( $candidates as $tag ) {
		$mofile = NH_SC_DIR . 'languages/nh-side-cart-' . $tag . '.mo';
		if ( ! is_readable( $mofile ) ) {
			continue;
		}
		unload_textdomain( NH_SC_TD, true );
		load_textdomain( NH_SC_TD, $mofile );
		return;
	}

	load_plugin_textdomain(
		NH_SC_TD,
		false,
		dirname( plugin_basename( NH_SC_FILE ) ) . '/languages'
	);
}

add_action( 'plugins_loaded', 'nh_sc_load_textdomain', 0 );
add_action( 'init', 'nh_sc_load_textdomain', 0 );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		NH_Side_Cart::instance();
	}
);
