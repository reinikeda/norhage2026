<?php
/**
 * CLI tests: contact page structure.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-contact-structure.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

require_once dirname( __DIR__ ) . '/inc/contact-structure.php';

$failures = 0;

function nh_contact_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$html = <<<'HTML'
<p class="wp-block-paragraph">We are here to support your project.</p>
<h2 class="wp-block-heading"><strong>How to Reach Us</strong></h2>
<p class="wp-block-paragraph"><strong>Email:</strong> <a href="/cdn-cgi/l/email-protection#abc"><span class="__cf_email__" data-cfemail="aabbcc">[email&#160;protected]</span></a><br><strong>Phone:</strong> <a href="tel:+4917665106609">+49 176 6510 6609</a></p>
<p class="wp-block-paragraph">Typically responding within one business day.</p>
<h2 class="wp-block-heading"><strong>Technical Inquiries &amp; Support Form</strong></h2>
<p class="wp-block-paragraph">Please complete the secure corporate inquiry form below.</p>
<div class="wpforms-container" id="wpforms-99"><form id="wpforms-form-99"><label for="name">Name</label><input id="name" type="text" name="wpforms[fields][1]"><button type="submit">Submit</button></form></div>
<hr class="wp-block-separator"/>
<h3 class="wp-block-heading"><strong>Corporate Network &amp; Updates</strong></h3>
<p class="wp-block-paragraph">Stay connected with the Norhage distribution network.</p>
<ul class="wp-block-list"><li><a href="https://www.facebook.com/norhage.de">Facebook</a></li><li><a href="https://www.instagram.com/norhage.de/">Instagram</a></li></ul>
HTML;

$out = nh_contact_enhance_html(
	$html,
	array(
		'company_label' => 'Company:',
		'company'       => 'Tehi UG',
		'address_label' => 'Address:',
		'address'       => 'Adolfstraße 1, Wiesbaden, 65185 HE, Germany',
	)
);

nh_contact_assert( 'contact slugs include every shop', count( nh_contact_page_slugs() ) === 6 && in_array( 'yhteystiedot', nh_contact_page_slugs(), true ) && in_array( 'kontaktai', nh_contact_page_slugs(), true ) );
nh_contact_assert( 'layout wraps the page', strpos( $out, 'nh-contact__layout' ) !== false );
nh_contact_assert( 'intro stays the lead', strpos( $out, 'nh-contact__lead' ) !== false && strpos( $out, 'We are here to support your project.' ) !== false );
nh_contact_assert( 'phone link is kept', strpos( $out, 'href="tel:+4917665106609"' ) !== false && strpos( $out, '+49 176 6510 6609' ) !== false );
nh_contact_assert( 'cloudflare email markup is kept', strpos( $out, 'data-cfemail="aabbcc"' ) !== false && strpos( $out, 'email-protection#abc' ) !== false );
nh_contact_assert( 'email and phone are channel cards', substr_count( $out, 'nh-contact-channel' ) >= 2 );
nh_contact_assert( 'response time stays', strpos( $out, 'within one business day' ) !== false );
nh_contact_assert( 'form heading and form stay together', strpos( $out, 'nh-contact-section--form' ) !== false && strpos( $out, 'id="wpforms-99"' ) !== false && strpos( $out, 'Please complete the secure corporate inquiry form below.' ) !== false );
nh_contact_assert(
	'social section follows the form as its own section',
	preg_match( '/nh-contact-section--form[\s\S]*?<\/section>[\s\S]*nh-contact-section--social/', $out ) === 1 && strpos( $out, 'nh-contact-social' ) !== false
);
nh_contact_assert(
	'the form comes before the social links',
	strpos( $out, 'nh-contact-section--form' ) < strpos( $out, 'nh-contact-section--social' )
);
nh_contact_assert( 'facebook link is kept', strpos( $out, 'https://www.facebook.com/norhage.de' ) !== false );
nh_contact_assert( 'company and address are added from existing details', strpos( $out, 'Tehi UG' ) !== false && strpos( $out, 'Wiesbaden' ) !== false );
nh_contact_assert( 'page does not gain a second h1', stripos( $out, '<h1' ) === false );
nh_contact_assert( 'separator rule is not part of the layout', strpos( $out, 'wp-block-separator' ) === false );

$again = nh_contact_enhance_html( $out );
nh_contact_assert( 'enhancing twice does not nest another layout', substr_count( $again, 'nh-contact__layout' ) === 1 );

$with_address = nh_contact_enhance_html(
	$html . '<p>Adolfstraße 1, Wiesbaden, 65185 HE, Germany</p>',
	array(
		'company' => 'Tehi UG',
		'address' => 'Adolfstraße 1, Wiesbaden, 65185 HE, Germany',
	)
);
nh_contact_assert( 'an address already on the page is not repeated', substr_count( $with_address, 'Adolfstraße 1, Wiesbaden, 65185 HE, Germany' ) === 1 );

echo $failures === 0 ? "All contact structure tests passed\n" : "{$failures} failed\n";
exit( $failures === 0 ? 0 : 1 );
