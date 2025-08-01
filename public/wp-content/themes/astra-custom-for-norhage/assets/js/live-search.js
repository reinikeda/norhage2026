jQuery(function($){
  var $input   = $('#nrh-search-input'),
      $results = $('#nrh-search-results'),
      typingTimer,
      doneTypingInterval = 300;

  $input.on('input', function(){
    clearTimeout(typingTimer);
    var q = $(this).val();
    if(q.length < 2){
      $results.hide();
      return;
    }
    typingTimer = setTimeout(function(){
      $.ajax({
        url: nrh_live_search.ajax_url,
        method: 'GET',
        data: {
          action: nrh_live_search.action,
          q: q
        },
        success: function(items){
          if(!items.length){
            $results.html('<li class="no-results">No results found</li>').show();
            return;
          }
            var html = items.map(function(item){
            return '<li>'
                +   '<img src="'+ item.img +'" width="48" height="48" />'
                +   '<div class="info">'
                +     '<div class="title"><a href="'+ item.link +'">'+ item.title +'</a></div>'
                +     '<div class="price">'+ item.price +'</div>'
                +   '</div>'
                + '</li>';
            }).join('');
          $results.html(html).show();
        }
      });
    }, doneTypingInterval);
  });

  $(document).on('click', function(e){
    if(!$(e.target).closest('.nrh-live-search').length){
      $results.hide();
    }
  });
});
