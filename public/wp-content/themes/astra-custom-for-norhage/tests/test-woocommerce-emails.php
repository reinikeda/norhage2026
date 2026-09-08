<?php
/**
 * CLI tests for WooCommerce email placeholder restoration.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-woocommerce-emails.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
		return true;
	}
}

require_once dirname( __DIR__ ) . '/inc/woocommerce-emails.php';

$failures = 0;

function nh_email_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "OK $label\n";
		return;
	}
	$failures++;
	fwrite( STDERR, "FAIL $label\n" );
}

$lt_subject = '[{site_title}]: Gavote naują užsakymą: #{užsakymo_numeris}';
$en_source  = '[{site_title}]: You\'ve got a new order: #{order_number}';

nh_email_assert(
	'restore Lithuanian order_number token',
	nh_restore_woocommerce_email_placeholder_tokens( $lt_subject, $en_source ) === '[{site_title}]: Gavote naują užsakymą: #{order_number}'
);

nh_email_assert(
	'leave heading that already has English token',
	nh_restore_woocommerce_email_placeholder_tokens(
		'Naujas užsakymas: #{order_number}',
		'New order: #{order_number}'
	) === 'Naujas užsakymas: #{order_number}'
);

nh_email_assert(
	'ignore strings without order_number in source',
	nh_restore_woocommerce_email_placeholder_tokens( '{užsakymo_numeris}', 'Something else' ) === '{užsakymo_numeris}'
);

nh_email_assert(
	'inject custom LT-prefixed order number',
	nh_inject_order_number_for_translated_placeholders( $lt_subject, 'LT-1014' ) === '[{site_title}]: Gavote naują užsakymą: #LT-1014'
);

nh_email_assert(
	'inject via ASCII-folded placeholder',
	nh_inject_order_number_for_translated_placeholders( 'Order #{uzsakymo_numeris}', 'LT-1014' ) === 'Order #LT-1014'
);

nh_email_assert(
	'skip inject when order number is empty',
	nh_inject_order_number_for_translated_placeholders( $lt_subject, '' ) === $lt_subject
);

$email = (object) array(
	'placeholders' => array(
		'{order_number}' => 'LT-1014',
		'{site_title}'   => 'Norhage',
	),
);

nh_email_assert(
	'format_string safety net uses email placeholders',
	nh_replace_translated_woocommerce_email_placeholders( $lt_subject, $email ) === '[{site_title}]: Gavote naują užsakymą: #LT-1014'
);

if ( $failures ) {
	fwrite( STDERR, "\n{$failures} test(s) failed\n" );
	exit( 1 );
}

echo "\nAll tests passed\n";
exit( 0 );
