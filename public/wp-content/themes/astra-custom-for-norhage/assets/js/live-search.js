jQuery(function ($) {
  var doneTypingInterval = 300;

  function escHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function initLiveSearch($wrap) {
    if (!$wrap.length || $wrap.data('nrhLiveSearchReady')) return;
    $wrap.data('nrhLiveSearchReady', true);

    var $input = $wrap.find('input[type="search"]').first();
    var $results = $wrap.find('.nh-live-results').first();
    var $status = $wrap.find('[role="status"]').first();
    var typingTimer;
    var currentRequest = null;
    var uid = $input.attr('id') || ('nrh-search-' + Math.random().toString(36).slice(2, 8));

    if (!$input.length || !$results.length) return;

    function openResults() {
      $wrap.addClass('is-open');
      $results.show().attr('aria-hidden', 'false');
      $input.attr('aria-expanded', 'true');
      $('.nhhb-header-main').addClass('search-active');
    }

    function closeResults() {
      $wrap.removeClass('is-open');
      $results.hide().attr('aria-hidden', 'true');
      $input.attr('aria-expanded', 'false').removeAttr('aria-activedescendant');
      $results.find('[role="option"][aria-selected="true"]').attr('aria-selected', 'false').removeClass('is-active');
      $status.text('');
      $('.nhhb-header-main').removeClass('search-active');

      if (currentRequest && currentRequest.readyState && currentRequest.readyState !== 4) {
        try { currentRequest.abort(); } catch (e) { /* ignore */ }
      }
      currentRequest = null;
    }

    function setActive($el) {
      if (!$el || !$el.length) return;
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
        var id = uid + '-result-' + idx;
        var $li = $('<li>', {
          id: id,
          role: 'option',
          'aria-selected': 'false',
          class: 'nh-live-item',
          tabindex: -1
        });

        var $a = $('<a>', { class: 'nh-live-link', href: item.link || '#' });
        var $img = $('<img>', {
          class: 'nh-thumb',
          src: item.img || '',
          alt: '',
          loading: 'lazy',
          decoding: 'async',
          width: 48,
          height: 48
        });
        var $info = $('<div>', { class: 'info' });
        var $title = $('<div>', { class: 'title', text: item.title || '' });
        var $price = $('<div>', { class: 'nh-price' });

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
        var $more = $('<a>', { class: 'nh-live-more', href: url, html: escHtml('View all results') + (total ? ' (' + total + ')' : '') });
        $footer.append($more);
        $frag.append($footer);
      }

      $results.append($frag);
      announceCount(total || items.length);
      openResults();
    }

    $input.on('input', function () {
      clearTimeout(typingTimer);
      var q = $(this).val().trim();

      if (q.length < 2) {
        $results.empty();
        closeResults();
        return;
      }

      typingTimer = setTimeout(function () {
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
          error: function (_, status) {
            currentRequest = null;
            if (status === 'abort') return;
            $results.html('<li class="no-results" role="option">Search temporarily unavailable</li>');
            announceCount(0);
            openResults();
          }
        });
      }, doneTypingInterval);
    });

    $input.on('focus', function () {
      if ($results.children().length) openResults();
    });

    $input.on('keydown', function (e) {
      var $options = $results.find('[role="option"]');
      if (!$options.length) {
        if (e.key === 'Escape') closeResults();
        return;
      }

      var activeId = $input.attr('aria-activedescendant');
      var $active = activeId ? $results.find('#' + activeId) : $();

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        var $next = $active.length ? $active.nextAll('[role="option"]').first() : $options.first();
        if ($next.length) setActive($next);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        var $prev = $active.length ? $active.prevAll('[role="option"]').first() : $options.last();
        if ($prev.length) setActive($prev);
      } else if (e.key === 'Enter') {
        if ($active && $active.length) {
          var href = $active.find('a').attr('href');
          if (href) {
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

    $results.on('mouseover', '[role="option"]', function () {
      setActive($(this));
    });

    $results.on('mousedown', function (e) {
      e.stopPropagation();
    });

    $results.on('click', 'a', function () {
      closeResults();
    });

    $(document).on('click.nrhLiveSearch', function (e) {
      if (!$(e.target).closest($wrap).length) {
        closeResults();
      }
    });

    $(document).on('keydown.nrhLiveSearch', function (e) {
      if (e.key === 'Escape') closeResults();
    });
  }

  $('.nh-live-search, .nrh-live-search').each(function () {
    initLiveSearch($(this));
  });
});
