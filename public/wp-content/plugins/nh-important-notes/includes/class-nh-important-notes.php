<?php
namespace NHIN;

if (!defined('ABSPATH')) exit;

final class Plugin {
	const CPT_NOTE = 'nh_note';

	const META_NOTE_TYPE = '_nh_note_type';
	const META_NOTE_TEXT = '_nh_note_text';
	const META_PRODUCT_NOTES = '_nh_product_note_ids';

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if (self::$instance === null) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		require_once NH_IN_PLUGIN_PATH . 'includes/class-nh-important-notes-cpt.php';
		require_once NH_IN_PLUGIN_PATH . 'includes/class-nh-important-notes-product.php';
		require_once NH_IN_PLUGIN_PATH . 'includes/class-nh-important-notes-render.php';
		require_once NH_IN_PLUGIN_PATH . 'admin/class-nh-important-notes-admin.php';
		require_once NH_IN_PLUGIN_PATH . 'public/class-nh-important-notes-public.php';

		(new CPT($this))->hooks();
		(new Product($this))->hooks();
		(new Render($this))->hooks();
		(new \NHIN\Admin\Admin($this))->hooks();
		(new \NHIN\PublicSite\PublicSite($this))->hooks();
	}

	/** Fixed icon list (extend later). */
	public function note_types(): array {
		return [
			'warranty'          => __('Warranty', 'nh-important-notes'),
			'handling'          => __('Handling', 'nh-important-notes'),
			'storage'           => __('Storage', 'nh-important-notes'),
			'cutting_tolerance' => __('Cutting tolerance', 'nh-important-notes'),
			'cutting_fee'       => __('Cutting fee', 'nh-important-notes'),
			'standard_width'    => __('Standard width', 'nh-important-notes'),
			'regulations'       => __('Regulations', 'nh-important-notes'),
		];
	}

	/** Dashicons mapping (simple + consistent; can switch to SVG later). */
	public function dashicon_for_type(string $type): string {
		$map = [
			'warranty'          => 'dashicons-awards',
			'handling'          => 'dashicons-hammer',
			'storage'           => 'dashicons-archive',
			'cutting_tolerance' => 'dashicons-editor-contract',
			'cutting_fee'       => 'dashicons-money-alt',
			'standard_width'    => 'dashicons-editor-table',
			'regulations'       => 'dashicons-clipboard',
		];
		return $map[$type] ?? 'dashicons-info-outline';
	}

	public function sanitize_note_type(string $type): string {
		$allowed = array_keys($this->note_types());
		return in_array($type, $allowed, true) ? $type : 'warranty';
	}
}
