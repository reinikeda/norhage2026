<?php
/**
 * NH Important Notes - Definitions & Renderer
 *
 * Text domain: nh-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * All available important notes.
 *
 * Keys are stable IDs (never change). Values are title + text.
 *
 * @return array
 */
function nh_get_important_notes() {
    return apply_filters(
        'nh_important_notes',
        array(

            'color_variations' => array(
                'title' => __( 'Color Variations', 'nh-theme' ),
                'text'  => __( 'Color shades of multiwall sheets can vary by up to 8% between different production batches. We recommend ordering all sheets for a single project at the same time to ensure a consistent color.', 'nh-theme' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20h.01"/><path d="M7 20v-4"/><path d="M12 20v-8"/><path d="M17 20V8"/><path d="M22 4v16"/></svg>',
            ),

            'internal_structure' => array(
                'title' => __( 'Internal Structure', 'nh-theme' ),
                'text'  => __( 'The internal design (channels shape) of the sheets may vary based on stock levels, but the technical specs are identical. We never mix different structures in the same order to ensure a uniform look.', 'nh-theme' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/><path d="M9 3v18"/></svg>',
            ),

            'warranty_30_year' => array(
                'title' => __( '30-Year Warranty', 'nh-theme' ),
                'text'  => __( 'Includes a 30-year warranty against yellowing, ensuring high light transmission throughout this period.', 'nh-theme' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
            ),

            'warranty_15_year' => array(
                'title' => __( '15-Year Warranty', 'nh-theme' ),
                'text'  => __( 'Includes a 15-year warranty against yellowing, ensuring high light transmission throughout this period.', 'nh-theme' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
            ),

            'warranty_10_year' => array(
                'title' => __( '10-Year Warranty', 'nh-theme' ),
                'text'  => __( 'Covers discoloration caused by weather, as well as loss of impact resistance and light transmission.', 'nh-theme' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
            ),

            'standard_width' => array(
                'title' => __( 'Standard Width', 'nh-theme' ),
                'text'  => __( 'The standard sheet width is 2.1 m. If a sheet is cut down to 1.05 m, one edge will remain open due to the internal channel structure.', 'nh-theme' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 6H3"/><path d="M21 18H3"/><path d="M3 6v12"/><path d="M21 6v12"/><path d="M7 10v4"/><path d="M12 9v6"/><path d="M17 10v4"/></svg>',
            ),

            'uv_protection' => array(
                'title' => __( 'UV Protection & Installation', 'nh-theme' ),
                'text'  => __( 'Polycarbonate sheets must be installed with the UV-protected side facing up (facing the outside) for the protection to work. The UV side is clearly marked on the protective film.', 'nh-theme' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>',
            ),

            'cutting_tolerance' => array(
                'title' => __( 'Cutting Tolerance', 'nh-theme' ),
                'text'  => __( 'The maximum allowable cutting deviation on the edges is 4 mm.', 'nh-theme' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg>',
            ),

            'handle_with_care' => array(
                'title' => __( 'Handle with Care', 'nh-theme' ),
                'text'  => __( 'Handle the sheets carefully to prevent scratches and damage.', 'nh-theme' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 11V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v0"/><path d="M14 10V4a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v2"/><path d="M10 10.5V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v8"/><path d="M18 11a2 2 0 1 1 4 0v3a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/></svg>',
            ),

            'storage' => array(
                'title' => __( 'Storage', 'nh-theme' ),
                'text'  => __( 'Store in a cool, dry place away from direct sunlight.', 'nh-theme' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>',
            ),

            'winter_care' => array(
                'title' => __( 'Winter Care', 'nh-theme' ),
                'text'  => __( 'If using automatic window openers (e.g., in greenhouses), remove the cylinder when temperatures drop below 0°C. Clean, lightly lubricate, and store it in a warm, dry place to prevent frost damage and ensure it works next season.', 'nh-theme' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="2" y1="12" x2="22" y2="12"/><line x1="12" y1="2" x2="12" y2="22"/><path d="m20 16-4-4 4-4"/><path d="m4 8 4 4-4 4"/><path d="m16 4-4 4-4-4"/><path d="m8 20 4-4 4 4"/></svg>',
            ),

            'sizing_installation' => array(
                'title' => __( 'Sizing & Installation', 'nh-theme' ),
                'text'  => __( 'Polycarbonate sheets may be delivered in standard, uncut sizes. Because final greenhouse dimensions can vary slightly after assembly, we provide full-size sheets to guarantee a perfect fit. Some extra cutting or trimming may be required during installation.', 'nh-theme' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20h.01"/><path d="M7 20v-4"/><path d="M12 20v-8"/><path d="M17 20V8"/><path d="M22 4v16"/></svg>',
            ),

        )
    );
}

/**
 * Get a single note by key.
 *
 * @param string $key Note key.
 * @return array|null
 */
function nh_get_important_note( $key ) {
    $notes = nh_get_important_notes();
    return isset( $notes[ $key ] ) ? $notes[ $key ] : null;
}

/**
 * Build the HTML output for a product's important notes.
 *
 * @param int $product_id Product ID.
 * @return string HTML or empty string.
 */
function nh_get_important_notes_html( $product_id = 0 ) {
    if ( ! $product_id ) {
        $product_id = get_the_ID();
    }

    $raw = get_post_meta( $product_id, '_nh_important_notes', true );
    if ( empty( $raw ) ) {
        return '';
    }

    // Support comma-separated or line-separated values.
    $keys = array_map( 'trim', preg_split( '/[\r\n,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY ) );
    $keys = array_filter( $keys );

    if ( empty( $keys ) ) {
        return '';
    }

    $notes = nh_get_important_notes();
    $found = false;

    ob_start();
    ?>
    <div class="nh-important-notes">
        <div class="nh-in-head">
            <span class="nh-in-head-ico">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block;width:100%;height:100%;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
            </span>
            <h2 class="nh-in-title"><?php echo esc_html__( 'Important information', 'nh-theme' ); ?></h2>
        </div>
        <ul class="nh-in-list">
            <?php foreach ( $keys as $key ) : ?>
                <?php
                if ( ! isset( $notes[ $key ] ) ) {
                    continue;
                }
                $found = true;
                $note = $notes[ $key ];
                ?>
                <li class="nh-in-item">
                    <div class="nh-in-ico-wrap">
                        <span class="nh-in-ico">
                            <?php echo $notes[ $key ]['icon']; ?>
                        </span>
                    </div>
                    <div class="nh-in-txt">
                        <strong><?php echo esc_html( $note['title'] ); ?></strong> 
                        <?php echo esc_html( $note['text'] ); ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    $html = ob_get_clean();

    return $found ? $html : '';
}
