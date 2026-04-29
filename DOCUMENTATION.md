# Immo Manager – Dokumentation

Willkommen zur offiziellen, technischen Dokumentation des **Immo Manager** Plugins für WordPress.
Dieses Plugin bietet eine professionelle, vollständig integrierte Immobilien-Verwaltung speziell zugeschnitten auf den österreichischen Markt (inkl. Bundesländer & Bezirke) und verfügt über eine hochperformante REST-API für Headless-Szenarien.

---

## Inhaltsverzeichnis

1. [Systemarchitektur & Datenbank](#1-systemarchitektur--datenbank)
2. [Installation & Setup](#2-installation--setup)
3. [Kernkonzepte: Immobilien, Projekte, Wohneinheiten](#3-kernkonzepte-immobilien-projekte-wohneinheiten)
4. [Der Eingabe-Wizard](#4-der-eingabe-wizard)
5. [Frontend: Shortcodes & Elementor](#5-frontend-shortcodes--elementor)
6. [Nebenkosten- & Finanzierungsrechner](#6-nebenkosten--finanzierungsrechner)
7. [Einstellungen & Design-System](#7-einstellungen--design-system)
8. [Layout-Varianten & Galerie-Typen](#8-layout-varianten--galerie-typen)
9. [Lead-Management (Anfragen)](#9-lead-management-anfragen)
10. [OpenImmo Import & Export](#10-openimmo-import--export)
11. [SEO: Schema.org Markup](#11-seo-schemaorg-markup)
12. [REST-API & Headless-Betrieb](#12-rest-api--headless-betrieb)
13. [Admin-Menü-Struktur](#13-admin-menü-struktur)

---

## 1. Systemarchitektur & Datenbank

Das Plugin nutzt eine hybride Datenbank-Architektur, um maximale WordPress-Kompatibilität bei gleichzeitig höchster Performance für relationale Abfragen zu gewährleisten.

### Custom Post Types (CPTs)
- `immo_property` — Eigenständige Immobilien. Meta-Daten in `wp_postmeta` (Prefix `_immo_`).
- `immo_project` — Bauprojekte als Container. Meta-Daten ebenfalls in `wp_postmeta`.

### Custom Tables (für Performance & Relationen)
- `wp_immo_units` — Wohneinheiten innerhalb eines Bauprojekts. Verknüpft per `project_id` mit `immo_project`-Posts und optional mit `property_id` (Verknüpfung zu eigenständiger Property).
- `wp_immo_inquiries` — Eingehende Kundenanfragen mit Status-Workflow.
- `wp_immo_openimmo_sync_log` — OpenImmo Import/Export-Historie.
- `wp_immo_openimmo_conflicts` — Erkannte Konflikte aus dem OpenImmo-Import.

Alle Tabellen werden bei Plugin-Aktivierung via `dbDelta()` angelegt und versioniert.

### Codeorganisation

```
immo-manager/
├─ immo-manager.php              # Plugin-Bootstrap
├─ includes/
│  ├─ class-plugin.php           # Singleton-Bootstrapper
│  ├─ class-database.php         # Schema, Migrations
│  ├─ class-post-types.php       # CPTs registrieren
│  ├─ class-meta-fields.php      # Meta-Field-Schema
│  ├─ class-metaboxes.php        # Edit-Sidebar (Layout-Overrides)
│  ├─ class-units.php            # Units CRUD
│  ├─ class-inquiries.php        # Anfragen CRUD
│  ├─ class-features.php         # Ausstattungs-Tags + Kategorien
│  ├─ class-regions.php          # AT-Bundesländer + Bezirke
│  ├─ class-wizard.php           # 7-Schritte Eingabe-Wizard
│  ├─ class-shortcodes.php       # Frontend-Shortcodes
│  ├─ class-templates.php        # Template-Loader
│  ├─ class-rest-api.php         # REST-Endpunkte
│  ├─ class-settings.php         # Settings-Sektionen
│  ├─ class-admin-pages.php      # Admin-Menü, Help-Seite
│  ├─ class-admin-ajax.php       # AJAX-Endpunkte (Inquiry-Reply, Unit-CRUD)
│  ├─ class-dashboard.php        # Dashboard-Widget
│  ├─ class-schema.php           # Schema.org JSON-LD Injection
│  ├─ class-elementor-integration.php
│  ├─ elementor/                 # 4 Elementor-Widgets
│  └─ openimmo/                  # OpenImmo Import/Export Module
├─ templates/                    # Frontend + Admin Templates
├─ public/                       # CSS + JS (Wizard, Frontend, Calculator, Filters)
└─ languages/                    # i18n
```

---

## 2. Installation & Setup

1. Plugin-ZIP via WordPress-Backend hochladen oder direkt nach `wp-content/plugins/immo-manager` entpacken.
2. Aktivieren — beim Aktivieren werden Custom Tables angelegt und Default-Settings geschrieben.
3. Optional: Demo-Daten installieren via `Immo Manager → Einstellungen → Module → Demo-Daten installieren`.
4. Empfohlen: Permalink-Struktur unter `Einstellungen → Permalinks` einmal speichern, damit die CPT-URLs greifen.

### Voraussetzungen
- WordPress ≥ 6.0
- PHP ≥ 7.4 (empfohlen 8.1+)
- MySQL ≥ 5.7 / MariaDB ≥ 10.3
- Optional: Elementor (für die zusätzlichen Widgets)
- Optional: OpenSSL + ssh2-Extension (für SFTP im OpenImmo-Modul)

---

## 3. Kernkonzepte: Immobilien, Projekte, Wohneinheiten

### Immobilie (Property)
Eigenständiges Objekt: Wohnung, Haus, Grundstück, Gewerbeobjekt. Hat Galerie, Preise (Kauf und/oder Miete), Ausstattung, Kontakt, optional Geo-Daten.

### Bauprojekt (Project)
Container für mehrere Wohneinheiten. Eigenes Bild, Beschreibung, Adresse, Status (`planning` / `building` / `completed`). Wird wie eine Adresse einmal angelegt — die einzelnen Tops folgen.

### Wohneinheit (Unit)
Top innerhalb eines Bauprojekts. Eigene Felder: Top-Nummer, Etage, Fläche, Zimmer, Preis/Miete, Status, Grundriss-Bild. Eine Unit kann zusätzlich mit einer normalen `immo_property` verknüpft werden — dann erscheint sie sowohl in der Bauprojekt-Seite ALS AUCH in der globalen Immobilien-Liste, ohne Doppelpflege.

### Verknüpfungs-Logik

```
immo_project ──1:n──> wp_immo_units ──0:1──> immo_property
                                             (optional)
```

---

## 4. Der Eingabe-Wizard

Statt klassischer Metaboxen leitet das Plugin beim Anlegen/Bearbeiten in einen 7-stufigen Wizard.

| Schritt | Inhalt |
|---|---|
| 1. Typ | Immobilientyp, Modus (Miete/Kauf/beides), Status |
| 2. Lage | Adresse, PLZ, Ort, Bundesland, Bezirk, Geo-Koordinaten |
| 3. Details | Fläche, Zimmer, Bäder, Etage, Baujahr, Sanierung, Energieklasse, HWB, Heizung |
| 4. Preis | Kaufpreis, Miete, Betriebskosten, Kaution, Provisionsfrei-Toggle, Verfügbarkeit |
| 5. Ausstattung | Klickbare Feature-Tags (Innen/Außen/Sicherheit/Sonstiges) + freier Text |
| 6. Medien | Hauptbild, Galerie, Dokumente (Exposé-PDF), Video |
| 7. Kontakt | Ansprechpartner-Daten + Foto (für Anfrage-Lightbox) |

**Auto-Save-Schutz:** Verlässt der User den Wizard mit ungespeicherten Änderungen, warnt das Plugin per `beforeunload`-Dialog.

**Bauprojekt-Spezifikum:** Nach Schritt 7 erscheint eine Tabelle für die Wohneinheiten — anlegen, sortieren (Drag & Drop) und löschen passiert per AJAX, ohne Page-Reload.

---

## 5. Frontend: Shortcodes & Elementor

### Shortcodes

| Shortcode | Verwendung |
|---|---|
| `[immo_list]` | Hauptliste mit AJAX-Filter-Sidebar. Attribute: `status`, `mode`, `per_page`, `orderby`, `layout` |
| `[immo_detail id="123"]` | Detail-Ansicht für eine bestimmte Immobilie |
| `[immo_detail_page]` | Dynamische Detail-Seite (ID aus URL `?immo_id=`) |
| `[immo_latest count="3"]` | N neueste Immobilien |
| `[immo_featured count="3"]` | Featured (markierte) Immobilien |
| `[immo_count]` | Reine Zahl: aktuell verfügbare Immobilien |
| `[immo_search]` | Inline-Suchformular (Header/Hero) |

### Elementor-Widgets

Wenn Elementor aktiv ist, registriert das Plugin automatisch unter der Kategorie *Immo Manager*:

- **Immobilien-Liste** — Grid/List/Slider/Karten-Layout, Query-Filter, Card-Design-Optionen
- **Bauprojekte** — mit Status-Badges und Verfügbarkeits-Stats
- **Projekt-Wohneinheiten** — tabellarische Top-Übersicht
- **Immobilien-Suche** — horizontaler Such-Schlitz

---

## 6. Nebenkosten- & Finanzierungsrechner

Auf jeder Kauf-Property mit Preis und auf jeder Bauprojekt-Seite mit ≥ 1 Kauf-Einheit erscheinen automatisch zwei separate Akkordeons unterhalb aller Detail-Sektionen.

### Akkordeon 1: Nebenkostenrechner

Berechnet die typischen AT-Erwerbsnebenkosten:

- Grunderwerbsteuer (Default 3,5 %)
- Grundbucheintragung (Default 1,1 %)
- Notar/Treuhand (Prozent oder Pauschalbetrag — umschaltbar in den Settings)
- Maklerprovision (Default 3 %) — wird ausgeblendet bei Property-Override `_commission_free`
- USt auf Provision (Default 20 %)

Jeder Posten ist global ein-/ausschaltbar; alle Sätze sind frei konfigurierbar.

### Akkordeon 2: Finanzierungsrechner

Klassische Annuitätenrechnung:

- Eigenkapital — umschaltbar zwischen € und % (Default 20 %, KIM-V-konform)
- Zinssatz (% p.a., capped 0–20)
- Laufzeit (1–50 Jahre)
- Optionale jährliche Sondertilgung
- Output: monatliche Rate, Gesamt-Zinsen, Gesamtaufwand, Tilgung-Endjahr
- Optional: jährlicher Tilgungsplan (Jahr / Zinsen / Tilgung / Restschuld)

Edge Case Zinssatz 0 %: lineare Tilgung statt Annuität.

### Bauprojekt-Modus

Auf Bauprojekt-Seiten zeigt jeder Rechner ein zusätzliches Wohneinheits-Dropdown ganz oben. Auswahl wechselt sofort den Berechnungs-Kaufpreis und das `commission_free`-Flag.

### Konfiguration

`Immo Manager → Einstellungen → 🧮 Rechner`:

- Aktivierungs-Toggles für jeden der beiden Rechner
- Alle Sätze + Pro-Posten-Toggles
- Notar-Modus (Prozent vs. Pauschal) + Pauschal-Betrag
- Finanz-Defaults (Eigenkapital %, Zins %, Laufzeit, Sondertilgung)
- Tilgungsplan-Anzeige (an/aus)

### Disclaimer

Unter jedem Rechner steht der Hinweis "Unverbindliche Schätzung – ersetzt keine Bank-/Steuerberatung."

---

## 7. Einstellungen & Design-System

Settings-Tabs:

| Tab | Inhalt |
|---|---|
| ⚙️ Allgemein | Währung, Symbol, Position, Dezimalen, Trennzeichen, Items pro Seite, Default-View |
| 🎨 Design & Layout | Farben, Schriften, Border-Radius, Card-Stil, Default-Detail-Layout, Default-Galerie, Hero-Stil |
| 🧩 Module | An-/Aus-Schalter für Anfragen, Karten, Galerie, Schema.org, Demo-Daten etc. |
| 📧 Kontakt | Globaler Empfänger für Anfragen, Mail-Template, Reply-To-Logik |
| 🗺️ Karten | Provider (Leaflet/OSM oder Google Maps), API-Keys, Default-Zoom |
| 🧮 Rechner | Sätze, Toggles, Notar-Modus, Finanz-Defaults, Tilgungsplan |
| 🔌 API & Integration | API-Key, CORS-Origins, Webhook-URL, Tracking-IDs |

Alle Werte werden in einer einzigen Option `immo_manager_settings` (serialisiertes Array) gespeichert. Sanitize-Callback prüft jeden Key gegen ein Schema.

CSS-Custom-Properties (`--immo-accent`, `--immo-bg`, `--immo-text`, `--immo-border`, `--immo-radius`, ...) werden im `<head>` injiziert und sind in allen Plugin-Templates konsistent verwendet.

---

## 8. Layout-Varianten & Galerie-Typen

Drei orthogonale Layout-Achsen, jeweils global einstellbar UND per Property/Projekt überschreibbar:

| Achse | Werte | Wirkung |
|---|---|---|
| `layout_type` | `standard`, `compact` | Zweispaltig mit Sticky-Sidebar vs. einspaltig |
| `gallery_type` | `slider`, `grid` | Hauptbild + Thumbnails vs. Bento-Grid |
| `hero_type` | `full`, `compact` | Volle Breite vs. kompakte Hero-Höhe |

Per-Property-Override findet der User in der Sidebar-Box "Darstellung & Layout" am Edit-Screen.

---

## 9. Lead-Management (Anfragen)

### Frontend

- "Anfrage senden"-Button öffnet eine Lightbox mit Formular
- Pflichtfelder: Name, E-Mail, DSGVO-Consent
- Optional: Telefon, Nachricht, Wohneinheit (bei Bauprojekten)
- Anti-Spam: Honeypot + optional reCAPTCHA
- Validierung clientseitig + serverseitig

### Backend

`Immo Manager → Anfragen`:

- Status-Tabs mit Live-Zählern (Alle / Neu / Gelesen / Beantwortet / Spam)
- Direkt-Antwort-Button (öffnet Mail-Programm mit vorgefertigtem Subject)
- Auto-Status-Update auf "Beantwortet" beim Antworten
- Lösch-Funktion mit Nonce-Schutz
- Mail-Benachrichtigung an globalen oder Property-spezifischen Empfänger
- Dashboard-Widget zeigt offene Anfragen-Zahl

---

## 10. OpenImmo Import & Export

OpenImmo 1.2.7 ist der etablierte XML-Standard für den Datenaustausch mit Immobilienportalen.

### Export

1. Mappt alle Plugin-Felder auf das OpenImmo-Schema (`immobilie/objektkategorie/preise/freitexte/anhaenge/...`)
2. Image-Processor: Resize + Optimierung der Bilder vor dem Verpacken
3. XSD-Validator prüft das XML gegen die offizielle OpenImmo-Schema-Datei
4. ZIP-Packager erzeugt das Versand-Paket inkl. allen Bildern
5. SFTP-Uploader transferiert das Paket an die konfigurierten Portal-Targets
6. Manueller Trigger oder per Cron-Schedule (stündlich/täglich/wöchentlich)

### Import

1. SFTP-Puller holt periodisch ZIP-Pakete aus dem konfigurierten Eingangs-Verzeichnis
2. ZIP-Extractor entpackt
3. XML wird geparst, Bilder via Image-Importer in die Mediathek
4. Reverse-Mapping zurück auf Plugin-Felder
5. Conflict-Detector erkennt Race-Conditions (lokal verändert UND extern geändert)
6. Sync-Log + Conflicts-List für manuelle Auflösung

### Wartung

- Email-Notifier sendet optional Sync-Berichte
- Retention-Cleaner löscht alte Logs und Temp-Dateien automatisch
- Cron-Scheduler steuert die Frequenz

---

## 11. SEO: Schema.org Markup

Auf jeder Property- und Projekt-Detailseite wird automatisch JSON-LD im `<head>` injiziert:

- `RealEstateListing` (Top-Level)
- `Place` / `GeoCoordinates`
- `Offer` / `PriceSpecification`
- `Accommodation` / `Apartment` / `House` / `SingleFamilyResidence`
- `Organization` / `Person` (Anbieter & Kontakt)

Vorteile: Google Rich Results, Indexierung in Google for Real Estate, strukturierte Daten für Voice-Search und KI-Suchmaschinen.

Validieren: [Google Rich Results Test](https://search.google.com/test/rich-results)

---

## 12. REST-API & Headless-Betrieb

Namespace: `/wp-json/immo-manager/v1/`

### Authentifizierung

- GET-Endpunkte: öffentlich
- POST `/inquiries`: API-Key erforderlich (falls in Settings konfiguriert)
- Header: `X-Immo-API-Key: DEIN_KEY`
- CORS-Origins in den Settings konfigurierbar

### Endpunkte

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/properties` | Paginierte Liste mit Filtern |
| GET | `/properties/{id}` | Detail einer Property |
| GET | `/properties/{id}/similar` | Ähnliche Properties |
| GET | `/projects` | Liste aller Bauprojekte |
| GET | `/projects/{id}` | Projekt-Detail |
| GET | `/projects/{id}/units` | Wohneinheiten + Stats |
| GET | `/regions` | AT-Bundesländer |
| GET | `/regions/{state}/districts` | Bezirke eines Bundeslandes |
| GET | `/features` | Ausstattungs-Tags |
| GET | `/settings/public` | Öffentliche Settings (Währung/Farben/Maps) |
| GET | `/search` | Volltextsuche |
| POST | `/inquiries` | Anfrage einreichen |

### Filterparameter für `/properties`

`per_page`, `page`, `orderby` (`newest`/`price_asc`/`price_desc`/`area_desc`), `status`, `mode`, `type`, `region_state`, `region_district`, `price_min`, `price_max`, `area_min`, `area_max`, `rooms` (Komma-Liste), `project_id`, `search`.

### Beispiel

```js
fetch('/wp-json/immo-manager/v1/properties?mode=sale&region_state=steiermark&price_max=500000')
  .then( r => r.json() )
  .then( data => console.log( data.properties, data.pagination ) );
```

---

## 13. Admin-Menü-Struktur

Das Plugin sortiert seine Submenüs in eine logische Reihenfolge — Verwaltung zuerst, Konfiguration und Hilfe zuletzt:

1. **Dashboard**
2. **Alle Immobilien**
3. **+ Neue Immobilie**
4. **Alle Bauprojekte**
5. **+ Neues Bauprojekt**
6. **Wohneinheiten** (Plugin-eigene Übersicht über alle Projekte)
7. **Anfragen**
8. **Einstellungen**
9. **API & Hilfe**

Diese Reihenfolge ist über `AdminPages::reorder_submenu()` (Hook `admin_menu` mit Priority 99) erzwungen und bleibt stabil, auch wenn Drittplugins zusätzliche Einträge hinzufügen.
