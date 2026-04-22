/**
 * Immo Manager – Wizard JavaScript
 * Multi-Step Navigation, LocalStorage, Validation, Image Upload, Summary
 */
(function () {
	'use strict';

	var cfg    = window.immoManager || {};
	var API    = cfg.apiBase || '';

	/* ───────────────────────────────────────────────────────────
	 * WizardManager
	 * ─────────────────────────────────────────────────────────── */
	function WizardManager(el) {
		this.el          = el;
		this.ajaxUrl     = el.dataset.ajaxUrl || '';
		this.nonce       = el.dataset.nonce || '';
		this.restNonce   = el.dataset.restNonce || '';
		this.postId      = parseInt(el.dataset.postId, 10) || 0;
		this.redirect    = el.dataset.redirect || '';
		this.totalSteps  = parseInt(el.dataset.totalSteps, 10) || 6;
		this.currentStep = 1;
		this.draftId     = 0;
		this.draftTimer  = null;
		this.apiBase     = el.dataset.apiBase || '';
		this.storageKey  = 'immo_wizard_' + (this.postId || 'new');
		this.init();
	}

	WizardManager.prototype.init = function () {
		var self = this;

		// LocalStorage restore.
		this.restoreFromStorage();

		// Nav Buttons.
		var nextBtn = this.el.querySelector('.immo-wizard-next');
		if (nextBtn) nextBtn.addEventListener('click', function () { self.goNext(); });
		var prevBtn = this.el.querySelector('.immo-wizard-prev');
		if (prevBtn) prevBtn.addEventListener('click', function () { self.goPrev(); });
		var draftBtn = this.el.querySelector('.immo-wizard-draft');
		if (draftBtn) draftBtn.addEventListener('click', function () { self.saveDraft(); });
		var submitBtn = this.el.querySelector('.immo-wizard-submit');
		if (submitBtn) submitBtn.addEventListener('click', function () { self.submit(false); });

		// Entity Type selection (Property / Project / Unit)
		this.el.querySelectorAll('.immo-entity-option').forEach(function (tile) {
			tile.addEventListener('click', function () {
				var name = tile.querySelector('input').getAttribute('name');
				self.el.querySelectorAll('[name="' + name + '"]').forEach(function (i) {
					var p = i.closest('.immo-entity-option');
					if (p) p.classList.remove('selected');
				});
				tile.classList.add('selected');
				tile.querySelector('input').checked = true;
				self.updateEntitySections();
			});
		});

		// Type/Mode tiles – visual selection.
		this.el.querySelectorAll('.immo-type-tile, .immo-mode-option, .immo-status-option').forEach(function (tile) {
			tile.addEventListener('click', function () {
				var name = tile.querySelector('input').getAttribute('name');
				self.el.querySelectorAll('[name="' + name + '"]').forEach(function (i) {
					var p = i.closest('.immo-type-tile, .immo-mode-option, .immo-status-option');
					if (p) p.classList.remove('selected');
				});
				tile.classList.add('selected');
				tile.querySelector('input').checked = true;
				// Mode-change → price sections.
				if (name === '_immo_mode') { self.updatePriceSections(); }
			});
		});

		// Feature tiles – visual selection.
		this.el.querySelectorAll('.immo-wizard-feature-item').forEach(function (item) {
			item.querySelector('input').addEventListener('change', function () {
				item.classList.toggle('checked', this.checked);
			});
		});

		// Region cascading (uses REST API).
		var stateSelect    = this.el.querySelector('.immo-region-state');
		var districtSelect = this.el.querySelector('.immo-region-district');
		if (stateSelect && districtSelect) {
			stateSelect.addEventListener('change', function () {
				self.loadDistricts(stateSelect.value, districtSelect);
			});
		}

		// Make steps clickable
		this.el.querySelectorAll('.immo-wizard-step-indicator').forEach(function (ind) {
			ind.style.cursor = 'pointer';
			ind.addEventListener('click', function () {
				var targetStep = parseInt(ind.dataset.step, 10);
				self.saveToStorage();
				self.goToStep(targetStep);
			});
		});

		// Image upload.
		this.initImageUpload();
		this.initDocumentUpload();
		this.initVideoUpload();

		// Auto-save on input changes.
		var inputs = this.el.querySelectorAll('.immo-wizard-input');
		inputs.forEach(function (input) {
			input.addEventListener('change', function () { self.scheduleAutosave(); });
		});

		// TinyMCE auto-sync and autosave.
		try {
			var tmce = typeof tinymce !== 'undefined' ? tinymce : (typeof tinyMCE !== 'undefined' ? tinyMCE : null);
			if (tmce) {
				tmce.on('AddEditor', function(e) {
					e.editor.on('change keyup', function() {
						tmce.triggerSave();
						self.scheduleAutosave();
					});
				});
			}
		} catch(e) {}

		// Show first step.
		this.goToStep(1);
		this.updatePriceSections();
		this.updateEntitySections();
	};

	/* ── Navigation ── */
	WizardManager.prototype.goNext = function () {
		if (!this.validateStep(this.currentStep)) {
			var err = this.el.querySelector('.immo-field-error:not([hidden]), .immo-wizard-error-global:not([hidden])');
			if (err) { err.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
			return; 
		}
		if (this.currentStep < this.totalSteps) {
			this.saveToStorage();
			this.goToStep(this.currentStep + 1);
		}
	};

	WizardManager.prototype.goPrev = function () {
		if (this.currentStep > 1) { this.goToStep(this.currentStep - 1); }
	};

	WizardManager.prototype.goToStep = function (step) {
		var self = this;
		this.currentStep = step;

		// Show/hide steps.
		this.el.querySelectorAll('.immo-wizard-step').forEach(function (s) {
			var n = parseInt(s.dataset.step, 10);
			if (n === step) {
				s.hidden = false;
				s.style.display = 'block';
				s.classList.add('active');
			} else {
				s.hidden = true;
				s.style.display = 'none';
				s.classList.remove('active');
			}
		});

		// Progress indicators.
		this.el.querySelectorAll('.immo-wizard-step-indicator').forEach(function (ind) {
			var n = parseInt(ind.dataset.step, 10);
			ind.classList.remove('active', 'completed');
			if (n < step)  { ind.classList.add('completed'); }
			if (n === step){ ind.classList.add('active'); }
		});
		this.el.querySelectorAll('.immo-wizard-step-line').forEach(function (line, idx) {
			line.classList.toggle('completed', idx + 1 < step);
		});

		// Progress-bar aria.
		var prog = this.el.querySelector('.immo-wizard-progress');
		if (prog) prog.setAttribute('aria-valuenow', String(step));

		// Nav buttons.
		var btnPrev = this.el.querySelector('.immo-wizard-prev');
		if (btnPrev) { btnPrev.hidden = step === 1; btnPrev.style.display = (step === 1) ? 'none' : ''; }
		var btnNext = this.el.querySelector('.immo-wizard-next');
		if (btnNext) { btnNext.hidden = step === this.totalSteps; btnNext.style.display = (step === this.totalSteps) ? 'none' : ''; }
		var btnSub = this.el.querySelector('.immo-wizard-submit');
		if (btnSub) { 
			if (this.postId > 0) {
				btnSub.hidden = false; 
				btnSub.style.display = '';
			} else {
				btnSub.hidden = step !== this.totalSteps; 
				btnSub.style.display = (step !== this.totalSteps) ? 'none' : ''; 
			}
		}

		// Last step → build summary.
		if (step === this.totalSteps) { this.buildSummary(); }

		// Scroll top.
		this.el.scrollIntoView({ behavior: 'smooth', block: 'start' });
	};

	/* ── Validation ── */
	WizardManager.prototype.validateStep = function (step) {
		this.clearErrors();
		var valid = true;
		var entity = this.getVal('entity_type') || 'property';

		if (step === 1) {
			if (entity === 'property' && !this.getVal('_immo_property_type')) {
				this.showError('_immo_property_type', 'Bitte wähle einen Immobilientyp für die Immobilie.');
				valid = false;
			}
			if (entity === 'property' && !this.getVal('_immo_mode')) {
				this.showError('_immo_mode', 'Bitte wähle einen Angebotsmodus.');
				valid = false;
			}
		}
		if (step === 2) {
			if (!this.getVal('_immo_city')) {
				this.showError('_immo_city', 'Bitte gib den Ort an.');
				valid = false;
			}
		}
		if (step === 4) {
			if (entity !== 'project') {
				var mode = this.getVal('_immo_mode');
				// Unit prices are always required optionally, but strictly checked for properties
				if (entity === 'property') {
					if ((mode === 'sale' || mode === 'both') && !this.getVal('_immo_price')) { this.showError('_immo_price', 'Kaufpreis erforderlich.'); valid = false; }
					if ((mode === 'rent' || mode === 'both') && !this.getVal('_immo_rent')) { this.showError('_immo_rent', 'Mietpreis erforderlich.'); valid = false; }
				}
			}
		}
		return valid;
	};

	WizardManager.prototype.clearErrors = function () {
		this.el.querySelectorAll('.immo-field-error').forEach(function (el) { 
			el.textContent = ''; 
			el.hidden = true; 
			el.style.display = 'none';
		});
		var globalErr = this.el.querySelector('.immo-wizard-error-global');
		if (globalErr) { globalErr.hidden = true; globalErr.style.display = 'none'; }
	};

	WizardManager.prototype.showError = function (field, msg) {
		var errEl = this.el.querySelector('.immo-field-error[data-field="' + field + '"]');
		if (errEl) { 
			errEl.textContent = msg; 
			errEl.hidden = false; 
			errEl.style.display = 'block'; 
		} else {
			var globalErr = this.el.querySelector('.immo-wizard-error-global');
			if (globalErr) {
				globalErr.textContent = msg; 
				globalErr.hidden = false; 
				globalErr.style.display = 'block';
			}
		}
	};

	/* ── Submit & Draft ── */
	WizardManager.prototype.submit = function (asDraft) {
		var self = this;
		if (!asDraft) {
			if (!this.validateStep(1)) { this.goToStep(1); this.validateStep(1); return; }
			if (!this.validateStep(2)) { this.goToStep(2); this.validateStep(2); return; }
			if (!this.validateStep(4)) { this.goToStep(4); this.validateStep(4); return; }
			if (!this.validateStep(this.currentStep)) { return; }
		}

		var btn = asDraft ? this.el.querySelector('.immo-wizard-draft') : this.el.querySelector('.immo-wizard-submit');
		btn.disabled = true;
		var spinner = this.el.querySelector('.immo-wizard-spinner');
		if (spinner && !asDraft) { spinner.hidden = false; }

		var formData = this.collectFormData();
		formData.set('action', asDraft ? 'immo_wizard_autosave' : 'immo_wizard_save');
		formData.set('nonce',  this.nonce);
		formData.set('post_id', String(this.draftId || this.postId));

		fetch(this.ajaxUrl, { method: 'POST', body: formData })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.success) {
					if (asDraft) {
						self.draftId = data.data.post_id || 0;
						self.setAutosaveStatus('💾 ' + (data.data.message || 'Gespeichert.'));
					} else {
						self.showSuccess(data.data.message, data.data.permalink, data.data.edit_url);
						localStorage.removeItem(self.storageKey);
					}
				} else {
					var msg = (data.data && data.data.message) ? data.data.message : 'Fehler.';
					var globalErr = self.el.querySelector('.immo-wizard-error-global');
					if (globalErr) { globalErr.textContent = msg; globalErr.hidden = false; }
				}
			})
			.catch(function () {
				var globalErr = self.el.querySelector('.immo-wizard-error-global');
				if (globalErr) {
					globalErr.textContent = 'Netzwerkfehler.'; globalErr.hidden = false;
				}
			})
			.finally(function () {
				btn.disabled = false;
				if (spinner && !asDraft) { spinner.hidden = true; }
			});
	};

	WizardManager.prototype.saveDraft = function () { this.submit(true); };

	WizardManager.prototype.scheduleAutosave = function () {
		var self = this;
		clearTimeout(this.draftTimer);
		this.draftTimer = setTimeout(function () {
			if (self.currentStep >= 3) { self.saveDraft(); }
		}, 3000);
	};

	WizardManager.prototype.setAutosaveStatus = function (msg) {
		var el = this.el.querySelector('.immo-wizard-autosave-status');
		if (el) {
			el.textContent = msg;
			setTimeout(function () { el.textContent = ''; }, 4000);
		}
	};

	/* ── Form Data ── */
	WizardManager.prototype.collectFormData = function () {
		try { if (typeof tinymce !== 'undefined') { tinymce.triggerSave(); } } catch(e) {}
		try { if (typeof tinyMCE !== 'undefined') { tinyMCE.triggerSave(); } } catch(e) {}
		var fd = new FormData();
		var inputs = this.el.querySelectorAll('.immo-wizard-input, [name="gallery_ids"], [name="description"], [name="title"]');
		inputs.forEach(function (input) {
			if (!input.name) { return; }
			if ((input.type === 'checkbox' || input.type === 'radio') && !input.checked) { return; }
			if (input.type === 'file') { return; }
			fd.append(input.name, input.value);
		});
		var galleryInput = this.el.querySelector('#immo-gallery-ids');
		if (galleryInput) { fd.set('gallery_ids', galleryInput.value); }
		var docInput = this.el.querySelector('#immo-document-ids');
		if (docInput) { fd.set('document_ids', docInput.value); }

		// Den Entity-Type explizit an die Payload anfügen
		var entityType = this.getVal('entity_type');
		if (entityType) { fd.set('entity_type', entityType); }

		return fd;
	};

	WizardManager.prototype.getVal = function (name) {
		var input = this.el.querySelector('[name="' + name + '"]:not([type="checkbox"]):not([type="radio"])');
		if (input) { return input.value.trim(); }
		var checked = this.el.querySelector('[name="' + name + '"]:checked');
		return checked ? checked.value.trim() : '';
	};

	/* ── LocalStorage ── */
	WizardManager.prototype.saveToStorage = function () {
		try { if (typeof tinymce !== 'undefined') { tinymce.triggerSave(); } } catch(e) {}
		try { if (typeof tinyMCE !== 'undefined') { tinyMCE.triggerSave(); } } catch(e) {}
		var data = {};
		this.el.querySelectorAll('.immo-wizard-input, [name="title"], [name="description"]').forEach(function (input) {
			if (!input.name) { return; }
			if (input.type === 'file') { return; }
			if (input.type === 'checkbox') { data[input.name + ':' + input.value] = input.checked; return; }
			if (input.type === 'radio') { if (input.checked) { data[input.name] = input.value; } return; }
			data[input.name] = input.value;
		});
		try { localStorage.setItem(this.storageKey, JSON.stringify(data)); } catch (e) {}
	};

	WizardManager.prototype.restoreFromStorage = function () {
		var self = this;
		var raw;
		try { raw = localStorage.getItem(this.storageKey); } catch (e) { return; }
		if (!raw) { return; }
		var data = JSON.parse(raw) || {};
		Object.keys(data).forEach(function (key) {
			if (key.includes(':')) {
				// Checkbox.
				var parts = key.split(':'); var name = parts[0]; var val = parts[1];
				try {
					var cb = self.el.querySelector('[name="' + name + '"][value="' + val.replace(/"/g, '\\"') + '"]');
					if (cb) { cb.checked = !!data[key]; var p = cb.closest('.immo-wizard-feature-item'); if (p) p.classList.toggle('checked', !!data[key]); }
				} catch(e) {}
			} else {
				var input = self.el.querySelector('[name="' + key + '"]:not([type="checkbox"]):not([type="radio"])');
				if (input) { 
					input.value = data[key]; 
				} else {
					try {
						var radio = self.el.querySelector('[name="' + key + '"][value="' + String(data[key]).replace(/"/g, '\\"') + '"]');
						if (radio) { radio.checked = true; var pr = radio.closest('.immo-type-tile, .immo-mode-option, .immo-status-option, .immo-entity-option'); if (pr) pr.classList.add('selected'); }
					} catch(e) {}
				}
			}
		});

		if (data['description'] && typeof tinyMCE !== 'undefined') {
			setTimeout(function() {
				var ed = tinyMCE.get('immodescription');
				if (ed) { ed.setContent(data['description']); }
			}, 500);
		}
	};

	/* ── Summary ── */
	WizardManager.prototype.buildSummary = function () {
		var self    = this;
		var summary = this.el.querySelector('#immo-wizard-summary');
		if (!summary) { return; }
	
		var getStateLabel = function() { var o = self.el.querySelector('.immo-region-state option:checked'); return o ? o.text : ''; };
		var getStatusLabel = function() { 
			var isProj = (self.getVal('entity_type') === 'project');
			var c = self.el.querySelector('[name="' + (isProj ? '_immo_project_status' : '_immo_status') + '"]:checked'); 
			var l = c ? c.closest('label') : null; 
			return l && l.textContent ? l.textContent.trim() : ''; 
		};
		var getModeLabel = function() { var c = self.el.querySelector('[name="_immo_mode"]:checked'); var l = c ? c.closest('label') : null; return l && l.querySelector('.immo-mode-label') ? l.querySelector('.immo-mode-label').textContent.trim() : ''; };
		var getTypeLabel = function() { var c = self.el.querySelector('[name="_immo_property_type"]:checked'); var l = c ? c.closest('label') : null; return l && l.querySelector('.immo-type-label') ? l.querySelector('.immo-type-label').textContent.trim() : ''; };
	
		var groups = {
			general: {
				icon: '📝',
				title: 'Basisdaten',
				rows: [
					{ label: 'Titel', val: this.getVal('title') },
					{ label: 'Immobilientyp', val: getTypeLabel() },
					{ label: 'Angebotsmodus', val: getModeLabel() },
					{ label: 'Status', val: getStatusLabel() },
				]
			},
			location: {
				icon: '📍',
				title: 'Standort',
				rows: [
					{ label: 'Adresse', val: [this.getVal('_immo_address'), this.getVal('_immo_postal_code'), this.getVal('_immo_city')].filter(Boolean).join(', ') },
					{ label: 'Region', val: [getStateLabel(), this.getVal('_immo_region_district')].filter(Boolean).join(', ') },
				]
			},
			details: {
				icon: '📏',
				title: 'Details',
				rows: [
					{ label: 'Gesamtfläche', val: this.getVal('_immo_area') ? this.getVal('_immo_area') + ' m²' : '' },
					{ label: 'Zimmer', val: this.getVal('_immo_rooms') },
					{ label: 'Baujahr', val: this.getVal('_immo_built_year') },
					{ label: 'Energieklasse', val: this.getVal('_immo_energy_class') },
				]
			},
			price: {
				icon: '💶',
				title: 'Preis',
				rows: [
					{ label: 'Kaufpreis', val: this.getVal('_immo_price') ? this.getVal('_immo_price') + ' €' : '' },
					{ label: 'Mietpreis', val: this.getVal('_immo_rent') ? this.getVal('_immo_rent') + ' € / Monat' : '' },
					{ label: 'Verfügbar ab', val: this.getVal('_immo_available_from') },
				]
			},
			features: {
				icon: '✨',
				title: 'Ausstattung',
				full: true,
				rows: []
			}
		};
	
		var checked = this.el.querySelectorAll('[name="_immo_features[]"]:checked');
		if (checked.length) {
			var labels = Array.from(checked).map(function (cb) {
				var p = cb.closest('.immo-wizard-feature-item');
				var l = p ? p.querySelector('.immo-feature-label') : null;
				return (l && l.textContent) ? l.textContent.trim() : cb.value;
			}).join(', ');
			groups.features.rows.push({ label: 'Merkmale', val: labels });
		}
		var customFeatures = this.getVal('_immo_custom_features');
		if (customFeatures) {
			groups.features.rows.push({ label: 'Weitere', val: customFeatures });
		}
	
		var html = '<div class="immo-summary-grid">';
		var hasContent = false;
	
		Object.keys(groups).forEach(function(key) {
			var group = groups[key];
			var groupRows = group.rows.filter(function (r) { return r.val; });
			if (groupRows.length > 0) {
				hasContent = true;
				html += '<div class="immo-summary-group ' + (group.full ? 'immo-summary-group--full' : '') + '">';
				html += '<h4>' + (group.icon ? '<span style="margin-right: 8px;">' + group.icon + '</span>' : '') + escHtml(group.title) + '</h4>';
				groupRows.forEach(function (r) {
					html += '<div class="immo-summary-row"><span class="immo-summary-label">' + escHtml(r.label) + '</span><span class="immo-summary-value">' + escHtml(r.val) + '</span></div>';
				});
				html += '</div>';
			}
		});
	
		html += '</div>';
		summary.innerHTML = hasContent ? html : '<p class="immo-summary-hint">Noch keine Daten eingetragen.</p>';
	};

	/* ── UI helpers ── */
	WizardManager.prototype.updatePriceSections = function () {
		var entity  = this.getVal('entity_type') || 'property';
		var mode    = this.getVal('_immo_mode');
		var saleEl  = this.el.querySelector('.immo-price-section-sale');
		var rentEl  = this.el.querySelector('.immo-price-section-rent');
		if (!saleEl || !rentEl) { return; }

		if (entity === 'project') {
			saleEl.style.display = 'none';
			rentEl.style.display = 'none';
			return;
		}

		// For properties, show/hide based on mode
		saleEl.style.display = (mode === 'rent') ? 'none' : '';
		rentEl.style.display = (mode === 'sale') ? 'none' : '';
	};

	WizardManager.prototype.updateEntitySections = function () {
		var entity = this.getVal('entity_type') || 'property';
		var isProject = (entity === 'project');
		var self = this;

		// Helper to toggle visibility of a field's wrapper
		var toggleField = function (name, show) {
			var inputs = self.el.querySelectorAll('[name="' + name + '"]');
			inputs.forEach(function (inp) {
				var wrapper = inp.closest('.immo-field') || inp.closest('.immo-wizard-section');
				if (wrapper) {
					wrapper.style.display = show ? '' : 'none';
				}
			});
		};

		// Step 1: Type & Mode sections
		toggleField('_immo_property_type', !isProject);

		// Hide complete property-only sections (including labels and titles)
		self.el.querySelectorAll('.immo-property-only').forEach(function(el) {
			el.style.display = isProject ? 'none' : '';
		});

		// Project fields
		toggleField('_immo_project_status', isProject);
		toggleField('_immo_project_start_date', isProject);
		toggleField('_immo_project_completion', isProject);

		// Unit manager sections
		var unitPlaceholder = self.el.querySelector('.immo-units-integration-placeholder');
		if (unitPlaceholder) { unitPlaceholder.style.display = (isProject && self.postId === 0) ? '' : 'none'; }

		// Also update the price sections which have their own logic
		this.updatePriceSections();
	};

	WizardManager.prototype.showSuccess = function (msg, link, edit_link) {
		var toast = document.createElement('div');
		toast.className = 'immo-toast-success';
		toast.innerHTML = '🎉 ' + (msg || 'Erfolgreich gespeichert!');
		document.body.appendChild(toast);
		setTimeout(function() { if(toast) toast.remove(); }, 3500);

		var entity = this.getVal('entity_type') || 'property';
		if (entity === 'project' && !this.postId && edit_link) {
			setTimeout(function () { window.location.href = edit_link; }, 1500);
			return;
		}

		if (this.redirect && link && !this.postId) { 
			setTimeout(function () { window.location.href = link; }, 1500); 
		}
	};

	WizardManager.prototype.loadDistricts = function (state, select) {
		if (!state) { select.innerHTML = '<option value="">— Bezirk —</option>'; select.disabled = true; return; }
		select.disabled = true;
		fetch(API + '/regions/' + encodeURIComponent(state) + '/districts')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				var districts = data.districts || {};
				var html = '<option value="">— Bezirk —</option>';
				Object.keys(districts).forEach(function (k) {
					html += '<option value="' + escHtml(k) + '">' + escHtml(districts[k]) + '</option>';
				});
				select.innerHTML = html;
				select.disabled = false;
			})
			.catch(function () { select.disabled = false; });
	};

	/* ── Image Upload ── */
	WizardManager.prototype.initImageUpload = function () {
		var self      = this;
		var dropZone  = this.el.querySelector('#immo-drop-zone');
		var fileInput = this.el.querySelector('#immo-file-input');
		var preview   = this.el.querySelector('#immo-upload-preview');
		var idsInput  = this.el.querySelector('#immo-gallery-ids');

		if (!dropZone || !fileInput || !preview || !idsInput) { return; }

		var ids = idsInput.value ? idsInput.value.split(',').filter(Boolean) : [];

		// Vorhandene Bilder anzeigen (bei Bearbeitung).
		if (ids.length) {
			ids.forEach(function (id) {
				self.addExistingPreview(parseInt(id, 10), preview, ids, idsInput);
			});
		}

		dropZone.addEventListener('click', function () { fileInput.click(); });
		var dBtn = dropZone.querySelector('.immo-upload-btn');
		if (dBtn) dBtn.addEventListener('click', function (e) { e.stopPropagation(); fileInput.click(); });
		fileInput.addEventListener('change', function () { self.uploadFiles(fileInput.files, preview, ids, idsInput); });

		dropZone.addEventListener('dragover', function (e) { e.preventDefault(); dropZone.classList.add('drag-over'); });
		dropZone.addEventListener('dragleave', function () { dropZone.classList.remove('drag-over'); });
		dropZone.addEventListener('drop', function (e) {
			e.preventDefault(); dropZone.classList.remove('drag-over');
			self.uploadFiles(e.dataTransfer.files, preview, ids, idsInput);
		});

		// WP Media Library Integration for Images
		var mediaBtn = dropZone.querySelector('.immo-media-btn');
		if (mediaBtn) {
			var mediaFrame;
			mediaBtn.addEventListener('click', function(e) {
				e.stopPropagation();
				if (mediaFrame) { mediaFrame.open(); return; }
				mediaFrame = wp.media({
					title: 'Bilder aus Mediathek wählen',
					button: { text: 'In Galerie einfügen' },
					multiple: true,
					library: { type: 'image' }
				});
				mediaFrame.on('select', function() {
					var selection = mediaFrame.state().get('selection');
					selection.map(function(attachment) {
						attachment = attachment.toJSON();
						var id = attachment.id;
						if (ids.indexOf(String(id)) === -1) {
							ids.push(String(id));
							idsInput.value = ids.join(',');
							var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
							
							var item = document.createElement('div');
							item.className = 'immo-preview-item';
							item.dataset.id = id;
							item.draggable = true;
							item.innerHTML = '<img src="' + url + '" alt=""><button type="button" class="immo-preview-remove" data-id="' + id + '">✕</button>';
							item.querySelector('.immo-preview-remove').addEventListener('click', function () {
								ids.splice(ids.indexOf(String(id)), 1);
								idsInput.value = ids.join(',');
								item.remove();
								self.saveToStorage();
							});
							preview.appendChild(item);
						}
					});
					self.saveToStorage();
				});
				mediaFrame.open();
			});
		}

		// Initialize sortable for existing items
		self.initSortable(preview, ids, idsInput);
	};

	WizardManager.prototype.initSortable = function (container, ids, idsInput) {
		var self = this;
		var dragEl = null;

		if (container.dataset.sortableInitialized) { return; }
		container.dataset.sortableInitialized = 'true';

		container.addEventListener('dragstart', function(e) {
			dragEl = e.target.closest('.immo-preview-item');
			if (!dragEl) return;
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', dragEl.dataset.id || '');
			setTimeout(function() { dragEl.style.opacity = '0.5'; }, 0);
		});

		container.addEventListener('dragover', function(e) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
			var target = e.target.closest('.immo-preview-item');
			if (target && target !== dragEl) {
				var rect = target.getBoundingClientRect();
				var next = (e.clientX - rect.left) / rect.width > 0.5;
				container.insertBefore(dragEl, next ? target.nextSibling : target);
			}
		});

		container.addEventListener('dragend', function(e) {
			if (dragEl) dragEl.style.opacity = '1';
			dragEl = null;
			
			// Update IDs
			ids.length = 0;
			container.querySelectorAll('.immo-preview-item').forEach(function(item) {
				if (item.dataset.id) { ids.push(item.dataset.id); }
			});
			idsInput.value = ids.join(',');
			self.saveToStorage();
		});
	};

	WizardManager.prototype.uploadFiles = function (files, preview, ids, idsInput) {
		var self = this;
		var fileArray = Array.from(files).filter(function(file) { return file.type.startsWith('image/'); });

		function processNext() {
			if (fileArray.length === 0) return;
			var file = fileArray.shift();

			var placeholder = document.createElement('div');
			placeholder.className = 'immo-preview-item immo-preview-uploading';
			placeholder.innerHTML = '<div class="immo-spinner"></div>';
			preview.appendChild(placeholder);

			var fd = new FormData();
			fd.append('action', 'upload-attachment');
			fd.append('_wpnonce', self.el.dataset.mediaNonce || self.el.dataset.nonce);
			if (self.postId > 0) { fd.append('post_id', self.postId); }
			fd.append('async-upload', file, file.name);

			fetch(self.ajaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (data.success && data.data && data.data.id) {
						var id = data.data.id;
						ids.push(String(id));
						idsInput.value = ids.join(',');
						
						placeholder.classList.remove('immo-preview-uploading');
						placeholder.dataset.id = id;
						placeholder.draggable = true;
						placeholder.innerHTML = '<img src="' + (data.data.url || '') + '" alt=""><button type="button" class="immo-preview-remove" data-id="' + id + '">✕</button>';
						placeholder.querySelector('.immo-preview-remove').addEventListener('click', function () {
							ids.splice(ids.indexOf(String(id)), 1);
							idsInput.value = ids.join(',');
							placeholder.remove();
							self.saveToStorage();
						});
						self.saveToStorage();
					} else {
						placeholder.remove();
					}
				})
				.catch(function () { placeholder.remove(); })
				.finally(processNext);
		}
		processNext();
	};

	WizardManager.prototype.addExistingPreview = function (id, preview, ids, idsInput) {
		var self = this;
		if (!id) { return; }
		var item = document.createElement('div');
		item.className = 'immo-preview-item';
		item.innerHTML = '<div class="immo-spinner"></div>';
		preview.appendChild(item);
		fetch(self.apiBase.replace(/immo-manager\/v1\/?/, 'wp/v2/media/') + id + '?_fields=id,source_url', {
			headers: { 'X-WP-Nonce': self.restNonce }
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.source_url) {
					item.dataset.id = id;
					item.draggable = true;
					item.innerHTML = '<img src="' + data.source_url + '" alt=""><button type="button" class="immo-preview-remove" data-id="' + id + '">✕</button>';
					item.querySelector('.immo-preview-remove').addEventListener('click', function () {
						var idx = ids.indexOf(String(id));
						if (idx > -1) { ids.splice(idx, 1); }
						idsInput.value = ids.join(',');
						item.remove();
					});
				} else {
					item.remove();
				}
			})
			.catch(function () { item.remove(); });
	};

	/* ── Document Upload ── */
	WizardManager.prototype.initDocumentUpload = function () {
		var self      = this;
		var dropZone  = this.el.querySelector('#immo-doc-drop-zone');
		var fileInput = this.el.querySelector('#immo-doc-file-input');
		var preview   = this.el.querySelector('#immo-doc-preview');
		var idsInput  = this.el.querySelector('#immo-document-ids');

		if (!dropZone || !fileInput || !preview || !idsInput) { return; }

		var ids = idsInput.value ? idsInput.value.split(',').filter(Boolean) : [];

		if (ids.length) {
			ids.forEach(function (id) {
				self.addExistingDocPreview(parseInt(id, 10), preview, ids, idsInput);
			});
		}

		dropZone.addEventListener('click', function () { fileInput.click(); });
		var dBtn = dropZone.querySelector('.immo-doc-upload-btn');
		if (dBtn) dBtn.addEventListener('click', function (e) { e.stopPropagation(); fileInput.click(); });
		fileInput.addEventListener('change', function () { self.uploadDocs(fileInput.files, preview, ids, idsInput); });

		// WP Media Library Integration for Documents
		var docMediaBtn = dropZone.querySelector('.immo-doc-media-btn');
		if (docMediaBtn) {
			var docMediaFrame;
			docMediaBtn.addEventListener('click', function(e) {
				e.stopPropagation();
				if (docMediaFrame) { docMediaFrame.open(); return; }
				docMediaFrame = wp.media({
					title: 'Dokumente aus Mediathek wählen',
					button: { text: 'Einfügen' },
					multiple: true,
					library: { type: 'application' }
				});
				docMediaFrame.on('select', function() {
					var selection = docMediaFrame.state().get('selection');
					selection.map(function(attachment) {
						attachment = attachment.toJSON();
						var id = attachment.id;
						if (ids.indexOf(String(id)) === -1) {
							ids.push(String(id));
							idsInput.value = ids.join(',');
							var name = attachment.filename || attachment.title || 'Dokument';
							
							var item = document.createElement('div');
							item.className = 'immo-preview-item';
							item.innerHTML = '<div style="font-size:30px;text-align:center;padding-top:20px;">📄</div><div style="font-size:11px;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:4px;" title="' + name + '">' + name + '</div><button type="button" class="immo-preview-remove" data-id="' + id + '">✕</button>';
							item.querySelector('.immo-preview-remove').addEventListener('click', function () {
								var idx = ids.indexOf(String(id));
								if (idx > -1) ids.splice(idx, 1);
								idsInput.value = ids.join(',');
								item.remove();
								self.saveToStorage();
							});
							preview.appendChild(item);
						}
					});
					self.saveToStorage();
				});
				docMediaFrame.open();
			});
		}
	};

	/* ── Video Upload ── */
	WizardManager.prototype.initVideoUpload = function () {
		var self      = this;
		var dropZone  = this.el.querySelector('#immo-video-drop-zone');
		var preview   = this.el.querySelector('#immo-video-preview');
		var idsInput  = this.el.querySelector('#immo-video-id');

		if (!dropZone || !preview || !idsInput) { return; }

		var existingId = idsInput.value ? parseInt(idsInput.value, 10) : 0;
		if (existingId) {
			self.addExistingVideoPreview(existingId, preview, idsInput);
		}

		var mediaBtn = dropZone.querySelector('.immo-video-media-btn');
		if (mediaBtn) {
			var mediaFrame;
			mediaBtn.addEventListener('click', function(e) {
				e.stopPropagation();
				if (mediaFrame) { mediaFrame.open(); return; }
				mediaFrame = wp.media({
					title: 'Video aus Mediathek wählen',
					button: { text: 'Verwenden' },
					multiple: false,
					library: { type: 'video' }
				});
				mediaFrame.on('select', function() {
					var attachment = mediaFrame.state().get('selection').first().toJSON();
					idsInput.value = attachment.id;
					var name = attachment.filename || attachment.title || 'Video';
					preview.innerHTML = '';
					var item = document.createElement('div');
					item.className = 'immo-preview-item';
					item.innerHTML = '<div style="font-size:30px;text-align:center;padding-top:20px;">🎥</div><div style="font-size:11px;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:4px;" title="' + name + '">' + name + '</div><button type="button" class="immo-preview-remove">✕</button>';
					item.querySelector('.immo-preview-remove').addEventListener('click', function () {
						idsInput.value = '';
						item.remove();
						self.saveToStorage();
					});
					preview.appendChild(item);
					self.saveToStorage();
				});
				mediaFrame.open();
			});
		}
	};

	WizardManager.prototype.addExistingVideoPreview = function (id, preview, idsInput) {
		var self = this;
		if (!id) { return; }
		var item = document.createElement('div');
		item.className = 'immo-preview-item';
		item.innerHTML = '<div class="immo-spinner"></div>';
		preview.appendChild(item);
		fetch(self.apiBase.replace(/immo-manager\/v1\/?/, 'wp/v2/media/') + id + '?_fields=id,source_url,title', {
			headers: { 'X-WP-Nonce': self.restNonce }
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.source_url) {
					item.dataset.id = id;
					var name = data.title && data.title.rendered ? data.title.rendered : 'Video';
					item.innerHTML = '<div style="font-size:30px;text-align:center;padding-top:20px;">🎥</div><div style="font-size:11px;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:4px;" title="' + name + '">' + name + '</div><button type="button" class="immo-preview-remove">✕</button>';
					item.querySelector('.immo-preview-remove').addEventListener('click', function () {
						idsInput.value = '';
						item.remove();
					});
				} else {
					item.remove();
				}
			})
			.catch(function () { item.remove(); });
	};

	WizardManager.prototype.uploadDocs = function (files, preview, ids, idsInput) {
		var self = this;
		var fileArray = Array.from(files);

		function processNext() {
			if (fileArray.length === 0) return;
			var file = fileArray.shift();

			var placeholder = document.createElement('div');
			placeholder.className = 'immo-preview-item immo-preview-uploading';
			placeholder.innerHTML = '<div class="immo-spinner"></div>';
			preview.appendChild(placeholder);

			var fd = new FormData();
			fd.append('action', 'upload-attachment');
			fd.append('_wpnonce', self.el.dataset.mediaNonce || self.el.dataset.nonce);
			if (self.postId > 0) { fd.append('post_id', self.postId); }
			fd.append('async-upload', file, file.name);

			fetch(self.ajaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (data.success && data.data && data.data.id) {
						var id = data.data.id;
						ids.push(String(id));
						idsInput.value = ids.join(',');
						placeholder.innerHTML = '<div style="font-size:30px;text-align:center;padding-top:20px;">📄</div><div style="font-size:11px;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:4px;" title="'+file.name+'">' + file.name + '</div><button type="button" class="immo-preview-remove" data-id="' + id + '">✕</button>';
						placeholder.querySelector('.immo-preview-remove').addEventListener('click', function () { ids.splice(ids.indexOf(String(id)), 1); idsInput.value = ids.join(','); placeholder.remove(); });
						placeholder.classList.remove('immo-preview-uploading');
					} else { placeholder.remove(); }
				})
				.catch(function () { placeholder.remove(); })
				.finally(processNext);
		}
		processNext();
	};

	WizardManager.prototype.addExistingDocPreview = function (id, preview, ids, idsInput) {
		var self = this;
		if (!id) { return; }
		var item = document.createElement('div');
		item.className = 'immo-preview-item';
		preview.appendChild(item);
		fetch(self.apiBase.replace('/immo-manager/v1', '') + '/wp/v2/media/' + id + '?_fields=id,source_url,title')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.source_url) {
					var name = data.title && data.title.rendered ? data.title.rendered : 'Dokument';
					item.innerHTML = '<div style="font-size:30px;text-align:center;padding-top:20px;">📄</div><div style="font-size:11px;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:4px;" title="'+name+'">' + name + '</div><button type="button" class="immo-preview-remove" data-id="' + id + '">✕</button>';
					item.querySelector('.immo-preview-remove').addEventListener('click', function () { var idx = ids.indexOf(String(id)); if (idx > -1) { ids.splice(idx, 1); } idsInput.value = ids.join(','); item.remove(); });
				} else { item.remove(); }
			}).catch(function () { item.remove(); });
	};

	/* ── Utility ── */
	function escHtml(str) {
		return String(str || '').replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
		});
	}

	/* ── Init ── */
	document.addEventListener('DOMContentLoaded', function () {
		initWizards();
	});

	function initWizards() {
		document.querySelectorAll('.immo-wizard').forEach(function (el) {
			if (el.dataset.wizardInit) return; // Prevent double init
			el.dataset.wizardInit = '1';
			new WizardManager(el);
		});
	}
	if (document.readyState !== 'loading') { initWizards(); }

})();
