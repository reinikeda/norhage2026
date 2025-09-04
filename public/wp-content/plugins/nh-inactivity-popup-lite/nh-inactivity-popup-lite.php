<?php
/**
 * Plugin Name: Inactivity Popup (Lite)
 * Description: Shows a simple popup after 10s of user inactivity (no click/scroll). Once per session. No cookies, no consent data.
 * Version: 1.0.0
 * Author: Daiva Reinike
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

class NH_Inactivity_Popup_Lite {
    private $handle_css = 'nh-iplite-css';
    private $handle_js  = 'nh-iplite-js';

    // ---- Default popup content (edit these or override via filters) ----
    private $defaults = [
        'title'      => 'Need a hand?',
        'text'       => 'Get tips, offers, and help choosing the right product.',
        'btn_text'   => 'Contact us',
        'btn_url'    => '',
        'image_url' => '',
        // Delay in milliseconds of continuous inactivity
        'delay_ms'   => 10000,
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

        if (empty($cfg['btn_url'])) {
            $page = get_page_by_path( 'contact-us' );
            if ( $page ) {
                $cfg['btn_url'] = get_permalink( $page->ID );
            }
        }

        // Optionally skip on cart/checkout pages
        if ($cfg['disable_on_cart_checkout']) {
            if (function_exists('is_cart') && is_cart()) return;
            if (function_exists('is_checkout') && is_checkout()) return;
        }

        $title    = esc_html($cfg['title']);
        $text     = esc_html($cfg['text']);
        $btn_text = esc_html($cfg['btn_text']);
        $btn_url  = esc_url($cfg['btn_url']);
        $image    = esc_url($cfg['image_url']);

        ?>
        <div id="nh-iplite-overlay" class="nh-hidden" aria-hidden="true"></div>
        <div id="nh-iplite" class="nh-hidden" role="dialog" aria-modal="true" aria-labelledby="nh-iplite-title" aria-describedby="nh-iplite-desc" tabindex="-1">
          <div class="nh-card">
            <button class="nh-close" data-nh-close aria-label="<?php echo esc_attr__('Close popup', 'nh-iplite'); ?>">x</button>

            <div class="nh-grid">
              <div class="nh-media">
                <img src="<?php echo $image; ?>" alt="" loading="lazy" decoding="async" />
              </div>

              <div class="nh-content">
                <h2 id="nh-iplite-title"><?php echo $title; ?></h2>
                <p id="nh-iplite-desc"><?php echo $text; ?></p>
                <a class="nh-btn" href="<?php echo $btn_url; ?>">
                  <?php echo $btn_text; ?>
                </a>
              </div>
            </div>
          </div>
        </div>
        <?php
    }

    private function get_config() {
        $cfg = apply_filters('nh_iplite_config', $this->defaults);

        // Safety: ensure keys exist
        $keys = ['title','text','btn_text','btn_url','image_url','delay_ms','disable_on_cart_checkout'];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $cfg)) $cfg[$k] = $this->defaults[$k];
        }

        // ✅ Compute image URL at runtime if empty
        if (empty($cfg['image_url'])) {
            $cfg['image_url'] = plugins_url('assets/help.jpg', __FILE__);
        }

        return $cfg;
    }
}

new NH_Inactivity_Popup_Lite();
