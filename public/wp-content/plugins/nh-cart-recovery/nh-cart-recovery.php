<?php
/**
 * Plugin Name: Norhage Cart Recovery
 * Description: Abandoned cart and unfinished Svea checkout emails via wp_mail (WP Mail SMTP / Brevo). Captures the email from the Svea iframe, which generic recovery plugins miss.
 * Author: Daiva Reinike
 * Version: 1.0.0
 * Requires Plugins: woocommerce
 * Text Domain: nh-cart-recovery
 * Domain Path: /languages
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NH_CR_VERSION', '1.0.0' );
define( 'NH_CR_FILE', __FILE__ );
define( 'NH_CR_DIR', plugin_dir_path( __FILE__ ) );
define( 'NH_CR_URL', plugin_dir_url( __FILE__ ) );
define( 'NH_CR_TD', 'nh-cart-recovery' );

require_once NH_CR_DIR . 'includes/class-nh-cr-copy.php';
require_once NH_CR_DIR . 'includes/class-nh-cr-store.php';
require_once NH_CR_DIR . 'includes/class-nh-cr-tracker.php';
require_once NH_CR_DIR . 'includes/class-nh-cr-mailer.php';
require_once NH_CR_DIR . 'includes/class-nh-cr-cron.php';
require_once NH_CR_DIR . 'includes/class-nh-cr-admin.php';

register_activation_hook(
	NH_CR_FILE,
	static function () {
		NH_CR_Store::install();
		NH_CR_Cron::schedule();
	}
);

register_deactivation_hook(
	NH_CR_FILE,
	static function () {
		NH_CR_Cron::unschedule();
	}
);

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NH_CR_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( NH_CR_TD, false, dirname( plugin_basename( NH_CR_FILE ) ) . '/languages' );
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		NH_CR_Store::maybe_upgrade();
		NH_CR_Tracker::init();
		NH_CR_Mailer::init();
		NH_CR_Cron::init();
		NH_CR_Admin::init();
	}
);
