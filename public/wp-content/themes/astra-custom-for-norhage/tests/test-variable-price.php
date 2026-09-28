<?php
/**
 * CLI tests: variable "from" price, without touching offer schema.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-variable-price.php
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

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

$nh_x_translations = array();

if ( ! function_exists( '_x' ) ) {
	function _x( $text, $context, $domain = 'default' ) {
		global $nh_x_translations;
		unset( $context );
		$key = $domain . '|' . $text;
		return isset( $nh_x_translations[ $key ] ) ? $nh_x_translations[ $key ] : $text;
	}
}

require_once dirname( __DIR__ ) . '/inc/variable-price.php';

$failures = 0;

function nh_price_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$range = '118 kr – 1 396,80 kr<span class="screen-reader-text">Price range: 118 kr through 1 396,80 kr</span>';
$from  = nh_format_variable_from_price( '118', '1396.80', '<span class="amount">118 kr</span>', '', $range, 'Fra:' );

nh_price_assert( 'range becomes the lowest price', false !== strpos( $from, '118 kr' ) && false === strpos( $from, '1 396,80' ) );
nh_price_assert( 'prefix is marked as from', false !== strpos( $from, 'class="from"' ) && false !== strpos( $from, 'Fra:' ) );
nh_price_assert( 'second pass does not duplicate the prefix', $from === nh_format_variable_from_price( '118', '1396.80', '<span class="amount">118 kr</span>', '', $from, 'Fra:' ) );

$sale = '<del>200 kr</del> <ins>150 kr</ins>';
nh_price_assert(
	'one price keeps the original sale HTML',
	$sale === nh_format_variable_from_price( '150.00', '150', '<span>150 kr</span>', '', $sale, 'From:' )
);

nh_price_assert( '118 and 118.00 are the same price', ! nh_variable_prices_differ( '118', '118.00' ) );
nh_price_assert( 'different amounts still differ', nh_variable_prices_differ( '118', '1396.80' ) );

$suffix = '<small>inkl. mva</small>';
$with_suffix = nh_format_variable_from_price( '10', '20', '<span>10 kr</span>', $suffix, '10 – 20', 'From:' );
nh_price_assert( 'shop suffix is kept once', 1 === substr_count( $with_suffix, 'inkl. mva' ) );

$nh_x_translations = array(
	'woocommerce|From:' => 'Från:',
	'nh-theme|From:'    => 'From:',
);
nh_price_assert( 'WooCommerce translation wins when it exists', 'Från:' === nh_variable_price_from_label() );

$nh_x_translations = array(
	'woocommerce|From:' => 'From:',
	'nh-theme|From:'    => 'Alkaen:',
);
nh_price_assert( 'theme translation is used when WooCommerce has no translation', 'Alkaen:' === nh_variable_price_from_label() );

$nh_x_translations = array();
if ( ! function_exists( 'determine_locale' ) ) {
	function determine_locale() {
		return 'nb_NO';
	}
}
nh_price_assert( 'Norwegian locale still says Fra when no catalog is loaded', 'Fra:' === nh_variable_price_from_label() );

$source = file_get_contents( dirname( __DIR__ ) . '/inc/variable-price.php' );
nh_price_assert( 'display filter is registered', false !== strpos( $source, "add_filter( 'woocommerce_variable_price_html'" ) );
nh_price_assert(
	'schema hooks are not registered',
	false === strpos( $source, 'woocommerce_structured_data' ) && false === strpos( $source, 'wpseo_schema' )
);

$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/nh-variable-price.js' );
nh_price_assert( 'selected variation replaces the visible price', false !== strpos( $js, 'found_variation.nhFrom' ) && false !== strpos( $js, 'variation.price_html' ) );
nh_price_assert( 'clearing the variation restores From', false !== strpos( $js, 'reset_data.nhFrom' ) && false !== strpos( $js, 'original' ) );
nh_price_assert( 'hidden variation price is not the target', false !== strpos( $js, 'woocommerce-variation-price' ) );

$css = file_get_contents( dirname( __DIR__ ) . '/style.css' );
nh_price_assert( 'From uses the same text color as the price', false !== strpos( $css, '.woocommerce .price .from' ) && false !== strpos( $css, 'var(--ui-text, #2C2A29)' ) );

$expected = array(
	'nb_NO.po' => 'Fra:',
	'sv_SE.po' => 'Från:',
	'da_DK.po' => 'Fra:',
	'de_DE.po' => 'Ab:',
	'fi.po'    => 'Alkaen:',
	'lt_LT.po' => 'Nuo:',
);

foreach ( $expected as $file => $translation ) {
	$po = file_get_contents( dirname( __DIR__ ) . '/languages/' . $file );
	$has = false !== strpos( $po, "msgctxt \"min_price\"\nmsgid \"From:\"\nmsgstr \"{$translation}\"" );
	nh_price_assert( $file . ' translates From:', $has );
}

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} failed\n" );
	exit( 1 );
}

echo "all passed\n";
