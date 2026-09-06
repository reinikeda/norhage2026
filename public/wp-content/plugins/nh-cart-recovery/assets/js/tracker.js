/**
 * Capture billing / shipping calculator / Svea / Kustom identity so recovery
 * rows show who priced shipping even before an email is known.
 */
(function () {
  'use strict';

  var cfg = window.nhCartRecovery || {};
  var timer = null;
  var last = '';
  var kustomTries = 0;
  var sveaTries = 0;

  var FIELD_IDS = {
    email: ['billing_email'],
    first_name: ['billing_first_name'],
    last_name: ['billing_last_name'],
    postcode: ['calc_shipping_postcode', 'nh_sc_shipping_postcode', 'shipping_postcode', 'billing_postcode'],
    city: ['shipping_city', 'billing_city', 'calc_shipping_city'],
    country: ['calc_shipping_country', 'nh_sc_shipping_country', 'shipping_country', 'billing_country'],
    phone: ['billing_phone']
  };

  function val(id) {
    var el = document.getElementById(id);
    return el ? String(el.value || '').trim() : '';
  }

  function firstVal(ids) {
    var i;
    for (i = 0; i < ids.length; i += 1) {
      var v = val(ids[i]);
      if (v) {
        return v;
      }
    }
    return '';
  }

  function looksObfuscated(value) {
    value = String(value || '');
    return value.indexOf('*') !== -1 || value.indexOf('•') !== -1;
  }

  function usableEmail(email) {
    email = String(email || '').trim();
    if (!email || email.indexOf('@') === -1 || looksObfuscated(email)) {
      return '';
    }
    return email;
  }

  function usableText(value) {
    value = String(value || '').trim();
    if (!value || looksObfuscated(value)) {
      return '';
    }
    return value;
  }

  function payloadFromDom() {
    return {
      email: firstVal(FIELD_IDS.email),
      first_name: firstVal(FIELD_IDS.first_name),
      last_name: firstVal(FIELD_IDS.last_name),
      postcode: firstVal(FIELD_IDS.postcode),
      city: firstVal(FIELD_IDS.city),
      country: firstVal(FIELD_IDS.country),
      phone: firstVal(FIELD_IDS.phone)
    };
  }

  function extract(data) {
    if (data == null) {
      return '';
    }
    if (typeof data === 'string' || typeof data === 'number') {
      return String(data).trim();
    }
    if (typeof data === 'object') {
      return String(
        data.value ||
        data.email ||
        data.firstName ||
        data.lastName ||
        data.given_name ||
        data.family_name ||
        data.postalCode ||
        data.postal_code ||
        data.postcode ||
        data.phoneNumber ||
        data.phone ||
        ''
      ).trim();
    }
    return '';
  }

  function identityFromPayload(data) {
    data = data || {};
    var nested = data.billing_address || data.shipping_address || data.customer || data.billingAddress || data.shippingAddress || {};
    return {
      email: usableEmail(data.email || nested.email || extract(data)),
      first_name: String(data.given_name || data.first_name || data.firstName || nested.given_name || nested.first_name || '').trim(),
      last_name: String(data.family_name || data.last_name || data.lastName || nested.family_name || nested.last_name || '').trim(),
      postcode: usableText(data.postal_code || data.postalCode || data.postcode || nested.postal_code || nested.postalCode || nested.postcode || ''),
      city: usableText(data.city || nested.city || ''),
      country: usableText(data.country || data.country_code || data.countryCode || nested.country || nested.country_code || ''),
      phone: usableText(data.phone || data.phone_number || data.phoneNumber || nested.phone || nested.phone_number || '')
    };
  }

  function hasSignal(data) {
    return !!(data.email || data.first_name || data.last_name || data.postcode || data.city || data.phone);
  }

  function sync(extra) {
    extra = extra || {};
    var data = payloadFromDom();
    var key;
    for (key in extra) {
      if (Object.prototype.hasOwnProperty.call(extra, key) && extra[key]) {
        data[key] = extra[key];
      }
    }
    data.email = usableEmail(data.email);
    data.first_name = usableText(data.first_name);
    data.last_name = usableText(data.last_name);
    data.postcode = usableText(data.postcode);
    data.city = usableText(data.city);
    data.country = usableText(data.country);
    data.phone = usableText(data.phone);
    if (!data.postcode && !data.city) {
      data.country = '';
    }
    if (!hasSignal(data)) {
      return;
    }
    var stamp = [data.email, data.first_name, data.last_name, data.postcode, data.city, data.country, data.phone].join('|');
    if (stamp === last) {
      return;
    }
    last = stamp;
    if (!cfg.ajax || !cfg.nonce) {
      return;
    }
    var body = new URLSearchParams();
    body.set('security', cfg.nonce);
    body.set('email', data.email);
    body.set('first_name', data.first_name);
    body.set('last_name', data.last_name);
    body.set('postcode', data.postcode);
    body.set('city', data.city);
    body.set('country', data.country);
    body.set('phone', data.phone);
    if (cfg.ajax.indexOf('admin-ajax.php') !== -1) {
      body.set('action', 'nh_cr_sync');
    }
    try {
      window.fetch(cfg.ajax, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString()
      });
    } catch (e) {
      // ignore
    }
  }

  function schedule(extra) {
    window.clearTimeout(timer);
    timer = window.setTimeout(function () {
      sync(extra);
    }, 400);
  }

  function onKustomData(data) {
    var ident = identityFromPayload(data);
    if (hasSignal(ident)) {
      schedule(ident);
    }
  }

  function bindSvea() {
    var api = window.scoApi;
    if (!api || typeof api.observeEvent !== 'function' || window._nhCrSvea) {
      return false;
    }
    window._nhCrSvea = true;
    api.observeEvent('identity.email', function (data) {
      schedule({ email: extract(data) });
    });
    api.observeEvent('identity.firstName', function (data) {
      schedule({ first_name: extract(data) });
    });
    api.observeEvent('identity.lastName', function (data) {
      schedule({ last_name: extract(data) });
    });
    api.observeEvent('identity.postalCode', function (data) {
      schedule({ postcode: extract(data) });
    });
    api.observeEvent('identity.phoneNumber', function (data) {
      schedule({ phone: extract(data) });
    });
    return true;
  }

  function bindKustom() {
    var fn = window._klarnaCheckout || window._kustomCheckout;
    if (typeof fn !== 'function' || window._nhCrKustom) {
      return false;
    }
    window._nhCrKustom = true;
    try {
      fn(function (api) {
        if (!api || typeof api.on !== 'function') {
          return;
        }
        api.on({
          change: onKustomData,
          billing_address_change: onKustomData,
          shipping_address_change: onKustomData
        });
      });
    } catch (e) {
      window._nhCrKustom = false;
      return false;
    }
    return true;
  }

  function waitSvea() {
    if (bindSvea() || sveaTries > 60) {
      return;
    }
    sveaTries += 1;
    window.setTimeout(waitSvea, 500);
  }

  function waitKustom() {
    if (bindKustom() || kustomTries > 40) {
      return;
    }
    kustomTries += 1;
    window.setTimeout(waitKustom, 500);
  }

  function watchHiddenIdentity() {
    if (window._nhCrWatchId) {
      return;
    }
    window._nhCrWatchId = window.setInterval(function () {
      if (hasSignal(payloadFromDom())) {
        schedule();
      }
    }, 800);
  }

  function kustomIframePresent() {
    return !!(
      document.querySelector(
        '#klarna-checkout-container, #kco-wrapper, #kco-iframe, #kustom-checkout-container, ' +
        '.kco-iframe, iframe[src*="checkout.klarna"], iframe[src*="kustom."]'
      )
    );
  }

  function sveaIframePresent() {
    return !!(
      document.querySelector(
        '.wc-svea-checkout-page, #svea-checkout, #svea-checkout-iframe-container, form.svea-checkout, ' +
        'iframe[src*="svea.com"], iframe[src*="sveacheckout"]'
      )
    );
  }

  function isWatchedId(id) {
    var key;
    var ids;
    var i;
    for (key in FIELD_IDS) {
      if (!Object.prototype.hasOwnProperty.call(FIELD_IDS, key)) {
        continue;
      }
      ids = FIELD_IDS[key];
      for (i = 0; i < ids.length; i += 1) {
        if (ids[i] === id) {
          return true;
        }
      }
    }
    return false;
  }

  document.addEventListener('change', function (e) {
    var t = e.target;
    if (!t || !t.id) {
      return;
    }
    if (isWatchedId(t.id)) {
      schedule();
    }
  });

  document.addEventListener('blur', function (e) {
    var t = e.target;
    if (!t || !t.id) {
      return;
    }
    if (isWatchedId(t.id)) {
      schedule();
    }
  }, true);

  function onCheckoutReady() {
    window.setTimeout(bindSvea, 50);
    window.setTimeout(bindKustom, 50);
  }
  document.addEventListener('checkoutReady', onCheckoutReady);
  window.addEventListener('checkoutReady', onCheckoutReady);

  if (window.jQuery) {
    window.jQuery(document.body).on('updated_checkout updated_wc_div updated_shipping_method', function () {
      schedule();
    });
  }

  if (window.scoApi) {
    bindSvea();
  }
  waitSvea();
  waitKustom();
  if (kustomIframePresent() || sveaIframePresent() || document.body.classList.contains('woocommerce-checkout') || document.body.classList.contains('woocommerce-cart')) {
    watchHiddenIdentity();
  }
})();
