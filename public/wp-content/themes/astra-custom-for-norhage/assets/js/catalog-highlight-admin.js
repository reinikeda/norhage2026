/**
 * Catalog highlight — Quick Edit checkbox sync.
 */
(function ($) {
	'use strict';

	$('#the-list').on('click', '.editinline', function () {
		var $row = $(this).closest('tr');
		var postId = $row.attr('id');
		if (!postId) {
			return;
		}
		postId = postId.replace('post-', '');

		var $inline = $('#nh_catalog_highlight_inline_' + postId);
		var isOn = $inline.length
			? $inline.text().trim() === 'yes'
			: $row.find('.column-nh_catalog_highlight .nh-catalog-highlight-col--yes').length > 0;

		var $edit = $('.inline-edit-row');
		$edit.find('input.nh-catalog-highlight-quick-edit').prop('checked', isOn);
	});
})(jQuery);
