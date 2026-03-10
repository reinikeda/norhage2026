(function () {

	function moveProductImageNote() {

		const note = document.querySelector('.single-product .nh-product-image-note');
		const gallery = document.querySelector('.single-product .woocommerce-product-gallery');

		if (!note || !gallery) return;

		const thumbs = gallery.querySelector('.flex-control-nav.flex-control-thumbs');

		// Gallery with thumbnails
		if (thumbs) {
			if (note.parentNode !== gallery || note.previousElementSibling !== thumbs) {
				thumbs.insertAdjacentElement('afterend', note);
			}
			return;
		}

		// Single image
		if (gallery.lastElementChild !== note) {
			gallery.appendChild(note);
		}

	}

	document.addEventListener('DOMContentLoaded', moveProductImageNote);
	window.addEventListener('load', moveProductImageNote);

	setTimeout(moveProductImageNote, 200);
	setTimeout(moveProductImageNote, 600);
	setTimeout(moveProductImageNote, 1200);

})();
