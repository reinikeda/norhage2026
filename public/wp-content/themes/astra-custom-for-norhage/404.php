<?php
/**
 * 404 — keep shoppers on the site after old-shop / broken inbound links.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$shop_url   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$home_url   = home_url( '/' );
$search_url = home_url( '/' );
$suggested  = function_exists( 'nh_404_suggested_products' ) ? nh_404_suggested_products() : array();
$suggest_ids = array();
foreach ( $suggested as $product ) {
	if ( $product instanceof WC_Product ) {
		$suggest_ids[] = $product->get_id();
	}
}
$popular    = function_exists( 'nh_404_popular_products' ) ? nh_404_popular_products( $suggest_ids ) : array();
$categories = function_exists( 'nh_404_categories' ) ? nh_404_categories() : array();
?>

<main class="nh-404" id="main">
	<div class="nh-404__intro">
		<p class="nh-404__code" aria-hidden="true">404</p>
		<h1><?php esc_html_e( "We couldn't find that page", 'nh-theme' ); ?></h1>
		<p class="nh-404__lead">
			<?php esc_html_e( 'This link is from our previous shop or is no longer available. Search for a product, or continue from a category below.', 'nh-theme' ); ?>
		</p>

		<form class="nh-404__search" action="<?php echo esc_url( $search_url ); ?>" method="get" role="search">
			<label class="screen-reader-text" for="nh-404-search"><?php esc_html_e( 'Search products', 'nh-theme' ); ?></label>
			<input
				type="search"
				id="nh-404-search"
				name="s"
				placeholder="<?php esc_attr_e( 'Search products…', 'nh-theme' ); ?>"
				autocomplete="off"
			/>
			<input type="hidden" name="post_type" value="product" />
			<button type="submit" class="nh-404__search-btn">
				<?php esc_html_e( 'Search products', 'nh-theme' ); ?>
			</button>
		</form>

		<div class="nh-404__actions">
			<?php if ( $shop_url ) : ?>
				<a class="nh-404__btn nh-404__btn--primary" href="<?php echo esc_url( $shop_url ); ?>">
					<?php esc_html_e( 'Browse the shop', 'nh-theme' ); ?>
				</a>
			<?php endif; ?>
			<a class="nh-404__btn nh-404__btn--ghost" href="<?php echo esc_url( $home_url ); ?>">
				<?php esc_html_e( 'Back to Homepage', 'nh-theme' ); ?>
			</a>
		</div>
	</div>

	<?php if ( ! empty( $suggested ) ) : ?>
		<section class="nh-404__section" aria-labelledby="nh-404-suggested">
			<h2 id="nh-404-suggested"><?php esc_html_e( 'Were you looking for these?', 'nh-theme' ); ?></h2>
			<?php nh_404_render_product_grid( $suggested ); ?>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $categories ) ) : ?>
		<section class="nh-404__section" aria-labelledby="nh-404-cats">
			<h2 id="nh-404-cats"><?php esc_html_e( 'Shop by Category', 'nh-theme' ); ?></h2>
			<div class="nh-404-cats">
				<?php foreach ( $categories as $term ) : ?>
					<?php
					$thumb_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
					$link     = get_term_link( $term );
					if ( is_wp_error( $link ) ) {
						continue;
					}
					?>
					<a class="nh-404-cat" href="<?php echo esc_url( $link ); ?>">
						<span class="nh-404-cat__img">
							<?php
							if ( $thumb_id ) {
								echo wp_get_attachment_image(
									(int) $thumb_id,
									'woocommerce_thumbnail',
									false,
									array(
										'alt'      => $term->name,
										'loading'  => 'lazy',
										'decoding' => 'async',
									)
								);
							} else {
								echo '<img src="' . esc_url( wc_placeholder_img_src( 'woocommerce_thumbnail' ) ) . '" alt="" loading="lazy" decoding="async" width="300" height="300" />';
							}
							?>
						</span>
						<span class="nh-404-cat__name"><?php echo esc_html( $term->name ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $popular ) ) : ?>
		<section class="nh-404__section" aria-labelledby="nh-404-popular">
			<h2 id="nh-404-popular"><?php esc_html_e( 'Popular products', 'nh-theme' ); ?></h2>
			<?php nh_404_render_product_grid( $popular ); ?>
		</section>
	<?php endif; ?>
</main>

<?php
get_footer();
