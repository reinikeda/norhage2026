<?php
/**
 * Sequence, palette, and locale copy. Custom admin text is stored only when it
 * differs from the shop-language default, so translations keep working.
 *
 * @package nh-cart-recovery
 */

if ( ! defined( 'ABSPATH' ) && php_sapi_name() !== 'cli' ) {
	exit;
}

/**
 * @return array<string, mixed>
 */
function nh_cr_default_settings() {
	$out = array(
		'enabled'            => 1,
		'checkout_on_cancel' => 1,
		'email_1_minutes'    => 60,
		'email_2_hours'      => 24,
		'email_3_hours'      => 72,
		'max_emails'         => 3,
		'delete_after_days'  => 30,
	);
	foreach ( nh_cr_copy_field_keys() as $key ) {
		$out[ $key ] = '';
	}
	return $out;
}

/**
 * @return array<int, string>
 */
function nh_cr_copy_field_keys() {
	$keys = array();
	foreach ( array( 'cart', 'checkout' ) as $type ) {
		foreach ( array( 1, 2, 3 ) as $step ) {
			foreach ( array( 'subject', 'heading', 'intro', 'body', 'button' ) as $field ) {
				$keys[] = "copy_{$type}_{$step}_{$field}";
			}
		}
	}
	return $keys;
}

/**
 * @return array<string, mixed>
 */
function nh_cr_get_settings() {
	$saved = function_exists( 'get_option' ) ? get_option( 'nh_cr_settings', array() ) : array();
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( nh_cr_default_settings(), $saved );
}

/**
 * @return array{green:string,forest:string,charcoal:string,cream:string,offwhite:string,mint:string,gold:string,muted:string}
 */
function nh_cr_palette() {
	return array(
		'green'    => '#00704A',
		'forest'   => '#1E3932',
		'charcoal' => '#2C2A29',
		'cream'    => '#F1E6D6',
		'offwhite' => '#FAF7F2',
		'mint'     => '#C3E8C6',
		'gold'     => '#C89F63',
		'muted'    => '#8e8c89',
	);
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
 * @param int $step 1–3.
 * @return int
 */
function nh_cr_normalize_step( $step ) {
	$step = (int) $step;
	if ( $step < 1 ) {
		return 1;
	}
	if ( $step > 3 ) {
		return 3;
	}
	return $step;
}

/**
 * Default copy for one email in the sequence.
 *
 * @param string $locale Locale.
 * @param string $type   cart|checkout.
 * @param int    $step   1–3.
 * @return array{subject:string,heading:string,intro:string,body:string,button:string}
 */
function nh_cr_default_copy( $locale, $type = 'cart', $step = 1 ) {
	$type  = 'checkout' === $type ? 'checkout' : 'cart';
	$step  = nh_cr_normalize_step( $step );
	$group = nh_cr_locale_group( $locale );
	$all   = nh_cr_copy_catalog();
	$pack  = isset( $all[ $group ] ) ? $all[ $group ] : $all['en'];
	if ( isset( $pack[ $type ][ $step ] ) ) {
		return $pack[ $type ][ $step ];
	}
	return $all['en'][ $type ][ $step ];
}

/**
 * Shared chrome strings (table headers, unsubscribe).
 *
 * @param string $locale Locale.
 * @return array<string, string>
 */
function nh_cr_ui_copy( $locale ) {
	$group = nh_cr_locale_group( $locale );
	$all   = array(
		'en' => array(
			'hi_named'  => 'Hi %s,',
			'hi_anon'   => 'Hi,',
			'product'   => 'Product',
			'qty'       => 'Qty',
			'total'     => 'Total',
			'unsub'     => 'Unsubscribe from cart reminder emails',
			'secure'    => 'Secure checkout. Nothing is charged until you complete payment.',
		),
		'sv' => array(
			'hi_named'  => 'Hej %s,',
			'hi_anon'   => 'Hej,',
			'product'   => 'Produkt',
			'qty'       => 'Antal',
			'total'     => 'Summa',
			'unsub'     => 'Avsluta påminnelser om varukorgen',
			'secure'    => 'Säker kassa. Inget dras förrän du slutför betalningen.',
		),
		'nb' => array(
			'hi_named'  => 'Hei %s,',
			'hi_anon'   => 'Hei,',
			'product'   => 'Produkt',
			'qty'       => 'Antall',
			'total'     => 'Sum',
			'unsub'     => 'Meld deg av påminnelser om handlekurven',
			'secure'    => 'Sikker kasse. Ingenting trekkes før du fullfører betalingen.',
		),
		'da' => array(
			'hi_named'  => 'Hej %s,',
			'hi_anon'   => 'Hej,',
			'product'   => 'Produkt',
			'qty'       => 'Antal',
			'total'     => 'Total',
			'unsub'     => 'Afmeld påmindelser om kurven',
			'secure'    => 'Sikker kasse. Der trækkes intet, før du gennemfører betalingen.',
		),
		'fi' => array(
			'hi_named'  => 'Hei %s,',
			'hi_anon'   => 'Hei,',
			'product'   => 'Tuote',
			'qty'       => 'Määrä',
			'total'     => 'Yhteensä',
			'unsub'     => 'Peru ostoskorimuistutukset',
			'secure'    => 'Turvallinen kassa. Maksua ei veloiteta ennen kuin viimeistelet tilauksen.',
		),
		'de' => array(
			'hi_named'  => 'Hallo %s,',
			'hi_anon'   => 'Hallo,',
			'product'   => 'Artikel',
			'qty'       => 'Menge',
			'total'     => 'Summe',
			'unsub'     => 'Warenkorb-Erinnerungen abbestellen',
			'secure'    => 'Sichere Kasse. Es wird nichts abgebucht, bevor Sie die Zahlung abschließen.',
		),
		'lt' => array(
			'hi_named'  => 'Sveiki, %s,',
			'hi_anon'   => 'Sveiki,',
			'product'   => 'Prekė',
			'qty'       => 'Kiekis',
			'total'     => 'Suma',
			'unsub'     => 'Atsisakyti krepšelio priminimų',
			'secure'    => 'Saugus atsiskaitymas. Nieko nenuimama, kol nebaigiate mokėjimo.',
		),
	);
	return isset( $all[ $group ] ) ? $all[ $group ] : $all['en'];
}

/**
 * Merge locale defaults with shop overrides.
 *
 * @param array  $settings Settings.
 * @param string $locale   Locale.
 * @param string $type     cart|checkout.
 * @param int    $step     1–3.
 * @return array{subject:string,heading:string,intro:string,body:string,button:string}
 */
function nh_cr_effective_copy( $settings, $locale, $type, $step ) {
	$copy = nh_cr_default_copy( $locale, $type, $step );
	$type = 'checkout' === $type ? 'checkout' : 'cart';
	$step = nh_cr_normalize_step( $step );
	foreach ( array( 'subject', 'heading', 'intro', 'body', 'button' ) as $field ) {
		$key = "copy_{$type}_{$step}_{$field}";
		if ( ! empty( $settings[ $key ] ) && trim( (string) $settings[ $key ] ) !== '' ) {
			$copy[ $field ] = (string) $settings[ $key ];
		}
	}
	return $copy;
}

/**
 * Value shown in the admin editor: custom text, otherwise translated default.
 *
 * @param array  $settings Settings.
 * @param string $locale   Locale.
 * @param string $type     cart|checkout.
 * @param int    $step     1–3.
 * @param string $field    Field.
 * @return string
 */
function nh_cr_editor_value( $settings, $locale, $type, $step, $field ) {
	$type = 'checkout' === $type ? 'checkout' : 'cart';
	$step = nh_cr_normalize_step( $step );
	$key  = "copy_{$type}_{$step}_{$field}";
	if ( ! empty( $settings[ $key ] ) && trim( (string) $settings[ $key ] ) !== '' ) {
		return (string) $settings[ $key ];
	}
	$copy = nh_cr_default_copy( $locale, $type, $step );
	return isset( $copy[ $field ] ) ? $copy[ $field ] : '';
}

/**
 * Store empty when the shop left the translated default, so copy can still be updated in code.
 *
 * @param string $submitted Submitted text.
 * @param string $locale    Locale.
 * @param string $type      cart|checkout.
 * @param int    $step      1–3.
 * @param string $field     Field.
 * @return string
 */
function nh_cr_sanitize_copy_field( $submitted, $locale, $type, $step, $field ) {
	$submitted = is_string( $submitted ) ? trim( $submitted ) : '';
	$default   = nh_cr_default_copy( $locale, $type, $step );
	$expected  = isset( $default[ $field ] ) ? trim( (string) $default[ $field ] ) : '';
	if ( $submitted === '' || $submitted === $expected ) {
		return '';
	}
	return $submitted;
}

/**
 * @param string $text       Template.
 * @param string $first_name Name.
 * @return string
 */
function nh_cr_personalize( $text, $first_name ) {
	$text = (string) $text;
	$name = trim( (string) $first_name );
	if ( $name === '' ) {
		$text = preg_replace( '/\{first_name\},?\s*/u', '', $text );
		$text = trim( (string) $text );
		if ( $text !== '' ) {
			$text = function_exists( 'mb_strtoupper' )
				? mb_strtoupper( mb_substr( $text, 0, 1 ) ) . mb_substr( $text, 1 )
				: ucfirst( $text );
		}
		return $text;
	}
	return trim( str_replace( '{first_name}', $name, $text ) );
}

/**
 * Whether the next sequence email is due.
 *
 * @param object $row      Store row.
 * @param array  $settings Settings.
 * @param int    $now      Unix time (WP local).
 * @return int Next step (1–3) or 0 if not due.
 */
function nh_cr_next_due_step( $row, $settings, $now ) {
	$sent = isset( $row->emails_sent ) ? (int) $row->emails_sent : 0;
	$max  = isset( $settings['max_emails'] ) ? max( 1, min( 3, (int) $settings['max_emails'] ) ) : 3;
	if ( $sent >= $max ) {
		return 0;
	}
	$type = isset( $row->type ) ? (string) $row->type : 'cart';
	$d1   = isset( $settings['email_1_minutes'] ) ? max( 15, (int) $settings['email_1_minutes'] ) : 60;
	$d2   = isset( $settings['email_2_hours'] ) ? max( 6, (int) $settings['email_2_hours'] ) : 24;
	$d3   = isset( $settings['email_3_hours'] ) ? max( 24, (int) $settings['email_3_hours'] ) : 72;

	if ( $sent === 0 ) {
		$updated = isset( $row->updated_at ) ? strtotime( (string) $row->updated_at ) : 0;
		$wait    = ( 'checkout' === $type ) ? ( 5 * 60 ) : ( $d1 * 60 );
		return ( $updated && ( $now - $updated ) >= $wait ) ? 1 : 0;
	}

	$first = ! empty( $row->first_emailed_at ) ? strtotime( (string) $row->first_emailed_at ) : 0;
	if ( ! $first && ! empty( $row->emailed_at ) ) {
		$first = strtotime( (string) $row->emailed_at );
	}
	if ( ! $first ) {
		return 0;
	}
	if ( $sent === 1 ) {
		return ( $now - $first ) >= ( $d2 * HOUR_IN_SECONDS ) ? 2 : 0;
	}
	if ( $sent === 2 ) {
		return ( $now - $first ) >= ( $d3 * HOUR_IN_SECONDS ) ? 3 : 0;
	}
	return 0;
}

/**
 * High-converting defaults. {first_name} is removed when no name is known.
 *
 * @return array<string, mixed>
 */
function nh_cr_copy_catalog() {
	$en_cart = array(
		1 => array(
			'subject' => '{first_name}, your Norhage cart is saved',
			'heading' => 'Your items are waiting',
			'intro'   => 'You added items to your cart but did not finish checkout. Nothing has been charged. Your selection is saved, including custom sizes.',
			'body'    => 'You can change quantity, delivery or payment at checkout. It only takes a minute to continue.',
			'button'  => 'Continue to checkout',
		),
		2 => array(
			'subject' => '{first_name}, still want these items?',
			'heading' => 'Your cart is still here',
			'intro'   => 'A quick reminder: the items you chose at Norhage are still saved for you.',
			'body'    => 'Greenhouse panels and custom-cut parts are made to order. Completing checkout now keeps your exact sizes.',
			'button'  => 'Return to my cart',
		),
		3 => array(
			'subject' => 'Last reminder from Norhage',
			'heading' => 'We will stop reminding you',
			'intro'   => 'This is the last email about the items you left. If you still want them, they are one click away.',
			'body'    => 'If you changed your mind, you can ignore this message or unsubscribe below. We will not send another reminder for this cart.',
			'button'  => 'Complete my order',
		),
	);
	$en_check = array(
		1 => array(
			'subject' => '{first_name}, payment was not completed',
			'heading' => 'Your order is not paid yet',
			'intro'   => 'You started checkout at Norhage, but payment was not finished. Nothing was charged.',
			'body'    => 'Your items are still saved. Continue to checkout to complete BankID, card or invoice — it only takes a minute.',
			'button'  => 'Complete payment',
		),
		2 => array(
			'subject' => '{first_name}, your Norhage order is still open',
			'heading' => 'The items are still reserved in your cart',
			'intro'   => 'Yesterday you started an order but payment did not go through. The cart is still waiting.',
			'body'    => 'This is common with BankID or card — you can try again now. Nothing is charged until payment succeeds.',
			'button'  => 'Try checkout again',
		),
		3 => array(
			'subject' => 'Last reminder: finish your Norhage order',
			'heading' => 'Last chance to keep this cart',
			'intro'   => 'This is the last reminder. Your unpaid checkout was never captured, so you have not been charged.',
			'body'    => 'If you still want the items, complete checkout now. After this we will stop emailing about this cart.',
			'button'  => 'Finish my order',
		),
	);

	return array(
		'en' => array(
			'cart'     => $en_cart,
			'checkout' => $en_check,
		),
		'sv' => array(
			'cart'     => array(
				1 => array(
					'subject' => '{first_name}, din varukorg hos Norhage är sparad',
					'heading' => 'Dina varor väntar',
					'intro'   => 'Du lade varor i varukorgen men gick inte hela vägen till kassan. Inget har dragits. Urvalet är sparat, även specialmått.',
					'body'    => 'Du kan ändra antal, leverans eller betalning i kassan. Det tar bara en minut att fortsätta.',
					'button'  => 'Fortsätt till kassan',
				),
				2 => array(
					'subject' => '{first_name}, vill du fortfarande ha varorna?',
					'heading' => 'Varukorgen är kvar',
					'intro'   => 'En kort påminnelse: varorna du valde hos Norhage är fortfarande sparade.',
					'body'    => 'Kanalplast och specialkapade delar tillverkas mot order. Om du slutför köpet nu behåller du exakt de mått du valde.',
					'button'  => 'Tillbaka till varukorgen',
				),
				3 => array(
					'subject' => 'Sista påminnelsen från Norhage',
					'heading' => 'Vi slutar påminna efter detta',
					'intro'   => 'Det här är sista mejlet om varorna du lämnade. Vill du ha dem är det ett klick bort.',
					'body'    => 'Har du ångrat dig kan du ignorera mejlet eller avregistrera dig nedan. Vi skickar inga fler påminnelser om den här varukorgen.',
					'button'  => 'Slutför köpet',
				),
			),
			'checkout' => array(
				1 => array(
					'subject' => '{first_name}, betalningen slutfördes inte',
					'heading' => 'Ordern är inte betald än',
					'intro'   => 'Du påbörjade kassan hos Norhage, men betalningen gick inte igenom. Inget har dragits.',
					'body'    => 'Varorna är fortfarande sparade. Fortsätt till kassan och slutför med BankID, kort eller faktura — det tar bara en minut.',
					'button'  => 'Slutför betalningen',
				),
				2 => array(
					'subject' => '{first_name}, din Norhage-order väntar fortfarande',
					'heading' => 'Varorna ligger kvar i kassan',
					'intro'   => 'Igår påbörjade du en order men betalningen blev inte klar. Varukorgen väntar.',
					'body'    => 'Det är vanligt med BankID eller kort. Du kan prova igen nu. Inget dras förrän betalningen lyckas.',
					'button'  => 'Försök igen i kassan',
				),
				3 => array(
					'subject' => 'Sista påminnelsen: slutför din order',
					'heading' => 'Sista chansen att behålla varukorgen',
					'intro'   => 'Det här är sista påminnelsen. Den obetalda kassan fångades aldrig, så du har inte debiterats.',
					'body'    => 'Vill du fortfarande ha varorna? Slutför kassan nu. Därefter skickar vi inga fler mejl om den här ordern.',
					'button'  => 'Slutför min order',
				),
			),
		),
		'nb' => array(
			'cart'     => array(
				1 => array(
					'subject' => '{first_name}, handlekurven din hos Norhage er lagret',
					'heading' => 'Varene dine venter',
					'intro'   => 'Du la varer i handlekurven, men fullførte ikke kassen. Ingenting er trukket. Utvalget er lagret, også tilpassede mål.',
					'body'    => 'Du kan endre antall, levering eller betaling i kassen. Det tar bare et minutt å fortsette.',
					'button'  => 'Fortsett til kassen',
				),
				2 => array(
					'subject' => '{first_name}, vil du fortsatt ha varene?',
					'heading' => 'Handlekurven er der fortsatt',
					'intro'   => 'En kort påminnelse: varene du valgte hos Norhage er fortsatt lagret.',
					'body'    => 'Kanalplast og tilpasset kapping lages på bestilling. Fullfører du nå, beholder du nøyaktig de målene du valgte.',
					'button'  => 'Tilbake til handlekurven',
				),
				3 => array(
					'subject' => 'Siste påminnelse fra Norhage',
					'heading' => 'Vi slutter å minne deg etter dette',
					'intro'   => 'Dette er siste e-post om varene du lot ligge. Vil du ha dem, er det ett klikk unna.',
					'body'    => 'Har du ombestemt deg, kan du se bort fra denne meldingen eller melde deg av under. Vi sender ikke flere påminnelser om denne kurven.',
					'button'  => 'Fullfør kjøpet',
				),
			),
			'checkout' => array(
				1 => array(
					'subject' => '{first_name}, betalingen ble ikke fullført',
					'heading' => 'Bestillingen er ikke betalt ennå',
					'intro'   => 'Du startet kassen hos Norhage, men betalingen gikk ikke gjennom. Ingenting er trukket.',
					'body'    => 'Varene er fortsatt lagret. Fortsett til kassen og fullfør med BankID, kort eller faktura — det tar bare et minutt.',
					'button'  => 'Fullfør betalingen',
				),
				2 => array(
					'subject' => '{first_name}, Norhage-ordren din venter fortsatt',
					'heading' => 'Varene ligger fortsatt i kassen',
					'intro'   => 'I går startet du en bestilling, men betalingen ble ikke fullført. Handlekurven venter.',
					'body'    => 'Det er vanlig med BankID eller kort. Du kan prøve igjen nå. Ingenting trekkes før betalingen lykkes.',
					'button'  => 'Prøv kassen på nytt',
				),
				3 => array(
					'subject' => 'Siste påminnelse: fullfør bestillingen',
					'heading' => 'Siste sjanse til å beholde kurven',
					'intro'   => 'Dette er siste påminnelse. Den ubetalte kassen ble aldri gjennomført, så du er ikke belastet.',
					'body'    => 'Vil du fortsatt ha varene? Fullfør kassen nå. Etter dette sender vi ikke flere e-poster om denne ordren.',
					'button'  => 'Fullfør bestillingen',
				),
			),
		),
		'da' => array(
			'cart'     => array(
				1 => array(
					'subject' => '{first_name}, din Norhage-kurv er gemt',
					'heading' => 'Dine varer venter',
					'intro'   => 'Du lagde varer i kurven, men afsluttede ikke kassen. Der er ikke trukket noget. Dit valg er gemt, også specialmål.',
					'body'    => 'Du kan ændre antal, levering eller betaling i kassen. Det tager kun et minut at fortsætte.',
					'button'  => 'Fortsæt til kassen',
				),
				2 => array(
					'subject' => '{first_name}, vil du stadig have varerne?',
					'heading' => 'Kurven er der stadig',
					'intro'   => 'En kort påmindelse: varerne, du valgte hos Norhage, er stadig gemt.',
					'body'    => 'Kanalplast og specialtilskæring laves på bestilling. Gennemfører du nu, beholder du præcis de mål, du valgte.',
					'button'  => 'Tilbage til kurven',
				),
				3 => array(
					'subject' => 'Sidste påmindelse fra Norhage',
					'heading' => 'Vi stopper påmindelserne efter denne',
					'intro'   => 'Dette er den sidste mail om varerne, du lod ligge. Vil du have dem, er det ét klik væk.',
					'body'    => 'Har du fortrudt, kan du ignorere mailen eller afmelde dig nedenfor. Vi sender ikke flere påmindelser om denne kurv.',
					'button'  => 'Gennemfør købet',
				),
			),
			'checkout' => array(
				1 => array(
					'subject' => '{first_name}, betalingen blev ikke gennemført',
					'heading' => 'Ordren er ikke betalt endnu',
					'intro'   => 'Du startede kassen hos Norhage, men betalingen gik ikke igennem. Der er ikke trukket noget.',
					'body'    => 'Varerne er stadig gemt. Fortsæt til kassen og gennemfør med MitID, kort eller faktura — det tager kun et minut.',
					'button'  => 'Gennemfør betalingen',
				),
				2 => array(
					'subject' => '{first_name}, din Norhage-ordre venter stadig',
					'heading' => 'Varerne ligger stadig i kassen',
					'intro'   => 'I går startede du en ordre, men betalingen blev ikke gennemført. Kurven venter.',
					'body'    => 'Det sker ofte med MitID eller kort. Du kan prøve igen nu. Der trækkes intet, før betalingen lykkes.',
					'button'  => 'Prøv kassen igen',
				),
				3 => array(
					'subject' => 'Sidste påmindelse: gennemfør din ordre',
					'heading' => 'Sidste chance for at beholde kurven',
					'intro'   => 'Dette er sidste påmindelse. Den ubetalte kasse blev aldrig gennemført, så du er ikke blevet debiteret.',
					'body'    => 'Vil du stadig have varerne? Gennemfør kassen nu. Derefter sender vi ikke flere mails om denne ordre.',
					'button'  => 'Gennemfør min ordre',
				),
			),
		),
		'fi' => array(
			'cart'     => array(
				1 => array(
					'subject' => '{first_name}, Norhage-ostoskorisi on tallennettu',
					'heading' => 'Tuotteesi odottavat',
					'intro'   => 'Lisäsit tuotteita koriin, mutta et viimeistellyt kassaa. Mitään ei ole veloitettu. Valinta on tallessa, myös mittatilaukset.',
					'body'    => 'Voit muuttaa määrää, toimitusta tai maksutapaa kassalla. Jatkoon menee vain minuutti.',
					'button'  => 'Jatka kassalle',
				),
				2 => array(
					'subject' => '{first_name}, haluatko vielä nämä tuotteet?',
					'heading' => 'Ostoskorisi on yhä tallessa',
					'intro'   => 'Lyhyt muistutus: Norhagesta valitsemasi tuotteet odottavat yhä.',
					'body'    => 'Kennolevyt ja mittatilaukset tehdään tilauksesta. Kun viimeistelet nyt, säilyvät juuri ne mitat, jotka valitsit.',
					'button'  => 'Palaa ostoskoriin',
				),
				3 => array(
					'subject' => 'Viimeinen muistutus Norhagelta',
					'heading' => 'Tämä on viimeinen muistutus',
					'intro'   => 'Tämä on viimeinen viesti jättämistäsi tuotteista. Jos haluat ne, ne ovat yhden klikkauksen päässä.',
					'body'    => 'Jos muutit mieltäsi, voit jättää viestin huomiotta tai perua muistutukset alta. Emme lähetä tästä korista uusia viestejä.',
					'button'  => 'Viimeistele tilaus',
				),
			),
			'checkout' => array(
				1 => array(
					'subject' => '{first_name}, maksua ei viimeistelty',
					'heading' => 'Tilausta ei ole vielä maksettu',
					'intro'   => 'Aloitit Norhagen kassan, mutta maksu ei mennyt läpi. Mitään ei veloitettu.',
					'body'    => 'Tuotteet ovat yhä tallessa. Jatka kassalle ja viimeistele pankkitunnuksilla, kortilla tai laskulla — se vie vain minuutin.',
					'button'  => 'Viimeistele maksu',
				),
				2 => array(
					'subject' => '{first_name}, Norhage-tilauksesi odottaa yhä',
					'heading' => 'Tuotteet ovat yhä kassalla',
					'intro'   => 'Eilen aloitit tilauksen, mutta maksu ei valmistunut. Kori odottaa.',
					'body'    => 'Tämä on tavallista pankkitunnuksilla tai kortilla. Voit yrittää uudelleen nyt. Maksua ei veloiteta ennen onnistunutta suoritusta.',
					'button'  => 'Yritä kassaa uudelleen',
				),
				3 => array(
					'subject' => 'Viimeinen muistutus: viimeistele tilaus',
					'heading' => 'Viimeinen mahdollisuus pitää kori',
					'intro'   => 'Tämä on viimeinen muistutus. Maksamaton kassa ei toteutunut, joten sinua ei ole veloitettu.',
					'body'    => 'Jos haluat tuotteet yhä, viimeistele kassa nyt. Tämän jälkeen emme lähetä tästä tilauksesta uusia viestejä.',
					'button'  => 'Viimeistele tilaukseni',
				),
			),
		),
		'de' => array(
			'cart'     => array(
				1 => array(
					'subject' => '{first_name}, Ihr Norhage-Warenkorb ist gespeichert',
					'heading' => 'Ihre Artikel warten',
					'intro'   => 'Sie haben Artikel in den Warenkorb gelegt, aber nicht zur Kasse gegangen. Es wurde nichts abgebucht. Ihre Auswahl ist gespeichert, auch Sondermaße.',
					'body'    => 'Menge, Lieferung und Zahlung können Sie an der Kasse noch ändern. Weiter geht es in einer Minute.',
					'button'  => 'Weiter zur Kasse',
				),
				2 => array(
					'subject' => '{first_name}, möchten Sie die Artikel noch?',
					'heading' => 'Ihr Warenkorb ist noch da',
					'intro'   => 'Kurze Erinnerung: Die Artikel, die Sie bei Norhage gewählt haben, sind noch gespeichert.',
					'body'    => 'Stegplatten und Sonderzuschnitte werden auf Bestellung gefertigt. Wenn Sie jetzt abschließen, behalten Sie genau die Maße.',
					'button'  => 'Zurück zum Warenkorb',
				),
				3 => array(
					'subject' => 'Letzte Erinnerung von Norhage',
					'heading' => 'Danach erinnern wir nicht mehr',
					'intro'   => 'Das ist die letzte E-Mail zu den liegen gebliebenen Artikeln. Wenn Sie sie noch möchten, ist es ein Klick.',
					'body'    => 'Falls Sie es sich anders überlegt haben, ignorieren Sie diese Nachricht oder melden Sie sich unten ab. Weitere Erinnerungen zu diesem Warenkorb gibt es nicht.',
					'button'  => 'Bestellung abschließen',
				),
			),
			'checkout' => array(
				1 => array(
					'subject' => '{first_name}, die Zahlung wurde nicht abgeschlossen',
					'heading' => 'Die Bestellung ist noch nicht bezahlt',
					'intro'   => 'Sie haben die Kasse bei Norhage gestartet, aber die Zahlung ist nicht durchgegangen. Es wurde nichts abgebucht.',
					'body'    => 'Ihre Artikel sind noch gespeichert. Gehen Sie zur Kasse und schließen Sie mit BankID, Karte oder Rechnung ab — das dauert nur eine Minute.',
					'button'  => 'Zahlung abschließen',
				),
				2 => array(
					'subject' => '{first_name}, Ihre Norhage-Bestellung wartet noch',
					'heading' => 'Die Artikel liegen noch an der Kasse',
					'intro'   => 'Gestern haben Sie eine Bestellung begonnen, die Zahlung wurde aber nicht abgeschlossen. Der Warenkorb wartet.',
					'body'    => 'Das passiert häufig bei BankID oder Karte. Sie können es jetzt erneut versuchen. Abgebucht wird erst bei erfolgreicher Zahlung.',
					'button'  => 'Kasse erneut öffnen',
				),
				3 => array(
					'subject' => 'Letzte Erinnerung: Bestellung abschließen',
					'heading' => 'Letzte Chance, den Warenkorb zu behalten',
					'intro'   => 'Das ist die letzte Erinnerung. Der unbezahlte Checkout wurde nie abgeschlossen, Sie wurden nicht belastet.',
					'body'    => 'Wenn Sie die Artikel noch möchten, schließen Sie jetzt ab. Danach senden wir zu dieser Bestellung keine E-Mails mehr.',
					'button'  => 'Meine Bestellung abschließen',
				),
			),
		),
		'lt' => array(
			'cart'     => array(
				1 => array(
					'subject' => '{first_name}, jūsų Norhage krepšelis išsaugotas',
					'heading' => 'Jūsų prekės laukia',
					'intro'   => 'Įdėjote prekių į krepšelį, bet nebaigėte atsiskaitymo. Nieko nenuimta. Pasirinkimas išsaugotas, įskaitant nestandartinius matmenis.',
					'body'    => 'Kassoje galite pakeisti kiekį, pristatymą ar mokėjimą. Tęsti užtrunka tik minutę.',
					'button'  => 'Tęsti į atsiskaitymą',
				),
				2 => array(
					'subject' => '{first_name}, vis dar norite šių prekių?',
					'heading' => 'Krepšelis vis dar čia',
					'intro'   => 'Trumpas priminimas: Norhage pasirinktos prekės vis dar išsaugotos.',
					'body'    => 'Kanalinis plastikas ir nestandartinis pjovimas gaminami pagal užsakymą. Užbaigę dabar išlaikysite pasirinktus matmenis.',
					'button'  => 'Grįžti į krepšelį',
				),
				3 => array(
					'subject' => 'Paskutinis priminimas iš Norhage',
					'heading' => 'Daugiau nepriminsime',
					'intro'   => 'Tai paskutinis laiškas apie paliktas prekes. Jei vis dar norite — vienas paspaudimas.',
					'body'    => 'Persigalvojus galite ignoruoti laišką arba atsisakyti priminimų žemiau. Apie šį krepšelį daugiau nesiųsime.',
					'button'  => 'Užbaigti užsakymą',
				),
			),
			'checkout' => array(
				1 => array(
					'subject' => '{first_name}, mokėjimas nebuvo užbaigtas',
					'heading' => 'Užsakymas dar neapmokėtas',
					'intro'   => 'Pradėjote Norhage atsiskaitymą, bet mokėjimas nepraėjo. Nieko nenuimta.',
					'body'    => 'Prekės vis dar išsaugotos. Tęskite į kasą ir užbaikite su BankID, kortele ar sąskaita — tai užtrunka minutę.',
					'button'  => 'Užbaigti mokėjimą',
				),
				2 => array(
					'subject' => '{first_name}, jūsų Norhage užsakymas vis dar laukia',
					'heading' => 'Prekės vis dar kasoje',
					'intro'   => 'Vakar pradėjote užsakymą, bet mokėjimas nebuvo baigtas. Krepšelis laukia.',
					'body'    => 'Taip dažnai nutinka su BankID ar kortele. Galite bandyti vėl. Niekas nenuimama, kol mokėjimas nepavyksta sėkmingai.',
					'button'  => 'Bandyti kasą dar kartą',
				),
				3 => array(
					'subject' => 'Paskutinis priminimas: užbaikite užsakymą',
					'heading' => 'Paskutinė galimybė išlaikyti krepšelį',
					'intro'   => 'Tai paskutinis priminimas. Neapmokėtas atsiskaitymas nebuvo užfiksuotas, todėl jums nebuvo nuskaičiuota.',
					'body'    => 'Jei prekių vis dar norite, užbaikite kasą dabar. Po to apie šį užsakymą neberašysime.',
					'button'  => 'Užbaigti mano užsakymą',
				),
			),
		),
	);
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

	$image_url = '';
	if ( isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) && method_exists( $cart_item['data'], 'get_image_id' ) && function_exists( 'wp_get_attachment_image_url' ) ) {
		$image_id  = (int) $cart_item['data']->get_image_id();
		$image_url = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';
	}

	$line_total = 0.0;
	if ( isset( $cart_item['line_total'] ) && is_numeric( $cart_item['line_total'] ) ) {
		$line_total = (float) $cart_item['line_total'];
		if ( isset( $cart_item['line_tax'] ) && is_numeric( $cart_item['line_tax'] ) ) {
			$line_total += (float) $cart_item['line_tax'];
		}
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
		'line_total'     => $line_total,
		'image_url'      => $image_url,
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
	unset( $payment_method );
	return true;
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
