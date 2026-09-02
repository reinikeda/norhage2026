<?php
// Top Features (up to 4 items)
if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('nhhb-top-features');

$items_raw = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

$items = array_values(array_filter($items_raw, function ($it) {
    return !empty($it['icon']) || !empty($it['title']) || !empty($it['text']);
}));
$items = array_slice($items, 0, 4);

if (!$items) {
    return;
}

foreach ($items as &$it) {
    $it = [
        'icon'  => $it['icon'] ?? 0,
        'title' => $it['title'] ?? '',
        'text'  => $it['text'] ?? '',
    ];
}
unset($it);
?>
<section class="nhhb-top-features" aria-label="<?php echo esc_attr__('Shop benefits', 'nhhb'); ?>">
  <h2 class="screen-reader-text"><?php esc_html_e('Shop benefits', 'nhhb'); ?></h2>
  <div class="nhhb-features-grid">
    <?php foreach ($items as $it) : ?>
      <article class="nhhb-feature">
        <div class="nhhb-icon" aria-hidden="true"><?php echo nhhb_inline_svg($it['icon']); ?></div>
        <div class="nhhb-copy">
          <?php if ($it['title']) : ?><h3 class="nhhb-feature-title"><?php echo esc_html($it['title']); ?></h3><?php endif; ?>
          <?php if ($it['text']) : ?><p class="nhhb-feature-sub"><?php echo esc_html($it['text']); ?></p><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
