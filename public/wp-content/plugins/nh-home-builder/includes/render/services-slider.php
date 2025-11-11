<?php
// Services Slider (1 service per slide)
if (!defined('ABSPATH')) exit;

wp_enqueue_style('nhhb-services');
wp_enqueue_script('nhhb-services');

// Section title
$section_title = isset($data['title']) ? trim((string)$data['title']) : __('Our Services','nhhb');

// Word limits
$WORDS_DESKTOP = isset($data['words_desktop']) ? max(8, (int)$data['words_desktop']) : 36;
$WORDS_MOBILE  = isset($data['words_mobile'])  ? max(6, (int)$data['words_mobile'])  : 16;

// Optional overrides (kept for backward-compatibility)
$overrides = (isset($data['services']) && is_array($data['services'])) ? $data['services'] : [];

/** Helper: best teaser from a post (excerpt → trimmed content) */
function nhhb_service_teaser_from_post($post_id){
  $excerpt = trim(get_post_field('post_excerpt', $post_id));
  if ($excerpt !== '') return $excerpt;

  $content = get_post_field('post_content', $post_id);
  $content = do_shortcode($content);
  $content = wp_strip_all_tags($content);
  $content = preg_replace('/\s+/', ' ', $content);
  return trim($content);
}

/** Query all Service posts */
$q = new WP_Query([
  'post_type'      => 'service',
  'posts_per_page' => -1,
  'post_status'    => 'publish',
  'orderby'        => 'menu_order title',
  'order'          => 'ASC',
]);

$slides = [];
if ($q->have_posts()){
  while ($q->have_posts()){ $q->the_post();
    $sid = get_the_ID();
    $ov  = isset($overrides[$sid]) && is_array($overrides[$sid]) ? $overrides[$sid] : [];

    // Include flag (default ON)
    $include = array_key_exists('include', $ov) ? (int)$ov['include'] : 1;
    if (!$include) continue;

    // Title
    $title = !empty($ov['title']) ? $ov['title'] : get_the_title($sid);

    // Description: override > post excerpt > trimmed content
    $desc_raw = (array_key_exists('desc', $ov) && $ov['desc'] !== '')
      ? $ov['desc']
      : nhhb_service_teaser_from_post($sid);

    // Icon / Background
    $icon_id  = !empty($ov['icon']) ? absint($ov['icon']) : 0;
    $icon_url = $icon_id ? wp_get_attachment_image_url($icon_id, 'thumbnail') : '';

    $bg_id    = !empty($ov['bg']) ? absint($ov['bg']) : 0;
    $bg_url   = $bg_id ? wp_get_attachment_image_url($bg_id, 'full')
                       : get_the_post_thumbnail_url($sid, 'full');

    // CTA
    $btn_text = !empty($ov['btn_text']) ? $ov['btn_text'] : __('Read More','nhhb');
    $btn_url  = !empty($ov['btn_url'])  ? $ov['btn_url']  : get_permalink($sid);

    // Nothing meaningful? skip
    if (!$bg_url && !$title && $desc_raw === '') continue;

    // Build trimmed copies (HTML-escaped output)
    $desc_full  = esc_html( wp_trim_words( $desc_raw, $WORDS_DESKTOP, '…' ) );
    $desc_short = esc_html( wp_trim_words( $desc_raw, $WORDS_MOBILE,  '…' ) );

    $slides[] = [
      'title'      => $title,
      'desc_full'  => $desc_full,
      'desc_short' => $desc_short,
      'icon_url'   => $icon_url,
      'bg_url'     => $bg_url,
      'btn_text'   => $btn_text,
      'btn_url'    => $btn_url,
    ];
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

              <?php if (!empty($s['desc_full'])): ?>
                <!-- Desktop / large -->
                <p class="nhhb-svc-desc is-desktop"><?php echo $s['desc_full']; ?></p>
                <!-- Mobile / short -->
                <p class="nhhb-svc-desc is-mobile"><?php echo $s['desc_short']; ?></p>
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

      <!-- arrows (inline SVG; visible in all themes) -->
      <button class="nhhb-svc-nav nhhb-svc-prev" aria-label="<?php esc_attr_e('Previous slide','nhhb'); ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15.41 16.59 10.83 12l4.58-4.59L14 6l-6 6 6 6z"/>
        </svg>
      </button>
      <button class="nhhb-svc-nav nhhb-svc-next" aria-label="<?php esc_attr_e('Next slide','nhhb'); ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="m8.59 16.59 4.58-4.59-4.58-4.59L10 6l6 6-6 6z"/>
        </svg>
      </button>

      <!-- dots -->
      <div class="nhhb-svc-pagination swiper-pagination" aria-hidden="true"></div>
    </div>
  <?php else: ?>
    <p class="nhhb-services-empty">
      <?php esc_html_e('No services to display. Add Services or enable “Include in slider”.','nhhb'); ?>
    </p>
  <?php endif; ?>
</section>
