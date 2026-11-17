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
  var planEl = root.querySelector('[data-sheet-plan]');
  var inlineThumbs = {
    sheet: root.querySelector('[data-inline-thumb="sheet"]'),
    connecting: root.querySelector('[data-inline-thumb="connecting"]'),
    finish: root.querySelector('[data-inline-thumb="finish"]')
  };
  var thkSel = form.querySelector('[name="thickness"]');
  var colSel = form.querySelector('[name="colour"]');
  var matSel = form.querySelector('[name="material"]');
  var ccInput = form.querySelector('[name="cc_mm"]');
  var connectSel = form.querySelector('[name="connecting_profile"]');
  var connectCol = form.querySelector('[name="connecting_color"]');
  var finishSel = form.querySelector('[name="finish_profile"]');
  var finishCol = form.querySelector('[name="finish_color"]');

  var timer = null;
  var lastPayload = null;
  var posting = false;
  var ccCustom = false;
  var jointsPinned = false;
  var defaultCc = Number(cfg.defaultCc) || 600;
  var widthInput = form.querySelector('[name="width_mm"]');
  var lengthInput = form.querySelector('[name="length_mm"]');
  var overhangInput = form.querySelector('[name="overhang_mm"]');
  var supportInput = form.querySelector('[name="support_mm"]');
  var stockCard = root.querySelector('[data-stock-card]');
  var stockChannels = root.querySelector('[data-stock-channels]');
  var stockChannelEl = root.querySelector('[data-stock-channel]');
  var stockSizes = root.querySelector('[data-stock-sizes]');
  var stockWidthsEl = root.querySelector('[data-stock-widths]');
  var stockLengthInput = root.querySelector('[data-stock-length]');
  var stockEmpty = root.querySelector('[data-stock-empty]');
  var stockOos = root.querySelector('[data-stock-oos]');
  var stockState = { key: '', channel: '', width: '', length: '' };
  var stockSig = '';
  var wizardStep = 1;
  var mobileBar = root.querySelector('[data-mobile-bar]');
  var mobileTotal = root.querySelector('[data-mobile-total]');
  var mobileCta = root.querySelector('[data-mobile-cta]');
  var wizardBack = root.querySelector('[data-wizard-back]');
  var wizardNext = root.querySelector('[data-wizard-next]');
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
    if (wizardStep === 3 && root.querySelector('.nh-tc__offer')) {
      try {
        root.querySelector('.nh-tc__offer').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } catch (e) { /* ignore */ }
    }
  }

  function kitGroup(role) {
    if (role === 'sheet') return 'sheets';
    if (role === 'connecting' || role === 'finish' || role === 'wall' || role === 'ridge' || role === 'end_cap') return 'profiles';
    if (role === 'vent_tape' || role === 'iso_tape' || role === 'silicon') return 'sealing';
    return 'fixings';
  }

  function groupLabel(key) {
    if (key === 'sheets') return i18n('groupSheets', 'Sheets');
    if (key === 'profiles') return i18n('groupProfiles', 'Profiles');
    if (key === 'sealing') return i18n('groupSealing', 'Sealing');
    return i18n('groupFixings', 'Fixings');
  }

  function labelFor(value, fallbackMap) {
    return i18n(value, fallbackMap && fallbackMap[value] ? fallbackMap[value] : value);
  }

  function fillSelect(sel, values, preferred) {
    if (!sel) return;
    var current = sel.value;
    sel.innerHTML = '';
    values.forEach(function (v) {
      var opt = document.createElement('option');
      opt.value = v;
      opt.textContent = labelFor(v);
      if (/^\d+$/.test(String(v))) {
        opt.textContent = i18n('mm', '%d mm').replace('%d', v);
      }
      sel.appendChild(opt);
    });
    if (current && values.indexOf(current) !== -1) sel.value = current;
    else if (preferred && values.indexOf(preferred) !== -1) sel.value = preferred;
    else if (values.length) sel.value = values[0];
  }

  function syncSheetOptions() {
    var tree = cfg.tree || {};
    var materials = Object.keys(tree);
    if (matSel && materials.length) {
      fillSelect(matSel, materials, 'multiwall');
    }
    var material = matSel ? matSel.value : 'multiwall';
    var thkMap = tree[material] || {};
    var thicknesses = Object.keys(thkMap).sort(function (a, b) {
      return Number(a) - Number(b);
    });
    fillSelect(thkSel, thicknesses, '10');
    var colours = thkMap[thkSel.value] || [];
    fillSelect(colSel, colours, 'clear');
  }

  function syncProfileOptions() {
    var connectTree = cfg.connectTree || {};
    var finishTree = cfg.finishTree || {};
    var connectTypes = Object.keys(connectTree);
    var finishTypes = Object.keys(finishTree).filter(function (t) {
      return t !== 'f_profile' || !finishTree.f_aluminium;
    });
    if (connectTypes.length) {
      fillSelect(connectSel, connectTypes, 'clamping');
    }
    fillSelect(connectCol, connectTree[connectSel.value] || [], 'silver');
    if (finishTypes.length) {
      fillSelect(finishSel, finishTypes, 'f_aluminium');
    }
    fillSelect(finishCol, finishTree[finishSel.value] || [], 'silver');
  }

  function customCutAvailable() {
    var stock = cfg.customStock || {};
    var material = matSel ? matSel.value : 'multiwall';
    var thickness = thkSel ? String(thkSel.value) : '';
    var colour = colSel ? colSel.value : '';
    var row = (stock[material] && stock[material][thickness]) || {};
    return row[colour] !== false;
  }

  function syncSupplyAvailability() {
    var label = root.querySelector('[data-supply-custom]');
    var input = form.querySelector('[name="sheet_supply"][value="custom"]');
    var available = customCutAvailable();
    if (label) label.hidden = !available;
    if (!input) return;
    input.disabled = !available;
    if (!available && input.checked) {
      input.checked = false;
      var standard = form.querySelector('[name="sheet_supply"][value="standard"]');
      if (standard) standard.checked = true;
      jointsPinned = false;
      applyJointDefault();
    }
  }

  function currentSupply() {
    var picked = form.querySelector('[name="sheet_supply"]:checked');
    return picked ? picked.value : 'custom';
  }

  function applyJointDefault() {
    if (jointsPinned) return;
    var want = currentSupply() === 'standard' ? 'optimal' : 'every';
    Array.prototype.forEach.call(form.querySelectorAll('[name="profile_joints"]'), function (input) {
      input.checked = input.value === want;
    });
  }

  function stockGroups() {
    var cat = cfg.standardSheets || {};
    var material = matSel ? matSel.value : 'multiwall';
    var thickness = thkSel ? String(thkSel.value) : '';
    var colour = colSel ? colSel.value : '';
    var byMaterial = cat[material] || {};
    var byThickness = byMaterial[thickness] || {};
    return byThickness[colour] || {};
  }

  function renderChips(container, name, values, selected, labelFn) {
    if (!container) return;
    container.innerHTML = '';
    values.forEach(function (value) {
      var label = document.createElement('label');
      label.className = 'nh-tc__chip';
      var input = document.createElement('input');
      input.type = 'radio';
      input.name = name;
      input.value = String(value);
      input.checked = String(value) === String(selected);
      var text = document.createElement('span');
      text.textContent = labelFn(value);
      label.appendChild(input);
      label.appendChild(text);
      container.appendChild(label);
    });
  }

  function lengthsOf(entry) {
    if (!entry) return [];
    if (Object.prototype.toString.call(entry) === '[object Array]') {
      return entry.map(String);
    }
    return Object.keys(entry).filter(function (key) {
      return Number(key) > 0;
    }).sort(function (a, b) {
      return Number(a) - Number(b);
    });
  }

  function slopeCount() {
    var picked = form.querySelector('[name="construction"]:checked');
    return picked && picked.value === 'gable' ? 2 : 1;
  }

  function frameLength() {
    return (lengthInput ? Number(lengthInput.value) : 0) * slopeCount();
  }

  function syncGableHint() {
    var gable = slopeCount() > 1;
    var hint = root.querySelector('[data-gable-length]');
    var widthLabel = root.querySelector('[data-width-label]');
    var lengthLabel = root.querySelector('[data-length-label]');
    if (hint) hint.hidden = !gable;
    if (widthLabel) {
      widthLabel.textContent = gable
        ? i18n('widthGable', 'Width along the gable')
        : i18n('widthWall', 'Width along the wall');
    }
    if (lengthLabel) {
      lengthLabel.textContent = gable
        ? i18n('lengthGable', 'Length of one side (ridge to eave)')
        : i18n('lengthLean', 'Projection (wall to front)');
    }
  }

  function preferredLength(lengths, material) {
    var need = frameLength() + (overhangInput ? Number(overhangInput.value) : 0);
    var sorted = lengths.map(Number).filter(function (n) { return n > 0; }).sort(function (a, b) { return a - b; });
    if (material === 'solid' && sorted.indexOf(3050) !== -1) {
      return '3050';
    }
    if (material === 'multiwall') {
      // Round up to a full metre (1–7 m), then pick the shortest sold length that covers it.
      var metre = need >= 7000 ? 7000 : Math.max(1000, Math.min(7000, Math.ceil(need / 1000) * 1000));
      need = metre;
    }
    var i;
    for (i = 0; i < sorted.length; i++) {
      if (sorted[i] >= need) return String(sorted[i]);
    }
    return sorted.length ? String(sorted[sorted.length - 1]) : '';
  }

  function setStockDisabled(disabled) {
    if (!stockCard) return;
    Array.prototype.forEach.call(stockCard.querySelectorAll('input'), function (input) {
      input.disabled = disabled;
    });
  }

  function syncStockCard() {
    if (!stockCard) return;
    var standard = currentSupply() === 'standard';
    stockCard.hidden = !standard;
    if (!standard) {
      setStockDisabled(true);
      stockSig = 'custom';
      return;
    }

    var groups = stockGroups();
    var channels = Object.keys(groups);
    var key = [
      matSel ? matSel.value : '',
      thkSel ? thkSel.value : '',
      colSel ? colSel.value : ''
    ].join('|');
    if (key !== stockState.key) {
      stockState.key = key;
      stockState.channel = '';
      stockState.width = '';
      stockState.length = '';
    }

    var empty = !channels.length;
    var customOn = customCutAvailable();
    if (stockEmpty) stockEmpty.hidden = !empty || !customOn;
    if (stockOos) stockOos.hidden = !empty || customOn;
    if (stockSizes) stockSizes.hidden = empty;
    if (stockChannels) stockChannels.hidden = empty || (channels.length === 1 && channels[0] === 'stock');
    if (empty) {
      if (stockChannelEl) stockChannelEl.innerHTML = '';
      if (stockWidthsEl) stockWidthsEl.innerHTML = '';
      if (stockLengthInput) stockLengthInput.value = '';
      stockSig = key + '|empty';
      setStockDisabled(true);
      return;
    }

    if (channels.indexOf(stockState.channel) === -1) {
      if (channels.indexOf('stock') !== -1) stockState.channel = 'stock';
      else if (channels.indexOf('6w') !== -1) stockState.channel = '6w';
      else stockState.channel = channels[0];
      stockState.width = '';
    }

    var widthMap = (groups[stockState.channel] && groups[stockState.channel].widths) || {};
    var widths = Object.keys(widthMap).sort(function (a, b) { return Number(a) - Number(b); });
    var material = matSel ? matSel.value : 'multiwall';
    // Standard supply uses full sheets: multiwall 2100 mm, solid 2050 mm.
    if (material === 'multiwall' && widths.indexOf('2100') !== -1) {
      widths = ['2100'];
    } else if (material === 'solid' && widths.indexOf('2050') !== -1) {
      widths = ['2050'];
    }
    if (widths.indexOf(String(stockState.width)) === -1) {
      if (material === 'multiwall' && widths.indexOf('2100') !== -1) stockState.width = '2100';
      else if (material === 'solid' && widths.indexOf('2050') !== -1) stockState.width = '2050';
      else stockState.width = widths[widths.length - 1];
    }

    var lengths = lengthsOf(widthMap[stockState.width]);
    stockState.length = preferredLength(lengths, material);
    if (stockLengthInput) stockLengthInput.value = stockState.length;

    var sig = [key, stockState.channel, stockState.width].join('|');
    if (sig === stockSig) {
      setStockDisabled(false);
      return;
    }
    stockSig = sig;

    renderChips(stockChannelEl, 'stock_channel', channels, stockState.channel, function (channel) {
      return i18n(channel, channel);
    });
    renderChips(stockWidthsEl, 'stock_width_mm', widths, stockState.width, function (width) {
      return i18n('mm', '%d mm').replace('%d', width);
    });
    setStockDisabled(false);
  }

  function rangeFor(material, thickness) {
    var tables = cfg.supportRanges || {};
    var table = tables[material] || tables.multiwall || {};
    var keys = Object.keys(table).map(Number).filter(function (n) {
      return n <= Number(thickness);
    }).sort(function (a, b) { return a - b; });
    var key = keys.length ? String(keys[keys.length - 1]) : Object.keys(table).sort(function (a, b) {
      return Number(a) - Number(b);
    })[0];
    var pair = (key && table[key]) || [500, 600, 600];
    return { min: Number(pair[0]), max: Number(pair[1]), prefill: Number(pair[2] || pair[1]) };
  }

  function rafterCount(width, support, cc) {
    var inner = Math.max(0, Number(width) - Number(support || 0));
    var step = Math.max(1, Number(cc) || 1);
    var bays = inner <= step ? 1 : Math.ceil(inner / step);
    return bays + 1;
  }

  function applySpacing(force) {
    var width = widthInput ? Number(widthInput.value) : 0;
    var support = supportInput ? Number(supportInput.value) : 50;
    if (!support) support = 50;
    var material = matSel ? matSel.value : 'multiwall';
    var thickness = thkSel ? Number(thkSel.value) : 10;
    var range = rangeFor(material, thickness);
    var prefill = range.prefill || range.max || defaultCc;
    if (ccInput && (force || !ccCustom)) {
      ccInput.value = String(prefill);
    }
    var used = ccInput ? Number(ccInput.value) : prefill;
    if (recEl) {
      recEl.textContent = fillTemplate(
        i18n('rafters', 'Recommended spacing for this thickness is %1$d–%2$d mm. This roof uses %3$d mm centres (%4$d rafters).'),
        [range.min, range.max, used || even.cc, rafterCount(width, support, used || even.cc)]
      );
    }
  }

  function raiseCents(text) {
    var p = cfg.currency || {};
    if (!p.superscript) return String(text);
    var decimal = p.decimal != null ? String(p.decimal) : ',';
    var esc = decimal.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return String(text).replace(new RegExp('(<[^>]+>)|(' + esc + ')(\\d{2})(?!\\d)', 'g'), function (match, tag, sep, cents) {
      return tag ? tag : sep + '<sup class="nh-eur-cents">' + cents + '</sup>';
    });
  }

  function showPrice(el, text) {
    if (!el) return;
    var html = raiseCents(text);
    if (html.indexOf('<sup') !== -1) el.innerHTML = html;
    else el.textContent = text;
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
    var hideZeros = p.trim && decs > 0 && parts[1] && /^0+$/.test(parts[1]);
    var value = (decs > 0 && !hideZeros) ? parts[0] + decimal + (parts[1] || '') : parts[0];
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
      if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) return;
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

  function unitAmount(item, taxDisplay) {
    return taxDisplay === 'excl' ? item.unit_ex : item.unit_inc;
  }

  function lineAmount(item, taxDisplay) {
    return taxDisplay === 'excl' ? item.line_ex : item.line_inc;
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
      if (mobileBar) mobileBar.hidden = true;
      drawInlineThumbs([]);
      return;
    }

    var taxDisplay = payload.tax_display || cfg.taxDisplay || 'incl';
    var order = ['sheets', 'profiles', 'sealing', 'fixings'];
    var buckets = { sheets: [], profiles: [], sealing: [], fixings: [] };

    function addRow(item, isMissing, list) {
      var li = document.createElement('li');
      if (isMissing) li.className = 'is-missing';
      var thumb = productThumb(item);
      var productUrl = safeUrl(item.permalink);
      if (thumb) {
        li.classList.add('has-thumb');
        if (productUrl) {
          var thumbLink = document.createElement('a');
          thumbLink.className = 'nh-tc__thumb-link';
          thumbLink.href = productUrl;
          thumbLink.setAttribute('aria-label', item.name || item.label || '');
          thumbLink.appendChild(thumb);
          li.appendChild(thumbLink);
        } else {
          li.appendChild(thumb);
        }
      }
      var name = document.createElement(productUrl ? 'a' : 'div');
      name.className = 'nh-tc__item-name';
      if (productUrl) name.href = productUrl;
      name.textContent = item.name || item.label || '';
      var spec = document.createElement('div');
      spec.className = 'nh-tc__item-spec';
      var qtyLabel = i18n('pcs', '%s pcs').replace('%s', item.qty);
      var each = isMissing ? '' : i18n('each', '%s each').replace('%s', item.unit_display || fmt(unitAmount(item, taxDisplay)));
      spec.textContent = '';
      [qtyLabel, each, item.spec].filter(Boolean).forEach(function (piece, index) {
        if (index) spec.appendChild(document.createTextNode(' · '));
        if (piece === each && raiseCents(piece).indexOf('<sup') !== -1) {
          var eachNode = document.createElement('span');
          eachNode.innerHTML = raiseCents(piece);
          spec.appendChild(eachNode);
        } else {
          spec.appendChild(document.createTextNode(piece));
        }
      });
      var price = document.createElement('div');
      price.className = 'nh-tc__item-price';
      if (isMissing) price.textContent = '—';
      else showPrice(price, item.line_display || fmt(lineAmount(item, taxDisplay)));
      li.appendChild(name);
      li.appendChild(price);
      li.appendChild(spec);
      list.push(li);
    }

    items.forEach(function (item) { addRow(item, false, buckets[kitGroup(item.role)] || buckets.fixings); });
    missing.forEach(function (item) { addRow(item, true, buckets[kitGroup(item.role)] || buckets.fixings); });

    order.forEach(function (key) {
      if (!buckets[key].length) return;
      var header = document.createElement('li');
      header.className = 'nh-tc__group';
      header.textContent = groupLabel(key);
      itemsEl.appendChild(header);
      buckets[key].forEach(function (li) { itemsEl.appendChild(li); });
    });

    if (metaEl && payload.meta) {
      metaEl.textContent = i18n('sheets', '%d sheets').replace('%d', payload.meta.sheet_count);
    }
    drawPlan(payload.meta);
    drawInlineThumbs(items.concat(missing));

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
          drawPlan(null);
          drawInlineThumbs([]);
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
    applySpacing(false);
    syncGableHint();
    syncSupplyAvailability();
    syncStockCard();
    drawInlineThumbs();
    clearTimeout(timer);
    timer = setTimeout(quote, 280);
  }

  function fillTemplate(template, values) {
    return String(template).replace(/%(\d+)\$d|%d/g, function (match, index) {
      if (index) return values[Number(index) - 1];
      var next = values.shift();
      return next;
    });
  }

  function sheetChoiceImage() {
    var sheets = (cfg.choiceImages && cfg.choiceImages.sheet) || {};
    var material = matSel ? matSel.value : 'multiwall';
    var thickness = thkSel ? thkSel.value : '';
    var colour = colSel ? colSel.value : '';
    var byThickness = sheets[material] || {};
    var row = byThickness[thickness] || {};
    if (row[colour]) return row[colour];
    var colours = Object.keys(row);
    if (colours.length) return row[colours[0]];
    var thicknesses = Object.keys(byThickness);
    for (var i = 0; i < thicknesses.length; i++) {
      var fallback = byThickness[thicknesses[i]] || {};
      var names = Object.keys(fallback);
      if (names.length) return fallback[names[0]];
    }
    return '';
  }

  function profileChoiceImage(group, typeSel, colourSel) {
    var tree = (cfg.choiceImages && cfg.choiceImages[group]) || {};
    var type = typeSel ? typeSel.value : '';
    var colour = colourSel ? colourSel.value : '';
    var row = tree[type] || {};
    if (row[colour]) return row[colour];
    var colours = Object.keys(row);
    return colours.length ? row[colours[0]] : '';
  }

  function hardwareImage(slot, colour) {
    var hw = (cfg.choiceImages && cfg.choiceImages.hardware) || {};
    var value = hw[slot];
    if (!value) return '';
    if (typeof value === 'string') return value;
    if (colour && value[colour]) return value[colour];
    var colours = Object.keys(value);
    return colours.length ? value[colours[0]] : '';
  }

  function itemImage(item) {
    if (item && item.image) return item.image;
    if (!item) return '';
    if (item.role === 'sheet') return sheetChoiceImage();
    if (item.role === 'connecting') return profileChoiceImage('connecting', connectSel, connectCol);
    if (item.role === 'finish') return profileChoiceImage('finish', finishSel, finishCol);
    if (item.role === 'end_cap') return hardwareImage('end_cap', connectCol ? connectCol.value : 'silver');
    var slot = {
      screw: 'screws',
      fixing_button: 'fixing_buttons',
      vent_tape: 'vent_tape',
      iso_tape: 'iso_tape',
      silicon: 'silicon',
      wall: 'wall',
      ridge: 'ridge'
    }[item.role];
    return slot ? hardwareImage(slot) : '';
  }

  function productThumb(item) {
    var src = itemImage(item);
    if (!src) return null;
    var img = document.createElement('img');
    img.className = 'nh-tc__thumb';
    img.src = src;
    img.alt = '';
    img.width = 48;
    img.height = 48;
    img.decoding = 'async';
    return img;
  }

  function safeUrl(url) {
    if (!url || typeof url !== 'string') return '';
    var value = url.trim();
    if (!value || value.indexOf('javascript:') === 0) return '';
    return value;
  }

  function setInlineThumb(el, item) {
    if (!el) return;
    var img = el.querySelector('img');
    var src = item ? itemImage(item) : '';
    var url = item ? safeUrl(item.permalink) : '';
    if (!src) {
      el.classList.add('is-empty');
      el.removeAttribute('href');
      if (img) img.removeAttribute('src');
      return;
    }
    el.classList.remove('is-empty');
    if (img) {
      img.src = src;
      img.alt = item.name || item.label || '';
    }
    if (url) el.href = url;
    else el.removeAttribute('href');
  }

  function drawInlineThumbs() {
    setInlineThumb(inlineThumbs.sheet, { role: 'sheet' });
    setInlineThumb(inlineThumbs.connecting, { role: 'connecting' });
    setInlineThumb(inlineThumbs.finish, { role: 'finish' });
  }

  function drawPlan(meta) {
    if (!planEl) return;
    if (!meta || !meta.sheet_plan || !meta.sheet_plan.length) {
      planEl.innerHTML = '<p class="nh-tc__plan-note">' + i18n('planWait', 'Enter the roof size to see the cutting diagram.') + '</p>';
      return;
    }

    var plan = meta.sheet_plan;
    var rafters = Array.isArray(meta.rafters_mm) ? meta.rafters_mm : [];
    var framed = rafters.length > 1 && Number(meta.width_mm) > 0;
    var gable = Number(meta.slopes) > 1;

    function buildSlope(slopeClass) {
      var slope = document.createElement('div');
      slope.className = framed ? 'nh-tc__frame ' + slopeClass : 'nh-tc__sheets';
      plan.forEach(function (sheet) {
        var cell = document.createElement('div');
        cell.className = 'nh-tc__sheet is-' + (sheet.edge === 'side' ? 'side' : 'middle');
        if (framed) {
          cell.style.left = (Number(sheet.x_mm) / Number(meta.width_mm) * 100) + '%';
          cell.style.width = (Number(sheet.width_mm) / Number(meta.width_mm) * 100) + '%';
        } else {
          cell.style.flexGrow = String(Math.max(1, sheet.width_mm));
        }
        var label = document.createElement('span');
        label.textContent = sheet.width_mm;
        cell.appendChild(label);
        slope.appendChild(cell);
      });
      if (framed) {
        rafters.forEach(function (pos) {
          var line = document.createElement('i');
          line.className = 'nh-tc__rafter';
          line.style.left = (Number(pos) / Number(meta.width_mm) * 100) + '%';
          line.setAttribute('aria-hidden', 'true');
          slope.appendChild(line);
        });
        var beam = document.createElement('div');
        beam.className = 'nh-tc__beam';
        beam.setAttribute('aria-hidden', 'true');
        slope.appendChild(beam);
      }
      return slope;
    }

    var row = buildSlope(gable ? 'is-upper' : '');
    if (!gable) {
      row.setAttribute('role', 'img');
      var aria = i18n('sheets', '%d sheets').replace('%d', plan.length);
      if (framed) aria += ', ' + rafters.length + ' ' + i18n('rafter', 'Rafter');
      row.setAttribute('aria-label', aria);
    }

    var groups = [];
    plan.forEach(function (sheet) {
      var found = null;
      groups.forEach(function (group) {
        if (group.width_mm === sheet.width_mm && group.edge === sheet.edge) found = group;
      });
      if (!found) {
        found = { width_mm: sheet.width_mm, qty: 0, edge: sheet.edge };
        groups.push(found);
      }
      found.qty += 1;
    });

    var cutBits = groups.map(function (group) {
      var kind = group.edge === 'side' ? i18n('outer', 'outer') : i18n('middle', 'middle');
      var size = i18n('mm', '%d mm').replace('%d', group.width_mm);
      return group.qty > 1 ? group.qty + ' × ' + size + ' ' + kind : size + ' ' + kind;
    });

    var halfGap = Math.round((Number(meta.profile_gap_mm) || 10) / 2);
    var split = Number(meta.length_pieces) > 1;
    var note = document.createElement('p');
    note.className = 'nh-tc__plan-note';
    if (plan.length < 2) {
      note.textContent = split
        ? i18n('planCover', 'One sheet covers the frame from edge to edge.')
        : fillTemplate(
          i18n('planSingle', 'One sheet covers the frame from edge to edge. Cut length %1$d mm (frame %2$d mm + %3$d mm overhang).'),
          [meta.sheet_length_mm, meta.length_mm, meta.overhang_mm]
        );
    } else if (meta.profile_joints === 'optimal') {
      var optimalText = i18n('planOptimal', 'Sheets are as wide as they can be. A joint is added only where they meet, on a rafter.');
      if (!split) {
        optimalText += ' ' + fillTemplate(
          i18n('planCut', 'Cut length %1$d mm (frame %2$d mm + %3$d mm overhang).'),
          [meta.sheet_length_mm, meta.length_mm, meta.overhang_mm]
        );
      }
      note.textContent = optimalText;
    } else {
      var leftW = Number(plan[0].width_mm);
      var rightW = Number(plan[plan.length - 1].width_mm);
      var sides = leftW === rightW
        ? i18n('planSidesEqual', 'Both side sheets are %d mm.').replace('%d', leftW)
        : fillTemplate(i18n('planSides', 'The side sheets are %1$d mm and %2$d mm.'), [leftW, rightW]);
      var text = fillTemplate(
        i18n('planOuter', 'Side sheets are %1$d mm wider so they cover the %2$d mm beam. %3$d mm is left at the joint.'),
        [meta.side_extra_mm, meta.support_mm, halfGap]
      ) + ' ' + sides;
      if (framed) text += ' ' + i18n('planJoint', 'Joints sit on the centre of a rafter.');
      if (!split) {
        text += ' ' + fillTemplate(
          i18n('planCut', 'Cut length %1$d mm (frame %2$d mm + %3$d mm overhang).'),
          [meta.sheet_length_mm, meta.length_mm, meta.overhang_mm]
        );
      }
      note.textContent = text;
    }

    var cuts = document.createElement('p');
    cuts.className = 'nh-tc__plan-cuts';
    cuts.textContent = i18n('planCuts', 'Cut widths: %s.').replace('%s', cutBits.join(', '));

    var legend = document.createElement('p');
    legend.className = 'nh-tc__legend';
    legend.innerHTML = '<span><i class="is-side"></i>' + i18n('outer', 'outer') + '</span><span><i class="is-middle"></i>' + i18n('middle', 'middle') + '</span>' + (framed ? '<span><i class="is-rafter"></i>' + i18n('rafter', 'Rafter') + '</span>' : '') + (gable ? '<span><i class="is-ridge"></i>' + i18n('ridge', 'Ridge') + '</span>' : '') + '<span><i class="is-joint"></i>' + (meta.profile_gap_mm || 10) + ' mm</span>';

    planEl.innerHTML = '';
    if (gable) {
      var scheme = document.createElement('div');
      scheme.className = 'nh-tc__gable';
      scheme.setAttribute('role', 'img');
      scheme.setAttribute('aria-label', i18n('sheets', '%d sheets').replace('%d', plan.length * 2) + ', ' + i18n('ridge', 'Ridge'));
      var ridge = document.createElement('div');
      ridge.className = 'nh-tc__ridge';
      ridge.setAttribute('aria-hidden', 'true');
      scheme.appendChild(row);
      scheme.appendChild(ridge);
      scheme.appendChild(buildSlope('is-lower'));
      planEl.appendChild(scheme);
    } else {
      planEl.appendChild(row);
    }
    planEl.appendChild(legend);
    planEl.appendChild(cuts);
    planEl.appendChild(note);
    if (Number(meta.slopes) > 1) {
      var gableNote = document.createElement('p');
      gableNote.className = 'nh-tc__plan-note';
      gableNote.textContent = fillTemplate(
        i18n('planGable', 'You entered one side (%1$d mm). Both sides are included, so the length is %2$d mm.'),
        [meta.projection_mm, meta.length_mm]
      );
      planEl.appendChild(gableNote);
    }
    if (meta.sheet_supply === 'standard' && Number(meta.stock_width_mm) > 0 && Number(meta.stock_length_mm) > 0) {
      var stockNote = document.createElement('p');
      stockNote.className = 'nh-tc__plan-note';
      stockNote.textContent = fillTemplate(
        i18n('planStockBuy', 'You buy %1$d whole sheets, %2$d × %3$d mm. The drawing shows one way to cut them.'),
        [meta.sheet_count, meta.stock_width_mm, meta.stock_length_mm]
      );
      planEl.appendChild(stockNote);
    }
    if (Number(meta.length_pieces) > 1) {
      var solidNote = document.createElement('p');
      solidNote.className = 'nh-tc__plan-note';
      solidNote.textContent = fillTemplate(
        i18n('planSolid', 'Solid sheets are %1$d × %2$d mm and can be turned either way. The %3$d mm length needs %4$d pieces.'),
        [meta.blank_short_mm, meta.blank_long_mm, meta.sheet_length_mm, meta.length_pieces]
      );
      planEl.appendChild(solidNote);
    }
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

  if (matSel) {
    matSel.addEventListener('change', function () {
      ccCustom = false;
      syncSheetOptions();
      schedule();
    });
  }
  if (thkSel) {
    thkSel.addEventListener('change', function () {
      ccCustom = false;
      var tree = cfg.tree || {};
      var thkMap = tree[matSel.value] || {};
      fillSelect(colSel, thkMap[thkSel.value] || [], 'clear');
      schedule();
    });
  }
  if (stockCard) {
    stockCard.addEventListener('change', function (event) {
      var target = event.target;
      if (!target || !target.name) return;
      if (target.name === 'stock_channel') {
        stockState.channel = target.value;
        stockState.width = '';
      } else if (target.name === 'stock_width_mm') {
        stockState.width = target.value;
      }
    });
  }
  if (ccInput) {
    ccInput.addEventListener('input', function () {
      ccCustom = true;
    });
  }
  if (connectSel) {
    connectSel.addEventListener('change', function () {
      var connectTree = cfg.connectTree || {};
      fillSelect(connectCol, connectTree[connectSel.value] || [], 'silver');
      schedule();
    });
  }
  if (finishSel) {
    finishSel.addEventListener('change', function () {
      var finishTree = cfg.finishTree || {};
      fillSelect(finishCol, finishTree[finishSel.value] || [], 'silver');
      schedule();
    });
  }
  form.addEventListener('change', function (event) {
    var target = event.target;
    if (!target || !target.name) return;
    if (target.name === 'sheet_supply') {
      jointsPinned = false;
      applyJointDefault();
    } else if (target.name === 'profile_joints') {
      jointsPinned = true;
    }
  });
  form.addEventListener('input', schedule);
  form.addEventListener('change', schedule);
  atc.addEventListener('click', addToCart);

  root.querySelectorAll('[data-goto]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setWizardStep(btn.getAttribute('data-goto'));
    });
  });
  if (wizardBack) {
    wizardBack.addEventListener('click', function () {
      setWizardStep(wizardStep - 1);
    });
  }
  if (wizardNext) {
    wizardNext.addEventListener('click', function () {
      setWizardStep(wizardStep + 1);
    });
  }
  if (mobileCta) {
    mobileCta.addEventListener('click', function () {
      if (wizardStep >= 3) addToCart();
      else setWizardStep(wizardStep + 1);
    });
  }
  setWizardStep(1);
  syncSheetOptions();
  syncProfileOptions();
  applySpacing(true);
  syncGableHint();
  syncSupplyAvailability();
  syncStockCard();
  drawInlineThumbs([]);
  quote();
})();
