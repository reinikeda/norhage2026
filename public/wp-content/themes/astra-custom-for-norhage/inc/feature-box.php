<?php
/**
 * NH Feature Box — Definitions & Renderer
 *
 * Text domain: nh-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * All available product features.
 * Keys are stable string IDs used in meta field and CSV.
 *
 * @return array
 */
function nh_get_features() {
    return apply_filters( 'nh_feature_list', array(

        'anti_drip' => array(
            'label' => __( 'Anti-Drip Tech', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2s6 5.5 6 9a6 6 0 1 1-12 0c0-3.5 6-9 6-9z"/><path d="M9 13c1-1 3-1 4 0"/></svg>',
        ),

        'swedish_pine_frame' => array(
            'label' => __( 'Swedish Pine Frame', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"/><path d="M6 9l6-6 6 6"/><path d="M8.5 13.5l3-3 3 3"/></svg>',
        ),

        'premium_swedish_coating' => array(
            'label' => __( 'Premium Swedish Coating', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12l4 3-4 3H4z"/><path d="M8 9h8"/><path d="M6 18l3-2 3 2 3-2 3 2"/></svg>',
        ),

        'weather_storm_proof' => array(
            'label' => __( 'Weather & Storm Proof', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 16.58A5 5 0 0 0 18 8h-1.3A8 8 0 1 0 4 16.25"/><line x1="16" y1="19" x2="16" y2="22"/><line x1="8" y1="19" x2="8" y2="22"/></svg>',
        ),

        'scratch_rust_resistant' => array(
            'label' => __( 'Scratch & Rust Resistant', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l6 4v6a6 6 0 1 1-12 0V6l6-4z"/><path d="M8.5 13.5l7-7"/></svg>',
        ),

        'made_in_eu' => array(
            'label' => __( 'Made in the EU', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="5.2" r="0.9"/><circle cx="15.6" cy="7.1" r="0.85"/><circle cx="17.2" cy="10.4" r="0.8"/><circle cx="15.6" cy="13.6" r="0.8"/><circle cx="12" cy="15.6" r="0.9"/><circle cx="8.4" cy="13.6" r="0.8"/><circle cx="6.8" cy="10.4" r="0.8"/><circle cx="8.4" cy="7.1" r="0.85"/></svg>',
        ),

        'built_in_uv_protection' => array(
            'label' => __( 'Built-in UV Protection', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18h16"/><path d="M8 8v4l-2-2"/><path d="M16 8v4l2-2"/><path d="M8 5l-2 2"/><path d="M16 5l2 2"/></svg>',
        ),

        'shatterproof_flexible' => array(
            'label' => __( 'Shatterproof & Flexible', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21l6-6"/><path d="M9 15l4-4 6 6"/><path d="M14 5l6 6"/></svg>',
        ),

        'fast_easy_installation' => array(
            'label' => __( 'Fast & Easy Installation', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h6"/><path d="M15 12h6"/><path d="M12 3v6"/><path d="M12 15v6"/><path d="M8 8l8 8"/></svg>',
        ),

        'crystal_clear_clarity' => array(
            'label' => __( 'Crystal Clear Clarity', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="4"/><line x1="15.5" y1="15.5" x2="20" y2="20"/></svg>',
        ),

        'long_lasting_performance' => array(
            'label' => __( 'Long-Lasting Performance', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10"/><path d="M12 6v6l4 2"/></svg>',
        ),

        'lightweight_strong' => array(
            'label' => __( 'Lightweight & Strong', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7c0 1.5-1 3-2.5 3.5-1.5.5-3 0-4 1-1 1-3 1.5-4 1.5"/><path d="M3 21l4-4"/></svg>',
        ),

        'easy_to_cut_shape' => array(
            'label' => __( 'Easy to Cut & Shape', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="7" r="2"/><circle cx="6" cy="17" r="2"/><path d="M8 8l12 8"/><path d="M8 16l12-8"/></svg>',
        ),

        'fire_retardant_rated' => array(
            'label' => __( 'Fire-Retardant Rated', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3s3 3 3 6-3 4-3 8c-2-2-4-4-4-6 0-3 3-5 4-8z"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        ),

        'zero_maintenance' => array(
            'label' => __( 'Zero Maintenance', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><path d="M9 12l2 2 4-4"/></svg>',
        ),

        'thermal_insulation' => array(
            'label' => __( 'Thermal Insulation', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v11"/><path d="M9 14a3 3 0 1 0 6 0c0-1.5-1.5-3-3-3"/></svg>',
        ),

    ) );
}

/**
 * Build the feature box HTML for a given product.
 *
 * @param int $product_id
 * @return string HTML or empty string.
 */
function nh_get_feature_box_html( $product_id = 0 ) {
    if ( ! $product_id ) {
        $product_id = get_the_ID();
    }

    $raw = get_post_meta( $product_id, '_nhf_feature_ids', true );

    // Backward compatibility: old plugin stored an array of post IDs.
    // New format is a comma-separated string of feature keys.
    if ( empty( $raw ) || is_array( $raw ) ) {
        return '';
    }

    $keys     = array_map( 'trim', preg_split( '/[\r\n,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY ) );
    $keys     = array_filter( $keys );
    $all      = nh_get_features();
    $selected = array();

    foreach ( $keys as $key ) {
        if ( isset( $all[ $key ] ) ) {
            $selected[ $key ] = $all[ $key ];
        }
    }

    if ( empty( $selected ) ) {
        return '';
    }

    ob_start();
    ?>
    <section class="nhf-box nhf--summary-card" aria-label="<?php esc_attr_e( 'Key product features', 'nh-theme' ); ?>">
        <ul class="nhf-list" role="list">
            <?php foreach ( $selected as $key => $feature ) : ?>
                <li class="nhf-item">
                    <span class="nhf-icon" aria-hidden="true">
                        <?php echo $feature['icon']; ?>
                    </span>
                    <span class="nhf-label"><?php echo esc_html( $feature['label'] ); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php
    return ob_get_clean();
}
