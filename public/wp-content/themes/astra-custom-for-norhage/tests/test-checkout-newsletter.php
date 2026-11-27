<?php
/**
 * CLI tests: checkout newsletter opt-in (Sender.net).
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-checkout-newsletter.php
 */

$failures = 0;

function nh_nl_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$theme = dirname( __DIR__ );
$inc   = file_get_contents( $theme . '/inc/checkout-newsletter.php' );
$fn    = file_get_contents( $theme . '/functions.php' );
$css   = file_get_contents( $theme . '/assets/css/checkout.css' );
$form  = file_get_contents( $theme . '/woocommerce/checkout/form-checkout.php' );

nh_nl_assert( 'checkout newsletter module is required', false !== strpos( $fn, 'checkout-newsletter.php' ) );
nh_nl_assert( 'shared Sender helper exists', false !== strpos( $fn, 'function nh_sender_subscribe_email' ) );
nh_nl_assert( 'AJAX handler uses shared helper', false !== strpos( $fn, 'nh_sender_subscribe_email( $email )' ) );

nh_nl_assert(
	'classic opt-in renders before Place order',
	false !== strpos( $inc, 'function nh_checkout_render_newsletter_before_place_order' )
);
$payment = file_get_contents( $theme . '/woocommerce/checkout/payment.php' );
nh_nl_assert(
	'payment template calls newsletter before place-order',
	false !== strpos( $payment, 'nh_checkout_render_newsletter_before_place_order' )
);
nh_nl_assert(
	'newsletter defaults to checked',
	(bool) preg_match( '/function nh_checkout_newsletter_is_checked[\s\S]*return true;/s', $inc )
);
nh_nl_assert(
	'session capture on update_order_review',
	false !== strpos( $inc, "add_action( 'woocommerce_checkout_update_order_review'" )
);
nh_nl_assert(
	'subscribe runs after order processed',
	false !== strpos( $inc, "add_action( 'woocommerce_checkout_order_processed'" )
);
nh_nl_assert(
	'snippet path renders opt-in above iframe',
	false !== strpos( $form, 'nh_checkout_render_newsletter_optin_for_snippet' )
);
nh_nl_assert(
	'checkout CSS styles newsletter as its own card',
	false !== strpos( $css, '.nh-checkout-newsletter-block' )
		&& false !== strpos( $css, '.nh-checkout-newsletter__label' )
);
nh_nl_assert(
	'delivery radios kill Astra label pseudo bullets',
	false !== strpos( $css, '.nh-checkout-delivery__methods' )
		&& false !== strpos( $css, 'label::before' )
		&& false !== strpos( $css, 'content: none !important' )
);

$locales = array( 'nb_NO', 'sv_SE', 'da_DK', 'de_DE', 'fi', 'lt_LT' );
$required = array(
	'Yes, email me offers and gardening tips',
	'Optional. Uses the email from your order. Unsubscribe anytime.',
);
foreach ( $locales as $locale ) {
	$po = $theme . '/languages/nh-theme-' . $locale . '.po';
	if ( ! is_readable( $po ) ) {
		// Theme PO files are locale-named without text-domain prefix.
		$po = $theme . '/languages/' . $locale . '.po';
	}
	nh_nl_assert( "po exists for {$locale}", is_readable( $po ) );
	$po_text = is_readable( $po ) ? file_get_contents( $po ) : '';
	foreach ( $required as $msgid ) {
		nh_nl_assert(
			"{$locale} translates {$msgid}",
			(bool) preg_match( '/msgid "' . preg_quote( $msgid, '/' ) . '"\s*\nmsgstr ".+"/s', $po_text )
		);
	}
}

if ( $failures ) {
	echo "\n{$failures} test(s) failed\n";
	exit( 1 );
}

echo "\nAll tests passed\n";
exit( 0 );
