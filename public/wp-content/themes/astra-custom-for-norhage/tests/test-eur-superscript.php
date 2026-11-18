<?php
/**
 * Raised euro cents on catalog and product price HTML.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-eur-superscript.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

require_once dirname( __DIR__ ) . '/inc/eur-superscript.php';

$failures = 0;

function nh_eur_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$amount = '<span class="woocommerce-Price-amount amount"><bdi>10,49&nbsp;<span class="woocommerce-Price-currencySymbol">&euro;</span></bdi></span>';
$raised = nh_eur_superscript_html( $amount );

nh_eur_assert( 'cents are wrapped', false !== strpos( $raised, '10,<sup class="nh-eur-cents">49</sup>&nbsp;' ) );
nh_eur_assert( 'currency symbol stays in place', false !== strpos( $raised, 'woocommerce-Price-currencySymbol' ) );
nh_eur_assert( 'a second pass does not wrap again', $raised === nh_eur_superscript_html( $raised ) );

$sale = '<del>12,00&nbsp;€</del> <ins>10,49&nbsp;€</ins>';
$sale_raised = nh_eur_superscript_html( $sale );
nh_eur_assert( 'regular and sale cents are both raised', substr_count( $sale_raised, '<sup class="nh-eur-cents">' ) === 2 );
nh_eur_assert( 'sale amounts stay 12 and 10', false !== strpos( $sale_raised, '<del>12,<sup' ) && false !== strpos( $sale_raised, '<ins>10,<sup' ) );

$thousands = nh_eur_superscript_html( '1.234,56 €' );
nh_eur_assert( 'thousands stay on the line', '1.234,<sup class="nh-eur-cents">56</sup> €' === $thousands );

$symbol_first = nh_eur_superscript_html( '€10.49', '.' );
nh_eur_assert( 'dot decimals are raised when that is the separator', '€10.<sup class="nh-eur-cents">49</sup>' === $symbol_first );

$negative = nh_eur_superscript_html( '-10,49 €' );
nh_eur_assert( 'a negative amount keeps its sign', '-10,<sup class="nh-eur-cents">49</sup> €' === $negative );

$tagged = nh_eur_superscript_html( '<span data-x="1,23">10,49</span>' );
nh_eur_assert( 'attribute numbers are left alone', false !== strpos( $tagged, 'data-x="1,23"' ) && false !== strpos( $tagged, '>10,<sup class="nh-eur-cents">49</sup></span>' ) );

nh_eur_assert( 'an amount without cents is unchanged', '10 €' === nh_eur_superscript_html( '10 €' ) );
nh_eur_assert( 'empty html is unchanged', '' === nh_eur_superscript_html( '' ) );

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} failed\n" );
	exit( 1 );
}

echo "all passed\n";
