<?php
/**
 * Shortcode + product-page injection.
 *
 * @package nh-terrace-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_TC_Render {

	private static $printed = false;

	public static function init() {
		add_shortcode( 'nh_terrace_calculator', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ), 30 );
		// After product tabs (10). Nested output buffering keeps this safe inside nh-tax-switcher's summary buffer.
		add_action( 'woocommerce_after_single_product_summary', array( __CLASS__, 'inject_product' ), 12 );
		add_action( 'woocommerce_after_single_product', array( __CLASS__, 'inject_product' ), 5 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_data' ), 15, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'unique_kit_line' ), 20, 1 );
	}

	/**
	 * @param int $product_id
	 */
	public static function should_display( $product_id = 0 ) {
		$s    = NH_TC_Defaults::settings();
		$mode = isset( $s['display_mode'] ) ? $s['display_mode'] : 'all_products';

		if ( 'all_products' === $mode ) {
			return true;
		}

		$product_id = absint( $product_id );
		if ( ! $product_id && function_exists( 'is_product' ) && is_product() ) {
			$product_id = get_queried_object_id();
		}

		if ( 'selected' === $mode ) {
			$ids = array_map( 'absint', (array) ( $s['display_product_ids'] ?? array() ) );
			return $product_id && in_array( $product_id, $ids, true );
		}

		return $product_id && '1' === (string) get_post_meta( $product_id, NH_TC_Defaults::META_ENABLED, true );
	}

	public static function maybe_enqueue() {
		if ( function_exists( 'is_product' ) && is_product() && self::should_display( get_queried_object_id() ) ) {
			self::enqueue();
		}
	}

	/**
	 * @param array<string, string> $classes
	 * @return array<string, string>
	 */
	public static function body_class( $classes ) {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $classes;
		}
		$id = get_queried_object_id();
		if ( $id && '1' === (string) get_post_meta( $id, NH_TC_Defaults::META_HIDE_ATC, true ) ) {
			$classes[] = 'nh-tc-hide-default-cart';
		}
		return $classes;
	}

	public static function inject_product() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		if ( ! self::should_display( get_queried_object_id() ) ) {
			return;
		}
		echo self::markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param array<string, string> $atts
	 */
	public static function shortcode( $atts = array() ) {
		return self::markup();
	}

	public static function markup() {
		if ( self::$printed ) {
			return '';
		}
		self::$printed = true;
		self::enqueue();

		$s  = NH_TC_Defaults::settings();
		$cc = isset( $s['default_cc_mm'] ) ? (int) $s['default_cc_mm'] : 600;

		ob_start();
		?>
		<section class="nh-tc" id="nh-terrace-calculator" data-step="<?php echo esc_attr( $s['step_mm'] ); ?>">
			<div class="nh-tc__intro">
				<h2 class="nh-tc__title"><?php esc_html_e( 'Terrace roof calculator', NH_TC_TD ); ?></h2>
				<p class="nh-tc__lead"><?php esc_html_e( 'Enter the opening size, choose sheets and profiles, and add a complete weather-tight kit to the basket — live catalogue prices, including custom-cut sheets.', NH_TC_TD ); ?></p>
			</div>

			<div class="nh-tc__grid">
				<form class="nh-tc__form" id="nh-tc-form" novalidate>
					<fieldset class="nh-tc__card">
						<legend><?php esc_html_e( 'Roof size', NH_TC_TD ); ?></legend>
						<div class="nh-tc__row nh-tc__row--2">
							<label>
								<span><?php esc_html_e( 'Width (along the wall)', NH_TC_TD ); ?></span>
								<span class="nh-tc__input">
									<input type="number" name="width_mm" inputmode="numeric" required
										min="<?php echo esc_attr( $s['min_width_mm'] ); ?>"
										max="<?php echo esc_attr( $s['max_width_mm'] ); ?>"
										step="<?php echo esc_attr( $s['step_mm'] ); ?>"
										value="<?php echo esc_attr( $s['default_width_mm'] ); ?>">
									<span>mm</span>
								</span>
							</label>
							<label>
								<span><?php esc_html_e( 'Length (projection)', NH_TC_TD ); ?></span>
								<span class="nh-tc__input">
									<input type="number" name="length_mm" inputmode="numeric" required
										min="<?php echo esc_attr( $s['min_length_mm'] ); ?>"
										max="<?php echo esc_attr( $s['max_length_mm'] ); ?>"
										step="<?php echo esc_attr( $s['step_mm'] ); ?>"
										value="<?php echo esc_attr( $s['default_length_mm'] ); ?>">
									<span>mm</span>
								</span>
							</label>
						</div>
						<?php if ( ! empty( $s['show_postcode'] ) ) : ?>
						<label>
							<span><?php esc_html_e( 'Postal code (for shipping)', NH_TC_TD ); ?></span>
							<input type="text" name="postcode" autocomplete="postal-code" maxlength="12">
						</label>
						<?php endif; ?>
						<?php if ( ! empty( $s['show_discount'] ) ) : ?>
						<label>
							<span><?php esc_html_e( 'Discount %', NH_TC_TD ); ?></span>
							<input type="number" name="discount_pct" min="0" max="90" step="0.5" value="0">
						</label>
						<?php endif; ?>
					</fieldset>

					<fieldset class="nh-tc__card">
						<legend><?php esc_html_e( 'Sheets', NH_TC_TD ); ?></legend>
						<div class="nh-tc__row nh-tc__row--2">
							<label>
								<span><?php esc_html_e( 'Construction', NH_TC_TD ); ?></span>
								<select name="construction">
									<option value="single_slope" selected><?php esc_html_e( 'Single-slope (lean-to)', NH_TC_TD ); ?></option>
									<option value="gable"><?php esc_html_e( 'Gable / ridge', NH_TC_TD ); ?></option>
								</select>
							</label>
							<label>
								<span><?php esc_html_e( 'Material', NH_TC_TD ); ?></span>
								<select name="material">
									<option value="multiwall" selected><?php esc_html_e( 'Multiwall polycarbonate', NH_TC_TD ); ?></option>
									<option value="solid"><?php esc_html_e( 'Solid polycarbonate', NH_TC_TD ); ?></option>
								</select>
							</label>
							<label>
								<span><?php esc_html_e( 'Thickness', NH_TC_TD ); ?></span>
								<select name="thickness"></select>
							</label>
							<label>
								<span><?php esc_html_e( 'Colour', NH_TC_TD ); ?></span>
								<select name="colour"></select>
							</label>
							<label>
								<span><?php esc_html_e( 'Frame support spacing (CC)', NH_TC_TD ); ?></span>
								<span class="nh-tc__input">
									<input type="number" name="cc_mm" inputmode="numeric" min="200" max="2000" step="10" placeholder="<?php echo esc_attr( (string) $cc ); ?>" value="">
									<span>mm</span>
								</span>
								<small class="nh-tc__hint" data-rec-cc></small>
							</label>
						</div>
					</fieldset>

					<fieldset class="nh-tc__card">
						<legend><?php esc_html_e( 'Profiles', NH_TC_TD ); ?></legend>
						<div class="nh-tc__row nh-tc__row--2">
							<label>
								<span><?php esc_html_e( 'Connecting profile', NH_TC_TD ); ?></span>
								<select name="connecting_profile">
									<option value="clamping" selected><?php esc_html_e( 'Clamping profile', NH_TC_TD ); ?></option>
									<option value="clamping_lid"><?php esc_html_e( 'Clamping profile with lid', NH_TC_TD ); ?></option>
									<option value="h_plastic"><?php esc_html_e( 'Plastic H-profile', NH_TC_TD ); ?></option>
								</select>
							</label>
							<label>
								<span><?php esc_html_e( 'Connecting colour', NH_TC_TD ); ?></span>
								<select name="connecting_color">
									<option value="silver" selected><?php esc_html_e( 'Silver', NH_TC_TD ); ?></option>
									<option value="clear"><?php esc_html_e( 'Clear', NH_TC_TD ); ?></option>
									<option value="brown"><?php esc_html_e( 'Brown', NH_TC_TD ); ?></option>
								</select>
							</label>
							<label>
								<span><?php esc_html_e( 'Finish profile', NH_TC_TD ); ?></span>
								<select name="finish_profile">
									<option value="f_aluminium" selected><?php esc_html_e( 'F aluminium', NH_TC_TD ); ?></option>
									<option value="u_plastic"><?php esc_html_e( 'U plastic', NH_TC_TD ); ?></option>
									<option value="u_aluminium"><?php esc_html_e( 'U aluminium', NH_TC_TD ); ?></option>
									<option value="l_aluminium"><?php esc_html_e( 'L aluminium', NH_TC_TD ); ?></option>
								</select>
							</label>
							<label>
								<span><?php esc_html_e( 'Finish colour', NH_TC_TD ); ?></span>
								<select name="finish_color">
									<option value="silver" selected><?php esc_html_e( 'Silver', NH_TC_TD ); ?></option>
									<option value="brown"><?php esc_html_e( 'Brown', NH_TC_TD ); ?></option>
									<option value="clear"><?php esc_html_e( 'Clear', NH_TC_TD ); ?></option>
								</select>
							</label>
						</div>
					</fieldset>
				</form>

				<aside class="nh-tc__offer" aria-live="polite">
					<div class="nh-tc__offer-card">
						<h3><?php esc_html_e( 'Offer summary', NH_TC_TD ); ?></h3>
						<p class="nh-tc__offer-meta" data-offer-meta></p>
						<ul class="nh-tc__items" data-offer-items>
							<li class="nh-tc__empty"><?php esc_html_e( 'Enter a size to see the kit.', NH_TC_TD ); ?></li>
						</ul>
						<div class="nh-tc__totals" hidden>
							<div class="nh-tc__tax"><span><?php esc_html_e( 'VAT', NH_TC_TD ); ?></span><span data-offer-tax></span></div>
							<div class="nh-tc__sum"><span><?php esc_html_e( 'Total incl. VAT', NH_TC_TD ); ?></span><span data-offer-total></span></div>
							<p class="nh-tc__ship"><?php esc_html_e( 'Shipping is calculated in the basket from your postal code.', NH_TC_TD ); ?></p>
						</div>
						<button type="button" class="nh-tc__atc button alt" data-offer-atc disabled>
							<?php esc_html_e( 'Add kit to basket', NH_TC_TD ); ?>
						</button>
						<p class="nh-tc__status" data-offer-status hidden></p>
					</div>
				</aside>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function enqueue() {
		$s = NH_TC_Defaults::settings();
		wp_enqueue_style(
			'nh-tc',
			NH_TC_URL . 'assets/css/calculator.css',
			array(),
			NH_TC_VERSION
		);
		wp_enqueue_script(
			'nh-tc',
			NH_TC_URL . 'assets/js/calculator.js',
			array( 'jquery' ),
			NH_TC_VERSION,
			true
		);
		wp_localize_script(
			'nh-tc',
			'NH_TC',
			array(
				'quote'      => WC_AJAX::get_endpoint( 'nh_tc_quote' ),
				'add'        => WC_AJAX::get_endpoint( 'nh_tc_add_to_cart' ),
				'nonce'      => wp_create_nonce( 'nh_tc' ),
				'tree'       => NH_TC_Catalog::option_tree( $s ),
				'connectTree'=> NH_TC_Catalog::color_tree( $s['connecting'] ),
				'finishTree' => NH_TC_Catalog::color_tree( $s['finish'] ),
				'recCc'      => $s['recommended_cc'],
				'defaultCc'  => isset( $s['default_cc_mm'] ) ? (int) $s['default_cc_mm'] : 600,
				'currency'   => class_exists( 'WooCommerce' ) ? NH_TC_Catalog::currency_payload() : array(),
				'taxDisplay' => get_option( 'woocommerce_tax_display_shop', 'incl' ),
				'i18n'       => array(
					'recCc'         => __( 'Leave empty to use %s mm', NH_TC_TD ),
					'sheets'        => __( '%d sheets', NH_TC_TD ),
					'missing'       => __( 'Some items are not in this shop (SKU not found). The rest can still be added.', NH_TC_TD ),
					'adding'        => __( 'Adding kit…', NH_TC_TD ),
					'error'         => __( 'Could not add the kit. Please try again.', NH_TC_TD ),
					'pcs'           => __( '%s pcs', NH_TC_TD ),
					'each'          => __( '%s each', NH_TC_TD ),
					'loading'       => __( 'Updating prices…', NH_TC_TD ),
					'multiwall'     => __( 'Multiwall polycarbonate', NH_TC_TD ),
					'solid'         => __( 'Solid polycarbonate', NH_TC_TD ),
					'clamping'      => __( 'Clamping profile', NH_TC_TD ),
					'clamping_lid'  => __( 'Clamping profile with lid', NH_TC_TD ),
					'h_plastic'     => __( 'Plastic H-profile', NH_TC_TD ),
					'f_aluminium'   => __( 'F aluminium', NH_TC_TD ),
					'f_profile'     => __( 'F aluminium', NH_TC_TD ),
					'u_plastic'     => __( 'U plastic', NH_TC_TD ),
					'u_aluminium'   => __( 'U aluminium', NH_TC_TD ),
					'l_aluminium'   => __( 'L aluminium', NH_TC_TD ),
					'silver'        => __( 'Silver', NH_TC_TD ),
					'clear'         => __( 'Clear', NH_TC_TD ),
					'bronze'        => __( 'Bronze', NH_TC_TD ),
					'opal'          => __( 'Opal', NH_TC_TD ),
					'anthracite'    => __( 'Anthracite', NH_TC_TD ),
					'brown'         => __( 'Brown', NH_TC_TD ),
				),
			)
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $item_data
	 * @param array<string, mixed>             $cart_item
	 * @return array<int, array<string, mixed>>
	 */
	public static function cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['nh_terrace_kit'] ) || ! is_array( $cart_item['nh_terrace_kit'] ) ) {
			return $item_data;
		}
		$kit = $cart_item['nh_terrace_kit'];
		$item_data[] = array(
			'name'  => __( 'Terrace roof kit', NH_TC_TD ),
			'value' => sprintf(
				'%d × %d mm',
				isset( $kit['width'] ) ? (int) $kit['width'] : 0,
				isset( $kit['length'] ) ? (int) $kit['length'] : 0
			),
		);
		return $item_data;
	}

	/**
	 * Keep kit lines from merging with identical catalogue items.
	 *
	 * @param array<string, mixed> $cart_item_data
	 * @return array<string, mixed>
	 */
	public static function unique_kit_line( $cart_item_data ) {
		if ( ! empty( $cart_item_data['nh_terrace_kit'] ) ) {
			$cart_item_data['unique_key'] = wp_generate_uuid4();
		}
		return $cart_item_data;
	}
}
