<?php
/**
 * Plugin Name: NH Important Notes for WooCommerce
 * Description: Reusable “Important Notes” entries that can be attached to WooCommerce products and rendered after description.
 * Version: 1.0.0
 * Author: Daiva Reinike
 * Text Domain: nh-important-notes
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('NH_IN_PLUGIN_VERSION', '1.0.0');
define('NH_IN_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('NH_IN_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once NH_IN_PLUGIN_PATH . 'includes/class-nh-important-notes.php';

add_action('plugins_loaded', function () {
	// Load translations from /languages
	load_plugin_textdomain(
		'nh-important-notes',
		false,
		dirname(plugin_basename(__FILE__)) . '/languages'
	);

	\NHIN\Plugin::instance();
});
