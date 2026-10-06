/**
 * Headless-Test für public/embed/immo-embed.js mit jsdom gegen eine laufende
 * WordPress-Instanz (Default: http://localhost:8089, siehe bin/smoke-test.sh KEEP=1).
 *
 * node embed-test.js <plugin-root> [api-base]
 */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const root = process.argv[2];
const api = (process.argv[3] || 'http://localhost:8089/wp-json/immo-manager/v1').replace(/\/+$/, '');
const src = fs.readFileSync(path.join(root, 'public/embed/immo-embed.js'), 'utf8');

let pass = 0, fail = 0;
const ok = (m) => { pass++; console.log('  [OK]   ' + m); };
const bad = (m) => { fail++; console.log('  [FAIL] ' + m); };
const check = (cond, m) => (cond ? ok(m) : bad(m));

async function main() {
	// Projekt-Daten für die Tests holen.
	const projects = await (await fetch(api + '/projects?per_page=1')).json();
	const project = projects.projects[0];
	const props = await (await fetch(api + '/properties?per_page=1')).json();
	const prop = props.properties[0];
	console.log('  Test-Projekt:', project.id, project.slug, '| Test-Property:', prop.id, prop.slug);

	const html = `<!doctype html><html lang="de"><head><style>body{color:red}</style></head><body>
		<h1>Fremde Host-Seite</h1>
		<div id="w1" data-immo-embed="projects" data-columns="2" data-title="Unsere Projekte"><a href="#">Fallback</a></div>
		<div id="w2" data-immo-embed="units" data-project="${project.slug}"></div>
		<div id="w3" data-immo-embed="properties" data-limit="3" data-filters="1" data-link-template="https://kunde.example/immobilie/{slug}"></div>
		<div id="w4" data-immo-embed="property" data-slug="${prop.slug}" data-target="_blank"></div>
		<div id="w5" data-immo-embed="project" data-id="${project.id}"></div>
		<div id="w6" data-immo-embed="units"></div>
		<div id="w7" data-immo-embed="units" data-project="${project.id}" data-no-shadow="1" data-primary="#ff0000"></div>
	</body></html>`;

	const dom = new JSDOM(html, { url: 'https://kunde.example/seite/', runScripts: 'outside-only', pretendToBeVisual: true });
	const { window } = dom;
	window.fetch = (url, opts) => fetch(url, opts);  // Node-fetch (kein CORS im Test, CORS wird separat via curl geprüft)
	// Script wie per <script data-api> eingebunden.
	const s = window.document.createElement('script');
	s.setAttribute('data-api', api);
	s.src = 'https://wp.example/wp-content/plugins/immo-manager/public/embed/immo-embed.js';
	window.document.body.appendChild(s);
	window.document.currentScript = s;
	window.eval(src);

	check(window.ImmoEmbed && window.ImmoEmbed.__loaded, 'ImmoEmbed geladen');
	check(window.ImmoEmbed.config.api === api, 'API aus data-api übernommen');

	// Ableitung der API-URL aus der Script-URL (ohne data-api) testen.
	{
		const d2 = new JSDOM('<!doctype html><html><body></body></html>', { url: 'https://kunde.example/', runScripts: 'outside-only' });
		const s2 = d2.window.document.createElement('script');
		s2.src = 'https://wp.example/blog/wp-content/plugins/immo-manager/public/embed/immo-embed.js';
		d2.window.document.body.appendChild(s2);
		d2.window.document.currentScript = s2;
		d2.window.fetch = () => Promise.reject(new Error('no network'));
		d2.window.eval(src);
		check(d2.window.ImmoEmbed.config.api === 'https://wp.example/blog/wp-json/immo-manager/v1', 'API-URL aus Script-URL abgeleitet (inkl. Unterverzeichnis): ' + d2.window.ImmoEmbed.config.api);
	}

	// Rendering abwarten (Netzwerk).
	await new Promise((r) => setTimeout(r, 4000));

	const q = (id) => {
		const el = window.document.getElementById(id);
		return el.shadowRoot || el;
	};
	const txt = (id) => q(id).textContent;

	// w1 projects
	check(q('w1').querySelector('style') !== null, 'Shadow DOM mit eingebettetem Style (w1)');
	check(q('w1').querySelectorAll('.ie-card').length >= 1, 'projects: Cards gerendert (' + q('w1').querySelectorAll('.ie-card').length + ')');
	check(/Unsere Projekte/.test(txt('w1')), 'projects: data-title gerendert');
	check(!/Fallback/.test(txt('w1')), 'projects: Fallback-Inhalt ersetzt');
	check(/Einheit/.test(txt('w1')), 'projects: Unit-Stats sichtbar');
	check(q('w1').querySelector('.ie-grid').style.getPropertyValue('--ie-cols') === '2', 'projects: data-columns=2 übernommen');
	// w2 units by slug
	check(q('w2').querySelectorAll('.ie-table tbody tr').length >= 1, 'units(slug): Tabellenzeilen (' + q('w2').querySelectorAll('.ie-table tbody tr').length + ')');
	check(q('w2').querySelectorAll('.ie-pill').length >= 2, 'units: Status-Pills');
	{
		const pills = q('w2').querySelectorAll('.ie-pill');
		const soldPill = Array.from(pills).find((p) => p.getAttribute('data-f') === 'sold');
		if (soldPill) {
			soldPill.click();
			const rows = q('w2').querySelectorAll('.ie-table tbody tr');
			const visible = Array.from(rows).filter((r) => !r.hidden);
			check(visible.length >= 1 && visible.every((r) => r.getAttribute('data-status') === 'sold'), 'units: Pill-Filter blendet andere Status aus');
		} else { ok('units: (kein sold-Pill, Filtertest übersprungen)'); }
	}
	// w3 properties + filters + link template
	check(q('w3').querySelectorAll('.ie-card').length >= 1, 'properties: Cards (' + q('w3').querySelectorAll('.ie-card').length + ')');
	check(q('w3').querySelector('form.ie-filters select[name=mode]') !== null, 'properties: Filterleiste gerendert');
	check(q('w3').querySelectorAll('select[name=region_state] option').length > 3, 'properties: Bundesländer aus /regions geladen');
	const firstLink = q('w3').querySelector('.ie-card .ie-h a');
	check(firstLink && /^https:\/\/kunde\.example\/immobilie\//.test(firstLink.getAttribute('href')), 'properties: data-link-template greift: ' + (firstLink && firstLink.getAttribute('href')));
	check(/Ergebnisse/.test(txt('w3')), 'properties: Ergebnis-Zähler');
	{
		// Filter: mode=rent anwenden und neu laden
		const sel = q('w3').querySelector('select[name=mode]');
		sel.value = 'rent';
		sel.dispatchEvent(new window.Event('change', { bubbles: true }));
		await new Promise((r) => setTimeout(r, 2500));
		const cards = q('w3').querySelectorAll('.ie-card');
		const allRentOrEmpty = cards.length === 0 || Array.from(cards).every((c) => /Monat|Preisliste|ab /.test(c.textContent) || !/€/.test(c.querySelector('.ie-price') ? c.querySelector('.ie-price').textContent : ''));
		check(allRentOrEmpty, 'properties: Filter mode=rent neu geladen (' + cards.length + ' Cards)');
	}
	// w4 property detail
	check(q('w4').querySelector('.ie-detail') !== null, 'property: Detail-Layout');
	check(new RegExp(prop.title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).test(txt('w4')), 'property: Titel gerendert');
	check(q('w4').querySelectorAll('a[target=_blank][rel=noopener]').length >= 1, 'property: data-target=_blank + rel=noopener');
	check(!/<script/i.test(q('w4').innerHTML), 'property: kein <script> im Output');
	// w5 project detail + units
	check(q('w5').querySelector('.ie-detail') !== null && q('w5').querySelectorAll('.ie-units .ie-table tbody tr').length >= 1, 'project: Kopf + Wohneinheiten-Tabelle');
	// w6 fehlende Pflichtangabe
	check(/konnten nicht geladen/.test(txt('w6')), 'units ohne data-project: verständliche Fehlermeldung');
	// w7 light DOM + theme override
	const w7 = window.document.getElementById('w7');
	check(!w7.shadowRoot && w7.querySelector('.ie-table') !== null, 'units: data-no-shadow rendert im Light DOM');
	check(window.document.getElementById('immo-embed-css') !== null, 'Light DOM: CSS einmalig in <head> injiziert');
	check(w7.querySelector('.ie').style.getPropertyValue('--ie-primary') === '#ff0000', 'data-primary überschreibt Theme-Farbe');
	// Theme aus settings/public bei w1
	check(q('w1').querySelector('.ie').style.getPropertyValue('--ie-primary') !== '', 'Theme-Farbe aus /settings/public übernommen: ' + q('w1').querySelector('.ie').style.getPropertyValue('--ie-primary'));

	// XSS-Probe: Escaping
	{
		const d3 = new JSDOM('<!doctype html><html><body><div id="x" data-immo-embed="projects" data-api="http://x.invalid"></div></body></html>', { url: 'https://kunde.example/', runScripts: 'outside-only' });
		d3.window.fetch = (url) => Promise.resolve({ ok: true, json: () => Promise.resolve(
			/settings/.test(url) ? {} : { projects: [{ id: 1, title: '<img src=x onerror=alert(1)>Böse', slug: 'x', permalink: 'javascript:alert(1)', meta: { project_status: 'building', city: '<b>Wien</b>' }, unit_stats: { total: 2, available: 1 }, featured_image: null }] }
		) });
		d3.window.eval(src);
		await new Promise((r) => setTimeout(r, 300));
		const r = d3.window.document.getElementById('x').shadowRoot;
		check(r.querySelector('img[onerror]') === null && /Böse/.test(r.textContent) && /<b>Wien<\/b>/.test(r.textContent), 'XSS: Titel/Ort werden escaped');
		check(Array.from(r.querySelectorAll('a')).every((a) => a.getAttribute('href') === '#'), 'XSS: javascript:-Links neutralisiert');
	}

	// Preisregel bei zugeordneten Wohneinheiten (Mock): nie Property-Gesamtpreis.
	{
		const d4 = new JSDOM('<!doctype html><html><body><div id="p" data-immo-embed="properties" data-api="http://x.invalid"></div></body></html>', { url: 'https://kunde.example/', runScripts: 'outside-only' });
		const mk = (id, slug, extra) => Object.assign({ id, title: 'Objekt ' + id, slug, permalink: 'https://wp.example/' + slug, featured_image: null, meta: { mode: 'sale', status: 'available', price: 526534, price_formatted: null, rent: 0, rent_formatted: null } }, extra);
		d4.window.fetch = (url) => Promise.resolve({ ok: true, json: () => Promise.resolve(
			/settings/.test(url) ? {} : /regions/.test(url) ? { states: [] } : { properties: [
				mk(1, 'mit-units-ohne-preis', { unit_stats: { total: 6, available: 6, min_price: 0, min_price_formatted: null } }),
				mk(2, 'mit-units-mit-preis', { unit_stats: { total: 3, available: 2, min_price: 250000, min_price_formatted: '250.000 €' } }),
				mk(3, 'ohne-units', { meta: { mode: 'sale', status: 'available', price: 526534, price_formatted: '526.534 €', rent: 0 }, unit_stats: { total: 0 } }),
			], pagination: { total: 3, pages: 1 } }
		) });
		d4.window.eval(src);
		await new Promise((r) => setTimeout(r, 400));
		const cards = Array.from(d4.window.document.getElementById('p').shadowRoot.querySelectorAll('.ie-card'));
		const txt = (i) => (cards[i].querySelector('.ie-price') || {}).textContent || '';
		check(cards.length === 3, 'Preisregel: 3 Mock-Cards gerendert');
		check(/Preisliste/.test(txt(0)) && !/526/.test(txt(0)), 'Preisregel: Units ohne Preis -> "Preis siehe Preisliste", kein Gesamtpreis');
		check(/ab 250\.000/.test(txt(1)) && !/526/.test(txt(1)), 'Preisregel: Units mit Preis -> "ab 250.000 €"');
		check(/526\.534/.test(txt(2)), 'Preisregel: ohne Units -> Property-Preis');
	}

	console.log(`\nEMBED RESULT: ${pass} passed, ${fail} failed`);
	process.exit(fail ? 1 : 0);
}

main().catch((e) => { console.error(e); process.exit(2); });
