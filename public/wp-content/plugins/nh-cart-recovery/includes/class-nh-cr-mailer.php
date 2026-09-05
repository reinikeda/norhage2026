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
	 * @param object|null $row  Store row.
	 * @param int         $step 0 = compute next due step; 1–3 = send that email now.
	 * @return bool
	 */
	public static function send_row( $row, $step = 0 ) {
		$settings = nh_cr_get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}
		if ( ! $row || empty( $row->email ) || empty( $row->cart_hash ) ) {
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

		$step = (int) $step;
		if ( $step < 1 ) {
			$step = nh_cr_next_due_step( $row, $settings, (int) current_time( 'timestamp' ) );
		}
		if ( $step < 1 ) {
			return false;
		}
		$already = NH_CR_Store::emails_sent_count( $row );
		if ( $step <= $already ) {
			return false;
		}

		$items = json_decode( (string) $row->cart, true );
		if ( ! is_array( $items ) || ! $items ) {
			return false;
		}

		$type   = ( $row->type === 'checkout' ) ? 'checkout' : 'cart';
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$copy   = nh_cr_effective_copy( $settings, $locale, $type, $step );
		$name   = trim( (string) $row->first_name );
		foreach ( $copy as $field => $text ) {
			$copy[ $field ] = nh_cr_personalize( $text, $name );
		}

		$restore = add_query_arg( 'nh_cr', $row->token, wc_get_checkout_url() );
		$unsub   = add_query_arg( 'nh_cr_unsub', $row->token, home_url( '/' ) );
		$html    = self::body_html( $row, $items, $copy, $restore, $unsub, $locale );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( $unsub ) {
			$headers[] = 'List-Unsubscribe: <' . esc_url_raw( $unsub ) . '>';
		}

		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			$sent = wp_mail( $row->email, $copy['subject'], $html, $headers );
		} else {
			$mailer  = WC()->mailer();
			$wrapped = $mailer->wrap_message( $copy['heading'], $html );
			$sent    = $mailer->send( $row->email, $copy['subject'], $wrapped, implode( "\r\n", $headers ) . "\r\n" );
		}

		if ( $sent ) {
			$now  = current_time( 'mysql' );
			$data = array(
				'status'      => 'sent',
				'emailed_at'  => $now,
				'emails_sent' => $step,
			);
			if ( empty( $row->first_emailed_at ) ) {
				$data['first_emailed_at'] = $now;
			}
			NH_CR_Store::update( (int) $row->id, $data );
			return true;
		}
		return false;
	}

	/**
	 * Dummy cart used by the admin Preview tab (not sent to customers).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function sample_items() {
		return array(
			array(
				'product_id' => 0,
				'quantity'   => 1,
				'name'       => 'Kanalplast 16 mm, 800 × 2100 mm',
				'line_total' => 1290,
				'image_url'  => '',
			),
			array(
				'product_id' => 0,
				'quantity'   => 2,
				'name'       => 'Greenhouse aluminium profile',
				'line_total' => 860,
				'image_url'  => '',
			),
		);
	}

	/**
	 * Subject, heading, and inner HTML for one sequence email.
	 *
	 * @param string $type       cart|checkout.
	 * @param int    $step       1–3.
	 * @param string $locale     Locale.
	 * @param string $first_name Name, or empty.
	 * @return array{subject:string,heading:string,html:string}
	 */
	public static function preview_parts( $type, $step, $locale, $first_name = 'Anna' ) {
		$settings = nh_cr_get_settings();
		$copy     = nh_cr_effective_copy( $settings, $locale, $type, $step );
		foreach ( $copy as $field => $text ) {
			$copy[ $field ] = nh_cr_personalize( $text, $first_name );
		}
		$row = (object) array( 'first_name' => $first_name );
		return array(
			'subject' => $copy['subject'],
			'heading' => $copy['heading'],
			'html'    => self::body_html(
				$row,
				self::sample_items(),
				$copy,
				'https://example.com/checkout?nh_cr=preview',
				'https://example.com/?nh_cr_unsub=preview',
				$locale
			),
		);
	}

	/**
	 * Full HTML document approximating WooCommerce’s email wrap (shop header + our body).
	 *
	 * @param string $type       cart|checkout.
	 * @param int    $step       1–3.
	 * @param string $locale     Locale.
	 * @param string $first_name Name, or empty.
	 * @return string
	 */
	public static function preview_document( $type, $step, $locale, $first_name = 'Anna' ) {
		$parts = self::preview_parts( $type, $step, $locale, $first_name );
		$pal   = nh_cr_palette();
		$shop  = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : 'Norhage';
		if ( $shop === '' ) {
			$shop = 'Norhage';
		}

		$html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . esc_html( $parts['subject'] ) . '</title></head>';
		$html .= '<body style="margin:0;padding:0;background:' . esc_attr( $pal['cream'] ) . ';font-family:Arial,Helvetica,sans-serif;">';
		$html .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:' . esc_attr( $pal['cream'] ) . ';padding:24px 0;">';
		$html .= '<tr><td align="center">';
		$html .= '<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">';
		$html .= '<tr><td style="background:' . esc_attr( $pal['forest'] ) . ';color:' . esc_attr( $pal['offwhite'] ) . ';padding:20px 28px;font-size:20px;font-weight:700;letter-spacing:.02em;">' . esc_html( $shop ) . '</td></tr>';
		$html .= '<tr><td style="padding:28px 28px 8px;"><h2 style="margin:0 0 16px;color:' . esc_attr( $pal['forest'] ) . ';font-size:22px;line-height:1.3;">' . esc_html( $parts['heading'] ) . '</h2>';
		$html .= $parts['html'];
		$html .= '</td></tr>';
		$html .= '<tr><td style="background:' . esc_attr( $pal['offwhite'] ) . ';color:' . esc_attr( $pal['muted'] ) . ';padding:16px 28px;font-size:12px;border-top:1px solid ' . esc_attr( $pal['cream'] ) . ';">' . esc_html( $shop ) . '</td></tr>';
		$html .= '</table></td></tr></table></body></html>';
		return $html;
	}

	/**
	 * @param object               $row     Store row.
	 * @param array<int, mixed>    $items   Snapshot items.
	 * @param array<string,string> $copy    Copy.
	 * @param string               $restore Restore URL.
	 * @param string               $unsub   Unsubscribe URL.
	 * @param string               $locale  Locale.
	 * @return string
	 */
	public static function body_html( $row, $items, $copy, $restore, $unsub, $locale = '' ) {
		if ( $locale === '' ) {
			$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		}
		$ui   = nh_cr_ui_copy( $locale );
		$pal  = nh_cr_palette();
		$name = trim( (string) $row->first_name );
		$hi   = $name !== ''
			? sprintf( $ui['hi_named'], $name )
			: $ui['hi_anon'];

		$html  = '<p style="margin:0 0 12px;color:' . esc_attr( $pal['charcoal'] ) . ';font-size:16px;">' . esc_html( $hi ) . '</p>';
		$html .= '<p style="margin:0 0 12px;color:' . esc_attr( $pal['charcoal'] ) . ';">' . esc_html( $copy['intro'] ) . '</p>';
		if ( ! empty( $copy['body'] ) ) {
			$html .= '<p style="margin:0 0 16px;color:' . esc_attr( $pal['charcoal'] ) . ';">' . esc_html( $copy['body'] ) . '</p>';
		}
		$html .= self::items_table( $items, $ui, $pal );
		$html .= '<p style="margin:24px 0 8px;text-align:center;">';
		$html .= '<a class="nh-cr-btn" href="' . esc_url( $restore ) . '" style="display:inline-block;padding:14px 28px;background:' . esc_attr( $pal['green'] ) . ';color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;font-size:16px;">' . esc_html( $copy['button'] ) . '</a>';
		$html .= '</p>';
		$html .= '<p style="margin:8px 0 16px;text-align:center;font-size:12px;color:' . esc_attr( $pal['muted'] ) . ';">' . esc_html( $ui['secure'] ) . '</p>';
		$html .= '<p style="margin:24px 0 0;font-size:12px;color:' . esc_attr( $pal['muted'] ) . ';text-align:center;">';
		$html .= '<a href="' . esc_url( $unsub ) . '" style="color:' . esc_attr( $pal['muted'] ) . ';text-decoration:underline;">' . esc_html( $ui['unsub'] ) . '</a>';
		$html .= '</p>';
		return $html;
	}

	/**
	 * @param array<int, mixed>    $items Snapshot items.
	 * @param array<string,string> $ui    Chrome copy.
	 * @param array<string,string> $pal   Palette.
	 * @return string
	 */
	public static function items_table( $items, $ui, $pal ) {
		$html  = '<table class="nh-cr-table" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;background:' . esc_attr( $pal['offwhite'] ) . ';border:1px solid ' . esc_attr( $pal['cream'] ) . ';border-radius:8px;">';
		$html .= '<tr style="background:' . esc_attr( $pal['forest'] ) . ';color:' . esc_attr( $pal['offwhite'] ) . ';">';
		$html .= '<th align="left" style="padding:10px 12px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;" colspan="2">' . esc_html( $ui['product'] ) . '</th>';
		$html .= '<th align="center" style="padding:10px 12px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;">' . esc_html( $ui['qty'] ) . '</th>';
		$html .= '<th align="right" style="padding:10px 12px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;">' . esc_html( $ui['total'] ) . '</th>';
		$html .= '</tr>';

		$grand = 0.0;
		foreach ( $items as $item ) {
			$product = null;
			$label   = isset( $item['name'] ) ? (string) $item['name'] : '';
			if ( $label === '' && ! empty( $item['product_id'] ) && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( (int) $item['product_id'] );
				$label   = $product ? $product->get_name() : ( '#' . (int) $item['product_id'] );
			}
			$qty   = isset( $item['quantity'] ) ? (float) $item['quantity'] : 1;
			$total = isset( $item['line_total'] ) && is_numeric( $item['line_total'] ) ? (float) $item['line_total'] : 0.0;
			$grand += $total;
			$img   = isset( $item['image_url'] ) ? (string) $item['image_url'] : '';
			if ( $img === '' && ! empty( $item['product_id'] ) && function_exists( 'wc_get_product' ) && function_exists( 'wp_get_attachment_image_url' ) ) {
				if ( ! $product ) {
					$product = wc_get_product( (int) $item['product_id'] );
				}
				if ( $product ) {
					$image_id = (int) $product->get_image_id();
					$img      = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';
				}
			}

			$html .= '<tr>';
			$html .= '<td style="padding:12px;width:72px;border-bottom:1px solid ' . esc_attr( $pal['cream'] ) . ';">';
			if ( $img !== '' ) {
				$html .= '<img src="' . esc_url( $img ) . '" alt="" width="64" height="64" style="display:block;width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid ' . esc_attr( $pal['cream'] ) . ';" />';
			}
			$html .= '</td>';
			$html .= '<td style="padding:12px 8px;border-bottom:1px solid ' . esc_attr( $pal['cream'] ) . ';color:' . esc_attr( $pal['charcoal'] ) . ';font-weight:600;">' . esc_html( $label ) . '</td>';
			$html .= '<td align="center" style="padding:12px 8px;border-bottom:1px solid ' . esc_attr( $pal['cream'] ) . ';color:' . esc_attr( $pal['charcoal'] ) . ';">' . esc_html( (string) $qty ) . '</td>';
			$html .= '<td align="right" style="padding:12px;border-bottom:1px solid ' . esc_attr( $pal['cream'] ) . ';color:' . esc_attr( $pal['charcoal'] ) . ';">' . self::format_money( $total ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '<tr>';
		$html .= '<td colspan="3" align="right" style="padding:12px;font-weight:700;color:' . esc_attr( $pal['forest'] ) . ';">' . esc_html( $ui['total'] ) . '</td>';
		$html .= '<td align="right" style="padding:12px;font-weight:700;color:' . esc_attr( $pal['forest'] ) . ';border-top:2px solid ' . esc_attr( $pal['gold'] ) . ';">' . self::format_money( $grand ) . '</td>';
		$html .= '</tr>';
		$html .= '</table>';
		return $html;
	}

	/**
	 * @param float $amount Amount.
	 * @return string
	 */
	private static function format_money( $amount ) {
		if ( $amount <= 0 ) {
			return '';
		}
		if ( function_exists( 'wc_price' ) ) {
			return wp_kses_post( wc_price( $amount ) );
		}
		return esc_html( number_format( (float) $amount, 2 ) );
	}

	/**
	 * @param string $css Email CSS.
	 * @return string
	 */
	public static function email_styles( $css ) {
		$pal = nh_cr_palette();
		$css .= ' h2{color:' . $pal['forest'] . '!important;}';
		$css .= ' a.nh-cr-btn{display:inline-block;padding:14px 28px;background:' . $pal['green'] . ';color:#ffffff!important;text-decoration:none;border-radius:8px;font-weight:700;}';
		$css .= ' table.nh-cr-table{border:1px solid ' . $pal['cream'] . ';background:' . $pal['offwhite'] . ';}';
		return $css;
	}
}
