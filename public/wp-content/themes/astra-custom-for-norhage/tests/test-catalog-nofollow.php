<?php
/**
 * CLI tests: catalog nofollow stripping.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-catalog-nofollow.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

require_once dirname( __DIR__ ) . '/inc/catalog-nofollow.php';

$failures = 0;

function nh_nofollow_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$html = '<a href="https://norhage.lt/produktas/automatinis-siltnamio-lango-atidarytuvas-venta/" class="ast-on-card-button ast-select-options-trigger product_type_variable add_to_cart_button" rel="nofollow">Pasirinkti savybes</a>';
$out  = nh_strip_rel_nofollow( $html );

nh_nofollow_assert(
	'Astra on-card product link loses rel=nofollow',
	strpos( $out, 'rel="nofollow"' ) === false
);

nh_nofollow_assert(
	'product URL is kept',
	strpos( $out, 'https://norhage.lt/produktas/automatinis-siltnamio-lango-atidarytuvas-venta/' ) !== false
);

nh_nofollow_assert(
	'empty string is unchanged',
	nh_strip_rel_nofollow( '' ) === ''
);

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll tests passed\n";
exit( $failures ? 1 : 0 );
