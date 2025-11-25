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
			't1_weight'    => '200',   't1_amount' => '300',
			't2_weight'    => '500',   't2_amount' => '450',
			't3_weight'    => '1000',  't3_amount' => '700',
			't4_weight'    => '2000',  't4_amount' => '1100',
			't5_weight'    => '3000',  't5_amount' => '1500',
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
