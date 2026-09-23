<?php
/**
 * Drag-and-drop order for the Services list in wp-admin.
 *
 * The saved menu_order is what the archive, related services, and home slider use.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'manage_service_posts_columns', 'nh_service_order_column' );
add_action( 'manage_service_posts_custom_column', 'nh_service_order_column_content', 10, 2 );
add_action( 'pre_get_posts', 'nh_service_admin_list_query' );
add_action( 'admin_enqueue_scripts', 'nh_service_admin_sort_assets' );
add_action( 'wp_ajax_nh_service_sort', 'nh_service_ajax_sort' );

/**
 * True on the Services list while it is showing the frontend order.
 *
 * A column sort such as title or date is left alone, because dragging that
 * list would overwrite the stored order with a different sequence.
 *
 * @return bool
 */
function nh_service_sort_screen_is_active() {
	if ( ! is_admin() ) {
		return false;
	}

	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
	if ( $post_type !== 'service' ) {
		return false;
	}

	$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
	return $orderby === '' || $orderby === 'menu_order';
}

/**
 * @param string[] $columns List columns.
 * @return string[]
 */
function nh_service_order_column( $columns ) {
	if ( ! is_array( $columns ) ) {
		return $columns;
	}

	$with_order = array();
	foreach ( $columns as $key => $label ) {
		$with_order[ $key ] = $label;
		if ( $key === 'cb' ) {
			$with_order['nh_service_order'] = __( 'Order' );
		}
	}

	if ( ! isset( $with_order['nh_service_order'] ) ) {
		$with_order = array( 'nh_service_order' => __( 'Order' ) ) + $with_order;
	}

	return $with_order;
}

/**
 * @param string $column  Column key.
 * @param int    $post_id Service ID.
 */
function nh_service_order_column_content( $column, $post_id ) {
	if ( $column !== 'nh_service_order' || ! nh_service_sort_screen_is_active() ) {
		return;
	}

	echo '<span class="nh-service-order-handle dashicons dashicons-move" title="' . esc_attr__( 'Drag to reorder', 'nh-theme' ) . '"></span>';
	echo '<span class="screen-reader-text">' . esc_html__( 'Drag to reorder', 'nh-theme' ) . '</span>';
}

/**
 * Show the Services list in the same order as the storefront.
 *
 * @param WP_Query $query Admin list query.
 */
function nh_service_admin_list_query( $query ) {
	if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
		return;
	}
	if ( $query->get( 'post_type' ) !== 'service' || ! nh_service_sort_screen_is_active() ) {
		return;
	}

	$query->set( 'orderby', nh_service_orderby() );
}

/**
 * @param string $hook Admin page hook.
 */
function nh_service_admin_sort_assets( $hook ) {
	if ( $hook !== 'edit.php' || ! nh_service_sort_screen_is_active() ) {
		return;
	}

	wp_enqueue_script( 'jquery-ui-sortable' );

	$css = '.column-nh_service_order{width:46px;text-align:center}'
		. '.nh-service-order-handle{cursor:move;color:#787c82}'
		. '.nh-service-order-handle:hover{color:#1d2327}'
		. 'body.post-type-service #the-list tr.ui-sortable-helper{background:#fff}'
		. 'body.post-type-service #the-list tr.nh-service-order-placeholder td{background:#f0f6fc;border-top:2px dashed #c3c4c7}';
	wp_register_style( 'nh-service-order', false, array(), null );
	wp_enqueue_style( 'nh-service-order' );
	wp_add_inline_style( 'nh-service-order', $css );

	$nonce = wp_create_nonce( 'nh_service_sort' );
	$js    = <<<'JS'
jQuery(function($){
  var $tbody = $('#the-list');
  if (!$tbody.length || !$tbody.find('.nh-service-order-handle').length) return;
  if ($tbody.data('nhServiceSortable')) return;
  $tbody.data('nhServiceSortable', true);

  function fixWidth(e, tr){
    var $orig = tr.children();
    var $helper = tr.clone();
    $helper.children().each(function(i){ $(this).width($orig.eq(i).width()); });
    return $helper;
  }

  $tbody.sortable({
    items: '> tr',
    handle: '.nh-service-order-handle',
    helper: fixWidth,
    placeholder: 'nh-service-order-placeholder',
    forcePlaceholderSize: true,
    axis: 'y',
    update: function(){
      var order = [];
      $tbody.find('> tr').each(function(){
        var id = $(this).attr('id') || '';
        var postId = id.replace('post-', '');
        if ($.isNumeric(postId)) order.push(postId);
      });
      $.post(ajaxurl, {
        action: 'nh_service_sort',
        nonce: '__NONCE__',
        order: order
      }).done(function(response){
        if (!response || !response.success) window.location.reload();
      }).fail(function(){
        window.location.reload();
      });
    }
  });
});
JS;
	$js = str_replace( '__NONCE__', $nonce, $js );
	wp_add_inline_script( 'jquery-ui-sortable', $js );
}

/**
 * Save the dropped Services list as menu_order.
 */
function nh_service_ajax_sort() {
	check_ajax_referer( 'nh_service_sort', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
	}

	$page_ids = isset( $_POST['order'] ) ? (array) wp_unslash( $_POST['order'] ) : array();
	$page_ids = array_values( array_filter( array_map( 'absint', $page_ids ) ) );
	if ( ! $page_ids ) {
		wp_send_json_error( array( 'message' => 'Empty' ), 400 );
	}

	foreach ( $page_ids as $post_id ) {
		if ( get_post_type( $post_id ) !== 'service' || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
	}

	$full_ids = get_posts( array(
		'post_type'        => 'service',
		'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
		'numberposts'      => -1,
		'orderby'          => nh_service_orderby(),
		'fields'           => 'ids',
		'suppress_filters' => false,
	) );

	$map = nh_service_menu_order_map( $full_ids, $page_ids );
	foreach ( $map as $post_id => $menu_order ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
	}

	global $wpdb;
	$updated = 0;
	foreach ( $map as $post_id => $menu_order ) {
		$current = (int) get_post_field( 'menu_order', $post_id );
		if ( $current === (int) $menu_order ) {
			continue;
		}
		$wpdb->update(
			$wpdb->posts,
			array( 'menu_order' => (int) $menu_order ),
			array( 'ID' => (int) $post_id ),
			array( '%d' ),
			array( '%d' )
		);
		clean_post_cache( $post_id );
		$updated++;
	}

	wp_send_json_success( array( 'updated' => $updated ) );
}
