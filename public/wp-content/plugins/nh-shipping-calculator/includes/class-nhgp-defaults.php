<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NHGP_Defaults {

	public static function option_key_heavy(){ return 'nhgp_settings'; }
	public static function option_key_custom(){ return 'nhgp_custom_settings'; }

	/* ----------------------------------------------------------------------
	 * Heavy tiers defaults (now 5 levels)
	 * ------------------------------------------------------------------- */
	public static function heavy_all(){
		return array(
			'enabled'      => 1,

			// These are interpreted as "Weight ≥ X kg"
			// but you can still mirror your table nicely:

			// ~5–10 kg price band
			't1_weight'    => '5',    't1_amount' => '8',   // "iki 5 kg" in your table

			// ~10–20 kg
			't2_weight'    => '10',   't2_amount' => '12',

			// ~20–30 kg
			't3_weight'    => '20',   't3_amount' => '17',

			// ~30–55 kg
			't4_weight'    => '30',   't4_amount' => '21',

			// ~55–75 kg
			't5_weight'    => '55',   't5_amount' => '29',

			// ~75–100 kg
			't6_weight'    => '75',   't6_amount' => '35',

			// ~100–200 kg
			't7_weight'    => '100',  't7_amount' => '45',

			// ~200–500 kg
			't8_weight'    => '200',  't8_amount' => '59',

			// ~500–700 kg
			't9_weight'    => '500',  't9_amount' => '95',

			// ~700–1000 kg
			't10_weight'   => '700',  't10_amount' => '120',

			// ≥ 1000 kg (this will become your last tier if 12 is empty)
			't11_weight'   => '1000', 't11_amount' => '150',

			// Extra slot if you want another breakpoint (e.g. 1500 kg)
			// or leave both empty to ignore:
			't12_weight'   => '',     't12_amount' => '200',

			'append_label' => 1,
		);
	}

	public static function heavy_get(){
		$saved = get_option( self::option_key_heavy(), array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::heavy_all(), $saved );
	}

	/* ----------------------------------------------------------------------
	 * Custom cutting defaults
	 *  - Uses hard-coded meta keys in the runtime logic.
	 *  - Rules 1–7 are “max width / max height → shipping class”.
	 *  - Classes: xs, s, m, l, xl, xxl, xxxl
	 * ------------------------------------------------------------------- */
	public static function custom_all(){
		return array(
			'enabled'         => 1, // always on; runtime still checks for the flag on items

			// thresholds use same unit as inputs (your UI is mm)
			// You can tweak these, important part is the class slugs.
			'r1_w'            => '1050',
			'r1_h'            => '1000',
			'r1_class'        => 'xs',

			'r2_w'            => '2100',
			'r2_h'            => '2000',
			'r2_class'        => 's',

			'r3_w'            => '3000',
			'r3_h'            => '3000',
			'r3_class'        => 'm',

			'r4_w'            => '3600',
			'r4_h'            => '3600',
			'r4_class'        => 'l',

			'r5_w'            => '4200',
			'r5_h'            => '4200',
			'r5_class'        => 'xl',

			// Leave last two size bands “open” – configure when needed
			'r6_w'            => '',
			'r6_h'            => '',
			'r6_class'        => 'xxl',

			'r7_w'            => '',
			'r7_h'            => '',
			'r7_class'        => 'xxxl',

			'default_class'   => '',
			'show_base_label' => 1,
		);
	}

	public static function custom_get(){
		$saved = get_option( self::option_key_custom(), array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::custom_all(), $saved );
	}
}
