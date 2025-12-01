<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$hero = get_query_var( 'nhhb_hero', [ 'bg' => '', 'title' => '' ] );

// Fallback image (child theme directory)
$fallback_bg = get_stylesheet_directory_uri() . '/assets/images/hero-fallback.jpg';

// If no bg is set, use fallback
$bg = $hero['bg'] ? $hero['bg'] : $fallback_bg;
?>
<section class="nhhb-hero nhhb-hero--page" style="--nhhb-hero-bg:url('<?php echo esc_url( $bg ); ?>')">
  <div class="nhhb-hero__inner">
    <h1 class="nhhb-hero__title"><?php echo esc_html( $hero['title'] ); ?></h1>
    <?php if ( function_exists('bcn_display') ) : ?>
      <div class="nhhb-hero__breadcrumbs"><?php bcn_display(); ?></div>
    <?php endif; ?>
  </div>
</section>
