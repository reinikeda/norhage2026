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
    step_m:0.01,
    min_w:'', max_w:'', min_l:'', max_l:'',
    kg_per_m2: 0,
    unit: 'mm',
    type: 'planar'
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

    // Show price row and value
    $psBox().find('.nh-ps-perm2').css('display', 'flex');
    $psBox().find('[data-ps="perm2"]').html(perm2HTML);

    // Hide the cutting fee row entirely if fee is 0 or empty
    var $feeRow = $psBox().find('.nh-ps-cutfee');
    if (fee > 0) {
      $feeRow.css('display', 'flex');
      $feeRow.find('.nh-ps-val').html(cutHTML);
    } else {
      $feeRow.css('display', 'none');
    }

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
    var $w = $('#nh_width_mm'), $l = $('#nh_length_mm'), $lm = $('#nh_length_m'), $wrap = $mmWrap();

    // mm step (default 1)
    var step_mm = (CC.step != null && CC.step !== '') ? Number(CC.step) : 1;
    if (!isFinite(step_mm) || step_mm <= 0) step_mm = 1;

    // Determine metre step sensibly:
    // Priority:
    // 1) CC.step_m (explicit)
    // 2) CC.step (fallback) — but handle conversions based on size and unit
    var step_m = null;
    if (CC.step_m != null && CC.step_m !== '') {
      step_m = Number(CC.step_m);
    } else if (CC.step != null && CC.step !== '') {
      step_m = Number(CC.step); // admin may have entered step in the "single" field (unit-specific)
    }

    if (!isFinite(step_m) || step_m <= 0) {
      // fallback: derive from mm step
      step_m = step_mm / 1000;
    } else {
      // normalize: if value looks like mm (very large, e.g. >=1000) convert to metres
      if (step_m >= 1000) step_m = step_m / 1000;
      // if it's an integer >=1 and unit isn't metres, convert to metres for the metre input
      // but if unit is 'm' and admin intentionally entered integer (e.g. 5) we should keep it as metres.
      if (step_m >= 1 && CC.unit !== 'm') {
        step_m = step_m / 1000;
      }
    }
    // final guard
    if (!isFinite(step_m) || step_m <= 0) step_m = 0.01;

    function setMinMax($el, min, max){
      $el.removeAttr('min max');
      if (min !== '' && min != null) $el.attr('min', String(min));
      if (max !== '' && max != null) $el.attr('max', String(max));
    }
    function rangePH(min,max,unit){
      if (min !== '' && max !== '') return min + '–' + max + ' ' + unit;
      if (min !== '') return '≥ ' + min + ' ' + unit;
      if (max !== '') return '≤ ' + max + ' ' + unit;
      return '';
    }

    // mm inputs
    $w.add($l)
      .prop({ disabled:false, readOnly:false })
      .attr({ type:'number', inputmode:'numeric', step:String(step_mm), pattern:'[0-9]*' })
      .off('.ccDigits .ccClamp wheel');

    setMinMax($w, CC.min_w, CC.max_w);
    setMinMax($l, CC.min_l, CC.max_l);
    $w.attr('placeholder', rangePH(CC.min_w, CC.max_w, 'mm'));
    $l.attr('placeholder', rangePH(CC.min_l, CC.max_l, 'mm'));

    // Helper to normalize metre min/max values:
    function normalizeToMeters(v) {
      if (v === '' || v == null) return '';
      var n = Number(v);
      if (!isFinite(n)) return '';
      // If value seems large (likely mm) convert to metres
      if (n >= 1000) return String(n / 1000);
      // otherwise assume it's already in metres
      return String(n);
    }

    // metre input: choose behaviour depending on unit and available meta
    var lm_min = '', lm_max = '';
    if (CC.unit === 'm') {
      // admin likely saved mins as metres; if they look like mm (>=1000) convert
      lm_min = (CC.min_l != null && CC.min_l !== '') ? normalizeToMeters(CC.min_l) : '';
      lm_max = (CC.max_l != null && CC.max_l !== '') ? normalizeToMeters(CC.max_l) : '';
    } else {
      // unit is mm — convert mm limits to metres for the metre input display
      lm_min = (CC.min_l != null && CC.min_l !== '') ? String(Number(CC.min_l) / 1000) : '';
      lm_max = (CC.max_l != null && CC.max_l !== '') ? String(Number(CC.max_l) / 1000) : '';
    }

    $lm.prop({ disabled:false, readOnly:false })
       .attr({ type:'number', inputmode:'decimal', step:String(step_m), pattern:'[0-9]*' })
       .off('.ccDigits .ccClamp wheel');

    setMinMax($lm, lm_min, lm_max);
    $lm.attr('placeholder', rangePH(lm_min, lm_max, 'm'));

    // Input handlers for mm inputs
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

    // metre decimal handler
    $wrap.on('input.ccDigits', 'input.nh-m-input', function(){
      var $el = $(this), raw = String($el.val());
      // allow decimals and comma -> convert later
      var cleaned = raw.replace(/[^0-9\.,]/g,'').replace(',','.');
      if (raw !== cleaned) $el.val(cleaned);
    });

    $wrap.on('change.ccClamp blur.ccClamp', 'input.nh-m-input', function(){
      var $el = $(this);
      var raw = $el.val();
      if (raw === '') { recalcCustom(); updateButtonState(); return; }

      var val = parseFloat(String(raw).replace(',', '.'));
      var hasMin = $el.is('[min]'), hasMax = $el.is('[max]');
      var min = hasMin ? parseFloat($el.attr('min')) : null;
      var max = hasMax ? parseFloat($el.attr('max')) : null;

      if (!isFinite(val)) { $el.val(''); recalcCustom(); updateButtonState(); return; }

      var step = parseFloat($el.attr('step')) || step_m;
      if (hasMin && val < min) val = min;
      if (hasMax && val > max) val = max;
      // Snap to step; if you want strict multiples of step (i.e. always 0, step, 2*step...) set min to 0 or desired base
      val = snapToStep(val, step, (hasMin ? min : 0));

      // Format value preserving decimals
      $el.val(String(val));
      recalcCustom();
      updateButtonState();
    });

    // prevent wheel on any known dimension inputs (include metre)
    $mmInputs().add('#nh_length_m').on('wheel', function(e){ e.preventDefault(); });
    $mmWrap().find('table.variations').css('border-bottom','0');
  }

  /* =================== Qty helper =============================== */
  function qty(){
    var n = parseInt($qtyInput().val(), 10);
    return (Number.isFinite(n) && n > 0) ? n : 1;
  }

  // cache last computed values (exposed so bundle-add-to-cart.js can read it)
  window.NH_LAST_WEIGHT = window.NH_LAST_WEIGHT || { unit_kg: 0, total_kg: 0, area_m2: 0, wmm: 0, lmm: 0, lm:0, qty: 1 };
  function setLastWeight(obj){
    window.NH_LAST_WEIGHT = Object.assign(
      { unit_kg:0,total_kg:0,area_m2:0,wmm:0,lmm:0,lm:0,qty:1 },
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
    var lm_raw = $('#nh_length_m').val() || '';
    var lm = lm_raw !== '' ? parseFloat(String(lm_raw).replace(',', '.')) : 0;

    // Variable product and no variation selected: keep UI calm
    if (isVariableProduct() && !variationSelected()) {
      ensureWeightInputs();
      setWeightInputs(0, 0);
      setLastWeight({ unit_kg:0,total_kg:0,area_m2:0,wmm:wmm||0,lmm:lmm||0,lm:lm||0,qty:q });
      writeSummary({ unit: '—', total: '—', weight_html: '—' });
      renderPerm2AndFee();
      return;
    }

    ensureWeightInputs();
    setWeightInputs(0, 0);

    // PLANAR (mm) flow
    if ( CC.type === 'planar' && CC.unit === 'mm' ) {
      var valid = Number.isFinite(wmm) && wmm > 0 && Number.isFinite(lmm) && lmm > 0;

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

          setLastWeight({ unit_kg: unit_kg, total_kg: total_kg, area_m2: area, wmm: wmm, lmm: lmm, lm: 0, qty: q });
          setWeightInputs(unit_kg, total_kg);
        } else {
          setLastWeight({ unit_kg: 0, total_kg: 0, area_m2: area, wmm: wmm, lmm: lmm, lm: 0, qty: q });
        }

      } else {
        writeSummary({ unit: '—', total: '—', weight_html: '—' });
        $psBox().find('.nh-ps-weight').css('display','');
        setLastWeight({ unit_kg: 0, total_kg: 0, area_m2: 0, wmm: 0, lmm: 0, lm:0, qty: 1 });
      }

      renderPerm2AndFee();
      return;
    }

    // LINEAR (m) flow
    if ( CC.type === 'linear' && CC.unit === 'm' ) {
      var valid_m = Number.isFinite(lm) && lm > 0;

      ensureWeightInputs();
      setWeightInputs(0, 0);

      if (valid_m) {
        // For linear: CC.perm2_reg_disp holds price per metre (admin must set product price as price/m)
        var price_m_reg = Number(CC.perm2_reg_disp || 0);
        var price_m_sale = Number(CC.perm2_sale_disp || 0) || price_m_reg;
        var fee = Number(CC.cut_fee || 0);

        if (price_m_reg > 0 || price_m_sale > 0) {
          var unit_reg   = lm * price_m_reg + Math.max(0, fee);
          var unit_sale  = lm * price_m_sale + Math.max(0, fee);
          var total_reg  = unit_reg  * q;
          var total_sale = unit_sale * q;

          writeSummary({
            unit_html:  pairHTML(unit_reg,  unit_sale, money),
            total_html: pairHTML(total_reg, total_sale, money)
          });
        } else {
          writeSummary({ unit: '—', total: '—' });
        }

        // Weight: no automatic linear weight conversion by default
        setLastWeight({ unit_kg: 0, total_kg: 0, area_m2: 0, wmm: 0, lmm: 0, lm: lm, qty: q });
        setWeightInputs(0, 0);
      } else {
        writeSummary({ unit: '—', total: '—', weight_html: '—' });
        setLastWeight({ unit_kg: 0, total_kg: 0, area_m2: 0, wmm: 0, lmm: 0, lm:0, qty: 1 });
      }

      renderPerm2AndFee();
      return;
    }

    // Fallback: unknown config — keep previous display
    writeSummary({ unit: '—', total: '—' });
    renderPerm2AndFee();
  }

  /* =================== Add-to-cart button state ================= */
  var BTN_SEL = '.single_add_to_cart_button';
  function updateButtonState() {
    var $btn = $(BTN_SEL);
    if (!$btn.length) return;

    var dimsOk = true;

    if ( CC.type === 'planar' && CC.unit === 'mm' ) {
      var wOk = parseInt($('#nh_width_mm').val(), 10);
      var lOk = parseInt($('#nh_length_mm').val(), 10);
      dimsOk = Number.isFinite(wOk) && wOk > 0 && Number.isFinite(lOk) && lOk > 0;
    } else if ( CC.type === 'linear' && CC.unit === 'm' ) {
      var lm = parseFloat( String($('#nh_length_m').val() || '').replace(',', '.') );
      dimsOk = Number.isFinite(lm) && lm > 0;
    }

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
      var $inp  = $wrap.find('input.nh-mm-input, input.nh-m-input');
      if (!$inp.length || $inp.is(':disabled') || $inp.is('[readonly]')) return;

      var step   = parseFloat($inp.attr('step')) || 1;
      var hasMin = $inp.is('[min]'), hasMax = $inp.is('[max]');
      var min    = hasMin ? parseFloat($inp.attr('min')) : null;
      var max    = hasMax ? parseFloat($inp.attr('max')) : null;

      var raw = $inp.val();
      var val = raw === '' ? NaN : parseFloat(String(raw).replace(',', '.'));
      if (isNaN(val)) val = (min != null ? min : 0);
      val += $btn.hasClass('plus') ? step : -step;

      if (step > 0) {
        if (min != null && isFinite(min)) val = min + Math.round((val - min) / step) * step;
        else                               val = Math.round(val / step) * step;
      }
      if (min != null && val < min) val = min;
      if (max != null && val > max) val = max;

      // Use locale-friendly decimal dot
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

    if (variation.nh_cc_kg_per_m2 != null)       CC.kg_per_m2       = Number(variation.nh_cc_kg_per_m2) || 0;

    // Variation may also carry unit/type (from parent). Keep CC.unit/type from localized data.
    renderPerm2AndFee();
    recalcCustom();
    updateButtonState();
  });

  $(document).on('reset_data', 'form.variations_form', function(){
    if (!CC.enabled) return;
    CC.perm2_reg_disp  = 0;
    CC.perm2_sale_disp = 0;
    CC.kg_per_m2       = 0;
    renderPerm2AndFee();
    recalcCustom();
    updateButtonState();
  });

  /* =================== UI show/hide based on unit/type ============ */
  function applyUiMode() {
    if ( CC.type === 'linear' && CC.unit === 'm' ) {
      // show length_m, hide mm fields
      $('.nh-row-length-m').show();
      $('.nh-row-width, .nh-row-length').hide();
    } else {
      $('.nh-row-length-m').hide();
      $('.nh-row-width, .nh-row-length').show();
    }
  }

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

    applyUiMode();

    // For variable products: summary will fill after variation selected
    renderPerm2AndFee();
    recalcCustom();
    updateButtonState();

    // Keep button state synced as selects change (before found_variation fires)
    $(document).on('change', 'form.variations_form select', function(){
      updateButtonState();
      recalcCustom();
    });

    // Recalc on mm/m inputs
    $(document).on('input change', '#nh_width_mm, #nh_length_mm, #nh_length_m', function(){
      recalcCustom();
      updateButtonState();
    });
  });

})(jQuery);
