<?php
/**
 * Locale-aware default copy. Each shop can override subject/body in WooCommerce settings.
 *
 * @package nh-cart-recovery
 */

if ( ! defined( 'ABSPATH' ) && php_sapi_name() !== 'cli' ) {
	exit;
}

/**
 * @return array{enabled:int,cart_wait_minutes:int,checkout_on_cancel:int,delete_after_days:int,subject_cart:string,intro_cart:string,subject_checkout:string,intro_checkout:string}
 */
function nh_cr_default_settings() {
	return array(
		'enabled'             => 1,
		'cart_wait_minutes'   => 60,
		'checkout_on_cancel'  => 1,
		'delete_after_days'   => 30,
		'subject_cart'        => '',
		'intro_cart'          => '',
		'subject_checkout'    => '',
		'intro_checkout'      => '',
	);
}

/**
 * @return array<string, mixed>
 */
function nh_cr_get_settings() {
	$saved = get_option( 'nh_cr_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( nh_cr_default_settings(), $saved );
}

/**
 * @param string $locale WordPress locale.
 * @return string
 */
function nh_cr_locale_group( $locale ) {
	$locale = str_replace( '-', '_', (string) $locale );
	$map    = array(
		'sv_SE' => 'sv',
		'nb_NO' => 'nb',
		'nn_NO' => 'nb',
		'da_DK' => 'da',
		'fi'    => 'fi',
		'fi_FI' => 'fi',
		'de_DE' => 'de',
		'de_AT' => 'de',
		'lt_LT' => 'lt',
	);
	return isset( $map[ $locale ] ) ? $map[ $locale ] : 'en';
}

/**
 * Default customer-facing strings for one shop locale.
 *
 * @param string $locale WordPress locale.
 * @param string $type   cart|checkout.
 * @return array{subject:string,heading:string,intro:string,button:string}
 */
function nh_cr_default_copy( $locale, $type = 'cart' ) {
	$group = nh_cr_locale_group( $locale );
	$type  = 'checkout' === $type ? 'checkout' : 'cart';

	$all = array(
		'en' => array(
			'cart'     => array(
				'subject' => 'You left items in your cart',
				'heading' => 'Your cart is waiting',
				'intro'   => 'You added items to your cart but did not finish the order. You can pick up where you left off — your selection is saved.',
				'button'  => 'Return to checkout',
			),
			'checkout' => array(
				'subject' => 'Your order was not completed',
				'heading' => 'Payment was not finished',
				'intro'   => 'You started checkout, but payment was not completed. Nothing was charged. You can continue with the same items.',
				'button'  => 'Complete your order',
			),
		),
		'sv' => array(
			'cart'     => array(
				'subject' => 'Du har varor kvar i varukorgen',
				'heading' => 'Din varukorg väntar',
				'intro'   => 'Du lade varor i varukorgen men avslutade inte köpet. Du kan fortsätta där du slutade — ditt urval är sparat.',
				'button'  => 'Tillbaka till kassan',
			),
			'checkout' => array(
				'subject' => 'Din beställning slutfördes inte',
				'heading' => 'Betalningen slutfördes inte',
				'intro'   => 'Du påbörjade kassan, men betalningen genomfördes inte. Inget har dragits. Du kan fortsätta med samma varor.',
				'button'  => 'Slutför din beställning',
			),
		),
		'nb' => array(
			'cart'     => array(
				'subject' => 'Du har varer igjen i handlekurven',
				'heading' => 'Handlekurven din venter',
				'intro'   => 'Du la varer i handlekurven, men fullførte ikke kjøpet. Du kan fortsette der du slapp — utvalget ditt er lagret.',
				'button'  => 'Tilbake til kassen',
			),
			'checkout' => array(
				'subject' => 'Bestillingen din ble ikke fullført',
				'heading' => 'Betalingen ble ikke fullført',
				'intro'   => 'Du startet kassen, men betalingen ble ikke gjennomført. Ingenting er trukket. Du kan fortsette med de samme varene.',
				'button'  => 'Fullfør bestillingen',
			),
		),
		'da' => array(
			'cart'     => array(
				'subject' => 'Du har varer tilbage i kurven',
				'heading' => 'Din kurv venter',
				'intro'   => 'Du lagde varer i kurven, men afsluttede ikke købet. Du kan fortsætte, hvor du slap — dit valg er gemt.',
				'button'  => 'Tilbage til kassen',
			),
			'checkout' => array(
				'subject' => 'Din ordre blev ikke gennemført',
				'heading' => 'Betalingen blev ikke gennemført',
				'intro'   => 'Du startede kassen, men betalingen blev ikke gennemført. Der er ikke trukket noget. Du kan fortsætte med de samme varer.',
				'button'  => 'Gennemfør din ordre',
			),
		),
		'fi' => array(
			'cart'     => array(
				'subject' => 'Ostoskoriisi jäi tuotteita',
				'heading' => 'Ostoskorisi odottaa',
				'intro'   => 'Lisäsit tuotteita ostoskoriin, mutta et viimeistellyt tilausta. Voit jatkaa siitä, mihin jäit — valintasi on tallennettu.',
				'button'  => 'Takaisin kassalle',
			),
			'checkout' => array(
				'subject' => 'Tilaustasi ei viety loppuun',
				'heading' => 'Maksua ei viimeistelty',
				'intro'   => 'Aloitit kassan, mutta maksua ei suoritettu. Mitään ei veloitettu. Voit jatkaa samoilla tuotteilla.',
				'button'  => 'Viimeistele tilaus',
			),
		),
		'de' => array(
			'cart'     => array(
				'subject' => 'Sie haben Artikel im Warenkorb gelassen',
				'heading' => 'Ihr Warenkorb wartet',
				'intro'   => 'Sie haben Artikel in den Warenkorb gelegt, die Bestellung aber nicht abgeschlossen. Sie können dort weitermachen, wo Sie aufgehört haben.',
				'button'  => 'Zurück zur Kasse',
			),
			'checkout' => array(
				'subject' => 'Ihre Bestellung wurde nicht abgeschlossen',
				'heading' => 'Zahlung nicht abgeschlossen',
				'intro'   => 'Sie haben den Checkout gestartet, die Zahlung wurde aber nicht abgeschlossen. Es wurde nichts abgebucht. Sie können mit denselben Artikeln fortfahren.',
				'button'  => 'Bestellung abschließen',
			),
		),
		'lt' => array(
			'cart'     => array(
				'subject' => 'Krepšelyje liko prekių',
				'heading' => 'Jūsų krepšelis laukia',
				'intro'   => 'Įdėjote prekių į krepšelį, bet nebaigėte užsakymo. Galite tęsti ten, kur baigėte — pasirinkimas išsaugotas.',
				'button'  => 'Grįžti į atsiskaitymą',
			),
			'checkout' => array(
				'subject' => 'Užsakymas nebuvo užbaigtas',
				'heading' => 'Mokėjimas nebuvo baigtas',
				'intro'   => 'Pradėjote atsiskaitymą, bet mokėjimas nebuvo atliktas. Nieko nenuimta. Galite tęsti su tomis pačiomis prekėmis.',
				'button'  => 'Užbaigti užsakymą',
			),
		),
	);

	$pack = isset( $all[ $group ] ) ? $all[ $group ] : $all['en'];
	return $pack[ $type ];
}

/**
 * Woo internals that must not be fed back into add_to_cart().
 *
 * @return array<int, string>
 */
function nh_cr_skip_cart_keys() {
	return array(
		'key',
		'data',
		'data_hash',
		'line_tax_data',
		'line_subtotal',
		'line_subtotal_tax',
		'line_total',
		'line_tax',
	);
}

/**
 * @param array<string, mixed> $cart_item Woo cart item.
 * @return array<string, mixed>|null
 */
function nh_cr_snapshot_item( $cart_item ) {
	if ( ! is_array( $cart_item ) ) {
		return null;
	}
	$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
	if ( ! $product_id ) {
		return null;
	}
	$qty = isset( $cart_item['quantity'] ) ? (float) $cart_item['quantity'] : 0;
	if ( $qty <= 0 ) {
		return null;
	}

	$skip = array_flip( nh_cr_skip_cart_keys() );
	$data = array();
	foreach ( $cart_item as $key => $value ) {
		if ( isset( $skip[ $key ] ) || $key === 'product_id' || $key === 'quantity' ) {
			continue;
		}
		if ( is_object( $value ) ) {
			continue;
		}
		$data[ $key ] = $value;
	}

	$variation = array();
	if ( ! empty( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
		$variation = $cart_item['variation'];
		unset( $data['variation'] );
	}

	return array(
		'product_id'     => $product_id,
		'variation_id'   => isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0,
		'quantity'       => $qty,
		'variation'      => $variation,
		'cart_item_data' => $data,
		'name'           => isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) && method_exists( $cart_item['data'], 'get_name' )
			? (string) $cart_item['data']->get_name()
			: '',
	);
}

/**
 * @param array<int, array<string, mixed>> $items Snapshot items.
 * @return string
 */
function nh_cr_cart_hash( $items ) {
	$norm = array();
	foreach ( $items as $item ) {
		$norm[] = array(
			isset( $item['product_id'] ) ? (int) $item['product_id'] : 0,
			isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0,
			isset( $item['quantity'] ) ? (float) $item['quantity'] : 0,
		);
	}
	return md5( (string) ( function_exists( 'wp_json_encode' ) ? wp_json_encode( $norm ) : json_encode( $norm ) ) );
}

/**
 * Whether this unpaid cancel should get a recovery email.
 *
 * @param string $order_status Previous or current unpaid-ish status.
 * @param string $payment_method Gateway id.
 * @param bool   $has_later_paid_order Same email already placed a paid order.
 * @return bool
 */
function nh_cr_should_email_cancelled_checkout( $order_status, $payment_method, $has_later_paid_order ) {
	if ( $has_later_paid_order ) {
		return false;
	}
	$status = strtolower( (string) $order_status );
	if ( ! in_array( $status, array( 'pending', 'failed', 'cancelled', 'on-hold' ), true ) ) {
		return false;
	}
	$method = strtolower( (string) $payment_method );
	if ( $method === '' ) {
		return true;
	}
	return true;
}
