<?php
/**
 * Snapshot the Woo cart and attach an email when Svea, Kustom, or checkout reveals one.
 *
 * @package nh-cart-recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_CR_Tracker {

	public static function init() {
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'snapshot' ), 40 );
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'snapshot' ), 40 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( __CLASS__, 'snapshot' ), 40 );
		add_action( 'woocommerce_cart_emptied', array( __CLASS__, 'on_cart_emptied' ), 40 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_order_processed' ), 20, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'on_store_api_order' ), 20 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_paid' ), 20 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_paid' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_paid' ), 20 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'on_cancelled' ), 30, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_restore' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_unsubscribe' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 40 );
		add_action( 'wc_ajax_nh_cr_sync', array( __CLASS__, 'ajax_sync' ) );
		add_action( 'wp_ajax_nh_cr_sync', array( __CLASS__, 'ajax_sync' ) );
		add_action( 'wp_ajax_nopriv_nh_cr_sync', array( __CLASS__, 'ajax_sync' ) );
		foreach (
			array(
				'wp_ajax_kco_wc_iframe_shipping_address_change',
				'wp_ajax_nopriv_kco_wc_iframe_shipping_address_change',
				'wc_ajax_kco_wc_iframe_shipping_address_change',
			) as $hook
		) {
			add_action( $hook, array( __CLASS__, 'on_kustom_address_ajax' ), 1 );
		}
		add_action( 'woocommerce_sco_refresh_snippet_customer_updated', array( __CLASS__, 'on_woo_customer_identity' ), 20, 1 );
		add_action( 'woocommerce_sco_after_refresh_sco_snippet', array( __CLASS__, 'on_svea_snippet' ), 20, 1 );
		add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'on_update_order_review' ), 99 );
	}

	/**
	 * @return string
	 */
	public static function session_key() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return '';
		}
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		return (string) WC()->session->get_customer_id();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function current_items() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array();
		}
		$items = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$snap = nh_cr_snapshot_item( $cart_item );
			if ( $snap ) {
				$items[] = $snap;
			}
		}
		return $items;
	}

	public static function snapshot() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$session = self::session_key();
		if ( $session === '' ) {
			return;
		}

		$items = self::current_items();
		$row   = NH_CR_Store::get_open_by_session( $session );
		if ( ! $items ) {
			if ( $row && in_array( $row->status, array( 'open', 'sent' ), true ) && (int) $row->order_id === 0 ) {
				NH_CR_Store::update( (int) $row->id, array( 'status' => 'skipped', 'cart' => '[]', 'cart_hash' => '' ) );
			}
			return;
		}

		$email = '';
		$first = '';
		$last  = '';
		if ( $row ) {
			$email = (string) $row->email;
			$first = (string) $row->first_name;
			$last  = (string) $row->last_name;
		}
		$from_customer = self::customer_identity();
		if ( $from_customer['email'] !== '' ) {
			$email = $from_customer['email'];
		}
		if ( $from_customer['first_name'] !== '' ) {
			$first = $from_customer['first_name'];
		}
		if ( $from_customer['last_name'] !== '' ) {
			$last = $from_customer['last_name'];
		}

		$data = array(
			'session_key' => $session,
			'user_id'     => get_current_user_id(),
			'email'       => $email,
			'first_name'  => $first,
			'last_name'   => $last,
			'cart'        => wp_json_encode( $items ),
			'cart_hash'   => nh_cr_cart_hash( $items ),
			'type'        => 'cart',
		);

		if ( $row && in_array( $row->status, array( 'open', 'sent' ), true ) ) {
			$sent = NH_CR_Store::emails_sent_count( $row );
			$max  = (int) nh_cr_get_settings()['max_emails'];
			if ( $sent >= $max ) {
				$insert           = array_merge( NH_CR_Store::blank_row(), $data );
				$insert['status'] = 'open';
				NH_CR_Store::insert( $insert );
				return;
			}
			if ( $row->status === 'open' ) {
				$data['status'] = 'open';
			}
			if ( (int) $row->order_id > 0 ) {
				unset( $data['type'] );
			}
			NH_CR_Store::update( (int) $row->id, $data );
			return;
		}

		$insert           = array_merge( NH_CR_Store::blank_row(), $data );
		$insert['status'] = 'open';
		NH_CR_Store::insert( $insert );
	}

	/**
	 * @return array{email:string,first_name:string,last_name:string}
	 */
	public static function customer_identity() {
		$out = array(
			'email'      => '',
			'first_name' => '',
			'last_name'  => '',
		);
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$out['email']      = NH_CR_Store::normalize_email( $user->user_email );
			$out['first_name'] = sanitize_text_field( (string) $user->first_name );
			$out['last_name']  = sanitize_text_field( (string) $user->last_name );
		}
		if ( function_exists( 'WC' ) && WC()->customer ) {
			$c_email = NH_CR_Store::normalize_email( WC()->customer->get_billing_email() );
			if ( $c_email !== '' ) {
				$out['email'] = $c_email;
			}
			$fn = sanitize_text_field( (string) WC()->customer->get_billing_first_name() );
			$ln = sanitize_text_field( (string) WC()->customer->get_billing_last_name() );
			if ( $fn !== '' ) {
				$out['first_name'] = $fn;
			}
			if ( $ln !== '' ) {
				$out['last_name'] = $ln;
			}
		}
		return $out;
	}

	public static function on_cart_emptied() {
		$session = self::session_key();
		if ( $session === '' ) {
			return;
		}
		$row = NH_CR_Store::get_open_by_session( $session );
		if ( $row && in_array( $row->status, array( 'open', 'sent' ), true ) && (int) $row->order_id === 0 ) {
			NH_CR_Store::update( (int) $row->id, array( 'status' => 'skipped', 'cart' => '[]', 'cart_hash' => '' ) );
		}
	}

	/**
	 * @param int      $order_id Order id.
	 * @param array    $posted   Posted data.
	 * @param WC_Order $order    Order.
	 */
	public static function on_order_processed( $order_id, $posted = array(), $order = null ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$session = self::session_key();
		$row     = $session ? NH_CR_Store::get_open_by_session( $session ) : null;
		$email   = NH_CR_Store::normalize_email( $order->get_billing_email() );
		if ( $row ) {
			NH_CR_Store::update(
				(int) $row->id,
				array(
					'email'     => $email ? $email : $row->email,
					'order_id'  => (int) $order->get_id(),
					'type'      => 'checkout',
					'status'    => $order->is_paid() ? 'converted' : ( $row->status === 'sent' ? 'sent' : 'open' ),
				)
			);
		}
		if ( $order->is_paid() && $email ) {
			NH_CR_Store::mark_converted_for_email( $email, (int) $order->get_id() );
		}
	}

	/**
	 * @param WC_Order $order Order.
	 */
	public static function on_store_api_order( $order ) {
		if ( $order instanceof WC_Order ) {
			self::on_order_processed( $order->get_id(), array(), $order );
		}
	}

	/**
	 * @param int $order_id Order id.
	 */
	public static function on_paid( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$email = NH_CR_Store::normalize_email( $order->get_billing_email() );
		if ( $email ) {
			NH_CR_Store::mark_converted_for_email( $email, (int) $order->get_id() );
		}
	}

	/**
	 * Unpaid Svea/Woo cancel → queue a checkout recovery email.
	 *
	 * @param int           $order_id Order id.
	 * @param WC_Order|null $order    Order.
	 */
	public static function on_cancelled( $order_id, $order = null ) {
		$settings = nh_cr_get_settings();
		if ( empty( $settings['checkout_on_cancel'] ) ) {
			return;
		}
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( $order->is_paid() ) {
			return;
		}
		$email = NH_CR_Store::normalize_email( $order->get_billing_email() );
		if ( $email === '' ) {
			return;
		}
		if ( NH_CR_Store::email_unsubscribed( $email ) ) {
			return;
		}
		if ( NH_CR_Store::email_has_recent_paid_order( $email, 48 ) ) {
			return;
		}
		if ( ! nh_cr_should_email_cancelled_checkout( 'cancelled', $order->get_payment_method(), false ) ) {
			return;
		}

		$session = self::session_key();
		$row     = $session ? NH_CR_Store::get_open_by_session( $session ) : null;
		$items   = array();
		if ( $row && ! empty( $row->cart ) && ! empty( $row->cart_hash ) ) {
			$existing = json_decode( (string) $row->cart, true );
			if ( is_array( $existing ) && $existing ) {
				$items = $existing;
			}
		}
		if ( ! $items ) {
			foreach ( $order->get_items() as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				$product   = $item->get_product();
				$image_url = '';
				if ( $product && function_exists( 'wp_get_attachment_image_url' ) ) {
					$image_id  = (int) $product->get_image_id();
					$image_url = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';
				}
				$items[] = array(
					'product_id'     => (int) $item->get_product_id(),
					'variation_id'   => (int) $item->get_variation_id(),
					'quantity'       => (float) $item->get_quantity(),
					'variation'      => array(),
					'cart_item_data' => array(),
					'name'           => $item->get_name(),
					'line_total'     => (float) $item->get_total() + (float) $item->get_total_tax(),
					'image_url'      => $image_url,
				);
			}
		}
		if ( ! $items ) {
			return;
		}

		$already = $row ? NH_CR_Store::emails_sent_count( $row ) : 0;
		$data    = array(
			'email'      => $email,
			'first_name' => sanitize_text_field( (string) $order->get_billing_first_name() ),
			'last_name'  => sanitize_text_field( (string) $order->get_billing_last_name() ),
			'cart'       => wp_json_encode( $items ),
			'cart_hash'  => nh_cr_cart_hash( $items ),
			'order_id'   => (int) $order->get_id(),
			'type'       => 'checkout',
			'user_id'    => (int) $order->get_customer_id(),
		);

		if ( $row ) {
			if ( $already < 1 ) {
				$data['status'] = 'open';
			}
			NH_CR_Store::update( (int) $row->id, $data );
			if ( $already < 1 ) {
				NH_CR_Mailer::send_row( NH_CR_Store::get( (int) $row->id ), 1 );
			}
			return;
		}

		$insert           = array_merge( NH_CR_Store::blank_row(), $data );
		$insert['status'] = 'open';
		$id               = NH_CR_Store::insert( $insert );
		NH_CR_Mailer::send_row( NH_CR_Store::get( $id ), 1 );
	}

	public static function enqueue() {
		if ( ! function_exists( 'is_checkout' ) ) {
			return;
		}
		$on = ( function_exists( 'is_cart' ) && is_cart() ) || is_checkout();
		if ( ! $on ) {
			return;
		}
		$settings = nh_cr_get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		wp_enqueue_script(
			'nh-cart-recovery',
			NH_CR_URL . 'assets/js/tracker.js',
			array(),
			NH_CR_VERSION,
			true
		);
		$ajax = admin_url( 'admin-ajax.php' );
		if ( class_exists( 'WC_AJAX' ) && method_exists( 'WC_AJAX', 'get_endpoint' ) ) {
			$ajax = WC_AJAX::get_endpoint( 'nh_cr_sync' );
		}
		wp_localize_script(
			'nh-cart-recovery',
			'nhCartRecovery',
			array(
				'nonce' => wp_create_nonce( 'nh-cr-sync' ),
				'ajax'  => $ajax,
			)
		);
	}

	public static function ajax_sync() {
		check_ajax_referer( 'nh-cr-sync', 'security' );
		$email = NH_CR_Store::normalize_email( isset( $_POST['email'] ) ? wp_unslash( (string) $_POST['email'] ) : '' );
		$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['first_name'] ) ) : '';
		$last  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['last_name'] ) ) : '';
		self::apply_identity( $email, $first, $last );
		wp_send_json_success();
	}

	/**
	 * Kustom/Klarna Checkout plugin posts iframe address (including DE, where JS "change" does not fire).
	 */
	public static function on_kustom_address_ajax() {
		$raw = array();
		if ( isset( $_REQUEST['data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = wp_unslash( $_REQUEST['data'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		$ident = nh_cr_identity_from_payload( $raw );
		if ( $ident['email'] === '' && $ident['first_name'] === '' && $ident['last_name'] === '' ) {
			return;
		}
		self::apply_identity(
			NH_CR_Store::normalize_email( $ident['email'] ),
			sanitize_text_field( $ident['first_name'] ),
			sanitize_text_field( $ident['last_name'] )
		);
	}

	/**
	 * @param string $email Email.
	 * @param string $first First name.
	 * @param string $last  Last name.
	 */
	public static function apply_identity( $email, $first, $last ) {
		$email = NH_CR_Store::normalize_email( $email );
		$first = sanitize_text_field( (string) $first );
		$last  = sanitize_text_field( (string) $last );
		if ( $email === '' && $first === '' && $last === '' ) {
			return;
		}

		if ( function_exists( 'WC' ) && WC()->customer && $email ) {
			WC()->customer->set_billing_email( $email );
			if ( $first ) {
				WC()->customer->set_billing_first_name( $first );
			}
			if ( $last ) {
				WC()->customer->set_billing_last_name( $last );
			}
		}

		// Never snapshot an empty cart here: wc-ajax without a loaded cart would
		// mark the open recovery row skipped and drop the email.
		if ( self::current_items() ) {
			self::snapshot();
		}

		$session = self::session_key();
		$row     = $session ? NH_CR_Store::get_open_by_session( $session ) : null;
		if ( $row && ( $email || $first || $last ) ) {
			$update = array();
			if ( $email ) {
				$update['email'] = $email;
			}
			if ( $first ) {
				$update['first_name'] = $first;
			}
			if ( $last ) {
				$update['last_name'] = $last;
			}
			if ( $update ) {
				NH_CR_Store::update( (int) $row->id, $update );
			}
		}
	}

	/**
	 * Svea plugin copies iframe identity onto WC_Customer after Continue / snippet refresh.
	 *
	 * @param mixed $customer WC_Customer.
	 */
	public static function on_woo_customer_identity( $customer ) {
		if ( ! is_object( $customer ) || ! method_exists( $customer, 'get_billing_email' ) ) {
			return;
		}
		self::apply_identity(
			(string) $customer->get_billing_email(),
			method_exists( $customer, 'get_billing_first_name' ) ? (string) $customer->get_billing_first_name() : '',
			method_exists( $customer, 'get_billing_last_name' ) ? (string) $customer->get_billing_last_name() : ''
		);
	}

	/**
	 * @param mixed $module Svea checkout module array.
	 */
	public static function on_svea_snippet( $module ) {
		$ident = nh_cr_identity_from_svea_module( $module );
		if ( $ident['email'] === '' && $ident['first_name'] === '' && $ident['last_name'] === '' ) {
			return;
		}
		self::apply_identity( $ident['email'], $ident['first_name'], $ident['last_name'] );
	}

	/**
	 * After Woo/Kustom/Svea update the customer during checkout review.
	 */
	public static function on_update_order_review() {
		if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
			return;
		}
		$email = (string) WC()->customer->get_billing_email();
		if ( $email === '' ) {
			return;
		}
		self::apply_identity(
			$email,
			(string) WC()->customer->get_billing_first_name(),
			(string) WC()->customer->get_billing_last_name()
		);
	}

	public static function maybe_unsubscribe() {
		if ( empty( $_GET['nh_cr_unsub'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$row = NH_CR_Store::get_by_token( sanitize_text_field( wp_unslash( (string) $_GET['nh_cr_unsub'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $row ) {
			return;
		}
		NH_CR_Store::unsubscribe_email( $row->email );
		wp_safe_redirect( add_query_arg( 'nh_cr_unsub_done', '1', wc_get_page_permalink( 'shop' ) ) );
		exit;
	}

	public static function maybe_restore() {
		if ( empty( $_GET['nh_cr'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$row = NH_CR_Store::get_by_token( sanitize_text_field( wp_unslash( (string) $_GET['nh_cr'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $row || $row->status === 'unsubscribed' ) {
			return;
		}
		$items = json_decode( (string) $row->cart, true );
		if ( ! is_array( $items ) || ! $items ) {
			return;
		}

		WC()->cart->empty_cart();
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'restore_item_data' ), 999 );
		foreach ( $items as $item ) {
			$GLOBALS['nh_cr_restore_item'] = isset( $item['cart_item_data'] ) && is_array( $item['cart_item_data'] ) ? $item['cart_item_data'] : array();
			$pid = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
			$qty = isset( $item['quantity'] ) ? (float) $item['quantity'] : 0;
			if ( ! $pid || $qty <= 0 ) {
				continue;
			}
			$vid  = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
			$vars = isset( $item['variation'] ) && is_array( $item['variation'] ) ? $item['variation'] : array();
			WC()->cart->add_to_cart( $pid, $qty, $vid, $vars, $GLOBALS['nh_cr_restore_item'] );
		}
		unset( $GLOBALS['nh_cr_restore_item'] );
		remove_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'restore_item_data' ), 999 );

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Keep custom-cut meta from the snapshot; theme filters would otherwise zero it.
	 *
	 * @param array<string, mixed> $cart_item_data Data.
	 * @return array<string, mixed>
	 */
	public static function restore_item_data( $cart_item_data ) {
		if ( empty( $GLOBALS['nh_cr_restore_item'] ) || ! is_array( $GLOBALS['nh_cr_restore_item'] ) ) {
			return $cart_item_data;
		}
		return array_merge( is_array( $cart_item_data ) ? $cart_item_data : array(), $GLOBALS['nh_cr_restore_item'] );
	}
}
