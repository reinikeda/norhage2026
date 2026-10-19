<?php
/**
 * CLI tests: delivery-time attribute recognition and cart estimate.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-delivery-time.php
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

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		$args = func_get_args();
		unset( $tag, $args );
		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return (string) $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) __( $text, $domain ), ENT_QUOTES, 'UTF-8' );
	}
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

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return trim( strip_tags( (string) $string ) );
	}
}

$nhdt_locale = 'en_US';

if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		global $nhdt_locale;
		return $nhdt_locale ? $nhdt_locale : 'en_US';
	}
}

require_once dirname( __DIR__ ) . '/inc/delivery-time.php';

$failures = 0;

function nhdt_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

class Nhdt_Product_Stub {
	public $attrs           = array();
	public $attribute_list  = array();
	public $parent_id       = 0;

	public function get_attribute( $slug ) {
		return isset( $this->attrs[ $slug ] ) ? $this->attrs[ $slug ] : '';
	}

	public function get_attributes() {
		return $this->attribute_list;
	}

	public function get_parent_id() {
		return $this->parent_id;
	}
}

class Nhdt_Attr_Stub {
	public $name;

	public function __construct( $name ) {
		$this->name = $name;
	}

	public function get_name() {
		return $this->name;
	}
}

class Nhdt_Cart_Stub {
	public $items;

	public function __construct( $items ) {
		$this->items = $items;
	}

	public function get_cart() {
		return $this->items;
	}
}

$english = new Nhdt_Product_Stub();
$english->attrs['pa_delivery-time'] = '2-4 days';
nhdt_assert( 'reads pa_delivery-time', nhdt_get_delivery_text( $english ) === '2-4 days' );

$swedish = new Nhdt_Product_Stub();
$swedish->attrs['pa_leveranstid'] = '5-7 arbetsdagar';
global $nhdt_locale;
$nhdt_locale = 'sv_SE';
nhdt_assert( 'Swedish shop uses pa_leveranstid', nhdt_get_attribute_slug() === 'pa_leveranstid' );
nhdt_assert( 'reads translated Swedish attribute', nhdt_get_delivery_text( $swedish ) === '5-7 arbetsdagar' );

$nhdt_locale = 'en_US';
nhdt_assert( 'still finds pa_leveranstid from an English shop', nhdt_get_delivery_text( $swedish ) === '5-7 arbetsdagar' );

$lithuanian = new Nhdt_Product_Stub();
$lithuanian->attrs['pa_pristatymo-laikas'] = '3-5 d.d.';
nhdt_assert( 'reads Lithuanian attribute', nhdt_get_delivery_text( $lithuanian ) === '3-5 d.d.' );

$german_custom = new Nhdt_Product_Stub();
$german_custom->attribute_list = array( 'pa_lieferzeit-extra' => new Nhdt_Attr_Stub( 'pa_lieferzeit-extra' ) );
$german_custom->attrs['pa_lieferzeit-extra'] = '1-2 Wochen';
nhdt_assert( 'recognizes lieferzeit in a custom slug', nhdt_get_delivery_text( $german_custom ) === '1-2 Wochen' );

nhdt_assert( 'weeks beat days', nhdt_delivery_sort_key( '1-2 weeks' ) > nhdt_delivery_sort_key( '3-5 days' ) );
nhdt_assert( 'range uses the largest number', nhdt_delivery_sort_key( '2-4 days' ) === 4.0 );
nhdt_assert( 'Norwegian weeks convert to days', nhdt_delivery_sort_key( '2 uker' ) === 14.0 );

$fast = new Nhdt_Product_Stub();
$fast->attrs['pa_delivery-time'] = '2-4 days';
$slow = new Nhdt_Product_Stub();
$slow->attrs['pa_delivery-time'] = '1-2 weeks';
$none = new Nhdt_Product_Stub();

$mixed = new Nhdt_Cart_Stub(
	array(
		array( 'data' => $fast ),
		array( 'data' => $slow ),
	)
);
nhdt_assert( 'cart uses the longest estimate', nhdt_get_cart_estimated_delivery( $mixed ) === '1-2 weeks' );

$empty = new Nhdt_Cart_Stub(
	array(
		array( 'data' => $none ),
	)
);
nhdt_assert( 'no attribute means no estimate', nhdt_get_cart_estimated_delivery( $empty ) === '' );
nhdt_assert( 'markup is empty without an estimate', nhdt_cart_estimate_markup() === '' );

$only_slow = new Nhdt_Cart_Stub( array( array( 'data' => $slow ) ) );
$GLOBALS['nhdt_test_cart'] = $only_slow;

if ( ! function_exists( 'WC' ) ) {
	function WC() {
		$wc       = new stdClass();
		$wc->cart = isset( $GLOBALS['nhdt_test_cart'] ) ? $GLOBALS['nhdt_test_cart'] : null;
		return $wc;
	}
}

$markup = nhdt_cart_estimate_markup();
nhdt_assert( 'markup includes the label', strpos( $markup, 'Estimated delivery:' ) !== false );
nhdt_assert( 'markup includes the longest value', strpos( $markup, '1-2 weeks' ) !== false );

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} failed\n" );
	exit( 1 );
}

echo "all passed\n";
