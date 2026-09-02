// Lightweight slider (dots + auto-rotate) with reduced-motion and offscreen pause
(function () {
  function init(section) {
    var track = section.querySelector('.nhhb-slides');
    var slides = Array.from(section.querySelectorAll('.nhhb-slide'));
    if (!track || slides.length <= 1) return;

    var dotsWrap = section.querySelector('.nhhb-dots');
    var dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll('.nhhb-dot')) : [];
    var idx = 0;
    var timer = null;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function trackWidth() {
      return track.getBoundingClientRect().width;
    }

    function setActive(i, smooth) {
      idx = (i + slides.length) % slides.length;
      track.scrollTo({ left: Math.round(trackWidth() * idx), behavior: smooth && !reduceMotion ? 'smooth' : 'auto' });
      dots.forEach(function (d, k) {
        d.classList.toggle('is-active', k === idx);
        d.setAttribute('aria-selected', k === idx ? 'true' : 'false');
      });
    }

    function next() {
      setActive(idx + 1, true);
    }

    function stop() {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    }

    function start() {
      stop();
      if (reduceMotion) return;
      timer = setInterval(next, 5000);
    }

    dots.forEach(function (d, i) {
      d.addEventListener('click', function () {
        setActive(i, true);
        start();
      });
    });

    setActive(0, false);
    start();

    if (typeof ResizeObserver !== 'undefined') {
      var ro = new ResizeObserver(function () { setActive(idx, false); });
      ro.observe(track);
    } else {
      window.addEventListener('resize', function () { setActive(idx, false); });
    }

    section.addEventListener('mouseenter', stop);
    section.addEventListener('mouseleave', start);
    section.addEventListener('touchstart', stop, { passive: true });
    section.addEventListener('focusin', stop);
    section.addEventListener('focusout', start);

    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (ents) {
        ents.forEach(function (e) {
          if (e.isIntersecting) start();
          else stop();
        });
      }, { threshold: 0.25 });
      io.observe(section);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-nhhb-slider]').forEach(init);
  });
})();
