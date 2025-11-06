<?php
// NH Home Builder – New Arrivals (latest Woo products)
if (!defined('ABSPATH')) exit;

if (!function_exists('nhhb_img')) {
    function nhhb_img($id, $size = 'woocommerce_thumbnail', $attrs = []) {
        if (!$id) return '<div class="nhhb-na-ph"></div>';
        $attrs = array_merge(['loading' => 'lazy', 'alt' => ''], $attrs);
        return wp_get_attachment_image((int)$id, $size, false, $attrs);
    }
}

wp_enqueue_style('nhhb-new-arrivals');

// Read saved settings (with sane defaults)
$title       = isset($data['title']) ? sanitize_text_field($data['title']) : __('New Arrivals','nhhb');
$count       = isset($data['count']) ? max(1, min(24, (int)$data['count'])) : 8;
$view_label  = isset($data['view_label']) ? sanitize_text_field($data['view_label']) : __('View All','nhhb');
$view_url    = !empty($data['view_url']) ? esc_url($data['view_url']) : esc_url(home_url('/shop/'));

$q = new WP_Query([
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => $count,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'no_found_rows'  => true,
]);

?>
<section class="nhhb-new-arrivals">
  <div class="nhhb-na-head">
    <h2 class="nhhb-na-title"><?php echo esc_html($title); ?></h2>
    <a class="nhhb-na-viewall" href="<?php echo esc_url($view_url); ?>">
      <?php echo esc_html($view_label); ?>
    </a>
  </div>

  <div class="nhhb-na-grid">
    <?php if ($q->have_posts()): while ($q->have_posts()): $q->the_post();
        $product   = wc_get_product(get_the_ID());
        if (!$product) continue;
        $thumb_id  = get_post_thumbnail_id();
        $price_html = $product->get_price_html();
    ?>
      <article class="nhhb-na-card">
        <a class="nhhb-na-media" href="<?php the_permalink(); ?>">
          <span class="nhhb-na-img"><?php echo nhhb_img($thumb_id); ?></span>
          <span class="nhhb-na-hover">
            <span class="nhhb-na-cta"><?php esc_html_e('Explore','nhhb'); ?></span>
          </span>
        </a>

        <h3 class="nhhb-na-name">
          <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>

        <div class="nhhb-na-price">
          <?php echo wp_kses_post($price_html ?: '&nbsp;'); ?>
        </div>
      </article>
    <?php endwhile; wp_reset_postdata(); else: ?>
      <p class="nhhb-na-empty"><?php esc_html_e('No products yet. Add a few to WooCommerce.','nhhb'); ?></p>
    <?php endif; ?>
  </div>
</section>
