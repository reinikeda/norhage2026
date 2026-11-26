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

$nhg_meta  = array();
$nhg_terms = array();

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
if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( $taxonomy ) {
		return 'product_brand' === $taxonomy;
	}
}
if ( ! function_exists( 'wp_get_post_terms' ) ) {
	function wp_get_post_terms( $post_id, $taxonomy ) {
		global $nhg_terms;
		unset( $taxonomy );
		return isset( $nhg_terms[ $post_id ] ) ? $nhg_terms[ $post_id ] : array();
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return is_object( $thing ) && isset( $thing->errors );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return is_string( $string ) ? strip_tags( $string ) : '';
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
$nhg_is_product = false;
$nhg_is_admin   = false;
if ( ! function_exists( 'is_product' ) ) {
	function is_product() {
		global $nhg_is_product;
		return (bool) $nhg_is_product;
	}
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		global $nhg_is_admin;
		return (bool) $nhg_is_admin;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		if ( 'woocommerce_notify_low_stock_amount' === $key ) {
			return 2;
		}
		return $default;
	}
}
if ( ! function_exists( 'wc_price' ) ) {
	function wc_price( $amount ) {
		return number_format( (float) $amount, 2, ',', ' ' ) . '&nbsp;&euro;';
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
		public function managing_stock() {
			return false;
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
nhg_assert( 'slug norhage is house brand', true === $plugin->brand_is_norhage( 'norhage', 'Anything' ) );
nhg_assert( 'name Norhage is house brand', true === $plugin->brand_is_norhage( 'other', 'Norhage' ) );
nhg_assert( 'Arla is not house brand', false === $plugin->brand_is_norhage( 'arla', 'Arla' ) );

global $nhg_meta, $nhg_terms;
$nhg_meta[ 20 ][ '_nhg_made_by_norhage' ] = 'yes';
$nhg_meta[ 21 ][ '_nhg_made_by_norhage' ] = 'no';
$nhg_meta[ 22 ][ '_nhg_made_by_norhage' ] = 'no';
$nhg_terms[ 22 ] = array(
	(object) array( 'slug' => 'norhage', 'name' => 'Norhage' ),
);
$nhg_terms[ 23 ] = array(
	(object) array( 'slug' => 'arla', 'name' => 'Arla' ),
);
$nhg_terms[ 24 ] = array(
	(object) array( 'slug' => 'norhage', 'name' => 'Norhage' ),
	(object) array( 'slug' => 'arla', 'name' => 'Arla' ),
);

$made_product = new WC_Product( 20 );
$plain        = new WC_Product( 21 );
$branded      = new WC_Product( 22 );
$supplier     = new WC_Product( 23 );
$mixed        = new WC_Product( 24 );
$newish       = new WC_Product( 22 );
$newish->set_created_days_ago( 3 );

nhg_assert( 'yes meta is made by norhage', true === $plugin->is_made_by_norhage( $made_product ) );
nhg_assert( 'no meta and no brand is not made', false === $plugin->is_made_by_norhage( $plain ) );
nhg_assert( 'Norhage brand auto-enables made label', true === $plugin->is_made_by_norhage( $branded ) );
nhg_assert( 'supplier brand alone is not made', false === $plugin->is_made_by_norhage( $supplier ) );
nhg_assert( 'mixed brands still match Norhage', true === $plugin->is_made_by_norhage( $mixed ) );
nhg_assert( 'id helper works for branded product', true === $plugin->is_made_by_norhage( 22 ) );

$labels = $plugin->collect_labels( $newish );
nhg_assert( 'collect includes made from brand', isset( $labels['made'] ) && 'made' === $labels['made']['key'] );
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
nhg_assert( 'made badge uses stacked lines', false !== strpos( $html, 'nhg-made-text__line' ) );
nhg_assert(
	'made badge is printed before new badge',
	strpos( $html, 'nhg-badge--made' ) < strpos( $html, 'nhg-badge--new' )
);

$exact = $plugin->format_sale_badge( 'exact', 11 );
nhg_assert( 'exact sale badge uses Save + money', 0 === strpos( $exact['text'], 'Save ' ) && false !== strpos( $exact['text'], '11,00' ) );

$upto = $plugin->format_sale_badge( 'upto', 50.5 );
nhg_assert( 'upto sale badge uses Save up to', 0 === strpos( $upto['text'], 'Save up to ' ) );

$sale_product = new class( 30 ) extends WC_Product {
	protected $on_sale = true;
	public function get_regular_price() {
		return 100;
	}
	public function get_sale_price() {
		return 89;
	}
};
$sale_labels = $plugin->collect_labels( $sale_product );
nhg_assert( 'collect sale uses Save money text', isset( $sale_labels['sale'] ) && 0 === strpos( $sale_labels['sale']['text'], 'Save ' ) );

$sale_labels_2 = $plugin->collect_labels( $sale_product );
nhg_assert( 'collect_labels is cached per request', $sale_labels === $sale_labels_2 );

ob_start();
$method->invoke( $plugin, $sale_labels, true );
$sale_html = ob_get_clean();
nhg_assert( 'sale badge html includes Save text when included', false !== strpos( $sale_html, 'Save ' ) );

ob_start();
$method->invoke( $plugin, $sale_labels, false );
$no_sale_on_image = ob_get_clean();
nhg_assert( 'sale omitted from on-image print by default', false === strpos( $no_sale_on_image, 'nhg-badge--sale' ) );

$oos = new class( 31 ) extends WC_Product {
	protected $in_stock = false;
	protected $on_sale = true;
	public function get_regular_price() {
		return 100;
	}
	public function get_sale_price() {
		return 80;
	}
};
$oos_labels = $plugin->collect_labels( $oos );
nhg_assert( 'oos collect has only stock', array_keys( $oos_labels ) === array( 'stock' ) );
nhg_assert( 'oos stock type is out', 'out' === $oos_labels['stock']['stock_type'] );

$css = file_get_contents( dirname( __DIR__ ) . '/css/labels.css' );
nhg_assert( 'css defines circular made size', false !== strpos( $css, '--nhg-made-size:' ) );
nhg_assert( 'css made badge is a circle', false !== strpos( $css, 'border-radius: 50%' ) );
nhg_assert( 'css stacks new under made circle', false !== strpos( $css, '.nhg-labels:has(.nhg-badge--made) .nhg-badge--new' ) );
nhg_assert( 'css parks custom under wishlist clearance', false !== strpos( $css, '--nhg-wishlist-clearance' ) );
nhg_assert( 'css treats stock as a bottom bar', false !== strpos( $css, '.nhg-badge--stock' ) && false !== strpos( $css, 'bottom: var(--nhg-label-inset)' ) );
nhg_assert( 'css has tablet enhancement breakpoint', false !== strpos( $css, '@media (min-width: 768px)' ) );
nhg_assert(
	'css no longer pushes all trailing badges under sale/custom',
	false === strpos( $css, '.nhg-badge--sale ~ .nhg-badge--custom ~ .nhg-badge' )
);
nhg_assert( 'css defines below-image container', false !== strpos( $css, '.nhg-labels--below' ) );
nhg_assert( 'css defines Best sold badge', false !== strpos( $css, '.nhg-badge--best' ) );
nhg_assert( 'css defines under-image sale badge', false !== strpos( $css, '.nhg-badge--sale-below' ) );
nhg_assert( 'css hides Astra default OOS nodes', false !== strpos( $css, '.ast-shop-product-out-of-stock' ) );
nhg_assert( 'css hides Woo stock.out-of-stock in loop', false !== strpos( $css, '.stock.out-of-stock' ) );
$plugin_src = file_get_contents( dirname( __DIR__ ) . '/product-labels.php' );
nhg_assert(
	'Best sold hooks shop title_after for below-image badge',
	(bool) preg_match(
		"/add_action\\(\\s*'astra_woo_shop_title_after'\\s*,\\s*\\[\\s*\\\$this\\s*,\\s*'render_below_image_badge'\\s*\\]\\s*,\\s*1\\s*\\)/",
		$plugin_src
	)
);
nhg_assert(
	'Best sold is not registered on single title_after',
	! preg_match( "/add_action\\(\\s*'astra_woo_single_title_after'/", $plugin_src )
);
nhg_assert(
	'single Made by injects into gallery image HTML',
	false !== strpos( $plugin_src, 'inject_single_made_badge' )
		&& false !== strpos( $plugin_src, 'woocommerce_single_product_image_thumbnail_html' )
);
nhg_assert(
	'single Save note hooks summary after price',
	(bool) preg_match(
		"/add_action\\(\\s*'woocommerce_single_product_summary'\\s*,\\s*\\[\\s*\\\$this\\s*,\\s*'render_single_save_note'\\s*\\]\\s*,\\s*11\\s*\\)/",
		$plugin_src
	)
);
nhg_assert( 'css defines single Save note', false !== strpos( $css, '.nhg-save-note' ) );

$lang_dir = dirname( __DIR__ ) . '/languages';
$locales  = array( 'nb_NO', 'sv_SE', 'da_DK', 'de_DE', 'fi', 'lt_LT' );
$required_msgids = array(
	'Sale',
	'New',
	'Custom size',
	'Out of stock',
	'Low stock',
	'Made by',
	'Made by Norhage',
	'Best sold',
	'Save %s',
	'Save up to %s',
	'Show a circular “Made by Norhage” badge on the product image (top left). Products with brand Norhage get this automatically; tick here to force it on.',
);
foreach ( $locales as $locale ) {
	$po = $lang_dir . '/nhg-labels-' . $locale . '.po';
	$mo = $lang_dir . '/nhg-labels-' . $locale . '.mo';
	nhg_assert( "po exists for {$locale}", is_readable( $po ) );
	nhg_assert( "mo exists for {$locale}", is_readable( $mo ) );
	$po_text = file_get_contents( $po );
	foreach ( $required_msgids as $msgid ) {
		$pattern = '/msgid "' . preg_quote( $msgid, '/' ) . '"\s*\nmsgstr "(.+)"/';
		nhg_assert( "{$locale} translates {$msgid}", (bool) preg_match( $pattern, $po_text ) );
	}
}
nhg_assert( 'German Sale is Angebot', (bool) preg_match( '/msgid "Sale"\s*\nmsgstr "Angebot"/', file_get_contents( $lang_dir . '/nhg-labels-de_DE.po' ) ) );
nhg_assert( 'save note script exists', is_readable( dirname( __DIR__ ) . '/js/single-save-note.js' ) );
$save_js = file_get_contents( dirname( __DIR__ ) . '/js/single-save-note.js' );
nhg_assert( 'save note script places under price', false !== strpos( $save_js, 'insertAfter' ) );
$delivery_js = file_get_contents( dirname( __DIR__, 3 ) . '/themes/astra-custom-for-norhage/assets/js/delivery-time.js' );
nhg_assert( 'delivery script yields to Save note', false !== strpos( $delivery_js, 'nhg-save-note' ) );

$nhg_meta[ 40 ][ '_nh_catalog_highlight' ] = 'yes';
$nhg_meta[ 41 ][ '_nh_catalog_highlight' ] = 'no';
$highlight = new WC_Product( 40 );
$plain_hl  = new WC_Product( 41 );
nhg_assert( 'highlight meta enables Best sold gate', true === $plugin->is_catalog_highlight( $highlight ) );
nhg_assert( 'non-highlight skips Best sold gate', false === $plugin->is_catalog_highlight( $plain_hl ) );

global $product;
$product = $highlight;
ob_start();
$plugin->render_below_image_badge();
$best_html = ob_get_clean();
nhg_assert( 'below-image Best sold renders for highlight', false !== strpos( $best_html, 'nhg-badge--best' ) );
nhg_assert( 'below-image Best sold text present', false !== strpos( $best_html, 'Best sold' ) );

$product = $plain_hl;
ob_start();
$plugin->render_below_image_badge();
$no_best = ob_get_clean();
nhg_assert( 'non-highlight without sale prints nothing below', '' === $no_best );

$product = $sale_product;
ob_start();
$plugin->render_below_image_badge();
$sale_below = ob_get_clean();
nhg_assert( 'below-image Sale renders for on-sale product', false !== strpos( $sale_below, 'nhg-badge--sale-below' ) );
nhg_assert( 'below-image Sale shows Save text', false !== strpos( $sale_below, 'Save ' ) );

$product = $oos;
ob_start();
$plugin->render_below_image_badge();
$oos_below = ob_get_clean();
nhg_assert( 'oos prints nothing under image', '' === $oos_below );

ob_start();
$method->invoke( $plugin, $plugin->collect_labels( $highlight ), false );
$thumb_html = ob_get_clean();
nhg_assert( 'on-image badges never include Best sold', false === strpos( $thumb_html, 'nhg-badge--best' ) );

$theme_css = file_get_contents( dirname( __DIR__, 3 ) . '/themes/astra-custom-for-norhage/style.css' );
nhg_assert( 'theme flex-orders under-image bars first', false !== strpos( $theme_css, '.nhg-labels--below' ) && false !== strpos( $theme_css, 'order: -1' ) );
nhg_assert(
	'highlight summary has no top padding (flush bars)',
	(bool) preg_match(
		'/\.nh-catalog-highlight \.astra-shop-summary-wrap\s*\{[^}]*padding:\s*0\s+0\s+/s',
		$theme_css
	)
);
nhg_assert(
	'highlight star ratings keep summary inset',
	false !== strpos( $theme_css, 'nh-catalog-highlight .astra-shop-summary-wrap > .review-rating' )
		&& false !== strpos( $theme_css, 'margin-left: 0.7rem !important' )
);
nhg_assert(
	'catalog stars keep fixed 5.8em width',
	false !== strpos( $theme_css, 'width: 5.8em !important' )
);

global $nhg_is_product, $product;
$nhg_is_product = true;
$product        = $made_product;
$gallery_html   = '<div class="woocommerce-product-gallery__image"><img alt="" /></div>';
$injected       = $plugin->inject_single_made_badge( $gallery_html, 99 );
nhg_assert( 'PDP injects Made by into main image HTML', false !== strpos( $injected, 'nhg-badge--made' ) );
nhg_assert( 'PDP Made by nests inside image wrapper', 0 === strpos( $injected, '<div class="woocommerce-product-gallery__image">' ) );
nhg_assert( 'PDP Made by inject skips New badge', false === strpos( $injected, 'nhg-badge--new' ) );
nhg_assert( 'PDP Made by inject skips Sale badge', false === strpos( $injected, 'nhg-badge--sale' ) );
$second = $plugin->inject_single_made_badge( $gallery_html, 100 );
nhg_assert( 'PDP Made by injects only once per request', false === strpos( $second, 'nhg-badge--made' ) );

$product = $sale_product;
ob_start();
$plugin->render_single_save_note();
$save_note = ob_get_clean();
nhg_assert( 'PDP Save note renders under-price markup', false !== strpos( $save_note, 'nhg-save-note' ) );
nhg_assert( 'PDP Save note shows Save text', false !== strpos( $save_note, 'Save ' ) );

$var_data = $plugin->add_variation_save_html(
	[
		'display_regular_price' => 100,
		'display_price'         => 80,
	],
	$sale_product,
	$sale_product
);
nhg_assert( 'variation carries Save html', isset( $var_data['nhg_save_html'] ) && 0 === strpos( $var_data['nhg_save_html'], 'Save ' ) );

$var_plain = $plugin->add_variation_save_html(
	[
		'display_regular_price' => 50,
		'display_price'         => 50,
	],
	$sale_product,
	$sale_product
);
nhg_assert( 'non-sale variation clears Save html', isset( $var_plain['nhg_save_html'] ) && '' === $var_plain['nhg_save_html'] );

$nhg_is_product = false;

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll tests passed\n";
exit( $failures ? 1 : 0 );
