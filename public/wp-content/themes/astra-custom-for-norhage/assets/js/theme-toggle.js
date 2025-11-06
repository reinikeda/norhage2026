(function () {
  const root = document.documentElement;
  const btn  = document.getElementById('theme-toggle');
  if (!btn) return;

  const KEY = 'theme';
  const sunIcon  = btn.dataset.sunIcon || '';
  const moonIcon = btn.dataset.moonIcon || '';

  // Prefer previously chosen theme; otherwise follow OS preference.
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  const initial = localStorage.getItem(KEY) || (prefersDark ? 'dark' : 'light');

  function setIcon(theme) {
    const isDark = theme === 'dark';
    const img = btn.querySelector('img');
    const svg = btn.querySelector('svg');

    if (img) {
      // If you have an <img>, swap its src.
      if (sunIcon && moonIcon) {
        img.src = isDark ? sunIcon : moonIcon;
        img.alt = isDark ? 'Sun' : 'Moon';
      }
      return;
    }

    if (svg) {
      // With inline <svg>, toggle a data flag you can style.
      // Example CSS below.
      svg.dataset.icon = isDark ? 'sun' : 'moon';
    }
  }

  function apply(theme) {
    const isDark = theme === 'dark';

    // Keep your current hooks
    root.setAttribute('data-theme', theme);
    document.body.classList.toggle('dark-mode', isDark);

    // (Optional) also toggle on <html> if you want to key CSS from there:
    // root.classList.toggle('dark', isDark);

    // A11y
    btn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    btn.setAttribute('aria-pressed', String(isDark));

    setIcon(theme);
  }

  // Init
  apply(initial);

  btn.addEventListener('click', () => {
    const next = (root.getAttribute('data-theme') === 'dark') ? 'light' : 'dark';
    apply(next);
    localStorage.setItem(KEY, next);
  });
})();
