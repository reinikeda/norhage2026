<?php
/**
 * Astra Custom for Norhage Theme functions and definitions
 *
 * @package Astra Custom for Norhage
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION', '1.0.0' );

/* --------------------------------------------------------------------------
 * Core setup
 * ----------------------------------------------------------------------- */

/** Load textdomain */
add_action( 'after_setup_theme', function () {
	load_theme_textdomain( 'nh-theme', get_stylesheet_directory() . '/languages' );
} );

/** Register menus */
function norhage_register_menus() {
	register_nav_menus( array(
		'primary'         => __( 'Primary Menu', 'nh-theme' ),
		'secondary'       => __( 'Secondary Menu', 'nh-theme' ),
		'footer_explore'  => __( 'Footer — Explore', 'nh-theme' ),
		'footer_bottom'   => __( 'Footer — Bottom Bar', 'nh-theme' ),
	) );
}
add_action( 'after_setup_theme', 'norhage_register_menus' );

/* --------------------------------------------------------------------------
 * Assets
 * ----------------------------------------------------------------------- */
function norhage_enqueue_assets() {
	wp_enqueue_style(
		'astra-custom-for-norhage-theme-css',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'astra-theme-css' ),
		CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION
	);

	wp_enqueue_style(
		'norhage-custom-style',
		get_stylesheet_directory_uri() . '/assets/css/product-page.css',
		array(),
		CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION
	);

	wp_enqueue_script(
		'theme-toggle',
		get_stylesheet_directory_uri() . '/assets/js/theme-toggle.js',
		array(),
		null,
		true
	);

	wp_enqueue_style(
		'norhage-dark-mode-css',
		get_stylesheet_directory_uri() . '/assets/css/dark-mode.css',
		array(),
		CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION
	);

	wp_enqueue_script(
		'norhage-custom-js',
		get_stylesheet_directory_uri() . '/assets/js/script.js',
		array(),
		CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION,
		true
	);

	wp_enqueue_script(
		'nh-mobile-header',
		get_stylesheet_directory_uri() . '/assets/js/header-mobile.js',
		array(),
		'1.0.0',
		true
	);
}
add_action( 'wp_enqueue_scripts', 'norhage_enqueue_assets', 15 );

/* --------------------------------------------------------------------------
 * Header: utility bar + compact header include
 * ----------------------------------------------------------------------- */
add_action( 'astra_masthead_top', function () {

	$menu_args = [
		'container'   => false,
		'menu_class'  => 'nh-utility-menu',
		'fallback_cb' => '__return_empty_string',
	];

	$locations = get_nav_menu_locations();
	if ( ! empty( $locations['secondary'] ) ) {
		$menu_args['menu'] = (int) $locations['secondary'];
	} else {
		$maybe = wp_get_nav_menu_object( 'Info Menu' );
		if ( $maybe ) {
			$menu_args['menu'] = (int) $maybe->term_id;
		}
	}

	ob_start();
	wp_nav_menu( $menu_args );
	$left_menu_html = trim( ob_get_clean() );

	if ( $left_menu_html === '' ) {
		$left_menu_html  = '<ul class="nh-utility-menu">';
		$left_menu_html .= '<li><a href="' . esc_url( home_url( '/services/' ) ) . '">' . esc_html__( 'Services', 'nh-theme' ) . '</a></li>';
		$left_menu_html .= '<li><a href="' . esc_url( get_permalink( get_option( 'page_for_posts' ) ) ) . '">' . esc_html__( 'Blog', 'nh-theme' ) . '</a></li>';
		$left_menu_html .= '<li><a href="' . esc_url( home_url( '/contact-us/' ) ) . '">' . esc_html__( 'Contact Us', 'nh-theme' ) . '</a></li>';
		$left_menu_html .= '</ul>';
	}

	?>
	<div class="nh-utility" role="navigation" aria-label="<?php echo esc_attr__( 'Utility bar', 'nh-theme' ); ?>">
		<div class="nh-utility__left">
			<?php echo $left_menu_html; // phpcs:ignore ?>
		</div>
		<div class="nh-utility__right">
			<a class="nh-utility__tel" href="tel:+4917665106609">
				<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/icons/phone.svg' ); ?>" alt="Phone icon" class="nh-icon nh-icon--phone" width="18" height="18" />
				+49 176 65 10 6609
			</a>
			<span class="nh-utility__sep" aria-hidden="true">·</span>
			<a class="nh-utility__faq" href="<?php echo esc_url( home_url( 'frequently-asked-questions-faq/' ) ); ?>">
				<?php echo esc_html__( 'FAQ', 'nh-theme' ); ?>
			</a>
		</div>
	</div>
	<?php
}, 10 );

add_action( 'astra_masthead_bottom', function () {
	locate_template( 'template-parts/headers/header-main.php', true, false );
}, 12 );

/* Woo cart count fragment used by header */
add_filter( 'woocommerce_add_to_cart_fragments', function( $fragments ) {
	ob_start();
	$count = ( function_exists('WC') && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
	?>
	<span class="nh-cart-count"><?php echo (int) $count; ?></span>
	<?php
	$fragments['span.nh-cart-count'] = ob_get_clean();
	return $fragments;
} );

/* --------------------------------------------------------------------------
 * HERO injection (templates live in template-parts/headers/*)
 * Helpers come from /inc/hero.php
 * ----------------------------------------------------------------------- */
add_action( 'astra_header_after', function () {
	if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() || is_product() ) ) {
		return; // no hero on shop/product surfaces
	}

	$data = array(
		'bg'    => function_exists('nhhb_get_hero_image_url') ? nhhb_get_hero_image_url() : '',
		'title' => function_exists('nhhb_get_hero_title') ? nhhb_get_hero_title() : get_bloginfo('name'),
	);

	set_query_var( 'nhhb_hero', $data );

	if ( is_front_page() ) {
		locate_template( 'template-parts/headers/hero-home.php', true, false );
	} else {
		locate_template( 'template-parts/headers/hero-page.php', true, false );
	}
}, 20 );

/* --------------------------------------------------------------------------
 * Woo: subcategory grid on product category
 * ----------------------------------------------------------------------- */
add_action( 'woocommerce_before_shop_loop', 'show_subcategories_grid', 10 );
function show_subcategories_grid() {
	if ( ! is_product_category() ) return;

	$category = get_queried_object();
	$args = array(
		'taxonomy'   => 'product_cat',
		'child_of'   => $category->term_id,
		'hide_empty' => false,
		'parent'     => $category->term_id,
	);
	$subcategories = get_terms( $args );

	if ( ! empty( $subcategories ) ) {
		echo '<div class="subcategory-grid">';
		echo '<h2 class="subcategory-title">' . esc_html__( 'Shop by Category', 'nh-theme' ) . '</h2>';
		echo '<div class="subcategory-items">';

		foreach ( $subcategories as $subcategory ) {
			$thumbnail_id = get_term_meta( $subcategory->term_id, 'thumbnail_id', true );
			$image_url    = $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : wc_placeholder_img_src();

			echo '<div class="subcategory-item">';
			echo '<a href="' . esc_url( get_term_link( $subcategory ) ) . '">';
			echo '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $subcategory->name ) . '" />';
			echo '<span>' . esc_html( $subcategory->name ) . '</span>';
			echo '</a></div>';
		}

		echo '</div></div>';
	}
}

/* --------------------------------------------------------------------------
 * Feature modules (keep functions.php light)
 * ----------------------------------------------------------------------- */
require_once get_stylesheet_directory() . '/inc/search.php';
require_once get_stylesheet_directory() . '/inc/meta-boxes.php';
require_once get_stylesheet_directory() . '/inc/product-customize.php';
require_once get_stylesheet_directory() . '/inc/bundle-box.php';
require_once get_stylesheet_directory() . '/inc/sale-category-sync.php';
require_once get_stylesheet_directory() . '/inc/basket-customize.php';
require_once get_stylesheet_directory() . '/inc/order-attributes.php';

/* New split modules */
require_once get_stylesheet_directory() . '/inc/hero.php';      // hero helpers (title + image)
require_once get_stylesheet_directory() . '/inc/blog.php';      // blog titles/meta/nav/related
require_once get_stylesheet_directory() . '/inc/services.php';  // services CPT + titles/meta/related

/* --------------------------------------------------------------------------
 * Shop archive button tweaks (Customize flow for simple products)
 * ----------------------------------------------------------------------- */

function nh_is_custom_cut_simple( $product ): bool {
	if ( ! $product instanceof WC_Product ) return false;
	if ( ! $product->is_type( 'simple' ) ) return false;
	return (bool) get_post_meta( $product->get_id(), '_nh_cc_enabled', true );
}

add_filter( 'woocommerce_loop_add_to_cart_link', function( $html, $product, $args ){
	if ( nh_is_custom_cut_simple( $product ) ) {
		$url     = $product->get_permalink();
		$text    = __( 'Customize', 'nh-theme' );
		$label   = sprintf( __( 'Customize “%s”', 'nh-theme' ), $product->get_name() );
		$base    = isset( $args['class'] ) ? $args['class'] : 'button';
		$classes = trim( $base . ' nh-btn-customize' );
		return sprintf(
			'<a href="%s" class="%s" aria-label="%s" rel="nofollow">%s</a>',
			esc_url( $url ),
			esc_attr( $classes ),
			esc_attr( $label ),
			esc_html( $text )
		);
	}
	return $html;
}, 10, 3 );

add_filter( 'woocommerce_product_add_to_cart_text', function( $text, $product ){
	return nh_is_custom_cut_simple( $product ) ? __( 'Customize', 'nh-theme' ) : $text;
}, 10, 2 );

add_filter( 'woocommerce_product_single_add_to_cart_text', function( $text ){ return $text; }, 10 );

add_filter( 'woocommerce_product_add_to_cart_url', function( $url, $product ){
	return nh_is_custom_cut_simple( $product ) ? $product->get_permalink() : $url;
}, 10, 2 );

/* --------------------------------------------------------------------------
 * Secondary product title (editor field + outputs)
 * ----------------------------------------------------------------------- */
add_action( 'edit_form_after_title', function( $post ) {
	if ( $post->post_type !== 'product' ) return;

	$value = get_post_meta( $post->ID, '_secondary_product_title', true );
	wp_nonce_field( 'secondary_product_title_nonce', 'secondary_product_title_nonce' );

	echo '<div style="margin:12px 0;padding:10px 12px;border:1px solid #dcdcde;background:#fff;border-radius:4px;">';
	echo '<label for="secondary_product_title" style="font-weight:600;display:block;margin-bottom:6px;">' . esc_html__( 'Secondary product title', 'nh-theme' ) . '</label>';
	echo '<input type="text" id="secondary_product_title" name="secondary_product_title" value="' . esc_attr( $value ) . '" class="widefat" />';
	echo '</div>';
} );

add_action( 'save_post_product', function( $post_id ) {
	if ( ! isset( $_POST['secondary_product_title_nonce'] ) || ! wp_verify_nonce( $_POST['secondary_product_title_nonce'], 'secondary_product_title_nonce' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$val = isset( $_POST['secondary_product_title'] ) ? sanitize_text_field( $_POST['secondary_product_title'] ) : '';
	update_post_meta( $post_id, '_secondary_product_title', $val );
} );

add_action( 'astra_woo_shop_title_after', function () {
	$secondary = get_post_meta( get_the_ID(), '_secondary_product_title', true );
	if ( $secondary ) echo '<h2 class="product-secondary-title">' . esc_html( $secondary ) . '</h2>';
}, 10 );

add_action( 'astra_woo_single_title_after', function () {
	$secondary = get_post_meta( get_the_ID(), '_secondary_product_title', true );
	if ( $secondary ) echo '<h2 class="product-secondary-title">' . esc_html( $secondary ) . '</h2>';
}, 10 );

/* --------------------------------------------------------------------------
 * Footer
 * ----------------------------------------------------------------------- */
add_action('after_setup_theme', function () {
	register_nav_menus([
		'footer_shop'      => __('Footer — Shop Categories', 'nh-theme'),
		'footer_secondary' => __('Footer — Secondary (Services, Blog, Contact)', 'nh-theme'),
		'footer_legal'     => __('Footer — Legal/Policies', 'nh-theme'),
	]);
});

add_filter('astra_enable_footer_widget', '__return_false');
add_filter('astra_footer_copyright', '__return_false');

add_action('astra_footer', function () {
	get_template_part('template-parts/footer/footer');
}, 5);

/* --------------------------------------------------------------------------
 * Misc
 * ----------------------------------------------------------------------- */
function astra_child_enqueue_scripts() {
	wp_enqueue_script(
		'category-toggle',
		get_stylesheet_directory_uri() . '/assets/js/category-toggle.js',
		array( 'jquery' ),
		'1.0',
		true
	);
}
add_action( 'wp_enqueue_scripts', 'astra_child_enqueue_scripts' );

add_filter( 'woocommerce_product_categories_widget_args', function( $args ) {
	$uncategorized_id = get_option( 'default_product_cat' );
	$args['exclude']  = array( $uncategorized_id );
	return $args;
} );

/** Disable Astra Header Builder (we use our own header) */
add_action( 'wp', function() {
	remove_action( 'astra_header', 'astra_header_builder_markup' );
	remove_action( 'astra_header', 'astra_mobile_header_markup' );
	remove_action( 'astra_masthead', 'astra_masthead_primary_template' );
} );

/** Hide page title/featured image on Pages so hero owns them */
add_action( 'wp', function () {
	if ( ! is_page() ) return;

	if ( function_exists( 'astra_entry_title' ) ) {
		remove_action( 'astra_entry_content_before', 'astra_entry_title', 10 );
	}
	if ( function_exists( 'astra_post_thumbnail' ) ) {
		remove_action( 'astra_entry_content_before', 'astra_post_thumbnail', 8 );
		remove_action( 'astra_entry_top',            'astra_post_thumbnail', 8 );
	}
} );

/* --------------------------------------------------------------------------
 * Custom sequential order numbers per WooCommerce base country
 * ----------------------------------------------------------------------- */

add_filter( 'woocommerce_order_number', 'nh_custom_order_number_by_country', 10, 2 );
function nh_custom_order_number_by_country( $order_number, $order ) {

    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
        return $order_number;
    }

    // Get WooCommerce base country (set in WooCommerce → Settings → General)
    if ( function_exists( 'wc_get_base_location' ) ) {
        $base_location = wc_get_base_location(); // e.g. [ 'country' => 'LT', 'state' => '' ]
        $country       = isset( $base_location['country'] ) ? strtoupper( $base_location['country'] ) : '';
    } else {
        $country = '';
    }

    // Configure per-shop prefix + starting number
    $settings = [
        'LT' => [ 'prefix' => 'LT-', 'start' => 1 ],     // Lithuania: LT-0001, LT-0002...
        'NO' => [ 'prefix' => 'NO-', 'start' => 2000 ],  // Norway:   NO-2000, NO-2001...
        'SE' => [ 'prefix' => 'SE-', 'start' => 2000 ],  // Sweden:   SE-2000...
        'DE' => [ 'prefix' => 'DE-', 'start' => 500 ],   // Germany:  DE-0500...
        'FI' => [ 'prefix' => 'FI-', 'start' => 100 ],   // Finland:  FI-0100...
    ];

    // If this shop's country is not configured, keep default Woo number
    if ( ! isset( $settings[ $country ] ) ) {
        return $order_number;
    }

    $prefix = $settings[ $country ]['prefix'];
    $start  = (int) $settings[ $country ]['start'];

    // WooCommerce internal order ID
    $order_id = (int) $order->get_id();

    // Create sequential number with offset
    $custom_number = $start + ( $order_id - 1 );

    // Always 4 digits: 0001, 0200, 2000, etc.
    $formatted = str_pad( $custom_number, 4, '0', STR_PAD_LEFT );

    return $prefix . $formatted;
}

/**
 * Show product images in WooCommerce emails.
 */
add_filter( 'woocommerce_email_order_items_args', function( $args, $email ) {

	// Limit to specific emails (optional).
	$allowed_email_ids = array(
		'new_order',                   // Admin: new order
		'customer_processing_order',   // Customer: order received / processing
		'customer_completed_order',    // Customer: completed
	);

	if ( isset( $email->id ) && in_array( $email->id, $allowed_email_ids, true ) ) {
		$args['show_image'] = true;          // show product thumbnail
		$args['image_size'] = array( 64, 64 ); // thumbnail size in px
	}

	return $args;
}, 10, 2 );
