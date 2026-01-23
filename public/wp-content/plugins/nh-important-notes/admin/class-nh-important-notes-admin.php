<?php
namespace NHIN\Admin;

use NHIN\Plugin;

if (!defined('ABSPATH')) exit;

final class Admin {
	private Plugin $plugin;

	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;
	}

	public function hooks(): void {
		add_action('add_meta_boxes', [$this, 'register_metaboxes']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
	}

	public function register_metaboxes(): void {
		// Note editor metabox
		add_meta_box(
			'nh_note_details',
			__('Note Details', 'nh-important-notes'),
			[$this, 'render_note_details_metabox'],
			Plugin::CPT_NOTE,
			'normal',
			'high'
		);

		// Product sidebar metabox
		add_meta_box(
			'nh_product_notes',
			__('Important Notes', 'nh-important-notes'),
			[$this, 'render_product_notes_metabox'],
			'product',
			'side',
			'default'
		);
	}

	public function render_note_details_metabox(\WP_Post $post): void {
		$plugin = $this->plugin;
		include NH_IN_PLUGIN_PATH . 'admin/metabox-note-details.php';
	}

	public function render_product_notes_metabox(\WP_Post $post): void {
		$plugin = $this->plugin;
		include NH_IN_PLUGIN_PATH . 'admin/metabox-product-notes.php';
	}

	public function enqueue_admin_assets(string $hook): void {
		if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;

		$screen = get_current_screen();
		if (!$screen) return;

		// Only need sortable UI for product edit screens
		if ($screen->post_type === 'product') {
			wp_enqueue_script('jquery-ui-sortable');

			wp_enqueue_style(
				'nh-important-notes-admin',
				NH_IN_PLUGIN_URL . 'assets/admin/admin.css',
				[],
				NH_IN_PLUGIN_VERSION
			);

			wp_enqueue_script(
				'nh-important-notes-admin',
				NH_IN_PLUGIN_URL . 'assets/admin/admin.js',
				['jquery', 'jquery-ui-sortable'],
				NH_IN_PLUGIN_VERSION,
				true
			);
		}
	}
}
