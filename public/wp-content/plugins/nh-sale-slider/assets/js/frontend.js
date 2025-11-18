document.addEventListener('DOMContentLoaded', function () {
  var el = document.querySelector('.nh-sale-slider');
  if (!el || typeof Swiper === 'undefined') return;

  new Swiper(el, {
    loop: true,
    autoplay: { delay: 4000 },
    slidesPerView: 1,
    pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
    spaceBetween: 0
  });
});
