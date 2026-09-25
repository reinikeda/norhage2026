<?php
/**
 * HTML body for a wishlist email.
 *
 * @package nh-wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Escape text for the email body.
 *
 * @param string $text Text.
 * @return string
 */
function nh_wl_email_escape( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * @param array<string,mixed> $doc Prepared email.
 * @return string
 */
function nh_wl_email_html( $doc ) {
	$doc      = is_array( $doc ) ? $doc : array();
	$site     = nh_wl_email_escape( isset( $doc['site_name'] ) ? $doc['site_name'] : '' );
	$heading  = nh_wl_email_escape( isset( $doc['heading'] ) ? $doc['heading'] : '' );
	$list     = nh_wl_email_escape( isset( $doc['list_name'] ) ? $doc['list_name'] : '' );
	$comment  = isset( $doc['comment'] ) ? trim( (string) $doc['comment'] ) : '';
	$intro    = nh_wl_email_escape( isset( $doc['intro'] ) ? $doc['intro'] : '' );
	$comment_label = nh_wl_email_escape( isset( $doc['comment_label'] ) ? $doc['comment_label'] : '' );
	$open     = nh_wl_email_escape( isset( $doc['open_label'] ) ? $doc['open_label'] : '' );
	$vat      = nh_wl_email_escape( isset( $doc['vat_note'] ) ? $doc['vat_note'] : '' );
	$rows     = isset( $doc['items'] ) && is_array( $doc['items'] ) ? $doc['items'] : array();

	$html  = '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#f6f3ee;color:#2c2a29;font-family:Arial,sans-serif;">';
	$html .= '<div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #e4ddd2;border-radius:12px;padding:24px;">';
	$html .= '<p style="margin:0 0 8px;color:#00704a;font-size:13px;">' . $site . '</p>';
	$html .= '<h1 style="margin:0 0 8px;font-size:22px;color:#1e3932;">' . $heading . '</h1>';
	if ( '' !== $list ) {
		$html .= '<p style="margin:0 0 16px;font-size:15px;">' . $list . '</p>';
	}
	if ( '' !== $intro ) {
		$html .= '<p style="margin:0 0 16px;">' . $intro . '</p>';
	}
	if ( '' !== $vat ) {
		$html .= '<p style="margin:0 0 16px;">' . $vat . '</p>';
	}
	if ( '' !== $comment ) {
		$html .= '<p style="margin:0 0 6px;font-weight:700;">' . $comment_label . '</p>';
		$html .= '<p style="margin:0 0 20px;white-space:pre-wrap;">' . nl2br( nh_wl_email_escape( $comment ) ) . '</p>';
	}
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$name   = nh_wl_email_escape( isset( $row['name'] ) ? $row['name'] : '' );
		$url    = nh_wl_email_escape( isset( $row['url'] ) ? $row['url'] : '' );
		$qty    = nh_wl_email_escape( isset( $row['qty_label'] ) ? $row['qty_label'] : '' );
		$notice = nh_wl_email_escape( isset( $row['notice'] ) ? $row['notice'] : '' );
		$price  = nh_wl_email_escape( isset( $row['price'] ) ? $row['price'] : '' );
		$html  .= '<div style="border-top:1px solid #e4ddd2;padding:14px 0;">';
		$html  .= '<p style="margin:0 0 6px;font-size:16px;font-weight:700;">' . $name . '</p>';
		if ( '' !== $qty ) {
			$html .= '<p style="margin:0 0 4px;">' . $qty . '</p>';
		}
		$lines = isset( $row['lines'] ) && is_array( $row['lines'] ) ? $row['lines'] : array();
		foreach ( $lines as $line ) {
			if ( ! is_array( $line ) ) {
				continue;
			}
			$html .= '<p style="margin:0 0 4px;">' . nh_wl_email_escape( ( isset( $line['label'] ) ? $line['label'] : '' ) . ': ' . ( isset( $line['value'] ) ? $line['value'] : '' ) ) . '</p>';
		}
		if ( '' !== $price ) {
			$html .= '<p style="margin:8px 0 0;font-weight:700;">' . $price . '</p>';
		}
		if ( '' !== $notice ) {
			$html .= '<p style="margin:8px 0 0;color:#8a4b08;">' . $notice . '</p>';
		}
		if ( '' !== $url && '' !== $open ) {
			$html .= '<p style="margin:8px 0 0;"><a href="' . $url . '" style="color:#00704a;">' . $open . '</a></p>';
		}
		$html .= '</div>';
	}
	$html .= '</div></body></html>';
	return $html;
}
