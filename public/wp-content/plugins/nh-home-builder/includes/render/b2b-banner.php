<?php
// B2B Banner – logo left, text + button right (only button is a link)
if (!defined('ABSPATH')) exit;

wp_enqueue_style('nhhb-b2b');

$defaults = [
  'h2'       => __('For Business Customers', 'nhhb'),
  'h3'       => __('Exclusive pricing and services for B2B partners.', 'nhhb'),
  'btn_text' => __('Learn more', 'nhhb'),
  'btn_url'  => '',
  'logo'     => 0,
];
$data = is_array($data ?? null) ? array_merge($defaults, $data) : $defaults;

$logo_url = $data['logo'] ? wp_get_attachment_image_url((int)$data['logo'], 'medium') : '';
?>
<section class="nhhb-b2b-wrap">
  <div class="nhhb-b2b">
    <?php if ($logo_url): ?>
      <span class="nhhb-b2b-logo">
        <img src="<?php echo esc_url($logo_url); ?>" alt="" loading="lazy">
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
