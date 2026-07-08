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
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2s6 5.5 6 9a6 6 0 1 1-12 0c0-3.5 6-9 6-9z"/><path d="M9 14s1-2 3-2 3 2 3 2"/></svg>',
        ),

        'swedish_pine_frame' => array(
            'label' => __( 'Swedish Pine Frame', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M5 10l7-6 7 6"/><path d="M7 14l5-4 5 4"/></svg>',
        ),

        'premium_swedish_coating' => array(
            'label' => __( 'Premium Swedish Coating', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h20"/><path d="M12 2v4"/><path d="M8 8l8 0"/><path d="M6 18l12 0"/><path d="M10 14l4 0"/></svg>',
        ),

        'weather_storm_proof' => array(
            'label' => __( 'Weather & Storm Proof', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 16.58A5 5 0 0 0 18 8h-1.3"/><path d="M16 13l-3 3"/><path d="M13 8l-1 3"/></svg>',
        ),

        'scratch_rust_resistant' => array(
            'label' => __( 'Scratch & Rust Resistant', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l4 4-4 4-4-4 4-4z"/><path d="M8 14l2 2 4-4"/></svg>',
        ),

        'made_in_eu' => array(
            'label' => __( 'Made in the EU', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><path d="M12 6v6l3 3"/></svg>',
        ),

        'built_in_uv_protection' => array(
            'label' => __( 'Built-in UV Protection', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M4.93 4.93l1.41 1.41"/><path d="M17.66 17.66l1.41 1.41"/></svg>',
        ),

        'shatterproof_flexible' => array(
            'label' => __( 'Shatterproof & Flexible', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 9c2 1 4 1 6 0 2-1 4-1 6 0"/></svg>',
        ),

        'fast_easy_installation' => array(
            'label' => __( 'Fast & Easy Installation', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-8z"/></svg>',
        ),

        'crystal_clear_clarity' => array(
            'label' => __( 'Crystal Clear Clarity', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v2"/><path d="M12 20v2"/></svg>',
        ),

        'long_lasting_performance' => array(
            'label' => __( 'Long-Lasting Performance', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 3"/></svg>',
        ),

        'lightweight_strong' => array(
            'label' => __( 'Lightweight & Strong', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h16"/><path d="M4 7h10"/><path d="M4 17h10"/></svg>',
        ),

        'easy_to_cut_shape' => array(
            'label' => __( 'Easy to Cut & Shape', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l8.5 8.5"/><path d="M21 7l-8.5 8.5"/><circle cx="6" cy="6" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
        ),

        'fire_retardant_rated' => array(
            'label' => __( 'Fire-Retardant Rated', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 14s1.5-1 2-2c.5-1 0-2 1-3 1-1 2 0 2 0s1 1 1 2-1 3-1 3"/><path d="M12 22v-2"/></svg>',
        ),

        'zero_maintenance' => array(
            'label' => __( 'Zero Maintenance', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><path d="M9 12l2 2 4-4"/></svg>',
        ),

        'thermal_insulation' => array(
            'label' => __( 'Thermal Insulation', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v10"/><path d="M7 22a5 5 0 0 1 10 0"/></svg>',
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
