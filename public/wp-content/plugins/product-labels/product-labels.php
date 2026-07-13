<?php
/**
 * Plugin Name: NHG Product Labels (harmonized)
 * Description: Compact, robust product labels for WooCommerce thumbnails and single products.
 * Version: 1.1.1
 * Author: Your team
 * Text Domain: nhg-product-labels
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'NHG_Product_Labels' ) ) :

class NHG_Product_Labels {

	/**
	 * Plugin text domain.
	 *
	 * @var string
	 */
	protected $text_domain = 'nhg-product-labels';

	/**
	 * Constructor: register hooks
	 */
	public function __construct() {
		// Load translations
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

		// Render thumbnail overlays (inside the product image/link)
		add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'render_thumbnail_badges' ], 5 );

		// Single product: render badges inside the gallery wrapper (preferred)
		// woocommerce_product_thumbnails runs inside the gallery template in many themes (Astra-compatible)
		add_action( 'woocommerce_product_thumbnails', [ $this, 'render_single_badges' ], 5 );

		// Hide core WooCommerce stock HTML on archives (we provide our own)
		add_filter( 'woocommerce_get_stock_html', [ $this, 'filter_woocommerce_get_stock_html' ], 10, 2 );

		// Enqueue styles
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Load plugin textdomain for translations.
	 * Uses plugin languages folder /languages by default.
	 */
	public function load_textdomain() {
		$domain = $this->text_domain;

		// Try the standard WordPress way first (allows WP_LANG_DIR overrides)
		$loaded = load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Fallback: if that fails you can also call load_textdomain() with an explicit .mo path
		if ( ! $loaded ) {
			$mo = dirname( __FILE__ ) . '/languages/' . $domain . '-' . get_locale() . '.mo';
			if ( is_readable( $mo ) ) {
				load_textdomain( $domain, $mo );
			}
		}
	}

	/**
	 * Enqueue CSS file
	 */
	public function enqueue_assets() {
		$css_path = plugins_url( 'css/labels.css', __FILE__ );

		wp_enqueue_style(
			'nhg-product-labels',
			$css_path,
			[],
			'1.1.1'
		);
	}

	/**
	 * Hide Woo core stock HTML on non-single pages so our badges control UI
	 *
	 * @param string     $html    Existing stock HTML
	 * @param WC_Product $product Product object
	 * @return string
	 */
	public function filter_woocommerce_get_stock_html( $html, $product ) {
		// Preserve stock on single product page (for accessibility / product page details)
		if ( is_product() ) {
			return $html;
		}

		// On archives / loops, return empty to avoid duplicate stock markup
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

		// -------- SALE (with percent or range) --------
		if ( $product->is_on_sale() ) {
			$sale_text  = __( 'Sale', $this->text_domain );
			$sale_label = $sale_text;

			// Simple product (non-variable)
			if ( ! $product->is_type( 'variable' ) ) {
				$regular = (float) $product->get_regular_price();
				$sale    = (float) $product->get_sale_price();

				if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
					$pct = (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
					if ( $pct > 0 && $pct < 100 ) {
						$pct_label  = sprintf( _x( '%s %%', 'sale percent', $this->text_domain ), $pct );
						$sale_label = $sale_text . ' ' . $pct_label;
					}
				}
			} else {
				// Variable: compute percent range across variations
				$variation_pcts = [];
				$variation_ids  = $product->get_children();

				if ( is_array( $variation_ids ) && ! empty( $variation_ids ) ) {
					foreach ( $variation_ids as $var_id ) {
						$var = wc_get_product( $var_id );
						if ( ! $var || ! $var->is_on_sale() ) {
							continue;
						}
						$reg = (float) $var->get_regular_price();
						$sal = (float) $var->get_sale_price();

						if ( $reg > 0 && $sal > 0 && $sal < $reg ) {
							$p = (int) round( ( ( $reg - $sal ) / $reg ) * 100 );
							if ( $p > 0 && $p < 100 ) {
								$variation_pcts[] = $p;
							}
						}
					}
				}

				if ( ! empty( $variation_pcts ) ) {
					$variation_pcts = array_unique( $variation_pcts );
					$min = min( $variation_pcts );
					$max = max( $variation_pcts );

					if ( $min === $max ) {
						$pct_label  = sprintf( _x( '%s %%', 'sale percent', $this->text_domain ), $min );
					} else {
						$pct_label = sprintf( _x( '%s-%s %%', 'sale percent range', $this->text_domain ), $min, $max );
					}
					$sale_label = $sale_text . ' ' . $pct_label;
				}
			}

			$labels['sale'] = [
				'key'  => 'sale',
				'text' => $sale_label,
			];
		}

		// -------- NEW (30 days) --------
		$created = $product->get_date_created();
		if ( $created && ( time() - $created->getTimestamp() < 30 * DAY_IN_SECONDS ) ) {
			$labels['new'] = [
				'key'  => 'new',
				'text' => __( 'New', $this->text_domain ),
			];
		}

		// -------- CUSTOM cut / Custom size (integration point) --------
		if ( $this->is_custom_cut_product( $product ) ) {
			$labels['custom'] = [
				'key'  => 'custom',
				'text' => __( 'Custom size', $this->text_domain ),
			];
		}

		// -------- STOCK: out / low --------
		if ( ! $product->is_in_stock() ) {
			$labels['stock'] = [
				'key'        => 'stock',
				'text'       => __( 'Out of stock', $this->text_domain ),
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
						'text'       => __( 'Low stock', $this->text_domain ),
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

		if ( empty( $labels ) ) {
			return;
		}

		// Output container; CSS will absolutely position it over the image.
		printf( '<div class="nhg-labels nhg-labels--thumb" aria-hidden="true">' );

		// 1) Sale
		if ( isset( $labels['sale'] ) ) {
			printf(
				'<span class="nhg-badge nhg-badge--sale">%s</span>',
				esc_html( $labels['sale']['text'] )
			);
		}

		// 2) Custom
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

		// 4) Stock
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
	 * Render badges on single product near gallery (hooked into woocommerce_product_thumbnails)
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

		// Output container that is intended to be inside the gallery wrapper
		echo '<div class="nhg-labels nhg-labels--single" aria-hidden="true">';

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

endif;

// Instantiate
new NHG_Product_Labels();
