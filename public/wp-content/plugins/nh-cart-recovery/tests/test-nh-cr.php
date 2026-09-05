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

$hash_a = nh_cr_cart_hash( array( $snap ) );
$hash_b = nh_cr_cart_hash( array( $snap ) );
nh_cr_assert( 'hash stable', $hash_a === $hash_b );

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

$doc = NH_CR_Mailer::preview_document( 'checkout', 3, 'nb_NO', 'Anna' );
nh_cr_assert( 'document wraps heading', strpos( $doc, 'Siste sjanse' ) !== false );
nh_cr_assert( 'document uses cream page background', strpos( $doc, '#F1E6D6' ) !== false );

if ( $failures ) {
	fwrite( STDERR, "$failures failed\n" );
	exit( 1 );
}
echo "All nh-cart-recovery tests passed\n";
exit( 0 );
