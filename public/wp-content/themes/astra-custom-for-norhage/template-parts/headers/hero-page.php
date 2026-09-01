<?php
/**
 * Inner-page hero: photo + title.
 *
 * Uses a real <img> (not CSS background-image) so Autoptimize/lazyload
 * cannot replace the photo with an empty SVG placeholder.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hero  = get_query_var( 'nhhb_hero', array() );
$title = wp_strip_all_tags(
	! empty( $hero['title'] ) ? $hero['title'] : get_the_title()
);

$thumb_id = 0;
if ( is_singular() ) {
	$thumb_id = (int) get_post_thumbnail_id( get_queried_object_id() );
}

$fallback = function_exists( 'nhhb_get_hero_fallback_image_url' ) ? nhhb_get_hero_fallback_image_url() : '';
if ( $fallback === '' && ! empty( $hero['bg'] ) ) {
	$fallback = $hero['bg'];
}

$img_attr = array(
	'class'          => 'nhhb-hero__media skip-lazy no-lazyload',
	'alt'            => '',
	'loading'        => 'eager',
	'fetchpriority'  => 'high',
	'decoding'       => 'async',
	'data-no-lazy'   => '1',
	'data-skip-lazy' => '1',
	'aria-hidden'    => 'true',
);
?>
<section
	class="nhhb-hero nhhb-hero--page skip-lazy no-lazyload"
	data-no-lazy="1"
	data-skip-lazy="1"
	aria-label="<?php echo esc_attr( $title ); ?>"
>
	<?php if ( $thumb_id ) : ?>
		<?php echo wp_get_attachment_image( $thumb_id, 'full', false, $img_attr ); ?>
	<?php elseif ( $fallback ) : ?>
		<img
			class="<?php echo esc_attr( $img_attr['class'] ); ?>"
			src="<?php echo esc_url( $fallback ); ?>"
			alt=""
			width="1920"
			height="640"
			loading="eager"
			fetchpriority="high"
			decoding="async"
			data-no-lazy="1"
			data-skip-lazy="1"
			aria-hidden="true"
		/>
	<?php endif; ?>

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
