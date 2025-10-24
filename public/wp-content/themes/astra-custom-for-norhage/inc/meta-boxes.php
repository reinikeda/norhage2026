<?php
// 1) ADMIN ONLY: metabox registration & save
if ( is_admin() ) {

    // Register the Downloads, Video, and Custom Cutting metaboxes
    add_action( 'add_meta_boxes', function(){
        add_meta_box(
            'nrh_downloads_box',
            __( 'Downloads', 'your-text-domain' ),
            'nrh_downloads_box_html',
            'product',
            'normal',
            'high'
        );
        add_meta_box(
            'nrh_video_box',
            __( 'Video', 'your-text-domain' ),
            'nrh_video_box_html',
            'product',
            'normal',
            'high'
        );

        // --- Custom Cutting metabox ---
        add_meta_box(
            'nh_custom_cutting_box',
            __( 'Custom Cutting', 'your-text-domain' ),
            'nh_custom_cutting_box_html',
            'product',
            'normal',
            'default'
        );
    });

    // Render the Downloads metabox
    function nrh_downloads_box_html( $post ) {
        $downloads = get_post_meta( $post->ID, '_nrh_downloads', true );
        if ( ! is_array( $downloads ) ) {
            $downloads = [];
        }
        wp_nonce_field( 'nrh_save_downloads', 'nrh_downloads_nonce' );
        echo '<table class="form-table widefat fixed" id="nrh-downloads-table"><thead><tr>
                <th style="width:40%;">' . esc_html__( 'Label', 'your-text-domain' ) . '</th>
                <th style="width:55%;">' . esc_html__( 'PDF URL',   'your-text-domain' ) . '</th>
                <th style="width:5%;"></th>
              </tr></thead><tbody>';
        foreach ( $downloads as $i => $row ) {
            printf(
              '<tr>
                 <td><input type="text" name="nrh_downloads[%1$d][label]" value="%2$s" style="width:100%%;"></td>
                 <td><input type="url"  name="nrh_downloads[%1$d][url]"   value="%3$s" style="width:100%%;"></td>
                 <td><button class="button remove-download" type="button">–</button></td>
               </tr>',
              $i,
              esc_attr( $row['label'] ),
              esc_url( $row['url'] )
            );
        }
        echo '</tbody></table>';
        echo '<p><button id="add-download" class="button" type="button">+ '
             . esc_html__( 'Add Download', 'your-text-domain' )
             . '</button></p>';

        // JS to add/remove rows
        ?>
        <script>
        jQuery(function($){
          var $tbody   = $('#nrh-downloads-table tbody'),
              template = '<tr><td><input type="text" name="" style="width:100%;"></td>'
                       + '<td><input type="url"  name="" style="width:100%;"></td>'
                       + '<td><button class="button remove-download" type="button">–</button></td></tr>';

          $('#add-download').on('click', function(){
            var idx  = $tbody.children('tr').length,
                $row = $(template);
            $row.find('input[type=text]')
                .attr('name','nrh_downloads['+idx+'][label]');
            $row.find('input[type=url]')
                .attr('name','nrh_downloads['+idx+'][url]');
            $tbody.append($row);
          });

          $tbody.on('click', '.remove-download', function(){
            $(this).closest('tr').remove();
            $tbody.children('tr').each(function(i){
              $(this).find('input[type=text]')
                     .attr('name','nrh_downloads['+i+'][label]');
              $(this).find('input[type=url]')
                     .attr('name','nrh_downloads['+i+'][url]');
            });
          });
        });
        </script>
        <?php
    }

    // Render the Video metabox
    function nrh_video_box_html( $post ) {
        $video_url = get_post_meta( $post->ID, '_nrh_video_url', true );
        wp_nonce_field( 'nrh_save_video', 'nrh_video_nonce' );
        echo '<table class="form-table"><tr>
                <th><label for="nrh_video_url">'
                  . esc_html__( 'YouTube / Vimeo URL', 'your-text-domain' )
                . '</label></th>
                <td><input type="url" id="nrh_video_url" name="nrh_video_url" '
                  . 'value="' . esc_url( $video_url ) . '" style="width:100%;" />'
                . '</td>
              </tr></table>';
    }

    // --- Render the Custom Cutting metabox ---
    function nh_custom_cutting_box_html( $post ) {
        $pfx = '_nh_cc_';
        $vals = [
            'enabled'       => (bool) get_post_meta($post->ID, $pfx.'enabled', true),
            'cut_fee'       => get_post_meta($post->ID, $pfx.'cut_fee', true),
            'min_w'         => get_post_meta($post->ID, $pfx.'min_w', true),
            'max_w'         => get_post_meta($post->ID, $pfx.'max_w', true),
            'min_l'         => get_post_meta($post->ID, $pfx.'min_l', true),
            'max_l'         => get_post_meta($post->ID, $pfx.'max_l', true),
            'step_mm'       => get_post_meta($post->ID, $pfx.'step_mm', true),
            'weight_per_m2' => get_post_meta($post->ID, $pfx.'weight_per_m2', true),
        ];
        wp_nonce_field('nh_cc_save','nh_cc_nonce');
        ?>
        <style>
          .nh-cc-grid{display:grid;grid-template-columns:220px 1fr;gap:8px 16px;align-items:center}
          .nh-cc-grid label{font-weight:600}
          .nh-cc-row{display:contents}
          .nh-cc-desc{grid-column:1 / -1;color:#666}
        </style>

        <div class="nh-cc-grid">
          <div class="nh-cc-row">
            <label for="nh_cc_enabled"><?php _e('Enable custom cutting', 'your-text-domain'); ?></label>
            <input type="checkbox" id="nh_cc_enabled" name="nh_cc_enabled" value="1" <?php checked($vals['enabled']); ?> />
          </div>

          <div class="nh-cc-row">
            <label for="nh_cc_cut_fee"><?php _e('Cutting fee per sheet', 'your-text-domain'); ?></label>
            <input type="number" step="0.01" min="0" name="nh_cc_cut_fee" id="nh_cc_cut_fee" value="<?php echo esc_attr($vals['cut_fee']); ?>" />
          </div>

          <div class="nh-cc-row">
            <label for="nh_cc_min_w"><?php _e('Min width (mm)', 'your-text-domain'); ?></label>
            <input type="number" step="1" min="0" name="nh_cc_min_w" id="nh_cc_min_w" value="<?php echo esc_attr($vals['min_w']); ?>" />
          </div>
          <div class="nh-cc-row">
            <label for="nh_cc_max_w"><?php _e('Max width (mm)', 'your-text-domain'); ?></label>
            <input type="number" step="1" min="0" name="nh_cc_max_w" id="nh_cc_max_w" value="<?php echo esc_attr($vals['max_w']); ?>" />
          </div>

          <div class="nh-cc-row">
            <label for="nh_cc_min_l"><?php _e('Min length (mm)', 'your-text-domain'); ?></label>
            <input type="number" step="1" min="0" name="nh_cc_min_l" id="nh_cc_min_l" value="<?php echo esc_attr($vals['min_l']); ?>" />
          </div>
          <div class="nh-cc-row">
            <label for="nh_cc_max_l"><?php _e('Max length (mm)', 'your-text-domain'); ?></label>
            <input type="number" step="1" min="0" name="nh_cc_max_l" id="nh_cc_max_l" value="<?php echo esc_attr($vals['max_l']); ?>" />
          </div>

          <div class="nh-cc-row">
            <label for="nh_cc_step_mm"><?php _e('Cutting step (mm)', 'your-text-domain'); ?></label>
            <input type="number" step="1" min="1" name="nh_cc_step_mm" id="nh_cc_step_mm" value="<?php echo esc_attr($vals['step_mm']); ?>" />
          </div>

          <div class="nh-cc-row">
            <label for="nh_cc_weight_per_m2"><?php _e('Weight per m² (kg)', 'your-text-domain'); ?></label>
            <input type="number" step="0.001" min="0" name="nh_cc_weight_per_m2" id="nh_cc_weight_per_m2" value="<?php echo esc_attr($vals['weight_per_m2']); ?>" />
          </div>

          <div class="nh-cc-desc">
            <?php _e('Use this to enable custom cutting on a Simple product. Standard-size variants are best kept on a separate Variable product.', 'your-text-domain'); ?>
          </div>
        </div>
        <?php
    }

    // Save Downloads, Video, and Custom Cutting meta
    add_action( 'save_post_product', function( $post_id ) {
        // ----- Downloads -----
        if ( isset( $_POST['nrh_downloads_nonce'] ) &&
             wp_verify_nonce( $_POST['nrh_downloads_nonce'], 'nrh_save_downloads' ) &&
             current_user_can( 'edit_post', $post_id ) ) {
            $clean = [];
            if ( ! empty( $_POST['nrh_downloads'] ) && is_array( $_POST['nrh_downloads'] ) ) {
                foreach ( $_POST['nrh_downloads'] as $row ) {
                    $label = sanitize_text_field( $row['label'] ?? '' );
                    $url   = esc_url_raw(    $row['url']   ?? '' );
                    if ( $label && $url ) {
                        $clean[] = [ 'label' => $label, 'url' => $url ];
                    }
                }
            }
            update_post_meta( $post_id, '_nrh_downloads', $clean );
        }

        // ----- Video -----
        if ( isset( $_POST['nrh_video_nonce'] ) &&
             wp_verify_nonce( $_POST['nrh_video_nonce'], 'nrh_save_video' ) &&
             current_user_can( 'edit_post', $post_id ) ) {
            $video = ! empty( $_POST['nrh_video_url'] )
                   ? esc_url_raw( $_POST['nrh_video_url'] )
                   : '';
            update_post_meta( $post_id, '_nrh_video_url', $video );
        }

        // ----- Custom Cutting -----
        if ( isset($_POST['nh_cc_nonce']) &&
             wp_verify_nonce($_POST['nh_cc_nonce'], 'nh_cc_save') &&
             current_user_can('edit_post', $post_id) ) {

            $pfx = '_nh_cc_';
            $fields = [
                'enabled'       => isset($_POST['nh_cc_enabled']) ? '1' : '',
                'cut_fee'       => wc_format_decimal($_POST['nh_cc_cut_fee']       ?? ''),
                'min_w'         => ($v = $_POST['nh_cc_min_w']  ?? '') === '' ? '' : absint($v),
                'max_w'         => ($v = $_POST['nh_cc_max_w']  ?? '') === '' ? '' : absint($v),
                'min_l'         => ($v = $_POST['nh_cc_min_l']  ?? '') === '' ? '' : absint($v),
                'max_l'         => ($v = $_POST['nh_cc_max_l']  ?? '') === '' ? '' : absint($v),
                'step_mm'       => ($v = $_POST['nh_cc_step_mm']?? '') === '' ? '' : max(1, absint($v)),
                'weight_per_m2' => wc_format_decimal($_POST['nh_cc_weight_per_m2'] ?? ''),
            ];

            foreach ($fields as $k => $v){
                $meta_key = $pfx.$k;
                if ($v === '' || $v === null) {
                    delete_post_meta($post_id, $meta_key);
                } else {
                    update_post_meta($post_id, $meta_key, $v);
                }
            }
        }
    } );
}

// 2) ALWAYS (admin & front): register product tabs only when there is content
add_filter( 'woocommerce_product_tabs', function( $tabs ) {

    // Get current product ID safely on product pages
    $product_id = 0;
    if ( function_exists('is_product') && is_product() ) {
        $product_id = get_the_ID();
    }
    if ( ! $product_id ) {
        global $product;
        if ( $product instanceof WC_Product ) {
            $product_id = $product->get_id();
        }
    }
    if ( ! $product_id ) {
        return $tabs; // can't decide, leave existing tabs alone
    }

    // Check Downloads meta (array of rows with label+url)
    $downloads = get_post_meta( $product_id, '_nrh_downloads', true );
    $has_downloads = false;
    if ( is_array( $downloads ) ) {
        foreach ( $downloads as $row ) {
            $label = isset($row['label']) ? trim( (string) $row['label'] ) : '';
            $url   = isset($row['url'])   ? trim( (string) $row['url'] )   : '';
            if ( $label !== '' && $url !== '' ) { $has_downloads = true; break; }
        }
    }

    if ( $has_downloads ) {
        $tabs['nrh_downloads'] = [
            'title'    => __( 'Downloads', 'your-text-domain' ),
            'priority' => 25,
            'callback' => 'nrh_downloads_tab_content',
        ];
    }

    // Check Video meta (valid URL and embeddable if possible)
    $video_url = trim( (string) get_post_meta( $product_id, '_nrh_video_url', true ) );
    $has_video = false;
    if ( $video_url !== '' ) {
        // If wp_oembed_get() yields HTML, we know it's embeddable.
        $embed = wp_oembed_get( esc_url( $video_url ) );
        if ( $embed ) {
            $has_video = true;
        } else {
            // fallback: allow direct link tabs too — set to false if you prefer to hide when not embeddable
            $has_video = true;
        }
    }

    if ( $has_video ) {
        $tabs['nrh_video'] = [
            'title'    => __( 'Video', 'your-text-domain' ),
            'priority' => 30,
            'callback' => 'nrh_video_tab_content',
        ];
    }

    // Keep reviews later if present
    if ( isset( $tabs['reviews'] ) ) {
        $tabs['reviews']['priority'] = 35;
    }

    return $tabs;
}, 98 );

/**
 * Render Downloads tab (now only called if content exists)
 */
function nrh_downloads_tab_content() {
    global $product;
    if ( ! $product instanceof WC_Product ) return;

    $downloads = get_post_meta( $product->get_id(), '_nrh_downloads', true );
    if ( ! is_array( $downloads ) ) return;

    echo '<ul class="nrh-download-list">';
    foreach ( $downloads as $row ) {
        $label = isset($row['label']) ? trim( (string) $row['label'] ) : '';
        $url   = isset($row['url'])   ? trim( (string) $row['url'] )   : '';
        if ( $label !== '' && $url !== '' ) {
            printf(
                '<li><a href="%1$s" target="_blank" rel="noopener">%2$s</a></li>',
                esc_url( $url ),
                esc_html( $label )
            );
        }
    }
    echo '</ul>';
}

/**
 * Render Video tab (now only called if content exists)
 */
function nrh_video_tab_content() {
    global $product;
    if ( ! $product instanceof WC_Product ) return;

    $url = trim( (string) get_post_meta( $product->get_id(), '_nrh_video_url', true ) );
    if ( $url === '' ) return;

    $embed = wp_oembed_get( esc_url( $url ) );
    echo '<div class="product-video-wrap">';
    if ( $embed ) {
        echo $embed; // safe from wp_oembed_get
    } else {
        // Fallback: simple link if not embeddable
        printf(
            '<p><a href="%s" target="_blank" rel="noopener">%s</a></p>',
            esc_url( $url ),
            esc_html__( 'Watch video', 'your-text-domain' )
        );
    }
    echo '</div>';
}


// === Product extras metabox (uses Woo wc-product-search) =====================
if (!defined('ABSPATH')) exit;

define('NC_BUNDLE_META_KEY', '_nc_bundle_items_v2');

add_action('add_meta_boxes', function () {
    add_meta_box(
        'nc_bundle_items_box',
        __('Product extras', 'nc'),
        'nc_bundle_items_box_html',
        'product',
        'normal',
        'default'
    );
});

function nc_bundle_items_box_html($post) {
    $rows = get_post_meta($post->ID, NC_BUNDLE_META_KEY, true);
    if (!is_array($rows)) $rows = [];

    wp_nonce_field('nc_bundle_items_save', 'nc_bundle_items_nonce');

    echo '<p><strong>'.esc_html__("Product extra's", 'nc').'</strong></p>';
    echo '<p>'.esc_html__("Select one or more products that can be added to this product as add-ons. Only simple products or product-variants are allowed.", 'nc').'</p>';

    echo '<table class="widefat striped" id="nc-bundle-rows" style="margin-top:10px">';
    echo '<thead><tr>';
    echo '<th style="width:65%;">'.esc_html__('Product *', 'nc').'</th>';
    echo '<th style="width:20%;">'.esc_html__('Maximum quantity', 'nc').'</th>';
    echo '<th style="width:15%;"></th>';
    echo '</tr></thead><tbody class="nc-sortable">';

    if (empty($rows)) {
        echo nc_bundle_row_template_wc(null, ''); // empty row (no max by default)  // CHANGED
    } else {
        foreach ($rows as $r) {
            $id  = isset($r['id'])  ? (int)$r['id']  : 0;
            // allow empty max                                                      // CHANGED
            $max = (isset($r['max']) && $r['max'] !== '') ? (int)$r['max'] : '';
            echo nc_bundle_row_template_wc($id, $max);
        }
    }

    echo '</tbody></table>';
    echo '<p><button type="button" class="button button-primary" id="nc-add-bundle-row">'.esc_html__('Add product as add-on', 'nc').'</button></p>';

    ?>
    <style>
      #nc-bundle-rows td { vertical-align: middle; }
      .nc-handle { cursor: move; opacity:.7; margin-right:6px; }
      .nc-remove { color:#a00; }
    </style>
    <script>
    jQuery(function($){
      // Add row
      $('#nc-add-bundle-row').on('click', function(){
        // do not prefill max with 1 — leave empty                                   // CHANGED
        var html = <?php echo json_encode(preg_replace('/\s+/', ' ', nc_bundle_row_template_wc(null, ''))); ?>;
        $('#nc-bundle-rows tbody').append(html);
        // Initialize Woo enhanced selects on the newly added field
        $(document.body).trigger('wc-enhanced-select-init');
      });
      // Remove row
      $(document).on('click', '.nc-remove', function(){
        $(this).closest('tr').remove();
      });
      // Sortable (optional)
      if ($.fn.sortable) {
        $('#nc-bundle-rows tbody.nc-sortable').sortable({
          handle: '.nc-handle',
          items: '> tr'
        });
      }
      // Ensure any pre-rendered fields are initialized
      $(document.body).trigger('wc-enhanced-select-init');
    });
    </script>
    <?php
}

// $max default is '' and value can remain empty                                     // CHANGED
function nc_bundle_row_template_wc($prod_id = null, $max = '') {
    $prod_id = $prod_id ? (int)$prod_id : 0;

    // Preload selected option if we have one
    $option_html = '';
    if ($prod_id) {
        $p = wc_get_product($prod_id);
        if ($p) {
            $option_html = '<option value="'.esc_attr($prod_id).'" selected="selected">'.esc_html($p->get_formatted_name()).'</option>';
        }
    }

    ob_start(); ?>
    <tr>
      <td>
        <span class="dashicons dashicons-menu nc-handle" title="<?php echo esc_attr__('Drag to reorder', 'nc'); ?>"></span>
        <select
            class="wc-product-search"
            name="nc_bundle[id][]"
            data-placeholder="<?php echo esc_attr__('Search products & variations…', 'nc'); ?>"
            data-action="woocommerce_json_search_products_and_variations"
            style="width:92%">
          <?php echo $option_html; ?>
        </select>
      </td>
      <td>
        <input type="number"
               min="1"
               step="1"
               name="nc_bundle[max][]"
               value="<?php echo esc_attr($max); ?>"
               placeholder="—" /> <!-- show dash when empty -->                       <!-- CHANGED -->
      </td>
      <td>
        <button type="button" class="button-link nc-remove">&times;</button>
      </td>
    </tr>
    <?php
    return ob_get_clean();
}

// Save rows
add_action('save_post_product', function ($post_id){
    if (!isset($_POST['nc_bundle_items_nonce']) || !wp_verify_nonce($_POST['nc_bundle_items_nonce'], 'nc_bundle_items_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $ids = isset($_POST['nc_bundle']['id'])  ? (array) $_POST['nc_bundle']['id']  : [];
    $mxs = isset($_POST['nc_bundle']['max']) ? (array) $_POST['nc_bundle']['max'] : [];

    $out = [];
    foreach ($ids as $i => $id) {
        $id = (int) $id;
        if ($id <= 0) continue;

        $row = ['id' => $id];

        // Only save max if user entered something                                     // CHANGED
        $raw_max = isset($mxs[$i]) ? trim((string)$mxs[$i]) : '';
        if ($raw_max !== '') {
            $row['max'] = max(1, (int) $raw_max);
        }

        $out[] = $row;
    }

    if ($out) update_post_meta($post_id, NC_BUNDLE_META_KEY, $out);
    else      delete_post_meta($post_id, NC_BUNDLE_META_KEY);
}, 10);
