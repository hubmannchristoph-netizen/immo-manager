/**
 * Immo Manager – Embed-Widget (Standalone, framework-frei).
 *
 * Bettet Immobilien, Bauprojekte und Wohneinheiten aus einer Immo-Manager-
 * Installation in BELIEBIGE Webseiten ein (WordPress oder nicht). Kommuniziert
 * ausschließlich über die öffentliche REST-API (/wp-json/immo-manager/v1).
 *
 * Einbindung (eine Zeile Script + ein Platzhalter-Element):
 *
 *   <div data-immo-embed="projects" data-columns="3"></div>
 *   <div data-immo-embed="units" data-project="gruenes-quartier"></div>
 *   <div data-immo-embed="properties" data-mode="sale" data-limit="6" data-filters="1"></div>
 *   <div data-immo-embed="property" data-slug="penthouse-wien"></div>
 *   <div data-immo-embed="project" data-slug="gruenes-quartier"></div>
 *   <script src="https://IHRE-WP-SEITE.at/wp-content/plugins/immo-manager/public/embed/immo-embed.js" defer></script>
 *
 * Die API-URL wird automatisch aus der Script-URL abgeleitet; alternativ
 * per data-api="https://…/wp-json/immo-manager/v1" am <script> oder Element setzen.
 *
 * Styles werden in einem Shadow DOM isoliert (kein CSS-Konflikt mit der Host-Seite).
 * Farben: data-primary / data-accent / data-radius am Element oder Script,
 * sonst werden sie aus den Plugin-Einstellungen (/settings/public) übernommen.
 *
 * @package ImmoManager
 * @version 1.4.0
 */
(function (window, document) {
	'use strict';

	if (window.ImmoEmbed && window.ImmoEmbed.__loaded) {
		return;
	}

	/* ------------------------------------------------------------------ *
	 * Konfiguration
	 * ------------------------------------------------------------------ */
	var script = document.currentScript || (function () {
		var all = document.getElementsByTagName('script');
		for (var i = all.length - 1; i >= 0; i--) {
			if (/immo-embed(\.min)?\.js/.test(all[i].src || '')) { return all[i]; }
		}
		return null;
	})();

	function deriveApi(src) {
		try {
			var u = new URL(src, window.location.href);
			var m = u.pathname.match(/^(.*?)\/wp-content\//);
			return u.origin + (m ? m[1] : '') + '/wp-json/immo-manager/v1';
		} catch (e) {
			return '';
		}
	}

	var scriptAttr = function (name, fallback) {
		var v = script ? script.getAttribute('data-' + name) : null;
		return (v === null || v === undefined || v === '') ? fallback : v;
	};

	var CONFIG = {
		api:      scriptAttr('api', deriveApi(script ? script.src : '')).replace(/\/+$/, ''),
		lang:     scriptAttr('lang', (document.documentElement.lang || 'de').slice(0, 2).toLowerCase()),
		primary:  scriptAttr('primary', ''),
		accent:   scriptAttr('accent', ''),
		radius:   scriptAttr('radius', ''),
		target:   scriptAttr('target', '_self'),
		linkTemplate: scriptAttr('link-template', ''),
		projectLinkTemplate: scriptAttr('project-link-template', '')
	};

	/* ------------------------------------------------------------------ *
	 * i18n
	 * ------------------------------------------------------------------ */
	var I18N = {
		de: {
			loading: 'Lädt …', error: 'Inhalte konnten nicht geladen werden.', empty: 'Keine Einträge gefunden.',
			details: 'Details ansehen', project: 'Projekt ansehen', more: 'Mehr laden', perMonth: '/ Monat', from: 'ab',
			units: 'Einheiten', unit: 'Einheit', available: 'verfügbar', nr: 'Nr.', floor: 'Etage', area: 'Fläche',
			rooms: 'Zi.', price: 'Preis', status: 'Status', ground: 'EG', total: 'Gesamt', all: 'Alle',
			mode: 'Angebot', sale: 'Kaufen', rent: 'Mieten', region: 'Bundesland', priceMax: 'Preis bis', roomsMin: 'Zimmer ab',
			search: 'Suchen', reset: 'Zurücksetzen', results: 'Ergebnisse', floorPlan: 'Grundriss', description: 'Beschreibung',
			features: 'Ausstattung', contact: 'Kontakt', priceList: 'Preis siehe Preisliste',
			costs: 'Betriebsnebenkosten / Monat (brutto)', opCosts: 'Betriebskosten', heatCosts: 'Heizkosten', otherCosts: 'Sonstige Kosten', costsTotal: 'Gesamt',
			st: { available: 'Verfügbar', reserved: 'Reserviert', sold: 'Verkauft', rented: 'Vermietet' },
			ps: { planning: 'In Planung', building: 'In Bau', completed: 'Fertiggestellt' }
		},
		en: {
			loading: 'Loading …', error: 'Content could not be loaded.', empty: 'No entries found.',
			details: 'View details', project: 'View project', more: 'Load more', perMonth: '/ month', from: 'from',
			units: 'units', unit: 'unit', available: 'available', nr: 'No.', floor: 'Floor', area: 'Area',
			rooms: 'Rooms', price: 'Price', status: 'Status', ground: 'GF', total: 'Total', all: 'All',
			mode: 'Offer', sale: 'Buy', rent: 'Rent', region: 'State', priceMax: 'Max. price', roomsMin: 'Rooms from',
			search: 'Search', reset: 'Reset', results: 'results', floorPlan: 'Floor plan', description: 'Description',
			features: 'Features', contact: 'Contact', priceList: 'See price list',
			costs: 'Running costs / month (gross)', opCosts: 'Operating costs', heatCosts: 'Heating costs', otherCosts: 'Other costs', costsTotal: 'Total',
			st: { available: 'Available', reserved: 'Reserved', sold: 'Sold', rented: 'Rented' },
			ps: { planning: 'Planned', building: 'Under construction', completed: 'Completed' }
		}
	};

	/* ------------------------------------------------------------------ *
	 * CSS (wird im Shadow DOM jedes Widgets eingebettet)
	 * ------------------------------------------------------------------ */
	var CSS = [
		':host{display:block;all:initial}',
		'.ie{--ie-primary:#6750A4;--ie-accent:#7D5260;--ie-text:#1C1B1F;--ie-muted:#605D66;--ie-bg:#fff;--ie-border:#E7E0EC;--ie-radius:14px;--ie-ok:#2E7D32;--ie-warn:#ED6C02;--ie-bad:#D32F2F;',
		'font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ie-text);box-sizing:border-box;display:block;text-align:left}',
		'.ie *,.ie *::before,.ie *::after{box-sizing:border-box}',
		'.ie a{color:inherit;text-decoration:none}.ie img{display:block;max-width:100%}',
		'.ie-status{color:var(--ie-muted);padding:16px;text-align:center;font-size:14px}.ie-error{color:var(--ie-bad)}',
		'.ie-title{font-size:1.3em;font-weight:700;margin:0 0 14px;padding-bottom:8px;border-bottom:2px solid var(--ie-primary)}',
		/* Grid */
		'.ie-grid{display:grid;gap:20px;grid-template-columns:repeat(var(--ie-cols,3),minmax(0,1fr))}',
		'@media(max-width:900px){.ie-grid{grid-template-columns:repeat(min(2,var(--ie-cols,3)),minmax(0,1fr))}}',
		'@media(max-width:600px){.ie-grid{grid-template-columns:1fr}}',
		/* Card */
		'.ie-card{background:var(--ie-bg);border:1px solid var(--ie-border);border-radius:var(--ie-radius);overflow:hidden;display:flex;flex-direction:column;transition:transform .2s,box-shadow .2s;box-shadow:0 1px 3px rgba(0,0,0,.06)}',
		'.ie-card:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(0,0,0,.12)}',
		'.ie-img{position:relative;aspect-ratio:4/3;background:#f1f1f4;overflow:hidden}.ie-img img{width:100%;height:100%;object-fit:cover;transition:transform .35s}.ie-card:hover .ie-img img{transform:scale(1.04)}',
		'.ie-noimg{display:flex;align-items:center;justify-content:center;height:100%;font-size:40px;opacity:.5}',
		'.ie-badge{position:absolute;top:10px;left:10px;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#fff;background:var(--ie-muted)}',
		'.ie-badge.available,.ie-badge.completed{background:var(--ie-ok)}.ie-badge.reserved,.ie-badge.building,.ie-badge.planning{background:var(--ie-warn)}.ie-badge.sold,.ie-badge.rented{background:var(--ie-bad)}',
		'.ie-badge.type{left:auto;right:10px;background:rgba(0,0,0,.55)}',
		'.ie-cf{position:absolute;right:10px;bottom:10px;background:#FFC107;color:#3b2e00;font-size:11px;font-weight:800;padding:4px 9px;border-radius:6px;text-transform:uppercase}',
		'.ie-body{padding:16px;display:flex;flex-direction:column;gap:8px;flex:1}',
		'.ie-h{margin:0;font-size:1.08em;font-weight:700;line-height:1.3}.ie-h a:hover{color:var(--ie-primary)}',
		'.ie-loc{margin:0;color:var(--ie-muted);font-size:.9em}',
		'.ie-price{margin:0;font-size:1.15em;font-weight:800;color:var(--ie-primary)}.ie-price small{font-size:.7em;font-weight:500;color:var(--ie-muted)}',
		'.ie-facts{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:6px 14px;font-size:.88em;color:var(--ie-muted)}',
		'.ie-feats{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:6px}.ie-feats li{font-size:.8em;background:#f3f1f8;border-radius:6px;padding:3px 8px}',
		'.ie-foot{margin-top:auto;padding-top:8px}',
		'.ie-btn{display:inline-block;padding:9px 16px;border-radius:calc(var(--ie-radius) * .6);border:1px solid var(--ie-primary);background:var(--ie-primary);color:#fff;font-weight:600;font-size:.9em;cursor:pointer;font-family:inherit;line-height:1.2}',
		'.ie-btn:hover{filter:brightness(1.08)}.ie-btn.sec{background:transparent;color:var(--ie-primary)}.ie-btn.sm{padding:6px 12px;font-size:.82em}',
		'.ie-more{text-align:center;margin-top:20px}',
		/* Tabelle */
		'.ie-pills{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px}.ie-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;border:1px solid var(--ie-border);font-size:.85em;background:var(--ie-bg);cursor:pointer;font-family:inherit;color:inherit}',
		'.ie-pill.on{border-color:var(--ie-primary);background:var(--ie-primary);color:#fff}.ie-pill b{font-weight:700}',
		'.ie-twrap{overflow-x:auto;border:1px solid var(--ie-border);border-radius:var(--ie-radius);background:var(--ie-bg)}',
		'.ie-table{width:100%;border-collapse:collapse;font-size:.92em;min-width:560px}.ie-table th,.ie-table td{padding:11px 14px;text-align:left;border-bottom:1px solid var(--ie-border);vertical-align:middle}',
		'.ie-table th{font-size:.78em;text-transform:uppercase;letter-spacing:.04em;color:var(--ie-muted);background:#faf9fc}.ie-table tr:last-child td{border-bottom:0}.ie-table tr[hidden]{display:none}',
		'.ie-table td.num{font-weight:700}.ie-table td.price{font-weight:700;white-space:nowrap}',
		'.ie-st{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.78em;font-weight:700;color:#fff;background:var(--ie-muted)}.ie-st.available{background:var(--ie-ok)}.ie-st.reserved{background:var(--ie-warn)}.ie-st.sold,.ie-st.rented{background:var(--ie-bad)}',
		/* Filterleiste */
		'.ie-filters{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));align-items:end;margin:0 0 18px;padding:14px;border:1px solid var(--ie-border);border-radius:var(--ie-radius);background:#faf9fc}',
		'.ie-filters label{display:flex;flex-direction:column;gap:4px;font-size:.78em;font-weight:600;color:var(--ie-muted)}',
		'.ie-filters select,.ie-filters input{font:inherit;font-size:.95em;padding:8px 10px;border:1px solid var(--ie-border);border-radius:8px;background:#fff;color:var(--ie-text);width:100%}',
		'.ie-count{color:var(--ie-muted);font-size:.85em;margin:0 0 10px}',
		/* Detail */
		'.ie-detail{display:grid;gap:24px;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr)}@media(max-width:800px){.ie-detail{grid-template-columns:1fr}}',
		'.ie-detail .ie-img{border-radius:var(--ie-radius);aspect-ratio:16/10}.ie-detail .ie-h{font-size:1.5em}.ie-detail .ie-price{font-size:1.4em}',
		'.ie-section{margin-top:18px}.ie-section h4{margin:0 0 8px;font-size:.95em;text-transform:uppercase;letter-spacing:.04em;color:var(--ie-muted)}',
		'.ie-desc{font-size:.95em;color:var(--ie-text)}.ie-desc p{margin:0 0 .8em}',
		'.ie-gal{display:grid;gap:8px;grid-template-columns:repeat(4,1fr);margin-top:8px}.ie-gal img{aspect-ratio:4/3;object-fit:cover;width:100%;border-radius:8px}',
		'.ie-contact{display:flex;align-items:center;gap:12px;font-size:.9em}.ie-contact img{width:48px;height:48px;border-radius:50%;object-fit:cover}',
		'.ie-stats{display:flex;flex-wrap:wrap;gap:10px;margin:8px 0 0}.ie-stat{background:#faf9fc;border:1px solid var(--ie-border);border-radius:10px;padding:8px 12px;font-size:.85em}.ie-stat b{display:block;font-size:1.2em;color:var(--ie-primary)}'
	].join('');

	/* ------------------------------------------------------------------ *
	 * Helpers
	 * ------------------------------------------------------------------ */
	function esc(s) {
		return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function attr(el, name, fallback) {
		var v = el.getAttribute('data-' + name);
		return (v === null || v === '') ? fallback : v;
	}
	function num(v, fallback) { var n = parseInt(v, 10); return isNaN(n) ? fallback : n; }
	function t(lang, key) {
		var d = I18N[lang] || I18N.de;
		return d[key] !== undefined ? d[key] : (I18N.de[key] !== undefined ? I18N.de[key] : key);
	}
	function safeUrl(u) {
		u = String(u || '');
		return /^(https?:)?\/\//i.test(u) || /^\//.test(u) || /^#/.test(u) ? u : '#';
	}
	function query(params) {
		var parts = [];
		Object.keys(params).forEach(function (k) {
			var v = params[k];
			if (v === undefined || v === null || v === '' || v === false) { return; }
			parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
		});
		return parts.length ? '?' + parts.join('&') : '';
	}
	function getJSON(url) {
		return fetch(url, { credentials: 'omit', headers: { 'Accept': 'application/json' } }).then(function (r) {
			if (!r.ok) { throw new Error('HTTP ' + r.status + ' for ' + url); }
			return r.json();
		});
	}
	var settingsCache = {};
	function loadSettings(api) {
		if (!settingsCache[api]) {
			settingsCache[api] = getJSON(api + '/settings/public').catch(function () { return {}; });
		}
		return settingsCache[api];
	}
	function fmtArea(v) {
		v = parseFloat(v) || 0;
		if (!v) { return ''; }
		return (Math.round(v * 10) / 10).toLocaleString('de-AT', { maximumFractionDigits: v % 1 ? 1 : 0 }) + ' m²';
	}
	function link(ctx, item, isProject) {
		var tpl = isProject ? ctx.projectLinkTemplate : ctx.linkTemplate;
		if (tpl) {
			return tpl.replace('{slug}', encodeURIComponent(item.slug || '')).replace('{id}', String(item.id || ''));
		}
		return item.permalink || '#';
	}
	function targetAttr(ctx) {
		return ctx.target === '_blank' ? ' target="_blank" rel="noopener"' : '';
	}
	/**
	 * EAVG § 3 (AT, seit 1.7.2026): HWB + Endenergiebedarf gehören in jedes Inserat;
	 * fGEE nur noch als Übergangsregel bei Altausweisen.
	 */
	function energyBits(m) {
		var bits = [];
		if (m.energy_hwb) { bits.push('HWB ' + Math.round(m.energy_hwb)); }
		if (m.energy_eeb) { bits.push('EEB ' + Math.round(m.energy_eeb)); }
		else if (m.energy_fgee) { bits.push('fGEE ' + Number(m.energy_fgee).toFixed(2)); }
		return bits.join(' · ');
	}
	/**
	 * Betriebsnebenkosten (brutto, pro Monat) – identisch zur Manager-Detailseite.
	 */
	function costsHtml(w, m) {
		var cur = (m.price_formatted || m.rent_formatted || '').replace(/[0-9.,\s]/g, '') || '€';
		var fmt = function (v) { return Number(v).toLocaleString('de-AT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + cur; };
		var rows = [];
		if (m.operating_costs > 0) { rows.push([w.t('opCosts'), fmt(m.operating_costs)]); }
		if (m.heating_costs > 0)   { rows.push([w.t('heatCosts'), fmt(m.heating_costs)]); }
		if (m.other_costs > 0)     { rows.push([w.t('otherCosts'), fmt(m.other_costs)]); }
		if (!rows.length) { return ''; }
		if (rows.length > 1 && m.ancillary_costs_total > 0) { rows.push(['<strong>' + esc(w.t('costsTotal')) + '</strong>', '<strong>' + esc(fmt(m.ancillary_costs_total)) + '</strong>']); }
		return '<div class="ie-section"><h4>' + esc(w.t('costs')) + '</h4><ul class="ie-facts" style="flex-direction:column;gap:4px">' +
			rows.map(function (r) { return '<li>' + (r[0].indexOf('<strong>') === 0 ? r[0] : esc(r[0])) + ': ' + (r[1].indexOf('<strong>') === 0 ? r[1] : esc(r[1])) + '</li>'; }).join('') + '</ul></div>';
	}
	function imgUrl(img, size) {
		if (!img) { return ''; }
		if (typeof img === 'string') { return img; }
		return img['url_' + size] || img.url_medium || img.url_large || img.url || '';
	}

	/* ------------------------------------------------------------------ *
	 * Widget-Kontext (Element + Shadow DOM + Optionen)
	 * ------------------------------------------------------------------ */
	function Widget(el) {
		this.el   = el;
		this.type = (attr(el, 'immo-embed', '') || '').toLowerCase();
		this.api  = (attr(el, 'api', CONFIG.api) || '').replace(/\/+$/, '');
		this.lang = (attr(el, 'lang', CONFIG.lang) || 'de').slice(0, 2);
		this.target = attr(el, 'target', CONFIG.target);
		this.linkTemplate = attr(el, 'link-template', CONFIG.linkTemplate);
		this.projectLinkTemplate = attr(el, 'project-link-template', CONFIG.projectLinkTemplate);
		this.title = attr(el, 'title', '');

		var fallback = el.innerHTML;
		el.innerHTML = '';
		var host = el;
		var root = (el.attachShadow && !attr(el, 'no-shadow', '')) ? el.attachShadow({ mode: 'open' }) : null;
		if (root) {
			var style = document.createElement('style');
			style.textContent = CSS;
			root.appendChild(style);
		} else {
			if (!document.getElementById('immo-embed-css')) {
				var s = document.createElement('style');
				s.id = 'immo-embed-css';
				s.textContent = CSS.replace(':host{display:block;all:initial}', '');
				document.head.appendChild(s);
			}
		}
		this.root = root || host;
		this.box = document.createElement('div');
		this.box.className = 'ie';
		this.box.setAttribute('data-immo-embed-type', this.type);
		this.root.appendChild(this.box);
		this.fallback = fallback;

		// Theme-Variablen.
		var self = this;
		var primary = attr(el, 'primary', CONFIG.primary);
		var accent  = attr(el, 'accent', CONFIG.accent);
		var radius  = attr(el, 'radius', CONFIG.radius);
		if (radius) { this.box.style.setProperty('--ie-radius', /^\d+$/.test(radius) ? radius + 'px' : radius); }
		if (primary) { this.box.style.setProperty('--ie-primary', primary); }
		if (accent)  { this.box.style.setProperty('--ie-accent', accent); }
		this.theme = (primary && accent) ? Promise.resolve() : loadSettings(this.api).then(function (s) {
			if (!primary && s && s.primary_color) { self.box.style.setProperty('--ie-primary', s.primary_color); }
			if (!accent && s && s.accent_color)   { self.box.style.setProperty('--ie-accent', s.accent_color); }
		});
	}

	Widget.prototype.t = function (key) { return t(this.lang, key); };
	Widget.prototype.status = function (msg, isError) {
		this.box.innerHTML = '<div class="ie-status' + (isError ? ' ie-error' : '') + '">' + esc(msg) + '</div>';
	};
	Widget.prototype.fail = function (err) {
		if (window.console && console.warn) { console.warn('[ImmoEmbed]', err); }
		this.status(this.t('error'), true);
	};
	Widget.prototype.titleHtml = function () {
		return this.title ? '<h3 class="ie-title">' + esc(this.title) + '</h3>' : '';
	};
	Widget.prototype.unitLabel = function (count) {
		return count === 1 ? this.t('unit') : this.t('units');
	};

	/* ------------------------------------------------------------------ *
	 * Templates
	 * ------------------------------------------------------------------ */
	function propertyPrice(w, p) {
		var m = p.meta || {};
		var us = p.unit_stats || {};
		// Property mit zugeordneten Wohneinheiten → "ab X" bzw. "Preis siehe Preisliste";
		// der Property-Gesamtpreis wird dann NIE angezeigt (identisch zum Manager).
		if (m.has_units || parseInt(us.total, 10) > 0 || m.has_priced_units || us.min_price_formatted || us.min_rent_formatted) {
			if (us.min_price_formatted) { return esc(w.t('from') + ' ' + us.min_price_formatted); }
			if (us.min_rent_formatted)  { return esc(w.t('from') + ' ' + us.min_rent_formatted) + ' <small>' + esc(w.t('perMonth')) + '</small>'; }
			return esc(w.t('priceList'));
		}
		if (m.mode === 'rent' && m.rent_formatted) { return esc(m.rent_formatted) + ' <small>' + esc(w.t('perMonth')) + '</small>'; }
		if (m.price_formatted) { return esc(m.price_formatted); }
		if (m.rent_formatted)  { return esc(m.rent_formatted) + ' <small>' + esc(w.t('perMonth')) + '</small>'; }
		return '';
	}

	function propertyCard(w, p) {
		var m = p.meta || {};
		var st = m.status || 'available';
		var href = safeUrl(link(w, p, false));
		var loc = [m.postal_code, m.city].filter(Boolean).join(' ');
		if (m.region_state_label) { loc += (loc ? ', ' : '') + m.region_state_label; }
		var img = imgUrl(p.featured_image, 'medium');
		var cf = m.commission_free && (m.mode === 'sale' || m.mode === 'both');
		var feats = (m.features_detail || []).slice(0, 3);
		var area = parseFloat(m.area) || parseFloat(m.usable_area) || 0;
		var price = propertyPrice(w, p);

		return '<article class="ie-card">' +
			'<a class="ie-img" href="' + esc(href) + '"' + targetAttr(w) + ' tabindex="-1" aria-hidden="true">' +
				(img ? '<img src="' + esc(img) + '" alt="' + esc((p.featured_image && p.featured_image.alt) || p.title) + '" loading="lazy">' : '<div class="ie-noimg">🏠</div>') +
				'<span class="ie-badge ' + esc(st) + '">' + esc(w.t('st')[st] || st) + '</span>' +
				(m.property_type ? '<span class="ie-badge type">' + esc(m.property_type) + '</span>' : '') +
				(cf ? '<span class="ie-cf">' + esc(m.commission_free_label || 'Provisionsfrei') + '</span>' : '') +
			'</a>' +
			'<div class="ie-body">' +
				'<h3 class="ie-h"><a href="' + esc(href) + '"' + targetAttr(w) + '>' + esc(p.title) + '</a></h3>' +
				(loc ? '<p class="ie-loc">📍 ' + esc(loc) + '</p>' : '') +
				(price ? '<p class="ie-price">' + price + '</p>' : '') +
				'<ul class="ie-facts">' +
					(m.rooms ? '<li>🛏️ ' + esc(m.rooms) + ' ' + esc(w.t('rooms')) + '</li>' : '') +
					(area ? '<li>📐 ' + esc(fmtArea(area)) + '</li>' : '') +
					(m.energy_class ? '<li>⚡ ' + esc(m.energy_class) + '</li>' : '') +
					(energyBits(m) ? '<li>📊 ' + esc(energyBits(m)) + '</li>' : '') +
				'</ul>' +
				(feats.length ? '<ul class="ie-feats">' + feats.map(function (f) { return '<li title="' + esc(f.label) + '">' + esc(f.icon || '') + ' ' + esc(f.label) + '</li>'; }).join('') + '</ul>' : '') +
				'<div class="ie-foot"><a class="ie-btn sm" href="' + esc(href) + '"' + targetAttr(w) + '>' + esc(w.t('details')) + '</a></div>' +
			'</div>' +
		'</article>';
	}

	function projectCard(w, p) {
		var m = p.meta || {};
		var s = p.unit_stats || {};
		var st = m.project_status || '';
		var href = safeUrl(link(w, p, true));
		var loc = [m.postal_code, m.city].filter(Boolean).join(' ');
		var img = imgUrl(p.featured_image, 'medium');
		var amin = parseFloat(s.area_min) || 0, amax = parseFloat(s.area_max) || 0;
		var areaLabel = (amin && amax) ? (Math.abs(amax - amin) < 0.5 ? fmtArea(amin) : fmtArea(amin) + ' – ' + fmtArea(amax)) : '';

		return '<article class="ie-card">' +
			'<a class="ie-img" href="' + esc(href) + '"' + targetAttr(w) + ' tabindex="-1" aria-hidden="true">' +
				(img ? '<img src="' + esc(img) + '" alt="' + esc(p.title) + '" loading="lazy">' : '<div class="ie-noimg">🏗️</div>') +
				(st ? '<span class="ie-badge ' + esc(st) + '">' + esc(w.t('ps')[st] || st) + '</span>' : '') +
			'</a>' +
			'<div class="ie-body">' +
				'<h3 class="ie-h"><a href="' + esc(href) + '"' + targetAttr(w) + '>' + esc(p.title) + '</a></h3>' +
				(loc ? '<p class="ie-loc">📍 ' + esc(loc) + '</p>' : '') +
				(s.min_price_formatted ? '<p class="ie-price">' + esc(w.t('from')) + ' ' + esc(s.min_price_formatted) + '</p>' : '') +
				(s.total ? '<ul class="ie-facts">' +
					'<li>🏘️ ' + esc(s.total) + ' ' + esc(w.unitLabel(s.total)) + '</li>' +
					(areaLabel ? '<li>📐 ' + esc(areaLabel) + '</li>' : '') +
					(s.available ? '<li>✅ ' + esc(s.available) + ' ' + esc(w.t('available')) + '</li>' : '') +
				'</ul>' : '') +
				'<div class="ie-foot"><a class="ie-btn sm" href="' + esc(href) + '"' + targetAttr(w) + '>' + esc(w.t('project')) + '</a></div>' +
			'</div>' +
		'</article>';
	}

	function unitsTable(w, units, stats, opts) {
		opts = opts || {};
		var html = '';
		if (opts.showStats !== false && stats && stats.total) {
			html += '<div class="ie-pills" role="tablist">' +
				'<button type="button" class="ie-pill on" data-f="all">' + esc(w.t('all')) + ' <b>' + esc(stats.total) + '</b></button>';
			['available', 'reserved', 'sold', 'rented'].forEach(function (k) {
				if (stats[k]) { html += '<button type="button" class="ie-pill" data-f="' + k + '">' + esc(w.t('st')[k]) + ' <b>' + esc(stats[k]) + '</b></button>'; }
			});
			html += '</div>';
		}
		html += '<div class="ie-twrap"><table class="ie-table"><thead><tr>' +
			'<th>' + esc(w.t('nr')) + '</th><th>' + esc(w.t('floor')) + '</th><th>' + esc(w.t('area')) + '</th><th>' + esc(w.t('rooms')) + '</th><th>' + esc(w.t('price')) + '</th><th>' + esc(w.t('status')) + '</th><th></th>' +
			'</tr></thead><tbody>';
		units.forEach(function (u) {
			var price = '';
			if (parseFloat(u.price) > 0) { price = esc(u.price_formatted); }
			else if (parseFloat(u.rent) > 0) { price = esc(u.rent_formatted) + ' <small>' + esc(w.t('perMonth')) + '</small>'; }
			var floor = parseInt(u.floor, 10) || 0;
			var prop = u.property || null;
			var action = '';
			if (prop && (prop.permalink || w.linkTemplate)) {
				action = '<a class="ie-btn sec sm" href="' + esc(safeUrl(link(w, prop, false))) + '"' + targetAttr(w) + '>' + esc(w.t('details')) + '</a>';
			} else if (u.floor_plan && u.floor_plan.url) {
				action = '<a class="ie-btn sec sm" href="' + esc(safeUrl(u.floor_plan.url)) + '" target="_blank" rel="noopener">' + esc(w.t('floorPlan')) + '</a>';
			}
			html += '<tr data-status="' + esc(u.status) + '">' +
				'<td class="num">' + esc(u.unit_number) + '</td>' +
				'<td>' + (floor === 0 ? esc(w.t('ground')) : esc(floor) + '.') + '</td>' +
				'<td>' + (parseFloat(u.area) > 0 ? esc(fmtArea(u.area)) : '—') + '</td>' +
				'<td>' + (parseInt(u.rooms, 10) > 0 ? esc(u.rooms) : '—') + '</td>' +
				'<td class="price">' + (price || '—') + (prop && prop.commission_free && parseFloat(u.price) > 0 ? ' <span class="ie-cf" style="position:static;display:inline-block;margin-left:6px">' + esc(prop.commission_free_label || 'Provisionsfrei') + '</span>' : '') + '</td>' +
				'<td><span class="ie-st ' + esc(u.status) + '">' + esc(u.status_label || w.t('st')[u.status] || u.status) + '</span></td>' +
				'<td>' + action + '</td>' +
			'</tr>';
		});
		html += '</tbody></table></div>';
		return html;
	}

	function bindUnitFilter(w, container) {
		var pills = container.querySelectorAll('.ie-pill');
		var rows  = container.querySelectorAll('tbody tr');
		Array.prototype.forEach.call(pills, function (pill) {
			pill.addEventListener('click', function () {
				var f = pill.getAttribute('data-f');
				Array.prototype.forEach.call(pills, function (p) { p.classList.toggle('on', p === pill); });
				Array.prototype.forEach.call(rows, function (r) { r.hidden = !(f === 'all' || r.getAttribute('data-status') === f); });
			});
		});
	}

	/* ------------------------------------------------------------------ *
	 * Widget: properties
	 * ------------------------------------------------------------------ */
	function renderProperties(w) {
		var el = w.el;
		var perPage = Math.max(1, Math.min(50, num(attr(el, 'limit', attr(el, 'per-page', 12)), 12)));
		var cols = Math.max(1, Math.min(4, num(attr(el, 'columns', 3), 3)));
		var withFilters = attr(el, 'filters', '') === '1' || attr(el, 'filters', '') === 'true';
		var paginate = attr(el, 'pagination', '1') !== '0';
		var base = {
			status: attr(el, 'status', 'available'),
			mode: attr(el, 'mode', ''),
			type: attr(el, 'type', ''),
			region_state: attr(el, 'region', attr(el, 'region-state', '')),
			region_district: attr(el, 'district', ''),
			orderby: attr(el, 'orderby', 'newest'),
			project_id: attr(el, 'project', ''),
			price_min: attr(el, 'price-min', ''),
			price_max: attr(el, 'price-max', ''),
			rooms: attr(el, 'rooms', ''),
			per_page: perPage
		};
		var state = { page: 1, filters: {} };

		w.box.innerHTML = w.titleHtml() +
			(withFilters ? '<form class="ie-filters"></form><p class="ie-count"></p>' : '') +
			'<div class="ie-grid" style="--ie-cols:' + cols + '"></div><div class="ie-more"></div>';
		var grid = w.box.querySelector('.ie-grid');
		var more = w.box.querySelector('.ie-more');
		var form = w.box.querySelector('.ie-filters');
		var count = w.box.querySelector('.ie-count');

		function params() {
			var p = {};
			Object.keys(base).forEach(function (k) { p[k] = base[k]; });
			Object.keys(state.filters).forEach(function (k) { p[k] = state.filters[k]; });
			p.page = state.page;
			return p;
		}

		function load(append) {
			if (!append) { grid.innerHTML = '<div class="ie-status">' + esc(w.t('loading')) + '</div>'; }
			more.innerHTML = '';
			return getJSON(w.api + '/properties' + query(params())).then(function (data) {
				var items = data.properties || [];
				var pg = data.pagination || {};
				if (!append) { grid.innerHTML = ''; }
				if (!items.length && !append) {
					grid.innerHTML = '<div class="ie-status">' + esc(w.t('empty')) + '</div>';
				} else {
					grid.insertAdjacentHTML('beforeend', items.map(function (p) { return propertyCard(w, p); }).join(''));
				}
				if (count) { count.textContent = (pg.total || 0) + ' ' + w.t('results'); }
				if (paginate && pg.pages > state.page) {
					more.innerHTML = '<button type="button" class="ie-btn sec">' + esc(w.t('more')) + '</button>';
					more.querySelector('button').addEventListener('click', function () {
						state.page += 1;
						load(true);
					});
				}
			}).catch(function (e) { w.fail(e); });
		}

		if (form) {
			buildFilterForm(w, form, function (filters) {
				state.filters = filters;
				state.page = 1;
				load(false);
			});
		}
		return load(false);
	}

	function buildFilterForm(w, form, onChange) {
		form.innerHTML =
			'<label>' + esc(w.t('mode')) + '<select name="mode"><option value="">' + esc(w.t('all')) + '</option><option value="sale">' + esc(w.t('sale')) + '</option><option value="rent">' + esc(w.t('rent')) + '</option></select></label>' +
			'<label>' + esc(w.t('region')) + '<select name="region_state"><option value="">' + esc(w.t('all')) + '</option></select></label>' +
			'<label>' + esc(w.t('roomsMin')) + '<select name="rooms_min"><option value=""></option><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option></select></label>' +
			'<label>' + esc(w.t('priceMax')) + '<input type="number" name="price_max" min="0" step="10000" placeholder="€"></label>' +
			'<button type="button" class="ie-btn sec sm ie-reset">' + esc(w.t('reset')) + '</button>';

		var regionSel = form.querySelector('[name=region_state]');
		getJSON(w.api + '/regions').then(function (d) {
			var states = d.states || d || {};
			var list = Array.isArray(states) ? states : Object.keys(states).map(function (k) {
				var v = states[k];
				return { key: (v && v.key) || k, label: (v && (v.label || v.name)) || (typeof v === 'string' ? v : k) };
			});
			list.forEach(function (s) {
				var o = document.createElement('option');
				o.value = s.key || s.slug || s.id || '';
				o.textContent = s.label || s.name || o.value;
				regionSel.appendChild(o);
			});
		}).catch(function () {});

		var timer = null;
		function emit() {
			var f = {};
			var mode = form.querySelector('[name=mode]').value;
			var region = regionSel.value;
			var roomsMin = parseInt(form.querySelector('[name=rooms_min]').value, 10);
			var priceMax = form.querySelector('[name=price_max]').value;
			if (mode) { f.mode = mode; }
			if (region) { f.region_state = region; }
			if (roomsMin) { f.rooms = [roomsMin, roomsMin + 1, roomsMin + 2, roomsMin + 3, roomsMin + 4, roomsMin + 5].join(','); }
			if (priceMax) { f.price_max = priceMax; }
			onChange(f);
		}
		form.addEventListener('change', emit);
		form.addEventListener('input', function (e) {
			if (e.target && e.target.type === 'number') { clearTimeout(timer); timer = setTimeout(emit, 500); }
		});
		form.querySelector('.ie-reset').addEventListener('click', function () { form.reset(); emit(); });
	}

	/* ------------------------------------------------------------------ *
	 * Widget: projects
	 * ------------------------------------------------------------------ */
	function renderProjects(w) {
		var el = w.el;
		var perPage = Math.max(1, Math.min(50, num(attr(el, 'limit', 12), 12)));
		var cols = Math.max(1, Math.min(4, num(attr(el, 'columns', 3), 3)));
		w.box.innerHTML = w.titleHtml() + '<div class="ie-grid" style="--ie-cols:' + cols + '"><div class="ie-status">' + esc(w.t('loading')) + '</div></div>';
		var grid = w.box.querySelector('.ie-grid');
		return getJSON(w.api + '/projects' + query({ per_page: perPage, status: attr(el, 'status', '') })).then(function (data) {
			var items = data.projects || [];
			grid.innerHTML = items.length ? items.map(function (p) { return projectCard(w, p); }).join('') : '<div class="ie-status">' + esc(w.t('empty')) + '</div>';
		}).catch(function (e) { w.fail(e); });
	}

	/* ------------------------------------------------------------------ *
	 * Widget: units
	 * ------------------------------------------------------------------ */
	function unitsEndpoint(w, ref) {
		ref = String(ref || '').trim();
		if (!ref) { return null; }
		return /^\d+$/.test(ref) ? w.api + '/projects/' + ref + '/units' : w.api + '/projects/by-slug/' + encodeURIComponent(ref) + '/units';
	}
	function renderUnits(w) {
		var el = w.el;
		var endpoint = unitsEndpoint(w, attr(el, 'project', attr(el, 'id', attr(el, 'slug', ''))));
		if (!endpoint) { return Promise.resolve(w.fail('data-project (ID oder Slug) fehlt')); }
		w.status(w.t('loading'));
		var q = query({ status: attr(el, 'status', ''), orderby: attr(el, 'orderby', ''), limit: attr(el, 'limit', '') });
		return getJSON(endpoint + q).then(function (data) {
			var units = data.units || [];
			w.box.innerHTML = w.titleHtml() + (units.length ? unitsTable(w, units, data.stats, { showStats: attr(el, 'stats', '1') !== '0' }) : '<div class="ie-status">' + esc(w.t('empty')) + '</div>');
			bindUnitFilter(w, w.box);
		}).catch(function (e) { w.fail(e); });
	}

	/* ------------------------------------------------------------------ *
	 * Widget: property (Detail-Karte)
	 * ------------------------------------------------------------------ */
	function singleEndpoint(w, base, el) {
		var id = attr(el, 'id', ''), slug = attr(el, 'slug', '');
		if (id && /^\d+$/.test(id)) { return w.api + '/' + base + '/' + id; }
		if (slug) { return w.api + '/' + base + '/by-slug/' + encodeURIComponent(slug); }
		if (id) { return w.api + '/' + base + '/by-slug/' + encodeURIComponent(id); }
		return null;
	}
	function renderProperty(w) {
		var endpoint = singleEndpoint(w, 'properties', w.el);
		if (!endpoint) { return Promise.resolve(w.fail('data-id oder data-slug fehlt')); }
		w.status(w.t('loading'));
		return getJSON(endpoint).then(function (p) {
			var m = p.meta || {};
			var href = safeUrl(link(w, p, false));
			var loc = [m.address, [m.postal_code, m.city].filter(Boolean).join(' '), m.region_state_label].filter(Boolean).join(', ');
			var img = imgUrl(p.featured_image, 'large');
			var gal = (p.gallery || []).slice(0, 4);
			var price = propertyPrice(w, p);
			var feats = m.features_detail || [];
			var showDesc = attr(w.el, 'description', '1') !== '0';
			var cf = m.commission_free && (m.mode === 'sale' || m.mode === 'both');

			w.box.innerHTML = w.titleHtml() + '<div class="ie-detail">' +
				'<div>' +
					'<a class="ie-img" href="' + esc(href) + '"' + targetAttr(w) + '>' +
						(img ? '<img src="' + esc(img) + '" alt="' + esc(p.title) + '">' : '<div class="ie-noimg">🏠</div>') +
						'<span class="ie-badge ' + esc(m.status || '') + '">' + esc(w.t('st')[m.status] || m.status || '') + '</span>' +
						(cf ? '<span class="ie-cf">' + esc(m.commission_free_label || 'Provisionsfrei') + '</span>' : '') +
					'</a>' +
					(gal.length ? '<div class="ie-gal">' + gal.map(function (g) { return '<img src="' + esc(imgUrl(g, 'thumbnail')) + '" alt="" loading="lazy">'; }).join('') + '</div>' : '') +
				'</div>' +
				'<div>' +
					'<h3 class="ie-h"><a href="' + esc(href) + '"' + targetAttr(w) + '>' + esc(p.title) + '</a></h3>' +
					(loc ? '<p class="ie-loc">📍 ' + esc(loc) + '</p>' : '') +
					(price ? '<p class="ie-price">' + price + '</p>' : '') +
					'<ul class="ie-facts">' +
						(m.property_type ? '<li>🏠 ' + esc(m.property_type) + '</li>' : '') +
						(m.rooms ? '<li>🛏️ ' + esc(m.rooms) + ' ' + esc(w.t('rooms')) + '</li>' : '') +
						(m.area ? '<li>📐 ' + esc(fmtArea(m.area)) + '</li>' : '') +
						(m.floor ? '<li>🏢 ' + esc(m.floor) + '. ' + esc(w.t('floor')) + '</li>' : '') +
						(m.built_year ? '<li>📅 ' + esc(m.built_year) + '</li>' : '') +
						(m.energy_class ? '<li>⚡ ' + esc(m.energy_class) + '</li>' : '') +
						(m.energy_hwb ? '<li>📊 HWB ' + esc(m.energy_hwb) + ' kWh/m²a</li>' : '') +
						(m.energy_eeb ? '<li>🔋 EEB ' + esc(m.energy_eeb) + ' kWh/m²a</li>' : (m.energy_fgee ? '<li>📈 fGEE ' + esc(m.energy_fgee) + '</li>' : '')) +
						(m.heating ? '<li>🔥 ' + esc(m.heating) + '</li>' : '') +
					'</ul>' +
					costsHtml(w, m) +
					(feats.length ? '<div class="ie-section"><h4>' + esc(w.t('features')) + '</h4><ul class="ie-feats">' + feats.map(function (f) { return '<li>' + esc(f.icon || '') + ' ' + esc(f.label) + '</li>'; }).join('') + '</ul></div>' : '') +
					(showDesc && (p.description || p.excerpt) ? '<div class="ie-section"><h4>' + esc(w.t('description')) + '</h4><div class="ie-desc">' + (p.description ? sanitizeHtml(p.description) : '<p>' + esc(p.excerpt) + '</p>') + '</div></div>' : '') +
					(m.contact_name ? '<div class="ie-section"><h4>' + esc(w.t('contact')) + '</h4><div class="ie-contact">' + (m.contact_image ? '<img src="' + esc(imgUrl(m.contact_image, 'thumbnail')) + '" alt="">' : '') + '<div><strong>' + esc(m.contact_name) + '</strong>' + (m.contact_phone ? '<br><a href="tel:' + esc(m.contact_phone) + '">📞 ' + esc(m.contact_phone) + '</a>' : '') + '</div></div></div>' : '') +
					'<div class="ie-section"><a class="ie-btn" href="' + esc(href) + '"' + targetAttr(w) + '>' + esc(w.t('details')) + '</a></div>' +
				'</div>' +
			'</div>';
		}).catch(function (e) { w.fail(e); });
	}

	/**
	 * Sehr konservative HTML-Bereinigung für die Beschreibung (nur Textformatierung).
	 */
	function sanitizeHtml(html) {
		var tmp = document.createElement('div');
		tmp.innerHTML = String(html || '');
		var allowed = { P: 1, BR: 1, STRONG: 1, B: 1, EM: 1, I: 1, UL: 1, OL: 1, LI: 1, H3: 1, H4: 1, H5: 1 };
		(function walk(node) {
			var children = Array.prototype.slice.call(node.childNodes);
			children.forEach(function (c) {
				if (c.nodeType === 1) {
					if (!allowed[c.nodeName]) {
						var frag = document.createDocumentFragment();
						while (c.firstChild) { frag.appendChild(c.firstChild); }
						node.replaceChild(frag, c);
						walk(node);
						return;
					}
					while (c.attributes.length) { c.removeAttribute(c.attributes[0].name); }
					walk(c);
				} else if (c.nodeType !== 3) {
					node.removeChild(c);
				}
			});
		})(tmp);
		return tmp.innerHTML;
	}

	/* ------------------------------------------------------------------ *
	 * Widget: project (Projekt-Kopf + Wohneinheiten)
	 * ------------------------------------------------------------------ */
	function renderProject(w) {
		var endpoint = singleEndpoint(w, 'projects', w.el);
		if (!endpoint) { return Promise.resolve(w.fail('data-id oder data-slug fehlt')); }
		w.status(w.t('loading'));
		return getJSON(endpoint).then(function (p) {
			var m = p.meta || {};
			var s = p.unit_stats || {};
			var href = safeUrl(link(w, p, true));
			var img = imgUrl(p.featured_image, 'large');
			var loc = [m.address, [m.postal_code, m.city].filter(Boolean).join(' '), m.region_state_label].filter(Boolean).join(', ');
			var showDesc = attr(w.el, 'description', '1') !== '0';
			w.box.innerHTML = w.titleHtml() + '<div class="ie-detail">' +
				'<div><a class="ie-img" href="' + esc(href) + '"' + targetAttr(w) + '>' +
					(img ? '<img src="' + esc(img) + '" alt="' + esc(p.title) + '">' : '<div class="ie-noimg">🏗️</div>') +
					(m.project_status ? '<span class="ie-badge ' + esc(m.project_status) + '">' + esc(w.t('ps')[m.project_status] || m.project_status) + '</span>' : '') +
				'</a></div>' +
				'<div>' +
					'<h3 class="ie-h"><a href="' + esc(href) + '"' + targetAttr(w) + '>' + esc(p.title) + '</a></h3>' +
					(loc ? '<p class="ie-loc">📍 ' + esc(loc) + '</p>' : '') +
					(s.total ? '<div class="ie-stats">' +
						'<div class="ie-stat"><b>' + esc(s.total) + '</b>' + esc(w.unitLabel(s.total)) + '</div>' +
						'<div class="ie-stat"><b>' + esc(s.available || 0) + '</b>' + esc(w.t('available')) + '</div>' +
						(s.area_min ? '<div class="ie-stat"><b>' + esc(fmtArea(s.area_min)) + (s.area_max && s.area_max !== s.area_min ? ' – ' + esc(fmtArea(s.area_max)) : '') + '</b>' + esc(w.t('area')) + '</div>' : '') +
						(s.min_price_formatted ? '<div class="ie-stat"><b>' + esc(w.t('from')) + ' ' + esc(s.min_price_formatted) + '</b>' + esc(w.t('price')) + '</div>' : '') +
					'</div>' : '') +
					(showDesc && (p.description || p.excerpt) ? '<div class="ie-section"><h4>' + esc(w.t('description')) + '</h4><div class="ie-desc">' + (p.description ? sanitizeHtml(p.description) : '<p>' + esc(p.excerpt) + '</p>') + '</div></div>' : '') +
					'<div class="ie-section"><a class="ie-btn" href="' + esc(href) + '"' + targetAttr(w) + '>' + esc(w.t('project')) + '</a></div>' +
				'</div>' +
			'</div><div class="ie-section ie-units"><div class="ie-status">' + esc(w.t('loading')) + '</div></div>';

			var unitsBox = w.box.querySelector('.ie-units');
			return getJSON(w.api + '/projects/' + p.id + '/units' + query({ status: attr(w.el, 'status', ''), orderby: attr(w.el, 'orderby', '') })).then(function (d) {
				var units = d.units || [];
				unitsBox.innerHTML = units.length ? '<h4>' + esc(w.t('units')) + '</h4>' + unitsTable(w, units, d.stats) : '';
				bindUnitFilter(w, unitsBox);
			});
		}).catch(function (e) { w.fail(e); });
	}

	/* ------------------------------------------------------------------ *
	 * Bootstrap
	 * ------------------------------------------------------------------ */
	var RENDERERS = {
		properties: renderProperties,
		list: renderProperties,
		latest: renderProperties,
		projects: renderProjects,
		units: renderUnits,
		property: renderProperty,
		project: renderProject
	};

	function render(el) {
		if (!el || el.__immoEmbed) { return el && el.__immoEmbed; }
		var w = new Widget(el);
		el.__immoEmbed = w;
		if (!w.api) {
			w.fail('API-URL konnte nicht ermittelt werden – bitte data-api am <script> setzen.');
			return w;
		}
		var fn = RENDERERS[w.type];
		if (!fn) {
			w.fail('Unbekannter Widget-Typ: ' + w.type);
			return w;
		}
		w.theme.then(function () { return fn(w); }).catch(function (e) { w.fail(e); });
		return w;
	}

	function init(root) {
		var nodes = (root || document).querySelectorAll('[data-immo-embed]');
		Array.prototype.forEach.call(nodes, render);
		return nodes.length;
	}

	window.ImmoEmbed = {
		__loaded: true,
		version: '1.4.0',
		config: CONFIG,
		init: init,
		render: render,
		i18n: I18N
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { init(); });
	} else {
		init();
	}
})(window, document);
