# Property-IDs-Liste + Slider + Inquiry-Diagnose – Implementation Plan

**Goal:** Externe Sites mit ImmoClient sollen kuratierte Property-Listen (per ID) als Grid oder Splide-Slider einbinden können, während eingehende Anfragen mit Quellnachweis zentral im Manager landen. Bug-Fix Stufe A macht stille API-Forward-Fehler sichtbar.

**Architecture:** Kein neuer Service, keine neue DB-Tabelle. Manager bekommt einen zusätzlichen REST-Param. Client bekommt Splide-Assets, ein neues Template, Shortcode-Atts und einen Admin-Notice-Hook für Inquiry-Forward-Fehler.

**Tech Stack:** WordPress 5.9+, PHP 7.4+, Splide.js v4.1.4 (lokal, MIT).

**Spec:** `docs/superpowers/specs/2026-05-06-property-ids-list-und-slider-design.md`

**Branch:** `feat/property-ids-list` (in beiden Repos)

---

## File Structure

| Aktion | Datei (Repo immo-manager) |
|---|---|
| Modify | `includes/class-rest-api.php` |
| Modify | `includes/class-admin-pages.php` |

| Aktion | Datei (Repo immo-client) |
|---|---|
| Create | `assets/vendor/splide/splide.min.js` |
| Create | `assets/vendor/splide/splide.min.css` |
| Create | `assets/vendor/splide/LICENSE.txt` |
| Create | `assets/js/immo-list-slider.js` |
| Create | `assets/css/immo-list-slider.css` |
| Create | `templates/list-slider.php` |
| Modify | `immo-client.php` |
| Modify | `includes/class-immo-shortcodes.php` |
| Modify | `includes/class-immo-ajax.php` |
| Modify | `includes/class-immo-help.php` |

---

## Task 1 – Manager: REST `ids`-Param

- [x] In `properties_args()` `'ids' => array( 'type' => 'string' )` ergänzen.
- [x] In `build_property_query()` direkt nach Initialisierung von `$args` einen Block einfügen, der `ids` parst (`preg_split('/[,;]/', …)` + `absint` + `array_unique` + `array_slice($_, 0, 100)`).
- [x] Bei nichtleerer ID-Liste: `post__in`, `orderby='post__in'`, `posts_per_page=count($ids)`, `paged=1` setzen.
- [x] Default-Sortierungs-Switch um `if ( empty( $id_list ) )`-Guard erweitern (Default `newest` darf `post__in` nicht überschreiben). Expliziter `orderby` (`price_*`, `area_desc`) hat weiterhin Vorrang.
- [x] PHP-Lint clean.

## Task 2 – Client: Splide lokal

- [x] Ordner `assets/vendor/splide/` anlegen.
- [x] Splide v4.1.4 min.js + min.css aus `cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4` herunterladen.
- [x] LICENSE aus dem GitHub-Repo (master) als `LICENSE.txt` ablegen.

## Task 3 – Client: eigene Slider-Assets

- [x] `assets/js/immo-list-slider.js`: Bootstrap, Splide-Mount mit per-Container-Konfig, idempotent.
- [x] `assets/css/immo-list-slider.css`: Pfeile in Primärfarbe (`var(--immo-primary)`), Pagination, Slide-Stretch.

## Task 4 – Client: Asset-Registrierung

- [x] `immo-client.php::enqueue_assets`: 4× `wp_register_*` (kein Enqueue) für Splide CSS/JS und eigene Slider CSS/JS. Dependencies: `immo-client-list-slider` → `immo-client-splide`.

## Task 5 – Client: Shortcode `ids` + `layout=slider`

- [x] `class-immo-shortcodes.php::render_list_shortcode`: 7 neue Atts (`ids`, `layout`, `per_page`, `per_page_md`, `per_page_sm`, `gap`, `autoplay`, `loop`).
- [x] IDs Komma- oder Semikolon-getrennt parsen → CSV als API-Param.
- [x] Bei IDs gesetzt: API-Args nutzen `ids`, kein `per_page` (Manager-Default greift dann).
- [x] Filter-Bar wird übersprungen, sobald IDs gesetzt sind.
- [x] `layout=slider` nur für `properties` (bei `projects` Fallback auf `grid`).
- [x] Slider-Assets nur bei `layout=slider` per `wp_enqueue_*` aktivieren.
- [x] `templates/list-slider.php` erstellen (Splide-Markup + Card-Logik wie `list-grid.php`).

## Task 6 – Client: Bug-Fix Stufe A

- [x] Konstante `LAST_ERROR_OPTION` und neue Methode `log_inquiry_api_error()` in `class-immo-ajax.php`.
- [x] In `submit_inquiry()` API-Result auswerten: `WP_Error` → log; `array` ohne `success` → log mit künstlichem WP_Error; Erfolg → `delete_option`.
- [x] Hook `admin_notices` → `render_inquiry_error_notice()` (nur `manage_options`).
- [x] Hook `admin_post_immo_dismiss_inquiry_err` → `dismiss_inquiry_error()` (Nonce + Capability).
- [x] PHP-Lint clean.

## Task 7 – Help-Pages

- [x] Manager: `class-admin-pages.php` REST-Tabelle um `ids`-Zeile + neuen Beispiel-Block erweitern.
- [x] Manager: neuer Abschnitt „Cross-Site-Einbindung mit dem ImmoClient" am Ende des REST-Kapitels.
- [x] Client: `class-immo-help.php` Shortcode-Tabelle 3.1 um neue Atts erweitern.
- [x] Client: neues Kapitel 12 „Integrations-Szenarien" mit 6 Use-Cases.

## Task 8 – Vault-Doku

- [x] Spec: `docs/superpowers/specs/2026-05-06-property-ids-list-und-slider-design.md`.
- [x] Plan: `docs/superpowers/plans/2026-05-06-property-ids-list-und-slider.md`.
- [ ] `03_Dokumentation.md` im Vault ergänzen (Abschnitte „REST-API" + „ImmoClient-Shortcodes" + „Anfragen-Logging").

## Task 9 – Verifikation (manuell durch User)

- [ ] curl-Test gegen `/properties?ids=…` → korrekte Reihenfolge.
- [ ] Slider-Shortcode auf Test-Seite → visueller Check (Pfeile, Pagination, Responsive-Breakpoints, Autoplay).
- [ ] Status-Filter mit IDs → Liste richtig gefiltert.
- [ ] Inquiry-Bug-Fix: Forced-Fail-Test (falscher API-Key) → rote Admin-Notice erscheint mit HTTP 401.
- [ ] Standalone-Funktionalität: `/immobilie/{slug}`, `/bauprojekt/{slug}`, `[immo_property]`, `[immo_project]`, `[immo_units]` unverändert.

## Open Risks / Follow-ups

- Wenn Stufe A im Diagnose-Modus zeigt, dass User regelmäßig fehlschlagende Forwards haben, evtl. Stufe B (lokale Fallback-Tabelle) nachziehen.
- Splide.js wird bei jedem Splide-Slider geladen, auch wenn `[immo_list]` mehrfach auf einer Seite ist – das ist OK (WP de-dupliziert via Handle).
