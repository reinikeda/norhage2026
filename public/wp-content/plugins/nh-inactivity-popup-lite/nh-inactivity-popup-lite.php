<?php
/**
 * Plugin Name: Inactivity Popup (Lite)
 * Description: Shows a simple popup after 10s of user inactivity (no click/scroll). Once per session. No cookies, no consent data.
 * Version: 1.2.0
 * Author: Daiva Reinike
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

class NH_Inactivity_Popup_Lite {
    private $handle_css = 'nh-iplite-css';
    private $handle_js  = 'nh-iplite-js';

    // ---- Default popup content (edit these or override via filters) ----
    private $defaults = [
        'title'      => 'Need some help?',
        'text'       => 'Not sure which product fits your project? We’ll guide you — no pressure.',
        'btn_text'   => 'Contact us',
        'btn_url'    => '',
        'image_url'  => '',            // optional override; otherwise plugin assets are used
        // FAQ link support
        'faq_text'   => 'Browse FAQs',
        'faq_url'    => '',            // leave empty; we’ll set a sensible default
        // Delay in milliseconds of continuous inactivity
        'delay_ms'   => 20000,
        // Disable on cart/checkout by default (you can change this below)
        'disable_on_cart_checkout' => true,
    ];

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_footer',          [$this, 'render_markup']);
    }

    public function enqueue() {
        $cfg = $this->get_config();

        // CSS
        wp_register_style($this->handle_css, plugins_url('assets/popup.css', __FILE__), [], '1.0.0');
        wp_enqueue_style($this->handle_css);

        // JS
        wp_register_script($this->handle_js, plugins_url('assets/popup.js', __FILE__), [], '1.0.0', true);

        $payload = [
            'delay'          => (int) $cfg['delay_ms'],
            'selectors'      => [
                'container' => '#nh-iplite',
                'overlay'   => '#nh-iplite-overlay',
                'close'     => '[data-nh-close]',
            ],
            // one-per-session; no cookies or consent data
            'oncePerSession' => true,
        ];

        wp_localize_script($this->handle_js, 'NH_IPLITE', $payload);
        wp_enqueue_script($this->handle_js);
    }

    public function render_markup() {
        $cfg = $this->get_config();

        // Contact URL fallback
        if (empty($cfg['btn_url'])) {
            $page = get_page_by_path('contact-us');
            if (!$page) $page = get_page_by_path('contact');
            if ($page) $cfg['btn_url'] = get_permalink($page->ID);
        }

        // FAQ URL fallback (tries common slugs, incl. LT "duk")
        if (empty($cfg['faq_url'])) {
            $faq_slugs = ['faq','faqs','frequently-asked-questions','duk','duk-faq','frequently-asked-questions-faq'];
            foreach ($faq_slugs as $slug) {
                $p = get_page_by_path($slug);
                if ($p) { $cfg['faq_url'] = get_permalink($p->ID); break; }
            }
        }

        // Optionally skip on cart/checkout pages
        if ($cfg['disable_on_cart_checkout']) {
            if (function_exists('is_cart') && is_cart()) return;
            if (function_exists('is_checkout') && is_checkout()) return;
        }

        $title     = esc_html($cfg['title']);
        $text      = esc_html($cfg['text']);
        $btn_text  = esc_html($cfg['btn_text']);
        $btn_url   = esc_url($cfg['btn_url']);
        $image     = esc_url($cfg['image_url']); // fallback for <img src>
        $faq_text  = esc_html($cfg['faq_text']);
        $faq_url   = esc_url($cfg['faq_url']);

        // Build optional <source> list from plugin assets (help.avif/webp/jpg)
        $asset_dir = plugin_dir_path(__FILE__) . 'assets/';
        $asset_url = plugins_url('assets/', __FILE__);
        $sources   = [];

        if (file_exists($asset_dir . 'help.avif')) $sources[] = ['url' => $asset_url . 'help.avif', 'type' => 'image/avif'];
        if (file_exists($asset_dir . 'help.webp')) $sources[] = ['url' => $asset_url . 'help.webp', 'type' => 'image/webp'];
        if (file_exists($asset_dir . 'help.jpg'))  $sources[] = ['url' => $asset_url . 'help.jpg',  'type' => 'image/jpeg'];

        ?>
        <div id="nh-iplite-overlay" class="nh-hidden" aria-hidden="true"></div>
        <div id="nh-iplite" class="nh-hidden" role="dialog" aria-modal="true" aria-labelledby="nh-iplite-title" aria-describedby="nh-iplite-desc" tabindex="-1">
          <div class="nh-card">
            <button class="nh-close" data-nh-close aria-label="<?php echo esc_attr__('Close popup', 'nh-iplite'); ?>">x</button>

            <div class="nh-grid">
              <div class="nh-media">
                <picture>
                  <?php foreach ($sources as $s): ?>
                    <source srcset="<?php echo esc_url($s['url']); ?>" type="<?php echo esc_attr($s['type']); ?>">
                  <?php endforeach; ?>
                  <img
                    src="<?php echo $image; ?>"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    width="600" height="450"
                  />
                </picture>
              </div>

              <div class="nh-content">
                <h2 id="nh-iplite-title"><?php echo $title; ?></h2>
                <p id="nh-iplite-desc"><?php echo $text; ?></p>

                <div class="nh-actions">
                  <a class="nh-btn" href="<?php echo $btn_url; ?>">
                    <?php echo $btn_text; ?>
                  </a>

                  <?php if (!empty($faq_url)) : ?>
                    <a class="nh-link" href="<?php echo $faq_url; ?>" aria-label="<?php echo esc_attr__('Browse frequently asked questions', 'nh-iplite'); ?>">
                      <?php echo $faq_text; ?>
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php
    }

    private function get_config() {
        $cfg = apply_filters('nh_iplite_config', $this->defaults);

        // Ensure keys exist
        $keys = ['title','text','btn_text','btn_url','image_url','faq_text','faq_url','delay_ms','disable_on_cart_checkout'];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $cfg)) $cfg[$k] = $this->defaults[$k];
        }

        // Image default (prefer AVIF if present, else JPEG)
        if (empty($cfg['image_url'])) {
            $avif = plugin_dir_path(__FILE__) . 'assets/help.avif';
            if (file_exists($avif)) {
                $cfg['image_url'] = plugins_url('assets/help.avif', __FILE__);
            } else {
                $cfg['image_url'] = plugins_url('assets/help.jpg', __FILE__); // fallback
            }
        }

        // Preferred FAQ path if not provided
        if (empty($cfg['faq_url'])) {
            $cfg['faq_url'] = site_url('/frequently-asked-questions-faq/');
        }

        return $cfg;
    }
}

new NH_Inactivity_Popup_Lite();
