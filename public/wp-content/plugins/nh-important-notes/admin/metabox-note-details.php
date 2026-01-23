<?php
use NHIN\Plugin;

if (!defined('ABSPATH')) exit;

wp_nonce_field('nh_note_save', 'nh_note_nonce');

$type = (string) get_post_meta($post->ID, Plugin::META_NOTE_TYPE, true);
$text = (string) get_post_meta($post->ID, Plugin::META_NOTE_TEXT, true);

$types = $plugin->note_types();
?>
<p>
	<label for="nh_note_type"><strong><?php esc_html_e('Icon', 'nh-important-notes'); ?></strong></label><br>
	<select name="nh_note_type" id="nh_note_type" style="min-width:280px;">
		<?php foreach ($types as $key => $label): ?>
			<option value="<?php echo esc_attr($key); ?>" <?php selected($type, $key); ?>>
				<?php echo esc_html($label); ?>
			</option>
		<?php endforeach; ?>
	</select>
</p>

<p>
	<label for="nh_note_text"><strong><?php esc_html_e('Text', 'nh-important-notes'); ?></strong></label><br>
	<textarea name="nh_note_text" id="nh_note_text" rows="4" style="width:100%;"><?php echo esc_textarea($text); ?></textarea>
	<em style="display:block;margin-top:6px;color:#666;">
		<?php esc_html_e('Shown after the bold title, e.g. "3 € per sheet."', 'nh-important-notes'); ?>
	</em>
</p>
