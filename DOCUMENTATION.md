# Immo Manager – Dokumentation

Willkommen zur offiziellen, technischen Dokumentation des **Immo Manager** Plugins für WordPress. 
Dieses Plugin bietet eine professionelle, komplett integrierte Immobilien-Verwaltung speziell zugeschnitten auf den österreichischen Markt (inkl. Bundesländer & Bezirke) und verfügt über eine hochperformante REST-API für Headless-Szenarien.

---

## Inhaltsverzeichnis

1. [Systemarchitektur & Datenbank](#1-systemarchitektur--datenbank)
2. [Installation & Setup](#2-installation--setup)
3. [Kernkonzepte: Immobilien vs. Projekte](#3-kernkonzepte-immobilien-vs-projekte)
4. [Der Eingabe-Wizard](#4-der-eingabe-wizard)
5. [Frontend: Shortcodes & Elementor](#5-frontend-shortcodes--elementor)
6. [Einstellungen & Design-System](#6-einstellungen--design-system)
7. [Layout-Varianten & Galerie-Typen](#7-layout-varianten--galerie-typen)
8. [Lead-Management (Anfragen)](#8-lead-management-anfragen)
9. [REST-API & Headless-Betrieb](#9-rest-api--headless-betrieb)

---

## 1. Systemarchitektur & Datenbank

Das Plugin nutzt eine hybride Datenbank-Architektur, um maximale Kompatibilität mit WordPress bei gleichzeitig höchster Performance zu gewährleisten.

### Custom Post Types (CPTs)
* **`immo_property`**: Eigenständige Immobilien. Meta-Daten werden in der `wp_postmeta` Tabelle gespeichert (Prefix `_immo_`).
* **`immo_project`**: Bauprojekte als "Container". Meta-Daten ebenfalls in `wp_postmeta`.

### Custom Tables (für Performance & Relationen)
Da Bauprojekte hunderte Wohneinheiten haben können, und eine Meta-Abfrage hier zu langsam wäre, nutzt das Plugin eigene Tabellen:
* **`wp_immo_units`**: Speichert alle Wohneinheiten. Sie sind über `project_id` (und optional `property_id`) verknüpft.
* **`wp_immo_inquiries`**: Speichert alle eingegangenen Leads/Kontaktanfragen isoliert und sicher.

*Hinweis:* Bei Plugin-Updates wird das Schema über `dbDelta()` automatisch via `class-database.php` migriert.

---

## 2. Installation & Setup

1. Lade den Ordner `immo-manager` in dein Verzeichnis `wp-content/plugins/` hoch.
2. Aktiviere das Plugin im WordPress-Backend unter **Plugins**.
3. **WICHTIG:** Gehe zu **Einstellungen > Permalinks** und klicke auf **"Änderungen speichern"**. Nur so werden die Custom-Post-Type URLs registriert und 404-Fehler vermieden.
4. Gehe zu **Immo Manager > Dashboard** und klicke auf **"Demo-Daten importieren"**. Das füllt das System mit realistischen Muster-Immobilien, Bildern und Einheiten.

---

## 3. Kernkonzepte: Immobilien vs. Projekte

### Immobilien (`immo_property`)
Eigenständige Objekte, die direkt vermarktet werden (Haus, Wohnung, Grundstück). 
Sie verfügen über Kauf-/Mietpreise, genaue Flächenangaben, Energieausweis-Daten und eine eigene Bildergalerie.

### Bauprojekte (`immo_project`)
Projekte dienen als Klammer für mehrere Einheiten (z.B. ein Neubau-Wohnblock). Das Projekt hat eine globale Adresse, allgemeine Bilder und einen übergeordneten Status (In Planung, In Bau, Fertiggestellt).

#### Wohneinheiten (Units)
Dies sind die eigentlichen Wohnungen (Tops) innerhalb eines Bauprojekts. 
* Sie haben eigene Preise, Zimmer, Flächen und Etagenangaben.
* Sie haben einen eigenen Status (`available`, `reserved`, `sold`, `rented`).
* **Verknüpfung (Quick-Info):** Einer Wohneinheit kann eine eigenständige Immobilie (`immo_property`) zugewiesen werden. Dadurch entsteht im Frontend ein Button, der eine Quick-Info Lightbox mit Bild, Preis und Link zum vollständigen Einzel-Exposé öffnet.

---

## 4. Der Eingabe-Wizard

Das klassische WordPress-Backend ist oft zu unübersichtlich für komplexe Immobiliendaten. Daher "zwingt" das Plugin den Redakteur freundlich in einen **7-stufigen Eingabe-Wizard**. Dieser speichert die Daten nahtlos per AJAX.

**Die 7 Schritte:**
1. **Typ & Modus:** Was soll erstellt werden? Eigene Immobilie vs. Bauprojekt. Miete vs. Kauf.
2. **Standort:** Adresse, Bundesland/Bezirk (kaskadierend) und Geokoordinaten.
3. **Details:** Flächen, Zimmer (inkl. Schlaf-/Badezimmer), Stockwerk, Baujahr, Energieausweis (HWB, fGEE), Objektbeschreibung.
4. **Preis:** Preisangaben, Betriebskosten, Kaution, Provision, Verfügbarkeitsdatum und Status.
5. **Ausstattung:** Einfache Checkbox-Auswahl aus 38 Merkmalen, gruppiert in Kategorien (z.B. Heizung, Barrierefreiheit, Technik).
6. **Medien:** Drag & Drop Upload für Titelbild, Galerie-Bilder und Dokumente.
   * *Hinweis:* Das erste Bild in der Galerie wird automatisch und zuverlässig als Beitragsbild (Featured Image) der Immobilie synchronisiert.
7. **Abschluss:** Makler-Daten (Name, E-Mail, Telefon, Foto). 

---

## 5. Frontend: Shortcodes & Elementor

Das Plugin bietet verschiedene Möglichkeiten zur Darstellung von Inhalten.

### Shortcodes
Du kannst diese Codes in jeden Editor einfügen:
* **`[immo_list]`**: Immobilien-Liste mit Live-Filter. Parameter: `layout` (grid/list), `per_page`, `status`, `mode`.
* **`[immo_detail id="X"]`**: Einzel-Exposé.
* **`[immo_search]`**: Suchmaske für die Startseite.

### Elementor Integration
In Elementor findest du eine eigene Kategorie **"Immo Manager"** mit spezialisierten Widgets:

1. **Immobilien-Liste:**
   * **Abfrage:** Filtere nach Anzahl, Verkaufs-/Miet-Modus und Sortierung.
   * **Layouts:** Wähle zwischen **Raster (Grid)**, **Liste**, **Carousel (Slider)** oder einer **Interaktiven Karte (Map)**.
   * **Responsive:** Stelle die Spaltenanzahl individuell für Desktop, Tablet und Mobil ein.
2. **Bauprojekte:**
   * Zeigt eine Übersicht deiner Projekte mit Status-Badges und Vorschaubildern.
3. **Projekt-Wohneinheiten:**
   * Platziere dieses Widget auf einer Projekt-Seite. Es listet automatisch alle zugehörigen Einheiten in einer **sortierbaren Tabelle** (Fläche, Zimmer, Preis, Status) auf.
4. **Immobilien-Suche:**
   * **Inline:** Ein eleganter Suchbalken für den Hero-Bereich.
   * **Lightbox:** Ein kompaktes Lupen-Icon, das bei Klick eine Suchebene öffnet.

---

## 6. Einstellungen & Design-System

Die zentralen Einstellungen befinden sich unter **Immo Manager > Einstellungen**. Das Menü ist in übersichtliche Reiter (Tabs) unterteilt:

* **Allgemein:** Währung, Trennzeichen und globale Listen-Vorgaben.
* **Design & Layout:** Hier legst du globale Standardwerte für die Detailseiten fest (Layout-Typ, Galerie-Art, Bildbreite).
* **Module:** Aktiviere/Deaktiviere Funktionen wie den Wizard, die Filter oder das Bauprojekt-Modul.
* **Karten:** Konfiguriere den Tile-Server für OpenStreetMap (DSGVO-konform).
* **API:** Generiere API-Keys für externe Anwendungen oder Headless-Frontends.

*Tipp:* Das Umschalten der Tabs erfolgt blitzschnell per JavaScript, ohne dass die Seite neu geladen wird oder Eingaben verloren gehen.

---

## 7. Layout-Varianten & Galerie-Typen

Du kannst das Erscheinungsbild der Immobilien-Detailseiten (Exposés) flexibel steuern.

### Globale Vorgaben
In den Einstellungen (Tab "Design & Layout") definierst du den Standard für deine gesamte Website.

### Individuelle Overrides
Jede Immobilie kann im WordPress-Editor (Seitenleiste: **"Darstellung & Layout"**) vom Standard abweichen:

1. **Layout-Typ:**
   * **Standard:** Klassische zwei-spaltige Ansicht mit prominentem Hero-Bereich und Sticky-Sidebar für Anfragen.
   * **Kompakt:** Einspaltiges, fokussiertes Layout, ideal für minimalistische Designs.
2. **Galerie-Typ:**
   * **Slider:** Bilder zum Durchklicken/Wischen.
   * **Raster (Grid):** Moderne Collage-Ansicht der ersten 5 Bilder mit "+X"-Overlay für den Rest.
3. **Hauptbild-Breite:**
   * **Volle Breite:** Das Bild geht über die gesamte Bildschirmbreite (Edge-to-Edge).
   * **Eingefasst:** Das Bild bleibt innerhalb des Inhalts-Containers.

---

## 8. Lead-Management (Anfragen)

Anfragen werden in der Datenbank gespeichert und per HTML-E-Mail versendet. Die Verwaltung erfolgt unter **"Anfragen"** im WordPress-Menü. Durch Klick auf "Antworten" wird der Status automatisch nachverfolgt.

---

## 9. REST-API & Headless-Betrieb

Vollständiger Support für moderne Web-Architekturen über den Namespace `/wp-json/immo-manager/v1/`. Alle Endpunkte sind performant und für Cross-Domain-Zugriffe (CORS) konfigurierbar.
