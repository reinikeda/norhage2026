<?php
/**
 * Classic checkout UX: mobile-first layout, private/business details,
 * and field rules that work with SVEA, MakeCommerce, Kustom, PayPal, BASC.
 *
 * Checkout Blocks are not used. Those gateways need the shortcode checkout.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Standard checkout form (not thank-you / pay-for-order).
 */
function nh_is_classic_checkout_form() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return false;
	}
	if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		return false;
	}
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() ) {
		return false;
	}
	return true;
}

/**
 * Chosen payment method from this request, then the Woo session.
 *
 * @return string
 */
/**
 * Payment method posted on this request (not the Woo session).
 *
 * @return string
 */
function nh_checkout_posted_payment_method() {
	$raw = '';
	if ( isset( $_POST['payment_method'] ) && is_scalar( $_POST['payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = sanitize_text_field( wp_unslash( (string) $_POST['payment_method'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	} elseif ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form = array();
		parse_str( wp_unslash( $_POST['post_data'] ), $form ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $form['payment_method'] ) && is_scalar( $form['payment_method'] ) ) {
			$raw = sanitize_text_field( (string) $form['payment_method'] );
		}
	}
	if ( $raw === '' || $raw === 'nh_none' || $raw === 'undefined' || $raw === 'null' ) {
		return '';
	}
	return $raw;
}

function nh_checkout_chosen_payment_method() {
	if ( ! nh_checkout_is_payment_step() ) {
		return '';
	}
	$posted = nh_checkout_posted_payment_method();
	if ( $posted !== '' ) {
		return $posted;
	}
	if ( function_exists( 'WC' ) && WC()->session ) {
		$session = (string) WC()->session->get( 'chosen_payment_method' );
		if ( $session === 'nh_none' || $session === 'undefined' ) {
			return '';
		}
		return $session;
	}
	return '';
}

/**
 * Details first, then payment. Empty until the customer clicks Next.
 *
 * @return bool
 */
function nh_checkout_is_payment_step() {
	$step = '';
	if ( isset( $_POST['nh_checkout_step'] ) && is_scalar( $_POST['nh_checkout_step'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$step = sanitize_text_field( wp_unslash( (string) $_POST['nh_checkout_step'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	} elseif ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form = array();
		parse_str( wp_unslash( $_POST['post_data'] ), $form ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $form['nh_checkout_step'] ) && is_scalar( $form['nh_checkout_step'] ) ) {
			$step = sanitize_text_field( (string) $form['nh_checkout_step'] );
		}
	} elseif ( function_exists( 'WC' ) && WC()->session ) {
		$step = (string) WC()->session->get( 'nh_checkout_step' );
	}
	return 'payment' === $step;
}

/**
 * Load Svea/Kustom iframe only after the customer is on the payment step and picked that method.
 *
 * @return bool
 */
function nh_checkout_should_load_iframe() {
	return nh_checkout_is_payment_step() && nh_checkout_is_snippet_gateway();
}

/**
 * SVEA / Kustom / Klarna Checkout collect payment in an iframe after the Woo form.
 *
 * @param string $gateway_id Optional gateway id; request/session method if empty.
 */
function nh_checkout_is_snippet_gateway( $gateway_id = '' ) {
	if ( $gateway_id === '' ) {
		$gateway_id = nh_checkout_chosen_payment_method();
	}
	$id = strtolower( (string) $gateway_id );
	if ( $id === '' ) {
		return false;
	}
	if ( in_array( $id, array( 'kco', 'sco', 'kustom_checkout', 'svea_checkout', 'sveacheckout', 'klarna_checkout' ), true ) ) {
		return true;
	}
	return (bool) preg_match( '/svea.?checkout|sveacheckout|kustom_checkout|klarna_checkout/', $id );
}

/**
 * Payment iframes that collect or freeze address/totals outside the Woo form.
 *
 * @param string $gateway_id Optional gateway id.
 * @return bool
 */
function nh_checkout_is_iframe_gateway( $gateway_id = '' ) {
	if ( nh_checkout_is_snippet_gateway( $gateway_id ) ) {
		return true;
	}
	$id = strtolower( (string) ( $gateway_id !== '' ? $gateway_id : nh_checkout_chosen_payment_method() ) );
	if ( $id === '' ) {
		return false;
	}
	return (bool) preg_match( '/paypal|ppcp|ppec|paypal_express|paypalcp/', $id );
}

/**
 * Extra Woo fields snippet checkouts would otherwise print next to the iframe.
 *
 * @param array $fields Field names.
 * @return array
 */
function nh_checkout_snippet_ignored_fields( $fields ) {
	if ( ! is_array( $fields ) ) {
		$fields = array();
	}
	foreach ( array(
		'billing_customer_type',
		'billing_company_reg',
		'billing_contact_email',
		'billing_contact_phone',
		'billing_phone_code',
		'billing_contact_phone_code',
		'nh_section_person',
		'order_comments',
	) as $name ) {
		if ( ! in_array( $name, $fields, true ) ) {
			$fields[] = $name;
		}
	}
	return $fields;
}

function nh_checkout_ux_init() {
	add_filter( 'render_block', 'nh_checkout_render_classic_block', 5, 2 );
	add_filter( 'body_class', 'nh_checkout_ux_body_class' );
	add_action( 'wp_enqueue_scripts', 'nh_checkout_ux_assets', 100 );
	add_action( 'wp', 'nh_checkout_split_review_and_payment', 20 );
	add_action( 'wp', 'nh_checkout_prepare_steps', 5 );
	add_action( 'wp', 'nh_checkout_restore_identity_on_checkout', 6 );
	add_filter( 'woocommerce_available_payment_gateways', 'nh_checkout_no_default_gateway', 999 );
	add_filter( 'woocommerce_gateway_title', 'nh_checkout_translate_gateway_text', 20, 1 );
	add_filter( 'woocommerce_gateway_description', 'nh_checkout_translate_gateway_text', 20, 1 );
	add_filter( 'woocommerce_order_button_text', 'nh_checkout_translate_gateway_text', 20, 1 );
	add_filter( 'woocommerce_shipping_rate_label', 'nh_checkout_translate_gateway_text', 20, 1 );
	add_filter( 'woocommerce_shipping_package_name', 'nh_checkout_translate_shipping_package_name', 20, 3 );
	add_filter( 'wc_get_template', 'nh_checkout_force_woo_form_until_iframe', 1000, 2 );
	add_action( 'woocommerce_checkout_update_order_review', 'nh_checkout_sync_checkout_step', 0 );
	add_action( 'woocommerce_checkout_update_order_review', 'nh_checkout_sync_payment_method_session', 999 );
	add_action( 'wc_ajax_sco_change_payment_method', 'nh_checkout_keep_payment_step_on_snippet_ajax', 1 );
	add_action( 'wc_ajax_kco_wc_change_payment_method', 'nh_checkout_keep_payment_step_on_snippet_ajax', 1 );
	add_action( 'wc_ajax_sco_checkout_order', 'nh_checkout_svea_serialize_woo_order', 1 );
	add_action( 'wc_ajax_refresh_sco_snippet', 'nh_checkout_svea_lock_snippet_refresh', 0 );
	add_action( 'wc_ajax_nopriv_refresh_sco_snippet', 'nh_checkout_svea_lock_snippet_refresh', 0 );

	add_filter( 'woocommerce_default_address_fields', 'nh_checkout_default_address_fields', 20 );
	add_filter( 'woocommerce_get_country_locale', 'nh_checkout_country_locale', 20 );
	add_filter( 'woocommerce_billing_fields', 'nh_checkout_billing_fields', 20 );
	add_filter( 'woocommerce_checkout_fields', 'nh_checkout_fields', 999 );
	add_filter( 'woocommerce_form_field_args', 'nh_checkout_form_field_args', 99, 3 );
	add_filter( 'woocommerce_form_field_nh_section', 'nh_checkout_section_field', 10, 4 );
	add_filter( 'woocommerce_form_field_tel', 'nh_checkout_phone_field_html', 10, 4 );
	add_filter( 'woocommerce_checkout_get_value', 'nh_checkout_get_value', 10, 2 );
	add_filter( 'woocommerce_checkout_posted_data', 'nh_checkout_normalize_posted_phones', 20 );
	add_filter( 'woocommerce_checkout_posted_data', 'nh_checkout_posted_data_prefer_iframe', 30 );
	add_filter( 'woocommerce_sco_checkout_fields_mapping', 'nh_checkout_svea_fields_mapping' );

	add_action( 'woocommerce_after_checkout_validation', 'nh_checkout_validate_fields', 20, 2 );
	add_action( 'woocommerce_checkout_create_order', 'nh_checkout_save_order_meta', 20, 2 );
	add_action( 'woocommerce_checkout_update_customer', 'nh_checkout_save_customer_meta', 20, 2 );

	add_filter( 'woocommerce_order_formatted_billing_address', 'nh_checkout_formatted_billing_address', 20, 2 );
	add_filter( 'woocommerce_email_customer_details_fields', 'nh_checkout_email_contact_fields', 20, 3 );
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'nh_checkout_admin_billing_meta', 10, 1 );
	add_action( 'woocommerce_order_details_after_customer_details', 'nh_checkout_order_contact_details', 10, 1 );
	add_action( 'wpo_wcpdf_after_billing_address', 'nh_checkout_pdf_reg_number', 10, 2 );
	add_action( 'woocommerce_review_order_after_submit', 'nh_checkout_secure_note', 8 );
	add_action( 'woocommerce_checkout_after_terms_and_conditions', 'nh_checkout_terms_required_hint', 5 );
	add_action( 'wp_footer', 'nh_checkout_layout_lock_css', 1 );
	add_action( 'wp_footer', 'nh_checkout_shipping_index_boot_script', 1 );
	add_action( 'woocommerce_checkout_update_order_review', 'nh_checkout_sanitize_posted_shipping', 1 );
	add_action( 'woocommerce_checkout_update_order_review', 'nh_checkout_sync_review_address', 2 );
	add_action( 'woocommerce_sco_refresh_snippet_customer_updated', 'nh_checkout_reapply_posted_iframe_zip', 1, 2 );
	add_action( 'woocommerce_sco_refresh_snippet_customer_updated', 'nh_checkout_reapply_posted_woo_identity', 2, 2 );
	add_action( 'woocommerce_sco_refresh_snippet_customer_updated', 'nh_checkout_on_iframe_customer_updated', 5, 2 );
	add_filter( 'woocommerce_cart_ready_to_calc_shipping', 'nh_checkout_ready_to_calc_shipping', 999 );
	add_filter( 'woocommerce_no_shipping_available_html', 'nh_checkout_snippet_no_shipping_html' );
	add_filter( 'woocommerce_cart_no_shipping_available_html', 'nh_checkout_snippet_no_shipping_html' );
	add_action( 'wc_ajax_nh_snippet_apply_zip', 'nh_checkout_ajax_snippet_apply_zip' );
	add_filter( 'kco_ignored_checkout_fields', 'nh_checkout_snippet_ignored_fields' );
	add_filter( 'kco_wc_ignored_order_fields', 'nh_checkout_snippet_ignored_fields' );
	add_filter( 'kco_ignored_field_names', 'nh_checkout_snippet_ignored_fields' );
	add_filter( 'sco_ignored_checkout_fields', 'nh_checkout_snippet_ignored_fields' );
	add_filter( 'svea_checkout_ignored_fields', 'nh_checkout_snippet_ignored_fields' );
	add_filter( 'svea_wc_ignored_checkout_fields', 'nh_checkout_snippet_ignored_fields' );
	add_filter( 'woocommerce_svea_checkout_ignored_fields', 'nh_checkout_snippet_ignored_fields' );
	add_filter( 'woocommerce_ship_to_different_address_checked', '__return_false', 20 );
	add_filter( 'woocommerce_package_rates', 'nh_checkout_numeric_shipping_rate_costs', 999 );
	add_filter( 'woocommerce_sco_should_remove_default_fields', '__return_false' );
	add_filter( 'woocommerce_sco_show_change_payment_button', '__return_false' );
	add_filter( 'woocommerce_sco_needs_new_checkout', 'nh_checkout_svea_needs_new_for_identity', 10, 2 );
	add_filter( 'woocommerce_sco_create_order', 'nh_checkout_svea_create_order_identity' );
	add_filter( 'kco_wc_api_request_args', 'nh_checkout_kustom_api_prefill', 20, 1 );
	add_action( 'wp', 'nh_checkout_unhook_snippet_chrome', 30 );
}

/**
 * Keep Woo billing fields and payment radios. Svea/Kustom only supply the iframe.
 */
function nh_checkout_unhook_snippet_chrome() {
	if ( ! nh_is_classic_checkout_form() ) {
		return;
	}

	remove_action( 'kco_wc_after_order_review', 'kco_wc_add_extra_checkout_fields', 10 );
	remove_action( 'kco_wc_after_order_review', 'kco_wc_show_another_gateway_button', 20 );

	if ( class_exists( 'KCO_Templates' ) && method_exists( 'KCO_Templates', 'get_instance' ) ) {
		$templates = KCO_Templates::get_instance();
		if ( is_object( $templates ) ) {
			remove_action( 'kco_wc_before_snippet', array( $templates, 'add_wc_form' ), 10 );
			remove_action( 'wp_footer', array( $templates, 'check_that_kco_template_has_loaded' ) );
		}
	}

	if ( nh_checkout_should_load_iframe() ) {
		return;
	}

	if ( function_exists( 'svea_checkout' ) ) {
		$svea = svea_checkout();
		if ( is_object( $svea ) && isset( $svea->template_handler ) && is_object( $svea->template_handler ) ) {
			remove_action( 'woocommerce_checkout_before_order_review', array( $svea->template_handler, 'modify_checkout_page_hooks' ) );
			remove_action( 'woocommerce_checkout_before_order_review', array( $svea->template_handler, 'maybe_remove_checkout_fields' ) );
		}
	}
}

/**
 * Keep the first payment method unselected until the customer reaches the payment step and clicks one.
 *
 * @param array<string, WC_Payment_Gateway> $gateways Gateways.
 * @return array<string, WC_Payment_Gateway>
 */
function nh_checkout_no_default_gateway( $gateways ) {
	if ( ! is_array( $gateways ) ) {
		return $gateways;
	}
	if ( ! nh_is_classic_checkout_form() && ! ( defined( 'WOOCOMMERCE_CHECKOUT' ) && WOOCOMMERCE_CHECKOUT ) ) {
		return $gateways;
	}
	if ( nh_checkout_is_payment_step() && nh_checkout_chosen_payment_method() !== '' ) {
		return $gateways;
	}
	if ( nh_checkout_is_payment_step() ) {
		nh_checkout_lock_empty_payment_choice();
	}
	foreach ( $gateways as $gateway ) {
		if ( is_object( $gateway ) ) {
			$gateway->chosen = false;
		}
	}
	return $gateways;
}

/**
 * Do not let Svea/Kustom swap in their iframe-only templates before a method is chosen.
 *
 * @param string $template      Located template.
 * @param string $template_name Template name.
 * @return string
 */
function nh_checkout_force_woo_form_until_iframe( $template, $template_name ) {
	if ( 'checkout/form-checkout.php' !== $template_name ) {
		return $template;
	}
	if ( nh_checkout_should_load_iframe() ) {
		return $template;
	}
	$ours = get_stylesheet_directory() . '/woocommerce/checkout/form-checkout.php';
	return file_exists( $ours ) ? $ours : $template;
}

/**
 * Persist details vs payment step from the checkout form.
 *
 * @param string $post_data Serialized form.
 */
function nh_checkout_sync_checkout_step( $post_data ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	$step = 'details';
	if ( isset( $_POST['nh_checkout_step'] ) && is_scalar( $_POST['nh_checkout_step'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$step = sanitize_text_field( wp_unslash( (string) $_POST['nh_checkout_step'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	} elseif ( is_string( $post_data ) && $post_data !== '' ) {
		$form = array();
		parse_str( $post_data, $form );
		if ( ! empty( $form['nh_checkout_step'] ) && is_scalar( $form['nh_checkout_step'] ) ) {
			$step = sanitize_text_field( (string) $form['nh_checkout_step'] );
		}
	}

	$step = ( 'payment' === $step ) ? 'payment' : 'details';
	WC()->session->set( 'nh_checkout_step', $step );

	if ( 'details' === $step ) {
		WC()->session->set( 'chosen_payment_method', '' );
		return;
	}

	$method = nh_checkout_posted_payment_method();
	if ( $method !== '' ) {
		WC()->session->set( 'chosen_payment_method', $method );
		return;
	}

	nh_checkout_lock_empty_payment_choice();
}

/**
 * Persist BACS/PayPal (or nh_none) so Svea cannot treat an empty choice as “Svea is first”.
 */
function nh_checkout_sync_payment_method_session() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	if ( ! nh_checkout_is_payment_step() ) {
		return;
	}
	$method = nh_checkout_posted_payment_method();
	if ( $method !== '' ) {
		WC()->session->set( 'chosen_payment_method', $method );
		return;
	}
	nh_checkout_lock_empty_payment_choice();
}

/**
 * Svea is_svea() is true when chosen_payment_method is empty and Svea is the first gateway.
 *
 * @return void
 */
function nh_checkout_lock_empty_payment_choice() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	$current = (string) WC()->session->get( 'chosen_payment_method' );
	if ( $current === '' ) {
		WC()->session->set( 'chosen_payment_method', 'nh_none' );
	}
}

/**
 * Fresh checkout visit starts on the details step with no payment method selected.
 */
function nh_checkout_prepare_steps() {
	if ( ! nh_is_classic_checkout_form() || wp_doing_ajax() ) {
		return;
	}
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	if ( nh_checkout_is_payment_step() ) {
		return;
	}
	WC()->session->set( 'nh_checkout_step', 'details' );
	WC()->session->set( 'chosen_payment_method', '' );
}

/**
 * Svea/Kustom change-payment AJAX reloads checkout. Keep the payment step so
 * the iframe is allowed to render after that reload.
 */
function nh_checkout_keep_payment_step_on_snippet_ajax() {
	if ( function_exists( 'WC' ) && WC()->session ) {
		WC()->session->set( 'nh_checkout_step', 'payment' );
	}
}

/**
 * Svea checkout JS can fire sco_checkout_order more than once for one iframe
 * payment (stacked listeners). Point later requests at the Woo order the first
 * request already created so we do not leave extra pending-payment orders.
 */
function nh_checkout_svea_serialize_woo_order() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	$sco_id = (string) WC()->session->get( 'sco_order_id' );
	if ( $sco_id === '' ) {
		return;
	}

	$lock_key = nh_checkout_svea_place_lock_key( $sco_id );
	$held     = get_transient( $lock_key );

	if ( is_numeric( $held ) && absint( $held ) > 0 ) {
		WC()->session->set( 'order_awaiting_payment', absint( $held ) );
		return;
	}

	if ( $held === 'pending' ) {
		$deadline = time() + 12;
		while ( time() < $deadline ) {
			usleep( 200000 );
			$held = get_transient( $lock_key );
			if ( is_numeric( $held ) && absint( $held ) > 0 ) {
				WC()->session->set( 'order_awaiting_payment', absint( $held ) );
				return;
			}
		}
	}

	set_transient( $lock_key, 'pending', 60 );
	add_action( 'woocommerce_checkout_order_processed', 'nh_checkout_svea_remember_placed_order', 1 );
}

/**
 * @param string $sco_id Svea checkout order id.
 * @return string
 */
function nh_checkout_svea_place_lock_key( $sco_id ) {
	return 'nh_sco_place_' . md5( (string) $sco_id );
}

/**
 * @param int $order_id Woo order id.
 */
function nh_checkout_svea_remember_placed_order( $order_id ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	$sco_id = (string) WC()->session->get( 'sco_order_id' );
	if ( $sco_id === '' ) {
		return;
	}
	set_transient( nh_checkout_svea_place_lock_key( $sco_id ), absint( $order_id ), 120 );
}

/**
 * Svea checkout create/update has no lock. Several refresh_sco_snippet
 * requests at once all see an empty sco_order_id and each call Svea Create.
 * The log then shows 4–6 "Creating order" lines in the same second, two
 * Svea IDs, and "Received push for order we don't have yet. Standing by".
 * Woo stays pending-payment because the Final push is not replayed.
 *
 * @return void
 */
function nh_checkout_svea_lock_snippet_refresh() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	$option = nh_checkout_svea_snippet_lock_option();
	$ttl    = 20;
	$start  = time();
	$got    = false;

	while ( ( time() - $start ) < $ttl ) {
		$existing = get_option( $option );
		if ( is_numeric( $existing ) && (int) $existing < ( time() - $ttl ) ) {
			delete_option( $option );
		}
		if ( add_option( $option, (string) time(), '', 'no' ) ) {
			$got = true;
			break;
		}
		usleep( 250000 );
	}

	if ( ! $got ) {
		delete_option( $option );
		add_option( $option, (string) time(), '', 'no' );
	}

	$release = static function () use ( $option ) {
		delete_option( $option );
	};

	add_action( 'woocommerce_sco_after_refresh_sco_snippet', $release, 999 );
	add_action( 'shutdown', $release, 0 );
}

/**
 * @return string
 */
function nh_checkout_svea_snippet_lock_option() {
	$sid = '';
	if ( function_exists( 'WC' ) && WC()->session && is_callable( array( WC()->session, 'get_customer_id' ) ) ) {
		$sid = (string) WC()->session->get_customer_id();
	}
	if ( $sid === '' ) {
		$sid = (string) wp_get_session_token();
	}
	return 'nh_sco_snippet_lock_' . md5( $sid );
}

/**
 * Recreate the Svea session only when Woo and Svea both have a value and they differ.
 * An empty Svea field means "not identified yet" — update the existing checkout
 * (presets). Treating empty as a mismatch created a second Svea order (see NO-3167:
 * 118339342 then 118339347 three seconds later).
 *
 * @param string $woo Woo form value.
 * @param string $sco Svea checkout value.
 * @return bool
 */
function nh_checkout_svea_identity_conflict( $woo, $sco ) {
	$woo = trim( (string) $woo );
	$sco = trim( (string) $sco );
	if ( $woo === '' || $sco === '' ) {
		return false;
	}
	return strcasecmp( $woo, $sco ) !== 0;
}

/**
 * Checkout fields we can persist and hand to Svea/Kustom.
 *
 * @return array<string, string> field => sanitizer callback
 */
function nh_checkout_identity_field_map() {
	return array(
		'billing_email'       => 'sanitize_email',
		'billing_phone'       => 'wc_clean',
		'billing_first_name'  => 'wc_clean',
		'billing_last_name'   => 'wc_clean',
		'billing_company'     => 'wc_clean',
		'billing_company_reg' => 'wc_clean',
		'billing_postcode'    => 'nh_checkout_usable_postcode',
		'billing_city'        => 'wc_clean',
		'billing_address_1'   => 'wc_clean',
		'billing_address_2'   => 'wc_clean',
		'billing_country'     => 'wc_clean',
		'billing_state'       => 'wc_clean',
		'billing_customer_type' => 'wc_clean',
	);
}

/**
 * Collect billed identity from a parsed checkout form / POST.
 *
 * @param array<string, mixed> $form Parsed checkout post_data.
 * @return array<string, string>
 */
function nh_checkout_collect_posted_identity( $form = array() ) {
	if ( ! is_array( $form ) ) {
		$form = array();
	}

	$identity = array();
	foreach ( nh_checkout_identity_field_map() as $key => $sanitize ) {
		$value = '';
		if ( ! empty( $form[ $key ] ) && is_scalar( $form[ $key ] ) ) {
			$value = (string) $form[ $key ];
		}
		if ( $value === '' ) {
			$value = nh_checkout_posted_scalar( $key );
		}
		if ( $value === '' || ! is_callable( $sanitize ) ) {
			continue;
		}
		if ( 'billing_phone' === $key ) {
			$code = '';
			if ( ! empty( $form['billing_phone_code'] ) && is_scalar( $form['billing_phone_code'] ) ) {
				$code = (string) $form['billing_phone_code'];
			}
			if ( $code === '' ) {
				$code = nh_checkout_posted_scalar( 'billing_phone_code' );
			}
			$value = nh_checkout_normalize_phone( $value, $code );
		} else {
			$value = call_user_func( $sanitize, $value );
		}
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( $value === '' ) {
			continue;
		}
		if ( 'billing_customer_type' === $key && ! in_array( $value, array( 'private', 'business' ), true ) ) {
			continue;
		}
		$identity[ $key ] = $value;
	}

	return $identity;
}

/**
 * @param array<string, string> $identity Identity fields.
 */
function nh_checkout_store_identity( $identity ) {
	if ( ! is_array( $identity ) || ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	$stored = nh_checkout_get_stored_identity();
	foreach ( $identity as $key => $value ) {
		if ( is_string( $value ) && $value !== '' ) {
			$stored[ $key ] = $value;
		}
	}
	WC()->session->set( 'nh_checkout_identity', $stored );
}

/**
 * @return array<string, string>
 */
function nh_checkout_get_stored_identity() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return array();
	}
	$stored = WC()->session->get( 'nh_checkout_identity' );
	return is_array( $stored ) ? $stored : array();
}

/**
 * Identity currently on the Woo customer object.
 *
 * @return array<string, string>
 */
function nh_checkout_identity_from_customer() {
	if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
		return array();
	}

	$customer = WC()->customer;
	$values   = array(
		'billing_email'      => method_exists( $customer, 'get_billing_email' ) ? sanitize_email( (string) $customer->get_billing_email() ) : '',
		'billing_phone'      => method_exists( $customer, 'get_billing_phone' ) ? wc_clean( (string) $customer->get_billing_phone() ) : '',
		'billing_first_name' => method_exists( $customer, 'get_billing_first_name' ) ? wc_clean( (string) $customer->get_billing_first_name() ) : '',
		'billing_last_name'  => method_exists( $customer, 'get_billing_last_name' ) ? wc_clean( (string) $customer->get_billing_last_name() ) : '',
		'billing_company'    => method_exists( $customer, 'get_billing_company' ) ? wc_clean( (string) $customer->get_billing_company() ) : '',
		'billing_postcode'   => method_exists( $customer, 'get_billing_postcode' ) ? nh_checkout_usable_postcode( $customer->get_billing_postcode() ) : '',
		'billing_city'       => method_exists( $customer, 'get_billing_city' ) ? wc_clean( (string) $customer->get_billing_city() ) : '',
		'billing_address_1'  => method_exists( $customer, 'get_billing_address_1' ) ? wc_clean( (string) $customer->get_billing_address_1() ) : '',
		'billing_address_2'  => method_exists( $customer, 'get_billing_address_2' ) ? wc_clean( (string) $customer->get_billing_address_2() ) : '',
		'billing_country'    => method_exists( $customer, 'get_billing_country' ) ? wc_clean( (string) $customer->get_billing_country() ) : '',
		'billing_state'      => method_exists( $customer, 'get_billing_state' ) ? wc_clean( (string) $customer->get_billing_state() ) : '',
	);

	if ( method_exists( $customer, 'get_meta' ) ) {
		$reg  = wc_clean( (string) $customer->get_meta( 'billing_company_reg' ) );
		$type = wc_clean( (string) $customer->get_meta( 'billing_customer_type' ) );
		if ( $reg !== '' ) {
			$values['billing_company_reg'] = $reg;
		}
		if ( in_array( $type, array( 'private', 'business' ), true ) ) {
			$values['billing_customer_type'] = $type;
		}
	}

	return array_filter(
		$values,
		static function ( $value ) {
			return is_string( $value ) && $value !== '';
		}
	);
}

/**
 * Posted + session + customer, later sources fill gaps only.
 *
 * @param array<string, mixed> $form Parsed checkout post_data.
 * @return array<string, string>
 */
function nh_checkout_merged_identity( $form = array() ) {
	return array_filter(
		array_merge(
			nh_checkout_identity_from_customer(),
			nh_checkout_get_stored_identity(),
			nh_checkout_collect_posted_identity( $form )
		)
	);
}

/**
 * Write identity onto the Woo customer (and matching shipping fields).
 *
 * @param array<string, string> $identity Identity fields.
 * @param bool                  $save     Persist guest session data.
 */
function nh_checkout_apply_identity_to_customer( $identity, $save = false ) {
	if ( ! is_array( $identity ) || ! function_exists( 'WC' ) || ! WC()->customer ) {
		return;
	}

	$customer = WC()->customer;
	$setters  = array(
		'billing_email'      => 'set_billing_email',
		'billing_phone'      => 'set_billing_phone',
		'billing_first_name' => 'set_billing_first_name',
		'billing_last_name'  => 'set_billing_last_name',
		'billing_company'    => 'set_billing_company',
		'billing_postcode'   => 'set_billing_postcode',
		'billing_city'       => 'set_billing_city',
		'billing_address_1'  => 'set_billing_address_1',
		'billing_address_2'  => 'set_billing_address_2',
		'billing_country'    => 'set_billing_country',
		'billing_state'      => 'set_billing_state',
	);
	$ship     = array(
		'billing_phone'      => 'set_shipping_phone',
		'billing_first_name' => 'set_shipping_first_name',
		'billing_last_name'  => 'set_shipping_last_name',
		'billing_company'    => 'set_shipping_company',
		'billing_postcode'   => 'set_shipping_postcode',
		'billing_city'       => 'set_shipping_city',
		'billing_address_1'  => 'set_shipping_address_1',
		'billing_address_2'  => 'set_shipping_address_2',
		'billing_country'    => 'set_shipping_country',
		'billing_state'      => 'set_shipping_state',
	);

	try {
		foreach ( $setters as $key => $setter ) {
			if ( empty( $identity[ $key ] ) || ! method_exists( $customer, $setter ) ) {
				continue;
			}
			$customer->{$setter}( $identity[ $key ] );
			if ( isset( $ship[ $key ] ) && method_exists( $customer, $ship[ $key ] ) ) {
				$customer->{$ship[ $key ]}( $identity[ $key ] );
			}
		}
		if ( method_exists( $customer, 'update_meta_data' ) ) {
			if ( ! empty( $identity['billing_company_reg'] ) ) {
				$customer->update_meta_data( 'billing_company_reg', $identity['billing_company_reg'] );
			}
			if ( ! empty( $identity['billing_customer_type'] ) ) {
				$customer->update_meta_data( 'billing_customer_type', $identity['billing_customer_type'] );
			}
		}
		if ( $save && method_exists( $customer, 'save' ) ) {
			$customer->save();
		}
	} catch ( Throwable $e ) {
		return;
	}
}

/**
 * Stable hash of the fields a gateway can actually prefill.
 *
 * @param array<string, string> $identity Identity fields.
 * @return string
 */
function nh_checkout_identity_prefill_hash( $identity ) {
	$keys = array(
		'billing_email',
		'billing_phone',
		'billing_first_name',
		'billing_last_name',
		'billing_company',
		'billing_company_reg',
		'billing_postcode',
		'billing_city',
		'billing_address_1',
		'billing_address_2',
		'billing_country',
		'billing_customer_type',
	);
	$parts = array();
	foreach ( $keys as $key ) {
		$parts[ $key ] = isset( $identity[ $key ] ) ? strtolower( trim( (string) $identity[ $key ] ) ) : '';
	}
	return md5( wp_json_encode( $parts ) );
}

/**
 * Restore Woo form identity onto the customer before Svea/Kustom create the iframe order.
 */
function nh_checkout_restore_identity_on_checkout() {
	if ( ! nh_is_classic_checkout_form() || wp_doing_ajax() ) {
		return;
	}
	$identity = nh_checkout_merged_identity();
	if ( ! $identity ) {
		return;
	}
	nh_checkout_store_identity( $identity );
	nh_checkout_apply_identity_to_customer( $identity, true );
}

/**
 * Apply stored Woo identity immediately before the payment iframe is built.
 *
 * @return array<string, string>
 */
function nh_checkout_prepare_snippet_identity() {
	$identity = nh_checkout_merged_identity();
	if ( $identity ) {
		nh_checkout_store_identity( $identity );
		nh_checkout_apply_identity_to_customer( $identity, true );
	}
	return $identity;
}

/**
 * Drop a leftover empty Kustom session so create() can send Woo billing_address.
 * Klarna ignores most address updates after the first iframe order exists.
 *
 * @param array<string, string> $identity Identity fields.
 */
function nh_checkout_kustom_maybe_recreate_for_identity( $identity ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session || ! is_array( $identity ) ) {
		return;
	}
	if ( function_exists( 'kco_wc_prefill_allowed' ) && ! kco_wc_prefill_allowed() ) {
		return;
	}

	$has_prefill = ( ! empty( $identity['billing_email'] ) || ! empty( $identity['billing_phone'] ) || ! empty( $identity['billing_first_name'] ) || ! empty( $identity['billing_address_1'] ) );
	if ( ! $has_prefill ) {
		return;
	}

	$hash     = nh_checkout_identity_prefill_hash( $identity );
	$previous = (string) WC()->session->get( 'nh_kco_prefill_hash', '' );
	if ( $previous === $hash && WC()->session->get( 'kco_wc_order_id' ) ) {
		return;
	}

	WC()->session->__unset( 'kco_wc_order_id' );
	WC()->session->__unset( 'kco_update_md5' );
	WC()->session->set( 'nh_kco_prefill_hash', $hash );
}

/**
 * Inject Woo identity into Kustom create/update payloads when the plugin's get_value() was empty.
 *
 * @param array $request_body Kustom API body.
 * @return array
 */
function nh_checkout_kustom_api_prefill( $request_body ) {
	if ( ! is_array( $request_body ) ) {
		return $request_body;
	}
	if ( function_exists( 'kco_wc_prefill_allowed' ) && ! kco_wc_prefill_allowed() ) {
		return $request_body;
	}

	$identity = nh_checkout_merged_identity();
	if ( ! $identity ) {
		return $request_body;
	}

	$billing = isset( $request_body['billing_address'] ) && is_array( $request_body['billing_address'] )
		? $request_body['billing_address']
		: array();

	$map = array(
		'email'             => 'billing_email',
		'phone'             => 'billing_phone',
		'given_name'        => 'billing_first_name',
		'family_name'       => 'billing_last_name',
		'organization_name' => 'billing_company',
		'street_address'    => 'billing_address_1',
		'street_address2'   => 'billing_address_2',
		'city'              => 'billing_city',
		'postal_code'       => 'billing_postcode',
		'country'           => 'billing_country',
		'region'            => 'billing_state',
	);
	foreach ( $map as $klarna => $woo ) {
		$current = isset( $billing[ $klarna ] ) ? trim( (string) $billing[ $klarna ] ) : '';
		if ( $current !== '' || empty( $identity[ $woo ] ) ) {
			continue;
		}
		$value = $identity[ $woo ];
		if ( 'postal_code' === $klarna ) {
			$value = str_replace( ' ', '', $value );
		}
		$billing[ $klarna ] = $value;
	}

	$billing = array_filter(
		$billing,
		static function ( $value ) {
			return ! ( $value === '' || $value === null );
		}
	);
	if ( $billing ) {
		$request_body['billing_address']  = $billing;
		$request_body['shipping_address'] = isset( $request_body['shipping_address'] ) && is_array( $request_body['shipping_address'] )
			? array_merge( $request_body['shipping_address'], $billing )
			: $billing;
	}

	$type = isset( $identity['billing_customer_type'] ) ? $identity['billing_customer_type'] : '';
	if ( $type === '' ) {
		$type = nh_checkout_posted_type_from_request();
	}
	$kco_id = ( function_exists( 'WC' ) && WC()->session ) ? (string) WC()->session->get( 'kco_wc_order_id' ) : '';
	$lock_type = ( $kco_id === '' );

	if ( $type !== '' && $lock_type ) {
		if ( 'business' === $type ) {
			if ( ! isset( $request_body['customer'] ) || ! is_array( $request_body['customer'] ) ) {
				$request_body['customer'] = array();
			}
			$request_body['customer']['type'] = 'organization';
			if ( ! empty( $identity['billing_company_reg'] ) ) {
				$request_body['customer']['organization_registration_id'] = $identity['billing_company_reg'];
			}
		} elseif ( 'private' === $type ) {
			if ( ! isset( $request_body['customer'] ) || ! is_array( $request_body['customer'] ) ) {
				$request_body['customer'] = array();
			}
			if ( empty( $request_body['customer']['type'] ) ) {
				$request_body['customer']['type'] = 'person';
			}
		}
	}

	return $request_body;
}

/**
 * Read a Svea GET-order field from top-level, Customer, or PresetValues.
 *
 * @param array  $checkout_data Svea order.
 * @param string $type_name     EmailAddress|PhoneNumber|PostalCode|NationalId.
 * @return string
 */
function nh_checkout_svea_checkout_field( $checkout_data, $type_name ) {
	if ( ! is_array( $checkout_data ) || $type_name === '' ) {
		return '';
	}
	if ( ! empty( $checkout_data[ $type_name ] ) && is_scalar( $checkout_data[ $type_name ] ) ) {
		return trim( (string) $checkout_data[ $type_name ] );
	}
	if ( ! empty( $checkout_data['Customer'][ $type_name ] ) && is_scalar( $checkout_data['Customer'][ $type_name ] ) ) {
		return trim( (string) $checkout_data['Customer'][ $type_name ] );
	}
	$presets = array();
	if ( ! empty( $checkout_data['PresetValues'] ) && is_array( $checkout_data['PresetValues'] ) ) {
		$presets = $checkout_data['PresetValues'];
	} elseif ( ! empty( $checkout_data['presetValues'] ) && is_array( $checkout_data['presetValues'] ) ) {
		$presets = $checkout_data['presetValues'];
	}
	foreach ( $presets as $preset ) {
		if ( ! is_array( $preset ) ) {
			continue;
		}
		$name = isset( $preset['TypeName'] ) ? $preset['TypeName'] : ( isset( $preset['typeName'] ) ? $preset['typeName'] : '' );
		if ( strcasecmp( (string) $name, $type_name ) !== 0 ) {
			continue;
		}
		$value = isset( $preset['Value'] ) ? $preset['Value'] : ( isset( $preset['value'] ) ? $preset['value'] : '' );
		if ( is_scalar( $value ) ) {
			return trim( (string) $value );
		}
	}
	return '';
}

/**
 * Map Svea NationalId onto the Woo registration-number field at pay time.
 *
 * @param array $map Svea plugin field map.
 * @return array
 */
function nh_checkout_svea_fields_mapping( $map ) {
	if ( ! is_array( $map ) ) {
		$map = array();
	}
	if ( empty( $map['billing_company_reg'] ) ) {
		$map['billing_company_reg'] = 'Customer:NationalId,NationalId';
	}
	return $map;
}

/**
 * Nested scalar from a Svea/Kustom payload (case-insensitive keys).
 *
 * @param mixed                $source Payload.
 * @param array<int, string>   $path   Key path.
 * @return string
 */
function nh_checkout_nested_scalar( $source, $path ) {
	$cursor = $source;
	foreach ( $path as $key ) {
		if ( ! is_array( $cursor ) ) {
			return '';
		}
		if ( array_key_exists( $key, $cursor ) ) {
			$cursor = $cursor[ $key ];
			continue;
		}
		$found = null;
		foreach ( $cursor as $k => $v ) {
			if ( strcasecmp( (string) $k, $key ) === 0 ) {
				$found = $v;
				break;
			}
		}
		if ( $found === null ) {
			return '';
		}
		$cursor = $found;
	}
	if ( is_bool( $cursor ) ) {
		return $cursor ? '1' : '';
	}
	return is_scalar( $cursor ) ? trim( (string) $cursor ) : '';
}

/**
 * Current Svea checkout GET payload, cached per request.
 *
 * @return array<string, mixed>
 */
function nh_checkout_svea_current_order() {
	static $cached = null;
	if ( $cached !== null ) {
		return $cached;
	}
	$cached = array();
	if ( ! class_exists( '\Svea_Checkout_For_Woocommerce\Models\Svea_Checkout' ) ) {
		return $cached;
	}
	if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->session->get( 'sco_order_id' ) ) {
		return $cached;
	}
	try {
		$sco  = new \Svea_Checkout_For_Woocommerce\Models\Svea_Checkout();
		$data = $sco->get();
		$cached = is_array( $data ) ? $data : array();
	} catch ( Throwable $e ) {
		$cached = array();
	}
	return $cached;
}

/**
 * Identity collected inside the Svea iframe.
 *
 * @return array<string, string>
 */
function nh_checkout_iframe_identity_from_svea() {
	$data = nh_checkout_svea_current_order();
	if ( ! $data ) {
		return array();
	}

	$is_company = (bool) nh_checkout_nested_scalar( $data, array( 'Customer', 'IsCompany' ) );
	if ( ! $is_company ) {
		$is_company = (bool) nh_checkout_nested_scalar( $data, array( 'IsCompany' ) );
	}

	$identity = array(
		'billing_customer_type' => $is_company ? 'business' : 'private',
		'billing_email'         => nh_checkout_svea_checkout_field( $data, 'EmailAddress' ),
		'billing_phone'         => nh_checkout_svea_checkout_field( $data, 'PhoneNumber' ),
		'billing_first_name'    => nh_checkout_nested_scalar( $data, array( 'BillingAddress', 'FirstName' ) ),
		'billing_last_name'     => nh_checkout_nested_scalar( $data, array( 'BillingAddress', 'LastName' ) ),
		'billing_address_1'     => nh_checkout_nested_scalar( $data, array( 'BillingAddress', 'StreetAddress' ) ),
		'billing_address_2'     => nh_checkout_nested_scalar( $data, array( 'BillingAddress', 'CoAddress' ) ),
		'billing_postcode'      => nh_checkout_usable_postcode( nh_checkout_nested_scalar( $data, array( 'BillingAddress', 'PostalCode' ) ) ),
		'billing_city'          => nh_checkout_nested_scalar( $data, array( 'BillingAddress', 'City' ) ),
		'billing_country'       => strtoupper( nh_checkout_nested_scalar( $data, array( 'BillingAddress', 'CountryCode' ) ) ),
		'billing_company'       => $is_company ? nh_checkout_nested_scalar( $data, array( 'BillingAddress', 'FullName' ) ) : '',
		'billing_company_reg'   => $is_company ? nh_checkout_svea_checkout_field( $data, 'NationalId' ) : '',
	);

	if ( $is_company && $identity['billing_first_name'] === '' && $identity['billing_company'] !== '' ) {
		$identity['billing_first_name'] = $identity['billing_company'];
	}

	return $identity;
}

/**
 * Identity collected inside the Kustom/Klarna iframe.
 *
 * @return array<string, string>
 */
function nh_checkout_iframe_identity_from_kustom() {
	if ( ! function_exists( 'KCO_WC' ) || ! function_exists( 'WC' ) || ! WC()->session ) {
		return array();
	}
	$id = (string) WC()->session->get( 'kco_wc_order_id' );
	if ( $id === '' ) {
		return array();
	}
	$api = ( KCO_WC() && isset( KCO_WC()->api ) ) ? KCO_WC()->api : null;
	if ( ! is_object( $api ) || ! method_exists( $api, 'get_klarna_order' ) ) {
		return array();
	}

	try {
		$order = $api->get_klarna_order( $id );
	} catch ( Throwable $e ) {
		return array();
	}
	if ( ! is_array( $order ) ) {
		return array();
	}

	$billing = isset( $order['billing_address'] ) && is_array( $order['billing_address'] ) ? $order['billing_address'] : array();
	$cust    = isset( $order['customer'] ) && is_array( $order['customer'] ) ? $order['customer'] : array();
	$org     = nh_checkout_nested_scalar( $billing, array( 'organization_name' ) );
	$is_org  = ( isset( $cust['type'] ) && $cust['type'] === 'organization' ) || $org !== '';

	$reg = '';
	if ( ! empty( $cust['organization_registration_id'] ) && is_scalar( $cust['organization_registration_id'] ) ) {
		$reg = trim( (string) $cust['organization_registration_id'] );
	} elseif ( ! empty( $billing['organization_registration_id'] ) && is_scalar( $billing['organization_registration_id'] ) ) {
		$reg = trim( (string) $billing['organization_registration_id'] );
	}

	$identity = array(
		'billing_customer_type' => $is_org ? 'business' : 'private',
		'billing_email'         => nh_checkout_nested_scalar( $billing, array( 'email' ) ),
		'billing_phone'         => nh_checkout_nested_scalar( $billing, array( 'phone' ) ),
		'billing_first_name'    => nh_checkout_nested_scalar( $billing, array( 'given_name' ) ),
		'billing_last_name'     => nh_checkout_nested_scalar( $billing, array( 'family_name' ) ),
		'billing_address_1'     => nh_checkout_nested_scalar( $billing, array( 'street_address' ) ),
		'billing_address_2'     => nh_checkout_nested_scalar( $billing, array( 'street_address2' ) ),
		'billing_postcode'      => nh_checkout_usable_postcode( nh_checkout_nested_scalar( $billing, array( 'postal_code' ) ) ),
		'billing_city'          => nh_checkout_nested_scalar( $billing, array( 'city' ) ),
		'billing_country'       => strtoupper( nh_checkout_nested_scalar( $billing, array( 'country' ) ) ),
		'billing_state'         => nh_checkout_nested_scalar( $billing, array( 'region' ) ),
		'billing_company'       => $is_org ? $org : '',
		'billing_company_reg'   => $is_org ? $reg : '',
	);

	if ( $is_org && $identity['billing_first_name'] === '' && $identity['billing_company'] !== '' ) {
		$identity['billing_first_name'] = $identity['billing_company'];
	}

	return $identity;
}

/**
 * Overlay iframe identity onto Woo posted checkout data.
 *
 * @param array<string, string> $data    Woo posted data.
 * @param array<string, string> $iframe  Iframe identity.
 * @return array<string, string>
 */
function nh_checkout_apply_iframe_identity( $data, $iframe ) {
	if ( ! is_array( $data ) || ! is_array( $iframe ) || ! $iframe ) {
		return $data;
	}

	$type = isset( $iframe['billing_customer_type'] ) ? $iframe['billing_customer_type'] : '';
	foreach ( $iframe as $key => $value ) {
		if ( $key === 'billing_customer_type' ) {
			continue;
		}
		if ( $value === '' || $value === null ) {
			continue;
		}
		$data[ $key ] = $value;
		$_POST[ $key ] = $value; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	if ( 'business' === $type || 'private' === $type ) {
		$data['billing_customer_type'] = $type;
		$_POST['billing_customer_type'] = $type; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	if ( 'private' === $type ) {
		$data['billing_company']     = '';
		$data['billing_company_reg'] = '';
		$_POST['billing_company']     = ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST['billing_company_reg'] = ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	return $data;
}

/**
 * Svea/Kustom iframe values win over the Woo details form at pay time.
 *
 * @param array $data Posted checkout data.
 * @return array
 */
function nh_checkout_posted_data_prefer_iframe( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	$method = isset( $data['payment_method'] ) ? $data['payment_method'] : '';
	if ( ! nh_checkout_is_snippet_gateway( $method ) ) {
		return $data;
	}

	$iframe = array();
	if ( preg_match( '/svea|sco/', strtolower( (string) $method ) ) ) {
		$iframe = nh_checkout_iframe_identity_from_svea();
	} elseif ( preg_match( '/kco|kustom|klarna/', strtolower( (string) $method ) ) ) {
		$iframe = nh_checkout_iframe_identity_from_kustom();
	} else {
		$iframe = nh_checkout_iframe_identity_from_svea();
		if ( ! $iframe ) {
			$iframe = nh_checkout_iframe_identity_from_kustom();
		}
	}

	if ( ! $iframe && function_exists( 'WC' ) && WC()->session ) {
		$session_type = (string) WC()->session->get( 'nh_iframe_customer_type' );
		if ( in_array( $session_type, array( 'private', 'business' ), true ) ) {
			$iframe['billing_customer_type'] = $session_type;
		}
	}

	return nh_checkout_apply_iframe_identity( $data, $iframe );
}

/**
 * Add or replace a Svea PresetValues entry.
 *
 * @param array  $presets  Preset list.
 * @param string $type_name TypeName.
 * @param mixed  $value     Value.
 * @param bool   $overwrite Replace an existing value.
 * @return array
 */
function nh_checkout_svea_upsert_preset( $presets, $type_name, $value, $overwrite = true ) {
	if ( ! is_array( $presets ) ) {
		$presets = array();
	}
	if ( $value === '' || $value === null ) {
		return $presets;
	}

	foreach ( $presets as $i => $preset ) {
		if ( ! is_array( $preset ) ) {
			continue;
		}
		$name = isset( $preset['TypeName'] ) ? $preset['TypeName'] : '';
		if ( $name !== $type_name ) {
			continue;
		}
		if ( ! empty( $preset['IsReadOnly'] ) ) {
			return $presets;
		}
		if ( $overwrite || $preset['Value'] === '' || $preset['Value'] === null ) {
			$presets[ $i ]['Value'] = $value;
		}
		return $presets;
	}

	$presets[] = array(
		'TypeName'   => $type_name,
		'Value'      => $value,
		'IsReadOnly' => false,
	);
	return $presets;
}

/**
 * Svea create() only accepts EmailAddress, PhoneNumber, PostalCode, IsCompany, NationalId.
 * Recreate the SCO when Woo now has identity the existing checkout session does not.
 *
 * @param bool  $need_new      Plugin decision.
 * @param array $checkout_data Current Svea checkout payload.
 * @return bool
 */
function nh_checkout_svea_needs_new_for_identity( $need_new, $checkout_data ) {
	if ( $need_new || ! is_array( $checkout_data ) ) {
		return $need_new;
	}

	$identity = nh_checkout_merged_identity();

	$email = isset( $identity['billing_email'] ) ? sanitize_email( $identity['billing_email'] ) : '';
	$sco_email = sanitize_email( nh_checkout_svea_checkout_field( $checkout_data, 'EmailAddress' ) );
	if ( nh_checkout_svea_identity_conflict( $email, $sco_email ) ) {
		return true;
	}

	$phone = isset( $identity['billing_phone'] ) ? trim( (string) $identity['billing_phone'] ) : '';
	$sco_phone = trim( nh_checkout_svea_checkout_field( $checkout_data, 'PhoneNumber' ) );
	if ( nh_checkout_svea_identity_conflict( $phone, $sco_phone ) ) {
		return true;
	}

	$zip = isset( $identity['billing_postcode'] ) ? nh_checkout_usable_postcode( $identity['billing_postcode'] ) : '';
	$sco_zip = nh_checkout_usable_postcode( nh_checkout_svea_checkout_field( $checkout_data, 'PostalCode' ) );
	if ( $sco_zip === '' && ! empty( $checkout_data['BillingAddress']['PostalCode'] ) ) {
		$sco_zip = nh_checkout_usable_postcode( $checkout_data['BillingAddress']['PostalCode'] );
	}
	if ( nh_checkout_svea_identity_conflict( $zip, $sco_zip ) ) {
		return true;
	}

	return $need_new;
}

/**
 * Put every Svea-supported preset onto create() from the Woo form.
 *
 * @param array $data Create-order payload.
 * @return array
 */
function nh_checkout_svea_create_order_identity( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	$key = isset( $data['PresetValues'] ) && is_array( $data['PresetValues'] ) ? 'PresetValues' : 'presetValues';
	if ( ! isset( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
		$data[ $key ] = array();
	}

	$identity = nh_checkout_merged_identity();
	$type     = isset( $identity['billing_customer_type'] ) ? $identity['billing_customer_type'] : nh_checkout_posted_type_from_request();

	if ( ! empty( $identity['billing_email'] ) ) {
		$email = sanitize_email( $identity['billing_email'] );
		if ( $email !== '' ) {
			$data[ $key ] = nh_checkout_svea_upsert_preset( $data[ $key ], 'EmailAddress', substr( $email, 0, 50 ) );
		}
	}
	if ( ! empty( $identity['billing_phone'] ) ) {
		$data[ $key ] = nh_checkout_svea_upsert_preset( $data[ $key ], 'PhoneNumber', $identity['billing_phone'] );
	}
	if ( ! empty( $identity['billing_postcode'] ) ) {
		$data[ $key ] = nh_checkout_svea_upsert_preset( $data[ $key ], 'PostalCode', $identity['billing_postcode'] );
	}

	if ( $type !== '' ) {
		$is_company = ( 'business' === $type );
		$data[ $key ] = nh_checkout_svea_upsert_preset( $data[ $key ], 'IsCompany', $is_company );
		if ( $is_company && ! empty( $identity['billing_company_reg'] ) ) {
			$data[ $key ] = nh_checkout_svea_upsert_preset( $data[ $key ], 'NationalId', $identity['billing_company_reg'] );
		}
	}

	return $data;
}

/**
 * Customer type from this request (AJAX post_data or POST).
 *
 * @return string private|business|empty
 */
function nh_checkout_posted_type_from_request() {
	if ( isset( $_POST['billing_customer_type'] ) && is_scalar( $_POST['billing_customer_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$type = sanitize_text_field( wp_unslash( (string) $_POST['billing_customer_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return in_array( $type, array( 'private', 'business' ), true ) ? $type : '';
	}

	if ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form = array();
		parse_str( wp_unslash( $_POST['post_data'] ), $form ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $form['billing_customer_type'] ) && is_scalar( $form['billing_customer_type'] ) ) {
			$type = sanitize_text_field( (string) $form['billing_customer_type'] );
			return in_array( $type, array( 'private', 'business' ), true ) ? $type : '';
		}
	}

	return '';
}

add_action( 'init', 'nh_checkout_ux_init' );

/**
 * If the checkout page still has the Checkout block, render classic shortcode
 * so SVEA / MakeCommerce / similar gateways can collect payment.
 *
 * @param string $content Block HTML.
 * @param array  $block   Parsed block.
 * @return string
 */
function nh_checkout_render_classic_block( $content, $block ) {
	if ( empty( $block['blockName'] ) || 'woocommerce/checkout' !== $block['blockName'] ) {
		return $content;
	}
	if ( ! shortcode_exists( 'woocommerce_checkout' ) ) {
		return $content;
	}
	return do_shortcode( '[woocommerce_checkout]' );
}

/**
 * @param array $classes Body classes.
 * @return array
 */
function nh_checkout_ux_body_class( $classes ) {
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		$classes[] = 'nh-checkout-ux';
	}
	if ( nh_is_classic_checkout_form() ) {
		$classes[] = 'nh-checkout-form';
		$classes[] = nh_checkout_is_payment_step() ? 'nh-checkout--step-payment' : 'nh-checkout--step-details';
	}
	if ( nh_checkout_should_load_iframe() ) {
		$classes[] = 'nh-checkout--snippet';
	}
	return $classes;
}

function nh_checkout_ux_assets() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	$style_deps = array( 'astra-custom-for-norhage-theme-css' );
	foreach ( array(
		'woocommerce-layout',
		'woocommerce-general',
		'woocommerce-smallscreen',
		'astra-theme-css',
		'astra-addon-css',
		'woocommerce-inline',
		'select2',
		'custom-basket-css',
	) as $handle ) {
		if ( wp_style_is( $handle, 'registered' ) || wp_style_is( $handle, 'enqueued' ) ) {
			$style_deps[] = $handle;
		}
	}

	wp_enqueue_style(
		'nh-checkout-ux',
		get_stylesheet_directory_uri() . '/assets/css/checkout.css',
		$style_deps,
		norhage_asset_version( '/assets/css/checkout.css' )
	);

	if ( ! nh_is_classic_checkout_form() ) {
		return;
	}

	$script_deps = array( 'jquery' );
	if ( wp_script_is( 'wc-checkout', 'registered' ) || wp_script_is( 'wc-checkout', 'enqueued' ) ) {
		$script_deps[] = 'wc-checkout';
	}
	if ( wp_script_is( 'nh-crisp', 'registered' ) ) {
		$script_deps[] = 'nh-crisp';
	}

	wp_enqueue_script(
		'nh-checkout-ux',
		get_stylesheet_directory_uri() . '/assets/js/checkout-ux.js',
		$script_deps,
		norhage_asset_version( '/assets/js/checkout-ux.js' ),
		function_exists( 'norhage_script_args' ) ? norhage_script_args() : true
	);

	wp_localize_script(
		'nh-checkout-ux',
		'nhCheckoutUx',
		array(
			'contactHeading' => __( 'Contact person', 'nh-theme' ),
			'noteLabel'      => __( 'Add a note (optional)', 'nh-theme' ),
			'summaryLabel'   => __( 'Order summary', 'nh-theme' ),
			'phoneIsoCodes'   => nh_checkout_calling_codes(),
			'phoneCodeFlags'  => nh_checkout_calling_code_flag_map(),
			'phoneLengths'    => nh_checkout_national_digit_limits(),
			'phoneInvalid'    => __( 'Please enter a valid phone number.', 'nh-theme' ),
			'otherPayment'    => __( 'Other payment method', 'nh-theme' ),
			'snippetCheckout' => nh_checkout_should_load_iframe(),
			'checkoutStep'    => nh_checkout_is_payment_step() ? 'payment' : 'details',
			'chosenPayment'   => nh_checkout_chosen_payment_method(),
			'nextLabel'       => __( 'Continue to payment', 'nh-theme' ),
			'backLabel'       => __( 'Back to details', 'nh-theme' ),
			'selectPayment'   => __( 'Please choose a payment method.', 'nh-theme' ),
			'termsRequired'   => __( 'Please agree to the website terms and conditions to continue.', 'nh-theme' ),
			'applyZipNonce'   => wp_create_nonce( 'nh-snippet-apply-zip' ),
			'inclShipping'    => __( 'Shipping: %s', 'nh-theme' ),
		)
	);
}

/**
 * Shipping line for the mobile summary bar, so the cost stays visible if the accordion is closed.
 *
 * @return string HTML
 */
function nh_checkout_summary_shipping_html() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->cart->needs_shipping() ) {
		return '';
	}
	if ( ! WC()->cart->show_shipping() ) {
		return '';
	}

	$total = WC()->cart->get_cart_shipping_total();
	$text  = wp_strip_all_tags( (string) $total );
	if ( $text === '' || ! preg_match( '/\d/', $text ) ) {
		return '';
	}

	return sprintf(
		/* translators: %s: formatted shipping price */
		__( 'Shipping: %s', 'nh-theme' ),
		$total
	);
}

/**
 * Keep line items in the summary column; payment radios stay under the form.
 * Svea also removes woocommerce_checkout_payment from order review — re-attach here.
 */
function nh_checkout_split_review_and_payment() {
	if ( ! nh_is_classic_checkout_form() ) {
		return;
	}
	remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
	if ( ! has_action( 'nh_checkout_payment', 'woocommerce_checkout_payment' ) ) {
		add_action( 'nh_checkout_payment', 'woocommerce_checkout_payment', 10 );
	}
}

/**
 * State is optional on every country. Address line 2 stays optional.
 *
 * @param array $fields Address fields.
 * @return array
 */
function nh_checkout_default_address_fields( $fields ) {
	if ( isset( $fields['state'] ) ) {
		$fields['state']['required'] = false;
		$fields['state']['priority'] = 90;
	}
	if ( isset( $fields['address_2'] ) ) {
		$fields['address_2']['required'] = false;
		$fields['address_2']['priority'] = 70;
	}
	if ( isset( $fields['company'] ) ) {
		$fields['company']['label']    = __( 'Business name', 'nh-theme' );
		$fields['company']['required'] = false;
	}
	if ( isset( $fields['country'] ) ) {
		$fields['country']['priority'] = 40;
		$fields['country']['class']    = array( 'form-row-first', 'address-field', 'update_totals_on_change' );
	}
	if ( isset( $fields['postcode'] ) ) {
		$fields['postcode']['priority'] = 50;
		$fields['postcode']['class']    = array( 'form-row-last', 'address-field', 'update_totals_on_change' );
	}
	if ( isset( $fields['address_1'] ) ) {
		$fields['address_1']['priority'] = 60;
	}
	if ( isset( $fields['city'] ) ) {
		$fields['city']['priority'] = 80;
		$fields['city']['class']    = array( 'form-row-first', 'address-field' );
	}
	if ( isset( $fields['state'] ) ) {
		$fields['state']['class'] = array( 'form-row-last', 'address-field' );
	}
	return $fields;
}

/**
 * Country-switch JS reads this locale map. Keep state optional there too.
 *
 * @param array $locale Locale field overrides.
 * @return array
 */
function nh_checkout_country_locale( $locale ) {
	if ( ! is_array( $locale ) ) {
		return $locale;
	}

	foreach ( $locale as $country => $fields ) {
		if ( ! is_array( $fields ) ) {
			continue;
		}
		$locale[ $country ]['state']['required']     = false;
		$locale[ $country ]['state']['priority']     = 90;
		$locale[ $country ]['company']['label']      = __( 'Business name', 'nh-theme' );
		$locale[ $country ]['company']['required']   = false;
		$locale[ $country ]['country']['priority']   = 40;
		$locale[ $country ]['country']['class']      = array( 'form-row-first' );
		$locale[ $country ]['postcode']['priority']  = 50;
		$locale[ $country ]['postcode']['class']     = array( 'form-row-last' );
		$locale[ $country ]['address_1']['priority'] = 60;
		$locale[ $country ]['address_2']['priority'] = 70;
		$locale[ $country ]['city']['priority']      = 80;
		$locale[ $country ]['city']['class']         = array( 'form-row-first' );
		$locale[ $country ]['state']['class']        = array( 'form-row-last' );
	}

	return $locale;
}

/**
 * Phone is required. Registration number is stored with billing fields.
 *
 * @param array $fields Billing fields.
 * @return array
 */
function nh_checkout_billing_fields( $fields ) {
	if ( isset( $fields['billing_phone'] ) ) {
		$fields['billing_phone']['required'] = true;
		$fields['billing_phone']['type']     = 'tel';
		$fields['billing_phone']['custom_attributes']['inputmode']    = 'tel';
		$fields['billing_phone']['custom_attributes']['autocomplete'] = 'tel';
	}
	if ( isset( $fields['billing_email'] ) ) {
		$fields['billing_email']['required'] = true;
		$fields['billing_email']['custom_attributes']['inputmode']    = 'email';
		$fields['billing_email']['custom_attributes']['autocomplete'] = 'email';
	}

	$fields['billing_company_reg'] = array(
		'label'        => __( 'Registration number', 'nh-theme' ),
		'required'     => false,
		'class'        => array( 'form-row-wide', 'nh-checkout-field--business' ),
		'autocomplete' => 'off',
		'priority'     => 32,
		'placeholder'  => nh_checkout_reg_placeholder(),
	);

	return $fields;
}

/**
 * Example format hint per shop locale. Validation stays free-text.
 *
 * @return string
 */
function nh_checkout_reg_placeholder() {
	$locale = function_exists( 'get_locale' ) ? get_locale() : '';
	$map    = array(
		'sv_SE' => 'XXXXXX-XXXX',
		'nb_NO' => '123 456 789',
		'nn_NO' => '123 456 789',
		'fi'    => '1234567-8',
		'fi_FI' => '1234567-8',
		'de_DE' => 'HRB 12345',
		'lt_LT' => '123456789',
		'da_DK' => '12345678',
	);
	return isset( $map[ $locale ] ) ? $map[ $locale ] : '';
}

/**
 * Checkout field order, customer type, and section headings.
 *
 * @param array $fields Checkout fieldsets.
 * @return array
 */
function nh_checkout_fields( $fields ) {
	if ( empty( $fields['billing'] ) || ! is_array( $fields['billing'] ) ) {
		return $fields;
	}

	$billing = &$fields['billing'];

	$billing['billing_customer_type'] = array(
		'type'     => 'radio',
		'label'    => __( 'I am ordering as', 'nh-theme' ),
		'required' => true,
		'class'    => array( 'form-row-wide', 'nh-checkout-type', 'update_totals_on_change' ),
		'options'  => array(
			'private'  => __( 'Private', 'nh-theme' ),
			'business' => __( 'Business', 'nh-theme' ),
		),
		'default'  => 'private',
		'priority' => 4,
	);

	$billing['nh_section_person'] = array(
		'type'     => 'nh_section',
		'label'    => __( 'Contact person', 'nh-theme' ),
		'required' => false,
		'class'    => array( 'nh-checkout-field--person-heading' ),
		'priority' => 200,
	);

	nh_checkout_set_field( $billing, 'billing_company', array(
		'label'        => __( 'Business name', 'nh-theme' ),
		'required'     => false,
		'class'        => array( 'form-row-wide', 'nh-checkout-field--business' ),
		'autocomplete' => 'organization',
		'priority'     => 10,
	) );

	nh_checkout_set_field( $billing, 'billing_company_reg', array(
		'label'        => __( 'Registration number', 'nh-theme' ),
		'required'     => false,
		'class'        => array( 'form-row-wide', 'nh-checkout-field--business' ),
		'autocomplete' => 'off',
		'priority'     => 20,
		'placeholder'  => nh_checkout_reg_placeholder(),
	) );

	nh_checkout_set_field( $billing, 'billing_first_name', array(
		'required'     => false,
		'class'        => array( 'form-row-first', 'nh-checkout-pair-start', 'nh-checkout-field--person' ),
		'autocomplete' => 'given-name',
		'priority'     => 30,
	) );

	nh_checkout_set_field( $billing, 'billing_last_name', array(
		'required'     => false,
		'class'        => array( 'form-row-last', 'nh-checkout-pair-end', 'nh-checkout-field--person' ),
		'autocomplete' => 'family-name',
		'priority'     => 32,
	) );

	nh_checkout_set_field( $billing, 'billing_email', array(
		'required'     => true,
		'class'        => array( 'form-row-first', 'nh-checkout-pair-start', 'nh-checkout-field--contact', 'update_totals_on_change' ),
		'validate'     => array( 'email' ),
		'autocomplete' => 'email',
		'priority'     => 34,
		'custom_attributes' => array(
			'inputmode' => 'email',
		),
	) );

	nh_checkout_set_field( $billing, 'billing_phone', array(
		'type'         => 'tel',
		'required'     => true,
		'class'        => array( 'form-row-last', 'nh-checkout-pair-end', 'nh-checkout-field--contact', 'update_totals_on_change' ),
		'validate'     => array( 'phone' ),
		'autocomplete' => 'tel-national',
		'priority'     => 36,
		'custom_attributes' => array(
			'inputmode' => 'tel',
		),
	) );

	nh_checkout_set_field( $billing, 'billing_country', array(
		'class'        => array( 'form-row-first', 'nh-checkout-pair-start', 'address-field', 'update_totals_on_change' ),
		'autocomplete' => 'country',
		'priority'     => 40,
	) );

	nh_checkout_set_field( $billing, 'billing_postcode', array(
		'class'        => array( 'form-row-last', 'nh-checkout-pair-end', 'address-field', 'update_totals_on_change' ),
		'autocomplete' => 'postal-code',
		'priority'     => 50,
	) );

	nh_checkout_set_field( $billing, 'billing_address_1', array(
		'class'        => array( 'form-row-wide', 'address-field' ),
		'autocomplete' => 'address-line1',
		'priority'     => 60,
	) );

	nh_checkout_set_field( $billing, 'billing_address_2', array(
		'label'        => __( 'Apartment, suite, etc.', 'nh-theme' ),
		'required'     => false,
		'class'        => array( 'form-row-wide', 'address-field' ),
		'autocomplete' => 'address-line2',
		'priority'     => 70,
	) );

	nh_checkout_set_field( $billing, 'billing_city', array(
		'class'        => array( 'form-row-first', 'nh-checkout-pair-start', 'address-field' ),
		'autocomplete' => 'address-level2',
		'priority'     => 80,
	) );

	nh_checkout_set_field( $billing, 'billing_state', array(
		'required'     => false,
		'class'        => array( 'form-row-last', 'nh-checkout-pair-end', 'address-field' ),
		'autocomplete' => 'address-level1',
		'priority'     => 90,
	) );

	$billing['billing_contact_email'] = array(
		'type'         => 'email',
		'label'        => __( 'Contact email', 'nh-theme' ),
		'required'     => false,
		'class'        => array( 'form-row-first', 'nh-checkout-pair-start', 'nh-checkout-field--person-extra' ),
		'validate'     => array( 'email' ),
		'autocomplete' => 'email',
		'priority'     => 220,
		'custom_attributes' => array(
			'inputmode' => 'email',
		),
	);

	$billing['billing_contact_phone'] = array(
		'type'         => 'tel',
		'label'        => __( 'Contact phone', 'nh-theme' ),
		'required'     => false,
		'class'        => array( 'form-row-last', 'nh-checkout-pair-end', 'nh-checkout-field--person-extra' ),
		'validate'     => array( 'phone' ),
		'autocomplete' => 'tel-national',
		'priority'     => 222,
		'custom_attributes' => array(
			'inputmode' => 'tel',
		),
	);

	if ( function_exists( 'wc_checkout_fields_uasort_comparison' ) ) {
		uasort( $billing, 'wc_checkout_fields_uasort_comparison' );
	}

	if ( ! empty( $fields['shipping'] ) && is_array( $fields['shipping'] ) ) {
		nh_checkout_set_field( $fields['shipping'], 'shipping_country', array(
			'class'    => array( 'form-row-first', 'address-field', 'update_totals_on_change' ),
			'priority' => 40,
		) );
		nh_checkout_set_field( $fields['shipping'], 'shipping_postcode', array(
			'class'    => array( 'form-row-last', 'address-field', 'update_totals_on_change' ),
			'priority' => 50,
		) );
		nh_checkout_set_field( $fields['shipping'], 'shipping_address_1', array(
			'priority' => 60,
		) );
		nh_checkout_set_field( $fields['shipping'], 'shipping_address_2', array(
			'priority' => 70,
		) );
		nh_checkout_set_field( $fields['shipping'], 'shipping_city', array(
			'class'    => array( 'form-row-first', 'address-field' ),
			'priority' => 80,
		) );
		nh_checkout_set_field( $fields['shipping'], 'shipping_state', array(
			'required' => false,
			'class'    => array( 'form-row-last', 'address-field' ),
			'priority' => 90,
		) );
		if ( isset( $fields['shipping']['shipping_company'] ) ) {
			$fields['shipping']['shipping_company']['label']    = __( 'Business name', 'nh-theme' );
			$fields['shipping']['shipping_company']['required'] = false;
		}
	}

	return $fields;
}

/**
 * Merge args onto an existing checkout field without dropping Woo defaults.
 *
 * @param array  $fields Fieldset.
 * @param string $key    Field key.
 * @param array  $args   Overrides.
 */
function nh_checkout_set_field( &$fields, $key, $args ) {
	if ( ! isset( $fields[ $key ] ) || ! is_array( $fields[ $key ] ) ) {
		$fields[ $key ] = $args;
		return;
	}

	if ( isset( $args['custom_attributes'] ) ) {
		$existing = isset( $fields[ $key ]['custom_attributes'] ) && is_array( $fields[ $key ]['custom_attributes'] )
			? $fields[ $key ]['custom_attributes']
			: array();
		$args['custom_attributes'] = array_merge( $existing, $args['custom_attributes'] );
	}

	$fields[ $key ] = array_merge( $fields[ $key ], $args );
}

/**
 * Keep paired fields from being forced full-width by Woo/Astra at render time.
 *
 * @param array  $args  Field args.
 * @param string $key   Field key.
 * @param mixed  $value Unused.
 * @return array
 */
function nh_checkout_form_field_args( $args, $key, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	$pairs = array(
		'billing_first_name'     => 'start',
		'billing_last_name'      => 'end',
		'billing_email'          => 'start',
		'billing_phone'          => 'end',
		'billing_country'        => 'start',
		'billing_postcode'       => 'end',
		'billing_city'           => 'start',
		'billing_state'          => 'end',
		'billing_contact_email'  => 'start',
		'billing_contact_phone'  => 'end',
		'shipping_country'       => 'start',
		'shipping_postcode'      => 'end',
		'shipping_city'          => 'start',
		'shipping_state'         => 'end',
	);

	if ( ! isset( $pairs[ $key ] ) || ! is_array( $args ) ) {
		return $args;
	}

	$class = isset( $args['class'] ) && is_array( $args['class'] ) ? $args['class'] : array();
	$class = array_values( array_diff( $class, array( 'form-row-wide', 'form-row-first', 'form-row-last', 'nh-checkout-pair-start', 'nh-checkout-pair-end' ) ) );
	if ( 'start' === $pairs[ $key ] ) {
		$class[] = 'form-row-first';
		$class[] = 'nh-checkout-pair-start';
	} else {
		$class[] = 'form-row-last';
		$class[] = 'nh-checkout-pair-end';
	}
	$args['class'] = $class;

	return $args;
}

/**
 * Dialling codes used by the checkout phone field.
 *
 * Keys are ISO 3166-1 alpha-2. Values are digits without the plus sign.
 *
 * @return array<string,string>
 */
function nh_checkout_calling_codes() {
	return array(
		'LT' => '370',
		'LV' => '371',
		'EE' => '372',
		'FI' => '358',
		'SE' => '46',
		'NO' => '47',
		'DK' => '45',
		'IS' => '354',
		'DE' => '49',
		'AT' => '43',
		'BE' => '32',
		'NL' => '31',
		'FR' => '33',
		'PL' => '48',
		'CZ' => '420',
		'SK' => '421',
		'HU' => '36',
		'IE' => '353',
		'ES' => '34',
		'PT' => '351',
		'IT' => '39',
		'GR' => '30',
		'RO' => '40',
		'BG' => '359',
		'HR' => '385',
		'SI' => '386',
		'LU' => '352',
		'CH' => '41',
		'GB' => '44',
		'CY' => '357',
		'MT' => '356',
		'US' => '1',
		'CA' => '1',
	);
}

/**
 * Unique calling-code options for the phone prefix select.
 *
 * Empty "+" is the default until the number (or the customer) sets a code.
 *
 * @return array<string,string> code => label
 */
function nh_checkout_calling_code_options() {
	$options = array(
		'' => '+',
	);
	$codes = array_unique( array_values( nh_checkout_calling_codes() ) );
	sort( $codes, SORT_NUMERIC );
	$flags = nh_checkout_calling_code_flag_map();
	foreach ( $codes as $code ) {
		$iso   = isset( $flags[ $code ] ) ? $flags[ $code ] : '';
		$flag  = nh_checkout_flag_emoji( $iso );
		$label = trim( $flag . ' +' . $code );
		$options[ $code ] = $label !== '' ? $label : ( '+' . $code );
	}
	return $options;
}

/**
 * First ISO country for each calling code (for flags).
 *
 * @return array<string,string> code => ISO
 */
function nh_checkout_calling_code_flag_map() {
	$map = array();
	foreach ( nh_checkout_calling_codes() as $iso => $code ) {
		if ( ! isset( $map[ $code ] ) ) {
			$map[ $code ] = $iso;
		}
	}
	return $map;
}

/**
 * Regional-indicator flag emoji for an ISO country code.
 *
 * @param string $iso ISO 3166-1 alpha-2.
 * @return string
 */
function nh_checkout_flag_emoji( $iso ) {
	$iso = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $iso ) );
	if ( 2 !== strlen( $iso ) ) {
		return '';
	}
	if ( function_exists( 'mb_chr' ) ) {
		return mb_chr( 127397 + ord( $iso[0] ) ) . mb_chr( 127397 + ord( $iso[1] ) );
	}
	return html_entity_decode( '&#' . ( 127397 + ord( $iso[0] ) ) . ';&#' . ( 127397 + ord( $iso[1] ) ) . ';', ENT_NOQUOTES, 'UTF-8' );
}

/**
 * National significant-number length after the calling code (no leading 0).
 *
 * @return array<string,array{0:int,1:int}> code => [ min, max ]
 */
function nh_checkout_national_digit_limits() {
	return array(
		'370' => array( 8, 8 ),
		'371' => array( 8, 8 ),
		'372' => array( 7, 8 ),
		'358' => array( 6, 10 ),
		'46'  => array( 7, 9 ),
		'47'  => array( 8, 8 ),
		'45'  => array( 8, 8 ),
		'354' => array( 7, 7 ),
		'49'  => array( 10, 11 ),
		'43'  => array( 10, 13 ),
		'32'  => array( 8, 9 ),
		'31'  => array( 9, 9 ),
		'33'  => array( 9, 9 ),
		'48'  => array( 9, 9 ),
		'420' => array( 9, 9 ),
		'421' => array( 9, 9 ),
		'36'  => array( 8, 9 ),
		'353' => array( 7, 9 ),
		'34'  => array( 9, 9 ),
		'351' => array( 9, 9 ),
		'39'  => array( 9, 11 ),
		'30'  => array( 10, 10 ),
		'40'  => array( 9, 9 ),
		'359' => array( 8, 9 ),
		'385' => array( 8, 9 ),
		'386' => array( 8, 8 ),
		'352' => array( 8, 9 ),
		'41'  => array( 9, 9 ),
		'44'  => array( 10, 10 ),
		'357' => array( 8, 8 ),
		'356' => array( 8, 8 ),
		'1'   => array( 10, 10 ),
	);
}

/**
 * Whether a stored +XXXXXXXX number has a plausible national length.
 *
 * @param string $phone Normalised E.164-like number.
 * @return bool
 */
function nh_checkout_phone_number_is_valid( $phone ) {
	$phone = trim( (string) $phone );
	if ( $phone === '' ) {
		return false;
	}

	list( $code, $national ) = nh_checkout_split_phone( $phone );
	$national = preg_replace( '/\D/', '', (string) $national );

	if ( $code === '' || $national === '' ) {
		$digits = preg_replace( '/\D/', '', $phone );
		$len    = strlen( (string) $digits );
		return $len >= 8 && $len <= 15;
	}

	$limits = nh_checkout_national_digit_limits();
	$min    = 6;
	$max    = 15;
	if ( isset( $limits[ $code ] ) ) {
		$min = (int) $limits[ $code ][0];
		$max = (int) $limits[ $code ][1];
	}

	$len = strlen( $national );
	return $len >= $min && $len <= $max;
}

/**
 * Default calling-code digits for the shop / current billing country.
 *
 * @param string $country ISO country.
 * @return string
 */
function nh_checkout_default_calling_code( $country = '' ) {
	$map     = nh_checkout_calling_codes();
	$country = strtoupper( (string) $country );
	if ( $country && isset( $map[ $country ] ) ) {
		return $map[ $country ];
	}
	$base = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_base_country() : '';
	if ( $base && isset( $map[ $base ] ) ) {
		return $map[ $base ];
	}
	return '370';
}

/**
 * Keep only an allowed calling-code value.
 *
 * @param string $code Raw posted code.
 * @return string
 */
function nh_checkout_sanitize_calling_code( $code ) {
	$code    = preg_replace( '/\D/', '', (string) $code );
	$allowed = nh_checkout_calling_code_options();
	return isset( $allowed[ $code ] ) ? $code : '';
}

/**
 * Combine a national number with a calling code into +XXXXXXXX.
 *
 * If the number is already international (+ or 00), keep that form.
 *
 * @param string $number Raw national or international number.
 * @param string $code   Dialling digits without +.
 * @return string
 */
function nh_checkout_normalize_phone( $number, $code ) {
	$number = preg_replace( '/[^\d+]/', '', (string) $number );
	$code   = nh_checkout_sanitize_calling_code( $code );

	if ( '' === $number ) {
		return '';
	}

	if ( 0 === strpos( $number, '00' ) ) {
		$number = '+' . substr( $number, 2 );
	}

	if ( 0 === strpos( $number, '+' ) ) {
		$digits = preg_replace( '/\D/', '', substr( $number, 1 ) );
		return $digits === '' ? '' : '+' . $digits;
	}

	if ( $code && 0 === strpos( $number, '0' ) ) {
		$number = ltrim( $number, '0' );
	}

	if ( $code && $number !== '' ) {
		return '+' . $code . $number;
	}

	return $number;
}

/**
 * Split a stored international number into calling-code digits and the rest.
 *
 * @param string $phone Stored phone.
 * @return array{0:string,1:string} [ code digits, national remainder ]
 */
function nh_checkout_split_phone( $phone ) {
	$phone = (string) $phone;
	if ( 0 === strpos( $phone, '00' ) ) {
		$phone = '+' . substr( $phone, 2 );
	}
	if ( 0 !== strpos( $phone, '+' ) ) {
		return array( '', $phone );
	}
	$digits  = preg_replace( '/\D/', '', substr( $phone, 1 ) );
	$options = array_keys( nh_checkout_calling_code_options() );
	usort(
		$options,
		function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		}
	);
	foreach ( $options as $code ) {
		if ( $code === '' || ! is_string( $code ) && ! is_int( $code ) ) {
			continue;
		}
		$code = (string) $code;
		if ( $code === '' ) {
			continue;
		}
		if ( 0 === strpos( $digits, $code ) ) {
			return array( $code, substr( $digits, strlen( $code ) ) );
		}
	}
	return array( '', $phone );
}

/**
 * Inject a calling-code select next to checkout tel fields.
 *
 * @param string $field Field HTML.
 * @param string $key   Field key.
 * @param array  $args  Field args.
 * @param mixed  $value Current value.
 * @return string
 */
function nh_checkout_phone_field_html( $field, $key, $args, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	if ( ! in_array( $key, array( 'billing_phone', 'billing_contact_phone' ), true ) ) {
		return $field;
	}
	if ( ! preg_match( '/<input\b[^>]*>/i', $field, $match ) ) {
		return $field;
	}

	$code_name   = $key . '_code';
	$posted_code = isset( $_POST[ $code_name ] ) ? wc_clean( wp_unslash( $_POST[ $code_name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	list( $split_code, $national ) = nh_checkout_split_phone( (string) $value );

	$selected = nh_checkout_sanitize_calling_code( $posted_code ? $posted_code : $split_code );
	$display  = $split_code ? $national : (string) $value;

	$input = $match[0];
	if ( preg_match( '/\svalue="/', $input ) ) {
		$input = preg_replace( '/\svalue="[^"]*"/', ' value="' . esc_attr( $display ) . '"', $input, 1 );
	} else {
		$input = preg_replace( '/<input\b/i', '<input value="' . esc_attr( $display ) . '"', $input, 1 );
	}
	if ( false === strpos( $input, 'autocomplete=' ) ) {
		$input = preg_replace( '/<input\b/i', '<input autocomplete="tel-national"', $input, 1 );
	}
	if ( false === strpos( $input, 'maxlength=' ) ) {
		$input = preg_replace( '/<input\b/i', '<input maxlength="16"', $input, 1 );
	}

	$flags    = nh_checkout_calling_code_flag_map();
	$flag_iso = ( $selected && isset( $flags[ $selected ] ) ) ? $flags[ $selected ] : '';
	$flag     = nh_checkout_flag_emoji( $flag_iso );

	$options_html = '';
	foreach ( nh_checkout_calling_code_options() as $code => $label ) {
		$options_html .= '<option value="' . esc_attr( $code ) . '"' . selected( (string) $selected, (string) $code, false ) . '>' . esc_html( $label ) . '</option>';
	}

	$combo = '<span class="nh-phone-combo">'
		. '<span class="nh-phone-prefix">'
		. '<span class="nh-phone-flag" aria-hidden="true">' . esc_html( $flag ) . '</span>'
		. '<select name="' . esc_attr( $code_name ) . '" id="' . esc_attr( $code_name ) . '" class="nh-phone-code" aria-label="' . esc_attr__( 'Country calling code', 'nh-theme' ) . '" autocomplete="tel-country-code">'
		. $options_html
		. '</select>'
		. '</span>'
		. $input
		. '</span>';

	return str_replace( $match[0], $combo, $field );
}

/**
 * Persist phones as +XXXXXXXX from the calling-code select + number.
 *
 * @param array $data Posted checkout data.
 * @return array
 */
function nh_checkout_normalize_posted_phones( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	$country = isset( $data['billing_country'] ) ? $data['billing_country'] : '';
	$code    = isset( $_POST['billing_phone_code'] ) ? wc_clean( wp_unslash( $_POST['billing_phone_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! nh_checkout_sanitize_calling_code( $code ) ) {
		$code = nh_checkout_default_calling_code( $country );
	}
	if ( isset( $data['billing_phone'] ) ) {
		$data['billing_phone'] = nh_checkout_normalize_phone( $data['billing_phone'], $code );
	}

	$contact_code = isset( $_POST['billing_contact_phone_code'] ) ? wc_clean( wp_unslash( $_POST['billing_contact_phone_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! nh_checkout_sanitize_calling_code( $contact_code ) ) {
		$contact_code = nh_checkout_default_calling_code( $country );
	}
	if ( isset( $data['billing_contact_phone'] ) ) {
		$data['billing_contact_phone'] = nh_checkout_normalize_phone( $data['billing_contact_phone'], $contact_code );
	}

	return $data;
}

/**
 * Section heading field (not submitted).
 *
 * @param string $field Empty from core (unknown type).
 * @param string $key   Field key.
 * @param array  $args  Field args.
 * @param mixed  $value Unused.
 * @return string
 */
function nh_checkout_section_field( $field, $key, $args, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	$classes = array( 'form-row', 'form-row-wide', 'nh-checkout-section' );
	if ( ! empty( $args['class'] ) && is_array( $args['class'] ) ) {
		$classes = array_merge( $classes, $args['class'] );
	}

	$sort = isset( $args['priority'] ) ? $args['priority'] : '';

	$html  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" id="' . esc_attr( $key ) . '_field" data-priority="' . esc_attr( (string) $sort ) . '">';
	$html .= '<h3 class="nh-checkout-section__title">' . esc_html( $args['label'] ) . '</h3>';
	$html .= '</div>';

	return $html;
}

/**
 * Default to private. Prefill from the customer, or business if a company is saved.
 *
 * @param mixed  $value Current value.
 * @param string $input Field key.
 * @return mixed
 */
function nh_checkout_get_value( $value, $input ) {
	if ( 'billing_customer_type' === $input ) {
		if ( in_array( $value, array( 'private', 'business' ), true ) ) {
			return $value;
		}
		$stored = nh_checkout_get_stored_identity();
		if ( ! empty( $stored['billing_customer_type'] ) && in_array( $stored['billing_customer_type'], array( 'private', 'business' ), true ) ) {
			return $stored['billing_customer_type'];
		}
		if ( is_user_logged_in() ) {
			$saved = get_user_meta( get_current_user_id(), 'billing_customer_type', true );
			if ( in_array( $saved, array( 'private', 'business' ), true ) ) {
				return $saved;
			}
			$company = get_user_meta( get_current_user_id(), 'billing_company', true );
			if ( is_string( $company ) && trim( $company ) !== '' ) {
				return 'business';
			}
		}
		return 'private';
	}

	if ( $value !== null && $value !== '' ) {
		return $value;
	}

	$identity_key = $input;
	if ( strpos( $input, 'shipping_' ) === 0 ) {
		$identity_key = 'billing_' . substr( $input, strlen( 'shipping_' ) );
	}

	$stored = nh_checkout_get_stored_identity();
	if ( ! empty( $stored[ $identity_key ] ) && is_string( $stored[ $identity_key ] ) ) {
		return $stored[ $identity_key ];
	}

	if ( in_array( $input, array( 'billing_company_reg', 'billing_contact_email', 'billing_contact_phone' ), true ) && is_user_logged_in() ) {
		$saved = (string) get_user_meta( get_current_user_id(), $input, true );
		if ( $saved !== '' ) {
			return $saved;
		}
	}

	return $value;
}

/**
 * Posted customer type, defaulting to private.
 *
 * @param array $data Posted checkout data.
 * @return string
 */
function nh_checkout_posted_type( $data ) {
	$type = isset( $data['billing_customer_type'] ) ? sanitize_text_field( wp_unslash( $data['billing_customer_type'] ) ) : '';
	return 'business' === $type ? 'business' : 'private';
}

/**
 * Business name + registration number required only for business orders.
 * Phone and email are always required.
 *
 * @param array    $data   Posted data.
 * @param WP_Error $errors Error bag.
 */
function nh_checkout_validate_fields( $data, $errors ) {
	if ( ! $errors instanceof WP_Error ) {
		return;
	}

	$method  = isset( $data['payment_method'] ) ? $data['payment_method'] : '';
	$snippet = nh_checkout_is_snippet_gateway( $method );

	$email = isset( $data['billing_email'] ) ? trim( (string) $data['billing_email'] ) : '';
	$phone = isset( $data['billing_phone'] ) ? trim( (string) $data['billing_phone'] ) : '';
	$first = isset( $data['billing_first_name'] ) ? trim( (string) $data['billing_first_name'] ) : '';
	$last  = isset( $data['billing_last_name'] ) ? trim( (string) $data['billing_last_name'] ) : '';
	$type  = nh_checkout_posted_type( $data );

	if ( $snippet ) {
		$errors->remove( 'billing_customer_type' );
		$errors->remove( 'billing_contact_email' );
		$errors->remove( 'billing_contact_phone' );
		$errors->remove( 'billing_company' );
		$errors->remove( 'billing_company_reg' );
	}

	if ( $email === '' ) {
		$errors->add( 'billing_email', __( 'Please enter a valid email address.', 'nh-theme' ) );
	}
	if ( $phone === '' ) {
		$errors->add( 'billing_phone', __( 'Please enter a phone number.', 'nh-theme' ) );
	} elseif ( ! nh_checkout_phone_number_is_valid( $phone ) ) {
		$errors->add( 'billing_phone', __( 'Please enter a valid phone number.', 'nh-theme' ) );
	}

	if ( 'business' === $type ) {
		$errors->remove( 'billing_first_name' );
		$errors->remove( 'billing_last_name' );

		$company = isset( $data['billing_company'] ) ? trim( (string) $data['billing_company'] ) : '';
		$reg     = isset( $data['billing_company_reg'] ) ? trim( (string) $data['billing_company_reg'] ) : '';

		if ( $company === '' && ! $snippet ) {
			$errors->add( 'billing_company', __( 'Please enter your business name.', 'nh-theme' ) );
		}
		if ( $reg === '' && ! $snippet ) {
			$errors->add( 'billing_company_reg', __( 'Please enter your registration number.', 'nh-theme' ) );
		}

		$contact_email = isset( $data['billing_contact_email'] ) ? trim( (string) $data['billing_contact_email'] ) : '';
		if ( $contact_email !== '' && ! is_email( $contact_email ) ) {
			$errors->add( 'billing_contact_email', __( 'Please enter a valid contact email.', 'nh-theme' ) );
		}

		$contact_phone = isset( $data['billing_contact_phone'] ) ? trim( (string) $data['billing_contact_phone'] ) : '';
		if ( $contact_phone !== '' && ! nh_checkout_phone_number_is_valid( $contact_phone ) ) {
			$errors->add( 'billing_contact_phone', __( 'Please enter a valid phone number.', 'nh-theme' ) );
		}
		return;
	}

	$errors->remove( 'billing_contact_email' );
	$errors->remove( 'billing_contact_phone' );
	$errors->remove( 'billing_company' );
	$errors->remove( 'billing_company_reg' );

	if ( $first === '' && ! $snippet ) {
		$errors->add( 'billing_first_name', __( 'Please enter a first name.', 'nh-theme' ) );
	}
	if ( $last === '' && ! $snippet ) {
		$errors->add( 'billing_last_name', __( 'Please enter a last name.', 'nh-theme' ) );
	}
	if ( $snippet && $first === '' && $last === '' ) {
		$company = isset( $data['billing_company'] ) ? trim( (string) $data['billing_company'] ) : '';
		if ( $company === '' ) {
			$errors->add( 'billing_first_name', __( 'Please enter a first name.', 'nh-theme' ) );
		}
	}
}

/**
 * Persist customer type and registration number. Clear company fields for private.
 *
 * @param WC_Order $order Order.
 * @param array    $data  Posted data.
 */
function nh_checkout_save_order_meta( $order, $data ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$type = nh_checkout_posted_type( $data );
	$order->update_meta_data( '_billing_customer_type', $type );

	if ( 'business' === $type ) {
		$reg = isset( $data['billing_company_reg'] ) ? sanitize_text_field( wp_unslash( $data['billing_company_reg'] ) ) : '';
		$order->update_meta_data( '_billing_company_reg', $reg );
		$order->update_meta_data( '_billing_contact_email', isset( $data['billing_contact_email'] ) ? sanitize_email( $data['billing_contact_email'] ) : '' );
		$order->update_meta_data( '_billing_contact_phone', isset( $data['billing_contact_phone'] ) ? wc_clean( $data['billing_contact_phone'] ) : '' );

		$first = trim( (string) $order->get_billing_first_name() );
		$last  = trim( (string) $order->get_billing_last_name() );
		if ( $first === '' && $last === '' ) {
			$company = trim( (string) $order->get_billing_company() );
			if ( $company !== '' ) {
				$order->set_billing_first_name( $company );
			}
		}
		return;
	}

	$order->set_billing_company( '' );
	$order->update_meta_data( '_billing_company_reg', '' );
	$order->update_meta_data( '_billing_contact_email', '' );
	$order->update_meta_data( '_billing_contact_phone', '' );
}

/**
 * Remember type + registration number on the customer for the next checkout.
 *
 * @param WC_Customer $customer Customer.
 * @param array       $data     Posted data.
 */
function nh_checkout_save_customer_meta( $customer, $data ) {
	if ( ! is_object( $customer ) || ! method_exists( $customer, 'update_meta_data' ) ) {
		return;
	}

	$type = nh_checkout_posted_type( $data );
	$customer->update_meta_data( 'billing_customer_type', $type );

	if ( 'business' === $type ) {
		$reg = isset( $data['billing_company_reg'] ) ? sanitize_text_field( wp_unslash( $data['billing_company_reg'] ) ) : '';
		$customer->update_meta_data( 'billing_company_reg', $reg );
		$customer->update_meta_data( 'billing_contact_email', isset( $data['billing_contact_email'] ) ? sanitize_email( $data['billing_contact_email'] ) : '' );
		$customer->update_meta_data( 'billing_contact_phone', isset( $data['billing_contact_phone'] ) ? wc_clean( $data['billing_contact_phone'] ) : '' );
		return;
	}

	$customer->set_billing_company( '' );
	$customer->update_meta_data( 'billing_company_reg', '' );
	$customer->update_meta_data( 'billing_contact_email', '' );
	$customer->update_meta_data( 'billing_contact_phone', '' );
}

/**
 * Append registration number to the company line in emails and addresses.
 *
 * @param array    $address Address parts.
 * @param WC_Order $order   Order.
 * @return array
 */
function nh_checkout_formatted_billing_address( $address, $order ) {
	if ( ! $order instanceof WC_Order ) {
		return $address;
	}

	$reg = trim( (string) $order->get_meta( '_billing_company_reg' ) );
	if ( $reg === '' ) {
		return $address;
	}

	$company = isset( $address['company'] ) ? trim( (string) $address['company'] ) : '';
	$line    = sprintf(
		/* translators: %s: company registration number */
		__( 'Reg. no. %s', 'nh-theme' ),
		$reg
	);

	$address['company'] = $company === '' ? $line : $company . ' (' . $line . ')';

	return $address;
}

/**
 * Optional business contact-person email and phone stored on the order.
 *
 * @param WC_Order $order Order.
 * @return array<int,array{label:string,value:string}>
 */
function nh_checkout_contact_meta_lines( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return array();
	}

	$lines = array();
	$email = trim( (string) $order->get_meta( '_billing_contact_email' ) );
	$phone = trim( (string) $order->get_meta( '_billing_contact_phone' ) );
	if ( $email !== '' ) {
		$lines[] = array(
			'label' => __( 'Contact email', 'nh-theme' ),
			'value' => $email,
		);
	}
	if ( $phone !== '' ) {
		$lines[] = array(
			'label' => __( 'Contact phone', 'nh-theme' ),
			'value' => $phone,
		);
	}
	return $lines;
}

/**
 * @param WC_Order $order Order.
 */
function nh_checkout_admin_billing_meta( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$type = $order->get_meta( '_billing_customer_type' );
	$reg  = $order->get_meta( '_billing_company_reg' );

	if ( $type ) {
		$label = 'business' === $type ? __( 'Business', 'nh-theme' ) : __( 'Private', 'nh-theme' );
		echo '<p><strong>' . esc_html__( 'Customer type', 'nh-theme' ) . ':</strong> ' . esc_html( $label ) . '</p>';
	}

	if ( $reg ) {
		echo '<p><strong>' . esc_html__( 'Registration number', 'nh-theme' ) . ':</strong> ' . esc_html( $reg ) . '</p>';
	}

	foreach ( nh_checkout_contact_meta_lines( $order ) as $line ) {
		echo '<p><strong>' . esc_html( $line['label'] ) . ':</strong> ' . esc_html( $line['value'] ) . '</p>';
	}
}

/**
 * Thank-you / my-account order details.
 *
 * @param WC_Order $order Order.
 */
function nh_checkout_order_contact_details( $order ) {
	$lines = nh_checkout_contact_meta_lines( $order );
	if ( ! $lines ) {
		return;
	}

	echo '<section class="woocommerce-customer-details--contact"><h2>' . esc_html__( 'Contact person', 'nh-theme' ) . '</h2>';
	foreach ( $lines as $line ) {
		echo '<p><strong>' . esc_html( $line['label'] ) . ':</strong> ' . esc_html( $line['value'] ) . '</p>';
	}
	echo '</section>';
}

/**
 * @param array         $fields        Email customer fields.
 * @param bool          $sent_to_admin Unused.
 * @param WC_Order|null $order         Order.
 * @return array
 */
function nh_checkout_email_contact_fields( $fields, $sent_to_admin, $order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	if ( ! $order instanceof WC_Order || ! is_array( $fields ) ) {
		return $fields;
	}

	foreach ( nh_checkout_contact_meta_lines( $order ) as $index => $line ) {
		$fields[ 'nh_contact_' . $index ] = array(
			'label' => $line['label'],
			'value' => $line['value'],
		);
	}

	return $fields;
}

/**
 * PDF Invoices hook is (type, order) in the Norhage template.
 *
 * @param string          $type             Document type.
 * @param WC_Order|object $order_or_document Order or document.
 */
function nh_checkout_pdf_reg_number( $type, $order_or_document ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	$order = $order_or_document;
	if ( is_object( $order_or_document ) && ! ( $order_or_document instanceof WC_Order ) && ! empty( $order_or_document->order ) ) {
		$order = $order_or_document->order;
	}
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$reg = trim( (string) $order->get_meta( '_billing_company_reg' ) );
	if ( $reg === '' ) {
		return;
	}

	echo '<div class="billing-reg">' . esc_html( sprintf( __( 'Reg. no. %s', 'nh-theme' ), $reg ) ) . '</div>';
}

function nh_checkout_secure_note() {
	if ( nh_checkout_is_snippet_gateway() ) {
		return;
	}
	echo '<p class="nh-checkout-secure">' . esc_html__( 'Secure checkout', 'nh-theme' ) . '</p>';
}

/**
 * Shown in red when the customer tries to pay without ticking terms.
 */
function nh_checkout_terms_required_hint() {
	if ( ! function_exists( 'wc_terms_and_conditions_checkbox_enabled' ) || ! wc_terms_and_conditions_checkbox_enabled() ) {
		return;
	}
	echo '<p class="nh-checkout-terms-error" role="alert">' . esc_html__( 'Please agree to the website terms and conditions to continue.', 'nh-theme' ) . '</p>';
}

/**
 * Gateway titles/descriptions are stored in Woo settings in English and skip gettext.
 * Run them through WooCommerce and theme translations on checkout.
 *
 * @param string $text Title, description, or button label.
 * @return string
 */
function nh_checkout_translate_gateway_text( $text ) {
	if ( ! is_string( $text ) ) {
		return $text;
	}
	$original = $text;
	$lookup   = trim( wp_strip_all_tags( $text ) );
	if ( $lookup === '' ) {
		return $original;
	}
	foreach ( array( 'woocommerce', 'nh-theme' ) as $domain ) {
		$translated = translate( $lookup, $domain ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.NonSingularStringLiteralDomain
		if ( is_string( $translated ) && $translated !== '' && $translated !== $lookup && trim( $original ) === $lookup ) {
			return $translated;
		}
	}
	return $original;
}

/**
 * Woo package titles and some shipping method labels are stored in English (Shipping / Shipment).
 *
 * @param string $name    Package name.
 * @param int    $index   Package index.
 * @param array  $package Package.
 * @return string
 */
function nh_checkout_translate_shipping_package_name( $name, $index = 0, $package = array() ) {
	unset( $index, $package );
	$plain = trim( wp_strip_all_tags( (string) $name ) );
	if ( $plain === '' ) {
		return $name;
	}
	if ( preg_match( '/^(Shipping|Shipment)(?:\s+(\d+))?$/i', $plain, $m ) ) {
		$label = ( strtolower( $m[1] ) === 'shipment' )
			? __( 'Shipment', 'nh-theme' )
			: __( 'Shipping', 'nh-theme' );
		if ( ! empty( $m[2] ) && (int) $m[2] > 1 ) {
			return $label . ' ' . (int) $m[2];
		}
		return $label;
	}
	$translated = nh_checkout_translate_gateway_text( $plain );
	return $translated !== $plain ? $translated : $name;
}

/**
 * Woo checkout.js posts shipping_method[undefined] when data-index is missing.
 * Package 0 then keeps the previous method and the total does not change.
 *
 * @param array $methods Posted methods.
 * @return array<int, string>
 */
function nh_checkout_normalize_shipping_methods( $methods ) {
	if ( ! is_array( $methods ) ) {
		return array();
	}

	$clean = array();
	foreach ( $methods as $key => $value ) {
		if ( ! is_scalar( $value ) ) {
			continue;
		}
		$value = wc_clean( (string) $value );
		if ( '' === $value ) {
			continue;
		}
		if ( 'undefined' === $key || '' === $key || null === $key || 'NaN' === $key ) {
			$key = 0;
		}
		if ( ! is_numeric( $key ) ) {
			continue;
		}
		$clean[ (int) $key ] = $value;
	}

	return $clean;
}

/**
 * Rewrite shipping_method[undefined] (and recover methods from serialized post_data)
 * before Woo copies them into the session.
 *
 * @param string $post_data Checkout form query string.
 */
function nh_checkout_sanitize_posted_shipping( $post_data ) {
	$posted = isset( $_POST['shipping_method'] ) ? wp_unslash( $_POST['shipping_method'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! is_array( $posted ) ) {
		$posted = array();
	}

	$clean = nh_checkout_normalize_shipping_methods( $posted );

	if ( ! $clean && is_string( $post_data ) && '' !== $post_data ) {
		$form = array();
		parse_str( $post_data, $form );
		$from_form = isset( $form['shipping_method'] ) ? $form['shipping_method'] : array();
		if ( is_array( $from_form ) ) {
			$clean = nh_checkout_normalize_shipping_methods( $from_form );
		}
	}

	if ( $clean ) {
		$_POST['shipping_method'] = $clean;
	}

	if ( is_string( $post_data ) && $post_data !== '' && false !== strpos( $post_data, 'shipping_method' ) ) {
		$rewritten = str_replace(
			array( 'shipping_method%5Bundefined%5D', 'shipping_method[undefined]' ),
			array( 'shipping_method%5B0%5D', 'shipping_method[0]' ),
			$post_data
		);
		if ( $rewritten !== $post_data && isset( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST['post_data'] = $rewritten;
		}
	}
}

/**
 * @param string $value Raw postcode.
 * @return string
 */
function nh_checkout_usable_postcode( $value ) {
	$value = trim( (string) $value );
	if ( $value === '' || $value === '••••' || preg_match( '/^•+$/u', $value ) ) {
		return '';
	}
	return $value;
}

/**
 * @param string $key POST key.
 * @return string
 */
function nh_checkout_posted_scalar( $key ) {
	if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return '';
	}
	$value = trim( wc_clean( wp_unslash( (string) $_POST[ $key ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( strpos( $key, 'postcode' ) !== false ) {
		return nh_checkout_usable_postcode( $value );
	}
	return $value;
}

/**
 * @param string               $post_key Review-request key (postcode, s_postcode, …).
 * @param array<int, string>   $form_keys Checkout field names to try from post_data.
 * @param array<string, mixed> $form      Parsed checkout form.
 * @param string               $customer_field billing_postcode / shipping_postcode / …
 */
function nh_checkout_fill_review_field( $post_key, $form_keys, $form, $customer_field ) {
	if ( nh_checkout_posted_scalar( $post_key ) !== '' ) {
		return;
	}

	foreach ( $form_keys as $form_key ) {
		$value = '';
		if ( ! empty( $form[ $form_key ] ) && is_scalar( $form[ $form_key ] ) ) {
			$value = trim( wc_clean( (string) $form[ $form_key ] ) );
		}
		if ( $value === '' ) {
			$value = nh_checkout_posted_scalar( $form_key );
		}
		if ( strpos( $post_key, 'postcode' ) !== false || strpos( $form_key, 'postcode' ) !== false ) {
			$value = nh_checkout_usable_postcode( $value );
		}
		if ( $value !== '' ) {
			$_POST[ $post_key ] = $value;
			return;
		}
	}

	// Restoring a stale session zip here can freeze zip-specific shipping.
	if ( nh_checkout_is_iframe_gateway() && ! nh_checkout_is_snippet_gateway() && ( strpos( $post_key, 'postcode' ) !== false || strpos( $customer_field, 'postcode' ) !== false ) ) {
		return;
	}

	if ( ! nh_checkout_is_iframe_gateway() || ! function_exists( 'WC' ) || ! WC()->customer ) {
		return;
	}

	$customer = WC()->customer;
	$map      = array(
		'billing_postcode'   => 'get_billing_postcode',
		'shipping_postcode'  => 'get_shipping_postcode',
		'billing_country'    => 'get_billing_country',
		'shipping_country'   => 'get_shipping_country',
		'billing_city'       => 'get_billing_city',
		'shipping_city'      => 'get_shipping_city',
		'billing_state'      => 'get_billing_state',
		'shipping_state'     => 'get_shipping_state',
		'billing_address_1'  => 'get_billing_address_1',
		'shipping_address_1' => 'get_shipping_address_1',
	);
	if ( ! isset( $map[ $customer_field ] ) || ! method_exists( $customer, $map[ $customer_field ] ) ) {
		return;
	}
	$existing = trim( (string) call_user_func( array( $customer, $map[ $customer_field ] ) ) );
	if ( strpos( $post_key, 'postcode' ) !== false || strpos( $customer_field, 'postcode' ) !== false ) {
		$existing = nh_checkout_usable_postcode( $existing );
	}
	if ( $existing !== '' ) {
		$_POST[ $post_key ] = $existing;
	}
}

/**
 * Iframe checkouts (SVEA, Kustom, PayPal) set the postcode on the customer,
 * then Woo's next update_order_review can overwrite it with empty form fields.
 * Country + postcode is enough to recalculate zip-based shipping.
 *
 * @param string $post_data Checkout form query string.
 */
function nh_checkout_sync_review_address( $post_data ) {
	$form = array();
	if ( is_string( $post_data ) && $post_data !== '' ) {
		parse_str( $post_data, $form );
	}

	nh_checkout_copy_posted_identity( $form );

	$ship_diff = ! empty( $form['ship_to_different_address'] );

	nh_checkout_fill_review_field( 'postcode', array( 'billing_postcode', 'shipping_postcode' ), $form, 'billing_postcode' );
	nh_checkout_fill_review_field( 'country', array( 'billing_country', 'shipping_country' ), $form, 'billing_country' );
	nh_checkout_fill_review_field( 'city', array( 'billing_city', 'shipping_city' ), $form, 'billing_city' );
	nh_checkout_fill_review_field( 'state', array( 'billing_state', 'shipping_state' ), $form, 'billing_state' );
	nh_checkout_fill_review_field( 'address', array( 'billing_address_1', 'shipping_address_1' ), $form, 'billing_address_1' );

	if ( $ship_diff ) {
		nh_checkout_fill_review_field( 's_postcode', array( 'shipping_postcode', 'billing_postcode' ), $form, 'shipping_postcode' );
		nh_checkout_fill_review_field( 's_country', array( 'shipping_country', 'billing_country' ), $form, 'shipping_country' );
		nh_checkout_fill_review_field( 's_city', array( 'shipping_city', 'billing_city' ), $form, 'shipping_city' );
		nh_checkout_fill_review_field( 's_state', array( 'shipping_state', 'billing_state' ), $form, 'shipping_state' );
		nh_checkout_fill_review_field( 's_address', array( 'shipping_address_1', 'billing_address_1' ), $form, 'shipping_address_1' );
	} else {
		foreach ( array(
			's_postcode' => 'postcode',
			's_country'  => 'country',
			's_city'     => 'city',
			's_state'    => 'state',
			's_address'  => 'address',
		) as $ship_key => $bill_key ) {
			if ( nh_checkout_posted_scalar( $ship_key ) === '' && nh_checkout_posted_scalar( $bill_key ) !== '' ) {
				$_POST[ $ship_key ] = nh_checkout_posted_scalar( $bill_key );
			}
		}
		nh_checkout_fill_review_field( 's_postcode', array( 'shipping_postcode', 'billing_postcode' ), $form, 'shipping_postcode' );
		nh_checkout_fill_review_field( 's_country', array( 'shipping_country', 'billing_country' ), $form, 'shipping_country' );
	}

	$postcode = nh_checkout_posted_scalar( 's_postcode' );
	if ( $postcode === '' ) {
		$postcode = nh_checkout_posted_scalar( 'postcode' );
	}
	$country = nh_checkout_posted_scalar( 's_country' );
	if ( $country === '' ) {
		$country = nh_checkout_posted_scalar( 'country' );
	}
	if ( $postcode !== '' && $country !== '' ) {
		$_POST['has_full_address'] = '1';
	}

	nh_checkout_flush_shipping_cache_for_destination( $country, $postcode );
}

/**
 * Pull a usable postcode from the current iframe refresh request.
 *
 * @param string $post_data Serialized checkout form.
 * @return string
 */
function nh_checkout_posted_iframe_zip( $post_data = '' ) {
	$zip = nh_checkout_usable_postcode( nh_checkout_posted_scalar( 'billing_postcode' ) );
	if ( $zip !== '' ) {
		return $zip;
	}

	$zip = nh_checkout_usable_postcode( nh_checkout_posted_scalar( 'postcode' ) );
	if ( $zip !== '' ) {
		return $zip;
	}

	if ( is_string( $post_data ) && $post_data !== '' ) {
		$form = array();
		parse_str( $post_data, $form );
		if ( ! empty( $form['billing_postcode'] ) && is_scalar( $form['billing_postcode'] ) ) {
			$zip = nh_checkout_usable_postcode( (string) $form['billing_postcode'] );
			if ( $zip !== '' ) {
				return $zip;
			}
		}
	}

	if ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form = array();
		parse_str( wp_unslash( $_POST['post_data'] ), $form ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $form['billing_postcode'] ) && is_scalar( $form['billing_postcode'] ) ) {
			return nh_checkout_usable_postcode( (string) $form['billing_postcode'] );
		}
	}

	return '';
}

/**
 * Country + ZIP is enough for snippet order-summary rates. Do not wait for
 * street/name/email from the iframe, and do not copy stale session address.
 *
 * @param string $post_data Serialized checkout form.
 */
function nh_checkout_apply_snippet_posted_zip( $post_data = '' ) {
	if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
		return;
	}

	$zip = nh_checkout_posted_iframe_zip( $post_data );
	if ( $zip === '' ) {
		return;
	}

	$country = nh_checkout_posted_scalar( 'billing_country' );
	if ( $country === '' ) {
		$country = nh_checkout_posted_scalar( 'country' );
	}
	if ( $country === '' ) {
		$country = (string) WC()->customer->get_shipping_country();
	}
	if ( $country === '' ) {
		$country = (string) WC()->customer->get_billing_country();
	}

	WC()->customer->set_billing_postcode( $zip );
	WC()->customer->set_shipping_postcode( $zip );
	if ( $country !== '' ) {
		WC()->customer->set_billing_country( $country );
		WC()->customer->set_shipping_country( $country );
	}
	WC()->customer->set_calculated_shipping( true );

	$_POST['billing_postcode'] = $zip; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$_POST['postcode']         = $zip; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$_POST['s_postcode']       = $zip; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$_POST['has_full_address'] = '1'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	nh_checkout_flush_shipping_cache_for_destination( $country, $zip );
}

/**
 * SVEA refresh_sco_snippet overlays BillingAddress from its server get(),
 * which stays empty until the customer is fully identified. Restore the ZIP
 * that was already posted from the iframe.
 *
 * @param WC_Customer          $customer Customer.
 * @param array<string, mixed> $data     Address payload from SVEA.
 */
function nh_checkout_reapply_posted_iframe_zip( $customer, $data = array() ) {
	if ( ! is_object( $customer ) || ! method_exists( $customer, 'set_billing_postcode' ) ) {
		return;
	}

	$zip = nh_checkout_posted_iframe_zip();
	if ( $zip === '' && is_array( $data ) ) {
		if ( ! empty( $data['billing_postcode'] ) ) {
			$zip = nh_checkout_usable_postcode( $data['billing_postcode'] );
		}
		if ( $zip === '' && ! empty( $data['shipping_postcode'] ) ) {
			$zip = nh_checkout_usable_postcode( $data['shipping_postcode'] );
		}
	}
	if ( $zip === '' ) {
		return;
	}

	$current_billing  = nh_checkout_usable_postcode( $customer->get_billing_postcode() );
	$current_shipping = nh_checkout_usable_postcode( $customer->get_shipping_postcode() );
	if ( $current_billing === $zip && $current_shipping === $zip ) {
		return;
	}

	try {
		$customer->set_billing_postcode( $zip );
		$customer->set_shipping_postcode( $zip );
		if ( method_exists( $customer, 'set_calculated_shipping' ) ) {
			$customer->set_calculated_shipping( true );
		}
		// SVEA owns this AJAX. save() on a guest session has fataled refresh_sco_snippet.
	} catch ( Throwable $e ) {
		return;
	}
}

/**
 * Copy Woo form identity onto the customer so Svea create() / Kustom update see it.
 *
 * @param array<string, mixed> $form Parsed checkout post_data.
 */
function nh_checkout_copy_posted_identity( $form = array() ) {
	$identity = nh_checkout_collect_posted_identity( $form );
	if ( ! $identity ) {
		return;
	}
	nh_checkout_store_identity( $identity );
	nh_checkout_apply_identity_to_customer( $identity, false );
}

/**
 * Svea refresh overlays BillingAddress/EmailAddress from its server get(), which
 * stays empty until the customer is identified in the iframe. Restore Woo form values.
 *
 * @param WC_Customer          $customer Customer.
 * @param array<string, mixed> $data     Address payload from SVEA.
 */
function nh_checkout_reapply_posted_woo_identity( $customer, $data = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	if ( ! is_object( $customer ) || ! method_exists( $customer, 'set_billing_email' ) ) {
		return;
	}

	$form = array();
	if ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		parse_str( wp_unslash( $_POST['post_data'] ), $form ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	$identity = nh_checkout_merged_identity( $form );
	if ( ! $identity ) {
		return;
	}

	nh_checkout_store_identity( $identity );

	try {
		if ( ! empty( $identity['billing_email'] ) && trim( (string) $customer->get_billing_email() ) === '' ) {
			$customer->set_billing_email( $identity['billing_email'] );
		}
		if ( ! empty( $identity['billing_phone'] ) && trim( (string) $customer->get_billing_phone() ) === '' ) {
			$customer->set_billing_phone( $identity['billing_phone'] );
			if ( method_exists( $customer, 'set_shipping_phone' ) && trim( (string) $customer->get_shipping_phone() ) === '' ) {
				$customer->set_shipping_phone( $identity['billing_phone'] );
			}
		}
		if ( ! empty( $identity['billing_first_name'] ) && method_exists( $customer, 'set_billing_first_name' ) && trim( (string) $customer->get_billing_first_name() ) === '' ) {
			$customer->set_billing_first_name( $identity['billing_first_name'] );
		}
		if ( ! empty( $identity['billing_last_name'] ) && method_exists( $customer, 'set_billing_last_name' ) && trim( (string) $customer->get_billing_last_name() ) === '' ) {
			$customer->set_billing_last_name( $identity['billing_last_name'] );
		}
	} catch ( Throwable $e ) {
		return;
	}
}

/**
 * After SVEA writes the iframe ZIP onto the Woo customer, drop cached rates
 * so zip-specific methods are rebuilt for that destination.
 *
 * @param WC_Customer          $customer Customer.
 * @param array<string, mixed> $data     Address payload from SVEA.
 */
function nh_checkout_on_iframe_customer_updated( $customer, $data = array() ) {
	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$country = isset( $data['shipping_country'] ) ? (string) $data['shipping_country'] : '';
	if ( $country === '' && is_object( $customer ) && method_exists( $customer, 'get_shipping_country' ) ) {
		$country = (string) $customer->get_shipping_country();
	}

	if ( function_exists( 'WC' ) && WC()->session && array_key_exists( 'is_company', $data ) ) {
		WC()->session->set( 'nh_iframe_customer_type', ! empty( $data['is_company'] ) ? 'business' : 'private' );
	}

	$postcode = isset( $data['shipping_postcode'] ) ? nh_checkout_usable_postcode( $data['shipping_postcode'] ) : '';
	if ( $postcode === '' && is_object( $customer ) && method_exists( $customer, 'get_shipping_postcode' ) ) {
		$postcode = nh_checkout_usable_postcode( $customer->get_shipping_postcode() );
	}

	if ( $country === '' && $postcode === '' ) {
		return;
	}

	if ( function_exists( 'WC' ) && WC()->session ) {
		WC()->session->set( 'nh_ship_dest', '' );
	}

	nh_checkout_flush_shipping_cache_for_destination( $country, $postcode );
}

/**
 * Drop cached package rates when the destination postcode changes.
 *
 * @param string $country  Shipping country.
 * @param string $postcode Shipping postcode.
 */
function nh_checkout_flush_shipping_cache_for_destination( $country, $postcode ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	$country  = strtoupper( trim( (string) $country ) );
	$postcode = (string) $postcode;
	if ( $country === '' && $postcode === '' ) {
		return;
	}

	$key  = $country . '|' . $postcode;
	$prev = (string) WC()->session->get( 'nh_ship_dest', '' );
	if ( $key === $prev ) {
		return;
	}

	WC()->session->set( 'nh_ship_dest', $key );

	if ( method_exists( WC()->session, 'get_session_data' ) ) {
		foreach ( array_keys( (array) WC()->session->get_session_data() ) as $session_key ) {
			if ( is_string( $session_key ) && strpos( $session_key, 'shipping_for_package_' ) === 0 ) {
				WC()->session->__unset( $session_key );
			}
		}
	} else {
		for ( $i = 0; $i < 10; $i++ ) {
			WC()->session->__unset( 'shipping_for_package_' . $i );
		}
	}
}

/**
 * SVEA map_shipping() fatals when a rate cost is "" (string + int).
 * Keep every package rate numeric before the snippet is built.
 *
 * @param array<string, mixed> $rates Package rates.
 * @return array<string, mixed>
 */
function nh_checkout_numeric_shipping_rate_costs( $rates ) {
	if ( ! is_array( $rates ) ) {
		return $rates;
	}

	foreach ( $rates as $rate ) {
		if ( ! is_object( $rate ) || ! method_exists( $rate, 'get_cost' ) || ! method_exists( $rate, 'set_cost' ) ) {
			continue;
		}
		$cost = $rate->get_cost();
		if ( $cost === '' || $cost === null || ! is_numeric( $cost ) ) {
			$rate->set_cost( 0 );
		}
		if ( ! method_exists( $rate, 'get_taxes' ) || ! method_exists( $rate, 'set_taxes' ) ) {
			continue;
		}
		$taxes = $rate->get_taxes();
		if ( ! is_array( $taxes ) ) {
			continue;
		}
		$clean = array();
		foreach ( $taxes as $id => $tax ) {
			$clean[ $id ] = is_numeric( $tax ) ? $tax : 0;
		}
		$rate->set_taxes( $clean );
	}

	return $rates;
}

/**
 * Zip-based rates should calculate once country + postcode exist, even if street is still in the iframe.
 *
 * @param bool $ready Woo default.
 * @return bool
 */
function nh_checkout_ready_to_calc_shipping( $ready ) {
	if ( $ready || ! function_exists( 'WC' ) || ! WC()->customer ) {
		return $ready;
	}

	$customer = WC()->customer;
	$postcode = nh_checkout_usable_postcode( $customer->get_shipping_postcode() );
	if ( $postcode === '' ) {
		$postcode = nh_checkout_usable_postcode( $customer->get_billing_postcode() );
	}
	$country = trim( (string) $customer->get_shipping_country() );
	if ( $country === '' ) {
		$country = trim( (string) $customer->get_billing_country() );
	}

	if ( $postcode !== '' && $country !== '' ) {
		return true;
	}

	return $ready;
}

/**
 * Do not tell iframe customers that no shipping exists before a postcode is known.
 *
 * @param string $html WooCommerce empty-shipping HTML.
 * @return string
 */
function nh_checkout_snippet_no_shipping_html( $html ) {
	if ( ! nh_checkout_is_snippet_gateway() || ! function_exists( 'WC' ) || ! WC()->customer ) {
		return $html;
	}

	$postcode = nh_checkout_usable_postcode( WC()->customer->get_shipping_postcode() );
	if ( $postcode === '' ) {
		$postcode = nh_checkout_usable_postcode( WC()->customer->get_billing_postcode() );
	}
	if ( $postcode !== '' ) {
		return $html;
	}

	return '<p>' . esc_html__( 'Enter your postcode to see the shipping cost.', 'nh-theme' ) . '</p>';
}

/**
 * Apply an iframe ZIP to the Woo customer and return the order-summary fragment.
 * Bypasses SVEA's empty BillingAddress get() so rates can show before full identity.
 */
function nh_checkout_ajax_snippet_apply_zip() {
	check_ajax_referer( 'nh-snippet-apply-zip', 'security' );

	if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->customer ) {
		wp_send_json_error();
	}

	wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

	$zip = nh_checkout_usable_postcode( nh_checkout_posted_scalar( 'postcode' ) );
	if ( $zip === '' ) {
		$zip = nh_checkout_usable_postcode( nh_checkout_posted_scalar( 'billing_postcode' ) );
	}
	if ( $zip === '' ) {
		wp_send_json_error();
	}

	$country = nh_checkout_posted_scalar( 'country' );
	if ( $country === '' ) {
		$country = nh_checkout_posted_scalar( 'billing_country' );
	}
	if ( $country === '' ) {
		$country = (string) WC()->customer->get_shipping_country();
	}
	if ( $country === '' ) {
		$country = (string) WC()->customer->get_billing_country();
	}

	WC()->customer->set_billing_postcode( $zip );
	WC()->customer->set_shipping_postcode( $zip );
	if ( $country !== '' ) {
		WC()->customer->set_billing_country( $country );
		WC()->customer->set_shipping_country( $country );
	}
	WC()->customer->set_calculated_shipping( true );
	WC()->customer->save();

	if ( WC()->session ) {
		WC()->session->set( 'nh_ship_dest', '' );
	}
	nh_checkout_flush_shipping_cache_for_destination( $country, $zip );

	WC()->cart->calculate_shipping();
	WC()->cart->calculate_totals();

	$template = function_exists( 'wc_locate_template' ) ? wc_locate_template( 'checkout/review-order.php' ) : '';
	ob_start();
	if ( $template ) {
		include $template;
	} elseif ( function_exists( 'woocommerce_order_review' ) ) {
		woocommerce_order_review();
	}
	$html = ob_get_clean();

	wp_send_json_success(
		array(
			'fragments' => array(
				'.woocommerce-checkout-review-order' => $html,
			),
		)
	);
}

/**
 * Stamp data-index before wc-checkout.js runs (it reads the attribute on first update_checkout).
 */
function nh_checkout_shipping_index_boot_script() {
	$on_checkout = nh_is_classic_checkout_form();
	$on_cart     = function_exists( 'is_cart' ) && is_cart();
	if ( ! $on_checkout && ! $on_cart ) {
		return;
	}
	echo '<script id="nh-shipping-index-boot">document.querySelectorAll("input.shipping_method,select.shipping_method").forEach(function(el){var n=el.getAttribute("name")||"",m=n.match(/shipping_method\\[(\\d+)\\]/);el.setAttribute("data-index",m?m[1]:(el.getAttribute("data-index")||"0"));});</script>' . "\n";
}

/**
 * CSS class for the checkout form: Svea JS binds to .wc-svea-checkout-page.
 *
 * @return string
 */
function nh_checkout_form_classes() {
	$classes = array( 'checkout', 'woocommerce-checkout', 'nh-checkout-form-el' );

	$checkout = function_exists( 'WC' ) && WC()->checkout() ? WC()->checkout() : null;
	if ( $checkout && 'business' === $checkout->get_value( 'billing_customer_type' ) ) {
		$classes[] = 'nh-checkout--business';
	}

	if ( nh_checkout_is_payment_step() ) {
		$classes[] = 'nh-checkout--step-payment';
	} else {
		$classes[] = 'nh-checkout--step-details';
	}

	if ( ! nh_checkout_should_load_iframe() ) {
		return implode( ' ', array_unique( $classes ) );
	}

	$classes[] = 'nh-checkout--snippet';
	$method    = strtolower( (string) nh_checkout_chosen_payment_method() );
	if ( $method === 'svea_checkout' || $method === 'sco' || $method === 'sveacheckout' || preg_match( '/svea.?checkout/', $method ) ) {
		$classes[] = 'svea-checkout';
		$classes[] = 'wc-svea-checkout-page';
	}
	if ( $method === 'kco' || $method === 'kustom_checkout' || $method === 'klarna_checkout' ) {
		$classes[] = 'kco-checkout';
	}

	return implode( ' ', array_unique( $classes ) );
}

/**
 * Svea / Kustom iframe under the payment radios. Kept outside #payment so
 * Woo's update_checkout fragment does not replace a live iframe.
 * Not printed until the customer is on the payment step and picked that method.
 */
function nh_checkout_render_gateway_iframe() {
	if ( ! nh_checkout_should_load_iframe() ) {
		return;
	}

	$identity = nh_checkout_prepare_snippet_identity();

	$method  = strtolower( (string) nh_checkout_chosen_payment_method() );
	$is_kco  = in_array( $method, array( 'kco', 'kustom_checkout', 'klarna_checkout' ), true );
	$is_svea = ( $method === 'svea_checkout' || $method === 'sco' || $method === 'sveacheckout' || (bool) preg_match( '/svea.?checkout/', $method ) );

	echo '<div class="nh-checkout-iframe" id="nh-checkout-iframe">';

	if ( $is_kco && function_exists( 'kco_wc_show_snippet' ) ) {
		nh_checkout_kustom_maybe_recreate_for_identity( $identity );
		echo '<div id="kco-wrapper" class="nh-checkout-iframe__kco"><div id="kco-iframe">';
		do_action( 'kco_wc_before_snippet' );
		kco_wc_show_snippet();
		do_action( 'kco_wc_after_snippet' );
		echo '</div></div>';
	} elseif ( $is_svea && class_exists( '\Svea_Checkout_For_Woocommerce\Template_Handler' ) ) {
		echo '<div class="wc-svea-checkout-checkout-module"><div id="svea-checkout-iframe-container">';
		echo '<div class="svea-skeleton-loader"><div class="svea-skeleton-loader__heading"></div><div class="svea-skeleton-loader__input"></div><div class="svea-skeleton-loader__input"></div><div class="svea-skeleton-loader__button"></div></div>';
		\Svea_Checkout_For_Woocommerce\Template_Handler::get_svea_snippet();
		echo '</div></div>';
	}

	echo '</div>';
}

/**
 * Beat Astra/Woo floats on #order_review (they shrink the summary to ~40% of the sidebar).
 * Also flatten the inner #order_review card so the summary is a single frame.
 */
function nh_checkout_layout_lock_css() {
	if ( ! nh_is_classic_checkout_form() ) {
		return;
	}
	echo '<style id="nh-checkout-layout-lock">'
		. 'html body.woocommerce-checkout.nh-checkout-form .nh-checkout-layout{display:flex!important;flex-direction:column;width:100%!important;max-width:100%!important;float:none!important}'
		. 'html body.woocommerce-checkout.nh-checkout-form .nh-checkout-layout__aside,'
		. 'html body.woocommerce-checkout.nh-checkout-form .nh-checkout-summary,'
		. 'html body.woocommerce-checkout.nh-checkout-form #order_review,'
		. 'html body.woocommerce-checkout.nh-checkout-form #order_review_heading,'
		. 'html body.woocommerce-checkout.nh-checkout-form .woocommerce-checkout-review-order{float:none!important;width:100%!important;max-width:100%!important}'
		. 'html body.woocommerce-checkout.nh-checkout-form .nh-checkout-summary #order_review,'
		. 'html body.woocommerce-checkout.nh-checkout-form .nh-checkout-summary #order_review_heading,'
		. 'html body.woocommerce-checkout.nh-checkout-form .nh-checkout-summary .woocommerce-checkout-review-order,'
		. 'html body.woocommerce-checkout.nh-checkout-form .nh-checkout-summary table.shop_table,'
		. 'html body.woocommerce-checkout.nh-checkout-form .nh-checkout-summary table.woocommerce-checkout-review-order-table{border:0!important;border-width:0!important;outline:0!important;box-shadow:none!important;background:transparent!important;padding:0!important;margin:0!important;border-radius:0!important;min-height:0!important}'
		. 'html body.woocommerce-checkout.nh-checkout-form .nh-checkout-summary #order_review_heading{padding:0 0 .75rem!important;margin:0!important}'
		. 'html body.woocommerce-checkout.nh-checkout-form .nh-checkout-summary #order_review_heading:before,'
		. 'html body.woocommerce-checkout.nh-checkout-form .nh-checkout-summary #order_review_heading:after{content:none!important;display:none!important;border:0!important}'
		. 'html body.woocommerce-checkout.nh-checkout-form #order_review table.shop_table,'
		. 'html body.woocommerce-checkout.nh-checkout-form table.woocommerce-checkout-review-order-table{display:table!important;width:100%!important;max-width:100%!important;table-layout:auto!important;float:none!important}'
		. 'html body.woocommerce-checkout.nh-checkout-form #order_review table.shop_table tr{display:table-row!important}'
		. 'html body.woocommerce-checkout.nh-checkout-form #order_review table.shop_table th,'
		. 'html body.woocommerce-checkout.nh-checkout-form #order_review table.shop_table td{display:table-cell!important;float:none!important}'
		. '@media(min-width:960px){'
		. 'html body.woocommerce-checkout.nh-checkout-form .nh-checkout-layout{display:grid!important;grid-template-columns:minmax(0,1fr) 400px!important;align-items:start}'
		. 'html body.woocommerce-checkout.nh-checkout-form .nh-checkout-layout__aside{width:400px!important;max-width:400px!important;min-width:400px!important;flex:0 0 400px!important}'
		. 'html body.woocommerce-checkout.nh-checkout-form .nh-checkout-layout__main{min-width:0!important;width:auto!important;max-width:none!important}'
		. '}'
		. 'html body.woocommerce-checkout .nh-checkout-layout__aside .nh-checkout-secure,'
		. 'html body.woocommerce-checkout .nh-checkout-layout__aside #billing_customer_type_field,'
		. 'html body.woocommerce-checkout .nh-checkout-layout__aside .nh-checkout-type,'
		. 'html body.woocommerce-checkout .nh-checkout-layout__aside .nh-notes,'
		. 'html body.woocommerce-checkout .nh-checkout-layout__aside .woocommerce-additional-fields{display:none!important}'
		. 'html body.woocommerce-checkout.nh-checkout--snippet #payment .form-row.place-order{display:none!important}'
		. 'html body.woocommerce-checkout .nh-checkout-other-payment,'
		. 'html body.woocommerce-checkout .nh-checkout-other-payment-btn,'
		. 'html body.woocommerce-checkout #klarna-checkout-select-other,'
		. 'html body.woocommerce-checkout #svea-checkout-select-other,'
		. 'html body.woocommerce-checkout #sco-change-payment,'
		. 'html body.woocommerce-checkout .kco-select-another-method,'
		. 'html body.woocommerce-checkout a.sco-change-payment-method{display:none!important}'
		. '@media(max-width:959px){'
		. 'html body.woocommerce-checkout.nh-checkout-form.nh-checkout--step-payment .nh-checkout-layout__main{order:-1!important}'
		. 'html body.woocommerce-checkout.nh-checkout-form.nh-checkout--step-payment .nh-checkout-layout__aside{order:2!important}'
		. 'html body.woocommerce-checkout .site-content>.ast-container,'
		. 'html body.woocommerce-checkout.ast-separate-container .ast-container,'
		. 'html body.woocommerce-checkout.ast-plain-container .ast-container{padding-left:10px!important;padding-right:10px!important}'
		. 'html body.woocommerce-checkout #primary,'
		. 'html body.woocommerce-checkout .ast-article-single,'
		. 'html body.woocommerce-checkout .entry-content,'
		. 'html body.woocommerce-checkout .woocommerce{padding-left:0!important;padding-right:0!important;margin-left:0!important;margin-right:0!important}'
		. '}'
		. '</style>' . "\n";
}

