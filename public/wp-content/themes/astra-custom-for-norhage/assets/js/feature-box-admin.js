(function ($) {

    // Sortable list
    $('#nhf-sortable').sortable({
        handle: '.nhf-grip',
        axis: 'y'
    });

    // Enforce max feature limit
    $('#nhf-sortable').on('change', '.nhf-check', function () {
        var max     = parseInt(NHFAdmin.max || 6, 10);
        var checked = $('#nhf-sortable .nhf-check:checked').length;
        if (checked > max) {
            alert(NHFAdmin.i18n.limit);
            this.checked = false;
        }
    });

})(jQuery);
