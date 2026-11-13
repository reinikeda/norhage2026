<?php
/**
 * Whole-krone gross rounding for NOK, SEK and DKK.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-integer-gross.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

require_once dirname( __DIR__ ) . '/inc/integer-gross.php';

$failures = 0;

function nh_ig_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

function nh_ig_rate( $percent ) {
	return array(
		1 => array(
			'rate'     => $percent,
			'compound' => 'no',
		),
	);
}

nh_ig_assert( 'NOK, SEK and DKK are whole-unit currencies', nh_ig_currency_is_whole( 'nok' ) && nh_ig_currency_is_whole( 'SEK' ) && nh_ig_currency_is_whole( 'DKK' ) );
nh_ig_assert( 'EUR stays on normal decimals', ! nh_ig_currency_is_whole( 'EUR' ) );

$split = nh_ig_adjust_net( 99.00, nh_ig_rate( 25 ) );
nh_ig_assert( '99.00 before 25% VAT becomes 124 to pay', abs( $split['gross'] - 124 ) < 0.001 );
nh_ig_assert( 'invoice net is 99.20', abs( $split['net'] - 99.20 ) < 0.001 );
nh_ig_assert( 'invoice VAT is 24.80', abs( $split['tax'] - 24.80 ) < 0.001 );
nh_ig_assert( 'net plus VAT equals the gross', abs( ( $split['net'] + $split['tax'] ) - $split['gross'] ) < 0.001 );

$exact = nh_ig_adjust_net( 80.00, nh_ig_rate( 25 ) );
nh_ig_assert( '80.00 before 25% VAT stays 100', abs( $exact['net'] - 80 ) < 0.001 && abs( $exact['gross'] - 100 ) < 0.001 );

$half = nh_ig_adjust_net( 10.50, nh_ig_rate( 25 ) );
nh_ig_assert( '10.50 before VAT rounds 13.13 up to 13', abs( $half['gross'] - 13 ) < 0.001 );
nh_ig_assert( '13 is 10.40 plus 2.60 VAT', abs( $half['net'] - 10.40 ) < 0.001 && abs( $half['tax'] - 2.60 ) < 0.001 );

foreach ( array( 25, 12 ) as $percent ) {
	$miss = 0;
	for ( $target = 1; $target <= 2000; $target++ ) {
		$row = nh_ig_split_for_gross( $target, nh_ig_rate( $percent ), $target );
		if ( abs( $row['gross'] - $target ) >= 0.001 || abs( ( $row['net'] + $row['tax'] ) - $row['gross'] ) >= 0.001 ) {
			$miss++;
		}
	}
	nh_ig_assert( $percent . '% can invoice every whole amount from 1 to 2000', 0 === $miss );
}

foreach ( array( 15, 6 ) as $percent ) {
	$unbalanced = 0;
	$far        = 0;
	for ( $target = 1; $target <= 2000; $target++ ) {
		$row = nh_ig_split_for_gross( $target, nh_ig_rate( $percent ), $target );
		if ( abs( ( $row['net'] + $row['tax'] ) - $row['gross'] ) >= 0.001 ) {
			$unbalanced++;
		}
		if ( abs( $row['gross'] - $target ) > 0.011 ) {
			$far++;
		}
	}
	nh_ig_assert( $percent . '% invoices still add up when a whole krone is impossible', 0 === $unbalanced && 0 === $far );
}

$targets = nh_ig_line_targets( 124, 3, 37.20 );
nh_ig_assert( 'three units of 124 stay 372 before the discount', abs( $targets['subtotal_gross'] - 372 ) < 0.001 );
nh_ig_assert( '10% of 372 rounds the discount to 37', abs( $targets['discount_gross'] - 37 ) < 0.001 );
nh_ig_assert( 'the customer pays 335', abs( $targets['total_gross'] - 335 ) < 0.001 );

$paid = nh_ig_split_for_gross( $targets['total_gross'], nh_ig_rate( 25 ) );
nh_ig_assert( '335 splits into net plus 25% VAT', abs( $paid['gross'] - 335 ) < 0.001 && abs( $paid['net'] + $paid['tax'] - 335 ) < 0.001 );

$shares = nh_ig_distribute_total( array( 'a' => 10, 'b' => 20 ), 37 );
nh_ig_assert( 'coupon shares add up to the rounded discount', abs( array_sum( $shares ) - 37 ) < 0.001 );

$shipping = nh_ig_round_cost_with_taxes( 39.60, array( 1 => 9.90 ), nh_ig_rate( 25 ) );
nh_ig_assert( 'shipping 49.50 becomes a whole krone', abs( $shipping['gross'] - round( $shipping['gross'] ) ) < 0.001 );
nh_ig_assert( 'shipping net plus VAT equals the gross', abs( ( $shipping['cost'] + array_sum( $shipping['taxes'] ) ) - $shipping['gross'] ) < 0.001 );

$untaxed = nh_ig_round_cost_with_taxes( 49.40, array(), array() );
nh_ig_assert( 'shipping without VAT rounds to 49', abs( $untaxed['cost'] - 49 ) < 0.001 );

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} failed\n" );
	exit( 1 );
}

echo "all passed\n";
