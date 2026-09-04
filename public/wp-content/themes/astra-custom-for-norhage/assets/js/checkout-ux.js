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
        'billing_email_field',
        'billing_phone_field',
        'billing_country_field',
        'billing_postcode_field',
        'billing_address_1_field',
        'billing_address_2_field',
        'billing_city_field',
        'billing_state_field',
        'nh_section_person_field',
        'billing_first_name_field',
        'billing_last_name_field',
        'billing_contact_email_field',
        'billing_contact_phone_field'
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

    $('.nh-checkout-field--person-extra').each(function () {
      var $row = $(this);
      $row.toggleClass('nh-checkout-field--hidden', !isBusiness);
      if (!isBusiness) {
        $row.removeClass('woocommerce-invalid woocommerce-invalid-required-field validate-required');
        ensureRequiredMark($row, false);
      }
    });

    orderBillingFields();
    syncPairedAddressRows();
  }

  function bindCustomerType() {
    $(document.body).off('change.nhCheckoutType', 'input[name="billing_customer_type"]');
    $(document.body).on('change.nhCheckoutType', 'input[name="billing_customer_type"]', applyCustomerType);
    applyCustomerType();
  }

  function callingCodeForCountry(iso) {
    var map = i18n.phoneIsoCodes || {};
    return map[iso] || '';
  }

  function longestCallingCodes() {
    var map = i18n.phoneIsoCodes || {};
    var codes = [];
    Object.keys(map).forEach(function (iso) {
      var code = String(map[iso]);
      if (codes.indexOf(code) === -1) {
        codes.push(code);
      }
    });
    codes.sort(function (a, b) {
      return b.length - a.length;
    });
    return codes;
  }

  function splitInternationalPhone(value) {
    var raw = String(value || '').trim();
    if (raw.indexOf('00') === 0) {
      raw = '+' + raw.slice(2);
    }
    if (raw.charAt(0) !== '+') {
      return null;
    }
    var digits = raw.replace(/\D/g, '');
    var codes = longestCallingCodes();
    for (var i = 0; i < codes.length; i++) {
      if (digits.indexOf(codes[i]) === 0) {
        return { code: codes[i], national: digits.slice(codes[i].length) };
      }
    }
    return null;
  }

  function hydratePhoneCombos() {
    $('.nh-phone-combo').each(function () {
      var $combo = $(this);
      if ($combo.data('nhHydrated')) {
        return;
      }
      var $select = $combo.find('select.nh-phone-code');
      var $input = $combo.find('input.input-text, input[type="tel"]');
      if (!$select.length || !$input.length) {
        return;
      }
      var parsed = splitInternationalPhone($input.val());
      if (parsed) {
        if ($select.find('option[value="' + parsed.code + '"]').length) {
          $select.val(parsed.code);
        }
        $input.val(parsed.national);
        $select.data('userSet', true);
      }
      $combo.data('nhHydrated', true);
    });
  }

  function bindCallingCode() {
    $(document.body).off('change.nhPhoneCode', '.nh-phone-code');
    $(document.body).on('change.nhPhoneCode', '.nh-phone-code', function () {
      $(this).data('userSet', true);
    });
    $(document.body).off('change.nhPhoneCountry', '#billing_country');
    $(document.body).on('change.nhPhoneCountry', '#billing_country', function () {
      var code = callingCodeForCountry($(this).val());
      if (!code) {
        return;
      }
      $('#billing_phone_code, #billing_contact_phone_code').each(function () {
        var $select = $(this);
        if ($select.data('userSet')) {
          return;
        }
        var $input = $select.closest('.nh-phone-combo').find('input');
        var val = $.trim($input.val() || '');
        if (val.charAt(0) === '+' || val.indexOf('00') === 0) {
          return;
        }
        $select.val(code);
      });
    });
  }

  function pairCityState($city, $state) {
    if (!$city.length) {
      return;
    }
    var stateHidden = !$state.length ||
      !$state.is(':visible') ||
      $state.hasClass('hidden') ||
      $state.find('input[type="hidden"]').length > 0;
    $city.toggleClass('form-row-wide', stateHidden);
    $city.toggleClass('form-row-first', !stateHidden);
  }

  function syncPairedAddressRows() {
    pairCityState($('#billing_city_field'), $('#billing_state_field'));
    pairCityState($('#shipping_city_field'), $('#shipping_state_field'));
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

  function keepPhoneCodeNative() {
    $('.nh-phone-code').each(function () {
      var $el = $(this);
      if ($el.hasClass('select2-hidden-accessible') && $el.data('select2')) {
        $el.select2('destroy');
      }
    });
  }

  function boot() {
    bindCustomerType();
    bindCallingCode();
    keepPhoneCodeNative();
    hydratePhoneCombos();
    bindSummaryToggle();
    syncSummaryTotal();
    enhanceNotes();
    enhancePaymentCards();
    lockSummaryLayout();
    syncPairedAddressRows();
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
      syncPairedAddressRows();
      lockSummaryLayout();
    }, 0);
  });
  $(document.body).on('payment_method_selected', enhancePaymentCards);
})(jQuery);
