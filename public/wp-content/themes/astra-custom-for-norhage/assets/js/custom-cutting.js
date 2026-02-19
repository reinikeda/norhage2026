(function ($) {
  'use strict';

  /* ======================= Money helpers (minimal) ======================= */
  function fmtNumber(n) {
    const p  = window.NH_PRICE_FMT || {};
    const d  = (p.decs != null) ? parseInt(p.decs, 10) : 2;
    const dec = p.decimal  || ',';
    const th  = p.thousand || '.';
    const fx  = Number(n || 0).toFixed(d);
    const pa  = fx.split('.');
    pa[0] = pa[0].replace(/\B(?=(\d{3})+(?!\d))/g, th);
    return d ? pa[0] + dec + pa[1] : pa[0];
  }

  function money(n) {
    const p   = window.NH_PRICE_FMT || {};
    const sym = p.symbol || '';
    const pos = p.pos || 'right_space';
    const val = fmtNumber(n);
    const nbsp = '\u00A0';
    switch (pos) {
      case 'left':        return sym + val;
      case 'left_space':  return sym + nbsp + val;
      case 'right':       return val + sym;
      default:            return val + (sym ? nbsp + sym : '');
    }
  }

  function pairHTML(reg, sale, F){
    reg = Number(reg||0); sale = Number(sale||0);
    if (reg > 0 && sale > 0 && sale < reg) return '<del>'+F(reg)+'</del> <ins>'+F(sale)+'</ins>';
    var v = sale > 0 ? sale : reg;
    return v > 0 ? '<ins>'+F(v)+'</ins>' : '—';
  }

  /* ======================= Weight helpers ======================= */
  function formatWeight(kg) {
    const n = Number(kg || 0);
    if (!isFinite(n) || n <= 0) return '—';
    if (n < 1) return Math.round(n * 1000) + ' g';
    const s = (Math.round(n * 100) / 100).toFixed(n >= 10 ? 1 : 2).replace(/\.0+$/,'');
    return s + ' kg';
  }
  function weightHtml(kg) {
    const txt = formatWeight(kg);
    if (txt === '—') return txt;
    return '<span class="nh-weight" data-kg="'+ String(Number(kg||0)) +'">'+ txt +'</span>';
  }

  /* ======================= DOM helpers ========================= */
  function $mmWrap()   { return $('#nh-custom-size-wrap'); }
  function $mmInputs() { return $('#nh_width_mm, #nh_length_mm'); }
  function $psBox()    { return $('#nh-price-summary'); }
  function $qtyInput() { return $('form.cart .quantity input.qty'); }
  function $cartForm() { return $('form.cart'); }

  function isVariableProduct() {
    return !!$('form.variations_form').length;
  }
  function variationSelected() {
    const $v = $('form.variations_form input[name="variation_id"]');
    if (!$v.length) return true;
    return parseInt($v.val() || '0', 10) > 0;
  }

  var CC = window.NH_CC || {
    enabled:false,
    perm2_reg_disp:0,
    perm2_sale_disp:0,
    cut_fee:0,
    step:1,
    min_w:'', max_w:'', min_l:'', max_l:'',
    kg_per_m2: 0
  };

  // accept either kg_per_m2 or weight_per_m2 from PHP
  if (CC.kg_per_m2 == null && CC.weight_per_m2 != null) {
    CC.kg_per_m2 = Number(CC.weight_per_m2) || 0;
  }

  /* =================== Summary bridge ========================== */
  function writeSummary(data){
    if (window.NHPriceSummary && typeof NHPriceSummary.update === 'function') {
      NHPriceSummary.update(data);
    }
    $(document).trigger('nh:lineTotalUpdated', [0]);
    if (Object.prototype.hasOwnProperty.call(data, 'unit_weight_html') ||
        Object.prototype.hasOwnProperty.call(data, 'total_weight_html') ||
        Object.prototype.hasOwnProperty.call(data, 'weight_html')) {
      $(document).trigger('nh:weightUpdated', [data]);
    }
  }

  /* =================== Hidden inputs (for PHP) ================= */
  function ensureWeightInputs(){
    const $f = $cartForm();
    if (!$f.length) return;
    if (!$f.find('input[name="nh_custom_unit_kg"]').length) {
      $f.append('<input type="hidden" name="nh_custom_unit_kg" value="0" />');
    }
    if (!$f.find('input[name="nh_custom_total_kg"]').length) {
      $f.append('<input type="hidden" name="nh_custom_total_kg" value="0" />');
    }
  }
  function setWeightInputs(unitKg, totalKg){
    const $f = $cartForm();
    $f.find('input[name="nh_custom_unit_kg"]').val(String(unitKg || 0));
    $f.find('input[name="nh_custom_total_kg"]').val(String(totalKg || 0));
  }

  /* =================== Perm² + Cut fee (render) ================= */
  function renderPerm2AndFee() {
    var reg = Number(CC.perm2_reg_disp || 0);
    var sale = Number(CC.perm2_sale_disp || 0);
    var fee = Number(CC.cut_fee || 0);

    var perm2HTML =
      (reg > 0 && sale > 0 && sale < reg) ? ('<del>' + money(reg) + '</del> <ins>' + money(sale) + '</ins>') :
      (sale > 0 ? ('<ins>' + money(sale) + '</ins>') :
      (reg  > 0 ? ('<ins>' + money(reg)  + '</ins>') : '—'));

    var cutHTML = fee > 0 ? money(fee) : '—';

    $psBox().find('.nh-ps-perm2').css('display','flex');
    $psBox().find('.nh-ps-cutfee').css('display', fee > 0 ? 'flex' : '');

    writeSummary({ perm2_html: perm2HTML, cutfee_html: cutHTML });
  }

  /* =================== Utils for step/limits ==================== */
  function getStep($el){ var n = parseFloat($el.attr('step')); return (isFinite(n) && n > 0) ? n : 1; }
  function snapToStep(val, step, min){
    if (!isFinite(val)) return val;
    if (step > 0) {
      if (isFinite(min)) val = min + Math.round((val - min) / step) * step;
      else               val = Math.round(val / step) * step;
    }
    return val;
  }

  /* =================== Apply limits from meta =================== */
  function applyLimitsFromMeta(){
    var $w = $('#nh_width_mm'), $l = $('#nh_length_mm'), $wrap = $mmWrap();

    var step = CC.step ? Number(CC.step) : 1;
    if (!isFinite(step) || step <= 0) step = 1;

    function setMinMax($el, min, max){
      $el.removeAttr('min max');
      if (min !== '' && min != null) $el.attr('min', String(min));
      if (max !== '' && max != null) $el.attr('max', String(max));
    }
    function rangePH(min,max){
      if (min !== '' && max !== '') return min + '–' + max + ' mm';
      if (min !== '') return '≥ ' + min + ' mm';
      if (max !== '') return '≤ ' + max + ' mm';
      return '';
    }

    $w.add($l)
      .prop({ disabled:false, readOnly:false })
      .attr({ type:'number', inputmode:'numeric', step:String(step), pattern:'[0-9]*' })
      .off('.ccDigits .ccClamp wheel');

    setMinMax($w, CC.min_w, CC.max_w);
    setMinMax($l, CC.min_l, CC.max_l);
    $w.attr('placeholder', rangePH(CC.min_w, CC.max_w));
    $l.attr('placeholder', rangePH(CC.min_l, CC.max_l));

    $wrap.on('input.ccDigits', 'input.nh-mm-input', function(){
      var $el = $(this), raw = String($el.val()), digits = raw.replace(/\D+/g,'');
      if (raw !== digits) $el.val(digits);
    });

    $wrap.on('change.ccClamp blur.ccClamp', 'input.nh-mm-input', function(){
      var $el = $(this);
      var raw = $el.val();
      if (raw === '') { recalcCustom(); updateButtonState(); return; }

      var val = parseFloat(raw);
      var hasMin = $el.is('[min]'), hasMax = $el.is('[max]');
      var min = hasMin ? parseFloat($el.attr('min')) : null;
      var max = hasMax ? parseFloat($el.attr('max')) : null;

      if (!isFinite(val)) { $el.val(''); recalcCustom(); updateButtonState(); return; }

      var step = getStep($el);
      if (hasMin && val < min) val = min;
      if (hasMax && val > max) val = max;
      val = snapToStep(val, step, (hasMin ? min : 0));

      $el.val(String(val));
      recalcCustom();
      updateButtonState();
    });

    $mmInputs().on('wheel', function(e){ e.preventDefault(); });
    $mmWrap().find('table.variations').css('border-bottom','0');
  }

  /* =================== Qty helper =============================== */
  function qty(){
    var n = parseInt($qtyInput().val(), 10);
    return (Number.isFinite(n) && n > 0) ? n : 1;
  }

  // cache last computed values (exposed so bundle-add-to-cart.js can read it)
  window.NH_LAST_WEIGHT = window.NH_LAST_WEIGHT || { unit_kg: 0, total_kg: 0, area_m2: 0, wmm: 0, lmm: 0, qty: 1 };
  function setLastWeight(obj){
    window.NH_LAST_WEIGHT = Object.assign(
      { unit_kg:0,total_kg:0,area_m2:0,wmm:0,lmm:0,qty:1 },
      obj || {}
    );
  }

  /* =================== Recalculate (custom) ===================== */
  function recalcCustom(){
    if (!CC.enabled) return;

    var M2_REG  = Number(CC.perm2_reg_disp || 0);
    var M2_SALE = Number(CC.perm2_sale_disp || 0) || M2_REG;
    var FEE     = Number(CC.cut_fee || 0);
    var KGPM2   = Number(CC.kg_per_m2 || 0);
    var q       = qty();

    var wmm = parseInt($('#nh_width_mm').val() || '0', 10);
    var lmm = parseInt($('#nh_length_mm').val() || '0', 10);

    // Variable product and no variation selected: keep UI calm
    if (isVariableProduct() && !variationSelected()) {
      ensureWeightInputs();
      setWeightInputs(0, 0);
      setLastWeight({ unit_kg:0,total_kg:0,area_m2:0,wmm:wmm||0,lmm:lmm||0,qty:q });
      writeSummary({ unit: '—', total: '—', weight_html: '—' });
      renderPerm2AndFee();
      return;
    }

    var valid = Number.isFinite(wmm) && wmm > 0 && Number.isFinite(lmm) && lmm > 0;

    ensureWeightInputs();
    setWeightInputs(0, 0);

    if (valid) {
      var area = (wmm / 1000) * (lmm / 1000); // m²

      // Price
      if (M2_REG > 0 || M2_SALE > 0) {
        var unit_reg   = area * M2_REG  + Math.max(0, FEE);
        var unit_sale  = area * M2_SALE + Math.max(0, FEE);
        var total_reg  = unit_reg  * q;
        var total_sale = unit_sale * q;

        writeSummary({
          unit_html:  pairHTML(unit_reg,  unit_sale, money),
          total_html: pairHTML(total_reg, total_sale, money)
        });
      } else {
        writeSummary({ unit: '—', total: '—' });
      }

      // Weight
      if (KGPM2 > 0) {
        var unit_kg  = area * KGPM2;
        var total_kg = unit_kg * q;

        $psBox().find('.nh-ps-weight').css('display','flex');
        writeSummary({
          unit_weight_html:  weightHtml(unit_kg),
          total_weight_html: weightHtml(total_kg),
          weight_html:
            '<span class="nh-weight-pair"><em>Unit:</em> '  + weightHtml(unit_kg)  +
            ' &nbsp; <em>Total:</em> ' + weightHtml(total_kg) + '</span>'
        });

        setLastWeight({ unit_kg: unit_kg, total_kg: total_kg, area_m2: area, wmm: wmm, lmm: lmm, qty: q });
        setWeightInputs(unit_kg, total_kg);
      } else {
        setLastWeight({ unit_kg: 0, total_kg: 0, area_m2: area, wmm: wmm, lmm: lmm, qty: q });
      }
    } else {
      writeSummary({ unit: '—', total: '—', weight_html: '—' });
      $psBox().find('.nh-ps-weight').css('display','');
      setLastWeight({ unit_kg: 0, total_kg: 0, area_m2: 0, wmm: 0, lmm: 0, qty: 1 });
    }

    renderPerm2AndFee();
  }

  /* =================== Add-to-cart button state ================= */
  var BTN_SEL = '.single_add_to_cart_button';
  function updateButtonState() {
    var $btn = $(BTN_SEL);
    if (!$btn.length) return;

    var wOk = parseInt($('#nh_width_mm').val(), 10);
    var lOk = parseInt($('#nh_length_mm').val(), 10);
    var dimsOk = Number.isFinite(wOk) && wOk > 0 && Number.isFinite(lOk) && lOk > 0;

    var varOk = variationSelected();
    var enabled = dimsOk && varOk;

    $btn.toggleClass('disabled nh-forced-disabled', !enabled)
        .attr('aria-disabled', String(!enabled))
        .prop('disabled', !enabled);
  }

  /* ========= +/- for custom width/length ========= */
  $(document).on(
    'click',
    '#nh-custom-size-wrap .quantity.buttons_added .minus, #nh-custom-size-wrap .quantity.buttons_added .plus',
    function (e) {
      e.preventDefault();
      var $btn  = $(this);
      var $wrap = $btn.closest('.quantity');
      var $inp  = $wrap.find('input.nh-mm-input');
      if (!$inp.length || $inp.is(':disabled') || $inp.is('[readonly]')) return;

      var step   = parseFloat($inp.attr('step')) || 1;
      var hasMin = $inp.is('[min]'), hasMax = $inp.is('[max]');
      var min    = hasMin ? parseFloat($inp.attr('min')) : null;
      var max    = hasMax ? parseFloat($inp.attr('max')) : null;

      var raw = $inp.val();
      var val = raw === '' ? NaN : parseFloat(raw);
      if (isNaN(val)) val = (min != null ? min : 0);
      val += $btn.hasClass('plus') ? step : -step;

      if (step > 0) {
        if (min != null && isFinite(min)) val = min + Math.round((val - min) / step) * step;
        else                               val = Math.round(val / step) * step;
      }
      if (min != null && val < min) val = min;
      if (max != null && val > max) val = max;

      $inp.val(String(val)).trigger('input').trigger('change');
    }
  );

  /* =================== Qty → recalc ============================= */
  $(document).on('input change', 'form.cart .quantity input.qty', function(){
    recalcCustom();
    updateButtonState();
  });

  /* =================== Variation events =================== */
  $(document).on('found_variation', 'form.variations_form', function(e, variation){
    if (!CC.enabled || !variation) return;

    if (variation.nh_cc_perm2_reg_disp != null)  CC.perm2_reg_disp  = Number(variation.nh_cc_perm2_reg_disp) || 0;
    if (variation.nh_cc_perm2_sale_disp != null) CC.perm2_sale_disp = Number(variation.nh_cc_perm2_sale_disp) || 0;
    if (variation.nh_cc_cut_fee_disp != null)    CC.cut_fee         = Number(variation.nh_cc_cut_fee_disp) || 0;

    // UPDATED: kg per m² now comes from variation weight provided by PHP (or fallback to parent)
    if (variation.nh_cc_kg_per_m2 != null)       CC.kg_per_m2       = Number(variation.nh_cc_kg_per_m2) || 0;

    renderPerm2AndFee();
    recalcCustom();
    updateButtonState();
  });

  $(document).on('reset_data', 'form.variations_form', function(){
    if (!CC.enabled) return;
    CC.perm2_reg_disp  = 0;
    CC.perm2_sale_disp = 0;
    // keep CC.kg_per_m2 as-is? better reset to 0 so weight doesn't “stick” between variations
    CC.kg_per_m2       = 0;
    renderPerm2AndFee();
    recalcCustom();
    updateButtonState();
  });

  /* =================== Ensure weights for ALL flows ============= */
  function nhWriteWeightsToForm() {
    ensureWeightInputs();
    var LW = window.NH_LAST_WEIGHT || {};
    var unit = (LW.unit_kg) ? LW.unit_kg : 0;
    var tot  = (LW.total_kg) ? LW.total_kg : 0;
    setWeightInputs(unit, tot);
  }

  $(document).on('submit', 'form.cart', function () {
    nhWriteWeightsToForm();
  });

  $(document).on('click', '.single_add_to_cart_button', function () {
    nhWriteWeightsToForm();
  });

  $(document).on('adding_to_cart', function(e, $button, data){
    try {
      nhWriteWeightsToForm();
      var LW = window.NH_LAST_WEIGHT || {};
      if (LW && (LW.unit_kg > 0 || LW.total_kg > 0)) {
        data['nh_custom_unit_kg']  = String(LW.unit_kg);
        data['nh_custom_total_kg'] = String(LW.total_kg);
      }
    } catch(err){ /* silent */ }
  });

  /* =================== Boot ==================================== */
  $(function () {
    if (!CC.enabled) return;

    ensureWeightInputs();
    applyLimitsFromMeta();

    // For variable products: summary will fill after variation selected
    renderPerm2AndFee();
    recalcCustom();
    updateButtonState();

    // Keep button state synced as selects change (before found_variation fires)
    $(document).on('change', 'form.variations_form select', function(){
      updateButtonState();
      recalcCustom();
    });

    // Recalc on mm input typing
    $(document).on('input change', '#nh_width_mm, #nh_length_mm', function(){
      recalcCustom();
      updateButtonState();
    });
  });

})(jQuery);
