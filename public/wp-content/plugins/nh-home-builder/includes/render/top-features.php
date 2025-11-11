<?php
// Top Features (up to 4 items)
wp_enqueue_style('nhhb-top-features');

/**
 * Inline SVG from Media Library.
 */
if ( ! function_exists('nhhb_img') ) {
    function nhhb_img($id) {
        if ( ! $id ) return '<div class="nhhb-ph-img" aria-hidden="true"></div>';

        $path = get_attached_file($id);
        if ( ! $path || ! file_exists($path) ) return '';

        $svg = file_get_contents($path);

        // Clean up unnecessary headers + width/height
        $svg = preg_replace('/<\?xml.*?\?>/i', '', $svg);
        $svg = preg_replace('/<!DOCTYPE.*?>/is', '', $svg);
        $svg = preg_replace('/\s(width|height)="[^"]*"/i', '', $svg);

        // Optional: remove hard-coded fills/strokes for CSS color control
        // $svg = preg_replace('/\s(fill|stroke)="(?!none)[^"]*"/i', '', $svg);

        // Sanitize SVG tags (keeps only basic elements)
        $allowed = [
            'svg'   => ['viewBox'=>true,'xmlns'=>true,'role'=>true,'aria-label'=>true],
            'g'     => ['fill'=>true,'stroke'=>true],
            'path'  => ['d'=>true,'fill'=>true,'stroke'=>true],
            'rect'  => ['x'=>true,'y'=>true,'width'=>true,'height'=>true,'rx'=>true,'ry'=>true],
            'circle'=> ['cx'=>true,'cy'=>true,'r'=>true,'fill'=>true,'stroke'=>true],
            'title' => [],
        ];
        $svg = wp_kses($svg, $allowed);

        return '<span class="nhhb-svg">'.$svg.'</span>';
    }
}

// Prepare up to 4 features
$items_raw = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
$items = [];
for ($i = 0; $i < 4; $i++) {
    $it = isset($items_raw[$i]) ? $items_raw[$i] : [];
    $items[] = [
        'icon'  => $it['icon']  ?? 0,
        'title' => $it['title'] ?? '',
        'text'  => $it['text']  ?? '',
    ];
}

// Fallback demo items
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
        <div class="nhhb-icon"><?php echo nhhb_img($it['icon']); ?></div>
        <div class="nhhb-copy">
          <?php if ($it['title']): ?><h3 class="nhhb-feature-title"><?php echo esc_html($it['title']); ?></h3><?php endif; ?>
          <?php if ($it['text']): ?><p class="nhhb-feature-sub"><?php echo esc_html($it['text']); ?></p><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
