=== Immo Manager ===
Contributors: hubmannchristoph
Tags: immobilien, real-estate, vermietung, verkauf, austria, headless, rest-api
Requires at least: 5.9
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professionelle Immobilienverwaltung für Österreich – mit REST API für Headless/Multi-Site Betrieb.

== Description ==

Immo Manager ermöglicht die zentrale Verwaltung von Immobilien und Bauprojekten mit einer vollständigen REST API für Headless-Betrieb. Eine Installation versorgt beliebig viele externe Websites.

= Hauptfunktionen =

* Verwaltung von Kauf- und Mietimmobilien
* Bauprojekte mit Wohneinheiten (individuelle Preise und Status pro Unit)
* 6-stufiger Eingabe-Wizard ([immo_wizard])
* Listenansicht mit AJAX-Filter-Sidebar ([immo_list])
* Detailseite mit Galerie, Karte und Anfrage-Formular ([immo_detail])
* Bauprojekte-Übersicht ([immo_projects]) und Wohneinheiten-Tabelle ([immo_units])
* Elementor-Widgets: Immobilien-Liste, Bauprojekte, Wohneinheiten, Suche
* Embed-Widget: Immobilien, Bauprojekte und Wohneinheiten per Script-Snippet auf beliebigen externen Webseiten (Shadow DOM, REST-API)
* REST API für Headless/Multi-Site Betrieb
* CORS-Konfiguration für externe Frontends
* API-Key für schreibende Endpunkte
* Konfigurierbare CORS-Origins
* OpenStreetMap via Leaflet.js (kein API-Key nötig)
* Österreich-spezifisch: alle 9 Bundesländer mit Bezirken
* 38 Ausstattungskriterien in 7 Kategorien
* Demo-Daten importierbar (4 Immobilien + 2 Projekte)

= REST API Endpunkte =

* GET  /wp-json/immo-manager/v1/properties
* GET  /wp-json/immo-manager/v1/properties/{id}
* GET  /wp-json/immo-manager/v1/projects
* GET  /wp-json/immo-manager/v1/projects/{id}/units
* GET  /wp-json/immo-manager/v1/regions
* GET  /wp-json/immo-manager/v1/features
* GET  /wp-json/immo-manager/v1/settings/public
* POST /wp-json/immo-manager/v1/inquiries

= Headless Betrieb =

Eine WordPress-Installation dient als Datenquelle. Externe Websites laden Immobilien via REST API und zeigen sie auf eigenem Frontend an. Nur eine Wartungsstelle nötig.

== Installation ==

1. Plugin in /wp-content/plugins/immo-manager/ entpacken
2. Im WordPress-Admin aktivieren
3. Unter Einstellungen > Permalinks einmal speichern
4. Unter Immo Manager > Dashboard Demo-Daten importieren

== Changelog ==

= 1.4.0 =
* Neu: Bauprojekte als freischaltbares Paket – global abschaltbar (reine Immobilienverwaltung, Daten bleiben erhalten), Freischaltung pro Rolle (Einstellungen → Module) und pro Benutzer (Benutzerprofil); eigene Capabilities edit_immo_projects etc.
* Neu: Energieausweis-Pflichtangaben laut EAVG-Novelle (1.7.2026): Feld Endenergiebedarf (EEB) in Wizard, Metabox, REST (meta.energy_eeb/energy_fgee), Schema.org, OpenImmo (endenergiebedarf) und allen Ausgaben; fGEE nur noch als Altausweis-Angabe; Veröffentlichen im Wizard erfordert Klasse + HWB + EEB/fGEE (Grundstücke ausgenommen); Hinweis-/Warntexte im Backend
* Neu: Bedienungsanleitung für Embed-Widgets im Backend (API & Hilfe, Kapitel 12) mit Snippet-Generator + Live-Vorschau, Plattform-Anleitungen für Nicht-WordPress-Seiten und Fehlersuche; eigenständige Anleitungsseite public/embed/anleitung.html mit Live-Demo
* Neu: Embed-Widget public/embed/immo-embed.js – Immobilien, Bauprojekte, Wohneinheiten und Detailkarten per Script-Snippet auf JEDER externen Webseite (Shadow DOM, Filterleiste, „Mehr laden", Link-Templates, de/en); Snippet-Generator unter API & Hilfe
* Neu: Shortcode [immo_projects] – Bauprojekte als Grid/Liste/Slider auf beliebigen Seiten
* Neu: Shortcode [immo_units] – Wohneinheiten-Tabelle eines Bauprojekts (per ID oder Slug)
* Neu: Gemeinsames Card-Template für Bauprojekte (Archiv, Shortcode, Elementor)
* Performance: Frontend-CSS/JS wird nur noch auf Plugin-Seiten geladen (CPT-Seiten, Seiten mit Plugin-Shortcodes, Elementor-Widgets) statt auf jeder Seite
* Performance: Settings-Request-Cache, Aggregat-Cache für Wohneinheiten-Statistiken, Projekt-Kurzinfo-Cache in der REST-API (N+1 entfernt)
* Performance/Datenschutz: Leaflet wird lokal ausgeliefert (kein unpkg.com-CDN mehr); Google Fonts optional abschaltbar
* Sicherheit: notify_email / skip_notifications bei POST /inquiries nur noch mit gültigem API-Key wirksam (kein anonymes Mail-Relay)
* Sicherheit: Anfrage-Felder werden vor Mailversand bereinigt
* Fix: Elementor-Widget „Wohneinheiten" verursachte Fehler (Array-/Objekt-Zugriff)
* Fix: Nicht-Administratoren (Redakteure, Autoren) erhielten im Immo-Manager-Menü „Du darfst diese Seite nicht aufrufen“ – Top-Level-Menü und Dashboard verlangen jetzt edit_posts statt manage_options
* Fix: Elementor-Widget „Bauprojekte" zeigte rohe Status-Keys statt Labels
* Fix: Autoloader unterstützt Sub-Namespaces (OpenImmo, Elementor)
* Datensicherheit: Beim Löschen des Plugins bleiben alle Daten erhalten, sofern nicht explizit in den Einstellungen aktiviert
* Wartung: OpenImmo-Cron-Events werden nach Updates automatisch nachregistriert
* Tooling: bin/build-zip.py erzeugt ein installierbares Plugin-ZIP

= 1.3.6 =
* Fix: Heizungs-Dropdown-Wert wurde im Wizard nicht gespeichert, weil das Hidden-Feld nicht in den Wizard-Sammelselektor (`.immo-wizard-input`) eingebunden war – Folge: Heizung auf der Detailseite leer. Hidden-Feld trägt jetzt die übergebenen CSS-Klassen.

= 1.3.5 =
* Heizungsart als Dropdown statt Freitext (Wizard Schritt 3 + Property-Metabox). Optionen: Fernwärme, Gasheizung, Ölheizung, Wärmepumpe, Pellets/Holz, Elektroheizung, Solar, Kamin/Ofen, Sonstige. Bestehende Freitext-Werte bleiben erhalten — sie werden automatisch als "Sonstige" mit Freitext-Fallback angezeigt.

= 1.3.4 =
* REST: `meta.has_priced_units` (bool) und `unit_stats.min_price_formatted` / `min_rent_formatted` mitgeliefert, damit das immo-client-Plugin Auflistung und Detailseite identisch wie der Manager rendern kann.

= 1.3.3 =
* Detailseite: Nebenkosten- und Finanzierungsrechner werden auch bei zugeordneten Wohneinheiten angezeigt – vorbelegt mit dem günstigsten verfügbaren Unit-Preis und einem Dropdown zur Auswahl jeder verfügbaren Einheit (Preis und Provisionsfrei-Status werden bei Wechsel automatisch übernommen).

= 1.3.2 =
* Immobilien-Auflistung: bei zugeordneten Wohneinheiten zeigt die Karte jetzt den günstigsten verfügbaren Unit-Preis als "ab X €" (statt "Preis siehe Preisliste"). Ohne Units: weiterhin der normale Property-Preis. Detailseite bleibt unverändert ("Preis siehe Preisliste" bei Units).
* REST: `unit_stats` liefert zusätzlich `min_price` und `min_rent` der verfügbaren Units.

= 1.3.1 =
* Wenn einer Immobilie Wohneinheiten zugeordnet sind, wird der Property-Preis nicht mehr angezeigt – stattdessen erscheint "Preis siehe Preisliste" (Auflistung & Detailseite).
* Finanzierungs-/Nebenkostenrechner wird in diesem Fall ausgeblendet.
* JSON-LD: Kein Property-Sale-Offer mehr, wenn Units zugeordnet sind (Konsistenz zur UI).

= 1.0.0 =
* Erste Version
* Custom Post Types: Immobilien, Bauprojekte
* Custom Tables: Units, Inquiries
* 6-Step Wizard
* REST API mit CORS und API-Key
* Demo-Daten Import
