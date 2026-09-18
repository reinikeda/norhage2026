// NHHB – Reviews slider: scroll controls + hide arrows when nothing to scroll
(function () {
  function init(section) {
    var track = section.querySelector('.nhhb-rev-track');
    if (!track) return;

    var prev = section.querySelector('.nhhb-rev-prev');
    var next = section.querySelector('.nhhb-rev-next');
    var arrows = section.querySelector('.nhhb-rev-arrows');

    function step() {
      var first = track.querySelector('.nhhb-rev-card');
      if (first) {
        var gap = 12;
        return Math.round(first.getBoundingClientRect().width + gap);
      }
      return Math.round(track.clientWidth * 0.85);
    }

    function overflowing() {
      return track.scrollWidth > track.clientWidth + 4;
    }

    function update() {
      var canScroll = overflowing();
      if (arrows) arrows.classList.toggle('is-idle', !canScroll);

      var atStart = track.scrollLeft <= 2;
      var atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
      if (prev) prev.setAttribute('aria-disabled', !canScroll || atStart ? 'true' : 'false');
      if (next) next.setAttribute('aria-disabled', !canScroll || atEnd ? 'true' : 'false');
    }

    if (prev) {
      prev.addEventListener('click', function () {
        track.scrollBy({ left: -step(), behavior: 'smooth' });
      });
    }
    if (next) {
      next.addEventListener('click', function () {
        track.scrollBy({ left: step(), behavior: 'smooth' });
      });
    }

    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    if (typeof ResizeObserver !== 'undefined') {
      new ResizeObserver(update).observe(track);
    }
    update();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-nhhb-reviews]').forEach(init);
  });
})();
