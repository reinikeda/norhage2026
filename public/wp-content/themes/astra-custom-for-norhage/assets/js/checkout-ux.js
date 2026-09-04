/**
 * Classic checkout: private/business toggle, field order, summary layout lock.
 */
(function ($) {
  'use strict';

  var i18n = window.nhCheckoutUx || {};

  function selectedType() {
    var $checked = $('input[name="billing_customer_type"]:checked');
    if ($checked.length) {
      return $checked.val() === 'business' ? 'business' : 'private';
    }
    return 'private';
  }

  function fieldOrder(isBusiness) {
    if (isBusiness) {
      return [
        'billing_customer_type_field',
        'billing_company_field',
        'billing_company_reg_field',
        'billing_country_field',
        'billing_postcode_field',
        'billing_address_1_field',
        'billing_address_2_field',
        'billing_city_field',
        'billing_state_field',
        'billing_email_field',
        'billing_phone_field',
        'nh_section_person_field',
        'billing_first_name_field',
        'billing_last_name_field'
      ];
    }
    return [
      'billing_customer_type_field',
      'billing_first_name_field',
      'billing_last_name_field',
      'billing_email_field',
      'billing_phone_field',
      'billing_country_field',
      'billing_postcode_field',
      'billing_address_1_field',
      'billing_address_2_field',
      'billing_city_field',
      'billing_state_field'
    ];
  }

  function orderBillingFields() {
    var $wrap = $('.woocommerce-billing-fields__field-wrapper');
    if (!$wrap.length) {
      return;
    }
    var isBusiness = selectedType() === 'business';
    fieldOrder(isBusiness).forEach(function (id, index) {
      var $el = $('#' + id);
      if (!$el.length) {
        return;
      }
      var priority = (index + 1) * 10;
      $el.attr('data-priority', priority).data('priority', priority);
      $wrap.append($el);
    });
  }

  function ensureRequiredMark($row, on) {
    var $label = $row.find('> label').first();
    if (!$label.length) {
      return;
    }
    $label.find('.optional').toggle(!on);
    if (on) {
      if (!$label.find('.required').length) {
        $label.append(' <span class="required" aria-hidden="true">*</span>');
      }
      $row.find('.input-text, select, textarea').attr('aria-required', 'true');
    } else {
      $label.find('.required').remove();
      $row.find('.input-text, select, textarea').removeAttr('aria-required');
    }
  }

  function applyCustomerType() {
    var isBusiness = selectedType() === 'business';
    var $form = $('form.checkout');
    var $body = $(document.body);

    $form.toggleClass('nh-checkout--business', isBusiness);
    $body.toggleClass('nh-checkout--business', isBusiness);

    $('.nh-checkout-field--business').each(function () {
      var $row = $(this);
      $row.toggleClass('nh-checkout-field--hidden', !isBusiness);
      if ($row.is('p.form-row')) {
        $row.toggleClass('validate-required', isBusiness);
        ensureRequiredMark($row, isBusiness);
        if (!isBusiness) {
          $row.removeClass('woocommerce-invalid woocommerce-invalid-required-field');
        }
      }
    });

    $('.nh-checkout-field--person').each(function () {
      var $row = $(this);
      $row.toggleClass('validate-required', !isBusiness);
      ensureRequiredMark($row, !isBusiness);
      if (isBusiness) {
        $row.removeClass('woocommerce-invalid woocommerce-invalid-required-field');
      }
    });

    orderBillingFields();
  }

  function bindCustomerType() {
    $(document.body).off('change.nhCheckoutType', 'input[name="billing_customer_type"]');
    $(document.body).on('change.nhCheckoutType', 'input[name="billing_customer_type"]', applyCustomerType);
    applyCustomerType();
  }

  function lockSummaryLayout() {
    var wide = window.matchMedia('(min-width: 960px)').matches;
    var layout = document.querySelector('.nh-checkout-layout');
    var aside = document.querySelector('.nh-checkout-layout__aside');
    var main = document.querySelector('.nh-checkout-layout__main');
    var review = document.getElementById('order_review');
    var table = document.querySelector('#order_review table.shop_table');

    if (layout) {
      layout.style.setProperty('width', '100%', 'important');
      layout.style.setProperty('max-width', '100%', 'important');
      layout.style.setProperty('float', 'none', 'important');
      layout.style.setProperty('display', wide ? 'grid' : 'flex', 'important');
      if (wide) {
        layout.style.setProperty('grid-template-columns', 'minmax(0, 1fr) 400px', 'important');
      } else {
        layout.style.setProperty('flex-direction', 'column', 'important');
      }
    }
    if (aside) {
      aside.style.setProperty('float', 'none', 'important');
      if (wide) {
        aside.style.setProperty('width', '400px', 'important');
        aside.style.setProperty('max-width', '400px', 'important');
        aside.style.setProperty('min-width', '400px', 'important');
      } else {
        aside.style.setProperty('width', '100%', 'important');
        aside.style.setProperty('max-width', '100%', 'important');
        aside.style.setProperty('min-width', '0', 'important');
      }
    }
    if (main) {
      main.style.setProperty('float', 'none', 'important');
      main.style.setProperty('min-width', '0', 'important');
      if (!wide) {
        main.style.setProperty('width', '100%', 'important');
      }
    }
    [review, document.getElementById('order_review_heading')].forEach(function (el) {
      if (!el) {
        return;
      }
      el.style.setProperty('float', 'none', 'important');
      el.style.setProperty('width', '100%', 'important');
      el.style.setProperty('max-width', '100%', 'important');
    });
    if (table) {
      table.style.setProperty('display', 'table', 'important');
      table.style.setProperty('width', '100%', 'important');
      table.style.setProperty('max-width', '100%', 'important');
      table.style.setProperty('float', 'none', 'important');
      table.style.setProperty('table-layout', 'auto', 'important');
      table.style.removeProperty('zoom');
    }
  }

  function syncSummaryTotal() {
    var $src = $('#order_review .order-total td').first();
    var $dest = $('.nh-checkout-summary-toggle__amount');
    if ($src.length && $dest.length) {
      $dest.html($src.html());
    }
  }

  function bindSummaryToggle() {
    var $summary = $('.nh-checkout-summary');
    if (!$summary.length) {
      return;
    }

    var $toggle = $summary.find('.nh-checkout-summary-toggle');
    $toggle.off('click.nhCheckout').on('click.nhCheckout', function () {
      if (window.matchMedia('(min-width: 960px)').matches) {
        return;
      }
      var open = $summary.toggleClass('is-open').hasClass('is-open');
      $toggle.attr('aria-expanded', open ? 'true' : 'false');
    });
  }

  function enhanceNotes() {
    var $wrap = $('.woocommerce-additional-fields');
    if (!$wrap.length || $wrap.data('nhNotesReady')) {
      return;
    }

    var $fields = $wrap.find('.woocommerce-additional-fields__field-wrapper');
    var $textarea = $fields.find('textarea');
    if (!$fields.length || !$textarea.length) {
      return;
    }

    $wrap.data('nhNotesReady', true);
    $wrap.addClass('nh-notes');
    $wrap.find('> h3').hide();

    var hasValue = $.trim($textarea.val() || '') !== '';
    var $btn = $('<button type="button" class="nh-notes-toggle" aria-expanded="false"></button>');
    $btn.text(i18n.noteLabel || 'Add a note (optional)');
    $fields.before($btn);

    function setOpen(open) {
      $wrap.toggleClass('is-open', open);
      $btn.attr('aria-expanded', open ? 'true' : 'false');
      $fields.toggle(open);
    }

    setOpen(hasValue);

    $btn.on('click', function () {
      var open = !$wrap.hasClass('is-open');
      setOpen(open);
      if (open) {
        $textarea.trigger('focus');
      }
    });
  }

  function enhancePaymentCards() {
    var $list = $('ul.wc_payment_methods');
    if ($list.length) {
      $list.find('li').each(function () {
        var $li = $(this);
        $li.toggleClass('is-selected', $li.find('input.input-radio:checked').length > 0);
      });
    }

    var $section = $('.nh-checkout-payment');
    if ($section.length) {
      $section.toggle(!!$section.find('#payment').length);
    }
  }

  function boot() {
    bindCustomerType();
    bindSummaryToggle();
    syncSummaryTotal();
    enhanceNotes();
    enhancePaymentCards();
    lockSummaryLayout();
  }

  $(boot);
  $(window).on('resize.nhCheckout', lockSummaryLayout);
  $(document.body).on(
    'updated_checkout payment_method_selected init_checkout',
    function () {
      boot();
    }
  );
  $(document.body).on('country_to_state_changing country_to_state_changed', function () {
    window.setTimeout(function () {
      orderBillingFields();
      lockSummaryLayout();
    }, 0);
  });
  $(document.body).on('payment_method_selected', enhancePaymentCards);
})(jQuery);
