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

  // ---- GTM helper ----
  function pushThemeEvent(eventName, theme, source) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: eventName,          // e.g. "theme_init" or "theme_toggle"
      theme_mode: theme,         // "dark" | "light"
      theme_source: source       // "storage" | "os" | "toggle"
    });
  }

  function setIcon(theme) {
    const isDark = theme === 'dark';
    const img = btn.querySelector('img');
    const svg = btn.querySelector('svg');

    if (img) {
      if (sunIcon && moonIcon) {
        img.src = isDark ? sunIcon : moonIcon;
        img.alt = isDark ? 'Sun' : 'Moon';
      }
      return;
    }

    if (svg) {
      svg.dataset.icon = isDark ? 'sun' : 'moon';
    }
  }

  function apply(theme) {
    const isDark = theme === 'dark';

    root.setAttribute('data-theme', theme);
    document.body.classList.toggle('dark-mode', isDark);

    btn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    btn.setAttribute('aria-pressed', String(isDark));

    setIcon(theme);
  }

  // Init
  apply(initial);

  // Track initial theme (page load)
  const initialSource = localStorage.getItem(KEY) ? 'storage' : 'os';
  pushThemeEvent('theme_init', initial, initialSource);

  btn.addEventListener('click', () => {
    const next = (root.getAttribute('data-theme') === 'dark') ? 'light' : 'dark';
    apply(next);
    localStorage.setItem(KEY, next);

    // Track the switch
    pushThemeEvent('theme_toggle', next, 'toggle');
  });
})();
