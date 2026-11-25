<?php
/**
 * Catalog product highlight.
 *
 * Merchandisers tick "Highlight in catalog" on a product (edit screen or Quick
 * Edit). Highlighted cards get a soft yellow block in shop/category grids and
 * sort ahead of other in-stock products. Optional attribute ticks on Product
 * Data → Attributes show those label/value rows between title and price.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post meta key for the catalog highlight flag.
 *
 * @return string
 */
function nh_catalog_highlight_meta_key() {
	return '_nh_catalog_highlight';
}

/**
 * Post meta key for attribute names shown on the highlight card.
 *
 * @return string
 */
function nh_catalog_highlight_attrs_meta_key() {
	return '_nh_catalog_highlight_attrs';
}

/**
 * Whether a product is marked to highlight in the catalog.
 *
 * @param int|\WC_Product|null $product Product ID or object.
 * @return bool
 */
function nh_product_is_catalog_highlight( $product ) {
	$id = 0;
	if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
		$id = (int) $product->get_id();
	} else {
		$id = absint( $product );
	}
	if ( ! $id ) {
		return false;
	}
	return 'yes' === get_post_meta( $id, nh_catalog_highlight_meta_key(), true );
}

/**
 * Attribute taxonomy/custom names selected for the highlight card.
 *
 * @param int $product_id Product ID.
 * @return string[]
 */
function nh_catalog_highlight_selected_attr_names( $product_id ) {
	$names = get_post_meta( (int) $product_id, nh_catalog_highlight_attrs_meta_key(), true );
	if ( ! is_array( $names ) ) {
		return array();
	}
	$clean = array();
	foreach ( $names as $name ) {
		$name = is_string( $name ) ? trim( $name ) : '';
		if ( $name !== '' ) {
			$clean[] = $name;
		}
	}
	return array_values( array_unique( $clean ) );
}

/**
 * Build label/value rows for a highlighted catalog card.
 *
 * @param \WC_Product $product Product.
 * @return array<int, array{label:string,value:string}>
 */
function nh_catalog_highlight_attr_rows( $product ) {
	if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
		return array();
	}
	if ( ! nh_product_is_catalog_highlight( $product ) ) {
		return array();
	}
	if ( ! method_exists( $product, 'get_attribute' ) ) {
		return array();
	}

	$rows = array();
	foreach ( nh_catalog_highlight_selected_attr_names( $product->get_id() ) as $name ) {
		$value = $product->get_attribute( $name );
		if ( ! is_string( $value ) ) {
			$value = '';
		}
		$value = trim( wp_strip_all_tags( $value ) );
		if ( $value === '' ) {
			continue;
		}
		$label = function_exists( 'wc_attribute_label' )
			? wc_attribute_label( $name, $product )
			: $name;
		$rows[] = array(
			'label' => (string) $label,
			'value' => $value,
		);
	}
	return $rows;
}

/**
 * Parse attribute highlight ticks from a product-save request.
 *
 * Returns null when the attributes panel was not submitted (e.g. Quick Edit),
 * so existing selections are left alone.
 *
 * @param array $request Request array (usually $_POST).
 * @return string[]|null
 */
function nh_catalog_highlight_attrs_from_request( array $request ) {
	if ( ! isset( $request['attribute_names'] ) || ! is_array( $request['attribute_names'] ) ) {
		return null;
	}

	$ticks = ( isset( $request['nh_catalog_highlight_attr'] ) && is_array( $request['nh_catalog_highlight_attr'] ) )
		? $request['nh_catalog_highlight_attr']
		: array();

	$selected = array();
	foreach ( $request['attribute_names'] as $i => $name ) {
		if ( empty( $ticks[ $i ] ) ) {
			continue;
		}
		$name = is_string( $name ) ? trim( wp_unslash( $name ) ) : '';
		if ( function_exists( 'wc_clean' ) ) {
			$name = wc_clean( $name );
		} else {
			$name = sanitize_text_field( $name );
		}
		if ( $name === '' ) {
			continue;
		}
		$selected[] = $name;
	}

	return array_values( array_unique( $selected ) );
}

/**
 * Product Data → General: highlight checkbox.
 */
add_action( 'woocommerce_product_options_general_product_data', 'nh_catalog_highlight_field' );
function nh_catalog_highlight_field() {
	echo '<div class="options_group">';
	woocommerce_wp_checkbox(
		array(
			'id'          => nh_catalog_highlight_meta_key(),
			'label'       => __( 'Highlight in catalog', 'nh-theme' ),
			'description' => __( 'Show this product with a highlighted card and place it first in category and shop grids. Tick attributes under the Attributes tab to list them on the card.', 'nh-theme' ),
		)
	);
	echo '</div>';
}

/**
 * Product Data → Attributes: per-attribute “show on highlight card” tick.
 *
 * @param \WC_Product_Attribute $attribute Attribute.
 * @param int                   $i         Attribute index in the admin form.
 */
add_action( 'woocommerce_after_product_attribute_settings', 'nh_catalog_highlight_attr_setting', 10, 2 );
function nh_catalog_highlight_attr_setting( $attribute, $i ) {
	global $post;

	$name = '';
	if ( is_object( $attribute ) && method_exists( $attribute, 'get_name' ) ) {
		$name = (string) $attribute->get_name();
	}

	$selected = ( $post && isset( $post->ID ) )
		? nh_catalog_highlight_selected_attr_names( (int) $post->ID )
		: array();
	$checked  = ( $name !== '' && in_array( $name, $selected, true ) );

	echo '<tr class="nh-catalog-highlight-attr-row"><td colspan="2">';
	echo '<label><input type="checkbox" class="checkbox" name="nh_catalog_highlight_attr[' . esc_attr( (string) (int) $i ) . ']" value="1" ' . checked( $checked, true, false ) . ' /> ';
	echo esc_html__( 'Show on catalog highlight card', 'nh-theme' );
	echo '</label>';
	echo '<p class="description" style="margin:4px 0 0;">' . esc_html__( 'Only used when Highlight in catalog is enabled. Values appear under the title on category cards.', 'nh-theme' ) . '</p>';
	echo '</td></tr>';
}

/**
 * Persist highlight flag and attribute selections on full product save.
 *
 * @param int $post_id Product ID.
 */
add_action( 'woocommerce_process_product_meta', 'nh_catalog_highlight_save' );
function nh_catalog_highlight_save( $post_id ) {
	$key   = nh_catalog_highlight_meta_key();
	$value = isset( $_POST[ $key ] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	update_post_meta( (int) $post_id, $key, $value );

	$attrs = nh_catalog_highlight_attrs_from_request( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( null !== $attrs ) {
		update_post_meta( (int) $post_id, nh_catalog_highlight_attrs_meta_key(), $attrs );
	}
}

/**
 * Quick Edit: highlight checkbox.
 */
add_action( 'woocommerce_product_quick_edit_end', 'nh_catalog_highlight_quick_edit_field' );
function nh_catalog_highlight_quick_edit_field() {
	?>
	<div class="inline-edit-group nh-catalog-highlight-quick-edit-group">
		<label class="alignleft">
			<input type="checkbox" class="nh-catalog-highlight-quick-edit" name="<?php echo esc_attr( nh_catalog_highlight_meta_key() ); ?>" value="yes" />
			<span class="checkbox-title"><?php esc_html_e( 'Highlight in catalog', 'nh-theme' ); ?></span>
		</label>
	</div>
	<?php
}

/**
 * Save highlight from Quick Edit (attributes are not in this form).
 *
 * @param \WC_Product $product Product being saved.
 */
add_action( 'woocommerce_product_quick_edit_save', 'nh_catalog_highlight_quick_edit_save' );
function nh_catalog_highlight_quick_edit_save( $product ) {
	if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
		return;
	}
	$key   = nh_catalog_highlight_meta_key();
	$value = isset( $_REQUEST[ $key ] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$product->update_meta_data( $key, $value );
}

/**
 * Admin assets for Quick Edit.
 *
 * @param string $hook Current admin page.
 */
add_action( 'admin_enqueue_scripts', 'nh_catalog_highlight_admin_assets' );
function nh_catalog_highlight_admin_assets( $hook ) {
	if ( 'edit.php' !== $hook ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit-product' !== $screen->id ) {
		return;
	}

	wp_enqueue_script(
		'nh-catalog-highlight-admin',
		get_stylesheet_directory_uri() . '/assets/js/catalog-highlight-admin.js',
		array( 'jquery', 'inline-edit-post' ),
		function_exists( 'norhage_asset_version' ) ? norhage_asset_version( '/assets/js/catalog-highlight-admin.js' ) : '1.0.0',
		true
	);
}

/**
 * Add CSS class on catalog product cards.
 *
 * @param string[]    $classes Class list.
 * @param \WC_Product $product Product.
 * @return string[]
 */
add_filter( 'woocommerce_post_class', 'nh_catalog_highlight_post_class', 10, 2 );
function nh_catalog_highlight_post_class( $classes, $product ) {
	if ( nh_product_is_catalog_highlight( $product ) ) {
		$classes[] = 'nh-catalog-highlight';
	}
	return $classes;
}

/**
 * Products list column so highlights are visible without opening each product.
 *
 * @param array<string,string> $columns Columns.
 * @return array<string,string>
 */
add_filter( 'manage_edit-product_columns', 'nh_catalog_highlight_column', 20 );
function nh_catalog_highlight_column( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'sku' === $key || ( 'name' === $key && ! isset( $columns['sku'] ) ) ) {
			$new['nh_catalog_highlight'] = __( 'Highlight', 'nh-theme' );
		}
	}
	if ( ! isset( $new['nh_catalog_highlight'] ) ) {
		$new['nh_catalog_highlight'] = __( 'Highlight', 'nh-theme' );
	}
	return $new;
}

/**
 * Render the products-list highlight column + hidden Quick Edit seed.
 *
 * @param string $column Column key.
 */
add_action( 'manage_product_posts_custom_column', 'nh_catalog_highlight_column_content', 10, 1 );
function nh_catalog_highlight_column_content( $column ) {
	if ( 'nh_catalog_highlight' !== $column ) {
		return;
	}
	global $post;
	if ( ! $post || empty( $post->ID ) ) {
		return;
	}
	$on = nh_product_is_catalog_highlight( $post->ID );
	echo '<div class="hidden" id="nh_catalog_highlight_inline_' . esc_attr( (string) (int) $post->ID ) . '">' . ( $on ? 'yes' : 'no' ) . '</div>';
	if ( ! $on ) {
		echo '<span class="nh-catalog-highlight-col nh-catalog-highlight-col--no" aria-hidden="true">—</span>';
		return;
	}
	echo '<span class="nh-catalog-highlight-col nh-catalog-highlight-col--yes" title="' . esc_attr__( 'Highlighted in catalog', 'nh-theme' ) . '">' . esc_html__( 'Yes', 'nh-theme' ) . '</span>';
}

/**
 * Narrow the admin column.
 */
add_action( 'admin_head', 'nh_catalog_highlight_admin_css' );
function nh_catalog_highlight_admin_css() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit-product' !== $screen->id ) {
		return;
	}
	echo '<style>
		.wp-list-table .column-nh_catalog_highlight { width: 5.5em; text-align: center; }
		.nh-catalog-highlight-col--yes { font-weight: 600; color: #1E3932; }
		.nh-catalog-highlight-quick-edit-group { clear: both; margin-top: 0.5em; }
	</style>';
}

/**
 * Print selected attributes on highlighted catalog cards (between title and price).
 *
 * Astra rebuilds the shop card: title/price live in .astra-shop-summary-wrap via
 * astra_woo_woocommerce_shop_product_content(). Woo’s after_shop_loop_item_title
 * still fires inside the thumbnail wrap, so use Astra’s title_after hook.
 */
add_action( 'astra_woo_shop_title_after', 'nh_catalog_highlight_render_attrs', 10 );
function nh_catalog_highlight_render_attrs() {
	global $product;
	if ( ! $product ) {
		return;
	}
	$rows = nh_catalog_highlight_attr_rows( $product );
	if ( ! $rows ) {
		return;
	}

	echo '<ul class="nh-catalog-highlight-attrs">';
	foreach ( $rows as $row ) {
		echo '<li class="nh-catalog-highlight-attrs__item">';
		echo '<span class="nh-catalog-highlight-attrs__label">' . esc_html( $row['label'] ) . '</span>';
		echo '<span class="nh-catalog-highlight-attrs__sep">: </span>';
		echo '<span class="nh-catalog-highlight-attrs__value">' . esc_html( $row['value'] ) . '</span>';
		echo '</li>';
	}
	echo '</ul>';
}

/**
 * Pin highlighted products ahead of other in-stock items on catalog queries.
 *
 * Runs after out-of-stock sorting so the final order is:
 * in-stock highlighted → other in-stock → out of stock.
 *
 * @param array    $posts_clauses Query clauses.
 * @param WP_Query $query        Query.
 * @return array
 */
add_filter( 'posts_clauses', 'nh_catalog_highlight_posts_clauses', 2001, 2 );
function nh_catalog_highlight_posts_clauses( $posts_clauses, $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return $posts_clauses;
	}
	if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy() ) ) {
		return $posts_clauses;
	}

	global $wpdb;
	return nh_catalog_highlight_append_sort_clauses( $posts_clauses, $wpdb );
}

/**
 * Append highlight JOIN + ORDER BY fragment (testable without WP query context).
 *
 * @param array  $posts_clauses Query clauses.
 * @param object $wpdb         WordPress DB API.
 * @return array
 */
function nh_catalog_highlight_append_sort_clauses( $posts_clauses, $wpdb ) {
	$meta_key = nh_catalog_highlight_meta_key();
	$join     = isset( $posts_clauses['join'] ) ? (string) $posts_clauses['join'] : '';

	if ( ! preg_match( '/\bnh_highlight\b/', $join ) ) {
		$posts_clauses['join'] = $join . $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} AS nh_highlight ON ({$wpdb->posts}.ID = nh_highlight.post_id AND nh_highlight.meta_key = %s) ",
			$meta_key
		);
	}

	$highlight_order = "CASE WHEN nh_highlight.meta_value = 'yes' THEN 0 ELSE 1 END ASC";
	$orderby         = isset( $posts_clauses['orderby'] ) ? (string) $posts_clauses['orderby'] : '';

	if ( preg_match( '/nh_highlight\.meta_value/', $orderby ) ) {
		return $posts_clauses;
	}

	$stock_fragment = "CASE nh_stock.stock_status WHEN 'outofstock' THEN 1 ELSE 0 END ASC";
	if ( false !== strpos( $orderby, $stock_fragment ) ) {
		$posts_clauses['orderby'] = str_replace(
			$stock_fragment,
			$stock_fragment . ', ' . $highlight_order,
			$orderby
		);
	} else {
		$posts_clauses['orderby'] = $highlight_order . ( $orderby !== '' ? ', ' . $orderby : '' );
	}

	return $posts_clauses;
}
