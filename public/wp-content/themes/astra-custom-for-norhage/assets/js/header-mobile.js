/* header-mobile.js — compact header (row1: logo+icons, row2: burger+search) + drawer */
(function () {
  const masthead = document.getElementById('masthead');
  if (!masthead) return;

  const mq = window.matchMedia('(max-width: 1023px)');

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

  let lastY = window.scrollY || 0;
  let moved = false;
  let everLeftTop = (window.scrollY || 0) > 8;

  /* ---------- Tools relocation (icons only) ---------- */
  function moveIconsToRow1(){
    if (!mq.matches || !toolsWrap || !toolsSlot || moved) return;

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
    if (searchBox && !toolsWrap.contains(searchBox)) toolsWrap.appendChild(searchBox);

    moved = true;
  }

  function moveIconsBack(){
    if (!moved || !toolsWrap) return;
    const ref = toolsWrap.firstChild;
    acc   && toolsWrap.insertBefore(acc,   ref);
    cart  && toolsWrap.insertBefore(cart,  ref);
    theme && toolsWrap.insertBefore(theme, ref);
    moved = false;
  }

  /* ---------- Body offset while compact is fixed ---------- */
  function setBodyOffsetFromHeader(){
    const h = masthead.offsetHeight || 112;
    document.body.style.setProperty('--mhc-total', `${h}px`);
    // also expose a masthead height var (used by CSS as a fallback)
    masthead.style.setProperty('--masthead-h', `${h}px`);
    document.body.classList.add('mh-fixed-on');
  }
  function clearBodyOffset(){
    document.body.classList.remove('mh-fixed-on');
    document.body.style.removeProperty('--mhc-total');
    masthead.style.removeProperty('--masthead-h');
  }

  /* ---------- Compact mode toggles ---------- */
  function enterCompact(){
    if (!mq.matches) return;
    masthead.classList.add('mh-compact','mh-fixed');
    masthead.classList.remove('mh-hidden');
    moveIconsToRow1();
    requestAnimationFrame(setBodyOffsetFromHeader);
  }
  function exitCompact(){
    masthead.classList.remove('mh-compact','mh-fixed','mh-hidden');
    moveIconsBack();
    clearBodyOffset();
    closeDrawer(true);
  }
  function hideCompact(){ masthead.classList.add('mh-hidden'); }

  /* ---------- Scroll behavior ---------- */
  function onScrollMobile(){
    const y     = window.scrollY || 0;
    const delta = y - lastY;   // +down, -up
    const atTop = y <= 8;
    const THRESH = 2;

    if (atTop) {
      if (everLeftTop) {
        enterCompact();
      } else {
        exitCompact();
      }
    } else {
      everLeftTop = true;

      if (delta > THRESH) {
        hideCompact();
      } else if (delta < -THRESH) {
        enterCompact();
      }
    }

    lastY = y;
  }

  function onScroll(){ if (mq.matches) onScrollMobile(); }
  function onResize(){ if (!mq.matches) exitCompact(); }

  /* ---------- Drawer helpers ---------- */
  function updateDrawerPosition(){
    if (!panel) return;

    panel.style.position = 'fixed';

    const mrect = masthead.getBoundingClientRect();
    const top   = Math.max(0, mrect.bottom); // <-- no extra gap

    panel.style.left = '0px';
    panel.style.right = '';                  // let width control the right edge
    panel.style.width = '66.6667vw';         // 2/3 of screen

    panel.style.top = `${top}px`;

    const maxH = window.innerHeight - top;   // flush to bottom if needed
    panel.style.height = 'auto';
    panel.style.maxHeight = `${Math.max(200, maxH)}px`;
  }

  /* ---------- Drawer controls ---------- */
  function openDrawer(){
    if (!drawer) return;
    drawer.hidden = false;
    drawer.setAttribute('aria-hidden','false');
    drawer.classList.add('is-open');
    document.body.classList.add('drawer-open');
    if (burger) burger.setAttribute('aria-expanded','true');

    updateDrawerPosition(); // <- key fix

    // focus first actionable element
    setTimeout(() => {
      const firstFocus = drawer.querySelector('.nh-drawer__nav a, .nh-drawer__close');
      if (firstFocus) firstFocus.focus({ preventScroll:true });
      else if (panel) panel.focus({ preventScroll:true });
    }, 10);
  }

  function closeDrawer(force){
    if (!drawer) return;
    if (force || drawer.classList.contains('is-open')){
      drawer.classList.remove('is-open');
      drawer.setAttribute('aria-hidden','true');
      document.body.classList.remove('drawer-open');
      if (burger) burger.setAttribute('aria-expanded','false');

      // clean inline styles so CSS remains source of truth
      if (panel){
        panel.style.top = '';
        panel.style.left = '';
        panel.style.right = '';
        panel.style.maxHeight = '';
        panel.style.position = '';
      }
      setTimeout(()=>{ drawer.hidden = true; }, 200);
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
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

  // Recalculate on viewport changes while open
  window.addEventListener('resize', () => {
    if (drawer && drawer.classList.contains('is-open')) updateDrawerPosition();
    onResize();
  }, { passive:true });
  window.addEventListener('scroll', () => {
    if (drawer && drawer.classList.contains('is-open')) updateDrawerPosition();
    onScroll();
  }, { passive:true });

  // Initial state
  if (mq.matches){
    if ((window.scrollY || 0) > 8) enterCompact();
    else exitCompact();
  }
})();
