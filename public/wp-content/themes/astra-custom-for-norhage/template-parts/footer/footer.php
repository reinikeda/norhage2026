<?php
/**
 * Norhage Footer (self-contained config with column headings)
 */
if (!defined('ABSPATH')) exit;

$year      = date('Y');
$site_name = get_bloginfo('name');

/* ====== Project-local config (edit here, now translatable) ====== */
$company = __('Tehi UG', 'nh-theme');
$address = __('Adolfstraße 1, Wiesbaden, 65185 HE, Germany', 'nh-theme');
$phone   = __('+49 176 65 10 6609', 'nh-theme');
$email   = __('info@norhage.de', 'nh-theme');

// Social URLs (leave empty string to hide an icon)
$social = [
  'facebook'  => __('https://www.facebook.com/yourpage', 'nh-theme'),
  'instagram' => __('https://www.instagram.com/yourpage', 'nh-theme'),
  'youtube'   => __('https://www.youtube.com/@yourchannel', 'nh-theme'),
  'linkedin'  => __('https://www.linkedin.com/company/yourcompany', 'nh-theme'),
];

// Optional: translatable B2B portal URL
$b2b_url = __('https://norhageindustri.lt/', 'nh-theme');

// Social label map (for aria-label)
$social_labels = [
  'facebook'  => __('Facebook', 'nh-theme'),
  'instagram' => __('Instagram', 'nh-theme'),
  'youtube'   => __('YouTube', 'nh-theme'),
  'linkedin'  => __('LinkedIn', 'nh-theme'),
];
/* ============================================== */

/**
 * Helper: render a footer menu block.
 * $locations can be a string (single location) or an array of fallbacks in priority order.
 */
function nh_footer_menu_block($title, $locations){
  if (is_string($locations)) {
    $locations = [$locations];
  } elseif (!is_array($locations)) {
    return;
  }

  $chosen = null;
  foreach ($locations as $loc) {
    if (has_nav_menu($loc)) { $chosen = $loc; break; }
  }
  if (!$chosen) return;

  echo '<nav class="nh-footer__nav" aria-label="' . esc_attr($title) . '">';
  echo '<h3 class="nh-footer__heading">' . esc_html($title) . '</h3>';
  wp_nav_menu([
    'theme_location' => $chosen,
    'container'      => false,
    'menu_class'     => 'nh-footer__menu',
    'depth'          => 1,
    'fallback_cb'    => '__return_empty_string',
  ]);
  echo '</nav>';
}
?>

<footer class="nh-footer" role="contentinfo">
  <div class="nh-footer__top">
    <!-- 1. Company / Contacts -->
    <section class="nh-footer__brand">
      <h3 class="nh-footer__heading"><?php echo esc_html__('Company Info', 'nh-theme'); ?></h3>
      <ul class="nh-footer__list">
        <li><strong><?php esc_html_e('Company:', 'nh-theme'); ?></strong> <?php echo esc_html($company); ?></li>
        <li><strong><?php esc_html_e('Address:', 'nh-theme'); ?></strong> <?php echo esc_html($address); ?></li>
        <li><strong><?php esc_html_e('Phone:', 'nh-theme'); ?></strong>
          <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a>
        </li>
        <li><strong><?php esc_html_e('Email:', 'nh-theme'); ?></strong>
          <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
        </li>
      </ul>

      <?php if (array_filter($social)): ?>
        <div class="nh-footer__social-wrap">
          <h4 class="nh-footer__subheading"><?php esc_html_e('Follow us', 'nh-theme'); ?></h4>
          <div class="nh-footer__social" aria-label="<?php esc_attr_e('Social media', 'nh-theme'); ?>">
            <?php foreach ($social as $key => $url):
              if (!$url) continue;
              $label = isset($social_labels[$key]) ? $social_labels[$key] : ucfirst($key);
              ?>
              <a
                class="nh-social__link nh-social__link--light"
                href="<?php echo esc_url($url); ?>"
                target="_blank"
                rel="noopener"
                aria-label="<?php echo esc_attr($label); ?>"
              >
                <img
                  loading="lazy"
                  src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/icons/' . $key . '.svg'); ?>"
                  alt=""
                  width="22"
                  height="22"
                >
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    </section>

    <!-- 2. Shop Menu -->
    <?php nh_footer_menu_block(__('Shop Categories', 'nh-theme'), ['footer_shop', 'primary']); ?>

    <!-- 3. Explore Menu -->
    <?php nh_footer_menu_block(__('Explore & Support', 'nh-theme'), ['footer_explore', 'footer']); ?>

    <!-- 4. Legal Menu -->
    <?php nh_footer_menu_block(__('Legal & Information', 'nh-theme'), ['footer_legal', 'footer']); ?>

    <!-- 5. Newsletter -->
    <section class="nh-footer__newsletter" aria-labelledby="nh-footer-newsletter-title">
      <h3 id="nh-footer-newsletter-title" class="nh-footer__heading"><?php esc_html_e('Stay in the Loop', 'nh-theme'); ?></h3>
      <p class="nh-footer__text"><?php esc_html_e('Get trends, offers and how-tos straight to your inbox.', 'nh-theme'); ?></p>

      <form class="nh-nl" action="#" method="post" onsubmit="return false" novalidate>
        <label class="screen-reader-text" for="nh-nl-email"><?php esc_html_e('Email address', 'nh-theme'); ?></label>
        <input
          id="nh-nl-email"
          class="nh-nl__input"
          type="email"
          name="email"
          placeholder="<?php esc_attr_e('Your email address', 'nh-theme'); ?>"
          autocomplete="email"
          required
        >
        <button class="nh-nl__btn" type="submit"><?php esc_html_e('Subscribe', 'nh-theme'); ?></button>
        <small class="nh-nl__hint">
          <?php esc_html_e('By subscribing you agree to our', 'nh-theme'); ?>
          <a href="<?php echo esc_url(get_privacy_policy_url()); ?>"><?php esc_html_e('Privacy Policy', 'nh-theme'); ?></a>.
        </small>
      </form>
    </section>
  </div>

  <!-- Bottom bar -->
  <div class="nh-footer__bottom">
    <div class="nh-footer__legal-left">© <?php echo esc_html($year . ' ' . $site_name); ?></div>

    <div class="nh-footer__legal-right">
      <a class="nh-footer__b2b" href="<?php echo esc_url($b2b_url); ?>">
        <?php esc_html_e('B2B Portal', 'nh-theme'); ?>
      </a>
      <?php if (has_nav_menu('footer_bottom')): ?>
        <nav class="nh-footer__legal" aria-label="<?php esc_attr_e('Legal bottom', 'nh-theme'); ?>">
          <?php
          wp_nav_menu([
            'theme_location' => 'footer_bottom',
            'container'      => false,
            'menu_class'     => 'nh-footer__bottom-menu',
            'depth'          => 1,
            'fallback_cb'    => '__return_empty_string',
          ]);
          ?>
        </nav>
      <?php endif; ?>
    </div>
  </div>

</footer>
