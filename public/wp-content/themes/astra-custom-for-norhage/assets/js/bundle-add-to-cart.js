jQuery(document).ready(function ($) {
    function getPriceFromText(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        const text = tmp.textContent || tmp.innerText || "";
        const match = text.replace(',', '.').match(/[\d\.]+/);
        return match ? parseFloat(match[0]) : 0;
    }

    function updateBundlePrices() {
        let total = 0;

        $('.bundle-product').each(function () {
            const $product = $(this);
            const qty = parseInt($product.find('.bundle-qty').val(), 10) || 0;
            let price = 0;

            const basePriceHtml = $product.find('.price').html();
            if (basePriceHtml) price = getPriceFromText(basePriceHtml);

            const subtotal = qty * price;
            total += subtotal;

            $product.find('.product-subtotal').remove();
            if (qty > 0 && price > 0) {
                $product.append('<div class="product-subtotal">Subtotal: ' + subtotal.toFixed(2) + ' €</div>');
            }
        });

        $('#bundle-total').remove();
        if (total > 0) {
            $('#bundle-products').after('<div id="bundle-total"><strong>Total:</strong> ' + total.toFixed(2) + ' €</div>');
        }
    }

    $('#bundle-products').on('change', '.bundle-qty, select', function () {
        updateBundlePrices();
    });

    $('#add-bundle-to-cart').click(function () {
        const products = [];

        $('.bundle-product').each(function () {
            const $product = $(this);
            const quantity = parseInt($product.find('.bundle-qty').val(), 10) || 0;
            if (quantity <= 0) return;

            const productId = parseInt($product.data('product-id'));
            const productType = $product.data('product-type');
            const attributes = {};

            $product.find('select').each(function () {
                const name = $(this).attr('name');
                const value = $(this).val();
                if (value) attributes[name.replace('attribute_', '')] = value;
            });

            products.push({
                product_id: productId,
                quantity: quantity,
                attributes: attributes,
            });
        });

        if (products.length === 0) {
            $('#bundle-result').html('<div style="color:red;">Please select at least one product.</div>');
            return;
        }

        $('#bundle-result').html('Adding...');

        let completed = 0;
        let errors = [];

        products.forEach(product => {
            fetch(bundle_ajax.ajax_url + '?action=bundle_add_to_cart', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(product),
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) errors.push(data.data);
                completed++;
                if (completed === products.length) {
                    if (errors.length) {
                        $('#bundle-result').html('<div style="color:red;">' + errors.join('<br>') + '</div>');
                    } else {
                        $('#bundle-result').html('<div style="color:green;">All products added to cart!</div>');
                    }
                }
            });
        });
    });

    // Initial load
    updateBundlePrices();
});
