document.addEventListener('DOMContentLoaded', function () {
  const slider = document.querySelector('[data-nh-hero-slider]');
  if (!slider) return;

  const slides = Array.from(slider.querySelectorAll('.nhhb-hero-slide'));
  if (!slides.length) return;

  const prevBtn = slider.querySelector('[data-nh-hero-prev]');
  const nextBtn = slider.querySelector('[data-nh-hero-next]');
  const dots = Array.from(slider.querySelectorAll('[data-nh-hero-dot]'));
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let current = 0;
  let timer = null;
  const delay = 5500;

  function render(index) {
    current = (index + slides.length) % slides.length;

    slides.forEach((slide, i) => {
      const active = i === current;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-hidden', active ? 'false' : 'true');
    });

    dots.forEach((dot, i) => {
      const active = i === current;
      dot.classList.toggle('is-active', active);
      dot.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  }

  function nextSlide() {
    render(current + 1);
  }

  function prevSlide() {
    render(current - 1);
  }

  function stopAuto() {
    if (timer) {
      window.clearInterval(timer);
      timer = null;
    }
  }

  function startAuto() {
    if (reduceMotion || slides.length < 2) return;
    stopAuto();
    timer = window.setInterval(nextSlide, delay);
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', function () {
      prevSlide();
      startAuto();
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', function () {
      nextSlide();
      startAuto();
    });
  }

  dots.forEach((dot) => {
    dot.addEventListener('click', function () {
      const index = parseInt(dot.getAttribute('data-nh-hero-dot'), 10);
      if (!Number.isNaN(index)) {
        render(index);
        startAuto();
      }
    });
  });

  slider.addEventListener('mouseenter', stopAuto);
  slider.addEventListener('mouseleave', startAuto);
  slider.addEventListener('focusin', stopAuto);
  slider.addEventListener('focusout', function (e) {
    if (!slider.contains(e.relatedTarget)) {
      startAuto();
    }
  });

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      stopAuto();
    } else {
      startAuto();
    }
  });

  render(0);
  startAuto();
});