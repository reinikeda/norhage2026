/* header-mobile.js — compact header (mobile + short-height desktop) + drawer */
(function () {
  const masthead = document.getElementById('masthead');
  if (!masthead) return;

  // Breakpoints
  const mqMobile   = window.matchMedia('(max-width: 1023px)');
  const mqDesktop  = window.matchMedia('(min-width: 1024px)');
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
  const scrim  = drawer ? drawer.querySelector('.nh-drawer__scrim')  : null;
  const close  = drawer ? drawer.querySelector('.nh-drawer__close')  : null;
  const panel  = drawer ? drawer.querySelector('.nh-drawer__panel')  : null;

  const utilityBar = document.querySelector('.nh-utility');

  // --- state ---
  let movedIconsMobile   = false;

  let lastYMobile        = window.scrollY || 0;
  let everLeftTopMobile  = (window.scrollY || 0) > 8;

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

  /* ---------- Body offset while compact is fixed ---------- */
  function setBodyOffsetFromHeader() {
    const h = masthead.offsetHeight || 112;
    document.body.style.setProperty('--mhc-total', `${h}px`);
    masthead.style.setProperty('--masthead-h', `${h}px`);
    document.body.classList.add('mh-fixed-on');
    // NEW: mark that compact header is active (mobile or short desktop)
    document.body.classList.add('nhhb-compact-header');
  }

  function clearBodyOffset() {
    document.body.classList.remove('mh-fixed-on');
    document.body.classList.remove('nhhb-compact-header');
    document.body.style.removeProperty('--mhc-total');
    masthead.style.removeProperty('--masthead-h');
  }

  /* ---------- MOBILE compact mode ---------- */
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

  function hideCompactMobile() {
    masthead.classList.add('mh-hidden');
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

  /* ---------- Scroll behavior: MOBILE ---------- */
  function onScrollMobile() {
    const y     = window.scrollY || 0;
    const delta = y - lastYMobile;   // +down, -up
    const atTop = y <= 8;
    const THRESH = 2;

    if (atTop) {
      if (everLeftTopMobile) {
        enterCompactMobile();
      } else {
        exitCompactMobile();
      }
    } else {
      everLeftTopMobile = true;

      if (delta > THRESH)       hideCompactMobile();
      else if (delta < -THRESH) enterCompactMobile();
    }

    lastYMobile = y;
  }

  /* ---------- Scroll behavior: DESKTOP (short height only) ---------- */
  function onScrollDesktop() {
    // Only do hide/show behaviour when compact desktop is actually enabled
    if (!desktopCompactEnabled()) {
      exitDesktopCompact();
      lastYDesktop = window.scrollY || 0;
      return;
    }

    const y     = window.scrollY || 0;
    const delta = y - lastYDesktop;
    const atTop = y <= 8;
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
  function updateDrawerPosition(){
    if (!panel) return;

    panel.style.position = 'fixed';

    const mrect = masthead.getBoundingClientRect();
    const top   = Math.max(0, mrect.bottom);

    // Only control vertical position + max height from JS
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
  burger && burger.addEventListener('click', () => {
    const expanded = burger.getAttribute('aria-expanded') === 'true';
    expanded ? closeDrawer() : openDrawer();
  });

  // Close interactions
  scrim && scrim.addEventListener('click', () => closeDrawer());
  close && close.addEventListener('click', () => closeDrawer());
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

      if ((window.scrollY || 0) > 8) {
        everLeftTopMobile = true;
        enterCompactMobile();
      } else {
        exitCompactMobile();
      }
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
    if ((window.scrollY || 0) > 8) {
      everLeftTopMobile = true;
      enterCompactMobile();
    } else {
      exitCompactMobile();
    }
  } else if (desktopCompactEnabled()) {
    if ((window.scrollY || 0) > 8) {
      everLeftTopDesktop = true;
      enterDesktopCompact();
    } else {
      exitDesktopCompact(); // full header, compact only after scrolling once
    }
  }

})();
