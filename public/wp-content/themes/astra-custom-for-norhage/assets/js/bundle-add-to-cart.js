jQuery(function ($) {
  'use strict';

  // --- CONFIG / STATE ---
  const $variationForm = $('form.variations_form');
  const $mainForm      = $('form.cart');
  const $mainQtyInput  = $mainForm.find('.quantity input.qty');
  const INCLUDE_MAIN_IN_TOTAL = true;
  const REDIRECT_AFTER_ADD = false; // keep false to stay on product page
  let posting = false;

  // --- WC AJAX ENDPOINTS ---
  function wcAjaxEndpoint(endpoint) {
    const src = (window.wc_add_to_cart_params && window.wc_add_to_cart_params.wc_ajax_url)
             || (window.wc_cart_fragments_params && window.wc_cart_fragments_params.wc_ajax_url)
             || '/?wc-ajax=%%endpoint%%';
    return src.replace('%%endpoint%%', endpoint);
  }

  const ADD_TO_CART_URL    = wcAjaxEndpoint('add_to_cart');
  const CART_FRAGMENTS_URL = wcAjaxEndpoint('get_refreshed_fragments');
  const cartUrl            = (window.bundle_ajax && bundle_ajax.cart_url) || '';

  // --- FORMAT / PARSE HELPERS (from form data attributes) ---
  function getBundleForm() {
    return $('#nc-bundle-form');
  }

  function getPriceCfg() {
    const $form = getBundleForm();

    return {
      symbol:   String($form.attr('data-currency-symbol') || ''),
      pos:      String($form.attr('data-currency-pos') || 'right_space'),
      decimals: Number.isFinite(Number($form.attr('data-decimals')))
        ? parseInt($form.attr('data-decimals'), 10)
        : 2,
      thousand: String($form.attr('data-thousand') ?? '.'),
      decimal:  String($form.attr('data-decimal') ?? ',')
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
        return value + (p.symbol ? nbsp + p.symbol : ''); // right_space
    }
  }

  // Parse localized money text using configured thousand/decimal separators.
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

    // If multiple dots remain, keep the last one as decimal separator
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
    val = parseInt(val, 10);
    if (!Number.isFinite(val)) val = 0;
    if (Number.isFinite(min)) val = Math.max(min, val);
    if (Number.isFinite(max) && max > 0) val = Math.min(max, val);
    return val;
  }

  function getMainQty() {
    const n = parseInt($mainQtyInput.val(), 10);
    return Number.isFinite(n) && n > 0 ? n : 1;
  }

  function getRowUnitPrice($row) {
    const raw = String($row.attr('data-base-price') || '').trim();
    const unit = Number(raw);
    return Number.isFinite(unit) && unit > 0 ? unit : 0;
  }

  function getRowPerSetQty($row) {
    const $q = $row.find('input.qty:enabled');
    const q  = clamp($q.val(), Number($q.attr('min') || 0), Number($q.attr('max') || 0));
    if ($q.length && String(q) !== String($q.val())) $q.val(q);
    return q;
  }

  function isVariationChosen() {
    const $vi = $('form.variations_form').find('input[name="variation_id"]');
    if (!$vi.length) return true; // simple product
    const id = parseInt($vi.val(), 10);
    return Number.isFinite(id) && id > 0;
  }

  // --- TOTALS ---
  function getMainDisplayedTotal() {
    const $ps = $('#nh-price-summary [data-ps="total"]');
    if ($ps.length) {
      const ins = $ps.find('ins').text().trim();
      const txt = ins || $ps.text().trim();
      if (txt && txt !== '—') return parseMoneySmart(txt);
    }

    if ($('form.variations_form').length && !isVariationChosen()) return 0;

    const el = document.getElementById('nh-line-total');
    if (el && el.textContent.trim().length) return parseMoneySmart(el.textContent);

    const $alt = $('.single_variation .price, .summary .price').first();
    if ($alt.length) return parseMoneySmart($alt.text());

    return 0;
  }

  function collectRowAttributes($row) {
    const attrs = {};
    $row.find('.bundle-variation').each(function () {
      const m = this.name && this.name.match(/\[([^]]+)\]/);
      if (m && this.value) attrs['attribute_' + m[1]] = this.value;
    });
    return attrs;
  }

  function keyFor(pid, vid, attrs) {
    const parts = ['pid=' + pid, 'vid=' + (vid || 0)];
    const ks = Object.keys(attrs || {}).sort();
    ks.forEach(k => parts.push(k + '=' + attrs[k]));
    return parts.join('|');
  }

  function collectDesiredAddons() {
    const map = new Map(); // key -> {pid, qty, unit, vid, attrs}

    $('#nc-complete-set .nc-bundle-form .nc-bundle-row[data-product-id]').each(function () {
      const $row = $(this);
      const $qty = $row.find('input.qty:enabled:visible');
      if (!$qty.length) return;

      const pid   = String($row.data('product-id'));
      const unit  = getRowUnitPrice($row);
      const qty   = getRowPerSetQty($row);
      if (!qty) return;

      const attrs = collectRowAttributes($row);
      const vid   = parseInt(($row.find('.selected-variation-id').val() || '0'), 10) || 0;
      const k     = keyFor(pid, vid, attrs);

      if (!map.has(k)) map.set(k, { pid, qty, unit, vid, attrs });
    });

    return Array.from(map.values());
  }

  // --- BUNDLE BUTTON ENABLE / DISABLE ---
  function updateBundleButtonState() {
    const $btn = $('#add-bundle-to-cart');
    if (!$btn.length) return;

    const items        = collectDesiredAddons();
    const anyQty       = items.length > 0;
    const variationOk  = isVariationChosen();
    const shouldEnable = anyQty && variationOk;

    $btn.prop('disabled', !shouldEnable)
      .toggleClass('is-disabled', !shouldEnable)
      .attr('aria-disabled', shouldEnable ? 'false' : 'true');
  }

  function updateGrandTotal() {
    const items  = collectDesiredAddons();
    const addOns = items.reduce((sum, it) => sum + it.unit * it.qty, 0);
    const main   = INCLUDE_MAIN_IN_TOTAL ? getMainDisplayedTotal() : 0;

    $('#bundle-total-amount').html(fmt(main + addOns));

    // keep button state in sync
    updateBundleButtonState();
  }

  // Observe the main total changing
  (function observePrice() {
    const node = document.querySelector('#nh-price-summary [data-ps="total"]')
              || document.querySelector('.single_variation .price')
              || document.querySelector('.summary .price');
    if (!node) return;

    const debounced = (fn => {
      let t;
      return () => {
        clearTimeout(t);
        t = setTimeout(fn, 60);
      };
    })(updateGrandTotal);

    new MutationObserver(debounced).observe(node, {
      childList: true,
      subtree: true,
      characterData: true
    });
  })();

  $variationForm.on('found_variation show_variation hide_variation reset_data', () => setTimeout(updateGrandTotal, 10));
  $mainQtyInput.on('input change keyup mouseup', updateGrandTotal);
  $(document).on('input change keyup mouseup', '#nc-complete-set .qty', updateGrandTotal);
  $(document).on('nh:lineTotalUpdated', updateGrandTotal);
  $(document.body).on('updated_wc_div wc_fragments_loaded wc_fragments_refreshed', updateGrandTotal);

  // --- Make the +/- buttons work for bundle rows ---
  $(document).on('click', '#nc-bundle-form .quantity .plus, #nc-bundle-form .quantity .minus', function (e) {
    e.preventDefault();

    const $btn = $(this);
    const $qty = $btn.siblings('input.qty');
    if (!$qty.length || $qty.is(':disabled')) return;

    const step = parseFloat($qty.attr('step')) || 1;
    const min  = isNaN(parseFloat($qty.attr('min'))) ? -Infinity : parseFloat($qty.attr('min'));
    const max  = isNaN(parseFloat($qty.attr('max'))) ? Infinity : parseFloat($qty.attr('max'));
    let val    = parseFloat($qty.val()) || 0;

    if ($btn.hasClass('plus')) val += step;
    else val -= step;

    val = Math.max(min, Math.min(max, val));
    $qty.val(val).trigger('change');
  });

  // Also re-run when the product qty +/- is used
  $(document).on('click', 'form.cart .quantity .plus, form.cart .quantity .minus', function () {
    setTimeout(updateGrandTotal, 0);
  });

  // initial totals & button state
  updateGrandTotal();

  // --- HELPERS for building the MAIN payload correctly ---
  function getParentProductId() {
    let id = parseInt($mainForm.find('input[name="product_id"]').val(), 10);
    if (!id) id = parseInt($variationForm.data('product_id'), 10);
    if (!id) id = parseInt($mainForm.find('input[name="add-to-cart"]').val(), 10);
    if (!id) id = parseInt($mainForm.find('button[name="add-to-cart"]').val(), 10);
    if (!id) id = parseInt($variationForm.attr('data-product_id'), 10);
    return id || 0;
  }

  function collectMainVariationAttrs() {
    const attrs = {};
    $('form.variations_form')
      .find('select[name^="attribute_"], input[name^="attribute_"]')
      .each(function () {
        if (this.name && this.value) attrs[this.name] = this.value;
      });
    return attrs;
  }

  function serializeMainFormToObj() {
    const obj = {};
    $mainForm.serializeArray().forEach(function (p) {
      if (p && p.name) obj[p.name] = p.value;
    });

    delete obj.action;
    delete obj._wpnonce;
    delete obj._wp_http_referer;

    return obj;
  }

  function buildMainPayload() {
    const pid   = getParentProductId();
    const qty   = String(getMainQty());
    const vid   = parseInt($('form.variations_form').find('input[name="variation_id"]').val(), 10) || 0;
    const attrs = collectMainVariationAttrs();
    const form  = serializeMainFormToObj();

    Object.keys(attrs).forEach(function (k) {
      form[k] = attrs[k];
      form['variation[' + k + ']'] = attrs[k];
    });

    // VARIABLE product flow
    if (vid || Object.keys(attrs).length) {
      const postData = {
        'add-to-cart': String(pid),
        quantity: qty
      };

      if (vid) postData.variation_id = vid;

      Object.keys(form).forEach(function (k) {
        if (k.indexOf('nh_') === 0 || k.indexOf('attribute_') === 0 || k.indexOf('variation[') === 0) {
          postData[k] = form[k];
        }
      });

      return postData;
    }

    // SIMPLE product flow
    return Object.assign({}, form, { product_id: String(pid), quantity: qty });
  }

  // --- Cart fragments refresh ---
  function refreshFragments() {
    $(document.body).trigger('wc_fragment_refresh');

    return $.post(CART_FRAGMENTS_URL).done(function (resp) {
      if (resp && resp.fragments) {
        $.each(resp.fragments, function (key, value) {
          $(key).replaceWith(value);
        });
        $(document.body).trigger('wc_fragments_refreshed');
      }
    });
  }

  // --- CORE HANDLER ---
  function handleAddAllClick(e) {
    const $btn = $(e.target).closest('#add-bundle-to-cart');
    if (!$btn.length) return;

    if ($btn.is(':disabled')) {
      e.preventDefault();
      return;
    }

    e.preventDefault();
    if (posting) return;

    const pid = getParentProductId();
    if (!pid) {
      const msg = 'Please refresh the page and try again (product id missing).';
      const $wrap = $('.woocommerce-notices-wrapper, form.cart').first();
      if ($wrap.length) {
        $wrap.prepend('<ul class="woocommerce-error" role="alert"><li>' + msg + '</li></ul>');
      } else {
        alert(msg);
      }
      return;
    }

    posting = true;
    $btn.prop('disabled', true).addClass('is-busy');

    const mainData      = buildMainPayload();
    const addonItems    = collectDesiredAddons();
    const successAddons = [];

    // 1) Add main product
    $.post(ADD_TO_CART_URL, mainData)
      .fail(function () {
        const msg = 'Choose product options (size/variation) before adding the complete set.';
        const $wrap = $('.woocommerce-notices-wrapper, form.cart').first();
        if ($wrap.length) {
          $wrap.prepend('<ul class="woocommerce-error" role="alert"><li>' + msg + '</li></ul>');
        } else {
          alert(msg);
        }
      })
      .always(function () {
        // 2) Add-ons in sequence
        let seq = $.Deferred().resolve().promise();

        addonItems.forEach(function (it) {
          seq = seq.then(function () {
            const data = { product_id: it.pid, quantity: it.qty };

            Object.keys(it.attrs || {}).forEach(function (k) {
              data['variation[' + k + ']'] = it.attrs[k];
            });

            if (it.vid) data.variation_id = it.vid;

            return $.post(ADD_TO_CART_URL, data)
              .done(function (resp) {
                if (resp && !resp.error) {
                  successAddons.push({
                    product_id: String(it.vid || it.pid),
                    quantity: it.qty
                  });
                }
              })
              .catch(function () {});
          });
        });

        // 3) Combined notice + refresh
        seq.always(function () {
          const parentId = getParentProductId();
          const vId      = parseInt($('form.variations_form').find('input[name="variation_id"]').val(), 10) || 0;
          const mainLine = {
            product_id: String(vId || parentId),
            quantity: getMainQty()
          };

          const lines = [mainLine].concat(successAddons);

          $.post(wcAjaxEndpoint('nh_bundle_notice'), { items: JSON.stringify(lines) })
            .done(function (resp) {
              if (resp && resp.success && resp.data && resp.data.html) {
                let $wrap = $('.woocommerce-notices-wrapper').first();

                if (!$wrap.length) {
                  const $anchor = $('.woocommerce-notices-wrapper, form.cart, .summary').first();
                  $wrap = $('<div class="woocommerce-notices-wrapper"></div>');
                  if ($anchor.length) $wrap.insertBefore($anchor);
                  else $('main, body').first().prepend($wrap);
                }

                $wrap.html(resp.data.html);
                $(document.body).trigger('wc_notices_refreshed');
              }
            })
            .always(function () {
              refreshFragments().always(function () {
                posting = false;
                $btn.prop('disabled', false).removeClass('is-busy');
                updateBundleButtonState();
                if (REDIRECT_AFTER_ADD && cartUrl) window.location = cartUrl;
              });
            });
        });
      });
  }

  // LISTENERS
  $(document)
    .off('click.ncBundle', '#add-bundle-to-cart')
    .on('click.ncBundle', '#add-bundle-to-cart', handleAddAllClick);

  document.addEventListener('click', handleAddAllClick, true);

  document.addEventListener('keydown', function (e) {
    if (!e.target) return;
    const isBtn = e.target.id === 'add-bundle-to-cart' || !!e.target.closest?.('#add-bundle-to-cart');
    if (!isBtn) return;
    if (e.key === 'Enter' || e.key === ' ') handleAddAllClick(e);
  }, true);
});
