<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$hero        = get_query_var( 'nhhb_hero', [] );
$fallback_bg = get_stylesheet_directory_uri() . '/assets/images/hero-fallback.webp';
$thumb_id    = get_post_thumbnail_id();

if ( ! empty( $hero['bg'] ) ) {
	$bg = $hero['bg'];
} elseif ( $thumb_id ) {
	$bg = get_the_post_thumbnail_url( null, '1536x1536' );
	if ( ! $bg ) {
		$bg = get_the_post_thumbnail_url( null, 'large' );
	}
} else {
	$bg = $fallback_bg;
}

$raw_title = ! empty( $hero['title'] ) ? $hero['title'] : get_the_title();
$title     = wp_strip_all_tags( $raw_title );
?>
<section
  class="nhhb-hero nhhb-hero--page"
  aria-label="<?php echo esc_attr( $title ); ?>"
>
  <?php
  if ( $thumb_id && empty( $hero['bg'] ) ) {
	  echo wp_get_attachment_image(
		  $thumb_id,
		  '1536x1536',
		  false,
		  array(
			  'class'         => 'nhhb-hero--page__img',
			  'alt'           => '',
			  'decoding'      => 'async',
			  'fetchpriority' => 'high',
			  'loading'       => 'eager',
			  'sizes'         => '100vw',
		  )
	  );
  } else {
	  printf(
		  '<img class="nhhb-hero--page__img" src="%s" alt="" decoding="async" fetchpriority="high" loading="eager" sizes="100vw">',
		  esc_url( $bg )
	  );
  }
  ?>
  <div class="nhhb-hero__overlay" aria-hidden="true"></div>

  <div class="nhhb-hero__inner">
    <h1 class="nhhb-hero__title"><?php echo esc_html( $title ); ?></h1>

    <?php if ( function_exists( 'bcn_display' ) ) : ?>
      <nav class="nhhb-hero__breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumbs', 'nh-theme' ); ?>">
        <?php bcn_display(); ?>
      </nav>
    <?php endif; ?>
  </div>
</section>
