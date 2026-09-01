<?php
// Services Slider (1 service per slide)
if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('nhhb-services');
wp_enqueue_script('nhhb-services');

$section_title = '';
if (isset($data['services_title']) && trim((string) $data['services_title']) !== '') {
    $section_title = trim((string) $data['services_title']);
} elseif (isset($data['title']) && trim((string) $data['title']) !== '') {
    $section_title = trim((string) $data['title']);
} else {
    $section_title = __('Our Services', 'nhhb');
}

$manual = (isset($data['services']) && is_array($data['services'])) ? $data['services'] : [];

$q = new WP_Query([
    'post_type'           => 'service',
    'posts_per_page'      => 24,
    'post_status'         => 'publish',
    'orderby'             => 'menu_order title',
    'order'               => 'ASC',
    'no_found_rows'       => true,
    'ignore_sticky_posts' => true,
]);

$slides = [];
if ($q->have_posts()) {
    while ($q->have_posts()) {
        $q->the_post();
        $sid = get_the_ID();
        $row = (isset($manual[$sid]) && is_array($manual[$sid])) ? $manual[$sid] : [];

        $desktop_raw = isset($row['desktop']) ? trim((string) $row['desktop']) : '';
        $mobile_raw  = isset($row['mobile']) ? trim((string) $row['mobile']) : '';
        if ($desktop_raw === '' && $mobile_raw === '') {
            continue;
        }
        if ($desktop_raw === '') {
            $desktop_raw = $mobile_raw;
        }
        if ($mobile_raw === '') {
            $mobile_raw = $desktop_raw;
        }

        $thumb_id = (int) get_post_thumbnail_id($sid);
        $slides[] = [
            'title'      => get_the_title($sid),
            'desc_full'  => $desktop_raw,
            'desc_short' => $mobile_raw,
            'thumb_id'   => $thumb_id,
            'btn_text'   => __('Read More', 'nhhb'),
            'btn_url'    => get_permalink($sid),
        ];

        if (count($slides) >= 12) {
            break;
        }
    }
    wp_reset_postdata();
}

$uid = 'nhhb-svc-' . wp_unique_id();
?>
<section class="nhhb-services" data-nhhb-services-slider aria-labelledby="<?php echo esc_attr($uid); ?>">
  <?php if ($section_title !== '') : ?>
    <h2 id="<?php echo esc_attr($uid); ?>" class="nhhb-svc-section-title"><?php echo esc_html($section_title); ?></h2>
  <?php endif; ?>

  <?php if (!empty($slides)) : ?>
    <div class="nhhb-services-swiper swiper">
      <div class="swiper-wrapper">
        <?php foreach ($slides as $s) : ?>
          <article class="swiper-slide">
            <div class="nhhb-svc-bg">
              <?php
              if (!empty($s['thumb_id'])) {
                  echo nhhb_attachment_image((int) $s['thumb_id'], '1536x1536', [
                      'class' => 'nhhb-svc-img',
                      'alt'   => $s['title'],
                      'sizes' => '100vw',
                  ]);
              }
              ?>
            </div>

            <div class="nhhb-svc-inner">
              <?php if (!empty($s['title'])) : ?>
                <h3 class="nhhb-svc-title"><?php echo esc_html($s['title']); ?></h3>
              <?php endif; ?>

              <?php if ($s['desc_full'] !== '' || $s['desc_short'] !== '') : ?>
                <?php if ($s['desc_full'] !== '') : ?>
                  <p class="nhhb-svc-desc is-desktop"><?php echo esc_html($s['desc_full']); ?></p>
                <?php endif; ?>
                <?php if ($s['desc_short'] !== '') : ?>
                  <p class="nhhb-svc-desc is-mobile"><?php echo esc_html($s['desc_short']); ?></p>
                <?php endif; ?>
              <?php endif; ?>

              <div class="nhhb-svc-cta">
                <a class="nhhb-svc-btn" href="<?php echo esc_url($s['btn_url']); ?>">
                  <?php echo esc_html($s['btn_text']); ?>
                </a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <button type="button" class="nhhb-svc-nav nhhb-svc-prev" aria-label="<?php esc_attr_e('Previous slide', 'nhhb'); ?>">
        <?php echo nhhb_chevron_svg('prev'); ?>
      </button>
      <button type="button" class="nhhb-svc-nav nhhb-svc-next" aria-label="<?php esc_attr_e('Next slide', 'nhhb'); ?>">
        <?php echo nhhb_chevron_svg('next'); ?>
      </button>

      <div class="nhhb-svc-pagination swiper-pagination" aria-hidden="true"></div>
    </div>
  <?php else : ?>
    <p class="nhhb-services-empty">
      <?php esc_html_e('No services to display. Add Desktop/Mobile text in the section settings.', 'nhhb'); ?>
    </p>
  <?php endif; ?>
</section>
