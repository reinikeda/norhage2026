/* NH Price Summary — core helpers */
(function($){
  // fmt: Woo-like HTML with currency symbol and position
  function fmt(n){
    const F  = window.NH_PRICE_FMT || {};
    const sym = F.symbol || '';
    const pos = F.pos || 'right_space';

    // decs can come as string from wp_localize_script
    const dRaw = (F.decs != null) ? F.decs : 2;
    const d = Number.isFinite(Number(dRaw)) ? parseInt(dRaw, 10) : 2;

    const th  = (F.thousand != null) ? F.thousand : '.';
    const dc  = (F.decimal  != null) ? F.decimal  : ',';

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

  function setBoxVal($box, key, val, isHtml){
    const $el = $box.find('[data-ps="'+ key +'"]');
    if (!$el.length) return;

    if (isHtml){
      $el.html(val || '—').attr('data-mode','html');
      return;
    }

    // only write plain text if current mode isn't html
    const mode = ($el.attr('data-mode') || 'text');
    if (mode !== 'html') $el.text(val || '—');
  }

  window.NHPriceSummary = window.NHPriceSummary || {
    update:function(data){
      var $b = $('#nh-price-summary'); if(!$b.length) return;

      // Unit / Total
      if ('unit_html' in data)  setBoxVal($b, 'unit',  data.unit_html,  true);
      if ('total_html' in data) setBoxVal($b, 'total', data.total_html, true);
      if ('unit' in data)       setBoxVal($b, 'unit',  data.unit,       false);
      if ('total' in data)      setBoxVal($b, 'total', data.total,      false);

      // Price per m² + cutting fee
      if ('perm2_html' in data) setBoxVal($b, 'perm2', data.perm2_html, true);
      else if ('perm2' in data) setBoxVal($b, 'perm2', data.perm2, false);

      if ('cutfee_html' in data) setBoxVal($b, 'cutfee', data.cutfee_html, true);
      else if ('cutfee' in data) setBoxVal($b, 'cutfee', data.cutfee, false);

      // OPTIONAL weight support (only works if your markup includes data-ps="weight")
      if ('weight_html' in data) setBoxVal($b, 'weight', data.weight_html, true);
      else if ('weight' in data) setBoxVal($b, 'weight', data.weight, false);

      // Optional: if you later add separate fields data-ps="unit_weight" / "total_weight"
      if ('unit_weight_html' in data)  setBoxVal($b, 'unit_weight',  data.unit_weight_html,  true);
      if ('total_weight_html' in data) setBoxVal($b, 'total_weight', data.total_weight_html, true);
    },
    fmt:fmt,
    parse:parse
  };

  document.dispatchEvent(new CustomEvent('nh:price-summary-ready'));
})(jQuery);
