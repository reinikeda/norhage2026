<?php
/**
 * Shortcode + product-page injection (same pattern as terrace calculator).
 *
 * @package nh-pvc-strip-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_PSC_Render {

	private static $printed = false;

	public static function init() {
		add_shortcode( 'nh_pvc_strip_calculator', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ), 30 );
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
		$s    = NH_PSC_Defaults::settings();
		$mode = isset( $s['display_mode'] ) ? $s['display_mode'] : 'flagged';

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

		return $product_id && '1' === (string) get_post_meta( $product_id, NH_PSC_Defaults::META_ENABLED, true );
	}

	public static function maybe_enqueue() {
		if ( function_exists( 'is_product' ) && is_product() && self::should_display( get_queried_object_id() ) ) {
			self::enqueue();
		}
	}

	/**
	 * @param array<int, string> $classes
	 * @return array<int, string>
	 */
	public static function body_class( $classes ) {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $classes;
		}
		$id = get_queried_object_id();
		if ( $id && '1' === (string) get_post_meta( $id, NH_PSC_Defaults::META_HIDE_ATC, true ) ) {
			$classes[] = 'nh-psc-hide-default-cart';
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

	public static function enqueue() {
		if ( wp_script_is( 'nh-psc-calculator', 'enqueued' ) ) {
			return;
		}
		$s = NH_PSC_Defaults::settings();
		wp_enqueue_style(
			'nh-psc-calculator',
			NH_PSC_URL . 'assets/css/calculator.css',
			array(),
			NH_PSC_VERSION
		);
		wp_enqueue_script(
			'nh-psc-calculator',
			NH_PSC_URL . 'assets/js/calculator.js',
			array(),
			NH_PSC_VERSION,
			true
		);
		wp_localize_script(
			'nh-psc-calculator',
			'NH_PSC',
			array(
				'quote'        => WC_AJAX::get_endpoint( 'nh_psc_quote' ),
				'add'          => WC_AJAX::get_endpoint( 'nh_psc_add_to_cart' ),
				'nonce'        => wp_create_nonce( 'nh_psc' ),
				'showPostcode' => ! empty( $s['show_postcode'] ),
				'taxDisplay'   => get_option( 'woocommerce_tax_display_shop', 'incl' ),
				'currency'     => class_exists( 'WooCommerce' ) ? NH_PSC_Catalog::currency_payload() : array(),
				'i18n'         => array(
					'continue'   => __( 'Continue', NH_PSC_TD ),
					'reviewKit'  => __( 'Review kit', NH_PSC_TD ),
					'addKit'     => __( 'Add kit to basket', NH_PSC_TD ),
					'loading'    => __( 'Enter an opening size to see the kit.', NH_PSC_TD ),
					'missing'    => __( 'Some items are not mapped in this shop. The rest can still be added.', NH_PSC_TD ),
					'adding'     => __( 'Adding kit…', NH_PSC_TD ),
					'error'      => __( 'Could not add the kit. Please try again.', NH_PSC_TD ),
					'strips'     => __( '%d strips', NH_PSC_TD ),
					'metres'     => __( '%s m total', NH_PSC_TD ),
					'recWidth'   => __( 'Recommended for this height: %d mm strips.', NH_PSC_TD ),
					'groupStrips'=> __( 'Strips', NH_PSC_TD ),
					'groupHardware' => __( 'Hardware', NH_PSC_TD ),
					'pcs'        => __( '%s pcs', NH_PSC_TD ),
					'each'       => __( '%s each', NH_PSC_TD ),
					'mm'         => __( '%d mm', NH_PSC_TD ),
				),
			)
		);
	}

	public static function markup() {
		if ( self::$printed ) {
			return '';
		}
		self::$printed = true;
		self::enqueue();

		$s         = NH_PSC_Defaults::settings();
		$show_post = ! empty( $s['show_postcode'] );
		$rec       = NH_PSC_Defaults::recommend_strip_width( (int) $s['default_height_mm'] );

		ob_start();
		?>
		<section class="nh-psc" id="nh-pvc-strip-calculator" data-wizard-step="1" data-show-postcode="<?php echo $show_post ? '1' : '0'; ?>">
			<div class="nh-psc__intro">
				<h2 class="nh-psc__title"><?php esc_html_e( 'PVC strip curtain calculator', NH_PSC_TD ); ?></h2>
				<p class="nh-psc__lead"><?php esc_html_e( 'Three short steps: measure the opening, choose strips and overlap, then add a complete kit — strips, hangers and rail.', NH_PSC_TD ); ?></p>
			</div>

			<nav class="nh-psc__progress" aria-label="<?php esc_attr_e( 'Calculator steps', NH_PSC_TD ); ?>">
				<button type="button" class="nh-psc__progress-step is-active" data-goto="1" aria-current="step">
					<span class="nh-psc__progress-no">1</span>
					<span class="nh-psc__progress-label"><?php esc_html_e( 'Opening', NH_PSC_TD ); ?></span>
				</button>
				<button type="button" class="nh-psc__progress-step" data-goto="2">
					<span class="nh-psc__progress-no">2</span>
					<span class="nh-psc__progress-label"><?php esc_html_e( 'Strips', NH_PSC_TD ); ?></span>
				</button>
				<button type="button" class="nh-psc__progress-step" data-goto="3">
					<span class="nh-psc__progress-no">3</span>
					<span class="nh-psc__progress-label"><?php esc_html_e( 'Kit', NH_PSC_TD ); ?></span>
				</button>
			</nav>

			<div class="nh-psc__grid">
				<form class="nh-psc__form" id="nh-psc-form" novalidate>
					<div class="nh-psc__panel is-active" data-panel="1">
						<fieldset class="nh-psc__card">
							<legend><?php esc_html_e( 'Mounting', NH_PSC_TD ); ?></legend>
							<div class="nh-psc__choices">
								<label class="nh-psc__choice">
									<input type="radio" name="mounting" value="face" checked>
									<span class="nh-psc__choice-art" aria-hidden="true">
										<svg viewBox="0 0 88 56" width="88" height="56"><rect x="10" y="10" width="68" height="8" rx="1" fill="currentColor"/><path d="M18 18 V48 H28 V18 M40 18 V48 H50 V18 M62 18 V48 H72 V18" fill="none" stroke="currentColor" stroke-width="3"/></svg>
									</span>
									<span class="nh-psc__choice-title"><?php esc_html_e( 'Above the opening', NH_PSC_TD ); ?></span>
									<span class="nh-psc__choice-sub"><?php esc_html_e( 'Rail fixed to the wall face', NH_PSC_TD ); ?></span>
								</label>
								<label class="nh-psc__choice">
									<input type="radio" name="mounting" value="soffit">
									<span class="nh-psc__choice-art" aria-hidden="true">
										<svg viewBox="0 0 88 56" width="88" height="56"><path d="M14 8 H74 V48 H14 Z" fill="none" stroke="currentColor" stroke-width="2"/><rect x="18" y="12" width="52" height="6" fill="currentColor"/><path d="M24 18 V42 M40 18 V42 M56 18 V42" stroke="currentColor" stroke-width="3"/></svg>
									</span>
									<span class="nh-psc__choice-title"><?php esc_html_e( 'Into the opening', NH_PSC_TD ); ?></span>
									<span class="nh-psc__choice-sub"><?php esc_html_e( 'Rail under the lintel / soffit', NH_PSC_TD ); ?></span>
								</label>
							</div>
							<div class="nh-psc__measure-guide" aria-hidden="true">
								<svg class="nh-psc__measure-svg" viewBox="0 0 320 140" width="320" height="140">
									<path d="M40 28 H280 V112 H40 Z" fill="currentColor" opacity=".08" stroke="currentColor" stroke-width="2"/>
									<path d="M48 36 H272" stroke="currentColor" stroke-width="6"/>
									<path d="M56 44 V104 M96 44 V104 M136 44 V104 M176 44 V104 M216 44 V104 M256 44 V104" stroke="currentColor" stroke-width="3" opacity=".7"/>
									<path d="M40 124 H280" stroke="currentColor" stroke-width="2"/>
									<text x="160" y="136" text-anchor="middle" class="nh-psc__measure-text"><?php esc_html_e( 'Opening width', NH_PSC_TD ); ?></text>
									<path d="M300 28 V112" stroke="currentColor" stroke-width="2"/>
									<text x="312" y="76" text-anchor="middle" class="nh-psc__measure-text" transform="rotate(90 312 76)"><?php esc_html_e( 'Height', NH_PSC_TD ); ?></text>
								</svg>
							</div>
						</fieldset>

						<fieldset class="nh-psc__card">
							<legend><?php esc_html_e( 'Opening size', NH_PSC_TD ); ?></legend>
							<div class="nh-psc__size">
								<label>
									<span><?php esc_html_e( 'Opening width', NH_PSC_TD ); ?></span>
									<span class="nh-psc__input">
										<input type="number" name="width_mm" inputmode="numeric" required
											min="<?php echo esc_attr( $s['min_width_mm'] ); ?>"
											max="<?php echo esc_attr( $s['max_width_mm'] ); ?>"
											step="<?php echo esc_attr( $s['step_mm'] ); ?>"
											value="<?php echo esc_attr( $s['default_width_mm'] ); ?>">
										<span>mm</span>
									</span>
								</label>
								<label>
									<span><?php esc_html_e( 'Opening height', NH_PSC_TD ); ?></span>
									<span class="nh-psc__input">
										<input type="number" name="height_mm" inputmode="numeric" required
											min="<?php echo esc_attr( $s['min_height_mm'] ); ?>"
											max="<?php echo esc_attr( $s['max_height_mm'] ); ?>"
											step="<?php echo esc_attr( $s['step_mm'] ); ?>"
											value="<?php echo esc_attr( $s['default_height_mm'] ); ?>">
										<span>mm</span>
									</span>
									<small class="nh-psc__hint" data-mount-hint></small>
								</label>
							</div>
							<p class="nh-psc__notice" data-calc-dims></p>
						</fieldset>
					</div>

					<div class="nh-psc__panel" data-panel="2" hidden>
						<fieldset class="nh-psc__card">
							<legend><?php esc_html_e( 'Strip size', NH_PSC_TD ); ?></legend>
							<p class="nh-psc__ask"><?php esc_html_e( 'Taller openings need wider, thicker strips.', NH_PSC_TD ); ?></p>
							<small class="nh-psc__hint" data-rec-width><?php echo esc_html( sprintf( __( 'Recommended for this height: %d mm strips.', NH_PSC_TD ), $rec ) ); ?></small>
							<div class="nh-psc__thickness" role="group">
								<label class="nh-psc__thk">
									<input type="radio" name="strip_width_mm" value="200" <?php checked( $rec, 200 ); ?>>
									<span class="nh-psc__thk-mm">200 × 2 mm</span>
									<span class="nh-psc__thk-sub"><?php esc_html_e( 'Up to 2.5 m high', NH_PSC_TD ); ?></span>
								</label>
								<label class="nh-psc__thk">
									<input type="radio" name="strip_width_mm" value="300" <?php checked( $rec, 300 ); ?>>
									<span class="nh-psc__thk-mm">300 × 3 mm</span>
									<span class="nh-psc__thk-sub"><?php esc_html_e( '2.5–4 m high', NH_PSC_TD ); ?></span>
								</label>
								<label class="nh-psc__thk">
									<input type="radio" name="strip_width_mm" value="400" <?php checked( $rec, 400 ); ?>>
									<span class="nh-psc__thk-mm">400 × 4 mm</span>
									<span class="nh-psc__thk-sub"><?php esc_html_e( '4 m and taller', NH_PSC_TD ); ?></span>
								</label>
							</div>
						</fieldset>

						<fieldset class="nh-psc__card">
							<legend><?php esc_html_e( 'Overlap', NH_PSC_TD ); ?></legend>
							<div class="nh-psc__chips nh-psc__chips--stack">
								<label class="nh-psc__chip nh-psc__chip--wide">
									<input type="radio" name="overlap" value="min">
									<span class="nh-psc__chip-title"><?php esc_html_e( 'Minimum', NH_PSC_TD ); ?></span>
									<span class="nh-psc__chip-sub"><?php esc_html_e( 'Easier passage · indoor pedestrian traffic', NH_PSC_TD ); ?></span>
								</label>
								<label class="nh-psc__chip nh-psc__chip--wide">
									<input type="radio" name="overlap" value="med" checked>
									<span class="nh-psc__chip-title"><?php esc_html_e( 'Medium', NH_PSC_TD ); ?></span>
									<span class="nh-psc__chip-sub"><?php esc_html_e( 'Balanced seal · most indoor openings', NH_PSC_TD ); ?></span>
								</label>
								<label class="nh-psc__chip nh-psc__chip--wide">
									<input type="radio" name="overlap" value="max">
									<span class="nh-psc__chip-title"><?php esc_html_e( 'Maximum', NH_PSC_TD ); ?></span>
									<span class="nh-psc__chip-sub"><?php esc_html_e( 'Best draught barrier · outdoor & cold rooms', NH_PSC_TD ); ?></span>
								</label>
							</div>
						</fieldset>

						<fieldset class="nh-psc__card">
							<legend><?php esc_html_e( 'Strip type & grade', NH_PSC_TD ); ?></legend>
							<div class="nh-psc__size">
								<label>
									<span><?php esc_html_e( 'Surface', NH_PSC_TD ); ?></span>
									<select name="strip_type">
										<option value="smooth" selected><?php esc_html_e( 'Smooth', NH_PSC_TD ); ?></option>
										<option value="ribbed"><?php esc_html_e( 'Ribbed (heavy traffic)', NH_PSC_TD ); ?></option>
										<option value="antistatic"><?php esc_html_e( 'Antistatic', NH_PSC_TD ); ?></option>
										<option value="frosted"><?php esc_html_e( 'Frosted (privacy)', NH_PSC_TD ); ?></option>
									</select>
								</label>
								<label>
									<span><?php esc_html_e( 'Temperature grade', NH_PSC_TD ); ?></span>
									<select name="grade">
										<option value="standard" selected><?php esc_html_e( 'Standard (−5 °C to +60 °C)', NH_PSC_TD ); ?></option>
										<option value="frost"><?php esc_html_e( 'Cold-resistant (−30 °C to +50 °C)', NH_PSC_TD ); ?></option>
									</select>
								</label>
							</div>
							<label class="nh-psc__check">
								<input type="checkbox" name="red_edges" value="1">
								<span><?php esc_html_e( 'Red marker strips at both edges', NH_PSC_TD ); ?></span>
							</label>
						</fieldset>

						<div class="nh-psc__plan" data-strip-plan></div>
					</div>

					<div class="nh-psc__panel" data-panel="3" hidden>
						<fieldset class="nh-psc__card">
							<legend><?php esc_html_e( 'Review your kit', NH_PSC_TD ); ?></legend>
							<p class="nh-psc__ask"><?php esc_html_e( 'Your kit includes cut-to-length PVC strips, hanger plates and a hanging rail. Add everything in one go.', NH_PSC_TD ); ?></p>
							<ul class="nh-psc__kit-points">
								<li><?php esc_html_e( 'Opening allowances match Plastena face / soffit rules', NH_PSC_TD ); ?></li>
								<li><?php esc_html_e( 'Overlap chooses how tightly neighbouring strips cover each other', NH_PSC_TD ); ?></li>
								<li><?php esc_html_e( 'Hangers and rail cover the calculated width', NH_PSC_TD ); ?></li>
							</ul>
							<?php if ( $show_post ) : ?>
								<label class="nh-psc__postcode">
									<span><?php esc_html_e( 'Postal code (for shipping)', NH_PSC_TD ); ?></span>
									<input type="text" name="postcode" autocomplete="postal-code" maxlength="12">
								</label>
							<?php else : ?>
								<input type="hidden" name="postcode" value="">
							<?php endif; ?>
						</fieldset>
					</div>

					<div class="nh-psc__wizard-nav">
						<button type="button" class="nh-psc__nav-btn nh-psc__nav-btn--back" data-wizard-back hidden><?php esc_html_e( 'Back', NH_PSC_TD ); ?></button>
						<button type="button" class="nh-psc__nav-btn nh-psc__nav-btn--next" data-wizard-next><?php esc_html_e( 'Continue', NH_PSC_TD ); ?></button>
					</div>
				</form>

				<aside class="nh-psc__offer" aria-live="polite">
					<div class="nh-psc__offer-card">
						<h3><?php esc_html_e( 'Your kit', NH_PSC_TD ); ?></h3>
						<p class="nh-psc__offer-meta" data-offer-meta></p>
						<div class="nh-psc__items-wrap">
							<ul class="nh-psc__items" data-offer-items>
								<li class="nh-psc__empty"><?php esc_html_e( 'Enter an opening size to see the kit.', NH_PSC_TD ); ?></li>
							</ul>
						</div>
						<div class="nh-psc__totals" hidden>
							<div class="nh-psc__tax"><span><?php esc_html_e( 'VAT', NH_PSC_TD ); ?></span><span data-offer-tax></span></div>
							<div class="nh-psc__sum"><span><?php echo esc_html( 'excl' === get_option( 'woocommerce_tax_display_shop', 'incl' ) ? __( 'Total excl. VAT', NH_PSC_TD ) : __( 'Total incl. VAT', NH_PSC_TD ) ); ?></span><span data-offer-total></span></div>
							<p class="nh-psc__ship"><?php esc_html_e( 'Shipping is calculated in the basket from your postal code.', NH_PSC_TD ); ?></p>
						</div>
						<button type="button" class="nh-psc__atc button alt" data-offer-atc disabled>
							<?php esc_html_e( 'Add kit to basket', NH_PSC_TD ); ?>
						</button>
						<p class="nh-psc__status" data-offer-status hidden></p>
					</div>
				</aside>
			</div>

			<div class="nh-psc__mobile-bar" data-mobile-bar hidden>
				<div class="nh-psc__mobile-total">
					<span><?php esc_html_e( 'Kit total', NH_PSC_TD ); ?></span>
					<strong data-mobile-total>—</strong>
				</div>
				<button type="button" class="nh-psc__mobile-cta" data-mobile-cta><?php esc_html_e( 'Continue', NH_PSC_TD ); ?></button>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * @param array<int, array<string, mixed>> $item_data
	 * @param array<string, mixed>             $cart_item
	 * @return array<int, array<string, mixed>>
	 */
	public static function cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['nh_pvc_strip_kit'] ) || ! is_array( $cart_item['nh_pvc_strip_kit'] ) ) {
			return $item_data;
		}
		$kit = $cart_item['nh_pvc_strip_kit'];
		$item_data[] = array(
			'key'   => __( 'PVC strip kit', NH_PSC_TD ),
			'value' => sprintf(
				/* translators: 1: width mm, 2: height mm */
				__( '%1$d × %2$d mm opening', NH_PSC_TD ),
				(int) ( $kit['width'] ?? 0 ),
				(int) ( $kit['height'] ?? 0 )
			),
		);
		return $item_data;
	}

	/**
	 * @param array<string, mixed> $cart_item_data
	 * @return array<string, mixed>
	 */
	public static function unique_kit_line( $cart_item_data ) {
		if ( ! empty( $cart_item_data['nh_pvc_strip_kit'] ) ) {
			$cart_item_data['unique_key'] = md5( microtime() . wp_rand() );
		}
		return $cart_item_data;
	}
}
