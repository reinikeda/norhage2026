(function (window) {
	// Ensure the WordPress data module exists
	if (!window.wp || !window.wp.data) {
		return;
	}

	const { select, subscribe } = window.wp.data;

	// Known Woo cart store keys (varies a bit by version)
	const STORE_KEYS = ['wc/store/cart', 'wc/store'];

	/**
	 * Get current cart data from one of the known stores.
	 * Returns null if not available (e.g. classic cart).
	 */
	function getCartDataSafe() {
		for (let i = 0; i < STORE_KEYS.length; i++) {
			try {
				const store = select(STORE_KEYS[i]);
				if (store && typeof store.getCartData === 'function') {
					const data = store.getCartData();
					if (data) {
						return data;
					}
				}
			} catch (e) {
				// Try next key
			}
		}
		return null;
	}

	/**
	 * Convert raw itemsWeight into display weight.
	 * On Woo Blocks, itemsWeight is in GRAMS when the store unit is kg.
	 */
	function normalizeWeight(rawWeight, unit) {
		let value = Number(rawWeight || 0);

	    // Store unit is kg, Woo Blocks cartData.itemsWeight is in grams.
		if (unit.toLowerCase() === 'kg') {
			value = value / 1000;
		}

		return value;
	}

	/**
	 * Format a numeric weight value in clean EU style:
	 * - always 2 decimals
	 * - comma as decimal separator
	 * - NO thousands separator
	 */
	function formatWeight(weight, unit) {
		const decimals    = 2;
		const decSep      = ',';
		const thousandSep = ''; // no thousands separator

		const fixed = Number(weight || 0).toFixed(decimals);
		let [intPart, fracPart] = fixed.split('.');

		if (thousandSep) {
			intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
		}

		return intPart + decSep + fracPart + ' ' + unit;
	}

	/**
	 * Render the current cart weight into the `.nhgp-cart-total-weight`
	 * value element, if present.
	 */
	function renderWeight() {
		const el = document.querySelector('.nhgp-cart-total-weight');
		if (!el) return;

		const cartData = getCartDataSafe();
		if (!cartData || typeof cartData.itemsWeight === 'undefined') {
			return;
		}

		const unit   = el.getAttribute('data-unit') || 'kg';
		const value  = normalizeWeight(cartData.itemsWeight, unit);
		el.textContent = formatWeight(value, unit);
	}

	// Initial render after the DOM is ready
	document.addEventListener('DOMContentLoaded', renderWeight);

	// Re-render whenever the cart store changes (qty change, add/remove, etc.)
	let lastWeight = null;

	subscribe(function () {
		const cartData = getCartDataSafe();
		if (!cartData || typeof cartData.itemsWeight === 'undefined') {
			return;
		}

		const weight = cartData.itemsWeight;
		if (weight === lastWeight) {
			return;
		}

		lastWeight = weight;
		renderWeight();
	});
})(window);
