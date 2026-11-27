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
 * Normalize posted/session values to bool.
 *
 * @param mixed $value Raw value.
 * @return bool
 */
function nh_checkout_newsletter_value_is_on( $value ) {
	if ( is_bool( $value ) ) {
		return $value;
	}
	$value = is_string( $value ) ? strtolower( trim( $value ) ) : $value;
	return in_array( $value, array( '1', 1, 'yes', 'true', 'on' ), true );
}

/**
 * Whether the shopper opted in (POST or WC session).
 * Defaults to checked until the shopper explicitly turns it off.
 *
 * @return bool
 */
function nh_checkout_newsletter_is_checked() {
	$name = nh_checkout_newsletter_field_name();

	if ( isset( $_POST[ $name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return nh_checkout_newsletter_value_is_on( wp_unslash( $_POST[ $name ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	if ( function_exists( 'WC' ) && WC()->session ) {
		$session = WC()->session->get( nh_checkout_newsletter_session_key() );
		if ( 'yes' === $session ) {
			return true;
		}
		if ( 'no' === $session ) {
			return false;
		}
	}

	return true;
}

/**
 * Persist opt-in during update_order_review (keeps state across payment AJAX).
 *
 * A hidden 0 + checkbox 1 pair is always posted so “unchecked” is distinguishable
 * from “field not on the form yet”.
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

	$name = nh_checkout_newsletter_field_name();
	if ( ! array_key_exists( $name, $data ) ) {
		// Newsletter markup not in this request — keep default / existing session.
		return;
	}

	$on = nh_checkout_newsletter_value_is_on( $data[ $name ] );
	WC()->session->set( nh_checkout_newsletter_session_key(), $on ? 'yes' : 'no' );
}
add_action( 'woocommerce_checkout_update_order_review', 'nh_checkout_newsletter_capture_from_review', 20 );

/**
 * Render the compact opt-in markup (two lines).
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
	<section class="nh-checkout-newsletter-block" id="nh-checkout-newsletter" aria-label="<?php echo esc_attr__( 'Newsletter', 'nh-theme' ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
		<label class="nh-checkout-newsletter__label" for="<?php echo esc_attr( $id ); ?>">
			<input
				type="checkbox"
				class="nh-checkout-newsletter__input"
				name="<?php echo esc_attr( $name ); ?>"
				id="<?php echo esc_attr( $id ); ?>"
				value="1"
				<?php checked( $checked, true ); ?>
			/>
			<span class="nh-checkout-newsletter__copy">
				<span class="nh-checkout-newsletter__text"><?php esc_html_e( 'Yes, email me offers and gardening tips', 'nh-theme' ); ?></span>
				<span class="nh-checkout-newsletter__hint"><?php esc_html_e( 'Optional. Uses your order email — unsubscribe anytime.', 'nh-theme' ); ?></span>
			</span>
		</label>
	</section>
	<?php
}

/**
 * Classic checkout: own card above Place order (inside #payment fragment).
 */
function nh_checkout_render_newsletter_before_place_order() {
	if ( function_exists( 'nh_checkout_should_load_iframe' ) && nh_checkout_should_load_iframe() ) {
		return;
	}
	nh_checkout_render_newsletter_optin();
}

/**
 * Snippet (Svea/Kustom): Place order is hidden; show above the iframe.
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

	$name     = nh_checkout_newsletter_field_name();
	$opted_in = false;
	if ( isset( $_POST[ $name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$opted_in = nh_checkout_newsletter_value_is_on( wp_unslash( $_POST[ $name ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	} elseif ( function_exists( 'WC' ) && WC()->session ) {
		$session = WC()->session->get( nh_checkout_newsletter_session_key() );
		if ( null === $session || '' === $session ) {
			$opted_in = true; // default on
		} else {
			$opted_in = ( 'yes' === $session );
		}
	} else {
		$opted_in = true;
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
