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
  var FOCUS_KEY = 'nh_checkout_focus';
  var TERMS_KEY = 'nh_checkout_terms';
  var DRAFT_KEY = 'nh_checkout_draft';
  var termsAccepted = false;
  var draftTimer = null;

  function syncKcoPrevent() {
    if (!window.kco_wc) {
      return;
    }
    window.kco_wc.preventPaymentMethodChange = !snippetReady() || ignoreAutoPaymentClick || !allowSnippetGatewayReload;
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
        'nh_section_delivery_field',
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
      'nh_section_delivery_field',
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

  function phoneLimits(code) {
    var map = i18n.phoneLengths || {};
    if (map[code] && map[code].length >= 2) {
      return { min: parseInt(map[code][0], 10), max: parseInt(map[code][1], 10) };
    }
    return { min: 6, max: 15 };
  }

  function nationalValidForCode(code, national) {
    code = String(code || '').replace(/\D/g, '');
    national = String(national || '').replace(/\D/g, '');
    if (!code || !national) {
      return false;
    }
    var limits = phoneLimits(code);
    return national.length >= limits.min && national.length <= limits.max;
  }

  function matchCallingCode(digits) {
    digits = String(digits || '').replace(/\D/g, '');
    if (!digits) {
      return null;
    }
    var codes = longestCallingCodes();
    for (var i = 0; i < codes.length; i++) {
      if (digits.indexOf(codes[i]) !== 0) {
        continue;
      }
      var national = digits.slice(codes[i].length);
      if (nationalValidForCode(codes[i], national)) {
        return { code: codes[i], national: national };
      }
    }
    return null;
  }

  function parsePhoneInput(value, selectedCode) {
    var raw = String(value || '').trim();
    if (!raw) {
      return null;
    }
    if (raw.indexOf('00') === 0) {
      raw = '+' + raw.slice(2);
    }
    var plus = raw.charAt(0) === '+';
    var digits = raw.replace(/\D/g, '');
    selectedCode = String(selectedCode || '').replace(/\D/g, '');
    if (!digits) {
      return { code: selectedCode, national: '' };
    }
    var matched = matchCallingCode(digits);
    if (plus) {
      return matched || { code: selectedCode, national: digits };
    }
    if (selectedCode && nationalValidForCode(selectedCode, digits)) {
      return { code: selectedCode, national: digits };
    }
    if (matched) {
      return matched;
    }
    if (selectedCode && digits.indexOf(selectedCode) === 0) {
      var rest = digits.slice(selectedCode.length);
      if (nationalValidForCode(selectedCode, rest)) {
        return { code: selectedCode, national: rest };
      }
    }
    return { code: selectedCode, national: digits };
  }

  function updatePhonePrefixUI($select) {
    var code = $.trim($select.val() || '');
    var $flag = $select.closest('.nh-phone-combo').find('.nh-phone-flag');
    if ($flag.length) {
      $flag.text(code ? flagEmoji(isoForCallingCode(code)) : '');
    }
    $select.toggleClass('is-empty', !code);
  }

  function applyParsedPhone($input, $select, parsed) {
    if (!parsed) {
      return;
    }
    if (parsed.code && $select.find('option[value="' + parsed.code + '"]').length) {
      $select.val(parsed.code);
    }
    if (parsed.national !== undefined && String($input.val()) !== String(parsed.national)) {
      $input.val(parsed.national);
    }
    updatePhonePrefixUI($select);
  }

  function extractDialFromInput($input, $select) {
    if (!$input.length || !$select.length) {
      return;
    }
    var parsed = parsePhoneInput($input.val(), $select.val());
    if (!parsed || !parsed.code || !nationalValidForCode(parsed.code, parsed.national)) {
      return;
    }
    applyParsedPhone($input, $select, parsed);
  }

  function applyPhoneMaxlength($combo) {
    var $input = $combo.find('input.input-text, input[type="tel"]');
    $input.attr('maxlength', 16);
  }

  function phoneComboIsValid($combo, required) {
    var $select = $combo.find('select.nh-phone-code');
    var $input = $combo.find('input.input-text, input[type="tel"]');
    var raw = $.trim($input.val() || '');
    if (raw === '') {
      return !required;
    }
    var parsed = parsePhoneInput(raw, $select.val());
    var code = parsed && parsed.code ? parsed.code : $.trim($select.val() || '');
    var national = parsed && parsed.national ? String(parsed.national).replace(/\D/g, '') : raw.replace(/\D/g, '');
    if (!code || !national) {
      return false;
    }
    return nationalValidForCode(code, national);
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
      var $input = $combo.find('input.input-text, input[type="tel"]');
      extractDialFromInput($input, $select);
      updatePhonePrefixUI($select);
      applyPhoneMaxlength($combo);
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
      var $select = $combo.find('select.nh-phone-code');
      var $input = $combo.find('input.input-text, input[type="tel"]');
      extractDialFromInput($input, $select);
      ensureCallingCodeFromCountry($combo);
      validatePhoneCombo($combo, comboIsRequired($combo), true);
    });
    $('form.checkout').off('checkout_place_order.nhPhone');
    $('form.checkout').on('checkout_place_order.nhPhone', function () {
      $('.nh-phone-combo').each(function () {
        var $combo = $(this);
        var $select = $combo.find('select.nh-phone-code');
        var $input = $combo.find('input.input-text, input[type="tel"]');
        extractDialFromInput($input, $select);
        ensureCallingCodeFromCountry($combo);
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

  function snippetReady() {
    var $input = $('#nh_checkout_snippet_ready');
    if ($input.length) {
      return $input.val() === '1';
    }
    return !!i18n.snippetReady;
  }

  function setSnippetReady(on) {
    var $input = $('#nh_checkout_snippet_ready');
    if ($input.length) {
      $input.val(on ? '1' : '');
    }
    i18n.snippetReady = on ? '1' : '';
    i18n.snippetCheckout = !!on;
  }

  function isSnippetCheckoutPage() {
    return snippetReady() && (paymentIdIsSnippet(chosenPaymentId()) || snippetCheckoutPresent());
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
    return snippetReady() ? 'payment' : 'details';
  }

  function shouldKeepPaymentSelection() {
    if (paymentChosenByCustomer) {
      return true;
    }
    if (i18n.chosenPayment) {
      return true;
    }
    if (snippetReady()) {
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

  var scoOrderPlaced = false;
  var scoRefreshInFlight = null;

  $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
    if (!options || !options.url) {
      return;
    }
    var url = String(options.url);
    if (/refresh_sco_snippet/i.test(url)) {
      if (scoOrderPlaced) {
        jqXHR.abort();
        return;
      }
      if (scoRefreshInFlight && scoRefreshInFlight !== jqXHR) {
        try {
          scoRefreshInFlight.abort();
        } catch (err) { /* already finished */ }
      }
      scoRefreshInFlight = jqXHR;
      jqXHR.always(function () {
        if (scoRefreshInFlight === jqXHR) {
          scoRefreshInFlight = null;
        }
      });
      return;
    }
    if (/sco_checkout_order/i.test(url)) {
      jqXHR.done(function (res) {
        if (res && res.result === 'success') {
          scoOrderPlaced = true;
        }
      });
      return;
    }
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

    // Always abort plugin "switch to Svea/Kustom" AJAX. That call reloads the
    // page on success; we already reload once via reloadForSnippetGateway().
    // Letting both run stacked two full reloads. After the iframe is mounted,
    // Svea still fires this on every updated_checkout — aborting that is what
    // stops a reload loop.
    jqXHR.abort();
    window.setTimeout(unblockCheckout, 0);
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
    return snippetReady() && (paymentIdIsSnippet(chosenPaymentId()) || snippetCheckoutPresent());
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

    snippetOtherPayment().addClass('nh-checkout-other-payment-src').attr('hidden', 'hidden');

    keepShipToSameAddress();
    syncTermsGate();
    var status = document.getElementById('nh-checkout-iframe-status');
    var wrap = document.getElementById('nh-checkout-iframe');
    if (status && wrap) {
      status.hidden = !!wrap.querySelector('iframe');
    }
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
    if (aside && main) {
      aside.style.setProperty('order', '0', 'important');
      main.style.setProperty('order', '1', 'important');
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

  function shippingAmountEl() {
    var $row = $('#order_review tr.woocommerce-shipping-totals, #order_review tr.shipping').first();
    var $mount = $('#nh-checkout-shipping-mount');
    var $checked = $mount.add($row).find('input.shipping_method:checked, input.shipping_method[type="hidden"]').first();
    var $amount = $();
    if ($checked.length) {
      $amount = $checked.closest('li, td').find('.woocommerce-Price-amount').first();
    }
    if (!$amount.length) {
      $amount = $row.find('.nh-summary-ship-chosen .woocommerce-Price-amount, .woocommerce-Price-amount').first();
    }
    if (!$amount.length) {
      $amount = $mount.find('.woocommerce-Price-amount').first();
    }
    return $amount;
  }

  function shippingLabelHtml() {
    var $amount = shippingAmountEl();
    if (!$amount.length) {
      return '';
    }
    var template = i18n.inclShipping || 'Shipping: %s';
    return template.replace('%s', $amount.prop('outerHTML'));
  }

  function shippingMethodLabelParts($root) {
    var $checked = $root.find('input.shipping_method:checked, input.shipping_method[type="hidden"]').first();
    var $li = $checked.length ? $checked.closest('li') : $root.find('li').first();
    var $label = $li.find('label').first();
    var $amount = $label.find('.woocommerce-Price-amount').first();
    if (!$amount.length) {
      $amount = $li.find('.woocommerce-Price-amount').first();
    }
    var name = $.trim(
      $label.clone().find('.woocommerce-Price-amount, .dpd-carrier-icon-image-holder, .nh-checkout-shipping-extra').remove().end().text() ||
      $root.find('option:selected').text() ||
      ''
    );
    name = name.replace(/:\s*$/, '');
    return {
      name: name,
      amountHtml: $amount.length ? $amount.prop('outerHTML') : ''
    };
  }

  function renderChosenShipping($cell, parts) {
    if (!$cell || !$cell.length) {
      return;
    }
    var $chosen = $cell.children('.nh-summary-ship-chosen').first();
    if (!$chosen.length) {
      $chosen = $('<span class="nh-summary-ship-chosen"></span>');
      $cell.append($chosen);
    }
    $chosen.empty();
    if (parts.name) {
      $chosen.append($('<span class="nh-summary-ship-chosen__name"></span>').text(parts.name));
    }
    if (parts.amountHtml) {
      $chosen.append(parts.amountHtml);
    }
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
    var $sticky = $('.nh-checkout-sticky__amount');
    if ($src.length && $sticky.length) {
      $sticky.html(amountHtmlFromTotalCell($src));
    }
    var $compact = $('.nh-checkout-summary-toggle__label');
    if ($compact.length) {
      var count = 0;
      $('#order_review tbody .cart_item').each(function () {
        var qty = parseInt($(this).find('.product-quantity').text().replace(/\D/g, ''), 10);
        count += qty > 0 ? qty : 1;
      });
      if (!count && i18n.productsCount) {
        count = 0;
      }
      var totalText = $src.length ? $.trim($('<div/>').html(amountHtmlFromTotalCell($src)).text()) : '';
      var items = count === 1
        ? (i18n.productsCount ? i18n.productsCount.replace('%d', '1') : '1 product')
        : (i18n.productsCountMany ? i18n.productsCountMany.replace('%d', String(count)) : (count + ' products'));
      $compact.text(totalText ? (items + ' · ' + totalText) : items);
    }
  }

  function bindSummaryToggle() {
    var $summary = $('.nh-checkout-summary');
    if (!$summary.length) {
      return;
    }

    if (document.body.getAttribute('data-nh-summary-init') !== '1') {
      document.body.setAttribute('data-nh-summary-init', '1');
      var startClosed = !window.matchMedia('(min-width: 960px)').matches;
      $summary.toggleClass('is-open', !startClosed);
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
    var $radios = $('input[name="payment_method"]');
    $radios.prop('disabled', false);

    var keep = String(i18n.chosenPayment || '').trim();
    if (!keep && shouldKeepPaymentSelection()) {
      keep = chosenPaymentId();
    }
    if (keep && keep !== 'nh_none') {
      var $keep = $radios.filter(function () {
        return String(this.value) === keep;
      });
      if ($keep.length && !$keep.prop('checked')) {
        ignoreAutoPaymentClick = true;
        $radios.prop('checked', false);
        $keep.prop('checked', true);
        window.setTimeout(function () {
          ignoreAutoPaymentClick = false;
        }, 80);
      }
    }

    if (!snippetReady() && !iframeMarkupPresent()) {
      $('body, form.checkout').removeClass(
        'nh-checkout--snippet wc-svea-checkout-page svea-checkout kco-checkout'
      );
    }

    enhancePaymentCards();
    syncSnippetCheckout();
    syncKcoPrevent();
    syncTermsGate();
    syncStickyBar();
    syncPaypalButtons();
  }

  function termsCheckbox() {
    return document.querySelector('#terms, input[name="terms"]');
  }

  function termsWrapper() {
    return document.querySelector('.woocommerce-terms-and-conditions-wrapper');
  }

  function termsAgreed() {
    var box = termsCheckbox();
    return !box || box.checked;
  }

  function rememberTerms(on) {
    termsAccepted = !!on;
    try {
      if (on) {
        sessionStorage.setItem(TERMS_KEY, '1');
      } else {
        sessionStorage.removeItem(TERMS_KEY);
      }
    } catch (e) { /* private mode */ }
  }

  function rememberedTerms() {
    if (termsAccepted) {
      return true;
    }
    try {
      return sessionStorage.getItem(TERMS_KEY) === '1';
    } catch (e) {
      return false;
    }
  }

  function restoreTermsCheckbox() {
    if (!rememberedTerms()) {
      return;
    }
    var box = termsCheckbox();
    if (box && !box.checked) {
      box.checked = true;
    }
    clearTermsError();
  }

  function ensureTermsErrorEl(wrap) {
    if (!wrap || !termsCheckbox() || wrap.querySelector('.nh-checkout-terms-error')) {
      return;
    }
    var p = document.createElement('p');
    p.className = 'nh-checkout-terms-error';
    p.setAttribute('role', 'alert');
    p.textContent = i18n.termsRequired || 'Please agree to the website terms and conditions to continue.';
    wrap.appendChild(p);
  }

  function clearTermsError() {
    var wrap = termsWrapper();
    if (wrap) {
      wrap.classList.remove('nh-checkout-terms--error', 'woocommerce-invalid');
      wrap.querySelectorAll('.form-row').forEach(function (row) {
        row.classList.remove('woocommerce-invalid', 'woocommerce-invalid-required-field');
      });
    }
    var gate = document.getElementById('nh-checkout-terms-gate');
    if (gate) {
      gate.classList.remove('is-error');
    }
  }

  function markTermsError() {
    var wrap = termsWrapper();
    var box = termsCheckbox();
    if (!wrap || !box) {
      return false;
    }
    ensureTermsErrorEl(wrap);
    wrap.classList.add('nh-checkout-terms', 'nh-checkout-terms--error', 'woocommerce-invalid');
    var row = box.closest('.form-row');
    if (row) {
      row.classList.add('woocommerce-invalid', 'woocommerce-invalid-required-field');
    }
    var gate = document.getElementById('nh-checkout-terms-gate');
    if (gate) {
      gate.classList.add('is-error');
    }
    if (wrap.scrollIntoView) {
      wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    try {
      box.focus({ preventScroll: true });
    } catch (err) {
      box.focus();
    }
    return false;
  }

  function enforceTermsOrHighlight() {
    if (termsAgreed()) {
      clearTermsError();
      return true;
    }
    markTermsError();
    return false;
  }

  function syncTermsGate() {
    restoreTermsCheckbox();
    var wrap = termsWrapper();
    var box = termsCheckbox();
    var iframe = document.getElementById('nh-checkout-iframe');
    var gate = document.getElementById('nh-checkout-terms-gate');

    if (wrap) {
      wrap.classList.add('nh-checkout-terms');
      if (box) {
        ensureTermsErrorEl(wrap);
      }
    }

    var needGate = snippetReady() && iframeMarkupPresent() && box && !box.checked;
    if (!needGate) {
      if (gate) {
        gate.remove();
      }
      return;
    }
    if (!iframe) {
      return;
    }
    if (!gate) {
      gate = document.createElement('button');
      gate.type = 'button';
      gate.id = 'nh-checkout-terms-gate';
      gate.className = 'nh-checkout-terms-gate';
      gate.textContent = i18n.termsRequired || 'Please agree to the website terms and conditions to continue.';
      gate.addEventListener('click', function (e) {
        e.preventDefault();
        markTermsError();
      });
      iframe.appendChild(gate);
    } else if (!gate.textContent) {
      gate.textContent = i18n.termsRequired || 'Please agree to the website terms and conditions to continue.';
    }
  }

  function bindTermsAgreement() {
    if (document.body.getAttribute('data-nh-terms-gate') === '1') {
      return;
    }
    document.body.setAttribute('data-nh-terms-gate', '1');

    $(document.body).on('change.nhTerms', '#terms, input[name="terms"]', function () {
      rememberTerms(this.checked);
      if (this.checked) {
        clearTermsError();
      }
      syncTermsGate();
    });

    $(document.body).on('checkout_error.nhTerms', function () {
      if (!termsAgreed()) {
        markTermsError();
      }
    });

    $('form.checkout').on('checkout_place_order.nhTerms', function () {
      return enforceTermsOrHighlight();
    });

    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('#place_order') : null;
      if (!btn || termsAgreed()) {
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      markTermsError();
    }, true);

    $(document).on('ajaxComplete.nhTerms', function (e, xhr, settings) {
      var url = settings && settings.url ? String(settings.url) : '';
      if (!/sco_checkout_order/i.test(url)) {
        return;
      }
      if (!termsAgreed()) {
        markTermsError();
      }
    });

    syncTermsGate();
  }

  function fieldRowIsRequired($row) {
    if (!$row.length || $row.hasClass('nh-checkout-field--hidden') || !$row.is(':visible')) {
      return false;
    }
    return $row.hasClass('validate-required');
  }

  function fieldRowValue($row) {
    var $inputs = $row.find('input, select, textarea').filter(':visible').not('[type=hidden]').not(':disabled');
    if (!$inputs.length) {
      return { empty: true, $input: $() };
    }
    var type = String($inputs.first().attr('type') || '').toLowerCase();
    if (type === 'radio') {
      return { empty: !$row.find('input[type=radio]:checked').length, $input: $inputs.first() };
    }
    if (type === 'checkbox') {
      return { empty: !$inputs.first().is(':checked'), $input: $inputs.first() };
    }
    return { empty: $.trim($inputs.first().val() || '') === '', $input: $inputs.first() };
  }

  function emailLooksValid(value) {
    value = $.trim(value || '');
    return value === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  function setFieldError($row, message) {
    var $hint = $row.find('.nh-field-hint');
    if (!$hint.length) {
      $hint = $('<span class="nh-field-hint" role="status"></span>');
      $row.append($hint);
    }
    $row.addClass('woocommerce-invalid woocommerce-invalid-required-field');
    $row.removeClass('woocommerce-validated');
    $hint.text(message || i18n.fieldRequired || 'Please fill in this field.');
  }

  function clearFieldError($row) {
    $row.removeClass('woocommerce-invalid woocommerce-invalid-required-field');
    $row.find('.nh-field-hint').text('');
    if ($.trim($row.find('input, select, textarea').first().val() || '') !== '') {
      $row.addClass('woocommerce-validated');
    }
  }

  function validateFieldRow($row, show) {
    if (!$row.length || $row.hasClass('nh-checkout-field--hidden')) {
      return true;
    }
    var $email = $row.find('input[type=email], #billing_email, #billing_contact_email');
    if ($email.length) {
      var mail = $.trim($email.first().val() || '');
      if (mail !== '' && !emailLooksValid(mail)) {
        if (show) {
          setFieldError($row, i18n.emailInvalid || 'Please enter a valid email address.');
        }
        return false;
      }
    }
    if (!fieldRowIsRequired($row)) {
      if (show) {
        clearFieldError($row);
      }
      return true;
    }
    var info = fieldRowValue($row);
    if (info.empty) {
      if (show) {
        setFieldError($row, i18n.fieldRequired || 'Please fill in this field.');
      }
      return false;
    }
    if (show) {
      clearFieldError($row);
    }
    return true;
  }

  function validateDetailsStep(show) {
    if (typeof show === 'undefined') {
      show = true;
    }
    if (isSnippetMode()) {
      return true;
    }
    var ok = true;
    var $first = $();
    $('#customer_details p.form-row, #customer_details .form-row').not('.nh-checkout-field--hidden').each(function () {
      var $row = $(this);
      if (!validateFieldRow($row, show)) {
        ok = false;
        if (!$first.length) {
          $first = fieldRowValue($row).$input;
        }
      }
    });
    if (!validateAllPhones(show)) {
      ok = false;
      if (!$first.length) {
        $first = $('.woocommerce-invalid-phone:visible').find('input').first();
      }
    }
    if (show && !ok && $first.length) {
      $first.trigger('focus');
      if ($first[0].scrollIntoView) {
        $first[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }
    return ok;
  }

  function bindFieldValidation() {
    if (document.body.getAttribute('data-nh-field-validate') === '1') {
      return;
    }
    document.body.setAttribute('data-nh-field-validate', '1');
    $(document.body).on('blur.nhFieldValidate change.nhFieldValidate', '#customer_details .input-text, #customer_details select, #customer_details textarea', function () {
      validateFieldRow($(this).closest('.form-row'), true);
    });
  }

  function collapseSummaryOnMobile() {
    if (window.matchMedia('(min-width: 960px)').matches) {
      return;
    }
    var $summary = $('.nh-checkout-summary');
    if (!$summary.length) {
      return;
    }
    $summary.removeClass('is-open');
    $summary.find('.nh-checkout-summary-toggle').attr('aria-expanded', 'false');
  }

  function markPaymentFocus(kind) {
    try {
      sessionStorage.setItem(FOCUS_KEY, kind || 'payment');
    } catch (e) { /* private mode */ }
    if (history.scrollRestoration) {
      history.scrollRestoration = 'manual';
    }
  }

  function paymentFocusTarget(kind) {
    var iframe;
    if (kind === 'iframe' || iframeMarkupPresent()) {
      iframe = document.getElementById('nh-checkout-iframe');
      if (iframe) {
        return iframe;
      }
    }
    return document.getElementById('nh-checkout-payment') ||
      document.querySelector('.nh-checkout-payment') ||
      document.getElementById('payment');
  }

  function scrollToPaymentFocus(kind) {
    var node = paymentFocusTarget(kind);
    if (!node) {
      return;
    }
    collapseSummaryOnMobile();
    lockSummaryLayout();
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (typeof node.scrollIntoView === 'function') {
      node.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    }
  }

  function schedulePaymentFocus(kind) {
    kind = kind || 'payment';
    collapseSummaryOnMobile();
    lockSummaryLayout();
    [0, 80, 250, 700, 1400].forEach(function (ms) {
      window.setTimeout(function () {
        scrollToPaymentFocus(kind);
      }, ms);
    });
  }

  function consumePaymentFocus() {
    var kind = '';
    try {
      kind = sessionStorage.getItem(FOCUS_KEY) || '';
      if (kind) {
        sessionStorage.removeItem(FOCUS_KEY);
      }
    } catch (e) { /* private mode */ }
    if (!kind && /nh-checkout-(iframe|payment)/.test(window.location.hash || '')) {
      kind = 'iframe';
    }
    return kind;
  }

  function hideCrispOnMobileCheckout() {
    if (window.matchMedia('(min-width: 960px)').matches) {
      return;
    }
    if (document.body.classList.contains('nh-crisp-open')) {
      return;
    }
    if (window.nhCrisp) {
      window.nhCrisp.hideLauncher();
      return;
    }
    window.$crisp = window.$crisp || [];
    try {
      window.$crisp.push(['do', 'chat:hide']);
    } catch (e) { /* Crisp not ready */ }
  }

  function paymentKind(id) {
    id = String(id || chosenPaymentId() || '').toLowerCase();
    if (paymentIdIsSnippet(id)) {
      if (/kco|kustom|klarna/.test(id)) {
        return 'kustom';
      }
      return 'svea';
    }
    if (/paypal|ppcp|ppec|paypal_express|paypalcp/.test(id)) {
      return 'paypal';
    }
    if (/makecommerce|maksekeskus/.test(id)) {
      return 'makecommerce';
    }
    if (id === 'bacs') {
      return 'bacs';
    }
    return 'other';
  }

  function paymentIdIsMakecommerce(id) {
    return paymentKind(id) === 'makecommerce';
  }

  function makecommerceMethodValue() {
    var value = '';
    $('[name^="PRESELECTED_METHOD_"], [name^="preselected_method_"]').each(function () {
      var $el = $(this);
      var current = '';
      if ($el.is('[type=radio], [type=checkbox]')) {
        if ($el.is(':checked')) {
          current = $.trim($el.val() || '');
        }
      } else {
        current = $.trim($el.val() || '');
      }
      if (current) {
        value = current;
        return false;
      }
    });
    if (value) {
      return value;
    }
    var selected = document.querySelector('.makecommerce-banklink-picker.selected, .makecommerce_payment_option.selected');
    return selected ? String(selected.getAttribute('banklink_id') || selected.id || '') : '';
  }

  function makecommerceNeedsNestedMethod() {
    return !!(
      document.querySelector(
        '[name^="PRESELECTED_METHOD_"], [name^="preselected_method_"], .makecommerce-banklink-picker, .makecommerce_payment_option'
      )
    );
  }

  function makecommerceMethodSelected() {
    if (!paymentIdIsMakecommerce()) {
      return true;
    }
    if (!makecommerceNeedsNestedMethod()) {
      return true;
    }
    return makecommerceMethodValue() !== '';
  }

  function hasInlinePaypal() {
    return !!(
      document.querySelector(
        '#ppc-button, .paypal-buttons, .paypal-button-container, #paypal-button-container, .wc-ppcp-pay-later, iframe[name^="paypal"]'
      )
    );
  }

  function formattedTotal() {
    var html = $('.nh-checkout-sticky__amount').first().text() ||
      $('.nh-checkout-summary-toggle__amount').first().text() ||
      $('#order_review .order-total td').first().text();
    return $.trim(html || '');
  }

  function shouldShowStickyBar() {
    if (iframeMarkupPresent() || isSnippetMode()) {
      return false;
    }
    if (paymentKind() === 'paypal' && hasInlinePaypal()) {
      return false;
    }
    return true;
  }

  function stickyButtonLabel() {
    var $checked = $('input[name="payment_method"]:checked').not(':disabled');
    var kind = paymentKind();
    var total = formattedTotal();
    if (!$checked.length) {
      return i18n.reviewLabel || 'Review order';
    }
    if (kind === 'svea') {
      return iframeMarkupPresent() ? (i18n.completeInSvea || 'Complete payment in SVEA') : (i18n.continueSvea || 'Continue to SVEA');
    }
    if (kind === 'kustom') {
      return iframeMarkupPresent() ? (i18n.completeInKustom || 'Complete payment in Kustom') : (i18n.continueKustom || 'Continue to Kustom');
    }
    if (kind === 'paypal') {
      return hasInlinePaypal()
        ? (i18n.payLabel ? i18n.payLabel.replace('%s', total) : ('Pay ' + total))
        : (i18n.continuePaypal || 'Continue to PayPal');
    }
    if (kind === 'makecommerce') {
      return i18n.payViaMakecommerce || 'Pay via MakeCommerce';
    }
    var gatewayLabel = $.trim($checked.attr('data-order_button_text') || '');
    if (gatewayLabel) {
      return gatewayLabel;
    }
    if (total) {
      return i18n.payLabel ? i18n.payLabel.replace('%s', total) : ('Pay ' + total);
    }
    return i18n.reviewLabel || 'Review order';
  }

  function checkoutIsReadyToPay() {
    if (isSnippetMode()) {
      return termsAgreed();
    }
    return validateDetailsStep(false) && !!chosenPaymentId() && termsAgreed();
  }

  function showCheckoutStatus(title, text, kind) {
    var el = document.getElementById('nh-checkout-status');
    if (!el) {
      return;
    }
    el.hidden = false;
    el.classList.add('is-visible');
    if (kind) {
      el.setAttribute('data-kind', kind);
    }
    var t = document.getElementById('nh-checkout-status-title');
    var p = document.getElementById('nh-checkout-status-text');
    if (t) {
      t.textContent = title || '';
    }
    if (p) {
      p.textContent = text || '';
    }
  }

  function hideCheckoutStatus() {
    var el = document.getElementById('nh-checkout-status');
    if (!el) {
      return;
    }
    el.hidden = true;
    el.classList.remove('is-visible');
    el.removeAttribute('data-kind');
  }

  function scrollToCheckoutError() {
    var err = document.querySelector('.woocommerce-error, .woocommerce-NoticeGroup-checkout, .woocommerce-notices-wrapper .woocommerce-error');
    if (err && err.scrollIntoView) {
      err.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  function checkoutDraftSkipName(name) {
    name = String(name || '');
    return !name ||
      /nonce|password|card.?number|cvv|cvc|card.?exp/i.test(name) ||
      name.indexOf('shipping_method') === 0;
  }

  function checkoutDraftFields() {
    var data = {};
    $('form.checkout').find('input, select, textarea').each(function () {
      var $el = $(this);
      var name = $el.attr('name');
      if (checkoutDraftSkipName(name) || $el.is('[type=file]')) {
        return;
      }
      if ($el.is('[type=radio], [type=checkbox]')) {
        if ($el.is(':checked')) {
          data[name] = $el.val();
        }
        return;
      }
      data[name] = $el.val();
    });
    return data;
  }

  function saveCheckoutDraft() {
    try {
      sessionStorage.setItem(DRAFT_KEY, JSON.stringify(checkoutDraftFields()));
    } catch (e) { /* private mode */ }
  }

  function scheduleCheckoutDraft() {
    window.clearTimeout(draftTimer);
    draftTimer = window.setTimeout(saveCheckoutDraft, 200);
  }

  function restoreMakecommerceMethod() {
    var raw;
    try {
      raw = sessionStorage.getItem(DRAFT_KEY);
    } catch (e) {
      return;
    }
    if (!raw) {
      return;
    }
    var data;
    try {
      data = JSON.parse(raw);
    } catch (e) {
      return;
    }
    if (!data || typeof data !== 'object') {
      return;
    }
    Object.keys(data).forEach(function (name) {
      if (name.indexOf('PRESELECTED_METHOD_') !== 0 && name.indexOf('preselected_method_') !== 0) {
        return;
      }
      var val = String(data[name] || '');
      if (!val) {
        return;
      }
      var $el = $('[name="' + name.replace(/"/g, '\\"') + '"]');
      if ($el.is('select') || $el.is('[type=hidden]')) {
        $el.val(val);
      }
      $el.filter(function () {
        return ($(this).is('[type=radio]') || $(this).is('[type=checkbox]')) && String(this.value) === val;
      }).prop('checked', true);
      $('.makecommerce-banklink-picker').filter(function () {
        return String($(this).attr('banklink_id') || this.id || '') === val;
      }).addClass('selected');
    });
  }

  function restoreCheckoutDraft() {
    var raw;
    try {
      raw = sessionStorage.getItem(DRAFT_KEY);
    } catch (e) {
      return;
    }
    if (!raw) {
      return;
    }
    var data;
    try {
      data = JSON.parse(raw);
    } catch (e) {
      return;
    }
    if (!data || typeof data !== 'object') {
      return;
    }
    Object.keys(data).forEach(function (name) {
      if (checkoutDraftSkipName(name)) {
        return;
      }
      var $els = $('form.checkout').find('[name="' + String(name).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
      if (!$els.length) {
        return;
      }
      var val = data[name];
      if ($els.is('[type=radio]')) {
        var $match = $els.filter(function () {
          return String(this.value) === String(val);
        });
        if ($match.length) {
          $match.prop('checked', true);
          if (name === 'payment_method') {
            paymentChosenByCustomer = true;
            i18n.chosenPayment = String(val);
          }
        }
        return;
      }
      if ($els.is('[type=checkbox]')) {
        $els.prop('checked', true);
        return;
      }
      if ($.trim($els.val() || '') === '' && val != null && val !== '') {
        $els.val(val);
      }
    });
  }

  function syncStickyBar() {
    var $amount = $('.nh-checkout-sticky__amount');
    var $src = $('#order_review .order-total td').first();
    if ($amount.length && $src.length) {
      $amount.html(amountHtmlFromTotalCell($src));
    }
    var show = shouldShowStickyBar();
    var bar = document.getElementById('nh-checkout-sticky');
    if (bar) {
      bar.hidden = !show;
    }
    $('body').toggleClass('nh-checkout--need-sticky', show);
    var $btn = $('#nh-checkout-sticky-btn');
    if (!$btn.length) {
      return;
    }
    var ready = checkoutIsReadyToPay();
    var label = stickyButtonLabel();
    $btn.text(label);
    $btn.toggleClass('is-ready', ready);
    $('body').toggleClass('nh-checkout--ready', ready);
    if (chosenPaymentId() && show) {
      $('#place_order').text(label);
    }
  }

  function syncPaypalButtons() {
    var allow = termsAgreed() && validateDetailsStep(false);
    var $paypal = $(
      '#ppc-button, .paypal-buttons, .paypal-button-container, #paypal-button-container, #place_order_paypal'
    );
    $paypal.toggleClass('nh-paypal-blocked', !allow);
    $('body').toggleClass('nh-checkout--paypal-blocked', paymentKind() === 'paypal' && !allow);
  }

  function shippingExtraRows() {
    return $('#order_review tr').filter(function () {
      var cls = this.className || '';
      if (/\bwc_shipping_dpd|\bdpd-/.test(cls)) {
        return true;
      }
      return $(this).find(
        '#wc_shipping_dpd_parcels_terminal, #wc_shipping_dpd_sameday_parcels_terminal, [name="wc_shipping_dpd_parcels_terminal"], [name="wc_shipping_dpd_home_delivery_shifts"], .custom-dropdown, #dpd-show-parcel-modal, #dpd-selected-parcel'
      ).length > 0;
    });
  }

  function dpdPickupSelected() {
    var val = String(
      $('#nh-checkout-shipping-mount input.shipping_method:checked, form.checkout input.shipping_method:checked').first().val() || ''
    );
    return val.indexOf('parcels') !== -1;
  }

  function dpdAjaxUrl() {
    return (i18n && i18n.dpdAjax) || (window.wc_checkout_params && wc_checkout_params.ajax_url) || '/wp-admin/admin-ajax.php';
  }

  function bindDpdPickupOpenCapture() {
    if (document.documentElement.getAttribute('data-nh-dpd-open') === '1') {
      return;
    }
    document.documentElement.setAttribute('data-nh-dpd-open', '1');
    document.addEventListener(
      'click',
      function (e) {
        var opt = e.target && e.target.closest && e.target.closest('.nh-dpd-pickup .selected-option');
        if (!opt) {
          return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        var list = opt.parentNode ? opt.parentNode.querySelector('.dropdown-list') : null;
        if (list) {
          list.classList.toggle('active');
        }
      },
      true
    );
  }

  function bindDpdPickupUi($root) {
    if (!$root || !$root.length || $root.data('nhDpdBound')) {
      return;
    }
    $root.data('nhDpdBound', true);
    $(document.body).off('click', '.custom-dropdown .selected-option');
    bindDpdPickupOpenCapture();
    var $list = $root.find('.dropdown-list').first();
    var $results = $root.find('.dropdown-list-search-list').first();
    var $hidden = $root.find('input[type=hidden]').first();
    var $selected = $root.find('.selected-option').first();
    var searchTimer = null;

    $selected.on('keydown.nhDpd', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') {
        return;
      }
      e.preventDefault();
      $list.toggleClass('active');
    });

    $root.on('input.nhDpd', '.js--nh-pudo-search', function () {
      var q = $.trim($(this).val() || '');
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(function () {
        $.post(dpdAjaxUrl(), { action: 'nh_search_dpd_pudo', q: q, search_value: q })
          .done(function (data) {
            if (data && data.html) {
              $results.html(data.html);
            }
          });
      }, 200);
    });

    $root.on('click.nhDpd', '.pudo', function () {
      var id = String($(this).attr('data-value') || '');
      var label = $.trim($(this).text() || '');
      if (!id) {
        return;
      }
      $root.find('.pudo').removeClass('is-selected');
      $(this).addClass('is-selected');
      $hidden.val(id);
      if (label) {
        $selected.text(label);
      }
      $list.removeClass('active');
      $.post(dpdAjaxUrl(), { action: 'nh_set_dpd_terminal', selected_value: id });
    });
  }

  function wrapShippingMethodRows($mount) {
    $mount.find('ul#shipping_method > li, ul.woocommerce-shipping-methods > li').each(function () {
      var $li = $(this);
      if ($li.children('.nh-shipping-method-row').length) {
        return;
      }
      var $input = $li.children('input.shipping_method').first();
      var $label = $li.children('label').first();
      if (!$input.length && !$label.length) {
        return;
      }
      var $row = $('<div class="nh-shipping-method-row"></div>');
      if ($input.length) {
        $row.append($input);
      }
      if ($label.length) {
        $row.append($label);
      }
      $li.prepend($row);
    });
  }

  function ensureDpdPickupUi($mount) {
    wrapShippingMethodRows($mount);
    var $own = $mount.find('.nh-dpd-pickup[data-nh-dpd-pickup], .nh-dpd-pickup').first();
    if (!dpdPickupSelected()) {
      $mount.find('.nh-dpd-pickup').hide();
      return;
    }
    $mount.find('.nh-dpd-pickup').show();
    var $li = $mount.find('input.shipping_method:checked').closest('li');
    if ($own.length) {
      if ($li.length && $own.prev()[0] !== $li[0]) {
        $li.after($own);
      }
      bindDpdPickupUi($own);
      return;
    }
    if ($li.find('.custom-dropdown, #wc_shipping_dpd_parcels_terminal, #dpd-show-parcel-modal').length) {
      bindDpdPickupUi($li.find('.nh-dpd-pickup, .custom-dropdown').first());
      return;
    }
    if (!$li.length) {
      return;
    }
    var choose = (i18n && i18n.dpdChoose) || 'Choose a Pickup Point';
    var search = (i18n && i18n.dpdSearch) || 'Search';
    var $wrap = $('<div class="nh-checkout-shipping-extra nh-dpd-pickup" data-nh-dpd-pickup="1"></div>');
    $wrap.append($('<p class="nh-checkout-shipping-extra__label"></p>').text(choose));
    $wrap.append(
      '<div class="custom-dropdown nh-dpd-pickup__dropdown">' +
        '<div class="selected-option" role="button" tabindex="0"></div>' +
        '<div class="dropdown-list">' +
          '<div class="dropdown-list-search-input"><input type="search" class="js--nh-pudo-search" autocomplete="off"></div>' +
          '<div class="dropdown-list-search-list"></div>' +
        '</div>' +
      '</div>' +
      '<input type="hidden" name="wc_shipping_dpd_parcels_terminal" id="wc_shipping_dpd_parcels_terminal" value="" />'
    );
    $wrap.find('.selected-option').text(choose);
    $wrap.find('.js--nh-pudo-search').attr('placeholder', search);
    $li.after($wrap);
    bindDpdPickupUi($wrap);
    $wrap.find('.js--nh-pudo-search').trigger('input');
  }

  function placeShippingExtras($mount) {
    var $own = $mount.find('[data-nh-dpd-pickup="1"]');
    if ($own.length) {
      shippingExtraRows().remove();
      $mount.children('.nh-checkout-shipping-extra').not('[data-nh-dpd-pickup]').remove();
      ensureDpdPickupUi($mount);
      return;
    }
    var $extras = shippingExtraRows();
    if (!$extras.length) {
      $mount.children('.nh-checkout-shipping-extra').remove();
      ensureDpdPickupUi($mount);
      return;
    }
    $mount.children('.nh-checkout-shipping-extra').remove();
    $extras.each(function () {
      var $tr = $(this);
      var label = $.trim(
        $tr.children('th').first().clone().children('.required, abbr.required').remove().end().text()
      );
      var $wrap = $('<div class="nh-checkout-shipping-extra nh-dpd-pickup"></div>');
      if (label) {
        $wrap.append($('<p class="nh-checkout-shipping-extra__label"></p>').text(label));
      }
      $wrap.append($tr.children('td').contents());
      $wrap.find('ul').each(function () {
        var $ul = $(this);
        $ul.children('li').each(function () {
          var $li = $(this);
          $li.replaceWith($('<div></div>').attr('class', $li.attr('class')).attr('data-value', $li.attr('data-value')).attr('data-cod', $li.attr('data-cod')).html($li.html()));
        });
        $ul.replaceWith($('<div></div>').attr('class', $ul.attr('class')).html($ul.html()));
      });
      var $chosenLi = $mount.find('input.shipping_method:checked').closest('li');
      if ($chosenLi.length) {
        $chosenLi.after($wrap);
      } else {
        $mount.append($wrap);
      }
      $tr.remove();
    });
    ensureDpdPickupUi($mount);
  }

  function placeShippingMethods() {
    var $mount = $('#nh-checkout-shipping-mount');
    if (!$mount.length) {
      return;
    }
    var $row = $('#order_review tr.woocommerce-shipping-totals, #order_review tr.shipping').first();
    var $cell = $row.find('td').first();
    var $methods = $cell.find('ul#shipping_method, ul.woocommerce-shipping-methods, select.shipping_method').first();
    var $source = $methods.length ? $methods : $mount.find('ul#shipping_method, ul.woocommerce-shipping-methods, select.shipping_method').first();
    if ($methods.length && !$mount[0].contains($methods[0])) {
      var parts = shippingMethodLabelParts($methods);
      $mount.children('ul#shipping_method, ul.woocommerce-shipping-methods, select.shipping_method').remove();
      $mount.prepend($methods);
      renderChosenShipping($cell, parts);
    } else if ($source.length) {
      renderChosenShipping($cell, shippingMethodLabelParts($source));
    }
    wrapShippingMethodRows($mount);
    placeShippingExtras($mount);
  }

  function enhanceOptionalRows() {
    function bindOptional($row, labelKey, fallback) {
      if (!$row.length || $row.data('nhOptional')) {
        return;
      }
      $row.data('nhOptional', true);
      if ($.trim($row.find('input, textarea').val() || '') !== '') {
        return;
      }
      var $btn = $('<button type="button" class="nh-optional-toggle"></button>');
      $btn.text(i18n[labelKey] || fallback);
      $row.addClass('nh-checkout-field--hidden');
      $btn.insertBefore($row);
      $btn.on('click', function () {
        $row.removeClass('nh-checkout-field--hidden');
        $btn.remove();
        $row.find('input, textarea').trigger('focus');
      });
    }
    bindOptional($('#billing_address_2_field'), 'address2Label', 'Add apartment, suite, etc.');
    if (selectedType() === 'business') {
      var $email = $('#billing_contact_email_field');
      var $phone = $('#billing_contact_phone_field');
      var extraFilled = $.trim($email.find('input').val() || '') !== '' || $.trim($phone.find('input').val() || '') !== '';
      if (!extraFilled && $email.length && !$email.data('nhOptional')) {
        $email.data('nhOptional', true);
        $phone.data('nhOptional', true);
        var $btn = $('<button type="button" class="nh-optional-toggle"></button>');
        $btn.text(i18n.contactExtra || 'Add contact person');
        $email.addClass('nh-checkout-field--hidden');
        $phone.addClass('nh-checkout-field--hidden');
        $btn.insertBefore($email);
        $btn.on('click', function () {
          $email.removeClass('nh-checkout-field--hidden');
          $phone.removeClass('nh-checkout-field--hidden');
          $btn.remove();
          $email.find('input').trigger('focus');
        });
      }
    }
  }

  function lockStickyAboveKeyboard() {
    var bar = document.getElementById('nh-checkout-sticky');
    if (!bar || !window.visualViewport) {
      return;
    }
    var vv = window.visualViewport;
    var overlap = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
    bar.style.bottom = overlap ? overlap + 'px' : '';
  }

  function reloadCheckoutToPayment() {
    markPaymentFocus('iframe');
    if (window.location.hash !== '#nh-checkout-iframe') {
      window.location.hash = 'nh-checkout-iframe';
    }
    window.location.reload();
  }

  function reloadForSnippetGateway() {
    if (iframeMarkupPresent()) {
      hideCheckoutStatus();
      schedulePaymentFocus('iframe');
      return;
    }
    allowSnippetGatewayReload = true;
    syncKcoPrevent();
    setSnippetReady(true);
    window.clearTimeout(snippetReloadTimer);
    $(document.body).off('updated_checkout.nhSnippetPrefill');
    $(document.body).one('updated_checkout.nhSnippetPrefill', function () {
      window.clearTimeout(snippetReloadTimer);
      reloadCheckoutToPayment();
    });
    $(document.body).trigger('update_checkout');
    snippetReloadTimer = window.setTimeout(function () {
      reloadCheckoutToPayment();
    }, 2000);
  }

  function continueToSnippet() {
    if (!validateDetailsStep(true)) {
      return false;
    }
    if (!chosenPaymentId()) {
      window.alert(i18n.selectPayment || 'Please choose a payment method.');
      scrollToPaymentFocus('payment');
      return false;
    }
    if (!enforceTermsOrHighlight()) {
      return false;
    }
    var kind = paymentKind();
    if (kind === 'svea') {
      showCheckoutStatus(i18n.sveaPreparing, i18n.sveaPreparingText, 'svea');
    } else if (kind === 'kustom') {
      showCheckoutStatus(i18n.kustomPreparing, i18n.kustomPreparingText, 'kustom');
    } else {
      showCheckoutStatus(i18n.processingTitle, i18n.processingText, 'processing');
    }
    reloadForSnippetGateway();
    return false;
  }

  function leaveSnippetIfNeeded(id) {
    if (!(snippetCheckoutPresent() || iframeMarkupPresent() || snippetReady())) {
      return;
    }
    if (paymentIdIsSnippet(id)) {
      return;
    }
    showCheckoutStatus(i18n.processingTitle, i18n.processingText, 'processing');
    setSnippetReady(false);
    allowSnippetGatewayReload = false;
    $(document.body).one('updated_checkout.nhLeaveIframe', function () {
      window.location.reload();
    });
    window.setTimeout(function () {
      window.location.reload();
    }, 1600);
  }

  function runFinalAction() {
    if (!validateDetailsStep(true)) {
      return;
    }
    if (!chosenPaymentId()) {
      window.alert(i18n.selectPayment || 'Please choose a payment method.');
      scrollToPaymentFocus('payment');
      return;
    }
    if (paymentIdIsMakecommerce() && !makecommerceMethodSelected()) {
      hideCheckoutStatus();
      window.alert(i18n.selectMakecommerce || i18n.selectPayment || 'Please choose a payment method.');
      scrollToPaymentFocus('payment');
      $('body').addClass('nh-checkout--need-mc-method');
      return;
    }
    if (!enforceTermsOrHighlight()) {
      return;
    }
    var kind = paymentKind();
    if ((kind === 'svea' || kind === 'kustom') && !iframeMarkupPresent()) {
      continueToSnippet();
      return;
    }
    if ((kind === 'svea' || kind === 'kustom') && iframeMarkupPresent()) {
      schedulePaymentFocus('iframe');
      return;
    }
    if (kind === 'paypal' && hasInlinePaypal()) {
      var paypal = document.querySelector('#ppc-button, .paypal-buttons, .paypal-button-container, #paypal-button-container');
      if (paypal && paypal.scrollIntoView) {
        paypal.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      return;
    }
    if (kind === 'paypal') {
      showCheckoutStatus(i18n.paypalRedirect, '', 'paypal');
    } else {
      showCheckoutStatus(i18n.processingTitle, i18n.processingText, 'processing');
    }
    saveCheckoutDraft();
    $('#place_order').trigger('click');
  }

  function bindCheckoutSteps() {
    if (document.body.getAttribute('data-nh-checkout-steps') === '1') {
      return;
    }
    document.body.setAttribute('data-nh-checkout-steps', '1');
    syncKcoPrevent();
    bindFieldValidation();

    $(document.body).on('click.nhSticky', '#nh-checkout-sticky-btn', function (e) {
      e.preventDefault();
      runFinalAction();
    });

    $(document.body).on('click.nhMcMethod', '.makecommerce-banklink-picker, .makecommerce_payment_option, [name^="PRESELECTED_METHOD_"], [name^="preselected_method_"]', function () {
      $('body').removeClass('nh-checkout--need-mc-method');
      window.setTimeout(function () {
        saveCheckoutDraft();
        syncStickyBar();
      }, 0);
    });

    $(document.body).on('checkout_error.nhStatus', function () {
      hideCheckoutStatus();
      unblockCheckout();
      saveCheckoutDraft();
      window.setTimeout(scrollToCheckoutError, 50);
    });

    $(document.body).on('click.nhStatus', '#nh-checkout-status', function () {
      hideCheckoutStatus();
    });

    $(document.body).on('input.nhDraft change.nhDraft', 'form.checkout', scheduleCheckoutDraft);
    window.addEventListener('pagehide', saveCheckoutDraft);
    window.addEventListener('pageshow', function () {
      restoreCheckoutDraft();
      applyCheckoutStep();
      hideCheckoutStatus();
    });

    $(document.body).on('click.nhPayMethod change.nhPayMethod', 'input[name="payment_method"]', function (e) {
      if (ignoreAutoPaymentClick) {
        return;
      }
      if (!e.originalEvent) {
        return;
      }
      paymentChosenByCustomer = true;
      var prev = String(i18n.chosenPayment || '');
      var id = String(this.value || '');
      i18n.chosenPayment = id;
      if (!paymentIdIsSnippet(id)) {
        setSnippetReady(false);
      } else {
        setSnippetReady(true);
      }
      applyCheckoutStep();
      allowSnippetGatewayReload = false;
      syncKcoPrevent();
      if (paymentIdIsSnippet(id) && (!iframeMarkupPresent() || (prev !== '' && prev !== id))) {
        reloadForSnippetGateway();
        return;
      }
      $(document.body).trigger('update_checkout');
      leaveSnippetIfNeeded(id);
    });

    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || !form.classList || !form.classList.contains('checkout')) {
        return;
      }
      if (!validateDetailsStep(true)) {
        e.preventDefault();
        e.stopPropagation();
        return;
      }
      if (!$('input[name="payment_method"]:checked').not(':disabled').length) {
        e.preventDefault();
        e.stopPropagation();
        window.alert(i18n.selectPayment || 'Please choose a payment method.');
        return;
      }
      if (paymentIdIsMakecommerce() && !makecommerceMethodSelected()) {
        e.preventDefault();
        e.stopPropagation();
        hideCheckoutStatus();
        window.alert(i18n.selectMakecommerce || i18n.selectPayment || 'Please choose a payment method.');
        scrollToPaymentFocus('payment');
        $('body').addClass('nh-checkout--need-mc-method');
        return;
      }
      saveCheckoutDraft();
      if (!enforceTermsOrHighlight()) {
        e.preventDefault();
        e.stopPropagation();
        return;
      }
      var kind = paymentKind();
      if ((kind === 'svea' || kind === 'kustom') && !iframeMarkupPresent()) {
        e.preventDefault();
        e.stopPropagation();
        continueToSnippet();
      }
    }, true);

    document.addEventListener('click', function (e) {
      var paypal = e.target && e.target.closest
        ? e.target.closest('#ppc-button, .paypal-buttons, .paypal-button-container, #paypal-button-container')
        : null;
      if (!paypal) {
        return;
      }
      if (termsAgreed() && validateDetailsStep(true)) {
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      if (!termsAgreed()) {
        markTermsError();
      }
    }, true);

    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', lockStickyAboveKeyboard);
      window.visualViewport.addEventListener('scroll', lockStickyAboveKeyboard);
    }

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
    enhanceOptionalRows();
    placeShippingMethods();
    applyCheckoutStep();
    forcePairClasses();
    syncPairedAddressRows();
    syncStickyBar();
    syncPaypalButtons();
    lockStickyAboveKeyboard();
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
    if (iframeMarkupPresent() || paymentIdIsSnippet(chosenPaymentId())) {
      return;
    }
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

  function snippetMethodId() {
    return String(chosenPaymentId() || i18n.chosenPayment || '').toLowerCase();
  }

  function isKustomMethod() {
    return /kco|kustom|klarna/.test(snippetMethodId());
  }

  function withKustomApi(fn) {
    if (typeof window._klarnaCheckout !== 'function') {
      return false;
    }
    window._klarnaCheckout(function (api) {
      fn(api);
    });
    return true;
  }

  function markKustomShippingKnown() {
    if (window.kco_wc) {
      window.kco_wc.shippingAddressKnown = true;
    }
  }

  function suspendKustomIframe() {
    markKustomShippingKnown();
    return withKustomApi(function (api) {
      if (!api || typeof api.suspend !== 'function') {
        return;
      }
      if (window.kco_wc) {
        window.kco_wc.suspended = true;
      }
      api.suspend({ autoResume: { enabled: false } });
    });
  }

  function resumeKustomIframe() {
    withKustomApi(function (api) {
      if (!api || typeof api.resume !== 'function') {
        return;
      }
      if (window.kco_wc) {
        window.kco_wc.suspended = false;
      }
      api.resume();
    });
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
    var kustom = isKustomMethod();
    if (kustom) {
      suspendKustomIframe();
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
        placeShippingMethods();
        syncSummaryTotal();
        lockSummaryLayout();
        if (/svea|sco/.test(snippetMethodId()) && !kustom) {
          $(document).trigger('sco_refresh_data');
        }
      },
      complete: function () {
        if (kustom) {
          resumeKustomIframe();
        }
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
    api.observeEvent('identity.isCompany', function (data) {
      var flag = false;
      if (data === true || data === 'true' || data === 1 || data === '1') {
        flag = true;
      } else if (data && typeof data === 'object') {
        flag = data.value === true || data.value === 'true' || data.isCompany === true;
      }
      var val = flag ? 'business' : 'private';
      var $radio = $('input[name="billing_customer_type"][value="' + val + '"]');
      if ($radio.length && !$radio.prop('checked')) {
        $('input[name="billing_customer_type"]').prop('checked', false);
        $radio.prop('checked', true);
      }
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

  function extractZipFromUnknown(data) {
    if (data == null) {
      return '';
    }
    if (typeof data === 'string' || typeof data === 'number') {
      return usablePostcode(data);
    }
    if (typeof data !== 'object') {
      return '';
    }
    return usablePostcode(
      data.postal_code ||
      data.postalCode ||
      (data.customer && (data.customer.postal_code || data.customer.postalCode)) ||
      (data.shipping_address && (data.shipping_address.postal_code || data.shipping_address.postalCode)) ||
      (data.billing_address && (data.billing_address.postal_code || data.billing_address.postalCode)) ||
      (data.address && (data.address.postal_code || data.address.postalCode))
    );
  }

  function extractCountryFromUnknown(data) {
    if (!data || typeof data !== 'object') {
      return '';
    }
    return iso2Country(
      data.country ||
      data.country_code ||
      data.countryCode ||
      (data.shipping_address && (data.shipping_address.country || data.shipping_address.country_code)) ||
      (data.billing_address && (data.billing_address.country || data.billing_address.country_code)) ||
      (data.address && (data.address.country || data.address.country_code))
    );
  }

  function onKustomPostalChange(data) {
    var zip = extractZipFromUnknown(data);
    if (!zip) {
      return;
    }
    markKustomShippingKnown();
    onIframeZip(zip, extractCountryFromUnknown(data) || $('#billing_country').val());
  }

  function bindKustomZip() {
    if (typeof window._klarnaCheckout !== 'function') {
      return false;
    }
    // Register only `change`. A later api.on() for the same event replaces
    // that event; KCO's complete-address handlers must stay in place.
    // Postcode edits after a logged-in address already exists fire `change`
    // and often do not fire the complete-address events, so checkout stayed
    // on the old shipping while the cart drawer (fresh fragments) updated.
    window._klarnaCheckout(function (api) {
      if (!api || typeof api.on !== 'function') {
        return;
      }
      api.on({
        change: onKustomPostalChange
      });
    });
    return true;
  }

  function bindIframeZipMessages() {
    if (document.body.getAttribute('data-nh-iframe-zip-msg') === '1') {
      return;
    }
    document.body.setAttribute('data-nh-iframe-zip-msg', '1');
    window.addEventListener('message', function (e) {
      if (!snippetZipContextPresent()) {
        return;
      }
      var data = e && e.data;
      if (typeof data === 'string') {
        try {
          data = JSON.parse(data);
        } catch (err) {
          return;
        }
      }
      if (!data || typeof data !== 'object') {
        return;
      }
      if (!(
        data.postal_code ||
        data.postalCode ||
        data.shipping_address ||
        data.billing_address ||
        data.address ||
        (data.customer && (data.customer.postal_code || data.customer.postalCode))
      )) {
        return;
      }
      var zip = extractZipFromUnknown(data);
      if (!zip) {
        return;
      }
      onIframeZip(zip, extractCountryFromUnknown(data) || $('#billing_country').val());
    });
  }

  function snippetZipContextPresent() {
    return !!(
      document.querySelector(
        '#nh-checkout-iframe, .wc-svea-checkout-page, #svea-checkout-iframe-container, form.svea-checkout, form.kco-checkout, #kco-iframe, #klarna-checkout-container, #kustom-checkout-container'
      ) || document.body.classList.contains('nh-checkout--snippet')
    );
  }

  function bindIframeZipShipping() {
    if (!snippetZipContextPresent()) {
      return;
    }
    if (document.body.getAttribute('data-nh-iframe-zip') === '1') {
      bindSveaZip();
      bindKustomZip();
      return;
    }
    document.body.setAttribute('data-nh-iframe-zip', '1');
    watchHiddenSnippetPostcode();
    bindIframeZipMessages();
    bindSveaZip();
    bindKustomZip();
    $(document.body).on(
      'kco_customer_address_updated.nhZip kco_order_update.nhZip kco_checkout_update.nhZip',
      function (e, data) {
        var zip = extractZipFromUnknown(data);
        if (zip) {
          onIframeZip(zip, extractCountryFromUnknown(data) || $('#billing_country').val());
        }
      }
    );
    document.addEventListener('checkoutReady', function () {
      bindSveaZip();
      bindKustomZip();
    });
    var tries = 0;
    var timer = window.setInterval(function () {
      tries += 1;
      bindSveaZip();
      bindKustomZip();
      if (tries > 40) {
        window.clearInterval(timer);
      }
    }, 250);
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
        bindIframeZipShipping();
      }, 50);
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
    restoreCheckoutDraft();
    bindCheckoutSteps();
    bindTermsAgreement();
    refreshCheckoutChrome();
    hideCrispOnMobileCheckout();
    if (document.body.getAttribute('data-nh-crisp-hide') !== '1') {
      document.body.setAttribute('data-nh-crisp-hide', '1');
      [400, 1500, 4000].forEach(function (ms) {
        window.setTimeout(hideCrispOnMobileCheckout, ms);
      });
    }
    var pendingFocus = consumePaymentFocus();
    if (pendingFocus) {
      if (history.scrollRestoration) {
        history.scrollRestoration = 'manual';
      }
      schedulePaymentFocus(pendingFocus);
    }
  }

  /**
   * Svea’s plugin already inits on document.ready. Calling sveaCheckout() again
   * stacks order.validationCallback listeners, so one BankID payment creates
   * several Woo orders (only the last one is finalized).
   */
  function initSveaCheckoutOnce() {
    if (typeof $.fn.sveaCheckout !== 'function' || !iframeMarkupPresent()) {
      return;
    }
    var $page = $('.wc-svea-checkout-page');
    if (!$page.length || $page.get(0).sveaCheckout) {
      return;
    }
    $page.sveaCheckout();
  }

  stampShippingIndexes();

  $(boot);
  $(window).on('resize.nhCheckout', lockSummaryLayout);
  $(document.body).on('init_checkout', boot);
  $(document.body).on('updated_checkout', function () {
    refreshCheckoutChrome();
    initSveaCheckoutOnce();
    hideCrispOnMobileCheckout();
    window.setTimeout(function () {
      restoreMakecommerceMethod();
      applyCheckoutStep();
      unblockCheckout();
      placeShippingMethods();
    }, 0);
    window.setTimeout(function () {
      restoreMakecommerceMethod();
      applyCheckoutStep();
      ignoreAutoPaymentClick = false;
      syncStickyBar();
      placeShippingMethods();
    }, 120);
  });
  $(document.body).on('payment_method_selected', function () {
    if (ignoreAutoPaymentClick) {
      applyCheckoutStep();
      return;
    }
    syncSnippetCheckout();
    enhancePaymentCards();
    syncStickyBar();
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
