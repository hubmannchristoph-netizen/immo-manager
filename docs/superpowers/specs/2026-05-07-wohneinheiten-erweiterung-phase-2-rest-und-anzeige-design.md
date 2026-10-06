# Wohneinheiten-Erweiterung — Phase 2: REST & Anzeige

**Status:** Implemented (2026-05-07)
**Datum:** 2026-05-07
**Voraussetzung:** Phase 1 (Datenmodell) implementiert.
**Scope:** REST-DTOs liefern die neuen Felder, Manager-Frontend (`single-immo_mgr_project.php`) und immo-client (`single-project.php` + Lightbox-Datenpool) zeigen sie.

## Ziel

Die in Phase 1 angelegten Felder werden für Frontend-Konsumenten verfügbar und sichtbar:

1. **REST API** liefert Zusatzflächen pro Unit und Stellplatz-Konfiguration pro Projekt.
2. **Manager-Plugin** (`single-immo_mgr_project.php`) zeigt Stellplatz-Sektion + Zusatzflächen in Quick-Info.
3. **immo-client** (`templates/single-project.php` + Lightbox-Datenpool) zeigt dieselben Informationen optisch analog.

## Out of Scope

- Phase 3: Wizard-UI für die neuen Felder.
- Phase 4: Tabellen-Liste mit Mini-Icons, Mobile-Cards-Layout, Mobile-Bug-Diagnose.
- OpenImmo-Mapping der neuen Felder (folgt als eigene kleine Aufgabe nach Phase 4).

## REST-DTO-Erweiterung

### 1. Unit-Endpoints (`format_unit()` in `class-rest-api.php`)

Neue Felder werden im flachen Unit-Objekt ergänzt — direkt nach `usable_area`:

```php
'area'          => (float) $unit['area'],
'usable_area'   => (float) $unit['usable_area'],
'balcony_area'  => (float) $unit['balcony_area'],
'loggia_area'   => (float) $unit['loggia_area'],
'garden_area'   => (float) $unit['garden_area'],
'cellar_area'   => (float) $unit['cellar_area'],
'parking'       => array(
    'garage_count'           => (int)   $unit['parking_garage_count'],
    'outdoor_count'          => (int)   $unit['parking_outdoor_count'],
    'garage_price_override'  => null === $unit['parking_garage_price_override']  ? null : (float) $unit['parking_garage_price_override'],
    'outdoor_price_override' => null === $unit['parking_outdoor_price_override'] ? null : (float) $unit['parking_outdoor_price_override'],
),
```

**Begründung Nesting bei `parking`:** Es ist eine logische Gruppe von 4 zusammengehörigen Werten — flache Felder wären `parking_garage_count` etc., vier Mal `parking_*` als Top-Level-Keys ist Lärm. NULL-Override bleibt explizit (nicht zu 0 gecastet).

### 2. Project-Endpoint (`format_project()` in `class-rest-api.php`)

Neue Sektion `meta.parking` direkt nach `documents`:

```php
'meta' => array(
    // … bestehende Felder …
    'features'        => $features,
    'features_detail' => $features_detail,
    'custom_features' => (string) $m( '_immo_custom_features', '' ),
    'parking'         => array(
        'garage' => array(
            'available' => (bool)  $m( '_immo_parking_garage_available', false ),
            'total'     => (int)   $m( '_immo_parking_garage_total', 0 ),
            'price'     => (float) $m( '_immo_parking_garage_price', 0 ),
            'required'  => (bool)  $m( '_immo_parking_garage_required', false ),
        ),
        'outdoor' => array(
            'available' => (bool)  $m( '_immo_parking_outdoor_available', false ),
            'total'     => (int)   $m( '_immo_parking_outdoor_total', 0 ),
            'price'     => (float) $m( '_immo_parking_outdoor_price', 0 ),
            'required'  => (bool)  $m( '_immo_parking_outdoor_required', false ),
        ),
        'notes' => (string) $m( '_immo_parking_notes', '' ),
    ),
),
```

### 3. Embedded Project in Unit-Endpoint

Wenn `format_unit()` den eingebetteten `property`-Block befüllt (Zeilen ~1167+), bleibt der unverändert — die Stellplatz-Konfiguration kommt aus dem Project, nicht der Property. Im Unit-Endpoint reicht es, die Stellplatz-Counts pro Einheit zu liefern (siehe oben). Frontend kombiniert.

**Help-Doku:** `class-immo-help.php` (Manager) und `class-immo-help.php` (Client) bekommen einen kurzen Eintrag mit den neuen REST-Feldern. Lediglich Doku, keine Logik.

## Manager-Anzeige (`templates/single-immo_mgr_project.php`)

### Sektion 1: Stellplatz-Akkordeon

Neuer Akkordeon-Block zwischen „Gemeinschafts-Ausstattung" und „Dokumente & Exposé":

```php
<?php
$pk = $meta['parking'] ?? array();
$has_garage  = ! empty( $pk['garage']['available'] );
$has_outdoor = ! empty( $pk['outdoor']['available'] );
if ( $has_garage || $has_outdoor || ! empty( $pk['notes'] ) ) :
?>
    <div class="immo-accordion">
        <button class="immo-accordion-header" aria-expanded="false">
            <?php esc_html_e( 'Stellplätze', 'immo-manager' ); ?>
            <span class="immo-accordion-icon" aria-hidden="true"></span>
        </button>
        <div class="immo-accordion-body" hidden>
            <ul class="immo-parking-list">
                <?php if ( $has_garage ) : ?>
                    <li class="immo-parking-item">
                        <span class="immo-parking-icon">🅿️</span>
                        <div class="immo-parking-meta">
                            <strong><?php esc_html_e( 'Tiefgaragenplatz', 'immo-manager' ); ?></strong>
                            <span class="immo-parking-price">
                                <?php echo esc_html( $this->format_price( (float) $pk['garage']['price'] ) ); ?>
                            </span>
                            <span class="immo-parking-flag immo-parking-flag-<?php echo $pk['garage']['required'] ? 'required' : 'optional'; ?>">
                                <?php echo $pk['garage']['required'] ? esc_html__( 'verpflichtend', 'immo-manager' ) : esc_html__( 'optional', 'immo-manager' ); ?>
                            </span>
                            <?php if ( (int) $pk['garage']['total'] > 0 ) : ?>
                                <span class="immo-parking-total"><?php printf( esc_html__( '%d Plätze gesamt', 'immo-manager' ), (int) $pk['garage']['total'] ); ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endif; ?>
                <?php if ( $has_outdoor ) : ?>
                    <li class="immo-parking-item">
                        <span class="immo-parking-icon">🚗</span>
                        <div class="immo-parking-meta">
                            <strong><?php esc_html_e( 'Außen-Stellplatz', 'immo-manager' ); ?></strong>
                            <span class="immo-parking-price">
                                <?php echo esc_html( $this->format_price( (float) $pk['outdoor']['price'] ) ); ?>
                            </span>
                            <span class="immo-parking-flag immo-parking-flag-<?php echo $pk['outdoor']['required'] ? 'required' : 'optional'; ?>">
                                <?php echo $pk['outdoor']['required'] ? esc_html__( 'verpflichtend', 'immo-manager' ) : esc_html__( 'optional', 'immo-manager' ); ?>
                            </span>
                            <?php if ( (int) $pk['outdoor']['total'] > 0 ) : ?>
                                <span class="immo-parking-total"><?php printf( esc_html__( '%d Plätze gesamt', 'immo-manager' ), (int) $pk['outdoor']['total'] ); ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
            <?php if ( ! empty( $pk['notes'] ) ) : ?>
                <p class="immo-parking-notes"><?php echo esc_html( $pk['notes'] ); ?></p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
```

**Format-Helper:** `$this->format_price()` muss in dem Template-Kontext ggf. via `\ImmoManager\REST_API` aufgerufen werden — alternativ `number_format_i18n()` mit Currency-Symbol aus `Settings::get('currency_symbol')`. Im Plan wird die korrekte Variante festgeklopft.

### Sektion 2: Quick-Info-Modal — Zusatzflächen

Im JS-Teil der Quick-Info (sucht aktuell `unit.area`, `unit.rooms` etc.) zusätzlich die vier Zusatzflächen rendern, wenn > 0:

```js
const extraAreas = [
  unit.balcony_area > 0 ? `🏔️ Balkon: ${unit.balcony_area} m²` : null,
  unit.loggia_area  > 0 ? `🏛️ Loggia: ${unit.loggia_area} m²`   : null,
  unit.garden_area  > 0 ? `🌿 Garten: ${unit.garden_area} m²`   : null,
  unit.cellar_area  > 0 ? `🏚️ Keller: ${unit.cellar_area} m²`   : null,
].filter(Boolean);
```

Plus Stellplatz-Inkludierung wenn `unit.parking.garage_count > 0` oder `outdoor_count > 0`:
- „inkl. 1× Tiefgarage" oder „inkl. 2× Außenstellplatz"
- Override-Preis (falls gesetzt) als Hinweis: „Sonderkondition: 18.000 €"

**Plan klärt:** Wo genau (welches JS-File / welche Stelle) eingebaut.

## Client-Anzeige (`immo-client/templates/single-project.php`)

### Sektion 1: Stellplatz-Sektion

Neue `<section class="immo-section immo-project-parking">` zwischen „Gemeinschafts-Ausstattung" und „CTA-Banner". Inhalt analog zum Manager — gleiche Datenstruktur (REST liefert dieselben Felder), gleiches Markup, kompatibles CSS in `project-detail.css`.

```php
<?php
$pk = $meta['parking'] ?? array();
$has_garage  = ! empty( $pk['garage']['available'] );
$has_outdoor = ! empty( $pk['outdoor']['available'] );
if ( $has_garage || $has_outdoor || ! empty( $pk['notes'] ) ) :
?>
<section class="immo-section immo-project-parking">
    <h2>Stellplätze</h2>
    <ul class="immo-parking-list">
        <!-- analog Manager-Markup, Texte gleich -->
    </ul>
    <?php if ( ! empty( $pk['notes'] ) ) : ?>
        <p class="immo-parking-notes"><?php echo esc_html( $pk['notes'] ); ?></p>
    <?php endif; ?>
</section>
<?php endif; ?>
```

CSS-Klassen `.immo-parking-list`, `.immo-parking-item`, `.immo-parking-flag`, `.immo-parking-flag-required` (rot), `.immo-parking-flag-optional` (grau), `.immo-parking-notes` werden in `project-detail.css` ergänzt.

### Sektion 2: Lightbox-Datenpool — Zusatzflächen

In `templates/single-project.php` im `<div id="immo-unit-lightbox-data">`-Block (ab Zeile ~387) und in `templates/units-lightbox-data.php` (Shortcode-Variante) jeweils die `<ul class="immo-unit-quick-facts">` um die Zusatzflächen-Items erweitern (analog der bestehenden `📐 Wohnfläche`-Items):

```php
<?php if ( ! empty( $unit['balcony_area'] ) && (float) $unit['balcony_area'] > 0 ) : ?>
    <li><span class="ico" aria-hidden="true">🏔️</span><span class="lab">Balkon</span><strong><?php echo esc_html( $unit['balcony_area'] ); ?> m²</strong></li>
<?php endif; ?>
<?php if ( ! empty( $unit['loggia_area'] ) && (float) $unit['loggia_area'] > 0 ) : ?>
    <li><span class="ico" aria-hidden="true">🏛️</span><span class="lab">Loggia</span><strong><?php echo esc_html( $unit['loggia_area'] ); ?> m²</strong></li>
<?php endif; ?>
<?php if ( ! empty( $unit['garden_area'] ) && (float) $unit['garden_area'] > 0 ) : ?>
    <li><span class="ico" aria-hidden="true">🌿</span><span class="lab">Garten</span><strong><?php echo esc_html( $unit['garden_area'] ); ?> m²</strong></li>
<?php endif; ?>
<?php if ( ! empty( $unit['cellar_area'] ) && (float) $unit['cellar_area'] > 0 ) : ?>
    <li><span class="ico" aria-hidden="true">🏚️</span><span class="lab">Keller</span><strong><?php echo esc_html( $unit['cellar_area'] ); ?> m²</strong></li>
<?php endif; ?>
<?php
$pk_unit = $unit['parking'] ?? array();
if ( ! empty( $pk_unit['garage_count'] ) ) : ?>
    <li><span class="ico" aria-hidden="true">🅿️</span><span class="lab">Tiefgarage</span><strong>inkl. <?php echo (int) $pk_unit['garage_count']; ?>×</strong></li>
<?php endif; ?>
<?php if ( ! empty( $pk_unit['outdoor_count'] ) ) : ?>
    <li><span class="ico" aria-hidden="true">🚗</span><span class="lab">Stellplatz</span><strong>inkl. <?php echo (int) $pk_unit['outdoor_count']; ?>×</strong></li>
<?php endif; ?>
```

## Akzeptanzkriterien

1. `GET /wp-json/immo-manager/v1/units/{id}` enthält `balcony_area`, `loggia_area`, `garden_area`, `cellar_area`, `parking.garage_count`, `parking.outdoor_count`, `parking.garage_price_override`, `parking.outdoor_price_override`.
2. `GET /wp-json/immo-manager/v1/projects/{id}` enthält `meta.parking.garage.{available,total,price,required}`, `meta.parking.outdoor.{available,total,price,required}`, `meta.parking.notes`.
3. Auf Manager-Detailseite `/bauprojekt/{slug}` (im Manager-Plugin gerendert): Stellplatz-Akkordeon erscheint, wenn `garage.available` oder `outdoor.available` true ist; Quick-Info zeigt Zusatzflächen wenn > 0.
4. Auf Client-Detailseite `/bauprojekt/{slug}` (im immo-client gerendert): identische Stellplatz-Sektion + Lightbox zeigt Zusatzflächen.
5. Wenn keine Stellplatz-Konfiguration und keine Zusatzflächen gesetzt sind: alte Darstellung unverändert (kein leeres Akkordeon, keine leeren Listen-Items).
6. Help-Doku in beiden Plugins erwähnt die neuen REST-Felder kurz.

## Migrations-Hinweis

Keine DB-Änderungen in Phase 2. REST-Cache des `immo-client` muss ggf. nach Deploy geleert werden (vorhandener „Cache leeren"-Button auf der Help-Seite).

## Offene Punkte

- **Override-Preis-Anzeige in Quick-Info:** zeigen wir `garage_price_override` als „Sonderkondition X €", oder schlicht die berechnete Inklusion ohne Override-Hinweis? **Empfehlung:** Override nur als kleiner Sub-Hinweis, damit die Quick-Info nicht überladen wird. Im Plan festgeklopft.
- **Help-Doku-Detail:** ein Satz pro Feldgruppe oder ausführliche Tabelle? **Empfehlung:** ein Satz pro Gruppe + Verweis auf die Wizard-Doku (kommt in Phase 3).
