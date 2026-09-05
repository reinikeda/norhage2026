<?php
/**
 * WP-Cron: send idle-cart emails and purge old rows.
 *
 * @package nh-cart-recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_CR_Cron {

	const HOOK = 'nh_cr_cron_tick';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( 'init', array( __CLASS__, 'ensure_scheduled' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $schedules Cron schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function schedules( $schedules ) {
		if ( ! isset( $schedules['nh_cr_fifteen'] ) ) {
			$schedules['nh_cr_fifteen'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes', NH_CR_TD ),
			);
		}
		return $schedules;
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'nh_cr_fifteen', self::HOOK );
		}
	}

	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
			$timestamp = wp_next_scheduled( self::HOOK );
		}
	}

	public static function ensure_scheduled() {
		self::schedule();
	}

	public static function run() {
		$settings = nh_cr_get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$now  = (int) current_time( 'timestamp' );
		$rows = NH_CR_Store::due_email_candidates( (int) $settings['max_emails'] );
		foreach ( $rows as $row ) {
			$step = nh_cr_next_due_step( $row, $settings, $now );
			if ( $step ) {
				NH_CR_Mailer::send_row( $row, $step );
			}
		}

		NH_CR_Store::purge_old( (int) $settings['delete_after_days'] );
	}
}
