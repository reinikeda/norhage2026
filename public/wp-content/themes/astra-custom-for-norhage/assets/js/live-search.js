jQuery(function ($) {
  var $input   = $('#nrh-search-input');
  var $wrap    = $input.closest('.nh-live-search, .nrh-live-search');
  var $results = $('#nrh-search-results');
  var $status  = $('#nrh-search-status');
  var typingTimer;
  var doneTypingInterval = 300;
  var currentRequest = null;

  // helpers
  function openResults() {
    $wrap.addClass('is-open');
    $results.show().attr('aria-hidden', 'false');
    $input.attr('aria-expanded', 'true');
    // add header state class to temporarily disable nav hover/dropdowns
    $('.nhhb-header-main').addClass('search-active');
  }

  function closeResults() {
    $wrap.removeClass('is-open');
    $results.hide().attr('aria-hidden', 'true');
    $input.attr('aria-expanded', 'false').removeAttr('aria-activedescendant');
    // clear visual selection
    $results.find('[role="option"][aria-selected="true"]').attr('aria-selected', 'false').removeClass('is-active');
    $status.text('');
    // remove header state class
    $('.nhhb-header-main').removeClass('search-active');

    // abort any pending request
    if (currentRequest && currentRequest.readyState && currentRequest.readyState !== 4) {
      try { currentRequest.abort(); } catch (e) { /* ignore */ }
    }
    currentRequest = null;
  }

  function setActive($el) {
    if (!$el || !$el.length) return;
    // deselect previous
    $results.find('[role="option"][aria-selected="true"]').attr('aria-selected', 'false').removeClass('is-active');
    $el.attr('aria-selected', 'true').addClass('is-active');
    $input.attr('aria-activedescendant', $el.attr('id'));
  }

  function announceCount(count) {
    if (!$status.length) return;
    if (count === 0) {
      $status.text('No results found');
    } else {
      $status.text(count + (count === 1 ? ' result' : ' results'));
    }
  }

  // escape helper (basic)
  function escHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function renderItems(items, more, url, total) {
    $results.empty();
    $input.removeAttr('aria-activedescendant');

    if (!items || !items.length) {
      $results.html('<li class="no-results" role="option">No results found</li>');
      announceCount(0);
      openResults();
      return;
    }

    var $frag = $(document.createDocumentFragment());

    items.forEach(function (item, idx) {
      var id = 'nrh-result-' + idx;
      var $li = $('<li>', {
        id: id,
        role: 'option',
        'aria-selected': 'false',
        class: 'nh-live-item',
        tabindex: -1
      });

      var $a = $('<a>', { class: 'nh-live-link', href: item.link || '#' });

      // thumbnail
      var $img = $('<img>', {
        class: 'nh-thumb',
        src: item.img || '',
        alt: '',
        loading: 'lazy',
        width: 48,
        height: 48
      });

      var $info = $('<div>', { class: 'info' });
      var $title = $('<div>', { class: 'title', text: item.title || '' });
      var $price = $('<div>', { class: 'nh-price' });

      // price contains markup from server (Woo). If you don't trust it, use text() instead.
      if (item.price) {
        $price.html(item.price);
      }

      $info.append($title).append($price);
      $a.append($img).append($info);
      $li.append($a);
      $frag.append($li);
    });

    if (more && url) {
      var $footer = $('<li>', { class: 'nh-live-footer', role: 'option' });
      var $more = $('<a>', { class: 'nh-live-more', href: url, html: 'View all results' + (total ? ' (' + total + ')' : '') });
      $footer.append($more);
      $frag.append($footer);
    }

    $results.append($frag);
    announceCount(total || items.length);
    openResults();
  }

  // typing handler
  $input.on('input', function () {
    clearTimeout(typingTimer);
    var q = $(this).val().trim();

    if (q.length < 2) {
      $results.empty();
      closeResults();
      return;
    }

    typingTimer = setTimeout(function () {
      // abort previous request if any
      if (currentRequest && currentRequest.readyState && currentRequest.readyState !== 4) {
        try { currentRequest.abort(); } catch (e) { /* ignore */ }
      }

      currentRequest = $.ajax({
        url: nrh_live_search.ajax_url,
        method: 'GET',
        data: { action: nrh_live_search.action, q: q },
        success: function (resp) {
          currentRequest = null;
          var items = Array.isArray(resp) ? resp : (resp.items || []);
          var more  = !Array.isArray(resp) && !!resp.more;
          var url   = !Array.isArray(resp) ? resp.url : '';
          var total = !Array.isArray(resp) ? (resp.total || 0) : 0;
          renderItems(items, more, url, total);
        },
        error: function () {
          currentRequest = null;
          $results.html('<li class="no-results" role="option">Search temporarily unavailable</li>');
          announceCount(0);
          openResults();
        }
      });
    }, doneTypingInterval);
  });

  // focus behavior: show results if present
  $input.on('focus', function () {
    if ($results.children().length) openResults();
  });

  // keyboard navigation
  $input.on('keydown', function (e) {
    var $options = $results.find('[role="option"]');
    if (!$options.length) {
      if (e.key === 'Escape') closeResults();
      return;
    }

    var activeId = $input.attr('aria-activedescendant');
    var $active = activeId ? $('#' + activeId) : $();

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      var $next = $active.length ? $active.nextAll('[role="option"]').first() : $options.first();
      if ($next.length) setActive($next);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      var $prev = $active.length ? $active.prevAll('[role="option"]').first() : $options.last();
      if ($prev.length) setActive($prev);
    } else if (e.key === 'Enter') {
      // if an option is active, follow its link
      if ($active && $active.length) {
        var href = $active.find('a').attr('href');
        if (href) {
          // close first to avoid strange overlays
          closeResults();
          window.location.href = href;
        }
      }
    } else if (e.key === 'Escape') {
      closeResults();
      $input.val('');
      $input.trigger('blur');
    }
  });

  // mouse interactions: hover sets active; click follows link naturally
  $results.on('mouseover', '[role="option"]', function () {
    setActive($(this));
  });

  // Prevent underlying nav hover from triggering before clicks inside results
  $results.on('mousedown', function (e) {
    e.stopPropagation();
  });

  $results.on('click', 'a', function (e) {
    // let natural navigation occur; close results
    closeResults();
  });

  // Close on click outside
  $(document).on('click', function (e) {
    if (!$(e.target).closest('.nh-live-search, .nrh-live-search').length) {
      closeResults();
    }
  });

  // Close on Escape anywhere
  $(document).on('keydown', function (e) {
    if (e.key === 'Escape') closeResults();
  });
});
