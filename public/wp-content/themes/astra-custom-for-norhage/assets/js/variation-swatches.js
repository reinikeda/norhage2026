jQuery(function($){
  var $form = $('form.variations_form');

  // Delegate click on ANY swatch button
  $(document).on('click', '.nrh-variation-swatches .nrh-swatch', function(e){
    e.preventDefault();
    var $btn   = $(this),
        val    = $btn.data('value'),
        // Find the select immediately above this swatch container
        $select= $btn.closest('.nrh-variation-swatches').prevAll('select').first(),
        name   = $select.attr('name');

    // 1) Visual state
    $btn.addClass('selected').siblings().removeClass('selected');

    // 2) Sync into the real <select>
    if ( $select.length ) {
      $select.val(val).trigger('change');
    }

    // 3) Fire WooCommerce variation hooks
    $form.trigger('woocommerce_variation_select_change')
         .trigger('woocommerce_update_variation_values')
         .trigger('check_variations');
  });
});
