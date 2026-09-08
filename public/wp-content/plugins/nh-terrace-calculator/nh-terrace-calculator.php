<?php
/**
 * Plugin Name: Norhage Terrace Roof Calculator
 * Description: Product-page terrace roof configurator. Builds a live-priced kit from WooCommerce SKUs (sheets, profiles, tapes, screws) and adds every line to the cart, including custom-cut sizes.
 * Author: Daiva Reinike
 * Version: 1.0.0
 * Requires Plugins: woocommerce
 * Text Domain: nh-terrace-calculator
 * Domain Path: /languages
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NH_TC_VERSION', '1.0.0' );
define( 'NH_TC_FILE', __FILE__ );
define( 'NH_TC_DIR', plugin_dir_path( __FILE__ ) );
define( 'NH_TC_URL', plugin_dir_url( __FILE__ ) );
define( 'NH_TC_TD', 'nh-terrace-calculator' );

require_once NH_TC_DIR . 'includes/class-nh-tc-defaults.php';
require_once NH_TC_DIR . 'includes/class-nh-tc-engine.php';
require_once NH_TC_DIR . 'includes/class-nh-tc-catalog.php';
require_once NH_TC_DIR . 'includes/class-nh-tc-ajax.php';
require_once NH_TC_DIR . 'includes/class-nh-tc-admin.php';
require_once NH_TC_DIR . 'includes/class-nh-tc-render.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NH_TC_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain(
			NH_TC_TD,
			false,
			dirname( plugin_basename( NH_TC_FILE ) ) . '/languages'
		);

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		NH_TC_Admin::init();
		NH_TC_Ajax::init();
		NH_TC_Render::init();
	}
);
