<?php
/**
 * Customer note email
 *
 * Template override for: yourtheme/woocommerce/emails/customer-note.php
 *
 * @package WooCommerce\Templates\Emails
 * @version 10.1.0
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$email_improvements_enabled = FeaturesUtil::feature_is_enabled( 'email_improvements' );

/*
 * Email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php echo $email_improvements_enabled ? '<div class="email-introduction">' : ''; ?>

<p>
<?php
if ( ! empty( $order->get_billing_first_name() ) ) {
	/* translators: %s: Customer first name */
	printf(
		esc_html__( 'Hi %s,', 'nh-theme' ),
		esc_html( $order->get_billing_first_name() )
	);
} else {
	esc_html_e( 'Hi,', 'nh-theme' );
}
?>
</p>

<p><?php esc_html_e( 'The following note has been added to your order:', 'nh-theme' ); ?></p>

<blockquote>
<?php
$safe_note = wc_wptexturize_order_note( $customer_note );
echo wpautop( make_clickable( $safe_note ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
?>
</blockquote>

<p><?php esc_html_e( 'As a reminder, here are your order details:', 'nh-theme' ); ?></p>

<?php echo $email_improvements_enabled ? '</div>' : ''; ?>

<?php

/*
 * Order details
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/*
 * Order meta
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * Customer details
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

/*
 * Additional content
 */
if ( $additional_content ) {
	echo $email_improvements_enabled ? '<table border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td class="email-additional-content">' : '';
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	echo $email_improvements_enabled ? '</td></tr></table>' : '';
}

/*
 * Email footer
 */
do_action( 'woocommerce_email_footer', $email );
