# Property-IDs-Liste + Slider + Inquiry-Diagnose – Design

**Datum:** 2026-05-06
**Branch:** `feat/property-ids-list` (in beiden Plugins, Manager + Client)
**Status:** umgesetzt – Doku follows nach Verifikation auf der Test-Seite

---

## Ausgangslage

Zwei voneinander unabhängige Themen, die parallel gelöst werden:

1. **Bug:** Anfragen, die ein User auf einer externen Site (mit ImmoClient eingebunden) abgeschickt hat, tauchen nicht in der zentralen Manager-Anfragenliste auf. Mail kommt aber an. Im bestehenden Code in `class-immo-ajax.php::submit_inquiry()` wird der API-Fehler beim Forward an den Manager **stillschweigend verschluckt** – kein Log, keine Notice, kein User-Feedback. Ursachen-Kandidaten: API-Key fehlt/falsch, API-URL nicht konfiguriert, HTTP 401/422/500.

2. **Feature:** Auf einer externen Site sollen handverlesene Properties als kuratierte Liste oder Slider eingebunden werden können. Reihenfolge bestimmt der User über eine ID-Liste. Filter-Bar wird in diesem Modus nicht gebraucht. Standalone-Funktionalität (eigene Detailseiten, eigenes Branding, eigenes Mailing) bleibt vollständig erhalten.

## Anforderungen (User-Entscheidungen aus Brainstorming)

| # | Punkt | Wahl |
|---|---|---|
| 1 | Bug-Fix-Scope | Stufe A: Logging + persistierte Admin-Notice (keine Fallback-Tabelle) |
| 2 | Shortcode-Name für Feature | bestehender `[immo_list]` erweitert (kein neuer Name) |
| 3 | Trennzeichen `ids` | Komma **und** Semikolon erlaubt |
| 4 | Filter-Bar bei `ids` | automatisch ausgeblendet |
| 5 | Slider-Library | Splide.js v4.1.4, lokal gehostet (`assets/vendor/splide/`) |
| 6 | Status-Filter bei `ids` | optional kombinierbar – ohne Filter werden alle Status angezeigt (Use-Case Referenzliste) |
| 7 | Hard-Cap IDs | 100 (genug für Referenzlisten, schützt vor Missbrauch) |

## Architektur

### Manager-Seite (`immo-manager`)

**`includes/class-rest-api.php`:**

- `properties_args()` bekommt einen neuen Param `ids` (Typ: string).
- `build_property_query()` parst den Param mit `preg_split('/[,;]/', …)`, normalisiert via `absint`, deduplikatet, kappt auf 100 IDs.
- Bei vorhandenen IDs:
  - `post__in = $id_list`
  - `orderby = 'post__in'` (erhält die User-Reihenfolge)
  - `posts_per_page = count($id_list)` (überschreibt das `per_page=12`-Default-Limit)
  - `paged = 1` (Pagination wird im IDs-Modus nicht unterstützt)
- Default-Sortierung (`newest`) wird im Switch-Case nur angewendet, wenn keine IDs gesetzt sind. Expliziter `orderby` (price_*/area_desc) hat weiterhin Vorrang vor der ID-Reihenfolge.
- Alle bestehenden Filter (`status`, `mode`, `region_*`, `price_*`, `area_*`, `rooms`, `energy_class`) bleiben **kombinierbar** mit `ids`.

### Client-Seite (`immo-client`)

**Slider-Bibliothek:**
- `assets/vendor/splide/splide.min.js` (v4.1.4, ~30 kB)
- `assets/vendor/splide/splide.min.css` (~5 kB)
- `assets/vendor/splide/LICENSE.txt` (MIT)
- Lizenz aus dem offiziellen Repo gespiegelt.

**Eigene Slider-Assets:**
- `assets/js/immo-list-slider.js` – Bootstrap-Skript: liest Konfig aus `data-*` Attributen am Container, mountet Splide. Idempotent über `dataset.immoSliderReady`-Flag.
- `assets/css/immo-list-slider.css` – Eigene Styles (Pfeile in Primärfarbe, Pagination, Slide-Höhe = stretch).

**Asset-Registrierung (`immo-client.php`):**
- Splide + Slider-Assets werden nur **registriert**, nicht enqueued. Enqueue erfolgt im Shortcode bei `layout=slider`. So lädt eine Site, die nur Grids nutzt, kein zusätzliches Splide.

**Shortcode (`includes/class-immo-shortcodes.php::render_list_shortcode`):**

Neue Atts:

| Att | Default | Bedeutung |
|---|---|---|
| `ids` | – | Komma- oder Semikolon-getrennte Property-IDs |
| `layout` | `grid` | `grid` \| `slider` |
| `per_page` | 3 | Slider: sichtbare Slides Desktop |
| `per_page_md` | 2 | Slider: ≤ 900 px |
| `per_page_sm` | 1 | Slider: ≤ 600 px |
| `gap` | `1.5rem` | Slider: CSS-Gap |
| `autoplay` | `no` | Slider: 5 s Intervall |
| `loop` | `yes` | Slider: Endlos-Loop |

Verhalten:
- Bei `ids` gesetzt: Filter-Bar wird ausgeblendet (egal ob `filters="yes"`).
- `limit` wird ignoriert, sobald `ids` gesetzt ist (Manager liefert genau die ID-Anzahl zurück).
- `layout=slider` ist nur für `type=properties` aktiv; bei `type=projects` Fallback auf `grid`.

**Template (`templates/list-slider.php`):**
- Splide-Markup-Konvention (`<div class="splide"><div class="splide__track"><ul class="splide__list">…</ul></div></div>`)
- Card-Markup identisch zu `list-grid.php` (gleiche Felder, gleiche Inline-Styles, gleicher Provisionsfrei-Badge-Helper).
- `data-*`-Attribute werden vom Init-Skript gelesen.

### Bug-Fix Stufe A (`immo-client/includes/class-immo-ajax.php`)

**Neue Hilfsmethode `log_inquiry_api_error($context, WP_Error, $payload)`:**
- Schreibt eine Einzelzeile ins `error_log` (ohne PII: kein Name, keine Mail, kein Telefon — nur `property_id`, HTTP-Status, Source-URL, Fehlermeldung).
- Persistiert Detail in `update_option('immo_last_inquiry_error', […])` mit Timestamp.

**`submit_inquiry()` Erweiterung:**
- Bei `WP_Error` → `log_inquiry_api_error('property', …)`.
- Bei `array` mit `success=false` → künstlicher `WP_Error` + log.
- Bei Erfolg → `delete_option('immo_last_inquiry_error')` (Notice räumt sich selbst auf).
- Mail-Versand bleibt unverändert (User-Experience identisch).

**Admin-Notice (`render_inquiry_error_notice` Hook auf `admin_notices`):**
- Sichtbar nur für `manage_options`-Capability.
- Zeigt: Kontext (`property` / `project`), Fehlermeldung, HTTP-Status, `property_id`, Zeitstempel.
- Zwei Links: „Einstellungen prüfen" (zu ImmoClient-Settings) + „Hinweis ausblenden" (Nonce-geschützter Dismiss).

**Dismiss-Action (`admin_post_immo_dismiss_inquiry_err`):**
- Löscht die Option, leitet zurück.

## Help-Page-Updates

**Manager (`includes/class-admin-pages.php` → `render_help_page`):**
- Filtertabelle für `GET /properties` um `ids`-Zeile erweitert.
- Neuer Beispiel-Block „Beispiel: kuratierte Liste per ID" mit 2 fetch()-Snippets.
- Neuer Abschnitt „Cross-Site-Einbindung mit dem ImmoClient" am Ende des REST-Kapitels: Erklärt den Companion-Plugin-Workflow, listet alle Client-Shortcodes auf (inkl. der neuen `ids`/`slider`-Varianten), beschreibt API-Key-Anforderung und betont die Standalone-Funktionalität.

**Client (`includes/class-immo-help.php`):**
- Shortcode-Tabelle 3.1 um die neuen Atts erweitert (ids, layout, per_page*, gap, autoplay, loop).
- Neues Kapitel 12 „Integrations-Szenarien" mit 6 konkreten Use-Cases:
  1. Vollständige Standalone-Site
  2. Promo-Block mit handverlesenen Top-Immobilien
  3. Referenzliste mit verkauften Objekten
  4. Bauprojekt-Landingpage
  5. Hybrid (Filter-Liste + Slider)
  6. Mehrere Sites mit einem Manager
- Inline-Hinweis am Ende: Anfragen-Diagnose-Notice erscheint im Admin, wenn ein Forward fehlschlägt.

## Performance- & Sicherheits-Überlegungen

| Punkt | Maßnahme |
|---|---|
| ID-Bombing / Resource-Abuse | Hard-Cap 100 IDs in `build_property_query()` |
| Public-Visibility | Nur `post_status = publish`-Properties werden zurückgegeben (unverändert) |
| Asset-Last | Splide-Assets nur bei `layout=slider` enqueued |
| Cache | Bestehender Transient-Cache im Client greift weiter (Cache-Key enthält den vollen Query) |
| PII im Log | Bewusst keine Mail/Name/Telefon im error_log (nur property_id, HTTP-Status, source_url) |
| Notice-Dismiss | Nonce-geschützter Admin-Action-Endpoint |

## Testplan (manuell)

1. **REST-API:** `curl '…/wp-json/immo-manager/v1/properties?ids=42,17,93'` → Reihenfolge 42 → 17 → 93.
2. **Shortcode Grid:** Seite mit `[immo_list ids="…" layout="grid"]` rendert ohne Filter-Bar in genau dieser Reihenfolge.
3. **Shortcode Slider:** Seite mit `[immo_list ids="…" layout="slider" autoplay="yes"]` zeigt Slider mit Pfeilen + Pagination, Autoplay läuft, Loop wraps.
4. **Status-Filter mit IDs:** `[immo_list ids="51,72,88" status="sold"]` zeigt nur die verkauften aus der Liste.
5. **Bug-Fix Diagnose:** ImmoClient ohne API-Key konfigurieren → Anfrage abschicken → rote Admin-Notice mit „HTTP 401" erscheint. Korrekten API-Key hinterlegen → erneut Anfrage → Notice räumt sich auf, Eintrag erscheint in Manager-Inquiries.
6. **Standalone unverändert:** `/immobilie/{slug}` und `/bauprojekt/{slug}` rendern wie bisher; `[immo_property]`, `[immo_project]`, `[immo_units]` unverändert.

## Bewusst nicht im Scope

- Stufe B (lokale Fallback-Tabelle für nicht zustellbare Anfragen) → kommt nur, wenn das Logging Probleme zeigt, die A nicht abdeckt.
- Slider-Konfiguration in Settings-UI → Per-Shortcode-Atts reichen aktuell.
- Eigener Shortcode-Name `[immo_selection]` → Erweiterung des bestehenden `[immo_list]` ist sparsamer.
- Pagination im IDs-Modus → bei max 100 IDs nicht nötig.
