(function () {
  'use strict';

  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function gallery() {
    return document.querySelector('.single-product .woocommerce-product-gallery');
  }

  function thumbs(root) {
    return root.querySelector('.flex-control-nav.flex-control-thumbs');
  }

  function slides(root) {
    return root.querySelectorAll('.woocommerce-product-gallery__wrapper > .woocommerce-product-gallery__image');
  }

  function imageLabel(index, total) {
    var pattern = (window.nhGallery && window.nhGallery.imageLabel) || 'Image %1$d of %2$d';
    return String(pattern).replace('%1$d', String(index)).replace('%2$d', String(total));
  }

  function imagesLabel() {
    return (window.nhGallery && window.nhGallery.imagesLabel) || 'Product images';
  }

  function currentIndex(root) {
    var list = slides(root);
    for (var i = 0; i < list.length; i++) {
      if (list[i].classList.contains('flex-active-slide')) {
        return i;
      }
    }
    return 0;
  }

  function moveProductImageNote(root) {
    var note = document.querySelector('.single-product .nh-product-image-note');
    if (!note || !root) return;

    var nav = thumbs(root);
    if (nav) {
      if (note !== nav.nextElementSibling) {
        nav.insertAdjacentElement('afterend', note);
      }
      return;
    }

    if (root.lastElementChild !== note) {
      root.appendChild(note);
    }
  }

  function videoSlide(root) {
    return root.querySelector('.nh-gallery-video-slide');
  }

  function videoIsOpen(root) {
    var slide = videoSlide(root);
    return !!(slide && slide.classList.contains('flex-active-slide'));
  }

  /**
   * Keep the reel the same height as the photo currently in the gallery.
   */
  function syncVideoFrame(root) {
    var slide = videoSlide(root);
    if (!slide) return;

    var frame = root.querySelector('.flex-viewport');
    var height = frame ? frame.getBoundingClientRect().height : 0;
    if (height < 40) {
      var image = root.querySelector('.woocommerce-product-gallery__image:not(.nh-gallery-video-slide) img');
      height = image ? image.getBoundingClientRect().height : 0;
    }
    if (height < 40) return;

    var px = Math.round(height) + 'px';
    if (slide.style.height !== px) {
      slide.style.height = px;
    }
  }

  function markVideoThumb(root) {
    var nav = thumbs(root);
    var list = slides(root);
    if (!nav) return;

    var items = nav.children;
    for (var i = 0; i < list.length && i < items.length; i++) {
      items[i].classList.toggle('nh-gallery-video-thumb', list[i].classList.contains('nh-gallery-video-slide'));
    }
  }

  function syncVideoOpen(root) {
    var slide = videoSlide(root);
    if (slide && window.jQuery) {
      window.jQuery(slide).trigger('zoom.destroy');
    }

    var box = root.querySelector('.nh-gallery-video');
    if (!box || typeof box.nhSyncOpen !== 'function') return;
    box.nhSyncOpen();
  }

  function bindVideo(root) {
    var box = root.querySelector('.nh-gallery-video');
    if (!box || box.getAttribute('data-nh-bound') === '1') return;

    var media = box.querySelector('video');
    var button = box.querySelector('.nh-gallery-video__sound');
    if (!media || !button) return;
    box.setAttribute('data-nh-bound', '1');

    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function labelFor(mode) {
      if (mode === 'play') return button.getAttribute('data-label-play') || 'Play video';
      if (mode === 'sound') return button.getAttribute('data-label-off') || 'Sound off';
      return button.getAttribute('data-label-on') || 'Sound on';
    }

    function setMode(mode) {
      button.setAttribute('data-mode', mode);
      button.setAttribute('aria-label', labelFor(mode));
      button.setAttribute('aria-pressed', mode === 'sound' ? 'true' : 'false');
    }

    function playMuted() {
      media.muted = true;
      media.setAttribute('muted', '');
      var pending = media.play();
      setMode('muted');
      if (pending && typeof pending.catch === 'function') {
        pending.catch(function () {
          setMode('play');
        });
      }
    }

    media.pause();
    setMode('play');
    button.tabIndex = -1;

    if (window.jQuery) {
      window.jQuery(box.closest('.nh-gallery-video-slide')).trigger('zoom.destroy');
    }

    box.nhSyncOpen = function () {
      var open = videoIsOpen(root);
      button.tabIndex = open ? 0 : -1;
      if (!open) {
        if (box.getAttribute('data-nh-opened') === '1' || !media.paused) {
          media.pause();
          box.removeAttribute('data-nh-opened');
          setMode('play');
        }
        return;
      }

      if (box.getAttribute('data-nh-opened') === '1') return;
      box.setAttribute('data-nh-opened', '1');
      if (reduce) {
        media.pause();
        setMode('play');
        return;
      }
      playMuted();
    };

    function toggle() {
      var mode = button.getAttribute('data-mode');
      var wantSound = mode === 'play' || (mode === 'muted' && !media.paused);

      if (media.paused || mode === 'play') {
        media.muted = !wantSound;
        if (media.muted) {
          media.setAttribute('muted', '');
        } else {
          media.removeAttribute('muted');
        }
        var pending = media.play();
        if (pending && typeof pending.then === 'function') {
          pending.then(function () {
            setMode(media.muted ? 'muted' : 'sound');
          }).catch(function () {
            media.muted = true;
            media.setAttribute('muted', '');
            var retry = media.play();
            if (retry && typeof retry.catch === 'function') retry.catch(function () {});
            setMode(media.paused ? 'play' : 'muted');
          });
        } else {
          setMode(media.muted ? 'muted' : 'sound');
        }
        return;
      }

      if (media.muted) {
        media.muted = false;
        media.removeAttribute('muted');
        var unmute = media.play();
        if (unmute && typeof unmute.catch === 'function') unmute.catch(function () {});
        setMode('sound');
      } else {
        media.muted = true;
        media.setAttribute('muted', '');
        setMode('muted');
      }
    }

    button.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      toggle();
    });

    media.addEventListener('click', function () {
      toggle();
    });
  }

  function ensureDots(root) {
    var total = slides(root).length;
    var nav = thumbs(root);
    var dots = root.querySelector('.nh-gallery-dots');

    if (total < 2 || !nav) {
      if (dots) dots.remove();
      return null;
    }

    if (!dots) {
      dots = document.createElement('div');
      dots.className = 'nh-gallery-dots';
      dots.setAttribute('role', 'group');
      dots.setAttribute('aria-label', imagesLabel());
      nav.parentNode.insertBefore(dots, nav);
    }

    if (dots.childElementCount === total) {
      return dots;
    }

    dots.textContent = '';
    for (var i = 0; i < total; i++) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'nh-gallery-dots__btn';
      var slide = slides(root)[i];
      var dotLabel = slide && slide.classList.contains('nh-gallery-video-slide')
        ? ((window.nhGallery && window.nhGallery.videoLabel) || 'Product video')
        : imageLabel(i + 1, total);
      btn.setAttribute('aria-label', dotLabel);
      btn.dataset.index = String(i);
      btn.addEventListener('click', function (event) {
        var index = parseInt(event.currentTarget.dataset.index, 10);
        var images = root.querySelectorAll('.flex-control-nav.flex-control-thumbs img');
        if (images[index]) {
          images[index].click();
        }
      });
      dots.appendChild(btn);
    }

    return dots;
  }

  function syncDots(root) {
    var dots = root.querySelector('.nh-gallery-dots');
    if (!dots) return;

    var index = currentIndex(root);
    var buttons = dots.querySelectorAll('.nh-gallery-dots__btn');
    for (var i = 0; i < buttons.length; i++) {
      var on = i === index;
      buttons[i].classList.toggle('is-active', on);
      if (on) {
        buttons[i].setAttribute('aria-current', 'true');
      } else {
        buttons[i].removeAttribute('aria-current');
      }
    }
  }

  function scrollActiveThumb(root) {
    var nav = thumbs(root);
    var active = nav && nav.querySelector('img.flex-active');
    var item = active && active.closest('li');
    if (!nav || !item) return;

    var left = item.offsetLeft - (nav.clientWidth - item.offsetWidth) / 2;
    nav.scrollTo({ left: Math.max(0, left), behavior: 'auto' });
  }

  function syncBreadcrumbTab() {
    var nav = document.querySelector('.single-product .woocommerce-breadcrumb.nh-bc[data-nh-truncate="1"]');
    if (!nav) return;

    var narrow = window.matchMedia('(max-width: 921px)').matches;
    var links = nav.querySelectorAll('.is-collapsed a');
    for (var i = 0; i < links.length; i++) {
      if (narrow) {
        links[i].setAttribute('tabindex', '-1');
      } else {
        links[i].removeAttribute('tabindex');
      }
    }
  }

  function refresh() {
    var root = gallery();
    syncBreadcrumbTab();
    if (!root) return;

    moveProductImageNote(root);
    ensureDots(root);
    syncDots(root);
    markVideoThumb(root);
    syncVideoFrame(root);
    bindVideo(root);
    syncVideoOpen(root);
    scrollActiveThumb(root);
  }

  onReady(function () {
    syncBreadcrumbTab();
    window.addEventListener('resize', syncBreadcrumbTab);

    var root = gallery();
    if (!root) return;

    var lastIndex = -1;
    var scheduled = false;

    function update() {
      scheduled = false;
      var current = gallery();
      if (!current) return;
      moveProductImageNote(current);
      ensureDots(current);
      syncDots(current);
      markVideoThumb(current);
      syncVideoFrame(current);
      bindVideo(current);
      syncVideoOpen(current);
      var index = currentIndex(current);
      if (index !== lastIndex) {
        lastIndex = index;
        scrollActiveThumb(current);
      }
    }

    function schedule() {
      if (scheduled) return;
      scheduled = true;
      window.requestAnimationFrame(update);
    }

    if (window.MutationObserver) {
      var observer = new MutationObserver(schedule);
      observer.observe(root, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: ['class']
      });
    }

    if (window.jQuery) {
      window.jQuery(root).on('wc-product-gallery-after-init woocommerce_gallery_reset_slide_position', schedule);
    }

    refresh();
    window.addEventListener('load', refresh);
    window.addEventListener('resize', function () {
      syncVideoFrame(root);
    });
    if (window.ResizeObserver) {
      var observerBox = new ResizeObserver(function () {
        syncVideoFrame(gallery() || root);
      });
      var frame = root.querySelector('.flex-viewport') || root.querySelector('.woocommerce-product-gallery__image');
      if (frame) observerBox.observe(frame);
    }
    window.setTimeout(refresh, 200);
    window.setTimeout(refresh, 600);
    window.setTimeout(refresh, 1200);
  });
})();
