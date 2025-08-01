<?php
// 1) Shortcode: form + results container
add_shortcode('live_product_search','nrh_live_search_form');
function nrh_live_search_form(){
  return '
    <div class="nrh-live-search">
      <input type="search" id="nrh-search-input" placeholder="Search products…" autocomplete="off"/>
      <ul id="nrh-search-results" class="nrh-live-search-results"></ul>
    </div>
  ';
}

// 2) AJAX handler
add_action('wp_ajax_nopriv_nrh_live_search','nrh_live_search_callback');
add_action('wp_ajax_nrh_live_search','nrh_live_search_callback');
function nrh_live_search_callback(){
  global $wpdb;
  $term = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
  if(mb_strlen($term)<2){
    wp_send_json([]);
  }
  $like = '%'.$wpdb->esc_like($term).'%';
  $sql = $wpdb->prepare("
    SELECT p.ID
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm
      ON pm.post_id = p.ID AND pm.meta_key = '_sku'
    WHERE p.post_type='product'
      AND p.post_status='publish'
      AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)
    GROUP BY p.ID
    LIMIT 6
  ", $like, $like);
  $ids = $wpdb->get_col($sql);
  if(empty($ids)){
    wp_send_json([]);
  }
  $results = [];
  foreach($ids as $id){
    $prod = wc_get_product($id);
    $results[] = [
      'title'=>get_the_title($id),
      'link'=>get_permalink($id),
      'img'=>get_the_post_thumbnail_url($id,'thumbnail')?:wc_placeholder_img_src(),
      'price'=>$prod->get_price_html(),
    ];
  }
  wp_send_json($results);
}

// 3) Enqueue JS & localize
add_action('wp_enqueue_scripts', function(){
  // load on every front-end page
  wp_enqueue_script(
    'nrh-live-search',
    get_stylesheet_directory_uri() . '/assets/js/live-search.js',
    ['jquery'],
    '1.0',
    true
  );
  wp_localize_script('nrh-live-search','nrh_live_search',[
    'ajax_url'=> admin_url('admin-ajax.php'),
    'action'  => 'nrh_live_search',
  ]);
});
