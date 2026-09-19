<?php
/**
 * Classic checkout form — one page, contact then delivery then payment.
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

$cart_total     = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_total() : '';
$form_class     = function_exists( 'nh_checkout_form_classes' ) ? nh_checkout_form_classes() : 'checkout woocommerce-checkout nh-checkout-form-el';
$snippet_ready  = ( function_exists( 'nh_checkout_should_load_iframe' ) && nh_checkout_should_load_iframe() ) ? '1' : '';
$compact_label  = function_exists( 'nh_checkout_summary_compact_label' ) ? nh_checkout_summary_compact_label() : wp_strip_all_tags( $cart_total );
$item_count     = function_exists( 'nh_checkout_cart_item_count' ) ? nh_checkout_cart_item_count() : 0;
?>

<form name="checkout" method="post" class="<?php echo esc_attr( $form_class ); ?>" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>" autocomplete="on">

	<div class="nh-checkout-layout">
		<aside class="nh-checkout-layout__aside">
			<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

			<section class="nh-checkout-summary is-open" aria-labelledby="order_review_heading">
				<button type="button" class="nh-checkout-summary-toggle" aria-expanded="true" aria-controls="nh-checkout-summary-body">
					<span class="nh-checkout-summary-toggle__label"><?php echo esc_html( $compact_label ); ?></span>
					<span class="nh-checkout-summary-toggle__meta">
						<span class="nh-checkout-summary-toggle__view"><?php esc_html_e( 'View order summary', 'nh-theme' ); ?></span>
						<span class="nh-checkout-summary-toggle__amount"><?php echo wp_kses_post( $cart_total ); ?></span>
						<span class="nh-checkout-summary-toggle__shipping"><?php echo wp_kses_post( function_exists( 'nh_checkout_summary_shipping_html' ) ? nh_checkout_summary_shipping_html() : '' ); ?></span>
					</span>
				</button>

				<div id="nh-checkout-summary-body" class="nh-checkout-summary__body">
					<h3 id="order_review_heading"><?php esc_html_e( 'Your order', 'nh-theme' ); ?></h3>

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

			<section class="nh-checkout-delivery" id="nh-checkout-delivery" aria-labelledby="nh-checkout-delivery-title">
				<h3 class="nh-checkout-section__title" id="nh-checkout-delivery-title"><?php esc_html_e( 'Delivery method', 'nh-theme' ); ?></h3>
				<div class="nh-checkout-delivery__methods" id="nh-checkout-shipping-mount"></div>
			</section>

			<div id="kco-extra-checkout-fields"></div>

			<input type="hidden" name="nh_checkout_snippet_ready" id="nh_checkout_snippet_ready" value="<?php echo esc_attr( $snippet_ready ); ?>" />

			<section class="nh-checkout-payment" id="nh-checkout-payment" aria-label="<?php echo esc_attr__( 'Payment', 'nh-theme' ); ?>">
				<h3 class="nh-checkout-section__title"><?php esc_html_e( 'Payment method', 'nh-theme' ); ?></h3>
				<p class="nh-checkout-pay-hint"><?php esc_html_e( 'Choose how you want to pay. You can complete your details above first.', 'nh-theme' ); ?></p>
				<?php do_action( 'nh_checkout_payment' ); ?>
				<?php
				if ( function_exists( 'nh_checkout_render_gateway_iframe' ) ) {
					nh_checkout_render_gateway_iframe();
				}
				$gateway_count = function_exists( 'nh_checkout_available_gateway_count' ) ? nh_checkout_available_gateway_count() : 0;
				if ( $gateway_count > 1 ) :
					?>
				<button type="button" class="nh-checkout-other-payment" id="nh-checkout-other-payment">
					<?php esc_html_e( 'Choose another payment method', 'nh-theme' ); ?>
				</button>
				<?php endif; ?>
			</section>
		</div>
	</div>

	<div class="nh-checkout-status" id="nh-checkout-status" hidden>
		<p class="nh-checkout-status__title" id="nh-checkout-status-title"></p>
		<p class="nh-checkout-status__text" id="nh-checkout-status-text"></p>
	</div>

	<div class="nh-checkout-sticky" id="nh-checkout-sticky">
		<div class="nh-checkout-sticky__total">
			<span class="nh-checkout-sticky__label"><?php esc_html_e( 'Total', 'nh-theme' ); ?></span>
			<span class="nh-checkout-sticky__amount"><?php echo wp_kses_post( $cart_total ); ?></span>
		</div>
		<button type="button" class="button alt nh-checkout-sticky__btn" id="nh-checkout-sticky-btn">
			<?php echo esc_html( function_exists( 'nh_checkout_place_order_button_text' ) ? nh_checkout_place_order_button_text( __( 'Review order', 'nh-theme' ) ) : __( 'Review order', 'nh-theme' ) ); ?>
		</button>
	</div>

</form>

<?php
unset( $item_count );
do_action( 'woocommerce_after_checkout_form', $checkout );
?>
