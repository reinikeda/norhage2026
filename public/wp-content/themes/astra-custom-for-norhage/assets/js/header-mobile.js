(function () {
  const masthead  = document.getElementById('masthead');
  if (!masthead) return;

  const mq        = window.matchMedia('(max-width: 1023px)');
  const topRow    = masthead.querySelector('.nhhb-row--top');
  const bottomRow = masthead.querySelector('.nhhb-row--bottom');
  const tools     = bottomRow ? bottomRow.querySelector('.nhhb-tools') : null;

  let lastY = window.scrollY || 0;
  let moved = false;

  function moveToolsIntoTop(){ if (tools && topRow && !moved){ topRow.appendChild(tools); moved = true; } }
  function moveToolsBack(){    if (tools && bottomRow && moved){ bottomRow.appendChild(tools); moved = false; } }

  function lockFixedHeader(){
    // compute current header height and push page content down to avoid jump
    const h = masthead.offsetHeight || 56;
    document.body.style.setProperty('--mh-fixed-offset', h + 'px');
    document.body.classList.add('mh-fixed-on');
    masthead.classList.add('mh-fixed'); // position: fixed
  }
  function unlockFixedHeader(){
    masthead.classList.remove('mh-fixed');
    document.body.classList.remove('mh-fixed-on');
    document.body.style.removeProperty('--mh-fixed-offset');
  }

  function enterCompact(){
    masthead.classList.add('mh-compact');
    moveToolsIntoTop();
    lockFixedHeader();               // <- show immediately on scroll up
  }
  function exitCompact(){
    masthead.classList.remove('mh-compact','mh-hidden');
    moveToolsBack();
    unlockFixedHeader();             // <- restore sticky at page top
  }

  function onScrollMobile(){
    const y      = window.scrollY || 0;
    const delta  = y - lastY;     // +down, -up
    const atTop  = y <= 8;
    const THRESH = 2;

    if (atTop){
      // only at the very top we restore the full (non-fixed) header
      exitCompact();
    } else {
      if (delta > THRESH){
        // scrolling down -> hide (but keep it fixed so next up shows immediately)
        masthead.classList.add('mh-hidden');
      } else if (delta < -THRESH){
        // scrolling up -> show compact IMMEDIATELY (fixed)
        masthead.classList.remove('mh-hidden');
        enterCompact();
      }
    }
    lastY = y;
  }

  function onScroll(){ if (mq.matches) onScrollMobile(); }
  function onResize(){ if (!mq.matches){ exitCompact(); } }

  // Optional: touch help (works even when gesture starts on the horizontal nav)
  let startY = null;
  window.addEventListener('touchstart', e => { startY = e.touches?.[0]?.clientY ?? null; }, {passive:true});
  window.addEventListener('touchmove',  e => {
    if (startY == null) return;
    const dy = (e.touches?.[0]?.clientY ?? startY) - startY;
    if (dy > 6) { masthead.classList.remove('mh-hidden'); enterCompact(); }
  }, {passive:true});
  window.addEventListener('touchend',   () => { startY = null; }, {passive:true});

  window.addEventListener('scroll', onScroll, {passive:true});
  window.addEventListener('resize', onResize, {passive:true});
})();
