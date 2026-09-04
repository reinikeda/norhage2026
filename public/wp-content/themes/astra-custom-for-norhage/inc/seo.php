<?php
/**
 * Technical SEO: hreflang, schema, crawler signals, Yoast quality filters.
 *
 * Live shops (same theme, per-domain language):
 *   norhage.eu (en, x-default), .de, .dk, .se, .no, .fi, .lt
 *
 * Yoast SEO is installed on the servers (not in this repo). Filters no-op if Yoast is off.
 *
 * Product JSON-LD: EU return policy, GTIN, and customer aggregateRating/review
 * from approved WooCommerce / CusRev comments (never fabricated).
 *
 * Server-side (not in git) still required for full SEO:
 *   - Cloudflare Managed robots.txt Disallows GPTBot, ClaudeBot, Google-Extended,
 *     Applebot-Extended, Amazonbot, Bytespider, CCBot, meta-externalagent.
 *     WordPress cannot override that block. Allow those bots in Cloudflare if
 *     AI citation / AI-overview access is wanted. Content-Signal ai-train=no can stay.
 *   - Guest HTML cache (live cf-cache-status: DYNAMIC) for Core Web Vitals.
 *   - After deploy: regenerate Yoast llms.txt; keep Cart/Wishlist out of its page list.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shop network used for homepage hreflang.
 *
 * Homepage hreflang is host-based. Product hreflang is SKU-based: shops share
 * no database, so each shop dumps SKU → permalink once a day and pulls the
 * other dumps in a background job. Product HTML only reads that saved map
 * (no cross-shop HTTP, no TTFB wait). Missing SKUs are omitted.
 *
 * @return array<string, array{host:string, hreflang:string, x_default:bool, area:string, country:string}>
 */
function nh_seo_shop_network() {
	static $shops = null;
	if ( null !== $shops ) {
		return $shops;
	}

	$shops = array(
		'eu' => array(
			'host'      => 'norhage.eu',
			'hreflang'  => 'en',
			'x_default' => true,
			'area'      => 'EU',
			'country'   => 'DE',
		),
		'de' => array(
			'host'      => 'norhage.de',
			'hreflang'  => 'de-DE',
			'x_default' => false,
			'area'      => 'DE',
			'country'   => 'DE',
		),
		'dk' => array(
			'host'      => 'norhage.dk',
			'hreflang'  => 'da-DK',
			'x_default' => false,
			'area'      => 'DK',
			'country'   => 'DK',
		),
		'se' => array(
			'host'      => 'norhage.se',
			'hreflang'  => 'sv-SE',
			'x_default' => false,
			'area'      => 'SE',
			'country'   => 'SE',
		),
		'no' => array(
			'host'      => 'norhage.no',
			'hreflang'  => 'nb-NO',
			'x_default' => false,
			'area'      => 'NO',
			'country'   => 'NO',
		),
		'fi' => array(
			'host'      => 'norhage.fi',
			'hreflang'  => 'fi-FI',
			'x_default' => false,
			'area'      => 'FI',
			'country'   => 'FI',
		),
		'lt' => array(
			'host'      => 'norhage.lt',
			'hreflang'  => 'lt-LT',
			'x_default' => false,
			'area'      => 'LT',
			'country'   => 'LT',
		),
	);

	return $shops;
}

/**
 * Current request host without www.
 *
 * @return string
 */
function nh_seo_current_host() {
	$host = '';
	if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
		$host = strtolower( (string) wp_unslash( $_SERVER['HTTP_HOST'] ) );
	} elseif ( function_exists( 'home_url' ) ) {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	return (string) preg_replace( '/^www\./', '', $host );
}

/**
 * Current shop row from the network map, or null.
 *
 * @return array{host:string, hreflang:string, x_default:bool, area:string, country:string}|null
 */
function nh_seo_current_shop() {
	$host = nh_seo_current_host();
	foreach ( nh_seo_shop_network() as $shop ) {
		if ( $shop['host'] === $host ) {
			return $shop;
		}
	}
	return null;
}

/**
 * Encode JSON-LD safely inside a script tag.
 *
 * @param mixed $data Graph.
 * @return string
 */
function nh_seo_jsonld( $data ) {
	$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
	return is_string( $json ) ? $json : '{}';
}

/**
 * Print llms.txt discovery link and hreflang cluster (homepage or product SKU).
 */
function nh_seo_print_head_links() {
	echo '<link rel="alternate" type="text/plain" title="llms.txt" href="' . esc_url( home_url( '/llms.txt' ) ) . '" />' . "\n";

	$cluster = nh_seo_hreflang_cluster();
	if ( empty( $cluster ) ) {
		return;
	}

	$x_default = '';
	foreach ( $cluster as $row ) {
		if ( empty( $row['hreflang'] ) || empty( $row['url'] ) ) {
			continue;
		}
		echo '<link rel="alternate" hreflang="' . esc_attr( $row['hreflang'] ) . '" href="' . esc_url( $row['url'] ) . '" />' . "\n";
		if ( ! empty( $row['x_default'] ) ) {
			$x_default = $row['url'];
		}
	}

	if ( $x_default !== '' ) {
		echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $x_default ) . '" />' . "\n";
	}
}
add_action( 'wp_head', 'nh_seo_print_head_links', 2 );

/**
 * Hreflang rows for the current request.
 *
 * @return array<int, array{hreflang:string, url:string, x_default:bool}>
 */
function nh_seo_hreflang_cluster() {
	if ( is_front_page() ) {
		$rows = array();
		foreach ( nh_seo_shop_network() as $shop ) {
			$rows[] = array(
				'hreflang'  => $shop['hreflang'],
				'url'       => 'https://' . $shop['host'] . '/',
				'x_default' => ! empty( $shop['x_default'] ),
			);
		}
		return $rows;
	}

	if ( function_exists( 'is_product' ) && is_product() ) {
		return nh_seo_product_hreflang_cluster();
	}

	return array();
}

/**
 * Whether a SKU is safe to put in a query string / cache key.
 *
 * @param string $sku SKU.
 * @return bool
 */
function nh_seo_is_usable_sku( $sku ) {
	$sku = trim( (string) $sku );
	if ( $sku === '' || strlen( $sku ) > 80 ) {
		return false;
	}
	return (bool) preg_match( '/^[A-Za-z0-9._\-\/%]+$/', $sku );
}

/**
 * SKU used to cluster this product page across shops.
 *
 * @return string
 */
function nh_seo_current_product_sku() {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '';
	}

	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product ) {
		return '';
	}

	$sku = (string) $product->get_sku();
	if ( $sku === '' && $product->get_parent_id() ) {
		$parent = wc_get_product( $product->get_parent_id() );
		$sku    = $parent ? (string) $parent->get_sku() : '';
	}

	return nh_seo_is_usable_sku( $sku ) ? $sku : '';
}

/**
 * Local catalog URL for a SKU, if the product is published.
 *
 * @param string $sku SKU.
 * @return string
 */
function nh_seo_local_url_for_sku( $sku ) {
	if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
		return '';
	}

	$id = (int) wc_get_product_id_by_sku( $sku );
	if ( $id <= 0 ) {
		return '';
	}

	$product = wc_get_product( $id );
	if ( ! $product ) {
		return '';
	}

	if ( $product->is_type( 'variation' ) && $product->get_parent_id() ) {
		$parent = wc_get_product( $product->get_parent_id() );
		if ( $parent ) {
			$product = $parent;
		}
	}

	// Catalog visibility must not drop an indexable product URL from the
	// cluster: siblings that omit this shop break hreflang return links.
	if ( $product->get_status() !== 'publish' ) {
		return '';
	}

	$url = get_permalink( $product->get_id() );
	return is_string( $url ) && $url !== '' ? $url : '';
}

/**
 * Product hreflang cluster: local permalink + sibling shops resolved by SKU.
 *
 * Sibling URLs come from the daily network map (uploads/nh-seo/network-map.json).
 * Separate DBs mean slugs differ; we never rewrite the path onto another host.
 * Shops that lack the SKU are omitted. The current shop URL is always fresh.
 *
 * @return array<int, array{hreflang:string, url:string, x_default:bool}>
 */
function nh_seo_product_hreflang_cluster() {
	$sku = nh_seo_current_product_sku();
	if ( $sku === '' ) {
		return array();
	}

	$local_url = get_permalink();
	if ( ! is_string( $local_url ) || $local_url === '' ) {
		$local_url = nh_seo_local_url_for_sku( $sku );
	}
	if ( $local_url === '' ) {
		return array();
	}

	$map = nh_seo_sku_hreflang_map( $sku, $local_url );

	/**
	 * Filter the SKU → shop URL map before hreflang is printed.
	 *
	 * @param array<string, string> $map host => url.
	 * @param string                $sku SKU.
	 */
	$map = apply_filters( 'nh_seo_hreflang_map', $map, $sku );
	if ( ! is_array( $map ) ) {
		return array();
	}

	$rows = array();
	foreach ( nh_seo_shop_network() as $shop ) {
		$host = $shop['host'];
		if ( empty( $map[ $host ] ) ) {
			continue;
		}
		$url = nh_seo_normalize_shop_url( $map[ $host ], $host );
		if ( $url === '' ) {
			continue;
		}
		$rows[] = array(
			'hreflang'  => $shop['hreflang'],
			'url'       => $url,
			'x_default' => ! empty( $shop['x_default'] ),
		);
	}

	return $rows;
}

/**
 * Keep only https URLs whose host matches the expected shop.
 *
 * Path percent-encoded UTF-8 (e.g. %c2%b2 for ²) is decoded so hreflang
 * hrefs match Yoast canonical IRIs. Auditors treat those as different URLs
 * and flag missing return links.
 *
 * @param string $url  Candidate permalink.
 * @param string $host Expected host (no www).
 * @return string
 */
function nh_seo_normalize_shop_url( $url, $host ) {
	$parts = wp_parse_url( (string) $url );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
		return '';
	}
	if ( strtolower( $parts['scheme'] ) !== 'https' ) {
		return '';
	}

	$got = strtolower( (string) $parts['host'] );
	$got = (string) preg_replace( '/^www\./', '', $got );
	if ( $got !== strtolower( $host ) ) {
		return '';
	}

	$path  = isset( $parts['path'] ) ? nh_seo_decode_utf8_path( $parts['path'] ) : '/';
	$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';

	return 'https://' . $host . $path . $query;
}

/**
 * Decode percent-encoded UTF-8 in a URL path; leave ASCII encodings intact.
 *
 * @param string $path Path.
 * @return string
 */
function nh_seo_decode_utf8_path( $path ) {
	$path = (string) $path;
	if ( $path === '' ) {
		return '/';
	}

	$utf8 = '%(?:[cC][2-9a-fA-F]|[dD][0-9a-fA-F])%[89abAB][0-9a-fA-F]'
		. '|%[eE][0-9a-fA-F](?:%[89abAB][0-9a-fA-F]){2}'
		. '|%[fF][0-4](?:%[89abAB][0-9a-fA-F]){3}';

	$path = preg_replace_callback(
		'/' . $utf8 . '/',
		static function ( $m ) {
			$decoded = rawurldecode( $m[0] );
			if ( ! is_string( $decoded ) || $decoded === $m[0] ) {
				return $m[0];
			}
			if ( ! preg_match( '//u', $decoded ) ) {
				return $m[0];
			}
			return $decoded;
		},
		$path
	);

	if ( ! is_string( $path ) || $path === '' ) {
		return '/';
	}

	$upper = preg_replace_callback(
		'/%[0-9a-fA-F]{2}/',
		static function ( $m ) {
			return strtoupper( $m[0] );
		},
		$path
	);

	return is_string( $upper ) ? $upper : $path;
}

/**
 * host => url for a SKU. Always includes the current shop's local URL.
 *
 * Reads the daily network map only. Never performs HTTP during page render.
 *
 * @param string $sku       SKU.
 * @param string $local_url Current shop permalink.
 * @return array<string, string>
 */
function nh_seo_sku_hreflang_map( $sku, $local_url ) {
	$current_host = nh_seo_current_host();
	$sku_key      = strtolower( (string) $sku );
	$network      = nh_seo_load_network_map();
	$map          = array();

	if ( isset( $network['skus'][ $sku_key ] ) && is_array( $network['skus'][ $sku_key ] ) ) {
		foreach ( $network['skus'][ $sku_key ] as $host => $url ) {
			if ( ! is_string( $host ) || ! is_string( $url ) || $url === '' ) {
				continue;
			}
			$clean = nh_seo_normalize_shop_url( $url, $host );
			if ( $clean !== '' ) {
				$map[ $host ] = $clean;
			}
		}
	}

	if ( $current_host !== '' ) {
		$clean_local          = nh_seo_normalize_shop_url( $local_url, $current_host );
		$map[ $current_host ] = $clean_local !== '' ? $clean_local : $local_url;
	}

	return $map;
}

/**
 * Directory for SKU dump + merged network map.
 *
 * @return string
 */
function nh_seo_map_dir() {
	$uploads = wp_upload_dir();
	$base    = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
	return $base !== '' ? $base . '/nh-seo' : '';
}

/**
 * @return string
 */
function nh_seo_sku_map_path() {
	return nh_seo_map_dir() . '/sku-map.json';
}

/**
 * @return string
 */
function nh_seo_network_map_path() {
	return nh_seo_map_dir() . '/network-map.json';
}

/**
 * Load merged SKU → { host => url } map from disk.
 *
 * @param bool $reload Re-read from disk.
 * @return array{generated_at?:int, skus: array<string, array<string, string>>}
 */
function nh_seo_load_network_map( $reload = false ) {
	static $data = null;
	if ( $reload ) {
		$data = null;
	}
	if ( null !== $data ) {
		return $data;
	}

	$data = array(
		'generated_at' => 0,
		'skus'         => array(),
	);

	$path = nh_seo_network_map_path();
	if ( $path === '' || ! is_readable( $path ) ) {
		return $data;
	}

	$raw  = file_get_contents( $path );
	$json = is_string( $raw ) ? json_decode( $raw, true ) : null;
	if ( is_array( $json ) && isset( $json['skus'] ) && is_array( $json['skus'] ) ) {
		$data = $json;
	}

	return $data;
}

/**
 * Atomically write JSON.
 *
 * @param string               $path Path.
 * @param array<string, mixed> $data Data.
 * @return bool
 */
function nh_seo_write_json_file( $path, $data ) {
	$dir = dirname( $path );
	if ( $dir === '' || ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) ) {
		return false;
	}

	$index = $dir . '/index.php';
	if ( ! file_exists( $index ) ) {
		file_put_contents( $index, "<?php\n// Silence.\n" );
	}

	$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( ! is_string( $json ) ) {
		return false;
	}

	$tmp = $path . '.tmp.' . uniqid( '', true );
	if ( false === file_put_contents( $tmp, $json, LOCK_EX ) ) {
		return false;
	}
	if ( ! rename( $tmp, $path ) ) {
		unlink( $tmp );
		return false;
	}

	return true;
}

/**
 * Published parent-product SKU → permalink for this shop.
 *
 * @return array<string, string>
 */
function nh_seo_build_local_sku_map() {
	$map  = array();
	$shop = nh_seo_current_shop();
	$host = $shop ? $shop['host'] : nh_seo_current_host();
	if ( $host === '' || ! function_exists( 'wc_get_products' ) ) {
		return $map;
	}

	$page  = 1;
	$limit = 100;
	do {
		$ids = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => $limit,
				'page'   => $page,
				'return' => 'ids',
				'type'   => array( 'simple', 'variable', 'grouped', 'external' ),
			)
		);
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			break;
		}
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$sku = (string) $product->get_sku();
			if ( ! nh_seo_is_usable_sku( $sku ) ) {
				continue;
			}
			$url   = get_permalink( $id );
			$clean = is_string( $url ) ? nh_seo_normalize_shop_url( $url, $host ) : '';
			if ( $clean !== '' ) {
				$map[ strtolower( $sku ) ] = $clean;
			}
		}
		$page++;
	} while ( count( $ids ) === $limit );

	return $map;
}

/**
 * Replace one host's URLs in the network map from an authoritative dump.
 *
 * @param array<string, mixed>  $network Network map.
 * @param string                $host    Shop host.
 * @param array<string, string> $skus    sku => url.
 * @return array<string, mixed>
 */
function nh_seo_apply_host_sku_map( $network, $host, $skus ) {
	if ( ! isset( $network['skus'] ) || ! is_array( $network['skus'] ) ) {
		$network['skus'] = array();
	}

	foreach ( $network['skus'] as $sku => $hosts ) {
		if ( is_array( $hosts ) ) {
			unset( $network['skus'][ $sku ][ $host ] );
			if ( empty( $network['skus'][ $sku ] ) ) {
				unset( $network['skus'][ $sku ] );
			}
		}
	}

	foreach ( $skus as $sku => $url ) {
		$sku = strtolower( (string) $sku );
		if ( $sku === '' || ! is_string( $url ) || $url === '' ) {
			continue;
		}
		if ( ! isset( $network['skus'][ $sku ] ) || ! is_array( $network['skus'][ $sku ] ) ) {
			$network['skus'][ $sku ] = array();
		}
		$network['skus'][ $sku ][ $host ] = $url;
	}

	return $network;
}

/**
 * Parse a sibling sku-map JSON dump.
 *
 * @param string|null $body          Body.
 * @param string      $expected_host Host.
 * @return array<string, string>
 */
function nh_seo_parse_shop_sku_map( $body, $expected_host ) {
	$out = array();
	if ( ! is_string( $body ) || $body === '' ) {
		return $out;
	}
	$data = json_decode( $body, true );
	if ( ! is_array( $data ) || empty( $data['skus'] ) || ! is_array( $data['skus'] ) ) {
		return $out;
	}
	foreach ( $data['skus'] as $sku => $url ) {
		if ( ! nh_seo_is_usable_sku( (string) $sku ) || ! is_string( $url ) ) {
			continue;
		}
		$clean = nh_seo_normalize_shop_url( $url, $expected_host );
		if ( $clean !== '' ) {
			$out[ strtolower( (string) $sku ) ] = $clean;
		}
	}
	return $out;
}

/**
 * Daily job: dump this catalog, pull sibling dumps, save the merged map.
 *
 * Safe to run in WP-Cron / Action Scheduler. Not used during page render.
 */
function nh_seo_rebuild_hreflang_maps() {
	$shop = nh_seo_current_shop();
	$host = $shop ? $shop['host'] : nh_seo_current_host();
	if ( $host === '' ) {
		return;
	}

	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 120 );
	}

	$local = nh_seo_build_local_sku_map();
	nh_seo_write_json_file(
		nh_seo_sku_map_path(),
		array(
			'host'         => $host,
			'hreflang'     => $shop ? $shop['hreflang'] : '',
			'generated_at' => time(),
			'skus'         => $local,
		)
	);

	$network = nh_seo_load_network_map( true );
	if ( empty( $network['skus'] ) || ! is_array( $network['skus'] ) ) {
		$network = array(
			'generated_at' => time(),
			'skus'         => array(),
		);
	}

	$network = nh_seo_apply_host_sku_map( $network, $host, $local );

	$urls = array();
	foreach ( nh_seo_shop_network() as $row ) {
		if ( $row['host'] === $host ) {
			continue;
		}
		$urls[ $row['host'] ] = 'https://' . $row['host'] . '/wp-json/nh-seo/v1/sku-map';
	}

	if ( ! empty( $urls ) ) {
		$responses = nh_seo_parallel_get( $urls, 45.0 );
		foreach ( $urls as $remote_host => $ignored ) {
			$body = isset( $responses[ $remote_host ]['body'] ) ? $responses[ $remote_host ]['body'] : null;
			$skus = nh_seo_parse_shop_sku_map( $body, $remote_host );
			if ( ! empty( $skus ) ) {
				$network = nh_seo_apply_host_sku_map( $network, $remote_host, $skus );
			}
		}
	}

	$network['generated_at'] = time();
	$network['host']         = $host;
	nh_seo_write_json_file( nh_seo_network_map_path(), $network );
	update_option( 'nh_seo_hreflang_rebuilt_at', time(), false );
	nh_seo_load_network_map( true );
}

/**
 * Queue a background rebuild (Action Scheduler, else WP-Cron).
 */
function nh_seo_enqueue_hreflang_rebuild() {
	if ( function_exists( 'as_enqueue_async_action' ) ) {
		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( 'nh_seo_rebuild_hreflang_maps' ) ) {
			return;
		}
		as_enqueue_async_action( 'nh_seo_rebuild_hreflang_maps' );
		return;
	}
	if ( ! wp_next_scheduled( 'nh_seo_rebuild_hreflang_maps' ) ) {
		wp_schedule_single_event( time() + 30, 'nh_seo_rebuild_hreflang_maps' );
	}
}

/**
 * Recurring daily rebuild + one run soon after deploy if the map is missing.
 */
function nh_seo_schedule_hreflang_jobs() {
	if ( function_exists( 'as_schedule_recurring_action' ) ) {
		$has_daily = function_exists( 'as_has_scheduled_action' )
			? as_has_scheduled_action( 'nh_seo_rebuild_hreflang_maps_daily' )
			: (bool) get_option( 'nh_seo_hreflang_daily_scheduled', false );
		if ( ! $has_daily ) {
			as_schedule_recurring_action( time() + 120, DAY_IN_SECONDS, 'nh_seo_rebuild_hreflang_maps_daily' );
			update_option( 'nh_seo_hreflang_daily_scheduled', 1, false );
		}
	} elseif ( ! wp_next_scheduled( 'nh_seo_rebuild_hreflang_maps_daily' ) ) {
		wp_schedule_event( time() + 120, 'daily', 'nh_seo_rebuild_hreflang_maps_daily' );
	}

	$path = nh_seo_network_map_path();
	if ( $path !== '' && ! is_readable( $path ) ) {
		nh_seo_enqueue_hreflang_rebuild();
	}
}

/**
 * Daily hook wrapper.
 */
function nh_seo_rebuild_hreflang_maps_daily() {
	nh_seo_rebuild_hreflang_maps();
}

add_action( 'init', 'nh_seo_schedule_hreflang_jobs', 20 );
add_action( 'nh_seo_rebuild_hreflang_maps', 'nh_seo_rebuild_hreflang_maps' );
add_action( 'nh_seo_rebuild_hreflang_maps_daily', 'nh_seo_rebuild_hreflang_maps_daily' );

/**
 * Parallel GET. Falls back to sequential wp_remote_get when curl_multi is missing.
 *
 * @param array<string, string> $urls    key => url.
 * @param float|null            $timeout Seconds. Null uses the filter default.
 * @return array<string, array{code:int, body:?string}>
 */
function nh_seo_parallel_get( $urls, $timeout = null ) {
	$out     = array();
	foreach ( array_keys( $urls ) as $key ) {
		$out[ $key ] = array(
			'code' => 0,
			'body' => null,
		);
	}
	if ( null === $timeout ) {
		$timeout = 10.0;
	}
	$timeout = (float) apply_filters( 'nh_seo_hreflang_http_timeout', $timeout );

	if ( function_exists( 'curl_multi_init' ) && function_exists( 'curl_init' ) ) {
		$mh  = curl_multi_init();
		$chs = array();
		foreach ( $urls as $key => $url ) {
			$ch = curl_init( $url );
			if ( ! $ch ) {
				continue;
			}
			curl_setopt_array(
				$ch,
				array(
					CURLOPT_RETURNTRANSFER    => true,
					CURLOPT_FOLLOWLOCATION    => true,
					CURLOPT_MAXREDIRS         => 2,
					CURLOPT_TIMEOUT_MS        => (int) round( $timeout * 1000 ),
					CURLOPT_CONNECTTIMEOUT_MS => min( 8000, (int) round( $timeout * 1000 ) ),
					CURLOPT_NOSIGNAL          => true,
					CURLOPT_HTTPHEADER        => array( 'Accept: application/json' ),
					CURLOPT_USERAGENT         => 'NorhageHreflang/1.0',
					CURLOPT_SSL_VERIFYPEER    => true,
				)
			);
			curl_multi_add_handle( $mh, $ch );
			$chs[ $key ] = $ch;
		}

		$running = 0;
		do {
			$status = curl_multi_exec( $mh, $running );
			if ( $running ) {
				curl_multi_select( $mh, 0.2 );
			}
		} while ( $running && CURLM_OK === $status );

		foreach ( $chs as $key => $ch ) {
			$out[ $key ] = array(
				'code' => (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ),
				'body' => curl_multi_getcontent( $ch ),
			);
			curl_multi_remove_handle( $mh, $ch );
			curl_close( $ch );
		}
		curl_multi_close( $mh );

		return $out;
	}

	foreach ( $urls as $key => $url ) {
		$res = wp_remote_get(
			$url,
			array(
				'timeout'     => $timeout,
				'redirection' => 2,
				'headers'     => array( 'Accept' => 'application/json' ),
				'user-agent'  => 'NorhageHreflang/1.0',
			)
		);
		if ( ! is_wp_error( $res ) ) {
			$out[ $key ] = array(
				'code' => (int) wp_remote_retrieve_response_code( $res ),
				'body' => wp_remote_retrieve_body( $res ),
			);
		}
	}

	return $out;
}

/**
 * Public SKU dumps for sibling daily jobs (no prices or stock).
 */
function nh_seo_register_rest_routes() {
	register_rest_route(
		'nh-seo/v1',
		'/sku-map',
		array(
			'methods'             => 'GET',
			'callback'            => 'nh_seo_rest_sku_map',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'nh-seo/v1',
		'/product-by-sku',
		array(
			'methods'             => 'GET',
			'callback'            => 'nh_seo_rest_product_by_sku',
			'permission_callback' => '__return_true',
			'args'                => array(
				'sku' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
	register_rest_route(
		'nh-seo/v1',
		'/rebuild-maps',
		array(
			'methods'             => 'POST',
			'callback'            => 'nh_seo_rest_rebuild_maps',
			'permission_callback' => 'nh_seo_rest_rebuild_maps_permission',
		)
	);
}
add_action( 'rest_api_init', 'nh_seo_register_rest_routes' );

/**
 * Only logged-in admins (or a defined secret) may queue a rebuild.
 *
 * @param WP_REST_Request $request Request.
 * @return bool
 */
function nh_seo_rest_rebuild_maps_permission( $request ) {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	$secret = (string) apply_filters( 'nh_seo_hreflang_rebuild_secret', defined( 'NH_SEO_HREFLANG_SECRET' ) ? NH_SEO_HREFLANG_SECRET : '' );
	$token  = (string) $request->get_header( 'X-NH-SEO-Token' );
	if ( $token === '' ) {
		$token = (string) $request->get_param( 'token' );
	}
	return $secret !== '' && hash_equals( $secret, $token );
}

/**
 * This shop's SKU → permalink dump. Built on demand if the daily file is missing.
 *
 * @return WP_REST_Response
 */
function nh_seo_rest_sku_map() {
	$path = nh_seo_sku_map_path();
	$shop = nh_seo_current_shop();
	$host = $shop ? $shop['host'] : nh_seo_current_host();

	if ( $path === '' || ! is_readable( $path ) ) {
		$local = nh_seo_build_local_sku_map();
		$payload = array(
			'host'         => $host,
			'hreflang'     => $shop ? $shop['hreflang'] : '',
			'generated_at' => time(),
			'skus'         => $local,
		);
		if ( $path !== '' ) {
			nh_seo_write_json_file( $path, $payload );
		}
	} else {
		$raw     = file_get_contents( $path );
		$payload = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $payload ) ) {
			$payload = array(
				'host'         => $host,
				'hreflang'     => $shop ? $shop['hreflang'] : '',
				'generated_at' => 0,
				'skus'         => array(),
			);
		}
	}

	$response = new WP_REST_Response( $payload, 200 );
	$response->header( 'Cache-Control', 'public, max-age=3600' );
	return $response;
}

/**
 * REST: published product URL for one SKU.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function nh_seo_rest_product_by_sku( $request ) {
	$sku = $request->get_param( 'sku' );
	if ( ! nh_seo_is_usable_sku( $sku ) ) {
		return new WP_Error( 'nh_seo_bad_sku', 'Invalid SKU.', array( 'status' => 400 ) );
	}

	$url = nh_seo_local_url_for_sku( $sku );
	if ( $url === '' ) {
		return new WP_Error( 'nh_seo_not_found', 'Product not found.', array( 'status' => 404 ) );
	}

	$shop    = nh_seo_current_shop();
	$host    = $shop ? $shop['host'] : nh_seo_current_host();
	$cluster = array();
	$sku_key = strtolower( (string) $sku );
	$network = nh_seo_load_network_map();
	if ( isset( $network['skus'][ $sku_key ] ) && is_array( $network['skus'][ $sku_key ] ) ) {
		$cluster = $network['skus'][ $sku_key ];
	}
	if ( $host !== '' ) {
		$local            = nh_seo_normalize_shop_url( $url, $host );
		$cluster[ $host ] = $local !== '' ? $local : $url;
	}

	return new WP_REST_Response(
		array(
			'sku'      => $sku,
			'url'      => $url,
			'host'     => $host,
			'hreflang' => $shop ? $shop['hreflang'] : '',
			'cluster'  => $cluster,
		),
		200
	);
}

/**
 * Queue a background map rebuild (admin or secret token).
 *
 * @return WP_REST_Response
 */
function nh_seo_rest_rebuild_maps() {
	nh_seo_enqueue_hreflang_rebuild();
	return new WP_REST_Response(
		array(
			'status'     => 'queued',
			'rebuilt_at' => (int) get_option( 'nh_seo_hreflang_rebuilt_at', 0 ),
		),
		202
	);
}

/**
 * Keep this shop's local URL in the saved maps when a product is saved.
 * Sibling shops pick up the change on their next daily pull.
 *
 * @param int $product_id Product ID.
 */
function nh_seo_touch_sku_maps( $product_id ) {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return;
	}
	if ( $product->is_type( 'variation' ) && $product->get_parent_id() ) {
		$parent = wc_get_product( $product->get_parent_id() );
		if ( $parent ) {
			$product = $parent;
		}
	}

	$shop = nh_seo_current_shop();
	$host = $shop ? $shop['host'] : nh_seo_current_host();
	$sku  = (string) $product->get_sku();
	if ( $host === '' || ! nh_seo_is_usable_sku( $sku ) ) {
		return;
	}
	$sku_key = strtolower( $sku );
	$path    = nh_seo_sku_map_path();
	if ( $path === '' || ! is_readable( $path ) ) {
		return;
	}

	$raw   = file_get_contents( $path );
	$json  = is_string( $raw ) ? json_decode( $raw, true ) : null;
	$local = ( is_array( $json ) && isset( $json['skus'] ) && is_array( $json['skus'] ) ) ? $json['skus'] : array();

	if ( $product->get_status() === 'publish' ) {
		$url   = get_permalink( $product->get_id() );
		$clean = is_string( $url ) ? nh_seo_normalize_shop_url( $url, $host ) : '';
		if ( $clean !== '' ) {
			$local[ $sku_key ] = $clean;
		}
	} else {
		unset( $local[ $sku_key ] );
	}

	nh_seo_write_json_file(
		$path,
		array(
			'host'         => $host,
			'hreflang'     => $shop ? $shop['hreflang'] : '',
			'generated_at' => time(),
			'skus'         => $local,
		)
	);

	if ( ! is_readable( nh_seo_network_map_path() ) ) {
		return;
	}

	$network = nh_seo_load_network_map( true );
	if ( isset( $local[ $sku_key ] ) ) {
		if ( ! isset( $network['skus'] ) || ! is_array( $network['skus'] ) ) {
			$network['skus'] = array();
		}
		if ( ! isset( $network['skus'][ $sku_key ] ) || ! is_array( $network['skus'][ $sku_key ] ) ) {
			$network['skus'][ $sku_key ] = array();
		}
		$network['skus'][ $sku_key ][ $host ] = $local[ $sku_key ];
	} elseif ( isset( $network['skus'][ $sku_key ][ $host ] ) ) {
		unset( $network['skus'][ $sku_key ][ $host ] );
		if ( empty( $network['skus'][ $sku_key ] ) ) {
			unset( $network['skus'][ $sku_key ] );
		}
	}
	nh_seo_write_json_file( nh_seo_network_map_path(), $network );
	nh_seo_load_network_map( true );
}
add_action( 'woocommerce_update_product', 'nh_seo_touch_sku_maps' );
add_action( 'woocommerce_new_product', 'nh_seo_touch_sku_maps' );

/**
 * Organization details shared with JSON-LD.
 *
 * Phone, email and social URLs reuse footer.php gettext msgids (`nh-theme`)
 * so existing .po translations apply.
 *
 * @return array<string, mixed>
 */
function nh_seo_organization_profile() {
	$shop = nh_seo_current_shop();

	$same_as = array_values(
		array_unique(
			array_filter(
				array(
					__( 'https://www.facebook.com/norhage.de', 'nh-theme' ),
					__( 'https://www.instagram.com/norhage.de', 'nh-theme' ),
					__( 'https://www.youtube.com/@NorhageEU', 'nh-theme' ),
					__( 'https://www.linkedin.com/company/norhage-industri-norge', 'nh-theme' ),
				)
			)
		)
	);

	return array(
		'name'      => get_bloginfo( 'name', 'display' ),
		'legalName' => __( 'Tehi UG', 'nh-theme' ),
		'url'       => home_url( '/' ),
		'email'     => __( 'info@norhage.eu', 'nh-theme' ),
		'telephone' => __( '+49 176 65 10 6609', 'nh-theme' ),
		'sameAs'    => $same_as,
		'address'   => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => 'Adolfstraße 1',
			'addressLocality' => 'Wiesbaden',
			'postalCode'      => '65185',
			'addressRegion'   => 'HE',
			'addressCountry'  => 'DE',
		),
		'areaServed' => $shop ? $shop['area'] : 'EU',
		'country'    => $shop ? $shop['country'] : 'DE',
	);
}

/**
 * Enrich Yoast Organization: OnlineStore + address + social sameAs.
 *
 * @param array<string, mixed> $data Schema piece.
 * @return array<string, mixed>
 */
function nh_seo_filter_organization_schema( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	$profile = nh_seo_organization_profile();

	$types = isset( $data['@type'] ) ? (array) $data['@type'] : array( 'Organization' );
	if ( ! in_array( 'OnlineStore', $types, true ) ) {
		$types[] = 'OnlineStore';
	}
	$data['@type'] = array_values( array_unique( $types ) );

	$data['name']      = $profile['name'];
	$data['legalName'] = $profile['legalName'];
	$data['url']       = $profile['url'];
	$data['email']     = $profile['email'];
	$data['telephone'] = $profile['telephone'];
	$data['address']   = $profile['address'];
	$data['areaServed'] = $profile['areaServed'];

	$data['contactPoint'] = array(
		'@type'             => 'ContactPoint',
		'contactType'       => 'customer service',
		'telephone'         => $profile['telephone'],
		'email'             => $profile['email'],
		'areaServed'        => $profile['areaServed'],
		'availableLanguage' => get_bloginfo( 'language' ),
	);

	$existing_same  = isset( $data['sameAs'] ) ? (array) $data['sameAs'] : array();
	$data['sameAs'] = array_values( array_unique( array_filter( array_merge( $existing_same, $profile['sameAs'] ) ) ) );

	return $data;
}
add_filter( 'wpseo_schema_organization', 'nh_seo_filter_organization_schema' );

/**
 * Search pages are noindexed (robots.txt Disallow /*?s=). Do not advertise
 * a SearchAction sitelinks box that points at a blocked URL.
 *
 * @param array<string, mixed> $data Schema piece.
 * @return array<string, mixed>
 */
function nh_seo_filter_website_schema( $data ) {
	if ( is_array( $data ) && isset( $data['potentialAction'] ) ) {
		unset( $data['potentialAction'] );
	}
	return $data;
}
add_filter( 'wpseo_schema_website', 'nh_seo_filter_website_schema' );

/**
 * Yoast already emits BreadcrumbList; drop WooCommerce's duplicate.
 *
 * @param array<string, mixed> $markup Breadcrumb markup.
 * @return array<string, mixed>
 */
function nh_seo_disable_woo_breadcrumb_schema( $markup ) {
	return array();
}
add_filter( 'woocommerce_structured_data_breadcrumblist', 'nh_seo_disable_woo_breadcrumb_schema' );

/**
 * Collapse "Norhage … – Norhage" when the page title already contains the brand.
 *
 * @param string $title Title tag.
 * @return string
 */
function nh_seo_filter_title( $title ) {
	if ( ! is_string( $title ) || $title === '' ) {
		return $title;
	}

	$site = get_bloginfo( 'name', 'display' );
	if ( $site === '' ) {
		return $title;
	}

	$dupes = array(
		' – ' . $site,
		' — ' . $site,
		' - ' . $site,
		' | ' . $site,
	);

	foreach ( $dupes as $suffix ) {
		if ( substr( $title, -strlen( $suffix ) ) !== $suffix ) {
			continue;
		}
		$without = substr( $title, 0, -strlen( $suffix ) );
		if ( stripos( $without, $site ) !== false ) {
			return $without;
		}
	}

	return $title;
}
add_filter( 'wpseo_title', 'nh_seo_filter_title', 20 );

/**
 * Yoast archive templates like "Discover {title} at Norhage – Buy now…".
 *
 * @param string $desc Meta description.
 * @return bool
 */
function nh_seo_is_boilerplate_metadesc( $desc ) {
	$desc = trim( (string) $desc );
	if ( $desc === '' ) {
		return true;
	}

	$buy_now = (bool) preg_match( '/Buy now|Jetzt kaufen|Kjøp nå|Köp nu|Osta nyt/i', $desc );

	if ( $buy_now && preg_match( '/\b(Discover|Entdecke|Oppdag|Upptäck|Löydä)\b/i', $desc ) ) {
		return true;
	}

	// Empty archive title: "Discover at Norhage – Buy now…"
	if ( preg_match( '/^Discover\s+at\s+Norhage/i', $desc ) ) {
		return true;
	}

	return false;
}

/**
 * Replace empty / boilerplate Yoast descriptions with on-page language content.
 *
 * Avoids new marketing gettext so DE/DK/SE/NO/FI/LT do not fall back to English.
 *
 * @param string $desc Meta description.
 * @return string
 */
function nh_seo_filter_metadesc( $desc ) {
	$desc = is_string( $desc ) ? trim( wp_strip_all_tags( html_entity_decode( $desc, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) : '';

	if ( ! nh_seo_is_boilerplate_metadesc( $desc ) ) {
		return $desc;
	}

	$site    = get_bloginfo( 'name', 'display' );
	$tagline = trim( wp_strip_all_tags( get_bloginfo( 'description', 'display' ) ) );

	if ( is_front_page() ) {
		if ( $tagline !== '' && stripos( $tagline, 'Discover' ) === false ) {
			return $site !== '' ? $site . ' — ' . $tagline : $tagline;
		}
		return $site;
	}

	if ( function_exists( 'is_product_category' ) && is_product_category() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$plain = trim( wp_strip_all_tags( (string) $term->description ) );
			if ( $plain !== '' ) {
				return wp_html_excerpt( $plain, 155, '…' );
			}
			return $term->name . ' | ' . $site;
		}
	}

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			$excerpt = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
			if ( $excerpt !== '' ) {
				return wp_html_excerpt( $excerpt, 155, '…' );
			}
			$plain = trim( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
			if ( $plain !== '' ) {
				return wp_html_excerpt( $plain, 155, '…' );
			}
			$title = get_the_title( $post );
			if ( $title !== '' ) {
				return $title . ' | ' . $site;
			}
		}
	}

	return $desc;
}
add_filter( 'wpseo_metadesc', 'nh_seo_filter_metadesc', 20 );

/**
 * Strip leftover spreadsheet/HTML from Yoast llms.txt link blurbs.
 *
 * @param string     $description Description.
 * @param int|string $post_id     Post ID.
 * @param string     $post_type   Post type (Yoast 25.9+).
 * @return string
 */
function nh_seo_filter_llmstxt_link_description( $description, $post_id = 0, $post_type = '' ) {
	unset( $post_type );

	if ( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( $post instanceof WP_Post && nh_seo_is_utility_page( $post ) ) {
			return '';
		}
	}

	$raw = html_entity_decode( (string) $description, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$raw = str_replace( '\\', '', $raw );
	$clean = trim( wp_strip_all_tags( $raw ) );

	return $clean;
}
add_filter( 'wpseo_llmstxt_link_description', 'nh_seo_filter_llmstxt_link_description', 10, 3 );

/**
 * Cart / checkout / account / wishlist should not be recommended to LLMs or crawlers as content.
 *
 * @param WP_Post $post Post.
 * @return bool
 */
function nh_seo_is_utility_page( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	$skip = array( 'cart', 'checkout', 'my-account', 'wishlist', 'wish-list', 'wunschliste', 'merkliste' );
	if ( in_array( $post->post_name, $skip, true ) ) {
		return true;
	}

	$wishlist_ids = array_filter(
		array(
			(int) get_option( 'yith_wcwl_wishlist_page_id' ),
			(int) get_option( 'tinvwl-page' ),
		)
	);
	if ( in_array( (int) $post->ID, $wishlist_ids, true ) ) {
		return true;
	}

	if ( function_exists( 'wc_get_page_id' ) ) {
		$page_id = (int) $post->ID;
		$ids     = array_filter(
			array(
				(int) wc_get_page_id( 'cart' ),
				(int) wc_get_page_id( 'checkout' ),
				(int) wc_get_page_id( 'myaccount' ),
			)
		);
		if ( in_array( $page_id, $ids, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Keep cart/checkout/account/wishlist out of Yoast llms.txt when the filter exists.
 *
 * @param bool    $exclude Whether to exclude.
 * @param WP_Post $post    Post.
 * @return bool
 */
function nh_seo_exclude_from_llmstxt( $exclude, $post ) {
	if ( $exclude ) {
		return $exclude;
	}
	return nh_seo_is_utility_page( $post );
}
add_filter( 'wpseo_exclude_from_llmstxt', 'nh_seo_exclude_from_llmstxt', 10, 2 );

/**
 * EU 14-day withdrawal on WooCommerce Product JSON-LD.
 * Return shipping cost is not assumed (customer-paid unless a filter overrides).
 *
 * @param array<string, mixed> $markup  Product markup.
 * @param WC_Product           $product Product.
 * @return array<string, mixed>
 */
function nh_seo_product_return_policy( $markup, $product ) {
	if ( ! is_array( $markup ) ) {
		return $markup;
	}

	if ( is_object( $product ) && is_callable( array( $product, 'get_global_unique_id' ) ) ) {
		$gtin = $product->get_global_unique_id();
		if ( is_string( $gtin ) && $gtin !== '' ) {
			$markup['gtin'] = $gtin;
		}
	}

	$shop   = nh_seo_current_shop();
	$policy = array(
		'@type'                => 'MerchantReturnPolicy',
		'applicableCountry'    => $shop ? $shop['country'] : 'DE',
		'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
		'merchantReturnDays'   => 14,
		'returnMethod'         => 'https://schema.org/ReturnByMail',
		'returnFees'           => 'https://schema.org/ReturnFeesCustomerResponsibility',
	);

	/**
	 * Override or disable return-policy JSON-LD (return false to omit).
	 *
	 * @param array<string, mixed>|false $policy  Policy graph.
	 * @param WC_Product                 $product Product.
	 */
	$policy = apply_filters( 'nh_seo_merchant_return_policy', $policy, $product );
	if ( $policy ) {
		$markup['hasMerchantReturnPolicy'] = $policy;
	}

	return $markup;
}
add_filter( 'woocommerce_structured_data_product', 'nh_seo_product_return_policy', 20, 2 );

/**
 * Parent product ID used for reviews (variations inherit the parent's comments).
 *
 * @param WC_Product $product Product.
 * @return int
 */
function nh_seo_review_product_id( $product ) {
	if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) ) {
		return 0;
	}

	if ( is_callable( array( $product, 'is_type' ) ) && $product->is_type( 'variation' ) && is_callable( array( $product, 'get_parent_id' ) ) ) {
		$parent_id = (int) $product->get_parent_id();
		if ( $parent_id > 0 ) {
			return $parent_id;
		}
	}

	return (int) $product->get_id();
}

/**
 * Average and count of approved customer star ratings for a product.
 *
 * Reads comment meta directly so stale WooCommerce lookup tables or disabled
 * native star-rating settings cannot hide real CusRev / WooCommerce reviews.
 *
 * @param int $product_id Product ID.
 * @return array{count:int, average:float}|null
 */
function nh_seo_product_rating_aggregate( $product_id ) {
	static $cache = array();

	$product_id = (int) $product_id;
	if ( $product_id <= 0 ) {
		return null;
	}

	if ( array_key_exists( $product_id, $cache ) ) {
		return $cache[ $product_id ];
	}

	global $wpdb;

	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT COUNT(*) AS rating_count, AVG(cm.meta_value + 0) AS rating_avg
			FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->commentmeta} cm
				ON cm.comment_id = c.comment_ID AND cm.meta_key = 'rating'
			WHERE c.comment_post_ID = %d
				AND c.comment_approved = '1'
				AND c.comment_parent = 0
				AND c.comment_type NOT IN ('cr_qna', 'pingback', 'trackback', 'order_note')
				AND (cm.meta_value + 0) > 0",
			$product_id
		)
	);

	$count   = $row ? (int) $row->rating_count : 0;
	$average = $row ? (float) $row->rating_avg : 0.0;

	$cache[ $product_id ] = ( $count > 0 && $average > 0 )
		? array(
			'count'   => $count,
			'average' => $average,
		)
		: null;

	return $cache[ $product_id ];
}

/**
 * Most recent approved reviews that include a star rating.
 *
 * @param int $product_id Product ID.
 * @param int $limit      Max comments.
 * @return array<int, WP_Comment>
 */
function nh_seo_product_review_comments( $product_id, $limit = 10 ) {
	$product_id = (int) $product_id;
	$limit      = max( 1, (int) $limit );
	if ( $product_id <= 0 ) {
		return array();
	}

	$comments = get_comments(
		array(
			'post_id'                   => $product_id,
			'status'                    => 'approve',
			'parent'                    => 0,
			'type__not_in'              => array( 'cr_qna', 'pingback', 'trackback', 'order_note' ),
			'number'                    => $limit,
			'orderby'                   => 'comment_date_gmt',
			'order'                     => 'DESC',
			'no_found_rows'             => true,
			'update_comment_meta_cache' => true,
			'meta_query'                => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'rating',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		)
	);

	return is_array( $comments ) ? $comments : array();
}

/**
 * One nested Review object for Product JSON-LD, or null if unusable.
 *
 * @param WP_Comment $comment Review comment.
 * @return array<string, mixed>|null
 */
function nh_seo_comment_to_review_schema( $comment ) {
	if ( ! $comment instanceof WP_Comment ) {
		return null;
	}

	$rating = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
	if ( $rating < 1 || $rating > 5 ) {
		return null;
	}

	$author = trim( wp_strip_all_tags( get_comment_author( $comment ) ) );
	if ( $author === '' ) {
		return null;
	}

	$review = array(
		'@type'        => 'Review',
		'reviewRating' => array(
			'@type'       => 'Rating',
			'bestRating'  => '5',
			'worstRating' => '1',
			'ratingValue' => (string) $rating,
		),
		'author'       => array(
			'@type' => 'Person',
			'name'  => $author,
		),
		'datePublished' => get_comment_date( 'c', $comment->comment_ID ),
	);

	$title = trim( wp_strip_all_tags( (string) get_comment_meta( $comment->comment_ID, 'cr_rev_title', true ) ) );
	if ( $title !== '' ) {
		$review['name'] = $title;
	}

	$body = trim( wp_strip_all_tags( get_comment_text( $comment ) ) );
	if ( $body !== '' ) {
		$review['reviewBody'] = $body;
	}

	return $review;
}

/**
 * Whether Product markup already has a usable aggregateRating.
 *
 * @param array<string, mixed> $markup Product markup.
 * @return bool
 */
function nh_seo_product_markup_has_rating( $markup ) {
	if ( empty( $markup['aggregateRating'] ) || ! is_array( $markup['aggregateRating'] ) ) {
		return false;
	}

	$value = isset( $markup['aggregateRating']['ratingValue'] ) ? (float) $markup['aggregateRating']['ratingValue'] : 0.0;
	$count = 0;
	if ( isset( $markup['aggregateRating']['reviewCount'] ) ) {
		$count = (int) $markup['aggregateRating']['reviewCount'];
	} elseif ( isset( $markup['aggregateRating']['ratingCount'] ) ) {
		$count = (int) $markup['aggregateRating']['ratingCount'];
	}

	return $value > 0 && $count > 0;
}

/**
 * Nest real customer aggregateRating + review inside Product JSON-LD.
 *
 * WooCommerce only emits these when get_rating_count() is set and native
 * star ratings are enabled. CusRev reviews are WooCommerce comments, but
 * that gate often leaves Product snippets without review fields in GSC.
 *
 * Google forbids fabricated ratings: products with no approved reviews
 * stay unchanged (GSC will still list those as optional enhancements).
 *
 * @param array<string, mixed> $markup  Product markup.
 * @param WC_Product           $product Product.
 * @return array<string, mixed>
 */
function nh_seo_product_review_schema( $markup, $product ) {
	if ( ! is_array( $markup ) ) {
		return $markup;
	}

	$product_id = nh_seo_review_product_id( $product );
	if ( $product_id <= 0 ) {
		return $markup;
	}

	$has_rating = nh_seo_product_markup_has_rating( $markup );
	$has_review = ! empty( $markup['review'] );
	if ( $has_rating && $has_review ) {
		return $markup;
	}

	$aggregate = nh_seo_product_rating_aggregate( $product_id );
	if ( ! $aggregate ) {
		return $markup;
	}

	if ( ! $has_rating ) {
		$rating_value = function_exists( 'wc_format_decimal' )
			? wc_format_decimal( $aggregate['average'], 2 )
			: number_format( $aggregate['average'], 2, '.', '' );

		$markup['aggregateRating'] = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => $rating_value,
			'bestRating'  => '5',
			'worstRating' => '1',
			'ratingCount' => $aggregate['count'],
			'reviewCount' => $aggregate['count'],
		);
	}

	if ( ! $has_review ) {
		$limit = (int) apply_filters( 'nh_seo_product_schema_review_limit', 10, $product );
		$items = array();
		foreach ( nh_seo_product_review_comments( $product_id, $limit ) as $comment ) {
			$item = nh_seo_comment_to_review_schema( $comment );
			if ( $item ) {
				$items[] = $item;
			}
		}
		if ( $items ) {
			$markup['review'] = $items;
		}
	}

	return $markup;
}
add_filter( 'woocommerce_structured_data_product', 'nh_seo_product_review_schema', 30, 2 );

/**
 * Mirror review fields onto Yoast Product pieces when WooCommerce SEO is active.
 *
 * @param array<int, array<string, mixed>> $graph Schema graph.
 * @return array<int, array<string, mixed>>
 */
function nh_seo_filter_product_schema_graph( $graph ) {
	if ( ! is_array( $graph ) || ! function_exists( 'wc_get_product' ) ) {
		return $graph;
	}

	$product = null;
	if ( function_exists( 'is_product' ) && is_product() ) {
		$product = wc_get_product( get_queried_object_id() );
	}
	if ( ! $product ) {
		return $graph;
	}

	foreach ( $graph as $i => $piece ) {
		if ( ! is_array( $piece ) || empty( $piece['@type'] ) ) {
			continue;
		}
		$types = (array) $piece['@type'];
		if ( ! array_intersect( array( 'Product', 'ProductGroup' ), $types ) ) {
			continue;
		}
		$graph[ $i ] = nh_seo_product_review_schema( $piece, $product );
	}

	return $graph;
}
add_filter( 'wpseo_schema_graph', 'nh_seo_filter_product_schema_graph', 20 );

/**
 * ItemList of visible products on product category archives (page 1).
 */
function nh_seo_output_collection_itemlist() {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() || is_paged() ) {
		return;
	}

	$term = get_queried_object();
	if ( ! $term instanceof WP_Term ) {
		return;
	}

	global $wp_query;
	if ( empty( $wp_query->posts ) || ! is_array( $wp_query->posts ) ) {
		return;
	}

	$elements = array();
	$position = 1;

	foreach ( $wp_query->posts as $post ) {
		if ( $position > 24 ) {
			break;
		}
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;
		if ( ! $product || ! is_callable( array( $product, 'is_visible' ) ) || ! $product->is_visible() ) {
			continue;
		}
		$elements[] = array(
			'@type'    => 'ListItem',
			'position' => $position,
			'url'      => get_permalink( $post ),
			'name'     => get_the_title( $post ),
		);
		$position++;
	}

	if ( empty( $elements ) ) {
		return;
	}

	$graph = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'ItemList',
		'name'            => $term->name,
		'url'             => get_term_link( $term ),
		'numberOfItems'   => count( $elements ),
		'itemListElement' => $elements,
	);

	echo '<script type="application/ld+json">' . nh_seo_jsonld( $graph ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_footer', 'nh_seo_output_collection_itemlist', 20 );

/**
 * Whether this request should be kept out of the index.
 *
 * @return bool
 */
function nh_seo_is_noindex_request() {
	if ( is_search() ) {
		return true;
	}

	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
		return true;
	}

	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	if ( $uri && preg_match( '#/(wishlist|wish-list)(/|\?|$)#i', $uri ) ) {
		return true;
	}

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post && nh_seo_is_utility_page( $post ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Wishlist / search / utility Woo pages should not compete in the index.
 *
 * @param array<string, bool> $robots Robots directives.
 * @return array<string, bool>
 */
function nh_seo_wp_robots( $robots ) {
	if ( ! is_array( $robots ) ) {
		$robots = array();
	}

	if ( nh_seo_is_noindex_request() ) {
		$robots['noindex'] = true;
	}

	return $robots;
}
add_filter( 'wp_robots', 'nh_seo_wp_robots', 20 );

/**
 * Yoast robots string (Wishlist is currently indexable on live shops).
 *
 * @param string $robots Robots content.
 * @return string
 */
function nh_seo_wpseo_robots( $robots ) {
	if ( ! nh_seo_is_noindex_request() ) {
		return $robots;
	}

	$parts   = array_map( 'trim', explode( ',', (string) $robots ) );
	$parts   = array_values( array_filter( $parts, function ( $part ) {
		return $part !== '' && 0 !== strcasecmp( $part, 'index' );
	} ) );
	if ( ! in_array( 'noindex', $parts, true ) ) {
		array_unshift( $parts, 'noindex' );
	}

	return implode( ', ', $parts );
}
add_filter( 'wpseo_robots', 'nh_seo_wpseo_robots', 20 );

/**
 * Fallback JSON-LD when Yoast is not active (local/dev).
 */
function nh_seo_fallback_organization_jsonld() {
	if ( defined( 'WPSEO_VERSION' ) ) {
		return;
	}
	if ( ! is_front_page() ) {
		return;
	}

	$p     = nh_seo_organization_profile();
	$graph = array(
		'@context'   => 'https://schema.org',
		'@type'      => array( 'Organization', 'OnlineStore' ),
		'name'       => $p['name'],
		'legalName'  => $p['legalName'],
		'url'        => $p['url'],
		'email'      => $p['email'],
		'telephone'  => $p['telephone'],
		'address'    => $p['address'],
		'sameAs'     => $p['sameAs'],
		'areaServed' => $p['areaServed'],
	);

	echo '<script type="application/ld+json">' . nh_seo_jsonld( $graph ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_footer', 'nh_seo_fallback_organization_jsonld', 5 );
