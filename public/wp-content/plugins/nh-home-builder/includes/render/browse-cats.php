<?php
// Browse by Category – pulls Woo sub-categories of a chosen parent
if (!defined('ABSPATH')) exit;

wp_enqueue_style('nhhb-browse-cats');
wp_enqueue_script('nhhb-browse-cats');

if (!function_exists('nhhb_img')) {
    function nhhb_img($id, $size = 'thumbnail', $attrs = []) {
        if (!$id) return '<div class="nhhb-cat-ph"></div>';
        $attrs = array_merge(['loading' => 'lazy', 'alt' => ''], $attrs);
        return wp_get_attachment_image((int)$id, $size, false, $attrs);
    }
}

// Settings (saved in meta)
$title      = isset($data['title'])      ? sanitize_text_field($data['title'])   : __('Browse by Category','nhhb');
$parent_id  = isset($data['parent'])     ? absint($data['parent'])               : 0; // 0 = top level
$limit      = isset($data['limit'])      ? max(1, absint($data['limit']))        : 12;
$orderby    = isset($data['orderby'])    ? sanitize_text_field($data['orderby']) : 'name';
$order      = isset($data['order'])      ? sanitize_text_field($data['order'])   : 'ASC';
$hide_empty = !empty($data['hide_empty']);

// Woo's default product cat (Uncategorized) ID
$uncat_id = (int) get_option('default_product_cat', 0);

// Fetch terms
$args = [
    'taxonomy'   => 'product_cat',
    'hide_empty' => $hide_empty,
    'orderby'    => $orderby,
    'order'      => $order,
    'parent'     => $parent_id,
];
// Exclude uncategorized by ID when known
if ($uncat_id) {
    $args['exclude'] = [$uncat_id];
}

$terms = get_terms($args);

// Extra safety: remove by slug/ID, then reapply limit
if (!is_wp_error($terms) && $terms) {
    $terms = array_filter($terms, function($t) use ($uncat_id) {
        if ((int)$t->term_id === $uncat_id) return false;
        $slug = isset($t->slug) ? $t->slug : '';
        return !in_array($slug, ['uncategorized','uncategorised'], true);
    });
    // Reapply limit after filtering
    $terms = array_slice(array_values($terms), 0, $limit);
}
?>
<section class="nhhb-browse-cats" data-nhhb-cats>
  <div class="nhhb-cats-head">
    <h2 class="nhhb-cats-title"><?php echo esc_html($title); ?></h2>
    <div class="nhhb-cats-arrows">
      <button class="nhhb-cat-prev" type="button" aria-label="<?php esc_attr_e('Scroll left','nhhb'); ?>">‹</button>
      <button class="nhhb-cat-next" type="button" aria-label="<?php esc_attr_e('Scroll right','nhhb'); ?>">›</button>
    </div>
  </div>

  <div class="nhhb-cats-track" tabindex="0" role="list">
    <?php if (!is_wp_error($terms) && $terms): ?>
      <?php foreach ($terms as $t):
          $thumb_id = (int) get_term_meta($t->term_id, 'thumbnail_id', true);
          $link = get_term_link($t);
          ?>
          <a class="nhhb-cat" href="<?php echo esc_url($link); ?>" role="listitem">
            <span class="nhhb-cat-figure">
              <?php echo nhhb_img($thumb_id, 'medium'); ?>
            </span>
            <span class="nhhb-cat-name"><?php echo esc_html($t->name); ?></span>
          </a>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="nhhb-cats-empty"><?php esc_html_e('No categories to display. Check your settings.', 'nhhb'); ?></div>
    <?php endif; ?>
  </div>
</section>
