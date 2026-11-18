/**
 * PVC strip curtain calculator — live quote + add kit to cart.
 */
(function () {
  'use strict';

  var cfg = window.NH_PSC || {};
  var root = document.getElementById('nh-pvc-strip-calculator');
  if (!root || !cfg.quote) return;

  var form = document.getElementById('nh-psc-form');
  var itemsEl = root.querySelector('[data-offer-items]');
  var metaEl = root.querySelector('[data-offer-meta]');
  var totalsEl = root.querySelector('.nh-psc__totals');
  var taxEl = root.querySelector('[data-offer-tax]');
  var totalEl = root.querySelector('[data-offer-total]');
  var atc = root.querySelector('[data-offer-atc]');
  var statusEl = root.querySelector('[data-offer-status]');
  var planEl = root.querySelector('[data-strip-plan]');
  var dimsEl = root.querySelector('[data-calc-dims]');
  var hintEl = root.querySelector('[data-mount-hint]');
  var recEl = root.querySelector('[data-rec-width]');
  var mobileBar = root.querySelector('[data-mobile-bar]');
  var mobileTotal = root.querySelector('[data-mobile-total]');
  var mobileCta = root.querySelector('[data-mobile-cta]');
  var wizardBack = root.querySelector('[data-wizard-back]');
  var wizardNext = root.querySelector('[data-wizard-next]');

  var timer = null;
  var posting = false;
  var wizardStep = 1;
  var lastPayload = null;

  function i18n(key, fallback) {
    return (cfg.i18n && cfg.i18n[key]) || fallback || key;
  }

  function setWizardStep(step) {
    wizardStep = Math.max(1, Math.min(3, Number(step) || 1));
    root.setAttribute('data-wizard-step', String(wizardStep));
    root.querySelectorAll('[data-panel]').forEach(function (panel) {
      var n = Number(panel.getAttribute('data-panel'));
      var on = n === wizardStep;
      panel.hidden = !on;
      panel.classList.toggle('is-active', on);
    });
    root.querySelectorAll('[data-goto]').forEach(function (btn) {
      var n = Number(btn.getAttribute('data-goto'));
      var on = n === wizardStep;
      btn.classList.toggle('is-active', on);
      btn.classList.toggle('is-done', n < wizardStep);
      if (on) btn.setAttribute('aria-current', 'step');
      else btn.removeAttribute('aria-current');
    });
    if (wizardBack) wizardBack.hidden = wizardStep <= 1;
    if (wizardNext) {
      wizardNext.hidden = wizardStep >= 3;
      wizardNext.textContent = wizardStep === 2
        ? i18n('reviewKit', 'Review kit')
        : i18n('continue', 'Continue');
    }
    if (mobileCta) {
      mobileCta.textContent = wizardStep >= 3
        ? i18n('addKit', 'Add kit to basket')
        : (wizardStep === 2 ? i18n('reviewKit', 'Review kit') : i18n('continue', 'Continue'));
    }
  }

  function collect() {
    return {
      width_mm: Number((form.querySelector('[name="width_mm"]') || {}).value) || 0,
      height_mm: Number((form.querySelector('[name="height_mm"]') || {}).value) || 0,
      mounting: (form.querySelector('[name="mounting"]:checked') || {}).value || 'face',
      strip_width_mm: Number((form.querySelector('[name="strip_width_mm"]:checked') || {}).value) || 200,
      overlap: (form.querySelector('[name="overlap"]:checked') || {}).value || 'med',
      strip_type: (form.querySelector('[name="strip_type"]') || {}).value || 'smooth',
      grade: (form.querySelector('[name="grade"]') || {}).value || 'standard',
      postcode: (form.querySelector('[name="postcode"]') || {}).value || ''
    };
  }

  function syncOverlapLabels() {
    var w = String(collect().strip_width_mm || 200);
    var row = (cfg.overlapPct && (cfg.overlapPct[w] || cfg.overlapPct[Number(w)])) || {};
    ['min', 'med', 'max'].forEach(function (level) {
      var el = form.querySelector('[data-overlap-pct="' + level + '"]');
      if (!el) return;
      var frac = Number(row[level]);
      if (!isFinite(frac) || frac <= 0) {
        frac = level === 'min' ? 0.36 : (level === 'max' ? 0.98 : 0.77);
      }
      el.textContent = Math.round(frac * 100) + '%';
    });
  }

  function fmt(amount) {
    var c = cfg.currency || {};
    var n = Number(amount) || 0;
    var decimals = c.trim ? 0 : (c.decimals != null ? c.decimals : 2);
    var parts = n.toFixed(decimals).split('.');
    var whole = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thousand || ' ');
    var out = decimals ? whole + (c.decimal || ',') + parts[1] : whole;
    var sym = c.symbol || '';
    if (c.pos === 'left') return sym + out;
    if (c.pos === 'left_space') return sym + ' ' + out;
    if (c.pos === 'right') return out + sym;
    return out + ' ' + sym;
  }

  function showPrice(el, text) {
    if (!el) return;
    el.textContent = text || '';
  }

  function setStatus(msg, kind) {
    if (!statusEl) return;
    if (!msg) {
      statusEl.hidden = true;
      statusEl.textContent = '';
      return;
    }
    statusEl.hidden = false;
    statusEl.textContent = msg;
    statusEl.classList.toggle('is-error', kind === 'error');
    statusEl.classList.toggle('is-ok', kind === 'ok');
  }

  function kitGroup(role) {
    return (role === 'rail' || role === 'hanger') ? 'hardware' : 'strips';
  }

  function render(payload) {
    lastPayload = payload;
    var items = (payload && payload.items) || [];
    var missing = (payload && payload.missing) || [];
    itemsEl.innerHTML = '';

    if (!items.length && !missing.length) {
      itemsEl.innerHTML = '<li class="nh-psc__empty">' + i18n('loading', 'Enter an opening size to see the kit.') + '</li>';
      atc.disabled = true;
      totalsEl.hidden = true;
      if (mobileBar) mobileBar.hidden = true;
      drawPlan(null);
      return;
    }

    var taxDisplay = payload.tax_display || cfg.taxDisplay || 'incl';
    var buckets = { strips: [], hardware: [] };

    function addRow(item, isMissing, list) {
      var li = document.createElement('li');
      if (isMissing) li.className = 'is-missing';
      var name = document.createElement('div');
      name.className = 'nh-psc__item-name';
      name.textContent = item.name || item.label || '';
      var spec = document.createElement('div');
      spec.className = 'nh-psc__item-spec';
      spec.textContent = item.spec || '';
      var price = document.createElement('div');
      price.className = 'nh-psc__item-price';
      price.textContent = isMissing ? '—' : (item.line_display || fmt(taxDisplay === 'excl' ? item.line_ex : item.line_inc));
      li.appendChild(name);
      li.appendChild(price);
      li.appendChild(spec);
      list.push(li);
    }

    items.forEach(function (item) { addRow(item, false, buckets[kitGroup(item.role)] || buckets.strips); });
    missing.forEach(function (item) { addRow(item, true, buckets[kitGroup(item.role)] || buckets.strips); });

    ['strips', 'hardware'].forEach(function (key) {
      if (!buckets[key].length) return;
      var header = document.createElement('li');
      header.className = 'nh-psc__group';
      header.textContent = key === 'hardware'
        ? i18n('groupHardware', 'Hardware')
        : i18n('groupStrips', 'Strips');
      itemsEl.appendChild(header);
      buckets[key].forEach(function (li) { itemsEl.appendChild(li); });
    });

    if (metaEl && payload.meta) {
      metaEl.textContent =
        i18n('strips', '%d strips').replace('%d', payload.meta.strip_count) +
        ' · ' +
        i18n('metres', '%s m total').replace('%s', String(payload.meta.total_metres));
    }
    drawPlan(payload.meta);
    syncHints(payload.meta);

    if (payload.currency) cfg.currency = payload.currency;
    if (payload.totals) {
      totalsEl.hidden = false;
      showPrice(taxEl, payload.totals.tax_formatted || fmt(payload.totals.tax));
      var totalText = payload.totals.total_formatted || fmt(taxDisplay === 'excl' ? payload.totals.ex : payload.totals.inc);
      showPrice(totalEl, totalText);
      if (mobileTotal) showPrice(mobileTotal, totalText);
      if (mobileBar) mobileBar.hidden = false;
    }

    atc.disabled = items.length === 0;
    if (missing.length) setStatus(i18n('missing'), 'error');
    else setStatus('', '');
  }

  function syncHints(meta) {
    if (!meta) return;
    if (dimsEl) {
      var dims = i18n('calcDims', 'Calc. %1$d × %2$d mm')
        .replace('%1$d', meta.calc_width_mm)
        .replace('%2$d', meta.calc_height_mm);
      if (meta.overlap_pct != null) {
        dims += ' · ' + i18n('overlapPct', '%d%% overlap').replace('%d', meta.overlap_pct);
      }
      dimsEl.textContent = dims;
    }
    if (hintEl) {
      hintEl.textContent = meta.mounting === 'soffit'
        ? i18n('mountSoffit', 'Into the opening: height − 45 mm for the rail.')
        : i18n('mountFace', 'Above the opening: height + 30 mm; width gains a sealing allowance.');
    }
    if (recEl && meta.recommended_width_mm) {
      recEl.textContent = i18n('recWidth', 'Recommended for this height: %d mm strips.').replace('%d', meta.recommended_width_mm);
    }
  }

  function drawPlan(meta) {
    if (!planEl) return;
    if (!meta || !meta.strip_count) {
      planEl.innerHTML = '';
      return;
    }
    var count = Math.max(1, Number(meta.strip_count) || 1);
    var stripMm = Math.max(1, Number(meta.strip_width_mm) || 200);
    var calcW = Math.max(stripMm, Number(meta.calc_width_mm) || stripMm);
    // Geometry implied by strip count: average overlap between neighbours.
    var overlapMm = count > 1 ? Math.max(0, (count * stripMm - calcW) / (count - 1)) : 0;
    var pitchMm = Math.max(1, stripMm - overlapMm);
    var spanMm = stripMm + (count - 1) * pitchMm;
    var viz = document.createElement('div');
    viz.className = 'nh-psc__strip-viz';
    viz.setAttribute('role', 'img');
    viz.setAttribute(
      'aria-label',
      i18n('strips', '%d strips').replace('%d', count) +
        (meta.overlap_pct != null
          ? ', ' + i18n('overlapPct', '%d%% overlap').replace('%d', meta.overlap_pct)
          : '')
    );
    for (var i = 0; i < count; i++) {
      var s = document.createElement('span');
      if (i > 0) s.classList.add('is-overlap');
      s.style.width = ((stripMm / spanMm) * 100).toFixed(3) + '%';
      s.style.left = ((i * pitchMm / spanMm) * 100).toFixed(3) + '%';
      viz.appendChild(s);
    }
    var note = document.createElement('p');
    note.className = 'nh-psc__plan-note';
    note.textContent = i18n('planMeta', '%1$d × %2$d×%3$d mm · %4$s m · %5$s m²')
      .replace('%1$d', count)
      .replace('%2$d', meta.strip_width_mm)
      .replace('%3$d', meta.strip_thickness_mm)
      .replace('%4$s', String(meta.total_metres))
      .replace('%5$s', String(meta.area_m2));
    if (meta.overlap_pct != null) {
      note.textContent += ' · ' + i18n('overlapPct', '%d%% overlap').replace('%d', meta.overlap_pct);
    }
    planEl.innerHTML = '';
    planEl.appendChild(viz);
    planEl.appendChild(note);
  }

  function quote() {
    root.classList.add('is-loading');
    var body = new URLSearchParams();
    body.set('security', cfg.nonce);
    body.set('config', JSON.stringify(collect()));

    fetch(cfg.quote, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        root.classList.remove('is-loading');
        if (!json || !json.success) {
          atc.disabled = true;
          setStatus((json && json.data && json.data.message) || i18n('error'), 'error');
          return;
        }
        render(json.data);
      })
      .catch(function () {
        root.classList.remove('is-loading');
        atc.disabled = true;
        setStatus(i18n('error'), 'error');
      });
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(quote, 280);
  }

  function addToCart() {
    if (posting || atc.disabled) return;
    posting = true;
    atc.disabled = true;
    setStatus(i18n('adding'), '');

    var body = new URLSearchParams();
    body.set('security', cfg.nonce);
    body.set('config', JSON.stringify(collect()));

    fetch(cfg.add, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        posting = false;
        atc.disabled = false;
        if (!json || !json.success) {
          setStatus((json && json.data && json.data.message) || i18n('error'), 'error');
          return;
        }
        setStatus('', 'ok');
        var data = json.data || {};
        if (window.jQuery) {
          var $ = window.jQuery;
          $(document.body).trigger('added_to_cart', [data.fragments || {}, data.cart_hash || '', $(atc)]);
          $(document.body).trigger('wc_fragment_refresh');
        }
      })
      .catch(function () {
        posting = false;
        atc.disabled = false;
        setStatus(i18n('error'), 'error');
      });
  }

  form.addEventListener('input', schedule);
  form.addEventListener('change', schedule);
  atc.addEventListener('click', addToCart);

  root.querySelectorAll('[data-goto]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setWizardStep(btn.getAttribute('data-goto'));
    });
  });
  if (wizardBack) wizardBack.addEventListener('click', function () { setWizardStep(wizardStep - 1); });
  if (wizardNext) wizardNext.addEventListener('click', function () { setWizardStep(wizardStep + 1); });
  if (mobileCta) {
    mobileCta.addEventListener('click', function () {
      if (wizardStep >= 3) addToCart();
      else setWizardStep(wizardStep + 1);
    });
  }

  setWizardStep(1);
  syncOverlapLabels();
  form.addEventListener('change', function (ev) {
    var t = ev.target;
    if (t && t.name === 'strip_width_mm') syncOverlapLabels();
  });
  quote();
})();
