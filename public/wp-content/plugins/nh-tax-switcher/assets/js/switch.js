(function () {
  if (typeof NHTaxSwitcher === "undefined") return;

  const KEY   = NHTaxSwitcher.cookieKey || "nh_tax_display";
  const DAYS  = Number(NHTaxSwitcher.cookie_days || 365);
  let   MODE  = (NHTaxSwitcher.mode === "excl") ? "excl" : "incl";

  function setCookie(name, value, days) {
    const d = new Date();
    d.setTime(d.getTime() + (days*24*60*60*1000));
    // Path=/ is crucial so Woo sees it across category/single routes
    document.cookie = `${name}=${value};expires=${d.toUTCString()};path=/;SameSite=Lax`;
  }

  function getAllPriceNodes() {
    return Array.from(document.querySelectorAll('.nh-tax-price[data-product-id]'));
  }

  function getAllPriceIds() {
    return Array.from(new Set(getAllPriceNodes().map(n => {
      const id = parseInt(n.getAttribute('data-product-id'), 10);
      return isNaN(id) ? null : id;
    }).filter(Boolean)));
  }

  function setPricesOpacity(opacity) {
    getAllPriceNodes().forEach(n => {
      n.style.transition = 'opacity .18s ease';
      n.style.opacity = opacity;
    });
  }

  function updateAriaPressed(btn, newMode) {
    btn.setAttribute('aria-pressed', newMode === 'incl' ? 'true' : 'false');
    const sr = btn.querySelector('.nh-tax-visuallyhidden');
    if (sr) sr.textContent = (newMode === 'incl') ? (NHTaxSwitcher.label_in || 'Including VAT')
                                                  : (NHTaxSwitcher.label_ex || 'Excluding VAT');
  }

  async function fetchNewPrices(mode, ids) {
    try {
      const body = new URLSearchParams();
      body.set('action', 'nh_tax_get_prices');
      body.set('mode', mode);
      body.set('nonce', NHTaxSwitcher.nonce || '');
      ids.forEach(id => body.append('ids[]', String(id)));

      const res = await fetch(NHTaxSwitcher.ajax_url, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: body.toString(),
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (!data || !data.success || !data.data || !data.data.prices) return {};
      return data.data.prices;
    } catch (_) {
      return {};
    }
  }

  // Fallback: fetch current page HTML and copy over all .nh-tax-price contents (no full reload)
  async function fallbackSwapFromFullHTML() {
    const res = await fetch(window.location.href, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const html = await res.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');

    const newNodes = doc.querySelectorAll('.nh-tax-price[data-product-id]');
    if (!newNodes.length) return false;

    // Build a quick lookup by product id
    const map = {};
    newNodes.forEach(n => {
      const pid = parseInt(n.getAttribute('data-product-id'), 10);
      if (!isNaN(pid)) map[pid] = n.innerHTML;
    });

    // Swap on current page
    let swapped = 0;
    document.querySelectorAll('.nh-tax-price[data-product-id]').forEach(n => {
      const pid = parseInt(n.getAttribute('data-product-id'), 10);
      if (!isNaN(pid) && typeof map[pid] !== 'undefined') {
        n.innerHTML = map[pid];
        swapped++;
      }
    });

    return swapped > 0;
  }

  async function runToggle(btn) {
    const nextMode = (MODE === 'incl') ? 'excl' : 'incl';

    // Persist cookie for the page user so the server renders that mode
    setCookie(KEY, nextMode, DAYS);
    MODE = nextMode;

    // Update switch state immediately
    updateAriaPressed(btn, nextMode);

    // If there are no price wrappers (shouldn't happen), stop quietly
    const ids = getAllPriceIds();
    if (!ids.length) return;

    // Visual feedback while loading
    btn.disabled = true;
    setPricesOpacity(0.3);

    try {
      // First try the lightweight AJAX price endpoint
      const map = await fetchNewPrices(nextMode, ids);

      // If AJAX returned nothing (theme/custom HTML), do the robust fallback
      const needFallback = !map || Object.keys(map).length === 0;

      if (needFallback) {
        await fallbackSwapFromFullHTML();
      } else {
        // Swap html by product id
        document.querySelectorAll('.nh-tax-price[data-product-id]').forEach(n => {
          const pid = parseInt(n.getAttribute('data-product-id'), 10);
          if (!isNaN(pid) && typeof map[pid] !== 'undefined') {
            n.innerHTML = map[pid];
          }
        });
      }
    } catch (_) {
      // Final guard: try the fallback once more
      try { await fallbackSwapFromFullHTML(); } catch (_) {}
    } finally {
      setTimeout(() => setPricesOpacity(1), 50);
      btn.disabled = false;
    }
  }

  function init() {
    document.querySelectorAll('.nh-tax-switch').forEach(btn => {
      btn.addEventListener('click', () => runToggle(btn));
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
