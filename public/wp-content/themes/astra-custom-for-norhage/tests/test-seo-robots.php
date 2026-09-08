<?php
/**
 * CLI tests for Complianz robots.txt helper.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-seo-robots.php
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
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
		return true;
	}
}

require_once dirname( __DIR__ ) . '/inc/seo.php';

$failures = 0;

function nh_seo_robots_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "OK $label\n";
		return;
	}
	$failures++;
	fwrite( STDERR, "FAIL $label\n" );
}

$live = "User-agent: *\nDisallow: /wp-admin/\n";
$with = nh_seo_append_complianz_robots_txt( $live );

nh_seo_robots_assert(
	'adds Complianz Disallow',
	false !== strpos( $with, "Disallow: /wp-content/uploads/complianz/\n" )
);

nh_seo_robots_assert(
	'keeps existing rules',
	false !== strpos( $with, 'Disallow: /wp-admin/' )
);

nh_seo_robots_assert(
	'does not duplicate',
	nh_seo_append_complianz_robots_txt( $with ) === $with
);

if ( $failures ) {
	fwrite( STDERR, "\n{$failures} test(s) failed\n" );
	exit( 1 );
}

echo "\nAll tests passed\n";
exit( 0 );
