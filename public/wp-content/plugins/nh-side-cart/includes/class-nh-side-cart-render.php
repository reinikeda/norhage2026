<?php
/**
 * Side cart HTML.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NH_Side_Cart_Render {

	/**
	 * Inner drawer body (items + shipping + totals + actions).
	 *
	 * @return string
	 */
	public static function body_html() {
		ob_start();
		echo '<div id="nh-sc-body" class="nh-sc__body">';
		self::render_body();
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * @param int $count Item count.
	 * @return string
	 */
	public static function count_html( $count ) {
		$count = (int) $count;
		$label = sprintf(
			/* translators: %d: number of items in the basket */
			_n( '%d item', '%d items', $count, NH_SC_TD ),
			$count
		);

		return '<span class="nh-sc__count"' . ( $count ? '' : ' hidden' ) . '>' . esc_html( $label ) . '</span>';
	}

	public static function render_body() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$cart = WC()->cart;

		if ( $cart->is_empty() ) {
			self::render_empty();
			return;
		}

		$notices = wc_notice_count() ? wc_print_notices( true ) : '';
		if ( $notices ) {
			echo '<div class="nh-sc__notices" role="status">' . $notices . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<ul class="nh-sc__items">';
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			self::render_item( $cart_item_key, $cart_item );
		}
		echo '</ul>';

		if ( $cart->needs_shipping() && $cart->show_shipping() ) {
			self::render_shipping();
		}

		self::render_totals();
		self::render_actions();
	}

	private static function render_empty() {
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		?>
		<div class="nh-sc__empty">
			<p class="nh-sc__empty-title"><?php esc_html_e( 'Your basket is empty', NH_SC_TD ); ?></p>
			<p class="nh-sc__empty-text"><?php esc_html_e( 'Add products to see shipping costs by postcode.', NH_SC_TD ); ?></p>
			<a class="nh-sc__btn nh-sc__btn--primary" href="<?php echo esc_url( $shop ); ?>">
				<?php esc_html_e( 'Continue shopping', NH_SC_TD ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * @param string $cart_item_key Cart item key.
	 * @param array  $cart_item     Cart item.
	 */
	private static function render_item( $cart_item_key, $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		if ( ! $product instanceof WC_Product || ! $product->exists() || (int) $cart_item['quantity'] <= 0 ) {
			return;
		}

		$qty           = (int) $cart_item['quantity'];
		$permalink     = $product->is_visible() ? $product->get_permalink( $cart_item ) : '';
		$permalink     = apply_filters( 'woocommerce_cart_item_permalink', $permalink, $cart_item, $cart_item_key );
		$thumbnail     = $product->get_image(
			'woocommerce_gallery_thumbnail',
			array(
				'alt'      => $product->get_name(),
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
		$thumbnail     = apply_filters( 'woocommerce_cart_item_thumbnail', $thumbnail, $cart_item, $cart_item_key );
		$item_data     = wc_get_formatted_cart_item_data( $cart_item );
		$qty_locked    = self::is_qty_locked( $cart_item, $product );
		$min           = max( 1, (int) $product->get_min_purchase_quantity() );
		$max           = (int) $product->get_max_purchase_quantity();
		$max_attr      = $max > 0 ? $max : '';
		$subtotal      = WC()->cart->get_product_subtotal( $product, $qty );
		$remove_url    = wc_get_cart_remove_url( $cart_item_key );
		$name          = $product->get_name();
		?>
		<li class="nh-sc__item<?php echo $qty_locked ? ' nh-sc__item--qty-locked' : ''; ?>" data-key="<?php echo esc_attr( $cart_item_key ); ?>">
			<div class="nh-sc__thumb">
				<?php if ( $permalink ) : ?>
					<a href="<?php echo esc_url( $permalink ); ?>"><?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<?php else : ?>
					<?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</div>
			<div class="nh-sc__item-main">
				<div class="nh-sc__item-top">
					<?php if ( $permalink ) : ?>
						<a class="nh-sc__name" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $name ); ?></a>
					<?php else : ?>
						<span class="nh-sc__name"><?php echo esc_html( $name ); ?></span>
					<?php endif; ?>
					<button
						type="button"
						class="nh-sc__remove"
						data-nh-sc-remove="<?php echo esc_attr( $cart_item_key ); ?>"
						data-href="<?php echo esc_url( $remove_url ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name */ __( 'Remove %s from basket', NH_SC_TD ), $name ) ); ?>"
					>
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<?php if ( $item_data ) : ?>
					<div class="nh-sc__meta"><?php echo $item_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>
				<div class="nh-sc__item-bottom">
					<?php if ( $qty_locked ) : ?>
						<span class="nh-sc__qty-locked"><?php echo esc_html( sprintf( /* translators: %d: quantity */ __( 'Qty: %d', NH_SC_TD ), $qty ) ); ?></span>
					<?php else : ?>
						<div class="nh-sc__qty" data-nh-sc-qty="<?php echo esc_attr( $cart_item_key ); ?>">
							<button type="button" class="nh-sc__qty-btn" data-delta="-1" aria-label="<?php esc_attr_e( 'Decrease quantity', NH_SC_TD ); ?>">−</button>
							<input
								type="number"
								class="nh-sc__qty-input"
								name="nh_sc_qty[<?php echo esc_attr( $cart_item_key ); ?>]"
								value="<?php echo esc_attr( (string) $qty ); ?>"
								min="<?php echo esc_attr( (string) $min ); ?>"
								<?php echo $max_attr !== '' ? 'max="' . esc_attr( (string) $max_attr ) . '"' : ''; ?>
								step="1"
								inputmode="numeric"
								aria-label="<?php esc_attr_e( 'Quantity', NH_SC_TD ); ?>"
							/>
							<button type="button" class="nh-sc__qty-btn" data-delta="1" aria-label="<?php esc_attr_e( 'Increase quantity', NH_SC_TD ); ?>">+</button>
						</div>
					<?php endif; ?>
					<div class="nh-sc__price"><?php echo $subtotal; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				</div>
			</div>
		</li>
		<?php
	}

	/**
	 * @param array      $cart_item Cart item.
	 * @param WC_Product $product   Product.
	 * @return bool
	 */
	private static function is_qty_locked( $cart_item, $product ) {
		if ( function_exists( 'nh_is_sample_cart_item' ) && nh_is_sample_cart_item( $cart_item ) ) {
			return true;
		}
		if ( $product->is_sold_individually() ) {
			return true;
		}
		return (bool) apply_filters( 'nh_side_cart_qty_locked', false, $cart_item, $product );
	}

	private static function render_shipping() {
		NH_Side_Cart::ensure_shipping_calculated();

		$countries       = NH_Side_Cart::shipping_countries();
		$show_country    = count( $countries ) > 1;
		$current_country = NH_Side_Cart::default_shipping_country();
		$postcode        = WC()->customer ? (string) WC()->customer->get_shipping_postcode() : '';
		$packages        = WC()->shipping()->get_packages();
		$has_rates       = false;

		foreach ( $packages as $package ) {
			if ( ! empty( $package['rates'] ) ) {
				$has_rates = true;
				break;
			}
		}
		?>
		<section class="nh-sc__shipping" aria-labelledby="nh-sc-shipping-title">
			<div class="nh-sc__shipping-head">
				<h3 id="nh-sc-shipping-title" class="nh-sc__shipping-title"><?php esc_html_e( 'Calculate shipping', NH_SC_TD ); ?></h3>
				<p class="nh-sc__shipping-hint"><?php esc_html_e( 'Enter your postcode to see the shipping cost.', NH_SC_TD ); ?></p>
			</div>

			<form class="nh-sc__shipping-form" data-nh-sc-shipping="1">
				<?php if ( $show_country ) : ?>
					<p class="nh-sc__field">
						<label for="nh_sc_shipping_country"><?php esc_html_e( 'Country / region', NH_SC_TD ); ?></label>
						<select name="calc_shipping_country" id="nh_sc_shipping_country" autocomplete="country">
							<option value=""><?php esc_html_e( 'Select a country / region…', NH_SC_TD ); ?></option>
							<?php foreach ( $countries as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current_country, $code ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				<?php else : ?>
					<input type="hidden" name="calc_shipping_country" value="<?php echo esc_attr( $current_country ); ?>" />
				<?php endif; ?>

				<p class="nh-sc__field">
					<label for="nh_sc_shipping_postcode"><?php esc_html_e( 'Postcode / ZIP', NH_SC_TD ); ?></label>
					<input
						type="text"
						id="nh_sc_shipping_postcode"
						name="calc_shipping_postcode"
						value="<?php echo esc_attr( $postcode ); ?>"
						placeholder="<?php esc_attr_e( 'Postcode / ZIP', NH_SC_TD ); ?>"
						autocomplete="postal-code"
						inputmode="text"
						enterkeyhint="done"
					/>
				</p>

				<button type="submit" class="nh-sc__btn nh-sc__btn--secondary">
					<?php esc_html_e( 'Show shipping cost', NH_SC_TD ); ?>
				</button>
			</form>

			<?php if ( $has_rates ) : ?>
				<div class="nh-sc__methods">
					<?php self::render_methods( $packages ); ?>
				</div>
				<?php if ( NH_Side_Cart::packages_have_warehouse_pickup( $packages ) && NH_Side_Cart::packages_have_delivery_rate( $packages ) ) : ?>
					<p class="nh-sc__pickup-note"><?php esc_html_e( 'Free pickup from the warehouse is also available.', NH_SC_TD ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( NH_Side_Cart::is_lithuanian_shop() && NH_Side_Cart::is_pickup_only( $packages ) ) : ?>
				<?php echo NH_Side_Cart::pickup_only_notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * @param array $packages Shipping packages.
	 */
	private static function render_methods( $packages ) {
		$chosen = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', array() ) : array();

		foreach ( $packages as $i => $package ) {
			$rates = isset( $package['rates'] ) ? $package['rates'] : array();
			if ( empty( $rates ) ) {
				continue;
			}

			$selected   = isset( $chosen[ $i ] ) ? $chosen[ $i ] : '';
			$delivery   = NH_Side_Cart::pick_delivery_rate_id( $rates );
			$user_chose = WC()->session && WC()->session->get( NH_Side_Cart::SESSION_USER_METHOD );
			if ( $selected === '' || ! isset( $rates[ $selected ] ) ) {
				$selected = $delivery !== '' ? $delivery : (string) key( $rates );
			} elseif ( ! $user_chose && $delivery !== '' && NH_Side_Cart::rate_is_warehouse_pickup( $rates[ $selected ] ) ) {
				$selected = $delivery;
			}

			$ordered = array();
			foreach ( $rates as $rate_id => $rate ) {
				if ( ! NH_Side_Cart::rate_is_warehouse_pickup( $rate ) ) {
					$ordered[ $rate_id ] = $rate;
				}
			}
			foreach ( $rates as $rate_id => $rate ) {
				if ( NH_Side_Cart::rate_is_warehouse_pickup( $rate ) ) {
					$ordered[ $rate_id ] = $rate;
				}
			}

			echo '<ul class="nh-sc__method-list">';
			foreach ( $ordered as $rate_id => $rate ) {
				if ( ! $rate instanceof WC_Shipping_Rate ) {
					continue;
				}
				$input_id  = 'nh_sc_method_' . $i . '_' . sanitize_html_class( $rate_id );
				$cost_html = wc_cart_totals_shipping_method_label( $rate );
				?>
				<li class="nh-sc__method">
					<label for="<?php echo esc_attr( $input_id ); ?>">
						<input
							type="radio"
							id="<?php echo esc_attr( $input_id ); ?>"
							name="shipping_method[<?php echo esc_attr( (string) $i ); ?>]"
							value="<?php echo esc_attr( $rate_id ); ?>"
							class="nh-sc__method-input"
							data-index="<?php echo esc_attr( (string) $i ); ?>"
							<?php checked( $selected, $rate_id ); ?>
						/>
						<span class="nh-sc__method-label"><?php echo $cost_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</label>
				</li>
				<?php
			}
			echo '</ul>';
		}
	}

	private static function render_totals() {
		$cart = WC()->cart;
		?>
		<dl class="nh-sc__totals">
			<div class="nh-sc__total-row">
				<dt><?php esc_html_e( 'Subtotal', NH_SC_TD ); ?></dt>
				<dd><?php echo $cart->get_cart_subtotal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd>
			</div>
			<?php foreach ( $cart->get_coupons() as $code => $coupon ) : ?>
				<div class="nh-sc__total-row nh-sc__total-row--coupon">
					<dt><?php echo esc_html( sprintf( /* translators: %s: coupon code */ __( 'Coupon: %s', NH_SC_TD ), $code ) ); ?></dt>
					<dd><?php wc_cart_totals_coupon_html( $coupon ); ?></dd>
				</div>
			<?php endforeach; ?>
			<?php if ( $cart->needs_shipping() && $cart->show_shipping() ) : ?>
				<div class="nh-sc__total-row">
					<dt><?php echo esc_html( self::shipping_row_label() ); ?></dt>
					<dd><?php echo self::shipping_total_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd>
				</div>
			<?php endif; ?>
			<?php if ( wc_tax_enabled() && ! $cart->display_prices_including_tax() ) : ?>
				<?php foreach ( $cart->get_tax_totals() as $tax ) : ?>
					<div class="nh-sc__total-row">
						<dt><?php echo esc_html( $tax->label ); ?></dt>
						<dd><?php echo wp_kses_post( $tax->formatted_amount ); ?></dd>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
			<div class="nh-sc__total-row nh-sc__total-row--grand">
				<dt><?php esc_html_e( 'Total', NH_SC_TD ); ?></dt>
				<dd><?php echo $cart->get_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd>
			</div>
		</dl>
		<?php
	}

	/**
	 * @return string
	 */
	private static function shipping_row_label() {
		$rate = NH_Side_Cart::chosen_shipping_rate();
		if ( $rate && NH_Side_Cart::rate_is_warehouse_pickup( $rate ) ) {
			$label = trim( (string) $rate->get_label() );
			if ( $label !== '' ) {
				return $label;
			}
			return __( 'Warehouse pickup', NH_SC_TD );
		}

		return __( 'Shipping', NH_SC_TD );
	}

	/**
	 * @return string
	 */
	private static function shipping_total_html() {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return esc_html__( 'Enter postcode', NH_SC_TD );
		}

		$calculated = false;
		if ( WC()->customer ) {
			$calculated = method_exists( WC()->customer, 'has_calculated_shipping' )
				? (bool) WC()->customer->has_calculated_shipping()
				: (bool) WC()->customer->get_calculated_shipping();
		}

		if ( ! $calculated ) {
			return esc_html__( 'Enter postcode', NH_SC_TD );
		}

		return $cart->get_cart_shipping_total();
	}

	private static function render_actions() {
		$cart_url     = wc_get_cart_url();
		$checkout_url = wc_get_checkout_url();
		?>
		<div class="nh-sc__actions">
			<button type="button" class="nh-sc__continue" data-nh-sc-close>
				<?php esc_html_e( 'Continue shopping', NH_SC_TD ); ?>
			</button>
			<a class="nh-sc__btn nh-sc__btn--ghost" href="<?php echo esc_url( $cart_url ); ?>">
				<?php esc_html_e( 'View basket', NH_SC_TD ); ?>
			</a>
			<a class="nh-sc__btn nh-sc__btn--primary" href="<?php echo esc_url( $checkout_url ); ?>">
				<?php esc_html_e( 'Checkout', NH_SC_TD ); ?>
			</a>
		</div>
		<?php
	}
}
