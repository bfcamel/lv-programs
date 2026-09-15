(function () {
	'use strict';

	function initSection(section) {
		if (section.dataset.lvfpReady === '1') {
			return;
		}

		section.dataset.lvfpReady = '1';
		var cards = Array.prototype.slice.call(section.querySelectorAll('.lvfp-card'));

		cards.forEach(function (card) {
			var image = card.querySelector('.lvfp-card__img');
			if (image) {
				var replaceBrokenImage = function () {
					var placeholder = document.createElement('span');
					placeholder.className = 'lvfp-card__placeholder';
					placeholder.setAttribute('aria-hidden', 'true');
					placeholder.innerHTML = '<span>ЛВ</span>';
					image.replaceWith(placeholder);
				};

				image.addEventListener('error', replaceBrokenImage, { once: true });
				if (image.complete && image.naturalWidth === 0) {
					replaceBrokenImage();
				}
			}
		});

		if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			cards.forEach(function (card) {
				card.classList.add('lvfp-card--visible');
			});
			return;
		}

		section.classList.add('lvfp-motion-ready');
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) {
					return;
				}

				entry.target.classList.add('lvfp-card--visible');
				observer.unobserve(entry.target);
			});
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

		cards.forEach(function (card) {
			observer.observe(card);
		});
	}

	document.querySelectorAll('.lvfp').forEach(initSection);
}());
