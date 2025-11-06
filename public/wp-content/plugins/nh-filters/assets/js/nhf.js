/**
 * Category accordion logic
 */
document.addEventListener('DOMContentLoaded', () => {
  const toggles = document.querySelectorAll('.nhf-cat-toggle');

  toggles.forEach(toggle => {
    toggle.addEventListener('click', e => {
      e.preventDefault();

      const item = toggle.closest('.nhf-cat-item');
      const wasOpen = item.classList.contains('is-open');

      // Close all
      document.querySelectorAll('.nhf-cat-item.is-open').forEach(openItem => {
        openItem.classList.remove('is-open');
        openItem.querySelector('.nhf-cat-toggle').setAttribute('aria-expanded', 'false');
        const sub = openItem.querySelector('.nhf-cat-sub');
        if (sub) sub.setAttribute('aria-hidden', 'true');
      });

      // Reopen clicked if it was closed
      if (!wasOpen) {
        item.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        const sub = item.querySelector('.nhf-cat-sub');
        if (sub) sub.setAttribute('aria-hidden', 'false');
      }
    });
  });
});

/**
 * Filter Accordion (multi-open)
 */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.nhf-filter-toggle').forEach(toggle => {
    toggle.addEventListener('click', e => {
      e.preventDefault();
      const section = toggle.closest('.nhf-filter');
      const body = section.querySelector('.nhf-filter-body');

      // toggle only this section (do NOT close others)
      section.classList.toggle('is-open');

      // ARIA for accessibility
      const isOpen = section.classList.contains('is-open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (body) body.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    });

    // initialize ARIA on load based on markup class
    const section = toggle.closest('.nhf-filter');
    const body = section.querySelector('.nhf-filter-body');
    const isOpen = section.classList.contains('is-open');
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (body) body.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
  });
});

/**
 * Submit form when checkboxes change (desktop)
 */
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('.nhf-form');
  if (!form) return;

  // autosubmit for checkboxes
  form.querySelectorAll('input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', () => form.submit());
  });

  // optional: submit when pressing Enter inside price fields
  form.querySelectorAll('input[type="number"]').forEach(inp => {
    inp.addEventListener('keydown', e => {
      if (e.key === 'Enter') form.submit();
    });
  });
});

/**
 * Keep filter groups open if they contain a selection
 * and add a subtle "active" highlight on the header.
 */
document.addEventListener('DOMContentLoaded', () => {
  const sections = document.querySelectorAll('.nhf-filter');

  sections.forEach(section => {
    const body   = section.querySelector('.nhf-filter-body');
    const toggle = section.querySelector('.nhf-filter-toggle');

    // Detect if this group has any active selection
    const hasChecked = !!section.querySelector('input[type="checkbox"]:checked');
    const hasPrice   = !!section.querySelector('input[type="number"][name="price_min"], input[type="number"][name="price_max"]'); // for future price
    const hasPriceVal = hasPrice && (
      (section.querySelector('input[name="price_min"]') && section.querySelector('input[name="price_min"]').value !== '') ||
      (section.querySelector('input[name="price_max"]') && section.querySelector('input[name="price_max"]').value !== '')
    );

    const isActive = hasChecked || hasPriceVal;

    if (isActive) {
      // Ensure it's open on load
      section.classList.add('is-open', 'is-active-group');
      if (toggle) toggle.setAttribute('aria-expanded', 'true');
      if (body)   body.setAttribute('aria-hidden', 'false');
    } else {
      section.classList.toggle('is-active-group', false);
      // do not force-close here; respect whatever open/closed state markup had
    }

    // Keep the "active" highlight in sync when user checks/unchecks
    section.querySelectorAll('input[type="checkbox"]').forEach(cb => {
      cb.addEventListener('change', () => {
        const nowActive = !!section.querySelector('input[type="checkbox"]:checked');
        section.classList.toggle('is-active-group', nowActive);
      });
    });

    // (Optional future) If you re-enable price inputs, keep highlight in sync
    section.querySelectorAll('input[type="number"]').forEach(inp => {
      inp.addEventListener('input', () => {
        const minVal = section.querySelector('input[name="price_min"]')?.value || '';
        const maxVal = section.querySelector('input[name="price_max"]')?.value || '';
        const nowActive = (minVal !== '' || maxVal !== '');
        section.classList.toggle('is-active-group', nowActive);
      });
    });
  });
});
