/**
 * Immo Manager – Frontend JS v1.0.3
 * Slider | Accordion | Anfrage-Lightbox | Bild-Lightbox | Karte | Sticky-Sidebar
 */
(function () {
	'use strict';

	var cfg = window.immoManager || {};

	/* ─────────────────────────────────────────────
	 * 1. IMAGE SLIDER
	 * ───────────────────────────────────────────── */
	function initSliders() {
		document.querySelectorAll('.immo-slider').forEach(function (slider) {
			var slides     = slider.querySelectorAll('.immo-slide');
			var thumbs     = slider.querySelectorAll('.immo-slider-thumb');
			var prevBtn    = slider.querySelector('.immo-slider-prev');
			var nextBtn    = slider.querySelector('.immo-slider-next');
			var counterCur = slider.querySelector('.immo-slider-current');
			var expandBtn  = slider.querySelector('.immo-slider-expand');
			var total      = slides.length;
			var current    = 0;

			if (!total) { return; }

			function goTo(idx) {
				slides[current].classList.remove('active');
				slides[current].setAttribute('aria-hidden', 'true');
				if (thumbs[current]) { thumbs[current].classList.remove('active'); thumbs[current].setAttribute('aria-selected', 'false'); }
				current = (idx + total) % total;
				slides[current].classList.add('active');
				slides[current].setAttribute('aria-hidden', 'false');
				if (thumbs[current]) { thumbs[current].classList.add('active'); thumbs[current].setAttribute('aria-selected', 'true'); thumbs[current].scrollIntoView({ block:'nearest', inline:'center' }); }
				if (counterCur) { counterCur.textContent = String(current + 1); }
			}

			if (prevBtn) { prevBtn.addEventListener('click', function () { goTo(current - 1); }); }
			if (nextBtn) { nextBtn.addEventListener('click', function () { goTo(current + 1); }); }
			thumbs.forEach(function (thumb) {
				thumb.addEventListener('click', function () { goTo(parseInt(thumb.dataset.index, 10)); });
			});

			// Swipe
			var touchX = 0;
			slider.addEventListener('touchstart', function (e) { touchX = e.touches[0].clientX; }, { passive: true });
			slider.addEventListener('touchend', function (e) {
				var dx = e.changedTouches[0].clientX - touchX;
				if (Math.abs(dx) > 40) { goTo(current + (dx < 0 ? 1 : -1)); }
			}, { passive: true });

			// Keyboard
			slider.setAttribute('tabindex', '0');
			slider.addEventListener('keydown', function (e) {
				if (e.key === 'ArrowLeft') { goTo(current - 1); }
				if (e.key === 'ArrowRight') { goTo(current + 1); }
			});

			// Lightbox öffnen
			if (expandBtn) {
				expandBtn.addEventListener('click', function () {
					openLightbox(current, slider);
				});
			}
			slides.forEach(function (slide, idx) {
				var img = slide.querySelector('.immo-slide-img');
				if (img) { img.addEventListener('click', function () { openLightbox(idx, slider); }); }
			});
		});
	}

	function initUnitImages() {
		document.querySelectorAll('.immo-unit-image-trigger').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var img = btn.querySelector('img');
				var src = btn.dataset.full || (img ? img.src : '');
				var alt = btn.dataset.alt || (img ? img.alt : '');
				if (!src) { return; }
				openLightboxImage(src, alt);
			});
		});
	}

	function initGridGalleries() {
		document.querySelectorAll('.immo-grid-gallery').forEach(function (grid) {
			var items = grid.querySelectorAll('.immo-grid-item');
			items.forEach(function (item, idx) {
				item.addEventListener('click', function () {
					openLightbox(idx, grid);
				});
			});
		});
	}

	function openLightboxImage(src, alt) {
		var lb      = document.getElementById('immo-lightbox');
		if (!lb) { return; }
		var lbImg   = lb.querySelector('.immo-lightbox-img');
		var lbClose = lb.querySelector('.immo-lightbox-close');
		var counter = lb.querySelector('.immo-lightbox-counter');

		if (lbImg) {
			lbImg.src = src;
			lbImg.alt = alt || '';
		}
		if (counter) {
			counter.textContent = '';
		}

		lb.hidden = false;
		document.body.style.overflow = 'hidden';
		if (lbClose) { lbClose.focus(); }

		function closeLb() {
			lb.hidden = true;
			document.body.style.overflow = '';
		}

		lbClose && lbClose.addEventListener('click', closeLb, { once: true });
		lb.addEventListener('click', function (e) { if (e.target === lb) { closeLb(); } }, { once: true });

		document.addEventListener('keydown', function onKey(e) {
			if (!lb || lb.hidden) { document.removeEventListener('keydown', onKey); return; }
			if (e.key === 'Escape') { closeLb(); document.removeEventListener('keydown', onKey); }
		});
	}

	/* ─────────────────────────────────────────────
	 * 2. BILD-LIGHTBOX
	 * ───────────────────────────────────────────── */
	function openLightbox(startIdx, container) {
		var lb      = document.getElementById('immo-lightbox');
		if (!lb) { return; }
		var lbImg   = lb.querySelector('.immo-lightbox-img');
		var lbClose = lb.querySelector('.immo-lightbox-close');
		var lbPrev  = lb.querySelector('.immo-lightbox-prev');
		var lbNext  = lb.querySelector('.immo-lightbox-next');
		var counter = lb.querySelector('.immo-lightbox-counter');

		var images = [];
		(container || document).querySelectorAll('.immo-slide-img, .immo-grid-img').forEach(function (img) {
			images.push({ src: img.dataset.full || img.src, alt: img.alt || '' });
		});
		if (!images.length) { return; }

		var cur = startIdx || 0;

		function show(idx) {
			cur = (idx + images.length) % images.length;
			lbImg.src = images[cur].src;
			lbImg.alt = images[cur].alt;
			if (counter) { counter.textContent = (cur + 1) + ' / ' + images.length; }
		}

		show(cur);
		lb.hidden = false;
		document.body.style.overflow = 'hidden';
		if (lbClose) { lbClose.focus(); }

		function closeLb() {
			lb.hidden = true;
			document.body.style.overflow = '';
		}
		lbClose && lbClose.addEventListener('click', closeLb, { once: true });
		lbPrev  && lbPrev.addEventListener('click', function () { show(cur - 1); });
		lbNext  && lbNext.addEventListener('click', function () { show(cur + 1); });
		lb.addEventListener('click', function (e) { if (e.target === lb) { closeLb(); } }, { once: true });

		function onKey(e) {
			if (!lb || lb.hidden) { document.removeEventListener('keydown', onKey); return; }
			if (e.key === 'Escape')     { closeLb(); document.removeEventListener('keydown', onKey); }
			if (e.key === 'ArrowLeft')  { show(cur - 1); }
			if (e.key === 'ArrowRight') { show(cur + 1); }
		}
		document.addEventListener('keydown', onKey);
	}

	/* ─────────────────────────────────────────────
	 * 3. AKKORDEON
	 * ───────────────────────────────────────────── */
	function initAccordions() {
		document.querySelectorAll('.immo-accordion-header').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var expanded = btn.getAttribute('aria-expanded') === 'true';
				var body = btn.nextElementSibling;
				if (!body) { return; }
				btn.setAttribute('aria-expanded', String(!expanded));
				body.hidden = expanded;

				// Smooth scroll wenn zugeklappt
				if (!expanded) {
					var rect = btn.getBoundingClientRect();
					if (rect.top < 0) {
						btn.scrollIntoView({ behavior: 'smooth', block: 'start' });
					}
				}
			});
		});

		// Scroll-to-Link (aus Top-Features)
		document.querySelectorAll('.immo-scroll-to').forEach(function (link) {
			link.addEventListener('click', function (e) {
				e.preventDefault();
				var target = document.querySelector(link.getAttribute('href'));
				if (!target) { return; }
				var btn = target.querySelector('.immo-accordion-header');
				if (btn && btn.getAttribute('aria-expanded') === 'false') {
					btn.click();
				}
				setTimeout(function () {
					target.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}, 50);
			});
		});
	}

	/* ─────────────────────────────────────────────
	 * 4. ANFRAGE-LIGHTBOX
	 * ───────────────────────────────────────────── */
	function initInquiryLightbox() {
		var lb       = document.getElementById('immo-inquiry-lightbox');
		var openBtns = document.querySelectorAll('.immo-btn-inquiry-open');
		var closeBtn = document.getElementById('immo-inquiry-close');
		var backdrop = document.getElementById('immo-inquiry-backdrop');

		if (!lb) { return; }

		function openLb() {
			lb.hidden = false;
			document.body.style.overflow = 'hidden';
			var firstInput = lb.querySelector('input:not([type=checkbox])');
			if (firstInput) { setTimeout(function () { firstInput.focus(); }, 100); }
		}

		function closeLb() {
			lb.hidden = true;
			document.body.style.overflow = '';
		}

		openBtns.forEach(function (btn) { btn.addEventListener('click', openLb); });
		if (closeBtn) { closeBtn.addEventListener('click', closeLb); }
		if (backdrop) { backdrop.addEventListener('click', closeLb); }

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !lb.hidden) { closeLb(); }
		});

		// Formular absenden
		var form = lb.querySelector('.immo-inquiry-form');
		if (!form) { return; }

		var submitBtn = form.querySelector('.immo-inquiry-submit');
		var successEl = lb.querySelector('.immo-inquiry-success');
		var errorEl   = lb.querySelector('.immo-inquiry-error');
		var apiBase   = cfg.apiBase || '';
		var nonce     = cfg.nonce || '';

		if (!submitBtn) { return; }

		submitBtn.addEventListener('click', function () {
			clearErrors(form);
			var nameEl  = form.querySelector('[name="inquirer_name"]');
			var emailEl = form.querySelector('[name="inquirer_email"]');
			var consent = form.querySelector('[name="consent"]');
			var errors  = [];

			if (nameEl && !nameEl.value.trim()) { setError(nameEl, 'Pflichtfeld.'); errors.push('name'); }
			if (emailEl && !validEmail(emailEl.value)) { setError(emailEl, 'Gültige E-Mail erforderlich.'); errors.push('email'); }
			if (consent && !consent.checked) { setError(consent, 'Bitte bestätige die Datenschutzerklärung.'); errors.push('consent'); }
			if (errors.length) { return; }

			submitBtn.disabled = true;
			var spinner = submitBtn.querySelector('.immo-btn-spinner');
			if (spinner) { spinner.hidden = false; }
			if (errorEl) { errorEl.hidden = true; }

			var pid = parseInt(form.dataset.propertyId, 10) || 0;
			var unitField = form.querySelector('[name="unit_id"]');
			fetch(apiBase + '/inquiries', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
				body: JSON.stringify({
					property_id:      pid,
					unit_id:          unitField && unitField.value ? parseInt(unitField.value, 10) : null,
					inquirer_name:    nameEl ? nameEl.value.trim() : '',
					inquirer_email:   emailEl ? emailEl.value.trim() : '',
					inquirer_phone:   (form.querySelector('[name="inquirer_phone"]') || {}).value || '',
					inquirer_message: (form.querySelector('[name="inquirer_message"]') || {}).value || '',
					consent: true,
				}),
			})
			.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
			.then(function (res) {
				if (res.ok) {
					form.hidden = true;
					if (successEl) { successEl.hidden = false; }
					setTimeout(closeLb, 3000);
				} else {
					var msg = (res.data && res.data.message) || 'Fehler beim Senden.';
					if (errorEl) { errorEl.textContent = msg; errorEl.hidden = false; }
				}
			})
			.catch(function () {
				if (errorEl) { errorEl.textContent = 'Netzwerkfehler.'; errorEl.hidden = false; }
			})
			.finally(function () {
				submitBtn.disabled = false;
				if (spinner) { spinner.hidden = true; }
			});
		});
	}

	function clearErrors(form) {
		form.querySelectorAll('.immo-field-error').forEach(function (el) { el.textContent = ''; el.hidden = true; });
		form.querySelectorAll('.has-error').forEach(function (el) { el.classList.remove('has-error'); });
	}
	function setError(input, msg) {
		input.classList.add('has-error');
		var err = input.closest('.immo-form-field') && input.closest('.immo-form-field').querySelector('.immo-field-error');
		if (err) { err.textContent = msg; err.hidden = false; }
	}
	function validEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }

	/* ─────────────────────────────────────────────
	 * 5. LEAFLET-KARTE
	 * ───────────────────────────────────────────── */
	function initMaps() {
		if (!cfg.mapEnabled || typeof window.L === 'undefined') { return; }
		document.querySelectorAll('.immo-map[data-lat]').forEach(function (el) {
			var lat = parseFloat(el.dataset.lat);
			var lng = parseFloat(el.dataset.lng);
			if (!lat || !lng) { return; }
			var map = window.L.map(el, { zoomControl: true, scrollWheelZoom: false }).setView([lat, lng], 15);
			window.L.tileLayer(cfg.mapTileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				attribution: cfg.mapAttrib || '&copy; OpenStreetMap',
				maxZoom: 19,
			}).addTo(map);
			window.L.marker([lat, lng]).addTo(map)
				.bindPopup('<strong>' + escHtml(el.dataset.title || '') + '</strong>')
				.openPopup();

			// Karte initialisieren wenn Akkordeon geöffnet wird
			var accordion = el.closest('.immo-accordion-body');
			if (accordion) {
				var observer = new MutationObserver(function () {
					if (!accordion.hidden) { map.invalidateSize(); observer.disconnect(); }
				});
				observer.observe(accordion, { attributes: true, attributeFilter: ['hidden'] });
			}
		});
	}

	/* ─────────────────────────────────────────────
	 * 6. STICKY SIDEBAR: TOP-OFFSET für Sticky-Header
	 * ───────────────────────────────────────────── */
	function initStickyOffsets() {
		function getStickyHeaderHeight() {
			var h = 0;
			document.querySelectorAll('header, .site-header, #masthead, [class*="sticky"], [style*="position: sticky"], [style*="position:sticky"]').forEach(function (el) {
				var style = window.getComputedStyle(el);
				if (style.position === 'sticky' || style.position === 'fixed') {
					var rect = el.getBoundingClientRect();
					if (rect.top <= 8) { h = Math.max(h, rect.height); }
				}
			});
			return h;
		}

		function applyOffsets() {
			var offset = getStickyHeaderHeight();
			var top = Math.max(20, offset + 12) + 'px';

			// Filter-Sidebar
			var fs = document.querySelector('.immo-filter-sidebar');
			if (fs) {
				var media = window.matchMedia('(min-width:1025px)');
				if (media.matches) {
					fs.style.top = top;
					fs.style.maxHeight = 'calc(100vh - ' + (offset + 32) + 'px)';
				}
			}
			// Detail Sticky Sidebar
			var ds = document.querySelector('.immo-detail-sticky-sidebar');
			if (ds) { ds.style.top = top; }
		}

		applyOffsets();
		window.addEventListener('resize', applyOffsets, { passive: true });
		// Nach kurzem Delay nochmal (Themes laden Header-Styles ggf. async)
		setTimeout(applyOffsets, 500);
	}

	/* ─────────────────────────────────────────────
	 * GLOBAL SEARCH LIGHTBOX & AUTOCOMPLETE
	 * ───────────────────────────────────────────── */
	window.immoOpenSearch = function() {
		console.log('Suche-Lightbox getriggert.');
		var filterBtn = document.querySelector('.immo-filter-toggle');
		if (filterBtn) { 
			filterBtn.click(); 
		} else {
			alert('Bitte nutzen Sie das Suchformular auf der Immobilien-Seite.');
		}
	};

	function initSearchAutocomplete() {
		var inputs = document.querySelectorAll('.immo-search-autocomplete');
		if (!inputs.length) return;

		var apiBase = cfg.apiBase || '/wp-json/immo-manager/v1';

		inputs.forEach(function(input) {
			var resultsContainer = input.nextElementSibling;
			if (!resultsContainer || !resultsContainer.classList.contains('immo-search-autocomplete-results')) return;

			var timeout = null;

			input.addEventListener('input', function() {
				var query = input.value.trim();
				
				if (query.length < 2) {
					resultsContainer.innerHTML = '';
					resultsContainer.classList.remove('active');
					return;
				}

				clearTimeout(timeout);
				timeout = setTimeout(function() {
					fetch(apiBase + '/properties?search=' + encodeURIComponent(query) + '&per_page=5')
						.then(function(response) { return response.json(); })
						.then(function(data) {
							resultsContainer.innerHTML = '';
							if (data && data.properties && data.properties.length > 0) {
								data.properties.forEach(function(prop) {
									var item = document.createElement('a');
									item.href = prop.permalink || '#';
									item.className = 'immo-search-result-item';
									
									var imgHtml = '';
									if (prop.featured_image && prop.featured_image.url_thumbnail) {
										imgHtml = '<img src="' + escHtml(prop.featured_image.url_thumbnail) + '" alt="">';
									}

									var priceHtml = '';
									if (prop.meta && prop.meta.price_formatted) priceHtml = 'Kauf: ' + prop.meta.price_formatted;
									else if (prop.meta && prop.meta.rent_formatted) priceHtml = 'Miete: ' + prop.meta.rent_formatted;

									item.innerHTML = imgHtml + '<div class="immo-search-result-info"><h4>' + escHtml(prop.title) + '</h4><span>' + escHtml((prop.meta && prop.meta.address) || (prop.meta && prop.meta.city) || '') + ' - ' + escHtml(priceHtml) + '</span></div>';
									
									resultsContainer.appendChild(item);
								});
								resultsContainer.classList.add('active');
							} else {
								resultsContainer.innerHTML = '<div class="immo-search-result-item"><div class="immo-search-result-info"><span>Keine Ergebnisse gefunden.</span></div></div>';
								resultsContainer.classList.add('active');
							}
						})
						.catch(function(err) {
							console.error('Autocomplete Error:', err);
						});
				}, 300);
			});

			// Hide when clicking outside
			document.addEventListener('click', function(e) {
				if (!input.contains(e.target) && !resultsContainer.contains(e.target)) {
					resultsContainer.classList.remove('active');
				}
			});
			
			// Show again when focused
			input.addEventListener('focus', function() {
				if (resultsContainer.innerHTML.trim() !== '') {
					resultsContainer.classList.add('active');
				}
			});
		});
	}

	/* ─────────────────────────────────────────────
	 * WIDGET MAP INIT
	 * ───────────────────────────────────────────── */
	window.immoInitMap = function(mapId) {
		if (!cfg.mapEnabled || typeof window.L === 'undefined') { return; }
		var el = document.getElementById(mapId);
		if (!el) return;
		
		var pointsStr = el.dataset.points;
		if (!pointsStr) return;
		
		try {
			var points = JSON.parse(pointsStr);
			if (!points.length) return;
			
			var map = window.L.map(el, { zoomControl: true, scrollWheelZoom: false });
			window.L.tileLayer(cfg.mapTileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				attribution: cfg.mapAttrib || '&copy; OpenStreetMap',
				maxZoom: 19,
			}).addTo(map);

			var bounds = [];
			points.forEach(function(p) {
				if (p.lat && p.lng) {
					var marker = window.L.marker([p.lat, p.lng]).addTo(map);
					var popupContent = '<strong><a href="' + escHtml(p.link) + '">' + escHtml(p.title) + '</a></strong><br>' + escHtml(p.price);
					if (p.image) {
						popupContent = '<img src="' + escHtml(p.image) + '" style="width:100%;max-width:150px;border-radius:4px;margin-bottom:5px;"><br>' + popupContent;
					}
					marker.bindPopup(popupContent);
					bounds.push([p.lat, p.lng]);
				}
			});

			if (bounds.length > 0) {
				map.fitBounds(bounds, { padding: [20, 20], maxZoom: 15 });
			}
		} catch (e) {
			console.error('Fehler beim Initialisieren der Karte:', e);
		}
	};

	/* ─────────────────────────────────────────────
	 * WIDGET LIST SLIDER
	 * ───────────────────────────────────────────── */
	function initListSliders() {
		document.querySelectorAll('.immo-list-slider').forEach(function(slider) {
			// Einfacher Scroll-Slider für Elementor "Slider" Layout
			slider.style.display = 'flex';
			slider.style.overflowX = 'auto';
			slider.style.scrollSnapType = 'x mandatory';
			slider.style.gap = '20px';
			slider.style.paddingBottom = '20px';
			slider.style.scrollbarWidth = 'none'; // Firefox
			
			var cards = slider.querySelectorAll('.immo-property-card');
			cards.forEach(function(card) {
				card.style.flex = '0 0 auto';
				card.style.width = '80%';
				card.style.maxWidth = '300px';
				card.style.scrollSnapAlign = 'start';
			});
		});
	}

	/* ─────────────────────────────────────────────
	 * Utility
	 * ───────────────────────────────────────────── */
	function escHtml(str) {
		return String(str || '').replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
		});
	}

	/* ─────────────────────────────────────────────
	 * Init
	 * ───────────────────────────────────────────── */
	document.addEventListener('DOMContentLoaded', function () {
		initSliders();
		initUnitImages();
		initGridGalleries();
		initAccordions();
		initInquiryLightbox();
		initMaps();
		initStickyOffsets();
		initSearchAutocomplete();
		initListSliders();
	});

})();
