(function () {
    var filter = document.getElementById('nh-faq-product-filter');
    var list = document.getElementById('nh-faq-product-list');

    if (!filter || !list) {
        return;
    }

    filter.addEventListener('input', function () {
        var query = filter.value.toLowerCase();

        list.querySelectorAll('.nh-faq-product-item').forEach(function (row) {
            var label = row.getAttribute('data-label') || '';
            row.hidden = query !== '' && label.indexOf(query) === -1;
        });

        list.querySelectorAll('.nh-faq-product-topic').forEach(function (heading) {
            var sibling = heading.nextElementSibling;
            var visible = false;

            while (sibling && !sibling.classList.contains('nh-faq-product-topic')) {
                if (sibling.classList.contains('nh-faq-product-item') && !sibling.hidden) {
                    visible = true;
                }
                sibling = sibling.nextElementSibling;
            }

            heading.hidden = query !== '' && !visible;
        });
    });
})();
