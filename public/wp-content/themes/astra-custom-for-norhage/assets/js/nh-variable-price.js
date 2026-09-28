/* Product page: "From {lowest}" until a variation is chosen, then that price. */
(function ($) {
  'use strict';

  function visiblePrice($form) {
    var $summary = $form.closest('.summary, .entry-summary');
    if (!$summary.length) {
      $summary = $form.closest('.product');
    }

    return $summary.find('p.price, span.price').filter(function () {
      return $(this).closest('.single_variation, .woocommerce-variation-price').length === 0;
    }).first();
  }

  function priceTarget($price) {
    var $inner = $price.children('.nh-tax-price').first();
    return $inner.length ? $inner : $price;
  }

  function unwrapTaxPrice(html) {
    var $tmp = $('<div>').html(html);
    var $wrap = $tmp.children('.nh-tax-price');
    var onlyWrap = $tmp.contents().filter(function () {
      return this.nodeType === 1;
    }).length === 1;

    if ($wrap.length === 1 && onlyWrap) {
      return $wrap.html();
    }

    return html;
  }

  function bind($form) {
    if ($form.data('nhFromBound')) {
      return;
    }
    $form.data('nhFromBound', 1);

    var $price = visiblePrice($form);
    if (!$price.length) {
      return;
    }

    var $target = priceTarget($price);
    var original = $target.html();

    $form.on('found_variation.nhFrom', function (_event, variation) {
      if (!variation || !variation.price_html) {
        return;
      }

      var html = variation.price_html;
      if ($target.hasClass('nh-tax-price')) {
        html = unwrapTaxPrice(html);
      }
      $target.html(html);
    });

    $form.on('hide_variation.nhFrom reset_data.nhFrom', function () {
      $target.html(original);
    });

    $form.trigger('check_variations');
  }

  $(function () {
    $('form.variations_form').each(function () {
      bind($(this));
    });
  });
})(jQuery);
