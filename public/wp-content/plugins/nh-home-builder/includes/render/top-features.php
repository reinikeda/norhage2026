<?php
// Top Features (up to 4 items)
wp_enqueue_style('nhhb-top-features');

// Prepare up to 4 features (robust: remove empty entries and reindex)
$items_raw = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

// Filter out items that are completely empty and reindex
$items_filtered = array_values(array_filter($items_raw, function($it) {
    return !empty($it['icon']) || !empty($it['title']) || !empty($it['text']);
}));

// If no real items provided, use fallback demo items
if (empty($items_filtered)) {
    $items_filtered = [
        ['icon'=>0,'title'=>__('Free Shipping','nhhb'),'text'=>__('For all orders over €200','nhhb')],
        ['icon'=>0,'title'=>__('1 & 1 Returns','nhhb'),'text'=>__('Cancellation after 1 day','nhhb')],
        ['icon'=>0,'title'=>__('100% Secure Payments','nhhb'),'text'=>__('Guaranteed secure payments','nhhb')],
        ['icon'=>0,'title'=>__('24/7 Dedicated Support','nhhb'),'text'=>__('Anywhere & anytime','nhhb')],
    ];
}

// Limit to 4 visible items
$items = array_slice($items_filtered, 0, 4);

// Normalize keys for safety
foreach ($items as &$it) {
    $it = [
        'icon'  => $it['icon']  ?? 0,
        'title' => $it['title'] ?? '',
        'text'  => $it['text']  ?? '',
    ];
}
unset($it);
?>
<section class="nhhb-top-features" aria-label="<?php echo esc_attr__('Shop benefits','nhhb'); ?>">
  <h2 class="screen-reader-text"><?php esc_html_e('Shop benefits','nhhb'); ?></h2>
  <div class="nhhb-features-grid">
    <?php foreach ($items as $it): ?>
      <div class="nhhb-feature">
        <div class="nhhb-icon"><?php echo nhhb_inline_svg($it['icon']); ?></div>
        <div class="nhhb-copy">
          <?php if ($it['title']): ?><h3 class="nhhb-feature-title"><?php echo esc_html($it['title']); ?></h3><?php endif; ?>
          <?php if ($it['text']): ?><p class="nhhb-feature-sub"><?php echo esc_html($it['text']); ?></p><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
