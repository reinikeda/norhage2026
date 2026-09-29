<?php
/**
 * Live Product Search (Shortcode + AJAX)
 * - Shortcode renders input + empty results <ul>
 * - AJAX searches: title/content, SKU (incl. variations → parent), product tags ONLY
 * - Returns up to 6 results, sorted by relevance then date desc
 * - Submitting the product search (?s=&post_type=product) uses the same SKU and tag
 *   matches, so Enter finds the products the live results already showed.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1) Shortcode: form + results container
add_shortcode( 'live_product_search', 'nrh_live_search_form' );
function nrh_live_search_form() {
	static $instance = 0;
	$instance++;

	$placeholder = esc_attr__( 'Search products…', 'nh-theme' );
	$label_text  = esc_html__( 'Search products', 'nh-theme' );
	$input_id    = 'nrh-search-input-' . $instance;
	$results_id  = 'nrh-search-results-' . $instance;
	$status_id   = 'nrh-search-status-' . $instance;

	return '
	<div class="nh-live-search">
		<label for="' . esc_attr( $input_id ) . '" class="screen-reader-text">' . $label_text . '</label>
		<input
			type="search"
			id="' . esc_attr( $input_id ) . '"
			name="s"
			placeholder="' . $placeholder . '"
			autocomplete="off"
			aria-label="' . $placeholder . '"
			role="combobox"
			aria-haspopup="listbox"
			aria-expanded="false"
			aria-autocomplete="list"
			aria-controls="' . esc_attr( $results_id ) . '"
		/>
		<ul
			id="' . esc_attr( $results_id ) . '"
			class="nh-live-results"
			role="listbox"
			aria-label="' . esc_attr__( 'Search results', 'nh-theme' ) . '"
			aria-hidden="true"
		></ul>
		<div id="' . esc_attr( $status_id ) . '" class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true"></div>
	</div>
	';
}

// 2) AJAX handler (public + logged-in)
add_action( 'wp_ajax_nopriv_nrh_live_search', 'nrh_live_search_callback' );
add_action( 'wp_ajax_nrh_live_search',        'nrh_live_search_callback' );

function nrh_live_search_callback() {
	// Basic input guard
	$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	if ( mb_strlen( $term ) < 2 ) {
		wp_send_json( [ 'items' => [], 'more' => false, 'total' => 0, 'url' => '' ] );
	}

	$cache_key = 'nrh_ls_' . md5( strtolower( $term ) . '|' . get_locale() );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		wp_send_json( $cached );
	}

	// Optional: mild HTTP caching guard for AJAX endpoints
	nocache_headers();

	$found       = [];  // product_id => weight
	$limit_each  = 40;  // soft cap per source before merging
	$final_limit = 6;   // items returned to UI

	/* ----------------------------------------
	 * A) Title/Content search (WP_Query 's')
	 *    Excludes "exclude-from-search" visibility
	 * --------------------------------------*/
	$q_title = new WP_Query( [
		'post_type'           => 'product',
		'post_status'         => 'publish',
		's'                   => $term,
		'posts_per_page'      => $limit_each,
		'fields'              => 'ids',
		'ignore_sticky_posts' => true,
		'tax_query'           => [
			[
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => [ 'exclude-from-search' ],
				'operator' => 'NOT IN',
			],
		],
	] );

	if ( $q_title->have_posts() ) {
		foreach ( $q_title->posts as $pid ) {
			$found[ $pid ] = max( $found[ $pid ] ?? 0, 30 ); // title/content relevance
		}
	}
	wp_reset_postdata();

	foreach ( nrh_search_sku_ids( $term, $limit_each ) as $pid ) {
		$found[ $pid ] = max( $found[ $pid ] ?? 0, 50 ); // SKU is most relevant
	}

	foreach ( nrh_search_tag_ids( $term, $limit_each ) as $pid ) {
		$found[ $pid ] = max( $found[ $pid ] ?? 0, 20 ); // tag name match relevance
	}

	/* ----------------------------------------
	 * D) Merge, sort by relevance (then by date), slice
	 * --------------------------------------*/
	if ( empty( $found ) ) {
		wp_send_json( [ 'items' => [], 'more' => false, 'total' => 0, 'url' => '' ] );
	}

	$ids = array_keys( $found );

	// Sort by weight desc; tie-breaker: newer first
	usort( $ids, function( $a, $b ) use ( $found ) {
		$wa = $found[ $a ];
		$wb = $found[ $b ];
		if ( $wa !== $wb ) return $wb <=> $wa;
		return get_post_time( 'U', true, $b ) <=> get_post_time( 'U', true, $a );
	} );

	$total    = count( $ids );
	$has_more = $total > $final_limit;

	$ids = array_slice( $ids, 0, $final_limit );

	/* ----------------------------------------
	 * E) Build JSON response
	 * --------------------------------------*/
	$items = [];
	foreach ( $ids as $id ) {
		$prod = wc_get_product( $id );
		if ( ! $prod ) continue;

		$items[] = [
			'title' => get_the_title( $id ), // let filters/localization handle title
			'link'  => get_permalink( $id ),
			'img'   => get_the_post_thumbnail_url( $id, 'thumbnail' ) ?: wc_placeholder_img_src(),
			// Price HTML returned as-is for Woo styling (handled safely on render side)
			'price' => $prod->get_price_html(),
		];
	}

	// Build a full search URL (products only)
	$search_url = add_query_arg(
		[ 's' => $term, 'post_type' => 'product' ],
		home_url( '/' )
	);

	$payload = [
		'items' => $items,
		'more'  => $has_more,
		'total' => $total,
		'url'   => esc_url_raw( $search_url ),
	];

	set_transient( $cache_key, $payload, 5 * MINUTE_IN_SECONDS );

	wp_send_json( $payload );
}

/**
 * Published product IDs whose own SKU, or a variation SKU, matches the term.
 *
 * Variation matches return the parent product, which is what both the live
 * dropdown and the search results page list.
 *
 * @param string $term  Raw search term.
 * @param int    $limit Max IDs per source.
 * @return int[]
 */
function nrh_search_sku_ids( $term, $limit = 40 ) {
	global $wpdb;

	$term  = trim( (string) $term );
	$limit = max( 1, (int) $limit );
	if ( $term === '' || ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
		return array();
	}

	$cache_key = 'sku|' . strtolower( $term ) . '|' . $limit;
	$cached    = nrh_search_id_cache( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$like = '%' . $wpdb->esc_like( $term ) . '%';

	$product_ids = $wpdb->get_col(
		$wpdb->prepare(
			"
			SELECT pm.post_id
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = '_sku'
			  AND pm.meta_value LIKE %s
			  AND p.post_type = 'product'
			  AND p.post_status = 'publish'
			LIMIT %d
			",
			$like,
			$limit
		)
	);

	// Variation SKU → parent. Parent must itself be a published product.
	$parent_ids = $wpdb->get_col(
		$wpdb->prepare(
			"
			SELECT DISTINCT parent.ID
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			INNER JOIN {$wpdb->posts} parent ON parent.ID = p.post_parent
			WHERE pm.meta_key = '_sku'
			  AND pm.meta_value LIKE %s
			  AND p.post_type = 'product_variation'
			  AND p.post_status = 'publish'
			  AND parent.post_type = 'product'
			  AND parent.post_status = 'publish'
			LIMIT %d
			",
			$like,
			$limit
		)
	);

	$ids = array();
	foreach ( array_merge( (array) $product_ids, (array) $parent_ids ) as $pid ) {
		$pid = (int) $pid;
		if ( $pid <= 0 ) {
			continue;
		}
		if ( function_exists( 'get_post_type' ) && function_exists( 'get_post_status' ) ) {
			if ( get_post_type( $pid ) !== 'product' || get_post_status( $pid ) !== 'publish' ) {
				continue;
			}
		}
		$ids[ $pid ] = $pid;
	}

	$ids = array_values( $ids );
	nrh_search_id_cache( $cache_key, $ids );
	return $ids;
}

/**
 * Published product IDs tagged with a product_tag whose name matches the term.
 *
 * @param string $term  Raw search term.
 * @param int    $limit Max IDs.
 * @return int[]
 */
function nrh_search_tag_ids( $term, $limit = 40 ) {
	global $wpdb;

	$term  = trim( (string) $term );
	$limit = max( 1, (int) $limit );
	if ( $term === '' || ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
		return array();
	}

	$cache_key = 'tag|' . strtolower( $term ) . '|' . $limit;
	$cached    = nrh_search_id_cache( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$like = '%' . $wpdb->esc_like( $term ) . '%';
	$rows = $wpdb->get_col(
		$wpdb->prepare(
			"
			SELECT DISTINCT tr.object_id
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt
			        ON tt.term_id = t.term_id AND tt.taxonomy = 'product_tag'
			INNER JOIN {$wpdb->term_relationships} tr
			        ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->posts} p
			        ON p.ID = tr.object_id
			WHERE p.post_type = 'product'
			  AND p.post_status = 'publish'
			  AND t.name LIKE %s
			LIMIT %d
			",
			$like,
			$limit
		)
	);

	$ids = array();
	foreach ( (array) $rows as $pid ) {
		$pid = (int) $pid;
		if ( $pid > 0 ) {
			$ids[ $pid ] = $pid;
		}
	}

	$ids = array_values( $ids );
	nrh_search_id_cache( $cache_key, $ids );
	return $ids;
}

/**
 * Request-local cache for SKU/tag ID lookups.
 *
 * Pass $ids to store. Omit it to read. Null means "not cached".
 *
 * @param string     $key Cache key.
 * @param int[]|null $ids IDs to store, or null to read.
 * @return int[]|null
 */
function nrh_search_id_cache( $key, $ids = null ) {
	static $cache = array();
	if ( null !== $ids ) {
		$cache[ $key ] = array_values( $ids );
		return $cache[ $key ];
	}
	return array_key_exists( $key, $cache ) ? $cache[ $key ] : null;
}

/**
 * Whether this query is the public product search form (?s=&post_type=product).
 *
 * @param mixed $query WP_Query or test double.
 * @return bool
 */
function nrh_query_is_product_search( $query ) {
	if ( ! is_object( $query ) || ! method_exists( $query, 'is_main_query' ) || ! method_exists( $query, 'is_search' ) || ! method_exists( $query, 'get' ) ) {
		return false;
	}
	if ( function_exists( 'is_admin' ) && is_admin() ) {
		return false;
	}
	if ( ! $query->is_main_query() || ! $query->is_search() ) {
		return false;
	}

	$post_type = $query->get( 'post_type' );
	if ( 'product' === $post_type ) {
		return true;
	}
	return is_array( $post_type ) && in_array( 'product', $post_type, true );
}

/**
 * Fold extra product IDs into WordPress's search WHERE group.
 *
 * The core clause is "AND ((title) OR (excerpt) OR (content))" plus an
 * optional password predicate. IDs are OR'd inside that group so post type,
 * status, and visibility constraints outside it still apply.
 *
 * @param string $search     posts_search SQL.
 * @param int[]  $post_ids   Product IDs to include.
 * @param string $posts_table Posts table name, including prefix.
 * @return string
 */
function nrh_extend_product_search_sql( $search, $post_ids, $posts_table ) {
	$post_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $post_ids ) ) ) );
	if ( ! $post_ids || ! is_string( $posts_table ) || $posts_table === '' ) {
		return $search;
	}

	$or = $posts_table . '.ID IN (' . implode( ',', $post_ids ) . ')';
	$search = (string) $search;
	if ( trim( $search ) === '' ) {
		return ' AND (' . $or . ') ';
	}

	$password        = '';
	$password_needle = " AND ({$posts_table}.post_password = '')";
	if ( false !== strpos( $search, $password_needle ) ) {
		$password = $password_needle;
		$search   = str_replace( $password_needle, '', $search );
	}

	$trimmed = rtrim( $search );
	if ( substr( $trimmed, -1 ) !== ')' ) {
		return $search . ' OR ' . $or . ' ' . $password;
	}

	return substr( $trimmed, 0, -1 ) . ' OR ' . $or . ') ' . $password;
}

/**
 * Keep SKU and tag matches when the header search form is submitted.
 *
 * Live search finds them over AJAX. Enter (and the search button) load
 * /?s=term&post_type=product, which otherwise only matches title, excerpt,
 * and content.
 *
 * @param string $search Search SQL.
 * @param mixed  $query  WP_Query.
 * @return string
 */
function nrh_product_search_posts_search( $search, $query ) {
	if ( ! nrh_query_is_product_search( $query ) ) {
		return $search;
	}

	$term = $query->get( 's' );
	if ( ! is_string( $term ) ) {
		return $search;
	}
	$term = trim( $term );
	$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $term ) : strlen( $term );
	if ( $len < 2 ) {
		return $search;
	}

	global $wpdb;
	$posts_table = ( isset( $wpdb ) && is_object( $wpdb ) && ! empty( $wpdb->posts ) ) ? $wpdb->posts : 'wp_posts';
	$ids         = array_merge(
		nrh_search_sku_ids( $term, 1000 ),
		nrh_search_tag_ids( $term, 1000 )
	);

	return nrh_extend_product_search_sql( $search, $ids, $posts_table );
}
add_filter( 'posts_search', 'nrh_product_search_posts_search', 20, 2 );

// 3) Enqueue JS & localize
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_script(
		'nrh-live-search',
		get_stylesheet_directory_uri() . '/assets/js/live-search.js',
		[ 'jquery' ],
		norhage_asset_version( '/assets/js/live-search.js' ),
		function_exists( 'norhage_script_args' ) ? norhage_script_args() : true
	);

	wp_localize_script( 'nrh-live-search', 'nrh_live_search', [
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'action'   => 'nrh_live_search',
	] );
} );
