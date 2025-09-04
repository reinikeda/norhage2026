(function () {
  const s = window.NH_IPLITE || {};
  const delay = Number(s.delay || 10000);
  const containerSel = s.selectors?.container || '#nh-iplite';
  const overlaySel = s.selectors?.overlay || '#nh-iplite-overlay';
  const closeSel = s.selectors?.close || '[data-nh-close]';
  const oncePerSession = !!s.oncePerSession;

  const KEY = 'nhIPliteShown_v1';

  function alreadyShown() {
    return oncePerSession && sessionStorage.getItem(KEY) === '1';
  }
  function markShown() {
    if (oncePerSession) sessionStorage.setItem(KEY, '1');
  }

  function showPopup() {
    if (alreadyShown()) return;

    const popup   = document.querySelector(containerSel);
    const overlay = document.querySelector(overlaySel);
    if (!popup || !overlay) return;

    popup.classList.remove('nh-hidden');
    overlay.classList.remove('nh-hidden');
    popup.setAttribute('aria-hidden', 'false');
    overlay.setAttribute('aria-hidden', 'false');

    // Focus management + ESC
    const focusables = popup.querySelectorAll('a, button, input, textarea, select, [tabindex]:not([tabindex="-1"])');
    const first = focusables[0] || popup;
    const last  = focusables[focusables.length - 1] || popup;

    function onKey(e) {
      if (e.key === 'Escape') close();
      if (e.key === 'Tab' && focusables.length) {
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    }

    // Close handlers
    function close() {
      popup.classList.add('nh-hidden');
      overlay.classList.add('nh-hidden');
      popup.setAttribute('aria-hidden', 'true');
      overlay.setAttribute('aria-hidden', 'true');

      // Cleanup listeners
      document.removeEventListener('keydown', onKey);
      overlay.removeEventListener('click', close);
      popup.querySelectorAll(closeSel).forEach(btn => btn.removeEventListener('click', close));
      document.removeEventListener('mousedown', onDocDown, true);
      document.removeEventListener('touchstart', onDocDown, true);

      document.documentElement.classList.remove('nh-lock');
      document.body.classList.remove('nh-lock');
    }

    // Click on overlay closes (existing behavior)
    overlay.addEventListener('click', close);

    // Also close on any click/tap outside the card (extra safety)
    function onDocDown(e) {
      const card = popup.querySelector('.nh-card');
      if (!card) return; // if your markup changes
      if (!card.contains(e.target)) {
        close();
      }
    }
    document.addEventListener('mousedown', onDocDown, true);
    document.addEventListener('touchstart', onDocDown, true);

    // Close buttons
    popup.querySelectorAll(closeSel).forEach(btn => btn.addEventListener('click', close));

    // Prevent background scroll
    document.documentElement.classList.add('nh-lock');
    document.body.classList.add('nh-lock');

    markShown();
    // Initial focus
    setTimeout(() => (first || popup).focus(), 0);
  }

  // True inactivity: resets on any click/scroll; shows after 'delay' with no events
  function init() {
    if (alreadyShown()) return;

    let idleTimer = null;
    function resetTimer() {
      if (idleTimer) clearTimeout(idleTimer);
      idleTimer = setTimeout(showPopup, delay);
    }

    resetTimer();
    const onClick  = () => resetTimer();
    const onScroll = () => resetTimer();

    document.addEventListener('click', onClick, { passive: true });
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('focus', resetTimer);

    // Stop tracking after popup appears
    const observer = new MutationObserver(() => {
      const el = document.querySelector(containerSel);
      if (el && !el.classList.contains('nh-hidden')) {
        document.removeEventListener('click', onClick);
        window.removeEventListener('scroll', onScroll);
        window.removeEventListener('focus', resetTimer);
        observer.disconnect();
      }
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
