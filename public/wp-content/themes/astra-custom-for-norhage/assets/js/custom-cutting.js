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

  function moneyHtml(n) {
    const p = window.NH_PRICE_FMT || {};
    const sym = p.symbol || '';
    const pos = p.pos || 'right_space';
    const num = fmtNumber(n);
    const nbsp = '\u00A0';
    let before = '', after = '';
    switch (pos) {
      case 'left':        before = sym; break;
      case 'left_space':  before = sym + nbsp; break;
      case 'right':       after  = sym; break;
      default:            after  = sym ? (nbsp + sym) : ''; break;
    }
    return (
      '<span class="woocommerce-Price-amount amount"><bdi>' +
        (before ? '<span class="woocommerce-Price-currencySymbol">' + before + '</span>' : '') +
        num +
        (after  ? '<span class="woocommerce-Price-currencySymbol">' + after  + '</span>' : '') +
      '</bdi></span>'
    );
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
  function $modeRadios(){ return $('input[name="nh_cutting_mode"]'); }
  function $modeHidden(){ return $('#nh_custom_mode'); }
  function $cartForm()  { return $('form.cart'); }

  // ensure the hidden nh_custom_mode field exists
  function ensureCustomModeHidden() {
    const $f = $cartForm();
    if (!$f.length) return;
    if (!$f.find('#nh_custom_mode').length) {
      $f.append('<input type="hidden" id="nh_custom_mode" name="nh_custom_mode" value="0" />');
    }
  }

  var CC = window.NH_CC || {
    enabled:false, price_per_m2:0, cut_fee:0, step:1,
    min_w:'', max_w:'', min_l:'', max_l:'', perm2_reg_disp:0, perm2_sale_disp:0,
    kg_per_m2: 0
  };

  // accept either kg_per_m2 or weight_per_m2 from PHP
  if (CC.kg_per_m2 == null && CC.weight_per_m2 != null) {
    CC.kg_per_m2 = Number(CC.weight_per_m2) || 0;
  }

  /* ================== Price Summary placement ================== */
  var $psHome = null;
  function ensurePSHomeOnce(){
    var $box = $psBox();
    if (!$box.length) return false;
    if ($psHome && $psHome.length) return true;
    $psHome = $('<div id="nh-ps-home" style="display:none"></div>');
    $box.after($psHome);
    return true;
  }
  function raf(fn){ (window.requestAnimationFrame || function(f){ setTimeout(f,0); })(fn); }
  function placeSummaryAtHome(){
    raf(function(){
      if (!ensurePSHomeOnce()) { setTimeout(placeSummaryAtHome, 0); return; }
      var $box = $psBox();
      if ($box.length && $psHome && $psHome.length){ $box.insertAfter($psHome); }
    });
  }
  function placeSummaryAfterCustom(){
    raf(function(){
      var $box = $psBox(), $cw = $mmWrap();
      if (!$box.length || !$cw.length){ setTimeout(placeSummaryAfterCustom, 0); return; }
      $box.insertAfter($cw);
    });
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

  /* =================== Perm² + Cut fee (one-time) ============== */
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

    // only digits (no live debug recalcs)
    $wrap.on('input.ccDigits', 'input.nh-mm-input', function(){
      var $el = $(this), raw = String($el.val()), digits = raw.replace(/\D+/g,'');
      if (raw !== digits) $el.val(digits);
    });

    // clamp + snap
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

  // cache last computed values (for AJAX / fallback)
  var NH_LAST_WEIGHT = { unit_kg: 0, total_kg: 0, area_m2: 0, wmm: 0, lmm: 0, qty: 1 };

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
    var valid = Number.isFinite(wmm) && wmm > 0 && Number.isFinite(lmm) && lmm > 0;

    ensureWeightInputs();
    setWeightInputs(0, 0);

    if (valid) {
      var area = (wmm / 1000) * (lmm / 1000); // m²

      // Price
      if (M2_REG > 0 || M2_SALE > 0) {
        var unit_reg  = area * M2_REG  + Math.max(0, FEE);
        var unit_sale = area * M2_SALE + Math.max(0, FEE);
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

        // cache + hidden inputs (for POST submit path)
        NH_LAST_WEIGHT = { unit_kg: unit_kg, total_kg: total_kg, area_m2: area, wmm: wmm, lmm: lmm, qty: q };
        setWeightInputs(unit_kg, total_kg);
      } else {
        // nothing to compute without kg/m²
      }
    } else {
      writeSummary({ unit: '—', total: '—', weight_html: '—' });
      $psBox().find('.nh-ps-weight').css('display','');
      NH_LAST_WEIGHT = { unit_kg: 0, total_kg: 0, area_m2: 0, wmm: 0, lmm: 0, qty: 1 };
    }

    $psBox().find('.nh-ps-perm2').css('display','flex');
    $psBox().find('.nh-ps-cutfee').css('display', (Number(CC.cut_fee||0) > 0) ? 'flex' : '');
  }

  /* =================== Add-to-cart button state ================= */
  var BTN_SEL = '.single_add_to_cart_button';
  function updateButtonState() {
    var $btn = $(BTN_SEL);
    if (!$btn.length) return;
    var wOk = parseInt($('#nh_width_mm').val(), 10);
    var lOk = parseInt($('#nh_length_mm').val(), 10);
    var enabled = Number.isFinite(wOk) && wOk > 0 && Number.isFinite(lOk) && lOk > 0;
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
  $(document).on('input change', 'form.cart .quantity input.qty', recalcCustom);

  /* =================== Mode switching ========================== */
  function currentMode(){
    var $sel = $modeRadios().filter(':checked');
    return $sel.length ? $sel.val() : 'standard';
  }
  function setHiddenMode(val){ $modeHidden().val(val === 'custom' ? '1' : '0'); }

  function nhToggleWidthLengthVariations(hide){
    var $sels = $('select[name="attribute_pa_width"], select[name="attribute_width"], select[name="attribute_pa_length"], select[name="attribute_length"]');
    var $blocks = $sels.map(function(){
      var $s = $(this);
      var $b = $s.closest('tr');
      if (!$b.length) $b = $s.closest('.variations .value').parent();
      if (!$b.length) $b = $s.closest('.form-row, .row, .col, div');
      if ($b.closest('#nh-custom-size-wrap').length) return null;
      return $b[0] || this;
    });
    var $reset = $('.reset_variations').not('#nh-custom-size-wrap .reset_variations');
    if (hide){ $($blocks).stop(true,true).slideUp(150); $reset.hide(); }
    else     { $($blocks).stop(true,true).slideDown(150); $reset.show(); }
  }

  function onModeChanged(){
    var mode = currentMode();
    setHiddenMode(mode);
    $qtyInput().val(1).trigger('change');

    if (mode === 'custom'){
      nhToggleWidthLengthVariations(true);
      if (CC.enabled){
        $mmWrap().stop(true,true).slideDown(120);
        $mmInputs().prop('disabled',false).prop('readonly',false);
        applyLimitsFromMeta();
        placeSummaryAfterCustom();
        renderPerm2AndFee();
        ensureWeightInputs();
        ensureCustomModeHidden();
        $('#nh_custom_mode').val('1');
        recalcCustom();
      } else {
        $mmWrap().stop(true,true).slideUp(120);
        placeSummaryAtHome();
      }
    } else {
      nhToggleWidthLengthVariations(false);
      $mmWrap().stop(true,true).slideUp(120);
      placeSummaryAtHome();
      ensureCustomModeHidden();
      $('#nh_custom_mode').val('0');
    }
  }

  $(document).on('change', 'input[name="nh_cutting_mode"]', onModeChanged);

  /* =================== Ensure weights are present for ALL flows == */
  function nhWriteWeightsToForm() {
    ensureWeightInputs();
    var unit = (NH_LAST_WEIGHT && NH_LAST_WEIGHT.unit_kg) ? NH_LAST_WEIGHT.unit_kg : 0;
    var tot  = (NH_LAST_WEIGHT && NH_LAST_WEIGHT.total_kg) ? NH_LAST_WEIGHT.total_kg : 0;
    setWeightInputs(unit, tot);
  }

  // Non-AJAX submit path
  $(document).on('submit', 'form.cart', function () {
    nhWriteWeightsToForm();
  });

  // Some themes trigger programmatic submit on click
  $(document).on('click', '.single_add_to_cart_button', function () {
    nhWriteWeightsToForm();
  });

  /* =================== Inject weight into AJAX add-to-cart ====== */
  $(document).on('adding_to_cart', function(e, $button, data){
    try {
      nhWriteWeightsToForm();
      if (NH_LAST_WEIGHT && (NH_LAST_WEIGHT.unit_kg > 0 || NH_LAST_WEIGHT.total_kg > 0)) {
        data['nh_custom_unit_kg']  = String(NH_LAST_WEIGHT.unit_kg);
        data['nh_custom_total_kg'] = String(NH_LAST_WEIGHT.total_kg);
      } else {
        const $f = $cartForm();
        var unit = $f.find('input[name="nh_custom_unit_kg"]').val() || '0';
        var tot  = $f.find('input[name="nh_custom_total_kg"]').val() || '0';
        data['nh_custom_unit_kg']  = unit;
        data['nh_custom_total_kg'] = tot;
      }
      ensureCustomModeHidden();
    } catch(err){ /* silent */ }
  });

  /* =================== Boot ==================================== */
  $(function () {
    if (!CC.enabled) return;

    ensureWeightInputs();
    ensureCustomModeHidden();

    if (!$modeRadios().length){
      $('#nh_custom_mode').val('1');
      applyLimitsFromMeta();
      placeSummaryAfterCustom();
      renderPerm2AndFee();
      recalcCustom();
      updateButtonState();
      ensurePSHomeOnce();
      return;
    }

    if (currentMode() === 'custom'){
      $('#nh_custom_mode').val('1');
      $mmWrap().show();
      applyLimitsFromMeta();
      placeSummaryAfterCustom();
      renderPerm2AndFee();
      recalcCustom();
    } else {
      $('#nh_custom_mode').val('0');
      $mmWrap().hide();
      placeSummaryAtHome();
    }
    updateButtonState();
    ensurePSHomeOnce();
  });

})(jQuery);
