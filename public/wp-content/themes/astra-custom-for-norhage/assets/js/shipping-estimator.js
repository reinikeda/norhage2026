(function ($) {
    'use strict';

    $(document).on('click', '#cse-toggle', function () {
        var $toggle = $(this);
        var $body = $('#cse-body');
        var $icon = $toggle.find('.cse-toggle-icon');
        var isOpen = $body.is(':visible');

        $body.slideToggle(200);
        $toggle.attr('aria-expanded', isOpen ? 'false' : 'true');
        $icon.text(isOpen ? '+' : '−');
    });

    $(document).on('click', '#cse-calculate', function () {
        var $btn = $(this);
        var country = $('#cse-country').val();
        var postcode = $('#cse-postcode').val().trim();
        var $results = $('#cse-results');

        if (!country) {
            showMessage(cse_params.i18n.fill_country, $results, 'error');
            return;
        }

        if (!postcode) {
            showMessage(cse_params.i18n.fill_postcode, $results, 'error');
            return;
        }

        $btn.prop('disabled', true).text(cse_params.i18n.calculating);
        $results.hide().html('');

        $.ajax({
            url: cse_params.ajax_url,
            type: 'POST',
            data: {
                action: 'cse_calculate_shipping',
                nonce: cse_params.nonce,
                country: country,
                postcode: postcode
            },
            success: function (response) {
                if (!response || !response.success) {
                    showMessage(
                        response && response.data && response.data.message ? response.data.message : cse_params.i18n.error,
                        $results,
                        'error'
                    );
                    return;
                }

                if (response.data.empty || !response.data.rates || !response.data.rates.length) {
                    showMessage(cse_params.i18n.no_methods, $results, 'error');
                    return;
                }

                var html = '<div class="cse-results-box">';
                html += '<ul class="cse-rates">';

                $.each(response.data.rates, function (index, rate) {
                    html += '<li class="cse-rate' + (rate.free ? ' cse-rate--free' : '') + '">';
                    html += '<span class="cse-rate-label">' + rate.label + '</span>';
                    html += '<span class="cse-rate-cost">' + (rate.free ? cse_params.i18n.free : rate.cost) + '</span>';
                    html += '</li>';
                });

                html += '</ul>';
                html += '</div>';

                $results.html(html).slideDown(200);
            },
            error: function () {
                showMessage(cse_params.i18n.error, $results, 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).text(cse_params.i18n.calculate);
            }
        });
    });

    $(document).on('keydown', '#cse-postcode', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#cse-calculate').trigger('click');
        }
    });

    function showMessage(message, $container, type) {
        var className = type === 'error' ? 'cse-error' : 'cse-note';
        $container.html('<div class="' + className + '">' + message + '</div>').slideDown(200);
    }

})(jQuery);
