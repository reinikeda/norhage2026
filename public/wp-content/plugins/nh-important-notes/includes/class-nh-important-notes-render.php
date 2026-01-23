<?php
namespace NHIN;

if (!defined('ABSPATH')) exit;

final class Render {
	private Plugin $plugin;

	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;
	}

	public function hooks(): void {
		add_filter('the_content', [$this, 'append_notes_to_product_description'], 20);
	}

	public function append_notes_to_product_description(string $content): string {
		if (!function_exists('is_product') || !is_product()) return $content;
		if (!in_the_loop() || !is_main_query()) return $content;

		global $post;
		if (!$post || $post->post_type !== 'product') return $content;

		$note_ids = get_post_meta($post->ID, Plugin::META_PRODUCT_NOTES, true);
		if (!is_array($note_ids) || empty($note_ids)) return $content;

		$items_html = '';

		foreach ($note_ids as $id) {
			$id = (int) $id;
			$note = get_post($id);
			if (!$note || $note->post_type !== Plugin::CPT_NOTE || $note->post_status !== 'publish') continue;

			$type  = (string) get_post_meta($id, Plugin::META_NOTE_TYPE, true);
			$text  = (string) get_post_meta($id, Plugin::META_NOTE_TEXT, true);
			$icon  = $this->plugin->dashicon_for_type($type);
			$title = get_the_title($note);

			$items_html .= '<li class="nh-in-item">'
				. '<span class="nh-in-ico dashicons ' . esc_attr($icon) . '" aria-hidden="true"></span>'
				. '<span class="nh-in-txt"><strong>' . esc_html($title) . ':</strong> ' . wp_kses_post($text) . '</span>'
				. '</li>';
		}

		if ($items_html === '') return $content;

		$block =
			'<section class="nh-important-notes" aria-label="' . esc_attr__('Important notes', 'nh-important-notes') . '">'
			. '<div class="nh-in-head">'
			. '<span class="nh-in-head-ico dashicons dashicons-info-outline" aria-hidden="true"></span>'
			. '<h3 class="nh-in-title">' . esc_html__('Important information', 'nh-important-notes') . '</h3>'
			. '</div>'
			. '<ul class="nh-in-list">' . $items_html . '</ul>'
			. '</section>';

		return $content . $block;
	}
}
