<?php
/**
 * WooCommerce submenu: settings + recovery list.
 *
 * @package nh-cart-recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_CR_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_notices', array( __CLASS__, 'conflict_notice' ) );
	}

	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Cart recovery', NH_CR_TD ),
			__( 'Cart recovery', NH_CR_TD ),
			'manage_woocommerce',
			'nh-cart-recovery',
			array( __CLASS__, 'page' )
		);
	}

	public static function register() {
		register_setting(
			'nh_cr_settings_group',
			'nh_cr_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	/**
	 * @param mixed $input Raw POST.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$defaults = nh_cr_default_settings();
		$input    = is_array( $input ) ? $input : array();
		$out      = $defaults;
		$out['enabled']            = empty( $input['enabled'] ) ? 0 : 1;
		$out['checkout_on_cancel'] = empty( $input['checkout_on_cancel'] ) ? 0 : 1;
		$out['cart_wait_minutes']  = max( 15, min( 24 * 60, absint( $input['cart_wait_minutes'] ?? 60 ) ) );
		$out['delete_after_days']  = max( 7, min( 365, absint( $input['delete_after_days'] ?? 30 ) ) );
		$out['subject_cart']       = sanitize_text_field( (string) ( $input['subject_cart'] ?? '' ) );
		$out['intro_cart']         = sanitize_textarea_field( (string) ( $input['intro_cart'] ?? '' ) );
		$out['subject_checkout']   = sanitize_text_field( (string) ( $input['subject_checkout'] ?? '' ) );
		$out['intro_checkout']     = sanitize_textarea_field( (string) ( $input['intro_checkout'] ?? '' ) );
		return $out;
	}

	public static function conflict_notice() {
		if ( ! self::brainstorm_active() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || strpos( (string) $screen->id, 'nh-cart-recovery' ) === false ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Cart Abandonment Recovery for WooCommerce (Brainstorm Force) is still active. Disable it so customers are not emailed twice.', NH_CR_TD );
		echo '</p></div>';
	}

	/**
	 * @return bool
	 */
	public static function brainstorm_active() {
		return defined( 'CARTFLOWS_CA_FILE' )
			|| class_exists( 'CARTFLOWS_CA_Loader' )
			|| class_exists( 'Cartflows_Ca_Loader' )
			|| defined( 'WCF_CA_FILE' );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap"><h1>' . esc_html__( 'Cart recovery', NH_CR_TD ) . '</h1>';
		echo '<h2 class="nav-tab-wrapper">';
		echo '<a class="nav-tab ' . ( $tab !== 'list' ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=nh-cart-recovery&tab=settings' ) ) . '">' . esc_html__( 'Settings', NH_CR_TD ) . '</a>';
		echo '<a class="nav-tab ' . ( $tab === 'list' ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=nh-cart-recovery&tab=list' ) ) . '">' . esc_html__( 'Carts', NH_CR_TD ) . '</a>';
		echo '</h2>';
		if ( $tab === 'list' ) {
			self::render_list();
		} else {
			self::render_settings();
		}
		echo '</div>';
	}

	private static function render_settings() {
		$o      = nh_cr_get_settings();
		$locale = determine_locale();
		$cart   = nh_cr_default_copy( $locale, 'cart' );
		$check  = nh_cr_default_copy( $locale, 'checkout' );
		echo '<form method="post" action="options.php">';
		settings_fields( 'nh_cr_settings_group' );
		echo '<table class="form-table" role="presentation">';
		self::checkbox_row( 'enabled', $o['enabled'], __( 'Enable recovery emails', NH_CR_TD ), __( 'Emails are sent with wp_mail, so WP Mail SMTP + Brevo is used automatically. No extra Brevo API key is required.', NH_CR_TD ) );
		self::checkbox_row( 'checkout_on_cancel', $o['checkout_on_cancel'], __( 'Email after unpaid checkout is cancelled', NH_CR_TD ), __( 'This is the Svea “pending payment → cancelled” case. Skipped if the same email already placed a paid order.', NH_CR_TD ) );
		echo '<tr><th>' . esc_html__( 'Wait before cart email', NH_CR_TD ) . '</th><td>';
		echo '<input name="nh_cr_settings[cart_wait_minutes]" type="number" min="15" max="1440" value="' . esc_attr( (string) $o['cart_wait_minutes'] ) . '" /> ';
		esc_html_e( 'minutes after last cart change. Only sent if we have an email (login, checkout field, or Svea iframe).', NH_CR_TD );
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Delete old records after', NH_CR_TD ) . '</th><td>';
		echo '<input name="nh_cr_settings[delete_after_days]" type="number" min="7" max="365" value="' . esc_attr( (string) $o['delete_after_days'] ) . '" /> ';
		esc_html_e( 'days (converted / skipped only).', NH_CR_TD );
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Cart email subject', NH_CR_TD ) . '</th><td>';
		echo '<input class="regular-text" name="nh_cr_settings[subject_cart]" value="' . esc_attr( $o['subject_cart'] ) . '" placeholder="' . esc_attr( $cart['subject'] ) . '" />';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Cart email intro', NH_CR_TD ) . '</th><td>';
		echo '<textarea class="large-text" rows="3" name="nh_cr_settings[intro_cart]" placeholder="' . esc_attr( $cart['intro'] ) . '">' . esc_textarea( $o['intro_cart'] ) . '</textarea>';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Unfinished-payment subject', NH_CR_TD ) . '</th><td>';
		echo '<input class="regular-text" name="nh_cr_settings[subject_checkout]" value="' . esc_attr( $o['subject_checkout'] ) . '" placeholder="' . esc_attr( $check['subject'] ) . '" />';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Unfinished-payment intro', NH_CR_TD ) . '</th><td>';
		echo '<textarea class="large-text" rows="3" name="nh_cr_settings[intro_checkout]" placeholder="' . esc_attr( $check['intro'] ) . '">' . esc_textarea( $o['intro_checkout'] ) . '</textarea>';
		echo '</td></tr>';
		echo '</table>';
		submit_button();
		echo '<p class="description">' . esc_html__( 'Empty subject/intro fields use the shop language defaults. One email per address per 7 days. Brevo’s free plan is typically 300 emails/day — keep the wait at 60 minutes so you do not burn the quota on window-shoppers.', NH_CR_TD ) . '</p>';
		echo '</form>';
	}

	/**
	 * @param string $key     Setting key.
	 * @param mixed  $value   Current.
	 * @param string $label   Label.
	 * @param string $desc    Description.
	 */
	private static function checkbox_row( $key, $value, $label, $desc ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="nh_cr_settings[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $value ), true, false ) . ' /> ';
		echo esc_html( $desc );
		echo '</label></td></tr>';
	}

	private static function render_list() {
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data   = NH_CR_Store::query(
			array(
				'status' => $status,
				'paged'  => $paged,
			)
		);
		echo '<p>';
		foreach ( array( '' => __( 'All', NH_CR_TD ), 'open' => 'open', 'sent' => 'sent', 'converted' => 'converted', 'unsubscribed' => 'unsubscribed' ) as $key => $label ) {
			$url = add_query_arg(
				array(
					'page'   => 'nh-cart-recovery',
					'tab'    => 'list',
					'status' => $key,
				),
				admin_url( 'admin.php' )
			);
			echo '<a href="' . esc_url( $url ) . '" style="margin-right:12px;">' . esc_html( $label ) . '</a>';
		}
		echo '</p>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>ID</th><th>' . esc_html__( 'Email', NH_CR_TD ) . '</th><th>' . esc_html__( 'Type', NH_CR_TD ) . '</th><th>' . esc_html__( 'Status', NH_CR_TD ) . '</th><th>' . esc_html__( 'Updated', NH_CR_TD ) . '</th><th>' . esc_html__( 'Emailed', NH_CR_TD ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $data['rows'] ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No records yet.', NH_CR_TD ) . '</td></tr>';
		}
		foreach ( $data['rows'] as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $row->id ) . '</td>';
			echo '<td>' . esc_html( $row->email ? $row->email : '—' ) . '</td>';
			echo '<td>' . esc_html( $row->type ) . '</td>';
			echo '<td>' . esc_html( $row->status ) . '</td>';
			echo '<td>' . esc_html( $row->updated_at ) . '</td>';
			echo '<td>' . esc_html( $row->emailed_at ? $row->emailed_at : '—' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}
