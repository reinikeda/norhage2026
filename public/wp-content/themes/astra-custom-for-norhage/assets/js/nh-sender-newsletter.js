jQuery(function ($) {
  function attachNewsletterForm($form) {
    if (!$form.length) return;

    // Submit handler
    function submitHandler(e) {
      e.preventDefault();

      var $this = $form;
      var email = $.trim($this.find('input[name="email"]').val());
      var honeypot = $this.find('input[name="nhhb_hp"]').val(); // only exists on homepage form

      if (!email) {
        showMessage($this, nhSenderNewsletter.msg_invalid || 'Please enter your email.', 'error');
        return;
      }

      // Honeypot: if filled, silently "succeed"
      if (honeypot) {
        showMessage($this, 'Thank you!', 'success');
        return;
      }

      var $btn = $this.find('button[type="submit"], .nh-nl__btn, .nhhb-nl-btn').first();
      var originalText = $btn.data('original-text') || $btn.text();
      $btn.data('original-text', originalText);
      $btn.prop('disabled', true).text($btn.data('loading-text') || originalText);

      $.post(nhSenderNewsletter.ajax_url, {
        action: 'nh_sender_subscribe',
        nonce: nhSenderNewsletter.nonce,
        email: email
      })
        .done(function (resp) {
          if (resp && resp.success) {
              showMessage(
                $this,
                (resp.data && resp.data.message) || 'Thank you! You are subscribed.',
                'success'
              );
              $this.find('input[name="email"]').val('');
          } else {
              showMessage(
                $this,
                (resp && resp.data && resp.data.message) ||
                  'Sorry, subscription failed. Please try again.',
                'error'
              );

              // 🔥 Console debug output
              if (resp && resp.data && resp.data.debug) {
                console.warn("Sender debug:", resp.data.debug);
              }
          }
        })
        .fail(function () {
          showMessage(
            $this,
            'Network error. Please check your connection and try again.',
            'error'
          );
        })
        .always(function () {
          $btn.prop('disabled', false).text(originalText);
        });
    }

    // Attach submit event
    $form.on('submit', submitHandler);

    // If button type="button" (home newsletter), trigger submit manually
    $form.find('button[type="button"]').on('click', function (e) {
      e.preventDefault();
      $form.trigger('submit');
    });
  }

  function showMessage($form, msg, type) {
    var $box = $form.find('.nh-nl-msg');

    if (!$box.length) {
      $box = $('<p class="nh-nl-msg" aria-live="polite"></p>').appendTo($form);
    }

    $box
      .removeClass('is-success is-error')
      .addClass(type === 'success' ? 'is-success' : 'is-error')
      .text(msg);
  }

  // Footer form: <form class="nh-nl">
  attachNewsletterForm($('.nh-nl'));

  // Homepage custom plugin form: <form class="nhhb-nl-form">
  attachNewsletterForm($('.nhhb-nl-form'));
});
