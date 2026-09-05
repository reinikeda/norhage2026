<?php
/**
 * Pure classifier for unpaid Svea Checkout Woo orders.
 *
 * WooCommerce only creates a Svea order after the customer clicks Complete
 * purchase (client-side validation). A later pending → cancelled transition
 * is therefore an abandoned *payment*, not an abandoned cart.
 *
 * @package Astra Custom for Norhage
 */

if ( ! defined( 'ABSPATH' ) && php_sapi_name() !== 'cli' ) {
	exit;
}

/**
 * @param array $facts {
 *     @type int    $validation_attempts  How many times Woo processed the pay click.
 *     @type bool   $push_finalized       Svea Final push reached Woo.
 *     @type string $checkout_status      Created|Pending|Final|Cancelled|…
 *     @type string $payment_admin_status Open|Delivered|Cancelled|Failed|Expired|…
 *     @type array  $client_events        Optional JS event names.
 * }
 * @return array{code:string,severity:string,summary:string}
 */
function nh_svea_classify_unpaid_outcome( $facts ) {
	$facts = is_array( $facts ) ? $facts : array();

	$attempts = isset( $facts['validation_attempts'] ) ? (int) $facts['validation_attempts'] : 0;
	$finalized = ! empty( $facts['push_finalized'] );
	$checkout  = strtoupper( (string) ( $facts['checkout_status'] ?? '' ) );
	$pa        = strtoupper( (string) ( $facts['payment_admin_status'] ?? '' ) );
	$events    = isset( $facts['client_events'] ) && is_array( $facts['client_events'] )
		? $facts['client_events']
		: array();

	if ( $finalized || $checkout === 'FINAL' || in_array( $pa, array( 'OPEN', 'DELIVERED' ), true ) ) {
		return array(
			'code'     => 'paid_missed_push',
			'severity' => 'bug',
			'summary'  => 'Svea has this as paid (Checkout Final / Payment Admin Open), but WooCommerce never received or processed the payment push. Not an abandoned cart.',
		);
	}

	if ( in_array( $pa, array( 'FAILED', 'EXPIRED' ), true ) ) {
		return array(
			'code'     => 'payment_declined',
			'severity' => 'expected',
			'summary'  => 'Customer clicked Complete purchase, then Svea marked the payment failed or expired (BankID/card/Swish declined or timed out). Nothing was captured.',
		);
	}

	if ( $checkout === 'CANCELLED' || $pa === 'CANCELLED' ) {
		return array(
			'code'     => 'svea_cancelled',
			'severity' => 'expected',
			'summary'  => 'Svea cancelled the checkout session. WooCommerce is matching that. Payment was never captured.',
		);
	}

	$iframe_error = false;
	foreach ( $events as $event ) {
		$name = is_array( $event ) ? (string) ( $event['event'] ?? '' ) : (string) $event;
		if ( in_array( $name, array( 'confirm_order_failed', 'validation_failed', 'client_validation_timeout' ), true ) ) {
			$iframe_error = true;
			break;
		}
	}

	if ( $iframe_error ) {
		return array(
			'code'     => 'iframe_error',
			'severity' => 'check',
			'summary'  => 'Customer clicked Complete purchase, Woo accepted the order, then the Svea iframe reported confirmOrder/validation failure. Check WAF/Cloudflare and Svea confirmOrder 400s before treating this as an abandoned payment.',
		);
	}

	if ( $attempts >= 1 && in_array( $checkout, array( 'CREATED', 'PENDING', '' ), true ) ) {
		$retry = $attempts > 1
			? sprintf( ' Customer retried Complete purchase %d times.', $attempts )
			: '';
		return array(
			'code'     => 'payment_abandoned',
			'severity' => 'expected',
			'summary'  => 'Customer clicked Complete purchase so Woo created a pending order, but Svea never reached Final (no capture, nothing in Payment Admin). Typical BankID/card/Swish abandon or timeout.' . $retry,
		);
	}

	return array(
		'code'     => 'unknown',
		'severity' => 'check',
		'summary'  => 'Could not classify this unpaid Svea order. Check the Svea checkout log for validation vs Final push, and the Svea order status in Payment Admin.',
	);
}
