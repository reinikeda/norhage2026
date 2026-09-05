<?php
/**
 * Mobile sticky add-to-cart.
 *
 * Defaults to the main product button. When the bundle “Add all”
 * button is enabled, the sticky bar mirrors that instead.
 * Desktop keeps the in-form buttons — this bar is phones/tablets.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'nh_sticky_atc_assets', 20 );
function nh_sticky_atc_assets() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	wp_enqueue_script(
		'nh-sticky-atc',
		get_stylesheet_directory_uri() . '/assets/js/nh-sticky-atc.js',
		array(),
		norhage_asset_version( '/assets/js/nh-sticky-atc.js' ),
		function_exists( 'norhage_script_args' ) ? norhage_script_args() : true
	);
}

add_action( 'wp_footer', 'nh_render_sticky_atc', 20 );
function nh_render_sticky_atc() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return;
	}

	$label = $product->single_add_to_cart_text();
	?>
	<div id="nh-sticky-atc" class="nh-sticky-atc" hidden>
		<div class="nh-sticky-atc__price">
			<span class="nh-sticky-atc__label"><?php esc_html_e( 'Total', 'nh-theme' ); ?></span>
			<span class="nh-sticky-atc__amount" data-sticky-price>—</span>
		</div>
		<button type="button" class="button alt nh-sticky-atc__btn" data-default-label="<?php echo esc_attr( $label ); ?>">
			<?php echo esc_html( $label ); ?>
		</button>
	</div>
	<?php
}
