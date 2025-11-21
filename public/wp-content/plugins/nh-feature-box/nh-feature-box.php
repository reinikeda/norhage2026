<?php
/**
 * Plugin Name: Product Feature Box
 * Description: Global feature library (icon + label) and per-product picker (max 8). Renders near the short description on product pages.
 * Version:     1.0.2
 * Author:      Daiva Reinike
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NH_Feature_Box {
    const CPT              = 'nh_feature';
    const TAX_GROUP        = 'nh_feature_group';
    const META_ICON_ID     = '_nhf_icon_id';
    const META_ACTIVE      = '_nhf_active';
    const META_SHOW_BOX    = '_nhf_show_box';
    const META_FEATURE_IDS = '_nhf_feature_ids';
    const MAX_FEATURES     = 6;

    /** Guard to avoid double render */
    private $rendered = false;

    public function __construct() {
        // Admin + setup
        add_action( 'init',                [ $this, 'register_cpt_and_tax' ] );
        add_action( 'add_meta_boxes',      [ $this, 'add_feature_metabox' ] );
        add_action( 'save_post_' . self::CPT, [ $this, 'save_feature_meta' ] );

        add_filter( 'manage_edit-' . self::CPT . '_columns',        [ $this, 'admin_cols' ] );
        add_action( 'manage_' . self::CPT . '_posts_custom_column', [ $this, 'admin_cols_content' ], 10, 2 );

        // Product metabox
        add_action( 'add_meta_boxes',    [ $this, 'add_product_metabox' ] );
        add_action( 'save_post_product', [ $this, 'save_product_meta' ] );

        // Assets
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_front_assets' ] );

        /**
         * Frontend render:
         * - IN-STOCK products: before add-to-cart / custom-cut form
         * - OUT-OF-STOCK products: appended to the short description
         */
        add_action( 'woocommerce_before_add_to_cart_form', [ $this, 'render_feature_box_before_cart' ], 1 );
        add_filter( 'woocommerce_short_description',        [ $this, 'filter_short_description_append_box' ], 20 );

        // Allow SVG for admins (basic)
        add_filter( 'upload_mimes',              [ $this, 'allow_svg_for_admins' ] );
        add_filter( 'wp_check_filetype_and_ext', [ $this, 'fix_svg_mime' ], 10, 5 );
    }

    /* ---------------------------------------------
     * Setup: CPT + Taxonomy
     * ------------------------------------------- */
    public function register_cpt_and_tax() {
        register_post_type( self::CPT, [
            'label'       => __( 'Product Features', 'nhf' ),
            'labels'      => [
                'name'          => __( 'Product Features', 'nhf' ),
                'singular_name' => __( 'Product Feature', 'nhf' ),
                'add_new'       => __( 'Add Feature', 'nhf' ),
                'add_new_item'  => __( 'Add New Feature', 'nhf' ),
                'edit_item'     => __( 'Edit Feature', 'nhf' ),
                'new_item'      => __( 'New Feature', 'nhf' ),
                'view_item'     => __( 'View Feature', 'nhf' ),
                'search_items'  => __( 'Search Features', 'nhf' ),
            ],
            'public'      => false,
            'show_ui'     => true,
            'show_in_menu'=> true,
            'menu_icon'   => 'dashicons-star-filled',
            'supports'    => [ 'title' ],
            'has_archive' => false,
        ] );

        register_taxonomy( self::TAX_GROUP, self::CPT, [
            'label'            => __( 'Feature Groups', 'nhf' ),
            'public'           => false,
            'show_ui'          => true,
            'hierarchical'     => false,
            'show_admin_column'=> true,
        ] );
    }

    /* ---------------------------------------------
     * CPT Metabox: Icon + Active
     * ------------------------------------------- */
    public function add_feature_metabox() {
        add_meta_box(
            'nhf_feature_meta',
            __( 'Feature Settings', 'nhf' ),
            [ $this, 'feature_metabox_html' ],
            self::CPT,
            'normal',
            'high'
        );
    }

    public function feature_metabox_html( $post ) {
        wp_nonce_field( 'nhf_feature_meta', 'nhf_feature_meta_nonce' );
        $icon_id = (int) get_post_meta( $post->ID, self::META_ICON_ID, true );
        $active  = get_post_meta( $post->ID, self::META_ACTIVE, true ) === 'yes';

        $icon_url = $icon_id ? wp_get_attachment_url( $icon_id ) : '';
        ?>
        <div class="nhf-field">
            <label for="nhf_icon"><?php _e( 'Icon (SVG/PNG recommended)', 'nhf' ); ?></label>
            <div class="nhf-media-wrap">
                <input type="hidden" id="nhf_icon_id" name="nhf_icon_id" value="<?php echo esc_attr( $icon_id ); ?>">
                <button type="button" class="button nhf-upload"><?php _e( 'Select/Upload Icon', 'nhf' ); ?></button>
                <button type="button" class="button nhf-remove" <?php disabled( ! $icon_id ); ?>><?php _e( 'Remove', 'nhf' ); ?></button>
                <div class="nhf-preview" style="margin-top:8px;">
                    <?php if ( $icon_url ) : ?>
                        <img src="<?php echo esc_url( $icon_url ); ?>" style="max-width:48px; max-height:48px;" alt="">
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="nhf-field" style="margin-top:12px;">
            <label>
                <input type="checkbox" name="nhf_active" value="yes" <?php checked( $active ); ?>>
                <?php _e( 'Active (available in product picker)', 'nhf' ); ?>
            </label>
        </div>
        <p class="description">
            <?php _e( 'Tip: Keep labels short and clear. The feature title is used as the label on the product page.', 'nhf' ); ?>
        </p>
        <?php
    }

    public function save_feature_meta( $post_id ) {
        if ( ! isset( $_POST['nhf_feature_meta_nonce'] ) || ! wp_verify_nonce( $_POST['nhf_feature_meta_nonce'], 'nhf_feature_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $icon_id = isset( $_POST['nhf_icon_id'] ) ? (int) $_POST['nhf_icon_id'] : 0;
        if ( $icon_id ) {
            update_post_meta( $post_id, self::META_ICON_ID, $icon_id );
        } else {
            delete_post_meta( $post_id, self::META_ICON_ID );
        }

        $active = ( isset( $_POST['nhf_active'] ) && $_POST['nhf_active'] === 'yes' ) ? 'yes' : 'no';
        update_post_meta( $post_id, self::META_ACTIVE, $active );
    }

    public function admin_cols( $cols ) {
        $new = [];
        foreach ( $cols as $k => $v ) {
            if ( 'title' === $k ) {
                $new['nhf_icon']   = __( 'Icon', 'nhf' );
                $new[$k]           = $v;
                $new['nhf_active'] = __( 'Active', 'nhf' );
            } else {
                $new[$k] = $v;
            }
        }
        return $new;
    }

    public function admin_cols_content( $col, $post_id ) {
        if ( 'nhf_icon' === $col ) {
            $icon_id = (int) get_post_meta( $post_id, self::META_ICON_ID, true );
            $url     = $icon_id ? wp_get_attachment_url( $icon_id ) : '';
            if ( $url ) {
                echo '<img src="' . esc_url( $url ) . '" style="max-width:24px;max-height:24px;" alt="">';
            } else {
                echo '—';
            }
        }

        if ( 'nhf_active' === $col ) {
            $active = get_post_meta( $post_id, self::META_ACTIVE, true ) === 'yes';
            echo $active ? '✔' : '—';
        }
    }

    /* ---------------------------------------------
     * Product Metabox
     * ------------------------------------------- */
    public function add_product_metabox() {
        add_meta_box(
            'nhf_product_features',
            __( 'Product Feature Box', 'nhf' ),
            [ $this, 'product_metabox_html' ],
            'product',
            'side',
            'default'
        );
    }

    private function get_active_features() {
        $q = new WP_Query( [
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'   => self::META_ACTIVE,
                    'value' => 'yes',
                ],
            ],
        ] );
        return $q->posts;
    }

    public function product_metabox_html( $post ) {
        wp_nonce_field( 'nhf_product_meta', 'nhf_product_meta_nonce' );

        $show = get_post_meta( $post->ID, self::META_SHOW_BOX, true ) === 'yes';
        $ids  = get_post_meta( $post->ID, self::META_FEATURE_IDS, true );
        $ids  = is_array( $ids ) ? array_values( array_filter( $ids ) ) : [];

        $features = $this->get_active_features();
        ?>
        <div class="nhf-product-box">
            <p>
                <label>
                    <input type="checkbox" name="nhf_show_box" value="yes" <?php checked( $show ); ?>>
                    <?php _e( 'Show Feature Box on this product', 'nhf' ); ?>
                </label>
            </p>

            <div class="nhf-picker" <?php if ( ! $show ) echo 'style="display:none"'; ?>>
                <p><strong><?php _e( 'Select up to 8 features (drag to sort):', 'nhf' ); ?></strong></p>
                <ul id="nhf-sortable" class="nhf-sortable">
                    <?php
                    // First print already-selected (in saved order)
                    $printed = [];
                    foreach ( $ids as $fid ) {
                        $p = get_post( $fid );
                        if ( ! $p || $p->post_type !== self::CPT ) continue;
                        if ( get_post_meta( $fid, self::META_ACTIVE, true ) !== 'yes' ) continue;
                        $this->picker_item( $p, true );
                        $printed[ $fid ] = true;
                    }
                    // Then print remaining actives
                    foreach ( $features as $p ) {
                        if ( isset( $printed[ $p->ID ] ) ) continue;
                        $this->picker_item( $p, false );
                    }
                    ?>
                </ul>
                <input type="hidden" id="nhf-selected-count" value="<?php echo (int) count( $ids ); ?>">
                <p class="description">
                    <?php _e( 'Checked items are active; drag to change order. Limit: 8.', 'nhf' ); ?>
                </p>
            </div>
        </div>
        <?php
    }

    private function picker_item( $p, $checked ) {
        $icon_id = (int) get_post_meta( $p->ID, self::META_ICON_ID, true );
        $icon    = $icon_id ? wp_get_attachment_url( $icon_id ) : '';
        ?>
        <li class="nhf-item" data-id="<?php echo esc_attr( $p->ID ); ?>">
            <label>
                <input type="checkbox" class="nhf-check" name="nhf_feature_ids[]" value="<?php echo esc_attr( $p->ID ); ?>" <?php checked( $checked ); ?>>
                <?php if ( $icon ) : ?>
                    <img src="<?php echo esc_url( $icon ); ?>" alt="" style="width:18px;height:18px;vertical-align:middle;margin-right:6px;">
                <?php endif; ?>
                <span><?php echo esc_html( get_the_title( $p ) ); ?></span>
            </label>
            <span class="dashicons dashicons-move nhf-grip" title="<?php esc_attr_e( 'Drag to reorder', 'nhf' ); ?>"></span>
        </li>
        <?php
    }

    public function save_product_meta( $post_id ) {
        if ( ! isset( $_POST['nhf_product_meta_nonce'] ) || ! wp_verify_nonce( $_POST['nhf_product_meta_nonce'], 'nhf_product_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $show = ( isset( $_POST['nhf_show_box'] ) && $_POST['nhf_show_box'] === 'yes' ) ? 'yes' : 'no';
        update_post_meta( $post_id, self::META_SHOW_BOX, $show );

        $ids = isset( $_POST['nhf_feature_ids'] ) ? array_map( 'intval', (array) $_POST['nhf_feature_ids'] ) : [];
        $ids = array_values( array_unique( $ids ) );
        if ( count( $ids ) > self::MAX_FEATURES ) {
            $ids = array_slice( $ids, 0, self::MAX_FEATURES );
        }

        if ( ! empty( $ids ) ) {
            update_post_meta( $post_id, self::META_FEATURE_IDS, $ids );
        } else {
            delete_post_meta( $post_id, self::META_FEATURE_IDS );
        }
    }

    /* ---------------------------------------------
     * Frontend rendering helpers
     * ------------------------------------------- */
    private function get_product_features( $product_id ) {
        $show = get_post_meta( $product_id, self::META_SHOW_BOX, true ) === 'yes';
        if ( ! $show ) return [];

        $ids = get_post_meta( $product_id, self::META_FEATURE_IDS, true );
        if ( ! is_array( $ids ) || empty( $ids ) ) return [];

        $out = [];
        foreach ( $ids as $fid ) {
            $p = get_post( $fid );
            if ( ! $p || $p->post_type !== self::CPT ) continue;
            if ( get_post_meta( $fid, self::META_ACTIVE, true ) !== 'yes' ) continue;
            $out[] = $p;
        }
        return $out;
    }

    /**
     * Build HTML for feature box + cutting toggle, or return '' if no features.
     */
    private function get_box_and_toggle_html( $product_id ) {
        $features = $this->get_product_features( $product_id );
        if ( empty( $features ) ) {
            return '';
        }

        ob_start();
        echo $this->render_box_html( $features, 'nhf--summary-card' );
        do_action( 'nh_after_feature_box', $product_id ); // cutting toggle hooks here
        return ob_get_clean();
    }

    /**
     * IN-STOCK products: output box before add-to-cart / custom-cut form.
     */
    public function render_feature_box_before_cart() {
        if ( $this->rendered || ! is_product() ) {
            return;
        }

        global $product;
        if ( ! $product instanceof WC_Product ) {
            return;
        }

        if ( ! $product->is_in_stock() ) {
            return;
        }

        $html = $this->get_box_and_toggle_html( $product->get_id() );
        if ( $html === '' ) {
            return;
        }

        echo $html;
        $this->rendered = true;
    }

    /**
     * OUT-OF-STOCK products: append box to the short description HTML.
     */
    public function filter_short_description_append_box( $desc ) {
        if ( $this->rendered || ! is_product() ) {
            return $desc;
        }

        global $product;
        if ( ! $product instanceof WC_Product ) {
            return $desc;
        }

        if ( $product->is_in_stock() ) {
            // In-stock handled by render_feature_box_before_cart().
            return $desc;
        }

        $html = $this->get_box_and_toggle_html( $product->get_id() );
        if ( $html === '' ) {
            return $desc;
        }

        $this->rendered = true;

        // Box (and toggle) will appear directly after the description text.
        return $desc . $html;
    }

    private function render_box_html( $features, $extra_class = '' ) {
        ob_start();
        ?>
        <section class="nhf-box <?php echo esc_attr( $extra_class ); ?>" aria-label="<?php esc_attr_e( 'Key product features', 'nhf' ); ?>">
            <ul class="nhf-list" role="list">
                <?php foreach ( $features as $p ) :
                    $icon_id = (int) get_post_meta( $p->ID, self::META_ICON_ID, true );
                    $url     = $icon_id ? wp_get_attachment_url( $icon_id ) : '';
                    ?>
                    <li class="nhf-item">
                        <span class="nhf-icon" aria-hidden="true">
                            <?php if ( $url ) : ?>
                                <?php echo $this->icon_img_or_inline( $icon_id, $url ); ?>
                            <?php endif; ?>
                        </span>
                        <span class="nhf-label"><?php echo esc_html( get_the_title( $p ) ); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php
        return ob_get_clean();
    }

    private function icon_img_or_inline( $att_id, $url ) {
        $type = get_post_mime_type( $att_id );
        if ( 'image/svg+xml' === $type ) {
            $file = get_attached_file( $att_id );
            if ( $file && file_exists( $file ) ) {
                $svg = file_get_contents( $file );
                // Basic sanitize: strip scripts
                $svg = preg_replace( '#<script[^>]*>.*?</script>#is', '', $svg );
                return $svg; // inline
            }
        }
        return '<img src="' . esc_url( $url ) . '" alt="" />';
    }

    /* ---------------------------------------------
     * Assets
     * ------------------------------------------- */
    public function enqueue_admin_assets( $hook ) {
        $screen = get_current_screen();
        if ( $screen && ( $screen->id === 'product' || $screen->post_type === self::CPT ) ) {
            wp_enqueue_style( 'nhf-admin', plugins_url( 'assets/admin.css', __FILE__ ), [], '1.0.0' );
            wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_media();
            wp_enqueue_script( 'nhf-admin', plugins_url( 'assets/admin.js', __FILE__ ), [ 'jquery','jquery-ui-sortable' ], '1.0.2', true );
            wp_localize_script( 'nhf-admin', 'NHFAdmin', [
                'max'  => self::MAX_FEATURES,
                'i18n' => [
                    'limit' => sprintf( __( 'You can select up to %d features.', 'nhf' ), self::MAX_FEATURES ),
                ],
            ] );
        }
    }

    public function enqueue_front_assets() {
        if ( ! is_product() ) return;
        wp_enqueue_style( 'nhf-front', plugins_url( 'assets/frontend.css', __FILE__ ), [], '1.0.1' );
    }

    /* ---------------------------------------------
     * SVG allowance (admins only)
     * ------------------------------------------- */
    public function allow_svg_for_admins( $mimes ) {
        if ( current_user_can( 'manage_options' ) ) {
            $mimes['svg'] = 'image/svg+xml';
        }
        return $mimes;
    }

    public function fix_svg_mime( $data, $file, $filename, $mimes, $real_mime = '' ) {
        $is_svg = ( '.svg' === strtolower( substr( $filename, -4 ) ) );
        if ( $is_svg ) {
            $data['ext']  = 'svg';
            $data['type'] = 'image/svg+xml';
        }
        return $data;
    }
}

new NH_Feature_Box();
