jQuery(function($){
  // localized via wp_localize_script
  const ajaxUrl       = bundle_ajax.ajax_url;
  const cartUrl       = bundle_ajax.cart_url;
  const $totalEl      = $('#bundle-total-amount');
  const $addAllBtn    = $('#add-bundle-to-cart');
  const $variationForm= $('form.variations_form');
  const $mainQty      = $('form.cart .quantity input.qty');

  // — Helpers
  function parsePrice(str) {
    if (!str) return 0;
    let s = str
      .replace(/[^\d\.,]/g, '')                // strip letters & currency
      .replace(/\.(?=\d{3}(?:\D|$))/g, '')     // remove thousand-sep dots
      .replace(/,/g, '.');                     // comma to decimal point
    return parseFloat(s) || 0;
  }
  function formatPrice(n) {
    return '€' + n.toFixed(2);
  }

  // — 1) Main line total (use exactly what Woo prints)
  function getMainLineTotal() {
    let $amt = $variationForm
      .find('.single_variation_wrap .woocommerce-variation-price .amount')
      .first();
    if (!$amt.length) {
      $amt = $('.summary .price .amount').first();
    }
    return parsePrice($amt.text());
  }

  // — 2) Upsell line total
  function updateUpsellLine($row) {
    const base = parseFloat( $row.find('.bundle-price').data('base-price') ) || 0;
    const qty  = parseInt( $row.find('.bundle-qty').val(), 10 ) || 0;
    $row.find('.bundle-price').text( formatPrice(base * qty) );
  }

  // — 3) Grand total
  function updateGrandTotal() {
    let total = getMainLineTotal();
    $('.bundle-row[data-product-id]').each(function(){
      updateUpsellLine($(this));
      total += parsePrice( $(this).find('.bundle-price').text() );
    });
    $totalEl.text( formatPrice(total) );
  }

  // — 4) Fetch upsell variation price
  function fetchVariationPrice($row) {
    const pid   = $row.data('product-id');
    const attrs = {};
    $row.find('.bundle-variation').each(function(){
      const m = this.name.match(/\[([^]]+)\]/);
      if (m && this.value) {
        attrs['attribute_' + m[1]] = this.value;
      }
    });
    if ($.isEmptyObject(attrs)) {
      return $.Deferred().resolve().promise();
    }
    return $.post( ajaxUrl, {
      action:       'get_variation_id_from_attributes',
      product_id:   pid,
      attributes:   JSON.stringify(attrs)
    }).done(function(json){
      if (json.success && json.data.price != null) {
        $row.find('.bundle-price').data('base-price', json.data.price);
      }
    });
  }

  // — 5) Bind events
  $variationForm.on('found_variation show_variation', function(){
    setTimeout(updateGrandTotal, 10);
  });
  $mainQty.on('input change', updateGrandTotal);
  $(document).on('change', '.bundle-variation', function(){
    const $r = $(this).closest('.bundle-row');
    fetchVariationPrice($r).done(updateGrandTotal);
  });
  $(document).on('input change', '.bundle-qty', updateGrandTotal);

  // initial
  updateGrandTotal();

  // — 6) Add all to cart
  $addAllBtn.on('click', function(){
    // main product
    const mainData = $('form.cart').serialize();
    $.post( ajaxUrl + '?wc-ajax=add_to_cart', mainData )
     .always(function(){
       // upsells in sequence
       let seq = $.Deferred().resolve().promise();
       $('.bundle-row[data-product-id]').each(function(){
         const $r  = $(this);
         const qty = parseInt($r.find('.bundle-qty').val(),10) || 0;
         if (!qty) return;
         const vid = $r.find('.selected-variation-id').val();
         const data = {
           product_id: vid || $r.data('product-id'),
           quantity:   qty
         };
         seq = seq.then(function(){
           return $.post( ajaxUrl + '?wc-ajax=add_to_cart', data );
         });
       });
       seq.then(function(){
         window.location = cartUrl;
       });
     });
  });
});
