<?php
/**
 * Five-star review helpers for the homepage slider.
 *
 * Rating-only reviews (stars, no written comment) are skipped so the
 * slider is not filled with empty quote cards.
 *
 * WooCommerce does not collect reviewer photos. Initials are used instead
 * of Gravatar so guest reviews stay consistent and no third-party request
 * is made for an image that usually does not exist.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * True when the stored WooCommerce rating is 5 stars.
 *
 * @param mixed $rating Comment meta value.
 */
function nhhb_review_is_five_star($rating) {
    if ($rating === '' || $rating === null) {
        return false;
    }
    return (int) round((float) $rating) === 5;
}

/**
 * Plain-text review body. Empty after stripping means "rating only".
 *
 * @param mixed $content Comment content.
 */
function nhhb_review_comment_text($content) {
    $text = (string) $content;
    if ($text === '') {
        return '';
    }

    $text = preg_replace('/<br\s*\/?>/i', ' ', $text);
    if (function_exists('wp_strip_all_tags')) {
        $text = wp_strip_all_tags($text, true);
    } else {
        $text = trim(strip_tags($text));
    }

    $flags = ENT_QUOTES;
    if (defined('ENT_HTML5')) {
        $flags |= ENT_HTML5;
    } elseif (defined('ENT_HTML401')) {
        $flags |= ENT_HTML401;
    }
    $text = html_entity_decode($text, $flags, 'UTF-8');
    $text = str_replace("\xC2\xA0", ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);

    return $text;
}

/**
 * Include in the slider only when it is 5 stars and has a written comment.
 *
 * @param mixed  $rating  Comment rating meta.
 * @param mixed  $content Comment content.
 */
function nhhb_review_should_include($rating, $content) {
    return nhhb_review_is_five_star($rating) && nhhb_review_comment_text($content) !== '';
}

/**
 * One or two initials from a display name. Falls back to "?" when empty.
 *
 * @param mixed $name Reviewer name.
 */
function nhhb_review_initials($name) {
    $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
    if ($name === '') {
        return '?';
    }

    $parts = preg_split('/\s+/u', $name);
    $parts = array_values(array_filter($parts, static function ($part) {
        return $part !== '';
    }));

    if (count($parts) >= 2) {
        return nhhb_review_first_letter($parts[0]) . nhhb_review_first_letter($parts[count($parts) - 1]);
    }

    return nhhb_review_first_letter($parts[0]);
}

/**
 * Uppercase first letter, Unicode-safe when mbstring is available.
 *
 * @param string $str
 */
function nhhb_review_first_letter($str) {
    $str = (string) $str;
    if ($str === '') {
        return '';
    }
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($str, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return strtoupper(substr($str, 0, 1));
}

/**
 * Stable colour token so the same name keeps the same avatar tone.
 *
 * @param string $name
 */
function nhhb_review_avatar_tone($name) {
    $tones = ['forest', 'green', 'moss', 'pine'];
    $sum = 0;
    $raw = (string) $name;
    $len = strlen($raw);
    for ($i = 0; $i < $len; $i++) {
        $sum += ord($raw[$i]);
    }
    return $tones[$sum % count($tones)];
}

/**
 * Cache-busting version included in the transient key.
 */
function nhhb_reviews_cache_ver() {
    if (!function_exists('get_option')) {
        return 1;
    }
    return max(1, (int) get_option('nhhb_reviews_cache_ver', 1));
}

/**
 * Invalidate cached slider reviews after comment changes.
 */
function nhhb_reviews_flush_cache() {
    if (!function_exists('update_option')) {
        return;
    }
    update_option('nhhb_reviews_cache_ver', nhhb_reviews_cache_ver() + 1, false);
}

/**
 * Build a slider row from a comment object, or null if it should be skipped.
 *
 * @param object $comment WP_Comment-like object.
 * @return array<string, mixed>|null
 */
function nhhb_prepare_review($comment) {
    if (!is_object($comment) || empty($comment->comment_ID)) {
        return null;
    }

    $rating = '';
    if (function_exists('get_comment_meta')) {
        $rating = get_comment_meta((int) $comment->comment_ID, 'rating', true);
    } elseif (isset($comment->rating)) {
        $rating = $comment->rating;
    }

    $content = isset($comment->comment_content) ? $comment->comment_content : '';
    if (!nhhb_review_should_include($rating, $content)) {
        return null;
    }

    $author = '';
    if (isset($comment->comment_author)) {
        $author = function_exists('wp_strip_all_tags')
            ? wp_strip_all_tags((string) $comment->comment_author)
            : trim(strip_tags((string) $comment->comment_author));
    }
    $author = trim($author);
    if ($author === '') {
        $author = function_exists('__') ? __('Customer', 'nhhb') : 'Customer';
    }

    $product_id = isset($comment->comment_post_ID) ? (int) $comment->comment_post_ID : 0;
    $product    = '';
    $url        = '';
    if ($product_id && function_exists('get_post_status')) {
        if (get_post_status($product_id) !== 'publish') {
            return null;
        }
    }
    if ($product_id && function_exists('get_the_title')) {
        $product = (string) get_the_title($product_id);
    }
    if ($product_id && function_exists('get_permalink')) {
        $url = (string) get_permalink($product_id);
    }

    $verified = false;
    if (function_exists('wc_review_is_from_verified_owner')) {
        $verified = (bool) wc_review_is_from_verified_owner((int) $comment->comment_ID);
    } elseif (function_exists('get_comment_meta')) {
        $verified = (bool) get_comment_meta((int) $comment->comment_ID, 'verified', true);
    } elseif (isset($comment->verified)) {
        $verified = (bool) $comment->verified;
    }

    return [
        'id'       => (int) $comment->comment_ID,
        'author'   => $author,
        'initials' => nhhb_review_initials($author),
        'tone'     => nhhb_review_avatar_tone($author),
        'text'     => nhhb_review_comment_text($content),
        'product'  => $product,
        'url'      => $url,
        'verified' => $verified,
    ];
}

/**
 * Approved 5-star product reviews that include a written comment.
 *
 * @param int $limit
 * @return array<int, array<string, mixed>>
 */
function nhhb_query_five_star_reviews($limit = 8) {
    $limit = max(1, min(24, (int) $limit));
    if (!function_exists('get_comments')) {
        return [];
    }

    $locale = function_exists('determine_locale') ? determine_locale() : 'en';
    $key    = 'nhhb_5star_' . sanitize_key((string) $locale) . '_' . $limit . '_v' . nhhb_reviews_cache_ver();

    if (function_exists('get_transient')) {
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $comments = get_comments([
        'status'        => 'approve',
        'type'          => 'review',
        'post_type'     => 'product',
        'meta_key'      => 'rating',
        'meta_value'    => '5',
        'number'        => min(100, $limit * 5),
        'orderby'       => 'comment_date_gmt',
        'order'         => 'DESC',
        'no_found_rows' => true,
    ]);

    $out = [];
    foreach ((array) $comments as $comment) {
        $row = nhhb_prepare_review($comment);
        if (!$row) {
            continue;
        }
        $out[] = $row;
        if (count($out) >= $limit) {
            break;
        }
    }

    if (function_exists('set_transient')) {
        $ttl = defined('MINUTE_IN_SECONDS') ? 10 * MINUTE_IN_SECONDS : 600;
        set_transient($key, $out, $ttl);
    }

    return $out;
}

/**
 * Decorative 5-star row. Rating is already known to be 5.
 */
function nhhb_review_stars_html() {
    $star = '<svg class="nhhb-rev-star" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
    $label = function_exists('esc_attr__') ? esc_attr__('Rated 5 out of 5 stars', 'nhhb') : 'Rated 5 out of 5 stars';
    return '<span class="nhhb-rev-stars" aria-label="' . $label . '">' . str_repeat($star, 5) . '</span>';
}

/**
 * Initials avatar. No Gravatar / photo request.
 *
 * @param array<string, mixed> $review
 */
function nhhb_review_avatar_html($review) {
    $initials = isset($review['initials']) ? (string) $review['initials'] : '?';
    $tone     = isset($review['tone']) ? (string) $review['tone'] : 'forest';
    $author   = isset($review['author']) ? (string) $review['author'] : '';

    $esc_tone = function_exists('esc_attr') ? esc_attr($tone) : htmlspecialchars($tone, ENT_QUOTES, 'UTF-8');
    $esc_init = function_exists('esc_html') ? esc_html($initials) : htmlspecialchars($initials, ENT_QUOTES, 'UTF-8');
    $label    = $author !== ''
        ? sprintf(function_exists('__') ? __('%s avatar', 'nhhb') : '%s avatar', $author)
        : (function_exists('__') ? __('Customer avatar', 'nhhb') : 'Customer avatar');
    $esc_label = function_exists('esc_attr') ? esc_attr($label) : htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

    return '<span class="nhhb-rev-avatar" data-tone="' . $esc_tone . '" aria-label="' . $esc_label . '">'
        . '<span class="nhhb-rev-initials" aria-hidden="true">' . $esc_init . '</span>'
        . '</span>';
}

if (function_exists('add_action')) {
    add_action('comment_post', 'nhhb_reviews_flush_cache');
    add_action('edit_comment', 'nhhb_reviews_flush_cache');
    add_action('deleted_comment', 'nhhb_reviews_flush_cache');
    add_action('trashed_comment', 'nhhb_reviews_flush_cache');
    add_action('spammed_comment', 'nhhb_reviews_flush_cache');
    add_action('unspammed_comment', 'nhhb_reviews_flush_cache');
    add_action('transition_comment_status', 'nhhb_reviews_flush_cache');
    add_action('woocommerce_rest_insert_product_review', 'nhhb_reviews_flush_cache');
}
