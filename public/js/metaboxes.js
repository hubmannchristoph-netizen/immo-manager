/**
 * Immo Manager – Metabox Interaktionen (Admin)
 *
 * - Bezirks-Dropdown abhängig vom Bundesland (AJAX)
 * - Units-CRUD im Projekt-Editor (Add/Edit/Delete via AJAX)
 */
(function ($) {
	'use strict';

	var ajaxUrl = (window.immoManagerAdmin && window.immoManagerAdmin.ajaxUrl) || window.ajaxurl;
	var i18n   = (window.immoManagerAdmin && window.immoManagerAdmin.i18n) || {};
	var allUnits = (window.immoManagerAdmin && window.immoManagerAdmin.units) || [];

	/* -----------------------------------------------------------------
	 * Region cascading
	 * ----------------------------------------------------------------- */
	function initRegionCascading() {
		var $state    = $('.immo-region-state');
		var $district = $('.immo-region-district');
		if (!$state.length || !$district.length) { return; }

		$state.on('change', function () {
			var state    = $(this).val();
			var $current = $district.first();
			var selected = $current.data('current') || '';

			$current.prop('disabled', true).html(
				'<option value="">' + (i18n.loading || 'Lade…') + '</option>'
			);

			if (!state) {
				$current.prop('disabled', false).html(
					'<option value="">' + (i18n.district || '— Bezirk —') + '</option>'
				);
				return;
			}

			$.get(ajaxUrl, {
				action: 'immo_districts',
				state:  state
			}).done(function (response) {
				if (!response || !response.success) {
					$current.html('<option value="">' + (i18n.district || '— Bezirk —') + '</option>');
					return;
				}
				var districts = (response.data && response.data.districts) || {};
				var html = '<option value="">' + (i18n.district || '— Bezirk —') + '</option>';
				Object.keys(districts).forEach(function (key) {
					var isSelected = (key === selected) ? ' selected' : '';
					html += '<option value="' + escapeHtml(key) + '"' + isSelected + '>' + escapeHtml(districts[key]) + '</option>';
				});
				$current.html(html).prop('disabled', false);
			}).fail(function () {
				$current.html('<option value="">' + (i18n.district || '— Bezirk —') + '</option>').prop('disabled', false);
			});
		});
	}

	/* -----------------------------------------------------------------
	 * Units CRUD
	 * ----------------------------------------------------------------- */
	function initUnitsManager() {
		var $manager = $('.immo-units-manager');
		if (!$manager.length) { return; }

		var ajaxSettings = {
			url:       $manager.data('ajaxUrl') || ajaxUrl,
			projectId: parseInt($manager.data('projectId'), 10) || 0,
			nonce:     $manager.data('nonce') || '',
			currency:  $manager.data('currency') || '€'
		};

		if (!ajaxSettings.projectId) { return; }

		var $editor  = $manager.find('.immo-unit-editor');
		var $list    = $manager.find('.immo-units-list');
		var $spinner = $editor.find('.spinner');
		var $msg     = $editor.find('.immo-unit-message');

		function openEditor(unit) {
			$editor.find('input, textarea, select').each(function () {
				var $f = $(this);
				var name = $f.attr('name');
				if (name === 'status') {
					$f.val(unit && unit.status ? unit.status : 'available');
				} else if (unit && Object.prototype.hasOwnProperty.call(unit, name)) {
					$f.val(unit[name]);
				} else if (this.type === 'number') {
					$f.val('0');
				} else {
					$f.val('');
				}
			});
			$editor.find('input[name="unit_id"]').val(unit && unit.id ? unit.id : 0);

			// Robustly handle floor plan data
			var fpId = unit && unit.floor_plan_image_id ? parseInt(unit.floor_plan_image_id, 10) : 0;
			$editor.find('input[name="floor_plan_image_id"]').val(fpId);

			var hasFloorPlan = fpId && unit && unit.floor_plan && unit.floor_plan.url;
			if (hasFloorPlan) {
				$editor.find('.immo-floorplan-preview').html('<img src="' + escapeHtml(unit.floor_plan.url_thumbnail || unit.floor_plan.url) + '" style="max-height:80px; width:auto;">');
				$editor.find('.immo-floorplan-remove').show();
			} else {
				$editor.find('.immo-floorplan-preview').empty();
				$editor.find('.immo-floorplan-remove').hide();
			}
			$editor.find('.immo-unit-editor-title').text(
				unit && unit.id ? (i18n.editUnit || 'Wohneinheit bearbeiten') : (i18n.addUnit || 'Wohneinheit hinzufügen')
			);
			$msg.text('').removeClass('error');
			$editor.css('display', 'flex');
			// Hintergrund fixieren
			$('body').css('overflow', 'hidden');
		}

		function closeEditor() {
			$editor.hide();
			$('body').css('overflow', '');
		}

		// Klick außerhalb der Modal-Box schließt diese
		$editor.on('click', function (e) {
			if (e.target === this) { closeEditor(); }
		});

		// Media Uploader für Grundriss
		var frame;
		$manager.on('click', '.immo-floorplan-btn', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('div');
			if (frame) {
				frame.open();
				return;
			}
			frame = wp.media({
				title: 'Grundriss wählen',
				multiple: false,
				library: { type: 'image' }
			});
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$wrap.find('input[name="floor_plan_image_id"]').val(attachment.id);
				$wrap.find('.immo-floorplan-preview').html('<img src="' + (attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url) + '" style="max-height:80px; width:auto;">');
				$wrap.find('.immo-floorplan-remove').show();
			});
			// Z-Index-Fix für Media-Modal über unserer Lightbox
			frame.on('open', function() { $editor.css('z-index', 159000); });
			frame.on('close', function() { $editor.css('z-index', ''); });
			frame.open();
		});
		$manager.on('click', '.immo-floorplan-remove', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('div');
			$wrap.find('input[name="floor_plan_image_id"]').val('0');
			$wrap.find('.immo-floorplan-preview').empty();
			$(this).hide();
		});

		function collectForm() {
			var data = {};
			$editor.find('.immo-unit-grid input, .immo-unit-grid select, .immo-unit-grid textarea').each(function () {
				var $f = $(this);
				var name = $f.attr('name');
				if (name) { data[name] = $f.val(); }
			});
			return data;
		}

		function rowHtml(unit) {
			var statusLabels = {
				available: 'Verfügbar',
				reserved:  'Reserviert',
				sold:      'Verkauft',
				rented:    'Vermietet'
			};
			var uStatus = statusLabels[unit.status] ? unit.status : 'available';
			var areaTxt  = Number(unit.area || 0).toLocaleString('de-AT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' m²';
			var priceTxt = Number(unit.price || 0).toLocaleString('de-AT') + ' ' + ajaxSettings.currency;
			var rentTxt  = unit.rent > 0 ? Number(unit.rent).toLocaleString('de-AT') + ' ' + ajaxSettings.currency : '—';
			return '<tr data-unit-id="' + unit.id + '">' +
				'<td>' + escapeHtml(unit.unit_number || '') + '</td>' +
				'<td>' + escapeHtml(String(unit.floor || 0)) + '</td>' +
				'<td>' + escapeHtml(areaTxt) + '</td>' +
				'<td>' + escapeHtml(String(unit.rooms || 0)) + '</td>' +
				'<td>' + escapeHtml(priceTxt) + '</td>' +
				'<td>' + escapeHtml(rentTxt) + '</td>' +
				'<td><span class="immo-unit-status-badge status-' + escapeHtml(uStatus) + '">' +
					escapeHtml(statusLabels[uStatus]) + '</span></td>' +
				'<td>' +
					'<button type="button" class="button button-small immo-edit-unit">Bearbeiten</button> ' +
					'<button type="button" class="button button-small button-link-delete immo-delete-unit">Löschen</button>' +
				'</td>' +
			'</tr>';
		}

		function refreshRow(unit) {
			var $existing = $list.find('tr[data-unit-id="' + unit.id + '"]');
			var $html     = $(rowHtml(unit)).data('unit', unit);
			if ($existing.length) {
				$existing.replaceWith($html);
			} else {
				$list.find('.immo-no-units').remove();
				$list.append($html);
			}
		}

		// Render existing rows into data cache.
		$list.find('tr[data-unit-id]').each(function () {
			// Nothing to do – data attributes + refresh-on-edit suffice.
		});

		// Check URL for unit_id to auto-open
		var urlParams = new URLSearchParams(window.location.search);
		var editUnitId = parseInt(urlParams.get('unit_id'), 10);
		if (editUnitId) {
			setTimeout(function () {
				var $targetRow = $list.find('tr[data-unit-id="' + editUnitId + '"]');
				if ($targetRow.length) {
					$targetRow.find('.immo-edit-unit').trigger('click');
				}
			}, 500);
		}

		// Events.
		$manager.on('click', '.immo-add-unit', function () {
			openEditor(null);
		});

		$manager.on('click', '.immo-edit-unit', function () {
			var $row = $(this).closest('tr');
			var unitId = parseInt($row.data('unitId'), 10) || 0;
			if (!unitId) { return; }

			// Find unit data from pre-loaded array
			var unit = allUnits.find(function (u) { return parseInt(u.id, 10) === unitId; });
			if (unit) {
				openEditor(unit);
			} else {
				// Fallback for dynamically added units (should not happen often)
				window.alert('Einheit nicht gefunden. Bitte Seite neu laden.');
			}
		});

		$manager.on('click', '.immo-delete-unit', function () {
			var $row = $(this).closest('tr');
			var unitId = parseInt($row.data('unitId'), 10) || 0;
			if (!unitId) { return; }
			if (!window.confirm(i18n.confirmDelete || 'Wohneinheit wirklich löschen?')) { return; }

			$.post(ajaxSettings.url, {
				action:  'immo_unit_delete',
				nonce:   ajaxSettings.nonce,
				unit_id: unitId
			}).done(function (response) {
				if (response && response.success) {
					$row.fadeOut(200, function () { $(this).remove(); });
				} else {
					window.alert((response && response.data && response.data.message) || 'Fehler beim Löschen.');
				}
			}).fail(function () {
				window.alert('Netzwerkfehler beim Löschen.');
			});
		});

		$manager.on('click', '.immo-cancel-unit', function () { closeEditor(); });

		$manager.on('click', '.immo-save-unit', function () {
			var $btn = $(this);
			var unitId = parseInt($editor.find('input[name="unit_id"]').val(), 10) || 0;
			var data = collectForm();

			var payload = {
				action:     'immo_unit_save',
				nonce:      ajaxSettings.nonce,
				project_id: ajaxSettings.projectId,
				unit_id:    unitId,
				unit:       data
			};

			$spinner.addClass('is-active');
			$msg.text('').removeClass('error');
			$btn.prop('disabled', true);

			$.post(ajaxSettings.url, payload).done(function (response) {
				if (response && response.success) {
					var unit = response.data && response.data.unit;
					if (unit) {
						// Update local cache
						allUnits = allUnits.filter(function(u) { return u.id !== unit.id; });
						allUnits.push(unit);
					}
					if (unit) { refreshRow(unit); }
					$msg.text((response.data && response.data.message) || 'Gespeichert.');
					setTimeout(closeEditor, 800);
				} else {
					$msg.text((response && response.data && response.data.message) || 'Fehler.').addClass('error');
				}
			}).fail(function () {
				$msg.text('Netzwerkfehler.').addClass('error');
			}).always(function () {
				$spinner.removeClass('is-active');
				$btn.prop('disabled', false);
			});
		});
	}

	/* -----------------------------------------------------------------
	 * Utility
	 * ----------------------------------------------------------------- */
	function escapeHtml(str) {
		return String(str).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
		});
	}

	/* -----------------------------------------------------------------
	 * Init
	 * ----------------------------------------------------------------- */
	$(function () {
		initRegionCascading();
		initUnitsManager();
	});
})(jQuery);
