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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'conflict_notice' ) );
	}

	/**
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ) {
		if ( strpos( (string) $hook, 'nh-cart-recovery' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'nh-cart-recovery-admin',
			NH_CR_URL . 'assets/css/admin.css',
			array(),
			NH_CR_VERSION
		);
		wp_enqueue_script(
			'nh-cart-recovery-admin',
			NH_CR_URL . 'assets/js/admin.js',
			array(),
			NH_CR_VERSION,
			true
		);
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
		$locale   = nh_cr_shop_locale();

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
		echo '<div class="wrap nh-cr-admin"><h1>' . esc_html__( 'Cart recovery', NH_CR_TD ) . '</h1>';
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
		$locale = nh_cr_shop_locale();
		echo '<form method="post" action="options.php">';
		settings_fields( 'nh_cr_settings_group' );
		echo '<table class="form-table" role="presentation">';
		self::checkbox_row( 'enabled', $o['enabled'], __( 'Enable recovery emails', NH_CR_TD ), __( 'Emails are sent with wp_mail, so WP Mail SMTP + Brevo is used automatically. No extra Brevo API key is required.', NH_CR_TD ) );
		self::checkbox_row( 'checkout_on_cancel', $o['checkout_on_cancel'], __( 'Email after unpaid checkout is cancelled', NH_CR_TD ), __( 'This is the Svea / Kustom “pending payment → cancelled” case. Skipped if the same email already placed a paid order.', NH_CR_TD ) );

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

		$group_labels = array(
			'en' => 'English',
			'sv' => 'Swedish',
			'nb' => 'Norwegian',
			'da' => 'Danish',
			'fi' => 'Finnish',
			'de' => 'German',
			'lt' => 'Lithuanian',
		);
		$group = nh_cr_locale_group( $locale );
		echo '<p class="description" style="max-width:52em;">';
		echo esc_html(
			sprintf(
				/* translators: 1: language name, 2: WordPress locale code */
				__( 'Fields below are prefilled in the shop language (%1$s, %2$s from Settings → General), not your WordPress profile language. Leave them as they are to keep future translation updates. Change a field only when you want shop-specific wording. Use {first_name} in subject/heading — it is replaced with the customer’s name, or removed when no name is known.', NH_CR_TD ),
				isset( $group_labels[ $group ] ) ? $group_labels[ $group ] : $group,
				$locale
			)
		);
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
		$locale = nh_cr_shop_locale();
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

		echo '<div class="nh-cr-preview-bar">';
		foreach ( array( 'cart' => __( 'Abandoned cart', NH_CR_TD ), 'checkout' => __( 'Unfinished payment', NH_CR_TD ) ) as $key => $label ) {
			echo '<a class="button' . ( $type === $key ? ' button-primary' : '' ) . '" href="' . esc_url( add_query_arg( array( 'type' => $key, 'step' => $step, 'named' => $named ? '1' : '0' ), $base ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</div><div class="nh-cr-preview-bar">';
		foreach ( array( 1 => __( 'Email 1 (1 hour / on cancel)', NH_CR_TD ), 2 => __( 'Email 2 (next day)', NH_CR_TD ), 3 => __( 'Email 3 (3 days)', NH_CR_TD ) ) as $n => $label ) {
			echo '<a class="button' . ( $step === $n ? ' button-primary' : '' ) . '" href="' . esc_url( add_query_arg( array( 'type' => $type, 'step' => $n, 'named' => $named ? '1' : '0' ), $base ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</div><div class="nh-cr-preview-bar">';
		echo '<a class="button' . ( $named ? ' button-primary' : '' ) . '" href="' . esc_url( add_query_arg( array( 'type' => $type, 'step' => $step, 'named' => '1' ), $base ) ) . '">' . esc_html__( 'With name (Anna)', NH_CR_TD ) . '</a>';
		echo '<a class="button' . ( ! $named ? ' button-primary' : '' ) . '" href="' . esc_url( add_query_arg( array( 'type' => $type, 'step' => $step, 'named' => '0' ), $base ) ) . '">' . esc_html__( 'No name known', NH_CR_TD ) . '</a>';
		echo '</div>';

		$parts = NH_CR_Mailer::preview_parts( $type, $step, $locale, $name );
		$doc   = NH_CR_Mailer::preview_document( $type, $step, $locale, $name );
		echo '<p><strong>' . esc_html__( 'Subject', NH_CR_TD ) . ':</strong> ' . esc_html( $parts['subject'] ) . '</p>';
		echo '<iframe class="nh-cr-preview-frame" title="' . esc_attr__( 'Email preview', NH_CR_TD ) . '" srcdoc="' . esc_attr( $doc ) . '"></iframe>';
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
		$signal = isset( $_GET['signal'] ) ? sanitize_key( wp_unslash( (string) $_GET['signal'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$device = isset( $_GET['device'] ) ? sanitize_key( wp_unslash( (string) $_GET['device'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $device !== '' && ! in_array( $device, nh_cr_device_keys(), true ) ) {
			$device = '';
		}
		$paged  = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data   = NH_CR_Store::query(
			array(
				'status' => $status,
				'signal' => $signal,
				'device' => $device,
				'paged'  => $paged,
			)
		);
		$base = array(
			'page' => 'nh-cart-recovery',
			'tab'  => 'list',
		);
		$pages = max( 1, (int) ceil( $data['total'] / max( 1, (int) $data['per_page'] ) ) );

		echo '<p class="nh-cr-help">';
		echo esc_html__( 'Each card is one WooCommerce browser session. Open a card to see every item in the latest cart snapshot. Recovery emails are only sent when an email is known. Postcode appears after the customer calculates shipping or fills checkout.', NH_CR_TD );
		echo ' ';
		echo esc_html__( 'This list is often larger than GA4 add_to_cart: the plugin saves on the server as soon as Woo adds an item, including crawlers and people who never load analytics. Use the device filter to separate phones, desktops, and likely bots.', NH_CR_TD );
		echo '</p>';

		echo '<div class="nh-cr-filters" role="navigation" aria-label="' . esc_attr__( 'Status', NH_CR_TD ) . '">';
		foreach (
			array(
				''             => __( 'All', NH_CR_TD ),
				'open'         => __( 'Open', NH_CR_TD ),
				'sent'         => __( 'Sent', NH_CR_TD ),
				'converted'    => __( 'Converted', NH_CR_TD ),
				'skipped'      => __( 'Skipped', NH_CR_TD ),
				'unsubscribed' => __( 'Unsubscribed', NH_CR_TD ),
			) as $key => $label
		) {
			self::chip(
				add_query_arg( array_merge( $base, array( 'status' => $key, 'signal' => $signal, 'device' => $device ) ), admin_url( 'admin.php' ) ),
				$label,
				$status === $key
			);
		}
		echo '</div>';
		echo '<div class="nh-cr-filters" role="navigation" aria-label="' . esc_attr__( 'Identity', NH_CR_TD ) . '">';
		foreach (
			array(
				''              => __( 'Any identity', NH_CR_TD ),
				'with_email'    => __( 'Has email', NH_CR_TD ),
				'with_postcode' => __( 'Has postcode', NH_CR_TD ),
				'anonymous'     => __( 'No email or postcode', NH_CR_TD ),
			) as $key => $label
		) {
			self::chip(
				add_query_arg( array_merge( $base, array( 'status' => $status, 'signal' => $key, 'device' => $device ) ), admin_url( 'admin.php' ) ),
				$label,
				$signal === $key
			);
		}
		echo '</div>';
		echo '<div class="nh-cr-filters" role="navigation" aria-label="' . esc_attr__( 'Device', NH_CR_TD ) . '">';
		foreach (
			array(
				''        => __( 'Any device', NH_CR_TD ),
				'desktop' => __( 'Desktop', NH_CR_TD ),
				'mobile'  => __( 'Mobile', NH_CR_TD ),
				'tablet'  => __( 'Tablet', NH_CR_TD ),
				'bot'     => __( 'Likely bot', NH_CR_TD ),
				'unknown' => __( 'Unknown', NH_CR_TD ),
			) as $key => $label
		) {
			self::chip(
				add_query_arg( array_merge( $base, array( 'status' => $status, 'signal' => $signal, 'device' => $key ) ), admin_url( 'admin.php' ) ),
				$label,
				$device === $key
			);
		}
		echo '</div>';

		echo '<div class="nh-cr-toolbar">';
		echo '<p class="nh-cr-count">' . esc_html(
			sprintf(
				/* translators: %d: number of cart records */
				_n( '%d cart', '%d carts', $data['total'], NH_CR_TD ),
				$data['total']
			)
		) . '</p>';
		if ( $data['rows'] ) {
			echo '<p>';
			echo '<button type="button" class="button" data-nh-cr-expand="1">' . esc_html__( 'Expand all', NH_CR_TD ) . '</button> ';
			echo '<button type="button" class="button" data-nh-cr-expand="0">' . esc_html__( 'Collapse all', NH_CR_TD ) . '</button>';
			echo '</p>';
		}
		echo '</div>';

		if ( ! $data['rows'] ) {
			echo '<div class="nh-cr-empty">' . esc_html__( 'No records yet.', NH_CR_TD ) . '</div>';
			return;
		}

		echo '<div class="nh-cr-list">';
		foreach ( $data['rows'] as $row ) {
			self::render_card( $row );
		}
		echo '</div>';

		if ( $pages > 1 ) {
			echo '<nav class="nh-cr-pager" aria-label="' . esc_attr__( 'Cart pages', NH_CR_TD ) . '">';
			for ( $i = 1; $i <= $pages; $i++ ) {
				$url = add_query_arg(
					array_merge(
						$base,
						array(
							'status' => $status,
							'signal' => $signal,
							'device' => $device,
							'paged'  => $i,
						)
					),
					admin_url( 'admin.php' )
				);
				$class = $i === (int) $data['paged'] ? ' button button-primary' : ' button';
				echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( (string) $i ) . '</a>';
			}
			echo '</nav>';
		}
	}

	/**
	 * @param string $url    Link.
	 * @param string $label  Label.
	 * @param bool   $active Active chip.
	 */
	private static function chip( $url, $label, $active ) {
		echo '<a class="nh-cr-chip' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * @param object $row Store row.
	 */
	private static function render_card( $row ) {
		$items    = nh_cr_decode_cart( isset( $row->cart ) ? $row->cart : '' );
		$count    = count( $items );
		$location = nh_cr_format_location( $row );
		$email    = isset( $row->email ) ? (string) $row->email : '';
		$name     = trim( ( isset( $row->first_name ) ? (string) $row->first_name : '' ) . ' ' . ( isset( $row->last_name ) ? (string) $row->last_name : '' ) );
		$phone    = isset( $row->phone ) ? (string) $row->phone : '';
		$status   = isset( $row->status ) ? (string) $row->status : 'open';
		$type     = isset( $row->type ) ? (string) $row->type : 'cart';
		$who      = $email !== '' ? $email : ( $name !== '' ? $name : __( 'No email', NH_CR_TD ) );
		$summary  = nh_cr_cart_summary( $items, 4 );
		$updated  = nh_cr_format_when( isset( $row->updated_at ) ? $row->updated_at : '' );
		$emailed  = nh_cr_format_when( isset( $row->emailed_at ) ? $row->emailed_at : '' );
		$sent     = NH_CR_Store::emails_sent_count( $row );
		$total    = nh_cr_cart_grand_total( $items );
		$device   = isset( $row->device ) ? (string) $row->device : '';
		$ua       = isset( $row->user_agent ) ? (string) $row->user_agent : '';
		$dev_key  = in_array( $device, nh_cr_device_keys(), true ) ? $device : 'unknown';
		$dev_label = nh_cr_device_label( $dev_key );

		echo '<details class="nh-cr-card">';
		echo '<summary>';
		echo '<div class="nh-cr-card__head">';
		echo '<div>';
		echo '<div class="nh-cr-card__title">';
		echo '<span class="nh-cr-card__id">#' . esc_html( (string) $row->id ) . '</span>';
		echo '<span class="nh-cr-badge nh-cr-badge--' . esc_attr( $status ) . '">' . esc_html( $status ) . '</span>';
		echo '<span class="nh-cr-badge nh-cr-badge--' . esc_attr( $type ) . '">' . esc_html( $type ) . '</span>';
		echo '<span class="nh-cr-badge nh-cr-badge--device nh-cr-badge--' . esc_attr( $dev_key ) . '"' . ( $ua !== '' ? ' title="' . esc_attr( $ua ) . '"' : '' ) . '>' . esc_html( $dev_label ) . '</span>';
		echo '</div>';
		echo '<div class="nh-cr-card__meta">';
		echo '<span>' . esc_html( $who ) . '</span>';
		if ( $location !== '' ) {
			echo '<span>' . esc_html( $location ) . '</span>';
		}
		echo '<span>' . esc_html(
			sprintf(
				/* translators: %d: number of products in the cart */
				_n( '%d item', '%d items', $count, NH_CR_TD ),
				$count
			)
		) . '</span>';
		if ( $updated !== '' ) {
			echo '<span>' . esc_html( $updated ) . '</span>';
		}
		echo '</div>';
		if ( $summary !== '' ) {
			echo '<div class="nh-cr-card__cart">' . esc_html( $summary ) . '</div>';
		}
		echo '</div>';
		echo '<div class="nh-cr-card__aside">';
		echo '<span class="nh-cr-chevron" aria-hidden="true"></span>';
		if ( $total > 0 ) {
			echo '<span>' . wp_kses_post( nh_cr_format_money( $total ) ) . '</span>';
		}
		echo '</div>';
		echo '</div>';
		echo '</summary>';

		echo '<div class="nh-cr-card__body">';
		echo '<dl class="nh-cr-facts">';
		self::fact( __( 'Email', NH_CR_TD ), $email !== '' ? $email : '—' );
		self::fact( __( 'Name', NH_CR_TD ), $name !== '' ? $name : '—' );
		self::fact( __( 'Phone', NH_CR_TD ), $phone !== '' ? $phone : '—' );
		self::fact( __( 'Location', NH_CR_TD ), $location !== '' ? $location : '—' );
		self::fact( __( 'Device', NH_CR_TD ), $dev_label );
		self::fact( __( 'User agent', NH_CR_TD ), $ua !== '' ? $ua : '—' );
		self::fact( __( 'Emails sent', NH_CR_TD ), (string) $sent );
		self::fact( __( 'Last emailed', NH_CR_TD ), $emailed !== '' ? $emailed : '—' );
		echo '</dl>';

		if ( ! $items ) {
			echo '<p class="description">' . esc_html__( 'No products in the latest snapshot.', NH_CR_TD ) . '</p>';
		} else {
			echo '<ul class="nh-cr-items">';
			foreach ( $items as $item ) {
				self::render_item( $item );
			}
			echo '</ul>';
			if ( $total > 0 ) {
				echo '<div class="nh-cr-total"><span>' . esc_html__( 'Cart total', NH_CR_TD ) . '</span><span>' . wp_kses_post( nh_cr_format_money( $total ) ) . '</span></div>';
			}
		}
		echo '</div>';
		echo '</details>';
	}

	/**
	 * @param string $label Label.
	 * @param string $value Value.
	 */
	private static function fact( $label, $value ) {
		echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
	}

	/**
	 * @param array<string, mixed> $item Snapshot item.
	 */
	private static function render_item( $item ) {
		$name  = nh_cr_cart_item_name( $item );
		$qty   = isset( $item['quantity'] ) ? (float) $item['quantity'] : 0;
		$meta  = nh_cr_cart_item_meta_lines( $item );
		$img   = isset( $item['image_url'] ) ? (string) $item['image_url'] : '';
		$price = isset( $item['line_total'] ) && is_numeric( $item['line_total'] ) ? (float) $item['line_total'] : 0.0;

		echo '<li class="nh-cr-item">';
		if ( $img !== '' ) {
			echo '<img class="nh-cr-item__img" src="' . esc_url( $img ) . '" alt="" width="48" height="48" />';
		} else {
			echo '<span class="nh-cr-item__ph" aria-hidden="true"></span>';
		}
		echo '<div>';
		echo '<p class="nh-cr-item__name"><span class="nh-cr-item__qty">' . esc_html( nh_cr_format_qty( $qty ) ) . ' ×</span> ' . esc_html( $name ) . '</p>';
		if ( $meta ) {
			echo '<p class="nh-cr-item__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
		}
		echo '</div>';
		echo '<div class="nh-cr-item__price">' . ( $price > 0 ? wp_kses_post( nh_cr_format_money( $price ) ) : '' ) . '</div>';
		echo '</li>';
	}
}
