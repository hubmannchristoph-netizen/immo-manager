=== Immo Manager ===
Contributors: hubmannchristoph
Tags: immobilien, real-estate, vermietung, verkauf, austria, headless, rest-api
Requires at least: 5.9
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.3.6
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
