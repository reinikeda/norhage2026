<?php
/**
 * Side cart bootstrap: assets, drawer, fragments, header intercept, XootiX hide.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NH_Side_Cart {

	const SESSION_OPEN = 'nh_sc_should_open';
	const NONCE        = 'nh-side-cart';

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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 30 );
		add_action( 'wp_footer', array( $this, 'render_drawer' ), 5 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'fragments' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'flag_open_after_add' ), 30 );
		add_action( 'admin_notices', array( $this, 'xootix_notice' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'hide_xootix' ), 999 );

		NH_Side_Cart_Ajax::init();
	}

	/**
	 * Whether the current request should show the side cart UI.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		return (bool) apply_filters( 'nh_side_cart_enabled', true );
	}

	/**
	 * Skip auto-open / header intercept on full cart (already the basket page).
	 *
	 * @return bool
	 */
	public static function is_full_cart_page() {
		return function_exists( 'is_cart' ) && is_cart();
	}

	/**
	 * @return array<string, string>
	 */
	public static function shipping_countries() {
		if ( function_exists( 'nh_cart_shipping_countries' ) ) {
			return nh_cart_shipping_countries();
		}
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return array();
		}
		$countries = WC()->countries->get_shipping_countries();
		return is_array( $countries ) ? $countries : array();
	}

	/**
	 * @return string
	 */
	public static function default_shipping_country() {
		if ( function_exists( 'nh_cart_default_shipping_country' ) ) {
			return nh_cart_default_shipping_country();
		}

		$countries = self::shipping_countries();
		$current   = ( function_exists( 'WC' ) && WC()->customer ) ? (string) WC()->customer->get_shipping_country() : '';

		if ( $current !== '' && isset( $countries[ $current ] ) ) {
			return $current;
		}

		$base = function_exists( 'WC' ) && WC()->countries ? (string) WC()->countries->get_base_country() : '';
		if ( $base !== '' && isset( $countries[ $base ] ) ) {
			return $base;
		}

		$keys = array_keys( $countries );
		return isset( $keys[0] ) ? (string) $keys[0] : '';
	}

	public function enqueue() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$script_args = function_exists( 'norhage_script_args' )
			? norhage_script_args()
			: array(
				'in_footer' => true,
				'strategy'  => 'defer',
			);

		wp_enqueue_style(
			'nh-side-cart',
			NH_SC_URL . 'assets/css/side-cart.css',
			array(),
			self::asset_version( 'assets/css/side-cart.css' )
		);

		$deps = array( 'jquery' );
		if ( wp_script_is( 'wc-cart-fragments', 'registered' ) ) {
			wp_enqueue_script( 'wc-cart-fragments' );
			$deps[] = 'wc-cart-fragments';
		}

		wp_enqueue_script(
			'nh-side-cart',
			NH_SC_URL . 'assets/js/side-cart.js',
			$deps,
			self::asset_version( 'assets/js/side-cart.js' ),
			$script_args
		);

		$ajax_url = function_exists( 'WC_AJAX' ) && method_exists( 'WC_AJAX', 'get_endpoint' )
			? WC_AJAX::get_endpoint( '%%endpoint%%' )
			: add_query_arg( 'wc-ajax', '%%endpoint%%', home_url( '/' ) );

		wp_localize_script(
			'nh-side-cart',
			'nhSideCart',
			array(
				'ajaxUrl'    => $ajax_url,
				'nonce'      => wp_create_nonce( self::NONCE ),
				'openOnLoad' => $this->consume_open_flag(),
				'isCartPage' => self::is_full_cart_page(),
				'i18n'       => array(
					'updating' => __( 'Updating…', NH_SC_TD ),
					'error'    => __( 'Could not update the basket. Please try again.', NH_SC_TD ),
				),
			)
		);
	}

	public function render_drawer() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$count = WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
		include NH_SC_DIR . 'templates/drawer.php';
	}

	/**
	 * @param array $fragments Fragments.
	 * @return array
	 */
	public function fragments( $fragments ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $fragments;
		}

		$count = (int) WC()->cart->get_cart_contents_count();

		$fragments['#nh-sc-body']          = NH_Side_Cart_Render::body_html();
		$fragments['span.nh-sc__count']    = NH_Side_Cart_Render::count_html( $count );
		$fragments['span.nh-cart-badge']   = isset( $fragments['span.nh-cart-badge'] )
			? $fragments['span.nh-cart-badge']
			: '<span class="nh-cart-badge" aria-hidden="true" data-count="' . esc_attr( (string) $count ) . '">' . esc_html( (string) $count ) . '</span>';

		return $fragments;
	}

	/**
	 * After a non-AJAX add-to-cart (custom-cut form POST, etc.), open on the next page.
	 */
	public function flag_open_after_add() {
		if ( wp_doing_ajax() ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		WC()->session->set( self::SESSION_OPEN, '1' );
	}

	/**
	 * @return bool
	 */
	private function consume_open_flag() {
		if ( self::is_full_cart_page() ) {
			return false;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return false;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return false;
		}
		$flag = (string) WC()->session->get( self::SESSION_OPEN );
		if ( '1' !== $flag ) {
			return false;
		}
		WC()->session->set( self::SESSION_OPEN, null );
		return true;
	}

	public function hide_xootix() {
		global $wp_styles, $wp_scripts;

		foreach ( array( $wp_styles, $wp_scripts ) as $list ) {
			if ( ! $list || empty( $list->registered ) ) {
				continue;
			}
			foreach ( array_keys( $list->registered ) as $handle ) {
				if ( false === strpos( $handle, 'xoo-wsc' ) && false === strpos( $handle, 'side-cart-woocommerce' ) ) {
					continue;
				}
				if ( $list === $wp_styles ) {
					wp_dequeue_style( $handle );
				} else {
					wp_dequeue_script( $handle );
				}
			}
		}
	}

	public function xootix_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$xootix = array(
			'side-cart-woocommerce/xoo-wsc-main.php',
			'side-cart-woocommerce/side-cart-woocommerce.php',
			'woocommerce-side-cart/xoo-wsc-main.php',
		);

		$active = false;
		foreach ( $xootix as $file ) {
			if ( is_plugin_active( $file ) ) {
				$active = true;
				break;
			}
		}
		if ( ! $active ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Norhage Side Cart is active. Deactivate Side Cart WooCommerce (XootiX) so customers only see one basket drawer.', NH_SC_TD );
		echo '</p></div>';
	}

	/**
	 * @param string $relative Path from plugin root.
	 * @return string
	 */
	private static function asset_version( $relative ) {
		$path = NH_SC_DIR . $relative;
		return file_exists( $path ) ? (string) filemtime( $path ) : NH_SC_VERSION;
	}
}
