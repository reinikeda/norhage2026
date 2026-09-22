<?php
/**
 * WooCommerce settings: pick catalog filter attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'nhf_register_admin_page' );
add_action( 'admin_init', 'nhf_register_settings' );

function nhf_register_admin_page() {
	add_submenu_page(
		'woocommerce',
		__( 'Catalog filters', 'nhf' ),
		__( 'Catalog filters', 'nhf' ),
		'manage_woocommerce',
		'nh-catalog-filters',
		'nhf_render_admin_page'
	);
}

function nhf_register_settings() {
	register_setting(
		'nhf_filter_settings',
		NHF_OPTION_ATTRIBUTES,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'nhf_sanitize_filter_attributes',
		)
	);
}

function nhf_render_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$attrs   = function_exists( 'wc_get_attribute_taxonomies' ) ? wc_get_attribute_taxonomies() : array();
	$checked = nhf_normalize_saved_slugs( get_option( NHF_OPTION_ATTRIBUTES, false ) );
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Catalog filters', 'nhf' ); ?></h1>
		<p>
			<?php echo esc_html__( 'Choose which product attributes appear in the catalog sidebar. Until you save this page, the catalog still shows every attribute. Stock and sale stay available.', 'nhf' ); ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'nhf_filter_settings' ); ?>
			<input type="hidden" name="<?php echo esc_attr( NHF_OPTION_ATTRIBUTES ); ?>[]" value="">

			<?php if ( empty( $attrs ) ) : ?>
				<p><?php echo esc_html__( 'No product attributes found.', 'nhf' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:720px;">
					<thead>
						<tr>
							<td class="check-column"><span class="screen-reader-text"><?php echo esc_html__( 'Show in filters', 'nhf' ); ?></span></td>
							<th><?php echo esc_html__( 'Attribute', 'nhf' ); ?></th>
							<th><?php echo esc_html__( 'Slug', 'nhf' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $attrs as $attr ) : ?>
							<?php
							if ( empty( $attr->attribute_name ) ) {
								continue;
							}
							$slug  = sanitize_title( $attr->attribute_name );
							$label = $attr->attribute_label ? $attr->attribute_label : $slug;
							$id    = 'nhf-attr-' . $slug;
							$is_on = in_array( $slug, $checked, true );
							?>
							<tr>
								<th class="check-column" scope="row">
									<input
										type="checkbox"
										id="<?php echo esc_attr( $id ); ?>"
										name="<?php echo esc_attr( NHF_OPTION_ATTRIBUTES ); ?>[]"
										value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( $is_on ); ?>
									>
								</th>
								<td>
									<label for="<?php echo esc_attr( $id ); ?>">
										<?php echo esc_html( $label ); ?>
									</label>
								</td>
								<td><code><?php echo esc_html( $slug ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
