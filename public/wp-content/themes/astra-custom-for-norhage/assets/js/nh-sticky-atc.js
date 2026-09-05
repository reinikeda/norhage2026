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

    var defaultLabel = stickyBtn.getAttribute('data-default-label') || stickyBtn.textContent;
    var mq = window.matchMedia('(max-width: 1023px)');

    function bundleBtn() {
      return document.getElementById('add-bundle-to-cart');
    }

    function buttonIsReady(btn) {
      if (!btn) return false;
      if (btn.disabled || btn.classList.contains('disabled') || btn.classList.contains('is-disabled')) return false;
      if (String(btn.getAttribute('aria-disabled') || '').toLowerCase() === 'true') return false;
      return true;
    }

    function bundleBusy() {
      var btn = bundleBtn();
      if (!btn) return false;
      return btn.classList.contains('is-busy') || String(btn.getAttribute('aria-busy') || '') === 'true';
    }

    function bundleReady() {
      return buttonIsReady(bundleBtn());
    }

    function useBundleMode() {
      return bundleReady() || bundleBusy();
    }

    function activeTarget() {
      return useBundleMode() ? bundleBtn() : mainBtn;
    }

    function priceSource() {
      if (useBundleMode()) {
        return document.getElementById('bundle-total-amount') ||
          document.querySelector('#nh-price-summary [data-ps="total"]');
      }
      return document.querySelector('#nh-price-summary [data-ps="total"]');
    }

    function syncPrice() {
      var src = priceSource();
      if (!src || !priceOut) return;
      priceOut.innerHTML = src.innerHTML;
    }

    function syncButton() {
      var useBundle = useBundleMode();
      var target = activeTarget();
      var ready = buttonIsReady(target);

      bar.classList.toggle('is-bundle', useBundle);
      stickyBtn.textContent = useBundle
        ? (bundleBtn().textContent || '').trim() || defaultLabel
        : defaultLabel;
      stickyBtn.disabled = !ready;
      stickyBtn.setAttribute('aria-disabled', ready ? 'false' : 'true');
      stickyBtn.classList.toggle('disabled', !ready);
    }

    function targetInView() {
      var target = activeTarget();
      if (!target) return false;
      var rect = target.getBoundingClientRect();
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

      var show = !targetInView();
      bar.hidden = !show;
      bar.classList.toggle('is-visible', show);
      document.body.classList.toggle('has-nh-sticky-atc', show);
    }

    function sync() {
      syncButton();
      syncPrice();
      updateVisibility();
    }

    stickyBtn.addEventListener('click', function (e) {
      e.preventDefault();
      var target = activeTarget();
      if (!target) return;
      if (!buttonIsReady(target)) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (target === mainBtn) {
          target.click();
        }
        return;
      }
      target.click();
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
        'found_variation hide_variation reset_data updated_checkout added_to_cart nh_bundle_state',
        sync
      );
      window.jQuery(document.body).on('nh_side_cart_open nh_side_cart_close', updateVisibility);
    }

    function observeNode(node) {
      if (!node || typeof MutationObserver !== 'function') return;
      new MutationObserver(sync).observe(node, { childList: true, subtree: true, characterData: true });
    }

    observeNode(document.querySelector('#nh-price-summary [data-ps="total"]'));
    observeNode(document.getElementById('bundle-total-amount'));

    function observeButton(btn) {
      if (!btn || typeof MutationObserver !== 'function') return;
      new MutationObserver(sync).observe(btn, {
        attributes: true,
        attributeFilter: ['disabled', 'class', 'aria-disabled', 'aria-busy'],
      });
    }

    observeButton(mainBtn);
    observeButton(bundleBtn());

    var bundleForm = document.getElementById('nc-bundle-form');
    if (bundleForm) {
      bundleForm.addEventListener('change', sync);
      bundleForm.addEventListener('input', sync);
    }

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
