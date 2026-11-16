<?php
/**
 * Whole-krone prices for NOK, SEK and DKK.
 *
 * Prices stay entered before VAT, with 2 decimals, and WooCommerce keeps
 * 2 decimal places so Svea Checkout can send 124.00. For these three
 * currencies the amount the customer pays (price with VAT) is rounded to
 * the nearest whole unit. The invoice stores the before-VAT amount and the
 * VAT that add up to that whole amount.
 *
 * EUR is left on the normal 2-decimal tax math.
 *
 * The amount shown with VAT is rounded again at display time. A stored sale
 * such as 20% off a whole krone is 2 712,80 with VAT; the shop must show
 * 2 713, and the invoice net plus VAT must add up to that. Hiding trailing
 * ,00 is not enough, because the øre are real until this step. Prices shown
 * before VAT keep their øre, because that figure is the invoice net.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Currencies charged as whole units, including VAT.
 *
 * @return string[]
 */
function nh_ig_whole_currencies() {
	return array( 'NOK', 'SEK', 'DKK' );
}

/**
 * @param string $currency ISO code.
 * @return bool
 */
function nh_ig_currency_is_whole( $currency ) {
	return in_array( strtoupper( (string) $currency ), nh_ig_whole_currencies(), true );
}

/**
 * Round half away from zero, matching Woo's ex-VAT tax rounding.
 *
 * @param float $value
 * @param int   $precision
 * @return float
 */
function nh_ig_round_half_up( $value, $precision ) {
	$precision = (int) $precision;
	if ( $precision < 0 ) {
		$precision = 0;
	}
	$factor = pow( 10, $precision );
	$scaled = (float) $value * $factor;

	return round( $scaled, 0, PHP_ROUND_HALF_UP ) / $factor;
}

/**
 * @param array<int|string, mixed> $rates Woo rate rows, or id => percent.
 * @return array<int|string, array{rate: float, compound: string}>
 */
function nh_ig_normalize_rates( $rates ) {
	$out = array();
	foreach ( (array) $rates as $key => $rate ) {
		if ( is_numeric( $rate ) ) {
			$out[ $key ] = array(
				'rate'     => (float) $rate,
				'compound' => 'no',
			);
			continue;
		}
		if ( ! is_array( $rate ) || ! isset( $rate['rate'] ) ) {
			continue;
		}
		$compound = isset( $rate['compound'] ) && ( 'yes' === $rate['compound'] || true === $rate['compound'] || 1 === $rate['compound'] );
		$out[ $key ] = array(
			'rate'     => (float) $rate['rate'],
			'compound' => $compound ? 'yes' : 'no',
		);
	}
	return $out;
}

/**
 * Exclusive tax per rate, rounded to Woo's internal precision (6 dp).
 *
 * @param float                                            $net
 * @param array<int|string, array{rate: float, compound: string}> $rates
 * @return array<int|string, float>
 */
function nh_ig_exclusive_taxes( $net, array $rates ) {
	$taxes = array();
	$net   = (float) $net;
	$pre   = 0.0;

	foreach ( $rates as $key => $rate ) {
		if ( 'yes' === $rate['compound'] ) {
			continue;
		}
		$amount         = nh_ig_round_half_up( $net * ( $rate['rate'] / 100 ), 6 );
		$taxes[ $key ]  = $amount;
		$pre           += $amount;
	}

	foreach ( $rates as $key => $rate ) {
		if ( 'yes' !== $rate['compound'] ) {
			continue;
		}
		$amount        = nh_ig_round_half_up( ( $net + $pre ) * ( $rate['rate'] / 100 ), 6 );
		$taxes[ $key ] = $amount;
		$pre          += $amount;
	}

	return $taxes;
}

/**
 * Customer-facing gross for a net amount.
 *
 * With per-line rounding this matches wc_get_price_including_tax() when
 * prices are entered excluding tax: each tax is rounded to the shop
 * decimals, then added to the net.
 *
 * @param float                      $net
 * @param array<int|string, mixed>   $rates
 * @param int                        $price_decimals
 * @param bool                       $round_tax_per_line
 * @return float
 */
function nh_ig_gross_from_net( $net, $rates, $price_decimals = 2, $round_tax_per_line = true ) {
	$rates = nh_ig_normalize_rates( $rates );
	$net   = nh_ig_round_half_up( (float) $net, (int) $price_decimals );
	if ( empty( $rates ) ) {
		return $net;
	}

	$precise = nh_ig_exclusive_taxes( $net, $rates );
	$tax     = 0.0;
	if ( $round_tax_per_line ) {
		foreach ( $precise as $amount ) {
			$tax += nh_ig_round_half_up( $amount, (int) $price_decimals );
		}
		$tax = nh_ig_round_half_up( $tax, (int) $price_decimals );
	} else {
		$tax = array_sum( $precise );
	}

	return nh_ig_round_half_up( $net + $tax, (int) $price_decimals );
}

/**
 * Net, per-rate VAT and gross for a target gross.
 *
 * The gross is the nearest amount the tax math can actually produce, so
 * net + VAT always equals the gross that is stored.
 *
 * @param float                    $target_gross
 * @param array<int|string, mixed> $rates
 * @param float                    $seed_net Entered or previous net, used as a tie-break.
 * @param int                      $price_decimals
 * @return array{net: float, tax: float, taxes: array<int|string, float>, gross: float}
 */
function nh_ig_split_for_gross( $target_gross, $rates, $seed_net = 0.0, $price_decimals = 2 ) {
	$rates  = nh_ig_normalize_rates( $rates );
	$target = nh_ig_round_half_up( (float) $target_gross, 2 );
	if ( $target < 0 ) {
		$target = 0.0;
	}
	$decimals = (int) $price_decimals;

	if ( empty( $rates ) ) {
		$net = nh_ig_round_half_up( $target, $decimals );
		return array(
			'net'   => $net,
			'tax'   => 0.0,
			'taxes' => array(),
			'gross' => $net,
		);
	}

	$combined = 0.0;
	foreach ( $rates as $rate ) {
		if ( 'yes' !== $rate['compound'] ) {
			$combined += $rate['rate'] / 100;
		}
	}
	$start = nh_ig_round_half_up( $combined > 0 ? $target / ( 1 + $combined ) : $target, $decimals );

	$best_net   = $start;
	$best_gross = nh_ig_gross_from_net( $start, $rates, $decimals, true );
	$best_diff  = abs( $best_gross - $target );
	$seed       = (float) $seed_net;

	if ( $best_diff >= 0.001 ) {
		for ( $cents = -150; $cents <= 150; $cents++ ) {
			$try = nh_ig_round_half_up( $start + ( $cents / 100 ), $decimals );
			if ( $try < 0 ) {
				continue;
			}
			$gross = nh_ig_gross_from_net( $try, $rates, $decimals, true );
			$diff  = abs( $gross - $target );
			$closer = $diff < $best_diff - 0.0000001;
			$tie    = abs( $diff - $best_diff ) < 0.0000001 && abs( $try - $seed ) < abs( $best_net - $seed );
			if ( $closer || $tie ) {
				$best_diff  = $diff;
				$best_net   = $try;
				$best_gross = $gross;
			}
			if ( $diff < 0.001 ) {
				break;
			}
		}
	}

	$taxes   = array();
	$tax_sum = 0.0;
	foreach ( nh_ig_exclusive_taxes( $best_net, $rates ) as $key => $amount ) {
		$rounded       = nh_ig_round_half_up( $amount, $decimals );
		$taxes[ $key ] = $rounded;
		$tax_sum      += $rounded;
	}
	$tax_sum = nh_ig_round_half_up( $tax_sum, $decimals );
	$gross   = nh_ig_round_half_up( $best_net + $tax_sum, $decimals );

	return array(
		'net'   => $best_net,
		'tax'   => $tax_sum,
		'taxes' => $taxes,
		'gross' => $gross,
	);
}

/**
 * Round one ex-VAT amount so its VAT-inclusive price is a whole unit.
 *
 * @param float                    $net
 * @param array<int|string, mixed> $rates
 * @param int                      $price_decimals
 * @return array{net: float, tax: float, taxes: array<int|string, float>, gross: float}
 */
function nh_ig_adjust_net( $net, $rates, $price_decimals = 2 ) {
	$net = (float) $net;
	if ( $net <= 0 ) {
		return array(
			'net'   => $net,
			'tax'   => 0.0,
			'taxes' => array(),
			'gross' => max( 0.0, $net ),
		);
	}

	$decimals = (int) $price_decimals;
	$current  = nh_ig_gross_from_net( $net, $rates, $decimals, true );
	$target   = (float) round( $current, 0, PHP_ROUND_HALF_UP );
	if ( abs( $current - $target ) < 0.001 ) {
		$rates_n = nh_ig_normalize_rates( $rates );
		$taxes   = array();
		$tax_sum = 0.0;
		$stored  = nh_ig_round_half_up( $net, $decimals );
		foreach ( nh_ig_exclusive_taxes( $stored, $rates_n ) as $key => $amount ) {
			$rounded       = nh_ig_round_half_up( $amount, $decimals );
			$taxes[ $key ] = $rounded;
			$tax_sum      += $rounded;
		}
		$tax_sum = nh_ig_round_half_up( $tax_sum, $decimals );
		return array(
			'net'   => $stored,
			'tax'   => $tax_sum,
			'taxes' => $taxes,
			'gross' => nh_ig_round_half_up( $stored + $tax_sum, $decimals ),
		);
	}

	return nh_ig_split_for_gross( $target, $rates, $net, $decimals );
}

/**
 * VAT-inclusive amount for one cart line.
 *
 * A whole quantity keeps the whole unit price: 3 × 2 713 is 8 139.
 * Metres and other fractional quantities are rounded once on the line,
 * so 2.4 × 2 713 does not leave øre on the amount the customer pays.
 *
 * @param float $unit_gross Unit price including VAT.
 * @param float $qty
 * @return float
 */
function nh_ig_paid_line_gross( $unit_gross, $qty ) {
	$qty = (float) $qty;
	if ( $qty <= 0 ) {
		$qty = 1.0;
	}
	if ( ! function_exists( 'get_woocommerce_currency' ) || ! nh_ig_currency_is_whole( get_woocommerce_currency() ) ) {
		return nh_ig_round_half_up( (float) $unit_gross * $qty, 2 );
	}

	$unit = (float) round( (float) $unit_gross, 0, PHP_ROUND_HALF_UP );
	$line = $unit * $qty;
	if ( abs( $qty - round( $qty ) ) > 0.000001 ) {
		return (float) round( $line, 0, PHP_ROUND_HALF_UP );
	}

	return nh_ig_round_half_up( $line, 2 );
}

/**
 * Sum of the VAT-inclusive line amounts. This is the basket subtotal
 * the customer sees. The before-VAT amount stays on each line as the net.
 *
 * @param array<int|string, mixed> $lines Cart lines with line_subtotal and line_subtotal_tax.
 * @return float
 */
function nh_ig_inclusive_lines_gross( array $lines ) {
	$sum = 0.0;
	foreach ( $lines as $line ) {
		if ( ! is_array( $line ) ) {
			continue;
		}
		$net  = isset( $line['line_subtotal'] ) ? (float) $line['line_subtotal'] : 0.0;
		$tax  = isset( $line['line_subtotal_tax'] ) ? (float) $line['line_subtotal_tax'] : 0.0;
		$sum += nh_ig_round_half_up( $net + $tax, 2 );
	}

	return nh_ig_round_half_up( $sum, 2 );
}

/**
 * Line grosses for Svea. The payable unit price is multiplied by quantity,
 * and the VAT-inclusive discount is rounded to a whole unit.
 *
 * @param float $unit_gross
 * @param float $qty
 * @param float $discount_gross Discount including VAT, before rounding.
 * @return array{subtotal_gross: float, discount_gross: float, total_gross: float}
 */
function nh_ig_line_targets( $unit_gross, $qty, $discount_gross ) {
	$subtotal = nh_ig_paid_line_gross( $unit_gross, $qty );
	$discount = (float) round( (float) $discount_gross, 0, PHP_ROUND_HALF_UP );
	if ( $discount < 0 ) {
		$discount = 0.0;
	}
	if ( $discount > $subtotal ) {
		$discount = $subtotal;
	}

	return array(
		'subtotal_gross' => $subtotal,
		'discount_gross' => $discount,
		'total_gross'    => $subtotal - $discount,
	);
}

/**
 * Scale coupon shares so they add up to the rounded discount.
 *
 * @param array<int|string, float|int|string> $parts
 * @param float                               $target
 * @return array<int|string, float>
 */
function nh_ig_distribute_total( array $parts, $target ) {
	$target = nh_ig_round_half_up( (float) $target, 2 );
	if ( empty( $parts ) ) {
		return array();
	}

	$keys = array_keys( $parts );
	$last = $keys[ count( $keys ) - 1 ];
	$sum  = 0.0;
	foreach ( $parts as $value ) {
		$sum += (float) $value;
	}

	$out = array();
	if ( abs( $sum ) < 0.00001 ) {
		foreach ( $keys as $key ) {
			$out[ $key ] = 0.0;
		}
		$out[ $last ] = $target;
		return $out;
	}

	$running = 0.0;
	foreach ( $keys as $key ) {
		if ( $key === $last ) {
			continue;
		}
		$share         = nh_ig_round_half_up( ( (float) $parts[ $key ] / $sum ) * $target, 2 );
		$out[ $key ]   = $share;
		$running      += $share;
	}
	$out[ $last ] = nh_ig_round_half_up( $target - $running, 2 );

	return $out;
}

/**
 * @return bool
 */
function nh_ig_enabled() {
	if ( ! function_exists( 'get_woocommerce_currency' ) || ! nh_ig_currency_is_whole( get_woocommerce_currency() ) ) {
		return false;
	}
	if ( function_exists( 'is_admin' ) && is_admin() && ! nh_ig_is_storefront_request() ) {
		return false;
	}
	return true;
}

/**
 * Cart, checkout and the block store API. Not the product editor.
 *
 * @return bool
 */
function nh_ig_is_storefront_request() {
	if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$blocked = array(
			'woocommerce_save_variations',
			'woocommerce_load_variations',
			'woocommerce_add_variation',
			'woocommerce_bulk_edit_variations',
			'inline-save',
		);
		return ! in_array( $action, $blocked, true );
	}
	if ( defined( 'WC_DOING_AJAX' ) && WC_DOING_AJAX ) {
		return true;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		return false !== strpos( $uri, '/wc/store' );
	}
	return false;
}

/**
 * @param WC_Product $product
 * @return array<int|string, array{rate: float, compound: string}>
 */
function nh_ig_rates_for_product( $product ) {
	if ( ! is_object( $product ) || ! function_exists( 'WC_Tax' ) ) {
		return array();
	}
	if ( method_exists( $product, 'is_taxable' ) && ! $product->is_taxable() ) {
		return array();
	}
	if ( function_exists( 'WC' ) && WC()->customer && method_exists( WC()->customer, 'get_is_vat_exempt' ) && WC()->customer->get_is_vat_exempt() ) {
		return array();
	}
	$tax_class = method_exists( $product, 'get_tax_class' ) ? $product->get_tax_class() : '';
	$rates     = nh_ig_normalize_rates( WC_Tax::get_rates( $tax_class ) );
	// No matching customer rate would leave the basket total before VAT.
	// Use the shop's own VAT for a local customer. A foreign country with
	// no rate, and a VAT-exempt customer, stay without this VAT.
	if ( ! $rates && nh_ig_use_shop_vat_rates() && method_exists( 'WC_Tax', 'get_base_tax_rates' ) ) {
		$rates = nh_ig_normalize_rates( WC_Tax::get_base_tax_rates( $tax_class ) );
	}
	return $rates;
}

/**
 * Shop VAT applies when the customer is local, or has not chosen a country yet.
 *
 * @return bool
 */
function nh_ig_use_shop_vat_rates() {
	if ( nh_ig_customer_is_vat_exempt() ) {
		return false;
	}
	if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
		return true;
	}
	$customer = WC()->customer;
	$country  = '';
	if ( method_exists( $customer, 'get_shipping_country' ) ) {
		$country = (string) $customer->get_shipping_country();
	}
	if ( '' === $country && method_exists( $customer, 'get_billing_country' ) ) {
		$country = (string) $customer->get_billing_country();
	}
	if ( '' === $country ) {
		return true;
	}
	$base = '';
	if ( function_exists( 'wc_get_base_location' ) ) {
		$location = wc_get_base_location();
		if ( is_array( $location ) && isset( $location['country'] ) ) {
			$base = (string) $location['country'];
		}
	}
	return '' !== $base && $country === $base;
}

/**
 * A VAT-exempt customer is shown prices before VAT on purpose.
 *
 * @return bool
 */
function nh_ig_customer_is_vat_exempt() {
	return function_exists( 'WC' )
		&& WC()->customer
		&& method_exists( WC()->customer, 'get_is_vat_exempt' )
		&& WC()->customer->get_is_vat_exempt();
}

/**
 * Nearest whole amount the customer pays for a line that already includes VAT.
 *
 * Quantity is applied after the unit is rounded, so 3 × 2 713 stays 8 139
 * rather than rounding 3 × 2 712,80 on its own. A fractional quantity is
 * then rounded once on the line.
 *
 * @param float $amount Including VAT, already multiplied by quantity.
 * @param float $qty    Quantity used to produce $amount.
 * @return float
 */
function nh_ig_round_paid_amount( $amount, $qty = 1.0 ) {
	$amount = (float) $amount;
	$qty    = (float) $qty;
	if ( $qty <= 0 ) {
		$qty = 1.0;
	}
	if ( ! function_exists( 'get_woocommerce_currency' ) || ! nh_ig_currency_is_whole( get_woocommerce_currency() ) ) {
		return $amount;
	}
	return nh_ig_paid_line_gross( $amount / $qty, $qty );
}

/**
 * Catalog and variation JSON read a cached including-VAT price. Round that
 * cache on every read so an old transient cannot keep showing øre.
 *
 * Prices cached for the excluding-VAT view are the invoice net and stay
 * at 2 decimals.
 *
 * @param array<string, array<int|string, mixed>> $prices
 * @param WC_Product                              $product
 * @param bool                                    $for_display
 * @return array<string, array<int|string, mixed>>
 */
function nh_ig_filter_variation_prices( $prices, $product, $for_display ) {
	unset( $product );
	if ( ! $for_display || ! is_array( $prices ) ) {
		return $prices;
	}
	if ( function_exists( 'get_option' ) && 'incl' !== get_option( 'woocommerce_tax_display_shop' ) ) {
		return $prices;
	}
	foreach ( $prices as $type => $values ) {
		if ( ! is_array( $values ) ) {
			continue;
		}
		foreach ( $values as $id => $amount ) {
			if ( '' === $amount || null === $amount || ! is_numeric( $amount ) ) {
				continue;
			}
			$prices[ $type ][ $id ] = nh_ig_round_paid_amount( (float) $amount, 1 );
		}
	}
	return $prices;
}

/**
 * Last step for every "price with VAT" Woo formats. Sale prices, cutting
 * fees and variation JSON all pass through here.
 *
 * @param float      $price   Line amount including VAT.
 * @param float|int  $qty     Quantity already included in $price.
 * @param WC_Product $product Product, unused.
 * @return float
 */
function nh_ig_filter_including_tax( $price, $qty = 1, $product = null ) {
	unset( $product );
	if ( ! is_numeric( $price ) ) {
		return $price;
	}
	return nh_ig_round_paid_amount( (float) $price, (float) $qty );
}

/**
 * Keep the variation-price transient separate from caches built before
 * the display rounding, and from another currency on the same code.
 *
 * @param array<int|string, mixed> $hash
 * @return array<int|string, mixed>
 */
function nh_ig_variation_prices_hash( $hash ) {
	$hash[] = 'nh-ig-paid-unit';
	if ( function_exists( 'get_woocommerce_currency' ) ) {
		$hash[] = get_woocommerce_currency();
	}
	return $hash;
}

/**
 * Nearest whole unit for an amount that is already including VAT.
 *
 * @param float $amount
 * @return float
 */
function nh_ig_round_display_gross( $amount ) {
	$amount = (float) $amount;
	if ( ! function_exists( 'get_woocommerce_currency' ) || ! nh_ig_currency_is_whole( get_woocommerce_currency() ) ) {
		return $amount;
	}
	return (float) round( $amount, 0, PHP_ROUND_HALF_UP );
}

/**
 * Round a price that already includes VAT to a whole krone, and return the
 * before-VAT amount that adds back up to it.
 *
 * @param float           $ex      Amount before VAT.
 * @param float           $inc     Amount including VAT.
 * @param WC_Product|null $product Product used to read the VAT rate.
 * @return array{ex: float, inc: float}
 */
function nh_ig_round_display_pair( $ex, $inc, $product = null ) {
	$ex  = (float) $ex;
	$inc = (float) $inc;
	if ( ! function_exists( 'get_woocommerce_currency' ) || ! nh_ig_currency_is_whole( get_woocommerce_currency() ) ) {
		return array(
			'ex'  => $ex,
			'inc' => $inc,
		);
	}

	$target = (float) round( $inc, 0, PHP_ROUND_HALF_UP );
	$rates  = is_object( $product ) ? nh_ig_rates_for_product( $product ) : array();
	$split  = nh_ig_split_for_gross( $target, $rates, $ex );

	return array(
		'ex'  => (float) $split['net'],
		'inc' => (float) $split['gross'],
	);
}

/**
 * @param WC_Product $product
 * @return array<int, array{rate: float, compound: bool}>
 */
function nh_ig_rates_for_js( $product ) {
	$out = array();
	foreach ( nh_ig_rates_for_product( $product ) as $rate ) {
		$out[] = array(
			'rate'     => (float) $rate['rate'],
			'compound' => ( 'yes' === $rate['compound'] ),
		);
	}
	return $out;
}

/**
 * @param mixed      $price
 * @param WC_Product $product
 * @return mixed
 */
function nh_ig_filter_product_price( $price, $product ) {
	if ( ! nh_ig_enabled() || ! is_object( $product ) ) {
		return $price;
	}
	if ( '' === $price || null === $price || ! is_numeric( $price ) ) {
		return $price;
	}
	$original = (float) $price;
	if ( $original <= 0 ) {
		return $price;
	}

	static $depth = 0;
	if ( $depth > 0 ) {
		return $price;
	}
	$depth++;

	$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
	if ( function_exists( 'wc_prices_include_tax' ) && wc_prices_include_tax() ) {
		$target = (float) round( $original, 0, PHP_ROUND_HALF_UP );
		$depth--;
		return function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $target, $decimals ) : $target;
	}

	$rates = nh_ig_rates_for_product( $product );
	$split = nh_ig_adjust_net( $original, $rates, $decimals );
	$depth--;

	if ( abs( $split['net'] - $original ) < 0.0001 ) {
		return $price;
	}

	return function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $split['net'], $decimals ) : $split['net'];
}

/**
 * @param array<string, WC_Shipping_Rate> $rates
 * @return array<string, WC_Shipping_Rate>
 */
function nh_ig_filter_package_rates( $rates ) {
	if ( ! nh_ig_enabled() || ! is_array( $rates ) ) {
		return $rates;
	}
	foreach ( $rates as $rate ) {
		if ( is_object( $rate ) && method_exists( $rate, 'get_cost' ) && method_exists( $rate, 'set_cost' ) ) {
			nh_ig_round_shipping_rate( $rate );
		}
	}
	return $rates;
}

/**
 * @param WC_Shipping_Rate $rate
 * @return void
 */
function nh_ig_round_shipping_rate( $rate ) {
	$cost  = (float) $rate->get_cost();
	$taxes = method_exists( $rate, 'get_taxes' ) ? $rate->get_taxes() : array();
	if ( ! is_array( $taxes ) ) {
		$taxes = array();
	}

	$defs = array();
	foreach ( $taxes as $rate_id => $amount ) {
		unset( $amount );
		if ( function_exists( 'WC_Tax' ) && method_exists( 'WC_Tax', 'get_rate_percent_value' ) ) {
			$defs[ $rate_id ] = array(
				'rate'     => (float) WC_Tax::get_rate_percent_value( $rate_id ),
				'compound' => 'no',
			);
		}
	}

	$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
	$rounded  = nh_ig_round_cost_with_taxes( $cost, $taxes, $defs, $decimals );
	$rate->set_cost( $rounded['cost'] );
	if ( method_exists( $rate, 'set_taxes' ) ) {
		$rate->set_taxes( $rounded['taxes'] );
	}

	if ( ! method_exists( $rate, 'get_meta_data' ) || ! method_exists( $rate, 'add_meta_data' ) ) {
		return;
	}
	$meta = $rate->get_meta_data();
	if ( empty( $meta['nshift_data'] ) || ! is_array( $meta['nshift_data'] ) ) {
		return;
	}
	foreach ( $meta['nshift_data'] as $percent => $amounts ) {
		if ( is_array( $amounts ) && isset( $amounts['cost'] ) && is_numeric( $amounts['cost'] ) ) {
			$meta['nshift_data'][ $percent ]['cost'] = (float) round( (float) $amounts['cost'], 0, PHP_ROUND_HALF_UP );
		}
	}
	$rate->add_meta_data( 'nshift_data', $meta['nshift_data'] );
}

/**
 * @param float                    $cost
 * @param array<int|string, float> $taxes
 * @param array<int|string, mixed> $rate_defs
 * @param int                      $decimals
 * @return array{cost: float, taxes: array<int|string, float>, gross: float}
 */
function nh_ig_round_cost_with_taxes( $cost, array $taxes, $rate_defs, $decimals = 2 ) {
	$cost    = (float) $cost;
	$tax_sum = 0.0;
	foreach ( $taxes as $amount ) {
		$tax_sum += (float) $amount;
	}
	$gross  = nh_ig_round_half_up( $cost + $tax_sum, (int) $decimals );
	$target = (float) round( $gross, 0, PHP_ROUND_HALF_UP );
	if ( abs( $gross - $target ) < 0.001 ) {
		return array(
			'cost'  => $cost,
			'taxes' => $taxes,
			'gross' => $gross,
		);
	}

	$defs = nh_ig_normalize_rates( $rate_defs );
	if ( empty( $defs ) ) {
		if ( abs( $tax_sum ) < 0.001 ) {
			return array(
				'cost'  => $target,
				'taxes' => $taxes,
				'gross' => $target,
			);
		}
		$share = $target / $gross;
		$new_taxes = array();
		$new_tax   = 0.0;
		$keys      = array_keys( $taxes );
		$last      = $keys ? $keys[ count( $keys ) - 1 ] : null;
		foreach ( $taxes as $id => $amount ) {
			if ( $id === $last ) {
				continue;
			}
			$rounded       = nh_ig_round_half_up( (float) $amount * $share, (int) $decimals );
			$new_taxes[ $id ] = $rounded;
			$new_tax         += $rounded;
		}
		if ( null !== $last ) {
			$new_taxes[ $last ] = nh_ig_round_half_up( $target - nh_ig_round_half_up( $cost * $share, (int) $decimals ) - $new_tax, (int) $decimals );
			$new_tax           += $new_taxes[ $last ];
		}
		$new_cost = nh_ig_round_half_up( $target - $new_tax, (int) $decimals );
		return array(
			'cost'  => $new_cost,
			'taxes' => $new_taxes,
			'gross' => nh_ig_round_half_up( $new_cost + $new_tax, (int) $decimals ),
		);
	}

	$split = nh_ig_split_for_gross( $target, $defs, $cost, (int) $decimals );
	return array(
		'cost'  => $split['net'],
		'taxes' => $split['taxes'],
		'gross' => $split['gross'],
	);
}

/**
 * Snap cart lines, coupons and fees onto whole-unit grosses.
 *
 * @param WC_Cart $cart
 * @return void
 */
function nh_ig_after_calculate_totals( $cart ) {
	if ( ! nh_ig_enabled() || ! is_object( $cart ) || ! isset( $cart->cart_contents ) || ! is_array( $cart->cart_contents ) ) {
		return;
	}
	if ( function_exists( 'wc_prices_include_tax' ) && wc_prices_include_tax() ) {
		return;
	}

	static $running = false;
	if ( $running ) {
		return;
	}
	$running = true;

	$decimals     = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
	$sum_subtotal = 0.0;
	$sum_subtax   = 0.0;
	$sum_total    = 0.0;
	$sum_tax      = 0.0;
	$merged       = array();

	foreach ( $cart->cart_contents as $key => $item ) {
		if ( ! is_array( $item ) || empty( $item['data'] ) || ! is_object( $item['data'] ) ) {
			continue;
		}
		$product = $item['data'];
		$qty     = isset( $item['quantity'] ) ? (float) $item['quantity'] : 1.0;
		if ( $qty <= 0 ) {
			$qty = 1.0;
		}
		$rates = nh_ig_rates_for_product( $product );

		$unit_net = method_exists( $product, 'get_price' ) ? (float) $product->get_price() : 0.0;
		$unit     = nh_ig_adjust_net( $unit_net, $rates, $decimals );

		$sub_net  = isset( $item['line_subtotal'] ) ? (float) $item['line_subtotal'] : 0.0;
		$sub_tax  = isset( $item['line_subtotal_tax'] ) ? (float) $item['line_subtotal_tax'] : 0.0;
		$line_net = isset( $item['line_total'] ) ? (float) $item['line_total'] : 0.0;
		$line_tax = isset( $item['line_tax'] ) ? (float) $item['line_tax'] : 0.0;

		$old_sub_gross = nh_ig_round_half_up( $sub_net + $sub_tax, $decimals );
		$old_gross     = nh_ig_round_half_up( $line_net + $line_tax, $decimals );
		// No VAT rate must not turn a line that already has VAT into a before-VAT price.
		if ( empty( $rates ) && ( $sub_tax > 0.00001 || $line_tax > 0.00001 ) ) {
			$sum_subtotal += $sub_net;
			$sum_subtax   += $sub_tax;
			$sum_total    += $line_net;
			$sum_tax      += $line_tax;
			$existing = array();
			if ( isset( $item['line_tax_data']['total'] ) && is_array( $item['line_tax_data']['total'] ) ) {
				$existing = $item['line_tax_data']['total'];
			} elseif ( $line_tax > 0 ) {
				$existing = array( 'kept' => $line_tax );
			}
			foreach ( $existing as $rate_id => $amount ) {
				if ( ! isset( $merged[ $rate_id ] ) ) {
					$merged[ $rate_id ] = 0.0;
				}
				$merged[ $rate_id ] += (float) $amount;
			}
			continue;
		}
		$discount      = nh_ig_round_half_up( $old_sub_gross - $old_gross, $decimals );
		$targets       = nh_ig_line_targets( $unit['gross'], $qty, $discount );

		$sub_split  = nh_ig_split_for_gross( $targets['subtotal_gross'], $rates, $sub_net, $decimals );
		$line_split = nh_ig_split_for_gross( $targets['total_gross'], $rates, $line_net, $decimals );

		$cart->cart_contents[ $key ]['line_subtotal']     = $sub_split['net'];
		$cart->cart_contents[ $key ]['line_subtotal_tax'] = $sub_split['tax'];
		$cart->cart_contents[ $key ]['line_total']        = $line_split['net'];
		$cart->cart_contents[ $key ]['line_tax']          = $line_split['tax'];
		$cart->cart_contents[ $key ]['line_tax_data']     = array(
			'subtotal' => $sub_split['taxes'],
			'total'    => $line_split['taxes'],
		);

		$sum_subtotal += $sub_split['net'];
		$sum_subtax   += $sub_split['tax'];
		$sum_total    += $line_split['net'];
		$sum_tax      += $line_split['tax'];
		foreach ( $line_split['taxes'] as $rate_id => $amount ) {
			if ( ! isset( $merged[ $rate_id ] ) ) {
				$merged[ $rate_id ] = 0.0;
			}
			$merged[ $rate_id ] += (float) $amount;
		}
	}

	foreach ( $merged as $rate_id => $amount ) {
		$merged[ $rate_id ] = nh_ig_round_half_up( $amount, $decimals );
	}
	$sum_tax = nh_ig_round_half_up( array_sum( $merged ), $decimals );

	$need_discount_net = nh_ig_round_half_up( $sum_subtotal - $sum_total, $decimals );
	$need_discount_tax = nh_ig_round_half_up( $sum_subtax - $sum_tax, $decimals );

	if ( method_exists( $cart, 'get_coupon_discount_totals' ) && method_exists( $cart, 'set_coupon_discount_totals' ) ) {
		$coupon_net = nh_ig_distribute_total( (array) $cart->get_coupon_discount_totals(), $need_discount_net );
		$coupon_tax = array();
		if ( method_exists( $cart, 'get_coupon_discount_tax_totals' ) ) {
			$coupon_tax = nh_ig_distribute_total( (array) $cart->get_coupon_discount_tax_totals(), $need_discount_tax );
		}
		$cart->set_coupon_discount_totals( $coupon_net );
		if ( method_exists( $cart, 'set_coupon_discount_tax_totals' ) ) {
			$cart->set_coupon_discount_tax_totals( $coupon_tax );
		}
	}
	if ( method_exists( $cart, 'set_discount_total' ) ) {
		$cart->set_discount_total( $need_discount_net );
	}
	if ( method_exists( $cart, 'set_discount_tax' ) ) {
		$cart->set_discount_tax( $need_discount_tax );
	}
	if ( method_exists( $cart, 'set_subtotal' ) ) {
		$cart->set_subtotal( nh_ig_round_half_up( $sum_subtotal, $decimals ) );
	}
	if ( method_exists( $cart, 'set_subtotal_tax' ) ) {
		$cart->set_subtotal_tax( nh_ig_round_half_up( $sum_subtax, $decimals ) );
	}
	if ( method_exists( $cart, 'set_cart_contents_total' ) ) {
		$cart->set_cart_contents_total( nh_ig_round_half_up( $sum_total, $decimals ) );
	}
	if ( method_exists( $cart, 'set_cart_contents_tax' ) ) {
		$cart->set_cart_contents_tax( nh_ig_round_half_up( $sum_tax, $decimals ) );
	}
	if ( method_exists( $cart, 'set_cart_contents_taxes' ) ) {
		$cart->set_cart_contents_taxes( $merged );
	}

	$fee_net = method_exists( $cart, 'get_fee_total' ) ? (float) $cart->get_fee_total() : 0.0;
	$fee_tax = method_exists( $cart, 'get_fee_tax' ) ? (float) $cart->get_fee_tax() : 0.0;
	if ( method_exists( $cart, 'get_fees' ) ) {
		$fee_net = 0.0;
		$fee_tax = 0.0;
		$fee_merged = array();
		foreach ( $cart->get_fees() as $fee ) {
			if ( ! is_object( $fee ) ) {
				continue;
			}
			$total = isset( $fee->total ) ? (float) $fee->total : ( isset( $fee->amount ) ? (float) $fee->amount : 0.0 );
			$tax   = isset( $fee->tax ) ? (float) $fee->tax : 0.0;
			$gross = nh_ig_round_half_up( $total + $tax, $decimals );
			$sign  = $gross < 0 ? -1.0 : 1.0;
			$class = isset( $fee->tax_class ) ? (string) $fee->tax_class : '';
			$rates = array();
			if ( ! empty( $fee->taxable ) && function_exists( 'WC_Tax' ) ) {
				$rates = nh_ig_normalize_rates( WC_Tax::get_rates( $class ) );
				if ( ! $rates && nh_ig_use_shop_vat_rates() && method_exists( 'WC_Tax', 'get_base_tax_rates' ) ) {
					$rates = nh_ig_normalize_rates( WC_Tax::get_base_tax_rates( $class ) );
				}
			}
			if ( ! $rates && $tax > 0.00001 ) {
				$fee_net += $total;
				$fee_tax += $tax;
				continue;
			}
			$split = nh_ig_split_for_gross( abs( $gross ), $rates, abs( $total ), $decimals );
			$fee->total = $sign * $split['net'];
			$fee->tax   = $sign * $split['tax'];
			$signed     = array();
			foreach ( $split['taxes'] as $rate_id => $amount ) {
				$signed[ $rate_id ] = $sign * $amount;
				if ( ! isset( $fee_merged[ $rate_id ] ) ) {
					$fee_merged[ $rate_id ] = 0.0;
				}
				$fee_merged[ $rate_id ] += $signed[ $rate_id ];
			}
			$fee->tax_data = $signed;
			$fee_net      += $fee->total;
			$fee_tax      += $fee->tax;
		}
		if ( method_exists( $cart, 'set_fee_taxes' ) ) {
			$cart->set_fee_taxes( $fee_merged );
		}
	}
	if ( method_exists( $cart, 'set_fee_total' ) ) {
		$cart->set_fee_total( nh_ig_round_half_up( $fee_net, $decimals ) );
	}
	if ( method_exists( $cart, 'set_fee_tax' ) ) {
		$cart->set_fee_tax( nh_ig_round_half_up( $fee_tax, $decimals ) );
	}

	$ship_net = method_exists( $cart, 'get_shipping_total' ) ? (float) $cart->get_shipping_total() : 0.0;
	$ship_tax = method_exists( $cart, 'get_shipping_tax' ) ? (float) $cart->get_shipping_tax() : 0.0;

	$grand = nh_ig_round_half_up( $sum_total + $sum_tax + $ship_net + $ship_tax + $fee_net + $fee_tax, $decimals );
	if ( $grand < 0 ) {
		$grand = 0.0;
	}
	if ( method_exists( $cart, 'set_total_tax' ) ) {
		$cart->set_total_tax( nh_ig_round_half_up( $sum_tax + $ship_tax + $fee_tax, $decimals ) );
	}
	if ( method_exists( $cart, 'set_total' ) ) {
		$cart->set_total( $grand );
	}

	$running = false;
}

/**
 * Hide trailing ,00 once the amount really is a whole unit.
 *
 * @param bool $trim
 * @return bool
 */
function nh_ig_trim_zeros( $trim ) {
	if ( function_exists( 'get_woocommerce_currency' ) && nh_ig_currency_is_whole( get_woocommerce_currency() ) ) {
		return true;
	}
	return $trim;
}

/**
 * Basket and checkout always show the price with VAT.
 *
 * Prices are entered and stored before VAT. The catalog switcher can still
 * show a product page before VAT, but the basket, the side cart and checkout
 * show the amount the customer pays. That amount includes VAT. The product
 * editor keeps WooCommerce's saved option.
 *
 * @param mixed $value Option value coming from WordPress. False means "read the database".
 * @return mixed
 */
function nh_ig_frontend_cart_tax_display( $value ) {
	if ( function_exists( 'is_admin' ) && is_admin() && ! nh_ig_is_storefront_request() ) {
		return $value;
	}
	if ( nh_ig_customer_is_vat_exempt() ) {
		return 'excl';
	}
	return 'incl';
}

/**
 * Same rule as the cart tax-display option, for code that asks the cart
 * object instead of reading the option.
 *
 * @param bool $include Woo's own answer.
 * @return bool
 */
function nh_ig_cart_amounts_include_vat( $include ) {
	if ( function_exists( 'is_admin' ) && is_admin() && ! nh_ig_is_storefront_request() ) {
		return $include;
	}
	if ( nh_ig_customer_is_vat_exempt() ) {
		return false;
	}
	return true;
}

/**
 * Storefront basket and checkout, for a customer who pays VAT.
 *
 * @return bool
 */
function nh_ig_storefront_shows_vat() {
	if ( function_exists( 'is_admin' ) && is_admin() && ! nh_ig_is_storefront_request() ) {
		return false;
	}
	return ! nh_ig_customer_is_vat_exempt();
}

/**
 * Before-VAT amount plus its VAT. This is the price with VAT.
 *
 * @param float $net Amount before VAT.
 * @param float $tax VAT.
 * @return float
 */
function nh_ig_amount_with_vat( $net, $tax ) {
	$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
	return nh_ig_round_half_up( (float) $net + (float) $tax, $decimals );
}

/**
 * Order total with VAT: discounted lines, shipping and fees.
 *
 * @param WC_Cart $cart Cart.
 * @return float
 */
function nh_ig_charged_gross( $cart ) {
	if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
		return 0.0;
	}
	$gross = 0.0;
	foreach ( $cart->get_cart() as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$gross += nh_ig_amount_with_vat( $item['line_total'] ?? 0, $item['line_tax'] ?? 0 );
	}
	if ( method_exists( $cart, 'get_shipping_total' ) ) {
		$gross += (float) $cart->get_shipping_total();
	}
	if ( method_exists( $cart, 'get_shipping_tax' ) ) {
		$gross += (float) $cart->get_shipping_tax();
	}
	if ( method_exists( $cart, 'get_fee_total' ) ) {
		$gross += (float) $cart->get_fee_total();
	}
	if ( method_exists( $cart, 'get_fee_tax' ) ) {
		$gross += (float) $cart->get_fee_tax();
	}
	if ( $gross < 0 ) {
		$gross = 0.0;
	}
	$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
	return nh_ig_round_half_up( $gross, $decimals );
}

/**
 * Line total in the basket and checkout, with VAT.
 *
 * @param string $html          Existing HTML.
 * @param array  $cart_item     Cart line.
 * @param string $cart_item_key Cart key.
 * @return string
 */
function nh_ig_filter_visible_line_subtotal( $html, $cart_item, $cart_item_key ) {
	unset( $cart_item_key );
	if ( ! nh_ig_storefront_shows_vat() || ! is_array( $cart_item ) || ! function_exists( 'wc_price' ) ) {
		return $html;
	}
	$tax = (float) ( $cart_item['line_subtotal_tax'] ?? 0 );
	if ( $tax <= 0.00001 ) {
		return $html;
	}
	$gross = nh_ig_amount_with_vat( $cart_item['line_subtotal'] ?? 0, $tax );
	if ( $gross <= 0 ) {
		return $html;
	}
	return wc_price( $gross );
}

/**
 * Unit price in the basket, with VAT.
 *
 * @param string $html          Existing HTML.
 * @param array  $cart_item     Cart line.
 * @param string $cart_item_key Cart key.
 * @return string
 */
function nh_ig_filter_visible_line_price( $html, $cart_item, $cart_item_key ) {
	unset( $cart_item_key );
	if ( ! nh_ig_storefront_shows_vat() || ! is_array( $cart_item ) || ! function_exists( 'wc_price' ) ) {
		return $html;
	}
	$qty = isset( $cart_item['quantity'] ) ? (float) $cart_item['quantity'] : 1.0;
	if ( $qty <= 0 ) {
		return $html;
	}
	$tax = (float) ( $cart_item['line_subtotal_tax'] ?? 0 );
	if ( $tax <= 0.00001 ) {
		return $html;
	}
	$gross = nh_ig_amount_with_vat( $cart_item['line_subtotal'] ?? 0, $tax );
	if ( $gross <= 0 ) {
		return $html;
	}
	$unit = $gross / $qty;
	if ( function_exists( 'get_woocommerce_currency' ) && nh_ig_currency_is_whole( get_woocommerce_currency() ) ) {
		$unit = (float) round( $unit, 0, PHP_ROUND_HALF_UP );
	} else {
		$unit = nh_ig_round_half_up( $unit, function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2 );
	}
	return wc_price( $unit );
}

/**
 * Fee row in the basket and checkout, with VAT.
 *
 * @param string $html Existing HTML.
 * @param object $fee  Fee.
 * @return string
 */
function nh_ig_filter_visible_fee_html( $html, $fee ) {
	if ( ! nh_ig_storefront_shows_vat() || ! is_object( $fee ) || ! function_exists( 'wc_price' ) ) {
		return $html;
	}
	$net = isset( $fee->total ) ? (float) $fee->total : 0.0;
	$tax = isset( $fee->tax ) ? (float) $fee->tax : 0.0;
	if ( $tax <= 0.00001 ) {
		return $html;
	}
	return wc_price( nh_ig_amount_with_vat( $net, $tax ) );
}

/**
 * Shipping price in the basket and checkout, with VAT.
 *
 * @param string  $html Existing HTML.
 * @param WC_Cart $cart Cart.
 * @return string
 */
function nh_ig_filter_visible_shipping_total( $html, $cart ) {
	if ( ! nh_ig_storefront_shows_vat() || ! is_object( $cart ) || ! function_exists( 'wc_price' ) ) {
		return $html;
	}
	if ( ! method_exists( $cart, 'get_shipping_total' ) ) {
		return $html;
	}
	$net = (float) $cart->get_shipping_total();
	$tax = method_exists( $cart, 'get_shipping_tax' ) ? (float) $cart->get_shipping_tax() : 0.0;
	if ( $tax <= 0.00001 ) {
		return $html;
	}
	return wc_price( nh_ig_amount_with_vat( $net, $tax ) );
}

/**
 * Formatted order total. Used by the sticky bar and the checkout header.
 *
 * @param string $html Existing HTML.
 * @return string
 */
function nh_ig_filter_cart_total_html( $html ) {
	if ( ! nh_ig_storefront_shows_vat() || ! function_exists( 'WC' ) || ! WC()->cart || ! function_exists( 'wc_price' ) ) {
		return $html;
	}
	$gross = nh_ig_charged_gross( WC()->cart );
	if ( $gross <= 0 ) {
		return $html;
	}
	return wc_price( $gross );
}

/**
 * Checkout and basket total row, with VAT included in the number.
 *
 * @param string $html Existing HTML.
 * @return string
 */
function nh_ig_filter_visible_order_total( $html ) {
	if ( ! nh_ig_storefront_shows_vat() || ! function_exists( 'WC' ) || ! WC()->cart || ! function_exists( 'wc_price' ) ) {
		return $html;
	}
	$gross = nh_ig_charged_gross( WC()->cart );
	if ( $gross <= 0 ) {
		return $html;
	}
	$out = '<strong>' . wc_price( $gross ) . '</strong> ';
	if ( is_string( $html ) && preg_match( '/<small class="includes_tax">.*?<\/small>/s', $html, $match ) ) {
		$out .= $match[0];
	}
	return $out;
}

/**
 * Basket subtotal. The figure next to Subtotal is the sum of the VAT-inclusive
 * line prices, not the before-VAT sum.
 *
 * @param string  $cart_subtotal Existing HTML.
 * @param bool    $compound      Compound-tax display.
 * @param WC_Cart $cart          Cart.
 * @return string
 */
function nh_ig_filter_cart_subtotal( $cart_subtotal, $compound, $cart ) {
	if ( $compound || ! is_object( $cart ) || ! function_exists( 'wc_price' ) ) {
		return $cart_subtotal;
	}
	if ( nh_ig_customer_is_vat_exempt() ) {
		return $cart_subtotal;
	}
	if ( function_exists( 'is_admin' ) && is_admin() && ! nh_ig_is_storefront_request() ) {
		return $cart_subtotal;
	}
	if ( ! method_exists( $cart, 'get_cart' ) ) {
		return $cart_subtotal;
	}

	$lines = $cart->get_cart();
	$ready = false;
	foreach ( $lines as $line ) {
		if ( is_array( $line ) && ( isset( $line['line_subtotal'] ) || isset( $line['line_subtotal_tax'] ) ) ) {
			$ready = true;
			break;
		}
	}
	if ( ! $ready ) {
		return $cart_subtotal;
	}

	$tax = 0.0;
	foreach ( $lines as $line ) {
		if ( is_array( $line ) ) {
			$tax += isset( $line['line_subtotal_tax'] ) ? (float) $line['line_subtotal_tax'] : 0.0;
		}
	}
	if ( $tax <= 0.00001 ) {
		return $cart_subtotal;
	}

	return wc_price( nh_ig_inclusive_lines_gross( $lines ) );
}

/**
 * @param string $hook
 * @return void
 */
function nh_ig_register_price_filter( $hook ) {
	add_filter( $hook, 'nh_ig_filter_product_price', 9999, 2 );
}

nh_ig_register_price_filter( 'woocommerce_product_get_price' );
nh_ig_register_price_filter( 'woocommerce_product_get_regular_price' );
nh_ig_register_price_filter( 'woocommerce_product_get_sale_price' );
nh_ig_register_price_filter( 'woocommerce_product_variation_get_price' );
nh_ig_register_price_filter( 'woocommerce_product_variation_get_regular_price' );
nh_ig_register_price_filter( 'woocommerce_product_variation_get_sale_price' );

// Catalog and "From" prices read the raw variation amount, then add VAT.
// These filters adjust that raw amount first, so the price with VAT is a whole krone.
nh_ig_register_price_filter( 'woocommerce_variation_prices_price' );
nh_ig_register_price_filter( 'woocommerce_variation_prices_regular_price' );
nh_ig_register_price_filter( 'woocommerce_variation_prices_sale_price' );

// The size calculator replaces flat-rate costs at PHP_INT_MAX and then
// recalculates VAT, which puts øre back. Registering here, from the theme,
// runs after that plugin because the theme loads later.
add_filter( 'woocommerce_package_rates', 'nh_ig_filter_package_rates', PHP_INT_MAX );
add_action( 'woocommerce_after_calculate_totals', 'nh_ig_after_calculate_totals', 9999 );
add_filter( 'woocommerce_price_trim_zeros', 'nh_ig_trim_zeros' );
add_filter( 'woocommerce_get_price_including_tax', 'nh_ig_filter_including_tax', 9999, 3 );
add_filter( 'pre_option_woocommerce_tax_display_cart', 'nh_ig_frontend_cart_tax_display', 20 );
add_filter( 'woocommerce_cart_display_prices_including_tax', 'nh_ig_cart_amounts_include_vat', PHP_INT_MAX );
add_filter( 'woocommerce_cart_subtotal', 'nh_ig_filter_cart_subtotal', 20, 3 );
add_filter( 'woocommerce_cart_item_subtotal', 'nh_ig_filter_visible_line_subtotal', 15, 3 );
add_filter( 'woocommerce_cart_item_price', 'nh_ig_filter_visible_line_price', 15, 3 );
add_filter( 'woocommerce_cart_shipping_total', 'nh_ig_filter_visible_shipping_total', 15, 2 );
add_filter( 'woocommerce_cart_totals_fee_html', 'nh_ig_filter_visible_fee_html', 15, 2 );
add_filter( 'woocommerce_cart_total', 'nh_ig_filter_cart_total_html', 15 );
add_filter( 'woocommerce_cart_totals_order_total_html', 'nh_ig_filter_visible_order_total', 15 );
add_filter( 'woocommerce_variation_prices', 'nh_ig_filter_variation_prices', 9999, 3 );
add_filter( 'woocommerce_get_variation_prices_hash', 'nh_ig_variation_prices_hash' );
