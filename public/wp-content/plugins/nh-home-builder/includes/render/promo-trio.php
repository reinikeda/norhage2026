<?php
// Promo Trio (1 big + 2 small)
if (!defined('ABSPATH')) exit;

wp_enqueue_style('nhhb-promo-trio');

if (!function_exists('nhhb_img')) {
    function nhhb_img($id, $size = 'large', $attrs = []) {
        if (!$id) return '<div class="nhhb-pt-ph"></div>';
        $attrs = array_merge(['loading' => 'lazy', 'alt' => ''], $attrs);
        return wp_get_attachment_image((int)$id, $size, false, $attrs);
    }
}

$cards = (isset($data['cards']) && is_array($data['cards'])) ? $data['cards'] : [];

/* Safe defaults so the section never breaks */
for ($i = 0; $i < 3; $i++) {
    if (!isset($cards[$i]) || !is_array($cards[$i])) $cards[$i] = [];
    $cards[$i] = array_merge([
        'img'      => 0,
        'h2'       => '',
        'h3'       => '',
        'p'        => '',
        'btn_text' => '',
        'btn_url'  => '',
    ], $cards[$i]);
}
?>
<section class="nhhb-promo-trio">
  <div class="nhhb-pt-grid">
    <?php /* Hero (full width) */ $c = $cards[0]; ?>
    <div class="nhhb-pt-card nhhb-pt-hero">
      <div class="nhhb-pt-media">
        <?php echo nhhb_img((int)$c['img'], 'full', ['class'=>'nhhb-pt-img']); ?>
      </div>
      <div class="nhhb-pt-copy">
        <?php if(!empty($c['h3'])): ?><div class="nhhb-pt-kicker"><?php echo esc_html($c['h3']); ?></div><?php endif; ?>
        <?php if(!empty($c['h2'])): ?><h2 class="nhhb-pt-h2"><?php echo esc_html($c['h2']); ?></h2><?php endif; ?>
        <?php if(!empty($c['p'])):  ?><p class="nhhb-pt-p"><?php echo esc_html($c['p']);  ?></p><?php endif; ?>
        <?php if(!empty($c['btn_text'])): ?>
          <a class="nhhb-pt-btn" href="<?php echo esc_url($c['btn_url'] ?: '#'); ?>"><?php echo esc_html($c['btn_text']); ?></a>
        <?php endif; ?>
      </div>
    </div>

    <?php /* Two small promos */ ?>
    <?php for ($i = 1; $i <= 2; $i++): $c = $cards[$i]; ?>
      <div class="nhhb-pt-card nhhb-pt-small">
        <div class="nhhb-pt-media">
          <?php echo nhhb_img((int)$c['img'], 'large', ['class'=>'nhhb-pt-img']); ?>
        </div>
        <div class="nhhb-pt-copy">
          <?php if(!empty($c['h3'])): ?><div class="nhhb-pt-kicker"><?php echo esc_html($c['h3']); ?></div><?php endif; ?>
          <?php if(!empty($c['h2'])): ?><h3 class="nhhb-pt-h3"><?php echo esc_html($c['h2']); ?></h3><?php endif; ?>
          <?php if(!empty($c['p'])):  ?><p class="nhhb-pt-p"><?php echo esc_html($c['p']);  ?></p><?php endif; ?>
          <?php if(!empty($c['btn_text'])): ?>
            <a class="nhhb-pt-btn" href="<?php echo esc_url($c['btn_url'] ?: '#'); ?>"><?php echo esc_html($c['btn_text']); ?></a>
          <?php endif; ?>
        </div>
      </div>
    <?php endfor; ?>
  </div>
</section>
