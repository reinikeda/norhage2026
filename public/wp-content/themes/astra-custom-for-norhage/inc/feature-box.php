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

        // Lucide: droplets
        'anti_drip' => array(
            'label' => __( 'Anti-Drip Tech', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 16.3c2.2 0 4-1.83 4-4.05 0-1.16-.57-2.26-1.71-3.19S7.29 6.75 7 5.3c-.29 1.45-1.14 2.84-2.29 3.76S3 11.1 3 12.25c0 2.22 1.8 4.05 4 4.05z"/><path d="M12.56 6.6A10.97 10.97 0 0 0 14 3.02c.5 2.5 2 4.9 4 6.5s3 3.5 3 5.5a6.98 6.98 0 0 1-11.91 4.97"/></svg>',
        ),

        // Lucide: trees
        'swedish_pine_frame' => array(
            'label' => __( 'Swedish Pine Frame', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 10v.2A3 3 0 0 1 8.9 16H5a3 3 0 0 1-1-5.8V10a3 3 0 0 1 6 0Z"/><path d="M7 16v6"/><path d="M13 19v3"/><path d="M12 19h8.3a1 1 0 0 0 .7-1.7L18 14h.3a1 1 0 0 0 .7-1.7L16 9h.2a1 1 0 0 0 .8-1.7L13 3l-1.4 1.5"/></svg>',
        ),

        // Lucide: paintbrush-2
        'premium_swedish_coating' => array(
            'label' => __( 'Premium Swedish Coating', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a1 1 0 0 1-1-1v-3H7a2 2 0 0 1-2-2v-3H3a1 1 0 0 1-.7-1.7l9-9a1 1 0 0 1 1.4 0l9 9A1 1 0 0 1 21 13h-2v3a2 2 0 0 1-2 2h-4v3a1 1 0 0 1-1 1Z"/></svg>',
        ),

        // Lucide: cloud-lightning
        'weather_storm_proof' => array(
            'label' => __( 'Weather & Storm Proof', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 16.326A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 .5 8.973"/><path d="m13 12-3 5h4l-3 5"/></svg>',
        ),

        // Lucide: shield-check
        'scratch_rust_resistant' => array(
            'label' => __( 'Scratch & Rust Resistant', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>',
        ),

        // Lucide: globe
        'made_in_eu' => array(
            'label' => __( 'Made in the EU', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>',
        ),

        // Lucide: sun
        'built_in_uv_protection' => array(
            'label' => __( 'Built-in UV Protection', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>',
        ),

        // Lucide: layers
        'shatterproof_flexible' => array(
            'label' => __( 'Shatterproof & Flexible', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>',
        ),

        // Lucide: zap
        'fast_easy_installation' => array(
            'label' => __( 'Fast & Easy Installation', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>',
        ),

        // Lucide: search
        'crystal_clear_clarity' => array(
            'label' => __( 'Crystal Clear Clarity', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
        ),

        // Lucide: clock
        'long_lasting_performance' => array(
            'label' => __( 'Long-Lasting Performance', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        ),

        // Lucide: feather
        'lightweight_strong' => array(
            'label' => __( 'Lightweight & Strong', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.67 19a2 2 0 0 0 1.416-.588l6.154-6.172a6 6 0 0 0-8.49-8.49L5.586 9.914A2 2 0 0 0 5 11.328V18a1 1 0 0 0 1 1z"/><path d="M16 8 2 22"/><path d="M17.5 15H9"/></svg>',
        ),

        // Lucide: scissors
        'easy_to_cut_shape' => array(
            'label' => __( 'Easy to Cut & Shape', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><path d="M8.12 8.12 12 12"/><path d="M20 4 8.12 15.88"/><circle cx="6" cy="18" r="3"/><path d="M14.8 14.8 20 20"/></svg>',
        ),

        // Lucide: flame
        'fire_retardant_rated' => array(
            'label' => __( 'Fire-Retardant Rated', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>',
        ),

        // Lucide: circle-check
        'zero_maintenance' => array(
            'label' => __( 'Zero Maintenance', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>',
        ),

        // Lucide: thermometer
        'thermal_insulation' => array(
            'label' => __( 'Thermal Insulation', 'nh-theme' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></svg>',
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

    $count     = count( $selected );
    $collapse  = $count > 6;
    $box_class = 'nhf-box nhf--summary-card' . ( $collapse ? ' nhf--collapsible' : '' );

    ob_start();
    ?>
    <section class="<?php echo esc_attr( $box_class ); ?>" aria-label="<?php esc_attr_e( 'Key product features', 'nh-theme' ); ?>">
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
        <?php if ( $collapse ) : ?>
            <button type="button" class="nhf-more" data-nhf-more aria-expanded="false">
                <span class="nhf-more__open"><?php esc_html_e( 'Show more', 'nh-theme' ); ?></span>
                <span class="nhf-more__close"><?php esc_html_e( 'Show less', 'nh-theme' ); ?></span>
            </button>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}
