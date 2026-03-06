jQuery(function ($) {
	const $summary = $('.single-product .summary');
	const $box = $summary.find('.nh-delivery-time-under[data-nhdt="1"]').first();
	const $form = $('form.variations_form');

	if (!$summary.length || !$box.length) {
		return;
	}

	function moveBoxAfterPrice() {
		const $price = $summary.find('p.price').first();

		if ($price.length) {
			$box.insertAfter($price);
		}
	}

	function setBoxText(text) {
		const clean = String(text || '').trim();
		const $value = $box.find('.nh-delivery-time__value');

		if (clean) {
			$value.text(clean);
			$box.show();
		} else {
			$value.text('');
			$box.hide();
		}
	}

	moveBoxAfterPrice();

	const initialText = String($box.find('.nh-delivery-time__value').text() || '').trim();

	if ($form.length) {
		$form.on('found_variation', function (event, variation) {
			moveBoxAfterPrice();

			const text = variation && variation.nh_delivery_text
				? variation.nh_delivery_text
				: initialText;

			setBoxText(text);
		});

		$form.on('reset_data hide_variation', function () {
			moveBoxAfterPrice();
			setBoxText(initialText);
		});
	}

	$(document).on('wc_variation_form woocommerce_variation_select_change', function () {
		moveBoxAfterPrice();
	});

	// Extra safety in case theme/scripts repaint the summary later
	setTimeout(moveBoxAfterPrice, 50);
	setTimeout(moveBoxAfterPrice, 200);
});
