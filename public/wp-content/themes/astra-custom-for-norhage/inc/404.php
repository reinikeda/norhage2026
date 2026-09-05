<?php
/**
 * 404 recovery page — search, URL guesses, categories, popular products.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'nh_404_enqueue_assets', 20 );
function nh_404_enqueue_assets() {
	if ( ! is_404() ) {
		return;
	}

	wp_enqueue_style(
		'nh-404',
		get_stylesheet_directory_uri() . '/assets/css/404.css',
		array( 'astra-custom-for-norhage-theme-css' ),
		norhage_asset_version( '/assets/css/404.css' )
	);
}

/**
 * Turn the broken URL into a product search string.
 */
function nh_404_guess_query() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	$path = rawurldecode( $path );
	$path = trim( $path, '/' );
	$path = preg_replace( '/\.(html?|php)$/i', '', $path );

	$parts = preg_split( '#[/\-_+.]+#', $path );
	if ( ! is_array( $parts ) ) {
		return '';
	}

	$stop = array(
		'produkt', 'product', 'products', 'produktai', 'tuote', 'tuotteet',
		'shop', 'butikk', 'butik', 'kauppa', 'parduotuve',
		'kategori', 'kategoria', 'category', 'categories', 'kategorija',
		'page', 'p', 'index', 'wp', 'en', 'no', 'se', 'dk', 'de', 'fi', 'lt',
	);

	$words = array();
	foreach ( $parts as $part ) {
		$part = strtolower( trim( (string) $part ) );
		if ( strlen( $part ) < 3 || is_numeric( $part ) ) {
			continue;
		}
		if ( in_array( $part, $stop, true ) ) {
			continue;
		}
		$words[] = $part;
	}

	$words = array_slice( array_unique( $words ), 0, 6 );
	return implode( ' ', $words );
}

/**
 * @param array $args
 * @return WC_Product[]
 */
function nh_404_get_products( $args ) {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return array();
	}

	$defaults = array(
		'status'     => 'publish',
		'limit'      => 8,
		'visibility' => 'visible',
		'return'     => 'objects',
	);

	$products = wc_get_products( array_merge( $defaults, $args ) );
	return is_array( $products ) ? $products : array();
}

function nh_404_suggested_products() {
	$guess = nh_404_guess_query();
	if ( $guess === '' ) {
		return array();
	}

	return nh_404_get_products(
		array(
			's'     => $guess,
			'limit' => 6,
		)
	);
}

function nh_404_popular_products( $exclude_ids = array() ) {
	return nh_404_get_products(
		array(
			'limit'   => 8,
			'orderby' => 'popularity',
			'exclude' => array_filter( array_map( 'absint', $exclude_ids ) ),
		)
	);
}

function nh_404_categories() {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return array();
	}

	$exclude = array();
	$uncat   = get_option( 'default_product_cat' );
	if ( $uncat ) {
		$exclude[] = (int) $uncat;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => true,
			'number'     => 8,
			'exclude'    => $exclude,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);

	return is_wp_error( $terms ) ? array() : $terms;
}

function nh_404_render_product_card( WC_Product $product ) {
	$url   = $product->get_permalink();
	$title = $product->get_name();
	$image = $product->get_image(
		'woocommerce_thumbnail',
		array(
			'alt'      => $title,
			'loading'  => 'lazy',
			'decoding' => 'async',
		)
	);
	?>
	<a class="nh-404-card" href="<?php echo esc_url( $url ); ?>">
		<span class="nh-404-card__img"><?php echo $image; ?></span>
		<span class="nh-404-card__name"><?php echo esc_html( $title ); ?></span>
		<span class="nh-404-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
	</a>
	<?php
}

function nh_404_render_product_grid( $products ) {
	if ( empty( $products ) ) {
		return;
	}
	echo '<div class="nh-404-products">';
	foreach ( $products as $product ) {
		if ( $product instanceof WC_Product ) {
			nh_404_render_product_card( $product );
		}
	}
	echo '</div>';
}
