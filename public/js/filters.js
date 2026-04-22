/**
 * Immo Manager – AJAX Filter & Inquiry System
 *
 * Konsumiert die REST API /wp-json/immo-manager/v1/
 * → Läuft identisch auf externer Webseite (API-URL anpassen)
 */

(function () {
	'use strict';

	var cfg = window.immoManager || {};
	var API  = cfg.apiBase || '';
	var i18n = cfg.i18n   || {};

	/* ─────────────────────────────────────────────────────────
	 * ImmoListManager – Filter + Pagination
	 * ───────────────────────────────────────────────────────── */
	function ImmoListManager(container) {
		this.container  = container;
		this.sidebar    = container.querySelector('#immo-filter-sidebar');
		this.backdrop   = container.querySelector('#immo-filter-backdrop');
		this.grid       = container.querySelector('#immo-properties-grid');
		this.pagination = container.querySelector('#immo-pagination');
		this.loader     = container.querySelector('#immo-loader');
		this.sortSelect = container.querySelector('#immo-sort');
		this.countEl    = container.querySelector('.immo-result-count');
		this.tagsWrap   = container.querySelector('.immo-active-filters');
		this.tagsEl     = container.querySelector('.immo-filter-tags');
		this.badgeEl    = container.querySelector('.immo-filter-badge');
		this.currentPage = 1;
		this.currentLayout = container.querySelector('.immo-properties-grid').className.match(/layout-(\w+)/)?.[1] || 'grid';
		this.debounceTimer = null;
		this.init();
	}

	ImmoListManager.prototype.init = function () {
		var self = this;

		// Filter-Toggle.
		var toggleBtn = this.container.querySelector('.immo-filter-toggle');
		if (toggleBtn) {
			toggleBtn.addEventListener('click', function () { self.toggleSidebar(); });
		}

		// Sidebar schließen.
		var closeBtn = this.container.querySelector('.immo-filter-close');
		if (closeBtn) { closeBtn.addEventListener('click', function () { self.closeSidebar(); }); }
		if (this.backdrop) { this.backdrop.addEventListener('click', function () { self.closeSidebar(); }); }

		// Filter-Inputs.
		var inputs = this.container.querySelectorAll('.immo-filter-input');
		inputs.forEach(function (input) {
			var evt = (input.type === 'number') ? 'input' : 'change';
			input.addEventListener(evt, function () {
				if (input.type === 'number') {
					clearTimeout(self.debounceTimer);
					self.debounceTimer = setTimeout(function () { self.onFilterChange(); }, 600);
				} else {
					self.onFilterChange();
				}
			});
		});

		// Region cascading.
		var stateSelect = this.container.querySelector('.immo-region-state');
		var districtSelect = this.container.querySelector('.immo-region-district');
		if (stateSelect && districtSelect) {
			stateSelect.addEventListener('change', function () {
				self.loadDistricts(stateSelect.value, districtSelect);
			});
		}

		// Sortierung.
		if (this.sortSelect) {
			this.sortSelect.addEventListener('change', function () { self.currentPage = 1; self.fetchProperties(); });
		}

		// Layout-Toggle.
		var layoutBtns = this.container.querySelectorAll('.immo-layout-btn');
		layoutBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				layoutBtns.forEach(function (b) { b.classList.remove('active'); });
				btn.classList.add('active');
				self.currentLayout = btn.dataset.layout;
				self.grid.className = self.grid.className.replace(/layout-\w+/, 'layout-' + self.currentLayout);
			});
		});

		// Reset.
		var resetBtns = this.container.querySelectorAll('.immo-filter-reset, .immo-filter-reset-all');
		resetBtns.forEach(function (btn) {
			btn.addEventListener('click', function () { self.resetFilters(); });
		});

		// URL-Params laden.
		this.loadFromUrl();

		// Smart-Filter beim Laden initialisieren.
		this.updateSmartFilters({});
	};

	ImmoListManager.prototype.toggleSidebar = function () {
		var isOpen = this.sidebar && this.sidebar.classList.contains('open');
		isOpen ? this.closeSidebar() : this.openSidebar();
	};

	ImmoListManager.prototype.openSidebar = function () {
		if (!this.sidebar) { return; }
		this.sidebar.classList.add('open');
		this.sidebar.setAttribute('aria-hidden', 'false');
		if (this.backdrop) { this.backdrop.classList.add('open'); }
		var toggleBtn = this.container.querySelector('.immo-filter-toggle');
		if (toggleBtn) { toggleBtn.classList.add('active'); toggleBtn.setAttribute('aria-expanded', 'true'); }
	};

	ImmoListManager.prototype.closeSidebar = function () {
		if (!this.sidebar) { return; }
		this.sidebar.classList.remove('open');
		this.sidebar.setAttribute('aria-hidden', 'true');
		if (this.backdrop) { this.backdrop.classList.remove('open'); }
		var toggleBtn = this.container.querySelector('.immo-filter-toggle');
		if (toggleBtn) { toggleBtn.classList.remove('active'); toggleBtn.setAttribute('aria-expanded', 'false'); }
	};

	ImmoListManager.prototype.onFilterChange = function () {
		this.currentPage = 1;
		this.fetchProperties();
	};

	ImmoListManager.prototype.getActiveFilters = function () {
		var filters = {};
		var inputs = this.container.querySelectorAll('.immo-filter-input');
		inputs.forEach(function (input) {
			if (!input.name) { return; }
			if ((input.type === 'checkbox' || input.type === 'radio') && !input.checked) { return; }
			var val = input.value ? String(input.value).trim() : '';
			if (!val) { return; }
			if (input.type === 'checkbox') {
				if (!filters[input.name]) { filters[input.name] = []; }
				filters[input.name].push(val);
			} else {
				filters[input.name] = val;
			}
		});
		// Arrays → komma-getrennte Strings.
		Object.keys(filters).forEach(function (k) {
			if (Array.isArray(filters[k])) { filters[k] = filters[k].join(','); }
		});
		return filters;
	};

	ImmoListManager.prototype.fetchProperties = function () {
		var self    = this;
		var filters = this.getActiveFilters();
		var sort    = this.sortSelect ? this.sortSelect.value : 'newest';
		var params  = Object.assign({}, filters, {
			orderby:  sort,
			page:     this.currentPage,
			per_page: 12,
		});

		// URL aktualisieren (Back-Button Support).
		var qs = new URLSearchParams();
		Object.keys(params).forEach(function (k) { if (params[k] !== undefined && params[k] !== '') { qs.set(k, params[k]); } });
		history.pushState(null, '', '?' + qs.toString());

		// Tags + Badge aktualisieren.
		this.updateTags(filters);

		// Smart-Filter: Optionen ohne Ergebnisse deaktivieren.
		this.updateSmartFilters(filters);

		// Loader.
		if (this.loader) { this.loader.hidden = false; }
		if (this.grid)   { this.grid.style.opacity = '.4'; }

		// REST API.
		var url = API + '/properties?' + new URLSearchParams(params).toString();
		fetch(url, {
			headers: { 'X-WP-Nonce': cfg.nonce || '' }
		})
		.then(function (r) { return r.json(); })
		.then(function (data) {
			self.renderProperties(data.properties || []);
			self.renderPagination(data.pagination || {});
			self.updateCount(data.pagination ? data.pagination.total : 0);
		})
		.catch(function (err) {
			console.error('Immo Filter Error:', err);
		})
		.finally(function () {
			if (self.loader) { self.loader.hidden = true; }
			if (self.grid)   { self.grid.style.opacity = '1'; }
		});
	};

	ImmoListManager.prototype.renderProperties = function (properties) {
		var self = this;
		if (!this.grid) { return; }

		if (!properties.length) {
			this.grid.innerHTML = '<p class="immo-no-results">' + (i18n.noResults || 'Keine Immobilien gefunden.') + '</p>';
			return;
		}

		this.grid.innerHTML = properties.map(function (p) {
			return self.buildCard(p);
		}).join('');
	};

	ImmoListManager.prototype.buildCard = function (p) {
		var meta   = p.meta || {};
		var img    = p.featured_image;
		var status = meta.status || 'available';
		var statusMap = { available: 'Verfügbar', reserved: 'Reserviert', sold: 'Verkauft', rented: 'Vermietet' };
		var statusClass = { available: 'available', reserved: 'reserved', sold: 'sold', rented: 'sold' };
		var mode  = meta.mode || 'sale';
		var price = mode === 'rent' && meta.rent > 0 ? meta.rent_formatted : meta.price_formatted;
		var suffix = mode === 'rent' ? ' / Monat' : '';
		var loc   = [meta.postal_code, meta.city].filter(Boolean).join(' ');
		var topFeat = (meta.features_detail || []).slice(0, 3).map(function (f) {
			return '<li title="' + escHtml(f.label) + '"><span aria-hidden="true">' + f.icon + '</span></li>';
		}).join('');

		return '<article class="immo-property-card" role="listitem" data-property-id="' + p.id + '">'
			+ '<a href="' + escHtml(p.permalink) + '" class="immo-card-link" tabindex="-1" aria-hidden="true">'
			+ '<div class="immo-card-image">'
			+ (img ? '<img src="' + escHtml(img.url_large || img.url_medium || img.url) + '" alt="' + escHtml(img.alt || p.title) + '" loading="lazy" width="800" height="600">' : '<div class="immo-card-no-image">🏠</div>')
			+ '<span class="immo-status-badge status-' + escHtml(statusClass[status] || 'available') + '">' + escHtml(statusMap[status] || status) + '</span>'
			+ (meta.property_type ? '<span class="immo-type-badge">' + escHtml(meta.property_type) + '</span>' : '')
			+ '</div></a>'
			+ '<div class="immo-card-body">'
			+ '<h3 class="immo-card-title"><a href="' + escHtml(p.permalink) + '">' + escHtml(p.title) + '</a></h3>'
			+ (loc ? '<p class="immo-card-location">📍 ' + escHtml(loc) + (meta.region_state_label ? ', ' + escHtml(meta.region_state_label) : '') + '</p>' : '')
			+ (price ? '<p class="immo-card-price"><strong>' + escHtml(price + suffix) + '</strong></p>' : '')
			+ '<ul class="immo-card-facts">'
			+ (meta.rooms ? '<li>🛏️ ' + escHtml(String(meta.rooms)) + ' Zi.</li>' : '')
			+ (meta.area  ? '<li>📐 ' + escHtml(Number(meta.area).toLocaleString('de-AT')) + ' m²</li>' : '')
			+ (meta.energy_class ? '<li>⚡ ' + escHtml(meta.energy_class) + '</li>' : '')
			+ '</ul>'
			+ (topFeat ? '<ul class="immo-card-features">' + topFeat + '</ul>' : '')
			+ '<div class="immo-card-footer"><a href="' + escHtml(p.permalink) + '" class="immo-btn immo-btn-primary immo-btn-sm">Details ansehen</a></div>'
			+ '</div></article>';
	};

	ImmoListManager.prototype.renderPagination = function (pagination) {
		if (!this.pagination) { return; }
		var pages   = pagination.pages || 1;
		var current = pagination.current_page || 1;
		var self    = this;

		if (pages <= 1) { this.pagination.hidden = true; return; }
		this.pagination.hidden = false;
		var html = '';
		for (var i = 1; i <= pages; i++) {
			html += '<button class="immo-page-btn' + (i === current ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
		}
		this.pagination.innerHTML = html;
		this.pagination.querySelectorAll('.immo-page-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				self.currentPage = parseInt(btn.dataset.page, 10);
				self.fetchProperties();
				var top = self.container.getBoundingClientRect().top + window.scrollY - 20;
				window.scrollTo({ top: top, behavior: 'smooth' });
			});
		});
	};

	ImmoListManager.prototype.updateCount = function (total) {
		if (!this.countEl) { return; }
		this.countEl.textContent = total + (total === 1 ? ' Immobilie' : ' Immobilien');
	};

	ImmoListManager.prototype.updateTags = function (filters) {
		if (!this.tagsEl || !this.tagsWrap) { return; }
		var self     = this;
		var labelMap = {
			type: 'Typ', mode: 'Angebot', price_min: 'Preis von', price_max: 'Preis bis',
			area_min: 'Fläche von', area_max: 'Fläche bis', rooms: 'Zimmer',
			region_state: 'Bundesland', region_district: 'Bezirk', status: 'Status',
			energy_class: 'Energie', features: 'Ausstattung',
		};
		var activeCount = 0;
		var html = '';
		Object.keys(filters).forEach(function (k) {
			if (!filters[k] || k === 'status') { return; }
			activeCount++;
			html += '<span class="immo-filter-tag">' + escHtml((labelMap[k] || k) + ': ' + filters[k])
				+ '<button data-filter-key="' + escHtml(k) + '" aria-label="Filter entfernen">✕</button></span>';
		});
		this.tagsEl.innerHTML = html;
		this.tagsWrap.hidden = activeCount === 0;

		// Badge.
		if (this.badgeEl) {
			this.badgeEl.textContent = String(activeCount);
			this.badgeEl.hidden = activeCount === 0;
		}

		// Tag remove handler.
		this.tagsEl.querySelectorAll('button[data-filter-key]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var key = btn.dataset.filterKey;
				var inputs = self.container.querySelectorAll('[name="' + key + '"]');
				inputs.forEach(function (inp) {
					if (inp.type === 'checkbox' || inp.type === 'radio') { inp.checked = false; }
					else { inp.value = ''; }
				});
				self.onFilterChange();
			});
		});
	};

	ImmoListManager.prototype.resetFilters = function () {
		var inputs = this.container.querySelectorAll('.immo-filter-input');
		inputs.forEach(function (input) {
			if (input.type === 'checkbox') { input.checked = false; }
			else if (input.type === 'radio') { input.checked = input.value === ''; }
			else { input.value = ''; }
		});
		this.currentPage = 1;
		this.fetchProperties();
	};

	ImmoListManager.prototype.loadFromUrl = function () {
		var params = new URLSearchParams(window.location.search);
		var self   = this;
		params.forEach(function (value, key) {
			var inputs = self.container.querySelectorAll('[name="' + key + '"]');
			inputs.forEach(function (input) {
				if (input.type === 'checkbox' && value.split(',').includes(input.value)) {
					input.checked = true;
				} else if (input.type === 'radio' && input.value === value) {
					input.checked = true;
				} else if (input.type !== 'checkbox' && input.type !== 'radio') {
					input.value = value;
				}
			});
		});
		if (params.toString()) { this.fetchProperties(); }
	};

	ImmoListManager.prototype.loadDistricts = function (state, select) {
		if (!state) {
			select.innerHTML = '<option value="">' + (i18n.district || '— Bezirk —') + '</option>';
			select.disabled = true;
			return;
		}
		select.disabled = true;
		fetch(API + '/regions/' + encodeURIComponent(state) + '/districts')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				var districts = data.districts || {};
				var html = '<option value="">' + (i18n.district || '— Bezirk —') + '</option>';
				Object.keys(districts).forEach(function (k) {
					html += '<option value="' + escHtml(k) + '">' + escHtml(districts[k]) + '</option>';
				});
				select.innerHTML = html;
				select.disabled = false;
			})
			.catch(function () { select.disabled = false; });
	};

	/* ─── Smart Filter: Optionen mit 0 Ergebnissen deaktivieren ─── */
	ImmoListManager.prototype.updateSmartFilters = function (activeFilters) {
		var self = this;
		// Prüfe nur bestimmte kategorische Filter (nicht Ranges)
		var filterGroups = [
			{ name: 'status',         type: 'checkbox' },
			{ name: 'mode',           type: 'radio' },
			{ name: 'type',           type: 'checkbox' },
			{ name: 'rooms',          type: 'checkbox' },
			{ name: 'energy_class',   type: 'checkbox' },
			{ name: 'region_state',   type: 'select' },
		];

		filterGroups.forEach(function (group) {
			var inputs;
			if (group.type === 'select') {
				inputs = self.container.querySelectorAll('[name="' + group.name + '"] option:not([value=""])');
			} else {
				inputs = self.container.querySelectorAll('[name="' + group.name + '"]');
			}

			inputs.forEach(function (input) {
				var val = input.value;
				if (!val) { return; }

				var testParams = {};
				Object.keys(activeFilters).forEach(function (k) {
					if (k !== group.name) { testParams[k] = activeFilters[k]; }
				});
				testParams[group.name] = val;
				testParams.per_page = 1;

				fetch(API + '/properties?' + new URLSearchParams(testParams).toString())
					.then(function (r) { return r.json(); })
					.then(function (data) {
						var total = (data.pagination && data.pagination.total) || 0;
						var el = group.type === 'select' ? input : input.closest('label, .immo-filter-option, .immo-filter-btn-option');
						if (!el) { return; }
						if (group.type === 'select') {
							if (total === 0 && !input.selected) {
								input.disabled = true;
							} else {
								input.disabled = false;
							}
						} else {
							if (total === 0 && !(input.checked || input.selected)) {
								el.classList.add('immo-option-disabled');
							} else {
								el.classList.remove('immo-option-disabled');
							}
							// Anzahl der Treffer anzeigen
							var countEl = el.querySelector('.immo-filter-count');
							if (!countEl) {
								countEl = document.createElement('span');
								countEl.className = 'immo-filter-count';
								el.appendChild(countEl);
							}
							countEl.textContent = '(' + total + ')';
						}
					})
					.catch(function () {});
			});
		});
	};


	/* ─────────────────────────────────────────────────────────
	 * Inquiry Form
	 * ───────────────────────────────────────────────────────── */
	function initInquiryForms() {
		document.querySelectorAll('.immo-inquiry-form').forEach(function (form) {
			var propertyId  = parseInt(form.dataset.propertyId, 10) || 0;
			var submitBtn   = form.querySelector('.immo-inquiry-submit');
			
			// Find success/error messages relative to the form (supporting card AND lightbox).
			var container  = form.closest('.immo-inquiry-card, .immo-inquiry-lightbox-panel');
			var successMsg = container ? container.querySelector('.immo-inquiry-success') : null;
			var errorMsg   = container ? container.querySelector('.immo-inquiry-error') : null;

			if (!submitBtn) { return; }

			submitBtn.addEventListener('click', function () {
				// Client-seitige Validierung.
				var errors = [];
				var nameField  = form.querySelector('[name="inquirer_name"]');
				var emailField = form.querySelector('[name="inquirer_email"]');
				var consent    = form.querySelector('[name="consent"]');

				clearFieldErrors(form);

				if (nameField && !nameField.value.trim()) {
					setFieldError(nameField, i18n.required || 'Pflichtfeld.');
					errors.push('name');
				}
				if (emailField && !isValidEmail(emailField.value)) {
					setFieldError(emailField, i18n.invalidEmail || 'Gültige E-Mail-Adresse erforderlich.');
					errors.push('email');
				}
				if (consent && !consent.checked) {
					setFieldError(consent, i18n.consentRequired || 'Bitte bestätige die Datenschutzerklärung.');
					errors.push('consent');
				}
				if (errors.length) { return; }

				// Loading.
				submitBtn.disabled = true;
				var spinner = submitBtn.querySelector('.immo-btn-spinner');
				if (spinner) { spinner.hidden = false; }

				var body = {
					property_id:       propertyId,
					inquirer_name:     nameField ? nameField.value.trim() : '',
					inquirer_email:    emailField ? emailField.value.trim() : '',
					inquirer_phone:    (form.querySelector('[name="inquirer_phone"]') || {}).value || '',
					inquirer_message:  (form.querySelector('[name="inquirer_message"]') || {}).value || '',
					consent:           true,
				};

				fetch(API + '/inquiries', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
					body: JSON.stringify(body),
				})
				.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
				.then(function (result) {
					if (result.ok) {
						form.hidden = true;
						if (successMsg) { successMsg.hidden = false; }
					} else {
						var msg = result.data && result.data.message ? result.data.message : (i18n.inquiryError || 'Fehler.');
						if (errorMsg) { errorMsg.textContent = msg; errorMsg.hidden = false; }
					}
				})
				.catch(function () {
					if (errorMsg) { errorMsg.textContent = i18n.inquiryError || 'Netzwerkfehler.'; errorMsg.hidden = false; }
				})
				.finally(function () {
					submitBtn.disabled = false;
					if (spinner) { spinner.hidden = true; }
				});
			});
		});
	}

	function clearFieldErrors(form) {
		form.querySelectorAll('.immo-field-error').forEach(function (el) { el.textContent = ''; el.hidden = true; });
		form.querySelectorAll('.has-error').forEach(function (el) { el.classList.remove('has-error'); });
	}

	function setFieldError(input, msg) {
		input.classList.add('has-error');
		var err = input.closest('.immo-form-field') ? input.closest('.immo-form-field').querySelector('.immo-field-error') : null;
		if (err) { err.textContent = msg; err.hidden = false; }
	}

	function isValidEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }

	/* ─────────────────────────────────────────────────────────
	 * Utility
	 * ───────────────────────────────────────────────────────── */
	function escHtml(str) {
		return String(str || '').replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
		});
	}

	/* ─────────────────────────────────────────────────────────
	 * Init
	 * ───────────────────────────────────────────────────────── */
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.immo-list-container').forEach(function (container) {
			new ImmoListManager(container);
		});
		initInquiryForms();
	});

})();
