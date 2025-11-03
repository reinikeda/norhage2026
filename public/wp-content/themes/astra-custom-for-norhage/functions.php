<?php
/**
 * Astra Custom for Norhage Theme functions and definitions
 *
 * @package Astra Custom for Norhage
 * @since 1.0.0
 */

define( 'CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION', '1.0.0' );

/**
 * Register Primary & Secondary Menu Locations
 */
function norhage_register_menus() {
    register_nav_menus( array(
        'primary'   => __( 'Primary Menu',   'your-textdomain' ),
        'secondary' => __( 'Secondary Menu', 'your-textdomain' ),
    ) );
}
add_action( 'after_setup_theme', 'norhage_register_menus' );

// Created services post type
function register_services_post_type() {
    $labels = array(
        'name'               => 'Services',
        'singular_name'      => 'Service',
        'menu_name'          => 'Services',
        'name_admin_bar'     => 'Service',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Service',
        'new_item'           => 'New Service',
        'edit_item'          => 'Edit Service',
        'view_item'          => 'View Service',
        'all_items'          => 'All Services',
        'search_items'       => 'Search Services',
        'not_found'          => 'No services found.',
        'not_found_in_trash' => 'No services found in Trash.',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => true,
        'rewrite'            => array('slug' => 'services'),
        'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
        'show_in_rest'       => true, // Enables Gutenberg
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-admin-tools',
    );

    register_post_type('service', $args);
}
add_action('init', 'register_services_post_type');

function norhage_enqueue_assets() {
    // Parent + custom styles
    wp_enqueue_style(
        'astra-custom-for-norhage-theme-css',
        get_stylesheet_directory_uri() . '/style.css',
        array('astra-theme-css'),
        CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION
    );
    // Load product page styles
    wp_enqueue_style(
        'norhage-custom-style',
        get_stylesheet_directory_uri() . '/assets/css/product-page.css',
        array(),
        CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION
    );

    // Dark-mode toggle logic
    wp_enqueue_script(
        'theme-toggle',
        get_stylesheet_directory_uri() . '/assets/js/theme-toggle.js',
        [],
        null,
        true // load in footer
    );

    // Dark-mode stylesheet
    wp_enqueue_style(
        'norhage-dark-mode-css',
        get_stylesheet_directory_uri() . '/assets/css/dark-mode.css',
        array(),
        CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION
    );

    // Custom JS (your site-wide script)
    wp_enqueue_script(
        'norhage-custom-js',
        get_stylesheet_directory_uri() . '/assets/js/script.js',
        array(),
        CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'norhage_enqueue_assets', 15);

// Always show the native page/post title (H1)
add_filter( 'astra_the_title_enabled', '__return_true', 999 );

// -----------------------------------------------------------------------------
// Subcategory grid (kept, still useful on category pages)
// -----------------------------------------------------------------------------

add_action('woocommerce_before_shop_loop', 'show_subcategories_grid', 10);
function show_subcategories_grid() {
    if (!is_product_category()) return;

    $category = get_queried_object();

    $args = array(
        'taxonomy'     => 'product_cat',
        'child_of'     => $category->term_id,
        'hide_empty'   => false,
        'parent'       => $category->term_id,
    );

    $subcategories = get_terms($args);

    if (!empty($subcategories)) {
        echo '<div class="subcategory-grid">';
        echo '<h2 class="subcategory-title">Shop by Category</h2>';
        echo '<div class="subcategory-items">';

        foreach ($subcategories as $subcategory) {
            $thumbnail_id = get_term_meta($subcategory->term_id, 'thumbnail_id', true);
            $image_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : wc_placeholder_img_src();

            echo '<div class="subcategory-item">';
            echo '<a href="' . get_term_link($subcategory) . '">';
            echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($subcategory->name) . '" />';
            echo '<span>' . esc_html($subcategory->name) . '</span>';
            echo '</a></div>';
        }

        echo '</div></div>';
    }
}

// -----------------------------------------------------------------------------
// Custom Astra header override
// -----------------------------------------------------------------------------

add_action('init', function () {
    remove_all_actions('astra_header');
});

add_action('wp', function () {
    add_action('astra_header', 'custom_norhage_header');
});

function custom_norhage_header() {
    ?>
    <div class="custom-header-wrapper">
        <!-- Secondary Header -->
        <div class="custom-header-secondary">
            <nav class="secondary-menu">
                <?php
                wp_nav_menu( array(
                    'theme_location' => 'secondary',
                    'container'      => false,
                    'menu_class'     => 'secondary-menu-list',
                    'fallback_cb'    => false,
                    'menu'           => 'Info Menu',
                ) );
                ?>
            </nav>
            <div class="secondary-contact">
                <a href="tel:+4917665106609">📞 +49 176 65 10 6609</a>
                <a href="<?php echo esc_url(home_url('frequently-asked-questions-faq/')); ?>">FAQ</a>
            </div>
        </div>

        <!-- Top Header -->
        <div class="custom-header-top">
            <div class="custom-logo">
                <a href="<?php echo esc_url(home_url('/')); ?>">
                    <img
                        src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/header-1920.jpg' ); ?>"
                        srcset="
                        <?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/header-768.jpg' ); ?> 768w,
                        <?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/header-1280.jpg' ); ?> 1280w,
                        <?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/header-1920.jpg' ); ?> 1920w,
                        <?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/header-2560.jpg' ); ?> 2560w
                        "
                        sizes="100vw"
                        width="1920" height="1080"
                        alt="<?php echo esc_attr( get_bloginfo('name') ); ?>"
                        class="site-logo"
                        loading="eager"
                        fetchpriority="high"
                        decoding="async" />
                </a>
            </div>

            <div class="custom-header-right">
                <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">
                    <?php esc_html_e('Sign in / My Account', 'your-textdomain'); ?>
                </a>

                <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="cart-icon">
                    <img
                        src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/shopping-cart.png' ); ?>"
                        alt="Cart"
                        class="cart-image" />
                    <span class="cart-count">
                        <?php echo WC()->cart ? WC()->cart->get_cart_contents_count() : '0'; ?>
                    </span>
                </a>

                <button
                id="theme-toggle"
                class="theme-toggle"
                type="button"
                aria-label="Switch to dark mode"
                data-dark-css="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/css/dark-mode.css' ); ?>"
                data-sun-icon="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/sun.png' ); ?>"
                data-moon-icon="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/moon.png' ); ?>">
                    <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/moon.png' ); ?>" alt="Toggle theme" />
                </button>
            </div>
        </div>

		<!-- Bottom Header -->
        <div class="custom-header-bottom">
            <div class="menu-search">
                <div class="nrh-live-search">
                    <form method="get" class="header-search" action="<?php echo esc_url(home_url('/')); ?>">
                        <input type="search" id="nrh-search-input" name="s" placeholder="Search products…" autocomplete="off" />
                        <input type="hidden" name="post_type" value="product" />
                        <button type="submit" class="search-button">🔍</button>
                    </form>
                    <ul id="nrh-search-results" class="nrh-live-search-results"></ul>
                </div>
                <button class="menu-toggle" aria-label="Toggle menu" aria-expanded="false">☰</button>
            </div>
            <ul class="custom-menu">
              <?php
              wp_nav_menu(array(
                  'theme_location' => 'primary',
                  'container'      => false,
                  'items_wrap'     => '%3$s',
                  'fallback_cb'    => false
              ));
              ?>
            </ul>
        </div>

        <!-- Ticker Line Output -->
        <?php
        if (function_exists('rtl_display_ticker')) {
            rtl_display_ticker();
        }
        ?>
    </div>
	
    <script>
    document.addEventListener("DOMContentLoaded", function(){
      const menuToggle  = document.querySelector(".menu-toggle");
      const menu        = document.querySelector(".custom-menu");
      menuToggle?.addEventListener("click", () => {
        const isOpen = menu.classList.toggle("active");
        menuToggle.setAttribute("aria-expanded", isOpen);
      });
    });
    </script>
    <?php
}

// -----------------------------------------------------------------------------
// Include custom feature files
// -----------------------------------------------------------------------------

require_once get_stylesheet_directory() . '/inc/search.php';
require_once get_stylesheet_directory() . '/inc/meta-boxes.php';
require_once get_stylesheet_directory() . '/inc/product-customize.php';
require_once get_stylesheet_directory() . '/inc/bundle-box.php';
require_once get_stylesheet_directory() . '/inc/sale-category-sync.php';
require_once get_stylesheet_directory() . '/inc/basket-customize.php';

// -----------------------------------------------------------------------------
// Secondary product title
// -----------------------------------------------------------------------------

add_action('edit_form_after_title', function($post){
    if ($post->post_type !== 'product') return;

    $value = get_post_meta($post->ID, '_secondary_product_title', true);
    wp_nonce_field('secondary_product_title_nonce', 'secondary_product_title_nonce');

    echo '<div style="margin:12px 0;padding:10px 12px;border:1px solid #dcdcde;background:#fff;border-radius:4px;">';
    echo '<label for="secondary_product_title" style="font-weight:600;display:block;margin-bottom:6px;">Secondary product title</label>';
    echo '<input type="text" id="secondary_product_title" name="secondary_product_title" value="' . esc_attr($value) . '" class="widefat" />';
    echo '</div>';
});

add_action('save_post_product', function($post_id){
    if (!isset($_POST['secondary_product_title_nonce']) || !wp_verify_nonce($_POST['secondary_product_title_nonce'], 'secondary_product_title_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $val = isset($_POST['secondary_product_title']) ? sanitize_text_field($_POST['secondary_product_title']) : '';
    update_post_meta($post_id, '_secondary_product_title', $val);
});

// Catalog: print subtitle directly under the product title (Astra hook)
add_action('astra_woo_shop_title_after', function () {
    $secondary = get_post_meta(get_the_ID(), '_secondary_product_title', true);
    if ($secondary) {
        echo '<h2 class="product-secondary-title">' . esc_html($secondary) . '</h2>';
    }
}, 10);

// Single product: print subtitle right after H1 (Astra hook)
add_action('astra_woo_single_title_after', function () {
    $secondary = get_post_meta(get_the_ID(), '_secondary_product_title', true);
    if ($secondary) {
        echo '<h2 class="product-secondary-title">' . esc_html($secondary) . '</h2>';
    }
}, 10);

function nhg_output_secondary_title_single() {
    global $product;
    if (!$product) return;

    $secondary = get_post_meta($product->get_id(), '_secondary_product_title', true);
    if ($secondary) {
        echo '<h2 class="product-secondary-title">' . esc_html($secondary) . '</h2>';
    }
}

// -----------------------------------------------------------------------------
// Blog category navigation
// -----------------------------------------------------------------------------

function norhage_blog_category_nav() {
    if (is_home() || is_category()) {
        $current_cat_id = get_queried_object_id();

        echo '<div class="blog-category-nav">';
        echo '<ul>';

        // "All" link
        $all_class = is_home() ? 'active' : '';
        echo '<li><a class="' . $all_class . '" href="' . esc_url(get_permalink(get_option('page_for_posts'))) . '">All</a></li>';

        // Get all categories (including subcategories)
        $categories = get_categories(array(
            'orderby' => 'name',
            'hide_empty' => true,
        ));

        foreach ($categories as $category) {
            $active = ($current_cat_id === $category->term_id) ? 'active' : '';
            echo '<li><a class="' . $active . '" href="' . esc_url(get_category_link($category->term_id)) . '">' . esc_html($category->name) . '</a></li>';
        }

        echo '</ul>';
        echo '</div>';
    }
}
add_action('astra_primary_content_top', 'norhage_blog_category_nav');

// -----------------------------------------------------------------------------
// Misc
// -----------------------------------------------------------------------------

// Remove the default archive title markup from Astra
add_filter('astra_the_title_enabled', '__return_false');

// enqueue category toggle
function astra_child_enqueue_scripts() {
    wp_enqueue_script(
        'category-toggle',
        get_stylesheet_directory_uri() . '/assets/js/category-toggle.js',
        array('jquery'),
        '1.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'astra_child_enqueue_scripts');

// remove uncategorized from category list
add_filter('woocommerce_product_categories_widget_args', 'hide_uncategorized_category');
function hide_uncategorized_category($args) {
    $uncategorized_id = get_option('default_product_cat');
    $args['exclude'] = array($uncategorized_id);
    return $args;
}
