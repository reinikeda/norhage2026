<?php
/**
 * Cart shipping calculator — country (only if more than one) + postcode.
 *
 * Always visible. City and state are not collected.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

$countries       = function_exists( 'nh_cart_shipping_countries' ) ? nh_cart_shipping_countries() : WC()->countries->get_shipping_countries();
$show_country    = count( $countries ) > 1;
$current_country = function_exists( 'nh_cart_default_shipping_country' ) ? nh_cart_default_shipping_country() : WC()->customer->get_shipping_country();
$current_postcode = WC()->customer ? (string) WC()->customer->get_shipping_postcode() : '';
$field_class      = $show_country ? 'nh-shipping-calc__fields nh-shipping-calc__fields--split' : 'nh-shipping-calc__fields';

do_action( 'woocommerce_before_shipping_calculator' );
?>

<form class="woocommerce-shipping-calculator nh-shipping-calc" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
	<div class="nh-shipping-calc__head">
		<h2 class="nh-shipping-calc__title"><?php esc_html_e( 'Calculate shipping', 'woocommerce' ); ?></h2>
		<p class="nh-shipping-calc__hint"><?php esc_html_e( 'Enter your postcode to see the shipping cost.', 'nh-theme' ); ?></p>
	</div>

	<section class="shipping-calculator-form nh-shipping-calc__form" id="nh-shipping-calculator-form">
		<div class="<?php echo esc_attr( $field_class ); ?>">
			<?php if ( $show_country ) : ?>
				<p class="form-row form-row-wide nh-shipping-calc__row" id="calc_shipping_country_field">
					<label for="calc_shipping_country"><?php esc_html_e( 'Country / region', 'woocommerce' ); ?></label>
					<select name="calc_shipping_country" id="calc_shipping_country" class="country_to_state country_select" rel="calc_shipping_state">
						<option value=""><?php esc_html_e( 'Select a country / region&hellip;', 'woocommerce' ); ?></option>
						<?php
						foreach ( $countries as $key => $value ) {
							echo '<option value="' . esc_attr( $key ) . '"' . selected( $current_country, $key, false ) . '>' . esc_html( $value ) . '</option>';
						}
						?>
					</select>
				</p>
			<?php else : ?>
				<input type="hidden" name="calc_shipping_country" value="<?php echo esc_attr( $current_country ); ?>" />
			<?php endif; ?>

			<?php if ( apply_filters( 'woocommerce_shipping_calculator_enable_postcode', true ) ) : ?>
				<p class="form-row form-row-wide nh-shipping-calc__row" id="calc_shipping_postcode_field">
					<label for="calc_shipping_postcode"><?php esc_html_e( 'Postcode / ZIP', 'woocommerce' ); ?></label>
					<input
						type="text"
						class="input-text"
						value="<?php echo esc_attr( $current_postcode ); ?>"
						placeholder="<?php esc_attr_e( 'Postcode / ZIP', 'woocommerce' ); ?>"
						name="calc_shipping_postcode"
						id="calc_shipping_postcode"
						autocomplete="postal-code"
						inputmode="text"
						enterkeyhint="done"
					/>
				</p>
			<?php endif; ?>
		</div>

		<p class="nh-shipping-calc__submit">
			<?php
			$button_class = 'button';
			if ( function_exists( 'wc_wp_theme_get_element_class_name' ) ) {
				$theme_btn = wc_wp_theme_get_element_class_name( 'button' );
				if ( $theme_btn ) {
					$button_class .= ' ' . $theme_btn;
				}
			}
			?>
			<button type="submit" name="calc_shipping" value="1" class="<?php echo esc_attr( $button_class ); ?>">
				<?php esc_html_e( 'Show shipping cost', 'nh-theme' ); ?>
			</button>
		</p>
		<?php wp_nonce_field( 'woocommerce-shipping-calculator', 'woocommerce-shipping-calculator-nonce' ); ?>
	</section>
</form>

<?php do_action( 'woocommerce_after_shipping_calculator' ); ?>
