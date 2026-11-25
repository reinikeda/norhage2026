<?php
/**
 * CLI tests: Made by Norhage product label.
 *
 * Run: php public/wp-content/plugins/product-labels/tests/test-made-by-label.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

$nhg_meta = array();

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
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key, $single = false ) {
		global $nhg_meta;
		unset( $single );
		return isset( $nhg_meta[ $post_id ][ $key ] ) ? $nhg_meta[ $post_id ][ $key ] : '';
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( '_x' ) ) {
	function _x( $text, $context, $domain = '' ) {
		unset( $context, $domain );
		return $text;
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
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain() {
		return true;
	}
}
if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		protected $id;
		protected $type = 'simple';
		protected $on_sale = false;
		protected $in_stock = true;
		protected $created = null;

		public function __construct( $id ) {
			$this->id = (int) $id;
		}
		public function get_id() {
			return $this->id;
		}
		public function is_type( $type ) {
			return $this->type === $type;
		}
		public function is_on_sale() {
			return (bool) $this->on_sale;
		}
		public function is_in_stock() {
			return (bool) $this->in_stock;
		}
		public function get_date_created() {
			return $this->created;
		}
		public function get_regular_price() {
			return 0;
		}
		public function get_sale_price() {
			return 0;
		}
		public function get_children() {
			return array();
		}
		public function get_low_stock_amount() {
			return null;
		}
		public function get_stock_quantity() {
			return null;
		}
		public function set_created_days_ago( $days ) {
			$this->created = new class( $days ) {
				private $days;
				public function __construct( $days ) {
					$this->days = (int) $days;
				}
				public function getTimestamp() {
					return time() - ( $this->days * DAY_IN_SECONDS );
				}
			};
		}
	}
}

require_once dirname( __DIR__ ) . '/product-labels.php';

$failures = 0;
function nhg_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$plugin = new NHG_Product_Labels();

nhg_assert( 'made meta key is stable', '_nhg_made_by_norhage' === $plugin->made_meta_key() );

global $nhg_meta;
$nhg_meta[ 20 ][ '_nhg_made_by_norhage' ] = 'yes';
$nhg_meta[ 21 ][ '_nhg_made_by_norhage' ] = 'no';

$made_product = new WC_Product( 20 );
$plain        = new WC_Product( 21 );
$newish       = new WC_Product( 20 );
$newish->set_created_days_ago( 3 );

nhg_assert( 'yes meta is made by norhage', true === $plugin->is_made_by_norhage( $made_product ) );
nhg_assert( 'no meta is not made by norhage', false === $plugin->is_made_by_norhage( $plain ) );
nhg_assert( 'id helper works', true === $plugin->is_made_by_norhage( 20 ) );

$labels = $plugin->collect_labels( $newish );
nhg_assert( 'collect includes made', isset( $labels['made'] ) && 'made' === $labels['made']['key'] );
nhg_assert( 'collect includes new for recent product', isset( $labels['new'] ) );
nhg_assert( 'made line1 is Made by', 'Made by' === $labels['made']['line1'] );
nhg_assert( 'made line2 is Norhage', 'Norhage' === $labels['made']['line2'] );

ob_start();
$ref = new ReflectionClass( $plugin );
$method = $ref->getMethod( 'print_badges' );
$method->setAccessible( true );
$method->invoke( $plugin, $labels );
$html = ob_get_clean();

nhg_assert( 'badge html has made class', false !== strpos( $html, 'nhg-badge--made' ) );
nhg_assert( 'badge html has new class', false !== strpos( $html, 'nhg-badge--new' ) );
nhg_assert(
	'made badge is printed before new badge',
	strpos( $html, 'nhg-badge--made' ) < strpos( $html, 'nhg-badge--new' )
);

$css = file_get_contents( dirname( __DIR__ ) . '/css/labels.css' );
nhg_assert( 'css defines made circle size', false !== strpos( $css, '--nhg-made-size' ) );
nhg_assert( 'css stacks new under made', false !== strpos( $css, '.nhg-badge--made ~ .nhg-badge--new' ) );
nhg_assert(
	'css no longer pushes all trailing badges under sale/custom',
	false === strpos( $css, '.nhg-badge--sale ~ .nhg-badge--custom ~ .nhg-badge' )
);

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll tests passed\n";
exit( $failures ? 1 : 0 );
