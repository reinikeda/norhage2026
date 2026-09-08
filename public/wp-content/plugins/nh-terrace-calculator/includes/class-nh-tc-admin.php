<?php
/**
 * Settings page + product metabox.
 *
 * @package nh-terrace-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_TC_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Terrace calculator', NH_TC_TD ),
			__( 'Terrace calculator', NH_TC_TD ),
			'manage_woocommerce',
			'nh-terrace-calculator',
			array( __CLASS__, 'page' )
		);
	}

	public static function register() {
		register_setting(
			'nh_tc_settings_group',
			NH_TC_Defaults::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'nh-terrace-calculator' ) ) {
			return;
		}
		wp_enqueue_style(
			'nh-tc-admin',
			NH_TC_URL . 'assets/css/admin.css',
			array(),
			NH_TC_VERSION
		);
	}

	/**
	 * @param mixed $input
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$defaults = NH_TC_Defaults::all();
		$input    = is_array( $input ) ? $input : array();
		$out      = $defaults;

		foreach ( array( 'min_width_mm', 'max_width_mm', 'min_length_mm', 'max_length_mm', 'step_mm', 'default_width_mm', 'default_length_mm', 'overlap_mm', 'standard_sheet_width_mm', 'wall_profile_mm', 'screw_spacing_mm', 'screw_pack_size', 'tape_roll_mm' ) as $int_key ) {
			if ( isset( $input[ $int_key ] ) ) {
				$out[ $int_key ] = absint( $input[ $int_key ] );
			}
		}

		$out['silicon_metres_per_tube'] = isset( $input['silicon_metres_per_tube'] ) ? (float) $input['silicon_metres_per_tube'] : $defaults['silicon_metres_per_tube'];
		$out['show_discount']           = empty( $input['show_discount'] ) ? 0 : 1;
		$out['show_postcode']           = empty( $input['show_postcode'] ) ? 0 : 1;

		if ( isset( $input['hardware'] ) && is_array( $input['hardware'] ) ) {
			foreach ( $defaults['hardware'] as $key => $val ) {
				if ( is_array( $val ) ) {
					foreach ( $val as $ck => $_v ) {
						if ( isset( $input['hardware'][ $key ][ $ck ] ) ) {
							$out['hardware'][ $key ][ $ck ] = sanitize_text_field( $input['hardware'][ $key ][ $ck ] );
						}
					}
				} elseif ( isset( $input['hardware'][ $key ] ) ) {
					$out['hardware'][ $key ] = sanitize_text_field( $input['hardware'][ $key ] );
				}
			}
		}

		if ( isset( $input['connecting'] ) && is_array( $input['connecting'] ) ) {
			$out['connecting'] = self::sanitize_map( $defaults['connecting'], $input['connecting'] );
		}
		if ( isset( $input['finish'] ) && is_array( $input['finish'] ) ) {
			$out['finish'] = self::sanitize_map( $defaults['finish'], $input['finish'] );
		}
		if ( isset( $input['sheets'] ) && is_array( $input['sheets'] ) ) {
			$out['sheets'] = self::sanitize_map( $defaults['sheets'], $input['sheets'] );
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $defaults
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private static function sanitize_map( array $defaults, array $input ) {
		$out = $defaults;
		foreach ( $defaults as $key => $val ) {
			if ( is_array( $val ) ) {
				$out[ $key ] = self::sanitize_map( $val, isset( $input[ $key ] ) && is_array( $input[ $key ] ) ? $input[ $key ] : array() );
			} elseif ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}
		return $out;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s = NH_TC_Defaults::settings();
		?>
		<div class="wrap nh-tc-admin">
			<h1><?php esc_html_e( 'Terrace roof calculator', NH_TC_TD ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Maps live WooCommerce products by SKU. Put the shortcode [nh_terrace_calculator] on a product, or enable it on the product edit screen.', NH_TC_TD ); ?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'nh_tc_settings_group' ); ?>

				<h2><?php esc_html_e( 'Behaviour', NH_TC_TD ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Default size (mm)', NH_TC_TD ); ?></th>
						<td>
							<label>W <input type="number" name="<?php echo esc_attr( NH_TC_Defaults::OPTION_KEY ); ?>[default_width_mm]" value="<?php echo esc_attr( $s['default_width_mm'] ); ?>"></label>
							<label>L <input type="number" name="<?php echo esc_attr( NH_TC_Defaults::OPTION_KEY ); ?>[default_length_mm]" value="<?php echo esc_attr( $s['default_length_mm'] ); ?>"></label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Limits (mm)', NH_TC_TD ); ?></th>
						<td>
							<label><?php esc_html_e( 'Width', NH_TC_TD ); ?>
								<input type="number" name="<?php echo esc_attr( NH_TC_Defaults::OPTION_KEY ); ?>[min_width_mm]" value="<?php echo esc_attr( $s['min_width_mm'] ); ?>"> –
								<input type="number" name="<?php echo esc_attr( NH_TC_Defaults::OPTION_KEY ); ?>[max_width_mm]" value="<?php echo esc_attr( $s['max_width_mm'] ); ?>">
							</label><br>
							<label><?php esc_html_e( 'Length', NH_TC_TD ); ?>
								<input type="number" name="<?php echo esc_attr( NH_TC_Defaults::OPTION_KEY ); ?>[min_length_mm]" value="<?php echo esc_attr( $s['min_length_mm'] ); ?>"> –
								<input type="number" name="<?php echo esc_attr( NH_TC_Defaults::OPTION_KEY ); ?>[max_length_mm]" value="<?php echo esc_attr( $s['max_length_mm'] ); ?>">
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Show postcode field', NH_TC_TD ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( NH_TC_Defaults::OPTION_KEY ); ?>[show_postcode]" value="1" <?php checked( $s['show_postcode'] ); ?>> <?php esc_html_e( 'Saved to the customer when the kit is added, so shipping can be quoted in the basket.', NH_TC_TD ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Show discount %', NH_TC_TD ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( NH_TC_Defaults::OPTION_KEY ); ?>[show_discount]" value="1" <?php checked( $s['show_discount'] ); ?>> <?php esc_html_e( 'Display-only on the quote. Cart prices stay as in the catalogue (use a coupon for real discounts).', NH_TC_TD ); ?></label></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Hardware SKUs', NH_TC_TD ); ?></h2>
				<table class="form-table">
					<?php
					$hw_labels = array(
						'screws'    => __( 'Wood screws (variable, pack of 50)', NH_TC_TD ),
						'wall'      => __( 'Wall profile 2.2 m', NH_TC_TD ),
						'ridge'     => __( 'Ridge / gable profile 2.2 m', NH_TC_TD ),
						'vent_tape' => __( 'Ventilation tape (variable)', NH_TC_TD ),
						'iso_tape'  => __( 'Insulation tape (variable)', NH_TC_TD ),
						'gasket'    => __( 'EPDM gasket 50 mm (per metre)', NH_TC_TD ),
						'silicon'   => __( 'Neutral silicone', NH_TC_TD ),
					);
					foreach ( $hw_labels as $key => $label ) :
						?>
						<tr>
							<th><?php echo esc_html( $label ); ?></th>
							<td><input type="text" class="regular-text" name="<?php echo esc_attr( NH_TC_Defaults::OPTION_KEY ); ?>[hardware][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $s['hardware'][ $key ] ); ?>"></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th><?php esc_html_e( 'End cap – silver / grey', NH_TC_TD ); ?></th>
						<td><input type="text" class="regular-text" name="<?php echo esc_attr( NH_TC_Defaults::OPTION_KEY ); ?>[hardware][end_cap][silver]" value="<?php echo esc_attr( $s['hardware']['end_cap']['silver'] ); ?>"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'End cap – brown', NH_TC_TD ); ?></th>
						<td><input type="text" class="regular-text" name="<?php echo esc_attr( NH_TC_Defaults::OPTION_KEY ); ?>[hardware][end_cap][brown]" value="<?php echo esc_attr( $s['hardware']['end_cap']['brown'] ); ?>"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Connecting profiles', NH_TC_TD ); ?></h2>
				<table class="form-table">
					<?php self::sku_rows( 'connecting', $s['connecting'] ); ?>
				</table>

				<h2><?php esc_html_e( 'Finish profiles', NH_TC_TD ); ?></h2>
				<table class="form-table">
					<?php self::sku_rows( 'finish', $s['finish'] ); ?>
				</table>

				<h2><?php esc_html_e( 'Custom-cut sheets', NH_TC_TD ); ?></h2>
				<p class="description"><?php esc_html_e( 'Use the custom-size (…C) SKUs so sheets are added with width × length, priced per m².', NH_TC_TD ); ?></p>
				<table class="widefat striped nh-tc-sheet-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Thickness', NH_TC_TD ); ?></th>
							<th><?php esc_html_e( 'Colour', NH_TC_TD ); ?></th>
							<th>SKU</th>
						</tr>
					</thead>
					<tbody>
					<?php
					foreach ( $s['sheets']['multiwall'] as $thk => $colours ) {
						foreach ( $colours as $colour => $sku ) {
							printf(
								'<tr><td>%s mm</td><td>%s</td><td><input type="text" name="%s[sheets][multiwall][%s][%s]" value="%s" class="regular-text"></td></tr>',
								esc_html( $thk ),
								esc_html( $colour ),
								esc_attr( NH_TC_Defaults::OPTION_KEY ),
								esc_attr( $thk ),
								esc_attr( $colour ),
								esc_attr( $sku )
							);
						}
					}
					?>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @param array<string, array<string, string>> $map
	 */
	private static function sku_rows( $group, array $map ) {
		foreach ( $map as $type => $colours ) {
			foreach ( $colours as $colour => $sku ) {
				printf(
					'<tr><th>%s / %s</th><td><input type="text" class="regular-text" name="%s[%s][%s][%s]" value="%s"></td></tr>',
					esc_html( str_replace( '_', ' ', $type ) ),
					esc_html( $colour ),
					esc_attr( NH_TC_Defaults::OPTION_KEY ),
					esc_attr( $group ),
					esc_attr( $type ),
					esc_attr( $colour ),
					esc_attr( $sku )
				);
			}
		}
	}

	public static function metabox() {
		add_meta_box(
			'nh_tc_product',
			__( 'Terrace roof calculator', NH_TC_TD ),
			array( __CLASS__, 'metabox_html' ),
			'product',
			'side',
			'default'
		);
	}

	public static function metabox_html( $post ) {
		$enabled = get_post_meta( $post->ID, NH_TC_Defaults::META_ENABLED, true );
		$hide    = get_post_meta( $post->ID, NH_TC_Defaults::META_HIDE_ATC, true );
		wp_nonce_field( 'nh_tc_product', 'nh_tc_product_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="nh_tc_enabled" value="1" <?php checked( $enabled, '1' ); ?>>
				<?php esc_html_e( 'Show calculator on this product page', NH_TC_TD ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="nh_tc_hide_atc" value="1" <?php checked( $hide, '1' ); ?>>
				<?php esc_html_e( 'Hide the default add-to-cart form', NH_TC_TD ); ?>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'You can also paste [nh_terrace_calculator] into the product description.', NH_TC_TD ); ?></p>
		<?php
	}

	public static function save_product( $post_id ) {
		if ( ! isset( $_POST['nh_tc_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nh_tc_product_nonce'] ) ), 'nh_tc_product' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, NH_TC_Defaults::META_ENABLED, empty( $_POST['nh_tc_enabled'] ) ? '0' : '1' );
		update_post_meta( $post_id, NH_TC_Defaults::META_HIDE_ATC, empty( $_POST['nh_tc_hide_atc'] ) ? '0' : '1' );
	}
}
