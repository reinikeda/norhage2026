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
    var red = form.querySelector('[name="red_edges"]');
    return {
      width_mm: Number((form.querySelector('[name="width_mm"]') || {}).value) || 0,
      height_mm: Number((form.querySelector('[name="height_mm"]') || {}).value) || 0,
      mounting: (form.querySelector('[name="mounting"]:checked') || {}).value || 'face',
      strip_width_mm: Number((form.querySelector('[name="strip_width_mm"]:checked') || {}).value) || 200,
      overlap: (form.querySelector('[name="overlap"]:checked') || {}).value || 'med',
      strip_type: (form.querySelector('[name="strip_type"]') || {}).value || 'smooth',
      grade: (form.querySelector('[name="grade"]') || {}).value || 'standard',
      red_edges: !!(red && red.checked),
      postcode: (form.querySelector('[name="postcode"]') || {}).value || ''
    };
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
      dimsEl.textContent =
        'Calc. ' + meta.calc_width_mm + ' × ' + meta.calc_height_mm + ' mm' +
        (meta.overlap_pct != null ? ' · ' + meta.overlap_pct + '% overlap' : '');
    }
    if (hintEl) {
      hintEl.textContent = meta.mounting === 'soffit'
        ? 'Into the opening: height − 45 mm for the rail.'
        : 'Above the opening: height + 30 mm; width gains a sealing allowance.';
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
    var viz = document.createElement('div');
    viz.className = 'nh-psc__strip-viz';
    viz.setAttribute('role', 'img');
    viz.setAttribute('aria-label', i18n('strips', '%d strips').replace('%d', meta.strip_count));
    for (var i = 0; i < meta.strip_count; i++) {
      var s = document.createElement('span');
      if (meta.red_edges && (i === 0 || i === meta.strip_count - 1)) s.className = 'is-red';
      else if (i > 0) s.className = 'is-overlap';
      viz.appendChild(s);
    }
    var note = document.createElement('p');
    note.className = 'nh-psc__plan-note';
    note.textContent =
      meta.strip_count + ' × ' + meta.strip_width_mm + '×' + meta.strip_thickness_mm + ' mm · ' +
      meta.total_metres + ' m · ' + meta.area_m2 + ' m²';
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
  quote();
})();
