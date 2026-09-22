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
      if (note.parentNode !== root || note.previousElementSibling !== nav) {
        nav.insertAdjacentElement('afterend', note);
      }
      return;
    }

    if (root.lastElementChild !== note) {
      root.appendChild(note);
    }
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
      btn.setAttribute('aria-label', imageLabel(i + 1, total));
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
    window.setTimeout(refresh, 200);
    window.setTimeout(refresh, 600);
    window.setTimeout(refresh, 1200);
  });
})();
