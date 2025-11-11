<?php
if (!defined('ABSPATH')) exit;

class NHHB_Admin {
    public static function init() {
        add_action('init', [__CLASS__, 'register_section_cpt']);
        add_action('admin_menu', [__CLASS__, 'add_menu']);

        add_action('add_meta_boxes', [__CLASS__, 'add_section_metabox']);
        add_action('save_post_nh_section', [__CLASS__, 'save_section']);

        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'front_assets']);
    }

    public static function add_menu() {
        add_menu_page(
            'Home Builder',
            'Home Builder',
            'edit_pages',
            'nhhb-sections',
            function () {
                wp_safe_redirect( admin_url('edit.php?post_type=nh_section') );
                exit;
            },
            'dashicons-layout',
            3
        );
    }

    public static function register_section_cpt() {
        register_post_type('nh_section', [
            'labels' => [
                'name'          => 'Sections',
                'singular_name' => 'Section',
                'menu_name'     => 'Home Builder',
                'add_new_item'  => 'Add New Section',
                'edit_item'     => 'Edit Section',
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false,
            'supports'     => ['title'],
        ]);
    }

    public static function add_section_metabox() {
        add_meta_box('nhhb_section_fields', 'Section Settings', [__CLASS__, 'render_mb'], 'nh_section', 'normal', 'high');
    }

    public static function render_mb($post) {
        wp_nonce_field('nhhb_save_section', 'nhhb_nonce');

        $type = get_post_meta($post->ID, '_nhhb_type', true) ?: 'top-offers';
        $data_raw = get_post_meta($post->ID, '_nhhb_data', true);
        $data = is_array($data_raw) ? $data_raw : [];

        // Normalize shared arrays
        $slides = isset($data['slides']) && is_array($data['slides']) ? $data['slides'] : [];
        for ($i=0; $i<3; $i++) { $slides[$i] = isset($slides[$i]) && is_array($slides[$i]) ? $slides[$i] : []; }

        $promos = isset($data['promos']) && is_array($data['promos']) ? $data['promos'] : [];
        for ($i=0; $i<2; $i++) { $promos[$i] = isset($promos[$i]) && is_array($promos[$i]) ? $promos[$i] : []; }

        $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
        for ($i=0; $i<4; $i++) { $items[$i] = isset($items[$i]) && is_array($items[$i]) ? $items[$i] : []; }

        // Browse by categories defaults
        $bc = [
            'title'      => $data['title']      ?? 'Browse by Category',
            'limit'      => isset($data['limit'])  ? (int)$data['limit']  : 12,
            'orderby'    => $data['orderby']    ?? 'name',
            'order'      => $data['order']      ?? 'ASC',
            'hide_empty' => !empty($data['hide_empty']),
        ];

        // Promo Trio defaults
        $cards = isset($data['cards']) && is_array($data['cards']) ? $data['cards'] : [];
        for ($i=0;$i<3;$i++){
            $cards[$i] = isset($cards[$i]) && is_array($cards[$i]) ? $cards[$i] : [];
        }

        // Newsletter defaults
        $nl = [
            'title'        => $data['title']        ?? 'Don’t Miss Out Latest Trends & Offers',
            'text'         => $data['text']         ?? 'Register to receive news about the latest offers & discount codes',
            'placeholder'  => $data['placeholder']  ?? 'Enter your email',
            'btn_text'     => $data['btn_text']     ?? 'Subscribe',
            'action'       => $data['action']       ?? '',
            'method'       => isset($data['method']) ? strtoupper($data['method']) : 'POST',
            'consent_text' => $data['consent_text'] ?? '',
        ];

        // Services slider defaults (CPT-based)
        $sv = [
            'title'    => $data['title']    ?? 'Our Services',
            'services' => isset($data['services']) && is_array($data['services']) ? $data['services'] : [],
        ];
        ?>
        <p><strong>Section Type</strong></p>
        <p>
            <select name="nhhb_type" id="nhhb_type">
                <option value="top-offers"   <?php selected($type, 'top-offers'); ?>>Top offers (slider + 2 promos)</option>
                <option value="top-features" <?php selected($type, 'top-features'); ?>>Top features (icons + text)</option>
                <option value="browse-cats"  <?php selected($type, 'browse-cats'); ?>>Browse by Category</option>
                <option value="new-arrivals" <?php selected($type, 'new-arrivals'); ?>>New Arrivals (latest products)</option>
                <option value="promo-trio"   <?php selected($type, 'promo-trio'); ?>>Promo Trio (1 big + 2 small)</option>
                <option value="newsletter"   <?php selected($type, 'newsletter'); ?>>Newsletter / Subscribe</option>
                <option value="services-slider" <?php selected($type, 'services-slider'); ?>>Services Slider (CPT: service)</option>
                <option value="b2b-banner" <?php selected($type, 'b2b-banner'); ?>>B2B Banner</option>
            </select>
        </p>
        <hr>

        <style>
            .nhhb-grid{display:grid;gap:16px}
            .nhhb-2{grid-template-columns:1fr 1fr}
            .nhhb-3{grid-template-columns:repeat(3,1fr)}
            .nhhb-4{grid-template-columns:repeat(4,1fr)}
            .nhhb-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px}
            .nhhb-thumb{width:100%;max-width:140px;height:100px;background:#f6f7f7;border:1px solid #dcdcde;display:flex;align-items:center;justify-content:center;border-radius:6px;overflow:hidden}
            .nhhb-thumb img{max-width:100%;max-height:100%}
            .nhhb-row{display:flex;gap:12px;align-items:flex-start}
            .nhhb-actions{display:flex;gap:8px;margin-top:6px}
            .nhhb-copywrap{display:inline-flex;align-items:center;gap:6px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:4px 8px}
            .nhhb-copywrap code{user-select:all}
            .nhhb-hidden{display:none}
            .nhhb-svc-grid{display:grid;gap:14px}
            .nhhb-svc-grid .nhhb-card{padding:12px}
            .nhhb-svc-row{display:grid;grid-template-columns:140px 1fr;gap:14px}
            .nhhb-svc-row .widefat{width:100%}
            .nhhb-svc-small{font-size:12px;color:#666}
        </style>

        <!-- TOP OFFERS -->
        <div id="nhhb_fields_top_offers" class="<?php echo $type==='top-offers' ? '' : 'nhhb-hidden'; ?>">
            <h3>Slider (max 3 slides)</h3>
            <div class="nhhb-grid nhhb-3">
                <?php for ($i=0; $i<3; $i++):
                    $s = $slides[$i]; $img_id = isset($s['img']) ? absint($s['img']) : 0;
                    $img_src = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : '';
                ?>
                <div class="nhhb-card">
                    <h4>Slide <?php echo $i+1; ?></h4>
                    <div class="nhhb-row">
                        <div>
                            <div class="nhhb-thumb" id="slide_thumb_<?php echo $i; ?>">
                                <?php echo $img_src ? '<img src="'.esc_url($img_src).'" alt=""/>' : 'No image'; ?>
                            </div>
                            <div class="nhhb-actions">
                                <button type="button" class="button nhhb-upload" data-target="slide_<?php echo $i; ?>">Browse</button>
                                <button type="button" class="button-link-delete nhhb-remove" data-target="slide_<?php echo $i; ?>">Remove</button>
                            </div>
                            <input type="hidden" name="data[slides][<?php echo $i; ?>][img]" id="slide_<?php echo $i; ?>" value="<?php echo esc_attr($img_id); ?>">
                        </div>
                        <div style="flex:1">
                            <p><label>H1<br><input type="text" class="widefat" name="data[slides][<?php echo $i; ?>][h1]" value="<?php echo esc_attr($s['h1'] ?? ''); ?>"></label></p>
                            <p><label>H2<br><input type="text" class="widefat" name="data[slides][<?php echo $i; ?>][h2]" value="<?php echo esc_attr($s['h2'] ?? ''); ?>"></label></p>
                            <p><label>H3<br><input type="text" class="widefat" name="data[slides][<?php echo $i; ?>][h3]" value="<?php echo esc_attr($s['h3'] ?? ''); ?>"></label></p>
                            <p class="nhhb-2">
                                <label>Button Text
                                    <input type="text" class="widefat" name="data[slides][<?php echo $i; ?>][btn_text]" value="<?php echo esc_attr($s['btn_text'] ?? ''); ?>">
                                </label>
                                <label>Button URL
                                    <input type="url"  class="widefat" name="data[slides][<?php echo $i; ?>][btn_url]" value="<?php echo esc_attr($s['btn_url'] ?? ''); ?>">
                                </label>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <hr>
            <h3>Right side promos (2 cards)</h3>
            <div class="nhhb-grid nhhb-2">
                <?php for ($i=0; $i<2; $i++):
                    $p = $promos[$i]; $img_id = isset($p['img']) ? absint($p['img']) : 0;
                    $img_src = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : '';
                ?>
                <div class="nhhb-card">
                    <h4>Promo <?php echo $i+1; ?></h4>
                    <div class="nhhb-row">
                        <div>
                            <div class="nhhb-thumb" id="promo_thumb_<?php echo $i; ?>">
                                <?php echo $img_src ? '<img src="'.esc_url($img_src).'" alt=""/>' : 'No image'; ?>
                            </div>
                            <div class="nhhb-actions">
                                <button type="button" class="button nhhb-upload" data-target="promo_<?php echo $i; ?>">Browse</button>
                                <button type="button" class="button-link-delete nhhb-remove" data-target="promo_<?php echo $i; ?>">Remove</button>
                            </div>
                            <input type="hidden" name="data[promos][<?php echo $i; ?>][img]" id="promo_<?php echo $i; ?>" value="<?php echo esc_attr($img_id); ?>">
                        </div>
                        <div style="flex:1">
                            <p><label>H1 (title, clickable)<br><input type="text" class="widefat" name="data[promos][<?php echo $i; ?>][h1]" value="<?php echo esc_attr($p['h1'] ?? ''); ?>"></label></p>
                            <p><label>H3 (subtext)<br><input type="text" class="widefat" name="data[promos][<?php echo $i; ?>][h3]" value="<?php echo esc_attr($p['h3'] ?? ''); ?>"></label></p>
                            <p><label>URL (applies to H1)<br><input type="url" class="widefat" name="data[promos][<?php echo $i; ?>][btn_url]" value="<?php echo esc_attr($p['btn_url'] ?? ''); ?>"></label></p>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- TOP FEATURES -->
        <div id="nhhb_fields_top_features" class="<?php echo $type==='top-features' ? '' : 'nhhb-hidden'; ?>">
            <h3>Top features (max 4)</h3>
            <div class="nhhb-grid nhhb-4">
                <?php for ($i=0; $i<4; $i++):
                    $it = $items[$i];
                    $icon_id = isset($it['icon']) ? absint($it['icon']) : 0;
                    $icon_src = $icon_id ? wp_get_attachment_image_url($icon_id, 'thumbnail') : '';
                ?>
                <div class="nhhb-card">
                    <h4>Feature <?php echo $i+1; ?></h4>
                    <div class="nhhb-row">
                        <div>
                            <div class="nhhb-thumb" id="feat_thumb_<?php echo $i; ?>">
                                <?php echo $icon_src ? '<img src="'.esc_url($icon_src).'" alt=""/>' : 'No icon'; ?>
                            </div>
                            <div class="nhhb-actions">
                                <button type="button" class="button nhhb-upload" data-target="feat_<?php echo $i; ?>">Browse</button>
                                <button type="button" class="button-link-delete nhhb-remove" data-target="feat_<?php echo $i; ?>">Remove</button>
                            </div>
                            <input type="hidden" name="data[items][<?php echo $i; ?>][icon]" id="feat_<?php echo $i; ?>" value="<?php echo esc_attr($icon_id); ?>">
                        </div>
                        <div style="flex:1">
                            <p><label>Headline (H3)<br><input type="text" class="widefat" name="data[items][<?php echo $i; ?>][title]" value="<?php echo esc_attr($it['title'] ?? ''); ?>"></label></p>
                            <p><label>Subtext<br><input type="text" class="widefat" name="data[items][<?php echo $i; ?>][text]" value="<?php echo esc_attr($it['text'] ?? ''); ?>"></label></p>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- BROWSE CATS -->
        <div id="nhhb_fields_browse_cats" class="<?php echo $type==='browse-cats' ? '' : 'nhhb-hidden'; ?>">
            <h3>Browse by Category</h3>

            <p><label>Section Title<br>
                <input type="text" class="widefat" name="data[title]" value="<?php echo esc_attr($bc['title']); ?>">
            </label></p>

            <div class="nhhb-grid nhhb-2">
                <p><label>Max items<br>
                    <input type="number" min="1" class="widefat" name="data[limit]" value="<?php echo (int)$bc['limit']; ?>">
                </label></p>

                <p><label>Order by<br>
                    <select name="data[orderby]" class="widefat">
                        <?php foreach (['name'=>'Name','slug'=>'Slug','count'=>'Count','term_id'=>'ID'] as $k=>$lbl): ?>
                            <option value="<?php echo esc_attr($k); ?>" <?php selected($bc['orderby'],$k); ?>><?php echo esc_html($lbl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label></p>
            </div>

            <div class="nhhb-grid nhhb-2">
                <p><label>Order<br>
                    <select name="data[order]" class="widefat">
                        <option value="ASC"  <?php selected($bc['order'],'ASC'); ?>>ASC</option>
                        <option value="DESC" <?php selected($bc['order'],'DESC'); ?>>DESC</option>
                    </select>
                </label></p>

                <p style="margin-top:26px;">
                    <label><input type="checkbox" name="data[hide_empty]" value="1" <?php checked($bc['hide_empty']); ?>>
                        Hide empty categories
                    </label>
                </p>
            </div>
        </div>

        <!-- PROMO TRIO -->
        <div id="nhhb_fields_promo_trio" class="<?php echo $type==='promo-trio' ? '' : 'nhhb-hidden'; ?>">
            <h3>Promo Trio (1 big + 2 small)</h3>

            <div class="nhhb-card">
                <h4>Hero (Full width)</h4>
                <?php $c = $cards[0]; $img_id = isset($c['img']) ? absint($c['img']) : 0; $img_src = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : ''; ?>
                <div class="nhhb-row">
                    <div>
                        <div class="nhhb-thumb" id="ptr_thumb_0">
                            <?php echo $img_src ? '<img src="'.esc_url($img_src).'" alt=""/>' : 'No image'; ?>
                        </div>
                        <div class="nhhb-actions">
                            <button type="button" class="button nhhb-upload" data-target="ptr_0">Browse</button>
                            <button type="button" class="button-link-delete nhhb-remove" data-target="ptr_0">Remove</button>
                        </div>
                        <input type="hidden" name="data[cards][0][img]" id="ptr_0" value="<?php echo esc_attr($img_id); ?>">
                    </div>
                    <div style="flex:1">
                        <p><label>H3 (small line)<br><input type="text" class="widefat" name="data[cards][0][h3]" value="<?php echo esc_attr($c['h3'] ?? ''); ?>"></label></p>
                        <p><label>H2 (headline)<br><input type="text" class="widefat" name="data[cards][0][h2]" value="<?php echo esc_attr($c['h2'] ?? ''); ?>"></label></p>
                        <p><label>Paragraph<br><textarea class="widefat" name="data[cards][0][p]" rows="2"><?php echo esc_textarea($c['p'] ?? ''); ?></textarea></label></p>
                        <p class="nhhb-2">
                            <label>Button Text
                                <input type="text" class="widefat" name="data[cards][0][btn_text]" value="<?php echo esc_attr($c['btn_text'] ?? ''); ?>">
                            </label>
                            <label>Button URL
                                <input type="url" class="widefat" name="data[cards][0][btn_url]" value="<?php echo esc_attr($c['btn_url'] ?? ''); ?>">
                            </label>
                        </p>
                    </div>
                </div>
            </div>

            <div class="nhhb-grid nhhb-2" style="margin-top:14px;">
                <?php for ($i=1;$i<=2;$i++): $c = $cards[$i]; $img_id = isset($c['img']) ? absint($c['img']) : 0; $img_src = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : ''; ?>
                <div class="nhhb-card">
                    <h4>Promo <?php echo $i===1 ? 'A' : 'B'; ?></h4>
                    <div class="nhhb-row">
                        <div>
                            <div class="nhhb-thumb" id="ptr_thumb_<?php echo $i; ?>">
                                <?php echo $img_src ? '<img src="'.esc_url($img_src).'" alt=""/>' : 'No image'; ?>
                            </div>
                            <div class="nhhb-actions">
                                <button type="button" class="button nhhb-upload" data-target="ptr_<?php echo $i; ?>">Browse</button>
                                <button type="button" class="button-link-delete nhhb-remove" data-target="ptr_<?php echo $i; ?>">Remove</button>
                            </div>
                            <input type="hidden" name="data[cards][<?php echo $i; ?>][img]" id="ptr_<?php echo $i; ?>" value="<?php echo esc_attr($img_id); ?>">
                        </div>
                        <div style="flex:1">
                            <p><label>H3 (small line)<br><input type="text" class="widefat" name="data[cards][<?php echo $i; ?>][h3]" value="<?php echo esc_attr($c['h3'] ?? ''); ?>"></label></p>
                            <p><label>H2 (headline)<br><input type="text" class="widefat" name="data[cards][<?php echo $i; ?>][h2]" value="<?php echo esc_attr($c['h2'] ?? ''); ?>"></label></p>
                            <p><label>Paragraph<br><textarea class="widefat" name="data[cards][<?php echo $i; ?>][p]" rows="2"><?php echo esc_textarea($c['p'] ?? ''); ?></textarea></label></p>
                            <p class="nhhb-2">
                                <label>Button Text
                                    <input type="text" class="widefat" name="data[cards][<?php echo $i; ?>][btn_text]" value="<?php echo esc_attr($c['btn_text'] ?? ''); ?>">
                                </label>
                                <label>Button URL
                                    <input type="url" class="widefat" name="data[cards][<?php echo $i; ?>][btn_url]" value="<?php echo esc_attr($c['btn_url'] ?? ''); ?>">
                                </label>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <hr>
        <div style="margin-top:10px;">
            <em>Render with shortcode:</em>
            <?php $box_id = 'nhhb_shortcode_box_' . (int) $post->ID; ?>
            <span id="<?php echo esc_attr($box_id); ?>" class="nhhb-copywrap">
                <code>[nh_section id="<?php echo esc_html($post->ID); ?>"]</code>
                <button type="button" class="button button-small nhhb-copy-btn" data-target="<?php echo esc_attr($box_id); ?>">
                    Copy
                </button>
            </span>
        </div>

        <!-- B2B BANNER -->
        <div id="nhhb_fields_b2b_banner" class="<?php echo $type==='b2b-banner' ? '' : 'nhhb-hidden'; ?>">
            <h3>B2B Banner</h3>
            <?php
            // Resolve stored data with graceful fallbacks
            $bb = [
                'h2'       => $data['h2']       ?? 'For Business Customers',
                'h3'       => $data['h3']       ?? 'Exclusive pricing and services for B2B partners.',
                'btn_text' => $data['btn_text'] ?? 'Learn more',
                'btn_url'  => $data['btn_url']  ?? '',
                // Single-logo approach: prefer new 'logo', otherwise fallback to old fields
                'logo'     => isset($data['logo'])   ? absint($data['logo'])
                            : (isset($data['logo_d']) ? absint($data['logo_d'])
                            : (isset($data['logo_m']) ? absint($data['logo_m'])
                            : (isset($data['logo'])   ? absint($data['logo']) : 0))),
            ];
            $logo_src = $bb['logo'] ? wp_get_attachment_image_url($bb['logo'], 'medium') : '';
            ?>
            <div class="nhhb-grid nhhb-3">
                <p>
                <label>H2 (title)<br>
                    <input type="text" class="widefat" name="data[h2]" value="<?php echo esc_attr($bb['h2']); ?>">
                </label>
                </p>
                <p>
                <label>H3 (subtitle)<br>
                    <input type="text" class="widefat" name="data[h3]" value="<?php echo esc_attr($bb['h3']); ?>">
                </label>
                </p>
                <p>
                <label>Button text<br>
                    <input type="text" class="widefat" name="data[btn_text]" value="<?php echo esc_attr($bb['btn_text']); ?>">
                </label>
                </p>
            </div>

            <div class="nhhb-grid nhhb-2" style="align-items:end">
                <p>
                <label>Button URL (opens in new tab)<br>
                    <input type="url" class="widefat" name="data[btn_url]" value="<?php echo esc_attr($bb['btn_url']); ?>" placeholder="https://">
                </label>
                </p>

                <div class="nhhb-card" style="max-width:880px">
                <h4>Logo (single file)</h4>
                <div class="nhhb-row">
                    <div>
                    <div class="nhhb-thumb" id="b2b_logo_thumb_single">
                        <?php echo $logo_src ? '<img src="'.esc_url($logo_src).'" alt=""/>' : 'No logo selected'; ?>
                    </div>
                    <div class="nhhb-actions">
                        <button type="button" class="button nhhb-upload" data-target="b2b_logo_single">Browse</button>
                        <button type="button" class="button-link-delete nhhb-remove" data-target="b2b_logo_single">Remove</button>
                    </div>
                    <input type="hidden" name="data[logo]" id="b2b_logo_single" value="<?php echo esc_attr($bb['logo']); ?>">
                    </div>
                    <p class="description" style="margin:0 0 0 10px;">
                    Upload a <strong>single transparent PNG/SVG</strong> in your <em>dark blue</em> brand color.  
                    It will be shown as-is on light backgrounds and auto-converted to white in dark mode via CSS.
                    </p>
                </div>
                </div>
            </div>
        </div>

        <!-- SERVICES SLIDER (CPT-based, fully automatic) -->
        <?php
            // Saved values with sensible defaults
            $sv = is_array($sv ?? null) ? $sv : [];
            $title         = isset($sv['title'])         ? (string) $sv['title'] : '';
            $words_desktop = isset($sv['words_desktop']) ? (int) $sv['words_desktop'] : 36; // ~2–3 lines
            $words_mobile  = isset($sv['words_mobile'])  ? (int) $sv['words_mobile']  : 16; // ~1–2 lines
            ?>
            <div id="nhhb_fields_services_slider" class="<?php echo $type==='services-slider' ? '' : 'nhhb-hidden'; ?>">
            <h3>Services Slider</h3>

            <div class="nhhb-grid nhhb-3">
                <p>
                <label>Section Title<br>
                    <input type="text" class="widefat" name="data[title]" value="<?php echo esc_attr($title); ?>" placeholder="<?php esc_attr_e('Our Services','nhhb'); ?>">
                </label>
                </p>

                <p>
                <label>Desktop text length (words)<br>
                    <input type="number" min="8" max="80" step="1" class="small-text"
                        name="data[words_desktop]" value="<?php echo esc_attr($words_desktop); ?>">
                </label>
                <br><span class="description">Shown on larger screens (defaults to 36 words).</span>
                </p>

                <p>
                <label>Mobile text length (words)<br>
                    <input type="number" min="6" max="40" step="1" class="small-text"
                        name="data[words_mobile]" value="<?php echo esc_attr($words_mobile); ?>">
                </label>
                <br><span class="description">Shown on phones (defaults to 16 words).</span>
                </p>
            </div>

            <p class="description" style="margin-top:8px;">
                This slider is automatic. It pulls every published <strong>Service</strong>:
                Title → post title, Description → excerpt (or trimmed content), Image → featured image,
                Button → “Read More” linking to the service page. Ordering follows menu order / title.
            </p>

            <!-- Optional flag so the front-end knows we're in auto mode (not required, but tidy) -->
            <input type="hidden" name="data[services_mode]" value="auto">
        </div>

        <script>
        (function($){
            function toggleFields() {
                var t = $('#nhhb_type').val();
                $('#nhhb_fields_top_offers').toggleClass('nhhb-hidden', t !== 'top-offers');
                $('#nhhb_fields_top_features').toggleClass('nhhb-hidden', t !== 'top-features');
                $('#nhhb_fields_browse_cats').toggleClass('nhhb-hidden', t !== 'browse-cats');
                $('#nhhb_fields_promo_trio').toggleClass('nhhb-hidden', t !== 'promo-trio');
                $('#nhhb_fields_newsletter').toggleClass('nhhb-hidden', t !== 'newsletter');
                $('#nhhb_fields_services_slider').toggleClass('nhhb-hidden', t !== 'services-slider');
                $('#nhhb_fields_b2b_banner').toggleClass('nhhb-hidden', t !== 'b2b-banner');
            }
            $(document).on('change', '#nhhb_type', toggleFields);
            $(document).ready(function(){ toggleFields(); });

            // Media uploader
            let frame;
            $(document).on('click', '.nhhb-upload', function(e){
                e.preventDefault();
                const target = $(this).data('target');
                if (frame) frame.close();
                frame = wp.media({ title: 'Select image', button: { text: 'Use this image' }, multiple: false });
                frame.on('select', function(){
                    const at = frame.state().get('selection').first().toJSON();
                    $('#' + target).val(at.id);
                    const thumb = (at.sizes && at.sizes.medium) ? at.sizes.medium.url : at.url;

                    let tSel = '';
                    if (target.indexOf('slide_')===0) tSel = '#slide_thumb_' + target.split('_')[1];
                    else if (target.indexOf('promo_')===0) tSel = '#promo_thumb_' + target.split('_')[1];
                    else if (target.indexOf('feat_')===0)  tSel = '#feat_thumb_'  + target.split('_')[1];
                    else if (target.indexOf('ptr_')===0)   tSel = '#ptr_thumb_'   + target.split('_')[1];
                    else if (target.indexOf('svc_bg_')===0)   tSel = '#svc_bg_thumb_'   + target.split('_')[2];
                    else if (target.indexOf('svc_icon_')===0) tSel = '#svc_icon_thumb_' + target.split('_')[2];
                    else if (target === 'b2b_logo_d') tSel = '#b2b_logo_thumb_d';
                    else if (target === 'b2b_logo_m') tSel = '#b2b_logo_thumb_m';

                    if (tSel) $(tSel).html('<img src="'+thumb+'" alt="">');
                });
                frame.open();
            });
            $(document).on('click', '.nhhb-remove', function(e){
                e.preventDefault();
                const target = $(this).data('target');
                $('#' + target).val('');
                let tSel = '';
                if (target.indexOf('slide_')===0) tSel = '#slide_thumb_' + target.split('_')[1];
                else if (target.indexOf('promo_')===0) tSel = '#promo_thumb_' + target.split('_')[1];
                else if (target.indexOf('feat_')===0)  tSel = '#feat_thumb_'  + target.split('_')[1];
                else if (target.indexOf('ptr_')===0)   tSel = '#ptr_thumb_'   + target.split('_')[1];
                else if (target.indexOf('svc_bg_')===0)   tSel = '#svc_bg_thumb_'   + target.split('_')[2];
                else if (target.indexOf('svc_icon_')===0) tSel = '#svc_icon_thumb_' + target.split('_')[2];
                else if (target === 'b2b_logo') tSel = '#b2b_logo_thumb';
                if (tSel) $(tSel).text('No image');
            });

            // Copy shortcode
            $(document).on('click', '.nhhb-copy-btn', function(e){
                e.preventDefault();
                const targetId = $(this).data('target');
                const text = $('#' + targetId).find('code').text();
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = text; document.body.appendChild(ta);
                    ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
                }
                const btn = $(this); const old = btn.text();
                btn.text('Copied!'); setTimeout(()=>btn.text(old), 1500);
            });
        })(jQuery);
        </script>
        <?php
    }

    public static function save_section($post_id) {
        if (get_post_type($post_id) !== 'nh_section') return;
        if (!isset($_POST['nhhb_nonce']) || !wp_verify_nonce($_POST['nhhb_nonce'], 'nhhb_save_section')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $type = isset($_POST['nhhb_type']) ? sanitize_text_field($_POST['nhhb_type']) : 'top-offers';
        $data = (isset($_POST['data']) && is_array($_POST['data'])) ? wp_unslash($_POST['data']) : [];

        $clean = [];

        if ($type === 'top-offers') {
            $slides = [];
            if (!empty($data['slides']) && is_array($data['slides'])) {
                foreach ($data['slides'] as $s) {
                    $slides[] = [
                        'img'      => isset($s['img']) ? absint($s['img']) : 0,
                        'h1'       => sanitize_text_field($s['h1'] ?? ''),
                        'h2'       => sanitize_text_field($s['h2'] ?? ''),
                        'h3'       => sanitize_text_field($s['h3'] ?? ''),
                        'btn_text' => sanitize_text_field($s['btn_text'] ?? ''),
                        'btn_url'  => esc_url_raw($s['btn_url'] ?? ''),
                    ];
                }
            }
            $promos = [];
            if (!empty($data['promos']) && is_array($data['promos'])) {
                foreach ($data['promos'] as $p) {
                    $promos[] = [
                        'img'      => isset($p['img']) ? absint($p['img']) : 0,
                        'h1'       => sanitize_text_field($p['h1'] ?? ''),
                        'h3'       => sanitize_text_field($p['h3'] ?? ''),
                        'btn_url'  => esc_url_raw($p['btn_url'] ?? ''),
                    ];
                }
            }
            $clean = ['slides'=>$slides, 'promos'=>$promos];

        } elseif ($type === 'top-features') {
            $items = [];
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $it) {
                    $items[] = [
                        'icon'  => isset($it['icon']) ? absint($it['icon']) : 0,
                        'title' => sanitize_text_field($it['title'] ?? ''),
                        'text'  => sanitize_text_field($it['text'] ?? ''),
                    ];
                }
            }
            $clean = ['items'=>$items];

        } elseif ($type === 'browse-cats') {
            $clean = [
                'title'      => sanitize_text_field($data['title'] ?? 'Browse by Category'),
                'limit'      => isset($data['limit']) ? max(1, absint($data['limit'])) : 12,
                'orderby'    => sanitize_text_field($data['orderby'] ?? 'name'),
                'order'      => sanitize_text_field($data['order'] ?? 'ASC'),
                'hide_empty' => !empty($data['hide_empty']) ? 1 : 0,
            ];

        } elseif ($type === 'new-arrivals') {
            $clean = []; // dynamic

        } elseif ($type === 'promo-trio') {
            $cards = [];
            if (!empty($data['cards']) && is_array($data['cards'])) {
                foreach ($data['cards'] as $c) {
                    $cards[] = [
                        'img'      => isset($c['img']) ? absint($c['img']) : 0,
                        'h2'       => sanitize_text_field($c['h2'] ?? ''),
                        'h3'       => sanitize_text_field($c['h3'] ?? ''),
                        'p'        => sanitize_textarea_field($c['p'] ?? ''),
                        'btn_text' => sanitize_text_field($c['btn_text'] ?? ''),
                        'btn_url'  => esc_url_raw($c['btn_url'] ?? ''),
                    ];
                }
            }
            $clean = ['cards'=>$cards];

        } elseif ($type === 'newsletter') {
            $method = isset($data['method']) ? strtoupper($data['method']) : 'POST';
            if (!in_array($method, ['GET','POST'], true)) $method = 'POST';
            $clean = [
                'title'        => sanitize_text_field($data['title'] ?? ''),
                'text'         => sanitize_text_field($data['text'] ?? ''),
                'placeholder'  => sanitize_text_field($data['placeholder'] ?? ''),
                'btn_text'     => sanitize_text_field($data['btn_text'] ?? ''),
                'action'       => esc_url_raw($data['action'] ?? ''),
                'method'       => $method,
                'consent_text' => sanitize_text_field($data['consent_text'] ?? ''),
            ];

        // Services Slider — automatic (pulls directly from Service posts)
        } elseif ($type === 'services-slider') {

            // Optional length knobs; safe defaults
            $words_desktop = isset($data['words_desktop']) ? max(8, (int) $data['words_desktop']) : 36;
            $words_mobile  = isset($data['words_mobile'])  ? max(6, (int) $data['words_mobile'])  : 16;

            // Write only the lightweight settings. DO NOT persist per-service overrides.
            $clean = [
                'title'          => sanitize_text_field($data['title'] ?? __('Our Services', 'nhhb')),
                'words_desktop'  => $words_desktop,
                'words_mobile'   => $words_mobile,
                // 'services' intentionally omitted to purge legacy overrides
            ];

        } elseif ($type === 'b2b-banner') {
            $clean = [
                'h2'       => sanitize_text_field($data['h2'] ?? ''),
                'h3'       => sanitize_text_field($data['h3'] ?? ''),
                'btn_text' => sanitize_text_field($data['btn_text'] ?? ''),
                'btn_url'  => esc_url_raw($data['btn_url'] ?? ''),
                'logo'     => isset($data['logo']) ? absint($data['logo']) : 0,
            ];
        }

        update_post_meta($post_id, '_nhhb_type', $type);
        update_post_meta($post_id, '_nhhb_data', $clean);
    }

    public static function admin_assets($hook) {
        if (in_array($hook, ['post.php','post-new.php'], true)) {
            $screen = get_current_screen();
            if ($screen && $screen->post_type === 'nh_section') {
                wp_enqueue_media();
                wp_enqueue_script('jquery');
            }
        }
    }

    public static function front_assets() {
        wp_register_style('nhhb-core', NHHB_URL . 'assets/css/core.css', [], NHHB_VER);

        wp_register_style('nhhb-top-offers',   NHHB_URL . 'assets/css/top-offers.css',   ['nhhb-core'], NHHB_VER);
        wp_register_script('nhhb-top-offers',  NHHB_URL . 'assets/js/top-offers.js', [], NHHB_VER, true);

        wp_register_style('nhhb-top-features', NHHB_URL . 'assets/css/top-features.css', ['nhhb-core'], NHHB_VER);

        wp_register_style('nhhb-browse-cats',  NHHB_URL . 'assets/css/browse-cats.css', ['nhhb-core'], NHHB_VER);
        wp_register_script('nhhb-browse-cats', NHHB_URL . 'assets/js/browse-cats.js', [], NHHB_VER, true);

        wp_register_style('nhhb-new-arrivals', NHHB_URL . 'assets/css/new-arrivals.css', ['nhhb-core'], NHHB_VER);

        /* Promo Trio */
        wp_register_style('nhhb-promo-trio',   NHHB_URL . 'assets/css/promo-trio.css',   ['nhhb-core'], NHHB_VER);

        /* Newsletter */
        wp_register_style('nhhb-newsletter',   NHHB_URL . 'assets/css/newsletter.css',   ['nhhb-core'], NHHB_VER);

        /* Services Slider */
        wp_register_style('nhhb-services', NHHB_URL . 'assets/css/services-slider.css', ['nhhb-core'], NHHB_VER);
        wp_register_script('nhhb-services', NHHB_URL . 'assets/js/services-slider.js', [], NHHB_VER, true);

        /* B2B banner */
        wp_register_style('nhhb-b2b', NHHB_URL . 'assets/css/b2b-banner.css', ['nhhb-core'], NHHB_VER);
    }
}
NHHB_Admin::init();
