document.addEventListener('DOMContentLoaded', function () {
  var el = document.querySelector('.nh-sale-slider');
  if (!el || typeof Swiper === 'undefined') return;

  new Swiper(el, {
    loop: true,
    autoplay: { delay: 4000, disableOnInteraction: false },
    slidesPerView: 1,
    pagination: {
      el: el.querySelector('.swiper-pagination'),
      clickable: true,
      renderBullet: function (index, className) {
        // Render a real <button> with aria-label for better accessibility and keyboard focus
        return '<button class="' + className + '" aria-label="Go to slide ' + (index + 1) + '"></button>';
      }
    },
    spaceBetween: 0,
    keyboard: { enabled: true, onlyInViewport: true },
    a11y: {
      enabled: true,
      prevSlideMessage: 'Previous slide',
      nextSlideMessage: 'Next slide',
      firstSlideMessage: 'This is the first slide',
      lastSlideMessage: 'This is the last slide',
      slideLabelMessage: 'Slide {{index}} of {{slidesLength}}'
    }
  });
});
