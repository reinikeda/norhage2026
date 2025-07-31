jQuery(function($){
  var unitPrice = 0,
      $form     = $('form.variations_form'),
      $qtyInput = $('form.cart .quantity input.qty');

  // parse "€14,00" → 14.00
  function parsePrice(str){
    return parseFloat(
      str.replace(/[^\d\-,\.]/g,'')
         .replace(/\.(?=\d{3}\b)/g,'')
         .replace(',','.')
    ) || 0;
  }

  // format 14.00 → "€14,00"
  function formatPrice(num){
    return num
      .toLocaleString('de-DE',{ minimumFractionDigits:2, maximumFractionDigits:2 })
      .replace('.',',') + ' €';
  }

  // core recalc
  function updateTotal(){
    var qty   = parseInt( $qtyInput.val(), 10 ) || 1,
        total = unitPrice * qty;

    // override Woo's injected variation price
    var $amt = $('.single_variation .woocommerce-variation-price .amount');
    if ( $amt.length ) {
      $amt.text( formatPrice(total) );
      return;
    }

    // fallback simple product
    var $p = $('form.cart p.price .amount').first();
    if ( $p.length ) {
      $p.text( formatPrice(total) );
    }
  }

  // when variation is found (but before it shows) capture unit price
  $form.on('found_variation', function(e, variation){
    unitPrice = parseFloat( variation.display_price );
  });

  // the moment Woo actually writes its variation block, re-apply our total
  $form.on('show_variation', function(e, variation){
    // let Woo finish its DOM update, then recalc
    setTimeout(updateTotal, 5);
  });

  // qty changes
  $qtyInput.on('input change', updateTotal);

  // simple product on load
  if ( ! $form.length ) {
    var txt = $('form.cart p.price .amount').first().text()||'';
    unitPrice = parsePrice(txt);
    updateTotal();
  }
});
