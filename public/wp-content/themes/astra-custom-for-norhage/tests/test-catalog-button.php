<?php
/**
 * CLI tests: catalog card button label.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-catalog-button.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

$nh_catalog_translated = null;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		global $nh_catalog_translated;
		unset( $domain );
		return null !== $nh_catalog_translated ? $nh_catalog_translated : $text;
	}
}

if ( ! function_exists( 'determine_locale' ) ) {
	function determine_locale() {
		global $nh_catalog_locale;
		return $nh_catalog_locale ? $nh_catalog_locale : 'en_US';
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'nh_shorten_finnish_add_to_cart_text' ) ) {
	function nh_shorten_finnish_add_to_cart_text( $text ) {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : 'en_US';
		if ( strpos( (string) $locale, 'fi' ) !== 0 ) {
			return $text;
		}
		if ( $text === 'Lisää ostoskoriin' ) {
			return 'Lisää koriin';
		}
		return $text;
	}
}

require_once dirname( __DIR__ ) . '/inc/catalog-button.php';

$failures = 0;

function nh_catalog_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$nh_catalog_translated = 'Lägg i varukorg';
nh_catalog_assert( 'long Swedish Woo string is shortened', 'Lägg i korg' === nh_catalog_button_label() );

$nh_catalog_translated = 'Add to cart';
$nh_catalog_locale     = 'sv_SE';
nh_catalog_assert( 'Swedish fallback is Lägg i korg', 'Lägg i korg' === nh_catalog_button_label() );

$nh_catalog_locale = 'de_DE';
nh_catalog_assert( 'German fallback is In den Korb', 'In den Korb' === nh_catalog_button_label() );

$nh_catalog_translated = 'In den Warenkorb';
nh_catalog_assert( 'long German Woo string is shortened', 'In den Korb' === nh_catalog_button_label() );

$nh_catalog_translated = 'Add to cart';
$nh_catalog_locale     = 'fi';
nh_catalog_assert( 'Finnish fallback is Lisää koriin', 'Lisää koriin' === nh_catalog_button_label() );

$nh_catalog_translated = 'Lisää ostoskoriin';
$nh_catalog_locale     = 'fi_FI';
nh_catalog_assert( 'Finnish Woo string is shortened', 'Lisää koriin' === nh_catalog_button_label() );

$nh_catalog_translated = 'Add to cart';
$nh_catalog_locale     = 'lt_LT';
nh_catalog_assert( 'Lithuanian fallback is Į krepšelį', 'Į krepšelį' === nh_catalog_button_label() );

$nh_catalog_locale = 'da_DK';
nh_catalog_assert( 'Danish fallback is Læg i kurv', 'Læg i kurv' === nh_catalog_button_label() );

$nh_catalog_locale = 'nb_NO';
nh_catalog_assert( 'Norwegian fallback is Legg i kurv', 'Legg i kurv' === nh_catalog_button_label() );

$nh_catalog_translated = 'Legg i handlekurv';
nh_catalog_assert( 'long Norwegian Woo string is shortened', 'Legg i kurv' === nh_catalog_button_label() );

$nh_catalog_translated = 'Add to cart';
$nh_catalog_locale     = 'en_US';
nh_catalog_assert( 'English stays Add to cart', 'Add to cart' === nh_catalog_button_label() );

nh_catalog_assert( 'simple products open the product page', true === nh_catalog_product_opens_page( (object) array( 'type' => 'simple' ) ) );
nh_catalog_assert( 'variable products open the product page', true === nh_catalog_product_opens_page( (object) array( 'type' => 'variable' ) ) );
nh_catalog_assert( 'icon markup is an svg cart', false !== strpos( nh_catalog_button_icon_html(), 'nh-catalog-atc__icon' ) );

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		public function get_permalink() {
			return 'https://example.test/product';
		}
	}
}

$html = nh_catalog_button_link( '', new WC_Product(), array( 'class' => 'button' ) );
nh_catalog_assert( 'catalog button includes the cart icon', false !== strpos( $html, 'nh-catalog-atc__icon' ) );
nh_catalog_assert( 'catalog button includes the label', false !== strpos( $html, 'Add to cart' ) );

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} failed\n" );
	exit( 1 );
}

echo "all passed\n";
