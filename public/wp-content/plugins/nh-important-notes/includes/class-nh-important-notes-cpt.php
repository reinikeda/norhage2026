<?php
namespace NHIN;

if (!defined('ABSPATH')) exit;

final class CPT {
	private Plugin $plugin;

	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;
	}

	public function hooks(): void {
		add_action('init', [$this, 'register_note_cpt']);
		add_action('save_post_' . Plugin::CPT_NOTE, [$this, 'save_note_meta']);
		add_filter('manage_' . Plugin::CPT_NOTE . '_posts_columns', [$this, 'admin_columns']);
		add_action('manage_' . Plugin::CPT_NOTE . '_posts_custom_column', [$this, 'admin_column_render'], 10, 2);
	}

	public function register_note_cpt(): void {
		$labels = [
			'name'          => __('Important Notes', 'nh-important-notes'),
			'singular_name' => __('Important Note', 'nh-important-notes'),
			'menu_name'     => __('Important Notes', 'nh-important-notes'),
			'add_new_item'  => __('Add New Note', 'nh-important-notes'),
			'edit_item'     => __('Edit Note', 'nh-important-notes'),
		];

		register_post_type(Plugin::CPT_NOTE, [
			'labels'        => $labels,
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_icon'     => 'dashicons-info-outline',
			'supports'      => ['title'],
			'has_archive'   => false,
			'rewrite'       => false,
		]);
	}

	public function save_note_meta(int $post_id): void {
		if (!isset($_POST['nh_note_nonce']) || !wp_verify_nonce($_POST['nh_note_nonce'], 'nh_note_save')) return;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (!current_user_can('edit_post', $post_id)) return;

		$type = isset($_POST['nh_note_type']) ? sanitize_text_field($_POST['nh_note_type']) : '';
		$text = isset($_POST['nh_note_text']) ? wp_kses_post($_POST['nh_note_text']) : '';

		$type = $this->plugin->sanitize_note_type($type);

		update_post_meta($post_id, Plugin::META_NOTE_TYPE, $type);
		update_post_meta($post_id, Plugin::META_NOTE_TEXT, $text);
	}

	public function admin_columns(array $columns): array {
		$columns['nh_note_icon'] = __('Icon', 'nh-important-notes');
		$columns['nh_note_text'] = __('Text', 'nh-important-notes');
		return $columns;
	}

	public function admin_column_render(string $column, int $post_id): void {
		if ($column === 'nh_note_icon') {
			$type = (string) get_post_meta($post_id, Plugin::META_NOTE_TYPE, true);
			$types = $this->plugin->note_types();
			echo esc_html($types[$type] ?? $type);
		}
		if ($column === 'nh_note_text') {
			$text = (string) get_post_meta($post_id, Plugin::META_NOTE_TEXT, true);
			echo esc_html(wp_trim_words(wp_strip_all_tags($text), 14));
		}
	}
}
