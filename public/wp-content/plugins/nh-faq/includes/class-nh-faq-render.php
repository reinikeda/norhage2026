<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NH_FAQ_Render {

    public function __construct() {
        add_shortcode( 'nh_faq', [$this,'shortcode'] );
    }

    /**
     * Shortcode:
     * [nh_faq
     *   scope="global|product"
     *   product_id="current|123"
     *   topics="slug,slug"      // (global) if omitted -> any topic
     *   limit="all|5"
     *   accordion="1|0"         // used for product scope only
     *   schema="1|0"
     * ]
     */
    public function shortcode( $atts ) {
        $a = shortcode_atts([
            'scope'      => 'global',
            'product_id' => 'current',
            'topics'     => '',
            'limit'      => 'all',
            'accordion'  => '1',   // for product scope
            'schema'     => '1',
        ], $atts, 'nh_faq' );

        $faqs = $this->get_faqs( $a );
        if ( empty( $faqs ) ) return '';

        // Front assets
        wp_enqueue_style( 'nh-faq' );
        wp_enqueue_script( 'nh-faq' );

        if ( $a['scope'] === 'global' ) {
            $html = $this->render_global_h2_h3( $faqs );  // Topic H2, Question H3 (button) + accordion
        } else {
            $html = $this->render_product_h3_accordion( $faqs ); // Product: H3 (button) + accordion
        }

        if ( $a['schema'] === '1' ) {
            $this->print_schema( $faqs );
        }
        return $html;
    }

    /** Helper for Woo integration to fetch product FAQs. */
    public static function get_product_faqs( $product_id ) {
        $product_id = absint($product_id);
        if ( ! $product_id ) return [];

        return get_posts([
            'post_type'   => 'nh_faq',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query'  => [[
                'key'     => 'nh_products',
                'value'   => 'i:' . $product_id . ';', // match serialized int in stored array
                'compare' => 'LIKE',
            ]],
            'orderby'     => 'menu_order title',
            'order'       => 'ASC',
        ]);
    }

    /**
     * Fetch FAQs:
     * - scope=global => must have a topic (optionally filter by specific topics)
     * - scope=product => product ID listed in nh_products (serialized array)
     */
    private function get_faqs( $a ) {
        $args = [
            'post_type'   => 'nh_faq',
            'post_status' => 'publish',
            'orderby'     => 'menu_order title',
            'order'       => 'ASC',
            'numberposts' => ($a['limit']==='all') ? -1 : max(1, absint($a['limit'])),
        ];

        if ( $a['scope'] === 'global' ) {
            if ( ! empty($a['topics']) ) {
                $slugs = array_filter(array_map('sanitize_title', explode(',', $a['topics'])));
                $args['tax_query'] = [[
                    'taxonomy' => 'nh_faq_topic',
                    'field'    => 'slug',
                    'terms'    => $slugs,
                ]];
            } else {
                $args['tax_query'] = [[
                    'taxonomy' => 'nh_faq_topic',
                    'operator' => 'EXISTS',
                ]];
            }
        } else {
            $pid = ($a['product_id']==='current') ? get_the_ID() : absint($a['product_id']);
            if ( ! $pid ) return [];
            $args['meta_query'] = [[
                'key'     => 'nh_products',
                'value'   => 'i:' . $pid . ';', // match serialized int in stored array
                'compare' => 'LIKE',
            ]];
        }

        return get_posts( $args );
    }

    /* =========================
       RENDERERS
       ========================= */

    /**
     * GLOBAL: Topic cards grid. Each card has:
     * H2 topic heading, per-topic Expand/Collapse, and H3 question (button) + accordion answer.
     */
    private function render_global_h2_h3( $faqs ) {
        // Group by primary topic (nh_order then A–Z)
        $groups = []; // term_id => ['label','slug','order','faqs'=>[]]
        foreach ( $faqs as $f ) {
            $terms = get_the_terms( $f, 'nh_faq_topic' );
            if ( empty($terms) || is_wp_error($terms) ) continue;

            $primary = $this->nh_pick_primary_topic( $terms );
            if ( ! $primary ) continue;

            $tid = $primary->term_id;
            if ( ! isset($groups[$tid]) ) {
                $groups[$tid] = [
                    'label' => $primary->name,
                    'slug'  => $primary->slug,
                    'order' => $this->nh_topic_order_value( $primary ),
                    'faqs'  => [],
                ];
            }
            $groups[$tid]['faqs'][] = $f;
        }

        // Sort topic cards by custom order then label
        uasort($groups, function($a,$b){
            $oa = (int)$a['order']; $ob = (int)$b['order'];
            if ( $oa !== $ob ) return $oa - $ob;
            return strcasecmp($a['label'], $b['label']);
        });

        $id_base = 'nh-faq-' . wp_generate_uuid4();

        ob_start();

        // Top index (jump links)
        echo '<nav class="nh-faq-index">';
        foreach ( $groups as $g ) {
            $anchor = 'faq-topic-' . sanitize_title($g['slug']);
            printf('<a href="#%s">%s</a>', esc_attr($anchor), esc_html($g['label']));
        }
        echo '</nav>';

        // Cards grid
        echo '<div class="nh-faq nh-faq--global nh-faq-grid" data-accordion="multi">';

        foreach ( $groups as $g ) {
            $anchor = 'faq-topic-' . sanitize_title($g['slug']);
            echo '<section class="nh-faq-topic-card" id="'.esc_attr($anchor).'" aria-labelledby="'.esc_attr($anchor).'-label">';

            echo '<div class="nh-faq-card-head">';
            echo '<h2 class="nh-faq-topic-heading" id="'.esc_attr($anchor).'-label">'. esc_html($g['label']) .'</h2>';
            echo '<div class="nh-faq-toolbar">';
            echo '  <button type="button" class="nh-faq-tool" data-nh="expand-all">'. esc_html__( 'Expand all', 'nh-faq' ) .'</button>';
            echo '  <button type="button" class="nh-faq-tool" data-nh="collapse-all">'. esc_html__( 'Collapse all', 'nh-faq' ) .'</button>';
            echo '</div>';
            echo '</div>';

            echo '<div class="nh-faq-group" role="list">';

            foreach ( $g['faqs'] as $f ) {
                $qid    = $id_base . '-' . $f->ID;
                $title  = get_the_title($f);
                $answer = apply_filters('the_content', $f->post_content);

                echo '<div class="nh-faq-item" id="'.esc_attr($qid).'" role="listitem">';

                echo '<h3 class="nh-faq-q-h3">';
                echo   '<button class="nh-faq-h3btn" id="'.esc_attr($qid).'-btn" aria-expanded="false" aria-controls="'.esc_attr($qid).'-a">'
                    . esc_html($title)
                    . '</button>';
                echo '</h3>';

                echo '<div id="'.esc_attr($qid).'-a" class="nh-faq-a" role="region" aria-labelledby="'.esc_attr($qid).'-btn" hidden>';
                echo   $answer;
                echo '</div>';

                echo '</div>';
            }

            echo '</div>'; // .nh-faq-group
            echo '</section>'; // card
        }

        echo '</div>'; // grid
        return ob_get_clean();
    }


    /**
     * PRODUCT TAB: Question <h3> (button) with collapsible answer.
     */
    private function render_product_h3_accordion( $faqs ) {
        $id_base = 'nh-faq-' . wp_generate_uuid4();
        ob_start(); ?>
        <div class="nh-faq" role="list" data-accordion="multi">
            <?php foreach ( $faqs as $f ):
                $qid    = $id_base . '-' . $f->ID;
                $title  = get_the_title($f);
                $answer = apply_filters('the_content', $f->post_content);
            ?>
            <div class="nh-faq-item" id="<?php echo esc_attr( $qid ); ?>" role="listitem">
                <h3 class="nh-faq-q-h3">
                    <button class="nh-faq-h3btn" id="<?php echo esc_attr($qid); ?>-btn"
                        aria-expanded="false"
                        aria-controls="<?php echo esc_attr($qid); ?>-a">
                        <?php echo esc_html( $title ); ?>
                    </button>
                </h3>
                <div id="<?php echo esc_attr($qid); ?>-a" class="nh-faq-a" role="region"
                    aria-labelledby="<?php echo esc_attr($qid); ?>-btn" hidden>
                    <?php echo $answer; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* =========================
       HELPERS
       ========================= */

    /** Choose the primary topic for an FAQ (lowest nh_order, then A–Z). */
    private function nh_pick_primary_topic( $terms ) {
        if ( empty($terms) || is_wp_error($terms) ) return null;

        usort($terms, function($a,$b){
            $oa = get_term_meta($a->term_id, 'nh_order', true);
            $ob = get_term_meta($b->term_id, 'nh_order', true);
            $oa = ($oa === '' ? PHP_INT_MAX : (int)$oa);
            $ob = ($ob === '' ? PHP_INT_MAX : (int)$ob);
            if ( $oa !== $ob ) return $oa - $ob;
            return strcasecmp($a->name, $b->name);
        });

        $primary = $terms[0];
        return apply_filters('nh_faq_primary_topic', $primary, $terms);
    }

    /** Return integer order for a topic (nh_order meta if present, else 0). */
    private function nh_topic_order_value( $term ) {
        $v = get_term_meta( $term->term_id, 'nh_order', true );
        return ($v === '' ? 0 : (int)$v);
    }

    /* =========================
       SCHEMA
       ========================= */

    /** Print FAQPage JSON-LD when there are at least 2 Q&As. */
    public function print_schema( $faqs ) {
        if ( count( $faqs ) < 2 ) return;

        $entities = [];
        foreach ( $faqs as $f ) {
            $entities[] = [
                '@type' => 'Question',
                'name'  => wp_strip_all_tags( get_the_title($f) ),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_kses_post( apply_filters('the_content', $f->post_content ) ),
                ],
            ];
        }

        $data = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'inLanguage' => get_bloginfo('language'),
            'mainEntity' => $entities,
        ];

        printf(
            '<script type="application/ld+json">%s</script>',
            wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
        );
    }
}
