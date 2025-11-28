/**
 * Helper: translation lookup
 */
const nhfT = (key, fallback) =>
  (window.nhfL10n && window.nhfL10n[key]) || fallback;

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

      // close all others
      document.querySelectorAll('.nhf-cat-item.is-open').forEach(openItem => {
        openItem.classList.remove('is-open');
        openItem.querySelector('.nhf-cat-toggle').setAttribute('aria-expanded', 'false');
        const sub = openItem.querySelector('.nhf-cat-sub');
        if (sub) sub.setAttribute('aria-hidden', 'true');
      });

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
      const body    = section.querySelector('.nhf-filter-body');

      const wasOpen = section.classList.contains('is-open');

      // If we are closing, move focus OUT of the body to the toggle
      // so that no focused element ends up inside aria-hidden="true"
      if (wasOpen) {
        toggle.focus();
      }

      section.classList.toggle('is-open');
      const isOpen = section.classList.contains('is-open');

      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (body) {
        body.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
      }
    });

    // Initial ARIA state on load
    const section = toggle.closest('.nhf-filter');
    const body    = section.querySelector('.nhf-filter-body');
    const isOpen  = section.classList.contains('is-open');

    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (body) {
      body.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    }
  });
});

/**
 * Submit form when checkboxes change (desktop)
 */
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('.nhf-form');
  if (!form) return;

  // Auto-submit on checkbox change (desktop)
  form.querySelectorAll('input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', () => form.submit());
  });

  // Submit on Enter in number fields (price, if used later)
  form.querySelectorAll('input[type="number"]').forEach(inp => {
    inp.addEventListener('keydown', e => {
      if (e.key === 'Enter') form.submit();
    });
  });
});

/**
 * Keep filter groups open if active (checked values / price)
 */
document.addEventListener('DOMContentLoaded', () => {
  const sections = document.querySelectorAll('.nhf-filter');

  sections.forEach(section => {
    const body   = section.querySelector('.nhf-filter-body');
    const toggle = section.querySelector('.nhf-filter-toggle');

    const hasChecked = !!section.querySelector('input[type="checkbox"]:checked');
    const hasPrice   = !!section.querySelector('input[type="number"][name="price_min"], input[type="number"][name="price_max"]');

    const hasPriceVal = hasPrice && (
      (section.querySelector('input[name="price_min"]')?.value !== '') ||
      (section.querySelector('input[name="price_max"]')?.value !== '')
    );

    const isActive = hasChecked || hasPriceVal;

    if (isActive) {
      section.classList.add('is-open', 'is-active-group');
      toggle?.setAttribute('aria-expanded', 'true');
      body?.setAttribute('aria-hidden', 'false');
    } else {
      section.classList.toggle('is-active-group', false);
      // don't force closed/open here, just don't mark as active
    }

    // Update "active-group" state when checkboxes change
    section.querySelectorAll('input[type="checkbox"]').forEach(cb => {
      cb.addEventListener('change', () => {
        const nowActive = !!section.querySelector('input[type="checkbox"]:checked');
        section.classList.toggle('is-active-group', nowActive);
      });
    });

    // Update "active-group" state when price inputs change
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

/**
 * Mobile bar padding
 */
document.addEventListener('DOMContentLoaded', () => {
  const bar = document.querySelector('.nhf-mobilebar');
  if (!bar) return;

  const setH = () =>
    document.documentElement.style.setProperty('--nhf-mb-h', `${bar.offsetHeight || 60}px`);

  setH();
  new ResizeObserver(setH).observe(bar);
  document.body.classList.add('nhf-has-mobilebar');
});

/**
 * MOBILE FILTER UX
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
          <button class="nhf-drawer__close" aria-label="${nhfT('close','Close')}" data-close="1">✕</button>
        </div>
        <div class="nhf-drawer__body"></div>
        <div class="nhf-drawer__footer">
          ${footerHTML}
        </div>
      </div>
    `;
    document.body.appendChild(wrap);

    // Focus trap + ESC close
    wrap.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeDrawer(wrap, true);
      if (e.key !== 'Tab') return;

      const focusables = qsa('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])', wrap)
        .filter(el => !el.disabled && el.offsetParent !== null);

      if (!focusables.length) return;

      const first = focusables[0], last = focusables[focusables.length-1];

      if (e.shiftKey && document.activeElement === first) {
        last.focus(); e.preventDefault();
      } else if (!e.shiftKey && document.activeElement === last) {
        first.focus(); e.preventDefault();
      }
    });

    // Click backdrop / close button
    wrap.addEventListener('click', (e) => {
      if (e.target.dataset.close) closeDrawer(wrap, true);
    });

    return wrap;
  }

  function openDrawer(el, openerBtn) {
    openedByBtn = openerBtn || null;
    el.classList.add('is-open');
    lockBody(true);

    setTimeout(()=> { qs('.nhf-drawer__panel', el)?.focus(); }, 10);

    updateBadge();
  }

  function closeDrawer(el, restoreFocus) {
    el.classList.remove('is-open');
    lockBody(false);
    if (restoreFocus && openedByBtn) openedByBtn.focus();
  }

  function activeCountInForm(ctx) {
    return qsa('input[type="checkbox"]:checked', ctx).length;
  }

  function updateBadge() {
    if (!badgeEl || !filtersFormClone) return;
    const n = activeCountInForm(filtersFormClone);
    badgeEl.textContent = n;
    badgeEl.style.display = n > 0 ? 'inline-flex' : 'none';
  }

  function preventDesktopAutoSubmitOnMobile() {
    if (!filtersFormClone) return;
    // On mobile we DON'T auto-submit; just update badge
    qsa('.nhf-form input[type="checkbox"]', filtersFormClone).forEach(cb=>{
      cb.addEventListener('change', () => updateBadge());
    });
  }

  function buildMobileUI() {
    const bar = document.createElement('div');
    bar.className = 'nhf-mobilebar';
    bar.innerHTML = `
      <button class="nhf-mb-btn" id="nhf-mb-cats" aria-controls="nhf-drawer-cats">
        <span class="nhf-mb-icon">📂</span>
        <span class="nhf-mb-label">${nhfT('categories','Categories')}</span>
      </button>

      <button class="nhf-mb-btn" id="nhf-mb-filters" aria-controls="nhf-drawer-filters" aria-live="polite">
        <span class="nhf-mb-icon">⚙️</span>
        <span class="nhf-mb-label">${nhfT('filters','Filters')}</span>
        <span class="nhf-badge" id="nhf-badge" style="display:none">0</span>
      </button>
    `;
    document.body.appendChild(bar);

    badgeEl = qs('#nhf-badge', bar);

    filtersDrawer = createDrawer(
      'nhf-drawer-filters',
      nhfT('filterProducts','Filter Products'),
      `
        <button type="button" class="nhf-drawer__reset" data-action="reset">
          ${nhfT('reset','Reset')}
        </button>
        <button type="button" class="nhf-drawer__apply" data-action="apply">
          ${nhfT('apply','Apply')}
        </button>
      `
    );

    catsDrawer = createDrawer(
      'nhf-drawer-cats',
      nhfT('categories','Categories'),
      ``
    );

    const sidebar = qs('#nhf-sidebar');
    const filters = qs('.nhf-filters', sidebar);
    const cats    = qs('.nhf-cat-list', sidebar);

    filtersFormClone = filters ? filters.querySelector('form').cloneNode(true) : null;

    if (filtersFormClone) {
      // keep active groups open
      qsa('.nhf-filter', filtersFormClone).forEach(sec => {
        const hasChecked = !!sec.querySelector('input[type="checkbox"]:checked');
        if (hasChecked) {
          sec.classList.add('is-open', 'is-active-group');
          sec.querySelector('.nhf-filter-body')?.setAttribute('aria-hidden','false');
          sec.querySelector('.nhf-filter-toggle')?.setAttribute('aria-expanded','true');
        }
      });

      qs('.nhf-drawer__body', filtersDrawer).appendChild(filtersFormClone);

      // Remove desktop apply bar/buttons inside clone
      qsa('.nhf-applybar, .nhf-reset, .nhf-apply, .nhf-applybtn', filtersFormClone)
        .forEach(el => el.remove());
    }

    catsClone = cats ? cats.cloneNode(true) : null;
    if (catsClone) {
      qs('.nhf-drawer__body', catsDrawer).appendChild(catsClone);
    }

    const catsBtn    = qs('#nhf-mb-cats', bar);
    const filtersBtn = qs('#nhf-mb-filters', bar);

    catsBtn.addEventListener('click', ()=> openDrawer(catsDrawer, catsBtn));
    filtersBtn.addEventListener('click', ()=> openDrawer(filtersDrawer, filtersBtn));

    // Footer buttons
    filtersDrawer.addEventListener('click', (e)=>{
      const act = e.target?.dataset?.action;
      if (!act) return;

      if (act === 'reset') {
        const base = (qs('.nhf-form', filtersFormClone)?.getAttribute('action')) || window.location.pathname;
        window.location.href = base;
      }

      if (act === 'apply') {
        qs('.nhf-form', filtersDrawer)?.submit();
      }
    });

    // Update badge when toggling checkboxes (mobile only)
    preventDesktopAutoSubmitOnMobile();
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
      if (!initialized) {
        buildMobileUI();
        initialized = true;
      }
    } else {
      if (initialized) destroyMobileUI();
    }
  }

  onChange(mq);
  mq.addEventListener('change', onChange);
})();
