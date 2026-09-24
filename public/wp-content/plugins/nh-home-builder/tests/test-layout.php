<?php
/**
 * CLI tests: homepage layout helpers.
 *
 * Run: php public/wp-content/plugins/nh-home-builder/tests/test-layout.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $cb, $priority = 10, $accepted_args = 1) {
        unset($hook, $cb, $priority, $accepted_args);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        unset($domain);
        return (string) $text;
    }
}

if (!function_exists('translate')) {
    function translate($text, $domain = 'default') {
        unset($domain);
        $map = [
            'New Arrivals' => 'Nyheter',
            'Browse by Category' => 'Bläddra efter kategori',
            'Customer reviews' => 'Kundrecensioner',
        ];
        $text = (string) $text;
        return isset($map[$text]) ? $map[$text] : $text;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text) {
        return trim(strip_tags((string) $text));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($text) {
        return trim(strip_tags((string) $text));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($text) {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $text));
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return (string) $url;
    }
}

if (!function_exists('absint')) {
    function absint($num) {
        return abs((int) $num);
    }
}

require_once dirname(__DIR__) . '/includes/layout.php';

$failures = 0;
function nhhb_layout_assert($label, $ok) {
    global $failures;
    if ($ok) {
        echo "ok  {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL  {$label}\n";
}

$order = nhhb_default_order();
nhhb_layout_assert('reviews sit after new arrivals', array_search('reviews-slider', $order, true) === array_search('new-arrivals', $order, true) + 1);
nhhb_layout_assert('nine homepage sections', count($order) === 9);

$ids = nhhb_parse_section_ids('Intro [nh_section id="12"]<!-- wp:shortcode -->[nh_section id="15"]<!-- /wp:shortcode -->[nh_section id="12"]');
nhhb_layout_assert('shortcode ids parsed in order without dupes', $ids === [12, 15]);

$stripped = nhhb_strip_section_shortcodes('<p>[nh_section id="9"]</p><!-- wp:shortcode -->[nh_section id="10"]<!-- /wp:shortcode --><p></p>Hello');
nhhb_layout_assert('homepage shortcodes are stripped', strpos($stripped, 'nh_section') === false);
nhhb_layout_assert('other homepage copy is kept', strpos($stripped, 'Hello') !== false);

$with_rule = "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->\n<!-- wp:paragraph -->\n<p>Hello</p>\n<!-- /wp:paragraph -->";
$without_rule = nhhb_strip_section_shortcodes($with_rule);
nhhb_layout_assert('homepage separator block is removed', strpos($without_rule, 'wp-block-separator') === false && strpos($without_rule, 'wp:separator') === false);
nhhb_layout_assert('copy after the separator is kept', strpos($without_rule, 'Hello') !== false);

$raw_rule = nhhb_strip_section_shortcodes('<hr class="wp-block-separator has-alpha-channel-opacity" />Keep');
nhhb_layout_assert('rendered separator tag is removed', strpos($raw_rule, '<hr') === false && strpos($raw_rule, 'Keep') !== false);

nhhb_layout_assert('empty title uses fallback', nhhb_maybe_translate('', 'New Arrivals') === 'New Arrivals');
nhhb_layout_assert('stored English title is translated', nhhb_maybe_translate('Customer reviews', 'Customer reviews') === 'Kundrecensioner');
nhhb_layout_assert('custom title is kept', nhhb_maybe_translate('Summer picks', 'New Arrivals') === 'Summer picks');

$clean = nhhb_sanitize_section_data('new-arrivals', ['title' => '', 'count' => 8, 'view_label' => '']);
nhhb_layout_assert('empty titles stay empty so translations can fill them', $clean['title'] === '' && $clean['view_label'] === '');

$layout = nhhb_normalize_layout(['inject' => 1, 'order' => ['reviews-slider', 'bogus'], 'enabled' => ['reviews-slider' => 0], 'data' => []]);
nhhb_layout_assert('unknown types are dropped', !in_array('bogus', $layout['order'], true));
nhhb_layout_assert('missing types are appended', in_array('new-arrivals', $layout['order'], true));
nhhb_layout_assert('disabled flag is kept', $layout['enabled']['reviews-slider'] === 0);

if ($failures) {
    echo "\n{$failures} failing\n";
    exit(1);
}
echo "\nAll tests passed\n";
exit(0);
