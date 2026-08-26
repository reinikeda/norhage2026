jQuery(function ($) {
    $(document).on('click', '.norhage-add-sample', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var productId = $btn.data('product_id');
        var i18n = norhageSample.i18n || {};

        $btn.prop('disabled', true).text(i18n.adding || 'Adding...');

        $.post(norhageSample.ajax_url, {
            action: 'norhage_add_sample',
            nonce: norhageSample.nonce,
            product_id: productId
        })
        .done(function (response) {
            if (response.success) {
                $btn.text(i18n.added || 'Added');
                $(document.body).trigger('wc_fragment_refresh');

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
