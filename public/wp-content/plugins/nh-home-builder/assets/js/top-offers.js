// Lightweight slider (dots + auto-rotate) — fixed offset calc
(function(){
  function init(section){
    var track  = section.querySelector('.nhhb-slides');
    var slides = Array.from(section.querySelectorAll('.nhhb-slide'));
    if (!track || slides.length <= 1) return;

    var dotsWrap = section.querySelector('.nhhb-dots');
    var dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll('.nhhb-dot')) : [];
    var idx = 0, timer = null;

    function trackWidth(){
      return track.getBoundingClientRect().width; // <-- use the track, not the whole section
    }

    function setActive(i, smooth = true){
      idx = i;
      var left = Math.round(trackWidth() * i);
      track.scrollTo({ left: left, behavior: smooth ? 'smooth' : 'auto' });
      dots.forEach((d,k)=>d.classList.toggle('is-active', k===i));
    }

    function next(){ setActive((idx + 1) % slides.length); }
    function restart(){ clearInterval(timer); timer = setInterval(next, 5000); }

    // Dots
    dots.forEach((d,i)=>d.addEventListener('click', ()=>{ setActive(i); restart(); }));

    // Init
    setActive(0, false);
    restart();

    // Keep alignment on resize
    var ro = new ResizeObserver(()=> setActive(idx, false));
    ro.observe(track);

    // Optional: stop autoplay while user hovers
    section.addEventListener('mouseenter', ()=> clearInterval(timer));
    section.addEventListener('mouseleave', restart);
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('[data-nhhb-slider]').forEach(init);
  });
})();
