<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NHGP_Defaults {

	public static function option_key_heavy(){ return 'nhgp_settings'; }
	public static function option_key_custom(){ return 'nhgp_custom_settings'; }

	/* ----------------------------------------------------------------------
	 * Heavy tiers defaults
	 * ------------------------------------------------------------------- */
	public static function heavy_all(){
		return array(
			'enabled'      => 1,

			't1_weight'    => '5',    't1_amount' => '8',
			't2_weight'    => '10',   't2_amount' => '12',
			't3_weight'    => '20',   't3_amount' => '17',
			't4_weight'    => '30',   't4_amount' => '21',
			't5_weight'    => '55',   't5_amount' => '29',
			't6_weight'    => '75',   't6_amount' => '35',
			't7_weight'    => '100',  't7_amount' => '45',
			't8_weight'    => '200',  't8_amount' => '59',
			't9_weight'    => '500',  't9_amount' => '95',
			't10_weight'   => '700',  't10_amount' => '120',
			't11_weight'   => '1000', 't11_amount' => '150',

			// NOTE: if you leave weight empty, that row is ignored by sanitize().
			// If you want an extra breakpoint, fill t12_weight AND t12_amount.
			't12_weight'   => '',
			't12_amount'   => '200',

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
	 *  - thresholds are in mm (match your UI inputs)
	 *  - Rule matches when:
	 *      (width <= rX_w OR rX_w is empty/0) AND (height <= rX_h OR rX_h is empty/0)
	 *  - IMPORTANT: if both rX_w and rX_h are empty, the rule is ignored
	 * ------------------------------------------------------------------- */
	public static function custom_all(){
		return array(
			'enabled'         => 1,

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

			// Make the last size bands actually usable:
			// If you need different cutoffs, change these numbers.
			'r6_w'            => '6000',
			'r6_h'            => '6000',
			'r6_class'        => 'xxl',

			'r7_w'            => '12000',
			'r7_h'            => '12000',
			'r7_class'        => 'xxxl',

			// Final fallback if item is larger than all rules above:
			'default_class'   => 'xxxl',

			// kept for backward compatibility; logic ignores now
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
