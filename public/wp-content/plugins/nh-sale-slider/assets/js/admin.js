/* global wp */
jQuery(function ($) {
  // Open WP Media for the field whose button was clicked
  $(document).on('click', '.nhss-media-select', function (e) {
    e.preventDefault();
    var $wrap = $(this).closest('.nhss-media-wrap');
    var frame = wp.media({
      title: 'Select image',
      library: { type: 'image' },
      multiple: false
    });

    frame.on('select', function () {
      var att = frame.state().get('selection').first().toJSON();
      $wrap.find('input.nhss-url').val(att.url).trigger('change');
      $wrap.find('img.nhss-preview').attr('src', att.url).show();
    });

    frame.open();
  });

  $(document).on('click', '.nhss-media-remove', function (e) {
    e.preventDefault();
    var $wrap = $(this).closest('.nhss-media-wrap');
    $wrap.find('input.nhss-url').val('').trigger('change');
    $wrap.find('img.nhss-preview').attr('src', '').hide();
  });
});
