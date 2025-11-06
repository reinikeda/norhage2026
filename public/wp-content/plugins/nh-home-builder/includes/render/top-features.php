<?php
// Top Features (static row, up to 4 items)
wp_enqueue_style('nhhb-top-features');

if (!function_exists('nhhb_img')) {
    function nhhb_img($id, $size = 'thumbnail', $attrs = []) {
        if (!$id) return '<div class="nhhb-ph-img" aria-hidden="true"></div>';
        $attrs = array_merge(['loading' => 'lazy', 'alt' => ''], $attrs);
        return wp_get_attachment_image((int)$id, $size, false, $attrs);
    }
}

$items_raw = (isset($data['items']) && is_array($data['items'])) ? $data['items'] : [];
$items = [];
for ($i = 0; $i < 4; $i++) {
    $it = isset($items_raw[$i]) && is_array($items_raw[$i]) ? $items_raw[$i] : [];
    $items[] = [
        'icon'   => isset($it['icon']) ? absint($it['icon']) : 0,
        'title'  => isset($it['title']) ? $it['title'] : '',
        'text'   => isset($it['text']) ? $it['text'] : '',
    ];
}

// Fallback (so empty section still looks ok in preview)
if (!array_filter($items, fn($r)=> !empty($r['icon']) || !empty($r['title']) || !empty($r['text']))) {
    $items = [
        ['icon'=>0,'title'=>__('Free Shipping','nhhb'),'text'=>__('For all orders over €200','nhhb')],
        ['icon'=>0,'title'=>__('1 & 1 Returns','nhhb'),'text'=>__('Cancellation after 1 day','nhhb')],
        ['icon'=>0,'title'=>__('100% Secure Payments','nhhb'),'text'=>__('Guaranteed secure payments','nhhb')],
        ['icon'=>0,'title'=>__('24/7 Dedicated Support','nhhb'),'text'=>__('Anywhere & anytime','nhhb')],
    ];
}
?>
<section class="nhhb-top-features" aria-label="<?php echo esc_attr__('Shop benefits','nhhb'); ?>">
  <h2 class="screen-reader-text"><?php esc_html_e('Shop benefits','nhhb'); ?></h2>
  <div class="nhhb-features-grid">
    <?php foreach ($items as $it): ?>
      <div class="nhhb-feature">
        <div class="nhhb-icon"><?php echo nhhb_img($it['icon'], 'medium'); ?></div>
        <div class="nhhb-copy">
          <?php if (!empty($it['title'])): ?>
            <h3 class="nhhb-feature-title"><?php echo esc_html($it['title']); ?></h3>
          <?php endif; ?>
          <?php if (!empty($it['text'])): ?>
            <p class="nhhb-feature-sub"><?php echo esc_html($it['text']); ?></p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
