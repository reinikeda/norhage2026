<?php
/**
 * Customer cancelled order email
 *
 * Template override for: yourtheme/woocommerce/emails/customer-cancelled-order.php
 *
 * @package WooCommerce\Templates\Emails
 * @version 10.0.0
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$email_improvements_enabled = FeaturesUtil::feature_is_enabled( 'email_improvements' );

/**
 * Hook: woocommerce_email_header.
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php echo $email_improvements_enabled ? '<div class="email-introduction">' : ''; ?>

<?php
/* translators: %1$s: Order number */
$text = __( 'We’re sorry to let you know that your order #%1$s has been cancelled.', 'nh-theme' );

if ( $email_improvements_enabled ) {
	/* translators: %1$s: Order number */
	$text = __( 'We’re getting in touch to let you know that your order #%1$s has been cancelled.', 'nh-theme' );
}
?>
<p><?php printf( esc_html( $text ), esc_html( $order->get_order_number() ) ); ?></p>

<?php echo $email_improvements_enabled ? '</div>' : ''; ?>

<?php
/**
 * Hook: woocommerce_email_order_details.
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/**
 * Hook: woocommerce_email_order_meta.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/**
 * Hook: woocommerce_email_customer_details.
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

/**
 * Additional content (set in WooCommerce email settings).
 */
if ( $additional_content ) {
	echo $email_improvements_enabled ? '<table border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td class="email-additional-content">' : '';
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	echo $email_improvements_enabled ? '</td></tr></table>' : '';
}

/**
 * Hook: woocommerce_email_footer.
 */
do_action( 'woocommerce_email_footer', $email );
