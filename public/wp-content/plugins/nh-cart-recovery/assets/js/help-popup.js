/**
 * One-time help after a guest priced shipping and kept browsing.
 * Never emails; opens Crisp chat or checkout. Dismiss is remembered.
 */
(function () {
  'use strict';

  var cfg = window.nhCartHelp || {};
  var root = null;
  var card = null;
  var kickerEl = null;
  var timer = null;
  var shown = false;
  var priced = !!cfg.priced;
  var postcode = String(cfg.postcode || '');
  var delay = typeof cfg.delay === 'number' ? cfg.delay : 45000;
  var lastFocus = null;
  var STORAGE = 'nh_cr_help_v1';
  var COOLDOWN_MS = 14 * 24 * 60 * 60 * 1000;

  function $(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }

  function overlayOpen() {
    if (document.body.classList.contains('nh-sc-open') || document.body.classList.contains('drawer-open')) {
      return true;
    }
    var cart = document.getElementById('nh-side-cart');
    if (cart && cart.getAttribute('aria-hidden') === 'false') {
      return true;
    }
    return false;
  }

  function alreadySeen() {
    var raw = '';
    try {
      raw = window.localStorage.getItem(STORAGE) || '';
    } catch (e) {
      try {
        return !!window.sessionStorage.getItem(STORAGE);
      } catch (e2) {
        return false;
      }
    }
    if (!raw) {
      return false;
    }
    var ts = parseInt(raw, 10);
    if (!ts) {
      return true;
    }
    return (Date.now() - ts) < COOLDOWN_MS;
  }

  function markSeen() {
    var stamp = String(Date.now());
    try {
      window.localStorage.setItem(STORAGE, stamp);
    } catch (e) {
      try {
        window.sessionStorage.setItem(STORAGE, stamp);
      } catch (e2) {
        /* ignore */
      }
    }
  }

  function setCrisp(show) {
    if (window.nhCrisp) {
      if (show) {
        window.nhCrisp.showLauncher();
      } else {
        window.nhCrisp.hideLauncher();
      }
      return;
    }
    if (!show && document.body.classList.contains('nh-crisp-open')) {
      return;
    }
    window.$crisp = window.$crisp || [];
    try {
      window.$crisp.push(['do', show ? 'chat:show' : 'chat:hide']);
    } catch (e) {
      /* Crisp not present */
    }
  }

  function openCrisp() {
    if (window.nhCrisp && typeof window.nhCrisp.open === 'function') {
      window.nhCrisp.open();
      return;
    }
    document.body.classList.add('nh-crisp-open');
    window.$crisp = window.$crisp || [];
    try {
      window.$crisp.push(['do', 'chat:show']);
    } catch (e) {
      /* Crisp not present */
    }
    window.setTimeout(function () {
      try {
        window.$crisp.push(['do', 'chat:open']);
      } catch (e2) {
        /* Crisp not present */
      }
    }, 180);
  }

  function updateKicker() {
    if (!kickerEl) {
      return;
    }
    var tpl = cfg.copy && cfg.copy.kicker ? cfg.copy.kicker : '';
    if (!postcode || !tpl) {
      kickerEl.hidden = true;
      return;
    }
    kickerEl.textContent = tpl.replace('%s', postcode);
    kickerEl.hidden = false;
  }

  function markPriced(code) {
    priced = true;
    if (code) {
      postcode = String(code);
      updateKicker();
    }
    schedule();
  }

  function clearTimer() {
    if (timer) {
      window.clearTimeout(timer);
      timer = null;
    }
  }

  function schedule() {
    clearTimer();
    if (shown || !priced || alreadySeen() || !root) {
      return;
    }
    timer = window.setTimeout(tryShow, delay);
  }

  function tryShow() {
    timer = null;
    if (shown || !priced || alreadySeen()) {
      return;
    }
    if (overlayOpen()) {
      schedule();
      return;
    }
    if (document.body.classList.contains('woocommerce-checkout') || document.body.classList.contains('woocommerce-cart')) {
      return;
    }
    show();
  }

  function show() {
    if (!root || shown) {
      return;
    }
    shown = true;
    lastFocus = document.activeElement;
    root.hidden = false;
    root.classList.add('is-open');
    document.body.classList.add('nh-cr-help-open');
    setCrisp(false);
    markSeen();
    window.setTimeout(function () {
      if (card) {
        card.focus({ preventScroll: true });
      }
    }, 30);
  }

  function hide(skipLauncher) {
    if (!root) {
      return;
    }
    root.hidden = true;
    root.classList.remove('is-open');
    document.body.classList.remove('nh-cr-help-open');
    if (!skipLauncher) {
      setCrisp(true);
    }
    if (lastFocus && typeof lastFocus.focus === 'function') {
      lastFocus.focus({ preventScroll: true });
    }
  }

  function onKey(e) {
    if (!root || root.hidden) {
      return;
    }
    if (e.key === 'Escape') {
      e.preventDefault();
      hide();
    }
  }

  function bind() {
    root.addEventListener('click', function (e) {
      var t = e.target;
      if (!t) {
        return;
      }
      if (t.closest('[data-nh-cr-help-dismiss]')) {
        hide();
        return;
      }
      if (t.closest('[data-nh-cr-help-chat]')) {
        e.preventDefault();
        hide(true);
        openCrisp();
        return;
      }
      if (t.closest('[data-nh-cr-help-checkout]')) {
        markSeen();
      }
    });
    document.addEventListener('keydown', onKey);
  }

  function listenShipping() {
    document.body.addEventListener('nh_cr_shipping_priced', function (e) {
      var code = e.detail && e.detail.postcode ? e.detail.postcode : '';
      markPriced(code);
    });
    document.body.addEventListener('nh_side_cart_opened', function () {
      clearTimer();
      if (shown) {
        hide();
      }
    });
    document.body.addEventListener('nh_side_cart_closed', function () {
      schedule();
    });
    if (!window.jQuery) {
      return;
    }
    window.jQuery(document.body).on('updated_shipping_method updated_wc_div', function () {
      var el = document.getElementById('nh_sc_shipping_postcode') || document.getElementById('calc_shipping_postcode');
      if (el && String(el.value || '').trim()) {
        markPriced(el.value);
      }
    });
  }

  function boot() {
    root = document.getElementById('nh-cr-help');
    if (!root) {
      return;
    }
    if (alreadySeen()) {
      return;
    }
    card = $('.nh-cr-help__card', root);
    kickerEl = $('[data-nh-cr-help-kicker]', root);
    bind();
    listenShipping();
    if (priced) {
      schedule();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
