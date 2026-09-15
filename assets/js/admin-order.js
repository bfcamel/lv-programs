(function ($) {
	'use strict';

	function updateOrder($list) {
		var inputId = $list.data('order-input');
		var ids = $list.children('[data-program-id]').map(function () {
			return $(this).data('program-id');
		}).get();

		$('#' + inputId).val(ids.join(','));
	}

	$(function () {
		$('.lvfp-sortable').each(function () {
			var $list = $(this);
			$list.sortable({
				handle: '.lvfp-sortable__handle, .lvfp-sortable__title',
				placeholder: 'lvfp-sortable__placeholder',
				forcePlaceholderSize: true,
				update: function () {
					updateOrder($list);
				}
			});
		});

		$('.lvfp-order-form').on('submit', function () {
			$('.lvfp-sortable').each(function () {
				updateOrder($(this));
			});
		});
	});
}(jQuery));
