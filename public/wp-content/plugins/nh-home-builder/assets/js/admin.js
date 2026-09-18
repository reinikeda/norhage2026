(function ($) {
  $(document).on('click', '.nhhb-move-up, .nhhb-move-down', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var card = $(this).closest('.nhhb-section-card');
    if ($(this).hasClass('nhhb-move-up')) {
      card.prev('.nhhb-section-card').before(card);
    } else {
      card.next('.nhhb-section-card').after(card);
    }
  });

  $(document).on('click', '.nhhb-reset-order', function (e) {
    e.preventDefault();
    var list = $('.nhhb-section-list');
    var order = (list.data('default-order') || '').toString().split(',');
    order.forEach(function (type) {
      var card = list.find('.nhhb-section-card[data-type="' + type + '"]');
      if (card.length) list.append(card);
    });
  });

  let frame;
  $(document).on('click', '.nhhb-upload', function (e) {
    e.preventDefault();
    const target = $(this).data('target');
    if (frame) frame.close();
    frame = wp.media({
      title: 'Select Image',
      button: { text: 'Use This Image' },
      multiple: false
    });
    frame.on('select', function () {
      const at = frame.state().get('selection').first().toJSON();
      $('#' + target).val(at.id);
      const thumb = (at.sizes && at.sizes.medium) ? at.sizes.medium.url : at.url;
      let tSel = '';
      if (String(target).indexOf('slide_') === 0) tSel = '#slide_thumb_' + String(target).split('_')[1];
      else if (String(target).indexOf('promo_') === 0) tSel = '#promo_thumb_' + String(target).split('_')[1];
      else if (String(target).indexOf('feat_') === 0) tSel = '#feat_thumb_' + String(target).split('_')[1];
      else if (String(target).indexOf('ptr_') === 0) tSel = '#ptr_thumb_' + String(target).split('_')[1];
      else if (target === 'b2b_logo_single') tSel = '#b2b_logo_thumb_single';
      if (tSel) $(tSel).html('<img src="' + thumb + '" alt="">');
    });
    frame.open();
  });

  $(document).on('click', '.nhhb-remove', function (e) {
    e.preventDefault();
    const target = $(this).data('target');
    $('#' + target).val('');
    let tSel = '';
    let emptyText = 'No image';
    if (String(target).indexOf('slide_') === 0) tSel = '#slide_thumb_' + String(target).split('_')[1];
    else if (String(target).indexOf('promo_') === 0) tSel = '#promo_thumb_' + String(target).split('_')[1];
    else if (String(target).indexOf('feat_') === 0) tSel = '#feat_thumb_' + String(target).split('_')[1];
    else if (String(target).indexOf('ptr_') === 0) tSel = '#ptr_thumb_' + String(target).split('_')[1];
    else if (target === 'b2b_logo_single') {
      tSel = '#b2b_logo_thumb_single';
      emptyText = 'No logo selected';
    }
    if (tSel) $(tSel).text(emptyText);
  });
})(jQuery);
