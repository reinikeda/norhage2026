<?php
// Newsletter / Subscribe section
if (!defined('ABSPATH')) exit;

wp_enqueue_style('nhhb-newsletter'); // registered in front_assets()

// Visible texts: ALWAYS from translations (PO), not from saved data.
$title       = __('Don’t Miss Out Latest Trends & Offers', 'nhhb');
$text        = __('Register to receive news about the latest offers & discount codes', 'nhhb');
$placeholder = __('Enter your email', 'nhhb');
$btn_text    = __('Subscribe', 'nhhb');

// Only technical settings come from $data (per-site config)
$action       = isset($data['action'])       ? esc_url($data['action'])                   : '';
$method       = isset($data['method'])       ? strtoupper(sanitize_text_field($data['method'])) : 'POST';
$consent_text = isset($data['consent_text']) ? sanitize_text_field($data['consent_text']) : '';

// Fallback to POST if something odd gets saved
if (!in_array($method, ['GET','POST'], true)) {
    $method = 'POST';
}
?>
<section class="nhhb-newsletter" data-nhhb-newsletter>
  <div class="nhhb-nl-inner">
    <div class="nhhb-nl-copy">
      <?php if ($title): ?><h2 class="nhhb-nl-title"><?php echo esc_html($title); ?></h2><?php endif; ?>
      <?php if ($text):  ?><p class="nhhb-nl-text"><?php echo esc_html($text); ?></p><?php endif; ?>
    </div>

    <form class="nhhb-nl-form"
          action="<?php echo $action ? esc_url($action) : '#'; ?>"
          method="<?php echo esc_attr($method); ?>"
          <?php echo $action ? '' : 'onsubmit="return false"'; ?>>

      <label class="nhhb-nl-field">
        <input type="email"
               name="email"
               autocomplete="email"
               required
               placeholder="<?php echo esc_attr($placeholder); ?>"
               aria-label="<?php esc_attr_e('Email address','nhhb'); ?>">
      </label>

      <button class="nhhb-nl-btn" type="<?php echo $action ? 'submit' : 'button'; ?>">
        <?php echo esc_html($btn_text); ?>
      </button>

      <?php if ($consent_text): ?>
        <p class="nhhb-nl-consent"><?php echo esc_html($consent_text); ?></p>
      <?php endif; ?>

      <!-- simple honeypot -->
      <input type="text" name="nhhb_hp" value="" style="display:none" tabindex="-1" autocomplete="off">
    </form>
  </div>
</section>
