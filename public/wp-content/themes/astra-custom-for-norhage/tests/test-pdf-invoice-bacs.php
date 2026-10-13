<?php
/**
 * CLI tests: PDF invoices only for BACS orders.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-pdf-invoice-bacs.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return (string) $text;
	}
}

if ( ! function_exists( 'translate' ) ) {
	function translate( $text, $domain = 'default' ) {
		unset( $domain );
		return (string) $text;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return trim( strip_tags( (string) $string ) );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $text ) {
		return trim( (string) $text );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

require_once dirname( __DIR__ ) . '/inc/checkout-ux.php';

$failures = 0;

function nh_pdf_invoice_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "OK $label\n";
		return;
	}
	$failures++;
	fwrite( STDERR, "FAIL $label\n" );
}

class Nh_Pdf_Order_Stub {
	public $method;

	public function __construct( $method ) {
		$this->method = $method;
	}

	public function get_payment_method() {
		return $this->method;
	}
}

class Nh_Pdf_Document_Stub {
	public $type;
	public $order;
	public $already_exists = false;

	public function get_type() {
		return $this->type;
	}

	public function exists() {
		return $this->already_exists;
	}
}

function nh_pdf_doc( $type, $method = '', $exists = false ) {
	$document                    = new Nh_Pdf_Document_Stub();
	$document->type              = $type;
	$document->already_exists    = $exists;
	$document->order             = $method === '' ? null : new Nh_Pdf_Order_Stub( $method );
	return $document;
}

nh_pdf_invoice_assert(
	'bacs invoice is allowed',
	nh_checkout_pdf_invoice_is_allowed( true, nh_pdf_doc( 'invoice', 'bacs' ) ) === true
);
nh_pdf_invoice_assert(
	'svea invoice is blocked',
	nh_checkout_pdf_invoice_is_allowed( true, nh_pdf_doc( 'invoice', 'svea_checkout' ) ) === false
);
nh_pdf_invoice_assert(
	'kustom invoice is blocked',
	nh_checkout_pdf_invoice_is_allowed( true, nh_pdf_doc( 'invoice', 'kco' ) ) === false
);
nh_pdf_invoice_assert(
	'paypal invoice is blocked',
	nh_checkout_pdf_invoice_is_allowed( true, nh_pdf_doc( 'invoice', 'ppcp-gateway' ) ) === false
);
nh_pdf_invoice_assert(
	'makecommerce invoice is blocked',
	nh_checkout_pdf_invoice_is_allowed( true, nh_pdf_doc( 'invoice', 'makecommerce' ) ) === false
);
nh_pdf_invoice_assert(
	'packing slip stays allowed for svea',
	nh_checkout_pdf_invoice_is_allowed( true, nh_pdf_doc( 'packing-slip', 'svea_checkout' ) ) === true
);
nh_pdf_invoice_assert(
	'packing slip stays allowed for bacs',
	nh_checkout_pdf_invoice_is_allowed( true, nh_pdf_doc( 'packing-slip', 'bacs' ) ) === true
);
nh_pdf_invoice_assert(
	'existing paypal invoice stays downloadable',
	nh_checkout_pdf_invoice_is_allowed( true, nh_pdf_doc( 'invoice', 'ppcp-gateway', true ) ) === true
);
nh_pdf_invoice_assert(
	'invoice without an order is left unchanged',
	nh_checkout_pdf_invoice_is_allowed( true, nh_pdf_doc( 'invoice' ) ) === true
);
nh_pdf_invoice_assert(
	'already-disallowed invoice stays disallowed',
	nh_checkout_pdf_invoice_is_allowed( false, nh_pdf_doc( 'invoice', 'bacs' ) ) === false
);

$property_only         = new stdClass();
$property_only->type   = 'invoice';
$property_only->order  = new Nh_Pdf_Order_Stub( 'kco' );
nh_pdf_invoice_assert(
	'invoice type property is enough without get_type()',
	nh_checkout_pdf_invoice_is_allowed( true, $property_only ) === false
);

if ( $failures > 0 ) {
	exit( 1 );
}

echo "All tests passed.\n";
