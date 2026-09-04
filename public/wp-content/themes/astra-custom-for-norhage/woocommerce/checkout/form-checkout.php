<?php
/**
 * Classic checkout form — two-column layout, payment under details.
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
$customer_type = $checkout->get_value( 'billing_customer_type' );
$form_class    = 'checkout woocommerce-checkout nh-checkout-form-el';
if ( 'business' === $customer_type ) {
	$form_class .= ' nh-checkout--business';
}
$snippet_checkout = function_exists( 'nh_checkout_is_snippet_gateway' ) && nh_checkout_is_snippet_gateway();
?>

<form name="checkout" method="post" class="<?php echo esc_attr( $form_class ); ?>" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>" autocomplete="on">

	<div class="nh-checkout-layout">
		<aside class="nh-checkout-layout__aside">
			<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

			<section class="nh-checkout-summary" aria-labelledby="order_review_heading">
				<button type="button" class="nh-checkout-summary-toggle" aria-expanded="false" aria-controls="nh-checkout-summary-body">
					<span class="nh-checkout-summary-toggle__label"><?php esc_html_e( 'Order summary', 'nh-theme' ); ?></span>
					<span class="nh-checkout-summary-toggle__amount"><?php echo wp_kses_post( $cart_total ); ?></span>
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

			<button type="button" class="nh-checkout-other-payment"<?php echo $snippet_checkout ? '' : ' hidden'; ?>>
				<?php esc_html_e( 'Other payment method', 'nh-theme' ); ?>
			</button>
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

			<section class="nh-checkout-payment" aria-label="<?php echo esc_attr__( 'Payment', 'woocommerce' ); ?>">
				<h3 class="nh-checkout-section__title"><?php esc_html_e( 'Payment', 'woocommerce' ); ?></h3>
				<?php do_action( 'nh_checkout_payment' ); ?>
			</section>
		</div>
	</div>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
