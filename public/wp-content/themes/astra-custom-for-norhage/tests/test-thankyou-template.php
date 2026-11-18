<?php
/**
 * CLI tests: thank-you template matches WooCommerce 11.2.0 and stays translated.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-thankyou-template.php
 */

$failures = 0;

function nh_ty_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$theme = dirname( __DIR__ );
$file  = file_get_contents( $theme . '/woocommerce/checkout/thankyou.php' );

nh_ty_assert( 'template version is 11.2.0', false !== strpos( $file, '@version 11.2.0' ) );
nh_ty_assert( 'old 8.1.0 version is gone', false === strpos( $file, '8.1.0' ) );
nh_ty_assert( 'failed message uses the 11.2.0 filter', false !== strpos( $file, 'woocommerce_thankyou_order_failed_text' ) );
nh_ty_assert( 'received message uses the core template', false !== strpos( $file, "wc_get_template( 'checkout/order-received.php'" ) );
nh_ty_assert( 'thank-you wrapper class remains', false !== strpos( $file, 'woocommerce-order nh-thankyou' ) );

nh_ty_assert(
	'failed payment copy uses the theme domain',
	false !== strpos( $file, "esc_html__( 'The payment was not completed. Your order details are still saved. You can try again or choose another payment method.', 'nh-theme' )" )
);
nh_ty_assert(
	'retry button uses the theme domain',
	false !== strpos( $file, "esc_html_e( 'Try payment again', 'nh-theme' )" )
);
nh_ty_assert( 'account link uses WooCommerce translations', false !== strpos( $file, "esc_html_e( 'My account', 'woocommerce' )" ) );
nh_ty_assert( 'order number uses WooCommerce translations', false !== strpos( $file, "esc_html_e( 'Order number:', 'woocommerce' )" ) );
nh_ty_assert( 'date uses WooCommerce translations', false !== strpos( $file, "esc_html_e( 'Date:', 'woocommerce' )" ) );
nh_ty_assert( 'email uses WooCommerce translations', false !== strpos( $file, "esc_html_e( 'Email:', 'woocommerce' )" ) );
nh_ty_assert( 'total uses WooCommerce translations', false !== strpos( $file, "esc_html_e( 'Total:', 'woocommerce' )" ) );
nh_ty_assert( 'payment method uses WooCommerce translations', false !== strpos( $file, "esc_html_e( 'Payment method:', 'woocommerce' )" ) );

$theme_strings = array(
	'The payment was not completed. Your order details are still saved. You can try again or choose another payment method.',
	'Try payment again',
	'Thank you. Your order has been received.',
	'Thank you, %s. Your order has been received.',
);

foreach ( array( 'da_DK.po', 'de_DE.po', 'fi.po', 'lt_LT.po', 'nb_NO.po', 'sv_SE.po' ) as $catalog ) {
	$po = file_get_contents( $theme . '/languages/' . $catalog );
	foreach ( $theme_strings as $msgid ) {
		$pos = strpos( $po, 'msgid "' . $msgid . '"' );
		$ok  = false;
		if ( false !== $pos ) {
			$next = strpos( $po, 'msgstr "', $pos );
			$ok   = false !== $next && ! preg_match( '/msgstr ""\s*$/m', substr( $po, $next, 80 ) );
		}
		nh_ty_assert( $catalog . ' translates ' . $msgid, $ok );
	}
}

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
