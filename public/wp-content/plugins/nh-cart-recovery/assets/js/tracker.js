/**
 * Capture billing / Svea iframe identity so recovery emails have an address.
 */
(function () {
  'use strict';

  var cfg = window.nhCartRecovery || {};
  var timer = null;
  var last = '';

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
      return String(data.value || data.email || data.firstName || data.lastName || '').trim();
    }
    return '';
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
  });
  if (window.scoApi) {
    bindSvea();
  }
})();
