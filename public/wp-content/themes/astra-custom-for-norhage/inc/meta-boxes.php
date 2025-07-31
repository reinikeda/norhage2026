<?php
// 1) ADMIN ONLY: metabox registration & save
if ( is_admin() ) {

    // Register the Downloads & Video metaboxes
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

    // Save Downloads & Video meta
    add_action( 'save_post_product', function( $post_id ) {
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

        if ( isset( $_POST['nrh_video_nonce'] ) &&
             wp_verify_nonce( $_POST['nrh_video_nonce'], 'nrh_save_video' ) &&
             current_user_can( 'edit_post', $post_id ) ) {
            $video = ! empty( $_POST['nrh_video_url'] )
                   ? esc_url_raw( $_POST['nrh_video_url'] )
                   : '';
            update_post_meta( $post_id, '_nrh_video_url', $video );
        }
    } );
}

// 2) ALWAYS (admin & front): register the two product tabs
add_filter( 'woocommerce_product_tabs', function( $tabs ) {
    $tabs['nrh_downloads'] = [
        'title'    => __( 'Downloads', 'your-text-domain' ),
        'priority' => 25,
        'callback' => 'nrh_downloads_tab_content'
    ];
    $tabs['nrh_video'] = [
        'title'    => __( 'Video', 'your-text-domain' ),
        'priority' => 30,
        'callback' => 'nrh_video_tab_content'
    ];
    if ( isset( $tabs['reviews'] ) ) {
        $tabs['reviews']['priority'] = 35;
    }
    return $tabs;
}, 98 );

/**
 * Render Downloads tab front‐end
 */
function nrh_downloads_tab_content() {
    global $product;
    $downloads = get_post_meta( $product->get_id(), '_nrh_downloads', true );
    if ( ! empty( $downloads ) ) {
        echo '<ul class="nrh-download-list">';
        foreach ( $downloads as $row ) {
            printf(
              '<li><a href="%1$s" target="_blank">%2$s</a></li>',
              esc_url( $row['url'] ),
              esc_html( $row['label'] )
            );
        }
        echo '</ul>';
    } else {
        echo '<p>' . esc_html__( 'No downloads available.', 'your-text-domain' ) . '</p>';
    }
}

/**
 * Render Video tab front‐end
 */
function nrh_video_tab_content() {
    global $product;
    $url = get_post_meta( $product->get_id(), '_nrh_video_url', true );
    if ( $url ) {
        echo '<div class="product-video-wrap">';
        echo wp_oembed_get( esc_url( $url ) );
        echo '</div>';
    } else {
        echo '<p>' . esc_html__( 'No video available.', 'your-text-domain' ) . '</p>';
    }
}
