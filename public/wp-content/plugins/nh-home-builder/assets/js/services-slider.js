// NH Home Builder – Services Slider JS (refined)
(function () {
  var SWIPER_CSS = 'https://unpkg.com/swiper@11/swiper-bundle.min.css';
  var SWIPER_JS  = 'https://unpkg.com/swiper@11/swiper-bundle.min.js';

  function ensureSwiper(cb) {
    if (window.Swiper) { cb(); return; }

    if (!document.getElementById('nhhb-swiper-css')) {
      var link = document.createElement('link');
      link.id = 'nhhb-swiper-css';
      link.rel = 'stylesheet';
      link.href = SWIPER_CSS;
      document.head.appendChild(link);
    }

    if (document.getElementById('nhhb-swiper-js')) return waitForSwiper(cb);

    var script = document.createElement('script');
    script.id = 'nhhb-swiper-js';
    script.src = SWIPER_JS;
    script.async = true;
    script.onload = cb;
    document.head.appendChild(script);
  }

  function waitForSwiper(cb) {
    var start = Date.now();
    var t = setInterval(function () {
      if (window.Swiper) { clearInterval(t); cb(); }
      if (Date.now() - start > 7000) clearInterval(t);
    }, 50);
  }

  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function makeConfig(section) {
    var el   = section.querySelector('.nhhb-services-swiper');
    var slides = el ? el.querySelectorAll('.swiper-slide') : [];
    var next = section.querySelector('.nhhb-svc-next');
    var prev = section.querySelector('.nhhb-svc-prev');
    var pag  = section.querySelector('.nhhb-svc-pagination');

    var single = slides.length <= 1;
    if (single) {
      if (next) next.style.display = 'none';
      if (prev) prev.style.display = 'none';
      if (pag)  pag.style.display  = 'none';
    }

    var useAutoplay = !reduceMotion;
    var autoplayCfg = useAutoplay ? { delay: 4500, disableOnInteraction: false, pauseOnMouseEnter: true } : false;

    return {
      slidesPerView: 1,
      loop: !single,
      speed: reduceMotion ? 0 : 550,
      spaceBetween: 0,
      // Swiper reads RTL from CSS automatically, no 'rtl' key needed
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
  }

  function initOne(section) {
    var el = section.querySelector('.nhhb-services-swiper');
    if (!el || el.__nhhbInit) return;

    var cfg = makeConfig(section);
    el.__nhhbInit = true;
    el.__nhhbSwiper = new Swiper(el, cfg);

    // Pause/resume autoplay when offscreen
    if ('IntersectionObserver' in window && el.__nhhbSwiper && el.__nhhbSwiper.params.autoplay) {
      var io = new IntersectionObserver(function (ents) {
        ents.forEach(function (e) {
          var sw = el.__nhhbSwiper;
          if (!sw || !sw.autoplay) return;
          if (e.isIntersecting) { try { sw.autoplay.start(); } catch(_){} }
          else { try { sw.autoplay.stop(); } catch(_){} }
        });
      }, { root: null, threshold: 0.2 });
      io.observe(el);
      el.__nhhbIO = io;
    }
  }

  function maybeInitOnView(section) {
    if (!('IntersectionObserver' in window)) return initOne(section);
    var seen = false;
    var io = new IntersectionObserver(function (ents, obs) {
      ents.forEach(function (e) {
        if (e.isIntersecting && !seen) {
          seen = true;
          obs.disconnect();
          initOne(section);
        }
      });
    }, { root: null, threshold: 0.15 });
    io.observe(section);
  }

  function initAll() {
    document.querySelectorAll('[data-nhhb-services-slider]').forEach(maybeInitOnView);
  }

  function boot() {
    ensureSwiper(function () {
      initAll();
      if ('MutationObserver' in window) {
        var mo = new MutationObserver(function (muts) {
          for (var i = 0; i < muts.length; i++) {
            var nodes = muts[i].addedNodes;
            for (var j = 0; j < nodes.length; j++) {
              var n = nodes[j];
              if (!(n instanceof HTMLElement)) continue;
              if (n.matches && n.matches('[data-nhhb-services-slider]')) {
                maybeInitOnView(n);
              } else if (n.querySelectorAll) {
                n.querySelectorAll('[data-nhhb-services-slider]').forEach(maybeInitOnView);
              }
            }
          }
        });
        mo.observe(document.documentElement, { childList: true, subtree: true });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { passive: true });
  } else {
    boot();
  }
})();
