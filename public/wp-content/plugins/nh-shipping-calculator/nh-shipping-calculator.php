<?php
/**
 * Plugin Name: Universal Shipping Calculator (Heavy + Custom Cutting)
 * Description: One Flat rate in the zone. Tiered heavy override (3 levels) + Custom Cutting rules that map width/height to shipping classes (Small/Medium/Large…). Label shows "(Heavy Lx)" or "(Class)" accordingly.
 * Author: Daiva Reinike
 * Version: 6.0.0
 * Text Domain: nh-heavy-parcel
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'NHGP_VERSION', '6.0.0' );
define( 'NHGP_TEXTDOMAIN', 'nh-heavy-parcel' );
define( 'NHGP_DIR', plugin_dir_path( __FILE__ ) );
define( 'NHGP_URL', plugin_dir_url( __FILE__ ) );

// ---- Includes
require_once NHGP_DIR . 'includes/class-nhgp-defaults.php';
require_once NHGP_DIR . 'includes/class-nhgp-admin.php';
require_once NHGP_DIR . 'includes/class-nhgp-session.php';
require_once NHGP_DIR . 'includes/class-nhgp-custom-cut.php';
require_once NHGP_DIR . 'includes/class-nhgp-overrides.php';
require_once NHGP_DIR . 'includes/cart-bridge.php';

// ---- Boot
add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( NHGP_TEXTDOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
} );

NHGP_Admin::init();
NHGP_Session::init();
NHGP_Overrides::init();
