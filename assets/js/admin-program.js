(function ($) {
	'use strict';

	$(function () {
		$('.lvfp-image-control').each(function () {
			var $control = $(this);
			var $preview = $control.find('.lvfp-image-preview');
			var $input = $control.find('.lvfp-image-id');
			var $remove = $control.find('.lvfp-remove-image');
			var frame;

			$control.find('.lvfp-select-image').on('click', function () {
				if (frame) {
					frame.open();
					return;
				}

				frame = wp.media({
					title: 'Выберите изображение программы',
					button: { text: 'Использовать изображение' },
					library: { type: 'image' },
					multiple: false
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					var source = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
					$input.val(attachment.id);
					$preview.removeClass('is-empty').html($('<img>', { src: source, alt: '' }));
					$remove.prop('hidden', false);
				});

				frame.open();
			});

			$remove.on('click', function () {
				$input.val('');
				$preview.addClass('is-empty').empty();
				$remove.prop('hidden', true);
			});
		});
	});
}(jQuery));
