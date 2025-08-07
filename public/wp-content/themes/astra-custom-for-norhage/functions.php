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

    // Lottie runtime (needed by the toggle)
    wp_enqueue_script(
        'lottie-web',
        'https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.10.2/lottie.min.js',
        array(),
        null,
        true
    );

    // Dark-mode toggle logic (depends on Lottie)
    wp_enqueue_script(
        'norhage-dark-mode-js',
        get_stylesheet_directory_uri() . '/assets/js/dark-mode.js',
        array('lottie-web'),
        CHILD_THEME_ASTRA_CUSTOM_FOR_NORHAGE_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'norhage_enqueue_assets', 15);

// register sale slider
add_action('admin_menu', 'sale_slider_menu');
function sale_slider_menu() {
    add_menu_page(
        'Sale Slider',
        'Sale Slider',
        'manage_options',
        'sale-slider',
        'sale_slider_settings_page',
        'dashicons-images-alt2',
        20
    );
}

function sale_slider_settings_page() {
    if (isset($_POST['save_sale_slider'])) {
        update_option('sale_slider_data', $_POST['sale_slider_data']);
        echo '<div class="updated"><p>Slider saved!</p></div>';
    }

    $slides = get_option('sale_slider_data', []);
    ?>
    <div class="wrap">
        <h1>Sale Slider</h1>
        <form method="post">
            <table class="form-table">
                <tbody>
                <?php for ($i = 0; $i < 5; $i++):
                    $slide = $slides[$i] ?? ['image' => '', 'url' => '', 'start' => '', 'end' => ''];
                ?>
                    <tr>
                        <th colspan="2"><h2>Slide <?php echo $i + 1; ?></h2></th>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <label>Image URL</label><br>
                            <input type="text" name="sale_slider_data[<?php echo $i; ?>][image]" value="<?php echo esc_attr($slide['image']); ?>" style="width:100%;">
                            <br><small><a href="<?php echo admin_url('media-new.php'); ?>" target="_blank">Upload image</a></small>
                        </td>
                        <td>
                            <label>Link URL</label><br>
                            <input type="url" name="sale_slider_data[<?php echo $i; ?>][url]" value="<?php echo esc_attr($slide['url']); ?>" style="width:100%;"><br>
                            <label>Start Date</label><br>
                            <input type="date" name="sale_slider_data[<?php echo $i; ?>][start]" value="<?php echo esc_attr($slide['start']); ?>"><br>
                            <label>End Date</label><br>
                            <input type="date" name="sale_slider_data[<?php echo $i; ?>][end]" value="<?php echo esc_attr($slide['end']); ?>">
                        </td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
            <p><input type="submit" name="save_sale_slider" class="button button-primary" value="Save Slider"></p>
        </form>
    </div>
    <?php
}

// display slider on category page
add_action('woocommerce_before_shop_loop', 'show_global_sale_slider', 5);
function show_global_sale_slider() {
    if (!is_product_category()) return;

    $slides = get_option('sale_slider_data', []);
    $today = date('Y-m-d');

    // Filter slides by active date
    $active_slides = array_filter($slides, function($slide) use ($today) {
        return !empty($slide['image']) &&
               $today >= $slide['start'] &&
               $today <= $slide['end'];
    });

    if (empty($active_slides)) return;

    echo '<div class="sale-slider-container swiper"><div class="swiper-wrapper">';
    foreach ($active_slides as $slide) {
        echo '<div class="swiper-slide">';
        echo '<a href="' . esc_url($slide['url']) . '">';
        echo '<img src="' . esc_url($slide['image']) . '" alt="" />';
        echo '</a></div>';
    }
    echo '</div><div class="swiper-pagination"></div></div>';
}


// enqueue swiper
add_action('wp_enqueue_scripts', 'enqueue_sale_slider_assets');
function enqueue_sale_slider_assets() {
    if (!is_product_category()) return;

    wp_enqueue_style('swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css');
    wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], null, true);
    wp_add_inline_script('swiper-js', '
        document.addEventListener("DOMContentLoaded", function(){
            new Swiper(".sale-slider-container", {
                loop: true,
                autoplay: { delay: 4000 },
                slidesPerView: 1,
                pagination: { el: ".swiper-pagination", clickable: true },
                spaceBetween: 20
            });
        });
    ');
}

// categories listing
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
                    src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/logo.png' ); ?>" 
                    alt="<?php echo esc_attr( get_bloginfo('name') ); ?>" 
                    class="site-logo"
                    />
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
                <div id="theme-toggle"
                    class="theme-toggle"
                    data-lottie-path="<?php echo esc_attr( get_stylesheet_directory_uri() . '/assets/lottie/dark-mode-toggle.json' ); ?>">
                </div>
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
