<?php
/**
 * Custom table for recovery records.
 *
 * @package nh-cart-recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_CR_Store {

	const DB_VERSION = '1.0.0';

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nh_cart_recovery';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token varchar(64) NOT NULL,
			session_key varchar(191) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(191) NOT NULL DEFAULT '',
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			cart longtext NULL,
			cart_hash varchar(64) NOT NULL DEFAULT '',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(20) NOT NULL DEFAULT 'cart',
			status varchar(20) NOT NULL DEFAULT 'open',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			emailed_at datetime NULL,
			converted_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY session_key (session_key),
			KEY email (email),
			KEY status_updated (status, updated_at),
			KEY order_id (order_id)
		) {$collate};";
		dbDelta( $sql );
		update_option( 'nh_cr_db_version', self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'nh_cr_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function blank_row() {
		$now = current_time( 'mysql' );
		return array(
			'token'              => bin2hex( random_bytes( 16 ) ),
			'session_key'        => '',
			'user_id'            => 0,
			'email'              => '',
			'first_name'         => '',
			'last_name'          => '',
			'cart'               => '[]',
			'cart_hash'          => '',
			'order_id'           => 0,
			'type'               => 'cart',
			'status'             => 'open',
			'created_at'         => $now,
			'updated_at'         => $now,
			'emailed_at'         => null,
			'converted_order_id' => 0,
		);
	}

	/**
	 * @param int $id Row id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', absint( $id ) ) );
		return $row ? $row : null;
	}

	/**
	 * @param string $token Token.
	 * @return object|null
	 */
	public static function get_by_token( $token ) {
		global $wpdb;
		$token = sanitize_text_field( (string) $token );
		if ( $token === '' ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE token = %s', $token ) );
		return $row ? $row : null;
	}

	/**
	 * @param string $session_key Session.
	 * @return object|null
	 */
	public static function get_open_by_session( $session_key ) {
		global $wpdb;
		$session_key = sanitize_text_field( (string) $session_key );
		if ( $session_key === '' ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . " WHERE session_key = %s AND status IN ('open','sent') ORDER BY id DESC LIMIT 1",
				$session_key
			)
		);
		return $row ? $row : null;
	}

	/**
	 * @param array<string, mixed> $data Row data.
	 * @return int
	 */
	public static function insert( $data ) {
		global $wpdb;
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int                  $id   Row id.
	 * @param array<string, mixed> $data Fields.
	 * @return void
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		$wpdb->update( self::table(), $data, array( 'id' => absint( $id ) ) );
	}

	/**
	 * @param string $email Email.
	 * @return bool
	 */
	public static function email_unsubscribed( $email ) {
		global $wpdb;
		$email = self::normalize_email( $email );
		if ( $email === '' ) {
			return false;
		}
		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::table() . " WHERE email = %s AND status = 'unsubscribed' LIMIT 1",
				$email
			)
		);
		return (bool) $found;
	}

	/**
	 * @param string $email Email.
	 * @param int    $hours Lookback hours.
	 * @return bool
	 */
	public static function email_has_recent_paid_order( $email, $hours = 48 ) {
		$email = self::normalize_email( $email );
		if ( $email === '' || ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}
		$after  = gmdate( 'Y-m-d H:i:s', time() - ( $hours * HOUR_IN_SECONDS ) );
		$orders = wc_get_orders(
			array(
				'billing_email' => $email,
				'status'        => array( 'processing', 'completed', 'on-hold' ),
				'date_created'  => '>=' . $after,
				'limit'         => 1,
				'return'        => 'ids',
			)
		);
		return ! empty( $orders );
	}

	/**
	 * @param int $minutes Idle minutes for cart-type rows.
	 * @return array<int, object>
	 */
	public static function due_cart_emails( $minutes ) {
		global $wpdb;
		$minutes      = max( 15, (int) $minutes );
		$cart_cut     = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $minutes * MINUTE_IN_SECONDS ) );
		$checkout_cut = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 5 * MINUTE_IN_SECONDS ) );
		$sql          = $wpdb->prepare(
			'SELECT * FROM ' . self::table() . " WHERE status = 'open' AND email <> '' AND cart_hash <> '' AND emailed_at IS NULL AND (
				(type = 'cart' AND updated_at <= %s) OR (type = 'checkout' AND updated_at <= %s)
			) ORDER BY id ASC LIMIT 50",
			$cart_cut,
			$checkout_cut
		);
		$rows = $wpdb->get_results( $sql );
		return is_array( $rows ) ? $rows : array();
	}

	public static function unsubscribe_email( $email ) {
		global $wpdb;
		$email = self::normalize_email( $email );
		if ( $email === '' ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table() . " SET status = 'unsubscribed', updated_at = %s WHERE email = %s AND status IN ('open','sent')",
				current_time( 'mysql' ),
				$email
			)
		);
	}

	public static function mark_converted_for_email( $email, $order_id ) {
		global $wpdb;
		$email = self::normalize_email( $email );
		if ( $email === '' ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table() . " SET status = 'converted', converted_order_id = %d, updated_at = %s WHERE email = %s AND status IN ('open','sent')",
				absint( $order_id ),
				current_time( 'mysql' ),
				$email
			)
		);
	}

	public static function purge_old( $days ) {
		global $wpdb;
		$days = max( 7, (int) $days );
		$cut  = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::table() . " WHERE status IN ('converted','skipped') AND updated_at < %s",
				$cut
			)
		);
	}

	/**
	 * @param string $email Raw email.
	 * @return string
	 */
	public static function normalize_email( $email ) {
		$email = strtolower( trim( (string) $email ) );
		return is_email( $email ) ? $email : '';
	}

	/**
	 * @param array<string, string> $args Query args.
	 * @return array{rows:array,total:int}
	 */
	public static function query( $args ) {
		global $wpdb;
		$status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
		$paged  = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$per    = 20;
		$where  = '1=1';
		$params = array();
		if ( $status ) {
			$where   .= ' AND status = %s';
			$params[] = $status;
		}
		$offset = ( $paged - 1 ) * $per;
		$table  = self::table();
		if ( $params ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per, $offset ) ) ) );
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per, $offset ) );
		}
		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}
}
