<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NHGP_Admin {

	public static function init(){
		if ( is_admin() ) {
			add_action( 'admin_menu',  array( __CLASS__, 'menu' ) );
			add_action( 'admin_init',  array( __CLASS__, 'register' ) );

			// Bump version + clear cache on settings save
			$hk = NHGP_Defaults::option_key_heavy();
			$ck = NHGP_Defaults::option_key_custom();
			add_action( "update_option_{$hk}", array( __CLASS__, 'bump_rates_version' ), 10, 0 );
			add_action( "update_option_{$hk}", array( __CLASS__, 'clear_shipping_cache' ), 10, 0 );
			add_action( "update_option_{$ck}", array( __CLASS__, 'bump_rates_version' ), 10, 0 );
			add_action( "update_option_{$ck}", array( __CLASS__, 'clear_shipping_cache' ), 10, 0 );
		}
	}

	public static function menu(){
		add_submenu_page(
			'woocommerce',
			__( 'Universal Shipping Calculator', NHGP_TEXTDOMAIN ),
			__( 'Shipping Calculator', NHGP_TEXTDOMAIN ),
			'manage_woocommerce',
			'nh-heavy-parcel',
			array( __CLASS__, 'page' )
		);
	}

	public static function register(){
		register_setting(
			'nhgp_heavy_group',
			NHGP_Defaults::option_key_heavy(),
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize_heavy' ) )
		);
		register_setting(
			'nhgp_custom_group',
			NHGP_Defaults::option_key_custom(),
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize_custom' ) )
		);
	}

	public static function page(){
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'heavy';

		echo '<div class="wrap"><h1>' . esc_html__( 'Universal Shipping Calculator', NHGP_TEXTDOMAIN ) . '</h1>';
		echo '<h2 class="nav-tab-wrapper">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=nh-heavy-parcel&tab=heavy' ) )  . '" class="nav-tab ' . ( $tab === 'heavy' ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Heavy parcels', NHGP_TEXTDOMAIN ) . '</a>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=nh-heavy-parcel&tab=custom' ) ) . '" class="nav-tab ' . ( $tab === 'custom' ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Custom cutting', NHGP_TEXTDOMAIN ) . '</a>';
		echo '</h2>';

		if ( $tab === 'custom' ) {
			self::render_custom_tab();
		} else {
			self::render_heavy_tab();
		}

		echo '</div>';
	}

	/* ---------- Heavy Tab ---------- */
	private static function render_heavy_tab(){
		$o    = NHGP_Defaults::heavy_get();
		$unit = get_option( 'woocommerce_weight_unit', 'kg' ); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'nhgp_heavy_group' ); ?>
			<table class="form-table" role="presentation">

				<tr>
					<th scope="row"><?php esc_html_e( 'Heavy tiers', NHGP_TEXTDOMAIN ); ?></th>
					<td>
						<p class="description">
							<?php esc_html_e(
								'When total cart weight reaches a level, override the Flat rate with that level’s amount. The active level will be shown as "(Heavy L1–L5)" after the shipping method name.',
								NHGP_TEXTDOMAIN
							); ?>
						</p>
					</td>
				</tr>

				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( sprintf( __( 'Level %d', NHGP_TEXTDOMAIN ), $i ) ); ?></th>
						<td>
							<label><?php esc_html_e( 'Weight ≥', NHGP_TEXTDOMAIN ); ?>
								<input type="number"
									name="<?php echo esc_attr( NHGP_Defaults::option_key_heavy() . "[t{$i}_weight]" ); ?>"
									value="<?php echo esc_attr( $o["t{$i}_weight"] ); ?>"
									step="0.01"
									min="0"
									style="width:120px;margin-left:6px;" /> <?php echo esc_html( $unit ); ?>
							</label>
							&nbsp;&nbsp;
							<label><?php esc_html_e( 'Amount', NHGP_TEXTDOMAIN ); ?>
								<input type="number"
									name="<?php echo esc_attr( NHGP_Defaults::option_key_heavy() . "[t{$i}_amount]" ); ?>"
									value="<?php echo esc_attr( $o["t{$i}_amount"] ); ?>"
									step="0.01"
									min="0"
									style="width:120px;margin-left:6px;" />
							</label>
						</td>
					</tr>
				<?php endfor; ?>

			</table>
			<?php submit_button(); ?>
			<hr/>
			<p><?php esc_html_e( 'Tip: Keep ONE Flat rate in your zone. Below Level 1 your normal class costs apply.', NHGP_TEXTDOMAIN ); ?></p>
		</form>
	<?php }

	public static function sanitize_heavy( $in ){
		$d   = NHGP_Defaults::heavy_all();
		$out = array();

		// Always enabled now – no checkbox in UI and the flag is ignored in logic.
		$out['enabled'] = 1;

		$norm = function( $v, $def ){
			$v = isset( $v ) ? (string) $v : $def;
			$v = preg_replace( '/[^0-9\.\,]/', '', $v );
			$v = str_replace( ',', '.', $v );
			return ( $v === '' ) ? $def : $v;
		};

		for ( $i = 1; $i <= 5; $i++ ){
			$out[ "t{$i}_weight" ] = $norm( $in["t{$i}_weight"] ?? null, $d["t{$i}_weight"] );
			$out[ "t{$i}_amount" ] = $norm( $in["t{$i}_amount"] ?? null, $d["t{$i}_amount"] );
		}

		// keep for backward compatibility; no longer user-configurable
		$out['append_label'] = 1;

		// Ensure levels are sorted by weight
		$pairs = array(
			array( 'w' => (float) $out['t1_weight'], 'a' => (float) $out['t1_amount'] ),
			array( 'w' => (float) $out['t2_weight'], 'a' => (float) $out['t2_amount'] ),
			array( 'w' => (float) $out['t3_weight'], 'a' => (float) $out['t3_amount'] ),
			array( 'w' => (float) $out['t4_weight'], 'a' => (float) $out['t4_amount'] ),
			array( 'w' => (float) $out['t5_weight'], 'a' => (float) $out['t5_amount'] ),
		);

		usort( $pairs, fn( $A, $B ) => $A['w'] <=> $B['w'] );

		for ( $i = 0; $i < 5; $i++ ){
			$idx                     = $i + 1;
			$out[ "t{$idx}_weight" ] = (string) $pairs[ $i ]['w'];
			$out[ "t{$idx}_amount" ] = (string) $pairs[ $i ]['a'];
		}

		return $out;
	}

	/* ---------- Custom Cutting Tab ---------- */
	private static function render_custom_tab(){
		$o = NHGP_Defaults::custom_get();

		$classes = get_terms( array(
			'taxonomy'   => 'product_shipping_class',
			'hide_empty' => false,
		) );

		$choices = array( '' => __( '— none —', NHGP_TEXTDOMAIN ) );
		if ( ! is_wp_error( $classes ) ) {
			foreach ( $classes as $t ) {
				$choices[ $t->slug ] = $t->name . ' (' . $t->slug . ')';
			}
		} ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'nhgp_custom_group' ); ?>
			<table class="form-table" role="presentation">

				<tr>
					<th scope="row"><?php esc_html_e( 'Custom-cut size rules', NHGP_TEXTDOMAIN ); ?></th>
					<td>
						<p class="description">
							<?php esc_html_e( 'Custom-cut items with a width/height will be mapped to a shipping class using the rules below. The feature is always enabled for items that have the custom-cut flag. The active shipping class name will be appended in brackets after the Flat rate name.', NHGP_TEXTDOMAIN ); ?>
						</p>
					</td>
				</tr>

				<?php for ( $i = 1; $i <= 7; $i++ ) : ?>
					<tr>
						<th scope="row">
							<?php echo esc_html( sprintf( __( 'Rule %d', NHGP_TEXTDOMAIN ), $i ) ); ?>
						</th>
						<td>
							<label><?php esc_html_e( 'Width ≤', NHGP_TEXTDOMAIN ); ?>
								<input type="number"
									name="<?php echo esc_attr( NHGP_Defaults::option_key_custom() . "[r{$i}_w]" ); ?>"
									value="<?php echo esc_attr( $o["r{$i}_w"] ); ?>"
									step="0.01"
									min="0"
									style="width:120px;margin-left:6px;" />
							</label>
							&nbsp;&nbsp;
							<label><?php esc_html_e( 'Height ≤', NHGP_TEXTDOMAIN ); ?>
								<input type="number"
									name="<?php echo esc_attr( NHGP_Defaults::option_key_custom() . "[r{$i}_h]" ); ?>"
									value="<?php echo esc_attr( $o["r{$i}_h"] ); ?>"
									step="0.01"
									min="0"
									style="width:120px;margin-left:6px;" />
							</label>
							&nbsp;&nbsp;
							<label><?php esc_html_e( 'Shipping class', NHGP_TEXTDOMAIN ); ?>
								<select name="<?php echo esc_attr( NHGP_Defaults::option_key_custom() . "[r{$i}_class]" ); ?>">
									<?php foreach ( $choices as $slug => $label ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $o["r{$i}_class"] ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</label>
							<p class="description">
								<?php esc_html_e( 'Leave the row empty (no width, no height, no class) to ignore this rule.', NHGP_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>
				<?php endfor; ?>

				<tr>
					<th scope="row"><?php esc_html_e( 'Default class (if no rule matches)', NHGP_TEXTDOMAIN ); ?></th>
					<td>
						<select name="<?php echo esc_attr( NHGP_Defaults::option_key_custom() . "[default_class]" ); ?>">
							<?php foreach ( $choices as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $o['default_class'] ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Used when a custom-cut item is larger than all defined rules and has no product-specific fallback.', NHGP_TEXTDOMAIN ); ?>
						</p>
					</td>
				</tr>

			</table>
			<?php submit_button(); ?>
		</form>
	<?php }

	public static function sanitize_custom( $in ){
		$d   = NHGP_Defaults::custom_all();
		$out = array();

		// Always enabled (no UI checkbox)
		$out['enabled'] = 1;

		$norm = function( $v, $def ){
			$v = isset( $v ) ? (string) $v : $def;
			$v = preg_replace( '/[^0-9\.\,]/', '', $v );
			$v = str_replace( ',', '.', $v );
			return ( $v === '' ) ? $def : $v;
		};

		for ( $i = 1; $i <= 7; $i++ ){
			$def_w = $d["r{$i}_w"]     ?? '';
			$def_h = $d["r{$i}_h"]     ?? '';
			$def_c = $d["r{$i}_class"] ?? '';

			$out["r{$i}_w"]     = $norm( $in["r{$i}_w"] ?? null, $def_w );
			$out["r{$i}_h"]     = $norm( $in["r{$i}_h"] ?? null, $def_h );
			$out["r{$i}_class"] = sanitize_title( $in["r{$i}_class"] ?? $def_c );
		}

		$out['default_class'] = sanitize_title( $in['default_class'] ?? ( $d['default_class'] ?? '' ) );

		// keep for backward compatibility, but logic ignores it now
		$out['show_base_label'] = 1;

		return $out;
	}

	// Save hooks
	public static function bump_rates_version(){
		update_option( 'nhgp_rates_version', time() );
	}

	public static function clear_shipping_cache(){
		if ( ! function_exists( 'WC' ) || ! WC()->session ) return;

		if ( method_exists( WC()->session, 'get_session_data' ) ) {
			foreach ( (array) WC()->session->get_session_data() as $key => $val ) {
				if ( strpos( $key, 'shipping_for_package_' ) === 0 ) {
					WC()->session->__unset( $key );
				}
			}
		} else {
			for ( $i = 0; $i < 10; $i++ ) {
				WC()->session->__unset( "shipping_for_package_{$i}" );
			}
		}

		if ( WC()->cart ) {
			WC()->cart->calculate_shipping();
		}
	}
}
