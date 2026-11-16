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

if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	function get_woocommerce_currency() {
		return 'NOK';
	}
}

$pair = nh_ig_round_display_pair( 39.60, 49.50 );
nh_ig_assert( 'a 49.50 krone line becomes 50', abs( $pair['inc'] - 50 ) < 0.001 && abs( $pair['ex'] - $pair['inc'] ) < 0.001 );

// 3 391 kr including VAT, then 20% off, is 2 712,80. The shop must show 2 713.
$sale_net = nh_ig_adjust_net( 2170.24, nh_ig_rate( 25 ) );
nh_ig_assert( '20% sale of 3391 kr is paid as 2713', abs( $sale_net['gross'] - 2713 ) < 0.001 );
nh_ig_assert( 'that sale invoice still adds up', abs( ( $sale_net['net'] + $sale_net['tax'] ) - $sale_net['gross'] ) < 0.001 );

nh_ig_assert( 'a shown price of 2712.80 becomes 2713', abs( nh_ig_filter_including_tax( 2712.80, 1 ) - 2713 ) < 0.001 );
nh_ig_assert( 'three of those units are 8139, not 8138', abs( nh_ig_filter_including_tax( 2712.80 * 3, 3 ) - 8139 ) < 0.001 );

$metres = nh_ig_line_targets( 100, 2.4, 0 );
nh_ig_assert( '2.4 metres of a 100 kr unit is 240, not 200', abs( $metres['subtotal_gross'] - 240 ) < 0.001 );
$half_metre = nh_ig_line_targets( 124, 0.5, 0 );
nh_ig_assert( 'half a metre of 124 is 62, not a full unit', abs( $half_metre['subtotal_gross'] - 62 ) < 0.001 );
$odd_metres = nh_ig_line_targets( 2713, 2.4, 0 );
nh_ig_assert( '2.4 metres of 2713 pays 6511', abs( $odd_metres['subtotal_gross'] - 6511 ) < 0.001 );
nh_ig_assert(
	'the shown line for those metres matches the subtotal',
	abs( nh_ig_round_paid_amount( 2713 * 2.4, 2.4 ) - $odd_metres['subtotal_gross'] ) < 0.001
);

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		if ( 'woocommerce_tax_display_shop' === $key ) {
			return 'incl';
		}
		return $default;
	}
}

$cached = array(
	'price'         => array( 10 => '2712.80', 11 => '3391' ),
	'regular_price' => array( 10 => '3391', 11 => '3391' ),
	'sale_price'    => array( 10 => '2712.80', 11 => '' ),
);
$shown = nh_ig_filter_variation_prices( $cached, null, true );
nh_ig_assert( 'cached sale price with VAT becomes 2713', abs( (float) $shown['price'][10] - 2713 ) < 0.001 );
nh_ig_assert( 'cached regular price that is already whole stays 3391', abs( (float) $shown['regular_price'][10] - 3391 ) < 0.001 );
nh_ig_assert( 'an empty cached sale price stays empty', '' === $shown['sale_price'][11] );

$source = file_get_contents( dirname( __DIR__ ) . '/inc/integer-gross.php' );
nh_ig_assert(
	'variable catalog prices use the same rounding',
	false !== strpos( $source, "nh_ig_register_price_filter( 'woocommerce_variation_prices_price' )" )
	&& false !== strpos( $source, "add_filter( 'woocommerce_get_price_including_tax', 'nh_ig_filter_including_tax', 9999, 3 )" )
	&& false !== strpos( $source, "add_filter( 'woocommerce_variation_prices', 'nh_ig_filter_variation_prices', 9999, 3 )" )
);
nh_ig_assert(
	'shipping is rounded after the size calculator',
	false !== strpos( $source, "add_filter( 'woocommerce_package_rates', 'nh_ig_filter_package_rates', PHP_INT_MAX )" )
);
nh_ig_assert(
	'an empty cart rate uses the shop VAT',
	false !== strpos( $source, 'get_base_tax_rates' )
);
nh_ig_assert(
	'the catalog switcher still owns product pages',
	false !== strpos( $source, "add_filter( 'pre_option_woocommerce_tax_display_cart', 'nh_ig_frontend_cart_tax_display', 20 )" )
);

$sheet = nh_ig_adjust_net( 136.80 + 28.00, nh_ig_rate( 25 ) );
$material = nh_ig_adjust_net( 136.80, nh_ig_rate( 25 ) );
nh_ig_assert( 'a 1 m² custom cut is 206, which is 171 plus the 35 kr cutting fee', abs( $sheet['gross'] - 206 ) < 0.001 && abs( $material['gross'] - 171 ) < 0.001 );
nh_ig_assert( 'the cutting fee is not taken off the sheet', $sheet['gross'] > $material['gross'] );
nh_ig_assert( 'custom-cut sheet net plus VAT still balances', abs( ( $sheet['net'] + $sheet['tax'] ) - $sheet['gross'] ) < 0.001 );

$two = nh_ig_adjust_net( ( 2 * 136.80 ) + 28.00, nh_ig_rate( 25 ) );
nh_ig_assert( '2 m² is 377, not 342 and not 307', abs( $two['gross'] - 377 ) < 0.001 );

$plain = nh_ig_adjust_net( 99.00, nh_ig_rate( 25 ) );
$basket = nh_ig_inclusive_lines_gross(
	array(
		array(
			'line_subtotal'     => $sheet['net'],
			'line_subtotal_tax' => $sheet['tax'],
		),
		array(
			'line_subtotal'     => $plain['net'],
			'line_subtotal_tax' => $plain['tax'],
		),
	)
);
$before_vat = $sheet['net'] + $plain['net'];
nh_ig_assert( 'basket subtotal is the sum of the prices with VAT', abs( $basket - 330 ) < 0.001 );
nh_ig_assert( 'basket subtotal is not the before-VAT sum', abs( $basket - $before_vat ) > 1 );

nh_ig_assert( 'basket and checkout show prices with VAT', nh_ig_frontend_cart_tax_display( 'excl' ) === 'incl' );
nh_ig_assert( 'cart amounts stay with VAT when Woo would show the net', nh_ig_cart_amounts_include_vat( false ) === true );
nh_ig_assert( '206 with VAT is not printed as 164.80', abs( nh_ig_amount_with_vat( 164.80, 41.20 ) - 206 ) < 0.001 );

if ( ! function_exists( 'wc_price' ) ) {
	function wc_price( $amount ) {
		return 'PRICE:' . number_format( (float) $amount, 2, '.', '' );
	}
}

class NH_IG_Test_Cart {
	public $lines;
	public $ship;
	public $ship_tax;

	public function __construct( $lines, $ship = 0.0, $ship_tax = 0.0 ) {
		$this->lines     = $lines;
		$this->ship      = $ship;
		$this->ship_tax  = $ship_tax;
	}

	public function get_cart() {
		return $this->lines;
	}

	public function get_shipping_total() {
		return $this->ship;
	}

	public function get_shipping_tax() {
		return $this->ship_tax;
	}

	public function get_fee_total() {
		return 0.0;
	}

	public function get_fee_tax() {
		return 0.0;
	}
}

$with_vat = new NH_IG_Test_Cart(
	array(
		array(
			'line_subtotal'     => 164.80,
			'line_subtotal_tax' => 41.20,
			'line_total'        => 164.80,
			'line_tax'          => 41.20,
		),
	),
	80.00,
	20.00
);
nh_ig_assert(
	'subtotal HTML is the price with VAT',
	'PRICE:206.00' === nh_ig_filter_cart_subtotal( 'PRICE:164.80', false, $with_vat )
);
nh_ig_assert(
	'a line with VAT is shown with VAT',
	'PRICE:206.00' === nh_ig_filter_visible_line_subtotal( 'PRICE:164.80', $with_vat->lines[0], 'k' )
);
nh_ig_assert(
	'shipping HTML is the price with VAT',
	'PRICE:100.00' === nh_ig_filter_visible_shipping_total( 'PRICE:80.00', $with_vat )
);
$sale_line = array(
	'line_subtotal'     => 2170.24,
	'line_subtotal_tax' => 542.56,
	'line_total'        => 2170.24,
	'line_tax'          => 542.56,
	'quantity'          => 1,
);
nh_ig_assert(
	'a 2712.80 line is shown as 2713',
	'PRICE:2713.00' === nh_ig_filter_visible_line_subtotal( 'PRICE:2712.80', $sale_line, 'k' )
);
nh_ig_assert(
	'the basket subtotal rounds that line to 2713',
	'PRICE:2713.00' === nh_ig_filter_cart_subtotal( 'PRICE:2712.80', false, new NH_IG_Test_Cart( array( $sale_line ) ) )
);
$ship_ore = new NH_IG_Test_Cart( array(), 49.60, 12.38 );
nh_ig_assert(
	'shipping 61.98 is shown as 62',
	'PRICE:62.00' === nh_ig_filter_visible_shipping_total( 'PRICE:61.98', $ship_ore )
);
nh_ig_assert( 'euro cart amounts keep cents', abs( nh_ig_visible_gross( 10.20, 0.29, 1, 'EUR' ) - 10.49 ) < 0.001 );
nh_ig_assert(
	'order emails and the PDF use the same rounding',
	false !== strpos( $source, "add_filter( 'woocommerce_order_formatted_line_subtotal', 'nh_ig_filter_order_line_subtotal', 15, 3 )" )
	&& false !== strpos( $source, "add_filter( 'wpo_wcpdf_order_item_data', 'nh_ig_filter_pdf_item', 20, 3 )" )
);
nh_ig_assert(
	'order total is lines, shipping and their VAT',
	abs( nh_ig_charged_gross( $with_vat ) - 306 ) < 0.001
);

$without_vat = new NH_IG_Test_Cart(
	array(
		array(
			'line_subtotal'     => 164.80,
			'line_subtotal_tax' => 0,
			'line_total'        => 164.80,
			'line_tax'          => 0,
		),
	)
);
nh_ig_assert(
	'missing VAT is not painted onto a before-VAT line',
	'BEFORE' === nh_ig_filter_cart_subtotal( 'BEFORE', false, $without_vat )
);

$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/custom-cutting.js' );
nh_ig_assert(
	'the selection box adds the shown cutting fee when the raw fee is missing',
	false !== strpos( $js, 'withFee - material' ) && false !== strpos( $js, 'shownFee * 0.5' )
);

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} failed\n" );
	exit( 1 );
}

echo "all passed\n";
