<?php
/**
 * Plugin Name: Product Labels
 * Description: Sale %, New (30 days), Low stock (<3). Shows on loop thumbnail and single-product first image.
 * Author: Daiva Reinike
 * Version: 0.6
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NHG_Product_Labels {
	const NEW_DAYS      = 30;
	const LOW_STOCK_QTY = 3;

	public function __construct() {
		// Loop card (badges on thumbnail)
		add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'output_loop' ], 9 );

		// Single product:
		// - Put image badges (SALE, etc.) inside the gallery wrapper
		add_action( 'woocommerce_product_thumbnails', [ $this, 'output_single_inside_gallery' ], 99 );
		// - Print a plain "New" text flag above title (below breadcrumbs)
		add_action( 'woocommerce_single_product_summary', [ $this, 'output_single_new_inline' ], 4 );

		// Styles
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}

	public function enqueue_styles() {
		wp_enqueue_style(
			'nhg-product-labels',
			plugins_url( 'css/labels.css', __FILE__ ),
			[],
			'0.6'
		);
	}

	/** ---------- Label logic ---------- */
	private function get_labels( WC_Product $product ) : array {
		$labels = [];

		// SALE % (supports variable range)
		if ( $product->is_on_sale() ) {
			if ( $product->is_type( 'variable' ) ) {
				$min = 0; $max = 0;
				foreach ( $product->get_children() as $vid ) {
					$child = wc_get_product( $vid );
					if ( ! $child || ! $child->is_on_sale() ) continue;
					$reg  = (float) $child->get_regular_price();
					$sale = (float) $child->get_sale_price();
					if ( $reg > 0 && $sale > 0 && $sale < $reg ) {
						$pct = (int) round( ( ( $reg - $sale ) / $reg ) * 100 );
						if ( $pct > 0 ) {
							$min = $min ? min( $min, $pct ) : $pct;
							$max = max( $max, $pct );
						}
					}
				}
				if ( $max > 0 ) {
					$range = ( $min && $min !== $max ) ? "{$min}–{$max}%" : "{$max}%";
					$labels[] = [
						'key'   => 'sale',
						'text'  => sprintf( __( 'Sale %s', 'nhg' ), $range ), // fallback single-line
						'line1' => __( 'Sale', 'nhg' ),
						'line2' => $range,
					];
				}
			} else {
				$reg  = (float) $product->get_regular_price();
				$sale = (float) $product->get_sale_price();
				if ( $reg > 0 && $sale > 0 && $sale < $reg ) {
					$pct = (int) round( ( ( $reg - $sale ) / $reg ) * 100 );
					if ( $pct > 0 ) {
						$range = "{$pct}%";
						$labels[] = [
							'key'   => 'sale',
							'text'  => sprintf( __( 'Sale %s', 'nhg' ), $range ), // fallback single-line
							'line1' => __( 'Sale', 'nhg' ),
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
				$labels[] = [ 'key' => 'new', 'text' => __( 'New', 'nhg' ) ];
			}
		}

		// LOW STOCK (<3, managed, no backorders)
        // Show on simple products OR if ANY variation qualifies.
        $low_any = false;

        if ( $product->is_type( 'variable' ) ) {
            foreach ( $product->get_children() as $vid ) {
                $child = wc_get_product( $vid );
                if ( ! $child ) continue;

                if ( $child->managing_stock() && ! $child->backorders_allowed() ) {
                    $qty = $child->get_stock_quantity();
                    if ( is_numeric( $qty ) && $qty > 0 && $qty < self::LOW_STOCK_QTY ) {
                        $low_any = true;
                        break; // one is enough
                    }
                }
            }
        } else {
            if ( $product->managing_stock() && ! $product->backorders_allowed() ) {
                $qty = $product->get_stock_quantity();
                if ( is_numeric( $qty ) && $qty > 0 && $qty < self::LOW_STOCK_QTY ) {
                    $low_any = true;
                }
            }
        }

        if ( $low_any ) {
            $labels[] = [ 'key' => 'low', 'text' => __( 'Low stock', 'nhg' ) ];
        }

		// Priority order
		usort( $labels, function( $a, $b ) {
			$order = [ 'sale' => 1, 'new' => 2, 'low' => 3 ];
			return ( $order[ $a['key'] ] ?? 99 ) <=> ( $order[ $b['key'] ] ?? 99 );
		});

		return $labels;
	}

	private function has_label( array $labels, string $key ) : bool {
		foreach ( $labels as $l ) {
			if ( ($l['key'] ?? '') === $key ) return true;
		}
		return false;
	}

	/** ---------- Output helpers ---------- */
	private function render_labels( array $labels, string $context = 'loop' ) {
		foreach ( $labels as $l ) {
			$key  = $l['key']  ?? '';
			$text = $l['text'] ?? '';

			// Skip NEW on single gallery; we show it as inline text above title instead.
			if ( 'single' === $context && 'new' === $key ) continue;

			// A) SALE — blank element; CSS ::after prints the two-line text
			if ( 'sale' === $key ) {
				$line1 = $l['line1'] ?? 'Sale';
				$line2 = $l['line2'] ?? '';
				printf(
					'<span class="nhg-label nhg-label--sale" data-line1="%s" data-line2="%s" data-text="%s"></span>',
					esc_attr( $line1 ),
					esc_attr( $line2 ),
					esc_attr( $line1 . "\n" . $line2 )
				);
				continue;
			}

			// LOW — only on catalog (shop/category/tag) pages
            if ( 'low' === $key ) {
                if ( 'loop' === $context && ( is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy() ) ) {
                    echo '<span class="ast-shop-product-out-of-stock nhg-low-stock-banner">'
                    . esc_html__( 'Low stock', 'nhg' )
                    . '</span>';
                }
                continue;
            }

			// C) NEW — normal badge in loop
			printf(
				'<span class="nhg-label nhg-label--%s">%s</span>',
				esc_attr( $key ),
				esc_html( $text )
			);
		}
	}

	/** ---------- Hooks ---------- */
	public function output_loop() {
		global $product;
		if ( empty( $product ) || ! $product instanceof WC_Product ) return;

		$labels = $this->get_labels( $product );
		if ( empty( $labels ) ) return;

		echo '<div class="nhg-labels nhg-labels--loop">';
		$this->render_labels( $labels, 'loop' );
		echo '</div>';
	}

	// Single product: print badges INSIDE the gallery (SALE etc., but NOT "New")
	public function output_single_inside_gallery() {
		global $product;
		if ( empty( $product ) || ! $product instanceof WC_Product ) return;

		$labels = $this->get_labels( $product );
		if ( empty( $labels ) ) return;

		echo '<div class="nhg-labels nhg-labels--single">';
		$this->render_labels( $labels, 'single' );
		echo '</div>';
	}

	// Single product: plain "New" text above the title (below breadcrumbs)
	public function output_single_new_inline() {
		if ( ! is_product() ) return;
		global $product;
		if ( empty( $product ) || ! $product instanceof WC_Product ) return;

		$labels = $this->get_labels( $product );
		if ( ! $this->has_label( $labels, 'new' ) ) return;

		// Simple inline flag; style via .nhg-new-inline in your CSS if desired
		echo '<div class="nhg-new-inline" aria-label="New product">'
		   . esc_html__( '《 New Product 》', 'nhg' )
		   . '</div>';
	}
}

new NHG_Product_Labels();
