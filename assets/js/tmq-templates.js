/**
 * Templates admin tab — wires up the color pickers, media uploader, the
 * live preview and the "Send test email" AJAX button. Loaded only on the
 * Templates tab.
 */
(function ($, tmq) {
	'use strict';

	if (typeof $ !== 'function' || !tmq) {
		return;
	}

	$(function () {
		var refreshPreview = previewRefresher();

		// Native WP color picker on every .tmq-color-picker input. Its
		// change event fires while dragging, before the input holds the
		// new value, so the refresh is scheduled rather than run inline.
		if ($.fn.wpColorPicker) {
			$('.tmq-color-picker').wpColorPicker({
				change: refreshPreview,
				clear: refreshPreview,
			});
		}

		$('.tmq-templates-form').on('input change', refreshPreview);

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

	/**
	 * Build the debounced preview refresh: post the form's unsaved values,
	 * put the returned email into the sandboxed frame. A late reply from
	 * an earlier edit never overwrites a newer one.
	 *
	 * @return {Function} The refresh, safe to call on every keystroke.
	 */
	function previewRefresher() {
		var $frame = $('.tmq-templates-preview-frame');
		var $status = $('.tmq-templates-preview-status');
		var $form = $('.tmq-templates-form');
		var timer = null;
		var latest = 0;

		if (!$frame.length || !$form.length) {
			return function () {};
		}

		function send() {
			var ticket = ++latest;
			var data = $form.find(':input[name^="tmq_template["]').serializeArray();
			data.push({ name: 'action', value: $frame.data('action') });
			data.push({ name: 'nonce', value: $frame.data('nonce') });

			$status.text(tmq.i18n.previewUpdating || '');
			$.post(tmq.ajaxUrl, $.param(data))
				.done(function (response) {
					if (ticket !== latest) {
						return;
					}
					if (response && response.success && response.data) {
						$frame.attr('srcdoc', response.data.html);
						$status.text('');
					} else {
						$status.text(tmq.i18n.previewFailed || '');
					}
				})
				.fail(function () {
					if (ticket === latest) {
						$status.text(tmq.i18n.previewFailed || '');
					}
				});
		}

		return function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(send, 400);
		};
	}
})(window.jQuery, window.tmq);
