<?php
// Top offers (slider + 2 promos)
if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('nhhb-top-offers');
wp_enqueue_script('nhhb-top-offers');

$slides_raw = (isset($data['slides']) && is_array($data['slides'])) ? $data['slides'] : [];
$promos_raw = (isset($data['promos']) && is_array($data['promos'])) ? $data['promos'] : [];

$slides = array_values(array_filter($slides_raw, function ($s) {
    return is_array($s) && !empty($s['img']);
}));
$slides = array_slice($slides, 0, 3);

$promos = [];
for ($i = 0; $i < 2; $i++) {
    $promos[] = (isset($promos_raw[$i]) && is_array($promos_raw[$i])) ? $promos_raw[$i] : [];
}

if (empty($slides)) {
    return;
}
?>
<section class="nhhb-offers-hero" data-nhhb-slider>
  <div class="nhhb-hero-col">
    <div class="nhhb-slider" role="region" aria-roledescription="carousel" aria-label="<?php echo esc_attr__('Featured offers', 'nhhb'); ?>">
      <div class="nhhb-slides" aria-live="polite">
        <?php foreach ($slides as $i => $s) :
            $img_attrs = [
                'class' => 'nhhb-bg',
                'alt'   => (string) ($s['h1'] ?? ''),
            ];
            if ($i === 0) {
                $img_attrs['loading'] = 'eager';
                $img_attrs['fetchpriority'] = 'high';
            }
            ?>
          <div class="nhhb-slide">
            <div class="nhhb-slide-bg">
              <?php echo nhhb_attachment_image((int) ($s['img'] ?? 0), '1536x1536', $img_attrs); ?>
            </div>
            <div class="nhhb-slide-copy">
              <?php if (!empty($s['h1'])) : ?><p class="nhhb-h1"><?php echo esc_html($s['h1']); ?></p><?php endif; ?>
              <?php if (!empty($s['h2'])) : ?><h2 class="nhhb-h2"><?php echo esc_html($s['h2']); ?></h2><?php endif; ?>
              <?php if (!empty($s['h3'])) : ?><p class="nhhb-h3"><?php echo esc_html($s['h3']); ?></p><?php endif; ?>
              <?php if (!empty($s['btn_text'])) : ?>
                <a href="<?php echo esc_url($s['btn_url'] ?? '#'); ?>" class="nhhb-btn"><?php echo esc_html($s['btn_text']); ?></a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (count($slides) > 1) : ?>
        <div class="nhhb-dots" role="tablist" aria-label="<?php echo esc_attr__('Offer slides', 'nhhb'); ?>">
          <?php foreach ($slides as $i => $_) : ?>
            <button type="button" class="nhhb-dot<?php echo $i === 0 ? ' is-active' : ''; ?>" aria-label="<?php esc_attr_e('Go to slide', 'nhhb'); ?>"></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="nhhb-hero-col nhhb-hero-side">
    <?php foreach ([0, 1] as $i) :
        $p = $promos[$i];
        $url = trim((string) ($p['btn_url'] ?? ''));
        $tag = $url !== '' ? 'a' : 'article';
        $attrs = $url !== '' ? ' href="' . esc_url($url) . '"' : '';
        ?>
      <<?php echo $tag; ?> class="nhhb-promo"<?php echo $attrs; ?>>
        <span class="nhhb-promo-media">
          <?php echo nhhb_attachment_image((int) ($p['img'] ?? 0), 'large', ['alt' => (string) ($p['h1'] ?? '')]); ?>
        </span>
        <span class="nhhb-promo-body">
          <?php if (!empty($p['h1'])) : ?>
            <span class="nhhb-p2"><?php echo esc_html($p['h1']); ?></span>
          <?php endif; ?>
          <?php if (!empty($p['h3'])) : ?>
            <span class="nhhb-p3"><?php echo esc_html($p['h3']); ?></span>
          <?php endif; ?>
        </span>
      </<?php echo $tag; ?>>
    <?php endforeach; ?>
  </div>
</section>
