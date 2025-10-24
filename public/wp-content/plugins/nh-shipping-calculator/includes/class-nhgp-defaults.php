<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NHGP_Defaults {
	public static function option_key_heavy(){ return 'nhgp_settings'; }
	public static function option_key_custom(){ return 'nhgp_custom_settings'; }

	// Heavy tiers defaults
	public static function heavy_all(){
		return array(
			'enabled'      => 1,
			't1_weight'    => '200',  't1_amount' => '300',
			't2_weight'    => '500',  't2_amount' => '450',
			't3_weight'    => '1000', 't3_amount' => '700',
			'append_label' => 1,
		);
	}
	public static function heavy_get(){
		$saved = get_option( self::option_key_heavy(), array() );
		if ( ! is_array( $saved ) ) $saved = array();
		return array_merge( self::heavy_all(), $saved );
	}

	// Custom cutting defaults (aligned with your JS)
	public static function custom_all(){
		return array(
			'enabled'        => 0,
			'width_key'      => 'nh_width_mm',
			'height_key'     => 'nh_length_mm',
			'flag_key'       => 'nh_custom_mode',
			// thresholds use same unit as inputs (your UI is mm)
			'r1_w' => '1050', 'r1_h' => '1000', 'r1_class' => 'small',
			'r2_w' => '3000', 'r2_h' => '3000', 'r2_class' => 'medium',
			'r3_w' => '4200', 'r3_h' => '4200', 'r3_class' => 'large',
			'default_class'  => '',
			'show_base_label'=> 1,
		);
	}
	public static function custom_get(){
		$saved = get_option( self::option_key_custom(), array() );
		if ( ! is_array( $saved ) ) $saved = array();
		return array_merge( self::custom_all(), $saved );
	}
}
