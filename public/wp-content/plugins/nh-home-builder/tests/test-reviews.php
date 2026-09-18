<?php
/**
 * CLI tests: 5-star review extraction helpers.
 *
 * Run: php public/wp-content/plugins/nh-home-builder/tests/test-reviews.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        unset($domain);
        return (string) $text;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default') {
        unset($domain);
        return esc_attr($text);
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string) {
        return trim(strip_tags((string) $string));
    }
}

if (!function_exists('get_comment_meta')) {
    function get_comment_meta($id, $key, $single = false) {
        global $nhhb_test_comment_meta;
        unset($single);
        if (!isset($nhhb_test_comment_meta[(int) $id][$key])) {
            return '';
        }
        return $nhhb_test_comment_meta[(int) $id][$key];
    }
}

if (!function_exists('get_post_status')) {
    function get_post_status($id) {
        global $nhhb_test_post_status;
        return isset($nhhb_test_post_status[(int) $id]) ? $nhhb_test_post_status[(int) $id] : 'publish';
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($id) {
        global $nhhb_test_titles;
        return isset($nhhb_test_titles[(int) $id]) ? $nhhb_test_titles[(int) $id] : 'Product ' . (int) $id;
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($id) {
        return 'https://example.test/product/' . (int) $id . '/';
    }
}

require_once dirname(__DIR__) . '/includes/reviews.php';

$failures = 0;

function nhhb_assert($label, $ok) {
    global $failures;
    if ($ok) {
        echo "ok  {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL  {$label}\n";
}

nhhb_assert('5 is five-star', nhhb_review_is_five_star(5) === true);
nhhb_assert('string 5 is five-star', nhhb_review_is_five_star('5') === true);
nhhb_assert('5.0 is five-star', nhhb_review_is_five_star('5.0') === true);
nhhb_assert('4 is not five-star', nhhb_review_is_five_star(4) === false);
nhhb_assert('empty rating is not five-star', nhhb_review_is_five_star('') === false);

nhhb_assert(
    'plain comment is kept',
    nhhb_review_comment_text('  Easy to assemble.  ') === 'Easy to assemble.'
);
nhhb_assert(
    'html comment is stripped',
    nhhb_review_comment_text('<p>Great <strong>quality</strong></p>') === 'Great quality'
);
nhhb_assert(
    'empty paragraph is rating-only',
    nhhb_review_comment_text('<p>&nbsp;</p>') === ''
);
nhhb_assert(
    'br-only content is rating-only',
    nhhb_review_comment_text('<br>') === ''
);
nhhb_assert('empty string is rating-only', nhhb_review_comment_text('') === '');

nhhb_assert(
    '5-star with comment is included',
    nhhb_review_should_include(5, 'Loved it') === true
);
nhhb_assert(
    '5-star without comment is skipped',
    nhhb_review_should_include(5, '   ') === false
);
nhhb_assert(
    '4-star with comment is skipped',
    nhhb_review_should_include(4, 'Loved it') === false
);

nhhb_assert('two-word initials', nhhb_review_initials('Anna Berg') === 'AB');
nhhb_assert('single-name initial', nhhb_review_initials('Anna') === 'A');
nhhb_assert('empty name fallback', nhhb_review_initials('') === '?');
nhhb_assert('whitespace name fallback', nhhb_review_initials('   ') === '?');
nhhb_assert('first and last initials', nhhb_review_initials('Jean Luc Picard') === 'JP');
nhhb_assert('unicode initials', nhhb_review_initials('Åsa Lind') === 'ÅL');
nhhb_assert('avatar tone is stable', nhhb_review_avatar_tone('Anna') === nhhb_review_avatar_tone('Anna'));

$nhhb_test_comment_meta = [
    11 => ['rating' => '5', 'verified' => '1'],
    12 => ['rating' => '5'],
    13 => ['rating' => '4'],
    14 => ['rating' => '5'],
];
$nhhb_test_post_status = [101 => 'publish', 102 => 'draft'];
$nhhb_test_titles = [101 => 'Greenhouse 6x8'];

$with_text = (object) [
    'comment_ID'      => 11,
    'comment_content' => '<p>Excellent greenhouse, easy to assemble.</p>',
    'comment_author'  => 'Anna Berg',
    'comment_post_ID' => 101,
];
$row = nhhb_prepare_review($with_text);
nhhb_assert('usable review is prepared', is_array($row));
nhhb_assert('author is kept', $row && $row['author'] === 'Anna Berg');
nhhb_assert('initials from name', $row && $row['initials'] === 'AB');
nhhb_assert('comment text extracted', $row && $row['text'] === 'Excellent greenhouse, easy to assemble.');
nhhb_assert('verified purchase flagged', $row && $row['verified'] === true);
nhhb_assert('product title attached', $row && $row['product'] === 'Greenhouse 6x8');

$rating_only = (object) [
    'comment_ID'      => 12,
    'comment_content' => '<p></p>',
    'comment_author'  => 'Bo',
    'comment_post_ID' => 101,
];
nhhb_assert('rating-only review is skipped', nhhb_prepare_review($rating_only) === null);

$four_star = (object) [
    'comment_ID'      => 13,
    'comment_content' => 'Nice but pricey',
    'comment_author'  => 'Bo',
    'comment_post_ID' => 101,
];
nhhb_assert('non-five-star review is skipped', nhhb_prepare_review($four_star) === null);

$draft_product = (object) [
    'comment_ID'      => 14,
    'comment_content' => 'Great product',
    'comment_author'  => 'Bo',
    'comment_post_ID' => 102,
];
nhhb_assert('review on unpublished product is skipped', nhhb_prepare_review($draft_product) === null);

$no_name = (object) [
    'comment_ID'      => 11,
    'comment_content' => 'Perfect',
    'comment_author'  => '',
    'comment_post_ID' => 101,
];
$anon = nhhb_prepare_review($no_name);
nhhb_assert('missing author uses Customer', $anon && $anon['author'] === 'Customer');
nhhb_assert('missing author still has initials', $anon && $anon['initials'] === 'C');

$avatar = nhhb_review_avatar_html([
    'author'   => 'Anna Berg',
    'initials' => 'AB',
    'tone'     => 'green',
]);
nhhb_assert('avatar uses initials not an img', strpos($avatar, '<img') === false && strpos($avatar, 'AB') !== false);

if ($failures) {
    echo "\n{$failures} failing\n";
    exit(1);
}

echo "\nAll tests passed\n";
exit(0);
