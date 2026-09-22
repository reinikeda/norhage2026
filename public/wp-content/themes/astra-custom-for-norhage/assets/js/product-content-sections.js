/**
 * Product content sections: jump links, description clamp, hash open.
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function labels() {
    var cfg = window.nhPcs || {};
    return {
      more: cfg.more || 'Read more',
      less: cfg.less || 'Show less'
    };
  }

  function initClamp(root) {
    var blocks = root.querySelectorAll('[data-nh-pcs-clamp]');
    var text = labels();

    blocks.forEach(function (block) {
      var content = block.querySelector('.nh-pcs-clamp__content');
      var toggle = block.querySelector('[data-nh-pcs-clamp-toggle]');
      if (!content || !toggle) return;

      var measure = function () {
        if (block.classList.contains('is-expanded')) {
          return;
        }
        // Force collapsed measure.
        block.classList.add('is-clamped');
        var overflows = content.scrollHeight > content.clientHeight + 8;
        toggle.hidden = !overflows;
        if (!overflows) {
          block.classList.remove('is-clamped');
        }
      };

      toggle.addEventListener('click', function () {
        var open = block.classList.toggle('is-expanded');
        block.classList.toggle('is-clamped', !open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.textContent = open ? text.less : text.more;
        if (!open) {
          measure();
          block.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });

      measure();
      window.addEventListener('resize', measure);
    });
  }

  function openSectionByHash() {
    var hash = window.location.hash.replace(/^#/, '');
    if (!hash) return;

    var target = document.getElementById(hash);
    if (!target) return;

    var section = target.closest('details.nh-pcs-section, section.nh-pcs-section') ||
      (target.matches('details.nh-pcs-section, section.nh-pcs-section') ? target : null);

    // #reviews lives inside the reviews panel.
    if (!section && (hash === 'reviews' || hash === 'comment')) {
      section = document.querySelector('.nh-pcs-section--reviews');
    }

    if (!section) return;

    if (section.tagName === 'DETAILS') {
      section.open = true;
    }
    window.requestAnimationFrame(function () {
      section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  function initToc(root) {
    var links = root.querySelectorAll('.nh-pcs-toc__link');
    if (!links.length) return;

    var sections = [];
    links.forEach(function (link) {
      var id = (link.getAttribute('href') || '').replace(/^#/, '');
      var el = id ? document.getElementById(id) : null;
      if (el) sections.push({ link: link, el: el });
    });

    links.forEach(function (link) {
      link.addEventListener('click', function (e) {
        var id = (link.getAttribute('href') || '').replace(/^#/, '');
        var el = id ? document.getElementById(id) : null;
        if (!el) return;
        e.preventDefault();
        if (el.tagName === 'DETAILS') {
          el.open = true;
        }
        history.replaceState(null, '', '#' + id);
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    if (!('IntersectionObserver' in window) || !sections.length) return;

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var id = entry.target.id;
          links.forEach(function (link) {
            var active = (link.getAttribute('href') || '') === '#' + id;
            link.classList.toggle('is-active', active);
            if (active) {
              link.setAttribute('aria-current', 'true');
            } else {
              link.removeAttribute('aria-current');
            }
          });
        });
      },
      {
        rootMargin: '-20% 0px -55% 0px',
        threshold: 0.01
      }
    );

    sections.forEach(function (row) {
      observer.observe(row.el);
    });
  }

  ready(function () {
    var root = document.querySelector('[data-nh-pcs]');
    if (!root) return;
    initClamp(root);
    initToc(root);
    openSectionByHash();
    window.addEventListener('hashchange', openSectionByHash);
  });
})();
