<?php
namespace NHIN\PublicSite;

if (!defined('ABSPATH')) exit;

final class PublicSite {
	private \NHIN\Plugin $plugin;

	public function __construct(\NHIN\Plugin $plugin) {
		$this->plugin = $plugin;
	}

	public function hooks(): void {
		add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
	}

	public function enqueue_public_assets(): void {
		// Needed for dashicons on the frontend
		wp_enqueue_style('dashicons');

		wp_enqueue_style(
			'nh-important-notes-public',
			NH_IN_PLUGIN_URL . 'assets/public/public.css',
			[],
			NH_IN_PLUGIN_VERSION
		);
	}
}
