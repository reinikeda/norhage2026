(function($){
  // Feature CPT: media uploader
  $(document).on('click', '.nhf-upload', function(e){
    e.preventDefault();
    const $wrap = $(this).closest('.nhf-media-wrap');
    let frame = wp.media({
      title: 'Select or upload icon',
      library: { type: ['image/svg+xml', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'] },
      button: { text: 'Use this icon' },
      multiple: false
    });
    frame.on('select', function(){
      const att = frame.state().get('selection').first().toJSON();
      $wrap.find('#nhf_icon_id').val(att.id);
      $wrap.find('.nhf-preview').html('<img src="'+att.url+'" style="max-width:48px;max-height:48px;" alt="">');
      $wrap.find('.nhf-remove').prop('disabled', false);
    });
    frame.open();
  });

  $(document).on('click', '.nhf-remove', function(e){
    e.preventDefault();
    const $wrap = $(this).closest('.nhf-media-wrap');
    $wrap.find('#nhf_icon_id').val('');
    $wrap.find('.nhf-preview').empty();
    $(this).prop('disabled', true);
  });

  // Product metabox: toggle show/hide picker
  $('input[name="nhf_show_box"]').on('change', function(){
    $('.nhf-picker').toggle(this.checked);
  });

  // Sortable list
  $('#nhf-sortable').sortable({
    handle: '.nhf-grip',
    axis: 'y'
  });

  // Limit to max features
  function countChecked(){
    return $('#nhf-sortable .nhf-check:checked').length;
  }
  $('#nhf-sortable').on('change', '.nhf-check', function(){
    const max = parseInt(NHFAdmin.max || 8, 10);
    if (countChecked() > max) {
      alert(NHFAdmin.i18n.limit);
      this.checked = false;
    }
  });

})(jQuery);
