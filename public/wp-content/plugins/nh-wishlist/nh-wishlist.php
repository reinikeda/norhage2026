<?php
/**
 * Plugin Name: Norhage Wishlist
 * Description: Wishlists that keep the selected variation and custom-cut dimensions. Guests are stored with a cookie. Lists can be added to the basket, saved as PDF, or sent as a quote to customer service.
 * Author: Norhage
 * Version: 1.0.2
 * Requires Plugins: woocommerce
 * Text Domain: nh-wishlist
 * Domain Path: /languages
 * License: GPLv2 or later
 *
 * @package nh-wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NH_WL_VERSION', '1.0.2' );
define( 'NH_WL_FILE', __FILE__ );
define( 'NH_WL_DIR', plugin_dir_path( __FILE__ ) );
define( 'NH_WL_URL', plugin_dir_url( __FILE__ ) );

require_once NH_WL_DIR . 'includes/lists.php';
require_once NH_WL_DIR . 'includes/pdf.php';
require_once NH_WL_DIR . 'includes/email-html.php';
require_once NH_WL_DIR . 'includes/class-nh-wishlist-store.php';

/**
 * @return void
 */
function nh_wl_register_rewrites() {
	add_rewrite_rule( '^wishlist/?$', 'index.php?nh_wishlist=1', 'top' );
	add_rewrite_endpoint( 'wishlist', EP_ROOT | EP_PAGES );
}

register_activation_hook(
	NH_WL_FILE,
	static function () {
		NH_WL_Store::install();
		nh_wl_register_rewrites();
		flush_rewrite_rules();
		update_option( 'nh_wl_rewrite_version', NH_WL_VERSION );
	}
);

register_deactivation_hook(
	NH_WL_FILE,
	static function () {
		flush_rewrite_rules();
	}
);

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NH_WL_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'nh-wishlist', false, dirname( plugin_basename( NH_WL_FILE ) ) . '/languages' );
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Norhage Wishlist needs WooCommerce.', 'nh-wishlist' ) . '</p></div>';
				}
			);
			return;
		}
		require_once NH_WL_DIR . 'includes/class-nh-wishlist-cart.php';
		require_once NH_WL_DIR . 'includes/front.php';
		NH_WL_Plugin::instance();
	}
);

/**
 * Hooks for the wishlist.
 */
final class NH_WL_Plugin {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'rewrites' ), 5 );
		add_action( 'init', array( $this, 'merge_guest' ), 20 );
		add_action( 'wp_login', array( $this, 'merge_on_login' ), 10, 2 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'public_page' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'document_title_parts', array( $this, 'document_title' ) );
		add_action( 'wp_head', array( $this, 'robots' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 30 );
		add_action( 'wp_footer', array( $this, 'popover' ), 5 );
		add_action( 'woocommerce_before_shop_loop_item', array( $this, 'loop_heart' ), 6 );
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'single_heart' ), 1 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu' ) );
		add_action( 'woocommerce_account_wishlist_endpoint', array( $this, 'account_endpoint' ) );
		add_filter( 'woocommerce_endpoint_wishlist_title', array( $this, 'account_title' ) );
		NH_WL_Actions::init();
	}

	public function rewrites() {
		nh_wl_register_rewrites();
		if ( get_option( 'nh_wl_rewrite_version' ) !== NH_WL_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'nh_wl_rewrite_version', NH_WL_VERSION );
		}
	}

	public function merge_guest() {
		if ( is_user_logged_in() ) {
			NH_WL_Store::merge_guest_into_user( get_current_user_id() );
		}
	}

	/**
	 * @param string  $user_login Login.
	 * @param WP_User $user User.
	 * @return void
	 */
	public function merge_on_login( $user_login, $user ) {
		unset( $user_login );
		if ( $user instanceof WP_User ) {
			NH_WL_Store::merge_guest_into_user( $user->ID );
		}
	}

	/**
	 * @param array<int,string> $vars Vars.
	 * @return array<int,string>
	 */
	public function query_vars( $vars ) {
		$vars[] = 'nh_wishlist';
		return $vars;
	}

	public function public_page() {
		if ( ! nh_wl_is_public_page() ) {
			return;
		}
		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->is_404     = false;
			$wp_query->is_page    = true;
			$wp_query->is_singular = true;
		}
		status_header( 200 );
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate' );
		header( 'Vary: Cookie' );
		get_header();
		echo '<main id="primary" class="site-main nh-wl-main">';
		nh_wl_render_wishlist( 'page' );
		echo '</main>';
		get_footer();
		exit;
	}

	/**
	 * @param array<int,string> $classes Classes.
	 * @return array<int,string>
	 */
	public function body_class( $classes ) {
		if ( nh_wl_is_public_page() ) {
			$classes[] = 'nh-wishlist-page';
		}
		return $classes;
	}

	/**
	 * @param array<string,string> $parts Title parts.
	 * @return array<string,string>
	 */
	public function document_title( $parts ) {
		if ( nh_wl_is_public_page() ) {
			$parts['title'] = __( 'Wishlist', 'nh-wishlist' );
		}
		return $parts;
	}

	public function robots() {
		if ( nh_wl_is_public_page() || ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'wishlist' ) ) ) {
			echo "<meta name=\"robots\" content=\"noindex, nofollow\" />\n";
		}
	}

	public function assets() {
		if ( is_admin() ) {
			return;
		}
		wp_enqueue_style( 'nh-wishlist', NH_WL_URL . 'assets/css/wishlist.css', array(), NH_WL_VERSION );
		wp_enqueue_script( 'nh-wishlist', NH_WL_URL . 'assets/js/wishlist.js', array(), NH_WL_VERSION, true );
		wp_localize_script( 'nh-wishlist', 'NH_WL', nh_wl_script_data() );
	}

	public function popover() {
		echo '<div id="nh-wl-popover" class="nh-wl-popover" hidden>';
		echo '<div class="nh-wl-popover__card" role="dialog" aria-modal="true" aria-labelledby="nh-wl-popover-title">';
		echo '<h2 id="nh-wl-popover-title">' . esc_html__( 'Choose a wishlist', 'nh-wishlist' ) . '</h2>';
		echo '<div class="nh-wl-popover__lists"></div>';
		echo '<form class="nh-wl-popover__create">';
		echo '<label>' . esc_html__( 'List name', 'nh-wishlist' ) . ' <input type="text" name="list_name" maxlength="80" autocomplete="off"></label>';
		echo '<button type="submit">' . esc_html__( 'Create', 'nh-wishlist' ) . '</button>';
		echo '</form>';
		echo '<p class="nh-wl-popover__notice" role="status"></p>';
		echo '<button type="button" class="nh-wl-popover__close">' . esc_html__( 'Close', 'nh-wishlist' ) . '</button>';
		echo '</div></div>';
	}

	public function loop_heart() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		echo nh_wl_heart_html( $product, 'loop' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function single_heart() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$queried = (int) get_queried_object_id();
		if ( $queried && (int) $product->get_id() !== $queried ) {
			return;
		}
		echo '<div class="nh-wl-single">' . nh_wl_heart_html( $product, 'single' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param array<string,string> $items Menu items.
	 * @return array<string,string>
	 */
	public function account_menu( $items ) {
		$updated = array();
		$added   = false;
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$updated['wishlist'] = __( 'Wishlist', 'nh-wishlist' );
				$added               = true;
			}
			$updated[ $key ] = $label;
		}
		if ( ! $added ) {
			$updated['wishlist'] = __( 'Wishlist', 'nh-wishlist' );
		}
		return $updated;
	}

	public function account_endpoint() {
		echo '<div class="nh-wl-account">';
		nh_wl_render_wishlist( 'account' );
		echo '</div>';
	}

	/**
	 * @return string
	 */
	public function account_title() {
		return __( 'Wishlist', 'nh-wishlist' );
	}
}
