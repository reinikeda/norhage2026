<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Background image ──────────────────────────────────────────
// Priority: 1) nhhb_hero query var  2) featured image  3) fallback
$hero        = get_query_var( 'nhhb_hero', [] );
$fallback_bg = get_stylesheet_directory_uri() . '/assets/images/hero-fallback.webp';

if ( ! empty( $hero['bg'] ) ) {
    $bg = $hero['bg'];
} elseif ( has_post_thumbnail() ) {
    $bg = get_the_post_thumbnail_url( null, 'full' );
} else {
    $bg = $fallback_bg;
}

// ── Title ─────────────────────────────────────────────────────
$title = ! empty( $hero['title'] ) ? $hero['title'] : get_the_title();
?>
<section
  class="nhhb-hero nhhb-hero--page"
  style="background-image: url('<?php echo esc_url( $bg ); ?>');"
  aria-label="<​?php echo esc_attr( $title ); ?>"
>
  <div class="nhhb-hero__overlay" aria-hidden="true"></div>

  <div class="nhhb-hero__inner">
    <h1 class="nhhb-hero__title"><?php echo esc_html( $title ); ?></h1>

    <?php if ( function_exists( 'bcn_display' ) ) : ?>
      <nav class="nhhb-hero__breadcrumbs" aria-label="<​?php esc_attr_e( 'Breadcrumbs', 'nh-theme' ); ?>">
        <?php bcn_display(); ?>
      </nav>
    <?php endif; ?>
  </div>
</section>
