/* header-dynamic.js — always-visible header
   New layout: utility bar (row1) + logo/nav (row2) + mobile search (row3)
   Mobile: utility bar hidden, brand row + search row visible, burger opens drawer
*/
(function () {
  const masthead = document.getElementById('masthead');
  if (!masthead) return;

  const mqMobile = window.matchMedia('(max-width: 1023px)');
  const isMobile = () => mqMobile.matches;

  const burger  = masthead.querySelector('.nh-burger');
  const drawer  = document.getElementById('nh-mobile-drawer');
  const scrim   = drawer ? drawer.querySelector('.nh-drawer__scrim') : null;
  const closeBtn = drawer ? drawer.querySelector('.nh-drawer__close') : null;
  const panel   = drawer ? drawer.querySelector('.nh-drawer__panel') : null;

  let resizeRaf = null;

  /* ── Body offset ── */
  function setBodyOffsetFromHeader() {
    const height = Math.ceil(masthead.offsetHeight || 100);
    document.documentElement.style.setProperty('--masthead-h', `${height}px`);
    document.body.classList.add('has-fixed-masthead');
  }

  /* ── Drawer position ── */
  function updateDrawerPosition() {
    if (!panel || !drawer || drawer.hidden) return;
    const rect  = masthead.getBoundingClientRect();
    const top   = Math.max(0, rect.bottom);
    const maxH  = Math.max(200, window.innerHeight - top);
    panel.style.position  = 'fixed';
    panel.style.top       = `${top}px`;
    panel.style.left      = '0';
    panel.style.right     = 'auto';
    panel.style.height    = 'auto';
    panel.style.maxHeight = `${maxH}px`;
  }

  function resetDrawerInlineStyles() {
    if (!panel) return;
    panel.style.cssText = '';
  }

  /* ── Open / close drawer ── */
  function openDrawer() {
    if (!drawer || !isMobile()) return;
    drawer.hidden = false;
    drawer.setAttribute('aria-hidden', 'false');
    drawer.classList.add('is-open');
    document.body.classList.add('drawer-open');
    if (burger) burger.setAttribute('aria-expanded', 'true');
    updateDrawerPosition();
    window.setTimeout(() => {
      const firstFocus = drawer.querySelector('.nh-drawer__nav a, .nh-drawer__close');
      if (firstFocus) firstFocus.focus({ preventScroll: true });
      else if (panel) panel.focus({ preventScroll: true });
    }, 10);
  }

  function closeDrawer(force = false) {
    if (!drawer) return;
    if (!force && !drawer.classList.contains('is-open')) return;
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    drawer.hidden = true;
    document.body.classList.remove('drawer-open');
    if (burger) burger.setAttribute('aria-expanded', 'false');
    resetDrawerInlineStyles();
  }

  /* ── Header mode sync ── */
  function syncHeaderMode() {
    if (isMobile()) {
      masthead.classList.add('mh-mobile');
      masthead.classList.remove('mh-desktop');
    } else {
      masthead.classList.add('mh-desktop');
      masthead.classList.remove('mh-mobile');
      closeDrawer(true);
    }
  }

  /* ── Drawer accordion ── */
  function initDrawerAccordion() {
    if (!drawer) return;
    const menuItems = drawer.querySelectorAll('.drawer-menu--primary .menu-item-has-children');
    menuItems.forEach((item) => {
      if (item.dataset.accordionReady === 'true') return;
      item.dataset.accordionReady = 'true';
      const link    = item.querySelector(':scope > a');
      const submenu = item.querySelector(':scope > .sub-menu');
      if (!link || !submenu) return;
      item.classList.remove('is-open');
      submenu.hidden = true;
      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'nh-submenu-toggle';
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', `Expand ${link.textContent.trim()}`);
      toggle.innerHTML = '<span class="nh-submenu-toggle__icon" aria-hidden="true"></span>';
      item.insertBefore(toggle, submenu);
      toggle.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (!isMobile()) return;
        const isOpen = item.classList.contains('is-open');
        menuItems.forEach((other) => {
          const otherSub = other.querySelector(':scope > .sub-menu');
          const otherBtn = other.querySelector(':scope > .nh-submenu-toggle');
          const otherLnk = other.querySelector(':scope > a');
          if (!otherSub || !otherBtn) return;
          other.classList.remove('is-open');
          otherSub.hidden = true;
          otherBtn.setAttribute('aria-expanded', 'false');
          if (otherLnk) otherBtn.setAttribute('aria-label', `Expand ${otherLnk.textContent.trim()}`);
        });
        if (!isOpen) {
          item.classList.add('is-open');
          submenu.hidden = false;
          toggle.setAttribute('aria-expanded', 'true');
          toggle.setAttribute('aria-label', `Collapse ${link.textContent.trim()}`);
        }
      });
    });
  }

  /* ── Refresh ── */
  function refreshHeaderLayout() {
    syncHeaderMode();
    requestAnimationFrame(() => {
      setBodyOffsetFromHeader();
      updateDrawerPosition();
    });
  }

  /* ── Events ── */
  if (burger) {
    burger.addEventListener('click', () => {
      if (!isMobile()) return;
      burger.getAttribute('aria-expanded') === 'true' ? closeDrawer() : openDrawer();
    });
  }

  if (scrim)    scrim.addEventListener('click',    () => closeDrawer());
  if (closeBtn) closeBtn.addEventListener('click', () => closeDrawer());

  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDrawer(); });

  window.addEventListener('resize', () => {
    if (resizeRaf) cancelAnimationFrame(resizeRaf);
    resizeRaf = requestAnimationFrame(refreshHeaderLayout);
  }, { passive: true });

  window.addEventListener('scroll', () => {
    if (drawer && drawer.classList.contains('is-open')) updateDrawerPosition();
  }, { passive: true });

  window.addEventListener('load', refreshHeaderLayout);

  if (document.fonts?.ready?.then) {
    document.fonts.ready.then(refreshHeaderLayout);
  }

  initDrawerAccordion();
  refreshHeaderLayout();
})();
