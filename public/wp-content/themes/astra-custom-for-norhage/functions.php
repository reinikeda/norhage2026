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

/**
 * Locale-aware "Services" slug + URL helper.
 * Adjust the $map values to match the actual slugs you use per site.
 */
function nh_get_services_slug() {
	$locale = get_locale(); // e.g. 'lt_LT', 'nb_NO', 'sv_SE', 'de_DE', 'fi_FI'

	$map = [
		// Lithuanian
		'lt_LT' => 'paslaugos',

		// Norwegian (Bokmål) – example, change if needed
		'nb_NO' => 'tjenester',

		// Swedish – example
		'sv_SE' => 'tjanster',

		// Finnish – example
		'fi_FI' => 'palvelut',

		// German – example
		'de_DE' => 'leistungen',
	];

	$slug = isset( $map[ $locale ] ) ? $map[ $locale ] : 'services';

	/**
	 * Filter to allow per-site override without editing theme files.
	 *
	 * @param string $slug   The resolved slug (without slashes).
	 * @param string $locale Current site locale, e.g. 'lt_LT'.
	 */
	return apply_filters( 'nh_services_slug', $slug, $locale );
}

/**
 * Convenience helper: full URL to the Services page.
 */
function nh_get_services_url() {
	$slug = nh_get_services_slug();
	return home_url( '/' . trim( $slug, '/' ) . '/' );
}

if ( ! function_exists( 'nh_get_faq_url' ) ) {
	/**
	 * Get FAQ page URL in a translatable way.
	 *
	 * Default: looks for a page with slug "frequently-asked-questions-faq"
	 * (translated via .po). If the page is not found, falls back to home_url() with that slug.
	 *
	 * You can override via the `nh_get_faq_url` filter for WPML/Polylang, etc.
	 */
	function nh_get_faq_url() {
		// Translatable/default slug
		$slug = sanitize_title(
			_x( 'frequently-asked-questions-faq', 'Default FAQ page slug', 'nh-theme' )
		);

		$page = get_page_by_path( $slug );
		if ( $page ) {
			$url = get_permalink( $page );
		} else {
			// Fallback if page doesn’t exist (yet)
			$url = home_url( '/' . $slug . '/' );
		}

		return apply_filters( 'nh_get_faq_url', $url, $page, $slug );
	}
}

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
		'norhage-header-style',
		get_stylesheet_directory_uri() . '/assets/css/header.css',
		array(),
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

	wp_enqueue_style(
		'custom-basket-css',
		get_stylesheet_directory_uri() . '/assets/css/basket.css',
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
		'norhage-header-dynamic',
		get_stylesheet_directory_uri() . '/assets/js/header-dynamic.js',
		array('jquery'),
		CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION,
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
		$left_menu_html .= '<li><a href="' . esc_url( nh_get_services_url() ) . '">' . esc_html__( 'Services', 'nh-theme' ) . '</a></li>';
		$left_menu_html .= '<li><a href="' . esc_url( get_permalink( get_option( 'page_for_posts' ) ) ) . '">' . esc_html__( 'Blog', 'nh-theme' ) . '</a></li>';
		$left_menu_html .= '<li><a href="' . esc_url( home_url( '/contact-us/' ) ) . '">' . esc_html__( 'Contact Us', 'nh-theme' ) . '</a></li>';
		$left_menu_html .= '</ul>';
	}

	// Phone: text and href both translatable via .po.
	// Example default values (you translate them per language):
	$phone_display = __( '+49 176 65 10 6609', 'nh-theme' );
	$phone_href    = __( '+4917665106609', 'nh-theme' );

	// Build tel: link (strip spaces just in case)
	$phone_href_clean = 'tel:' . preg_replace( '/\s+/', '', $phone_href );

	?>
	<div class="nh-utility" role="navigation" aria-label="<?php echo esc_attr__( 'Utility bar', 'nh-theme' ); ?>">
		<div class="nh-utility__left">
			<?php echo $left_menu_html; // phpcs:ignore ?>
		</div>
		<div class="nh-utility__right">
			<a class="nh-utility__tel" href="<?php echo esc_attr( $phone_href_clean ); ?>">
				<img
					src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/icons/phone.svg' ); ?>"
					alt="<?php echo esc_attr__( 'Phone icon', 'nh-theme' ); ?>"
					class="nh-icon nh-icon--phone"
					width="18"
					height="18"
				/>
				<?php echo esc_html( $phone_display ); ?>
			</a>
			<span class="nh-utility__sep" aria-hidden="true">·</span>
			<a class="nh-utility__faq" href="<?php echo esc_url( nh_get_faq_url() ); ?>">
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
require_once get_stylesheet_directory() . '/inc/hero.php';
require_once get_stylesheet_directory() . '/inc/blog.php';
require_once get_stylesheet_directory() . '/inc/services.php';

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

add_filter( 'woocommerce_product_add_to_cart_text', function( $text, $product ) {

    // 1) Your custom simple products → "Customize" (already translatable in nh-theme)
    if ( nh_is_custom_cut_simple( $product ) ) {
        return __( 'Customize', 'nh-theme' );
    }

    // 2) Variable products → our own translatable string in the theme
    if ( $product instanceof WC_Product && $product->is_type( 'variable' ) ) {
        return __( 'Select options', 'nh-theme' );
    }
    return $text;

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

/* --------------------------------------------------------------------------
 * Newsletters connection to sender.net
 * ----------------------------------------------------------------------- */

// === 1) Enqueue JS & pass AJAX URL + nonce ==========================
add_action( 'wp_enqueue_scripts', function () {
	// Adjust path if you put the JS somewhere else.
	wp_enqueue_script(
		'nh-sender-newsletter',
		get_stylesheet_directory_uri() . '/assets/js/nh-sender-newsletter.js',
		[ 'jquery' ],
		'1.0.0',
		true
	);

	wp_localize_script(
		'nh-sender-newsletter',
		'nhSenderNewsletter',
		[
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'nh_sender_subscribe' ),
		]
	);
} );

// === 2) AJAX endpoint: called by BOTH forms =========================
add_action( 'wp_ajax_nopriv_nh_sender_subscribe', 'nh_sender_subscribe' );
add_action( 'wp_ajax_nh_sender_subscribe',        'nh_sender_subscribe' );

function nh_sender_subscribe() {
	check_ajax_referer( 'nh_sender_subscribe', 'nonce' );

	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

	if ( ! is_email( $email ) ) {
		wp_send_json_error( [
			'message' => __( 'Please enter a valid email address.', 'nh-theme' ),
		] );
	}

	// Simple honeypot support for the homepage newsletter form
	if ( ! empty( $_POST['nhhb_hp'] ) ) {
		// Pretend success for bots
		wp_send_json_success( [
			'message' => __( 'Thank you!', 'nh-theme' ),
		] );
	}

	$api_key  = defined('NH_SENDER_API_KEY')  ? NH_SENDER_API_KEY  : '';
	$group_id = defined('NH_SENDER_GROUP_ID') ? NH_SENDER_GROUP_ID : '';

	if ( ! $api_key || ! $group_id ) {
		wp_send_json_error( [
			'message' => __( 'Subscription is temporarily unavailable. Please contact site administrator.', 'nh-theme' ),
		] );
	}

	$body = [
		'email'              => $email,
		'groups'             => [ $group_id ],  // can be multiple
		'trigger_automation' => true,          // run welcome flows etc.
	];

	$response = wp_remote_post(
		'https://api.sender.net/v2/subscribers',
		[
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			],
			'body'    => wp_json_encode( $body ),
			'timeout' => 10,
		]
	);

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( [
			'message' => __( 'Could not connect to email service. Please try again.', 'nh-theme' ),
		] );
	}

	$status = wp_remote_retrieve_response_code( $response );
	$resp_body = json_decode( wp_remote_retrieve_body( $response ), true );

	// 200/201 – OK; 409 is typically "already subscribed"
	if ( in_array( $status, [ 200, 201, 409 ], true ) ) {
		wp_send_json_success( [
			'message' => __( 'Thank you! You are subscribed.', 'nh-theme' ),
			'raw'     => $resp_body,
		] );
	}

	// Build a readable error message from Sender
	$public_error = '';
	if ( is_array( $resp_body ) ) {
		if ( ! empty( $resp_body['message'] ) ) {
			$public_error = $resp_body['message'];
		} elseif ( ! empty( $resp_body['errors'] ) ) {
			$error_list = [];
			foreach ( $resp_body['errors'] as $field => $msg ) {
				$error_list[] = $field . ': ' . implode(', ', (array) $msg);
			}
			$public_error = implode(' | ', $error_list);
		}
	}

	if ( $public_error === '' ) {
		$public_error = 'Unknown error.';
	}

	wp_send_json_error( [
		'message'   => 'Subscription failed: ' . $public_error,
		'debug'     => [
			'status' => $status,
			'body'   => $resp_body,   // safe, contains no API key
		],
	] );

}

/**
 * Make the State / County field optional for ALL countries
 * in both billing and shipping addresses (classic + Blocks).
 */
add_filter( 'woocommerce_default_address_fields', function( $fields ) {

	if ( isset( $fields['state'] ) ) {
		$fields['state']['required'] = false;
	}

	return $fields;
}, 20 );
