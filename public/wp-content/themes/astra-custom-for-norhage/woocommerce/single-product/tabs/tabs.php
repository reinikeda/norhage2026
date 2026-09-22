<?php
/**
 * Product data sections — scrollable stack instead of tabs.
 *
 * Keeps every WooCommerce tab callback and prints the full HTML in the
 * page so search engines and AI crawlers can read every block.
 *
 * @package Astra_Custom_For_Norhage
 * @version 9.8.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'nh_pcs_get_product_tabs' ) ) {
	return;
}

$product_tabs = nh_pcs_get_product_tabs();

if ( empty( $product_tabs ) ) {
	return;
}

global $product;

$section_count = count( $product_tabs );
?>
<div class="woocommerce-tabs wc-tabs-wrapper nh-pcs" data-nh-pcs>
	<?php if ( $section_count > 1 ) : ?>
		<nav class="nh-pcs-toc" aria-label="<?php echo esc_attr__( 'Product information', 'nh-theme' ); ?>">
			<ol class="nh-pcs-toc__list">
				<?php foreach ( $product_tabs as $key => $product_tab ) : ?>
					<?php
					$title = apply_filters( 'woocommerce_product_' . $key . '_tab_title', $product_tab['title'], $key );
					$label = nh_pcs_nav_label( $title );
					if ( '' === $label ) {
						continue;
					}
					?>
					<li class="nh-pcs-toc__item">
						<a class="nh-pcs-toc__link" href="#tab-<?php echo esc_attr( $key ); ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>
	<?php endif; ?>

	<div class="nh-pcs-stack">
		<?php foreach ( $product_tabs as $key => $product_tab ) : ?>
			<?php
			$title      = apply_filters( 'woocommerce_product_' . $key . '_tab_title', $product_tab['title'], $key );
			$is_open    = nh_pcs_section_default_open( $key, $product instanceof WC_Product ? $product : null );
			$uses_clamp = nh_pcs_section_uses_clamp( $key );
			$is_static  = $uses_clamp; // Description stays open; long copy uses Read more.
			$panel_id   = 'nh-pcs-panel-' . sanitize_html_class( $key );
			$heading_id = 'nh-pcs-heading-' . sanitize_html_class( $key );

			ob_start();
			if ( isset( $product_tab['callback'] ) && is_callable( $product_tab['callback'] ) ) {
				call_user_func( $product_tab['callback'], $key, $product_tab );
			}
			$panel_html = ob_get_clean();
			$highlights = '';
			if ( $uses_clamp ) {
				$split      = nh_pcs_split_description_highlights( $panel_html );
				$panel_html = $split['body'];
				$highlights = $split['highlights'];
			}
			?>

			<?php if ( $is_static ) : ?>
				<section
					id="tab-<?php echo esc_attr( $key ); ?>"
					class="nh-pcs-section nh-pcs-section--static nh-pcs-section--<?php echo esc_attr( $key ); ?> nh-pcs-section--clamp"
					data-nh-pcs-section="<?php echo esc_attr( $key ); ?>"
					aria-label="<?php echo esc_attr( nh_pcs_nav_label( $title ) ); ?>"
				>
					<div
						class="nh-pcs-section__panel woocommerce-Tabs-panel woocommerce-Tabs-panel--<?php echo esc_attr( $key ); ?> panel entry-content"
						id="<?php echo esc_attr( $panel_id ); ?>"
					>
						<div class="nh-pcs-clamp" data-nh-pcs-clamp>
							<div class="nh-pcs-clamp__content">
								<?php echo $panel_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce tab callbacks. ?>
							</div>
							<button
								type="button"
								class="nh-pcs-clamp__toggle"
								data-nh-pcs-clamp-toggle
								hidden
								aria-expanded="false"
							>
								<?php echo esc_html__( 'Read more', 'nh-theme' ); ?>
							</button>
						</div>
						<?php if ( '' !== $highlights ) : ?>
							<div class="nh-pcs-highlights">
								<?php echo $highlights; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- same tab HTML, moved out of the clamp. ?>
							</div>
						<?php endif; ?>
					</div>
				</section>
			<?php else : ?>
				<details
					id="tab-<?php echo esc_attr( $key ); ?>"
					class="nh-pcs-section nh-pcs-section--<?php echo esc_attr( $key ); ?>"
					data-nh-pcs-section="<?php echo esc_attr( $key ); ?>"
					<?php echo $is_open ? ' open' : ''; ?>
				>
					<summary class="nh-pcs-section__summary" id="<?php echo esc_attr( $heading_id ); ?>">
						<span class="nh-pcs-section__title">
							<?php echo wp_kses_post( $title ); ?>
						</span>
						<span class="nh-pcs-section__icon" aria-hidden="true"></span>
					</summary>

					<div
						class="nh-pcs-section__panel woocommerce-Tabs-panel woocommerce-Tabs-panel--<?php echo esc_attr( $key ); ?> panel entry-content"
						id="<?php echo esc_attr( $panel_id ); ?>"
						role="region"
						aria-labelledby="<?php echo esc_attr( $heading_id ); ?>"
					>
						<?php echo $panel_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce tab callbacks. ?>
					</div>
				</details>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</div>
<?php
do_action( 'woocommerce_product_after_tabs' );
