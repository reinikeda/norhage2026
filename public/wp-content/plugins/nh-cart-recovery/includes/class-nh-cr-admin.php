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
		$locale   = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

		$out['enabled']            = empty( $input['enabled'] ) ? 0 : 1;
		$out['checkout_on_cancel'] = empty( $input['checkout_on_cancel'] ) ? 0 : 1;
		$out['email_1_minutes']    = max( 15, min( 24 * 60, absint( $input['email_1_minutes'] ?? 60 ) ) );
		$out['email_2_hours']      = max( 6, min( 168, absint( $input['email_2_hours'] ?? 24 ) ) );
		$out['email_3_hours']      = max( 24, min( 336, absint( $input['email_3_hours'] ?? 72 ) ) );
		if ( $out['email_3_hours'] < $out['email_2_hours'] + 12 ) {
			$out['email_3_hours'] = $out['email_2_hours'] + 24;
		}
		$out['max_emails']        = max( 1, min( 3, absint( $input['max_emails'] ?? 3 ) ) );
		$out['delete_after_days'] = max( 7, min( 365, absint( $input['delete_after_days'] ?? 30 ) ) );

		foreach ( array( 'cart', 'checkout' ) as $type ) {
			foreach ( array( 1, 2, 3 ) as $step ) {
				foreach ( array( 'subject', 'heading', 'intro', 'body', 'button' ) as $field ) {
					$key   = "copy_{$type}_{$step}_{$field}";
					$raw   = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
					$clean = in_array( $field, array( 'intro', 'body' ), true )
						? sanitize_textarea_field( $raw )
						: sanitize_text_field( $raw );
					$out[ $key ] = nh_cr_sanitize_copy_field( $clean, $locale, $type, $step, $field );
				}
			}
		}
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
		if ( ! in_array( $tab, array( 'settings', 'preview', 'list' ), true ) ) {
			$tab = 'settings';
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Cart recovery', NH_CR_TD ) . '</h1>';
		echo '<h2 class="nav-tab-wrapper">';
		$tabs = array(
			'settings' => __( 'Settings', NH_CR_TD ),
			'preview'  => __( 'Preview', NH_CR_TD ),
			'list'     => __( 'Carts', NH_CR_TD ),
		);
		foreach ( $tabs as $key => $label ) {
			$url = admin_url( 'admin.php?page=nh-cart-recovery&tab=' . $key );
			echo '<a class="nav-tab ' . ( $tab === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';
		if ( $tab === 'list' ) {
			self::render_list();
		} elseif ( $tab === 'preview' ) {
			self::render_preview();
		} else {
			self::render_settings();
		}
		echo '</div>';
	}

	private static function render_settings() {
		$o      = nh_cr_get_settings();
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		echo '<form method="post" action="options.php">';
		settings_fields( 'nh_cr_settings_group' );
		echo '<table class="form-table" role="presentation">';
		self::checkbox_row( 'enabled', $o['enabled'], __( 'Enable recovery emails', NH_CR_TD ), __( 'Emails are sent with wp_mail, so WP Mail SMTP + Brevo is used automatically. No extra Brevo API key is required.', NH_CR_TD ) );
		self::checkbox_row( 'checkout_on_cancel', $o['checkout_on_cancel'], __( 'Email after unpaid checkout is cancelled', NH_CR_TD ), __( 'This is the Svea “pending payment → cancelled” case. Skipped if the same email already placed a paid order.', NH_CR_TD ) );

		echo '<tr><th>' . esc_html__( 'Email 1 delay (abandoned cart)', NH_CR_TD ) . '</th><td>';
		echo '<input name="nh_cr_settings[email_1_minutes]" type="number" min="15" max="1440" value="' . esc_attr( (string) $o['email_1_minutes'] ) . '" /> ';
		esc_html_e( 'minutes after last cart change. Unfinished-payment email 1 is sent as soon as the unpaid checkout is cancelled.', NH_CR_TD );
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Email 2 delay', NH_CR_TD ) . '</th><td>';
		echo '<input name="nh_cr_settings[email_2_hours]" type="number" min="6" max="168" value="' . esc_attr( (string) $o['email_2_hours'] ) . '" /> ';
		esc_html_e( 'hours after email 1.', NH_CR_TD );
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Email 3 delay', NH_CR_TD ) . '</th><td>';
		echo '<input name="nh_cr_settings[email_3_hours]" type="number" min="24" max="336" value="' . esc_attr( (string) $o['email_3_hours'] ) . '" /> ';
		esc_html_e( 'hours after email 1 (last reminder). Must be later than email 2.', NH_CR_TD );
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Emails in the sequence', NH_CR_TD ) . '</th><td>';
		echo '<input name="nh_cr_settings[max_emails]" type="number" min="1" max="3" value="' . esc_attr( (string) $o['max_emails'] ) . '" /> ';
		esc_html_e( '1–3. Three is the usual cap; more emails rarely convert and look like spam.', NH_CR_TD );
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Delete old records after', NH_CR_TD ) . '</th><td>';
		echo '<input name="nh_cr_settings[delete_after_days]" type="number" min="7" max="365" value="' . esc_attr( (string) $o['delete_after_days'] ) . '" /> ';
		esc_html_e( 'days (converted / skipped only).', NH_CR_TD );
		echo '</td></tr>';
		echo '</table>';

		echo '<p class="description" style="max-width:52em;">';
		echo esc_html__( 'Fields below are prefilled in the shop language. Leave them as they are to keep future translation updates. Change a field only when you want shop-specific wording. Use {first_name} in subject/heading — it is replaced with the customer’s name, or removed when no name is known.', NH_CR_TD );
		echo '</p>';

		self::render_copy_block( $o, $locale, 'cart', __( 'Abandoned cart emails', NH_CR_TD ) );
		self::render_copy_block( $o, $locale, 'checkout', __( 'Unfinished payment emails', NH_CR_TD ) );

		submit_button();
		echo '<p class="description">' . esc_html__( 'Each message uses the shop WooCommerce email template, Norhage colours (forest, green, cream, gold), a cart table with image / name / qty / total, a checkout button, and an unsubscribe link. Open the Preview tab to see the layout. Brevo’s free plan is typically 300 emails/day.', NH_CR_TD ) . '</p>';
		echo '</form>';
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $locale   Locale.
	 * @param string               $type     cart|checkout.
	 * @param string               $title    Section title.
	 */
	private static function render_copy_block( $settings, $locale, $type, $title ) {
		$labels = array(
			1 => __( 'Email 1', NH_CR_TD ),
			2 => __( 'Email 2', NH_CR_TD ),
			3 => __( 'Email 3 (last reminder)', NH_CR_TD ),
		);
		$fields = array(
			'subject' => __( 'Subject', NH_CR_TD ),
			'heading' => __( 'Heading', NH_CR_TD ),
			'intro'   => __( 'Intro', NH_CR_TD ),
			'body'    => __( 'Body', NH_CR_TD ),
			'button'  => __( 'Button', NH_CR_TD ),
		);
		echo '<h2>' . esc_html( $title ) . '</h2>';
		foreach ( array( 1, 2, 3 ) as $step ) {
			echo '<h3>' . esc_html( $labels[ $step ] ) . '</h3>';
			echo '<table class="form-table" role="presentation">';
			foreach ( $fields as $field => $label ) {
				$key   = "copy_{$type}_{$step}_{$field}";
				$value = nh_cr_editor_value( $settings, $locale, $type, $step, $field );
				echo '<tr><th>' . esc_html( $label ) . '</th><td>';
				if ( in_array( $field, array( 'intro', 'body' ), true ) ) {
					echo '<textarea class="large-text" rows="3" name="nh_cr_settings[' . esc_attr( $key ) . ']">' . esc_textarea( $value ) . '</textarea>';
				} else {
					echo '<input class="large-text" name="nh_cr_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" />';
				}
				echo '</td></tr>';
			}
			echo '</table>';
		}
	}

	private static function render_preview() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$type   = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( (string) $_GET['type'] ) ) : 'cart'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'checkout' !== $type ) {
			$type = 'cart';
		}
		$step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$step = nh_cr_normalize_step( $step );
		$named = ! isset( $_GET['named'] ) || (string) $_GET['named'] !== '0'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$name  = $named ? 'Anna' : '';

		$base = admin_url( 'admin.php?page=nh-cart-recovery&tab=preview' );
		echo '<p class="description" style="max-width:52em;">';
		echo esc_html__( 'This is the inner email plus a Norhage-coloured header. Live mail is wrapped in the shop WooCommerce email template (logo and footer from WooCommerce → Settings → Emails). Sample products are placeholders; real emails use the customer’s cart, including images and custom sizes.', NH_CR_TD );
		echo '</p>';

		echo '<p>';
		foreach ( array( 'cart' => __( 'Abandoned cart', NH_CR_TD ), 'checkout' => __( 'Unfinished payment', NH_CR_TD ) ) as $key => $label ) {
			echo '<a class="button' . ( $type === $key ? ' button-primary' : '' ) . '" style="margin-right:8px;" href="' . esc_url( add_query_arg( array( 'type' => $key, 'step' => $step, 'named' => $named ? '1' : '0' ), $base ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</p><p>';
		foreach ( array( 1 => __( 'Email 1 (1 hour / on cancel)', NH_CR_TD ), 2 => __( 'Email 2 (next day)', NH_CR_TD ), 3 => __( 'Email 3 (3 days)', NH_CR_TD ) ) as $n => $label ) {
			echo '<a class="button' . ( $step === $n ? ' button-primary' : '' ) . '" style="margin-right:8px;" href="' . esc_url( add_query_arg( array( 'type' => $type, 'step' => $n, 'named' => $named ? '1' : '0' ), $base ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</p><p>';
		echo '<a class="button' . ( $named ? ' button-primary' : '' ) . '" style="margin-right:8px;" href="' . esc_url( add_query_arg( array( 'type' => $type, 'step' => $step, 'named' => '1' ), $base ) ) . '">' . esc_html__( 'With name (Anna)', NH_CR_TD ) . '</a>';
		echo '<a class="button' . ( ! $named ? ' button-primary' : '' ) . '" href="' . esc_url( add_query_arg( array( 'type' => $type, 'step' => $step, 'named' => '0' ), $base ) ) . '">' . esc_html__( 'No name known', NH_CR_TD ) . '</a>';
		echo '</p>';

		$parts = NH_CR_Mailer::preview_parts( $type, $step, $locale, $name );
		$doc   = NH_CR_Mailer::preview_document( $type, $step, $locale, $name );
		echo '<p><strong>' . esc_html__( 'Subject', NH_CR_TD ) . ':</strong> ' . esc_html( $parts['subject'] ) . '</p>';
		echo '<iframe title="' . esc_attr__( 'Email preview', NH_CR_TD ) . '" style="width:100%;max-width:680px;height:820px;border:1px solid #c3c4c7;background:#fff;" srcdoc="' . esc_attr( $doc ) . '"></iframe>';
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
		echo '<th>ID</th><th>' . esc_html__( 'Email', NH_CR_TD ) . '</th><th>' . esc_html__( 'Type', NH_CR_TD ) . '</th><th>' . esc_html__( 'Status', NH_CR_TD ) . '</th><th>' . esc_html__( 'Emails', NH_CR_TD ) . '</th><th>' . esc_html__( 'Updated', NH_CR_TD ) . '</th><th>' . esc_html__( 'Last emailed', NH_CR_TD ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $data['rows'] ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No records yet.', NH_CR_TD ) . '</td></tr>';
		}
		foreach ( $data['rows'] as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $row->id ) . '</td>';
			echo '<td>' . esc_html( $row->email ? $row->email : '—' ) . '</td>';
			echo '<td>' . esc_html( $row->type ) . '</td>';
			echo '<td>' . esc_html( $row->status ) . '</td>';
			echo '<td>' . esc_html( (string) NH_CR_Store::emails_sent_count( $row ) ) . '</td>';
			echo '<td>' . esc_html( $row->updated_at ) . '</td>';
			echo '<td>' . esc_html( $row->emailed_at ? $row->emailed_at : '—' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}
