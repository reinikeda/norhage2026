<?php
/**
 * Classic checkout newsletter opt-in (Sender.net).
 *
 * Not available via WooCommerce settings — custom checkbox above Place order
 * (and above the Svea/Kustom iframe when that path is used).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field name posted with the checkout form.
 */
function nh_checkout_newsletter_field_name() {
	return 'nh_newsletter_optin';
}

/**
 * Session key for snippet gateways that never hit Place order.
 */
function nh_checkout_newsletter_session_key() {
	return 'nh_newsletter_optin';
}

/**
 * Whether the shopper opted in (POST or WC session).
 *
 * @return bool
 */
function nh_checkout_newsletter_is_checked() {
	$name = nh_checkout_newsletter_field_name();

	if ( isset( $_POST[ $name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return ! empty( $_POST[ $name ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	if ( function_exists( 'WC' ) && WC()->session ) {
		return 'yes' === WC()->session->get( nh_checkout_newsletter_session_key() );
	}

	return false;
}

/**
 * Persist opt-in during update_order_review (keeps state across payment AJAX).
 *
 * @param string $post_data Serialized checkout form from Woo.
 */
function nh_checkout_newsletter_capture_from_review( $post_data ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	$data = array();
	if ( is_string( $post_data ) && $post_data !== '' ) {
		parse_str( $post_data, $data );
	}

	$name  = nh_checkout_newsletter_field_name();
	$on    = ! empty( $data[ $name ] );
	WC()->session->set( nh_checkout_newsletter_session_key(), $on ? 'yes' : 'no' );
}
add_action( 'woocommerce_checkout_update_order_review', 'nh_checkout_newsletter_capture_from_review', 20 );

/**
 * Render the opt-in checkbox markup.
 */
function nh_checkout_render_newsletter_optin() {
	if ( ! function_exists( 'nh_checkout_is_order_review_context' ) || ! nh_checkout_is_order_review_context() ) {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return;
		}
	}

	$name    = nh_checkout_newsletter_field_name();
	$checked = nh_checkout_newsletter_is_checked();
	$id      = 'nh-checkout-newsletter-optin';
	?>
	<div class="nh-checkout-newsletter form-row" id="nh-checkout-newsletter">
		<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox nh-checkout-newsletter__label" for="<?php echo esc_attr( $id ); ?>">
			<input
				type="checkbox"
				class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
				name="<?php echo esc_attr( $name ); ?>"
				id="<?php echo esc_attr( $id ); ?>"
				value="1"
				<?php checked( $checked, true ); ?>
			/>
			<span class="nh-checkout-newsletter__text">
				<?php esc_html_e( 'Yes, email me offers and gardening tips', 'nh-theme' ); ?>
			</span>
		</label>
		<p class="nh-checkout-newsletter__hint">
			<?php esc_html_e( 'Optional. Uses the email from your order. Unsubscribe anytime.', 'nh-theme' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Classic Place order path — directly above the pay button.
 */
add_action( 'woocommerce_review_order_before_submit', 'nh_checkout_render_newsletter_optin', 8 );

/**
 * Snippet (Svea/Kustom) path — Place order is hidden; show above the iframe.
 */
function nh_checkout_render_newsletter_optin_for_snippet() {
	if ( ! function_exists( 'nh_checkout_should_load_iframe' ) || ! nh_checkout_should_load_iframe() ) {
		return;
	}
	nh_checkout_render_newsletter_optin();
}

/**
 * Subscribe after the order is created when the box was ticked.
 *
 * @param int|\WC_Order $order_id Order ID or order object.
 */
function nh_checkout_newsletter_maybe_subscribe( $order_id ) {
	if ( ! function_exists( 'wc_get_order' ) ) {
		return;
	}

	$order = is_object( $order_id ) && is_a( $order_id, 'WC_Order' )
		? $order_id
		: wc_get_order( (int) $order_id );
	if ( ! $order ) {
		return;
	}
	$order_id = (int) $order->get_id();
	if ( $order_id <= 0 ) {
		return;
	}

	if ( 'yes' === $order->get_meta( '_nh_newsletter_optin_processed' ) ) {
		return;
	}

	$opted_in = false;
	$name     = nh_checkout_newsletter_field_name();
	if ( isset( $_POST[ $name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$opted_in = ! empty( $_POST[ $name ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	} elseif ( function_exists( 'WC' ) && WC()->session ) {
		$opted_in = 'yes' === WC()->session->get( nh_checkout_newsletter_session_key() );
	}

	$order->update_meta_data( '_nh_newsletter_optin', $opted_in ? 'yes' : 'no' );
	$order->update_meta_data( '_nh_newsletter_optin_processed', 'yes' );
	$order->save();

	if ( function_exists( 'WC' ) && WC()->session ) {
		WC()->session->set( nh_checkout_newsletter_session_key(), null );
	}

	if ( ! $opted_in || ! function_exists( 'nh_sender_subscribe_email' ) ) {
		return;
	}

	$email = $order->get_billing_email();
	if ( ! $email ) {
		return;
	}

	$result = nh_sender_subscribe_email( $email );
	if ( is_wp_error( $result ) ) {
		$order->add_order_note(
			sprintf(
				/* translators: %s: error message from Sender.net */
				__( 'Newsletter subscribe failed: %s', 'nh-theme' ),
				$result->get_error_message()
			)
		);
		return;
	}

	$order->add_order_note( __( 'Customer opted in to the newsletter (Sender.net).', 'nh-theme' ) );
}
add_action( 'woocommerce_checkout_order_processed', 'nh_checkout_newsletter_maybe_subscribe', 20, 1 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'nh_checkout_newsletter_maybe_subscribe', 20, 1 );
