/**
 * Immo Manager – Admin JavaScript
 *
 * - WP Color Picker
 * - Unsaved-Changes-Warnung
 * - API-Key Generator
 */
(function ($) {
	'use strict';

	$(function () {
		// WP Color Picker.
		if ($.fn.wpColorPicker) {
			$('.immo-color-picker').wpColorPicker();
		}

		// Unsaved-Changes Warnung im Settings-Form.
		var $form = $('#immo-manager-settings-form');
		if ($form.length) {
			var formChanged = false;
			$form.on('change input', 'input, select, textarea', function () { formChanged = true; });
			$form.on('submit', function () { formChanged = false; });
			$(window).on('beforeunload', function () {
				if (formChanged) {
					return (window.immoManagerAdmin && window.immoManagerAdmin.i18n && window.immoManagerAdmin.i18n.unsavedChanges)
						? window.immoManagerAdmin.i18n.unsavedChanges
						: 'Du hast ungespeicherte Änderungen.';
				}
			});
		}

		// API-Key Generator.
		$(document).on('click', '.immo-generate-api-key', function () {
			var $btn   = $(this);
			var nonce  = $btn.data('nonce');
			var $wrap  = $btn.closest('.immo-api-key-field');
			var $new   = $wrap.find('.immo-api-key-new');
			var $value = $wrap.find('.immo-api-key-value');

			if (!window.confirm('Einen neuen API-Key generieren? Der bisherige Key wird ungültig.')) {
				return;
			}

			$btn.prop('disabled', true).text('Generiere…');

			$.post(window.ajaxurl, {
				action: 'immo_generate_api_key',
				nonce:  nonce
			}).done(function (response) {
				if (response && response.success) {
					var key = response.data && response.data.key ? response.data.key : '';
					$value.text(key);
					$new.show();
					$btn.text('Neuen API-Key generieren (invalidiert alten)');
				} else {
					window.alert((response && response.data && response.data.message) || 'Fehler beim Generieren.');
					$btn.text('API-Key generieren');
				}
			}).fail(function () {
				window.alert('Netzwerkfehler.');
				$btn.text('API-Key generieren');
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});

		// API-Key in Clipboard kopieren.
		$(document).on('click', '.immo-copy-api-key', function () {
			var $btn = $(this);
			var key  = $btn.closest('.immo-api-key-new').find('.immo-api-key-value').text();
			if (!key) { return; }
			if (navigator.clipboard) {
				navigator.clipboard.writeText(key).then(function () {
					$btn.text('Kopiert ✓');
					setTimeout(function () { $btn.text('Kopieren'); }, 2000);
				});
			} else {
				window.prompt('API-Key (manuell kopieren):', key);
			}
		});

		// Demo-Daten Import / Remove.
		var $demo = $('.immo-demo-section');
		if ($demo.length) {
			var demoAjax = $demo.data('ajaxUrl');
			var demoNonce = $demo.data('nonce');
			var $status  = $demo.find('.immo-demo-status');
			var $spinner = $demo.find('.immo-demo-spinner');

			$demo.on('click', '.immo-import-demo', function () {
				if (!window.confirm('Demo-Daten importieren? Es werden 4 Immobilien und 2 Projekte angelegt.')) { return; }
				var $btn = $(this);
				$btn.prop('disabled', true);
				$spinner.show();
				$status.text('');

				$.post(demoAjax, { action: 'immo_import_demo', nonce: demoNonce })
					.done(function (res) {
						if (res.success) {
							$status.css('color', '#388e3c').text(res.data.message);
							$demo.find('.immo-remove-demo').prop('disabled', false);
						} else {
							$status.css('color', '#b71c1c').text((res.data && res.data.message) || 'Fehler.');
						}
					})
					.fail(function () { $status.css('color', '#b71c1c').text('Netzwerkfehler.'); })
					.always(function () { $btn.prop('disabled', false); $spinner.hide(); });
			});

			$demo.on('click', '.immo-remove-demo', function () {
				if (!window.confirm('Alle Demo-Daten wirklich entfernen?')) { return; }
				var $btn = $(this);
				$btn.prop('disabled', true);
				$spinner.show();
				$status.text('');

				$.post(demoAjax, { action: 'immo_remove_demo', nonce: demoNonce })
					.done(function (res) {
						if (res.success) {
							$status.css('color', '#1565c0').text(res.data.message);
							$btn.prop('disabled', true);
						} else {
							$status.css('color', '#b71c1c').text((res.data && res.data.message) || 'Fehler.');
							$btn.prop('disabled', false);
						}
					})
					.fail(function () { $status.css('color', '#b71c1c').text('Netzwerkfehler.'); $btn.prop('disabled', false); })
					.always(function () { $spinner.hide(); });
			});
		}

		// Design-Style Picker Interaktivität.
		$(document).on('change', '.immo-design-style-option input[type="radio"]', function () {
			var $picker = $(this).closest('.immo-design-style-picker');
			$picker.find('.immo-design-style-option').removeClass('selected');
			$(this).closest('.immo-design-style-option').addClass('selected');
		});
	});
})(jQuery);
