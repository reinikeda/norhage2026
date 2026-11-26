<?php
/**
 * Plugin Name: NHG Product Labels (harmonized)
 * Description: Compact, robust product labels for WooCommerce thumbnails and single products.
 * Version: 1.4.4
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
	 * Per-request label cache (related products were collecting twice each).
	 *
	 * @var array<int,array>
	 */
	protected $label_cache = [];

	/**
	 * Whether Made by was already injected into the main gallery image HTML.
	 *
	 * @var bool
	 */
	protected $single_made_injected = false;

	/**
	 * Constructor: register hooks
	 */
	public function __construct() {
		// Load translations
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

		// Render thumbnail overlays (inside the product image/link)
		add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'render_thumbnail_badges' ], 5 );

		// Catalog-only: Sale + Best sold under the image (Astra summary wrap).
		// Priority 1 so it can flex-order above category/title without touching PDP.
		add_action( 'astra_woo_shop_title_after', [ $this, 'render_below_image_badge' ], 1 );

		// Single: Made by on the main gallery image (survives Flexslider/gallery JS).
		add_filter( 'woocommerce_single_product_image_thumbnail_html', [ $this, 'inject_single_made_badge' ], 20, 2 );

		// Single: Save € note under the summary price (updates with variation).
		add_action( 'woocommerce_single_product_summary', [ $this, 'render_single_save_note' ], 11 );
		add_filter( 'woocommerce_available_variation', [ $this, 'add_variation_save_html' ], 10, 3 );

		// Hide core WooCommerce stock HTML in loops (we provide our own badge)
		add_filter( 'woocommerce_get_stock_html', [ $this, 'filter_woocommerce_get_stock_html' ], 10, 2 );

		// Hide Woo's default loop sale flash (we own Sale below the image)
		add_action( 'init', [ $this, 'remove_default_loop_sale_flash' ] );

		// Product Data → General: Made by Norhage checkbox
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'render_made_field' ] );
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_made_field' ] );

		// Enqueue styles (+ PDP save JS)
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
	 * Enqueue CSS (+ PDP Save note script).
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
			'1.4.4'
		);

		if ( function_exists( 'is_product' ) && is_product() ) {
			$js_path = plugins_url( 'js/single-save-note.js', __FILE__ );
			$deps    = [ 'jquery' ];
			if ( wp_script_is( 'wc-add-to-cart-variation', 'registered' ) ) {
				$deps[] = 'wc-add-to-cart-variation';
			}
			wp_enqueue_script(
				'nhg-single-save-note',
				$js_path,
				$deps,
				'1.4.4',
				true
			);
		}
	}

	/**
	 * Hide Woo core stock HTML in product loops so our badges control UI.
	 *
	 * Keeps stock on the main single-product buy box; blanks it in shop,
	 * category, related, and upsell loops (including on the product page).
	 *
	 * @param string     $html    Existing stock HTML
	 * @param WC_Product $product Product object
	 * @return string
	 */
	public function filter_woocommerce_get_stock_html( $html, $product ) {
		unset( $product );

		// Main single-product summary (not related/upsells/cross-sells).
		if ( function_exists( 'is_product' ) && is_product() ) {
			$loop_name = function_exists( 'wc_get_loop_prop' ) ? wc_get_loop_prop( 'name' ) : '';
			if ( empty( $loop_name ) ) {
				return $html;
			}
		}

		// Shop, archives, related, upsells, etc. — we own the badge.
		return '';
	}

	/**
	 * Remove WooCommerce default "Sale!" flash on catalog cards.
	 */
	public function remove_default_loop_sale_flash() {
		remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
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
	 * Format a money amount for badge text (plain, no HTML).
	 *
	 * @param float $amount Amount in store currency.
	 * @return string
	 */
	public function format_money_plain( $amount ) : string {
		$amount = (float) $amount;
		if ( function_exists( 'wc_price' ) ) {
			return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
		}
		return number_format( $amount, 2, ',', ' ' ) . ' €';
	}

	/**
	 * Sale badge copy as euro savings (shown under the catalog image).
	 *
	 * @param string $mode   exact|upto|plain
	 * @param float  $amount Savings amount.
	 * @return array{text:string,title:string}
	 */
	public function format_sale_badge( $mode, $amount = 0 ) : array {
		$sale_word = __( 'Sale', $this->text_domain );
		$money     = $this->format_money_plain( $amount );

		switch ( $mode ) {
			case 'exact':
				$text = sprintf(
					/* translators: %s: formatted money amount saved */
					__( 'Save %s', $this->text_domain ),
					$money
				);
				return [
					'text'  => $text,
					'title' => $text,
				];
			case 'upto':
				$text = sprintf(
					/* translators: %s: formatted max money amount saved */
					__( 'Save up to %s', $this->text_domain ),
					$money
				);
				return [
					'text'  => $text,
					'title' => $text,
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
		$id = (int) $product->get_id();
		if ( $id && isset( $this->label_cache[ $id ] ) ) {
			return $this->label_cache[ $id ];
		}

		$labels = [];

		// Out of stock: only the stock badge — drop Sale / Made / New / Custom.
		if ( ! $product->is_in_stock() ) {
			$labels['stock'] = [
				'key'        => 'stock',
				'text'       => __( 'Out of stock', $this->text_domain ),
				'stock_type' => 'out',
			];
			if ( $id ) {
				$this->label_cache[ $id ] = $labels;
			}
			return $labels;
		}

		// -------- SALE (euro savings; catalog shows this under the image) --------
		if ( $product->is_on_sale() ) {
			$sale = $this->build_sale_label( $product );
			if ( $sale ) {
				$labels['sale'] = $sale;
			}
		}

		// -------- MADE BY NORHAGE --------
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

		// -------- CUSTOM size --------
		if ( $this->is_custom_cut_product( $product ) ) {
			$labels['custom'] = [
				'key'  => 'custom',
				'text' => __( 'Custom size', $this->text_domain ),
			];
		}

		// -------- LOW STOCK (in-stock only; OOS returned early above) --------
		if ( $product->managing_stock() ) {
			$stock_qty = $product->get_stock_quantity();
			$low       = method_exists( $product, 'get_low_stock_amount' ) ? $product->get_low_stock_amount() : '';
			if ( $low === '' || $low === null ) {
				$low = absint( get_option( 'woocommerce_notify_low_stock_amount', 2 ) );
			} else {
				$low = absint( $low );
			}

			if ( $stock_qty !== null && $low > 0 && $stock_qty > 0 && $stock_qty <= $low ) {
				$labels['stock'] = [
					'key'        => 'stock',
					'text'       => __( 'Low stock', $this->text_domain ),
					'stock_type' => 'low',
				];
			}
		}

		if ( $id ) {
			$this->label_cache[ $id ] = $labels;
		}

		return $labels;
	}

	/**
	 * Build sale label from euro savings without N+1 variation loads.
	 *
	 * Uses Woo's cached get_variation_prices() for variable products so single
	 * product pages (main + related cards) stay fast.
	 *
	 * @param WC_Product $product Product.
	 * @return array{key:string,text:string,title:string}|null
	 */
	public function build_sale_label( WC_Product $product ) {
		$sale = $this->format_sale_badge( 'plain' );

		if ( ! $product->is_type( 'variable' ) ) {
			$regular = (float) $product->get_regular_price();
			$sale_p  = (float) $product->get_sale_price();

			if ( $regular > 0 && $sale_p > 0 && $sale_p < $regular ) {
				$save = $regular - $sale_p;
				if ( $save > 0 ) {
					$sale = $this->format_sale_badge( 'exact', $save );
				}
			}
		} elseif ( method_exists( $product, 'get_variation_prices' ) ) {
			$prices           = $product->get_variation_prices( true );
			$regular_prices   = isset( $prices['regular_price'] ) && is_array( $prices['regular_price'] ) ? $prices['regular_price'] : [];
			$sale_prices      = isset( $prices['sale_price'] ) && is_array( $prices['sale_price'] ) ? $prices['sale_price'] : [];
			$variation_saves  = [];
			$total_variations = count( $regular_prices );
			$discounted_count = 0;

			foreach ( $regular_prices as $vid => $reg ) {
				$reg = (float) $reg;
				$sal = isset( $sale_prices[ $vid ] ) ? (float) $sale_prices[ $vid ] : 0.0;

				if ( $reg > 0 && $sal > 0 && $sal < $reg ) {
					$save = $reg - $sal;
					if ( $save > 0 ) {
						$variation_saves[] = $save;
						$discounted_count++;
					}
				}
			}

			if ( ! empty( $variation_saves ) ) {
				$max = max( $variation_saves );
				$min = min( $variation_saves );
				$all = ( $total_variations > 0 && $discounted_count === $total_variations );

				if ( ! $all || $min !== $max ) {
					$sale = $this->format_sale_badge( 'upto', $max );
				} else {
					$sale = $this->format_sale_badge( 'exact', $min );
				}
			}
		}

		return [
			'key'   => 'sale',
			'text'  => $sale['text'],
			'title' => $sale['title'],
		];
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
	 * Print on-image badge markup.
	 *
	 * Catalog: custom (right) → made → new (left) → stock (bottom).
	 * Sale is printed under the image via render_below_image_badge().
	 * Single: may also include sale on the gallery.
	 *
	 * @param array $labels Labels from collect_labels().
	 * @param bool  $include_sale Whether to print sale on the image.
	 */
	private function print_badges( array $labels, $include_sale = false ) {
		if ( $include_sale && isset( $labels['sale'] ) ) {
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
	 * Sale is omitted here — it renders under the image.
	 */
	public function render_thumbnail_badges() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$labels = $this->collect_labels( $product );
		unset( $labels['sale'] );

		if ( empty( $labels ) ) {
			return;
		}

		echo '<div class="nhg-labels nhg-labels--thumb" aria-hidden="true">';
		$this->print_badges( $labels, false );
		echo '</div>';
	}

	/**
	 * Catalog under-image bars: Sale (euro savings) + Best sold (highlight).
	 *
	 * Hooked only to astra_woo_shop_title_after (loop/related cards).
	 * Skipped entirely for out-of-stock products (stock badge is on-image only).
	 */
	public function render_below_image_badge() {
		if ( is_admin() ) {
			return;
		}

		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( ! $product->is_in_stock() ) {
			return;
		}

		$labels  = $this->collect_labels( $product );
		$has_sale = isset( $labels['sale'] );
		$has_best = $this->is_catalog_highlight( $product );

		if ( ! $has_sale && ! $has_best ) {
			return;
		}

		echo '<div class="nhg-labels nhg-labels--below" aria-hidden="true">';

		if ( $has_sale ) {
			$title = isset( $labels['sale']['title'] ) ? $labels['sale']['title'] : $labels['sale']['text'];
			printf(
				'<span class="nhg-badge nhg-badge--sale nhg-badge--sale-below" title="%s">%s</span>',
				esc_attr( $title ),
				esc_html( $labels['sale']['text'] )
			);
		}

		if ( $has_best ) {
			printf(
				'<span class="nhg-badge nhg-badge--best">%s</span>',
				esc_html( __( 'Best sold', $this->text_domain ) )
			);
		}

		echo '</div>';
	}

	/**
	 * Inject Made by into the main single-product gallery image HTML.
	 *
	 * Gallery JS (Flexslider) often drops loose siblings from
	 * woocommerce_product_thumbnails; nesting inside the first image figure
	 * keeps the badge visible. PDP shows Made by only — no New / Custom /
	 * Sale / Stock on the photo (stock + Save live elsewhere).
	 *
	 * @param string $html          Image HTML.
	 * @param int    $attachment_id Attachment ID.
	 * @return string
	 */
	public function inject_single_made_badge( $html, $attachment_id ) {
		unset( $attachment_id );

		if ( $this->single_made_injected || ! function_exists( 'is_product' ) || ! is_product() ) {
			return $html;
		}

		global $product;
		if ( ! $product instanceof WC_Product || ! $this->is_made_by_norhage( $product ) ) {
			return $html;
		}

		$this->single_made_injected = true;

		$labels = [
			'made' => [
				'key'   => 'made',
				'line1' => __( 'Made by', $this->text_domain ),
				'line2' => 'Norhage',
				'text'  => __( 'Made by Norhage', $this->text_domain ),
			],
		];

		ob_start();
		echo '<div class="nhg-labels nhg-labels--single" aria-hidden="true">';
		$this->print_badges( $labels, false );
		echo '</div>';
		$badge = ob_get_clean();

		if ( ! is_string( $html ) || '' === $html ) {
			return $badge;
		}

		// Prefer nesting inside the first wrapper element so absolute positioning works.
		$injected = preg_replace( '/^(<\s*[a-zA-Z0-9]+\b[^>]*>)/', '$1' . $badge, $html, 1 );
		return is_string( $injected ) ? $injected : ( $badge . $html );
	}

	/**
	 * Print Save € note under the single-product summary price.
	 *
	 * Variable products start with "Save up to …" (or exact if all equal),
	 * then single-save-note.js swaps in the selected variation amount.
	 */
	public function render_single_save_note() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		global $product;
		if ( ! $product instanceof WC_Product || ! $product->is_on_sale() || ! $product->is_in_stock() ) {
			return;
		}

		$sale = $this->build_sale_label( $product );
		if ( empty( $sale['text'] ) ) {
			return;
		}

		printf(
			'<p class="nhg-save-note" data-nhg-save-default="%s">%s</p>',
			esc_attr( $sale['text'] ),
			esc_html( $sale['text'] )
		);
	}

	/**
	 * Attach preformatted Save HTML to each variation for the PDP note.
	 *
	 * @param array                $data      Variation data for JS.
	 * @param WC_Product           $product   Parent variable product.
	 * @param WC_Product_Variation $variation Variation.
	 * @return array
	 */
	public function add_variation_save_html( $data, $product, $variation ) {
		unset( $product );

		if ( ! is_array( $data ) ) {
			return $data;
		}

		$data['nhg_save_html'] = '';

		if ( ! $variation instanceof WC_Product ) {
			return $data;
		}

		$reg  = isset( $data['display_regular_price'] ) ? (float) $data['display_regular_price'] : 0.0;
		$sale = isset( $data['display_price'] ) ? (float) $data['display_price'] : 0.0;

		if ( $reg > 0 && $sale > 0 && $sale < $reg ) {
			$save = $reg - $sale;
			if ( $save > 0 ) {
				$badge                 = $this->format_sale_badge( 'exact', $save );
				$data['nhg_save_html'] = $badge['text'];
			}
		}

		return $data;
	}
}

endif;

// Instantiate
new NHG_Product_Labels();
