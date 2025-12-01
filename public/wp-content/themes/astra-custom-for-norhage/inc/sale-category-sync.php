<?php
/**
 * Auto “Sale” Category Sync
 * Keeps the product category with a language-specific slug in sync
 * with products that are on sale.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NHG_Sale_Category_Sync {

	/**
	 * Fallback slug if locale is not mapped.
	 */
	const DEFAULT_CAT_SLUG  = 'sale';

	const CRON_HOOK = 'nhg_sync_sale_category';

	public function __construct() {
		// When products are saved/updated
		add_action( 'save_post_product',          [ $this, 'sync_single' ], 20 );
		add_action( 'woocommerce_update_product', [ $this, 'sync_single' ], 20 );

		// Hourly sweep for scheduled start/end
		add_action( self::CRON_HOOK, [ $this, 'sync_all' ] );

		// Schedule cron on init (theme-safe)
		add_action( 'init', [ $this, 'maybe_schedule_cron' ] );

		// Unschedule if theme is switched
		add_action( 'switch_theme', [ __CLASS__, 'unschedule' ] );

		// One-time bootstrap full sync after first include (optional but handy)
		add_action( 'admin_init', [ $this, 'maybe_bootstrap_once' ] );
	}

	/** Ensure cron is scheduled (theme context) */
	public function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'hourly', self::CRON_HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Get the correct slug for the current site language.
	 * Adjust the map below for your actual languages/slugs.
	 */
	private function get_sale_slug() {
		$locale = get_locale(); // e.g. 'lt_LT', 'nb_NO', 'sv_SE', 'de_DE', 'fi_FI'

		$map = [
			// Lithuanian
			'lt_LT' => 'ispardavimas',

			// Norwegian (Bokmål) – example, adjust as needed
			'nb_NO' => 'salg',

			// Swedish – example
			'sv_SE' => 'rea',

			// Finnish – example
			'fi_FI' => 'ale',

			// German – example (you can change to 'angebote' or whatever you use)
			'de_DE' => 'sale',
		];

		$slug = isset( $map[ $locale ] ) ? $map[ $locale ] : self::DEFAULT_CAT_SLUG;

		/**
		 * Filter to allow per-site override without editing this file.
		 *
		 * @param string $slug   The resolved slug.
		 * @param string $locale Current site locale, e.g. 'lt_LT'.
		 */
		return apply_filters( 'nhg_sale_category_slug', $slug, $locale );
	}

	/** Create/get the sale term; create it if missing */
	private function get_or_create_sale_term() {
		$slug = $this->get_sale_slug();

		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			return $term;
		}

		// translators: product category name for items currently on sale
		$name = __( 'Sale', 'nh-theme' );

		$created = wp_insert_term(
			$name,
			'product_cat',
			[
				'slug' => $slug,
			]
		);

		if ( is_wp_error( $created ) ) {
			return null;
		}

		return get_term( (int) $created['term_id'], 'product_cat' );
	}

	/** Sync one product after it’s saved/updated */
	public function sync_single( $post_id ) {
		// Ignore autosaves/revisions
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( wp_is_post_revision( $post_id ) ) return;

		$term = $this->get_or_create_sale_term();
		if ( ! $term ) return;

		$product = wc_get_product( $post_id );
		if ( ! $product ) return;

		$is_on_sale = $product->is_on_sale();

		if ( $is_on_sale ) {
			wp_add_object_terms( $post_id, (int) $term->term_id, 'product_cat' );
		} else {
			wp_remove_object_terms( $post_id, (int) $term->term_id, 'product_cat' );
		}

		clean_object_term_cache( $post_id, 'product_cat' );
	}

	/** Hourly full sync (handles scheduled sale start/end) */
	public function sync_all() {
		$term = $this->get_or_create_sale_term();
		if ( ! $term ) return;

		$current_sale_ids = array_map( 'intval', wc_get_product_ids_on_sale() );

		$in_term_ids = get_objects_in_term( (int) $term->term_id, 'product_cat' );
		$in_term_ids = array_map( 'intval', (array) $in_term_ids );

		// Add any missing products that are on sale
		$to_add = array_diff( $current_sale_ids, $in_term_ids );
		foreach ( $to_add as $pid ) {
			wp_add_object_terms( $pid, (int) $term->term_id, 'product_cat' );
			clean_object_term_cache( $pid, 'product_cat' );
		}

		// Remove products no longer on sale
		$to_remove = array_diff( $in_term_ids, $current_sale_ids );
		foreach ( $to_remove as $pid ) {
			wp_remove_object_terms( $pid, (int) $term->term_id, 'product_cat' );
			clean_object_term_cache( $pid, 'product_cat' );
		}
	}

	/** Run one full sync once after installing the file */
	public function maybe_bootstrap_once() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) return;

		if ( ! get_option( 'nhg_sale_sync_bootstrapped' ) ) {
			$this->sync_all();
			update_option( 'nhg_sale_sync_bootstrapped', 1, false );
		}
	}
}

new NHG_Sale_Category_Sync();
