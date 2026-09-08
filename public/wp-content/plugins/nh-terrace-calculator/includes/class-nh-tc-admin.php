<?php
/**
 * Settings page + product metabox. Product slots use WooCommerce product search.
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
		add_filter( 'woocommerce_screen_ids', array( __CLASS__, 'screen_ids' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'general_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function screen_ids( $ids ) {
		$ids[] = 'woocommerce_page_nh-terrace-calculator';
		return $ids;
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
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style(
			'nh-tc-admin',
			NH_TC_URL . 'assets/css/admin.css',
			array( 'woocommerce_admin_styles' ),
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

		foreach ( array( 'min_width_mm', 'max_width_mm', 'min_length_mm', 'max_length_mm', 'step_mm', 'default_width_mm', 'default_length_mm', 'default_cc_mm', 'overlap_mm', 'standard_sheet_width_mm', 'wall_profile_mm', 'screw_spacing_mm', 'screw_pack_size', 'tape_roll_mm' ) as $int_key ) {
			if ( isset( $input[ $int_key ] ) ) {
				$out[ $int_key ] = absint( $input[ $int_key ] );
			}
		}

		$out['silicon_metres_per_tube'] = isset( $input['silicon_metres_per_tube'] ) ? (float) $input['silicon_metres_per_tube'] : $defaults['silicon_metres_per_tube'];
		$out['show_discount']           = empty( $input['show_discount'] ) ? 0 : 1;
		$out['show_postcode']           = empty( $input['show_postcode'] ) ? 0 : 1;
		$out['display_mode']            = in_array( $input['display_mode'] ?? '', array( 'all_products', 'selected', 'flagged' ), true )
			? $input['display_mode']
			: 'all_products';

		$ids = array();
		if ( ! empty( $input['display_product_ids'] ) && is_array( $input['display_product_ids'] ) ) {
			foreach ( $input['display_product_ids'] as $id ) {
				$id = absint( $id );
				if ( $id ) {
					$ids[] = $id;
				}
			}
		}
		$out['display_product_ids'] = array_values( array_unique( $ids ) );

		$out['hardware'] = $defaults['hardware'];
		if ( isset( $input['hardware'] ) && is_array( $input['hardware'] ) ) {
			foreach ( $defaults['hardware'] as $key => $val ) {
				if ( is_array( $val ) ) {
					foreach ( $val as $ck => $_v ) {
						$out['hardware'][ $key ][ $ck ] = self::ref_from_posted( $input['hardware'][ $key ][ $ck ] ?? '' );
					}
				} else {
					$out['hardware'][ $key ] = self::ref_from_posted( $input['hardware'][ $key ] ?? '' );
				}
			}
		}

		$out['connecting'] = self::sanitize_ref_map( $defaults['connecting'], $input['connecting'] ?? array() );
		$out['finish']     = self::sanitize_ref_map( $defaults['finish'], $input['finish'] ?? array() );
		$out['sheets']     = self::sanitize_sheets( $input['sheets'] ?? array() );

		return $out;
	}

	/**
	 * @param array<string, mixed> $defaults
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private static function sanitize_ref_map( array $defaults, $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = $defaults;
		foreach ( $defaults as $type => $colours ) {
			if ( ! is_array( $colours ) ) {
				continue;
			}
			foreach ( $colours as $colour => $_v ) {
				$posted = $input[ $type ][ $colour ] ?? null;
				if ( null !== $posted ) {
					$out[ $type ][ $colour ] = self::ref_from_posted( $posted );
				}
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, array<string, array<string, string>>>
	 */
	private static function sanitize_sheets( $input ) {
		$out  = NH_TC_Defaults::sheet_skus();
		$input = is_array( $input ) ? $input : array();
		foreach ( array( 'multiwall', 'solid' ) as $mat ) {
			if ( empty( $input[ $mat ] ) || ! is_array( $input[ $mat ] ) ) {
				continue;
			}
			foreach ( $input[ $mat ] as $thk => $colours ) {
				if ( ! is_array( $colours ) ) {
					continue;
				}
				$thk = (string) absint( $thk );
				foreach ( NH_TC_Defaults::colours() as $colour ) {
					if ( array_key_exists( $colour, $colours ) ) {
						$out[ $mat ][ $thk ][ $colour ] = self::ref_from_posted( $colours[ $colour ] );
					}
				}
			}
		}
		return $out;
	}

	private static function ref_from_posted( $val ) {
		if ( is_array( $val ) ) {
			$val = reset( $val );
		}
		$val = trim( (string) $val );
		if ( $val === '' ) {
			return '';
		}
		if ( ctype_digit( $val ) ) {
			$p = wc_get_product( (int) $val );
			if ( $p instanceof WC_Product ) {
				$sku = $p->get_sku();
				return $sku !== '' ? $sku : (string) $p->get_id();
			}
		}
		return sanitize_text_field( $val );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s   = NH_TC_Defaults::settings();
		$key = NH_TC_Defaults::OPTION_KEY;
		?>
		<div class="wrap nh-tc-admin">
			<h1><?php esc_html_e( 'Terrace roof calculator', NH_TC_TD ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Pick live catalogue products for each kit slot. The calculator looks them up by SKU, so the same mapping works on every Norhage shop.', NH_TC_TD ); ?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'nh_tc_settings_group' ); ?>

				<h2><?php esc_html_e( 'Where the form appears', NH_TC_TD ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Show calculator on', NH_TC_TD ); ?></th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( $key ); ?>[display_mode]" value="all_products" <?php checked( $s['display_mode'], 'all_products' ); ?>> <?php esc_html_e( 'Every product page', NH_TC_TD ); ?></label><br>
							<label><input type="radio" name="<?php echo esc_attr( $key ); ?>[display_mode]" value="selected" <?php checked( $s['display_mode'], 'selected' ); ?>> <?php esc_html_e( 'Only the products selected below', NH_TC_TD ); ?></label><br>
							<label><input type="radio" name="<?php echo esc_attr( $key ); ?>[display_mode]" value="flagged" <?php checked( $s['display_mode'], 'flagged' ); ?>> <?php esc_html_e( 'Only products with the calculator checkbox enabled', NH_TC_TD ); ?></label>
							<p class="description"><?php esc_html_e( 'Default is every product page so the form is visible without extra setup. Switch to selected/checkbox once you have chosen the terrace products. Shortcode: [nh_terrace_calculator].', NH_TC_TD ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Selected products', NH_TC_TD ); ?></th>
						<td>
							<select class="wc-product-search"
								multiple="multiple"
								style="width:100%;max-width:640px;"
								name="<?php echo esc_attr( $key ); ?>[display_product_ids][]"
								data-placeholder="<?php esc_attr_e( 'Search products…', NH_TC_TD ); ?>"
								data-action="woocommerce_json_search_products"
								data-allow_clear="true">
								<?php
								foreach ( (array) $s['display_product_ids'] as $pid ) {
									$p = wc_get_product( $pid );
									if ( $p ) {
										echo '<option value="' . esc_attr( $pid ) . '" selected>' . esc_html( $p->get_formatted_name() ) . '</option>';
									}
								}
								?>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Behaviour', NH_TC_TD ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Default size (mm)', NH_TC_TD ); ?></th>
						<td>
							<label>W <input type="number" name="<?php echo esc_attr( $key ); ?>[default_width_mm]" value="<?php echo esc_attr( $s['default_width_mm'] ); ?>"></label>
							<label>L <input type="number" name="<?php echo esc_attr( $key ); ?>[default_length_mm]" value="<?php echo esc_attr( $s['default_length_mm'] ); ?>"></label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Default frame spacing (CC)', NH_TC_TD ); ?></th>
						<td>
							<input type="number" name="<?php echo esc_attr( $key ); ?>[default_cc_mm]" value="<?php echo esc_attr( $s['default_cc_mm'] ); ?>">
							<p class="description"><?php esc_html_e( 'Used when the customer leaves CC empty. Standard is 600 mm.', NH_TC_TD ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Limits (mm)', NH_TC_TD ); ?></th>
						<td>
							<label><?php esc_html_e( 'Width', NH_TC_TD ); ?>
								<input type="number" name="<?php echo esc_attr( $key ); ?>[min_width_mm]" value="<?php echo esc_attr( $s['min_width_mm'] ); ?>"> –
								<input type="number" name="<?php echo esc_attr( $key ); ?>[max_width_mm]" value="<?php echo esc_attr( $s['max_width_mm'] ); ?>">
							</label><br>
							<label><?php esc_html_e( 'Length', NH_TC_TD ); ?>
								<input type="number" name="<?php echo esc_attr( $key ); ?>[min_length_mm]" value="<?php echo esc_attr( $s['min_length_mm'] ); ?>"> –
								<input type="number" name="<?php echo esc_attr( $key ); ?>[max_length_mm]" value="<?php echo esc_attr( $s['max_length_mm'] ); ?>">
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Show postcode field', NH_TC_TD ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>[show_postcode]" value="1" <?php checked( $s['show_postcode'] ); ?>> <?php esc_html_e( 'Saved to the customer when the kit is added, so shipping can be quoted in the basket.', NH_TC_TD ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Show discount %', NH_TC_TD ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>[show_discount]" value="1" <?php checked( $s['show_discount'] ); ?>> <?php esc_html_e( 'Display-only on the quote. Cart prices stay as in the catalogue (use a coupon for real discounts).', NH_TC_TD ); ?></label></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Multiwall polycarbonate (custom-cut)', NH_TC_TD ); ?></h2>
				<?php self::sheet_table( $key, 'multiwall', $s['sheets']['multiwall'] ?? array() ); ?>

				<h2><?php esc_html_e( 'Solid polycarbonate (custom-cut)', NH_TC_TD ); ?></h2>
				<?php self::sheet_table( $key, 'solid', $s['sheets']['solid'] ?? array() ); ?>

				<h2><?php esc_html_e( 'Connecting profiles', NH_TC_TD ); ?></h2>
				<?php self::profile_table( $key, 'connecting', $s['connecting'], array(
					'clamping'     => __( 'Clamping profile', NH_TC_TD ),
					'clamping_lid' => __( 'Clamping profile with lid', NH_TC_TD ),
					'h_plastic'    => __( 'Plastic H-profile', NH_TC_TD ),
				), array( 'silver', 'clear', 'brown' ) ); ?>

				<h2><?php esc_html_e( 'Finish profiles', NH_TC_TD ); ?></h2>
				<?php self::profile_table( $key, 'finish', $s['finish'], array(
					'f_aluminium' => __( 'F aluminium', NH_TC_TD ),
					'u_plastic'   => __( 'U plastic', NH_TC_TD ),
					'u_aluminium' => __( 'U aluminium', NH_TC_TD ),
					'l_aluminium' => __( 'L aluminium', NH_TC_TD ),
				), array( 'silver', 'brown', 'clear' ) ); ?>

				<h2><?php esc_html_e( 'Sealing tapes, screws and extras', NH_TC_TD ); ?></h2>
				<table class="form-table">
					<?php
					$hw = array(
						'screws'    => __( 'Wood screws with washers', NH_TC_TD ),
						'vent_tape' => __( 'Ventilation / anti-dust tape', NH_TC_TD ),
						'iso_tape'  => __( 'Insulation sealing tape', NH_TC_TD ),
						'gasket'    => __( 'EPDM rubber gasket 50 mm', NH_TC_TD ),
						'silicon'   => __( 'Neutral silicone', NH_TC_TD ),
						'wall'      => __( 'Wall profile 2.2 m', NH_TC_TD ),
						'ridge'     => __( 'Ridge profile 2.2 m (gable)', NH_TC_TD ),
					);
					foreach ( $hw as $slot => $label ) {
						echo '<tr><th>' . esc_html( $label ) . '</th><td>';
						self::product_select( $key . '[hardware][' . $slot . ']', $s['hardware'][ $slot ] ?? '' );
						echo '</td></tr>';
					}
					?>
					<tr>
						<th><?php esc_html_e( 'End cap – silver / grey', NH_TC_TD ); ?></th>
						<td><?php self::product_select( $key . '[hardware][end_cap][silver]', $s['hardware']['end_cap']['silver'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'End cap – brown', NH_TC_TD ); ?></th>
						<td><?php self::product_select( $key . '[hardware][end_cap][brown]', $s['hardware']['end_cap']['brown'] ?? '' ); ?></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @param array<string, array<string, string>> $rows
	 * @param array<string, string>                $type_labels
	 * @param string[]                             $colours
	 */
	private static function profile_table( $key, $group, array $rows, array $type_labels, array $colours ) {
		echo '<table class="widefat striped nh-tc-map"><thead><tr><th>' . esc_html__( 'Type', NH_TC_TD ) . '</th>';
		foreach ( $colours as $c ) {
			echo '<th>' . esc_html( ucfirst( $c ) ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $type_labels as $type => $label ) {
			echo '<tr><th>' . esc_html( $label ) . '</th>';
			foreach ( $colours as $c ) {
				echo '<td>';
				self::product_select( $key . '[' . $group . '][' . $type . '][' . $c . ']', $rows[ $type ][ $c ] ?? '' );
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * @param array<string, array<string, string>> $map
	 */
	private static function sheet_table( $key, $material, array $map ) {
		$colours = NH_TC_Defaults::colours();
		echo '<table class="widefat striped nh-tc-map"><thead><tr><th>' . esc_html__( 'Thickness', NH_TC_TD ) . '</th>';
		foreach ( $colours as $c ) {
			echo '<th>' . esc_html( ucfirst( $c ) ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( NH_TC_Defaults::thicknesses() as $thk ) {
			echo '<tr><th>' . esc_html( $thk ) . ' mm</th>';
			foreach ( $colours as $c ) {
				echo '<td>';
				self::product_select( $key . '[sheets][' . $material . '][' . $thk . '][' . $c . ']', $map[ (string) $thk ][ $c ] ?? '' );
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function product_select( $name, $ref ) {
		$product = function_exists( 'wc_get_product' ) ? NH_TC_Catalog::product_by_ref( $ref ) : null;
		echo '<span class="nh-tc-product-search">';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="" />';
		echo '<select class="wc-product-search" style="width:100%;min-width:180px;" name="' . esc_attr( $name ) . '" data-placeholder="' . esc_attr__( 'Search products…', NH_TC_TD ) . '" data-action="woocommerce_json_search_products" data-allow_clear="true">';
		if ( $product ) {
			echo '<option value="' . esc_attr( $product->get_id() ) . '" selected="selected">' . esc_html( $product->get_formatted_name() ) . '</option>';
		}
		echo '</select></span>';
	}

	public static function general_fields() {
		global $post;
		if ( ! $post ) {
			return;
		}
		echo '<div class="options_group">';
		woocommerce_wp_checkbox(
			array(
				'id'          => 'nh_tc_enabled',
				'label'       => __( 'Terrace calculator', NH_TC_TD ),
				'description' => __( 'Show the terrace roof calculator on this product page (used when display mode is “checkbox”).', NH_TC_TD ),
				'value'       => get_post_meta( $post->ID, NH_TC_Defaults::META_ENABLED, true ) ? 'yes' : 'no',
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => 'nh_tc_hide_atc',
				'label'       => __( 'Hide default add to cart', NH_TC_TD ),
				'description' => __( 'Hide the normal add-to-cart form; customers use the kit offer instead.', NH_TC_TD ),
				'value'       => get_post_meta( $post->ID, NH_TC_Defaults::META_HIDE_ATC, true ) ? 'yes' : 'no',
			)
		);
		echo '</div>';
	}

	public static function save_product( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$enabled = isset( $_POST['nh_tc_enabled'] ) && 'no' !== wp_unslash( $_POST['nh_tc_enabled'] );
		$hide    = isset( $_POST['nh_tc_hide_atc'] ) && 'no' !== wp_unslash( $_POST['nh_tc_hide_atc'] );
		update_post_meta( $post_id, NH_TC_Defaults::META_ENABLED, $enabled ? '1' : '0' );
		update_post_meta( $post_id, NH_TC_Defaults::META_HIDE_ATC, $hide ? '1' : '0' );
	}
}
