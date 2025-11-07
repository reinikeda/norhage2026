/**
 * Category accordion logic
 */
document.addEventListener('DOMContentLoaded', () => {
  const toggles = document.querySelectorAll('.nhf-cat-toggle');

  toggles.forEach(toggle => {
    toggle.addEventListener('click', e => {
      e.preventDefault();

      const item = toggle.closest('.nhf-cat-item');
      const wasOpen = item.classList.contains('is-open');

      // Close all
      document.querySelectorAll('.nhf-cat-item.is-open').forEach(openItem => {
        openItem.classList.remove('is-open');
        openItem.querySelector('.nhf-cat-toggle').setAttribute('aria-expanded', 'false');
        const sub = openItem.querySelector('.nhf-cat-sub');
        if (sub) sub.setAttribute('aria-hidden', 'true');
      });

      // Reopen clicked if it was closed
      if (!wasOpen) {
        item.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        const sub = item.querySelector('.nhf-cat-sub');
        if (sub) sub.setAttribute('aria-hidden', 'false');
      }
    });
  });
});

/**
 * Filter Accordion (multi-open)
 */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.nhf-filter-toggle').forEach(toggle => {
    toggle.addEventListener('click', e => {
      e.preventDefault();
      const section = toggle.closest('.nhf-filter');
      const body = section.querySelector('.nhf-filter-body');

      // toggle only this section (do NOT close others)
      section.classList.toggle('is-open');

      // ARIA for accessibility
      const isOpen = section.classList.contains('is-open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (body) body.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    });

    // initialize ARIA on load based on markup class
    const section = toggle.closest('.nhf-filter');
    const body = section.querySelector('.nhf-filter-body');
    const isOpen = section.classList.contains('is-open');
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (body) body.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
  });
});

/**
 * Submit form when checkboxes change (desktop)
 */
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('.nhf-form');
  if (!form) return;

  // autosubmit for checkboxes
  form.querySelectorAll('input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', () => form.submit());
  });

  // optional: submit when pressing Enter inside price fields
  form.querySelectorAll('input[type="number"]').forEach(inp => {
    inp.addEventListener('keydown', e => {
      if (e.key === 'Enter') form.submit();
    });
  });
});

/**
 * Keep filter groups open if they contain a selection
 * and add a subtle "active" highlight on the header.
 */
document.addEventListener('DOMContentLoaded', () => {
  const sections = document.querySelectorAll('.nhf-filter');

  sections.forEach(section => {
    const body   = section.querySelector('.nhf-filter-body');
    const toggle = section.querySelector('.nhf-filter-toggle');

    // Detect if this group has any active selection
    const hasChecked = !!section.querySelector('input[type="checkbox"]:checked');
    const hasPrice   = !!section.querySelector('input[type="number"][name="price_min"], input[type="number"][name="price_max"]'); // for future price
    const hasPriceVal = hasPrice && (
      (section.querySelector('input[name="price_min"]') && section.querySelector('input[name="price_min"]').value !== '') ||
      (section.querySelector('input[name="price_max"]') && section.querySelector('input[name="price_max"]').value !== '')
    );

    const isActive = hasChecked || hasPriceVal;

    if (isActive) {
      // Ensure it's open on load
      section.classList.add('is-open', 'is-active-group');
      if (toggle) toggle.setAttribute('aria-expanded', 'true');
      if (body)   body.setAttribute('aria-hidden', 'false');
    } else {
      section.classList.toggle('is-active-group', false);
      // do not force-close here; respect whatever open/closed state markup had
    }

    // Keep the "active" highlight in sync when user checks/unchecks
    section.querySelectorAll('input[type="checkbox"]').forEach(cb => {
      cb.addEventListener('change', () => {
        const nowActive = !!section.querySelector('input[type="checkbox"]:checked');
        section.classList.toggle('is-active-group', nowActive);
      });
    });

    // (Optional future) If you re-enable price inputs, keep highlight in sync
    section.querySelectorAll('input[type="number"]').forEach(inp => {
      inp.addEventListener('input', () => {
        const minVal = section.querySelector('input[name="price_min"]')?.value || '';
        const maxVal = section.querySelector('input[name="price_max"]')?.value || '';
        const nowActive = (minVal !== '' || maxVal !== '');
        section.classList.toggle('is-active-group', nowActive);
      });
    });
  });
});

// padding only applies when the bar exists
document.addEventListener('DOMContentLoaded', () => {
  const bar = document.querySelector('.nhf-mobilebar');
  if (!bar) return;
  // keep the variable accurate if the bar’s height changes
  const setH = () => document.documentElement.style.setProperty('--nhf-mb-h', `${bar.offsetHeight || 60}px`);
  setH();
  new ResizeObserver(setH).observe(bar);
  document.body.classList.add('nhf-has-mobilebar'); // triggers the reserved space
});


/**
 * =========================================================
 *  MOBILE FILTER UX: bottom bar + full-screen drawers
 * =========================================================
 */
(function(){
  const mq = window.matchMedia('(max-width: 992px)');
  let initialized = false;
  let filtersDrawer, catsDrawer, badgeEl, filtersFormClone, catsClone;
  let openedByBtn = null;

  function qs(sel, ctx=document){ return ctx.querySelector(sel); }
  function qsa(sel, ctx=document){ return Array.from(ctx.querySelectorAll(sel)); }

  function lockBody(lock) {
    document.documentElement.classList.toggle('nhf-lock', lock);
    document.body.classList.toggle('nhf-lock', lock);
  }

  function createDrawer(id, title, footerHTML='') {
    const wrap = document.createElement('div');
    wrap.className = 'nhf-drawer';
    wrap.id = id;
    wrap.setAttribute('role','dialog');
    wrap.setAttribute('aria-modal','true');
    wrap.innerHTML = `
      <div class="nhf-drawer__backdrop" data-close="1"></div>
      <div class="nhf-drawer__panel" tabindex="-1">
        <div class="nhf-drawer__header">
          <div class="nhf-drawer__title">${title}</div>
          <button class="nhf-drawer__close" aria-label="Close" data-close="1">✕</button>
        </div>
        <div class="nhf-drawer__body"></div>
        <div class="nhf-drawer__footer">
          ${footerHTML}
        </div>
      </div>
    `;
    document.body.appendChild(wrap);
    // basic focus trap
    wrap.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeDrawer(wrap, true);
      if (e.key !== 'Tab') return;
      const focusables = qsa('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])', wrap)
        .filter(el => !el.hasAttribute('disabled') && el.offsetParent !== null);
      if (!focusables.length) return;
      const first = focusables[0], last = focusables[focusables.length-1];
      if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
      else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
    });
    // close handlers
    wrap.addEventListener('click', (e) => {
      if (e.target.dataset.close) closeDrawer(wrap, true);
    });
    // swipe-down to close
    let startY = null;
    const panel = qs('.nhf-drawer__panel', wrap);
    panel.addEventListener('touchstart', (e)=>{ startY = e.touches[0].clientY; }, {passive:true});
    panel.addEventListener('touchmove', (e)=>{
      if (startY === null) return;
      const dy = e.touches[0].clientY - startY;
      if (dy > 70) { closeDrawer(wrap, true); startY = null; }
    }, {passive:true});
    return wrap;
  }

  function openDrawer(el, openerBtn) {
    openedByBtn = openerBtn || null;
    el.classList.add('is-open');
    lockBody(true);
    // focus panel
    setTimeout(()=> { qs('.nhf-drawer__panel', el)?.focus(); }, 10);
    // update badge on open
    updateBadge();
  }

  function closeDrawer(el, restoreFocus) {
    el.classList.remove('is-open');
    lockBody(false);
    if (restoreFocus && openedByBtn) { openedByBtn.focus(); }
  }

  function activeCountInForm(ctx) {
    const cbs = qsa('input[type="checkbox"]:checked', ctx);
    // if you re-enable price later, include number fields that have values
    return cbs.length;
  }

  function updateBadge() {
    if (!badgeEl || !filtersFormClone) return;
    const n = activeCountInForm(filtersFormClone);
    badgeEl.textContent = n;
    badgeEl.style.display = n > 0 ? 'inline-flex' : 'none';
  }

  function preventDesktopAutoSubmitOnMobile() {
    // If your desktop code auto-submits on checkbox change, disable it on mobile by stopping propagation here.
    qsa('.nhf-form input[type="checkbox"]', filtersFormClone).forEach(cb=>{
      cb.addEventListener('change', (e)=>{ /* no auto-submit on mobile */ updateBadge(); });
    });
  }

  function buildMobileUI() {
    // Bottom bar
    const bar = document.createElement('div');
    bar.className = 'nhf-mobilebar';
    bar.innerHTML = `
      <button class="nhf-mb-btn" id="nhf-mb-cats" aria-controls="nhf-drawer-cats">
        <span class="nhf-mb-icon">📂</span><span class="nhf-mb-label">Categories</span>
      </button>
      <button class="nhf-mb-btn" id="nhf-mb-filters" aria-controls="nhf-drawer-filters" aria-live="polite">
        <span class="nhf-mb-icon">⚙️</span><span class="nhf-mb-label">Filters</span>
        <span class="nhf-badge" id="nhf-badge" style="display:none">0</span>
      </button>
    `;
    document.body.appendChild(bar);
    badgeEl = qs('#nhf-badge', bar);

    // Drawers
    filtersDrawer = createDrawer(
      'nhf-drawer-filters',
      'Filter Products',
      `<button type="button" class="nhf-drawer__reset" data-action="reset">Reset</button>
       <button type="button" class="nhf-drawer__apply" data-action="apply">Apply</button>`
    );
    catsDrawer = createDrawer('nhf-drawer-cats', 'Categories', ``); // no footer

    // Clone content
    const sidebar = qs('#nhf-sidebar');
    const filters = qs('.nhf-filters', sidebar);
    const cats = qs('.nhf-cat-list', sidebar);

    // Clone the FILTER FORM (so it keeps names/GET params)
    filtersFormClone = filters ? filters.querySelector('form').cloneNode(true) : null;
    // keep groups with selections open (your existing JS will also handle this, but we ensure it now)
    if (filtersFormClone) {
      qsa('.nhf-filter', filtersFormClone).forEach(sec => {
        const hasChecked = !!sec.querySelector('input[type="checkbox"]:checked');
        if (hasChecked) sec.classList.add('is-open','is-active-group');
        const body = qs('.nhf-filter-body', sec);
        const toggle = qs('.nhf-filter-toggle', sec);
        if (hasChecked) {
          body && body.setAttribute('aria-hidden','false');
          toggle && toggle.setAttribute('aria-expanded','true');
        }
      });
      qs('.nhf-drawer__body', filtersDrawer).appendChild(filtersFormClone);

      // Remove duplicate desktop reset/apply buttons from cloned form
      qsa('.nhf-applybar, .nhf-reset, .nhf-apply, .nhf-applybtn', filtersFormClone).forEach(el => el.remove());
    }

    // Clone CATEGORIES block (list only; header is drawer title)
    catsClone = cats ? cats.cloneNode(true) : null;
    if (catsClone) {
      qs('.nhf-drawer__body', catsDrawer).appendChild(catsClone);
    }

    // Wire bottom bar buttons
    const catsBtn = qs('#nhf-mb-cats', bar);
    const filtersBtn = qs('#nhf-mb-filters', bar);
    catsBtn.addEventListener('click', ()=> openDrawer(catsDrawer, catsBtn));
    filtersBtn.addEventListener('click', ()=> openDrawer(filtersDrawer, filtersBtn));

    // Drawer footer actions
    filtersDrawer.addEventListener('click', (e)=>{
      const act = e.target?.dataset?.action;
      if (!act) return;
      if (act === 'reset') {
        // go to base archive URL (no params)
        const base = (qs('.nhf-form', filtersFormClone)?.getAttribute('action')) || window.location.pathname;
        window.location.href = base;
      }
      if (act === 'apply') {
        // submit cloned form
        qs('.nhf-form', filtersDrawer)?.submit();
      }
    });

    // Since this is MOBILE, disable auto-submit-on-change; user taps Apply
    preventDesktopAutoSubmitOnMobile();

    // Update badge initially & on changes
    updateBadge();
    filtersDrawer.addEventListener('change', (e)=>{
      if (e.target.matches('input[type="checkbox"]')) updateBadge();
    });
  }

  function destroyMobileUI() {
    qsa('.nhf-mobilebar, .nhf-drawer').forEach(el => el.remove());
    document.documentElement.classList.remove('nhf-lock');
    document.body.classList.remove('nhf-lock');
    initialized = false;
    filtersDrawer = catsDrawer = badgeEl = filtersFormClone = catsClone = null;
  }

  function onChange(e) {
    if (e.matches) {
      if (!initialized) { buildMobileUI(); initialized = true; }
    } else {
      if (initialized) destroyMobileUI();
    }
  }

  // Init now and on viewport changes
  onChange(mq);
  mq.addEventListener('change', onChange);
})();
