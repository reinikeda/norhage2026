/* NH Price Summary — VARIABLE products + SIMPLE (non-custom)
   IMPORTANT: Must not run on custom-cut products (custom-cutting.js owns pricing there).
*/
(function($){
  'use strict';

  // ===== Guard: do nothing on custom-cut pages =====
  function isCustomCutPage(){
    try {
      if (window.NH_CC && window.NH_CC.enabled) return true;
    } catch(e){}
    if ($('body').hasClass('nh-has-custom-cut')) return true;
    if ($('#nh-custom-size-wrap').length) return true;
    return false;
  }

  if (isCustomCutPage()) return;

  function fmt(n){
    return (window.NHPriceSummary && typeof window.NHPriceSummary.fmt === 'function')
      ? window.NHPriceSummary.fmt(n)
      : ('' + n);
  }

  function parse(t){
    return (window.NHPriceSummary && typeof window.NHPriceSummary.parse === 'function')
      ? window.NHPriceSummary.parse(t)
      : 0;
  }

  function makePair(reg, sale){
    reg = Number(reg || 0);
    sale = Number(sale || 0);

    if (!isFinite(reg)) reg = 0;
    if (!isFinite(sale)) sale = 0;

    if (reg > 0 && sale > 0 && sale < reg){
      return '<del>' + fmt(reg) + '</del> <ins>' + fmt(sale) + '</ins>';
    }

    var v = sale > 0 ? sale : reg;
    return v > 0 ? '<ins>' + fmt(v) + '</ins>' : '—';
  }

  function recomputeTotal(){
    var $box = $('#nh-price-summary');
    if (!$box.length) return;

    var qty = parseFloat($('form.cart .quantity input.qty').val()) || 1;
    var $unit = $box.find('[data-ps="unit"]');
    if (!$unit.length) return;

    var insText  = $unit.find('ins').first().text().trim();
    var delText  = $unit.find('del').first().text().trim();
    var fullText = $unit.text().trim();

    var sale = insText ? parse(insText) : parse(fullText);
    var reg  = delText ? parse(delText) : sale;

    if (sale > 0){
      var regTotal  = (reg || sale) * qty;
      var saleTotal = sale * qty;

      var html = (reg && sale < reg)
        ? '<del>' + fmt(regTotal) + '</del> <ins>' + fmt(saleTotal) + '</ins>'
        : '<ins>' + fmt(saleTotal) + '</ins>';

      NHPriceSummary.update({ total_html: html });
    } else {
      NHPriceSummary.update({ total_html: '—' });
    }
  }

  $(function(){
    var $vf = $('form.variations_form');

    if ($vf.length){
      // VARIABLE products
      $vf.on('found_variation', function(_e, v){
        if (!v) return;

        // IMPORTANT:
        // Do NOT use v.price_html here, because in some setups it can contain
        // pre-rendered HTML that does not match this summary's decimal settings.
        // Always rebuild using numeric values + our formatter.
        var reg  = parseFloat(v.display_regular_price || 0);
        var sale = parseFloat(v.display_price || 0);

        if (!isFinite(reg)) reg = 0;
        if (!isFinite(sale)) sale = 0;

        NHPriceSummary.update({
          unit_html: makePair(reg, sale)
        });

        recomputeTotal();
      });

      $vf.on('hide_variation reset_data', function(){
        NHPriceSummary.update({ unit_html: '—', total_html: '—' });
      });

    } else if (window.NH_SIMPLE_DEFAULT) {
      // SIMPLE (non-custom) — use server values
      NHPriceSummary.update({
        unit_html: makePair(window.NH_SIMPLE_DEFAULT.reg, window.NH_SIMPLE_DEFAULT.sale)
      });
      recomputeTotal();
    }

    $(document).on('input change', 'form.cart .quantity input.qty', recomputeTotal);
  });
})(jQuery);
