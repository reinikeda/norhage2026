<?php
use NHIN\Plugin;

if (!defined('ABSPATH')) exit;

wp_nonce_field('nh_product_notes_save', 'nh_product_notes_nonce');

$selected = get_post_meta($post->ID, Plugin::META_PRODUCT_NOTES, true);
if (!is_array($selected)) $selected = [];

$notes = get_posts([
	'post_type'   => Plugin::CPT_NOTE,
	'post_status' => 'publish',
	'numberposts' => -1,
	'orderby'     => 'title',
	'order'       => 'ASC',
]);

$by_id = [];
foreach ($notes as $n) $by_id[$n->ID] = $n;

// Render saved order first
$ordered = [];
foreach ($selected as $id) {
	$id = (int) $id;
	if (isset($by_id[$id])) $ordered[] = $by_id[$id];
}
foreach ($notes as $n) {
	if (!in_array($n->ID, $selected, true)) $ordered[] = $n;
}
?>
<p class="nh-in-admin-help">
	<?php esc_html_e('Check notes to show on the product page. Drag to reorder checked notes.', 'nh-important-notes'); ?>
</p>

<ul id="nh-notes-sortable" class="nh-in-admin-list">
	<?php foreach ($ordered as $note):
		$id = (int) $note->ID;
		$is_checked = in_array($id, $selected, true);

		$type = (string) get_post_meta($id, Plugin::META_NOTE_TYPE, true);
		$icon = $plugin->dashicon_for_type($type);
		$preview = wp_trim_words(wp_strip_all_tags((string) get_post_meta($id, Plugin::META_NOTE_TEXT, true)), 10);
	?>
	<li class="nh-note-item">
		<label class="nh-note-row">
			<input type="checkbox" name="nh_product_note_ids[]" value="<?php echo esc_attr($id); ?>" <?php checked($is_checked); ?>>
			<span class="dashicons <?php echo esc_attr($icon); ?>"></span>
			<span class="nh-note-text">
				<strong><?php echo esc_html(get_the_title($note)); ?></strong>
				<span class="nh-note-preview"><?php echo esc_html($preview); ?></span>
			</span>
		</label>

		<input type="hidden" class="nh-note-order" name="nh_note_order[]" value="<?php echo esc_attr($id); ?>">
	</li>
	<?php endforeach; ?>
</ul>
