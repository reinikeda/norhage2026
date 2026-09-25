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
  var stockState = { key: '', channel: '', width: '', length: '' };
  var stockSig = '';

  function i18n(key, fallback) {
    return (cfg.i18n && cfg.i18n[key]) || fallback || key;
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
        opt.textContent = v + ' mm';
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

  function currentSupply() {
    var picked = form.querySelector('[name="sheet_supply"]:checked');
    return picked ? picked.value : 'custom';
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

  function preferredLength(lengths) {
    var need = (lengthInput ? Number(lengthInput.value) : 0) + (overhangInput ? Number(overhangInput.value) : 0);
    var sorted = lengths.map(Number).filter(function (n) { return n > 0; }).sort(function (a, b) { return a - b; });
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
    if (stockEmpty) stockEmpty.hidden = !empty;
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
    if (widths.indexOf(String(stockState.width)) === -1) {
      stockState.width = widths.indexOf('2100') !== -1 ? '2100' : widths[widths.length - 1];
    }

    var lengths = lengthsOf(widthMap[stockState.width]);
    stockState.length = preferredLength(lengths);
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
    var pair = (key && table[key]) || [500, 600];
    return { min: Number(pair[0]), max: Number(pair[1]) };
  }

  function evenSpacing(width, support, maxCc) {
    var inner = Math.max(1, Number(width) - Number(support || 0));
    var max = Math.max(1, Number(maxCc) || defaultCc);
    if (inner <= max) return { cc: inner, bays: 1 };
    var bays = Math.ceil(inner / max);
    var cc = Math.ceil(inner / bays);
    if (cc > max) {
      bays += 1;
      cc = Math.ceil(inner / bays);
    }
    return { cc: Math.max(1, cc), bays: bays };
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
    var even = evenSpacing(width, support, range.max);
    if (ccInput && (force || !ccCustom)) {
      ccInput.value = String(even.cc);
    }
    var used = ccInput ? Number(ccInput.value) : even.cc;
    if (recEl) {
      recEl.textContent = fillTemplate(
        i18n('rafters', 'Recommended spacing for this thickness is %1$d–%2$d mm. This roof uses %3$d mm centres (%4$d rafters).'),
        [range.min, range.max, used || even.cc, rafterCount(width, support, used || even.cc)]
      );
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
      drawInlineThumbs([]);
      return;
    }

    var taxDisplay = payload.tax_display || cfg.taxDisplay || 'incl';

    function addRow(item, isMissing) {
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
      spec.textContent = [qtyLabel, each, item.spec].filter(Boolean).join(' · ');
      var price = document.createElement('div');
      price.className = 'nh-tc__item-price';
      price.textContent = isMissing ? '—' : (item.line_display || fmt(lineAmount(item, taxDisplay)));
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
    drawPlan(payload.meta);
    drawInlineThumbs(items.concat(missing));

    if (payload.currency) cfg.currency = payload.currency;
    if (payload.totals) {
      totalsEl.hidden = false;
      taxEl.textContent = payload.totals.tax_formatted || fmt(payload.totals.tax);
      totalEl.textContent = payload.totals.total_formatted || fmt(taxDisplay === 'excl' ? payload.totals.ex : payload.totals.inc);
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
    syncStockCard();
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

  var visualRoles = { sheet: 1, connecting: 1, finish: 1, wall: 1, ridge: 1 };

  function productThumb(item) {
    if (!item || !item.image || !visualRoles[item.role]) return null;
    var img = document.createElement('img');
    img.className = 'nh-tc__thumb';
    img.src = item.image;
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

  function firstWithImage(items, role) {
    for (var i = 0; i < items.length; i++) {
      if (items[i].role === role && items[i].image) return items[i];
    }
    return null;
  }

  function setInlineThumb(el, item) {
    if (!el) return;
    var img = el.querySelector('img');
    var url = item ? safeUrl(item.permalink) : '';
    if (!item || !item.image) {
      el.classList.add('is-empty');
      el.removeAttribute('href');
      if (img) img.removeAttribute('src');
      return;
    }
    el.classList.remove('is-empty');
    if (img) {
      img.src = item.image;
      img.alt = item.name || item.label || '';
    }
    if (url) el.href = url;
    else el.removeAttribute('href');
  }

  function drawInlineThumbs(items) {
    setInlineThumb(inlineThumbs.sheet, firstWithImage(items, 'sheet'));
    setInlineThumb(inlineThumbs.connecting, firstWithImage(items, 'connecting'));
    setInlineThumb(inlineThumbs.finish, firstWithImage(items, 'finish'));
  }

  function drawPlan(meta) {
    if (!planEl) return;
    if (!meta || !meta.sheet_plan || !meta.sheet_plan.length) {
      planEl.innerHTML = '<p class="nh-tc__plan-note">' + i18n('planWait', 'The cut diagram appears once the size is valid.') + '</p>';
      return;
    }

    var plan = meta.sheet_plan;
    var rafters = Array.isArray(meta.rafters_mm) ? meta.rafters_mm : [];
    var framed = rafters.length > 1 && Number(meta.width_mm) > 0;
    var row = document.createElement('div');
    row.className = framed ? 'nh-tc__frame' : 'nh-tc__sheets';
    row.setAttribute('role', 'img');
    var aria = i18n('sheets', '%d sheets').replace('%d', plan.length);
    if (framed) aria += ', ' + rafters.length + ' ' + i18n('rafter', 'Rafter');
    row.setAttribute('aria-label', aria);

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
      row.appendChild(cell);
    });

    if (framed) {
      rafters.forEach(function (pos) {
        var line = document.createElement('i');
        line.className = 'nh-tc__rafter';
        line.style.left = (Number(pos) / Number(meta.width_mm) * 100) + '%';
        line.setAttribute('aria-hidden', 'true');
        row.appendChild(line);
      });
      var beam = document.createElement('div');
      beam.className = 'nh-tc__beam';
      beam.setAttribute('aria-hidden', 'true');
      row.appendChild(beam);
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
    } else {
      var leftW = Number(plan[0].width_mm);
      var rightW = Number(plan[plan.length - 1].width_mm);
      var sides = leftW === rightW
        ? i18n('planSidesEqual', 'Both side sheets are %d mm.').replace('%d', leftW)
        : fillTemplate(i18n('planSides', 'The side sheets are %1$d mm and %2$d mm.'), [leftW, rightW]);
      var text = fillTemplate(
        i18n('planOuter', 'An outer sheet is %1$d mm wider than a full middle sheet: it reaches the end of the %2$d mm support and only loses %3$d mm at the joint.'),
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
    legend.innerHTML = '<span><i class="is-side"></i>' + i18n('outer', 'outer') + '</span><span><i class="is-middle"></i>' + i18n('middle', 'middle') + '</span>' + (framed ? '<span><i class="is-rafter"></i>' + i18n('rafter', 'Rafter') + '</span>' : '') + '<span><i class="is-joint"></i>' + (meta.profile_gap_mm || 10) + ' mm</span>';

    planEl.innerHTML = '';
    planEl.appendChild(row);
    planEl.appendChild(legend);
    planEl.appendChild(cuts);
    planEl.appendChild(note);
    if (meta.sheet_supply === 'standard' && Number(meta.stock_width_mm) > 0 && Number(meta.stock_length_mm) > 0) {
      var stockNote = document.createElement('p');
      stockNote.className = 'nh-tc__plan-note';
      stockNote.textContent = fillTemplate(
        i18n('planStockBuy', 'The drawing is a possible rafter layout. The material list adds %1$d stock sheets of %2$d × %3$d mm.'),
        [meta.sheet_count, meta.stock_width_mm, meta.stock_length_mm]
      );
      planEl.appendChild(stockNote);
    }
    if (Number(meta.length_pieces) > 1) {
      var solidNote = document.createElement('p');
      solidNote.className = 'nh-tc__plan-note';
      solidNote.textContent = fillTemplate(
        i18n('planSolid', 'Solid sheets are %1$d × %2$d mm and can be turned either way. The %3$d mm run is cut into %4$d pieces along the length.'),
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
  form.addEventListener('input', schedule);
  form.addEventListener('change', schedule);
  atc.addEventListener('click', addToCart);

  syncSheetOptions();
  syncProfileOptions();
  applySpacing(true);
  syncStockCard();
  quote();
})();
