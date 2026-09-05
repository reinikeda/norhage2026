/**
 * Norhage side cart.
 *
 * Header basket icon opens the drawer. Full cart page is only reached from
 * "View basket" inside the drawer. Opens after every successful add to cart.
 */
(function ($) {
  'use strict';

  var cfg = window.nhSideCart || {};
  var $root = null;
  var $panel = null;
  var lastFocus = null;
  var qtyTimer = null;
  var pending = null;

  function ajaxUrl(endpoint) {
    var src = cfg.ajaxUrl || '/?wc-ajax=%%endpoint%%';
    return src.replace('%%endpoint%%', endpoint);
  }

  function applyFragments(fragments) {
    if (!fragments) {
      return;
    }
    $.each(fragments, function (selector, html) {
      $(selector).replaceWith(html);
    });
  }

  function rememberCartHash(hash) {
    if (!hash || !window.sessionStorage) {
      return;
    }
    try {
      var params = window.wc_cart_fragments_params || {};
      var key = params.cart_hash_key || 'wc_cart_hash';
      sessionStorage.setItem(key, String(hash));
    } catch (err) {
      /* private mode / blocked storage */
    }
  }

  function setCrispLauncher(show) {
    if (show && document.body.classList.contains('has-nh-sticky-atc')) {
      show = false;
    }
    window.$crisp = window.$crisp || [];
    try {
      window.$crisp.push(['do', show ? 'chat:show' : 'chat:hide']);
    } catch (err) {
      /* Crisp not present */
    }
  }

  function setLoading(on) {
    if (!$root) {
      return;
    }
    $root.find('#nh-sc-body').toggleClass('is-loading', !!on);
    $root.attr('aria-busy', on ? 'true' : 'false');
  }

  function request(data) {
    if (pending && pending.abort) {
      pending.abort();
    }
    setLoading(true);
    pending = $.post(ajaxUrl('nh_sc_update'), $.extend({ nonce: cfg.nonce }, data))
      .done(function (res) {
        if (res && res.success && res.data && res.data.fragments) {
          rememberCartHash(res.data.cart_hash);
          applyFragments(res.data.fragments);
          $(document.body).trigger('wc_fragments_refreshed');
        } else {
          window.alert((res && res.data && res.data.message) || (cfg.i18n && cfg.i18n.error));
        }
      })
      .fail(function (xhr, status) {
        if (status === 'abort') {
          return;
        }
        window.alert((cfg.i18n && cfg.i18n.error) || 'Could not update the basket. Please try again.');
      })
      .always(function () {
        pending = null;
        setLoading(false);
      });
    return pending;
  }

  function isOpen() {
    return !!( $root && $root.hasClass('is-open') );
  }

  function trapTab(e) {
    if (e.key !== 'Tab' || !$panel) {
      return;
    }
    var focusable = $panel.find('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
    if (!focusable.length) {
      return;
    }
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  function closeMobileNav() {
    var drawer = document.getElementById('nh-mobile-drawer');
    if (!drawer) {
      return;
    }
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    drawer.hidden = true;
    document.body.classList.remove('drawer-open');
    var burger = document.querySelector('.nh-burger');
    if (burger) {
      burger.setAttribute('aria-expanded', 'false');
    }
  }

  function open() {
    if (!$root || cfg.isCartPage) {
      return;
    }
    if (isOpen()) {
      return;
    }
    lastFocus = document.activeElement;
    closeMobileNav();
    $root.removeAttr('hidden').addClass('is-open');
    $root.attr('aria-hidden', 'false');
    document.body.classList.add('nh-sc-open');
    setCrispLauncher(false);
    $(document.body).trigger('nh_side_cart_opened');
    window.setTimeout(function () {
      var closeBtn = $root.find('.nh-sc__close').get(0);
      if (closeBtn) {
        closeBtn.focus({ preventScroll: true });
      }
    }, 30);
  }

  function close() {
    if (!$root || !isOpen()) {
      return;
    }
    $root.removeClass('is-open').attr('aria-hidden', 'true');
    document.body.classList.remove('nh-sc-open');
    setCrispLauncher(true);
    $(document.body).trigger('nh_side_cart_closed');
    window.setTimeout(function () {
      if (!isOpen()) {
        $root.attr('hidden', 'hidden');
      }
    }, 280);
    if (lastFocus && typeof lastFocus.focus === 'function') {
      lastFocus.focus({ preventScroll: true });
    }
  }

  function bindHeader() {
    $(document).on('click.nhSc', 'a.nh-cart', function (e) {
      if (cfg.isCartPage) {
        return;
      }
      if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
        return;
      }
      e.preventDefault();
      open();
    });
  }

  function clampQty($input, next) {
    var min = parseFloat($input.attr('min') || '1', 10);
    var max = $input.attr('max') ? parseFloat($input.attr('max'), 10) : 0;
    if (!isFinite(min)) {
      min = 1;
    }
    if (next < min) {
      next = min;
    }
    if (max > 0 && next > max) {
      next = max;
    }
    return next;
  }

  function queueQty($wrap) {
    var key = $wrap.data('nh-sc-qty');
    var $input = $wrap.find('.nh-sc__qty-input');
    var qty = clampQty($input, parseFloat($input.val() || '0', 10) || 0);
    $input.val(String(qty));
    window.clearTimeout(qtyTimer);
    qtyTimer = window.setTimeout(function () {
      request({ op: 'qty', key: key, qty: qty });
    }, 280);
  }

  function bindDrawer() {
    $(document).on('click.nhSc', '[data-nh-sc-close]', function () {
      close();
    });

    $(document).on('keydown.nhSc', function (e) {
      if (e.key === 'Escape' && isOpen()) {
        close();
      }
      if (isOpen()) {
        trapTab(e);
      }
    });

    $(document).on('click.nhSc', '.nh-sc__qty-btn', function () {
      var $btn = $(this);
      var $wrap = $btn.closest('[data-nh-sc-qty]');
      var $input = $wrap.find('.nh-sc__qty-input');
      var delta = parseFloat($btn.data('delta'), 10) || 0;
      var next = clampQty($input, (parseFloat($input.val() || '0', 10) || 0) + delta);
      $input.val(String(next));
      queueQty($wrap);
    });

    $(document).on('change.nhSc', '.nh-sc__qty-input', function () {
      queueQty($(this).closest('[data-nh-sc-qty]'));
    });

    $(document).on('click.nhSc', '[data-nh-sc-remove]', function (e) {
      e.preventDefault();
      var key = $(this).data('nh-sc-remove');
      request({ op: 'remove', key: key });
    });

    $(document).on('submit.nhSc', '[data-nh-sc-shipping]', function (e) {
      e.preventDefault();
      var $form = $(this);
      request({
        op: 'shipping',
        calc_shipping_country: $form.find('[name="calc_shipping_country"]').val() || '',
        calc_shipping_postcode: $form.find('[name="calc_shipping_postcode"]').val() || '',
      });
    });

    $(document).on('change.nhSc', '.nh-sc__method-input', function () {
      var methods = {};
      $root.find('.nh-sc__method-input:checked').each(function () {
        var index = $(this).data('index');
        methods[index] = $(this).val();
      });
      request({ op: 'method', shipping_method: methods });
    });
  }

  function bindAddToCart() {
    $(document.body).on('added_to_cart', function () {
      open();
    });

    $(document.body).on('nh_side_cart_open', function () {
      open();
    });
  }

  $(function () {
    $root = $('#nh-side-cart');
    $panel = $root.find('.nh-sc__panel');
    if (!$root.length) {
      return;
    }

    bindHeader();
    bindDrawer();
    bindAddToCart();

    if (cfg.openOnLoad) {
      open();
    }

    window.nhSideCartApi = {
      open: open,
      close: close,
    };

    window.addEventListener('pagehide', function () {
      setCrispLauncher(true);
    });
  });
})(jQuery);
