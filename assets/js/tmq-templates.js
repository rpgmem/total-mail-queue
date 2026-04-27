/**
 * Templates admin tab — wires up the color pickers, media uploader, and
 * the "Send test email" AJAX button. Loaded only on the Templates tab.
 */
(function ($, tmq) {
	'use strict';

	if (typeof $ !== 'function' || !tmq) {
		return;
	}

	$(function () {
		// Native WP color picker on every .tmq-color-picker input.
		if ($.fn.wpColorPicker) {
			$('.tmq-color-picker').wpColorPicker();
		}

		// Logo media uploader — opens wp.media() and writes the selected
		// attachment URL into the bound input.
		$('.tmq-media-pick').on('click', function (event) {
			event.preventDefault();

			var targetId = $(this).data('target');
			var $target = $('#' + targetId);

			if (!window.wp || !wp.media) {
				return;
			}

			var frame = wp.media({
				title: tmq.i18n.mediaTitle,
				button: { text: tmq.i18n.mediaButton },
				library: { type: 'image' },
				multiple: false,
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				if (attachment && attachment.url) {
					$target.val(attachment.url).trigger('change');
				}
			});

			frame.open();
		});

		// "Send test email" button → admin-ajax.php roundtrip.
		var $btn = $('#tmq_send_test_email');
		var $result = $('#tmq_test_email_result');

		$btn.on('click', function () {
			var $self = $(this);
			var nonce = $self.data('nonce');
			var action = $self.data('action');
			var to = $('#tmq_test_email_to').val();

			$result.text('').removeClass('notice notice-success notice-error');
			$self.prop('disabled', true);
			var originalLabel = $self.text();
			$self.text(tmq.i18n.sending || 'Sending…');

			$.post(tmq.ajaxUrl, {
				action: action,
				_nonce: nonce,
				to: to,
			})
				.done(function (response) {
					if (response && response.success) {
						$result.addClass('notice notice-success').text((response.data && response.data.message) || '');
					} else {
						var msg = (response && response.data && response.data.message) || '';
						$result.addClass('notice notice-error').text((tmq.i18n.testFailed || '') + msg);
					}
				})
				.fail(function (xhr) {
					var msg = '';
					if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						msg = xhr.responseJSON.data.message;
					}
					$result.addClass('notice notice-error').text((tmq.i18n.testFailed || '') + msg);
				})
				.always(function () {
					$self.prop('disabled', false);
					$self.text(originalLabel);
				});
		});
	});
})(window.jQuery, window.tmq);
