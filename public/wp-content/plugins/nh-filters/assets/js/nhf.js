const nhfT = (key, fallback) =>
  (window.nhfL10n && window.nhfL10n[key]) || fallback;

(function () {
  const mq = window.matchMedia('(max-width: 992px)');

  let initializedMobileUI = false;
  let mobileBar = null;
  let filtersDrawer = null;
  let filtersFormClone = null;
  let badgeEl = null;
  let openedByBtn = null;

  const qs = (sel, ctx = document) => (ctx ? ctx.querySelector(sel) : null);
  const qsa = (sel, ctx = document) =>
    ctx ? Array.from(ctx.querySelectorAll(sel)) : [];

  function lockBody(lock) {
    document.documentElement.classList.toggle('nhf-lock', lock);
    document.body.classList.toggle('nhf-lock', lock);
  }

  function setFilterSectionAria(section) {
    if (!section) return;

    const toggle = qs('.nhf-filter-toggle', section);
    const body = qs('.nhf-filter-body', section);
    const isOpen = section.classList.contains('is-open');

    if (toggle) {
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    if (body) {
      body.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    }
  }

  function hasActiveFilters(section) {
    if (!section) return false;

    const hasChecked = !!qs('input[type="checkbox"]:checked', section);
    const priceMin = qs('input[name="price_min"]', section)?.value?.trim() || '';
    const priceMax = qs('input[name="price_max"]', section)?.value?.trim() || '';

    return hasChecked || priceMin !== '' || priceMax !== '';
  }

  function syncFilterSectionState(section, { openIfActive = false } = {}) {
    if (!section) return;

    const isActive = hasActiveFilters(section);

    section.classList.toggle('is-active-group', isActive);

    if (openIfActive && isActive) {
      section.classList.add('is-open');
    }

    setFilterSectionAria(section);
  }

  function initializeFilterSections(root) {
    qsa('.nhf-filter', root).forEach((section) => {
      syncFilterSectionState(section, { openIfActive: true });
    });
  }

  function updateSectionFromInput(input) {
    const section = input.closest('.nhf-filter');
    if (section) {
      syncFilterSectionState(section);
    }
  }

  function activeCountInForm(ctx) {
    return qsa('input[type="checkbox"]:checked', ctx).length;
  }

  function updateBadge() {
    if (!badgeEl || !filtersFormClone) return;

    const count = activeCountInForm(filtersFormClone);
    badgeEl.textContent = String(count);
    badgeEl.style.display = count > 0 ? 'inline-flex' : 'none';
  }

  function createDrawer(id, title, footerHTML = '') {
    const wrap = document.createElement('div');
    wrap.className = 'nhf-drawer';
    wrap.id = id;
    wrap.setAttribute('role', 'dialog');
    wrap.setAttribute('aria-modal', 'true');

    wrap.innerHTML = `
      <div class="nhf-drawer__backdrop" data-close="1"></div>
      <div class="nhf-drawer__panel" tabindex="-1">
        <div class="nhf-drawer__header">
          <div class="nhf-drawer__title">${title}</div>
          <button
            type="button"
            class="nhf-drawer__close"
            aria-label="${nhfT('close', 'Close')}"
            data-close="1"
          >✕</button>
        </div>
        <div class="nhf-drawer__body"></div>
        <div class="nhf-drawer__footer">
          ${footerHTML}
        </div>
      </div>
    `;

    document.body.appendChild(wrap);

    wrap.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeDrawer(wrap, true);
        return;
      }

      if (e.key !== 'Tab') return;

      const focusables = qsa(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
        wrap
      ).filter((el) => !el.disabled && el.offsetParent !== null);

      if (!focusables.length) return;

      const first = focusables[0];
      const last = focusables[focusables.length - 1];

      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });

    wrap.addEventListener('click', (e) => {
      if (e.target?.dataset?.close) {
        closeDrawer(wrap, true);
      }
    });

    return wrap;
  }

  function setOpenerExpanded(expanded) {
    if (!openedByBtn) return;
    openedByBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  }

  function openDrawer(drawer, openerBtn = null) {
    if (!drawer) return;

    openedByBtn = openerBtn;
    drawer.classList.add('is-open');
    setOpenerExpanded(true);
    lockBody(true);

    requestAnimationFrame(() => {
      qs('.nhf-drawer__panel', drawer)?.focus();
    });

    updateBadge();
  }

  function closeDrawer(drawer, restoreFocus = false) {
    if (!drawer) return;

    drawer.classList.remove('is-open');
    setOpenerExpanded(false);
    lockBody(false);

    if (restoreFocus && openedByBtn) {
      openedByBtn.focus();
    }
  }

  function buildMobileUI() {
    const sidebar = qs('#nhf-sidebar');
    const filters = sidebar ? qs('.nhf-filters', sidebar) : null;
    const originalForm = filters ? qs('.nhf-form', filters) : null;

    if (!sidebar || !filters || !originalForm) return;

    mobileBar = document.createElement('div');
    mobileBar.className = 'nhf-mobilebar';
    mobileBar.innerHTML = `
      <button
        type="button"
        class="nhf-mb-btn nhf-mb-btn--filters"
        id="nhf-mb-filters"
        aria-controls="nhf-drawer-filters"
        aria-expanded="false"
        aria-live="polite"
      >
        <span class="nhf-mb-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="4" y1="6" x2="20" y2="6"></line>
            <line x1="8" y1="12" x2="16" y2="12"></line>
            <line x1="11" y1="18" x2="13" y2="18"></line>
          </svg>
        </span>
        <span class="nhf-mb-label">${nhfT('filters', 'Filters')}</span>
        <span class="nhf-badge" id="nhf-badge" style="display:none">0</span>
      </button>
    `;
    document.body.appendChild(mobileBar);

    badgeEl = qs('#nhf-badge', mobileBar);

    filtersDrawer = createDrawer(
      'nhf-drawer-filters',
      nhfT('filterProducts', 'Filter Products'),
      `
        <button type="button" class="nhf-drawer__reset" data-action="reset">
          ${nhfT('reset', 'Reset')}
        </button>
        <button type="button" class="nhf-drawer__apply" data-action="apply">
          ${nhfT('apply', 'Apply')}
        </button>
      `
    );

    filtersFormClone = originalForm.cloneNode(true);

    qsa('.nhf-applybar', filtersFormClone).forEach((el) => el.remove());

    initializeFilterSections(filtersFormClone);

    qs('.nhf-drawer__body', filtersDrawer)?.appendChild(filtersFormClone);

    const filtersBtn = qs('#nhf-mb-filters', mobileBar);

    filtersBtn?.addEventListener('click', () => {
      openDrawer(filtersDrawer, filtersBtn);
    });

    filtersDrawer.addEventListener('click', (e) => {
      const actionEl = e.target.closest('[data-action]');
      if (!actionEl) return;

      const action = actionEl.dataset.action;

      if (action === 'reset') {
        const baseUrl =
          filtersFormClone?.getAttribute('action') || window.location.pathname;

        window.location.href = baseUrl;
      }

      if (action === 'apply') {
        filtersFormClone?.submit();
      }
    });

    updateBadge();
    initializedMobileUI = true;
  }

  function destroyMobileUI() {
    closeDrawer(filtersDrawer, false);

    filtersDrawer?.remove();
    mobileBar?.remove();

    document.documentElement.classList.remove('nhf-lock');
    document.body.classList.remove('nhf-lock');

    initializedMobileUI = false;
    mobileBar = null;
    filtersDrawer = null;
    filtersFormClone = null;
    badgeEl = null;
    openedByBtn = null;
  }

  function handleCategoryToggle(toggle) {
    const item = toggle.closest('.nhf-cat-item');
    if (!item) return;

    const rootList = toggle.closest('.nhf-cat-list') || document;
    const wasOpen = item.classList.contains('is-open');

    qsa('.nhf-cat-item.is-open', rootList).forEach((openItem) => {
      openItem.classList.remove('is-open');
      qs('button.nhf-cat-toggle', openItem)?.setAttribute('aria-expanded', 'false');
      qs('.nhf-cat-sub', openItem)?.setAttribute('aria-hidden', 'true');
    });

    if (!wasOpen) {
      item.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
      qs('.nhf-cat-sub', item)?.setAttribute('aria-hidden', 'false');
    }
  }

  function handleFilterToggle(toggle) {
    const section = toggle.closest('.nhf-filter');
    if (!section) return;

    const wasOpen = section.classList.contains('is-open');

    if (wasOpen) {
      toggle.focus();
    }

    section.classList.toggle('is-open');
    setFilterSectionAria(section);
  }

  function bindGlobalEvents() {
    document.addEventListener('click', (e) => {
      if (!(e.target instanceof Element)) return;

      const catToggle = e.target.closest('button.nhf-cat-toggle');
      if (catToggle) {
        e.preventDefault();
        handleCategoryToggle(catToggle);
        return;
      }

      const filterToggle = e.target.closest('button.nhf-filter-toggle');
      if (filterToggle) {
        e.preventDefault();
        handleFilterToggle(filterToggle);
      }
    });

    document.addEventListener('change', (e) => {
      const target = e.target;
      if (!(target instanceof Element)) return;

      if (
        target.matches('.nhf-filter input, .nhf-filter select, .nhf-filter textarea')
      ) {
        updateSectionFromInput(target);
      }

      if (filtersDrawer && filtersDrawer.contains(target) && target.matches('input[type="checkbox"]')) {
        updateBadge();
      }

      if (!mq.matches && target.matches('#nhf-sidebar .nhf-form input[type="checkbox"]')) {
        target.form?.submit();
      }
    });

    document.addEventListener('input', (e) => {
      const target = e.target;
      if (!(target instanceof Element)) return;

      if (
        target.matches(
          '.nhf-filter input[name="price_min"], .nhf-filter input[name="price_max"]'
        )
      ) {
        updateSectionFromInput(target);
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter') return;

      const target = e.target;
      if (!(target instanceof Element)) return;

      if (!mq.matches && target.matches('#nhf-sidebar .nhf-form input[type="number"]')) {
        target.form?.submit();
      }
    });
  }

  function handleViewportChange(e) {
    if (e.matches) {
      if (!initializedMobileUI) {
        buildMobileUI();
      }
    } else if (initializedMobileUI) {
      destroyMobileUI();
    }
  }

  function init() {
    initializeFilterSections(document);
    bindGlobalEvents();
    handleViewportChange(mq);

    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', handleViewportChange);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(handleViewportChange);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();