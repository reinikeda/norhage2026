(function (window) {
  if (!window.wp || !window.wp.data) return;

  const { select, subscribe } = window.wp.data;

  const STORE_KEYS = ['wc/store/cart', 'wc/store'];

  function getCartDataSafe() {
    for (let i = 0; i < STORE_KEYS.length; i++) {
      try {
        const store = select(STORE_KEYS[i]);
        if (store && typeof store.getCartData === 'function') {
          const data = store.getCartData();
          if (data) return data;
        }
      } catch (e) {}
    }
    return null;
  }

  function formatWeight(weight, unit) {
    const decimals = 2;
    const decSep = ',';
    const fixed = Number(weight || 0).toFixed(decimals);
    let [intPart, fracPart] = fixed.split('.');
    return intPart + decSep + fracPart + ' ' + unit;
  }

  // Build Woo wc-ajax endpoint url
  function wcAjaxEndpoint(endpoint) {
    const src =
      (window.wc_add_to_cart_params && window.wc_add_to_cart_params.wc_ajax_url) ||
      (window.wc_cart_fragments_params && window.wc_cart_fragments_params.wc_ajax_url) ||
      '/?wc-ajax=%%endpoint%%';
    return src.replace('%%endpoint%%', endpoint);
  }

  const WEIGHT_URL = wcAjaxEndpoint('nhgp_get_cart_weight');

  // Debounce helper
  function debounce(fn, wait) {
    let t = null;
    return function () {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(null, arguments), wait);
    };
  }

  async function fetchServerWeight(unitHint) {
    try {
      const url = WEIGHT_URL + (unitHint ? ('&unit=' + encodeURIComponent(unitHint)) : '');
      const resp = await fetch(url, { credentials: 'same-origin' });
      const json = await resp.json().catch(() => null);
      if (!json || json.success !== true || !json.data) return null;
      const w = Number(json.data.weight || 0);
      const u = String(json.data.unit || unitHint || 'kg');
      return { weight: w, unit: u };
    } catch (e) {
      return null;
    }
  }

  async function renderWeight() {
    const el = document.querySelector('.nhgp-cart-total-weight');
    if (!el) return;

    const unit = el.getAttribute('data-unit') || 'kg';

    // Always prefer server weight (it knows custom-cut line weights).
    const payload = await fetchServerWeight(unit);
    if (!payload) return;

    el.setAttribute('data-weight', String(payload.weight || 0));
    el.setAttribute('data-unit', payload.unit || unit);
    el.textContent = formatWeight(payload.weight, payload.unit || unit);
  }

  const renderWeightDebounced = debounce(renderWeight, 150);

  // Initial render
  document.addEventListener('DOMContentLoaded', function () {
    renderWeightDebounced();
  });

  // Re-render on Blocks cart store changes
  let lastSig = null;

  subscribe(function () {
    const cartData = getCartDataSafe();
    if (!cartData) return;

    // Use a stable signature so we don’t spam requests.
    // totals/itemsWeight may be wrong for custom-cut, but changes reliably when cart updates.
    const sig = [
      cartData.itemsCount,
      cartData.itemsWeight,
      cartData.totalItems,
      cartData.totalPrice
    ].join('|');

    if (sig === lastSig) return;
    lastSig = sig;

    renderWeightDebounced();
  });

  // Also listen for classic events / fragment refreshes
  document.addEventListener('wc_fragments_refreshed', renderWeightDebounced);
  document.addEventListener('updated_wc_div', renderWeightDebounced);
})(window);
