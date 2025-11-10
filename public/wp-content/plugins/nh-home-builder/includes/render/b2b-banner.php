<?php
// B2B Banner – logo left, text + button right (only button is a link)
if (!defined('ABSPATH')) exit;

wp_enqueue_style('nhhb-b2b');

$defaults = [
  'h2'       => __('For Business Customers', 'nhhb'),
  'h3'       => __('Exclusive pricing and services for B2B partners.', 'nhhb'),
  'btn_text' => __('Learn more', 'nhhb'),
  'btn_url'  => '',
  'logo_d'   => 0, // desktop logo (white)
  'logo_m'   => 0, // mobile logo (blue)
  'logo'     => 0, // legacy fallback
];
$data = is_array($data ?? null) ? array_merge($defaults, $data) : $defaults;

// Fallback to legacy single logo
if (empty($data['logo_d']) && !empty($data['logo'])) {
  $data['logo_d'] = absint($data['logo']);
}

$logo_d = $data['logo_d'] ? wp_get_attachment_image_url((int)$data['logo_d'], 'medium') : '';
$logo_m = $data['logo_m'] ? wp_get_attachment_image_url((int)$data['logo_m'], 'medium') : '';
?>
<section class="nhhb-b2b-wrap">
  <div class="nhhb-b2b">
    <?php if ($logo_d || $logo_m): ?>
      <span class="nhhb-b2b-logo">
        <?php if ($logo_d): ?>
          <img class="nhhb-b2b-logo--desktop" src="<?php echo esc_url($logo_d); ?>" alt="" loading="lazy">
        <?php endif; ?>
        <?php if ($logo_m): ?>
          <img class="nhhb-b2b-logo--mobile" src="<?php echo esc_url($logo_m); ?>" alt="" loading="lazy">
        <?php endif; ?>
      </span>
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
