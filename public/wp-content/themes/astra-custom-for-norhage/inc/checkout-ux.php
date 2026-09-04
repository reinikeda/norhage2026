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

function nh_checkout_ux_init() {
	add_filter( 'render_block', 'nh_checkout_render_classic_block', 5, 2 );
	add_filter( 'body_class', 'nh_checkout_ux_body_class' );
	add_action( 'wp_enqueue_scripts', 'nh_checkout_ux_assets', 100 );
	add_action( 'wp', 'nh_checkout_split_review_and_payment', 20 );

	add_filter( 'woocommerce_default_address_fields', 'nh_checkout_default_address_fields', 20 );
	add_filter( 'woocommerce_get_country_locale', 'nh_checkout_country_locale', 20 );
	add_filter( 'woocommerce_billing_fields', 'nh_checkout_billing_fields', 20 );
	add_filter( 'woocommerce_checkout_fields', 'nh_checkout_fields', 99 );
	add_filter( 'woocommerce_form_field_nh_section', 'nh_checkout_section_field', 10, 4 );
	add_filter( 'woocommerce_checkout_get_value', 'nh_checkout_get_value', 10, 2 );

	add_action( 'woocommerce_after_checkout_validation', 'nh_checkout_validate_fields', 20, 2 );
	add_action( 'woocommerce_checkout_create_order', 'nh_checkout_save_order_meta', 20, 2 );
	add_action( 'woocommerce_checkout_update_customer', 'nh_checkout_save_customer_meta', 20, 2 );

	add_filter( 'woocommerce_order_formatted_billing_address', 'nh_checkout_formatted_billing_address', 20, 2 );
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'nh_checkout_admin_billing_meta', 10, 1 );
	add_action( 'wpo_wcpdf_after_billing_address', 'nh_checkout_pdf_reg_number', 10, 2 );
	add_action( 'woocommerce_review_order_after_submit', 'nh_checkout_secure_note', 8 );
	add_action( 'wp_footer', 'nh_checkout_layout_lock_css', 1 );
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
	}
	if ( isset( $fields['postcode'] ) ) {
		$fields['postcode']['priority'] = 50;
		$fields['postcode']['class']    = array( 'form-row-wide', 'address-field', 'update_totals_on_change' );
	}
	if ( isset( $fields['address_1'] ) ) {
		$fields['address_1']['priority'] = 60;
	}
	if ( isset( $fields['city'] ) ) {
		$fields['city']['priority'] = 80;
		$fields['city']['class']    = array( 'form-row-wide', 'address-field' );
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
		$locale[ $country ]['postcode']['priority']  = 50;
		$locale[ $country ]['postcode']['class']     = array( 'form-row-wide' );
		$locale[ $country ]['address_1']['priority'] = 60;
		$locale[ $country ]['address_2']['priority'] = 70;
		$locale[ $country ]['city']['priority']      = 80;
		$locale[ $country ]['city']['class']         = array( 'form-row-wide' );
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
		'class'        => array( 'form-row-first', 'nh-checkout-field--person' ),
		'autocomplete' => 'given-name',
		'priority'     => 30,
	) );

	nh_checkout_set_field( $billing, 'billing_last_name', array(
		'required'     => false,
		'class'        => array( 'form-row-last', 'nh-checkout-field--person' ),
		'autocomplete' => 'family-name',
		'priority'     => 32,
	) );

	nh_checkout_set_field( $billing, 'billing_email', array(
		'required'     => true,
		'class'        => array( 'form-row-first', 'nh-checkout-field--contact' ),
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
		'class'        => array( 'form-row-last', 'nh-checkout-field--contact' ),
		'validate'     => array( 'phone' ),
		'autocomplete' => 'tel',
		'priority'     => 36,
		'custom_attributes' => array(
			'inputmode' => 'tel',
		),
	) );

	nh_checkout_set_field( $billing, 'billing_country', array(
		'class'        => array( 'form-row-wide', 'address-field', 'update_totals_on_change' ),
		'autocomplete' => 'country',
		'priority'     => 40,
	) );

	nh_checkout_set_field( $billing, 'billing_postcode', array(
		'class'        => array( 'form-row-wide', 'address-field', 'update_totals_on_change' ),
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
		'class'        => array( 'form-row-wide', 'address-field' ),
		'autocomplete' => 'address-level2',
		'priority'     => 80,
	) );

	nh_checkout_set_field( $billing, 'billing_state', array(
		'required'     => false,
		'class'        => array( 'form-row-wide', 'address-field' ),
		'autocomplete' => 'address-level1',
		'priority'     => 90,
	) );

	if ( function_exists( 'wc_checkout_fields_uasort_comparison' ) ) {
		uasort( $billing, 'wc_checkout_fields_uasort_comparison' );
	}

	if ( ! empty( $fields['shipping'] ) && is_array( $fields['shipping'] ) ) {
		nh_checkout_set_field( $fields['shipping'], 'shipping_country', array(
			'priority' => 40,
		) );
		nh_checkout_set_field( $fields['shipping'], 'shipping_postcode', array(
			'class'    => array( 'form-row-wide', 'address-field', 'update_totals_on_change' ),
			'priority' => 50,
		) );
		nh_checkout_set_field( $fields['shipping'], 'shipping_address_1', array(
			'priority' => 60,
		) );
		nh_checkout_set_field( $fields['shipping'], 'shipping_address_2', array(
			'priority' => 70,
		) );
		nh_checkout_set_field( $fields['shipping'], 'shipping_city', array(
			'class'    => array( 'form-row-wide', 'address-field' ),
			'priority' => 80,
		) );
		nh_checkout_set_field( $fields['shipping'], 'shipping_state', array(
			'required' => false,
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

	if ( 'billing_company_reg' === $input && ( $value === null || $value === '' ) && is_user_logged_in() ) {
		return (string) get_user_meta( get_current_user_id(), 'billing_company_reg', true );
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
		return;
	}

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
		return;
	}

	$customer->set_billing_company( '' );
	$customer->update_meta_data( 'billing_company_reg', '' );
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
	echo '<p class="nh-checkout-secure">' . esc_html__( 'Secure checkout', 'nh-theme' ) . '</p>';
}

/**
 * Beat Astra/Woo floats on #order_review (they shrink the summary to ~40% of the sidebar).
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
		. '</style>' . "\n";
}
