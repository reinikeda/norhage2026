<?php
/**
 * Plugin Name: NHG Product Labels (harmonized)
 * Description: Compact, robust product labels for WooCommerce thumbnails and single products.
 * Version: 1.1.0
 * Author: Your team
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NHG_Product_Labels {

	/**
	 * Constructor: register hooks
	 */
	public function __construct() {
		// Render thumbnail overlays (inside the product image/link)
		add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'render_thumbnail_badges' ], 5 );

		// Single product: render badges near gallery
		add_action( 'woocommerce_before_single_product_summary', [ $this, 'render_single_badges' ], 20 );

		// Hide core WooCommerce stock HTML on archives (we provide our own)
		add_filter( 'woocommerce_get_stock_html', [ $this, 'filter_woocommerce_get_stock_html' ], 10, 2 );

		// Enqueue styles
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue CSS file
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'nhg-product-labels',
			plugins_url( 'css/labels.css', __FILE__ ),
			[],
			'1.1.0'
		);
	}

	/**
	 * Hide Woo core stock HTML on non-single pages so our badges control UI
	 */
	public function filter_woocommerce_get_stock_html( $html, $product ) {
		if ( is_product() ) {
			return $html;
		}
		return '';
	}

	/**
	 * Collect label data for a product
	 *
	 * @param WC_Product $product
	 * @return array
	 */
	private function collect_labels( WC_Product $product ) : array {
		$labels = [];

		// Sale
		if ( $product->is_on_sale() ) {
			$labels['sale'] = [
				'key'  => 'sale',
				'text' => __( 'Sale', 'nhg-product-labels' ),
			];
		}

		// New (30 days)
		$created = $product->get_date_created();
		if ( $created && ( time() - $created->getTimestamp() < 30 * DAY_IN_SECONDS ) ) {
			$labels['new'] = [
				'key'  => 'new',
				'text' => __( 'New', 'nhg-product-labels' ),
			];
		}

		// Custom cut / Custom size (integration point)
		if ( $this->is_custom_cut_product( $product ) ) {
			$labels['custom'] = [
				'key'  => 'custom',
				'text' => __( 'Custom size', 'nhg-product-labels' ),
			];
		}

		// Stock: out / low
		if ( ! $product->is_in_stock() ) {
			$labels['stock'] = [
				'key'        => 'stock',
				'text'       => __( 'Out of stock', 'nhg-product-labels' ),
				'stock_type' => 'out',
			];
		} else {
			// Low stock if applicable
			if ( method_exists( $product, 'get_low_stock_amount' ) ) {
				$low_stock_amount = $product->get_low_stock_amount();
				$stock_qty       = $product->get_stock_quantity();

				if ( $stock_qty !== null && $low_stock_amount !== null && $stock_qty > 0 && $stock_qty <= $low_stock_amount ) {
					$labels['stock'] = [
						'key'        => 'stock',
						'text'       => __( 'Low stock', 'nhg-product-labels' ),
						'stock_type' => 'low',
					];
				}
			}
		}

		return $labels;
	}

	/**
	 * Detect whether product has custom cut enabled.
	 * Replace the internal check with your real integration if different.
	 *
	 * @param WC_Product $product
	 * @return bool
	 */
	private function is_custom_cut_product( WC_Product $product ) : bool {
		if ( function_exists( 'nh_cc_is_enabled_product' ) ) {
			return (bool) nh_cc_is_enabled_product( $product->get_id() );
		}
		return false;
	}

	/**
	 * Render badges inside the thumbnail area (archive loop).
	 * Desired order: sale (if any) -> custom (if any) -> new/stock
	 */
	public function render_thumbnail_badges() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$labels = $this->collect_labels( $product );

		// If no labels, bail
		if ( empty( $labels ) ) {
			return;
		}

		// Output container; CSS will absolutely position it over the image.
		echo '<div class="nhg-labels nhg-labels--thumb" aria-hidden="true">';

		// 1) Sale first (so it appears on top)
		if ( isset( $labels['sale'] ) ) {
			printf(
				'<span class="nhg-badge nhg-badge--sale">%s</span>',
				esc_html( $labels['sale']['text'] )
			);
		}

		// 2) Custom size directly after sale (so it sits below sale when stacked)
		if ( isset( $labels['custom'] ) ) {
			printf(
				'<span class="nhg-badge nhg-badge--custom">%s</span>',
				esc_html( $labels['custom']['text'] )
			);
		}

		// 3) New
		if ( isset( $labels['new'] ) ) {
			printf(
				'<span class="nhg-badge nhg-badge--new"><span class="nhg-new-text">%s</span></span>',
				esc_html( $labels['new']['text'] )
			);
		}

		// 4) Stock / Low stock
		if ( isset( $labels['stock'] ) ) {
			$stock_type = isset( $labels['stock']['stock_type'] ) ? $labels['stock']['stock_type'] : 'out';
			printf(
				'<span class="nhg-badge nhg-badge--stock" data-stock="%s">%s</span>',
				esc_attr( $stock_type ),
				esc_html( $labels['stock']['text'] )
			);
		}

		echo '</div>';
	}

	/**
	 * Render badges on single product near gallery
	 */
	public function render_single_badges() {
		if ( ! is_product() ) {
			return;
		}
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$labels = $this->collect_labels( $product );
		if ( empty( $labels ) ) {
			return;
		}

		echo '<div class="nhg-labels nhg-labels--single">';
		// Reuse same order as thumbnail
		if ( isset( $labels['sale'] ) ) {
			printf( '<span class="nhg-badge nhg-badge--sale">%s</span>', esc_html( $labels['sale']['text'] ) );
		}
		if ( isset( $labels['custom'] ) ) {
			printf( '<span class="nhg-badge nhg-badge--custom">%s</span>', esc_html( $labels['custom']['text'] ) );
		}
		if ( isset( $labels['new'] ) ) {
			printf( '<span class="nhg-badge nhg-badge--new"><span class="nhg-new-text">%s</span></span>', esc_html( $labels['new']['text'] ) );
		}
		if ( isset( $labels['stock'] ) ) {
			$stock_type = isset( $labels['stock']['stock_type'] ) ? $labels['stock']['stock_type'] : 'out';
			printf( '<span class="nhg-badge nhg-badge--stock" data-stock="%s">%s</span>', esc_attr( $stock_type ), esc_html( $labels['stock']['text'] ) );
		}
		echo '</div>';
	}
}

new NHG_Product_Labels();
