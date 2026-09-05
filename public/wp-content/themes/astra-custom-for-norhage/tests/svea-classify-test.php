<?php
/**
 * CLI tests for nh_svea_classify_unpaid_outcome().
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/svea-classify-test.php
 */

require_once dirname( __DIR__ ) . '/inc/svea-checkout-classify.php';

$failures = 0;

function nh_svea_assert( $label, $expected_code, $facts ) {
	global $failures;
	$got = nh_svea_classify_unpaid_outcome( $facts );
	if ( $got['code'] !== $expected_code ) {
		fwrite( STDERR, "FAIL $label: expected $expected_code, got {$got['code']}\n" );
		$failures++;
		return;
	}
	echo "OK $label\n";
}

// SE-3105: six pay clicks, Svea never Final, nothing in Payment Admin.
nh_svea_assert(
	'SE-3105 abandoned payment',
	'payment_abandoned',
	array(
		'validation_attempts'  => 6,
		'push_finalized'       => false,
		'checkout_status'      => 'Created',
		'payment_admin_status' => '',
		'client_events'        => array(),
	)
);

nh_svea_assert(
	'SE-3106 would be paid if push landed',
	'paid_missed_push',
	array(
		'validation_attempts'  => 1,
		'push_finalized'       => true,
		'checkout_status'      => 'Final',
		'payment_admin_status' => 'Open',
		'client_events'        => array(),
	)
);

nh_svea_assert(
	'Paid in Svea, Woo missed push',
	'paid_missed_push',
	array(
		'validation_attempts'  => 1,
		'push_finalized'       => false,
		'checkout_status'      => 'Final',
		'payment_admin_status' => '',
		'client_events'        => array(),
	)
);

nh_svea_assert(
	'BankID declined',
	'payment_declined',
	array(
		'validation_attempts'  => 2,
		'push_finalized'       => false,
		'checkout_status'      => 'Created',
		'payment_admin_status' => 'Failed',
		'client_events'        => array(),
	)
);

nh_svea_assert(
	'Svea cancelled the session',
	'svea_cancelled',
	array(
		'validation_attempts'  => 1,
		'push_finalized'       => false,
		'checkout_status'      => 'Cancelled',
		'payment_admin_status' => '',
		'client_events'        => array(),
	)
);

nh_svea_assert(
	'confirmOrder 400 after pay click',
	'iframe_error',
	array(
		'validation_attempts'  => 3,
		'push_finalized'       => false,
		'checkout_status'      => 'Created',
		'payment_admin_status' => '',
		'client_events'        => array(
			array( 'event' => 'confirm_order_failed' ),
		),
	)
);

nh_svea_assert(
	'Unknown if Svea cannot be reached',
	'unknown',
	array(
		'validation_attempts'  => 0,
		'push_finalized'       => false,
		'checkout_status'      => '',
		'payment_admin_status' => '',
		'client_events'        => array(),
	)
);

if ( $failures ) {
	fwrite( STDERR, "$failures failed\n" );
	exit( 1 );
}

echo "All svea classify tests passed\n";
exit( 0 );
