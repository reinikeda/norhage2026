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
function nh_checkout_chosen_payment_method() {
	if ( isset( $_POST['payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return sanitize_text_field( wp_unslash( $_POST['payment_method'] ) );
	}
	if ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form = array();
		parse_str( wp_unslash( $_POST['post_data'] ), $form ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $form['payment_method'] ) && is_scalar( $form['payment_method'] ) ) {
			return sanitize_text_field( (string) $form['payment_method'] );
		}
	}
	if ( function_exists( 'WC' ) && WC()->session ) {
		return (string) WC()->session->get( 'chosen_payment_method' );
	}
	return '';
}

/**
 * SVEA / Kustom / Klarna Checkout replace the Woo form with their iframe.
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

	add_filter( 'woocommerce_default_address_fields', 'nh_checkout_default_address_fields', 20 );
	add_filter( 'woocommerce_get_country_locale', 'nh_checkout_country_locale', 20 );
	add_filter( 'woocommerce_billing_fields', 'nh_checkout_billing_fields', 20 );
	add_filter( 'woocommerce_checkout_fields', 'nh_checkout_fields', 999 );
	add_filter( 'woocommerce_form_field_args', 'nh_checkout_form_field_args', 99, 3 );
	add_filter( 'woocommerce_form_field_nh_section', 'nh_checkout_section_field', 10, 4 );
	add_filter( 'woocommerce_form_field_tel', 'nh_checkout_phone_field_html', 10, 4 );
	add_filter( 'woocommerce_checkout_get_value', 'nh_checkout_get_value', 10, 2 );
	add_filter( 'woocommerce_checkout_posted_data', 'nh_checkout_normalize_posted_phones', 20 );

	add_action( 'woocommerce_after_checkout_validation', 'nh_checkout_validate_fields', 20, 2 );
	add_action( 'woocommerce_checkout_create_order', 'nh_checkout_save_order_meta', 20, 2 );
	add_action( 'woocommerce_checkout_update_customer', 'nh_checkout_save_customer_meta', 20, 2 );

	add_filter( 'woocommerce_order_formatted_billing_address', 'nh_checkout_formatted_billing_address', 20, 2 );
	add_filter( 'woocommerce_email_customer_details_fields', 'nh_checkout_email_contact_fields', 20, 3 );
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'nh_checkout_admin_billing_meta', 10, 1 );
	add_action( 'woocommerce_order_details_after_customer_details', 'nh_checkout_order_contact_details', 10, 1 );
	add_action( 'wpo_wcpdf_after_billing_address', 'nh_checkout_pdf_reg_number', 10, 2 );
	add_action( 'woocommerce_review_order_after_submit', 'nh_checkout_secure_note', 8 );
	add_action( 'wp_footer', 'nh_checkout_layout_lock_css', 1 );
	add_action( 'wp_footer', 'nh_checkout_shipping_index_boot_script', 1 );
	add_action( 'woocommerce_checkout_update_order_review', 'nh_checkout_sanitize_posted_shipping', 1 );
	add_filter( 'woocommerce_checkout_fields', 'nh_checkout_strip_snippet_fields', 10000 );
	add_filter( 'woocommerce_enable_order_notes_field', 'nh_checkout_snippet_disable_order_notes', 10000 );
	add_filter( 'kco_ignored_checkout_fields', 'nh_checkout_snippet_ignored_fields' );
	add_filter( 'kco_wc_ignored_order_fields', 'nh_checkout_snippet_ignored_fields' );
	add_filter( 'kco_ignored_field_names', 'nh_checkout_snippet_ignored_fields' );
	add_filter( 'sco_ignored_checkout_fields', 'nh_checkout_snippet_ignored_fields' );
	add_filter( 'svea_checkout_ignored_fields', 'nh_checkout_snippet_ignored_fields' );
	add_filter( 'svea_wc_ignored_checkout_fields', 'nh_checkout_snippet_ignored_fields' );
	add_filter( 'woocommerce_svea_checkout_ignored_fields', 'nh_checkout_snippet_ignored_fields' );
}

/**
 * Snippet checkouts collect address in their iframe — drop our extra Woo fields.
 *
 * @param array $fields Checkout fieldsets.
 * @return array
 */
function nh_checkout_strip_snippet_fields( $fields ) {
	if ( ! nh_checkout_is_snippet_gateway() || ! is_array( $fields ) ) {
		return $fields;
	}
	if ( isset( $fields['billing'] ) && is_array( $fields['billing'] ) ) {
		unset(
			$fields['billing']['billing_customer_type'],
			$fields['billing']['nh_section_person']
		);
	}
	if ( isset( $fields['order'] ) && is_array( $fields['order'] ) ) {
		unset( $fields['order']['order_comments'] );
	}
	return $fields;
}

/**
 * @param bool $enabled Whether Woo should print order notes.
 * @return bool
 */
function nh_checkout_snippet_disable_order_notes( $enabled ) {
	if ( nh_checkout_is_snippet_gateway() ) {
		return false;
	}
	return $enabled;
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
	}
	if ( nh_checkout_is_snippet_gateway() ) {
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
		)
	);
}

/**
 * Keep line items in the summary column; payment stays under the form.
 * Skip if a gateway already removed the default payment action.
 */
function nh_checkout_split_review_and_payment() {
	if ( ! nh_is_classic_checkout_form() ) {
		return;
	}
	if ( ! has_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment' ) ) {
		return;
	}
	remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
	add_action( 'nh_checkout_payment', 'woocommerce_checkout_payment', 10 );
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

	if ( nh_checkout_is_snippet_gateway() ) {
		unset( $billing['billing_customer_type'], $billing['nh_section_person'] );
		if ( isset( $fields['order'] ) && is_array( $fields['order'] ) ) {
			unset( $fields['order']['order_comments'] );
		}
	} else {
		$billing['billing_customer_type'] = array(
			'type'     => 'radio',
			'label'    => __( 'I am ordering as', 'nh-theme' ),
			'required' => true,
			'class'    => array( 'form-row-wide', 'nh-checkout-type' ),
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
	}

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
		'class'        => array( 'form-row-first', 'nh-checkout-pair-start', 'nh-checkout-field--contact' ),
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
		'class'        => array( 'form-row-last', 'nh-checkout-pair-end', 'nh-checkout-field--contact' ),
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

	if ( in_array( $input, array( 'billing_company_reg', 'billing_contact_email', 'billing_contact_phone' ), true ) && ( $value === null || $value === '' ) && is_user_logged_in() ) {
		return (string) get_user_meta( get_current_user_id(), $input, true );
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
	if ( nh_checkout_is_snippet_gateway() ) {
		return;
	}

	$email = isset( $data['billing_email'] ) ? trim( (string) $data['billing_email'] ) : '';
	$phone = isset( $data['billing_phone'] ) ? trim( (string) $data['billing_phone'] ) : '';
	$first = isset( $data['billing_first_name'] ) ? trim( (string) $data['billing_first_name'] ) : '';
	$last  = isset( $data['billing_last_name'] ) ? trim( (string) $data['billing_last_name'] ) : '';
	$type  = nh_checkout_posted_type( $data );

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

		if ( $company === '' ) {
			$errors->add( 'billing_company', __( 'Please enter your business name.', 'nh-theme' ) );
		}
		if ( $reg === '' ) {
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

	if ( $first === '' ) {
		$errors->add( 'billing_first_name', __( 'Please enter a first name.', 'nh-theme' ) );
	}
	if ( $last === '' ) {
		$errors->add( 'billing_last_name', __( 'Please enter a last name.', 'nh-theme' ) );
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
		. 'html body.woocommerce-checkout.nh-checkout--snippet .nh-checkout-secure,'
		. 'html body.woocommerce-checkout.nh-checkout--snippet #billing_customer_type_field,'
		. 'html body.woocommerce-checkout.nh-checkout--snippet .nh-checkout-type,'
		. 'html body.woocommerce-checkout.nh-checkout--snippet .nh-notes,'
		. 'html body.woocommerce-checkout.nh-checkout--snippet .woocommerce-additional-fields,'
		. 'html body.woocommerce-checkout .nh-checkout-layout__aside .nh-checkout-secure,'
		. 'html body.woocommerce-checkout .nh-checkout-layout__aside #billing_customer_type_field,'
		. 'html body.woocommerce-checkout .nh-checkout-layout__aside .nh-checkout-type,'
		. 'html body.woocommerce-checkout .nh-checkout-layout__aside .nh-notes,'
		. 'html body.woocommerce-checkout .nh-checkout-layout__aside .woocommerce-additional-fields{display:none!important}'
		. 'html body.woocommerce-checkout .nh-checkout-other-payment:not([hidden]),'
		. 'html body.woocommerce-checkout.nh-checkout--snippet .nh-checkout-other-payment-btn{display:flex!important;align-items:center;justify-content:center;width:100%!important;min-height:52px;margin:.85rem 0 0;padding:.85rem 1rem;border:0;border-radius:12px;background:#00704a!important;color:#fff!important;font-weight:800;font-size:1.05rem;text-align:center;text-decoration:none!important;cursor:pointer}'
		. '@media(max-width:959px){'
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
