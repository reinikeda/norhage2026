<?php
/**
 * WooCommerce email placeholder fixes.
 *
 * Lithuanian WooCommerce language packs translate the New order subject
 * placeholder {order_number} to {užsakymo_numeris}. WooCommerce only
 * replaces the English token, so the subject stays literal while the
 * heading (which kept {order_number}) still injects the number.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Placeholders that language packs have used in place of {order_number}.
 *
 * @return string[]
 */
function nh_woocommerce_translated_order_number_placeholders() {
	return array(
		'{užsakymo_numeris}',
		'{uzsakymo_numeris}',
	);
}

/**
 * Restore English {order_number} in a translated WooCommerce string.
 *
 * @param string $translation Translated text.
 * @param string $original    English source text.
 * @return string
 */
function nh_restore_woocommerce_email_placeholder_tokens( $translation, $original ) {
	if ( ! is_string( $translation ) || ! is_string( $original ) ) {
		return $translation;
	}

	if ( false === strpos( $original, '{order_number}' ) ) {
		return $translation;
	}

	return str_replace(
		nh_woocommerce_translated_order_number_placeholders(),
		'{order_number}',
		$translation
	);
}

/**
 * Replace leftover translated order-number tokens with the real number.
 *
 * Used after WooCommerce format_string(), for saved subjects that already
 * contain {užsakymo_numeris} and never pass through gettext.
 *
 * @param string $string       Subject/heading after placeholder replacement.
 * @param string $order_number Order number to inject.
 * @return string
 */
function nh_inject_order_number_for_translated_placeholders( $string, $order_number ) {
	if ( ! is_string( $string ) || '' === $string || '' === (string) $order_number ) {
		return $string;
	}

	return str_replace(
		nh_woocommerce_translated_order_number_placeholders(),
		(string) $order_number,
		$string
	);
}

/**
 * Keep {order_number} untranslated in WooCommerce gettext strings.
 *
 * @param string $translation Translated text.
 * @param string $text        English source text.
 * @return string
 */
function nh_gettext_woocommerce_email_placeholders( $translation, $text ) {
	return nh_restore_woocommerce_email_placeholder_tokens( $translation, $text );
}
add_filter( 'gettext_woocommerce', 'nh_gettext_woocommerce_email_placeholders', 10, 2 );

/**
 * Same restore for contextual WooCommerce translations.
 *
 * @param string $translation Translated text.
 * @param string $text        English source text.
 * @param string $context     gettext context.
 * @return string
 */
function nh_gettext_with_context_woocommerce_email_placeholders( $translation, $text, $context ) {
	unset( $context );
	return nh_restore_woocommerce_email_placeholder_tokens( $translation, $text );
}
add_filter( 'gettext_with_context_woocommerce', 'nh_gettext_with_context_woocommerce_email_placeholders', 10, 3 );

/**
 * Safety net: replace leftover translated placeholders after format_string.
 *
 * @param string   $string Formatted email string.
 * @param WC_Email $email  Email instance.
 * @return string
 */
function nh_replace_translated_woocommerce_email_placeholders( $string, $email ) {
	$order_number = '';

	if ( is_object( $email ) && ! empty( $email->placeholders['{order_number}'] ) ) {
		$order_number = (string) $email->placeholders['{order_number}'];
	} elseif ( is_object( $email ) && isset( $email->object ) && class_exists( 'WC_Order' ) && $email->object instanceof WC_Order ) {
		$order_number = (string) $email->object->get_order_number();
	}

	return nh_inject_order_number_for_translated_placeholders( $string, $order_number );
}
add_filter( 'woocommerce_email_format_string', 'nh_replace_translated_woocommerce_email_placeholders', 10, 2 );
