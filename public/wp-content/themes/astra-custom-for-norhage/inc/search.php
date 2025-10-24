<?php
/**
 * Live Product Search (Shortcode + AJAX)
 * - Shortcode renders input + empty results <ul>
 * - AJAX searches: title/content, SKU (incl. variations → parent), product tags ONLY
 * - Returns up to 6 results, sorted by relevance then date desc
 */

// 1) Shortcode: form + results container
add_shortcode('live_product_search', 'nrh_live_search_form');
function nrh_live_search_form(){
  return '
    <div class="nrh-live-search">
      <input type="search" id="nrh-search-input" placeholder="Search products…" autocomplete="off"/>
      <ul id="nrh-search-results" class="nrh-live-search-results"></ul>
    </div>
  ';
}

// 2) AJAX handler (public + logged-in)
add_action('wp_ajax_nopriv_nrh_live_search', 'nrh_live_search_callback');
add_action('wp_ajax_nrh_live_search', 'nrh_live_search_callback');

function nrh_live_search_callback(){
  global $wpdb;

  // Basic input guard
  $term = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
  if (mb_strlen($term) < 2) {
    wp_send_json([]);
  }

  // Optional: mild HTTP caching guard for AJAX endpoints
  nocache_headers();

  $like        = '%' . $wpdb->esc_like($term) . '%';
  $found       = [];  // product_id => weight
  $limit_each  = 40;  // soft cap per source before merging
  $final_limit = 6;   // items returned to UI

  /* ----------------------------------------
   * A) Title/Content search (WP_Query 's')
   *    Excludes "exclude-from-search" visibility
   * --------------------------------------*/
  $q_title = new WP_Query([
    'post_type'           => 'product',
    'post_status'         => 'publish',
    's'                   => $term,
    'posts_per_page'      => $limit_each,
    'fields'              => 'ids',
    'ignore_sticky_posts' => true,
    'tax_query'           => [
      [
        'taxonomy' => 'product_visibility',
        'field'    => 'name',
        'terms'    => ['exclude-from-search'],
        'operator' => 'NOT IN',
      ],
    ],
  ]);

  if ($q_title->have_posts()) {
    foreach ($q_title->posts as $pid) {
      $found[$pid] = max($found[$pid] ?? 0, 30); // title/content relevance
    }
  }
  wp_reset_postdata();

  /* ----------------------------------------
   * B) SKU search (products + variations → parent)
   *    - Direct product SKU match
   *    - Variation SKU match returns parent product
   * --------------------------------------*/
  // Direct product SKUs
  $sku_product_ids = $wpdb->get_col(
    $wpdb->prepare("
      SELECT pm.post_id
      FROM {$wpdb->postmeta} pm
      INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
      WHERE pm.meta_key = '_sku'
        AND pm.meta_value LIKE %s
        AND p.post_type = 'product'
        AND p.post_status = 'publish'
      LIMIT %d
    ", $like, $limit_each)
  );

  // Variation SKUs → parent product IDs
  $sku_variation_parent_ids = $wpdb->get_col(
    $wpdb->prepare("
      SELECT DISTINCT p.post_parent
      FROM {$wpdb->postmeta} pm
      INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
      WHERE pm.meta_key = '_sku'
        AND pm.meta_value LIKE %s
        AND p.post_type = 'product_variation'
        AND p.post_status = 'publish'
        AND p.post_parent > 0
      LIMIT %d
    ", $like, $limit_each)
  );

  foreach (array_merge($sku_product_ids, $sku_variation_parent_ids) as $pid) {
    if (get_post_type($pid) === 'product' && get_post_status($pid) === 'publish') {
      $found[$pid] = max($found[$pid] ?? 0, 50); // SKU is most relevant
    }
  }

  /* ----------------------------------------
   * C) TAG search only (product_tag by term NAME)
   *    (No categories per your request)
   * --------------------------------------*/
  $tag_object_ids = $wpdb->get_col(
    $wpdb->prepare("
      SELECT DISTINCT tr.object_id
      FROM {$wpdb->terms} t
      INNER JOIN {$wpdb->term_taxonomy} tt
              ON tt.term_id = t.term_id AND tt.taxonomy = 'product_tag'
      INNER JOIN {$wpdb->term_relationships} tr
              ON tr.term_taxonomy_id = tt.term_taxonomy_id
      INNER JOIN {$wpdb->posts} p
              ON p.ID = tr.object_id
      WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND t.name LIKE %s
      LIMIT %d
    ", $like, $limit_each)
  );

  foreach ($tag_object_ids as $pid) {
    $found[$pid] = max($found[$pid] ?? 0, 20); // tag name match relevance
  }

  /* ----------------------------------------
   * D) Merge, sort by relevance (then by date), slice
   * --------------------------------------*/
  if (empty($found)) {
    wp_send_json([]);
  }

  $ids = array_keys($found);

  // Sort by weight desc; tie-breaker: newer first
  usort($ids, function($a, $b) use ($found){
    $wa = $found[$a];
    $wb = $found[$b];
    if ($wa !== $wb) return $wb <=> $wa;
    return get_post_time('U', true, $b) <=> get_post_time('U', true, $a);
  });

  $ids = array_slice($ids, 0, $final_limit);

  /* ----------------------------------------
   * E) Build JSON response
   * --------------------------------------*/
  $results = [];
  foreach ($ids as $id) {
    $prod = wc_get_product($id);
    if (!$prod) continue;

    $results[] = [
      'title' => get_the_title($id),
      'link'  => get_permalink($id),
      'img'   => get_the_post_thumbnail_url($id, 'thumbnail') ?: wc_placeholder_img_src(),
      'price' => $prod->get_price_html(),
    ];
  }

  wp_send_json($results);
}

// 3) Enqueue JS & localize
add_action('wp_enqueue_scripts', function(){
  // Load on every front-end page
  wp_enqueue_script(
    'nrh-live-search',
    get_stylesheet_directory_uri() . '/assets/js/live-search.js',
    ['jquery'],
    '1.1', // bumped for cache-busting after handler changes
    true
  );

  wp_localize_script('nrh-live-search', 'nrh_live_search', [
    'ajax_url' => admin_url('admin-ajax.php'),
    'action'   => 'nrh_live_search',
  ]);
});
