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

  function syncStickyTotal() {
    var $src = $('.cart_totals .order-total td').first();
    var $dest = $('.nh-cart-sticky-bar__amount');
    if ($src.length && $dest.length) {
      $dest.html($src.html());
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

    layout.style.setProperty('display', 'grid', 'important');
    layout.style.setProperty('width', '100%', 'important');
    layout.style.setProperty('max-width', '100%', 'important');
    layout.style.setProperty('float', 'none', 'important');

    var wide = window.matchMedia('(min-width: 960px)').matches;
    layout.style.setProperty(
      'grid-template-columns',
      wide ? 'minmax(0, 1fr) 400px' : 'minmax(0, 1fr)',
      'important'
    );

    layout.querySelectorAll('.woocommerce-cart-form, .cart-collaterals, .cart_totals').forEach(function (el) {
      el.style.setProperty('float', 'none', 'important');
      el.style.setProperty('position', 'relative', 'important');
      el.style.setProperty('left', 'auto', 'important');
      el.style.setProperty('right', 'auto', 'important');
    });

    var main = layout.querySelector('.nh-cart-layout__main') || layout.querySelector('.woocommerce-cart-form');
    var side = layout.querySelector('.nh-cart-layout__side') || layout.querySelector('.cart-collaterals');
    if (main) {
      main.style.setProperty('min-width', '0', 'important');
      main.style.setProperty('max-width', '100%', 'important');
      main.style.setProperty('overflow', 'hidden', 'important');
      if (wide) {
        main.style.setProperty('grid-column', '1', 'important');
      }
    }
    if (side) {
      side.style.setProperty('min-width', '0', 'important');
      side.style.setProperty('float', 'none', 'important');
      if (wide) {
        side.style.setProperty('grid-column', '2', 'important');
        side.style.setProperty('width', '400px', 'important');
        side.style.setProperty('max-width', '400px', 'important');
      } else {
        side.style.setProperty('width', '100%', 'important');
        side.style.setProperty('max-width', '100%', 'important');
        side.style.removeProperty('grid-column');
      }
    }
  }

  function boot() {
    ensureLayout();
    lockColumnStyles();
    keepCalculatorOpen();
    bindQtyAutoUpdate();
    enhanceCoupon();
    syncStickyTotal();
  }

  $(boot);
  $(window).on('resize.nhCartUx', lockColumnStyles);
  $(document.body).on(
    'updated_wc_div updated_cart_totals wc_fragments_refreshed updated_shipping_method',
    boot
  );
})(jQuery);
