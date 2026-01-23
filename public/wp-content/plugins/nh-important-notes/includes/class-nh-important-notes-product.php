<?php
namespace NHIN;

if (!defined('ABSPATH')) exit;

final class Product {
	private Plugin $plugin;

	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;
	}

	public function hooks(): void {
		add_action('save_post_product', [$this, 'save_product_notes'], 10, 2);
	}

	public function save_product_notes(int $post_id, \WP_Post $post): void {
		if (!isset($_POST['nh_product_notes_nonce']) || !wp_verify_nonce($_POST['nh_product_notes_nonce'], 'nh_product_notes_save')) return;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (!current_user_can('edit_post', $post_id)) return;

		$order = isset($_POST['nh_note_order']) && is_array($_POST['nh_note_order'])
			? array_map('intval', $_POST['nh_note_order'])
			: [];

		$checked = isset($_POST['nh_product_note_ids']) && is_array($_POST['nh_product_note_ids'])
			? array_map('intval', $_POST['nh_product_note_ids'])
			: [];

		$checked_lookup = array_flip($checked);
		$final = [];

		foreach ($order as $id) {
			if (isset($checked_lookup[$id])) $final[] = $id;
		}

		update_post_meta($post_id, Plugin::META_PRODUCT_NOTES, $final);
	}
}
