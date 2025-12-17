<?php
// Services Slider (1 service per slide)
if (!defined('ABSPATH')) exit;

wp_enqueue_style('nhhb-services');
wp_enqueue_script('nhhb-services');

// Section title (new admin field: data[services_title]; fallback to old data[title])
$section_title = '';
if (isset($data['services_title']) && trim((string)$data['services_title']) !== '') {
  $section_title = trim((string)$data['services_title']);
} elseif (isset($data['title']) && trim((string)$data['title']) !== '') {
  $section_title = trim((string)$data['title']);
} else {
  $section_title = __('Our Services','nhhb');
}

// Manual texts per service (new behavior)
$manual = (isset($data['services']) && is_array($data['services'])) ? $data['services'] : [];

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

    // Manual row saved in section settings (keyed by post ID)
    $row = (isset($manual[$sid]) && is_array($manual[$sid])) ? $manual[$sid] : [];

    // Title + background + link still come from the Service post (automatic)
    $title  = get_the_title($sid);
    $bg_url = get_the_post_thumbnail_url($sid, 'full');
    $btn_text = __('Read More','nhhb');
    $btn_url  = get_permalink($sid);

    // Manual texts
    $desktop_raw = isset($row['desktop']) ? trim((string)$row['desktop']) : '';
    $mobile_raw  = isset($row['mobile'])  ? trim((string)$row['mobile'])  : '';

    // If both texts are empty, skip (so you control what appears)
    if ($desktop_raw === '' && $mobile_raw === '') continue;

    // If one is empty, fallback to the other (so you only write once if you want)
    if ($desktop_raw === '' && $mobile_raw !== '') $desktop_raw = $mobile_raw;
    if ($mobile_raw === ''  && $desktop_raw !== '') $mobile_raw  = $desktop_raw;

    // Escape for output
    $desc_full  = esc_html($desktop_raw);
    $desc_short = esc_html($mobile_raw);

    // Skip if nothing meaningful
    if (!$bg_url && !$title && $desc_full === '' && $desc_short === '') continue;

    $slides[] = [
      'title'      => $title,
      'desc_full'  => $desc_full,
      'desc_short' => $desc_short,
      'icon_url'   => '', // icons removed in new admin UI; keep key for template compatibility
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

              <?php if (!empty($s['desc_full']) || !empty($s['desc_short'])): ?>
                <?php if (!empty($s['desc_full'])): ?>
                  <!-- Desktop / large -->
                  <p class="nhhb-svc-desc is-desktop"><?php echo $s['desc_full']; ?></p>
                <?php endif; ?>
                <?php if (!empty($s['desc_short'])): ?>
                  <!-- Mobile / short -->
                  <p class="nhhb-svc-desc is-mobile"><?php echo $s['desc_short']; ?></p>
                <?php endif; ?>
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
      <?php esc_html_e('No services to display. Add Desktop/Mobile text in the section settings.','nhhb'); ?>
    </p>
  <?php endif; ?>
</section>
