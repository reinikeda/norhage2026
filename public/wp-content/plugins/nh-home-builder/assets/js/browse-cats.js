// NHHB – Browse Categories: tidy scroll controls
(function () {
  function init(section){
    var track = section.querySelector('.nhhb-cats-track');
    if (!track) return;

    var prev = section.querySelector('.nhhb-cat-prev');
    var next = section.querySelector('.nhhb-cat-next');

    // How far to move per click: ~80% of visible area or 3 items, whichever is smaller
    function step(){
      var vis = track.clientWidth * 0.8;
      var firstItem = track.querySelector('.nhhb-cat');
      if (firstItem){
        var three = firstItem.getBoundingClientRect().width * 3 + 72; // item*3 + gaps
        return Math.max(200, Math.min(vis, three));
      }
      return vis;
    }

    function updateDisabled(){
      // Optional: you can add disabled styles by toggling aria-disabled
      var atStart = track.scrollLeft <= 2;
      var atEnd   = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
      if (prev) prev.setAttribute('aria-disabled', atStart ? 'true' : 'false');
      if (next) next.setAttribute('aria-disabled', atEnd   ? 'true' : 'false');
    }

    if (prev){
      prev.addEventListener('click', function(){
        track.scrollBy({ left: -step(), behavior: 'smooth' });
        setTimeout(updateDisabled, 260);
      });
    }
    if (next){
      next.addEventListener('click', function(){
        track.scrollBy({ left: step(), behavior: 'smooth' });
        setTimeout(updateDisabled, 260);
      });
    }

    track.addEventListener('scroll', updateDisabled, { passive: true });
    window.addEventListener('resize', updateDisabled);

    // Initialize
    updateDisabled();
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('[data-nhhb-cats]').forEach(init);
  });
})();
