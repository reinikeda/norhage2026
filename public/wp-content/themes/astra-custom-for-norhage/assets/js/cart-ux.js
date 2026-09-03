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
      $form.add($col).wrapAll('<div class="nh-cart-layout"></div>');
      $layout = $form.closest('.nh-cart-layout');
    }

    if (!$form.parent().is($layout)) {
      $layout.prepend($form);
    }
    if (!$col.parent().is($layout)) {
      $form.after($col);
    }

    var $bar = $('.nh-cart-sticky-bar').first();
    if ($bar.length && !$bar.parent().is($layout)) {
      $layout.append($bar);
    }
  }

  function boot() {
    ensureLayout();
    keepCalculatorOpen();
    bindQtyAutoUpdate();
    enhanceCoupon();
    syncStickyTotal();
  }

  $(boot);
  $(document.body).on(
    'updated_wc_div updated_cart_totals wc_fragments_refreshed updated_shipping_method',
    boot
  );
})(jQuery);
