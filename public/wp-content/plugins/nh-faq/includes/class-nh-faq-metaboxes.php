<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NH_FAQ_Metaboxes {

    public function __construct() {
        // Metabox on FAQ edit
        add_action( 'add_meta_boxes',               [$this,'add_boxes'] );
        add_action( 'save_post_nh_faq',             [$this,'save'], 10, 2 );

        // Read-only metabox on Product edit (shows attached FAQs)
        add_action( 'add_meta_boxes_product',       [$this,'add_product_box'] );

        // Admin list table columns for nh_faq
        add_filter( 'manage_edit-nh_faq_columns',   [$this,'cols'] );
        add_action( 'manage_nh_faq_posts_custom_column', [$this,'coldata'], 10, 2 );
    }

    public function add_boxes() {
        add_meta_box(
            'nh_faq_details',
            __( 'FAQ Details', 'nh-faq' ),
            [$this,'render'],
            'nh_faq',
            'normal',
            'high'
        );
    }

    public function render( $post ) {
        wp_nonce_field( 'nh_faq_save', 'nh_faq_nonce' );

        $selected_raw = get_post_meta( $post->ID, 'nh_products', true );
        $selected = is_array( $selected_raw ) ? array_map( 'absint', $selected_raw ) : [];

        $args = apply_filters( 'nh_faq_products_list_args', [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'numberposts'    => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        $products = get_posts( $args );
        ?>
        <style>
            .nh-grid{display:grid;gap:12px;grid-template-columns:1fr}
            .nh-muted{color:#666}
            .nh-products-wrap{margin-top:.5rem}
            .nh-products-toolbar{display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem}
            .nh-products-filter{min-width:260px}
            .nh-products-list{max-height:320px;overflow:auto;border:1px solid #ccd0d4;border-radius:4px;background:#fff;padding:.5rem}
            .nh-products-list .nh-item{display:flex;align-items:center;gap:.5rem;padding:.2rem .25rem;border-radius:3px}
            .nh-products-list .nh-item:hover{background:#f6f7f7}

            /* Force-hide class to override any admin theme rules that may conflict with [hidden] */
            .nh-products-list .nh-item.nh-hidden { display: none !important; }
        </style>

        <div class="nh-grid">
            <div>
                <h4><?php echo esc_html__( 'Attach to products', 'nh-faq' ); ?></h4>

                <div class="nh-products-wrap">
                    <div class="nh-products-toolbar">
                        <input
                            type="search"
                            id="nh-filter"
                            class="nh-products-filter"
                            placeholder="<?php echo esc_attr__( 'Type to filter…', 'nh-faq' ); ?>"
                            aria-label="<?php echo esc_attr__( 'Filter products', 'nh-faq' ); ?>"
                        >
                        <button type="button" class="button" id="nh-select-all">
                            <?php echo esc_html__( 'Select visible', 'nh-faq' ); ?>
                        </button>
                        <button type="button" class="button" id="nh-clear-all">
                            <?php echo esc_html__( 'Clear visible', 'nh-faq' ); ?>
                        </button>
                        <span class="nh-muted" id="nh-count"></span>
                    </div>

                    <div class="nh-products-list" id="nh-products-list" aria-label="<?php echo esc_attr__( 'Products checklist', 'nh-faq' ); ?>">
                        <?php if ( empty( $products ) ) : ?>
                            <p class="nh-muted"><?php echo esc_html__( 'No products found.', 'nh-faq' ); ?></p>
                        <?php else : ?>
                            <?php
                            foreach ( $products as $p ) :
                                $title = get_the_title( $p );
                                $sku = (string) get_post_meta( $p->ID, '_sku', true );
                                $data_title = mb_strtolower( trim( $title . ' ' . $sku . ' ' . $p->ID ) );
                                ?>
                                <label class="nh-item" data-title="<?php echo esc_attr( $data_title ); ?>">
                                    <input type="checkbox" name="nh_products[]" value="<?php echo (int) $p->ID; ?>"
                                        <?php checked( in_array( (int) $p->ID, $selected, true ) ); ?>>
                                    <span><?php echo esc_html( $title ); ?><?php if ( $sku ) echo ' • ' . esc_html( $sku ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <p class="nh-muted" style="margin-top:.5rem;">
                        <?php echo esc_html__( 'Tip: use the filter to narrow the list, then “Select visible”.', 'nh-faq' ); ?>
                    </p>
                    <p class="nh-muted" style="margin-top:.25rem;">
                        <?php
                        printf(
                            esc_html__( 'To show this FAQ on the %1$s, assign one or more %2$s in the sidebar.', 'nh-faq' ),
                            esc_html__( 'Global FAQ page', 'nh-faq' ),
                            esc_html__( 'FAQ Topics', 'nh-faq' )
                        );
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <script>
        (function(){
          function norm(s){
            if (!s) return '';
            try {
              return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
            } catch(e) {
              return s.toLowerCase();
            }
          }

          const list   = document.getElementById('nh-products-list');
          const filter = document.getElementById('nh-filter');
          const btnAll = document.getElementById('nh-select-all');
          const btnClr = document.getElementById('nh-clear-all');
          const count  = document.getElementById('nh-count');

          if (!list || !filter || !count) return;

          function updateCount(){
            const total   = list.querySelectorAll('.nh-item').length;
            const visible = list.querySelectorAll('.nh-item:not(.nh-hidden)').length;
            const checked = list.querySelectorAll('.nh-item input:checked').length;
            count.textContent = checked + ' <?php echo esc_js( __( 'selected', 'nh-faq' ) ); ?> • ' +
                                visible + '/' + total + ' <?php echo esc_js( __( 'visible', 'nh-faq' ) ); ?>';
          }
          updateCount();

          // Precompute normalized title for each row
          list.querySelectorAll('.nh-item').forEach(row => {
            row.dataset.title = norm(row.dataset.title || row.textContent || '');
          });

          filter.addEventListener('input', () => {
            const q = norm(filter.value.trim());
            list.querySelectorAll('.nh-item').forEach(row => {
              const hay = row.dataset.title || '';
              if ( q && !hay.includes(q) ) {
                row.classList.add('nh-hidden');
              } else {
                row.classList.remove('nh-hidden');
              }
            });
            updateCount();
          });

          btnAll.addEventListener('click', () => {
            list.querySelectorAll('.nh-item:not(.nh-hidden) input[type="checkbox"]').forEach(cb => { cb.checked = true; });
            updateCount();
          });
          btnClr.addEventListener('click', () => {
            list.querySelectorAll('.nh-item:not(.nh-hidden) input[type="checkbox"]').forEach(cb => { cb.checked = false; });
            updateCount();
          });

          list.addEventListener('change', (e) => {
            if (e.target && e.target.type === 'checkbox') updateCount();
          });
        })();
        </script>
        <?php
    }

    public function save( $post_id, $post ) {
        if ( ! isset( $_POST['nh_faq_nonce'] ) || ! wp_verify_nonce( $_POST['nh_faq_nonce'], 'nh_faq_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $products = [];
        if ( isset( $_POST['nh_products'] ) && is_array( $_POST['nh_products'] ) ) {
            foreach ( $_POST['nh_products'] as $id ) {
                $id = (int) $id;
                if ( $id > 0 ) {
                    $products[] = $id;
                }
            }
            $products = array_values( array_unique( $products ) );
        }

        if ( $products ) {
            update_post_meta( $post_id, 'nh_products', $products );
        } else {
            delete_post_meta( $post_id, 'nh_products' );
        }

        delete_post_meta( $post_id, 'nh_scope' );
        delete_post_meta( $post_id, 'nh_is_global' );
    }

    public function add_product_box() {
        add_meta_box(
            'nh_faq_attached',
            __( 'FAQs attached to this product', 'nh-faq' ),
            function( $post ) {
                $faqs = get_posts([
                    'post_type'   => 'nh_faq',
                    'post_status' => 'publish',
                    'numberposts' => -1,
                    'meta_query'  => [[
                        'key'     => 'nh_products',
                        'value'   => 'i:' . $post->ID . ';',
                        'compare' => 'LIKE',
                    ]],
                ]);

                if ( empty( $faqs ) ) {
                    echo '<p>' . esc_html__( 'No FAQs attached.', 'nh-faq' ) . '</p>';
                    return;
                }

                echo '<ul>';
                foreach ( $faqs as $f ) {
                    $edit = get_edit_post_link( $f->ID );
                    echo '<li><a href="' . esc_url( $edit ) . '">' . esc_html( get_the_title( $f ) ) . '</a></li>';
                }
                echo '</ul>';
            },
            'product',
            'side',
            'default'
        );
    }

    public function cols( $cols ) {
        $ins = [
            'nh_count' => __( '#Products', 'nh-faq' ),
        ];
        $new = [];
        foreach ( $cols as $k => $v ) {
            $new[ $k ] = $v;
            if ( 'title' === $k ) {
                $new = array_merge( $new, $ins );
            }
        }
        return $new;
    }

    public function coldata( $col, $post_id ) {
        if ( 'nh_count' === $col ) {
            $arr = (array) get_post_meta( $post_id, 'nh_products', true );
            echo esc_html( count( $arr ) );
        }
    }
}