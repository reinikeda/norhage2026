<?php
/**
 * CLI tests for cart-recovery helpers.
 *
 * Run: php public/wp-content/plugins/nh-cart-recovery/tests/test-nh-cr.php
 */

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-nh-cr-copy.php';

$failures = 0;

function nh_cr_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "OK $label\n";
		return;
	}
	$failures++;
	fwrite( STDERR, "FAIL $label\n" );
}

nh_cr_assert( 'sv locale group', nh_cr_locale_group( 'sv_SE' ) === 'sv' );
nh_cr_assert( 'nb locale group', nh_cr_locale_group( 'nb_NO' ) === 'nb' );
nh_cr_assert( 'sv cart subject', nh_cr_default_copy( 'sv_SE', 'cart' )['subject'] !== '' );
nh_cr_assert( 'nb checkout button', nh_cr_default_copy( 'nb_NO', 'checkout' )['button'] !== '' );

$snap = nh_cr_snapshot_item(
	array(
		'product_id'   => 12,
		'variation_id' => 0,
		'quantity'     => 2,
		'line_total'   => 99,
		'data'         => new stdClass(),
		'nh_custom_size' => array(
			'width_mm' => 800,
		),
	)
);
nh_cr_assert( 'snapshot keeps custom cut', isset( $snap['cart_item_data']['nh_custom_size'] ) );
nh_cr_assert( 'snapshot drops line_total', ! isset( $snap['cart_item_data']['line_total'] ) );
nh_cr_assert( 'snapshot drops product object', ! isset( $snap['cart_item_data']['data'] ) );

nh_cr_assert(
	'skip email if they already paid',
	nh_cr_should_email_cancelled_checkout( 'cancelled', 'svea_checkout', true ) === false
);
nh_cr_assert(
	'email unpaid svea cancel',
	nh_cr_should_email_cancelled_checkout( 'cancelled', 'svea_checkout', false ) === true
);

$hash_a = nh_cr_cart_hash( array( $snap ) );
$hash_b = nh_cr_cart_hash( array( $snap ) );
nh_cr_assert( 'hash stable', $hash_a === $hash_b );

if ( $failures ) {
	fwrite( STDERR, "$failures failed\n" );
	exit( 1 );
}
echo "All nh-cart-recovery tests passed\n";
exit( 0 );
