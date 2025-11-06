<?php
/**
 * Astra Custom for Norhage Theme functions and definitions
 *
 * @package Astra Custom for Norhage
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION', '1.0.0' );

/**
 * Load theme textdomain
 */
add_action( 'after_setup_theme', function () {
	load_theme_textdomain( 'nh-theme', get_stylesheet_directory() . '/languages' );
} );

/**
 * Register Primary & Secondary Menu Locations
 */
function norhage_register_menus() {
	register_nav_menus( array(
		'primary'   => __( 'Primary Menu', 'nh-theme' ),
		'secondary' => __( 'Secondary Menu', 'nh-theme' ),
	) );
}
add_action( 'after_setup_theme', 'norhage_register_menus' );

/**
 * Localized slug for the "Services" CPT.
 */
function nh_get_services_slug(): string {
	if ( defined('NH_SERVICES_SLUG') && NH_SERVICES_SLUG ) {
		return sanitize_title( NH_SERVICES_SLUG );
	}
	$locale = function_exists('determine_locale') ? determine_locale() : get_locale();
	switch ( $locale ) {
		case 'lt_LT': return 'paslaugos';
		case 'nb_NO': return 'tjenester';
		case 'sv_SE': return 'tjanster';
		case 'de_DE': return 'leistungen';
		case 'fi_FI': return 'palvelut';
		default:      return 'services';
	}
}

/**
 * Register "Services" custom post type (with localized slug).
 */
function register_services_post_type() {
	$labels = array(
		'name'               => _x( 'Services', 'Post Type General Name', 'nh-theme' ),
		'singular_name'      => _x( 'Service', 'Post Type Singular Name', 'nh-theme' ),
		'menu_name'          => __( 'Services', 'nh-theme' ),
		'name_admin_bar'     => __( 'Service', 'nh-theme' ),
		'add_new'            => __( 'Add New', 'nh-theme' ),
		'add_new_item'       => __( 'Add New Service', 'nh-theme' ),
		'new_item'           => __( 'New Service', 'nh-theme' ),
		'edit_item'          => __( 'Edit Service', 'nh-theme' ),
		'view_item'          => __( 'View Service', 'nh-theme' ),
		'all_items'          => __( 'All Services', 'nh-theme' ),
		'search_items'       => __( 'Search Services', 'nh-theme' ),
		'not_found'          => __( 'No services found.', 'nh-theme' ),
		'not_found_in_trash' => __( 'No services found in Trash.', 'nh-theme' ),
	);

	$slug = apply_filters( 'nh/services_slug', nh_get_services_slug() );

	$args = array(
		'labels'        => $labels,
		'public'        => true,
		'show_in_rest'  => true,
		'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'menu_position' => 5,
		'menu_icon'     => 'dashicons-admin-tools',
		'rewrite'       => array( 'slug' => $slug, 'with_front' => false ),
		'has_archive'   => $slug,
	);

	register_post_type( 'service', $args );
}
add_action( 'init', 'register_services_post_type' );

/**
 * Flush rewrites on theme (re)activation.
 */
add_action( 'after_switch_theme', function () {
	flush_rewrite_rules();
} );

/**
 * Enqueue assets
 */
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
}
add_action( 'wp_enqueue_scripts', 'norhage_enqueue_assets', 15 );

/* --------------------------------------------------------------------------
 * UTILITY BAR (topmost): Secondary menu (left) + Phone & FAQ (right)
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
		<div class="nh-utility__left"><?php echo $left_menu_html; // phpcs:ignore ?></div>
		<div class="nh-utility__right">
			<a class="nh-utility__tel" href="tel:+4917665106609">📞 +49 176 65 10 6609</a>
			<span class="nh-utility__sep" aria-hidden="true">·</span>
			<a class="nh-utility__faq" href="<?php echo esc_url( home_url( 'frequently-asked-questions-faq/' ) ); ?>">
				<?php echo esc_html__( 'FAQ', 'nh-theme' ); ?>
			</a>
		</div>
	</div>
	<?php
}, 10 );

/* --------------------------------------------------------------------------
 * COMPACT MAIN HEADER: logo + primary menu + tools (template part)
 * ----------------------------------------------------------------------- */
add_action( 'astra_masthead_bottom', function () {
	// Render our unified header bar just below the utility bar.
	locate_template( 'template-parts/headers/header-main.php', true, false );
}, 12 );

/* --------------------------------------------------------------------------
 * Woo cart fragments for the cart count inside header-main.php
 * ----------------------------------------------------------------------- */
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
 * NEWS TICKER (plugin output) — shows between header and hero
 * ----------------------------------------------------------------------- */
add_action( 'astra_header_after', function () {
	if ( function_exists( 'rtl_display_ticker' ) ) {
		echo '<div class="nh-ticker-wrap">';
		rtl_display_ticker();
		echo '</div>';
	}
}, 15 ); // hero prints at 20

/* --------------------------------------------------------------------------
 * HERO / PAGE-HEADER SYSTEM (inject hero beneath header)
 * ----------------------------------------------------------------------- */
function nhhb_get_hero_image_url() {
	if ( is_singular() ) {
		$id  = get_queried_object_id();
		$url = get_the_post_thumbnail_url( $id, 'full' );
		if ( $url ) return $url;
	}
	return '';
}

function nhhb_get_hero_title() {
	if ( is_front_page() ) {
		return get_the_title( get_queried_object_id() );
	}
	if ( is_home() ) {
		$page_for_posts = (int) get_option( 'page_for_posts' );
		return $page_for_posts ? get_the_title( $page_for_posts ) : __( 'Blog', 'nh-theme' );
	}
	if ( is_singular() ) {
		return get_the_title();
	}
	if ( is_search() ) {
		return sprintf( __( 'Search results for “%s”', 'nh-theme' ), get_search_query() );
	}
	if ( is_archive() ) {
		return get_the_archive_title();
	}
	return get_bloginfo( 'name' );
}

add_action( 'astra_header_after', function () {
	if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() || is_product() ) ) {
		return; // no hero on shop/product surfaces
	}

	$data = array(
		'bg'    => nhhb_get_hero_image_url(),
		'title' => nhhb_get_hero_title(),
	);

	set_query_var( 'nhhb_hero', $data );

	if ( is_front_page() ) {
		locate_template( 'template-parts/headers/hero-home.php', true, false );
	} else {
		locate_template( 'template-parts/headers/hero-page.php', true, false );
	}
}, 20 );

/* --------------------------------------------------------------------------
 * Subcategory grid (kept)
 * ----------------------------------------------------------------------- */
add_action( 'woocommerce_before_shop_loop', 'show_subcategories_grid', 10 );
function show_subcategories_grid() {
	if ( ! is_product_category() ) {
		return;
	}

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
 * Include custom feature files
 * ----------------------------------------------------------------------- */
require_once get_stylesheet_directory() . '/inc/search.php';
require_once get_stylesheet_directory() . '/inc/meta-boxes.php';
require_once get_stylesheet_directory() . '/inc/product-customize.php';
require_once get_stylesheet_directory() . '/inc/bundle-box.php';
require_once get_stylesheet_directory() . '/inc/sale-category-sync.php';
require_once get_stylesheet_directory() . '/inc/basket-customize.php';

/* --------------------------------------------------------------------------
 * Secondary product title
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
	if ( $secondary ) {
		echo '<h2 class="product-secondary-title">' . esc_html( $secondary ) . '</h2>';
	}
}, 10 );

add_action( 'astra_woo_single_title_after', function () {
	$secondary = get_post_meta( get_the_ID(), '_secondary_product_title', true );
	if ( $secondary ) {
		echo '<h2 class="product-secondary-title">' . esc_html( $secondary ) . '</h2>';
	}
}, 10 );

function nhg_output_secondary_title_single() {
	global $product;
	if ( ! $product ) return;
	$secondary = get_post_meta( $product->get_id(), '_secondary_product_title', true );
	if ( $secondary ) {
		echo '<h2 class="product-secondary-title">' . esc_html( $secondary ) . '</h2>';
	}
}

/* --------------------------------------------------------------------------
 * Blog category navigation
 * ----------------------------------------------------------------------- */
function norhage_blog_category_nav() {
	if ( is_home() || is_category() ) {
		$current_cat_id = get_queried_object_id();

		echo '<div class="blog-category-nav"><ul>';

		$all_class = is_home() ? 'active' : '';
		echo '<li><a class="' . esc_attr( $all_class ) . '" href="' . esc_url( get_permalink( get_option( 'page_for_posts' ) ) ) . '">' . esc_html__( 'All', 'nh-theme' ) . '</a></li>';

		$categories = get_categories( array(
			'orderby'    => 'name',
			'hide_empty' => true,
		) );

		foreach ( $categories as $category ) {
			$active = ( $current_cat_id === $category->term_id ) ? 'active' : '';
			echo '<li><a class="' . esc_attr( $active ) . '" href="' . esc_url( get_category_link( $category->term_id ) ) . '">' . esc_html( $category->name ) . '</a></li>';
		}

		echo '</ul></div>';
	}
}
add_action( 'astra_primary_content_top', 'norhage_blog_category_nav' );

/* --------------------------------------------------------------------------
 * Footer
 * ----------------------------------------------------------------------- */

// === Footer menu locations ===
add_action('after_setup_theme', function () {
  register_nav_menus([
    'footer_shop'     => __('Footer — Shop Categories', 'nh-theme'),
    'footer_secondary'=> __('Footer — Secondary (Services, Blog, Contact)', 'nh-theme'),
    'footer_legal'    => __('Footer — Legal/Policies', 'nh-theme'),
  ]);
});

// Disable Astra footer pieces via code (also do this in Customizer to be safe)
add_filter('astra_enable_footer_widget', '__return_false');
add_filter('astra_footer_copyright', '__return_false');

// Inject our custom footer markup
add_action('astra_footer', function () {
  get_template_part('template-parts/footer/footer');
}, 5);


/* --------------------------------------------------------------------------
 * Misc / scripts
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

add_filter( 'woocommerce_product_categories_widget_args', 'hide_uncategorized_category' );
function hide_uncategorized_category( $args ) {
	$uncategorized_id = get_option( 'default_product_cat' );
	$args['exclude']  = array( $uncategorized_id );
	return $args;
}

/**
 * Disable Astra Header Builder output — we use our custom header instead.
 */
add_action( 'wp', function() {
	// Remove Header Builder markup (desktop + mobile)
	remove_action( 'astra_header', 'astra_header_builder_markup' );
	remove_action( 'astra_header', 'astra_mobile_header_markup' );

	// Optional: also remove old header template if active anywhere
	remove_action( 'astra_masthead', 'astra_masthead_primary_template' );
});

/**
 * Hide page title + featured image on Pages (Astra), so the hero can own them.
 * Keeps the featured image available for your hero background.
 */
add_action( 'wp', function () {
	if ( ! is_page() ) return;

	// 1) Remove Astra's page title output
	if ( function_exists( 'astra_entry_title' ) ) {
		remove_action( 'astra_entry_content_before', 'astra_entry_title', 10 );
	}

	// 2) Remove Astra's featured image output for pages (covers common hooks)
	if ( function_exists( 'astra_post_thumbnail' ) ) {
		remove_action( 'astra_entry_content_before', 'astra_post_thumbnail', 8 );
		remove_action( 'astra_entry_top',            'astra_post_thumbnail', 8 );
	}
});
