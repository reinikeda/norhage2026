<?php
/**
 * CLI tests: Shop by Category one-line horizontal strip.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-subcategory-scroll.php
 */

$failures = 0;

function nh_sub_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$theme = dirname( __DIR__ );
$css   = file_get_contents( $theme . '/style.css' );
$php   = file_get_contents( $theme . '/functions.php' );
$js    = file_get_contents( $theme . '/assets/js/subcategory-scroll.js' );

nh_sub_assert( 'scroll script exists', is_string( $js ) && $js !== '' );
nh_sub_assert( 'scroll script targets data-nh-subcats', false !== strpos( $js, 'data-nh-subcats' ) );
nh_sub_assert( 'scroll script hides idle arrows', false !== strpos( $js, 'is-idle' ) );

nh_sub_assert(
	'markup uses data-nh-subcats and arrow controls',
	false !== strpos( $php, 'data-nh-subcats' )
		&& false !== strpos( $php, 'subcategory-prev' )
		&& false !== strpos( $php, 'subcategory-next' )
);
nh_sub_assert(
	'subcategory scroll script is enqueued on product categories',
	false !== strpos( $php, 'nh-subcategory-scroll' )
		&& false !== strpos( $php, 'subcategory-scroll.js' )
);

nh_sub_assert(
	'items stay a flex scroll row (not desktop grid)',
	(bool) preg_match( '/\.subcategory-items\s*\{[^}]*display:\s*flex/s', $css )
		&& (bool) preg_match( '/\.subcategory-items\s*\{[^}]*overflow-x:\s*auto/s', $css )
		&& false === strpos( $css, 'grid-template-columns:repeat(auto-fit, minmax(140px, 1fr))' )
);
nh_sub_assert(
	'desktop media query no longer switches subcategory-items to grid',
	! preg_match(
		'/@media \(min-width:768px\)\s*\{[^}]*\.subcategory-items\s*\{[^}]*display:\s*grid/s',
		$css
	)
);
nh_sub_assert(
	'arrow idle state is hidden in CSS',
	(bool) preg_match( '/\.subcategory-arrows\.is-idle\s*\{\s*display:\s*none/s', $css )
);

if ( $failures ) {
	echo "\n{$failures} test(s) failed\n";
	exit( 1 );
}

echo "\nAll tests passed\n";
exit( 0 );
