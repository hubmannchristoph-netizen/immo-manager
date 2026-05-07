# Wohneinheiten-Erweiterung — Phase 1: Datenmodell

**Status:** Implemented (2026-05-07)
**Datum:** 2026-05-07
**Scope:** Nur DB-Schema + Sanitize/Validate. Kein Wizard, kein REST-Output, kein Frontend.

## Ziel

Das Datenmodell der Wohneinheiten so erweitern, dass die folgenden Informationen sauber gespeichert werden können:

1. **Zusätzliche Flächen pro Einheit:** Balkon, Loggia, Garten, Keller (alle optional, in m²).
2. **Stellplatz-Konfiguration auf Bauprojekt-Ebene:** Verfügbarkeit, Anzahl, Standardpreise, Pflicht/optional, Freitext-Hinweis — getrennt für Tiefgarage und Außen-Stellplatz.
3. **Stellplatz-Inkludierung pro Einheit:** Wie viele TG- und Außen-Stellplätze sind in der Einheit inkludiert (default 0); optional Override-Preis falls die Einheit eine Sonderkondition hat.

## Out of Scope

Folgende Punkte gehören zu späteren Phasen und werden hier **nicht** umgesetzt:

- Phase 2: REST-Endpoints liefern die neuen Felder; `immo-client` rendert sie.
- Phase 3: Wizard-Schritte für die neuen Felder, Stellplatz-Block im Projekt-Wizard.
- Phase 4: Listen-Darstellung, Lightbox-Erweiterung, Mobile-Layout.

## Datenmodell

### 1. Tabelle `{prefix}immo_units` — neue Spalten

Werden nach den vorhandenen Flächen-Spalten (`area`, `usable_area`) eingefügt:

| Spalte                       | Typ              | Default | Bedeutung                                              |
|------------------------------|------------------|---------|--------------------------------------------------------|
| `balcony_area`               | DECIMAL(8,2)     | 0.00    | Balkon-Fläche in m²                                    |
| `loggia_area`                | DECIMAL(8,2)     | 0.00    | Loggia-Fläche in m²                                    |
| `garden_area`                | DECIMAL(8,2)     | 0.00    | Garten-/Eigengarten-Fläche in m²                       |
| `cellar_area`                | DECIMAL(8,2)     | 0.00    | Keller-Fläche in m²                                    |
| `parking_garage_count`       | TINYINT UNSIGNED | 0       | Anzahl inkludierter Tiefgaragenplätze                  |
| `parking_outdoor_count`      | TINYINT UNSIGNED | 0       | Anzahl inkludierter Außen-Stellplätze                  |
| `parking_garage_price_override`  | DECIMAL(10,2) NULL | NULL | Override-Preis pro TG-Platz; NULL ⇒ Projekt-Default |
| `parking_outdoor_price_override` | DECIMAL(10,2) NULL | NULL | Override-Preis pro Außen-Stellplatz; NULL ⇒ Default |

**Begründung Wertebereiche:** TINYINT (0–255) reicht für realistische Stellplatz-Zahlen. DECIMAL(8,2) erlaubt bis 999.999,99 m² — ausreichend für jede Fläche. NULL bei Override-Preisen, damit „kein Override" eindeutig vom Wert „0 €" unterscheidbar ist (Wert 0 = Stellplatz ist gratis enthalten).

### 2. Project-Meta — neue Felder (Custom Post Type `immo_mgr_project`)

Registriert in `class-meta-fields.php::project_fields()`:

| Meta-Key                                | Typ      | Default | Bedeutung                                            |
|-----------------------------------------|----------|---------|------------------------------------------------------|
| `_immo_parking_garage_available`        | boolean  | false   | Tiefgaragen-Plätze gibt es im Projekt                |
| `_immo_parking_garage_total`            | integer  | 0       | Gesamtzahl TG-Plätze im Projekt                      |
| `_immo_parking_garage_price`            | number   | 0       | Standard-Preis pro TG-Platz (€)                      |
| `_immo_parking_garage_required`         | boolean  | false   | TG-Platz ist beim Kauf verpflichtend                 |
| `_immo_parking_outdoor_available`       | boolean  | false   | Außen-Stellplätze gibt es im Projekt                 |
| `_immo_parking_outdoor_total`           | integer  | 0       | Gesamtzahl Außen-Stellplätze im Projekt              |
| `_immo_parking_outdoor_price`           | number   | 0       | Standard-Preis pro Außen-Stellplatz (€)              |
| `_immo_parking_outdoor_required`        | boolean  | false   | Außen-Stellplatz ist beim Kauf verpflichtend         |
| `_immo_parking_notes`                   | string   | ''      | Freitext-Hinweis zu Stellplätzen (z. B. „1 TG verpflichtend") |

`show_in_rest` bleibt analog bestehende Meta-Felder (true für Skalare). Phase 2 entscheidet die Output-Struktur — hier wird nur registriert/gespeichert.

## Migration

- `Database::DB_VERSION` von `1.6.0` → `1.7.0`.
- `dbDelta()` ergänzt fehlende Spalten automatisch beim nächsten Request (`maybe_upgrade()` greift in `plugins_loaded`).
- Bestehende Datensätze: alle neuen Unit-Spalten haben Default `0` bzw. `NULL` — keine Datenmigration nötig.
- Bestehende Projekte: neue Meta-Felder existieren erst beim ersten Speichern; Reads liefern Defaults via `get_post_meta(... single=true) ?: <default>`.

**Rollback-Strategie:** Spalten bleiben bei DB-Downgrade erhalten (kein Datenverlust). Neue Meta-Keys können bei Bedarf über `delete_post_meta` entfernt werden.

## Sanitize / Validate

Erweiterung in `Units::sanitize()`:

```php
foreach ( array( 'balcony_area', 'loggia_area', 'garden_area', 'cellar_area' ) as $k ) {
    if ( isset( $data[ $k ] ) ) { $out[ $k ] = max( 0, (float) $data[ $k ] ); }
}
foreach ( array( 'parking_garage_count', 'parking_outdoor_count' ) as $k ) {
    if ( isset( $data[ $k ] ) ) { $out[ $k ] = max( 0, min( 255, (int) $data[ $k ] ) ); }
}
foreach ( array( 'parking_garage_price_override', 'parking_outdoor_price_override' ) as $k ) {
    if ( array_key_exists( $k, $data ) ) {
        $val       = $data[ $k ];
        $out[ $k ] = ( '' === $val || null === $val ) ? null : max( 0, (float) $val );
    }
}
```

Erweiterung in `Units::column_formats()`-Map:

```php
'balcony_area'                   => '%f',
'loggia_area'                    => '%f',
'garden_area'                    => '%f',
'cellar_area'                    => '%f',
'parking_garage_count'           => '%d',
'parking_outdoor_count'          => '%d',
'parking_garage_price_override'  => '%f',
'parking_outdoor_price_override' => '%f',
```

Project-Meta-Sanitization läuft über das bestehende `register_meta`-System (Type-basiert) — keine zusätzliche Logik nötig.

## Akzeptanzkriterien

1. Nach Plugin-Update zeigt `SHOW COLUMNS FROM {prefix}immo_units` alle 8 neuen Spalten mit korrekten Defaults.
2. `get_option('immo_manager_db_version')` liefert `1.7.0`.
3. `Units::create([...])` mit den neuen Feldern persistiert sie korrekt; `Units::get($id)` liest sie zurück.
4. `update_post_meta($project_id, '_immo_parking_garage_available', true)` und `get_post_meta(...)` liefert true zurück.
5. Bestehende Units bleiben unverändert; bestehende Projekte zeigen Defaults für neue Meta-Felder.
6. Existierende Tests / Sanity-Checks laufen weiter durch (nichts gebrochen).

## Offene Punkte

Keine — Stellplatz-Modell und Feld-Set sind mit dem User abgestimmt (Variante α: zentral pro Projekt + optionaler Override pro Einheit).
