<?php
/**
 * WooCommerce settings: pick catalog filter attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'nhf_register_admin_page' );
add_action( 'admin_init', 'nhf_register_settings' );
add_action( 'admin_enqueue_scripts', 'nhf_admin_assets' );

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

	register_setting(
		'nhf_filter_settings',
		NHF_OPTION_CHIPS,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'nhf_sanitize_chip_filters',
		)
	);
}

/**
 * Selectable category lists on the catalog filter screen.
 *
 * @param string $hook Current admin page hook.
 */
function nhf_admin_assets( $hook ) {
	if ( 'woocommerce_page_nh-catalog-filters' !== $hook ) {
		return;
	}

	wp_enqueue_style( 'woocommerce_admin_styles' );
	wp_enqueue_script( 'selectWoo' );
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
			<?php echo ' ' . esc_html__( 'Attributes ordered as Name (numeric) use a from–to range.', 'nhf' ); ?>
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

			<?php nhf_render_chip_settings(); ?>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Assign one attribute chip row to leaf categories.
 */
function nhf_render_chip_settings() {
	$attrs = function_exists( 'wc_get_attribute_taxonomies' ) ? wc_get_attribute_taxonomies() : array();
	$terms = function_exists( 'get_terms' ) ? get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		)
	) : array();
	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		$terms = array();
	}

	$by_id  = array();
	foreach ( $terms as $term ) {
		if ( is_object( $term ) && ! empty( $term->term_id ) ) {
			$by_id[ (int) $term->term_id ] = $term;
		}
	}
	$leaf_ids = nhf_leaf_ids_from_terms( $terms );
	$leaves   = array();
	foreach ( $leaf_ids as $id ) {
		if ( isset( $by_id[ $id ] ) ) {
			$leaves[ $id ] = nhf_category_path_label( $by_id[ $id ], $by_id );
		}
	}
	asort( $leaves, SORT_NATURAL | SORT_FLAG_CASE );

	$rows = nhf_get_chip_rows();
	if ( empty( $rows ) ) {
		$rows = array(
			array(
				'attribute'  => '',
				'categories' => array(),
			),
		);
	}
	?>
	<h2 style="margin-top:2rem;"><?php echo esc_html__( 'Category chip filters', 'nhf' ); ?></h2>
	<p style="max-width:720px;">
		<?php echo esc_html__( 'On a category with no subcategories, show one attribute as a row of blocks under the short description. Choose the attribute and the categories it belongs on. A category can have one chip filter.', 'nhf' ); ?>
	</p>
	<input type="hidden" name="<?php echo esc_attr( NHF_OPTION_CHIPS ); ?>[saved]" value="1">

	<div id="nhf-chip-rows" style="display:grid;gap:12px;max-width:720px;">
		<?php foreach ( $rows as $index => $row ) : ?>
			<?php nhf_render_chip_row( $index, $row, $attrs, $leaves ); ?>
		<?php endforeach; ?>
	</div>
	<p>
		<button type="button" class="button" id="nhf-chip-add"><?php echo esc_html__( 'Add chip filter', 'nhf' ); ?></button>
	</p>
	<template id="nhf-chip-template">
		<?php
		nhf_render_chip_row(
			'__INDEX__',
			array(
				'attribute'  => '',
				'categories' => array(),
			),
			$attrs,
			$leaves
		);
		?>
	</template>
	<script>
	(function () {
		var rows = document.getElementById('nhf-chip-rows');
		var tpl = document.getElementById('nhf-chip-template');
		var add = document.getElementById('nhf-chip-add');
		if (!rows || !tpl || !add) return;

		function enhance(scope) {
			if (!window.jQuery || !jQuery.fn.selectWoo) return;
			jQuery(scope).find('select.nhf-chip-cats').each(function () {
				var $el = jQuery(this);
				if ($el.data('select2')) return;
				$el.selectWoo({ width: '100%', placeholder: $el.data('placeholder') || '' });
			});
		}

		rows.addEventListener('click', function (event) {
			var button = event.target.closest('.nhf-chip-remove');
			if (!button) return;
			var row = button.closest('.nhf-chip-row');
			if (!row || rows.querySelectorAll('.nhf-chip-row').length < 2) {
				if (row) {
					row.querySelectorAll('select').forEach(function (select) {
						if (select.multiple) {
							Array.prototype.forEach.call(select.options, function (option) { option.selected = false; });
						} else {
							select.value = '';
						}
						select.dispatchEvent(new Event('change', { bubbles: true }));
					});
				}
				return;
			}
			row.remove();
		});

		add.addEventListener('click', function () {
			var html = tpl.innerHTML.replace(/__INDEX__/g, String(Date.now()));
			var wrap = document.createElement('div');
			wrap.innerHTML = html.trim();
			var row = wrap.firstElementChild;
			if (!row) return;
			rows.appendChild(row);
			enhance(row);
		});

		enhance(rows);
	}());
	</script>
	<?php
}

/**
 * One attribute-to-categories row.
 *
 * @param int|string $index  Field index.
 * @param array      $row    Saved row.
 * @param array      $attrs  Woo attribute rows.
 * @param array      $leaves Leaf category ID => label.
 */
function nhf_render_chip_row( $index, $row, $attrs, $leaves ) {
	$attribute  = isset( $row['attribute'] ) ? sanitize_title( (string) $row['attribute'] ) : '';
	$chosen     = isset( $row['categories'] ) && is_array( $row['categories'] ) ? array_map( 'intval', $row['categories'] ) : array();
	$name       = NHF_OPTION_CHIPS . '[rows][' . $index . ']';
	$attr_id    = 'nhf-chip-attr-' . $index;
	$placeholder = __( 'Categories', 'nhf' );
	?>
	<div class="nhf-chip-row" style="display:grid;gap:8px;padding:12px;background:#fff;border:1px solid #dcdcde;">
		<label for="<?php echo esc_attr( $attr_id ); ?>">
			<?php echo esc_html__( 'Attribute', 'nhf' ); ?>
		</label>
		<select id="<?php echo esc_attr( $attr_id ); ?>" name="<?php echo esc_attr( $name ); ?>[attribute]">
			<option value=""><?php echo esc_html__( 'Select an attribute', 'nhf' ); ?></option>
			<?php foreach ( (array) $attrs as $attr ) : ?>
				<?php
				if ( empty( $attr->attribute_name ) ) {
					continue;
				}
				$slug  = sanitize_title( $attr->attribute_name );
				$label = $attr->attribute_label ? $attr->attribute_label : $slug;
				?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $attribute, $slug ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<label>
			<?php echo esc_html__( 'Categories with no subcategories', 'nhf' ); ?>
			<select
				class="nhf-chip-cats"
				name="<?php echo esc_attr( $name ); ?>[categories][]"
				multiple="multiple"
				data-placeholder="<?php echo esc_attr( $placeholder ); ?>"
				style="width:100%;"
			>
				<?php foreach ( $leaves as $id => $label ) : ?>
					<option value="<?php echo esc_attr( (string) $id ); ?>" <?php echo in_array( (int) $id, $chosen, true ) ? 'selected' : ''; ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<p style="margin:0;">
			<button type="button" class="button-link-delete nhf-chip-remove"><?php echo esc_html__( 'Remove', 'nhf' ); ?></button>
		</p>
	</div>
	<?php
}
