(function () {
  const root = document.documentElement;
  const btn  = document.getElementById('theme-toggle');
  if (!btn) return;

  const KEY = 'theme';
  const DARK_ID   = 'nh-dark-css';
  const DARK_HREF = btn.dataset.darkCss;
  const sunIcon   = btn.dataset.sunIcon;
  const moonIcon  = btn.dataset.moonIcon;
  const imgEl     = btn.querySelector('img');

  function ensureDarkStyles(on) {
    let link = document.getElementById(DARK_ID);
    if (on) {
      if (!link) {
        link = document.createElement('link');
        link.id = DARK_ID;
        link.rel = 'stylesheet';
        link.href = DARK_HREF;
        document.head.appendChild(link);
      }
    } else if (link) {
      link.remove();
    }
  }

function apply(theme) {
  // keep attribute if you want, it's harmless
  document.documentElement.setAttribute('data-theme', theme);

  // ✅ this line makes your existing CSS kick in
  document.body.classList.toggle('dark-mode', theme === 'dark');

  const btn = document.getElementById('theme-toggle');
  const imgEl = btn.querySelector('img');
  const sunIcon  = btn.dataset.sunIcon;
  const moonIcon = btn.dataset.moonIcon;

  imgEl.src = theme === 'dark' ? sunIcon : moonIcon;
  btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
}


  // Initial state: default to LIGHT unless user chose before
  const saved = localStorage.getItem(KEY);
  apply(saved ? saved : 'light');

  btn.addEventListener('click', () => {
    const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    apply(next);
    localStorage.setItem(KEY, next);
  });
})();
