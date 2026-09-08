/**
 * Terrace roof calculator — live quote + add kit to cart.
 */
(function () {
  'use strict';

  var cfg = window.NH_TC || {};
  var root = document.getElementById('nh-terrace-calculator');
  if (!root || !cfg.quote) return;

  var form = document.getElementById('nh-tc-form');
  var itemsEl = root.querySelector('[data-offer-items]');
  var metaEl = root.querySelector('[data-offer-meta]');
  var totalsEl = root.querySelector('.nh-tc__totals');
  var taxEl = root.querySelector('[data-offer-tax]');
  var totalEl = root.querySelector('[data-offer-total]');
  var atc = root.querySelector('[data-offer-atc]');
  var statusEl = root.querySelector('[data-offer-status]');
  var recEl = root.querySelector('[data-rec-cc]');
  var thkSel = form.querySelector('[name="thickness"]');
  var colSel = form.querySelector('[name="colour"]');
  var matSel = form.querySelector('[name="material"]');
  var ccInput = form.querySelector('[name="cc_mm"]');

  var timer = null;
  var lastPayload = null;
  var posting = false;

  var colourLabels = {
    clear: 'Clear',
    bronze: 'Bronze',
    opal: 'Opal',
    anthracite: 'Anthracite'
  };

  function i18n(key, fallback) {
    return (cfg.i18n && cfg.i18n[key]) || fallback || key;
  }

  function fillSelect(sel, values, labels, preferred) {
    var current = sel.value;
    sel.innerHTML = '';
    values.forEach(function (v) {
      var opt = document.createElement('option');
      opt.value = v;
      opt.textContent = labels && labels[v] ? labels[v] : v;
      sel.appendChild(opt);
    });
    if (preferred && values.indexOf(preferred) !== -1) sel.value = preferred;
    else if (values.indexOf(current) !== -1) sel.value = current;
    else if (values.length) sel.value = values[0];
  }

  function syncSheetOptions() {
    var tree = cfg.tree || {};
    var material = matSel.value;
    var thkMap = tree[material] || {};
    var thicknesses = Object.keys(thkMap);
    if (thicknesses.indexOf('10') !== -1) {
      thicknesses.sort(function (a, b) { return Number(a) - Number(b); });
    }
    fillSelect(thkSel, thicknesses, null, '10');
    var colours = thkMap[thkSel.value] || [];
    fillSelect(colSel, colours, colourLabels, 'clear');
    updateRecCc();
  }

  function updateRecCc() {
    var rec = (cfg.recCc && cfg.recCc[thkSel.value]) || 600;
    if (recEl) recEl.textContent = i18n('recCc', 'Recommended CC: %s mm').replace('%s', rec);
    if (ccInput && (!ccInput.dataset.touched || ccInput.dataset.touched === '0')) {
      ccInput.value = rec;
    }
  }

  function fmt(n) {
    var p = cfg.currency || {};
    var num = Number(n || 0);
    if (!isFinite(num)) num = 0;
    var decs = Number.isFinite(Number(p.decimals)) ? parseInt(p.decimals, 10) : 2;
    var parts = num.toFixed(decs).split('.');
    var thousand = p.thousand != null ? String(p.thousand) : ' ';
    var decimal = p.decimal != null ? String(p.decimal) : ',';
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousand);
    var value = decs > 0 ? parts[0] + decimal + (parts[1] || '') : parts[0];
    var symbol = p.symbol || '';
    var nbsp = '\u00A0';
    switch (p.pos) {
      case 'left': return symbol + value;
      case 'left_space': return symbol + nbsp + value;
      case 'right': return value + symbol;
      default: return value + (symbol ? nbsp + symbol : '');
    }
  }

  function collect() {
    var data = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.disabled) return;
      data[el.name] = el.value;
    });
    return data;
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

  function render(payload) {
    lastPayload = payload;
    var items = (payload && payload.items) || [];
    var missing = (payload && payload.missing) || [];
    itemsEl.innerHTML = '';

    if (!items.length && !missing.length) {
      itemsEl.innerHTML = '<li class="nh-tc__empty">' + i18n('loading', 'Enter a size to see the kit.') + '</li>';
      atc.disabled = true;
      totalsEl.hidden = true;
      return;
    }

    function addRow(item, isMissing) {
      var li = document.createElement('li');
      if (isMissing) li.className = 'is-missing';
      var name = document.createElement('div');
      name.className = 'nh-tc__item-name';
      name.textContent = item.name || item.label || '';
      var spec = document.createElement('div');
      spec.className = 'nh-tc__item-spec';
      var qtyLabel = i18n('pcs', '%s pcs').replace('%s', item.qty);
      spec.textContent = [qtyLabel, item.spec].filter(Boolean).join(' · ');
      var price = document.createElement('div');
      price.className = 'nh-tc__item-price';
      var taxDisplay = payload.tax_display || cfg.taxDisplay || 'incl';
      price.textContent = isMissing ? '—' : fmt(taxDisplay === 'excl' ? item.line_ex : item.line_inc);
      li.appendChild(name);
      li.appendChild(price);
      li.appendChild(spec);
      itemsEl.appendChild(li);
    }

    items.forEach(function (item) { addRow(item, false); });
    missing.forEach(function (item) { addRow(item, true); });

    if (metaEl && payload.meta) {
      metaEl.textContent = i18n('sheets', '%d sheets').replace('%d', payload.meta.sheet_count);
    }

    if (payload.totals) {
      totalsEl.hidden = false;
      taxEl.textContent = fmt(payload.totals.tax);
      totalEl.textContent = fmt(payload.totals.inc);
    }

    atc.disabled = items.length === 0;
    if (missing.length) setStatus(i18n('missing'), 'error');
    else setStatus('', '');
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

  matSel.addEventListener('change', function () {
    syncSheetOptions();
    schedule();
  });
  thkSel.addEventListener('change', function () {
    var tree = cfg.tree || {};
    var thkMap = tree[matSel.value] || {};
    fillSelect(colSel, thkMap[thkSel.value] || [], colourLabels, 'clear');
    updateRecCc();
    schedule();
  });
  ccInput.addEventListener('input', function () {
    ccInput.dataset.touched = '1';
  });
  form.addEventListener('input', schedule);
  form.addEventListener('change', schedule);
  atc.addEventListener('click', addToCart);

  syncSheetOptions();
  quote();
})();
