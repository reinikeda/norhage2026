/**
 * Catalog highlight — Quick Edit checkbox sync.
 *
 * WordPress clones the Quick Edit row after .editinline is clicked, so the
 * checkbox must be set after inlineEditPost.edit finishes.
 */
(function ($) {
	'use strict';

	function readHighlight(postId) {
		var $inline = $('#nh_catalog_highlight_inline_' + postId);
		if ($inline.length) {
			return $inline.text().trim() === 'yes';
		}
		return $('#post-' + postId)
			.find('.column-nh_catalog_highlight .nh-catalog-highlight-col--yes')
			.length > 0;
	}

	function setQuickEditCheckbox(postId) {
		var isOn = readHighlight(postId);
		var $edit = $('#edit-' + postId);
		if (!$edit.length) {
			$edit = $('.inline-edit-row.inline-editor').filter(':visible').first();
		}
		$edit.find('input.nh-catalog-highlight-quick-edit').prop('checked', !!isOn);
	}

	if (typeof inlineEditPost !== 'undefined' && inlineEditPost.edit) {
		var wpInlineEdit = inlineEditPost.edit;
		inlineEditPost.edit = function (id) {
			wpInlineEdit.apply(this, arguments);

			var postId = 0;
			if (typeof id === 'object') {
				postId = parseInt(this.getId(id), 10) || 0;
			} else {
				postId = parseInt(id, 10) || 0;
			}
			if (!postId) {
				return;
			}

			// Defer one tick so Woo/WP finish painting the editor row.
			window.setTimeout(function () {
				setQuickEditCheckbox(postId);
			}, 0);
		};
		return;
	}

	// Fallback if inlineEditPost is unavailable.
	$('#the-list').on('click', '.editinline', function () {
		var $row = $(this).closest('tr');
		var postId = ($row.attr('id') || '').replace('post-', '');
		if (!postId) {
			return;
		}
		window.setTimeout(function () {
			setQuickEditCheckbox(postId);
		}, 50);
	});
})(jQuery);
