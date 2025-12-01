<?php
/**
 * Plugin Name: Product Labels
 * Description: Sale %, New (30 days), Low stock (<3). Shows on loop card and on the first image of single product.
 * Author: Daiva Reinike
 * Version: 0.9
 * License: GPL-2.0+
 * Text Domain: nhg-labels
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Load plugin textdomain.
 */
function nhg_labels_load_textdomain() {
	load_plugin_textdomain(
		'nhg-labels',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'nhg_labels_load_textdomain' );

class NHG_Product_Labels {
	const NEW_DAYS      = 30;
	const LOW_STOCK_QTY = 3;

	public function __construct() {

		/* ========= LOOP (archive/category/shop) =========
		 * We print TWO containers:
		 * 1) nhg-labels--loop  → anchored to <li.product>   (NEW + LOW)
		 * 2) nhg-labels--thumb → anchored inside image link (SALE)
		 */
		add_action( 'woocommerce_before_shop_loop_item',          [ $this, 'output_loop_non_sale' ], 9 ); // before <a>, sibling of thumbnail
		add_action( 'woocommerce_before_shop_loop_item_title',    [ $this, 'output_loop_sale_inside_thumb' ], 1 ); // inside the image <a>

		/* ========= SINGLE PRODUCT =========
		 * Inject badges into the first gallery image HTML (works with one or many images).
		 */
		add_filter( 'woocommerce_single_product_image_thumbnail_html', [ $this, 'inject_single_badges_into_image_html' ], 10, 2 );

		// Plain "New" text flag above title (below breadcrumbs)
		add_action( 'woocommerce_single_product_summary', [ $this, 'output_single_new_inline' ], 4 );

		// Styles
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}

	public function enqueue_styles() {
		wp_enqueue_style(
			'nhg-product-labels',
			plugins_url( 'css/labels.css', __FILE__ ),
			[],
			'0.9'
		);
	}

	/** ---------- Label logic ---------- */
	private function get_labels( WC_Product $product ) : array {
		$labels = [];

		// SALE % (supports variable range)
		if ( $product->is_on_sale() ) {
			if ( $product->is_type( 'variable' ) ) {

				// ----- VARIABLE PRODUCTS -----
				$discounts      = [];
				$total_children = 0;
				$sale_children  = 0;

				foreach ( $product->get_children() as $vid ) {
					$child = wc_get_product( $vid );
					if ( ! $child ) {
						continue;
					}

					$total_children++;

					$reg  = (float) $child->get_regular_price();
					$sale = (float) $child->get_sale_price();

					if ( $reg > 0 && $sale > 0 && $sale < $reg ) {
						$sale_children++;

						$pct = (int) round( ( ( $reg - $sale ) / $reg ) * 100 );
						if ( $pct > 0 ) {
							$discounts[] = $pct;
						}
					}
				}

				if ( ! empty( $discounts ) ) {
					$min = min( $discounts );
					$max = max( $discounts );

					$all_discounted = ( $total_children > 0 && $sale_children === $total_children );

					if ( $all_discounted ) {
						// All variations on sale: show X–Y% (or single X%)
						$range = ( $min === $max ) ? "{$max}%" : "{$min}–{$max}%";

						$labels[] = [
							'key'   => 'sale',
							'text'  => sprintf( __( 'Sale %s', 'nhg-labels' ), $range ),
							'line1' => __( 'Sale', 'nhg-labels' ),
							'line2' => $range,
						];
					} else {
						// Only some variations on sale: "Up to X%"
						$range = sprintf( __( 'Up to %s', 'nhg-labels' ), "{$max}%" );

						$labels[] = [
							'key'   => 'sale',
							'text'  => $range, // aria-label
							'line1' => __( 'Sale', 'nhg-labels' ),
							'line2' => $range,
						];
					}
				}

			} else {
				// ----- SIMPLE PRODUCTS -----
				$reg  = (float) $product->get_regular_price();
				$sale = (float) $product->get_sale_price();
				if ( $reg > 0 && $sale > 0 && $sale < $reg ) {
					$pct = (int) round( ( ( $reg - $sale ) / $reg ) * 100 );
					if ( $pct > 0 ) {
						$range = "{$pct}%";
						$labels[] = [
							'key'   => 'sale',
							'text'  => sprintf( __( 'Sale %s', 'nhg-labels' ), $range ),
							'line1' => __( 'Sale', 'nhg-labels' ),
							'line2' => $range,
						];
					}
				}
			}
		}

		// NEW (first 30 days)
		$created = $product->get_date_created();
		if ( $created ) {
			$days = (int) floor( ( time() - $created->getTimestamp() ) / DAY_IN_SECONDS );
			if ( $days >= 0 && $days < self::NEW_DAYS ) {
				$labels[] = [ 'key' => 'new', 'text' => __( 'New', 'nhg-labels' ) ];
			}
		}

		// LOW STOCK (<3, managed, no backorders) — loop only
		$low_any = false;
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $vid ) {
				$child = wc_get_product( $vid );
				if ( ! $child ) continue;
				if ( $child->managing_stock() && ! $child->backorders_allowed() ) {
					$qty = $child->get_stock_quantity();
					if ( is_numeric( $qty ) && $qty > 0 && $qty < self::LOW_STOCK_QTY ) { $low_any = true; break; }
				}
			}
		} else {
			if ( $product->managing_stock() && ! $product->backorders_allowed() ) {
				$qty = $product->get_stock_quantity();
				if ( is_numeric( $qty ) && $qty > 0 && $qty < self::LOW_STOCK_QTY ) $low_any = true;
			}
		}
		if ( $low_any ) $labels[] = [ 'key' => 'low', 'text' => __( 'Low stock', 'nhg-labels' ) ];

		usort( $labels, function( $a, $b ) {
			$order = [ 'sale' => 1, 'new' => 2, 'low' => 3 ];
			return ( $order[ $a['key'] ] ?? 99 ) <=> ( $order[ $b['key'] ] ?? 99 );
		});

		return $labels;
	}

	private function has_label( array $labels, string $key ) : bool {
		foreach ( $labels as $l ) if ( ( $l['key'] ?? '' ) === $key ) return true;
		return false;
	}

	/** ---------- Output helpers ---------- */
	private function render_labels( array $labels, string $context ) {
		foreach ( $labels as $l ) {
			$key  = $l['key']  ?? '';
			$text = $l['text'] ?? '';

			// Skip NEW on single gallery; we show it as inline text above title instead.
			if ( 'single' === $context && 'new' === $key ) continue;

			// Context-aware filtering
			if ( 'loop-nonsale' === $context && 'sale' === $key ) continue; // handled in thumb
			if ( 'loop-sale'    === $context && 'sale' !== $key ) continue; // only sale here

			// SALE — ribbon (CSS draws triangle; we only output data)
			if ( 'sale' === $key ) {
				$line1 = $l['line1'] ?? __( 'Sale', 'nhg-labels' );
				$line2 = $l['line2'] ?? '';
				printf(
					'<span class="nhg-badge nhg-badge--sale" aria-label="%s" data-l1="%s" data-l2="%s"></span>',
					esc_attr( $l['text'] ?? $line1 . ' ' . $line2 ),
					esc_attr( $line1 ),
					esc_attr( $line2 )
				);
				continue;
			}

			// LOW STOCK — small pill (loop only)
			if ( 'low' === $key ) {
				if ( 'loop-nonsale' === $context && ( is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy() ) ) {
					echo '<span class="ast-shop-product-out-of-stock nhg-low-stock-banner">' . esc_html__( 'Low stock', 'nhg-labels' ) . '</span>';
				}
				continue;
			}

			// NEW — burst
			if ( 'new' === $key ) {
				printf(
					'<span class="nhg-badge nhg-badge--new" aria-label="%s" data-text="%s"></span>',
					esc_attr( $text ),
					esc_attr( $text )
				);
				continue;
			}
		}
	}

	/** ---------- Hooks (loop) ---------- */

	// LOOP: output NON-SALE badges (NEW / LOW) at the card level (<li.product>)
	public function output_loop_non_sale() {
		global $product;
		if ( empty( $product ) || ! $product instanceof WC_Product ) return;

		$labels = $this->get_labels( $product );
		if ( empty( $labels ) ) return;

		echo '<div class="nhg-labels nhg-labels--loop">';
		$this->render_labels( $labels, 'loop-nonsale' );
		echo '</div>';
	}

	// LOOP: output SALE badge INSIDE the thumbnail link (flush to image)
	public function output_loop_sale_inside_thumb() {
		global $product;
		if ( empty( $product ) || ! $product instanceof WC_Product ) return;

		$labels = $this->get_labels( $product );
		if ( ! $this->has_label( $labels, 'sale' ) ) return;

		// This hook runs inside the image <a>, so we wrap a local container.
		echo '<span class="nhg-labels nhg-labels--thumb">';
		$this->render_labels( $labels, 'loop-sale' );
		echo '</span>';
	}

	/** ---------- Single product ---------- */

	// Inject SALE/etc. badges into the first gallery image HTML.
	public function inject_single_badges_into_image_html( $html, $post_id ) {
		if ( ! is_product() ) return $html;
		global $product;
		if ( empty( $product ) || ! $product instanceof WC_Product ) return $html;

		static $done = false;
		if ( $done ) return $html; // only once, before the first image

		$labels = $this->get_labels( $product );
		if ( empty( $labels ) ) return $html;

		ob_start();
		echo '<div class="nhg-labels nhg-labels--single">';
		$this->render_labels( $labels, 'single' );
		echo '</div>';
		$badges = ob_get_clean();

		$done = true;
		return $html . $badges;
	}

	// Single product: "New" label with icon above title (below breadcrumbs)
	public function output_single_new_inline() {
		if ( ! is_product() ) return;
		global $product;
		if ( empty( $product ) || ! $product instanceof WC_Product ) return;

		$labels = $this->get_labels( $product );
		if ( ! $this->has_label( $labels, 'new' ) ) return;

		$icon_url = get_stylesheet_directory_uri() . '/assets/icons/megaphone.svg';

		echo '<div class="nhg-new-inline" aria-label="' . esc_attr__( 'New product', 'nhg-labels' ) . '">'
		. '<img src="' . esc_url( $icon_url ) . '" alt="' . esc_attr__( 'New', 'nhg-labels' ) . '" class="nhg-new-icon" loading="lazy" decoding="async" />'
		. '<span class="nhg-new-text">' . esc_html__( 'New Product!', 'nhg-labels' ) . '</span>'
		. '</div>';
	}
}

new NHG_Product_Labels();
