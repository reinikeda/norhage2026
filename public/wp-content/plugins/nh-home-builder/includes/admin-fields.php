<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $type
 * @param string $path e.g. [slides][0][img]
 */
function nhhb_field_name($type, $path) {
    return 'nhhb[data][' . $type . ']' . $path;
}

/**
 * Settings fields for one homepage section.
 *
 * @param string $type
 * @param array  $data
 */
function nhhb_admin_section_fields($type, $data) {
    $data = is_array($data) ? $data : [];

    if ($type === 'top-offers') {
        nhhb_admin_fields_top_offers($data);
        return;
    }
    if ($type === 'top-features') {
        nhhb_admin_fields_top_features($data);
        return;
    }
    if ($type === 'browse-cats') {
        nhhb_admin_fields_browse_cats($data);
        return;
    }
    if ($type === 'new-arrivals') {
        nhhb_admin_fields_new_arrivals($data);
        return;
    }
    if ($type === 'reviews-slider') {
        nhhb_admin_fields_reviews($data);
        return;
    }
    if ($type === 'promo-trio') {
        nhhb_admin_fields_promo_trio($data);
        return;
    }
    if ($type === 'newsletter') {
        nhhb_admin_fields_newsletter($data);
        return;
    }
    if ($type === 'services-slider') {
        nhhb_admin_fields_services($data);
        return;
    }
    if ($type === 'b2b-banner') {
        nhhb_admin_fields_b2b($data);
    }
}

function nhhb_admin_thumb($id, $thumb_id, $empty) {
    $src = $id ? wp_get_attachment_image_url($id, 'medium') : '';
    echo '<div class="nhhb-thumb" id="' . esc_attr($thumb_id) . '">';
    echo $src ? '<img src="' . esc_url($src) . '" alt=""/>' : esc_html($empty);
    echo '</div>';
}

function nhhb_admin_media_buttons($target) {
    echo '<div class="nhhb-actions">';
    echo '<button type="button" class="button nhhb-upload" data-target="' . esc_attr($target) . '">' . esc_html__('Browse', 'nhhb') . '</button>';
    echo '<button type="button" class="button-link-delete nhhb-remove" data-target="' . esc_attr($target) . '">' . esc_html__('Remove', 'nhhb') . '</button>';
    echo '</div>';
}

function nhhb_admin_fields_top_offers($data) {
    $slides = isset($data['slides']) && is_array($data['slides']) ? $data['slides'] : [];
    $promos = isset($data['promos']) && is_array($data['promos']) ? $data['promos'] : [];
    echo '<p class="description">' . esc_html__('Campaign images and copy stay per shop. Leave a slide empty to hide it.', 'nhhb') . '</p>';
    echo '<h4>' . esc_html__('Slider (max. 3 slides)', 'nhhb') . '</h4>';
    echo '<div class="nhhb-grid nhhb-3">';
    for ($i = 0; $i < 3; $i++) {
        $s = isset($slides[$i]) && is_array($slides[$i]) ? $slides[$i] : [];
        $img_id = isset($s['img']) ? absint($s['img']) : 0;
        echo '<div class="nhhb-card"><h4>' . esc_html(sprintf(__('Slide %d', 'nhhb'), $i + 1)) . '</h4><div class="nhhb-row"><div>';
        nhhb_admin_thumb($img_id, 'slide_thumb_' . $i, __('No image', 'nhhb'));
        nhhb_admin_media_buttons('slide_' . $i);
        echo '<input type="hidden" name="' . esc_attr(nhhb_field_name('top-offers', '[slides][' . $i . '][img]')) . '" id="slide_' . (int) $i . '" value="' . esc_attr((string) $img_id) . '">';
        echo '</div><div style="flex:1">';
        echo '<p><label>' . esc_html__('Main Heading (H2)', 'nhhb') . '<br><input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('top-offers', '[slides][' . $i . '][h1]')) . '" value="' . esc_attr($s['h1'] ?? '') . '"></label></p>';
        echo '<p><label>' . esc_html__('Brand / Subheading', 'nhhb') . '<br><input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('top-offers', '[slides][' . $i . '][h2]')) . '" value="' . esc_attr($s['h2'] ?? '') . '"></label></p>';
        echo '<p><label>' . esc_html__('Description', 'nhhb') . '<br><input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('top-offers', '[slides][' . $i . '][h3]')) . '" value="' . esc_attr($s['h3'] ?? '') . '"></label></p>';
        echo '<p class="nhhb-2"><label>' . esc_html__('Button Text', 'nhhb') . '<input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('top-offers', '[slides][' . $i . '][btn_text]')) . '" value="' . esc_attr($s['btn_text'] ?? '') . '" placeholder="' . esc_attr__('Shop now', 'nhhb') . '"></label>';
        echo '<label>' . esc_html__('Button URL', 'nhhb') . '<input type="url" class="widefat" name="' . esc_attr(nhhb_field_name('top-offers', '[slides][' . $i . '][btn_url]')) . '" value="' . esc_attr($s['btn_url'] ?? '') . '"></label></p>';
        echo '</div></div></div>';
    }
    echo '</div><h4>' . esc_html__('Right-side Promos (2 cards)', 'nhhb') . '</h4><div class="nhhb-grid nhhb-2">';
    for ($i = 0; $i < 2; $i++) {
        $p = isset($promos[$i]) && is_array($promos[$i]) ? $promos[$i] : [];
        $img_id = isset($p['img']) ? absint($p['img']) : 0;
        echo '<div class="nhhb-card"><h4>' . esc_html(sprintf(__('Promo %d', 'nhhb'), $i + 1)) . '</h4><div class="nhhb-row"><div>';
        nhhb_admin_thumb($img_id, 'promo_thumb_' . $i, __('No image', 'nhhb'));
        nhhb_admin_media_buttons('promo_' . $i);
        echo '<input type="hidden" name="' . esc_attr(nhhb_field_name('top-offers', '[promos][' . $i . '][img]')) . '" id="promo_' . (int) $i . '" value="' . esc_attr((string) $img_id) . '">';
        echo '</div><div style="flex:1">';
        echo '<p><label>' . esc_html__('Title (H3, clickable)', 'nhhb') . '<br><input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('top-offers', '[promos][' . $i . '][h1]')) . '" value="' . esc_attr($p['h1'] ?? '') . '"></label></p>';
        echo '<p><label>' . esc_html__('Description', 'nhhb') . '<br><input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('top-offers', '[promos][' . $i . '][h3]')) . '" value="' . esc_attr($p['h3'] ?? '') . '"></label></p>';
        echo '<p><label>' . esc_html__('URL (applies to the title)', 'nhhb') . '<br><input type="url" class="widefat" name="' . esc_attr(nhhb_field_name('top-offers', '[promos][' . $i . '][btn_url]')) . '" value="' . esc_attr($p['btn_url'] ?? '') . '"></label></p>';
        echo '</div></div></div>';
    }
    echo '</div>';
}

function nhhb_admin_fields_top_features($data) {
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    $defaults = nhhb_default_features();
    echo '<p class="description">' . esc_html__('Leave fields empty to use the translated shop-benefit copy. Upload an icon to replace the default glyph.', 'nhhb') . '</p>';
    echo '<div class="nhhb-grid nhhb-4">';
    for ($i = 0; $i < 4; $i++) {
        $it = isset($items[$i]) && is_array($items[$i]) ? $items[$i] : [];
        $icon_id = isset($it['icon']) ? absint($it['icon']) : 0;
        echo '<div class="nhhb-card"><h4>' . esc_html(sprintf(__('Feature %d', 'nhhb'), $i + 1)) . '</h4><div class="nhhb-row"><div>';
        nhhb_admin_thumb($icon_id, 'feat_thumb_' . $i, __('No icon', 'nhhb'));
        nhhb_admin_media_buttons('feat_' . $i);
        echo '<input type="hidden" name="' . esc_attr(nhhb_field_name('top-features', '[items][' . $i . '][icon]')) . '" id="feat_' . (int) $i . '" value="' . esc_attr((string) $icon_id) . '">';
        echo '</div><div style="flex:1">';
        echo '<p><label>' . esc_html__('Heading (H3)', 'nhhb') . '<br><input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('top-features', '[items][' . $i . '][title]')) . '" value="' . esc_attr($it['title'] ?? '') . '" placeholder="' . esc_attr($defaults[$i]['title']) . '"></label></p>';
        echo '<p><label>' . esc_html__('Subtext', 'nhhb') . '<br><input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('top-features', '[items][' . $i . '][text]')) . '" value="' . esc_attr($it['text'] ?? '') . '" placeholder="' . esc_attr($defaults[$i]['text']) . '"></label></p>';
        echo '</div></div></div>';
    }
    echo '</div>';
}

function nhhb_admin_fields_browse_cats($data) {
    $title = $data['title'] ?? '';
    $limit = isset($data['limit']) ? (int) $data['limit'] : 12;
    $orderby = $data['orderby'] ?? 'name';
    $order = $data['order'] ?? 'ASC';
    $hide_empty = !empty($data['hide_empty']);
    echo '<p><label>' . esc_html__('Section Title (H2)', 'nhhb') . '<br>';
    echo '<input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('browse-cats', '[title]')) . '" value="' . esc_attr($title) . '" placeholder="' . esc_attr__('Browse by Category', 'nhhb') . '">';
    echo '</label></p><p class="description">' . esc_html__('Leave empty to use the translated title.', 'nhhb') . '</p>';
    echo '<div class="nhhb-grid nhhb-2">';
    echo '<p><label>' . esc_html__('Max. Items', 'nhhb') . '<br><input type="number" min="1" class="widefat" name="' . esc_attr(nhhb_field_name('browse-cats', '[limit]')) . '" value="' . (int) $limit . '"></label></p>';
    echo '<p><label>' . esc_html__('Order By', 'nhhb') . '<br><select name="' . esc_attr(nhhb_field_name('browse-cats', '[orderby]')) . '" class="widefat">';
    foreach (['name' => __('Name', 'nhhb'), 'slug' => __('Slug', 'nhhb'), 'count' => __('Count', 'nhhb'), 'term_id' => __('ID', 'nhhb')] as $k => $lbl) {
        echo '<option value="' . esc_attr($k) . '" ' . selected($orderby, $k, false) . '>' . esc_html($lbl) . '</option>';
    }
    echo '</select></label></p></div><div class="nhhb-grid nhhb-2">';
    echo '<p><label>' . esc_html__('Order', 'nhhb') . '<br><select name="' . esc_attr(nhhb_field_name('browse-cats', '[order]')) . '" class="widefat">';
    echo '<option value="ASC" ' . selected($order, 'ASC', false) . '>ASC</option>';
    echo '<option value="DESC" ' . selected($order, 'DESC', false) . '>DESC</option>';
    echo '</select></label></p>';
    echo '<p style="margin-top:26px;"><label><input type="checkbox" name="' . esc_attr(nhhb_field_name('browse-cats', '[hide_empty]')) . '" value="1" ' . checked($hide_empty, true, false) . '> ' . esc_html__('Hide empty categories', 'nhhb') . '</label></p></div>';
}

function nhhb_admin_fields_new_arrivals($data) {
    echo '<p><label>' . esc_html__('Section Title (H2)', 'nhhb') . '<br>';
    echo '<input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('new-arrivals', '[title]')) . '" value="' . esc_attr($data['title'] ?? '') . '" placeholder="' . esc_attr__('New Arrivals', 'nhhb') . '"></label></p>';
    echo '<p class="description">' . esc_html__('Leave empty to use the translated title. Products are pulled automatically.', 'nhhb') . '</p>';
    echo '<div class="nhhb-grid nhhb-3">';
    echo '<p><label>' . esc_html__('Number of products', 'nhhb') . '<br><input type="number" min="1" max="24" class="widefat" name="' . esc_attr(nhhb_field_name('new-arrivals', '[count]')) . '" value="' . (int) ($data['count'] ?? 8) . '"></label></p>';
    echo '<p><label>' . esc_html__('View All label', 'nhhb') . '<br><input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('new-arrivals', '[view_label]')) . '" value="' . esc_attr($data['view_label'] ?? '') . '" placeholder="' . esc_attr__('View All', 'nhhb') . '"></label></p>';
    echo '<p><label>' . esc_html__('View All URL (optional)', 'nhhb') . '<br><input type="url" class="widefat" name="' . esc_attr(nhhb_field_name('new-arrivals', '[view_url]')) . '" value="' . esc_attr($data['view_url'] ?? '') . '" placeholder="' . esc_attr__('Defaults to shop page', 'nhhb') . '"></label></p></div>';
}

function nhhb_admin_fields_reviews($data) {
    echo '<p class="description">' . esc_html__('Approved 5-star WooCommerce reviews with a written comment. Ratings without text are skipped; missing photos use initials.', 'nhhb') . '</p>';
    echo '<p><label>' . esc_html__('Section Title (H2)', 'nhhb') . '<br>';
    echo '<input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('reviews-slider', '[title]')) . '" value="' . esc_attr($data['title'] ?? '') . '" placeholder="' . esc_attr__('Customer reviews', 'nhhb') . '"></label></p>';
    echo '<div class="nhhb-grid nhhb-3">';
    echo '<p><label>' . esc_html__('Number of reviews', 'nhhb') . '<br><input type="number" min="1" max="24" class="widefat" name="' . esc_attr(nhhb_field_name('reviews-slider', '[count]')) . '" value="' . (int) ($data['count'] ?? 8) . '"></label></p>';
    echo '<p><label>' . esc_html__('View All label (optional)', 'nhhb') . '<br><input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('reviews-slider', '[view_label]')) . '" value="' . esc_attr($data['view_label'] ?? '') . '" placeholder="' . esc_attr__('View All', 'nhhb') . '"></label></p>';
    echo '<p><label>' . esc_html__('View All URL (optional)', 'nhhb') . '<br><input type="url" class="widefat" name="' . esc_attr(nhhb_field_name('reviews-slider', '[view_url]')) . '" value="' . esc_attr($data['view_url'] ?? '') . '"></label></p></div>';
}

function nhhb_admin_fields_promo_trio($data) {
    $cards = isset($data['cards']) && is_array($data['cards']) ? $data['cards'] : [];
    echo '<p class="description">' . esc_html__('Campaign images and copy stay per shop.', 'nhhb') . '</p>';
    $labels = [__('Hero (full width)', 'nhhb'), __('Promo A', 'nhhb'), __('Promo B', 'nhhb')];
    for ($i = 0; $i < 3; $i++) {
        $c = isset($cards[$i]) && is_array($cards[$i]) ? $cards[$i] : [];
        $img_id = isset($c['img']) ? absint($c['img']) : 0;
        echo '<div class="nhhb-card" style="margin-bottom:14px;"><h4>' . esc_html($labels[$i]) . '</h4><div class="nhhb-row"><div>';
        nhhb_admin_thumb($img_id, 'ptr_thumb_' . $i, __('No image', 'nhhb'));
        nhhb_admin_media_buttons('ptr_' . $i);
        echo '<input type="hidden" name="' . esc_attr(nhhb_field_name('promo-trio', '[cards][' . $i . '][img]')) . '" id="ptr_' . (int) $i . '" value="' . esc_attr((string) $img_id) . '">';
        echo '</div><div style="flex:1">';
        echo '<p><label>' . esc_html__('Kicker Text', 'nhhb') . '<br><input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('promo-trio', '[cards][' . $i . '][h3]')) . '" value="' . esc_attr($c['h3'] ?? '') . '"></label></p>';
        echo '<p><label>' . ($i === 0 ? esc_html__('Main Heading (H2)', 'nhhb') : esc_html__('Heading (H3)', 'nhhb')) . '<br><input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('promo-trio', '[cards][' . $i . '][h2]')) . '" value="' . esc_attr($c['h2'] ?? '') . '"></label></p>';
        echo '<p><label>' . esc_html__('Paragraph', 'nhhb') . '<br><textarea class="widefat" name="' . esc_attr(nhhb_field_name('promo-trio', '[cards][' . $i . '][p]')) . '" rows="2">' . esc_textarea($c['p'] ?? '') . '</textarea></label></p>';
        echo '<p class="nhhb-2"><label>' . esc_html__('Button Text', 'nhhb') . '<input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('promo-trio', '[cards][' . $i . '][btn_text]')) . '" value="' . esc_attr($c['btn_text'] ?? '') . '"></label>';
        echo '<label>' . esc_html__('Button URL', 'nhhb') . '<input type="url" class="widefat" name="' . esc_attr(nhhb_field_name('promo-trio', '[cards][' . $i . '][btn_url]')) . '" value="' . esc_attr($c['btn_url'] ?? '') . '"></label></p>';
        echo '</div></div></div>';
    }
}

function nhhb_admin_fields_newsletter($data) {
    echo '<p class="description">' . esc_html__('Headline, kicker, placeholder and button text come from the plugin translations so each shop language stays consistent.', 'nhhb') . '</p>';
    echo '<p><label>' . esc_html__('Consent note (optional)', 'nhhb') . '<br>';
    echo '<input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('newsletter', '[consent_text]')) . '" value="' . esc_attr($data['consent_text'] ?? '') . '"></label></p>';
}

function nhhb_admin_fields_services($data) {
    $sv = isset($data['services']) && is_array($data['services']) ? $data['services'] : [];
    echo '<p><label>' . esc_html__('Section Title (H2)', 'nhhb') . '<br>';
    echo '<input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('services-slider', '[title]')) . '" value="' . esc_attr($data['title'] ?? '') . '" placeholder="' . esc_attr__('Our Services', 'nhhb') . '"></label></p>';
    $q = new WP_Query([
        'post_type'      => 'service',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'orderby'        => function_exists( 'nh_service_orderby' ) ? nh_service_orderby() : array(
            'menu_order' => 'ASC',
            'date'       => 'DESC',
        ),
        'no_found_rows'  => true,
    ]);
    if (!$q->have_posts()) {
        echo '<p class="description">' . esc_html__('No published Service posts found.', 'nhhb') . '</p>';
        return;
    }
    echo '<div class="nhhb-svc-grid">';
    while ($q->have_posts()) {
        $q->the_post();
        $sid = get_the_ID();
        $row = isset($sv[$sid]) && is_array($sv[$sid]) ? $sv[$sid] : [];
        echo '<div class="nhhb-card"><div class="nhhb-svc-row"><div><strong>' . esc_html(get_the_title()) . '</strong>';
        echo '<div class="nhhb-svc-small">ID: ' . (int) $sid . ' — <a href="' . esc_url(get_permalink($sid)) . '" target="_blank" rel="noopener">' . esc_html__('View', 'nhhb') . '</a></div></div>';
        echo '<div class="nhhb-grid nhhb-2"><p style="margin:0;"><label>' . esc_html__('Desktop Text', 'nhhb') . '<br>';
        echo '<textarea class="widefat" rows="3" name="' . esc_attr(nhhb_field_name('services-slider', '[services][' . (int) $sid . '][desktop]')) . '">' . esc_textarea($row['desktop'] ?? '') . '</textarea></label></p>';
        echo '<p style="margin:0;"><label>' . esc_html__('Mobile Text', 'nhhb') . '<br>';
        echo '<textarea class="widefat" rows="3" name="' . esc_attr(nhhb_field_name('services-slider', '[services][' . (int) $sid . '][mobile]')) . '">' . esc_textarea($row['mobile'] ?? '') . '</textarea></label></p></div></div></div>';
    }
    wp_reset_postdata();
    echo '</div><p class="description">' . esc_html__('The slider uses each service title, featured image and link, plus the desktop/mobile text entered here.', 'nhhb') . '</p>';
}

function nhhb_admin_fields_b2b($data) {
    $logo = isset($data['logo']) ? absint($data['logo']) : 0;
    echo '<p class="description">' . esc_html__('Leave titles empty to use the translated B2B copy.', 'nhhb') . '</p>';
    echo '<div class="nhhb-grid nhhb-3">';
    echo '<p><label>' . esc_html__('Main Title (H2)', 'nhhb') . '<br><input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('b2b-banner', '[h2]')) . '" value="' . esc_attr($data['h2'] ?? '') . '" placeholder="' . esc_attr__('For Business Customers', 'nhhb') . '"></label></p>';
    echo '<p><label>' . esc_html__('Subtitle (H3)', 'nhhb') . '<br><input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('b2b-banner', '[h3]')) . '" value="' . esc_attr($data['h3'] ?? '') . '" placeholder="' . esc_attr__('Exclusive pricing and services for B2B partners.', 'nhhb') . '"></label></p>';
    echo '<p><label>' . esc_html__('Button Text', 'nhhb') . '<br><input type="text" class="widefat" name="' . esc_attr(nhhb_field_name('b2b-banner', '[btn_text]')) . '" value="' . esc_attr($data['btn_text'] ?? '') . '" placeholder="' . esc_attr__('Learn more', 'nhhb') . '"></label></p></div>';
    echo '<div class="nhhb-grid nhhb-2" style="align-items:end"><p><label>' . esc_html__('Button URL (opens in a new tab)', 'nhhb') . '<br><input type="url" class="widefat" name="' . esc_attr(nhhb_field_name('b2b-banner', '[btn_url]')) . '" value="' . esc_attr($data['btn_url'] ?? '') . '" placeholder="https://"></label></p>';
    echo '<div class="nhhb-card"><h4>' . esc_html__('Logo (single file)', 'nhhb') . '</h4><div class="nhhb-row"><div>';
    nhhb_admin_thumb($logo, 'b2b_logo_thumb_single', __('No logo selected', 'nhhb'));
    nhhb_admin_media_buttons('b2b_logo_single');
    echo '<input type="hidden" name="' . esc_attr(nhhb_field_name('b2b-banner', '[logo]')) . '" id="b2b_logo_single" value="' . esc_attr((string) $logo) . '"></div>';
    echo '<p class="description" style="margin:0 0 0 10px;">' . wp_kses_post(__('Upload a <strong>single transparent PNG/SVG</strong> in your brand color. It is inverted to white on the blue banner.', 'nhhb')) . '</p></div></div></div>';
}
