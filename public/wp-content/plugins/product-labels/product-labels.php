<?php
/**
 * Plugin Name: NHG Product Labels (harmonized)
 * Description: Compact, robust product labels for WooCommerce thumbnails and single products.
 * Version: 1.4.0
 * Author: Your team
 * Text Domain: nhg-labels
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
	protected $text_domain = 'nhg-labels';

	/**
	 * Meta key for the Made by Norhage product flag.
	 *
	 * @var string
	 */
	const MADE_META_KEY = '_nhg_made_by_norhage';

	/**
	 * Theme meta key for catalog highlight products (Best sold).
	 */
	const HIGHLIGHT_META_KEY = '_nh_catalog_highlight';

	/**
	 * Constructor: register hooks
	 */
	public function __construct() {
		// Load translations
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

		// Render thumbnail overlays (inside the product image/link)
		add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'render_thumbnail_badges' ], 5 );

		// Catalog-only: Best sold sits under the image inside Astra's summary wrap.
		// Priority 1 so it can flex-order above category/title without touching PDP.
		add_action( 'astra_woo_shop_title_after', [ $this, 'render_below_image_badge' ], 1 );

		// Single product: render badges inside the gallery wrapper (preferred)
		// woocommerce_product_thumbnails runs inside the gallery template in many themes (Astra-compatible)
		add_action( 'woocommerce_product_thumbnails', [ $this, 'render_single_badges' ], 5 );

		// Hide core WooCommerce stock HTML on archives (we provide our own)
		add_filter( 'woocommerce_get_stock_html', [ $this, 'filter_woocommerce_get_stock_html' ], 10, 2 );

		// Product Data → General: Made by Norhage checkbox
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'render_made_field' ] );
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_made_field' ] );

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
		if ( is_admin() ) {
			return;
		}

		$load = is_front_page()
			|| ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() || is_product() ) );

		if ( ! $load ) {
			return;
		}

		$css_path = plugins_url( 'css/labels.css', __FILE__ );

		wp_enqueue_style(
			'nhg-labels',
			$css_path,
			[],
			'1.4.0'
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
	 * Meta key for Made by Norhage.
	 *
	 * @return string
	 */
	public function made_meta_key() {
		return self::MADE_META_KEY;
	}

	/**
	 * Whether the product should show Made by Norhage.
	 *
	 * True when the product brand is Norhage (automatic), or when the
	 * optional Product Data checkbox is ticked.
	 *
	 * @param WC_Product|int $product Product or ID.
	 * @return bool
	 */
	public function is_made_by_norhage( $product ) : bool {
		$id = 0;
		if ( $product instanceof WC_Product ) {
			$id = (int) $product->get_id();
		} else {
			$id = absint( $product );
		}
		if ( ! $id ) {
			return false;
		}
		if ( 'yes' === get_post_meta( $id, $this->made_meta_key(), true ) ) {
			return true;
		}
		return $this->product_has_norhage_brand( $id );
	}

	/**
	 * True when the product is assigned the house brand (slug/name "norhage").
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function product_has_norhage_brand( $product_id ) : bool {
		$product_id = absint( $product_id );
		if ( ! $product_id || ! taxonomy_exists( 'product_brand' ) ) {
			return false;
		}

		$terms = wp_get_post_terms( $product_id, 'product_brand' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return false;
		}

		foreach ( $terms as $term ) {
			$slug = isset( $term->slug ) ? (string) $term->slug : '';
			$name = isset( $term->name ) ? (string) $term->name : '';
			if ( $this->brand_is_norhage( $slug, $name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * House brand match — same rules as the theme helper when available.
	 *
	 * @param string $slug Term slug.
	 * @param string $name Term name.
	 * @return bool
	 */
	public function brand_is_norhage( $slug, $name ) : bool {
		if ( function_exists( 'norhage_brand_is_house' ) ) {
			return (bool) norhage_brand_is_house( $slug, $name );
		}

		$slug = strtolower( trim( (string) $slug ) );
		$name = strtolower( trim( wp_strip_all_tags( (string) $name ) ) );

		return 'norhage' === $slug || 'norhage' === $name;
	}

	/**
	 * Product Data → General checkbox.
	 */
	public function render_made_field() {
		woocommerce_wp_checkbox(
			[
				'id'          => $this->made_meta_key(),
				'label'       => __( 'Made by Norhage', $this->text_domain ),
				'description' => __( 'Show a circular “Made by Norhage” badge on the product image (top left). Products with brand Norhage get this automatically; tick here to force it on.', $this->text_domain ),
			]
		);
	}

	/**
	 * Persist the Made by Norhage flag.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_made_field( $post_id ) {
		$key   = $this->made_meta_key();
		$value = isset( $_POST[ $key ] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_post_meta( (int) $post_id, $key, $value );
	}

	/**
	 * Whether the product is a catalog highlight (theme meta).
	 *
	 * Soft-calls the theme helper when present; otherwise reads meta directly.
	 *
	 * @param WC_Product|int $product Product object or ID.
	 * @return bool
	 */
	public function is_catalog_highlight( $product ) : bool {
		if ( function_exists( 'nh_product_is_catalog_highlight' ) ) {
			return (bool) nh_product_is_catalog_highlight( $product );
		}

		$id = 0;
		if ( $product instanceof WC_Product ) {
			$id = (int) $product->get_id();
		} elseif ( is_numeric( $product ) ) {
			$id = absint( $product );
		}

		if ( $id <= 0 ) {
			return false;
		}

		return 'yes' === get_post_meta( $id, self::HIGHLIGHT_META_KEY, true );
	}

	/**
	 * Compact sale badge copy that fits on a catalog thumbnail.
	 *
	 * @param string     $mode exact|upto|range|plain
	 * @param int        $min  Lower percent.
	 * @param int        $max  Upper percent.
	 * @return array{text:string,title:string}
	 */
	public function format_sale_badge( $mode, $min = 0, $max = 0 ) : array {
		$sale_word = __( 'Sale', $this->text_domain );
		$min       = (int) $min;
		$max       = (int) $max;

		switch ( $mode ) {
			case 'exact':
				return [
					'text'  => '−' . $min . '%',
					'title' => sprintf(
						/* translators: %s: discount percent */
						__( 'Sale %s%%', $this->text_domain ),
						$min
					),
				];
			case 'upto':
				return [
					'text'  => '−' . $max . '%',
					'title' => sprintf(
						/* translators: %s: max discount percent */
						__( 'Sale up to %s%%', $this->text_domain ),
						$max
					),
				];
			case 'range':
				return [
					'text'  => '−' . $min . '–' . $max . '%',
					'title' => sprintf(
						/* translators: 1: min percent 2: max percent */
						__( 'Sale %1$s–%2$s%%', $this->text_domain ),
						$min,
						$max
					),
				];
			default:
				return [
					'text'  => $sale_word,
					'title' => $sale_word,
				];
		}
	}

	/**
	 * Collect label data for a product
	 *
	 * @param WC_Product $product
	 * @return array
	 */
	public function collect_labels( WC_Product $product ) : array {
		$labels = [];

		// -------- SALE (compact percent on the badge; full phrase in title) --------
		if ( $product->is_on_sale() ) {
			$sale = $this->format_sale_badge( 'plain' );

			// Simple product (non-variable)
			if ( ! $product->is_type( 'variable' ) ) {
				$regular = (float) $product->get_regular_price();
				$sale_p  = (float) $product->get_sale_price();

				if ( $regular > 0 && $sale_p > 0 && $sale_p < $regular ) {
					$pct = (int) round( ( ( $regular - $sale_p ) / $regular ) * 100 );
					if ( $pct > 0 && $pct < 100 ) {
						$sale = $this->format_sale_badge( 'exact', $pct, $pct );
					}
				}
			} else {
				// Variable: compute percent range across variations, and detect
				// whether ALL variations have a discount or only SOME of them.
				$variation_pcts   = [];
				$total_variations = 0;
				$discounted_count = 0;
				$variation_ids    = $product->get_children();

				if ( is_array( $variation_ids ) && ! empty( $variation_ids ) ) {
					foreach ( $variation_ids as $var_id ) {
						$var = wc_get_product( $var_id );

						// Skip variations that don't exist / aren't valid products
						if ( ! $var || ! $var->exists() ) {
							continue;
						}

						$total_variations++;

						if ( ! $var->is_on_sale() ) {
							continue;
						}

						$reg = (float) $var->get_regular_price();
						$sal = (float) $var->get_sale_price();

						if ( $reg > 0 && $sal > 0 && $sal < $reg ) {
							$p = (int) round( ( ( $reg - $sal ) / $reg ) * 100 );
							if ( $p > 0 && $p < 100 ) {
								$variation_pcts[] = $p;
								$discounted_count++;
							}
						}
					}
				}

				if ( ! empty( $variation_pcts ) ) {
					$variation_pcts = array_unique( $variation_pcts );
					$min = min( $variation_pcts );
					$max = max( $variation_pcts );

					$all_discounted = ( $total_variations > 0 && $discounted_count === $total_variations );

					if ( ! $all_discounted ) {
						$sale = $this->format_sale_badge( 'upto', $max, $max );
					} elseif ( $min === $max ) {
						$sale = $this->format_sale_badge( 'exact', $min, $min );
					} else {
						$sale = $this->format_sale_badge( 'range', $min, $max );
					}
				}
			}

			$labels['sale'] = [
				'key'   => 'sale',
				'text'  => $sale['text'],
				'title' => $sale['title'],
			];
		}

		// -------- MADE BY NORHAGE (manual product flag) --------
		if ( $this->is_made_by_norhage( $product ) ) {
			$labels['made'] = [
				'key'   => 'made',
				'line1' => __( 'Made by', $this->text_domain ),
				'line2' => 'Norhage',
				'text'  => __( 'Made by Norhage', $this->text_domain ),
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
	 * Print badge markup for a collected labels array.
	 *
	 * Order: sale → custom (right) → made → new (left) → stock (bottom).
	 * Made is before New so CSS can stack New under Made when both show.
	 *
	 * @param array $labels Labels from collect_labels().
	 */
	private function print_badges( array $labels ) {
		if ( isset( $labels['sale'] ) ) {
			$title = isset( $labels['sale']['title'] ) ? $labels['sale']['title'] : $labels['sale']['text'];
			printf(
				'<span class="nhg-badge nhg-badge--sale" title="%s">%s</span>',
				esc_attr( $title ),
				esc_html( $labels['sale']['text'] )
			);
		}

		if ( isset( $labels['custom'] ) ) {
			printf(
				'<span class="nhg-badge nhg-badge--custom">%s</span>',
				esc_html( $labels['custom']['text'] )
			);
		}

		if ( isset( $labels['made'] ) ) {
			$line1 = isset( $labels['made']['line1'] ) ? $labels['made']['line1'] : __( 'Made by', $this->text_domain );
			$line2 = isset( $labels['made']['line2'] ) ? $labels['made']['line2'] : 'Norhage';
			$title = isset( $labels['made']['text'] ) ? $labels['made']['text'] : __( 'Made by Norhage', $this->text_domain );
			printf(
				'<span class="nhg-badge nhg-badge--made" title="%s"><span class="nhg-made-text"><span class="nhg-made-text__line">%s</span><span class="nhg-made-text__line">%s</span></span></span>',
				esc_attr( $title ),
				esc_html( $line1 ),
				esc_html( $line2 )
			);
		}

		if ( isset( $labels['new'] ) ) {
			printf(
				'<span class="nhg-badge nhg-badge--new"><span class="nhg-new-text">%s</span></span>',
				esc_html( $labels['new']['text'] )
			);
		}

		if ( isset( $labels['stock'] ) ) {
			$stock_type = isset( $labels['stock']['stock_type'] ) ? $labels['stock']['stock_type'] : 'out';
			printf(
				'<span class="nhg-badge nhg-badge--stock" data-stock="%s">%s</span>',
				esc_attr( $stock_type ),
				esc_html( $labels['stock']['text'] )
			);
		}
	}

	/**
	 * Render badges inside the thumbnail area (archive loop).
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
		echo '<div class="nhg-labels nhg-labels--thumb" aria-hidden="true">';
		$this->print_badges( $labels );
		echo '</div>';
	}

	/**
	 * Catalog-only Best sold bar under the product image (highlight products).
	 *
	 * Lives in the Astra summary wrap via astra_woo_shop_title_after, then
	 * flex-orders above category/title. Never printed on the single product page.
	 */
	public function render_below_image_badge() {
		if ( is_admin() ) {
			return;
		}

		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( ! $this->is_catalog_highlight( $product ) ) {
			return;
		}

		// Hooked only to astra_woo_shop_title_after (loop/related cards), never
		// astra_woo_single_title_after — keeps the single gallery/custom PDP alone.
		$text = __( 'Best sold', $this->text_domain );

		printf(
			'<div class="nhg-labels nhg-labels--below" aria-hidden="true"><span class="nhg-badge nhg-badge--best">%s</span></div>',
			esc_html( $text )
		);
	}

	/**
	 * Render badges on single product near gallery (hooked into woocommerce_product_thumbnails).
	 *
	 * Intentionally unchanged aside from shared collect/print helpers — gallery
	 * markup and product-page CSS are fragile; Best sold is catalog-only.
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
		$this->print_badges( $labels );
		echo '</div>';
	}
}

endif;

// Instantiate
new NHG_Product_Labels();
