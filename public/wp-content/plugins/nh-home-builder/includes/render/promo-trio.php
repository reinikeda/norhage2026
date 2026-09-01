<?php
// Promo Trio (1 big + 2 small) — whole card is the hit target when a URL exists
if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('nhhb-promo-trio');

$cards = (isset($data['cards']) && is_array($data['cards'])) ? $data['cards'] : [];

for ($i = 0; $i < 3; $i++) {
    if (!isset($cards[$i]) || !is_array($cards[$i])) {
        $cards[$i] = [];
    }
    $cards[$i] = array_merge([
        'img'      => 0,
        'h2'       => '',
        'h3'       => '',
        'p'        => '',
        'btn_text' => '',
        'btn_url'  => '',
    ], $cards[$i]);
}

$render_card = static function ($c, $extra_class, $heading, $img_size) {
    $url = trim((string) $c['btn_url']);
    $tag = $url !== '' ? 'a' : 'article';
    $attrs = $url !== '' ? ' href="' . esc_url($url) . '"' : '';
    $heading_tag = $heading === 'h2' ? 'h2' : 'h3';
    $heading_class = $heading === 'h2' ? 'nhhb-pt-h2' : 'nhhb-pt-h3';
    ?>
    <<?php echo $tag; ?> class="nhhb-pt-card <?php echo esc_attr($extra_class); ?>"<?php echo $attrs; ?>>
      <span class="nhhb-pt-media">
        <?php echo nhhb_attachment_image((int) $c['img'], $img_size, [
            'class' => 'nhhb-pt-img',
            'alt'   => (string) $c['h2'],
            'sizes' => $extra_class === 'nhhb-pt-hero' ? '(max-width: 900px) 100vw, 62vw' : '(max-width: 900px) 100vw, 38vw',
        ]); ?>
      </span>
      <span class="nhhb-pt-copy">
        <?php if (!empty($c['h3'])) : ?>
          <span class="nhhb-pt-kicker"><?php echo esc_html($c['h3']); ?></span>
        <?php endif; ?>
        <?php if (!empty($c['h2'])) : ?>
          <<?php echo $heading_tag; ?> class="<?php echo esc_attr($heading_class); ?>"><?php echo esc_html($c['h2']); ?></<?php echo $heading_tag; ?>>
        <?php endif; ?>
        <?php if (!empty($c['p'])) : ?>
          <span class="nhhb-pt-p"><?php echo esc_html($c['p']); ?></span>
        <?php endif; ?>
        <?php if (!empty($c['btn_text'])) : ?>
          <span class="nhhb-pt-btn"><?php echo esc_html($c['btn_text']); ?></span>
        <?php endif; ?>
      </span>
    </<?php echo $tag; ?>>
    <?php
};
?>
<section class="nhhb-promo-trio">
  <div class="nhhb-pt-grid">
    <?php $render_card($cards[0], 'nhhb-pt-hero', 'h2', '1536x1536'); ?>
    <?php $render_card($cards[1], 'nhhb-pt-small', 'h3', 'large'); ?>
    <?php $render_card($cards[2], 'nhhb-pt-small', 'h3', 'large'); ?>
  </div>
</section>
