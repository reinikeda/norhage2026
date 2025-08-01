<?php
/**
 * Astra Custom for Norhage Theme functions and definitions
 *
 * @package Astra Custom for Norhage
 * @since 1.0.0
 */

define( 'CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION', '1.0.0' );

function child_enqueue_styles() {
	// Inherit Astra parent styles
	wp_enqueue_style(
		'astra-custom-for-norhage-theme-css',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'astra-theme-css' ),
		CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION
	);

	// Your custom CSS
	wp_enqueue_style(
		'norhage-custom-style',
		get_stylesheet_directory_uri() . '/assets/css/style.css',
		array(),
		CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION
	);

	// Your custom JS
	wp_enqueue_script(
		'norhage-custom-js',
		get_stylesheet_directory_uri() . '/assets/js/script.js',
		array(),
		CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );

//Enqueues custom dark mode CSS and JS files
function child_enqueue_dark_mode_assets() {
    wp_enqueue_style(
        'dark-mode-css',
        get_stylesheet_directory_uri() . '/assets/css/dark-mode.css',
        array(),
        null
    );

    wp_enqueue_script(
        'dark-mode-toggle-js',
        get_stylesheet_directory_uri() . '/assets/js/dark-mode.js',
        array(),
        null,
        true
    );
}
add_action('wp_enqueue_scripts', 'child_enqueue_dark_mode_assets');

// Remove all default Astra header actions
add_action('init', function () {
    remove_all_actions('astra_header');
});

// Register your custom header AFTER Astra is cleared
add_action('wp', function () {
    add_action('astra_header', 'custom_norhage_header');
});

function custom_norhage_header() {
    ?>
    <div class="custom-header-wrapper">
        <!-- Top Header -->
        <div class="custom-header-top">
            <div class="custom-logo">
                <a href="<?php echo esc_url(home_url('/')); ?>">
                    <?php bloginfo('name'); ?>
                </a>
            </div>
            <div class="custom-header-right">
                <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">
                    <?php esc_html_e('Sign in / My Account', 'your-textdomain'); ?>
                </a>
                <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="cart-icon">
                    🛒 <span class="cart-count">
                        <?php echo WC()->cart ? WC()->cart->get_cart_contents_count() : '0'; ?>
                    </span>
                </a>
                <button id="theme-toggle" aria-label="Toggle dark mode">🌙</button>
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
    </div>
	
    <script>
    document.addEventListener("DOMContentLoaded", function(){
      const themeToggle = document.getElementById("theme-toggle");
      const menuToggle  = document.querySelector(".menu-toggle");
      const menu        = document.querySelector(".custom-menu");

      // Dark mode
      themeToggle?.addEventListener("click", () => {
        document.body.classList.toggle("dark-mode");
        themeToggle.textContent = document.body.classList.contains("dark-mode") ? "☀️" : "🌙";
      });

      // Hamburger toggle
      menuToggle?.addEventListener("click", () => {
        const isOpen = menu.classList.toggle("active");
        menuToggle.setAttribute("aria-expanded", isOpen);
      });
    });
    </script>
    <?php
}

// Search bar
require_once get_stylesheet_directory() . '/inc/search.php';

//Short description display next to product name in catalog
add_action('woocommerce_shop_loop_item_title', 'add_secondary_product_line', 11);

function add_secondary_product_line() {
    global $product;
    echo '<div class="secondary-title">' . $product->get_short_description() . '</div>';
}

// Metaboxes (Downloads & Video)
require_once get_stylesheet_directory() . '/inc/meta-boxes.php';

// Front-end product customizations (swatches, bundles, etc)
require_once get_stylesheet_directory() . '/inc/product-customize.php';

// Show blog category links (including "All") with active highlighting
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

// Remove the default archive title markup from Astra
add_filter('astra_the_title_enabled', '__return_false');

