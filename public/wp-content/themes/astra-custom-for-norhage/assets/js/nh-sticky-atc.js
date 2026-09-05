/**
 * Mobile PDP helpers: sticky add-to-cart (main product) + feature-box toggle.
 */
(function () {
  'use strict';

  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function initFeatureMore() {
    document.querySelectorAll('[data-nhf-more]').forEach(function (btn) {
      var box = btn.closest('.nhf-box');
      if (!box) return;

      btn.addEventListener('click', function () {
        var open = box.classList.toggle('is-expanded');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });
  }

  function isOverlayOpen() {
    if (document.body.classList.contains('drawer-open')) return true;

    var drawer = document.getElementById('nh-mobile-drawer');
    if (drawer && drawer.classList.contains('is-open')) return true;

    var side = document.getElementById('nh-side-cart');
    if (side && side.getAttribute('aria-hidden') === 'false') return true;
    if (side && !side.hasAttribute('hidden') && side.classList.contains('is-open')) return true;

    return false;
  }

  function initStickyAtc() {
    var bar = document.getElementById('nh-sticky-atc');
    if (!bar) return;

    var mainBtn = document.querySelector('form.cart .single_add_to_cart_button');
    var stickyBtn = bar.querySelector('.nh-sticky-atc__btn');
    var priceOut = bar.querySelector('[data-sticky-price]');
    if (!mainBtn || !stickyBtn) return;

    var mq = window.matchMedia('(max-width: 1023px)');

    function priceSource() {
      return document.querySelector('#nh-price-summary [data-ps="total"]');
    }

    function syncPrice() {
      var src = priceSource();
      if (!src || !priceOut) return;
      priceOut.innerHTML = src.innerHTML;
    }

    function mainReady() {
      if (mainBtn.disabled || mainBtn.classList.contains('disabled')) return false;
      if (String(mainBtn.getAttribute('aria-disabled') || '').toLowerCase() === 'true') return false;
      return true;
    }

    function syncButton() {
      var ready = mainReady();
      stickyBtn.disabled = !ready;
      stickyBtn.setAttribute('aria-disabled', ready ? 'false' : 'true');
      stickyBtn.classList.toggle('disabled', !ready);
    }

    function nativeInView() {
      var rect = mainBtn.getBoundingClientRect();
      var vh = window.innerHeight || 0;
      var barH = bar.classList.contains('is-visible') ? bar.offsetHeight : 72;
      return rect.top < (vh - barH - 8) && rect.bottom > 96;
    }

    function updateVisibility() {
      if (!mq.matches || isOverlayOpen()) {
        bar.hidden = true;
        bar.classList.remove('is-visible');
        document.body.classList.remove('has-nh-sticky-atc');
        return;
      }

      var show = !nativeInView();
      bar.hidden = !show;
      bar.classList.toggle('is-visible', show);
      document.body.classList.toggle('has-nh-sticky-atc', show);
    }

    function sync() {
      syncPrice();
      syncButton();
      updateVisibility();
    }

    stickyBtn.addEventListener('click', function (e) {
      e.preventDefault();
      if (!mainReady()) {
        mainBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      mainBtn.click();
    });

    if (window.NHPriceSummary && typeof window.NHPriceSummary.update === 'function') {
      var orig = window.NHPriceSummary.update;
      window.NHPriceSummary.update = function (data) {
        orig.call(window.NHPriceSummary, data);
        sync();
      };
    }

    document.addEventListener('nh:price-summary-ready', sync);

    var form = mainBtn.closest('form');
    if (form) {
      form.addEventListener('found_variation', sync);
      form.addEventListener('hide_variation', sync);
      form.addEventListener('reset_data', sync);
      form.addEventListener('change', sync);
    }

    if (window.jQuery) {
      window.jQuery(document.body).on(
        'found_variation hide_variation reset_data updated_checkout added_to_cart',
        sync
      );
      window.jQuery(document.body).on('nh_side_cart_open nh_side_cart_close', updateVisibility);
    }

    var src = priceSource();
    if (src && typeof MutationObserver === 'function') {
      new MutationObserver(sync).observe(src, { childList: true, subtree: true, characterData: true });
    }

    new MutationObserver(syncButton).observe(mainBtn, {
      attributes: true,
      attributeFilter: ['disabled', 'class', 'aria-disabled'],
    });

    window.addEventListener('scroll', updateVisibility, { passive: true });
    window.addEventListener('resize', updateVisibility);
    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', updateVisibility);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(updateVisibility);
    }

    sync();
    window.setTimeout(sync, 400);
  }

  onReady(function () {
    initFeatureMore();
    initStickyAtc();
  });
})();
