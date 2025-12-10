<?php
/**
 * Plugin Name: Inactivity Popup (Lite)
 * Description: Shows a simple popup after user inactivity. Once per session. No cookies, no consent data.
 * Version: 1.2.1
 * Author: Daiva Reinike
 * License: GPL-2.0-or-later
 * Text Domain: nh-iplite
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

class NH_Inactivity_Popup_Lite {
    private $handle_css = 'nh-iplite-css';
    private $handle_js  = 'nh-iplite-js';
    private $textdomain = 'nh-iplite';

    private $defaults = [
        'title'      => 'Need some help?',
        'text'       => 'Not sure which product fits your project? We’ll guide you — no pressure.',
        'btn_text'   => 'Contact us',
        'btn_url'    => '',
        'image_url'  => '',    // now unused; we force help.webp
        'faq_text'   => 'Browse FAQs',
        'faq_url'    => '',
        'delay_ms'   => 20000,
        'disable_on_cart_checkout' => true,
    ];

    public function __construct() {
        add_action('plugins_loaded',      [ $this, 'load_textdomain' ]);
        add_action('wp_enqueue_scripts',  [ $this, 'enqueue' ]);
        add_action('wp_footer',           [ $this, 'render_markup' ]);
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            $this->textdomain,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    public function enqueue() {
        $cfg = $this->get_config();

        wp_register_style(
            $this->handle_css,
            plugins_url('assets/popup.css', __FILE__),
            [],
            '1.0.0'
        );
        wp_enqueue_style($this->handle_css);

        wp_register_script(
            $this->handle_js,
            plugins_url('assets/popup.js', __FILE__),
            [],
            '1.0.0',
            true
        );

        wp_localize_script($this->handle_js, 'NH_IPLITE', [
            'delay' => (int) $cfg['delay_ms'],
            'selectors' => [
                'container' => '#nh-iplite',
                'overlay'   => '#nh-iplite-overlay',
                'close'     => '[data-nh-close]',
            ],
            'oncePerSession' => true
        ]);

        wp_enqueue_script($this->handle_js);
    }

    public function render_markup() {
        $cfg = $this->get_config();

        if (empty($cfg['btn_url'])) {
            $p = get_page_by_path('contact-us') ?: get_page_by_path('contact');
            if ($p) $cfg['btn_url'] = get_permalink($p->ID);
        }

        if (empty($cfg['faq_url'])) {
            foreach (['faq','faqs','duk'] as $slug) {
                $p = get_page_by_path($slug);
                if ($p) { $cfg['faq_url'] = get_permalink($p->ID); break; }
            }
        }

        if ($cfg['disable_on_cart_checkout']) {
            if (function_exists('is_cart') && is_cart()) return;
            if (function_exists('is_checkout') && is_checkout()) return;
        }

        $title    = esc_html($cfg['title']);
        $text     = esc_html($cfg['text']);
        $btn_text = esc_html($cfg['btn_text']);
        $btn_url  = esc_url($cfg['btn_url']);
        $faq_text = esc_html($cfg['faq_text']);
        $faq_url  = esc_url($cfg['faq_url']);

        // Built-in single image path
        $image = plugins_url('assets/help.webp', __FILE__);
        ?>

        <div id="nh-iplite-overlay" class="nh-hidden" aria-hidden="true"></div>
        <div id="nh-iplite" class="nh-hidden" role="dialog" aria-modal="true" aria-labelledby="nh-iplite-title" aria-describedby="nh-iplite-desc" tabindex="-1">
          <div class="nh-card">
            <button class="nh-close" data-nh-close aria-label="<?php echo esc_attr__('Close popup', 'nh-iplite'); ?>">x</button>

            <div class="nh-grid">

              <div class="nh-media">
                <img
                    src="<?php echo esc_url($image); ?>"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    width="600"
                    height="450"
                />
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

        foreach ($this->defaults as $k => $v) {
            if (!array_key_exists($k, $cfg)) {
                $cfg[$k] = $v;
            }
        }

        // Strings to translate
        foreach (['title', 'text', 'btn_text', 'faq_text'] as $k) {
            $cfg[$k] = __($cfg[$k], $this->textdomain);
        }

        return $cfg;
    }
}

new NH_Inactivity_Popup_Lite();
