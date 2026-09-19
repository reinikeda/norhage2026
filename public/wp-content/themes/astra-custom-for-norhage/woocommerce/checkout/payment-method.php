<?php
/**
 * Payment method card on the checkout page.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kind  = function_exists( 'nh_checkout_gateway_kind' ) ? nh_checkout_gateway_kind( $gateway->id ) : 'other';
$blurb = function_exists( 'nh_checkout_gateway_blurb' ) ? nh_checkout_gateway_blurb( $gateway ) : wp_strip_all_tags( (string) $gateway->get_description() );
?>
<li class="wc_payment_method payment_method_<?php echo esc_attr( $gateway->id ); ?><?php echo $gateway->chosen ? ' is-selected' : ''; ?>" data-nh-kind="<?php echo esc_attr( $kind ); ?>">
	<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio" class="input-radio" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $gateway->chosen, true ); ?> data-order_button_text="<?php echo esc_attr( $gateway->order_button_text ); ?>" data-nh-kind="<?php echo esc_attr( $kind ); ?>" />

	<label class="nh-pay-card" for="payment_method_<?php echo esc_attr( $gateway->id ); ?>">
		<span class="nh-pay-card__mark" aria-hidden="true"></span>
		<span class="nh-pay-card__copy">
			<span class="nh-pay-card__title"><?php echo $gateway->get_title(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php if ( $blurb !== '' ) : ?>
				<span class="nh-pay-card__blurb"><?php echo esc_html( $blurb ); ?></span>
			<?php endif; ?>
		</span>
		<span class="nh-pay-card__icon"><?php echo $gateway->get_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	</label>
	<?php if ( $gateway->has_fields() || $gateway->get_description() ) : ?>
		<div class="payment_box payment_method_<?php echo esc_attr( $gateway->id ); ?>" <?php if ( ! $gateway->chosen ) : ?>style="display:none;"<?php endif; ?>>
			<?php
			if ( 'svea' === $kind && $blurb !== '' ) {
				echo wpautop( esc_html( $blurb ) );
			} else {
				$gateway->payment_fields();
			}
			?>
		</div>
	<?php endif; ?>
</li>
