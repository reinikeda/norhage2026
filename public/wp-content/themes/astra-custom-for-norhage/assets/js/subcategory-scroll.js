/**
 * Shop by Category — one-line horizontal strip with optional arrows.
 * Arrows hide when everything fits; disable at scroll ends.
 */
(function () {
	function init(section) {
		var track = section.querySelector('.subcategory-items');
		if (!track) {
			return;
		}

		var prev = section.querySelector('.subcategory-prev');
		var next = section.querySelector('.subcategory-next');
		var arrows = section.querySelector('.subcategory-arrows');

		function step() {
			var vis = track.clientWidth * 0.75;
			var first = track.querySelector('.subcategory-item');
			if (first) {
				var styles = window.getComputedStyle(track);
				var gap = parseFloat(styles.columnGap || styles.gap) || 0;
				var three = first.getBoundingClientRect().width * 3 + gap * 2;
				return Math.max(160, Math.min(vis, three));
			}
			return vis;
		}

		function overflowing() {
			return track.scrollWidth > track.clientWidth + 4;
		}

		function update() {
			var canScroll = overflowing();
			if (arrows) {
				arrows.classList.toggle('is-idle', !canScroll);
			}

			var atStart = track.scrollLeft <= 2;
			var atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
			if (prev) {
				prev.setAttribute('aria-disabled', !canScroll || atStart ? 'true' : 'false');
			}
			if (next) {
				next.setAttribute('aria-disabled', !canScroll || atEnd ? 'true' : 'false');
			}
		}

		if (prev) {
			prev.addEventListener('click', function () {
				track.scrollBy({ left: -step(), behavior: 'smooth' });
			});
		}
		if (next) {
			next.addEventListener('click', function () {
				track.scrollBy({ left: step(), behavior: 'smooth' });
			});
		}

		track.addEventListener('scroll', update, { passive: true });
		window.addEventListener('resize', update);
		if (typeof ResizeObserver !== 'undefined') {
			new ResizeObserver(update).observe(track);
		}
		update();
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-nh-subcats]').forEach(init);
	});
})();
