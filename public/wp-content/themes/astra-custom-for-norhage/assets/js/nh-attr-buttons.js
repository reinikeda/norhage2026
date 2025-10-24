/* nh-attr-buttons.js — robust attribute buttons for Woo selects */
jQuery(function ($) {
  "use strict";

  /* --------- Read visual tokens from Add to Cart (for styling parity) --------- */
  function getPrimaryBtnStyles() {
    const $ref = $('.single_add_to_cart_button').first();
    if (!$ref.length) return null;
    const cs = getComputedStyle($ref[0]);
    return {
      bg: cs.backgroundColor,
      color: cs.color,
      radius: cs.borderRadius,
      padY: cs.paddingTop,
      padX: cs.paddingLeft,
      font: cs.font,
      lineHeight: cs.lineHeight,
      letterSpacing: cs.letterSpacing
    };
  }
  const PRIMARY = getPrimaryBtnStyles();

  function applyLook($btn, selected) {
    if (PRIMARY) {
      $btn.css({
        borderRadius: PRIMARY.radius,
        paddingTop: PRIMARY.padY,
        paddingBottom: PRIMARY.padY,
        paddingLeft: PRIMARY.padX,
        paddingRight: PRIMARY.padX,
        font: PRIMARY.font,
        lineHeight: PRIMARY.lineHeight,
        letterSpacing: PRIMARY.letterSpacing
      });
    }
    if (selected) {
      $btn
        .addClass('is-selected')
        .css({
          backgroundColor: PRIMARY ? PRIMARY.bg : '',
          color: PRIMARY ? PRIMARY.color : '',
          borderColor: PRIMARY ? PRIMARY.bg : '',
          borderStyle: 'solid',
          borderWidth: '1px'
        });
    } else {
      $btn
        .removeClass('is-selected')
        .css({
          backgroundColor: 'transparent',
          color: PRIMARY ? PRIMARY.bg : '',
          borderColor: '#cde8e1',
          borderStyle: 'solid',
          borderWidth: '1px'
        });
    }
  }

  /* ---------------------------- Build / Wire buttons --------------------------- */
  function buildButtons($select) {
    // skip if already built
    if ($select.data('nh-buttons')) return;

    // Some themes initialize SelectWoo; hide that mirror UI so our buttons are visible
    const $selectWoo = $select.next('.select2, .select2-container, .select2-container--default');
    if ($selectWoo.length) $selectWoo.hide();

    // Wrapper after the select (keeps Woo’s DOM expectations intact)
    const $wrap = $('<div class="nh-attr-buttons" />');

    // Build a button for each option (skip the placeholder)
    $select.find('option').each(function () {
      const val = this.value;
      if (!val) return;
      const txt = $(this).text();

      const $btn = $('<button type="button" class="nh-attr-btn button" />')
        .attr('data-value', val)
        .text(txt)
        .on('click', function () {
          // Set select value and propagate the usual Woo events
          $select.val(val).trigger('change').trigger('focusout');
        });

      applyLook($btn, false);
      $wrap.append($btn);
    });

    // Hide the original select (we already hid SelectWoo above if present)
    $select.hide().after($wrap);
    $select.data('nh-buttons', true);

    // Initial sync of selected/disabled states
    syncSelectedState($select);
    syncDisabledStates($select);

    // Keep in sync on change (Woo or user)
    $select.on('change.nhButtons', function () {
      syncSelectedState($select);
      // availability may also change because another attribute changed
      syncDisabledStates($select);
    });
  }

  function syncSelectedState($select) {
    const $wrap = $select.next('.nh-attr-buttons');
    if (!$wrap.length) return;
    const val = String($select.val() || '');
    $wrap.find('.nh-attr-btn').each(function () {
      const $b = $(this);
      const isSel = $b.attr('data-value') === val;
      applyLook($b, isSel);
    });
  }

  function syncDisabledStates($select) {
    const $wrap = $select.next('.nh-attr-buttons');
    if (!$wrap.length) return;
    $wrap.find('.nh-attr-btn').each(function () {
      const val = $(this).attr('data-value');
      const isDisabled = $select.find('option[value="' + val + '"]').is(':disabled');
      $(this).prop('disabled', isDisabled).toggleClass('is-disabled', isDisabled);
    });
  }

  /* ------------------------------ Init & re-init ------------------------------ */
  function initAll($root) {
    ($root || $(document)).find('.variations_form select').each(function () {
      buildButtons($(this));
    });
  }

  // Initial build
  initAll();

  // Woo fires this when it (re)initializes a variations form
  $(document).on('wc_variation_form', function (e) {
    initAll($(e.target));
  });

  // Woo fires this after it recalculates which options are available
  $(document).on('woocommerce_update_variation_values', function () {
    $('.variations_form select').each(function () {
      syncDisabledStates($(this));
      syncSelectedState($(this));
    });
  });

  // If SelectWoo re-renders the select container, rebuild our buttons just once
  const mo = new MutationObserver(function (mutations) {
    let shouldReinit = false;
    mutations.forEach(function (m) {
      if (m.addedNodes && m.addedNodes.length) shouldReinit = true;
    });
    if (shouldReinit) initAll();
  });
  $('.variations_form').each(function () {
    mo.observe(this, { childList: true, subtree: true });
  });
});
