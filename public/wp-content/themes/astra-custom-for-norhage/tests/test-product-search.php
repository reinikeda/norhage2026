<?php
/**
 * CLI tests: product search keeps SKU matches after the form is submitted.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-product-search.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['nh_search_hooks'][] = array( $hook, $callback, (int) $priority, (int) $accepted_args );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		unset( $tag, $callback );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return ! empty( $GLOBALS['nh_is_admin'] );
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $post = null ) {
		$id = (int) $post;
		if ( isset( $GLOBALS['nh_search_posts'][ $id ]['type'] ) ) {
			return $GLOBALS['nh_search_posts'][ $id ]['type'];
		}
		return 'product';
	}
}

if ( ! function_exists( 'get_post_status' ) ) {
	function get_post_status( $post = null ) {
		$id = (int) $post;
		if ( isset( $GLOBALS['nh_search_posts'][ $id ]['status'] ) ) {
			return $GLOBALS['nh_search_posts'][ $id ]['status'];
		}
		return 'publish';
	}
}

class Nh_Search_Wpdb {
	public $posts               = 'wp_posts';
	public $postmeta            = 'wp_postmeta';
	public $terms               = 'wp_terms';
	public $term_taxonomy       = 'wp_term_taxonomy';
	public $term_relationships  = 'wp_term_relationships';
	public $calls               = array();
	public $queue               = array();

	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	public function prepare( $sql, ...$args ) {
		return array(
			'sql'  => $sql,
			'args' => $args,
		);
	}

	public function get_col( $prepared ) {
		$this->calls[] = $prepared;
		if ( ! $this->queue ) {
			return array();
		}
		return array_shift( $this->queue );
	}
}

class Nh_Search_Query {
	public $main   = true;
	public $search = true;
	public $vars   = array();

	public function is_main_query() {
		return $this->main;
	}

	public function is_search() {
		return $this->search;
	}

	public function get( $key ) {
		return array_key_exists( $key, $this->vars ) ? $this->vars[ $key ] : null;
	}
}

require_once dirname( __DIR__ ) . '/inc/search.php';

$failures = 0;

function nh_search_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

function nh_search_balanced( $sql ) {
	$depth = 0;
	$len   = strlen( $sql );
	for ( $i = 0; $i < $len; $i++ ) {
		if ( '(' === $sql[ $i ] ) {
			$depth++;
		} elseif ( ')' === $sql[ $i ] ) {
			$depth--;
			if ( $depth < 0 ) {
				return false;
			}
		}
	}
	return 0 === $depth;
}

function nh_search_query( $term, $post_type = 'product' ) {
	$query               = new Nh_Search_Query();
	$query->vars['s']    = $term;
	$query->vars['post_type'] = $post_type;
	return $query;
}

function nh_search_core_sql( $term, $with_password = false ) {
	$sql = " AND (((wp_posts.post_title LIKE '%{$term}%') OR (wp_posts.post_excerpt LIKE '%{$term}%') OR (wp_posts.post_content LIKE '%{$term}%'))) ";
	if ( $with_password ) {
		$sql .= "AND (wp_posts.post_password = '') ";
	}
	return $sql;
}

$wpdb                = new Nh_Search_Wpdb();
$GLOBALS['wpdb']     = $wpdb;
$GLOBALS['nh_is_admin'] = false;

$core = nh_search_core_sql( 'NH-44120' );
$wpdb->queue = array( array( 15 ), array( 88 ), array( 15 ) );
$out = nrh_product_search_posts_search( $core, nh_search_query( 'NH-44120' ) );

nh_search_assert( 'submitted search includes the product sku', false !== strpos( $out, 'wp_posts.ID IN (15,88)' ) );
nh_search_assert( 'submitted search still matches the title', false !== strpos( $out, "post_title LIKE '%NH-44120%'" ) );
nh_search_assert( 'sku clause stays inside the search group', nh_search_balanced( $out ) );
nh_search_assert(
	'sku lookup reads product and variation skus',
	false !== strpos( $wpdb->calls[0]['sql'], "meta_key = '_sku'" )
	&& false !== strpos( $wpdb->calls[0]['sql'], "post_type = 'product'" )
	&& false !== strpos( $wpdb->calls[1]['sql'], "post_type = 'product_variation'" )
	&& false !== strpos( $wpdb->calls[1]['sql'], 'parent.ID' )
	&& '%NH-44120%' === $wpdb->calls[0]['args'][0]
);

$calls_after_first = count( $wpdb->calls );
$again = nrh_product_search_posts_search( $core, nh_search_query( 'NH-44120' ) );
nh_search_assert( 'same term reuses the sku lookup', $again === $out && count( $wpdb->calls ) === $calls_after_first );

$wpdb->calls = array();
$wpdb->queue = array( array( 4 ), array(), array() );
$short = nrh_product_search_posts_search( nh_search_core_sql( 'x' ), nh_search_query( 'x' ) );
nh_search_assert( 'one character search is left to wordpress', $short === nh_search_core_sql( 'x' ) && array() === $wpdb->calls );

$wpdb->calls = array();
$blog = nrh_product_search_posts_search( nh_search_core_sql( 'NH-44120' ), nh_search_query( 'NH-44120', 'post' ) );
nh_search_assert( 'blog search does not gain product skus', $blog === nh_search_core_sql( 'NH-44120' ) && array() === $wpdb->calls );

$secondary = nh_search_query( 'NH-9' );
$secondary->main = false;
$wpdb->calls = array();
$secondary_sql = nrh_product_search_posts_search( nh_search_core_sql( 'NH-9' ), $secondary );
nh_search_assert( 'secondary queries stay untouched', $secondary_sql === nh_search_core_sql( 'NH-9' ) && array() === $wpdb->calls );

$GLOBALS['nh_is_admin'] = true;
$wpdb->calls = array();
$admin = nrh_product_search_posts_search( nh_search_core_sql( 'NH-1' ), nh_search_query( 'NH-1' ) );
nh_search_assert( 'admin search is not rewritten', $admin === nh_search_core_sql( 'NH-1' ) && array() === $wpdb->calls );
$GLOBALS['nh_is_admin'] = false;

$protected = nh_search_core_sql( 'SKU-7', true );
$with_password = nrh_extend_product_search_sql( $protected, array( 3, 3, 0 ), 'wp_posts' );
nh_search_assert( 'password clause stays outside the match group', false !== strpos( $with_password, 'OR wp_posts.ID IN (3))' ) && false !== strpos( $with_password, "AND (wp_posts.post_password = '')" ) );
nh_search_assert( 'password sql stays balanced', nh_search_balanced( $with_password ) );
nh_search_assert(
	'password predicate follows the search group',
	strpos( $with_password, 'ID IN (3))' ) < strpos( $with_password, 'post_password' )
);

$empty = nrh_extend_product_search_sql( '', array( 9 ), 'wp_posts' );
nh_search_assert( 'empty search can still match an id', " AND (wp_posts.ID IN (9)) " === $empty );

$GLOBALS['nh_search_posts'] = array(
	12 => array( 'type' => 'product', 'status' => 'publish' ),
	40 => array( 'type' => 'product', 'status' => 'draft' ),
);
$wpdb->calls = array();
$wpdb->queue = array( array( 12, 0 ), array( 40 ) );
$sku_ids = nrh_search_sku_ids( ' VAR_1 ', 40 );
nh_search_assert( 'draft variation parents are dropped', array( 12 ) === $sku_ids );
nh_search_assert( 'sku term is trimmed before matching', '%VAR\\_1%' === $wpdb->calls[0]['args'][0] );

$wpdb->queue = array( array( 7 ) );
$tag_ids = nrh_search_tag_ids( 'greenhouse', 40 );
nh_search_assert( 'tag lookup is limited to product tags', array( 7 ) === $tag_ids && false !== strpos( $wpdb->calls[2]['sql'], "taxonomy = 'product_tag'" ) );

$underscored = new Nh_Search_Wpdb();
$GLOBALS['wpdb'] = $underscored;
$underscored->queue = array( array( 2 ), array() );
nrh_search_sku_ids( 'NH_1', 40 );
nh_search_assert( 'underscores in a sku are escaped', '%NH\\_1%' === $underscored->calls[0]['args'][0] );
$GLOBALS['wpdb'] = $wpdb;

$hooked = false;
foreach ( $GLOBALS['nh_search_hooks'] as $hook ) {
	if ( 'posts_search' === $hook[0] && 'nrh_product_search_posts_search' === $hook[1] && 20 === $hook[2] && 2 === $hook[3] ) {
		$hooked = true;
	}
}
nh_search_assert( 'posts_search filter is registered', $hooked );

$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/live-search.js' );
$enter_at = strpos( $js, "e.key === 'Enter'" );
$enter = false === $enter_at ? '' : substr( $js, $enter_at, 350 );
nh_search_assert(
	'enter on a highlighted result does not also submit the form',
	false !== strpos( $enter, 'e.preventDefault()' ) && strpos( $enter, 'e.preventDefault()' ) < strpos( $enter, 'window.location.href' )
);

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
exit( 0 );
