<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$hero = get_query_var( 'nhhb_hero', ['bg' => '', 'title' => '' ] );
?>
<section class="nhhb-hero nhhb-hero--page" <?php if ( $hero['bg'] ) echo 'style="--nhhb-hero-bg:url(' . esc_url( $hero['bg'] ) . ')"'; ?>>
  <div class="nhhb-hero__inner">
    <h1 class="nhhb-hero__title"><?php echo esc_html( $hero['title'] ); ?></h1>
    <?php if ( function_exists('bcn_display') ) : ?>
      <div class="nhhb-hero__breadcrumbs"><?php bcn_display(); ?></div>
    <?php endif; ?>
  </div>
</section>
