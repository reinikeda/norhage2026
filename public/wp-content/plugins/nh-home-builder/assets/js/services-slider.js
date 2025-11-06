// NH Home Builder – Services Slider JS
// Path: js/services-slider.js
(function () {
  var SWIPER_CSS = 'https://unpkg.com/swiper@11/swiper-bundle.min.css';
  var SWIPER_JS  = 'https://unpkg.com/swiper@11/swiper-bundle.min.js';

  function ensureSwiper(callback) {
    if (window.Swiper) { callback(); return; }

    // Inject Swiper CSS once
    var cssId = 'nhhb-swiper-css';
    if (!document.getElementById(cssId)) {
      var link = document.createElement('link');
      link.id = cssId;
      link.rel = 'stylesheet';
      link.href = SWIPER_CSS;
      document.head.appendChild(link);
    }

    // If JS already loading, just wait
    var jsId = 'nhhb-swiper-js';
    if (document.getElementById(jsId)) {
      waitForSwiper(callback);
      return;
    }

    var script = document.createElement('script');
    script.id = jsId;
    script.src = SWIPER_JS;
    script.async = true;
    script.onload = function () { callback(); };
    document.head.appendChild(script);
  }

  function waitForSwiper(callback) {
    var start = Date.now();
    var t = setInterval(function () {
      if (window.Swiper) { clearInterval(t); callback(); }
      if (Date.now() - start > 7000) { clearInterval(t); } // give up after 7s
    }, 50);
  }

  function prefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function initOneSection(section) {
    var el   = section.querySelector('.nhhb-services-swiper');
    if (!el || el.__nhhbInit) return;

    var slides = el.querySelectorAll('.swiper-slide');
    var next   = section.querySelector('.nhhb-svc-next');
    var prev   = section.querySelector('.nhhb-svc-prev');
    var pag    = section.querySelector('.nhhb-svc-pagination');

    // Hide nav/pagination when only 1 slide
    var single = slides.length <= 1;
    if (single) {
      if (next) next.style.display = 'none';
      if (prev) prev.style.display = 'none';
      if (pag)  pag.style.display  = 'none';
    }

    el.__nhhbInit = true;

    // Respect user motion settings
    var useAutoplay = true; // set to true to enable auto-rotate by default
    var autoplayCfg = useAutoplay && !prefersReducedMotion()
      ? { delay: 4500, disableOnInteraction: false }
      : false;

    // RTL support
    var isRtl = document.dir === 'rtl' || document.documentElement.dir === 'rtl';

    // Build config
    var config = {
      slidesPerView: 1,
      loop: !single,              // don't loop a single slide
      speed: prefersReducedMotion() ? 0 : 550,
      spaceBetween: 0,
      direction: 'horizontal',
      rtl: isRtl,
      navigation: single ? undefined : (next && prev ? { nextEl: next, prevEl: prev } : undefined),
      pagination: single ? undefined : (pag ? { el: pag, clickable: true } : undefined),
      autoplay: autoplayCfg,
      keyboard: { enabled: true, onlyInViewport: true },
      a11y: {
        enabled: true,
        containerMessage: 'Services slider',
        slideRole: 'group',
        firstSlideMessage: 'This is the first slide',
        lastSlideMessage: 'This is the last slide',
        nextSlideMessage: 'Next slide',
        prevSlideMessage: 'Previous slide'
      }
    };

    // Init Swiper
    var instance = new Swiper(el, config);
    el.__nhhbSwiper = instance;
  }

  function initAll() {
    document.querySelectorAll('[data-nhhb-services-slider]').forEach(initOneSection);
  }

  function boot() {
    ensureSwiper(function () {
      initAll();

      // Watch for dynamic insertions (e.g., blocks/AJAX)
      if ('MutationObserver' in window) {
        var mo = new MutationObserver(function (muts) {
          for (var i = 0; i < muts.length; i++) {
            var nodes = muts[i].addedNodes;
            for (var j = 0; j < nodes.length; j++) {
              var n = nodes[j];
              if (!(n instanceof HTMLElement)) continue;
              if (n.matches && n.matches('[data-nhhb-services-slider]')) {
                initOneSection(n);
              } else {
                // search descendants
                var found = n.querySelectorAll && n.querySelectorAll('[data-nhhb-services-slider]');
                if (found && found.length) found.forEach(initOneSection);
              }
            }
          }
        });
        mo.observe(document.documentElement, { childList: true, subtree: true });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
