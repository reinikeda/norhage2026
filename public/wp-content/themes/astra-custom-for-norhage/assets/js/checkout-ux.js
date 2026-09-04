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
    forcePairClasses();
    syncPairedAddressRows();
  }

  function bindCustomerType() {
    $(document.body).off('change.nhCheckoutType', 'input[name="billing_customer_type"]');
    $(document.body).on('change.nhCheckoutType', 'input[name="billing_customer_type"]', applyCustomerType);
    applyCustomerType();
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

  function flagEmoji(iso) {
    if (!iso || String(iso).length !== 2) {
      return '';
    }
    iso = String(iso).toUpperCase();
    return String.fromCodePoint(127397 + iso.charCodeAt(0), 127397 + iso.charCodeAt(1));
  }

  function isoForCallingCode(code) {
    var flags = i18n.phoneCodeFlags || {};
    if (flags[code]) {
      return flags[code];
    }
    var map = i18n.phoneIsoCodes || {};
    var keys = Object.keys(map);
    for (var i = 0; i < keys.length; i++) {
      if (String(map[keys[i]]) === String(code)) {
        return keys[i];
      }
    }
    return '';
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

  function updatePhonePrefixUI($select) {
    var code = $.trim($select.val() || '');
    var $flag = $select.closest('.nh-phone-combo').find('.nh-phone-flag');
    if ($flag.length) {
      $flag.text(code ? flagEmoji(isoForCallingCode(code)) : '');
    }
    $select.toggleClass('is-empty', !code);
  }

  function extractDialFromInput($input, $select) {
    if (!$input.length || !$select.length) {
      return;
    }
    var parsed = splitInternationalPhone($input.val());
    if (!parsed) {
      return;
    }
    if ($select.find('option[value="' + parsed.code + '"]').length) {
      $select.val(parsed.code);
    }
    if (String($input.val()) !== String(parsed.national)) {
      $input.val(parsed.national);
    }
    updatePhonePrefixUI($select);
  }

  function phoneLimits(code) {
    var map = i18n.phoneLengths || {};
    if (map[code] && map[code].length >= 2) {
      return { min: parseInt(map[code][0], 10), max: parseInt(map[code][1], 10) };
    }
    return { min: 6, max: 15 };
  }

  function applyPhoneMaxlength($combo) {
    var $select = $combo.find('select.nh-phone-code');
    var $input = $combo.find('input.input-text, input[type="tel"]');
    var raw = String($input.val() || '');
    if (raw.charAt(0) === '+' || raw.indexOf('00') === 0) {
      $input.attr('maxlength', 20);
      return;
    }
    $input.attr('maxlength', String(phoneLimits($.trim($select.val() || '')).max));
  }

  function phoneComboIsValid($combo, required) {
    var $select = $combo.find('select.nh-phone-code');
    var $input = $combo.find('input.input-text, input[type="tel"]');
    var raw = $.trim($input.val() || '');
    if (raw === '') {
      return !required;
    }
    var parsed = splitInternationalPhone(raw);
    var code = parsed ? parsed.code : $.trim($select.val() || '');
    var national = parsed ? parsed.national : raw.replace(/\D/g, '');
    if (!code || !national) {
      return false;
    }
    var limits = phoneLimits(code);
    return national.length >= limits.min && national.length <= limits.max;
  }

  function setPhoneComboState($combo, ok) {
    var $row = $combo.closest('.form-row');
    var $hint = $row.find('.nh-phone-hint');
    if (!$hint.length) {
      $hint = $('<span class="nh-phone-hint" role="status"></span>');
      $combo.after($hint);
    }
    $row.toggleClass('woocommerce-invalid woocommerce-invalid-phone', !ok);
    $row.toggleClass('woocommerce-validated', ok);
    $hint.text(ok ? '' : (i18n.phoneInvalid || 'Please enter a valid phone number.'));
  }

  function validatePhoneCombo($combo, required, show) {
    applyPhoneMaxlength($combo);
    var ok = phoneComboIsValid($combo, required);
    if (show) {
      setPhoneComboState($combo, ok);
    }
    return ok;
  }

  function comboIsRequired($combo) {
    var $row = $combo.closest('.form-row');
    if ($row.hasClass('nh-checkout-field--person-extra')) {
      return false;
    }
    return $row.hasClass('validate-required') || $combo.find('#billing_phone').length > 0;
  }

  function ensureCallingCodeFromCountry($combo) {
    var $select = $combo.find('select.nh-phone-code');
    if ($.trim($select.val() || '')) {
      return;
    }
    var iso = $('#billing_country').val();
    var map = i18n.phoneIsoCodes || {};
    if (iso && map[iso]) {
      $select.val(map[iso]);
      updatePhonePrefixUI($select);
    }
  }

  function validateAllPhones(show) {
    var ok = true;
    $('.nh-phone-combo').each(function () {
      var $combo = $(this);
      if ($combo.closest('.nh-checkout-field--hidden').length) {
        return;
      }
      if (!validatePhoneCombo($combo, comboIsRequired($combo), show)) {
        ok = false;
      }
    });
    return ok;
  }

  function hydratePhoneCombos() {
    $('.nh-phone-combo').each(function () {
      var $combo = $(this);
      var $select = $combo.find('select.nh-phone-code');
      var $input = $combo.find('input.input-text, input[type="tel"]');
      if (!$select.length || !$input.length) {
        return;
      }
      extractDialFromInput($input, $select);
      updatePhonePrefixUI($select);
      applyPhoneMaxlength($combo);
    });
  }

  function bindCallingCode() {
    $(document.body).off('change.nhPhoneCode', '.nh-phone-code');
    $(document.body).on('change.nhPhoneCode', '.nh-phone-code', function () {
      var $select = $(this);
      var $combo = $select.closest('.nh-phone-combo');
      updatePhonePrefixUI($select);
      applyPhoneMaxlength($combo);
      var $input = $combo.find('input.input-text, input[type="tel"]');
      var digits = String($input.val() || '').replace(/\D/g, '');
      var max = phoneLimits($.trim($select.val() || '')).max;
      if (digits.length > max) {
        $input.val(digits.slice(0, max));
      }
      if ($.trim($input.val() || '') !== '') {
        validatePhoneCombo($combo, comboIsRequired($combo), true);
      }
    });
    $(document.body).off('input.nhPhoneParse paste.nhPhoneParse', '.nh-phone-combo input');
    $(document.body).on('input.nhPhoneParse paste.nhPhoneParse', '.nh-phone-combo input', function () {
      var $input = $(this);
      var $combo = $input.closest('.nh-phone-combo');
      var $select = $combo.find('select.nh-phone-code');
      var raw = String($input.val() || '');
      if (raw.charAt(0) !== '+' && raw.indexOf('00') !== 0) {
        var digits = raw.replace(/\D/g, '');
        var max = phoneLimits($.trim($select.val() || '')).max;
        if (digits.length > max) {
          digits = digits.slice(0, max);
        }
        if (digits !== raw) {
          $input.val(digits);
        }
      }
      window.setTimeout(function () {
        extractDialFromInput($input, $select);
        applyPhoneMaxlength($combo);
      }, 0);
    });
    $(document.body).off('blur.nhPhoneValidate', '.nh-phone-combo input');
    $(document.body).on('blur.nhPhoneValidate', '.nh-phone-combo input', function () {
      var $combo = $(this).closest('.nh-phone-combo');
      ensureCallingCodeFromCountry($combo);
      validatePhoneCombo($combo, comboIsRequired($combo), true);
    });
    $('form.checkout').off('checkout_place_order.nhPhone');
    $('form.checkout').on('checkout_place_order.nhPhone', function () {
      $('.nh-phone-combo').each(function () {
        ensureCallingCodeFromCountry($(this));
      });
      return validateAllPhones(true);
    });
  }

  function checkoutShippingMethods() {
    var methods = {};
    $(
      'form.checkout select.shipping_method, form.checkout input[name^="shipping_method"][type="radio"]:checked, form.checkout input[name^="shipping_method"][type="hidden"]'
    ).each(function () {
      var $el = $(this);
      var name = $el.attr('name') || '';
      var match = name.match(/shipping_method\[(\d+)\]/);
      var index = match ? match[1] : ($el.attr('data-index') || '0');
      if (index === 'undefined' || index === '' || isNaN(parseInt(index, 10))) {
        index = '0';
      }
      $el.attr('data-index', index);
      $el.data('index', parseInt(index, 10));
      methods[parseInt(index, 10)] = $el.val();
    });
    return methods;
  }

  function isolateCheckoutShipping() {
    $('.nh-sc input[name^="shipping_method"], .nh-sc select[name^="shipping_method"]').each(function () {
      var $el = $(this);
      var name = String($el.attr('name') || '');
      if (name.indexOf('shipping_method') !== 0) {
        return;
      }
      $el.attr('name', name.replace(/^shipping_method/, 'nh_sc_shipping_method'));
      $el.removeClass('shipping_method');
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
      if (Object.prototype.hasOwnProperty.call(data.shipping_method, 'NaN')) {
        if (data.shipping_method[0] == null) {
          data.shipping_method[0] = data.shipping_method.NaN;
        }
        delete data.shipping_method.NaN;
      }
    }
    return data;
  }

  function isWooUpdateOrderReview(options) {
    var url = String((options && options.url) || '');
    return /update_order_review/i.test(url);
  }

  function stampShippingIndexes() {
    isolateCheckoutShipping();
    checkoutShippingMethods();
  }

  $.ajaxPrefilter(function (options) {
    if (!options || options.data == null || !isWooUpdateOrderReview(options)) {
      return;
    }
    options.data = rewriteShippingPayload(options.data);
  });

  function bindShippingTotals() {
    if (!document.body.getAttribute('data-nh-ship-capture')) {
      document.body.setAttribute('data-nh-ship-capture', '1');
      document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.name || String(t.name).indexOf('shipping_method') !== 0) {
          return;
        }
        if (!t.closest || !t.closest('form.checkout')) {
          return;
        }
        stampShippingIndexes();
      }, true);
    }
    $(document.body).off('change.nhShipTotals', 'form.checkout input.shipping_method, form.checkout select.shipping_method');
    $(document.body).on('change.nhShipTotals', 'form.checkout input.shipping_method, form.checkout select.shipping_method', function () {
      stampShippingIndexes();
      $(document.body).trigger('update_checkout', { update_shipping_method: true });
    });
  }

  function chosenPaymentId() {
    var $checked = $('input[name="payment_method"]:checked');
    if ($checked.length) {
      return String($checked.val() || '');
    }
    return String($('input[name="payment_method"]').first().val() || '');
  }

  function paymentIdIsSnippet(id) {
    id = String(id || '').toLowerCase();
    if (!id) {
      return false;
    }
    return /svea.?checkout|sveacheckout|^sco$|^kco$|kustom_checkout|klarna_checkout/.test(id);
  }

  function snippetCheckoutPresent() {
    return !!(
      document.querySelector([
        '#klarna-checkout-container',
        '#kco-wrapper',
        '#kco-iframe',
        '#svea-checkout',
        '#svea_checkout_iframe',
        '#svea-checkout-container',
        '#svea-checkout-wrapper',
        '#kustom-checkout-container',
        '.svea-checkout',
        '.kco-iframe',
        '.sco-checkout',
        'iframe[src*="checkout.klarna"]',
        'iframe[src*="kustom."]',
        'iframe[src*="svea.com"]',
        'iframe[src*="checkout.svea"]',
        'iframe[src*="sveacheckout"]'
      ].join(','))
    );
  }

  function snippetOtherPayment() {
    var $known = $(
      '#klarna-checkout-select-other, #svea-checkout-select-other, #sco-select-other, ' +
      '.kco-select-another-method a, .kco-change-payment-method, a.sco-change-payment-method, ' +
      '[id*="select-other"], [class*="select-other-payment"], [class*="change-payment-method"]'
    ).not('.nh-checkout-other-payment');
    if ($known.length) {
      return $known.first();
    }
    return $('.nh-checkout-layout__aside a, .nh-checkout-layout__aside button, #payment a, #payment button, .woocommerce-checkout-payment a, .woocommerce-checkout-payment button').filter(function () {
      var t = $(this).text().replace(/\s+/g, ' ').trim().toLowerCase();
      return t === 'other payment method' ||
        t === 'other payment options' ||
        t.indexOf('annet betalings') !== -1 ||
        t.indexOf('andre betalings') !== -1 ||
        t.indexOf('annat betals') !== -1 ||
        t.indexOf('anden betalings') !== -1 ||
        t.indexOf('andere zahlung') !== -1 ||
        t.indexOf('muu maksutapa') !== -1 ||
        t.indexOf('kitas mok') !== -1;
    }).not('.nh-checkout-other-payment').first();
  }

  var snippetExtras = [
    '.nh-checkout-secure',
    '#billing_customer_type_field',
    '.form-row.nh-checkout-type',
    '.nh-notes',
    '.woocommerce-additional-fields'
  ].join(',');

  function isSnippetMode() {
    if (snippetCheckoutPresent() || paymentIdIsSnippet(chosenPaymentId())) {
      return true;
    }
    return document.body.classList.contains('nh-checkout--snippet') && !chosenPaymentId();
  }

  function syncSnippetCheckout() {
    var on = isSnippetMode();
    $('body').toggleClass('nh-checkout--snippet', on);
    $('form.checkout').toggleClass('nh-checkout--snippet', on);

    var $extras = $(snippetExtras);
    if (on) {
      $extras.attr('hidden', 'hidden');
    } else {
      $extras.removeAttr('hidden');
    }

    var $ours = $('.nh-checkout-other-payment');
    var $plugin = snippetOtherPayment();
    if ($ours.length) {
      $ours.each(function () {
        if (!$.trim($(this).text())) {
          $(this).text(i18n.otherPayment || 'Other payment method');
        }
      });
      if (on) {
        $ours.removeAttr('hidden');
        $plugin.addClass('nh-checkout-other-payment-src').attr('hidden', 'hidden');
      } else {
        $ours.attr('hidden', 'hidden');
        $plugin.removeClass('nh-checkout-other-payment-src').removeAttr('hidden');
      }
    } else if (on && $plugin.length) {
      $plugin.addClass('nh-checkout-other-payment-btn').removeAttr('hidden');
    }
  }

  function forcePairClasses() {
    var starts = [
      '#billing_email_field',
      '#billing_contact_email_field',
      '#billing_first_name_field',
      '#billing_country_field',
      '#billing_city_field'
    ];
    var ends = [
      '#billing_phone_field',
      '#billing_contact_phone_field',
      '#billing_last_name_field',
      '#billing_postcode_field',
      '#billing_state_field'
    ];
    $(starts.join(',')).removeClass('form-row-wide').addClass('form-row-first nh-checkout-pair-start');
    $(ends.join(',')).removeClass('form-row-wide').addClass('form-row-last nh-checkout-pair-end');
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
    $city.toggleClass('form-row-first nh-checkout-pair-start', !stateHidden);
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
      el.style.setProperty('max-width', '100%', 'important');
      el.style.setProperty('border', '0', 'important');
      el.style.setProperty('border-width', '0', 'important');
      el.style.setProperty('outline', '0', 'important');
      el.style.setProperty('box-shadow', 'none', 'important');
      el.style.setProperty('background', 'transparent', 'important');
      el.style.setProperty('border-radius', '0', 'important');
    });
    if (review) {
      review.style.setProperty('width', '100%', 'important');
      review.style.setProperty('padding', '0', 'important');
      review.style.setProperty('margin', '0', 'important');
    }
    var heading = document.getElementById('order_review_heading');
    if (heading) {
      heading.style.setProperty('padding', '0 0 0.75rem', 'important');
      heading.style.setProperty('margin', '0', 'important');
      if (wide) {
        heading.style.setProperty('width', '100%', 'important');
        heading.style.setProperty('position', 'static', 'important');
      } else {
        heading.style.removeProperty('width');
      }
    }
    if (table) {
      table.style.setProperty('display', 'table', 'important');
      table.style.setProperty('width', '100%', 'important');
      table.style.setProperty('max-width', '100%', 'important');
      table.style.setProperty('float', 'none', 'important');
      table.style.setProperty('table-layout', 'auto', 'important');
      table.style.removeProperty('zoom');
    }
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

  function syncSummaryTotal() {
    var $src = $('#order_review .order-total td').first();
    var $dest = $('.nh-checkout-summary-toggle__amount');
    if ($src.length && $dest.length) {
      $dest.html(amountHtmlFromTotalCell($src));
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

  function refreshCheckoutChrome() {
    keepPhoneCodeNative();
    hydratePhoneCombos();
    stampShippingIndexes();
    bindSummaryToggle();
    syncSummaryTotal();
    enhanceNotes();
    enhancePaymentCards();
    lockSummaryLayout();
    forcePairClasses();
    syncPairedAddressRows();
    syncSnippetCheckout();
  }

  function fillCheckoutField(selector, value) {
    var $el = $(selector);
    if (!$el.length || value == null) {
      return false;
    }
    value = String(value);
    if (value === '' || String($el.val() || '') === value) {
      return false;
    }
    $el.val(value);
    return true;
  }

  function shipToDifferentAddress() {
    return $('#ship-to-different-address-checkbox').is(':checked');
  }

  var iframeAddrTimer = null;
  function applyIframeAddress(parts) {
    var changed = false;
    var copyShip = !shipToDifferentAddress();
    parts = parts || {};

    if (parts.postcode) {
      changed = fillCheckoutField('#billing_postcode', parts.postcode) || changed;
      if (copyShip) {
        changed = fillCheckoutField('#shipping_postcode', parts.postcode) || changed;
      }
    }
    if (parts.country && String(parts.country).length === 2) {
      changed = fillCheckoutField('#billing_country', String(parts.country).toUpperCase()) || changed;
      if (copyShip) {
        changed = fillCheckoutField('#shipping_country', String(parts.country).toUpperCase()) || changed;
      }
    }
    if (parts.city) {
      changed = fillCheckoutField('#billing_city', parts.city) || changed;
      if (copyShip) {
        changed = fillCheckoutField('#shipping_city', parts.city) || changed;
      }
    }
    if (!changed) {
      return;
    }
    window.clearTimeout(iframeAddrTimer);
    iframeAddrTimer = window.setTimeout(function () {
      $(document.body).trigger('update_checkout');
    }, 350);
  }

  function observeValue(data) {
    if (data == null) {
      return '';
    }
    if (typeof data === 'string' || typeof data === 'number') {
      return String(data);
    }
    if (typeof data.value === 'string' || typeof data.value === 'number') {
      return String(data.value);
    }
    if (typeof data.postalCode === 'string') {
      return data.postalCode;
    }
    if (typeof data.postal_code === 'string') {
      return data.postal_code;
    }
    return '';
  }

  var sveaAddressBound = false;
  function tryBindSveaAddress() {
    var api = window.scoApi;
    if (sveaAddressBound || !api || typeof api.observe !== 'function') {
      return;
    }
    sveaAddressBound = true;
    api.observe('identity.postalCode', function (data) {
      var postcode = observeValue(data);
      if (postcode) {
        applyIframeAddress({ postcode: postcode });
      }
    });
    api.observe('identity.city', function (data) {
      var city = observeValue(data);
      if (city) {
        applyIframeAddress({ city: city });
      }
    });
    api.observe('identity.countryCode', function (data) {
      var country = observeValue(data);
      if (country) {
        applyIframeAddress({ country: country });
      }
    });
  }

  function bindIframeShippingAddress() {
    if (document.body.getAttribute('data-nh-iframe-addr') === '1') {
      return;
    }
    document.body.setAttribute('data-nh-iframe-addr', '1');

    $(document.body).on(
      'change.nhIframeAddr input.nhIframeAddr',
      '#billing_postcode, #billing_country, #billing_city, #billing_address_1',
      function () {
        var dest = {
          billing_postcode: '#shipping_postcode',
          billing_country: '#shipping_country',
          billing_city: '#shipping_city',
          billing_address_1: '#shipping_address_1'
        }[this.id];
        if (!dest || shipToDifferentAddress()) {
          return;
        }
        fillCheckoutField(dest, $(this).val());
      }
    );

    tryBindSveaAddress();
    var tries = 0;
    var poll = window.setInterval(function () {
      tries += 1;
      tryBindSveaAddress();
      if (sveaAddressBound || tries >= 45) {
        window.clearInterval(poll);
      }
    }, 1000);
  }

  function watchSnippetCheckout() {
    if (document.body.getAttribute('data-nh-snippet-watch') === '1') {
      return;
    }
    document.body.setAttribute('data-nh-snippet-watch', '1');
    if (typeof MutationObserver !== 'function') {
      return;
    }
    var timer = null;
    var obs = new MutationObserver(function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        syncSnippetCheckout();
        tryBindSveaAddress();
      }, 50);
    });
    obs.observe(document.body, { childList: true, subtree: true });
  }

  function boot() {
    stampShippingIndexes();
    bindCustomerType();
    bindCallingCode();
    bindShippingTotals();
    bindIframeShippingAddress();
    refreshCheckoutChrome();
    watchSnippetCheckout();
  }

  $(document).on('click', '.nh-checkout-other-payment', function (e) {
    e.preventDefault();
    var $plugin = snippetOtherPayment();
    if ($plugin.length) {
      var el = $plugin.get(0);
      if (el && typeof el.click === 'function') {
        el.click();
      } else {
        $plugin.trigger('click');
      }
      return;
    }
    var $fallback = $('input[name="payment_method"]').filter(function () {
      return !paymentIdIsSnippet(this.value);
    }).first();
    if ($fallback.length) {
      $fallback.prop('checked', true).trigger('click');
      $(document.body).trigger('update_checkout');
    }
  });

  stampShippingIndexes();

  $(boot);
  $(window).on('resize.nhCheckout', lockSummaryLayout);
  $(document.body).on('init_checkout', boot);
  $(document.body).on('updated_checkout', function () {
    refreshCheckoutChrome();
  });
  $(document.body).on('payment_method_selected', function () {
    enhancePaymentCards();
    syncSnippetCheckout();
    window.setTimeout(syncSnippetCheckout, 300);
  });
  $(document.body).on('country_to_state_changing country_to_state_changed', function () {
    window.setTimeout(function () {
      orderBillingFields();
      forcePairClasses();
      syncPairedAddressRows();
      lockSummaryLayout();
    }, 0);
  });
})(jQuery);
