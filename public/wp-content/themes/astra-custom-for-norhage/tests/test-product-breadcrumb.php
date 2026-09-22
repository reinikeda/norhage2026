<?php
/**
 * CLI tests: single-product breadcrumb plan.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-product-breadcrumb.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = '' ) {
		unset( $domain );
		return (string) $text;
	}
}

require_once dirname( __DIR__ ) . '/inc/product-breadcrumb.php';

$failures = 0;

function nh_bc_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$crumbs = array(
	array( 'Hjem', 'https://norhage.no/' ),
	array( 'Drivhus', 'https://norhage.no/produkt-kategori/drivhus/' ),
	array( 'Drivhus i tre', 'https://norhage.no/produkt-kategori/drivhus/drivhus-i-tre/' ),
	array( 'Drivhus Tre 300 (12-36m²)', '' ),
);

$plan = nh_product_breadcrumb_plan( $crumbs );
nh_bc_assert( 'four crumbs truncate', $plan['truncate'] === true && $plan['count'] === 4 );

$visible = array();
foreach ( $plan['segments'] as $segment ) {
	if ( 'item' === $segment['type'] && empty( $segment['collapsed'] ) ) {
		$visible[] = $segment['label'];
	}
}
nh_bc_assert(
	'phone shows first category and product',
	$visible === array( 'Drivhus', 'Drivhus Tre 300 (12-36m²)' )
);

$short = 0;
foreach ( $plan['segments'] as $segment ) {
	if ( 'short' === $segment['type'] ) {
		$short++;
	}
}
nh_bc_assert( 'one ellipsis marker', $short === 1 );

$html = nh_product_breadcrumb_html( $crumbs );
nh_bc_assert( 'truncate flag is on the nav', strpos( $html, 'data-nh-truncate="1"' ) !== false );
nh_bc_assert( 'home stays in the html', strpos( $html, 'Hjem' ) !== false );
nh_bc_assert( 'middle category stays in the html', strpos( $html, 'Drivhus i tre' ) !== false );
nh_bc_assert( 'home is marked collapsed', strpos( $html, 'is-collapsed' ) !== false && strpos( $html, '>Hjem<' ) !== false );
$with_current_url = nh_product_breadcrumb_html(
	array(
		array( 'Hjem', 'https://norhage.no/' ),
		array( 'Drivhus', 'https://norhage.no/produkt-kategori/drivhus/' ),
		array( 'Drivhus i tre', 'https://norhage.no/produkt-kategori/drivhus/drivhus-i-tre/' ),
		array( 'Drivhus Tre 300 (12-36m²)', 'https://norhage.no/produkt/drivhus-tre-300/' ),
	)
);
nh_bc_assert(
	'current product is not a link',
	strpos( $with_current_url, 'href="https://norhage.no/produkt/drivhus-tre-300/"' ) === false
);
nh_bc_assert( 'category remains a link', strpos( $html, 'href="https://norhage.no/produkt-kategori/drivhus/"' ) !== false );
nh_bc_assert( 'product name is escaped', strpos( nh_product_breadcrumb_html( array( array( '<b>Bad</b>', '' ) ) ), '&lt;b&gt;Bad&lt;/b&gt;' ) !== false );

$short_trail = nh_product_breadcrumb_plan(
	array(
		array( 'Hjem', 'https://norhage.no/' ),
		array( 'Håndtak', '' ),
	)
);
nh_bc_assert( 'two crumbs stay whole', $short_trail['truncate'] === false && $short_trail['count'] === 2 );

$three = nh_product_breadcrumb_html(
	array(
		array( 'Hjem', 'https://norhage.no/' ),
		array( 'Tilbehør', 'https://norhage.no/produkt-kategori/tilbehor/' ),
		array( 'Håndtak', '' ),
	)
);
nh_bc_assert( 'three crumbs are not truncated', strpos( $three, 'data-nh-truncate' ) === false );
nh_bc_assert( 'empty trail renders nothing', nh_product_breadcrumb_html( array() ) === '' );

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
