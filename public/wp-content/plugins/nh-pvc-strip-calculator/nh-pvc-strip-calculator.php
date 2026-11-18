<?php
/**
 * Plugin Name: Norhage PVC Strip Curtain Calculator
 * Description: Configure PVC strip curtains (Plastena-style rules) and add a complete kit to WooCommerce.
 * Version: 1.0.4
 * Requires Plugins: woocommerce
 * Text Domain: nh-pvc-strip-calculator
 * Domain Path: /languages
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NH_PSC_VERSION', '1.0.4' );
define( 'NH_PSC_FILE', __FILE__ );
define( 'NH_PSC_DIR', plugin_dir_path( __FILE__ ) );
define( 'NH_PSC_URL', plugin_dir_url( __FILE__ ) );
define( 'NH_PSC_TD', 'nh-pvc-strip-calculator' );

require_once NH_PSC_DIR . 'includes/class-nh-psc-defaults.php';
require_once NH_PSC_DIR . 'includes/class-nh-psc-engine.php';
require_once NH_PSC_DIR . 'includes/class-nh-psc-catalog.php';
require_once NH_PSC_DIR . 'includes/class-nh-psc-ajax.php';
require_once NH_PSC_DIR . 'includes/class-nh-psc-admin.php';
require_once NH_PSC_DIR . 'includes/class-nh-psc-render.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NH_PSC_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain(
			NH_PSC_TD,
			false,
			dirname( plugin_basename( NH_PSC_FILE ) ) . '/languages'
		);

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		NH_PSC_Admin::init();
		NH_PSC_Ajax::init();
		NH_PSC_Render::init();
	}
);
