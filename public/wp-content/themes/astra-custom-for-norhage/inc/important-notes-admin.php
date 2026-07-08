<?php
/**
 * NH Important Notes - Admin Meta Box
 *
 * Text domain: nh-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register meta box.
 */
add_action( 'add_meta_boxes', 'nh_add_important_notes_meta_box' );
function nh_add_important_notes_meta_box() {
    add_meta_box(
        'nh_important_notes',
        __( 'Important Notes', 'nh-theme' ),
        'nh_render_important_notes_meta_box',
        'product',
        'side',
        'default'
    );
}

/**
 * Render meta box.
 *
 * @param WP_Post $post Post object.
 */
function nh_render_important_notes_meta_box( $post ) {
    wp_nonce_field( 'nh_save_important_notes', 'nh_important_notes_nonce' );

    $value = get_post_meta( $post->ID, '_nh_important_notes', true );
    ?>

    <p>
        <label for="nh_important_notes_field">
            <?php esc_html_e( 'Note keys (comma separated):', 'nh-theme' ); ?>
        </label>
    </p>
    <input
        type="text"
        id="nh_important_notes_field"
        name="nh_important_notes"
        value="<?php echo esc_attr( $value ); ?>"
        style="width:100%;"
    />
    <p class="description">
        <?php esc_html_e( 'Example: shipping_direct, assembly_required, warranty_included', 'nh-theme' ); ?>
    </p>

    <hr style="margin:12px 0;">

    <p><strong><?php esc_html_e( 'Available keys:', 'nh-theme' ); ?></strong></p>
    <ul style="margin-top:4px; font-size:12px; max-height:150px; overflow-y:auto;">
        <?php
        foreach ( nh_get_important_notes() as $key => $note ) {
            printf(
                '<li><code>%s</code> — %s</li>',
                esc_html( $key ),
                esc_html( $note['title'] )
            );
        }
        ?>
    </ul>

    <?php
}

/**
 * Save meta box value.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
add_action( 'save_post', 'nh_save_important_notes_meta_box', 10, 2 );
function nh_save_important_notes_meta_box( $post_id, $post ) {
    if ( ! isset( $_POST['nh_important_notes_nonce'] ) ) {
        return;
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nh_important_notes_nonce'] ) ), 'nh_save_important_notes' ) ) {
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

    if ( ! isset( $_POST['nh_important_notes'] ) ) {
        return;
    }

    $raw  = sanitize_text_field( wp_unslash( $_POST['nh_important_notes'] ) );
    $keys = array_map( 'trim', preg_split( '/[\r\n,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY ) );
    $clean = array();

    foreach ( $keys as $key ) {
        // Allow only lowercase letters, numbers, hyphens, underscores.
        $key = preg_replace( '/[^a-z0-9_-]/', '', $key );
        if ( '' !== $key ) {
            $clean[] = $key;
        }
    }

    if ( empty( $clean ) ) {
        delete_post_meta( $post_id, '_nh_important_notes' );
    } else {
        update_post_meta( $post_id, '_nh_important_notes', implode( ', ', $clean ) );
    }
}
