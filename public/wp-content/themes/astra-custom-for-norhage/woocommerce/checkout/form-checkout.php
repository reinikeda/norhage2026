<?php
/**
 * Classic checkout form — details first, then payment radios and optional iframe.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}

$cart_total = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_total() : '';
$form_class = function_exists( 'nh_checkout_form_classes' ) ? nh_checkout_form_classes() : 'checkout woocommerce-checkout nh-checkout-form-el';
$nh_step    = ( function_exists( 'nh_checkout_is_payment_step' ) && nh_checkout_is_payment_step() ) ? 'payment' : 'details';
?>

<form name="checkout" method="post" class="<?php echo esc_attr( $form_class ); ?>" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>" autocomplete="on">

	<div class="nh-checkout-layout">
		<aside class="nh-checkout-layout__aside">
			<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

			<section class="nh-checkout-summary is-open" aria-labelledby="order_review_heading">
				<button type="button" class="nh-checkout-summary-toggle" aria-expanded="true" aria-controls="nh-checkout-summary-body">
					<span class="nh-checkout-summary-toggle__label"><?php esc_html_e( 'Order summary', 'nh-theme' ); ?></span>
					<span class="nh-checkout-summary-toggle__meta">
						<span class="nh-checkout-summary-toggle__amount"><?php echo wp_kses_post( $cart_total ); ?></span>
						<span class="nh-checkout-summary-toggle__shipping"><?php echo wp_kses_post( function_exists( 'nh_checkout_summary_shipping_html' ) ? nh_checkout_summary_shipping_html() : '' ); ?></span>
					</span>
				</button>

				<div id="nh-checkout-summary-body" class="nh-checkout-summary__body">
					<h3 id="order_review_heading"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>

					<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

					<div id="order_review" class="woocommerce-checkout-review-order">
						<?php do_action( 'woocommerce_checkout_order_review' ); ?>
					</div>

					<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
				</div>
			</section>
		</aside>

		<div class="nh-checkout-layout__main">
			<?php if ( $checkout->get_checkout_fields() ) : ?>

				<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

				<div class="col2-set nh-checkout-details" id="customer_details">
					<div class="col-1">
						<?php do_action( 'woocommerce_checkout_billing' ); ?>
					</div>

					<div class="col-2">
						<?php do_action( 'woocommerce_checkout_shipping' ); ?>
					</div>
				</div>

				<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

			<?php endif; ?>

			<div id="kco-extra-checkout-fields"></div>

			<input type="hidden" name="nh_checkout_step" id="nh_checkout_step" value="<?php echo esc_attr( $nh_step ); ?>" />

			<div class="nh-checkout-step-actions nh-checkout-step-actions--details">
				<button type="button" class="button alt nh-checkout-next" id="nh-checkout-next">
					<?php esc_html_e( 'Continue to payment', 'nh-theme' ); ?>
				</button>
			</div>

			<section class="nh-checkout-payment" aria-label="<?php echo esc_attr__( 'Payment', 'woocommerce' ); ?>">
				<button type="button" class="nh-checkout-back" id="nh-checkout-back">
					<?php esc_html_e( 'Back to details', 'nh-theme' ); ?>
				</button>
				<h3 class="nh-checkout-section__title"><?php esc_html_e( 'Payment', 'woocommerce' ); ?></h3>
				<p class="nh-checkout-pay-hint"><?php esc_html_e( 'Choose how you want to pay', 'nh-theme' ); ?></p>
				<?php do_action( 'nh_checkout_payment' ); ?>
				<?php
				if ( function_exists( 'nh_checkout_render_gateway_iframe' ) ) {
					nh_checkout_render_gateway_iframe();
				}
				?>
			</section>
		</div>
	</div>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
