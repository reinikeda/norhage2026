<?php
// Five-star customer reviews slider (approved WooCommerce reviews with a written comment)
if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('nhhb-reviews');
wp_enqueue_script('nhhb-reviews');

$title = isset($data['title']) ? trim((string) $data['title']) : '';
if ($title === '') {
    $title = __('Customer reviews', 'nhhb');
}

$count = isset($data['count']) ? max(1, min(24, (int) $data['count'])) : 8;
$view_label = isset($data['view_label']) ? trim((string) $data['view_label']) : '';
$view_url   = isset($data['view_url']) ? (string) $data['view_url'] : '';

$reviews = nhhb_query_five_star_reviews($count);
$uid     = 'nhhb-rev-' . wp_unique_id();
?>
<section class="nhhb-reviews" data-nhhb-reviews aria-labelledby="<?php echo esc_attr($uid); ?>">
  <div class="nhhb-rev-head">
    <h2 id="<?php echo esc_attr($uid); ?>" class="nhhb-rev-title"><?php echo esc_html($title); ?></h2>
    <div class="nhhb-rev-tools">
      <?php if ($view_url !== '' && $view_label !== '') : ?>
        <a class="nhhb-rev-viewall" href="<?php echo esc_url($view_url); ?>">
          <?php echo esc_html($view_label); ?>
        </a>
      <?php endif; ?>
      <div class="nhhb-rev-arrows">
        <button class="nhhb-rev-prev" type="button" aria-label="<?php esc_attr_e('Previous reviews', 'nhhb'); ?>">
          <?php echo nhhb_chevron_svg('prev'); ?>
        </button>
        <button class="nhhb-rev-next" type="button" aria-label="<?php esc_attr_e('Next reviews', 'nhhb'); ?>">
          <?php echo nhhb_chevron_svg('next'); ?>
        </button>
      </div>
    </div>
  </div>

  <div class="nhhb-rev-track" tabindex="0" role="list">
    <?php if ($reviews) : ?>
      <?php foreach ($reviews as $review) : ?>
        <article class="nhhb-rev-card" role="listitem">
          <?php echo nhhb_review_stars_html(); ?>
          <blockquote class="nhhb-rev-quote">
            <p><?php echo esc_html($review['text']); ?></p>
          </blockquote>
          <footer class="nhhb-rev-byline">
            <?php echo nhhb_review_avatar_html($review); ?>
            <div class="nhhb-rev-meta">
              <span class="nhhb-rev-author"><?php echo esc_html($review['author']); ?></span>
              <?php if (!empty($review['verified'])) : ?>
                <span class="nhhb-rev-verified"><?php esc_html_e('Verified purchase', 'nhhb'); ?></span>
              <?php endif; ?>
              <?php if (!empty($review['product']) && !empty($review['url'])) : ?>
                <a class="nhhb-rev-product" href="<?php echo esc_url($review['url']); ?>">
                  <?php echo esc_html($review['product']); ?>
                </a>
              <?php elseif (!empty($review['product'])) : ?>
                <span class="nhhb-rev-product"><?php echo esc_html($review['product']); ?></span>
              <?php endif; ?>
            </div>
          </footer>
        </article>
      <?php endforeach; ?>
    <?php else : ?>
      <p class="nhhb-rev-empty">
        <?php esc_html_e('No 5-star reviews with a written comment yet.', 'nhhb'); ?>
      </p>
    <?php endif; ?>
  </div>
</section>
