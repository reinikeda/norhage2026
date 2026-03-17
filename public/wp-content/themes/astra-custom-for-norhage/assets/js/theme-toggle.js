(function () {
  const root = document.documentElement;
  const btn = document.getElementById('theme-toggle');
  if (!btn) return;

  const KEY = 'theme';
  const sunIcon = btn.dataset.sunIcon || '';
  const moonIcon = btn.dataset.moonIcon || '';

  const initial = localStorage.getItem(KEY) || 'light';

  function pushThemeEvent(eventName, theme, source) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: eventName,
      theme_mode: theme,
      theme_source: source
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

  function ensureDarkCSS(theme) {
    if (theme === 'dark' && typeof window.loadDarkModeCSS === 'function') {
      window.loadDarkModeCSS();
    }
  }

  function apply(theme) {
    const isDark = theme === 'dark';

    ensureDarkCSS(theme);

    root.setAttribute('data-theme', theme);
    document.body.classList.toggle('dark-mode', isDark);

    btn.setAttribute(
      'aria-label',
      isDark ? 'Switch to light mode' : 'Switch to dark mode'
    );
    btn.setAttribute('aria-pressed', String(isDark));

    setIcon(theme);
  }

  apply(initial);

  const initialSource = localStorage.getItem(KEY) ? 'storage' : 'default';
  pushThemeEvent('theme_init', initial, initialSource);

  btn.addEventListener('click', () => {
    const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';

    apply(next);
    localStorage.setItem(KEY, next);

    pushThemeEvent('theme_toggle', next, 'toggle');
  });
})();
