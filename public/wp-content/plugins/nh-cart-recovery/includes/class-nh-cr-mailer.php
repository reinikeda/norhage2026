<?php
/**
 * Send recovery emails through WooCommerce mailer → wp_mail → WP Mail SMTP / Brevo.
 *
 * @package nh-cart-recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_CR_Mailer {

	public static function init() {
		add_filter( 'woocommerce_email_styles', array( __CLASS__, 'email_styles' ), 20 );
	}

	/**
	 * @param object|null $row Store row.
	 * @return bool
	 */
	public static function send_row( $row ) {
		$settings = nh_cr_get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}
		if ( ! $row || empty( $row->email ) || empty( $row->cart_hash ) ) {
			return false;
		}
		if ( ! empty( $row->emailed_at ) ) {
			return false;
		}
		if ( in_array( $row->status, array( 'converted', 'unsubscribed', 'skipped' ), true ) ) {
			return false;
		}
		if ( NH_CR_Store::email_unsubscribed( $row->email ) ) {
			return false;
		}
		if ( NH_CR_Store::email_has_recent_paid_order( $row->email, 48 ) ) {
			NH_CR_Store::update( (int) $row->id, array( 'status' => 'skipped' ) );
			return false;
		}
		if ( self::sent_to_email_recently( $row->email ) ) {
			return false;
		}

		$items = json_decode( (string) $row->cart, true );
		if ( ! is_array( $items ) || ! $items ) {
			return false;
		}

		$type    = ( $row->type === 'checkout' ) ? 'checkout' : 'cart';
		$locale  = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$copy    = nh_cr_default_copy( $locale, $type );
		$subject = $type === 'checkout' ? $settings['subject_checkout'] : $settings['subject_cart'];
		$intro   = $type === 'checkout' ? $settings['intro_checkout'] : $settings['intro_cart'];
		if ( trim( (string) $subject ) !== '' ) {
			$copy['subject'] = $subject;
		}
		if ( trim( (string) $intro ) !== '' ) {
			$copy['intro'] = $intro;
		}

		$restore = add_query_arg( 'nh_cr', $row->token, wc_get_checkout_url() );
		$unsub   = add_query_arg( 'nh_cr_unsub', $row->token, home_url( '/' ) );
		$html    = self::body_html( $row, $items, $copy, $restore, $unsub );

		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			$sent = wp_mail(
				$row->email,
				$copy['subject'],
				$html,
				array( 'Content-Type: text/html; charset=UTF-8' )
			);
		} else {
			$mailer  = WC()->mailer();
			$wrapped = $mailer->wrap_message( $copy['heading'], $html );
			$sent    = $mailer->send( $row->email, $copy['subject'], $wrapped );
		}

		if ( $sent ) {
			NH_CR_Store::update(
				(int) $row->id,
				array(
					'status'     => 'sent',
					'emailed_at' => current_time( 'mysql' ),
				)
			);
			return true;
		}
		return false;
	}

	/**
	 * One recovery email per address per 7 days (Brevo free-plan friendly).
	 *
	 * @param string $email Email.
	 * @return bool
	 */
	public static function sent_to_email_recently( $email ) {
		global $wpdb;
		$email = NH_CR_Store::normalize_email( $email );
		if ( $email === '' ) {
			return false;
		}
		$cut = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 7 * DAY_IN_SECONDS ) );
		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . NH_CR_Store::table() . ' WHERE email = %s AND emailed_at IS NOT NULL AND emailed_at >= %s LIMIT 1',
				$email,
				$cut
			)
		);
		return (bool) $found;
	}

	/**
	 * @param object               $row     Store row.
	 * @param array<int, mixed>    $items   Snapshot items.
	 * @param array<string,string> $copy    Copy.
	 * @param string               $restore Restore URL.
	 * @param string               $unsub   Unsubscribe URL.
	 * @return string
	 */
	public static function body_html( $row, $items, $copy, $restore, $unsub ) {
		$name = trim( (string) $row->first_name );
		$hi   = $name !== ''
			? sprintf( /* translators: %s first name */ __( 'Hi %s,', NH_CR_TD ), esc_html( $name ) )
			: __( 'Hi,', NH_CR_TD );

		$lines = '<p>' . esc_html( $hi ) . '</p>';
		$lines .= '<p>' . esc_html( $copy['intro'] ) . '</p>';
		$lines .= '<ul>';
		foreach ( $items as $item ) {
			$label = isset( $item['name'] ) ? (string) $item['name'] : '';
			if ( $label === '' && ! empty( $item['product_id'] ) && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( (int) $item['product_id'] );
				$label   = $product ? $product->get_name() : ( '#' . (int) $item['product_id'] );
			}
			$qty = isset( $item['quantity'] ) ? (float) $item['quantity'] : 1;
			$lines .= '<li>' . esc_html( $label ) . ' × ' . esc_html( (string) $qty ) . '</li>';
		}
		$lines .= '</ul>';
		$lines .= '<p><a class="nh-cr-btn" href="' . esc_url( $restore ) . '" style="display:inline-block;padding:12px 20px;background:#00704a;color:#fff;text-decoration:none;border-radius:8px;font-weight:700;">' . esc_html( $copy['button'] ) . '</a></p>';
		$lines .= '<p style="font-size:12px;color:#666;"><a href="' . esc_url( $unsub ) . '">' . esc_html__( 'Unsubscribe from cart reminder emails', NH_CR_TD ) . '</a></p>';
		return $lines;
	}

	/**
	 * @param string $css Email CSS.
	 * @return string
	 */
	public static function email_styles( $css ) {
		return $css . ' a.nh-cr-btn{display:inline-block;padding:12px 20px;background:#00704a;color:#ffffff!important;text-decoration:none;border-radius:8px;font-weight:700;}';
	}
}
