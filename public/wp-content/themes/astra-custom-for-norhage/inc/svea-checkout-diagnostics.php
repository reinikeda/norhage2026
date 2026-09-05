<?php
/**
 * Trace Svea Checkout pay-clicks vs payment capture so pending→cancelled
 * orders can be told apart from a missed push / iframe bug.
 *
 * @package Astra Custom for Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_stylesheet_directory() . '/inc/svea-checkout-classify.php';

/**
 * Gateway ids used by Svea Checkout for WooCommerce.
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function nh_svea_is_svea_order( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}
	$method = strtolower( (string) $order->get_payment_method() );
	if ( in_array( $method, array( 'svea_checkout', 'sco', 'sveacheckout' ), true ) ) {
		return true;
	}
	return (string) $order->get_meta( '_svea_co_order_id' ) !== '';
}

/**
 * @param string $short Class short name.
 * @return string Fully-qualified class if it exists.
 */
function nh_svea_plugin_class( $short ) {
	foreach ( array(
		'Svea_Checkout_For_Woocommerce\\' . $short,
		'Svea_Checkout_For_WooCommerce\\' . $short,
		$short,
	) as $class ) {
		if ( class_exists( $class ) ) {
			return $class;
		}
	}
	return '';
}

function nh_svea_log( $message, $level = 'info' ) {
	if ( function_exists( 'wc_get_logger' ) ) {
		wc_get_logger()->log(
			$level,
			is_string( $message ) ? $message : wp_json_encode( $message ),
			array( 'source' => 'nh-svea-diagnostics' )
		);
	}
}

/**
 * @param WC_Order $order Order.
 * @param string   $key   Meta key.
 * @param mixed    $value Value to append.
 */
function nh_svea_append_meta_list( $order, $key, $value ) {
	$list = $order->get_meta( $key );
	if ( ! is_array( $list ) ) {
		$list = array();
	}
	$list[] = $value;
	$order->update_meta_data( $key, $list );
}

function nh_svea_diagnostics_init() {
	add_action( 'woocommerce_sco_checkout_send_checkout_result', 'nh_svea_on_pay_click', 10, 2 );
	add_action( 'woocommerce_sco_validation_after', 'nh_svea_on_server_validation', 10, 1 );
	add_action( 'woocommerce_sco_after_push_order_final', 'nh_svea_on_push_final', 10, 2 );
	add_action( 'woocommerce_sco_process_push_before', 'nh_svea_on_push_seen', 10, 1 );
	add_filter( 'woocommerce_cancel_unpaid_order', 'nh_svea_filter_cancel_unpaid', 5, 2 );
	add_action( 'woocommerce_order_status_cancelled', 'nh_svea_on_cancelled', 20, 2 );
	add_filter( 'woocommerce_email_enabled_customer_cancelled_order', 'nh_svea_skip_abandoned_cancel_email', 10, 2 );
	add_action( 'add_meta_boxes', 'nh_svea_register_trace_metabox' );
	add_action( 'wc_ajax_nh_svea_client_event', 'nh_svea_ajax_client_event' );
}
add_action( 'init', 'nh_svea_diagnostics_init' );

/**
 * Customer clicked Complete purchase; Woo just created the pending order.
 *
 * @param WC_Order $order   Order.
 * @param mixed    $sco_id  Svea checkout id.
 */
function nh_svea_on_pay_click( $order, $sco_id = '' ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$attempts = (int) $order->get_meta( '_nh_svea_validation_attempts' ) + 1;
	$order->update_meta_data( '_nh_svea_validation_attempts', $attempts );
	nh_svea_append_meta_list(
		$order,
		'_nh_svea_validation_times',
		array(
			'at'     => time(),
			'sco_id' => (string) $sco_id,
		)
	);
	if ( $sco_id ) {
		$order->update_meta_data( '_svea_co_order_id', $sco_id );
	}

	$order->add_order_note(
		sprintf(
			/* translators: 1: attempt number, 2: Svea checkout id */
			__( '[Svea] Pay click %1$d. Woo created/kept this order as pending payment. Waiting for Svea to confirm capture (Final push). Svea checkout ID: %2$s. This is not paid yet.', 'nh-theme' ),
			$attempts,
			$sco_id ? (string) $sco_id : '—'
		)
	);
	$order->save();

	nh_svea_log(
		sprintf(
			'Pay click %d for Woo %s / Svea %s',
			$attempts,
			$order->get_order_number(),
			(string) $sco_id
		)
	);
}

/**
 * @param WC_Order $order Order.
 */
function nh_svea_on_server_validation( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}
	nh_svea_append_meta_list( $order, '_nh_svea_server_validation_times', time() );
	$order->save();
}

/**
 * @param WC_Order $order Order.
 */
function nh_svea_on_push_seen( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}
	nh_svea_append_meta_list( $order, '_nh_svea_push_times', time() );
	$order->save();
}

/**
 * @param WC_Order $order      Order.
 * @param mixed    $svea_order Svea payload.
 */
function nh_svea_on_push_final( $order, $svea_order = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	if ( ! $order instanceof WC_Order ) {
		return;
	}
	$order->update_meta_data( '_nh_svea_push_finalized', 'yes' );
	$order->add_order_note(
		__( '[Svea] Payment confirmed. Final push received from Svea. This is a real paid order.', 'nh-theme' )
	);
	$order->save();
}

/**
 * Ask Svea whether this checkout was actually paid before Woo auto-cancels it.
 *
 * @param bool     $cancel Whether Woo should cancel.
 * @param WC_Order $order  Order.
 * @return bool
 */
function nh_svea_filter_cancel_unpaid( $cancel, $order ) {
	if ( ! $cancel || ! $order instanceof WC_Order || ! nh_svea_is_svea_order( $order ) ) {
		return $cancel;
	}

	$snapshot = nh_svea_fetch_remote_status( $order );
	$verdict  = nh_svea_classify_from_order( $order, $snapshot );
	nh_svea_store_verdict( $order, $verdict, $snapshot, true );

	if ( 'paid_missed_push' === $verdict['code'] ) {
		nh_svea_recover_paid_order( $order, $snapshot );
		return false;
	}

	return $cancel;
}

/**
 * Annotate any cancel (unpaid timeout or manual) that we have not already classified.
 *
 * @param int           $order_id Order id.
 * @param WC_Order|null $order    Order.
 */
function nh_svea_on_cancelled( $order_id, $order = null ) {
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order_id );
	}
	if ( ! $order instanceof WC_Order || ! nh_svea_is_svea_order( $order ) ) {
		return;
	}
	if ( $order->get_meta( '_nh_svea_cancel_verdict' ) ) {
		return;
	}

	$snapshot = nh_svea_fetch_remote_status( $order );
	$verdict  = nh_svea_classify_from_order( $order, $snapshot );
	nh_svea_store_verdict( $order, $verdict, $snapshot, false );

	if ( 'paid_missed_push' === $verdict['code'] && $order->get_meta( '_nh_svea_recovered' ) !== 'yes' ) {
		nh_svea_recover_paid_order( $order, $snapshot );
	}
}

/**
 * @param WC_Order $order    Order.
 * @param array    $snapshot Remote status.
 * @return array{code:string,severity:string,summary:string}
 */
function nh_svea_classify_from_order( $order, $snapshot ) {
	$events = $order->get_meta( '_nh_svea_client_events' );
	return nh_svea_classify_unpaid_outcome(
		array(
			'validation_attempts'  => (int) $order->get_meta( '_nh_svea_validation_attempts' ),
			'push_finalized'       => $order->get_meta( '_nh_svea_push_finalized' ) === 'yes',
			'checkout_status'      => (string) ( $snapshot['checkout_status'] ?? '' ),
			'payment_admin_status' => (string) ( $snapshot['payment_admin_status'] ?? '' ),
			'client_events'        => is_array( $events ) ? $events : array(),
		)
	);
}

/**
 * @param WC_Order $order Order.
 * @return array{checkout_status:string,payment_admin_status:string,error:string}
 */
function nh_svea_fetch_remote_status( $order ) {
	$out = array(
		'checkout_status'      => '',
		'payment_admin_status' => '',
		'error'                => '',
	);

	$sco_id = (int) preg_replace( '/\D/', '', (string) $order->get_meta( '_svea_co_order_id' ) );
	if ( ! $sco_id ) {
		$out['error'] = 'missing_svea_id';
		return $out;
	}

	$checkout_class = nh_svea_plugin_class( 'Svea_Checkout' );
	if ( $checkout_class ) {
		try {
			$client = new $checkout_class( false );
			if ( method_exists( $client, 'setup_client' ) ) {
				$client->setup_client( $order->get_currency(), $order->get_billing_country() );
			}
			$svea_order = $client->get( $sco_id );
			if ( is_array( $svea_order ) && ! empty( $svea_order['Status'] ) ) {
				$out['checkout_status'] = (string) $svea_order['Status'];
			} elseif ( is_array( $svea_order ) && ! empty( $svea_order['Gui']['Snippet'] ) && empty( $svea_order['Cart'] ) ) {
				$out['error'] = (string) $svea_order['Gui']['Snippet'];
			}
		} catch ( Throwable $e ) {
			$out['error'] = $e->getMessage();
			nh_svea_log( 'Checkout get failed: ' . $e->getMessage(), 'warning' );
		}
	}

	$pa_class = nh_svea_plugin_class( 'Svea_Payment_Admin' );
	if ( $pa_class ) {
		try {
			$pa = new $pa_class( $order );
			if ( method_exists( $pa, 'get' ) ) {
				$pa_order = $pa->get( $sco_id );
				if ( is_array( $pa_order ) && ! empty( $pa_order['OrderStatus'] ) ) {
					$out['payment_admin_status'] = (string) $pa_order['OrderStatus'];
				}
			}
		} catch ( Throwable $e ) {
			// Not being in Payment Admin is normal for unpaid Created checkouts.
			nh_svea_log( 'Payment Admin get skipped: ' . $e->getMessage() );
		}
	}

	$order->update_meta_data( '_nh_svea_last_checkout_status', $out['checkout_status'] );
	$order->update_meta_data( '_nh_svea_last_pa_status', $out['payment_admin_status'] );
	if ( $out['error'] ) {
		$order->update_meta_data( '_nh_svea_last_status_error', $out['error'] );
	}

	return $out;
}

/**
 * @param WC_Order $order     Order.
 * @param array    $verdict   Classifier result.
 * @param array    $snapshot  Remote status.
 * @param bool     $unpaid_to Whether this is Woo's unpaid-timeout cancel.
 */
function nh_svea_store_verdict( $order, $verdict, $snapshot, $unpaid_to ) {
	$order->update_meta_data( '_nh_svea_cancel_verdict', $verdict['code'] );
	$order->update_meta_data( '_nh_svea_cancel_severity', $verdict['severity'] );
	if ( $unpaid_to ) {
		$order->update_meta_data( '_nh_svea_unpaid_timeout', 'yes' );
	}

	$note = sprintf(
		'[Svea] %s | checkout=%s | payment_admin=%s | pay_clicks=%d | final_push=%s',
		$verdict['summary'],
		$snapshot['checkout_status'] ? $snapshot['checkout_status'] : 'n/a',
		$snapshot['payment_admin_status'] ? $snapshot['payment_admin_status'] : 'n/a',
		(int) $order->get_meta( '_nh_svea_validation_attempts' ),
		$order->get_meta( '_nh_svea_push_finalized' ) === 'yes' ? 'yes' : 'no'
	);
	$order->add_order_note( $note );
	$order->save();

	nh_svea_log(
		sprintf(
			'Verdict %s (%s) for %s: %s',
			$verdict['code'],
			$verdict['severity'],
			$order->get_order_number(),
			$note
		),
		'bug' === $verdict['severity'] ? 'error' : 'info'
	);
}

/**
 * Woo was about to cancel a pending order that Svea already captured.
 *
 * @param WC_Order $order    Order.
 * @param array    $snapshot Remote status.
 */
function nh_svea_recover_paid_order( $order, $snapshot ) {
	if ( $order->get_meta( '_nh_svea_recovered' ) === 'yes' ) {
		return;
	}
	$order->update_meta_data( '_nh_svea_recovered', 'yes' );
	$sco_id = preg_replace( '/\D/', '', (string) $order->get_meta( '_svea_co_order_id' ) );
	$order->add_order_note(
		__( '[Svea] Recovered: Svea already captured this payment. WooCommerce hold-stock cancel was blocked/reverted.', 'nh-theme' )
	);
	$order->save();

	if ( method_exists( $order, 'payment_complete' ) ) {
		$order->payment_complete( $sco_id ? (string) $sco_id : '' );
	}

	nh_svea_log(
		sprintf(
			'Recovered paid order %s (checkout=%s pa=%s)',
			$order->get_order_number(),
			$snapshot['checkout_status'] ?? '',
			$snapshot['payment_admin_status'] ?? ''
		),
		'error'
	);
}

/**
 * Do not email the customer for unpaid-timeout abandons (they often place a second order).
 *
 * @param bool          $enabled Default.
 * @param WC_Order|null $order   Order.
 * @return bool
 */
function nh_svea_skip_abandoned_cancel_email( $enabled, $order ) {
	if ( ! $enabled || ! $order instanceof WC_Order ) {
		return $enabled;
	}
	if ( $order->get_meta( '_nh_svea_unpaid_timeout' ) !== 'yes' ) {
		return $enabled;
	}
	$code = (string) $order->get_meta( '_nh_svea_cancel_verdict' );
	if ( in_array( $code, array( 'payment_abandoned', 'payment_declined', 'svea_cancelled' ), true ) ) {
		return false;
	}
	return $enabled;
}

function nh_svea_register_trace_metabox() {
	$screens = array( 'shop_order', 'woocommerce_page_wc-orders' );
	foreach ( $screens as $screen ) {
		add_meta_box(
			'nh-svea-payment-trace',
			__( 'Svea payment trace', 'nh-theme' ),
			'nh_svea_render_trace_metabox',
			$screen,
			'side',
			'high'
		);
	}
}

/**
 * @param WP_Post|WC_Order $post_or_order Order.
 */
function nh_svea_render_trace_metabox( $post_or_order ) {
	$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID ?? 0 );
	if ( ! $order instanceof WC_Order ) {
		return;
	}
	if ( ! nh_svea_is_svea_order( $order ) ) {
		echo '<p>' . esc_html__( 'Not a Svea Checkout order.', 'nh-theme' ) . '</p>';
		return;
	}

	$attempts  = (int) $order->get_meta( '_nh_svea_validation_attempts' );
	$finalized = $order->get_meta( '_nh_svea_push_finalized' ) === 'yes';
	$verdict   = (string) $order->get_meta( '_nh_svea_cancel_verdict' );
	$severity  = (string) $order->get_meta( '_nh_svea_cancel_severity' );
	$checkout  = (string) $order->get_meta( '_nh_svea_last_checkout_status' );
	$pa        = (string) $order->get_meta( '_nh_svea_last_pa_status' );
	$sco       = (string) $order->get_meta( '_svea_co_order_id' );

	echo '<p><strong>' . esc_html__( 'Svea checkout ID', 'nh-theme' ) . ':</strong> ' . esc_html( $sco !== '' ? $sco : '—' ) . '</p>';
	echo '<p><strong>' . esc_html__( 'Pay clicks', 'nh-theme' ) . ':</strong> ' . esc_html( (string) $attempts ) . '</p>';
	echo '<p><strong>' . esc_html__( 'Final push', 'nh-theme' ) . ':</strong> ' . esc_html( $finalized ? 'yes' : 'no' ) . '</p>';
	if ( $checkout !== '' || $pa !== '' ) {
		echo '<p><strong>' . esc_html__( 'Svea status', 'nh-theme' ) . ':</strong> ';
		echo esc_html( trim( 'checkout=' . ( $checkout !== '' ? $checkout : 'n/a' ) . ' / PA=' . ( $pa !== '' ? $pa : 'n/a' ) ) );
		echo '</p>';
	}
	if ( $verdict !== '' ) {
		$label = $severity === 'bug' ? __( 'Bug / missed push', 'nh-theme' ) : ( $severity === 'expected' ? __( 'Abandoned or declined payment', 'nh-theme' ) : __( 'Needs check', 'nh-theme' ) );
		echo '<p><strong>' . esc_html__( 'Verdict', 'nh-theme' ) . ':</strong> ' . esc_html( $label ) . ' <code>' . esc_html( $verdict ) . '</code></p>';
	} elseif ( $finalized ) {
		echo '<p>' . esc_html__( 'Paid and confirmed by Svea.', 'nh-theme' ) . '</p>';
	} elseif ( $attempts > 0 && $order->has_status( array( 'pending', 'on-hold', 'failed' ) ) ) {
		echo '<p>' . esc_html__( 'Waiting for the customer to finish BankID/card/Swish. Woo will auto-cancel if they never complete.', 'nh-theme' ) . '</p>';
	}

	$events = $order->get_meta( '_nh_svea_client_events' );
	if ( is_array( $events ) && $events ) {
		echo '<p><strong>' . esc_html__( 'Iframe events', 'nh-theme' ) . '</strong></p><ul>';
		foreach ( array_slice( $events, -8 ) as $event ) {
			$name = is_array( $event ) ? (string) ( $event['event'] ?? '' ) : (string) $event;
			echo '<li><code>' . esc_html( $name ) . '</code></li>';
		}
		echo '</ul>';
	}
}

function nh_svea_ajax_client_event() {
	check_ajax_referer( 'nh-svea-client-event', 'security' );

	$event = isset( $_POST['event'] ) ? sanitize_key( wp_unslash( (string) $_POST['event'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $event === '' ) {
		wp_send_json_error();
	}

	$order = null;
	if ( function_exists( 'WC' ) && WC()->session ) {
		$awaiting = absint( WC()->session->get( 'order_awaiting_payment' ) );
		if ( $awaiting ) {
			$order = wc_get_order( $awaiting );
		}
	}

	$detail = array(
		'event'   => $event,
		'at'      => time(),
		'status'  => isset( $_POST['status'] ) ? absint( $_POST['status'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		'message' => isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['message'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
	);

	if ( $order instanceof WC_Order ) {
		nh_svea_append_meta_list( $order, '_nh_svea_client_events', $detail );
		$order->add_order_note(
			sprintf(
				'[Svea] Iframe event: %s%s',
				$event,
				$detail['status'] ? ( ' HTTP ' . $detail['status'] ) : ''
			)
		);
		$order->save();
	}

	nh_svea_log( 'Client event ' . wp_json_encode( $detail ) );
	wp_send_json_success();
}
