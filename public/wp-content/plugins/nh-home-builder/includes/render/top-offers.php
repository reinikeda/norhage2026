<?php
// Top offers (slider + 2 promos) – safe renderer
wp_enqueue_style('nhhb-top-offers');
wp_enqueue_script('nhhb-top-offers');

if (!function_exists('nhhb_img')) {
    function nhhb_img($id, $size = 'large', $attrs = []) {
        if (!$id) return '<div class="nhhb-ph-img"></div>';
        $attrs = array_merge(['loading' => 'lazy', 'alt' => ''], $attrs);
        return wp_get_attachment_image((int)$id, $size, false, $attrs);
    }
}

$slides_raw = (isset($data['slides']) && is_array($data['slides'])) ? $data['slides'] : [];
$promos_raw = (isset($data['promos']) && is_array($data['promos'])) ? $data['promos'] : [];

$slides = array_values(array_filter($slides_raw, function($s){ return is_array($s) && !empty($s['img']); }));
$slides = array_slice($slides, 0, 3);

$promos = [];
for ($i = 0; $i < 2; $i++) {
    $promos[] = (isset($promos_raw[$i]) && is_array($promos_raw[$i])) ? $promos_raw[$i] : [];
}

if (empty($slides)) {
    $slides[] = [
        'img'      => 0,
        'h1'       => __('PREMIUM DESIGN', 'nhhb'),
        'h2'       => get_bloginfo('name'),
        'h3'       => __('Edit this section in Home Builder → Sections.', 'nhhb'),
        'btn_text' => __('Shop Now', 'nhhb'),
        'btn_url'  => home_url('/shop/'),
    ];
}
?>
<section class="nhhb-offers-hero" data-nhhb-slider>
  <div class="nhhb-hero-col">
    <div class="nhhb-slider" role="region" aria-roledescription="carousel" aria-label="<?php echo esc_attr__('Featured offers', 'nhhb'); ?>">
      <div class="nhhb-slides" aria-live="polite">
        <?php foreach ($slides as $s): ?>
          <div class="nhhb-slide">
            <div class="nhhb-slide-bg">
              <?php echo nhhb_img((int)($s['img'] ?? 0), 'full', ['class' => 'nhhb-bg']); ?>
            </div>
            <div class="nhhb-slide-copy">
              <?php if (!empty($s['h1'])): ?><div class="nhhb-h1"><?php echo esc_html($s['h1']); ?></div><?php endif; ?>
              <?php if (!empty($s['h2'])): ?><h2 class="nhhb-h2"><?php echo esc_html($s['h2']); ?></h2><?php endif; ?>
              <?php if (!empty($s['h3'])): ?><p class="nhhb-h3"><?php echo esc_html($s['h3']); ?></p><?php endif; ?>
              <?php if (!empty($s['btn_text'])): ?>
                <a href="<?php echo esc_url($s['btn_url'] ?? '#'); ?>" class="nhhb-btn"><?php echo esc_html($s['btn_text']); ?></a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (count($slides) > 1): ?>
        <div class="nhhb-dots">
          <?php foreach ($slides as $_): ?>
            <button class="nhhb-dot" aria-label="<?php esc_attr_e('Go to slide', 'nhhb'); ?>"></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="nhhb-hero-col nhhb-hero-side">
    <?php foreach ([0,1] as $i): $p = $promos[$i]; ?>
      <div class="nhhb-promo">
        <div class="nhhb-promo-media">
          <?php echo nhhb_img((int)($p['img'] ?? 0), 'large'); ?>
        </div>
        <div class="nhhb-promo-body">
          <?php if (!empty($p['h1'])): ?>
            <a class="nhhb-p2 nhhb-link-title" href="<?php echo esc_url($p['btn_url'] ?? '#'); ?>">
              <?php echo esc_html($p['h1']); ?>
            </a>
          <?php endif; ?>
          <?php if (!empty($p['h3'])): ?>
            <div class="nhhb-p3"><?php echo esc_html($p['h3']); ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
