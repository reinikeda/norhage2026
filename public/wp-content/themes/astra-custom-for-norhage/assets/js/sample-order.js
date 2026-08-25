jQuery(function ($) {
    $(document).on('click', '.norhage-add-sample', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var productId = $btn.data('product_id');

        $btn.prop('disabled', true).text('Adding...');

        $.post(norhageSample.ajax_url, {
            action: 'norhage_add_sample',
            nonce: norhageSample.nonce,
            product_id: productId
        })
        .done(function (response) {
            if (response.success) {
                $btn.text('Added');
                $(document.body).trigger('wc_fragment_refresh');

                setTimeout(function () {
                    $btn.prop('disabled', false).text('Add sample');
                }, 2000);
            } else {
                alert(response.data && response.data.message
                    ? response.data.message
                    : 'Could not add sample to cart.');

                $btn.prop('disabled', false).text('Add sample');
            }
        })
        .fail(function () {
            alert('Could not connect to WooCommerce. Please try again.');
            $btn.prop('disabled', false).text('Add sample');
        });
    });
});
