<?php
/**
 * Settings page + product metabox for PVC strip calculator.
 *
 * @package nh-pvc-strip-calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_PSC_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'woocommerce_screen_ids', array( __CLASS__, 'screen_ids' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'general_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function screen_ids( $ids ) {
		$ids[] = 'woocommerce_page_nh-pvc-strip-calculator';
		return $ids;
	}

	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'PVC strip calculator', NH_PSC_TD ),
			__( 'PVC strip calculator', NH_PSC_TD ),
			'manage_woocommerce',
			'nh-pvc-strip-calculator',
			array( __CLASS__, 'page' )
		);
	}

	public static function register() {
		register_setting(
			'nh_psc_settings_group',
			NH_PSC_Defaults::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'nh-pvc-strip-calculator' ) ) {
			return;
		}
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style(
			'nh-psc-admin',
			NH_PSC_URL . 'assets/css/admin.css',
			array( 'woocommerce_admin_styles' ),
			NH_PSC_VERSION
		);
	}

	/**
	 * @param mixed $input
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$defaults = NH_PSC_Defaults::all();
		$input    = is_array( $input ) ? $input : array();
		$out      = $defaults;

		foreach ( array( 'min_width_mm', 'max_width_mm', 'min_height_mm', 'max_height_mm', 'step_mm', 'default_width_mm', 'default_height_mm', 'soffit_height_deduct_mm', 'face_height_extra_mm', 'rail_stock_mm', 'hanger_per_strip' ) as $int_key ) {
			if ( isset( $input[ $int_key ] ) ) {
				$out[ $int_key ] = absint( $input[ $int_key ] );
			}
		}

		$out['show_postcode'] = empty( $input['show_postcode'] ) ? 0 : 1;
		$out['display_mode']  = in_array( $input['display_mode'] ?? '', array( 'all_products', 'selected', 'flagged' ), true )
			? $input['display_mode']
			: 'flagged';

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

		foreach ( array( '200', '300', '400' ) as $w ) {
			if ( isset( $input['face_width_extra'][ $w ] ) ) {
				$out['face_width_extra'][ $w ] = absint( $input['face_width_extra'][ $w ] );
			}
			foreach ( array( 'min', 'med', 'max' ) as $ov ) {
				if ( isset( $input['overlap_pct'][ $w ][ $ov ] ) ) {
					$out['overlap_pct'][ $w ][ $ov ] = max( 0.05, min( 0.99, (float) $input['overlap_pct'][ $w ][ $ov ] ) );
				}
			}
			$out['red_strips'][ $w ] = self::ref_from_posted( $input['red_strips'][ $w ] ?? '' );
			$out['hardware']['hanger'][ $w ] = self::ref_from_posted( $input['hardware']['hanger'][ $w ] ?? '' );

			foreach ( array( 'smooth', 'ribbed', 'antistatic', 'frosted' ) as $type ) {
				foreach ( array( 'standard', 'frost' ) as $grade ) {
					$out['strips'][ $w ][ $type ][ $grade ] = self::ref_from_posted(
						$input['strips'][ $w ][ $type ][ $grade ] ?? ''
					);
				}
			}
		}

		$out['hardware']['rail'] = self::ref_from_posted( $input['hardware']['rail'] ?? '' );

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
		$s   = NH_PSC_Defaults::settings();
		$key = NH_PSC_Defaults::OPTION_KEY;
		?>
		<div class="wrap nh-psc-admin">
			<h1><?php esc_html_e( 'PVC strip curtain calculator', NH_PSC_TD ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Plastena-style opening rules with Norhage kit pricing. Map catalogue products by SKU for each strip size, type and grade.', NH_PSC_TD ); ?>
			</p>

			<div class="nh-psc-help">
				<h2><?php esc_html_e( 'How quantities are calculated', NH_PSC_TD ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'Into the opening (soffit): calc height = opening − 45 mm; width unchanged.', NH_PSC_TD ); ?></li>
					<li><?php esc_html_e( 'Above the opening (face): calc height = opening + 30 mm; width + 80 / 90 / 100 mm for 200 / 300 / 400 mm strips.', NH_PSC_TD ); ?></li>
					<li><?php esc_html_e( 'Strip count = ceil(calc width × (1 + overlap%) ÷ strip width). Total metres = strips × calc height.', NH_PSC_TD ); ?></li>
					<li><?php esc_html_e( 'Rails cover the calc width in stock pieces (default 1500 mm). One hanger plate per strip.', NH_PSC_TD ); ?></li>
					<li><?php esc_html_e( 'Red edge strips replace the two outermost strips when enabled.', NH_PSC_TD ); ?></li>
				</ul>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'nh_psc_settings_group' ); ?>

				<h2><?php esc_html_e( 'Where the form appears', NH_PSC_TD ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Display mode', NH_PSC_TD ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $key ); ?>[display_mode]">
								<option value="flagged" <?php selected( $s['display_mode'], 'flagged' ); ?>><?php esc_html_e( 'Only products with the calculator flag', NH_PSC_TD ); ?></option>
								<option value="selected" <?php selected( $s['display_mode'], 'selected' ); ?>><?php esc_html_e( 'Selected product IDs', NH_PSC_TD ); ?></option>
								<option value="all_products" <?php selected( $s['display_mode'], 'all_products' ); ?>><?php esc_html_e( 'All products (not recommended)', NH_PSC_TD ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Enable the flag on the PVC strip curtains system product. Shortcode: [nh_pvc_strip_calculator]', NH_PSC_TD ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Selected products', NH_PSC_TD ); ?></th>
						<td>
							<select class="wc-product-search" multiple="multiple" style="width: 50%;" name="<?php echo esc_attr( $key ); ?>[display_product_ids][]" data-placeholder="<?php esc_attr_e( 'Search products…', NH_PSC_TD ); ?>" data-action="woocommerce_json_search_products_and_variations">
								<?php foreach ( (array) $s['display_product_ids'] as $pid ) : ?>
									<?php $p = wc_get_product( $pid ); if ( ! $p ) continue; ?>
									<option value="<?php echo esc_attr( (string) $pid ); ?>" selected><?php echo esc_html( $p->get_formatted_name() ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Show postcode field', NH_PSC_TD ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>[show_postcode]" value="1" <?php checked( ! empty( $s['show_postcode'] ) ); ?>> <?php esc_html_e( 'Show postal code on the kit step', NH_PSC_TD ); ?></label></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Opening defaults & allowances', NH_PSC_TD ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Default opening', NH_PSC_TD ); ?></th>
						<td>
							<label><?php esc_html_e( 'Width', NH_PSC_TD ); ?> <input type="number" name="<?php echo esc_attr( $key ); ?>[default_width_mm]" value="<?php echo esc_attr( $s['default_width_mm'] ); ?>"> mm</label>
							<label><?php esc_html_e( 'Height', NH_PSC_TD ); ?> <input type="number" name="<?php echo esc_attr( $key ); ?>[default_height_mm]" value="<?php echo esc_attr( $s['default_height_mm'] ); ?>"> mm</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Mounting adjustments', NH_PSC_TD ); ?></th>
						<td>
							<label><?php esc_html_e( 'Soffit height deduct', NH_PSC_TD ); ?> <input type="number" name="<?php echo esc_attr( $key ); ?>[soffit_height_deduct_mm]" value="<?php echo esc_attr( $s['soffit_height_deduct_mm'] ); ?>"> mm</label>
							<label><?php esc_html_e( 'Face height extra', NH_PSC_TD ); ?> <input type="number" name="<?php echo esc_attr( $key ); ?>[face_height_extra_mm]" value="<?php echo esc_attr( $s['face_height_extra_mm'] ); ?>"> mm</label>
							<?php foreach ( array( '200', '300', '400' ) as $w ) : ?>
								<label><?php echo esc_html( $w ); ?> mm face width extra
									<input type="number" name="<?php echo esc_attr( $key ); ?>[face_width_extra][<?php echo esc_attr( $w ); ?>]" value="<?php echo esc_attr( $s['face_width_extra'][ $w ] ); ?>"> mm
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Overlap fractions', NH_PSC_TD ); ?></th>
						<td>
							<?php foreach ( array( '200', '300', '400' ) as $w ) : ?>
								<p><strong><?php echo esc_html( $w ); ?> mm</strong>
								<?php foreach ( array( 'min' => __( 'Min', NH_PSC_TD ), 'med' => __( 'Medium', NH_PSC_TD ), 'max' => __( 'Max', NH_PSC_TD ) ) as $ov => $lab ) : ?>
									<label><?php echo esc_html( $lab ); ?>
										<input type="number" step="0.01" min="0.05" max="0.99" name="<?php echo esc_attr( $key ); ?>[overlap_pct][<?php echo esc_attr( $w ); ?>][<?php echo esc_attr( $ov ); ?>]" value="<?php echo esc_attr( $s['overlap_pct'][ $w ][ $ov ] ); ?>">
									</label>
								<?php endforeach; ?>
								</p>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Hardware packing', NH_PSC_TD ); ?></th>
						<td>
							<label><?php esc_html_e( 'Rail piece length', NH_PSC_TD ); ?> <input type="number" name="<?php echo esc_attr( $key ); ?>[rail_stock_mm]" value="<?php echo esc_attr( $s['rail_stock_mm'] ); ?>"> mm</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Strip products', NH_PSC_TD ); ?></h2>
				<p class="description"><?php esc_html_e( 'Search a product for each size × type × grade. Leave blank to hide that combination.', NH_PSC_TD ); ?></p>
				<?php
				$type_labels = array(
					'smooth'     => __( 'Smooth', NH_PSC_TD ),
					'ribbed'     => __( 'Ribbed', NH_PSC_TD ),
					'antistatic' => __( 'Antistatic', NH_PSC_TD ),
					'frosted'    => __( 'Frosted', NH_PSC_TD ),
				);
				$grade_labels = array(
					'standard' => __( 'Standard', NH_PSC_TD ),
					'frost'    => __( 'Cold-resistant', NH_PSC_TD ),
				);
				foreach ( array( '200', '300', '400' ) as $w ) :
					?>
					<h3><?php echo esc_html( sprintf( /* translators: %d: strip width */ __( '%d mm strips', NH_PSC_TD ), (int) $w ) ); ?></h3>
					<table class="widefat nh-psc-map">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Type', NH_PSC_TD ); ?></th>
								<th><?php esc_html_e( 'Standard', NH_PSC_TD ); ?></th>
								<th><?php esc_html_e( 'Cold-resistant', NH_PSC_TD ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $type_labels as $type => $tlab ) : ?>
							<tr>
								<td><?php echo esc_html( $tlab ); ?></td>
								<?php foreach ( array( 'standard', 'frost' ) as $grade ) : ?>
									<td><?php self::product_field( $key . '[strips][' . $w . '][' . $type . '][' . $grade . ']', $s['strips'][ $w ][ $type ][ $grade ] ); ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endforeach; ?>

				<h2><?php esc_html_e( 'Red edges & hardware', NH_PSC_TD ); ?></h2>
				<table class="form-table">
					<?php foreach ( array( '200', '300', '400' ) as $w ) : ?>
						<tr>
							<th><?php echo esc_html( sprintf( /* translators: %d width */ __( 'Red strip %d mm', NH_PSC_TD ), (int) $w ) ); ?></th>
							<td><?php self::product_field( $key . '[red_strips][' . $w . ']', $s['red_strips'][ $w ] ); ?></td>
						</tr>
						<tr>
							<th><?php echo esc_html( sprintf( /* translators: %d width */ __( 'Hanger %d mm', NH_PSC_TD ), (int) $w ) ); ?></th>
							<td><?php self::product_field( $key . '[hardware][hanger][' . $w . ']', $s['hardware']['hanger'][ $w ] ); ?></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th><?php esc_html_e( 'Hanging rail / bracket', NH_PSC_TD ); ?></th>
						<td><?php self::product_field( $key . '[hardware][rail]', $s['hardware']['rail'] ); ?></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @param string $name
	 * @param string $ref
	 */
	private static function product_field( $name, $ref ) {
		$selected = array();
		$ref      = (string) $ref;
		if ( $ref !== '' ) {
			$p = NH_PSC_Catalog::product_by_ref( $ref );
			if ( $p ) {
				$selected[ (string) $p->get_id() ] = $p->get_formatted_name();
			} else {
				$selected[ $ref ] = $ref;
			}
		}
		?>
		<select class="wc-product-search nh-psc-product-search" style="width: 100%; max-width: 420px;" name="<?php echo esc_attr( $name ); ?>" data-placeholder="<?php esc_attr_e( 'Search product…', NH_PSC_TD ); ?>" data-allow_clear="true" data-action="woocommerce_json_search_products_and_variations">
			<option value=""></option>
			<?php foreach ( $selected as $id => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $id ); ?>" selected="selected"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function general_fields() {
		echo '<div class="options_group">';
		woocommerce_wp_checkbox(
			array(
				'id'          => NH_PSC_Defaults::META_ENABLED,
				'label'       => __( 'PVC strip calculator', NH_PSC_TD ),
				'description' => __( 'Show the PVC strip curtain calculator on this product page.', NH_PSC_TD ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => NH_PSC_Defaults::META_HIDE_ATC,
				'label'       => __( 'Hide default add to cart', NH_PSC_TD ),
				'description' => __( 'Hide the normal add-to-cart form so customers use the kit calculator.', NH_PSC_TD ),
			)
		);
		echo '</div>';
	}

	public static function save_product( $post_id ) {
		$enabled = isset( $_POST[ NH_PSC_Defaults::META_ENABLED ] ) ? '1' : '0'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$hide    = isset( $_POST[ NH_PSC_Defaults::META_HIDE_ATC ] ) ? '1' : '0'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_post_meta( $post_id, NH_PSC_Defaults::META_ENABLED, $enabled );
		update_post_meta( $post_id, NH_PSC_Defaults::META_HIDE_ATC, $hide );
	}
}
