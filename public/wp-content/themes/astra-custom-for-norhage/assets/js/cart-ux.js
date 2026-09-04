/**
 * Classic cart: keep the shipping calculator open, auto-update qty, coupon toggle.
 */
(function ($) {
  'use strict';

  function keepCalculatorOpen() {
    var $form = $('.woocommerce-cart .shipping-calculator-form');
    if (!$form.length) {
      return;
    }
    $form.show().css('display', 'block');
  }

  function bindQtyAutoUpdate() {
    var $cart = $('.woocommerce-cart-form');
    if (!$cart.length || $cart.data('nhQtyBound')) {
      return;
    }
    $cart.data('nhQtyBound', true);

    var timer = null;
    $cart.on('change.nhCartUx', '.qty', function () {
      var $btn = $cart.find('button[name="update_cart"]');
      if (!$btn.length) {
        return;
      }
      clearTimeout(timer);
      timer = setTimeout(function () {
        $btn.prop('disabled', false).trigger('click');
      }, 280);
    });
  }

  function enhanceCoupon() {
    var $coupon = $('.woocommerce-cart-form .coupon');
    if (!$coupon.length || $coupon.hasClass('nh-coupon--ready')) {
      return;
    }

    var label = (window.nhCartUx && window.nhCartUx.couponLabel) || 'Have a coupon?';
    var $fields = $('<div class="nh-coupon-fields" />');
    $coupon.contents().appendTo($fields);
    $coupon.addClass('nh-coupon--ready').empty();

    var $toggle = $('<button type="button" class="nh-coupon-toggle" aria-expanded="false"></button>').text(label);
    $coupon.append($toggle).append($fields);

    $toggle.on('click', function () {
      var open = $coupon.toggleClass('is-open').hasClass('is-open');
      $toggle.attr('aria-expanded', open ? 'true' : 'false');
      if (open) {
        $coupon.find('input[name="coupon_code"]').trigger('focus');
      }
    });
  }

  function amountHtmlFromTotalCell($src) {
    var $strong;
    var $amount;
    var $clone;
    if (!$src || !$src.length) {
      return '';
    }
    $strong = $src.find('strong').first();
    if ($strong.length) {
      return $strong.html();
    }
    $amount = $src.find('.woocommerce-Price-amount').first();
    if ($amount.length) {
      return $amount.prop('outerHTML');
    }
    $clone = $src.clone();
    $clone.find('.includes_tax, .tax_label, .price-tax-note').remove();
    return $.trim($clone.html());
  }

  function syncStickyTotal() {
    var $src = $('.cart_totals .order-total td').first();
    var $dest = $('.nh-cart-sticky-bar__amount');
    if ($src.length && $dest.length) {
      $dest.html(amountHtmlFromTotalCell($src));
    }
  }

  function placeCrossSells($main, $form) {
    var $sells = $('.cross-sells');
    if (!$sells.length || !$main || !$main.length || !$form || !$form.length) {
      return;
    }
    $sells.not(':first').remove();
    $sells = $('.cross-sells').first();
    if (!$sells.parent().is($main) || $sells.prev()[0] !== $form[0]) {
      $sells.insertAfter($form);
    }
  }

  function ensureLayout() {
    var $form = $('.woocommerce-cart-form').first();
    var $col = $('.cart-collaterals').first();
    if (!$form.length || !$col.length) {
      return;
    }

    var $layout = $form.closest('.nh-cart-layout');
    if (!$layout.length) {
      $layout = $col.closest('.nh-cart-layout');
    }
    if (!$layout.length) {
      $form.add($col).wrapAll('<div class="nh-cart-layout"></div>');
      $layout = $form.closest('.nh-cart-layout');
    }

    var $main = $layout.children('.nh-cart-layout__main');
    if (!$main.length) {
      $main = $('<div class="nh-cart-layout__main" />');
      $layout.prepend($main);
    }

    var $side = $layout.children('.nh-cart-layout__side');
    if (!$side.length) {
      $side = $('<div class="nh-cart-layout__side" />');
      $main.after($side);
    }

    $layout.children('.woocommerce-notices-wrapper').prependTo($layout);

    if (!$form.closest('.nh-cart-layout__main').length) {
      $main.append($form);
    }
    if (!$col.closest('.nh-cart-layout__side').length) {
      $side.append($col);
    }

    placeCrossSells($main, $form);

    var $bar = $('.nh-cart-sticky-bar').first();
    if ($bar.length && !$bar.parent().is($layout)) {
      $layout.append($bar);
    }
  }

  function lockColumnStyles() {
    var layout = document.querySelector('.nh-cart-layout');
    if (!layout) {
      return;
    }

    layout.style.setProperty('display', 'flex', 'important');
    layout.style.setProperty('flex-wrap', 'wrap', 'important');
    layout.style.setProperty('width', '100%', 'important');
    layout.style.setProperty('max-width', '100%', 'important');
    layout.style.setProperty('float', 'none', 'important');

    var wide = window.matchMedia('(min-width: 960px)').matches;
    layout.style.setProperty('flex-direction', wide ? 'row' : 'column', 'important');

    var main = layout.querySelector('.nh-cart-layout__main');
    var side = layout.querySelector('.nh-cart-layout__side');
    var form = layout.querySelector('.woocommerce-cart-form');
    var col = layout.querySelector('.cart-collaterals');
    var totals = layout.querySelector('.cart_totals');

    if (main) {
      main.style.setProperty('min-width', '0', 'important');
      main.style.setProperty('overflow', 'visible', 'important');
      if (wide) {
        main.style.setProperty('flex', '1 1 0%', 'important');
        main.style.setProperty('max-width', 'calc(100% - 432px)', 'important');
        main.style.setProperty('width', 'auto', 'important');
      } else {
        main.style.setProperty('flex', '1 1 auto', 'important');
        main.style.setProperty('width', '100%', 'important');
        main.style.setProperty('max-width', '100%', 'important');
      }
    }
    if (side) {
      side.style.setProperty('min-width', '0', 'important');
      side.style.setProperty('float', 'none', 'important');
      if (wide) {
        side.style.setProperty('flex', '0 0 400px', 'important');
        side.style.setProperty('width', '400px', 'important');
        side.style.setProperty('max-width', '400px', 'important');
      } else {
        side.style.setProperty('flex', '1 1 auto', 'important');
        side.style.setProperty('width', '100%', 'important');
        side.style.setProperty('max-width', '100%', 'important');
      }
    }

    if (form) {
      form.style.setProperty('position', 'relative', 'important');
      if (wide && !main) {
        form.style.setProperty('float', 'left', 'important');
        form.style.setProperty('width', 'calc(100% - 432px)', 'important');
        form.style.setProperty('max-width', 'calc(100% - 432px)', 'important');
      } else {
        form.style.setProperty('float', 'none', 'important');
        form.style.setProperty('width', '100%', 'important');
        form.style.setProperty('max-width', '100%', 'important');
      }
    }
    if (col) {
      col.style.setProperty('position', 'relative', 'important');
      if (wide && !side) {
        col.style.setProperty('float', 'right', 'important');
        col.style.setProperty('width', '400px', 'important');
        col.style.setProperty('max-width', '400px', 'important');
      } else if (wide && side) {
        col.style.setProperty('float', 'none', 'important');
        col.style.setProperty('width', '100%', 'important');
        col.style.setProperty('max-width', '100%', 'important');
      } else {
        col.style.setProperty('float', 'none', 'important');
        col.style.setProperty('width', '100%', 'important');
        col.style.setProperty('max-width', '100%', 'important');
      }
    }
    if (totals) {
      totals.style.setProperty('float', 'none', 'important');
      totals.style.setProperty('width', '100%', 'important');
      totals.style.setProperty('max-width', '100%', 'important');
    }
  }

  function stampShippingIndexes() {
    $('input.shipping_method, select.shipping_method').each(function () {
      var $el = $(this);
      var name = $el.attr('name') || '';
      var match = name.match(/shipping_method\[(\d+)\]/);
      var index = match ? match[1] : ($el.attr('data-index') || '0');
      $el.attr('data-index', index);
      $el.data('index', parseInt(index, 10));
    });
  }

  function rewriteShippingPayload(data) {
    if (typeof data === 'string') {
      return data
        .replace(/shipping_method%5Bundefined%5D/g, 'shipping_method%5B0%5D')
        .replace(/shipping_method\[undefined\]/g, 'shipping_method[0]');
    }
    if (data && typeof data === 'object' && data.shipping_method && typeof data.shipping_method === 'object') {
      if (Object.prototype.hasOwnProperty.call(data.shipping_method, 'undefined')) {
        if (data.shipping_method[0] == null) {
          data.shipping_method[0] = data.shipping_method.undefined;
        }
        delete data.shipping_method.undefined;
      }
    }
    return data;
  }

  $.ajaxPrefilter(function (options) {
    if (!options || options.data == null) {
      return;
    }
    if (typeof options.data === 'string' && options.data.indexOf('shipping_method') === -1) {
      return;
    }
    options.data = rewriteShippingPayload(options.data);
  });

  function boot() {
    stampShippingIndexes();
    ensureLayout();
    lockColumnStyles();
    keepCalculatorOpen();
    bindQtyAutoUpdate();
    enhanceCoupon();
    syncStickyTotal();
  }

  stampShippingIndexes();

  $(boot);
  $(window).on('resize.nhCartUx', lockColumnStyles);
  $(document.body).on(
    'updated_wc_div updated_cart_totals wc_fragments_refreshed updated_shipping_method',
    boot
  );
})(jQuery);
