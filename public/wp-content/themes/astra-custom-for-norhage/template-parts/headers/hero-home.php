<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$hero = get_query_var( 'nhhb_hero', ['bg' => '', 'title' => '' ] );

// Fallback to your experiment image if no featured image is present
$fallback = trailingslashit( get_stylesheet_directory_uri() ) . 'assets/images/header-1920.jpg';
$bg_url   = $hero['bg'] ? $hero['bg'] : $fallback;
?>
<section class="nhhb-hero nhhb-hero--home" style="--nhhb-hero-bg:url(<?php echo esc_url( $bg_url ); ?>)">
  <div class="nhhb-hero__inner">
    <h1 class="nhhb-hero__title"><?php echo esc_html( $hero['title'] ); ?></h1>
    <?php /* Optional subtitle/CTA here later */ ?>
  </div>
</section>
