<?php
// B2B Banner — single-logo version (button is the only link)
if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('nhhb-b2b');

$defaults = [
    'h2'       => __('For Business Customers', 'nhhb'),
    'h3'       => __('Exclusive pricing and services for B2B partners.', 'nhhb'),
    'btn_text' => __('Learn more', 'nhhb'),
    'btn_url'  => '',
    'logo'     => 0,
    'logo_d'   => 0,
    'logo_m'   => 0,
];
$data = is_array($data ?? null) ? array_merge($defaults, $data) : $defaults;

$kicker = __('🤝 Business Solutions', 'nhhb');

if (empty($data['logo'])) {
    if (!empty($data['logo_m'])) {
        $data['logo'] = absint($data['logo_m']);
    } elseif (!empty($data['logo_d'])) {
        $data['logo'] = absint($data['logo_d']);
    }
}

$logo_html = '';
if (!empty($data['logo'])) {
    $logo_html = nhhb_attachment_image((int) $data['logo'], 'medium', [
        'class'       => 'nhhb-b2b-logo-img',
        'alt'         => '',
        'aria-hidden' => 'true',
    ]);
}

$uid = 'nhhb-b2b-' . wp_unique_id();
?>
<section class="nhhb-b2b-wrap" aria-labelledby="<?php echo esc_attr($uid); ?>">
  <div class="nhhb-b2b">
    <?php if ($logo_html) : ?>
      <div class="nhhb-b2b-logo"><?php echo $logo_html; ?></div>
    <?php endif; ?>

    <div class="nhhb-b2b-content">
      <span class="nhhb-b2b-kicker"><?php echo esc_html($kicker); ?></span>
      <?php if (!empty($data['h2'])) : ?>
        <h2 id="<?php echo esc_attr($uid); ?>" class="nhhb-b2b-title"><?php echo esc_html($data['h2']); ?></h2>
      <?php endif; ?>
      <?php if (!empty($data['h3'])) : ?>
        <p class="nhhb-b2b-text"><?php echo esc_html($data['h3']); ?></p>
      <?php endif; ?>
    </div>

    <?php if (!empty($data['btn_url']) && !empty($data['btn_text'])) : ?>
      <div class="nhhb-b2b-cta-col">
        <a class="nhhb-b2b-cta"
           href="<?php echo esc_url($data['btn_url']); ?>"
           target="_blank"
           rel="noopener noreferrer">
          <?php echo esc_html($data['btn_text']); ?>
        </a>
      </div>
    <?php endif; ?>
  </div>
</section>
