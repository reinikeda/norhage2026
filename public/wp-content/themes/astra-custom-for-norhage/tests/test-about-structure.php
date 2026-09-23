<?php
/**
 * CLI tests: about page structure.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-about-structure.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

require_once dirname( __DIR__ ) . '/inc/about-structure.php';

$failures = 0;

function nh_about_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$eu = <<<'HTML'
<p class="wp-block-paragraph">At Norhage, we take pride in our position as a leading Scandinavian provider.</p>
<h2 class="wp-block-heading"><strong>Our Heritage</strong></h2>
<p class="wp-block-paragraph">Founded more than 10 years ago.</p>
<h2 class="wp-block-heading"><strong>Our Product Ecosystem</strong></h2>
<p class="wp-block-paragraph">We supply a diverse catalog.</p>
<h2 class="wp-block-heading"><strong>Norhage Industri – Commercial Supply</strong></h2>
<p class="wp-block-paragraph">We operate a dedicated division via <a href="https://norhageindustri.com">Norhage Industri</a>.</p>
<hr class="wp-block-separator"/>
<div class="wp-block-media-text"><figure><img src="https://norhage.eu/wp-content/uploads/2026/07/trustpilot-logo.png" alt="Trustpilot logo"></figure><div><p>We’re new on Trustpilot — your feedback helps us grow.</p><p><a href="https://www.trustpilot.com/review/norhage.eu">Read our reviews or leave one</a>.</p></div></div>
HTML;

$out = nh_about_enhance_html( $eu, 'On this page' );

nh_about_assert( 'about slugs include every shop', in_array( 'ueber-uns', nh_about_page_slugs(), true ) && in_array( 'apie-mus', nh_about_page_slugs(), true ) && in_array( 'tietoa-meista', nh_about_page_slugs(), true ) );
nh_about_assert( 'intro stays the lead', strpos( $out, 'nh-about__lead' ) !== false && strpos( $out, 'leading Scandinavian provider' ) !== false );
nh_about_assert( 'three story sections are created', substr_count( $out, 'nh-about-section' ) === 3 );
nh_about_assert( 'section list uses the heading text', strpos( $out, 'Our Heritage' ) !== false && strpos( $out, 'href="#our-heritage"' ) !== false );
nh_about_assert( 'industri link is kept', strpos( $out, 'https://norhageindustri.com' ) !== false );
nh_about_assert( 'trust card keeps the review link and image', strpos( $out, 'nh-about-trust' ) !== false && strpos( $out, 'trustpilot-logo.png' ) !== false && strpos( $out, 'https://www.trustpilot.com/review/norhage.eu' ) !== false && strpos( $out, 'your feedback helps us grow' ) !== false );
nh_about_assert( 'trust card is not inside a story section', preg_match( '/nh-about-section[\s\S]*?<\/section>[\s\S]*nh-about-trust/', $out ) === 1 );
nh_about_assert( 'separator is dropped', strpos( $out, 'wp-block-separator' ) === false );
nh_about_assert( 'page does not gain a second h1', stripos( $out, '<h1' ) === false );

$again = nh_about_enhance_html( $out, 'On this page' );
nh_about_assert( 'enhancing twice does not nest another wrap', substr_count( $again, 'nh-about__wrap' ) === 1 );

$lt = <<<'HTML'
<p>Norhage – tai skandinaviška kokybė.</p>
<h3>Mūsų ištakos</h3>
<p>UAB Tehis buvo įkurta 2016 m.</p>
<ul><li>plastiko gaminių tiekimas</li><li>šiltnamių prekyba</li></ul>
<h3>Kodėl būtent mes?</h3>
<ul><li>kokybė</li></ul>
HTML;

$lt_out = nh_about_enhance_html( $lt, 'On this page' );
nh_about_assert( 'h3-only pages become sections', substr_count( $lt_out, 'nh-about-section' ) === 2 );
nh_about_assert( 'lithuanian lists stay inside the section', strpos( $lt_out, 'plastiko gaminių tiekimas' ) !== false && strpos( $lt_out, '<ul>' ) !== false );
nh_about_assert( 'lithuanian heading keeps its letters in the anchor', strpos( $lt_out, 'href="#mūsų-ištakos"' ) !== false || strpos( $lt_out, 'href="#musu-istakos"' ) !== false || strpos( $lt_out, 'Mūsų ištakos' ) !== false );

echo $failures === 0 ? "All about structure tests passed\n" : "{$failures} failed\n";
exit( $failures === 0 ? 0 : 1 );
