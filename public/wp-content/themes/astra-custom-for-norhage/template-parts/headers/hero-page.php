<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$hero = get_query_var( 'nhhb_hero', [ 'bg' => '', 'title' => '' ] );
$fallback_bg = get_stylesheet_directory_uri() . '/assets/images/hero-fallback.webp';

$bg    = ! empty( $hero['bg'] ) ? $hero['bg'] : $fallback_bg;
$title = ! empty( $hero['title'] ) ? $hero['title'] : '';
?>
<section class="nhhb-hero nhhb-hero--page" style="--nhhb-hero-bg:url(<?php echo esc_url( $bg ); ?>)">
  <div class="nhhb-hero__inner">
    <h1 class="nhhb-hero__title"><?php echo esc_html( $title ); ?></h1>
    <?php if ( function_exists( 'bcn_display' ) ) : ?>
      <div class="nhhb-hero__breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumbs', 'nh-theme' ); ?>">
        <?php bcn_display(); ?>
      </div>
    <?php endif; ?>
  </div>
</section>
