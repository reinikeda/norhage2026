/**
 * Single product: Save € note under the price, above delivery.
 * Updates when a variation is selected.
 */
(function ($) {
  'use strict';

  function summaryRoot($form) {
    var $summary = $form.closest('.summary, .entry-summary');
    if (!$summary.length) {
      $summary = $('.single-product .summary, .single-product .entry-summary').first();
    }
    return $summary;
  }

  function noteEl($summary) {
    return $summary.find('.nhg-save-note').first();
  }

  function priceEl($summary) {
    return $summary.find('p.price, span.price').filter(function () {
      return $(this).closest('.single_variation, .woocommerce-variation-price').length === 0;
    }).first();
  }

  function placeNote($summary, $note) {
    if (!$summary.length || !$note.length) {
      return;
    }

    var $price = priceEl($summary);
    var $delivery = $summary.find('.nh-delivery-time-under[data-nhdt="1"]').first();

    if ($price.length) {
      $note.insertAfter($price);
    }

    // Delivery script also repositions; if it already sits after price, keep Save between them.
    if ($delivery.length && $note.length && !$note.is('[hidden]')) {
      $delivery.insertAfter($note);
    }
  }

  function setNote($note, text) {
    if (!$note.length) {
      return;
    }
    text = (text || '').toString().trim();
    if (!text) {
      $note.attr('hidden', 'hidden').text('');
      return;
    }
    $note.removeAttr('hidden').text(text);
  }

  function bind($form) {
    if ($form.data('nhgSaveBound')) {
      return;
    }
    $form.data('nhgSaveBound', 1);

    var $summary = summaryRoot($form);
    var $note = noteEl($summary);
    if (!$note.length) {
      return;
    }

    var fallback = ($note.attr('data-nhg-save-default') || $note.text() || '').toString();

    placeNote($summary, $note);

    $form.on('found_variation.nhgSave', function (_event, variation) {
      if (!variation) {
        return;
      }
      var html = variation.nhg_save_html;
      if (typeof html === 'string' && html.length) {
        setNote($note, html);
      } else {
        setNote($note, '');
      }
      placeNote($summary, $note);
    });

    $form.on('hide_variation.nhgSave reset_data.nhgSave', function () {
      setNote($note, fallback);
      placeNote($summary, $note);
    });
  }

  function initSimple() {
    var $summary = $('.single-product .summary, .single-product .entry-summary').first();
    var $note = noteEl($summary);
    if (!$note.length) {
      return;
    }
    // Simple products (no variations form): still park under price / above delivery.
    if (!$('form.variations_form').length) {
      placeNote($summary, $note);
    }
  }

  $(function () {
    $('form.variations_form').each(function () {
      bind($(this));
    });
    initSimple();

    // Delivery script repositions on timeouts — follow it.
    setTimeout(function () {
      var $summary = $('.single-product .summary, .single-product .entry-summary').first();
      placeNote($summary, noteEl($summary));
    }, 60);
    setTimeout(function () {
      var $summary = $('.single-product .summary, .single-product .entry-summary').first();
      placeNote($summary, noteEl($summary));
    }, 220);
  });
})(jQuery);
