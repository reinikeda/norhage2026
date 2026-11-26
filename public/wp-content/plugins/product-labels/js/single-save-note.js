/**
 * Single product: keep the Save € note in sync with the selected variation.
 */
(function ($) {
  'use strict';

  function noteEl($form) {
    var $summary = $form.closest('.summary, .entry-summary');
    if (!$summary.length) {
      $summary = $form.closest('.product');
    }
    return $summary.find('.nhg-save-note').first();
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

    var $note = noteEl($form);
    if (!$note.length) {
      return;
    }

    var fallback = ($note.attr('data-nhg-save-default') || $note.text() || '').toString();

    $form.on('found_variation.nhgSave', function (_event, variation) {
      if (!variation) {
        return;
      }
      var html = variation.nhg_save_html;
      if (typeof html === 'string' && html.length) {
        setNote($note, html);
        return;
      }
      // Selected variation is not on sale — hide until reset.
      setNote($note, '');
    });

    $form.on('hide_variation.nhgSave reset_data.nhgSave', function () {
      setNote($note, fallback);
    });
  }

  $(function () {
    $('form.variations_form').each(function () {
      bind($(this));
    });
  });
})(jQuery);
