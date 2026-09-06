/**
 * Classic checkout: private/business toggle, field order, summary layout lock.
 */
(function ($) {
  'use strict';

  var i18n = window.nhCheckoutUx || {};
  var paymentChosenByCustomer = false;
  var ignoreAutoPaymentClick = false;
  var allowSnippetGatewayReload = false;
  var snippetReloadTimer = null;
  var backReloadTimer = null;

  function syncKcoPrevent() {
    if (!window.kco_wc) {
      return;
    }
    window.kco_wc.preventPaymentMethodChange = checkoutStep() !== 'payment' || ignoreAutoPaymentClick || !allowSnippetGatewayReload;
  }

  function unblockCheckout() {
    var $form = $('form.checkout');
    if ($form.length && $form.data('blockUI.isBlocked')) {
      $form.unblock();
    }
    $('.blockUI.blockOverlay, .blockUI.blockMsg').remove();
  }

  function iframeMarkupPresent() {
    var wrap = document.getElementById('nh-checkout-iframe');
    return !!(wrap && wrap.children && wrap.children.length);
  }

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

  function isSveaRefreshSnippet(options) {
    var url = String((options && options.url) || '');
    return /refresh_sco_snippet/i.test(url);
  }

  var lastWrittenZip = '';

  function ensureBillingPostcode(data, zip) {
    zip = usablePostcode(zip);
    if (!zip) {
      return data;
    }
    if (typeof data === 'string') {
      if (/billing_postcode=/.test(data)) {
        return data.replace(/billing_postcode=[^&]*/g, 'billing_postcode=' + encodeURIComponent(zip));
      }
      return data + (data ? '&' : '') + 'billing_postcode=' + encodeURIComponent(zip);
    }
    if (data && typeof data === 'object') {
      data.billing_postcode = zip;
      if (typeof data.post_data === 'string') {
        data.post_data = ensureBillingPostcode(data.post_data, zip);
      }
    }
    return data;
  }

  function isSnippetCheckoutPage() {
    if (checkoutStep() !== 'payment') {
      return false;
    }
    return paymentIdIsSnippet(chosenPaymentId()) || snippetCheckoutPresent();
  }

  function stampShippingIndexes() {
    isolateCheckoutShipping();
    checkoutShippingMethods();
  }

  $.ajaxPrefilter(function (options) {
    if (!options || options.data == null) {
      return;
    }
    if (isWooUpdateOrderReview(options)) {
      options.data = rewriteShippingPayload(options.data);
    }
    if (isSveaRefreshSnippet(options) && lastWrittenZip) {
      options.data = ensureBillingPostcode(options.data, lastWrittenZip);
    }
  });

  function checkoutStep() {
    var $input = $('#nh_checkout_step');
    if ($input.length) {
      return $input.val() === 'payment' ? 'payment' : 'details';
    }
    return i18n.checkoutStep === 'payment' ? 'payment' : 'details';
  }

  function shouldKeepPaymentSelection() {
    if (checkoutStep() !== 'payment') {
      return false;
    }
    if (paymentChosenByCustomer) {
      return true;
    }
    if (i18n.chosenPayment) {
      return true;
    }
    if (i18n.snippetCheckout) {
      return true;
    }
    return false;
  }

  function postDataFlag(data, name, onValues) {
    if (typeof data === 'string') {
      var re = new RegExp('(?:^|&)' + name + '=(' + onValues.join('|') + ')(?:&|$)', 'i');
      return re.test(data);
    }
    if (!data || typeof data !== 'object') {
      return false;
    }
    var val = data[name];
    return val === true || val === 1 || val === '1' || onValues.indexOf(String(val).toLowerCase()) !== -1;
  }

  $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
    if (!options || !options.url) {
      return;
    }
    var url = String(options.url);
    var isSveaChange = /sco_change_payment_method/i.test(url);
    var isKcoChange = /kco_wc_change_payment_method/i.test(url);
    if (!isSveaChange && !isKcoChange) {
      return;
    }

    var turningOn = isSveaChange
      ? postDataFlag(options.data, 'svea', ['true', '1'])
      : postDataFlag(options.data, 'kco', ['true', '1']);

    if (!turningOn) {
      return;
    }

    if (iframeMarkupPresent()) {
      jqXHR.abort();
      return;
    }

    // Woo auto-selects the first gateway after Next. Abort that so the iframe
    // does not boot until the customer clicks Svea/Kustom. When they have
    // clicked, let the plugin AJAX finish — it reloads and mounts the iframe.
    if (!allowSnippetGatewayReload || checkoutStep() !== 'payment') {
      jqXHR.abort();
      window.setTimeout(unblockCheckout, 0);
    }
  });

  $(document).on('ajaxComplete.nhSnippetUnblock', function (e, xhr, settings) {
    var url = settings && settings.url ? String(settings.url) : '';
    if (!/sco_change_payment_method|kco_wc_change_payment_method/i.test(url)) {
      return;
    }
    if (allowSnippetGatewayReload) {
      return;
    }
    unblockCheckout();
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
      if (paymentIdIsSnippet(chosenPaymentId())) {
        return;
      }
      $(document.body).trigger('update_checkout', { update_shipping_method: true });
    });
  }

  function chosenPaymentId() {
    var $checked = $('input[name="payment_method"]:checked').not(':disabled');
    if ($checked.length) {
      return String($checked.val() || '');
    }
    return '';
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
    if (iframeMarkupPresent()) {
      return true;
    }
    if (checkoutStep() !== 'payment') {
      return false;
    }
    if (paymentIdIsSnippet(chosenPaymentId())) {
      return true;
    }
    return !!(i18n.snippetCheckout && snippetCheckoutPresent());
  }

  function syncSnippetCheckout() {
    var on = isSnippetMode();
    var method = chosenPaymentId().toLowerCase();
    var hasMethod = method !== '' || on;
    $('body').toggleClass('nh-checkout--snippet', on);
    $('body').toggleClass('nh-checkout--has-method', hasMethod);
    $('form.checkout').toggleClass('nh-checkout--snippet', on);
    $('form.checkout').toggleClass('nh-checkout--has-method', hasMethod);
    $('form.checkout').toggleClass(
      'wc-svea-checkout-page svea-checkout',
      on && (/svea/.test(method) || method === 'sco' || i18n.chosenPayment === 'svea_checkout')
    );
    $('form.checkout').toggleClass(
      'kco-checkout',
      on && (/kco|kustom|klarna/.test(method) || i18n.chosenPayment === 'kco')
    );

    if (iframeMarkupPresent()) {
      $('#nh-checkout-iframe').show();
    } else {
      $('#nh-checkout-iframe').toggle(false);
    }

    var $ours = $('.nh-checkout-other-payment');
    $ours.attr('hidden', 'hidden');
    snippetOtherPayment().addClass('nh-checkout-other-payment-src').attr('hidden', 'hidden');

    keepShipToSameAddress();
  }

  function keepShipToSameAddress() {
    var $cb = $('#ship-to-different-address-checkbox');
    if ($cb.length && $cb.prop('checked')) {
      $cb.prop('checked', false);
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

  function shippingLabelHtml() {
    var $row = $('#order_review tr.woocommerce-shipping-totals, #order_review tr.shipping').first();
    if (!$row.length) {
      return '';
    }
    var $checked = $row.find('input.shipping_method:checked, input.shipping_method[type="hidden"]').first();
    var $amount = $();
    if ($checked.length) {
      $amount = $checked.closest('li, td').find('.woocommerce-Price-amount').first();
    }
    if (!$amount.length) {
      $amount = $row.find('.woocommerce-Price-amount').first();
    }
    if (!$amount.length) {
      return '';
    }
    var template = i18n.inclShipping || 'Shipping: %s';
    return template.replace('%s', $amount.prop('outerHTML'));
  }

  function syncSummaryTotal() {
    var $src = $('#order_review .order-total td').first();
    var $dest = $('.nh-checkout-summary-toggle__amount');
    if ($src.length && $dest.length) {
      $dest.html(amountHtmlFromTotalCell($src));
    }
    var $ship = $('.nh-checkout-summary-toggle__shipping');
    if ($ship.length) {
      $ship.html(shippingLabelHtml());
    }
  }

  function bindSummaryToggle() {
    var $summary = $('.nh-checkout-summary');
    if (!$summary.length) {
      return;
    }

    if (document.body.getAttribute('data-nh-summary-init') !== '1') {
      document.body.setAttribute('data-nh-summary-init', '1');
      $summary.addClass('is-open');
    }

    var $toggle = $summary.find('.nh-checkout-summary-toggle');
    $toggle.attr('aria-expanded', $summary.hasClass('is-open') ? 'true' : 'false');
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
  }

  function applyCheckoutStep() {
    var step = checkoutStep();
    var onPayment = step === 'payment';
    $('body')
      .toggleClass('nh-checkout--step-payment', onPayment)
      .toggleClass('nh-checkout--step-details', !onPayment);
    $('form.checkout')
      .toggleClass('nh-checkout--step-payment', onPayment)
      .toggleClass('nh-checkout--step-details', !onPayment);

    var $radios = $('input[name="payment_method"]');
    if (!onPayment) {
      paymentChosenByCustomer = false;
      $radios.prop('checked', false).prop('disabled', true);
      if (!iframeMarkupPresent()) {
        $('body, form.checkout').removeClass(
          'nh-checkout--snippet nh-checkout--has-method wc-svea-checkout-page svea-checkout kco-checkout'
        );
      }
    } else {
      $radios.prop('disabled', false);
      if (!shouldKeepPaymentSelection() && !iframeMarkupPresent()) {
        $radios.prop('checked', false);
      }
    }

    enhancePaymentCards();
    syncSnippetCheckout();
    syncKcoPrevent();
  }

  function validateDetailsStep() {
    var ok = true;
    var $first = $();
    $('#customer_details p.validate-required:visible').not('.nh-checkout-field--hidden').each(function () {
      var $row = $(this);
      var $inputs = $row.find('input, select, textarea').filter(':visible').not('[type=hidden]').not(':disabled');
      if (!$inputs.length) {
        return;
      }
      var type = String($inputs.first().attr('type') || '').toLowerCase();
      var empty = false;
      if (type === 'radio') {
        empty = !$row.find('input[type=radio]:checked').length;
      } else if (type === 'checkbox') {
        empty = !$inputs.first().is(':checked');
      } else {
        empty = $.trim($inputs.first().val() || '') === '';
      }
      if (empty) {
        $row.addClass('woocommerce-invalid woocommerce-invalid-required-field');
        ok = false;
        if (!$first.length) {
          $first = $inputs.first();
        }
      }
    });
    if (!validateAllPhones(true)) {
      ok = false;
      if (!$first.length) {
        $first = $('.woocommerce-invalid-phone:visible').find('input').first();
      }
    }
    if (!ok && $first.length) {
      $first.trigger('focus');
      if ($first[0].scrollIntoView) {
        $first[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }
    return ok;
  }

  function goToPayment() {
    if (!validateDetailsStep()) {
      return;
    }
    paymentChosenByCustomer = false;
    ignoreAutoPaymentClick = true;
    allowSnippetGatewayReload = false;
    i18n.chosenPayment = '';
    i18n.snippetCheckout = false;
    $('input[name="payment_method"]').prop('checked', false).prop('disabled', true);
    $('#nh_checkout_step').val('payment');
    i18n.checkoutStep = 'payment';
    $('body, form.checkout').removeClass('nh-checkout--step-details').addClass('nh-checkout--step-payment');
    $(document.body).trigger('update_checkout');
  }

  function goBackToDetails() {
    paymentChosenByCustomer = false;
    ignoreAutoPaymentClick = false;
    allowSnippetGatewayReload = false;
    i18n.chosenPayment = '';
    i18n.snippetCheckout = false;
    $('input[name="payment_method"]').prop('checked', false).prop('disabled', true);
    $('#nh_checkout_step').val('details');
    i18n.checkoutStep = 'details';
    var hadIframe = iframeMarkupPresent() || snippetCheckoutPresent();
    applyCheckoutStep();
    $(document.body).trigger('update_checkout');
    if (hadIframe) {
      window.clearTimeout(backReloadTimer);
      $(document.body).one('updated_checkout.nhBack', function () {
        window.clearTimeout(backReloadTimer);
        window.location.reload();
      });
      backReloadTimer = window.setTimeout(function () {
        window.location.reload();
      }, 1500);
    }
  }

  function reloadForSnippetGateway() {
    if (iframeMarkupPresent()) {
      return;
    }
    allowSnippetGatewayReload = true;
    syncKcoPrevent();
    $('#nh_checkout_step').val('payment');
    i18n.checkoutStep = 'payment';
    window.clearTimeout(snippetReloadTimer);
    $(document.body).off('updated_checkout.nhSnippetPrefill');
    $(document.body).one('updated_checkout.nhSnippetPrefill', function () {
      window.clearTimeout(snippetReloadTimer);
      window.location.reload();
    });
    $(document.body).trigger('update_checkout');
    snippetReloadTimer = window.setTimeout(function () {
      window.location.reload();
    }, 2000);
  }

  function bindCheckoutSteps() {
    if (document.body.getAttribute('data-nh-checkout-steps') === '1') {
      return;
    }
    document.body.setAttribute('data-nh-checkout-steps', '1');
    syncKcoPrevent();

    $(document.body).on('click.nhCheckoutNext', '#nh-checkout-next, .nh-checkout-next', function (e) {
      e.preventDefault();
      goToPayment();
    });
    $(document.body).on('click.nhCheckoutBack', '#nh-checkout-back, .nh-checkout-back', function (e) {
      e.preventDefault();
      goBackToDetails();
    });
    $(document.body).on('click.nhPayMethod change.nhPayMethod', 'input[name="payment_method"]', function () {
      if (checkoutStep() !== 'payment' || ignoreAutoPaymentClick) {
        return;
      }
      paymentChosenByCustomer = true;
      var id = String(this.value || '');
      i18n.chosenPayment = id;
      applyCheckoutStep();
      if (paymentIdIsSnippet(id)) {
        reloadForSnippetGateway();
        return;
      }
      allowSnippetGatewayReload = false;
      syncKcoPrevent();
      if (snippetCheckoutPresent() || iframeMarkupPresent()) {
        $(document.body).one('updated_checkout.nhLeaveIframe', function () {
          window.location.reload();
        });
        $(document.body).trigger('update_checkout');
      }
    });

    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || !form.classList || !form.classList.contains('checkout')) {
        return;
      }
      if (checkoutStep() !== 'payment') {
        e.preventDefault();
        e.stopPropagation();
        goToPayment();
        return;
      }
      if (!$('input[name="payment_method"]:checked').not(':disabled').length) {
        e.preventDefault();
        e.stopPropagation();
        window.alert(i18n.selectPayment || 'Please choose a payment method.');
      }
    }, true);

    if (i18n.chosenPayment || i18n.snippetCheckout || iframeMarkupPresent()) {
      paymentChosenByCustomer = true;
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
    stampShippingIndexes();
    bindSummaryToggle();
    syncSummaryTotal();
    lockSummaryLayout();
    keepPhoneCodeNative();
    hydratePhoneCombos();
    enhanceNotes();
    applyCheckoutStep();
    forcePairClasses();
    syncPairedAddressRows();
  }

  var postcodeShipTimer = null;
  var lastShipDest = '';

  function shipToDifferentAddress() {
    return $('#ship-to-different-address-checkbox').is(':checked');
  }

  function destinationKey() {
    var country = String($('#billing_country').val() || '').trim();
    var postcode = String($('#billing_postcode').val() || '').trim();
    var sCountry = country;
    var sPostcode = postcode;
    if (shipToDifferentAddress()) {
      sCountry = String($('#shipping_country').val() || country).trim();
      sPostcode = String($('#shipping_postcode').val() || '').trim();
    }
    return [country, postcode, sCountry, sPostcode].join('|');
  }

  function flushPostcodeShipping() {
    var key = destinationKey();
    var postcode = key.split('|')[1];
    if (!postcode) {
      lastShipDest = key;
      return;
    }
    if (key === lastShipDest) {
      return;
    }
    lastShipDest = key;
    stampShippingIndexes();
    $(document.body).trigger('update_checkout', { update_shipping_method: true });
  }

  function schedulePostcodeShipping() {
    window.clearTimeout(postcodeShipTimer);
    postcodeShipTimer = window.setTimeout(flushPostcodeShipping, 400);
  }

  function bindPostcodeShippingUpdate() {
    if (document.body.getAttribute('data-nh-postcode-ship') === '1') {
      return;
    }
    document.body.setAttribute('data-nh-postcode-ship', '1');

    var selector = [
      '#billing_postcode',
      '#shipping_postcode',
      '#billing_country',
      '#shipping_country'
    ].join(', ');

    $(document.body).on(
      'change.nhPostcodeShip input.nhPostcodeShip keyup.nhPostcodeShip paste.nhPostcodeShip',
      selector,
      schedulePostcodeShipping
    );
  }

  function usablePostcode(value) {
    value = String(value == null ? '' : value).trim();
    if (!value || value === '••••' || /^•+$/.test(value)) {
      return '';
    }
    return value;
  }

  function iso2Country(country) {
    country = String(country || '').toUpperCase();
    if (country.length === 2) {
      return country;
    }
    var map = {
      SWE: 'SE', NOR: 'NO', DNK: 'DK', FIN: 'FI', DEU: 'DE',
      AUT: 'AT', NLD: 'NL', BEL: 'BE', LTU: 'LT', LVA: 'LV',
      EST: 'EE', POL: 'PL', FRA: 'FR', GBR: 'GB', IRL: 'IE'
    };
    return map[country] || '';
  }

  function writeWooPostcode(postcode, country) {
    postcode = usablePostcode(postcode);
    if (!postcode) {
      return false;
    }
    lastWrittenZip = postcode;
    document.querySelectorAll('input#billing_postcode, input[name="billing_postcode"]').forEach(function (el) {
      el.value = postcode;
    });
    if (!shipToDifferentAddress()) {
      document.querySelectorAll('input#shipping_postcode, input[name="shipping_postcode"]').forEach(function (el) {
        el.value = postcode;
      });
    }
    country = iso2Country(country);
    if (country) {
      document.querySelectorAll('#billing_country, select[name="billing_country"]').forEach(function (el) {
        if (String(el.value || '').toUpperCase() !== country) {
          el.value = country;
        }
      });
      if (!shipToDifferentAddress()) {
        document.querySelectorAll('#shipping_country, select[name="shipping_country"]').forEach(function (el) {
          if (String(el.value || '').toUpperCase() !== country) {
            el.value = country;
          }
        });
      }
    }
    return true;
  }

  function extractSveaZip(data) {
    if (data == null) {
      return '';
    }
    if (typeof data === 'string' || typeof data === 'number') {
      return usablePostcode(data);
    }
    if (typeof data === 'object') {
      return usablePostcode(data.value || data.postalCode || data.postal_code || '');
    }
    return '';
  }

  function applySnippetZipAjax(postcode, country) {
    stampShippingIndexes();
    postcode = usablePostcode(postcode);
    if (!postcode) {
      return;
    }
    var params = window.wc_checkout_params || {};
    var url = String(params.wc_ajax_url || '');
    var nonce = i18n.applyZipNonce || '';
    if (!url || !nonce) {
      return;
    }
    $.ajax({
      type: 'POST',
      url: url.replace('%%endpoint%%', 'nh_snippet_apply_zip'),
      dataType: 'json',
      data: {
        security: nonce,
        postcode: postcode,
        billing_postcode: postcode,
        country: iso2Country(country) || String($('#billing_country').val() || '')
      },
      success: function (res) {
        var fragments = res && res.data && res.data.fragments;
        if (!fragments) {
          return;
        }
        $.each(fragments, function (sel, html) {
          var $el = $(sel);
          if ($el.length) {
            $el.html(html);
          }
        });
        stampShippingIndexes();
        syncSummaryTotal();
      }
    });
  }

  var iframeZipTimer = null;
  var lastIframeZip = '';
  function onIframeZip(postcode, country) {
    postcode = usablePostcode(postcode);
    if (!postcode) {
      return;
    }
    writeWooPostcode(postcode, country);
    var key = String(iso2Country(country) || $('#billing_country').val() || '') + '|' + postcode;
    window.clearTimeout(iframeZipTimer);
    iframeZipTimer = window.setTimeout(function () {
      if (key === lastIframeZip) {
        return;
      }
      lastIframeZip = key;
      applySnippetZipAjax(postcode, country);
    }, 250);
  }

  function bindSveaZip() {
    if (window._nhSveaZipBound) {
      return true;
    }
    var api = window.scoApi;
    if (!api || typeof api.observeEvent !== 'function') {
      return false;
    }
    window._nhSveaZipBound = true;
    api.observeEvent('identity.postalCode', function (data) {
      onIframeZip(extractSveaZip(data), $('#billing_country').val());
    });
    return true;
  }

  function watchHiddenSnippetPostcode() {
    if (document.body.getAttribute('data-nh-hidden-zip') === '1') {
      return;
    }
    document.body.setAttribute('data-nh-hidden-zip', '1');
    var seen = '';
    window.setInterval(function () {
      var el = document.getElementById('billing_postcode');
      if (!el) {
        return;
      }
      var zip = usablePostcode(el.value);
      if (zip && zip !== seen) {
        seen = zip;
        onIframeZip(zip, $('#billing_country').val());
      }
    }, 400);
  }

  function bindIframeZipShipping() {
    if (!document.querySelector('.wc-svea-checkout-page, #svea-checkout-iframe-container, form.svea-checkout')) {
      return;
    }
    if (document.body.getAttribute('data-nh-iframe-zip') === '1') {
      return;
    }
    document.body.setAttribute('data-nh-iframe-zip', '1');

    function attachSveaAfterNative() {
      window.setTimeout(bindSveaZip, 50);
    }
    document.addEventListener('checkoutReady', attachSveaAfterNative);
    if (window.scoApi && typeof window.scoApi.observeEvent === 'function') {
      attachSveaAfterNative();
    }

    watchHiddenSnippetPostcode();
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
      timer = window.setTimeout(syncSnippetCheckout, 50);
    });
    obs.observe(document.body, { childList: true, subtree: true });
  }

  function boot() {
    syncKcoPrevent();
    stampShippingIndexes();
    bindShippingTotals();
    watchSnippetCheckout();
    bindCustomerType();
    bindCallingCode();
    bindPostcodeShippingUpdate();
    bindIframeZipShipping();
    bindCheckoutSteps();
    refreshCheckoutChrome();
    if (iframeMarkupPresent() && typeof $.fn.sveaCheckout === 'function') {
      $('.wc-svea-checkout-page').sveaCheckout();
    }
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
    window.setTimeout(function () {
      applyCheckoutStep();
      unblockCheckout();
    }, 0);
    window.setTimeout(function () {
      applyCheckoutStep();
      ignoreAutoPaymentClick = false;
    }, 80);
  });
  $(document.body).on('payment_method_selected', function () {
    if (ignoreAutoPaymentClick || checkoutStep() !== 'payment') {
      applyCheckoutStep();
      return;
    }
    syncSnippetCheckout();
    enhancePaymentCards();
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
