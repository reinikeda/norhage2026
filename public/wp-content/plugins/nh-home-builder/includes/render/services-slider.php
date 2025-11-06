<?php
// Services Slider (1 service per slide)
// Renders from $data provided by the shortcode dispatcher.
if (!defined('ABSPATH')) exit;

wp_enqueue_style('nhhb-services');
wp_enqueue_script('nhhb-services');

/**
 * $data is expected to look like:
 * [
 *   'title'    => 'Our Services',
 *   'services' => [
 *       {SERVICE_ID} => [
 *         'include'  => 1|0,
 *         'title'    => '',
 *         'desc'     => '',
 *         'icon'     => ATTACHMENT_ID|0,
 *         'bg'       => ATTACHMENT_ID|0,
 *         'btn_text' => '',   // default "Read More"
 *         'btn_url'  => ''    // default get_permalink( SERVICE_ID )
 *       ],
 *       ...
 *   ]
 * ]
 */

// Section title
$section_title = isset($data['title']) ? trim((string)$data['title']) : __('Our Services', 'nhhb');
// Per-service overrides
$overrides = (isset($data['services']) && is_array($data['services'])) ? $data['services'] : [];

// Query all Service posts
$q = new WP_Query([
  'post_type'      => 'service',
  'posts_per_page' => -1,
  'post_status'    => 'publish',
  'orderby'        => 'menu_order title',
  'order'          => 'ASC',
]);

$slides = [];
if ($q->have_posts()) {
  while ($q->have_posts()) { $q->the_post();
    $sid = get_the_ID();

    // Get overrides if present
    $ov = isset($overrides[$sid]) && is_array($overrides[$sid]) ? $overrides[$sid] : [];

    // Include flag (default ON)
    $include = array_key_exists('include', $ov) ? (int)$ov['include'] : 1;
    if (!$include) continue;

    // Resolve fields with safe defaults
    $title    = !empty($ov['title']) ? $ov['title'] : get_the_title($sid);
    $desc     = array_key_exists('desc',$ov) && $ov['desc'] !== '' ? $ov['desc'] : get_the_excerpt($sid);

    $icon_id  = !empty($ov['icon']) ? absint($ov['icon']) : 0;
    $icon_url = $icon_id ? wp_get_attachment_image_url($icon_id, 'thumbnail') : '';

    $bg_id    = !empty($ov['bg']) ? absint($ov['bg']) : 0;
    $bg_url   = $bg_id ? wp_get_attachment_image_url($bg_id, 'full') : get_the_post_thumbnail_url($sid, 'full');

    $btn_text = !empty($ov['btn_text']) ? $ov['btn_text'] : __('Read More', 'nhhb');
    $btn_url  = !empty($ov['btn_url'])  ? $ov['btn_url']  : get_permalink($sid);

    // Skip if we have literally nothing visual
    if (!$bg_url && !$title && !$desc) continue;

    $slides[] = compact('title','desc','icon_url','bg_url','btn_text','btn_url');
  }
  wp_reset_postdata();
}
?>

<section class="nhhb-services" data-nhhb-services-slider>
  <?php if (!empty($section_title)) : ?>
    <h2 class="nhhb-svc-section-title"><?php echo esc_html($section_title); ?></h2>
  <?php endif; ?>

  <?php if (!empty($slides)) : ?>
    <div class="nhhb-services-swiper swiper">
      <div class="swiper-wrapper">
        <?php foreach ($slides as $s) : ?>
          <div class="swiper-slide">
            <div class="nhhb-svc-bg"<?php if (!empty($s['bg_url'])): ?>
              style="background-image:url(<?php echo esc_url($s['bg_url']); ?>);"<?php endif; ?>>
            </div>

            <div class="nhhb-svc-inner">
              <?php if (!empty($s['icon_url'])): ?>
                <div class="nhhb-svc-icon">
                  <img src="<?php echo esc_url($s['icon_url']); ?>" alt="">
                </div>
              <?php endif; ?>

              <?php if (!empty($s['title'])): ?>
                <h3 class="nhhb-svc-title"><?php echo esc_html($s['title']); ?></h3>
              <?php endif; ?>

              <?php if (!empty($s['desc'])): ?>
                <p class="nhhb-svc-desc"><?php echo wp_kses_post($s['desc']); ?></p>
              <?php endif; ?>

              <div class="nhhb-svc-cta">
                <a class="nhhb-svc-btn" href="<?php echo esc_url($s['btn_url']); ?>">
                  <?php echo esc_html($s['btn_text']); ?>
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- arrows -->
      <button class="nhhb-svc-nav nhhb-svc-prev" aria-label="<?php esc_attr_e('Previous slide','nhhb'); ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.41 16.59 10.83 12l4.58-4.59L14 6l-6 6 6 6z"/></svg>
      </button>
      <button class="nhhb-svc-nav nhhb-svc-next" aria-label="<?php esc_attr_e('Next slide','nhhb'); ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8.59 16.59 4.58-4.59-4.58-4.59L10 6l6 6-6 6z"/></svg>
      </button>

      <!-- dots -->
      <div class="nhhb-svc-pagination swiper-pagination" aria-hidden="true"></div>
    </div>
  <?php else: ?>
    <p class="nhhb-services-empty"><?php esc_html_e('No services to display. Add Services or enable “Include in slider”.','nhhb'); ?></p>
  <?php endif; ?>
</section>
