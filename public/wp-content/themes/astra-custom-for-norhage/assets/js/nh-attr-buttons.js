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

  /* ------------------ Compute attribute values present in any in-stock variation ------------------ */
  function computeAvailableValuesMap($form) {
    // Build map: { attribute_name: { valueSlug: true, ... }, ... }
    var variations = $form.data('product_variations') || [];
    var map = {};

    for (var i = 0; i < variations.length; i++) {
      var v = variations[i];
      // Count only IN-STOCK variations as "available"
      if (!v || v.is_in_stock !== true) continue;
      var attrs = v.attributes || {};
      for (var key in attrs) {
        if (!attrs.hasOwnProperty(key)) continue;
        var val = attrs[key];
        if (!val) continue;
        map[key] = map[key] || {};
        map[key][val] = true;
      }
    }

    // Cache for performance; we will refresh it when Woo updates variation values
    $form.data('nh-available-map', map);
    return map;
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
      var $form = $select.closest('.variations_form');
      $form.find('select[name^="attribute_"]').each(function () {
        syncSelectedState($(this));
        syncDisabledStates($(this));
      });
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

    var $form = $select.closest('.variations_form');
    var variations = $form.data('product_variations') || [];

    // Build map of current selections for other selects
    var current = {};
    $form.find('select[name^="attribute_"]').each(function () {
      var name = $(this).attr('name');
      var v = String($(this).val() || '');
      if (v) current[name] = v;
    });

    $wrap.find('.nh-attr-btn').each(function () {
      const $btn = $(this);
      const val  = $btn.attr('data-value');

      // Look for matching <option> (native Woo disabled states)
      const $opt = $select.find('option[value="' + val + '"]');
      const isDisabledBySelect = !$opt.length || $opt.is(':disabled');

      // Determine if there exists an IN-STOCK variation that:
      // - has this select's attribute == val
      // - AND for every other attribute that has a current selection, variation[attr] === selected value
      var selectName = $select.attr('name');
      var exists = false;
      for (var i = 0; i < variations.length; i++) {
        var v = variations[i];
        if (!v || v.is_in_stock !== true) continue; // only in-stock variations count
        var attrs = v.attributes || {};

        // must match candidate value for this select
        if (attrs[selectName] !== val) continue;

        var ok = true;
        for (var sName in current) {
          if (!current.hasOwnProperty(sName)) continue;
          if (sName === selectName) continue;
          var selVal = current[sName];
          // if user has selected something for sName and variation doesn't match, it's not a candidate
          if (selVal && attrs[sName] !== selVal) {
            ok = false;
            break;
          }
        }
        if (ok) { exists = true; break; }
      }

      // final disabled if native <option> disabled OR no matching in-stock variation exists for current selections
      var finalDisabled = isDisabledBySelect || !exists;

      $btn
        .prop('disabled', finalDisabled)
        .toggleClass('is-disabled', finalDisabled)
        .attr('aria-disabled', finalDisabled ? 'true' : null);
    });
  }

  /* ---------------------- Out-of-stock visual state (.is-oos) ---------------------- */
  function updateOOSState($form, variation) {
    // Clear previous OOS styling
    $form.find('.nh-attr-btn.is-oos').removeClass('is-oos');

    // If we have no variation or it is in stock, nothing more to do
    if (!variation || variation.is_in_stock !== false) {
      return;
    }

    // For an out-of-stock variation, mark the CURRENTLY SELECTED values
    $form.find('select[name^="attribute_"]').each(function () {
      const $select = $(this);
      const val = String($select.val() || '');
      if (!val) return;

      const $wrap = $select.next('.nh-attr-buttons');
      if (!$wrap.length) return;

      $wrap.find('.nh-attr-btn[data-value="' + val + '"]').addClass('is-oos');
    });
  }

  /* ---------------- All variations out of stock → show custom notice --------------- */
  function checkAllOutOfStock($form) {
    if (!$form || !$form.length) return;

    // Avoid repeating work
    if ($form.data('nh-all-oos-checked')) return;

    var variations = $form.data('product_variations');
    if (!variations || !variations.length) return;

    var anyInStock = false;
    for (var i = 0; i < variations.length; i++) {
      if (variations[i].is_in_stock) {
        anyInStock = true;
        break;
      }
    }
    if (anyInStock) return; // at least one variation in stock → nothing to do

    // Mark as processed
    $form.data('nh-all-oos-checked', true);
    $form.addClass('nh-all-variations-oos');

    // Build our own message text (translated via NH_ATTR_I18N)
    var msgText = 'Sorry, all combinations are unavailable.';
    if (window.NH_ATTR_I18N && NH_ATTR_I18N.all_oos_msg) {
      msgText = NH_ATTR_I18N.all_oos_msg;
    }

    // Create (or reuse) our custom message element
    var $msg = $form.find('.nh-all-oos-msg');
    if (!$msg.length) {
      $msg = $('<p class="nh-all-oos-msg stock out-of-stock"></p>');
      // Insert just AFTER the attributes table
      var $attrs = $form.find('table.variations').first();
      if ($attrs.length) {
        $msg.insertAfter($attrs);
      } else {
        // Fallback: at the top of the form
        $form.prepend($msg);
      }
    }
    $msg.text(msgText).show();

    // Disable Add to basket
    $form.closest('form.cart')
      .find('.single_add_to_cart_button')
      .addClass('disabled wc-variation-is-unavailable')
      .prop('disabled', true);
  }

  /* ------------------------------ Init & re-init ------------------------------ */
  function initAll($root) {
    ($root || $(document)).find('.variations_form select').each(function () {
      buildButtons($(this));
    });

    // For all forms we initialized, compute the availability map so buttons can be marked
    ($root || $(document)).find('.variations_form').each(function () {
      computeAvailableValuesMap($(this));
    });
  }

  // Initial build
  initAll();

  // Check if all variations are out of stock on initial load
  $('.variations_form').each(function () {
    checkAllOutOfStock($(this));
  });

  $(document).on('wc_variation_form', function (e) {
    var $form = $(e.target);
    initAll($form);
    checkAllOutOfStock($form);
  });

  // Woo fires this after it recalculates which options are available
  $(document).on('woocommerce_update_variation_values', function () {
    // Recompute availability map for each form (clear cached map and recompute)
    $('.variations_form').each(function () {
      var $form = $(this);
      // Clear cached map so it is rebuilt from the latest variations data
      $form.removeData('nh-available-map');
      computeAvailableValuesMap($form);

      // Now resync each select inside the form
      $form.find('select').each(function () {
        syncDisabledStates($(this));
        syncSelectedState($(this));
      });
    });
  });

  // Woo fires this when it finds a variation for current attributes
  $(document).on('found_variation', '.variations_form', function (e, variation) {
    const $form = $(this);
    updateOOSState($form, variation);
  });

  // Woo fires this when variation data is cleared / no match
  $(document).on('reset_data hide_variation', '.variations_form', function () {
    const $form = $(this);
    updateOOSState($form, null);
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
