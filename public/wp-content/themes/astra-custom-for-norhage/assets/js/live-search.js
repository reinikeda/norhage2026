jQuery(function ($) {
  var $input   = $('#nrh-search-input');
  var $wrap    = $input.closest('.nh-live-search, .nrh-live-search');
  var $results = $('#nrh-search-results');
  var typingTimer;
  var doneTypingInterval = 300;

  function closeResults() {
    $wrap.removeClass('is-open');
    $results.hide();
  }

  function openResults() {
    $wrap.addClass('is-open');
    $results.show();
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
      $.ajax({
        url: nrh_live_search.ajax_url,
        method: 'GET',
        data: { action: nrh_live_search.action, q: q },
        success: function (resp) {
          // Back-compat: server may return an array (old) or an object (new)
          var items = Array.isArray(resp) ? resp : (resp.items || []);
          var more  = !Array.isArray(resp) && !!resp.more;
          var url   = !Array.isArray(resp) ? resp.url : '';
          var total = !Array.isArray(resp) ? (resp.total || 0) : 0;

          if (!items.length) {
            $results.html('<li class="no-results">No results found</li>');
            openResults();
            return;
          }

          var html = items.map(function (item) {
            return (
              '<li class="nh-live-item">' +
                '<a class="nh-live-link" href="' + item.link + '">' +
                  '<img class="nh-thumb" src="' + item.img + '" alt="" loading="lazy" width="48" height="48" />' +
                  '<div class="info">' +
                    '<div class="title">' + item.title + '</div>' +
                    '<div class="nh-price">' + (item.price || '') + '</div>' +
                  '</div>' +
                '</a>' +
              '</li>'
            );
          }).join('');

          // Footer CTA if there are more results
          if (more && url) {
            html += '' +
              '<li class="nh-live-footer">' +
                '<a class="nh-live-more" href="' + url + '">' +
                  'View all results' + (total ? ' (' + total + ')' : '') +
                '</a>' +
              '</li>';
          }

          $results.html(html);
          openResults();
        },
        error: function () {
          $results.html('<li class="no-results">Search temporarily unavailable</li>');
          openResults();
        }
      });
    }, doneTypingInterval);
  });

  // Show results again if input regains focus and has content
  $input.on('focus', function () {
    if ($results.children().length) openResults();
  });

  // Close on click outside
  $(document).on('click', function (e) {
    if (!$(e.target).closest('.nh-live-search, .nrh-live-search').length) {
      closeResults();
    }
  });

  // Close on Escape
  $(document).on('keydown', function (e) {
    if (e.key === 'Escape') closeResults();
  });
});
