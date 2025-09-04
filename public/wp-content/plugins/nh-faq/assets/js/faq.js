(function(){
  // Click to toggle (works for product .nh-faq-q and global .nh-faq-h3btn)
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.nh-faq .nh-faq-q, .nh-faq .nh-faq-h3btn');
    if (!btn) return;

    const panel = document.getElementById(btn.getAttribute('aria-controls'));
    const wasOpen = btn.getAttribute('aria-expanded') === 'true';
    const container = btn.closest('.nh-faq');
    const group = btn.closest('.nh-faq-group') || container;
    const mode = (container && container.dataset.accordion) || 'multi';

    // If single mode and opening a new one, close others in the same group
    if (mode === 'single' && !wasOpen) {
      group.querySelectorAll('button[aria-expanded="true"]').forEach(function(b){
        if (b !== btn) {
          b.setAttribute('aria-expanded','false');
          const p = document.getElementById(b.getAttribute('aria-controls'));
          if (p) p.hidden = true;
        }
      });
    }

    btn.setAttribute('aria-expanded', String(!wasOpen));
    if (panel) panel.hidden = wasOpen;

    // GA4 (optional)
    try {
      window.dataLayer = window.dataLayer || [];
      const id = btn.closest('.nh-faq-item')?.id || '';
      window.dataLayer.push({ event: 'faq_open', question_id: id, opened: !wasOpen });
    } catch(e){}
  });

  // Deep link support: #nh-faq-123 opens it
  function openByHash() {
    const hash = window.location.hash.replace('#','');
    if (!hash) return;
    const wrap = document.getElementById(hash);
    const btn = wrap ? wrap.querySelector('.nh-faq-h3btn, .nh-faq-q') : null;
    if (!btn) return;
    const panel = document.getElementById(btn.getAttribute('aria-controls'));
    btn.setAttribute('aria-expanded','true');
    if (panel) panel.hidden = false;
    wrap.scrollIntoView({behavior:'smooth', block:'start'});
  }
  window.addEventListener('load', openByHash);
})();

// Per-topic expand/collapse
document.addEventListener('click', function(e){
  const ctrl = e.target.closest('.nh-faq [data-nh="expand-all"], .nh-faq [data-nh="collapse-all"]');
  if (!ctrl) return;

  const group = ctrl.closest('.nh-faq-topic-card')?.querySelector('.nh-faq-group');
  if (!group) return;

  const open = ctrl.getAttribute('data-nh') === 'expand-all';
  group.querySelectorAll('.nh-faq-h3btn').forEach(function(btn){
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    const panel = document.getElementById(btn.getAttribute('aria-controls'));
    if (panel) panel.hidden = !open;
  });
});
