<?php
/**
 * Technical SEO: hreflang, schema, crawler signals, Yoast quality filters.
 *
 * Live shops (same theme, per-domain language):
 *   norhage.eu (en, x-default), .de, .dk, .se, .no, .fi, .lt
 *
 * Yoast SEO is installed on the servers (not in this repo). Filters no-op if Yoast is off.
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
 * Homepage hreflang is host-based (complete, reciprocal, no SKU lookup).
 * Product SKU hreflang is disabled: catalogs do not overlap, so partial
 * clusters confused crawlers more than having no product annotations.
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
 * Print llms.txt discovery link and homepage hreflang cluster.
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

	return array();
}

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
