/* NH Price Summary — VARIABLE products + SIMPLE (non-custom) */
(function($){
  function fmt(n){ return (window.NHPriceSummary && NHPriceSummary.fmt) ? NHPriceSummary.fmt(n) : (''+n); }
  function parse(t){ return (window.NHPriceSummary && NHPriceSummary.parse) ? NHPriceSummary.parse(t) : 0; }

  function makePair(reg, sale){
    reg = Number(reg||0); sale = Number(sale||0);
    if (reg > 0 && sale > 0 && sale < reg){
      return '<del>'+fmt(reg)+'</del> <ins>'+fmt(sale)+'</ins>';
    }
    var v = sale>0 ? sale : reg;
    return v>0 ? '<ins>'+fmt(v)+'</ins>' : '—';
  }

  function recomputeTotal(){
    var $box = $('#nh-price-summary'); if(!$box.length) return;
    var qty = parseFloat($('form.cart .quantity input.qty').val()) || 1;

    var $unit = $box.find('[data-ps="unit"]');
    var ins = $unit.find('ins').text().trim();
    var del = $unit.find('del').text().trim();
    var sale = ins ? parse(ins) : parse($unit.text());
    var reg  = del ? parse(del) : sale;

    if (sale > 0){
      var regTotal  = (reg || sale) * qty;
      var saleTotal = sale * qty;
      var html = (reg && sale < reg)
        ? '<del>'+fmt(regTotal)+'</del> <ins>'+fmt(saleTotal)+'</ins>'
        : '<ins>'+fmt(saleTotal)+'</ins>';
      NHPriceSummary.update({ total_html: html });
    } else {
      NHPriceSummary.update({ total: '—' });
    }
  }

  $(function(){
    var $vf = $('form.variations_form');

    if ($vf.length){
      // VARIABLE products
      $vf.on('found_variation', function(_e, v){
        if (!v) return;
        var reg  = parseFloat(v.display_regular_price || 0);
        var sale = parseFloat(v.display_price || 0);
        NHPriceSummary.update({ unit_html: makePair(reg, sale) });
        recomputeTotal();
      });
      $vf.on('hide_variation reset_data', function(){
        NHPriceSummary.update({ unit_html: '—', total_html: '—' });
      });
    } else if (window.NH_SIMPLE_DEFAULT) {
      // SIMPLE (non-custom) — use server values
      NHPriceSummary.update({ unit_html: makePair(NH_SIMPLE_DEFAULT.reg, NH_SIMPLE_DEFAULT.sale) });
      recomputeTotal();
    }

    $(document).on('input change', 'form.cart .quantity input.qty', recomputeTotal);
  });
})(jQuery);
