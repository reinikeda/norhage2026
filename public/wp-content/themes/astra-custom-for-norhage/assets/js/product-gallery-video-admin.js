(function ($) {
  'use strict';

  var frame;
  var i18n = window.nhGalleryVideo || {};

  function field() {
    return $('#nh_gallery_video_id');
  }

  function showFile(id, filename) {
    var $input = field();
    var $file = $('.nh-gallery-video-admin__file');
    var $name = $('.nh-gallery-video-admin__name');
    $input.val(id || '');
    $('.nh-gallery-video-admin__heavy').prop('hidden', true);
    if (id && filename) {
      $name.text(filename);
      $file.prop('hidden', false);
      $('#nh-gallery-video-remove').prop('hidden', false);
      $('#nh-gallery-video-select').text(i18n.replace || 'Replace video');
      return;
    }
    $name.text('');
    $file.prop('hidden', true);
    $('#nh-gallery-video-remove').prop('hidden', true);
    $('#nh-gallery-video-select').text(i18n.select || 'Select video');
  }

  function mimeOf(attachment) {
    if (attachment.mime) return attachment.mime;
    if (attachment.subtype) return 'video/' + attachment.subtype;
    return '';
  }

  $('#nh-gallery-video-select').on('click', function (event) {
    event.preventDefault();
    if (!window.wp || !wp.media) return;

    if (frame) {
      frame.open();
      return;
    }

    frame = wp.media({
      title: i18n.title || 'Video gallery',
      button: { text: i18n.button || 'Use this video' },
      library: { type: 'video/mp4' },
      multiple: false
    });

    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      if (mimeOf(attachment) !== 'video/mp4') {
        window.alert(i18n.onlyMp4 || 'Choose an MP4 file.');
        return;
      }
      var bytes = parseInt(attachment.filesizeInBytes || attachment.filesize || 0, 10);
      if (bytes > (parseInt(i18n.limit, 10) || 15728640)) {
        window.alert(i18n.heavy || 'This file is over 15 MB.');
      }
      showFile(attachment.id, attachment.filename || attachment.title || '');
    });

    frame.open();
  });

  $('#nh-gallery-video-remove').on('click', function (event) {
    event.preventDefault();
    showFile('', '');
  });

  var $gallery = $('#woocommerce-product-images');
  var $box = $('#nh_gallery_video_box');
  if ($gallery.length && $box.length) {
    $box.insertAfter($gallery);
  }
})(jQuery);
