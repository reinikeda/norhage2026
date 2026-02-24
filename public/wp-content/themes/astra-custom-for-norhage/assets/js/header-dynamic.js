/* header-mobile.js — compact header (mobile always) + short-height desktop compact + drawer */
(function () {
  const masthead = document.getElementById('masthead');
  if (!masthead) return;

  // Breakpoints
  const mqMobile  = window.matchMedia('(max-width: 1023px)');
  const mqDesktop = window.matchMedia('(min-width: 1024px)');

  // Desktop compact header height limit (compact only on short-height desktops)
  const DESK_MIN_H = 900;

  function isMobile() {
    return mqMobile.matches;
  }

  function desktopCompactEnabled() {
    return mqDesktop.matches && window.innerHeight <= DESK_MIN_H;
  }

  // Layout refs
  const topRow    = masthead.querySelector('.nhhb-row--top');
  const bottomRow = masthead.querySelector('.nhhb-row--bottom');
  const toolsWrap = bottomRow ? bottomRow.querySelector('.nhhb-tools') : null;
  const toolsSlot = topRow ? topRow.querySelector('.nhhb-tools-slot') : null;

  // Individual tools we want to move (NOT the search)
  const acc       = toolsWrap ? toolsWrap.querySelector('.nh-account')     : null;
  const cart      = toolsWrap ? toolsWrap.querySelector('.nh-cart')        : null;
  const theme     = toolsWrap ? toolsWrap.querySelector('#theme-toggle')   : null;
  const searchBox = toolsWrap ? toolsWrap.querySelector('.nh-live-search') : null;

  // Drawer refs
  const burger = masthead.querySelector('.nh-burger');
  const drawer = document.getElementById('nh-mobile-drawer');
  const scrim  = drawer ? drawer.querySelector('.nh-drawer__scrim') : null;
  const close  = drawer ? drawer.querySelector('.nh-drawer__close') : null;
  const panel  = drawer ? drawer.querySelector('.nh-drawer__panel') : null;

  const utilityBar = document.querySelector('.nh-utility');

  // --- state ---
  let movedIconsMobile   = false;

  let lastYDesktop       = window.scrollY || 0;
  let everLeftTopDesktop = (window.scrollY || 0) > 8;

  /* ---------- Tools relocation (MOBILE icons only) ---------- */
  function moveIconsToRow1() {
    if (!isMobile() || !toolsWrap || !toolsSlot || movedIconsMobile) return;

    let holder = toolsSlot.querySelector('.nhhb-tools--compact');
    if (!holder) {
      holder = document.createElement('div');
      holder.className = 'nhhb-tools nhhb-tools--compact';
      toolsSlot.appendChild(holder);
    }

    acc   && holder.appendChild(acc);
    cart  && holder.appendChild(cart);
    theme && holder.appendChild(theme);

    // keep search in row 2
    if (searchBox && !toolsWrap.contains(searchBox)) {
      toolsWrap.appendChild(searchBox);
    }

    movedIconsMobile = true;
  }

  function moveIconsBack() {
    if (!movedIconsMobile || !toolsWrap) return;
    const ref = toolsWrap.firstChild;
    acc   && toolsWrap.insertBefore(acc,   ref);
    cart  && toolsWrap.insertBefore(cart,  ref);
    theme && toolsWrap.insertBefore(theme, ref);
    movedIconsMobile = false;
  }

  /* ---------- Body offset while fixed ---------- */
  function setBodyOffsetFromHeader() {
    const h = masthead.offsetHeight || 112;
    masthead.style.setProperty('--masthead-h', `${h}px`);
    document.body.classList.add('mh-fixed-on');
    document.body.classList.add('nhhb-compact-header');
  }

  function clearBodyOffset() {
    document.body.classList.remove('mh-fixed-on');
    document.body.classList.remove('nhhb-compact-header');
    masthead.style.removeProperty('--masthead-h');
  }

  /* ---------- MOBILE: always compact, always visible ---------- */
  function enterCompactMobile() {
    if (!isMobile()) return;

    masthead.classList.add('mh-compact', 'mh-fixed');
    masthead.classList.remove('mh-hidden');

    moveIconsToRow1();

    requestAnimationFrame(setBodyOffsetFromHeader);
  }

  function exitCompactMobile() {
    masthead.classList.remove('mh-compact', 'mh-fixed', 'mh-hidden');
    moveIconsBack();
    clearBodyOffset();
    closeDrawer(true);
  }

  /* ---------- DESKTOP compact mode (short height) ---------- */
  function enterDesktopCompact() {
    if (!desktopCompactEnabled()) return;

    masthead.classList.add('mh-desktop-compact', 'mh-fixed');
    masthead.classList.remove('mh-hidden');

    if (utilityBar) utilityBar.classList.add('is-hidden-compact');

    setBodyOffsetFromHeader();
  }

  function exitDesktopCompact() {
    masthead.classList.remove('mh-desktop-compact', 'mh-fixed', 'mh-hidden');

    if (utilityBar) utilityBar.classList.remove('is-hidden-compact');

    clearBodyOffset();
  }

  function hideDesktopCompact() {
    masthead.classList.add('mh-hidden');
  }

  /* ---------- Scroll behavior ---------- */
  function onScrollMobile() {
    // Mobile never hides; keep compact always
    enterCompactMobile();
    masthead.classList.remove('mh-hidden');
  }

  function onScrollDesktop() {
    // Only do hide/show behaviour when compact desktop is enabled
    if (!desktopCompactEnabled()) {
      exitDesktopCompact();
      lastYDesktop = window.scrollY || 0;
      return;
    }

    const y      = window.scrollY || 0;
    const delta  = y - lastYDesktop; // +down, -up
    const atTop  = y <= 8;
    const THRESH = 2;

    if (atTop) {
      if (everLeftTopDesktop) {
        enterDesktopCompact();
      } else {
        exitDesktopCompact(); // full header until user leaves the top once
      }
    } else {
      everLeftTopDesktop = true;

      if (delta > THRESH)       hideDesktopCompact();
      else if (delta < -THRESH) enterDesktopCompact();
    }

    lastYDesktop = y;
  }

  function handleScroll() {
    if (isMobile()) onScrollMobile();
    else            onScrollDesktop();
  }

  /* ---------- Drawer helpers ---------- */
  function updateDrawerPosition() {
    if (!panel) return;

    panel.style.position = 'fixed';

    const mrect = masthead.getBoundingClientRect();
    const top   = Math.max(0, mrect.bottom);

    panel.style.top = `${top}px`;

    const maxH = window.innerHeight - top;
    panel.style.height = 'auto';
    panel.style.maxHeight = `${Math.max(200, maxH)}px`;
  }

  /* ---------- Drawer controls ---------- */
  function openDrawer() {
    if (!drawer) return;

    drawer.hidden = false;
    drawer.setAttribute('aria-hidden', 'false');
    drawer.classList.add('is-open');
    document.body.classList.add('drawer-open');

    if (burger) burger.setAttribute('aria-expanded', 'true');

    updateDrawerPosition();

    setTimeout(() => {
      const firstFocus = drawer.querySelector('.nh-drawer__nav a, .nh-drawer__close');
      if (firstFocus) firstFocus.focus({ preventScroll: true });
      else if (panel) panel.focus({ preventScroll: true });
    }, 10);
  }

  function closeDrawer(force) {
    if (!drawer) return;

    if (force || drawer.classList.contains('is-open')) {
      drawer.classList.remove('is-open');
      drawer.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('drawer-open');

      if (burger) burger.setAttribute('aria-expanded', 'false');

      if (panel) {
        panel.style.top = '';
        panel.style.left = '';
        panel.style.right = '';
        panel.style.width = '';
        panel.style.maxHeight = '';
        panel.style.position = '';
      }

      setTimeout(() => { drawer.hidden = true; }, 200);
    }
  }

  // Toggle
  if (burger) {
    burger.addEventListener('click', () => {
      const expanded = burger.getAttribute('aria-expanded') === 'true';
      expanded ? closeDrawer() : openDrawer();
    });
  }

  // Close interactions
  if (scrim) scrim.addEventListener('click', () => closeDrawer());
  if (close) close.addEventListener('click', () => closeDrawer());
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeDrawer();
  });

  /* ---------- Resize + scroll wiring ---------- */
  function handleResize() {
    if (drawer && drawer.classList.contains('is-open')) {
      updateDrawerPosition();
    }

    if (isMobile()) {
      // entering mobile breakpoint
      exitDesktopCompact();

      // Always compact on mobile
      enterCompactMobile();
    } else {
      // entering desktop
      exitCompactMobile();

      if (desktopCompactEnabled()) {
        if ((window.scrollY || 0) > 8) {
          everLeftTopDesktop = true;
          enterDesktopCompact();
        } else {
          exitDesktopCompact();
        }
      } else {
        exitDesktopCompact();
      }
    }
  }

  window.addEventListener('resize', handleResize, { passive: true });
  window.addEventListener('scroll', () => {
    if (drawer && drawer.classList.contains('is-open')) updateDrawerPosition();
    handleScroll();
  }, { passive: true });

  /* ---------- Initial state ---------- */
  if (isMobile()) {
    // Always compact on mobile
    enterCompactMobile();
  } else if (desktopCompactEnabled()) {
    if ((window.scrollY || 0) > 8) {
      everLeftTopDesktop = true;
      enterDesktopCompact();
    } else {
      exitDesktopCompact(); // full header, compact only after scrolling once
    }
  } else {
    // ensure normal desktop state
    exitCompactMobile();
    exitDesktopCompact();
  }

})();
