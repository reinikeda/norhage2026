<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$slides   = function_exists( 'nhhb_get_home_hero_slides' ) ? nhhb_get_home_hero_slides() : [];
$sr_title = function_exists( 'nhhb_get_hero_title' ) ? nhhb_get_hero_title() : get_bloginfo( 'name' );
?>

<section
	class="nhhb-hero nhhb-hero--home nhhb-hero-slider"
	data-nh-hero-slider
	aria-label="<?php echo esc_attr__( 'Homepage highlights', 'nh-theme' ); ?>"
>
	<h1 class="screen-reader-text"><?php echo esc_html( $sr_title ); ?></h1>

	<div class="nhhb-hero-slider__slides">
		<?php foreach ( $slides as $index => $slide ) : ?>
			<article
				class="nhhb-hero-slide<?php echo 0 === $index ? ' is-active' : ''; ?>"
				style="--nhhb-hero-bg:url(<?php echo esc_url( $slide['image'] ); ?>)"
				aria-hidden="<?php echo 0 === $index ? 'false' : 'true'; ?>"
			>
				<div class="nhhb-hero__inner">
					<?php if ( ! empty( $slide['title'] ) ) : ?>
						<h2 class="nhhb-hero__title"><?php echo esc_html( $slide['title'] ); ?></h2>
					<?php endif; ?>

					<?php if ( ! empty( $slide['text'] ) ) : ?>
						<p class="nhhb-hero__subtitle"><?php echo esc_html( $slide['text'] ); ?></p>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>

	<?php if ( count( $slides ) > 1 ) : ?>
		<div class="nhhb-hero-slider__controls">
			<button
				type="button"
				class="nhhb-hero-slider__arrow nhhb-hero-slider__arrow--prev"
				data-nh-hero-prev
				aria-label="<?php esc_attr_e( 'Previous slide', 'nh-theme' ); ?>"
			>
				<span aria-hidden="true">‹</span>
			</button>

			<div
				class="nhhb-hero-slider__dots"
				role="tablist"
				aria-label="<?php esc_attr_e( 'Hero slides', 'nh-theme' ); ?>"
			>
				<?php foreach ( $slides as $index => $slide ) : ?>
					<button
						type="button"
						class="nhhb-hero-slider__dot<?php echo 0 === $index ? ' is-active' : ''; ?>"
						data-nh-hero-dot="<?php echo esc_attr( $index ); ?>"
						role="tab"
						aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
						aria-label="<?php echo esc_attr( sprintf( __( 'Go to slide %d', 'nh-theme' ), $index + 1 ) ); ?>"
					></button>
				<?php endforeach; ?>
			</div>

			<button
				type="button"
				class="nhhb-hero-slider__arrow nhhb-hero-slider__arrow--next"
				data-nh-hero-next
				aria-label="<?php esc_attr_e( 'Next slide', 'nh-theme' ); ?>"
			>
				<span aria-hidden="true">›</span>
			</button>
		</div>
	<?php endif; ?>
</section>
