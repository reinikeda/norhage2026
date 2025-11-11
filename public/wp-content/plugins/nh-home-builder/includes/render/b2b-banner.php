<?php
// B2B Banner — single-logo version (button is the only link)
if (!defined('ABSPATH')) exit;

wp_enqueue_style('nhhb-b2b');

/**
 * Expected $data:
 *  - h2, h3, btn_text, btn_url, logo
 * Back-compat fallbacks: logo_d, logo_m, legacy logo
 */
$defaults = [
  'h2'       => __('For Business Customers', 'nhhb'),
  'h3'       => __('Exclusive pricing and services for B2B partners.', 'nhhb'),
  'btn_text' => __('Learn more', 'nhhb'),
  'btn_url'  => '',
  'logo'     => 0,     // new single-asset key
  'logo_d'   => 0,     // legacy
  'logo_m'   => 0,     // legacy
];
$data = is_array($data ?? null) ? array_merge($defaults, $data) : $defaults;

/* ---- Backwards compatibility (prefer the new single 'logo') ---- */
if (empty($data['logo'])) {
  if (!empty($data['logo_m'])) {
    $data['logo'] = absint($data['logo_m']);
  } elseif (!empty($data['logo_d'])) {
    $data['logo'] = absint($data['logo_d']);
  } elseif (!empty($data['logo'])) {
    $data['logo'] = absint($data['logo']);
  }
}

/* Build logo HTML once (WP adds srcset/sizes for us) */
$logo_html = '';
if (!empty($data['logo'])) {
  $logo_html = wp_get_attachment_image(
    (int)$data['logo'],
    'medium',
    false,
    [
      'class' => 'nhhb-b2b-logo-img',
      'loading' => 'lazy',
      'decoding' => 'async',
      'alt' => '',                 // decorative; set text if you want it announced
      'aria-hidden' => 'true',
    ]
  );
}
?>
<section class="nhhb-b2b-wrap">
  <div class="nhhb-b2b">
    <?php if ($logo_html): ?>
      <span class="nhhb-b2b-logo"><?php echo $logo_html; ?></span>
    <?php endif; ?>

    <span class="nhhb-b2b-content">
      <?php if (!empty($data['h2'])): ?>
        <span class="nhhb-b2b-title"><?php echo esc_html($data['h2']); ?></span>
      <?php endif; ?>

      <?php if (!empty($data['h3'])): ?>
        <span class="nhhb-b2b-text"><?php echo esc_html($data['h3']); ?></span>
      <?php endif; ?>

      <?php if (!empty($data['btn_url']) && !empty($data['btn_text'])): ?>
        <a class="nhhb-b2b-cta" href="<?php echo esc_url($data['btn_url']); ?>" target="_blank" rel="noopener">
          <?php echo esc_html($data['btn_text']); ?>
        </a>
      <?php endif; ?>
    </span>
  </div>
</section>
