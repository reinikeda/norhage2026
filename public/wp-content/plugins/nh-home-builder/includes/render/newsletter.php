<?php
// Newsletter / Subscribe section
if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('nhhb-newsletter');
wp_enqueue_script('nh-sender-newsletter');

// Visible texts: ALWAYS from translations (PO), not from saved data.
$kicker      = __('✉ Stay in the loop', 'nhhb');
$title       = __('Don’t Miss Out Latest Trends & Offers', 'nhhb');
$text        = __('Register to receive news about the latest offers & discount codes', 'nhhb');
$placeholder = __('Enter your email', 'nhhb');
$btn_text    = __('Subscribe', 'nhhb');

$consent_text = isset($data['consent_text']) ? sanitize_text_field($data['consent_text']) : '';
$uid = 'nhhb-nl-' . wp_unique_id();
?>
<section class="nhhb-newsletter" data-nhhb-newsletter aria-labelledby="<?php echo esc_attr($uid); ?>">
  <div class="nhhb-nl-inner">
    <div class="nhhb-nl-copy">
      <span class="nhhb-nl-kicker"><?php echo esc_html($kicker); ?></span>
      <h2 id="<?php echo esc_attr($uid); ?>" class="nhhb-nl-title"><?php echo esc_html($title); ?></h2>
      <p class="nhhb-nl-text"><?php echo esc_html($text); ?></p>
    </div>

    <form class="nhhb-nl-form" method="post" action="#" novalidate>
      <div class="nhhb-nl-form-row">
        <label class="nhhb-nl-field">
          <span class="screen-reader-text"><?php esc_html_e('Email address', 'nhhb'); ?></span>
          <input type="email"
                 name="email"
                 autocomplete="email"
                 inputmode="email"
                 required
                 placeholder="<?php echo esc_attr($placeholder); ?>"
                 aria-label="<?php esc_attr_e('Email address', 'nhhb'); ?>">
        </label>
        <button class="nhhb-nl-btn" type="submit">
          <?php echo esc_html($btn_text); ?>
        </button>
      </div>

      <?php if ($consent_text) : ?>
        <p class="nhhb-nl-consent"><?php echo esc_html($consent_text); ?></p>
      <?php endif; ?>

      <input type="text" name="nhhb_hp" value="" tabindex="-1" autocomplete="off" class="nhhb-hp" aria-hidden="true">
    </form>
  </div>
</section>
