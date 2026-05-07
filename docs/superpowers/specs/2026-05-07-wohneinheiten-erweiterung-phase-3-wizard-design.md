# Wohneinheiten-Erweiterung — Phase 3: Wizard & Backend-Eingabe

**Status:** Implemented (2026-05-07)
**Datum:** 2026-05-07
**Voraussetzung:** Phasen 1 + 2 implementiert.
**Scope:** Backend-Eingabe-UI für die neuen Felder. Bauprojekt-Editor bekommt eine Stellplatz-Metabox, der Wohneinheits-Modal-Editor (in Wizard und Project-Metabox) bekommt Zusatzflächen + Stellplatz-Counts.

## Ziel

1. **Stellplatz-Konfiguration auf Bauprojekt-Ebene** ist über eine neue Metabox „Stellplätze" im Project-Editor pflegbar.
2. **Wohneinheit-Editor-Modal** zeigt zusätzlich Felder für Balkon, Loggia, Garten, Keller (m²) und inkludierte Stellplätze (TG, Außen). Override-Preise sind aus Komplexitäts-Gründen vorerst NICHT in der UI — werden DB-seitig leer gelassen, optional in Phase 4 nachgezogen.
3. **Beide Wohneinheits-Editor-Stellen** (Wizard-Form Project-Mode + `metabox-project-units.php`) zeigen identische Felder.

## Out of Scope

- Phase 4: Listen-/Lightbox-Anzeige + Mobile-Layout.
- Override-Preise pro Einheit (Spalte existiert in DB, UI erst bei Bedarf).

## Lösungsstrategie

### Stellplatz-Metabox auf Bauprojekt

Neue Metabox `immo_project_parking` (Titel: „Stellplätze") in `class-metaboxes.php` registriert + `render_project_parking()`-Methode. Felder im `immo_meta[]`-Array, damit `save_project()` automatisch greift (Phase 1 hat alle Meta-Keys schon registriert).

```html
<table class="form-table immo-form">
    <tr><th colspan="2"><h3>Tiefgarage</h3></th></tr>
    <tr><th>Verfügbar</th><td><label><input type="checkbox" name="immo_meta[_immo_parking_garage_available]" value="1" …> Im Projekt verfügbar</label></td></tr>
    <tr><th>Anzahl gesamt</th><td><input type="number" name="immo_meta[_immo_parking_garage_total]" min="0" …></td></tr>
    <tr><th>Preis pro Platz</th><td><input type="number" name="immo_meta[_immo_parking_garage_price]" min="0" step="1" …> €</td></tr>
    <tr><th>Verpflichtend</th><td><label><input type="checkbox" name="immo_meta[_immo_parking_garage_required]" value="1" …> Pflichtkauf</label></td></tr>

    <tr><th colspan="2"><h3>Außen-Stellplatz</h3></th></tr>
    <tr><th>Verfügbar</th><td><label><input type="checkbox" name="immo_meta[_immo_parking_outdoor_available]" value="1" …> Im Projekt verfügbar</label></td></tr>
    <tr><th>Anzahl gesamt</th><td><input type="number" name="immo_meta[_immo_parking_outdoor_total]" min="0" …></td></tr>
    <tr><th>Preis pro Platz</th><td><input type="number" name="immo_meta[_immo_parking_outdoor_price]" min="0" step="1" …> €</td></tr>
    <tr><th>Verpflichtend</th><td><label><input type="checkbox" name="immo_meta[_immo_parking_outdoor_required]" value="1" …> Pflichtkauf</label></td></tr>

    <tr><th>Hinweis</th><td><textarea name="immo_meta[_immo_parking_notes]" rows="2" cols="60" …></textarea></td></tr>
</table>
```

### Wohneinheits-Modal

Beide Modal-Templates (Wizard-Form ab Zeile 315, Metabox ab Zeile 92) bekommen einen neuen Block „Zusatzflächen & Stellplätze" mit Inputs:

- `balcony_area` (number, m²)
- `loggia_area` (number, m²)
- `garden_area` (number, m²)
- `cellar_area` (number, m²)
- `parking_garage_count` (number, 0–9)
- `parking_outdoor_count` (number, 0–9)

Die JS-Logik (`metaboxes.js::openEditor()` und das inline-JS im Wizard) iteriert generisch über alle Inputs/Selects per `name`-Attribut — keine Code-Anpassung nötig, sobald die Inputs existieren. Server-seitig kennt `Units::sanitize()` die Felder bereits aus Phase 1.

### Hydrate-Anpassung

`Units::hydrate()` ergänzt typisierte Casts für die neuen Felder, damit JSON-Output (REST + AJAX-Responses) konsistent ist:

```php
$row['balcony_area']                   = (float) ( $row['balcony_area'] ?? 0 );
$row['loggia_area']                    = (float) ( $row['loggia_area']  ?? 0 );
$row['garden_area']                    = (float) ( $row['garden_area']  ?? 0 );
$row['cellar_area']                    = (float) ( $row['cellar_area']  ?? 0 );
$row['parking_garage_count']           = (int)   ( $row['parking_garage_count']  ?? 0 );
$row['parking_outdoor_count']          = (int)   ( $row['parking_outdoor_count'] ?? 0 );
$row['parking_garage_price_override']  = isset( $row['parking_garage_price_override'] )  && '' !== $row['parking_garage_price_override']  ? (float) $row['parking_garage_price_override']  : null;
$row['parking_outdoor_price_override'] = isset( $row['parking_outdoor_price_override'] ) && '' !== $row['parking_outdoor_price_override'] ? (float) $row['parking_outdoor_price_override'] : null;
```

## Akzeptanzkriterien

1. Bauprojekt-Edit-Screen zeigt Metabox „Stellplätze" mit allen 9 Feldern; speichern legt die Werte in Post-Meta ab.
2. Wohneinheit-Modal (Project-Metabox + Wizard) zeigt 6 neue Inputs; Speichern persistiert die Werte in `wp_immo_units`.
3. Re-Open eines bestehenden Eintrags zeigt die gespeicherten Werte korrekt (typisiert).
4. REST-Endpoint (Phase 2) liefert nach Speichern die neuen Werte.

## Offene Punkte

Keine offenen Designfragen.
