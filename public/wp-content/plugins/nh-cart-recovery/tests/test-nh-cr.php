<?php
/**
 * CLI tests for cart-recovery helpers.
 *
 * Run: php public/wp-content/plugins/nh-cart-recovery/tests/test-nh-cr.php
 */

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

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
	function esc_url( $text ) {
		return (string) $text;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-nh-cr-copy.php';
require_once dirname( __DIR__ ) . '/includes/class-nh-cr-mailer.php';

$failures = 0;

function nh_cr_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "OK $label\n";
		return;
	}
	$failures++;
	fwrite( STDERR, "FAIL $label\n" );
}

nh_cr_assert( 'sv locale group', nh_cr_locale_group( 'sv_SE' ) === 'sv' );
nh_cr_assert( 'nb locale group', nh_cr_locale_group( 'nb_NO' ) === 'nb' );
nh_cr_assert( 'nb short locale group', nh_cr_locale_group( 'nb' ) === 'nb' );
nh_cr_assert( 'no_NO locale group', nh_cr_locale_group( 'no_NO' ) === 'nb' );
nh_cr_assert( 'shop locale is a string', is_string( nh_cr_shop_locale() ) && nh_cr_shop_locale() !== '' );
nh_cr_assert( 'sv cart 1 subject', nh_cr_default_copy( 'sv_SE', 'cart', 1 )['subject'] !== '' );
nh_cr_assert( 'sv cart 3 last reminder', strpos( nh_cr_default_copy( 'sv_SE', 'cart', 3 )['subject'], 'Sista' ) !== false );
nh_cr_assert( 'nb checkout 1 button', nh_cr_default_copy( 'nb_NO', 'checkout', 1 )['button'] !== '' );
nh_cr_assert( 'fi cart 2 has body', nh_cr_default_copy( 'fi', 'cart', 2 )['body'] !== '' );
nh_cr_assert( 'de checkout 3 heading', nh_cr_default_copy( 'de_DE', 'checkout', 3 )['heading'] !== '' );
nh_cr_assert( 'lt ui unsub', nh_cr_ui_copy( 'lt_LT' )['unsub'] !== '' );

$named = nh_cr_personalize( '{first_name}, your Norhage cart is saved', 'Anna' );
nh_cr_assert( 'personalize keeps name', $named === 'Anna, your Norhage cart is saved' );
$anon = nh_cr_personalize( '{first_name}, your Norhage cart is saved', '' );
nh_cr_assert( 'personalize strips name', $anon === 'Your Norhage cart is saved' );

$settings = nh_cr_default_settings();
nh_cr_assert( 'copy keys stored empty by default', $settings['copy_cart_1_subject'] === '' );
nh_cr_assert(
	'sanitize default stays empty',
	nh_cr_sanitize_copy_field( nh_cr_default_copy( 'en_GB', 'cart', 1 )['subject'], 'en_GB', 'cart', 1, 'subject' ) === ''
);
nh_cr_assert(
	'sanitize custom kept',
	nh_cr_sanitize_copy_field( 'My subject', 'en_GB', 'cart', 1, 'subject' ) === 'My subject'
);
nh_cr_assert(
	'editor shows translated default',
	nh_cr_editor_value( $settings, 'sv_SE', 'cart', 1, 'button' ) === nh_cr_default_copy( 'sv_SE', 'cart', 1 )['button']
);
$settings['copy_cart_1_button'] = 'Kassa nu';
nh_cr_assert( 'editor shows override', nh_cr_editor_value( $settings, 'sv_SE', 'cart', 1, 'button' ) === 'Kassa nu' );
$eff = nh_cr_effective_copy( $settings, 'sv_SE', 'cart', 1 );
nh_cr_assert( 'effective uses override', $eff['button'] === 'Kassa nu' );
nh_cr_assert( 'effective keeps default intro', $eff['intro'] === nh_cr_default_copy( 'sv_SE', 'cart', 1 )['intro'] );

$row = (object) array(
	'type'             => 'cart',
	'emails_sent'      => 0,
	'updated_at'       => date( 'Y-m-d H:i:s', 1_000_000 ),
	'first_emailed_at' => null,
	'emailed_at'       => null,
);
$now = 1_000_000 + ( 59 * 60 );
nh_cr_assert( 'cart not due at 59 min', nh_cr_next_due_step( $row, $settings, $now ) === 0 );
$now = 1_000_000 + ( 60 * 60 );
nh_cr_assert( 'cart due at 60 min', nh_cr_next_due_step( $row, $settings, $now ) === 1 );

$check = clone $row;
$check->type = 'checkout';
$now = 1_000_000 + ( 6 * 60 );
nh_cr_assert( 'checkout due after 5 min', nh_cr_next_due_step( $check, $settings, $now ) === 1 );

$row->emails_sent      = 1;
$row->first_emailed_at = date( 'Y-m-d H:i:s', 2_000_000 );
$now = 2_000_000 + ( 23 * HOUR_IN_SECONDS );
nh_cr_assert( 'email 2 not due at 23h', nh_cr_next_due_step( $row, $settings, $now ) === 0 );
$now = 2_000_000 + ( 24 * HOUR_IN_SECONDS );
nh_cr_assert( 'email 2 due at 24h', nh_cr_next_due_step( $row, $settings, $now ) === 2 );

$row->emails_sent = 2;
$now = 2_000_000 + ( 71 * HOUR_IN_SECONDS );
nh_cr_assert( 'email 3 not due at 71h', nh_cr_next_due_step( $row, $settings, $now ) === 0 );
$now = 2_000_000 + ( 72 * HOUR_IN_SECONDS );
nh_cr_assert( 'email 3 due at 72h', nh_cr_next_due_step( $row, $settings, $now ) === 3 );

$row->emails_sent = 3;
nh_cr_assert( 'no fourth email', nh_cr_next_due_step( $row, $settings, $now ) === 0 );

$snap = nh_cr_snapshot_item(
	array(
		'product_id'     => 12,
		'variation_id'   => 0,
		'quantity'       => 2,
		'line_total'     => 99,
		'line_tax'       => 1,
		'data'           => new stdClass(),
		'nh_custom_size' => array(
			'width_mm' => 800,
		),
	)
);
nh_cr_assert( 'snapshot keeps custom cut', isset( $snap['cart_item_data']['nh_custom_size'] ) );
nh_cr_assert( 'snapshot drops line_total from restore data', ! isset( $snap['cart_item_data']['line_total'] ) );
nh_cr_assert( 'snapshot keeps line_total for email table', isset( $snap['line_total'] ) && abs( $snap['line_total'] - 100.0 ) < 0.01 );
nh_cr_assert( 'snapshot has image_url key', array_key_exists( 'image_url', $snap ) );
nh_cr_assert( 'snapshot drops product object', ! isset( $snap['cart_item_data']['data'] ) );

nh_cr_assert(
	'skip email if they already paid',
	nh_cr_should_email_cancelled_checkout( 'cancelled', 'svea_checkout', true ) === false
);
nh_cr_assert(
	'email unpaid svea cancel',
	nh_cr_should_email_cancelled_checkout( 'cancelled', 'svea_checkout', false ) === true
);
nh_cr_assert(
	'bacs on-hold closes recovery',
	nh_cr_order_closes_recovery( false, 'on-hold', 'bacs' ) === true
);
nh_cr_assert(
	'bacs pending at checkout still closes recovery',
	nh_cr_order_closes_recovery( false, 'pending', 'bacs' ) === true
);
nh_cr_assert(
	'svea pending stays open',
	nh_cr_order_closes_recovery( false, 'pending', 'svea_checkout' ) === false
);
nh_cr_assert(
	'paid svea closes recovery',
	nh_cr_order_closes_recovery( true, 'processing', 'svea_checkout' ) === true
);
nh_cr_assert(
	'cancelled bacs does not close',
	nh_cr_order_closes_recovery( false, 'cancelled', 'bacs' ) === false
);

$hash_a = nh_cr_cart_hash( array( $snap ) );
$hash_b = nh_cr_cart_hash( array( $snap ) );
nh_cr_assert( 'hash stable', $hash_a === $hash_b );

$kustom = nh_cr_identity_from_payload(
	array(
		'email'       => 'anna@example.de',
		'given_name'  => 'Anna',
		'family_name' => 'Müller',
	)
);
nh_cr_assert( 'kustom change email', $kustom['email'] === 'anna@example.de' );
nh_cr_assert( 'kustom given_name', $kustom['first_name'] === 'Anna' );
nh_cr_assert( 'kustom family_name', $kustom['last_name'] === 'Müller' );
$nested = nh_cr_identity_from_payload(
	array(
		'billing_address' => array(
			'email'       => 'hans@example.de',
			'given_name'  => 'Hans',
			'family_name' => 'Berg',
		),
	)
);
nh_cr_assert( 'kustom nested email', $nested['email'] === 'hans@example.de' );
$obf = nh_cr_identity_from_payload( array( 'email' => 'a***@klarna.com' ) );
nh_cr_assert( 'kustom skips obfuscated email', $obf['email'] === '' );
$svea = nh_cr_identity_from_svea_module(
	array(
		'EmailAddress'   => 'anna@norhage.se',
		'BillingAddress' => array(
			'FirstName' => 'Anna',
			'LastName'  => 'Svensson',
		),
	)
);
nh_cr_assert( 'svea module email', $svea['email'] === 'anna@norhage.se' );
nh_cr_assert( 'svea module first', $svea['first_name'] === 'Anna' );
$svea_full = nh_cr_identity_from_svea_module(
	array(
		'EmailAddress'   => 'hans@norhage.se',
		'BillingAddress' => array(
			'FullName' => 'Hans Berg',
		),
	)
);
nh_cr_assert( 'svea full name split', $svea_full['first_name'] === 'Hans' && $svea_full['last_name'] === 'Berg' );

$located = nh_cr_identity_from_payload(
	array(
		'billing_address' => array(
			'postal_code' => '141 40',
			'city'        => 'Huddinge',
			'country'     => 'SE',
		),
	)
);
nh_cr_assert( 'kustom nested postcode', $located['postcode'] === '141 40' );
nh_cr_assert( 'kustom nested city', $located['city'] === 'Huddinge' );
nh_cr_assert( 'kustom nested country', $located['country'] === 'SE' );
$obf_post = nh_cr_identity_from_payload( array( 'postal_code' => '12•••' ) );
nh_cr_assert( 'skips obfuscated postcode', $obf_post['postcode'] === '' );
$country_only = nh_cr_merge_profile( nh_cr_empty_profile(), array( 'country' => 'NO' ) );
nh_cr_assert( 'country alone is not a signal', $country_only['country'] === '' && nh_cr_profile_has_value( $country_only ) === false );
$with_post = nh_cr_merge_profile( nh_cr_empty_profile(), array( 'postcode' => '0150', 'country' => 'NO' ) );
nh_cr_assert( 'postcode keeps country', $with_post['postcode'] === '0150' && $with_post['country'] === 'NO' );
nh_cr_assert( 'postcode is a signal', nh_cr_profile_has_value( $with_post ) === true );
$svea_post = nh_cr_identity_from_svea_module(
	array(
		'BillingAddress' => array(
			'PostalCode'  => '113 46',
			'City'        => 'Stockholm',
			'CountryCode' => 'SE',
		),
	)
);
nh_cr_assert( 'svea module postcode', $svea_post['postcode'] === '113 46' && $svea_post['country'] === 'SE' );
nh_cr_assert(
	'cart summary lists items',
	nh_cr_cart_summary(
		array(
			array( 'name' => 'Kanalplast', 'quantity' => 2 ),
			array( 'name' => 'Greenhouse', 'quantity' => 1 ),
		)
	) === '2 × Kanalplast, 1 × Greenhouse'
);
$many = array(
	array( 'name' => 'A', 'quantity' => 1 ),
	array( 'name' => 'B', 'quantity' => 1 ),
	array( 'name' => 'C', 'quantity' => 1 ),
	array( 'name' => 'D', 'quantity' => 1 ),
);
nh_cr_assert( 'cart summary truncates', strpos( nh_cr_cart_summary( $many, 3 ), '…' ) !== false );
nh_cr_assert( 'decode cart counts all lines', nh_cr_cart_item_count( $many ) === 4 );
$cut = array(
	'name'           => 'Kanalplast',
	'quantity'       => 2,
	'line_total'     => 199,
	'cart_item_data' => array(
		'nh_custom_size' => array(
			'width_mm'  => 800,
			'length_mm' => 2000,
		),
	),
);
nh_cr_assert( 'item qty name', nh_cr_cart_item_qty_name( $cut ) === '2 × Kanalplast' );
nh_cr_assert( 'custom cut meta', nh_cr_cart_item_meta_lines( $cut ) === array( '800 mm × 2000 mm' ) );
nh_cr_assert( 'grand total', abs( nh_cr_cart_grand_total( array( $cut, $cut ) ) - 398.0 ) < 0.01 );
nh_cr_assert(
	'location format',
	nh_cr_format_location( array( 'postcode' => '14140', 'city' => '', 'country' => 'SE' ) ) === '14140, SE'
);
nh_cr_assert( 'obfuscated helper', nh_cr_looks_obfuscated( 'a***@klarna.com' ) === true );

nh_cr_assert( 'empty ua is unknown', nh_cr_classify_client( '' ) === 'unknown' );
nh_cr_assert(
	'chrome desktop',
	nh_cr_classify_client( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36' ) === 'desktop'
);
nh_cr_assert(
	'mac safari desktop',
	nh_cr_classify_client( 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15' ) === 'desktop'
);
nh_cr_assert(
	'iphone is mobile',
	nh_cr_classify_client( 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' ) === 'mobile'
);
nh_cr_assert(
	'android phone is mobile',
	nh_cr_classify_client( 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36' ) === 'mobile'
);
nh_cr_assert(
	'ipad is tablet',
	nh_cr_classify_client( 'Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1' ) === 'tablet'
);
nh_cr_assert(
	'android tablet without mobile is tablet',
	nh_cr_classify_client( 'Mozilla/5.0 (Linux; Android 12; SM-T870) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36' ) === 'tablet'
);
nh_cr_assert(
	'googlebot is bot not desktop',
	nh_cr_classify_client( 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' ) === 'bot'
);
nh_cr_assert(
	'googlebot smartphone is bot not mobile',
	nh_cr_classify_client( 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' ) === 'bot'
);
nh_cr_assert( 'facebook preview is bot', nh_cr_classify_client( 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)' ) === 'bot' );
nh_cr_assert( 'curl is bot', nh_cr_classify_client( 'curl/8.0.1' ) === 'bot' );
nh_cr_assert( 'python requests is bot', nh_cr_classify_client( 'python-requests/2.31.0' ) === 'bot' );
nh_cr_assert( 'bingpreview is bot', nh_cr_classify_client( 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/534+ (KHTML, like Gecko) BingPreview/1.0b' ) === 'bot' );
nh_cr_assert( 'device label bot', nh_cr_device_label( 'bot' ) === 'Likely bot' );
nh_cr_assert( 'device keys include bot', in_array( 'bot', nh_cr_device_keys(), true ) );
nh_cr_assert( 'ua truncated to 191', strlen( nh_cr_truncate_user_agent( str_repeat( 'a', 300 ) ) ) === 191 );

$pal = nh_cr_palette();
nh_cr_assert( 'palette green', $pal['green'] === '#00704A' );
nh_cr_assert( 'palette gold', $pal['gold'] === '#C89F63' );

$named_parts = NH_CR_Mailer::preview_parts( 'cart', 1, 'sv_SE', 'Anna' );
nh_cr_assert( 'preview subject uses name', strpos( $named_parts['subject'], 'Anna' ) !== false );
nh_cr_assert( 'preview html greets by name', strpos( $named_parts['html'], 'Hej Anna' ) !== false );
nh_cr_assert( 'preview html has cart table', strpos( $named_parts['html'], 'Kanalplast' ) !== false );
nh_cr_assert( 'preview html uses green CTA', strpos( $named_parts['html'], '#00704A' ) !== false );
nh_cr_assert( 'preview html uses forest header', strpos( $named_parts['html'], '#1E3932' ) !== false );
nh_cr_assert( 'preview html has unsubscribe', strpos( $named_parts['html'], 'nh_cr_unsub' ) !== false );

$anon_parts = NH_CR_Mailer::preview_parts( 'cart', 1, 'en_GB', '' );
nh_cr_assert( 'anon subject drops placeholder', strpos( $anon_parts['subject'], '{first_name}' ) === false );
nh_cr_assert( 'anon subject capitalizes', strpos( $anon_parts['subject'], 'Your Norhage cart is saved' ) !== false );
nh_cr_assert( 'anon greeting has no name', strpos( $anon_parts['html'], 'Hi,' ) !== false );

$help = nh_cr_help_popup_copy( 'nb_NO' );
nh_cr_assert( 'nb help title', $help['title'] === 'Holder fraktprisen deg tilbake?' );
nh_cr_assert( 'nb help chat', $help['chat'] !== '' );
nh_cr_assert( 'nb help kicker placeholder', strpos( $help['kicker'], '%s' ) !== false );
$help_en = nh_cr_help_popup_copy( 'en_GB' );
nh_cr_assert( 'en help falls back', $help_en['dismiss'] === 'No thanks' );
$defaults = nh_cr_default_settings();
nh_cr_assert( 'help popup on by default', ! empty( $defaults['help_popup'] ) );
nh_cr_assert( 'help popup delay 45s', (int) $defaults['help_popup_seconds'] === 45 );
foreach ( array( 'sv_SE', 'da_DK', 'fi', 'de_DE', 'lt_LT' ) as $loc ) {
	$pack = nh_cr_help_popup_copy( $loc );
	nh_cr_assert( $loc . ' help keys', isset( $pack['title'], $pack['body'], $pack['chat'], $pack['checkout'], $pack['dismiss'] ) );
}

$doc = NH_CR_Mailer::preview_document( 'checkout', 3, 'nb_NO', 'Anna' );
nh_cr_assert( 'document wraps heading', strpos( $doc, 'Siste sjanse' ) !== false );
nh_cr_assert( 'document uses cream page background', strpos( $doc, '#F1E6D6' ) !== false );

if ( $failures ) {
	fwrite( STDERR, "$failures failed\n" );
	exit( 1 );
}
echo "All nh-cart-recovery tests passed\n";
exit( 0 );
