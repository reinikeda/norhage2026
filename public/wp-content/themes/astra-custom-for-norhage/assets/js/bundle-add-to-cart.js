jQuery(function ($) {
  'use strict';

  const $body = $(document.body);
  const $variationForm = $('form.variations_form').first();
  const $mainForm = $('form.cart').first();
  const $mainQtyInput = $mainForm.find('.quantity input.qty').first();

  const INCLUDE_MAIN_IN_TOTAL = true;
  const REDIRECT_AFTER_ADD = false;

  let posting = false;

  function wcAjaxEndpoint(endpoint) {
    const src =
      (window.wc_add_to_cart_params && window.wc_add_to_cart_params.wc_ajax_url) ||
      (window.wc_cart_fragments_params && window.wc_cart_fragments_params.wc_ajax_url) ||
      '/?wc-ajax=%%endpoint%%';

    return src.replace('%%endpoint%%', endpoint);
  }

  const BUNDLE_ADD_URL =
    (window.bundle_ajax && window.bundle_ajax.add_bundle_url) ||
    wcAjaxEndpoint('nh_add_bundle_to_cart');

  const cartUrl = (window.bundle_ajax && window.bundle_ajax.cart_url) || '';
  const ajaxNonce = (window.bundle_ajax && window.bundle_ajax.nonce) || '';

  function getBundleForm() {
    return $('#nc-bundle-form');
  }

  function getPriceCfg() {
    const $form = getBundleForm();
    const fallback = window.NH_PRICE_FMT || {};

    const decimalsRaw =
      $form.attr('data-decimals') != null
        ? $form.attr('data-decimals')
        : (fallback.decs != null ? fallback.decs : 2);

    return {
      symbol: String($form.attr('data-currency-symbol') || fallback.symbol || ''),
      pos: String($form.attr('data-currency-pos') || fallback.pos || 'right_space'),
      decimals: Number.isFinite(Number(decimalsRaw)) ? parseInt(decimalsRaw, 10) : 2,
      thousand: String(
        $form.attr('data-thousand') != null
          ? $form.attr('data-thousand')
          : (fallback.thousand != null ? fallback.thousand : '.')
      ),
      decimal: String(
        $form.attr('data-decimal') != null
          ? $form.attr('data-decimal')
          : (fallback.decimal != null ? fallback.decimal : ',')
      )
    };
  }

  function escapeRegExp(str) {
    return String(str).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function fmt(n) {
    const p = getPriceCfg();
    let num = Number(n || 0);

    if (!isFinite(num)) num = 0;

    const parts = num.toFixed(p.decimals).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, p.thousand);

    const value = p.decimals > 0 ? parts[0] + p.decimal + (parts[1] || '') : parts[0];
    const nbsp = '\u00A0';

    switch (p.pos) {
      case 'left':
        return p.symbol + value;
      case 'left_space':
        return p.symbol + nbsp + value;
      case 'right':
        return value + p.symbol;
      default:
        return value + (p.symbol ? nbsp + p.symbol : '');
    }
  }

  function fmtHtml(n) {
    if (window.NHPriceSummary && typeof window.NHPriceSummary.fmt === 'function') {
      return window.NHPriceSummary.fmt(n);
    }
    return fmt(n);
  }

  function parseMoneySmart(text) {
    if (!text) return 0;

    const p = getPriceCfg();
    let s = String(text);

    s = s.replace(/[\u00A0\u202F\s]/g, '');
    s = s.replace(/[^0-9,.\-]/g, '');

    if (p.thousand) {
      s = s.replace(new RegExp(escapeRegExp(p.thousand), 'g'), '');
    }

    if (p.decimal && p.decimal !== '.') {
      s = s.replace(new RegExp(escapeRegExp(p.decimal), 'g'), '.');
    }

    const lastDot = s.lastIndexOf('.');
    if (lastDot !== -1) {
      s =
        s.slice(0, lastDot).replace(/\./g, '') +
        '.' +
        s.slice(lastDot + 1).replace(/\./g, '');
    }

    const v = parseFloat(s);
    return Number.isFinite(v) ? v : 0;
  }

  function clamp(val, min, max) {
    let n = parseInt(val, 10);
    if (!Number.isFinite(n)) n = 0;

    const hasMin = Number.isFinite(Number(min));
    const hasMax = Number.isFinite(Number(max)) && Number(max) > 0;

    if (hasMin) n = Math.max(Number(min), n);
    if (hasMax) n = Math.min(Number(max), n);

    return n;
  }

  function getMainQty() {
    const n = parseInt($mainQtyInput.val(), 10);
    return Number.isFinite(n) && n > 0 ? n : 1;
  }

  function isVariationChosen() {
    const $vi = $variationForm.find('input[name="variation_id"]');
    if (!$vi.length) return true;

    const id = parseInt($vi.val(), 10);
    return Number.isFinite(id) && id > 0;
  }

  function isMainAddToCartActive() {
    const $btn = $mainForm.find('.single_add_to_cart_button').first();
    if (!$btn.length) return false;

    if ($btn.prop('disabled')) return false;
    if ($btn.is(':disabled')) return false;
    if ($btn.hasClass('disabled')) return false;

    const ariaDisabled = String($btn.attr('aria-disabled') || '').toLowerCase();
    if (ariaDisabled === 'true') return false;

    return true;
  }

  function getMainDisplayedTotal() {
    const $ps = $('#nh-price-summary [data-ps="total"]');
    if ($ps.length) {
      const ins = $ps.find('ins').text().trim();
      const txt = ins || $ps.text().trim();
      if (txt && txt !== '—') return parseMoneySmart(txt);
    }

    if ($variationForm.length && !isVariationChosen()) return 0;

    const el = document.getElementById('nh-line-total');
    if (el && el.textContent.trim().length) {
      return parseMoneySmart(el.textContent);
    }

    const $alt = $('.single_variation .price, .summary .price').first();
    if ($alt.length) return parseMoneySmart($alt.text());

    return 0;
  }

  function getRowQtyInput($row) {
    return $row.find('input.qty').first();
  }

  function getRowUnitPrice($row) {
    const raw = String($row.attr('data-base-price') || '').trim();
    const unit = Number(raw);
    return Number.isFinite(unit) && unit > 0 ? unit : 0;
  }

  function getRowPerSetQty($row) {
    const $q = getRowQtyInput($row);
    if (!$q.length) return 0;

    const q = clamp(
      $q.val(),
      Number($q.attr('min') || 0),
      Number($q.attr('max') || 0)
    );

    if (String(q) !== String($q.val())) {
      $q.val(q);
    }

    return q;
  }

  function collectRowAttributes($row) {
    const attrs = {};

    $row.find('.bundle-variation').each(function () {
      const name = this.name || '';
      const m = name.match(/\[([^\]]+)\]$/);

      if (m && this.value) {
        attrs['attribute_' + m[1]] = this.value;
      }
    });

    return attrs;
  }

  function keyFor(pid, vid, attrs) {
    const parts = ['pid=' + pid, 'vid=' + (vid || 0)];
    const ks = Object.keys(attrs || {}).sort();

    ks.forEach(function (k) {
      parts.push(k + '=' + attrs[k]);
    });

    return parts.join('|');
  }

  function collectDesiredAddons() {
    const map = new Map();

    $('#nc-complete-set .nc-bundle-row[data-product-id]').each(function () {
      const $row = $(this);
      if ($row.hasClass('nc-bundle-header')) return;

      const $qty = getRowQtyInput($row);
      if (!$qty.length || !$qty.is(':enabled')) return;

      const pid = String($row.data('product-id') || '');
      const unit = getRowUnitPrice($row);
      const qty = getRowPerSetQty($row);

      if (!pid || !qty) return;

      const attrs = collectRowAttributes($row);
      const vid = parseInt(($row.find('.selected-variation-id').val() || '0'), 10) || 0;

      if ($row.hasClass('is-variable') && !vid) return;

      const k = keyFor(pid, vid, attrs);

      if (!map.has(k)) {
        map.set(k, {
          product_id: pid,
          quantity: qty,
          variation_id: vid,
          attributes: attrs,
          unit: unit
        });
      }
    });

    return Array.from(map.values());
  }

  function getSerializedMainFormData() {
    const out = {};

    if (!$mainForm.length) return out;

    $mainForm.serializeArray().forEach(function (pair) {
      out[pair.name] = pair.value;
    });

    const $button = $mainForm.find('button.single_add_to_cart_button[name="add-to-cart"]').first();
    if ($button.length && !$button.prop('disabled') && $button.val()) {
      out['add-to-cart'] = $button.val();
    }

    return out;
  }

  function getMainPayload() {
    if (!$mainForm.length) return null;

    const formData = getSerializedMainFormData();

    const $button = $mainForm.find('button.single_add_to_cart_button[name="add-to-cart"]').first();
    const buttonVal = $button.length ? $button.val() : '';

    const productId = parseInt(
      formData['add-to-cart'] ||
      formData['product_id'] ||
      buttonVal ||
      $mainForm.find('input[name="product_id"]').val() ||
      0,
      10
    ) || 0;

    if (!productId) return null;

    const variationId = parseInt(
      $mainForm.find('input[name="variation_id"]').val() ||
      formData['variation_id'] ||
      0,
      10
    ) || 0;

    const attrs = {};
    $mainForm.find('select[name^="attribute_"], input[name^="attribute_"]').each(function () {
      if (this.value) {
        attrs[this.name] = this.value;
      }
    });

    return {
      product_id: String(productId),
      quantity: getMainQty(),
      variation_id: variationId,
      attributes: attrs,
      form_data: formData
    };
  }

  function ensureNoticeWrapper() {
    let $wrap = $('.woocommerce-notices-wrapper').first();

    if ($wrap.length) return $wrap;

    const $anchor = $('.woocommerce-notices-wrapper, form.cart, .summary, #nc-complete-set').first();

    $wrap = $('<div class="woocommerce-notices-wrapper"></div>');

    if ($anchor.length) {
      $wrap.insertBefore($anchor);
    } else {
      $wrap.prependTo('body');
    }

    return $wrap;
  }

  function stripForwardButtons($scope) {
    if (!$scope || !$scope.length) return;
    $scope.find('a.wc-forward, .button.wc-forward, a.added_to_cart').remove();
  }

  function showError(htmlOrText) {
    const $wrap = ensureNoticeWrapper();

    if (!htmlOrText) {
      $wrap.html(
        '<ul class="woocommerce-error" role="alert"><li>Unable to add bundle to basket.</li></ul>'
      );
    } else if (/<[a-z][\s\S]*>/i.test(String(htmlOrText))) {
      $wrap.html(htmlOrText);
    } else {
      $wrap.html(
        '<ul class="woocommerce-error" role="alert"><li>' +
          $('<div>').text(String(htmlOrText)).html() +
        '</li></ul>'
      );
    }

    stripForwardButtons($wrap);
    $body.trigger('wc_notices_refreshed');

    const node = $wrap.get(0);
    if (node && typeof node.scrollIntoView === 'function') {
      node.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function applyFragments(fragments) {
    if (!fragments) return;

    $.each(fragments, function (selector, html) {
      const $targets = $(selector);

      if ($targets.length) {
        $targets.each(function () {
          $(this).replaceWith(html);
        });
      }
    });
  }

  function setBusy($btn, busy) {
    posting = !!busy;

    $btn
      .prop('disabled', !!busy)
      .toggleClass('is-busy', !!busy)
      .attr('aria-busy', busy ? 'true' : 'false');

    if (!busy) {
      updateBundleButtonState();
    }
  }

  function setRowPriceHtml($row, html) {
    const finalHtml = html || '—';
    $row.find('.nc-price-desktop, .nc-price-mobile').html(finalHtml);
  }

  function getRowInitialPriceHtml($row) {
    return String($row.attr('data-initial-price-html') || '—');
  }

  function parseVariationsData($row) {
    let data = $row.attr('data-variations') || $row.data('variations');

    if (typeof data === 'string') {
      try {
        data = JSON.parse(data);
      } catch (_e) {
        data = [];
      }
    }

    return Array.isArray(data) ? data : [];
  }

  function clearVariableRow($row) {
    $row.find('.selected-variation-id').val('0');
    $row.attr('data-base-price', '0');
    setRowPriceHtml($row, getRowInitialPriceHtml($row));

    const $qty = getRowQtyInput($row);
    if ($qty.length) {
      $qty.prop('disabled', true).val('0');
    }

    $row.addClass('is-needs-variation').removeClass('is-variation-ready is-unavailable');
  }

  function enableVariableRow($row, variation) {
    const sale = Number(variation.display_price || 0);
    const reg = Number(variation.display_regular_price || sale || 0);

    let html = '—';
    if (sale > 0) {
      html = reg > sale
        ? '<del>' + fmtHtml(reg) + '</del> <ins>' + fmtHtml(sale) + '</ins>'
        : fmtHtml(sale);
    }

    setRowPriceHtml($row, html);
    $row.find('.selected-variation-id').val(String(variation.variation_id || 0));
    $row.attr('data-base-price', String(sale > 0 ? sale : 0));

    const $qty = getRowQtyInput($row);
    if ($qty.length) {
      const current = clamp($qty.val(), 0, Number($qty.attr('max') || 0));
      $qty.prop('disabled', false).val(current > 0 ? current : 0);
    }

    $row.removeClass('is-needs-variation is-unavailable').addClass('is-variation-ready');
  }

  function findMatchingVariation(variations, attrs) {
    if (!Array.isArray(variations) || !variations.length) return null;

    return variations.find(function (variation) {
      const vAttrs = variation.attributes || {};
      const keys = Object.keys(vAttrs);

      if (!keys.length) return false;

      return keys.every(function (k) {
        const want = String(attrs[k] || '');
        const have = String(vAttrs[k] || '');

        if (!want) return false;
        if (have === '') return true;

        return have === want;
      });
    }) || null;
  }

  function updateVariableRow($row) {
    const variations = parseVariationsData($row);
    const attrs = collectRowAttributes($row);
    const match = findMatchingVariation(variations, attrs);

    if (!match) {
      clearVariableRow($row);
      updateGrandTotal();
      return;
    }

    if (
      !match.variation_id ||
      match.is_in_stock === false ||
      match.is_purchasable === false ||
      match.variation_is_visible === false
    ) {
      clearVariableRow($row);
      $row.addClass('is-unavailable').removeClass('is-needs-variation is-variation-ready');
      updateGrandTotal();
      return;
    }

    enableVariableRow($row, match);
    updateGrandTotal();
  }

  function initVariableRows() {
    $('#nc-complete-set .nc-bundle-row.is-variable').each(function () {
      clearVariableRow($(this));
    });
  }

  function updateBundleButtonState() {
    const $btn = $('#add-bundle-to-cart');
    if (!$btn.length) return;

    const items = collectDesiredAddons();
    const anyAddonQty = items.length > 0;
    const mainActive = isMainAddToCartActive();

    const shouldEnable = !posting && mainActive && anyAddonQty;

    $btn
      .prop('disabled', !shouldEnable)
      .toggleClass('is-disabled', !shouldEnable)
      .attr('aria-disabled', shouldEnable ? 'false' : 'true');
  }

  function updateGrandTotal() {
    const items = collectDesiredAddons();
    const addOns = items.reduce(function (sum, it) {
      return sum + (Number(it.unit || 0) * Number(it.quantity || 0));
    }, 0);

    const main = INCLUDE_MAIN_IN_TOTAL ? getMainDisplayedTotal() : 0;
    $('#bundle-total-amount').html(fmtHtml(main + addOns));

    updateBundleButtonState();
  }

  function observeMainPriceChanges() {
    const node =
      document.querySelector('#nh-price-summary [data-ps="total"]') ||
      document.getElementById('nh-line-total') ||
      document.querySelector('.single_variation .price') ||
      document.querySelector('.summary .price');

    if (!node || typeof MutationObserver === 'undefined') return;

    const mo = new MutationObserver(function () {
      updateGrandTotal();
    });

    mo.observe(node, {
      childList: true,
      subtree: true,
      characterData: true
    });
  }

  function observeMainButtonState() {
    const btn = $mainForm.find('.single_add_to_cart_button').get(0);
    if (!btn || typeof MutationObserver === 'undefined') return;

    const mo = new MutationObserver(function () {
      updateBundleButtonState();
    });

    mo.observe(btn, {
      attributes: true,
      attributeFilter: ['disabled', 'class', 'aria-disabled']
    });
  }

  function requestBundleAdd(main, addons) {
    return $.ajax({
      url: BUNDLE_ADD_URL,
      type: 'POST',
      dataType: 'json',
      data: {
        security: ajaxNonce,
        main: JSON.stringify(main),
        addons: JSON.stringify(addons)
      }
    });
  }

  function cleanupForwardLinksAroundButton($btn) {
    $btn.siblings('a.wc-forward, a.added_to_cart, .button.wc-forward').remove();
    $btn.closest('.nc-bundle-footer').find('a.wc-forward, a.added_to_cart, .button.wc-forward').remove();
  }

  function handleAddAllClick(e) {
    e.preventDefault();

    const $btn = $(e.currentTarget);
    if (!$btn.length || posting || $btn.is(':disabled')) return;

    cleanupForwardLinksAroundButton($btn);

    const main = getMainPayload();
    if (!main) {
      showError('Unable to detect the main product.');
      return;
    }

    if (!isMainAddToCartActive()) {
      showError('Please complete the main product selection first.');
      updateBundleButtonState();
      return;
    }

    const addons = collectDesiredAddons();
    if (!addons.length) {
      updateBundleButtonState();
      return;
    }

    setBusy($btn, true);

    requestBundleAdd(main, addons)
      .done(function (resp) {
        if (!resp || !resp.success || !resp.data) {
          showError('Unable to add bundle to basket.');
          return;
        }

        if (resp.data.fragments) {
          applyFragments(resp.data.fragments);
          $body.trigger('wc_fragments_refreshed');
        }

        if (typeof resp.data.notices_html !== 'undefined') {
          const $wrap = ensureNoticeWrapper();
          $wrap.html(resp.data.notices_html || '');
          $body.trigger('wc_notices_refreshed');
        }

        cleanupForwardLinksAroundButton($btn);

        if (REDIRECT_AFTER_ADD && cartUrl) {
          window.location = cartUrl;
        }
      })
      .fail(function (xhr) {
        const json = xhr && xhr.responseJSON ? xhr.responseJSON : null;

        if (json && json.data && json.data.notices_html) {
          showError(json.data.notices_html);
          return;
        }

        if (json && json.data && json.data.message) {
          showError(json.data.message);
          return;
        }

        showError('Unable to add bundle to basket.');
      })
      .always(function () {
        cleanupForwardLinksAroundButton($btn);
        setBusy($btn, false);
      });
  }

  $(document)
    .off('click.ncBundle', '#add-bundle-to-cart')
    .on('click.ncBundle', '#add-bundle-to-cart', handleAddAllClick);

  $(document)
    .off('click.ncBundleQty', '#nc-bundle-form .quantity .plus, #nc-bundle-form .quantity .minus')
    .on('click.ncBundleQty', '#nc-bundle-form .quantity .plus, #nc-bundle-form .quantity .minus', function (e) {
      e.preventDefault();
      e.stopPropagation();

      const $control = $(this);
      const $wrap = $control.closest('.quantity');
      const $qty = $wrap.find('input.qty').first();

      if (!$qty.length) return;
      if ($qty.prop('disabled') || $qty.is(':disabled')) return;

      const step = parseFloat($qty.attr('step')) || 1;
      const min = parseFloat($qty.attr('min'));
      const max = parseFloat($qty.attr('max'));
      let val = parseFloat($qty.val());

      if (!isFinite(val)) val = 0;

      if ($control.hasClass('plus')) {
        val += step;
      } else {
        val -= step;
      }

      if (isFinite(min)) val = Math.max(min, val);
      if (isFinite(max) && max > 0) val = Math.min(max, val);

      val = Math.round(val);

      $qty.val(String(val)).trigger('input').trigger('change').focus();
    });

  $(document)
    .off('input.ncBundleManual change.ncBundleManual', '#nc-complete-set .nc-bundle-row input.qty')
    .on('input.ncBundleManual change.ncBundleManual', '#nc-complete-set .nc-bundle-row input.qty', function () {
      if (this.disabled) return;

      let raw = String(this.value || '').replace(/[^\d]/g, '');
      let val = raw === '' ? 0 : parseInt(raw, 10);

      if (!Number.isFinite(val)) val = 0;

      const min = Number(this.min || 0);
      const max = Number(this.max || 0);

      if (Number.isFinite(min)) val = Math.max(min, val);
      if (Number.isFinite(max) && max > 0) val = Math.min(max, val);

      this.value = String(val);
      updateGrandTotal();
    });

  $(document)
    .off('change.ncBundleRowVar', '#nc-complete-set .bundle-variation')
    .on('change.ncBundleRowVar', '#nc-complete-set .bundle-variation', function () {
      const $row = $(this).closest('.nc-bundle-row');
      if ($row.length) {
        updateVariableRow($row);
      } else {
        updateGrandTotal();
      }
    });

  $(document)
    .off('input.ncBundleMainQty change.ncBundleMainQty', 'form.cart .quantity input.qty')
    .on('input.ncBundleMainQty change.ncBundleMainQty', 'form.cart .quantity input.qty', function () {
      setTimeout(updateGrandTotal, 10);
    });

  $(document)
    .off(
      'change.ncBundleMainAttrs input.ncBundleMainAttrs',
      'form.cart select[name^="attribute_"], form.cart input[name^="attribute_"]'
    )
    .on(
      'change.ncBundleMainAttrs input.ncBundleMainAttrs',
      'form.cart select[name^="attribute_"], form.cart input[name^="attribute_"]',
      function () {
        setTimeout(updateGrandTotal, 10);
      }
    );

  $(document)
    .off('input.ncBundleMainCustom change.ncBundleMainCustom', '#nh_width_mm, #nh_length_mm')
    .on('input.ncBundleMainCustom change.ncBundleMainCustom', '#nh_width_mm, #nh_length_mm', function () {
      setTimeout(updateGrandTotal, 10);
    });

  $(document)
    .off('click.ncBundleMainCustomBtns', '#nh-custom-size-wrap .plus, #nh-custom-size-wrap .minus')
    .on('click.ncBundleMainCustomBtns', '#nh-custom-size-wrap .plus, #nh-custom-size-wrap .minus', function () {
      setTimeout(updateGrandTotal, 10);
    });

  if ($variationForm.length) {
    $variationForm.on(
      'found_variation show_variation hide_variation reset_data woocommerce_variation_has_changed',
      function () {
        setTimeout(updateGrandTotal, 10);
      }
    );
  }

  initVariableRows();
  observeMainPriceChanges();
  observeMainButtonState();
  updateGrandTotal();
});