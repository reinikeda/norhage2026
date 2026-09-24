(function () {
  var cfg = window.NH_WL || {};
  if (!Array.isArray(cfg.saved)) {
    cfg.saved = [];
  }
  if (!Array.isArray(cfg.lists)) {
    cfg.lists = [];
  }
  var pop = document.getElementById('nh-wl-popover');
  if (!pop || !cfg.ajax) {
    return;
  }

  var pending = null;
  var lastFocus = null;
  var listsEl = pop.querySelector('.nh-wl-popover__lists');
  var noticeEl = pop.querySelector('.nh-wl-popover__notice');
  var hintEl = pop.querySelector('.nh-wl-popover__hint');
  var createForm = pop.querySelector('.nh-wl-popover__create');

  function text(template, value) {
    return String(template || '').replace('%s', value);
  }

  function formatM(value) {
    var number = parseFloat(String(value == null ? '' : value).replace(',', '.'));
    if (!isFinite(number) || number <= 0) {
      return '0';
    }
    return number.toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
  }

  function selectionFrom(button) {
    var context = button.getAttribute('data-context') || 'loop';
    var data = {
      context: context,
      product_id: parseInt(button.getAttribute('data-product-id'), 10) || 0,
      variation_id: 0,
      quantity: 1,
      width_mm: 0,
      length_mm: 0,
      length_m: '0'
    };
    if (context !== 'single') {
      return data;
    }
    var scope = button.closest('.product') || document;
    var form = scope.querySelector('form.cart');
    if (!form) {
      return data;
    }
    var variation = form.querySelector('[name="variation_id"]');
    var quantity = form.querySelector('[name="quantity"]');
    data.variation_id = variation ? parseInt(variation.value, 10) || 0 : 0;
    data.quantity = quantity ? parseInt(quantity.value, 10) || 1 : 1;
    var width = document.getElementById('nh_width_mm');
    var length = document.getElementById('nh_length_mm');
    var metres = document.getElementById('nh_length_m');
    if (width && scope.contains(width)) {
      data.width_mm = parseInt(width.value, 10) || 0;
    }
    if (length && scope.contains(length)) {
      data.length_mm = parseInt(length.value, 10) || 0;
    }
    if (metres && scope.contains(metres)) {
      data.length_m = metres.value || '0';
    }
    return data;
  }

  function same(row, selection) {
    return Number(row.product_id) === selection.product_id &&
      Number(row.variation_id) === selection.variation_id &&
      Number(row.width_mm) === selection.width_mm &&
      Number(row.length_mm) === selection.length_mm &&
      String(row.length_m) === formatM(selection.length_m);
  }

  function rowsFor(selection, exact) {
    return (cfg.saved || []).filter(function (row) {
      if (Number(row.product_id) !== selection.product_id) {
        return false;
      }
      return exact ? same(row, selection) : true;
    });
  }

  function syncHearts() {
    document.querySelectorAll('.nh-wl-heart').forEach(function (button) {
      var selection = selectionFrom(button);
      var exact = button.getAttribute('data-context') === 'single';
      var pressed = rowsFor(selection, exact).length > 0;
      if (!exact) {
        pressed = rowsFor(selection, false).length > 0;
      }
      button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
      button.classList.toggle('is-saved', pressed);
      var label = pressed ? cfg.i18n.inWishlist : cfg.i18n.add;
      button.setAttribute('aria-label', label);
      var visible = button.querySelector('.nh-wl-heart__text');
      if (visible) {
        visible.textContent = label;
      }
    });
  }

  function syncBadges() {
    var count = String(cfg.count || 0);
    document.querySelectorAll('.nh-wl-badge').forEach(function (badge) {
      badge.textContent = count;
      badge.setAttribute('data-count', count);
    });
  }

  function applyState(data) {
    if (!data) {
      return;
    }
    if (data.saved) {
      cfg.saved = data.saved;
    }
    if (data.lists) {
      cfg.lists = data.lists;
    }
    if (typeof data.count !== 'undefined') {
      cfg.count = data.count;
    }
    if (data.active) {
      cfg.active = data.active;
    }
    syncBadges();
    syncHearts();
  }

  function post(fields) {
    var body = new URLSearchParams();
    Object.keys(fields).forEach(function (key) {
      var value = fields[key];
      body.set(key, value == null ? '' : String(value));
    });
    body.set('nonce', cfg.nonce);
    return fetch(cfg.ajax, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      body: body
    }).then(function (response) {
      return response.json();
    });
  }

  function showNotice(message) {
    noticeEl.textContent = message || '';
  }

  function closePopover() {
    pop.hidden = true;
    document.body.classList.remove('nh-wl-open');
    pending = null;
    if (lastFocus && lastFocus.focus) {
      lastFocus.focus();
    }
  }

  function renderLists() {
    listsEl.innerHTML = '';
    var selection = pending;
    (cfg.lists || []).forEach(function (list) {
      var exact = rowsFor(selection, true).some(function (row) {
        return row.list === list.id;
      });
      var button = document.createElement('button');
      button.type = 'button';
      button.className = exact ? 'is-saved' : '';
      button.textContent = exact ? text(cfg.i18n.removeFrom, list.name) : text(cfg.i18n.saveTo, list.name);
      button.addEventListener('click', function () {
        save(list.id, exact);
      });
      listsEl.appendChild(button);
    });
    var others = rowsFor(selection, false).length > rowsFor(selection, true).length;
    hintEl.hidden = !others;
    hintEl.textContent = others ? cfg.i18n.savedOptions : '';
  }

  function openPopover(button) {
    pending = selectionFrom(button);
    lastFocus = button;
    showNotice('');
    renderLists();
    pop.hidden = false;
    document.body.classList.add('nh-wl-open');
    var close = pop.querySelector('.nh-wl-popover__close');
    if (close) {
      close.focus();
    }
  }

  function save(listId, remove) {
    var fields = {
      action: 'nh_wl_save',
      list_id: listId,
      remove: remove ? '1' : '',
      context: pending.context,
      product_id: pending.product_id,
      variation_id: pending.variation_id,
      quantity: pending.quantity,
      width_mm: pending.width_mm,
      length_mm: pending.length_mm,
      length_m: pending.length_m
    };
    post(fields).then(function (result) {
      if (!result || !result.success) {
        showNotice((result && result.data && result.data.message) || cfg.i18n.tryAgain);
        return;
      }
      applyState(result.data);
      showNotice(result.data.message || '');
      renderLists();
    }).catch(function () {
      showNotice(cfg.i18n.tryAgain);
    });
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('.nh-wl-heart') : null;
    if (!button) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    openPopover(button);
  });

  pop.addEventListener('click', function (event) {
    if (event.target === pop) {
      closePopover();
    }
  });

  pop.querySelector('.nh-wl-popover__close').addEventListener('click', closePopover);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !pop.hidden) {
      closePopover();
    }
  });

  createForm.addEventListener('submit', function (event) {
    event.preventDefault();
    var input = createForm.querySelector('[name="list_name"]');
    var name = input ? input.value : '';
    post({ action: 'nh_wl_create', list_name: name }).then(function (result) {
      if (!result || !result.success) {
        showNotice((result && result.data && result.data.message) || cfg.i18n.tryAgain);
        return;
      }
      applyState(result.data);
      if (input) {
        input.value = '';
      }
      save(result.data.list_id, false);
    }).catch(function () {
      showNotice(cfg.i18n.tryAgain);
    });
  });

  document.addEventListener('change', function (event) {
    var target = event.target;
    if (!target) {
      return;
    }
    if (target.name === 'variation_id' || target.name === 'quantity' || (target.name && target.name.indexOf('attribute_') === 0) || target.id === 'nh_width_mm' || target.id === 'nh_length_mm' || target.id === 'nh_length_m') {
      syncHearts();
    }
  });

  if (window.jQuery) {
    window.jQuery(document).on('found_variation reset_data', 'form.variations_form', function () {
      syncHearts();
    });
  }

  document.addEventListener('input', function (event) {
    var target = event.target;
    if (!target) {
      return;
    }
    if (target.id === 'nh_width_mm' || target.id === 'nh_length_mm' || target.id === 'nh_length_m') {
      syncHearts();
    }
  });

  post({ action: 'nh_wl_state' }).then(function (result) {
    if (result && result.success) {
      applyState(result.data);
    } else {
      syncHearts();
    }
  }).catch(function () {
    syncHearts();
  });
})();
