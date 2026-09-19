<?php
/**
 * Homepage layout: default order, migration from CPT shortcodes, auto-inject.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical homepage order (hero stays in the theme).
 *
 * @return string[]
 */
function nhhb_default_order() {
    return [
        'top-offers',
        'top-features',
        'browse-cats',
        'new-arrivals',
        'reviews-slider',
        'promo-trio',
        'services-slider',
        'newsletter',
        'b2b-banner',
    ];
}

/**
 * @return string[]
 */
function nhhb_allowed_section_types() {
    return nhhb_default_order();
}

/**
 * Admin labels for each section type.
 *
 * @return array<string, string>
 */
function nhhb_section_labels() {
    return [
        'top-offers'      => __('Top Offers', 'nhhb'),
        'top-features'    => __('Shop benefits', 'nhhb'),
        'browse-cats'     => __('Browse by Category', 'nhhb'),
        'new-arrivals'    => __('New Arrivals', 'nhhb'),
        'reviews-slider'  => __('Customer reviews', 'nhhb'),
        'promo-trio'      => __('Discounts and Clearance', 'nhhb'),
        'services-slider' => __('Our Services', 'nhhb'),
        'newsletter'      => __('Newsletter', 'nhhb'),
        'b2b-banner'      => __('For Business Customers', 'nhhb'),
    ];
}

/**
 * Translate stored English copy, or the fallback msgid when empty.
 *
 * @param mixed  $value
 * @param string $fallback_msgid
 */
function nhhb_maybe_translate($value, $fallback_msgid = '') {
    $text = trim((string) $value);
    if ($text === '') {
        return $fallback_msgid !== '' ? __($fallback_msgid, 'nhhb') : '';
    }
    if (function_exists('translate')) {
        $translated = translate($text, 'nhhb');
        if (is_string($translated) && $translated !== '') {
            return $translated;
        }
    }
    return $text;
}

/**
 * Default shop-benefit items (copy from translations; icons optional).
 *
 * @return array<int, array{icon:int,title:string,text:string}>
 */
function nhhb_default_features() {
    return [
        [
            'icon'  => 0,
            'title' => __('100% Secure Payments', 'nhhb'),
            'text'  => __('Pay safely with all major payment methods.', 'nhhb'),
        ],
        [
            'icon'  => 0,
            'title' => __('Expert Support', 'nhhb'),
            'text'  => __('Friendly assistance and advice directly from our experts.', 'nhhb'),
        ],
        [
            'icon'  => 0,
            'title' => __('Certified Quality', 'nhhb'),
            'text'  => __('We use only high-quality, certified materials.', 'nhhb'),
        ],
        [
            'icon'  => 0,
            'title' => __('From European Warehouses', 'nhhb'),
            'text'  => __('Orders are shipped quickly from our European warehouses.', 'nhhb'),
        ],
    ];
}

/**
 * Simple inline SVG used when a feature has no uploaded icon.
 *
 * @param int $index 0–3
 */
function nhhb_default_feature_svg($index) {
    $index = (int) $index;
    $paths = [
        0 => 'M12 2 4 6v6c0 5 3.4 9.4 8 10.5C16.6 21.4 20 17 20 12V6l-8-4zm-1 14.2-3.7-3.7 1.4-1.4 2.3 2.3 4.9-4.9 1.4 1.4L11 16.2z',
        1 => 'M12 12c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4zm0 2c-3 0-8 1.5-8 4.5V21h16v-2.5c0-3-5-4.5-8-4.5z',
        2 => 'M12 2 4.5 5.5v6.3c0 4.7 3.2 9 7.5 10.2 4.3-1.2 7.5-5.5 7.5-10.2V5.5L12 2zm-1.2 13.8-3.3-3.3 1.4-1.4 1.9 1.9 4.2-4.2 1.4 1.4-5.6 5.6z',
        3 => 'M3 10.5 12 4l9 6.5V20H3V10.5zm2 1.6V18h14v-5.9L12 6.8 5 12.1zM8 18v-4h8v4H8z',
    ];
    $d = $paths[$index] ?? $paths[0];
    return '<span class="nhhb-svg"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="' . $d . '"/></svg></span>';
}

/**
 * @return array{inject:int,order:string[],enabled:array<string,int>,data:array<string,array>}
 */
function nhhb_empty_layout() {
    $order = nhhb_default_order();
    $enabled = [];
    $data = [];
    foreach ($order as $type) {
        $enabled[$type] = 1;
        $data[$type] = [];
    }
    return [
        'inject'  => 1,
        'order'   => $order,
        'enabled' => $enabled,
        'data'    => $data,
    ];
}

/**
 * Normalize a stored layout array.
 *
 * @param mixed $raw
 * @return array{inject:int,order:string[],enabled:array<string,int>,data:array<string,array>}
 */
function nhhb_normalize_layout($raw) {
    $base = nhhb_empty_layout();
    if (!is_array($raw)) {
        return $base;
    }

    $allowed = nhhb_allowed_section_types();
    $order = [];
    if (!empty($raw['order']) && is_array($raw['order'])) {
        foreach ($raw['order'] as $type) {
            $type = sanitize_key((string) $type);
            if (in_array($type, $allowed, true) && !in_array($type, $order, true)) {
                $order[] = $type;
            }
        }
    }
    foreach ($allowed as $type) {
        if (!in_array($type, $order, true)) {
            $order[] = $type;
        }
    }

    $enabled = [];
    foreach ($allowed as $type) {
        if (isset($raw['enabled']) && is_array($raw['enabled']) && array_key_exists($type, $raw['enabled'])) {
            $enabled[$type] = empty($raw['enabled'][$type]) ? 0 : 1;
        } else {
            $enabled[$type] = 1;
        }
    }

    $data = [];
    foreach ($allowed as $type) {
        $row = [];
        if (!empty($raw['data'][$type]) && is_array($raw['data'][$type])) {
            $row = $raw['data'][$type];
        }
        $data[$type] = nhhb_sanitize_section_data($type, $row);
    }

    return [
        'inject'  => empty($raw['inject']) ? 0 : 1,
        'order'   => $order,
        'enabled' => $enabled,
        'data'    => $data,
    ];
}

/**
 * @return array{inject:int,order:string[],enabled:array<string,int>,data:array<string,array>}
 */
function nhhb_get_layout() {
    $stored = get_option('nhhb_home', null);
    if ($stored === null || $stored === false) {
        $stored = nhhb_migrate_from_cpt();
        update_option('nhhb_home', $stored, false);
    }
    return nhhb_normalize_layout($stored);
}

/**
 * Pull existing section posts from homepage shortcodes, then fill the default order.
 *
 * @return array{inject:int,order:string[],enabled:array<string,int>,data:array<string,array>}
 */
function nhhb_migrate_from_cpt() {
    $layout = nhhb_empty_layout();
    $ids = nhhb_homepage_section_ids();
    if (!$ids) {
        $ids = nhhb_all_section_post_ids();
    }

    $used = [];
    $migrated_order = [];
    foreach ($ids as $id) {
        $type = (string) get_post_meta($id, '_nhhb_type', true);
        if ($type === 'offers-hero') {
            $type = 'top-offers';
        }
        if (!in_array($type, nhhb_allowed_section_types(), true) || isset($used[$type])) {
            continue;
        }
        $data = get_post_meta($id, '_nhhb_data', true);
        $layout['data'][$type] = nhhb_sanitize_section_data($type, is_array($data) ? $data : []);
        $used[$type] = 1;
        $migrated_order[] = $type;
    }

    if ($migrated_order) {
        $layout['order'] = array_values(array_unique(array_merge($migrated_order, nhhb_default_order())));
        $layout['order'] = array_values(array_filter($layout['order'], static function ($type) {
            return in_array($type, nhhb_allowed_section_types(), true);
        }));
    }

    return nhhb_normalize_layout($layout);
}

/**
 * Section IDs in the order they appear on the static homepage.
 *
 * @return int[]
 */
function nhhb_homepage_section_ids() {
    $page_id = (int) get_option('page_on_front');
    if (!$page_id) {
        return [];
    }
    $post = get_post($page_id);
    if (!$post || empty($post->post_content)) {
        return [];
    }
    return nhhb_parse_section_ids((string) $post->post_content);
}

/**
 * @param string $content
 * @return int[]
 */
function nhhb_parse_section_ids($content) {
    if (!preg_match_all('/\[nh_section[^\]]*id=["\']?(\d+)/i', (string) $content, $matches)) {
        return [];
    }
    $ids = [];
    foreach ($matches[1] as $id) {
        $id = absint($id);
        if ($id && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    return $ids;
}

/**
 * @return int[]
 */
function nhhb_all_section_post_ids() {
    $q = new WP_Query([
        'post_type'      => 'nh_section',
        'post_status'    => 'any',
        'posts_per_page' => 50,
        'orderby'        => 'menu_order date',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    return array_map('absint', $q->posts);
}

/**
 * @param string $type
 * @param mixed  $data
 * @return array
 */
function nhhb_sanitize_section_data($type, $data) {
    $data = is_array($data) ? $data : [];

    if ($type === 'top-offers') {
        $slides = [];
        if (!empty($data['slides']) && is_array($data['slides'])) {
            foreach ($data['slides'] as $s) {
                if (!is_array($s)) {
                    continue;
                }
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
                if (!is_array($p)) {
                    continue;
                }
                $promos[] = [
                    'img'      => isset($p['img']) ? absint($p['img']) : 0,
                    'h1'       => sanitize_text_field($p['h1'] ?? ''),
                    'h3'       => sanitize_text_field($p['h3'] ?? ''),
                    'btn_url'  => esc_url_raw($p['btn_url'] ?? ''),
                ];
            }
        }
        return ['slides' => $slides, 'promos' => $promos];
    }

    if ($type === 'top-features') {
        $items = [];
        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $items[] = [
                    'icon'  => isset($it['icon']) ? absint($it['icon']) : 0,
                    'title' => sanitize_text_field($it['title'] ?? ''),
                    'text'  => sanitize_text_field($it['text'] ?? ''),
                ];
            }
        }
        return ['items' => array_slice($items, 0, 4)];
    }

    if ($type === 'browse-cats') {
        return [
            'title'      => sanitize_text_field($data['title'] ?? ''),
            'limit'      => isset($data['limit']) ? max(1, absint($data['limit'])) : 12,
            'orderby'    => sanitize_text_field($data['orderby'] ?? 'name'),
            'order'      => sanitize_text_field($data['order'] ?? 'ASC'),
            'hide_empty' => !empty($data['hide_empty']) ? 1 : 0,
        ];
    }

    if ($type === 'new-arrivals') {
        return [
            'title'      => sanitize_text_field($data['title'] ?? ''),
            'count'      => isset($data['count']) ? max(1, min(24, absint($data['count']))) : 8,
            'view_label' => sanitize_text_field($data['view_label'] ?? ''),
            'view_url'   => esc_url_raw($data['view_url'] ?? ''),
        ];
    }

    if ($type === 'reviews-slider') {
        return [
            'title'      => sanitize_text_field($data['title'] ?? ($data['reviews_title'] ?? '')),
            'count'      => isset($data['count']) ? max(1, min(24, absint($data['count']))) : (isset($data['reviews_count']) ? max(1, min(24, absint($data['reviews_count']))) : 8),
            'view_label' => sanitize_text_field($data['view_label'] ?? ($data['reviews_view_label'] ?? '')),
            'view_url'   => esc_url_raw($data['view_url'] ?? ($data['reviews_view_url'] ?? '')),
        ];
    }

    if ($type === 'promo-trio') {
        $cards = [];
        if (!empty($data['cards']) && is_array($data['cards'])) {
            foreach ($data['cards'] as $c) {
                if (!is_array($c)) {
                    continue;
                }
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
        return ['cards' => $cards];
    }

    if ($type === 'newsletter') {
        return [
            'consent_text' => sanitize_text_field($data['consent_text'] ?? ''),
        ];
    }

    if ($type === 'services-slider') {
        $services_clean = [];
        if (!empty($data['services']) && is_array($data['services'])) {
            foreach ($data['services'] as $sid => $row) {
                $sid = absint($sid);
                if (!$sid || !is_array($row)) {
                    continue;
                }
                $desktop = sanitize_textarea_field($row['desktop'] ?? '');
                $mobile  = sanitize_textarea_field($row['mobile'] ?? '');
                if ($desktop !== '' || $mobile !== '') {
                    $services_clean[$sid] = [
                        'desktop' => $desktop,
                        'mobile'  => $mobile,
                    ];
                }
            }
        }
        return [
            'title'    => sanitize_text_field($data['title'] ?? ($data['services_title'] ?? '')),
            'services' => $services_clean,
            'mode'     => 'manual',
        ];
    }

    if ($type === 'b2b-banner') {
        return [
            'h2'       => sanitize_text_field($data['h2'] ?? ''),
            'h3'       => sanitize_text_field($data['h3'] ?? ''),
            'btn_text' => sanitize_text_field($data['btn_text'] ?? ''),
            'btn_url'  => esc_url_raw($data['btn_url'] ?? ''),
            'logo'     => isset($data['logo']) ? absint($data['logo']) : 0,
        ];
    }

    return [];
}

/**
 * Render enabled homepage sections in layout order.
 */
function nhhb_render_home() {
    $layout = nhhb_get_layout();
    $html = '';
    foreach ($layout['order'] as $type) {
        if (empty($layout['enabled'][$type])) {
            continue;
        }
        $data = isset($layout['data'][$type]) && is_array($layout['data'][$type]) ? $layout['data'][$type] : [];
        $html .= nhhb_render($type, $data);
    }
    return $html;
}

/**
 * @param string $content
 */
function nhhb_strip_section_shortcodes($content) {
    $content = (string) $content;
    $content = preg_replace('/<!--\s*wp:shortcode\s*-->\s*\[nh_section[^\]]*\]\s*<!--\s*\/wp:shortcode\s*-->/i', '', $content);
    $content = preg_replace('/\[nh_section[^\]]*\]/', '', $content);
    $content = preg_replace('/<p>(\s|&nbsp;)*<\/p>/i', '', $content);
    return is_string($content) ? $content : '';
}

/**
 * @param string $content
 */
function nhhb_inject_home_sections($content) {
    if (is_admin() || !is_front_page() || !is_main_query() || !in_the_loop()) {
        return $content;
    }

    $layout = nhhb_get_layout();
    if (empty($layout['inject'])) {
        return $content;
    }

    $stripped = nhhb_strip_section_shortcodes($content);
    $sections = nhhb_render_home();
    if ($sections === '') {
        return $stripped;
    }
    $trimmed = trim($stripped);
    return $trimmed === '' ? $sections : $trimmed . $sections;
}

add_filter('the_content', 'nhhb_inject_home_sections', 8);
