jQuery(function ($) {
    $(document).on('click', '.norhage-add-sample', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var productId = $btn.data('product_id');
        var $form = $btn.closest('.product, .summary, .type-product').find('form.cart').first();
        if (!$form.length) {
            $form = $('form.variations_form, form.cart').first();
        }
        var variationId = parseInt($form.find('input[name="variation_id"]').val(), 10) || 0;
        var i18n = norhageSample.i18n || {};

        $btn.prop('disabled', true).text(i18n.adding || 'Adding...');

        $.post(norhageSample.ajax_url, {
            action: 'norhage_add_sample',
            nonce: norhageSample.nonce,
            product_id: productId,
            variation_id: variationId
        })
        .done(function (response) {
            if (response.success) {
                $btn.text(i18n.added || 'Added');

                var fragments = response.data && response.data.fragments;
                var cartHash = response.data && response.data.cart_hash;

                // Apply fragments from THIS request (sample price already on
                // the product). A later wc_fragment_refresh would rebuild from
                // the catalog product and flash the wrong line price.
                if (fragments) {
                    $.each(fragments, function (selector, html) {
                        $(selector).replaceWith(html);
                    });
                    try {
                        var params = window.wc_cart_fragments_params || {};
                        var key = params.cart_hash_key || 'wc_cart_hash';
                        if (cartHash && window.sessionStorage) {
                            sessionStorage.setItem(key, String(cartHash));
                        }
                    } catch (err) { /* private mode */ }
                    $(document.body).trigger('added_to_cart', [fragments, cartHash, $btn]);
                    $(document.body).trigger('wc_fragments_refreshed');
                } else {
                    $(document.body).trigger('wc_fragment_refresh');
                }

                $(document.body).trigger('nh_side_cart_open');

                setTimeout(function () {
                    $btn.prop('disabled', false).text(i18n.add_sample || 'Add sample');
                }, 2000);
            } else {
                alert(response.data && response.data.message
                    ? response.data.message
                    : (i18n.error_generic || 'Could not add sample to cart.'));

                $btn.prop('disabled', false).text(i18n.add_sample || 'Add sample');
            }
        })
        .fail(function () {
            alert(i18n.error_connect || 'Could not connect to WooCommerce. Please try again.');
            $btn.prop('disabled', false).text(i18n.add_sample || 'Add sample');
        });
    });
});
