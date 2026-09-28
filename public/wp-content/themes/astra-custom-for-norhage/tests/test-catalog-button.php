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

$nh_catalog_translated = 'Velg';
nh_catalog_assert( 'theme translation wins', 'Velg' === nh_catalog_button_label() );

$nh_catalog_translated = 'Select';
$nh_catalog_locale     = 'sv_SE';
nh_catalog_assert( 'Swedish fallback is Välj', 'Välj' === nh_catalog_button_label() );

$nh_catalog_locale = 'de_DE';
nh_catalog_assert( 'German fallback is Auswählen', 'Auswählen' === nh_catalog_button_label() );

$nh_catalog_locale = 'fi';
nh_catalog_assert( 'Finnish fallback is Valitse', 'Valitse' === nh_catalog_button_label() );

$nh_catalog_locale = 'lt_LT';
nh_catalog_assert( 'Lithuanian fallback is Pasirinkti', 'Pasirinkti' === nh_catalog_button_label() );

$nh_catalog_locale = 'da_DK';
nh_catalog_assert( 'Danish fallback is Vælg', 'Vælg' === nh_catalog_button_label() );

$nh_catalog_locale = 'nb_NO';
nh_catalog_assert( 'Norwegian fallback is Velg', 'Velg' === nh_catalog_button_label() );

$nh_catalog_locale = 'en_US';
nh_catalog_assert( 'English stays Select', 'Select' === nh_catalog_button_label() );

$expected = array(
	'nb_NO.po' => 'Velg',
	'sv_SE.po' => 'Välj',
	'da_DK.po' => 'Vælg',
	'de_DE.po' => 'Auswählen',
	'fi.po'    => 'Valitse',
	'lt_LT.po' => 'Pasirinkti',
);

foreach ( $expected as $file => $translation ) {
	$po  = file_get_contents( dirname( __DIR__ ) . '/languages/' . $file );
	$has = false !== strpos( $po, "msgid \"Select\"\nmsgstr \"{$translation}\"" );
	nh_catalog_assert( $file . ' translates Select', $has );
}

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} failed\n" );
	exit( 1 );
}

echo "all passed\n";
