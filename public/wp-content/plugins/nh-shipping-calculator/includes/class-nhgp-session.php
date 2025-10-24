<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NHGP_Session {
	public static function init(){
		add_action( 'init', array( __CLASS__, 'maybe_clear_cached_shipping' ), 20 );
	}
	public static function maybe_clear_cached_shipping(){
		if ( ! function_exists( 'WC' ) || ! WC()->session ) return;
		$current_ver = (string) get_option( 'nhgp_rates_version', '' );
		$session_ver = (string) WC()->session->get( 'nhgp_rates_version', '' );
		if ( $current_ver !== '' && $current_ver !== $session_ver ) {
			if ( method_exists( WC()->session, 'get_session_data' ) ) {
				foreach ( (array) WC()->session->get_session_data() as $key => $val ) {
					if ( strpos( $key, 'shipping_for_package_' ) === 0 ) WC()->session->__unset( $key );
				}
			} else {
				for ( $i=0; $i<10; $i++ ) WC()->session->__unset( "shipping_for_package_{$i}" );
			}
			WC()->session->set( 'nhgp_rates_version', $current_ver );
			if ( WC()->cart ) WC()->cart->calculate_shipping();
		}
	}
}
