/**
 * Capture billing / Svea / Kustom iframe identity so recovery emails have an address.
 */
(function () {
  'use strict';

  var cfg = window.nhCartRecovery || {};
  var timer = null;
  var last = '';
  var kustomTries = 0;

  function val(id) {
    var el = document.getElementById(id);
    return el ? String(el.value || '').trim() : '';
  }

  function payloadFromDom() {
    return {
      email: val('billing_email'),
      first_name: val('billing_first_name'),
      last_name: val('billing_last_name')
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
      return String(data.value || data.email || data.firstName || data.lastName || data.given_name || data.family_name || '').trim();
    }
    return '';
  }

  function usableEmail(email) {
    email = String(email || '').trim();
    if (!email || email.indexOf('@') === -1 || email.indexOf('*') !== -1) {
      return '';
    }
    return email;
  }

  function identityFromPayload(data) {
    data = data || {};
    var nested = data.billing_address || data.shipping_address || data.customer || data.billingAddress || data.shippingAddress || {};
    return {
      email: usableEmail(data.email || nested.email || extract(data)),
      first_name: String(data.given_name || data.first_name || data.firstName || nested.given_name || nested.first_name || '').trim(),
      last_name: String(data.family_name || data.last_name || data.lastName || nested.family_name || nested.last_name || '').trim()
    };
  }

  function sync(extra) {
    extra = extra || {};
    var data = payloadFromDom();
    if (extra.email) {
      data.email = extra.email;
    }
    if (extra.first_name) {
      data.first_name = extra.first_name;
    }
    if (extra.last_name) {
      data.last_name = extra.last_name;
    }
    data.email = usableEmail(data.email);
    if (!data.email && !data.first_name && !data.last_name) {
      return;
    }
    var key = data.email + '|' + data.first_name + '|' + data.last_name;
    if (key === last) {
      return;
    }
    last = key;
    if (!cfg.ajax || !cfg.nonce) {
      return;
    }
    var body = new URLSearchParams();
    body.set('security', cfg.nonce);
    body.set('email', data.email);
    body.set('first_name', data.first_name);
    body.set('last_name', data.last_name);
    if (cfg.ajax.indexOf('admin-ajax.php') !== -1) {
      body.set('action', 'nh_cr_sync');
    }
    try {
      window.fetch(cfg.ajax, {
        method: 'POST',
        credentials: 'same-origin',
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
    if (ident.email || ident.first_name || ident.last_name) {
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
      if (val('billing_email') || val('billing_first_name')) {
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

  document.addEventListener('change', function (e) {
    var t = e.target;
    if (!t || !t.id) {
      return;
    }
    if (t.id === 'billing_email' || t.id === 'billing_first_name' || t.id === 'billing_last_name') {
      schedule();
    }
  });

  document.addEventListener('checkoutReady', function () {
    window.setTimeout(bindSvea, 50);
    window.setTimeout(bindKustom, 50);
  });
  if (window.scoApi) {
    bindSvea();
  }
  waitKustom();
  if (kustomIframePresent() || document.body.classList.contains('woocommerce-checkout')) {
    watchHiddenIdentity();
  }
})();
