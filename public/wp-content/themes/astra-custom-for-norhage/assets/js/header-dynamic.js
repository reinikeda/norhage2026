/* header-dynamic.js — mobile compact header only
   drawer + mobile accordion
*/
(function () {
  const masthead = document.getElementById('masthead');
  if (!masthead) return;

  /* -------------------------------------------------------
     1) BREAKPOINTS / CONSTANTS
     ------------------------------------------------------- */
  const mqMobile = window.matchMedia('(max-width: 1023px)');
  const mqDesktop = window.matchMedia('(min-width: 1024px)');

  const getScrollY = () => window.scrollY || window.pageYOffset || 0;
  const isMobile = () => mqMobile.matches;
  const isDesktop = () => mqDesktop.matches;

  /* -------------------------------------------------------
     2) DOM REFS
     ------------------------------------------------------- */
  const topRow = masthead.querySelector('.nhhb-row--top');
  const bottomRow = masthead.querySelector('.nhhb-row--bottom');

  const toolsWrap = bottomRow ? bottomRow.querySelector('.nhhb-tools') : null;
  const toolsSlot = topRow ? topRow.querySelector('.nhhb-tools-slot') : null;

  const acc = toolsWrap ? toolsWrap.querySelector('.nh-account') : null;
  const cart = toolsWrap ? toolsWrap.querySelector('.nh-cart') : null;
  const theme = toolsWrap ? toolsWrap.querySelector('#theme-toggle') : null;
  const searchBox = toolsWrap ? toolsWrap.querySelector('.nh-live-search') : null;

  const burger = masthead.querySelector('.nh-burger');
  const drawer = document.getElementById('nh-mobile-drawer');
  const scrim = drawer ? drawer.querySelector('.nh-drawer__scrim') : null;
  const closeBtn = drawer ? drawer.querySelector('.nh-drawer__close') : null;
  const panel = drawer ? drawer.querySelector('.nh-drawer__panel') : null;

  /* -------------------------------------------------------
     3) STATE
     ------------------------------------------------------- */
  let movedIconsMobile = false;

  /* -------------------------------------------------------
     4) TOOL RELOCATION (MOBILE)
     ------------------------------------------------------- */
  function moveIconsToRow1() {
    if (!isMobile() || !toolsWrap || !toolsSlot || movedIconsMobile) return;

    let holder = toolsSlot.querySelector('.nhhb-tools--compact');

    if (!holder) {
      holder = document.createElement('div');
      holder.className = 'nhhb-tools nhhb-tools--compact';
      toolsSlot.appendChild(holder);
    }

    if (acc) holder.appendChild(acc);
    if (cart) holder.appendChild(cart);
    if (theme) holder.appendChild(theme);

    /* Keep search in row 2 */
    if (searchBox && !toolsWrap.contains(searchBox)) {
      toolsWrap.appendChild(searchBox);
    }

    movedIconsMobile = true;
  }

  function moveIconsBack() {
    if (!movedIconsMobile || !toolsWrap) return;

    const ref = toolsWrap.firstChild;

    if (acc) toolsWrap.insertBefore(acc, ref);
    if (cart) toolsWrap.insertBefore(cart, ref);
    if (theme) toolsWrap.insertBefore(theme, ref);

    movedIconsMobile = false;
  }

  /* -------------------------------------------------------
     5) BODY OFFSET
     ------------------------------------------------------- */
  function setBodyOffsetFromHeader() {
    const height = masthead.offsetHeight || 112;
    masthead.style.setProperty('--masthead-h', `${height}px`);
    document.body.classList.add('mh-fixed-on', 'nhhb-compact-header');
  }

  function clearBodyOffset() {
    masthead.style.removeProperty('--masthead-h');
    document.body.classList.remove('mh-fixed-on', 'nhhb-compact-header');
  }

  /* -------------------------------------------------------
     6) DRAWER POSITIONING
     ------------------------------------------------------- */
  function updateDrawerPosition() {
    if (!panel) return;

    const rect = masthead.getBoundingClientRect();
    const top = Math.max(0, rect.bottom);
    const maxH = window.innerHeight - top;

    panel.style.position = 'fixed';
    panel.style.top = `${top}px`;
    panel.style.height = 'auto';
    panel.style.maxHeight = `${Math.max(200, maxH)}px`;
  }

  function resetDrawerInlineStyles() {
    if (!panel) return;

    panel.style.position = '';
    panel.style.top = '';
    panel.style.left = '';
    panel.style.right = '';
    panel.style.width = '';
    panel.style.height = '';
    panel.style.maxHeight = '';
  }

  /* -------------------------------------------------------
     7) DRAWER CONTROLS
     ------------------------------------------------------- */
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
      if (firstFocus) {
        firstFocus.focus({ preventScroll: true });
      } else if (panel) {
        panel.focus({ preventScroll: true });
      }
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

  /* -------------------------------------------------------
     8) MOBILE ACCORDION IN DRAWER
     ------------------------------------------------------- */
  function initDrawerAccordion() {
    if (!drawer) return;

    const menuItems = drawer.querySelectorAll('.drawer-menu--primary .menu-item-has-children');

    menuItems.forEach((item) => {
      if (item.dataset.accordionReady === 'true') return;
      item.dataset.accordionReady = 'true';

      const link = item.querySelector(':scope > a');
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

        menuItems.forEach((otherItem) => {
          const otherSub = otherItem.querySelector(':scope > .sub-menu');
          const otherBtn = otherItem.querySelector(':scope > .nh-submenu-toggle');
          const otherLink = otherItem.querySelector(':scope > a');

          if (!otherSub || !otherBtn) return;

          otherItem.classList.remove('is-open');
          otherSub.hidden = true;
          otherBtn.setAttribute('aria-expanded', 'false');

          if (otherLink) {
            otherBtn.setAttribute('aria-label', `Expand ${otherLink.textContent.trim()}`);
          }
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

  /* -------------------------------------------------------
     9) MODE HELPERS
     ------------------------------------------------------- */
  function enterCompactMobile() {
    if (!isMobile()) return;

    masthead.classList.add('mh-compact', 'mh-fixed');
    masthead.classList.remove('mh-hidden', 'mh-desktop-compact');

    moveIconsToRow1();
    requestAnimationFrame(setBodyOffsetFromHeader);
  }

  function exitCompactMobile() {
    masthead.classList.remove('mh-compact', 'mh-hidden');
    moveIconsBack();
    closeDrawer(true);
  }

  function applyDesktopDefault() {
    masthead.classList.remove('mh-compact', 'mh-desktop-compact', 'mh-hidden', 'mh-fixed');
    clearBodyOffset();
    moveIconsBack();
    closeDrawer(true);
  }

  /* -------------------------------------------------------
     10) SCROLL HANDLERS
     ------------------------------------------------------- */
  function handleScroll() {
    if (isMobile()) {
      enterCompactMobile();
      masthead.classList.remove('mh-hidden');

      if (drawer && drawer.classList.contains('is-open')) {
        updateDrawerPosition();
      }
      return;
    }

    /* Desktop should always stay standard */
    applyDesktopDefault();
  }

  /* -------------------------------------------------------
     11) RESIZE HANDLER
     ------------------------------------------------------- */
  function handleResize() {
    if (isMobile()) {
      enterCompactMobile();

      if (drawer && drawer.classList.contains('is-open')) {
        updateDrawerPosition();
      }
      return;
    }

    exitCompactMobile();
    applyDesktopDefault();
  }

  /* -------------------------------------------------------
     12) EVENTS
     ------------------------------------------------------- */
  if (burger) {
    burger.addEventListener('click', () => {
      if (!isMobile()) return;

      const isExpanded = burger.getAttribute('aria-expanded') === 'true';
      if (isExpanded) {
        closeDrawer();
      } else {
        openDrawer();
      }
    });
  }

  if (scrim) {
    scrim.addEventListener('click', () => closeDrawer());
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', () => closeDrawer());
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeDrawer();
  });

  window.addEventListener('resize', handleResize, { passive: true });
  window.addEventListener('scroll', handleScroll, { passive: true });

  /* -------------------------------------------------------
     13) INIT
     ------------------------------------------------------- */
  initDrawerAccordion();

  if (isMobile()) {
    enterCompactMobile();
  } else {
    applyDesktopDefault();
  }
})();
