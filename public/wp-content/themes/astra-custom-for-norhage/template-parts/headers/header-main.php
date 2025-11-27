<?php
/**
 * Main Header — two rows + ticker
 *  - Row 1: Logo + Primary Menu (desktop) / Logo + Icons (compact)
 *  - Row 2: Burger + Tools (Account, Cart, Theme, Search)
 *            + Desktop logo on second line (new)
 *  - Drawer: Off-canvas with ONLY the Primary Menu
 *  - Row 3: News ticker (optional)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$theme_uri   = get_stylesheet_directory_uri();
$account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
$cart_url    = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/');
$cart_count  = ( function_exists('WC') && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;

$is_logged_in  = is_user_logged_in();
$account_label = $is_logged_in ? esc_html__( 'My Account', 'nh-theme' ) : esc_html__( 'Sign in', 'nh-theme' );
$account_href  = $is_logged_in ? $account_url : wp_login_url( get_permalink() );
?>
<div class="nhhb-header-main">

  <!-- Row 1: Logo + Primary Menu (desktop) -->
  <div class="nhhb-row nhhb-row--top">
    <div class="nhhb-logo">
      <?php
      if ( has_custom_logo() ) {
        the_custom_logo();
      } else {
        echo '<a class="site-title" href="' . esc_url( home_url('/') ) . '">' . esc_html( get_bloginfo('name') ) . '</a>';
      }
      ?>
    </div>

    <nav class="nhhb-main-nav" aria-label="<?php esc_attr_e( 'Primary menu', 'nh-theme' ); ?>">
      <?php
      wp_nav_menu( [
        'theme_location' => 'primary',
        'container'      => false,
        'menu_class'     => 'primary-menu',
        'fallback_cb'    => false,
      ] );
      ?>
    </nav>

    <!-- Slot used in COMPACT mode to host the tools (icons) -->
    <div class="nhhb-tools-slot" aria-hidden="true"></div>
  </div>

  <!-- Row 2: Desktop logo (second line) + Burger + Tools (icons + search) -->
  <div class="nhhb-row nhhb-row--bottom">

    <!-- Desktop logo on second line (hidden on mobile/compact via CSS) -->
    <div class="nhhb-logo nhhb-logo--bottom-desktop">
      <?php
      if ( has_custom_logo() ) {
        the_custom_logo();
      } else {
        echo '<a class="site-title" href="' . esc_url( home_url('/') ) . '">' . esc_html( get_bloginfo('name') ) . '</a>';
      }
      ?>
    </div>

    <!-- Burger (used only in compact/mobile via CSS/JS) -->
    <div class="nhhb-compact-burger-wrap">
      <button
        class="nh-burger"
        type="button"
        aria-label="<?php esc_attr_e('Open menu','nh-theme'); ?>"
        aria-controls="nh-mobile-drawer"
        aria-expanded="false">
        <img
          class="nh-burger__icon"
          src="<?php echo esc_url( $theme_uri . '/assets/icons/hamburger-menu.svg' ); ?>"
          alt=""
          aria-hidden="true" />
      </button>
    </div>

    <div class="nhhb-tools">

      <!-- Account -->
      <a class="nh-account" href="<?php echo esc_url( $account_href ); ?>" title="<?php echo esc_attr( $account_label ); ?>">
        <svg class="nh-icon" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="8" r="4" stroke-width="2" fill="none"></circle>
          <path d="M4 20a8 8 0 0 1 16 0" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
        <span class="nh-account__text"><?php echo $account_label; ?></span>
      </a>

      <!-- Cart -->
      <a class="nh-cart" href="<?php echo esc_url( $cart_url ); ?>" aria-label="<?php echo esc_attr__( 'Cart', 'nh-theme' ); ?>">
        <span class="nh-cart-icon">
          <svg class="nh-icon" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
            <circle cx="9" cy="21" r="1" stroke-width="2" fill="none"></circle>
            <circle cx="19" cy="21" r="1" stroke-width="2" fill="none"></circle>
            <path d="M2 3h3l3.6 12.6a2 2 0 0 0 2 1.4h7.8a2  2 0 0 0 2-1.6l1.5-8.4H6" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"></path>
          </svg>
          <span class="nh-cart-badge" aria-hidden="true" data-count="<?php echo (int) $cart_count; ?>">
            <?php echo (int) $cart_count; ?>
          </span>
        </span>
        <span class="screen-reader-text"><?php esc_html_e( 'View cart', 'nh-theme' ); ?></span>
      </a>

      <!-- Theme toggle -->
      <button
        id="theme-toggle"
        class="theme-toggle"
        type="button"
        aria-label="<?php echo esc_attr__( 'Switch theme', 'nh-theme' ); ?>"
        data-sun-icon="<?php echo esc_url( $theme_uri . '/assets/icons/sun.svg' ); ?>"
        data-moon-icon="<?php echo esc_url( $theme_uri . '/assets/icons/moon.svg' ); ?>">
        <svg class="nh-icon" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
      </button>

      <!-- Live Search -->
      <div class="nh-live-search">
        <form class="nh-header-search nh-header-search--dark" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" role="search" autocomplete="off">
          <input
            type="search"
            id="nrh-search-input"
            name="s"
            placeholder="<?php echo esc_attr__( 'Search products…', 'nh-theme' ); ?>"
            aria-controls="nrh-search-results"
            aria-expanded="false"
            autocomplete="off" />
          <input type="hidden" name="post_type" value="product" />
          <button type="submit" aria-label="<?php echo esc_attr__( 'Search', 'nh-theme' ); ?>">
            <svg class="nh-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <circle cx="11" cy="11" r="7" stroke-width="2" fill="none"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65" stroke-width="2"></line>
            </svg>
          </button>
        </form>
        <ul id="nrh-search-results" class="nh-live-results" role="listbox" aria-label="<?php esc_attr_e('Search suggestions','nh-theme'); ?>"></ul>
      </div>

    </div>
  </div>

  <!-- Off-canvas drawer (ONLY Primary Menu) -->
  <div id="nh-mobile-drawer" class="nh-drawer" hidden aria-hidden="true">
    <button class="nh-drawer__scrim" tabindex="-1" aria-hidden="true"></button>

    <aside class="nh-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="nh-drawer-title">
      <header class="nh-drawer__header">
        <span id="nh-drawer-title" class="nh-drawer__title">
          <?php echo esc_html__('Menu', 'nh-theme'); ?>
        </span>
        <button class="nh-drawer__close" type="button" aria-label="<?php esc_attr_e('Close menu','nh-theme'); ?>">
          <span aria-hidden="true">&times;</span>
        </button>
      </header>

      <nav class="nh-drawer__nav" aria-label="<?php esc_attr_e('Primary menu','nh-theme'); ?>">
        <?php
        wp_nav_menu( [
          'theme_location' => 'primary',
          'container'      => false,
          'menu_class'     => 'drawer-menu',
          'fallback_cb'    => false,
        ] );
        ?>
      </nav>

    </aside>
  </div>

  <!-- Row 3: News Ticker -->
  <?php if ( function_exists( 'rtl_display_ticker' ) ) : ?>
    <div class="nh-ticker-wrap">
      <?php rtl_display_ticker(); ?>
    </div>
  <?php endif; ?>
</div>
