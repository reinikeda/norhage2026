<?php
/**
 * Desktop footer columns stay open. Phones keep the collapsed menus.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-footer-desktop.php
 */

$css       = file_get_contents( dirname( __DIR__ ) . '/style.css' );
$desktop   = substr( $css, (int) strpos( $css, '@media (min-width: 721px){' ) );
$desktop   = substr( $desktop, 0, (int) strpos( $desktop, '@media (max-width: 720px){' ) );
$failures  = 0;

function nh_footer_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

nh_footer_assert(
	'desktop footer reveals the closed details body',
	strpos( $desktop, '.nh-footer__fold::details-content{' ) !== false
		&& strpos( $desktop, 'content-visibility:visible;' ) !== false
);

nh_footer_assert(
	'desktop footer still shows the menu list',
	strpos( $desktop, '.nh-footer__fold:not([open]) > .nh-footer__menu{' ) !== false
);

nh_footer_assert(
	'phone footer keeps the collapsible menus',
	strpos( $css, '@media (max-width: 720px){' ) !== false
		&& strpos( $css, '.nh-footer__fold > summary{' ) !== false
);

echo $failures === 0 ? "All footer desktop tests passed\n" : "{$failures} failed\n";
exit( $failures === 0 ? 0 : 1 );
