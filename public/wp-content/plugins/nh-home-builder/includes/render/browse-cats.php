<?php
// Browse by Category – pulls Woo sub-categories of a chosen parent
if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('nhhb-browse-cats');
wp_enqueue_script('nhhb-browse-cats');

$title      = isset($data['title']) ? sanitize_text_field($data['title']) : __('Browse by Category', 'nhhb');
$parent_id  = isset($data['parent']) ? absint($data['parent']) : 0;
$limit      = isset($data['limit']) ? max(1, absint($data['limit'])) : 12;
$orderby    = isset($data['orderby']) ? sanitize_text_field($data['orderby']) : 'name';
$order      = isset($data['order']) ? sanitize_text_field($data['order']) : 'ASC';
$hide_empty = !empty($data['hide_empty']);

$uncat_id = (int) get_option('default_product_cat', 0);

$args = [
    'taxonomy'   => 'product_cat',
    'hide_empty' => $hide_empty,
    'orderby'    => $orderby,
    'order'      => $order,
    'parent'     => $parent_id,
    'number'     => $limit + 8,
];
if ($uncat_id) {
    $args['exclude'] = [$uncat_id];
}

$terms = get_terms($args);

if (!is_wp_error($terms) && $terms) {
    $terms = array_values(array_filter($terms, function ($t) use ($uncat_id) {
        if ((int) $t->term_id === $uncat_id) {
            return false;
        }
        $slug = isset($t->slug) ? $t->slug : '';
        return !in_array($slug, ['uncategorized', 'uncategorised'], true);
    }));
    $terms = array_slice($terms, 0, $limit);
}

$uid = 'nhhb-cats-' . wp_unique_id();
?>
<section class="nhhb-browse-cats" data-nhhb-cats aria-labelledby="<?php echo esc_attr($uid); ?>">
  <div class="nhhb-cats-head">
    <h2 id="<?php echo esc_attr($uid); ?>" class="nhhb-cats-title"><?php echo esc_html($title); ?></h2>
    <div class="nhhb-cats-arrows">
      <button class="nhhb-cat-prev" type="button" aria-label="<?php esc_attr_e('Scroll left', 'nhhb'); ?>">
        <?php echo nhhb_chevron_svg('prev'); ?>
      </button>
      <button class="nhhb-cat-next" type="button" aria-label="<?php esc_attr_e('Scroll right', 'nhhb'); ?>">
        <?php echo nhhb_chevron_svg('next'); ?>
      </button>
    </div>
  </div>

  <div class="nhhb-cats-track" tabindex="0" role="list">
    <?php if (!is_wp_error($terms) && $terms) : ?>
      <?php foreach ($terms as $t) :
          $thumb_id = (int) get_term_meta($t->term_id, 'thumbnail_id', true);
          $link = get_term_link($t);
          if (is_wp_error($link)) {
              continue;
          }
          ?>
        <a class="nhhb-cat" href="<?php echo esc_url($link); ?>" role="listitem">
          <span class="nhhb-cat-figure">
            <?php echo nhhb_attachment_image($thumb_id, 'woocommerce_thumbnail', [
                  'sizes' => '(max-width: 768px) 40vw, 160px',
                  'alt'   => $t->name,
            ]); ?>
          </span>
          <span class="nhhb-cat-name"><?php echo esc_html($t->name); ?></span>
        </a>
      <?php endforeach; ?>
    <?php else : ?>
      <div class="nhhb-cats-empty"><?php esc_html_e('No categories to display. Check your settings.', 'nhhb'); ?></div>
    <?php endif; ?>
  </div>
</section>
