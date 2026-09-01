// NHHB – Browse Categories: scroll controls + hide arrows when nothing to scroll
(function () {
  function init(section) {
    var track = section.querySelector('.nhhb-cats-track');
    if (!track) return;

    var prev = section.querySelector('.nhhb-cat-prev');
    var next = section.querySelector('.nhhb-cat-next');
    var arrows = section.querySelector('.nhhb-cats-arrows');

    function step() {
      var vis = track.clientWidth * 0.8;
      var firstItem = track.querySelector('.nhhb-cat');
      if (firstItem) {
        var three = firstItem.getBoundingClientRect().width * 3 + 36;
        return Math.max(160, Math.min(vis, three));
      }
      return vis;
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
    document.querySelectorAll('[data-nhhb-cats]').forEach(init);
  });
})();
