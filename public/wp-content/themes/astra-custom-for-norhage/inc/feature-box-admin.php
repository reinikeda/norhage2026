<?php
/**
 * NH Feature Box — Admin Meta Box (Product Editor)
 *
 * Text domain: nh-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register meta box on product editor.
 */
add_action( 'add_meta_boxes', 'nh_add_feature_box_metabox' );
function nh_add_feature_box_metabox() {
    add_meta_box(
        'nh_feature_box',
        __( 'Product Feature Box', 'nh-theme' ),
        'nh_render_feature_box_metabox',
        'product',
        'side',
        'default'
    );
}

/**
 * Render meta box HTML.
 *
 * @param WP_Post $post
 */
function nh_render_feature_box_metabox( $post ) {
    wp_nonce_field( 'nh_save_feature_box', 'nh_feature_box_nonce' );

    $raw      = get_post_meta( $post->ID, '_nhf_feature_ids', true );

    // Handle legacy array format (old plugin) — ignore old post IDs.
    if ( is_array( $raw ) ) {
        $saved = array();
    } else {
        $saved = $raw ? array_map( 'trim', preg_split( '/[\r\n,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY ) ) : array();
    }

    $features = nh_get_features();
    ?>

    <p class="description" style="margin-bottom:8px;">
        <?php esc_html_e( 'Select up to 6 features. Drag to reorder.', 'nh-theme' ); ?>
    </p>

    <ul id="nhf-sortable" class="nhf-sortable">
        <?php
        // Print saved (in saved order) first.
        $printed = array();
        foreach ( $saved as $key ) {
            if ( ! isset( $features[ $key ] ) ) {
                continue;
            }
            nh_feature_picker_item( $key, $features[ $key ], true );
            $printed[] = $key;
        }
        // Then remaining unchecked.
        foreach ( $features as $key => $feature ) {
            if ( in_array( $key, $printed, true ) ) {
                continue;
            }
            nh_feature_picker_item( $key, $feature, false );
        }
        ?>
    </ul>

    <p class="description" style="margin-top:6px;">
        <?php esc_html_e( 'CSV meta key: _nhf_feature_ids', 'nh-theme' ); ?>
    </p>

    <?php
}

/**
 * Render a single picker list item.
 *
 * @param string $key
 * @param array  $feature
 * @param bool   $checked
 */
function nh_feature_picker_item( $key, $feature, $checked ) {
    ?>
    <li class="nhf-item" data-key="<?php echo esc_attr( $key ); ?>">
        <label>
            <input
                type="checkbox"
                class="nhf-check"
                name="nhf_feature_ids[]"
                value="<?php echo esc_attr( $key ); ?>"
                <?php checked( $checked ); ?>
            >
            <span class="nhf-admin-icon"><?php echo $feature['icon']; ?></span>
            <span><?php echo esc_html( $feature['label'] ); ?></span>
        </label>
        <span class="dashicons dashicons-move nhf-grip" title="<
        ?php esc_attr_e( 'Drag to reorder', 'nh-theme' ); ?>"></span>
    </li>
    <?php
}

/**
 * Save meta box.
 *
 * @param int     $post_id
 * @param WP_Post $post
 */
add_action( 'save_post', 'nh_save_feature_box_metabox', 10, 2 );
function nh_save_feature_box_metabox( $post_id, $post ) {
    if ( ! isset( $_POST['nh_feature_box_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nh_feature_box_nonce'] ) ), 'nh_save_feature_box' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( 'product' !== $post->post_type ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $all_keys = array_keys( nh_get_features() );
    $selected = array();

    if ( isset( $_POST['nhf_feature_ids'] ) && is_array( $_POST['nhf_feature_ids'] ) ) {
        foreach ( $_POST['nhf_feature_ids'] as $key ) {
            $key = sanitize_key( $key );
            if ( in_array( $key, $all_keys, true ) ) {
                $selected[] = $key;
            }
        }
        $selected = array_slice( array_unique( $selected ), 0, 6 );
    }

    if ( ! empty( $selected ) ) {
        update_post_meta( $post_id, '_nhf_feature_ids', implode( ', ', $selected ) );
    } else {
        delete_post_meta( $post_id, '_nhf_feature_ids' );
    }
}

/**
 * Enqueue admin assets on product editor.
 */
add_action( 'admin_enqueue_scripts', 'nh_enqueue_feature_box_admin_assets' );
function nh_enqueue_feature_box_admin_assets() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'product' ) {
        return;
    }

    wp_enqueue_style(
        'nh-feature-box-admin',
        get_stylesheet_directory_uri() . '/assets/css/product-page.css',
        array(),
        '2.0.0'
    );

    wp_enqueue_script( 'jquery-ui-sortable' );

    wp_enqueue_script(
        'nh-feature-box-admin',
        get_stylesheet_directory_uri() . '/assets/js/feature-box-admin.js',
        array( 'jquery', 'jquery-ui-sortable' ),
        '2.0.0',
        true
    );

    wp_localize_script( 'nh-feature-box-admin', 'NHFAdmin', array(
        'max'  => 6,
        'i18n' => array(
            'limit' => sprintf(
                /* translators: %d = max features */
                __( 'You can select up to %d features.', 'nh-theme' ),
                6
            ),
        ),
    ) );
}
