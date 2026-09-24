<?php
/**
 * Logged-in wishlists live on the user. Guests are kept by a cookie, with a server copy when the list outgrows the cookie.
 *
 * @package nh-wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_WL_Store {

	const DB_VERSION   = '1';
	const META         = 'nh_wishlists';
	const COOKIE_TOKEN = 'nh_wl_token';
	const COOKIE_DATA  = 'nh_wl_data';

	/**
	 * @var array<string,mixed>|null
	 */
	private static $cache = null;

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nh_wishlists';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			token varchar(64) NOT NULL,
			payload longtext NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (token)
		) {$charset};";
		dbDelta( $sql );
		update_option( 'nh_wl_db_version', self::DB_VERSION );
	}

	public static function maybe_install() {
		if ( get_option( 'nh_wl_db_version' ) === self::DB_VERSION ) {
			return;
		}
		self::install();
	}

	/**
	 * @return array{active:string,lists:array<int,array<string,mixed>>}
	 */
	public static function state() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		if ( is_user_logged_in() ) {
			$stored = get_user_meta( get_current_user_id(), self::META, true );
			self::$cache = nh_wl_normalize_state( is_array( $stored ) ? $stored : array() );
			return self::$cache;
		}
		self::$cache = self::guest_state();
		return self::$cache;
	}

	/**
	 * @param array<string,mixed> $state State.
	 * @return array{active:string,lists:array<int,array<string,mixed>>}
	 */
	public static function save( $state ) {
		$state = nh_wl_normalize_state( $state );
		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), self::META, $state );
			self::$cache = $state;
			return $state;
		}
		self::save_guest( $state );
		self::$cache = $state;
		return $state;
	}

	/**
	 * Pull a guest wishlist onto the account, then drop the cookie.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public static function merge_guest_into_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}
		if ( empty( $_COOKIE[ self::COOKIE_TOKEN ] ) && empty( $_COOKIE[ self::COOKIE_DATA ] ) ) {
			return;
		}
		$guest  = self::guest_state();
		$stored = get_user_meta( $user_id, self::META, true );
		$merged = nh_wl_merge_states( is_array( $stored ) ? $stored : array(), $guest );
		update_user_meta( $user_id, self::META, $merged );
		$token = self::current_token();
		if ( '' !== $token ) {
			global $wpdb;
			self::maybe_install();
			$wpdb->delete( self::table(), array( 'token' => $token ), array( '%s' ) );
		}
		self::clear_cookie( self::COOKIE_TOKEN );
		self::clear_cookie( self::COOKIE_DATA );
		self::$cache = $merged;
	}

	/**
	 * @return array{active:string,lists:array<int,array<string,mixed>>}
	 */
	private static function guest_state() {
		$token = self::current_token();
		if ( '' !== $token ) {
			$payload = self::read_payload( $token );
			if ( is_string( $payload ) && '' !== $payload ) {
				$decoded = json_decode( $payload, true );
				if ( is_array( $decoded ) ) {
					return nh_wl_normalize_state( $decoded );
				}
			}
		}
		if ( ! empty( $_COOKIE[ self::COOKIE_DATA ] ) ) {
			return nh_wl_decode_cookie( wp_unslash( $_COOKIE[ self::COOKIE_DATA ] ) );
		}
		return nh_wl_empty_state();
	}

	/**
	 * @param array<string,mixed> $state State.
	 * @return void
	 */
	private static function save_guest( $state ) {
		$token   = self::ensure_token();
		$payload = wp_json_encode( $state );
		self::maybe_install();
		global $wpdb;
		$wpdb->replace(
			self::table(),
			array(
				'token'      => $token,
				'payload'    => $payload,
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);
		$encoded = nh_wl_encode_cookie( $state );
		if ( nh_wl_cookie_fits( $encoded ) ) {
			self::set_cookie( self::COOKIE_DATA, $encoded );
		} else {
			self::clear_cookie( self::COOKIE_DATA );
		}
	}

	/**
	 * @return string
	 */
	private static function current_token() {
		if ( empty( $_COOKIE[ self::COOKIE_TOKEN ] ) ) {
			return '';
		}
		$token = strtolower( (string) wp_unslash( $_COOKIE[ self::COOKIE_TOKEN ] ) );
		return preg_match( '/^[a-f0-9]{32}$/', $token ) ? $token : '';
	}

	/**
	 * @return string
	 */
	private static function ensure_token() {
		$token = self::current_token();
		if ( '' !== $token ) {
			return $token;
		}
		try {
			$token = bin2hex( random_bytes( 16 ) );
		} catch ( Exception $exception ) {
			$token = md5( uniqid( 'nh_wl', true ) );
		}
		self::set_cookie( self::COOKIE_TOKEN, $token );
		return $token;
	}

	/**
	 * @param string $token Token.
	 * @return string|null
	 */
	private static function read_payload( $token ) {
		global $wpdb;
		self::maybe_install();
		$table = self::table();
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT payload FROM {$table} WHERE token = %s", $token ) );
		return is_string( $value ) ? $value : null;
	}

	/**
	 * @param string $name Cookie name.
	 * @param string $value Cookie value.
	 * @return void
	 */
	private static function set_cookie( $name, $value ) {
		$expires = time() + ( 90 * DAY_IN_SECONDS );
		foreach ( self::cookie_paths() as $path ) {
			setcookie(
				$name,
				$value,
				array(
					'expires'  => $expires,
					'path'     => $path,
					'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
		$_COOKIE[ $name ] = $value;
	}

	/**
	 * @param string $name Cookie name.
	 * @return void
	 */
	private static function clear_cookie( $name ) {
		foreach ( self::cookie_paths() as $path ) {
			setcookie(
				$name,
				'',
				array(
					'expires'  => time() - DAY_IN_SECONDS,
					'path'     => $path,
					'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
		unset( $_COOKIE[ $name ] );
	}

	/**
	 * @return array<int,string>
	 */
	private static function cookie_paths() {
		$paths = array( ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/' );
		if ( defined( 'SITECOOKIEPATH' ) && SITECOOKIEPATH && ! in_array( SITECOOKIEPATH, $paths, true ) ) {
			$paths[] = SITECOOKIEPATH;
		}
		return $paths;
	}
}
