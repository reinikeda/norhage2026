/* NH Price Summary — core helpers */
(function($){
  // fmt: Woo-like HTML with currency symbol and position
  function fmt(n){
    const F  = window.NH_PRICE_FMT || {};
    const sym = F.symbol || '';
    const pos = F.pos || 'right_space';
    const d   = (typeof F.decs === 'number') ? F.decs : 2;
    const th  = F.thousand || '.';
    const dc  = F.decimal  || ',';

    n = Number(n || 0);
    const parts = n.toFixed(d).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, th);
    const num = d ? parts[0] + dc + parts[1] : parts[0];

    const nbsp = '\u00A0';
    let before = '', after = '';
    switch (pos) {
      case 'left':        before = sym; break;
      case 'left_space':  before = sym + nbsp; break;
      case 'right':       after  = sym; break;
      default:            after  = (sym ? nbsp + sym : ''); break; // right_space
    }
    return (
      '<span class="woocommerce-Price-amount amount"><bdi>' +
        (before ? '<span class="woocommerce-Price-currencySymbol">'+ before +'</span>' : '') +
        num +
        (after  ? '<span class="woocommerce-Price-currencySymbol">'+ after  +'</span>' : '') +
      '</bdi></span>'
    );
  }

  // parse human price text → number
  function parse(t){
    if (!t) return 0;
    var s = (''+t).replace(/[\u00A0\u202F]/g, '');
    s = s.replace(/[^0-9,.\-]/g, '');
    var c = s.lastIndexOf(','), d = s.lastIndexOf('.');
    var sep = c > d ? ',' : '.';
    var n = s.replace(new RegExp('[^0-9\\' + sep + '\\-]', 'g'), '');
    if (sep === ',') n = n.replace(',', '.');
    n = parseFloat(n);
    return isNaN(n) ? 0 : n;
  }

  window.NHPriceSummary = window.NHPriceSummary || {
    update:function(data){
      var $b=$('#nh-price-summary'); if(!$b.length) return;
      if('unit_html'  in data){ $b.find('[data-ps="unit"]').html(data.unit_html||'—').attr('data-mode','html'); }
      if('total_html' in data){ $b.find('[data-ps="total"]').html(data.total_html||'—').attr('data-mode','html'); }
      if('unit'  in data && ($b.find('[data-ps="unit"]').attr('data-mode')||'text')!=='html'){  $b.find('[data-ps="unit"]').text(data.unit); }
      if('total' in data && ($b.find('[data-ps="total"]').attr('data-mode')||'text')!=='html'){ $b.find('[data-ps="total"]').text(data.total); }
      if('perm2_html' in data){ $b.find('[data-ps="perm2"]').html(data.perm2_html||'—').attr('data-mode','html'); }
      else if('perm2' in data){ $b.find('[data-ps="perm2"]').text(data.perm2); }
      if('cutfee_html' in data){ $b.find('[data-ps="cutfee"]').html(data.cutfee_html||'—').attr('data-mode','html'); }
      else if('cutfee' in data){ $b.find('[data-ps="cutfee"]').text(data.cutfee); }
    },
    fmt:fmt,
    parse:parse
  };

  document.dispatchEvent(new CustomEvent('nh:price-summary-ready'));
})(jQuery);
