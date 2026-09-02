<?php
// NH Home Builder – New Arrivals (latest Woo products)
if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('nhhb-new-arrivals');

$title      = isset($data['title']) ? sanitize_text_field($data['title']) : __('New Arrivals', 'nhhb');
$count      = isset($data['count']) ? max(1, min(24, (int) $data['count'])) : 8;
$view_label = isset($data['view_label']) ? sanitize_text_field($data['view_label']) : __('View All', 'nhhb');

if (function_exists('wc_get_page_permalink')) {
    $default_shop_url = wc_get_page_permalink('shop');
} else {
    $default_shop_url = get_post_type_archive_link('product');
}

$view_url = !empty($data['view_url']) ? $data['view_url'] : $default_shop_url;

$query_args = [
    'post_type'           => 'product',
    'post_status'         => 'publish',
    'posts_per_page'      => $count,
    'orderby'             => 'date',
    'order'               => 'DESC',
    'no_found_rows'       => true,
    'ignore_sticky_posts' => true,
];

if (taxonomy_exists('product_visibility')) {
    $query_args['tax_query'] = [
        [
            'taxonomy' => 'product_visibility',
            'field'    => 'name',
            'terms'    => ['exclude-from-catalog', 'exclude-from-search'],
            'operator' => 'NOT IN',
        ],
    ];
}

$q = new WP_Query($query_args);
$uid = 'nhhb-na-' . wp_unique_id();
?>
<section class="nhhb-new-arrivals" aria-labelledby="<?php echo esc_attr($uid); ?>">
  <div class="nhhb-na-head">
    <h2 id="<?php echo esc_attr($uid); ?>" class="nhhb-na-title"><?php echo esc_html($title); ?></h2>
    <?php if ($view_url) : ?>
      <a class="nhhb-na-viewall" href="<?php echo esc_url($view_url); ?>">
        <?php echo esc_html($view_label); ?>
      </a>
    <?php endif; ?>
  </div>

  <div class="nhhb-na-grid">
    <?php if ($q->have_posts()) : ?>
      <?php while ($q->have_posts()) : $q->the_post();
          $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
          if (!$product) {
              continue;
          }
          $thumb_id   = get_post_thumbnail_id();
          $price_html = $product->get_price_html();
          $name       = $product->get_name();
          ?>
        <a class="nhhb-na-card" href="<?php the_permalink(); ?>">
          <span class="nhhb-na-media">
            <span class="nhhb-na-img"><?php echo nhhb_attachment_image($thumb_id, 'woocommerce_thumbnail', ['alt' => $name]); ?></span>
          </span>
          <span class="nhhb-na-name"><?php echo esc_html($name); ?></span>
          <span class="nhhb-na-price"><?php echo wp_kses_post($price_html ?: '&nbsp;'); ?></span>
        </a>
      <?php endwhile; wp_reset_postdata(); ?>
    <?php else : ?>
      <p class="nhhb-na-empty"><?php esc_html_e('No products yet. Add a few to WooCommerce.', 'nhhb'); ?></p>
    <?php endif; ?>
  </div>
</section>
