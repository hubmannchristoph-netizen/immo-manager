# Wohneinheiten-Erweiterung Phase 1 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Datenmodell der Wohneinheiten um Zusatzflächen (Balkon, Loggia, Garten, Keller) und Stellplatz-Konfiguration (Variante α: zentral pro Projekt + Override pro Einheit) erweitern.

**Architecture:** Custom-Tabelle `{prefix}immo_units` bekommt 8 neue Spalten. Bauprojekt-Post-Meta bekommt 9 neue Felder (`_immo_parking_*`). DB-Schema-Bump 1.6.0 → 1.7.0 triggert automatisch das `dbDelta()`-basierte Upgrade beim nächsten Request. Plugin-Version-Bump 1.0.0 → 1.1.0 + Author-Daten.

**Tech Stack:** PHP 7.4+, WordPress 5.9+, `dbDelta()` für Migration, `register_post_meta` für Meta-Felder, `wpdb` für Sanitize/Format-Mapping.

**Hinweis Tests:** Plugin hat keine automatisierte Test-Infrastruktur (kein PHPUnit-Setup vorhanden). Verifikation läuft manuell via SQL-Abfragen + WP-CLI/Browser.

**Spec:** `docs/superpowers/specs/2026-05-07-wohneinheiten-erweiterung-phase-1-datenmodell-design.md`

---

## File Structure

| Datei                                   | Aktion   | Verantwortung                                          |
|-----------------------------------------|----------|--------------------------------------------------------|
| `includes/class-database.php`           | Modify   | DB_VERSION-Konstante + `$sql_units`-CREATE-Statement   |
| `includes/class-units.php`              | Modify   | `sanitize()` + `column_formats()` um neue Felder erweitern |
| `includes/class-meta-fields.php`        | Modify   | `project_fields()` um neue Stellplatz-Meta-Felder erweitern |
| `immo-manager.php`                      | Modify   | Plugin-Header + Version-Konstante + Author/Plugin-URI  |
| `readme.txt`                            | Modify   | Stable tag + Contributors                              |

Alle Änderungen sind in sich geschlossen — nach jeder Task gibt es einen sauberen Commit.

---

### Task 1: DB-Schema-Erweiterung in `class-database.php`

**Files:**
- Modify: `includes/class-database.php` (DB_VERSION line 23 + `$sql_units` line 120-156)

- [ ] **Step 1: DB_VERSION-Konstante anheben**

In `includes/class-database.php` Zeile 23:

```php
public const DB_VERSION = '1.7.0';
```

- [ ] **Step 2: Neue Spalten im `$sql_units`-CREATE-Statement ergänzen**

Im `$sql_units`-String, nach der Zeile `usable_area DECIMAL(10,2) NOT NULL DEFAULT 0.00,`, vor `rooms INT NOT NULL DEFAULT 0,`:

```php
usable_area DECIMAL(10,2) NOT NULL DEFAULT 0.00,
balcony_area DECIMAL(8,2) NOT NULL DEFAULT 0.00,
loggia_area DECIMAL(8,2) NOT NULL DEFAULT 0.00,
garden_area DECIMAL(8,2) NOT NULL DEFAULT 0.00,
cellar_area DECIMAL(8,2) NOT NULL DEFAULT 0.00,
parking_garage_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
parking_outdoor_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
parking_garage_price_override DECIMAL(10,2) NULL DEFAULT NULL,
parking_outdoor_price_override DECIMAL(10,2) NULL DEFAULT NULL,
rooms INT NOT NULL DEFAULT 0,
```

**Wichtig:** dbDelta ist empfindlich — exakt zwei Leerzeichen nach `KEY` (kein Backtick), keine doppelten Whitespaces. Der vorhandene Block hält das bereits ein, neue Zeilen einfach gleich einrücken.

- [ ] **Step 3: PHP-Syntax-Check**

```bash
php -l includes/class-database.php
```

Erwartung: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add includes/class-database.php
git commit -m "feat(db): schema 1.7.0 — zusatzflaechen + stellplatz-counts an units"
```

---

### Task 2: Sanitize + Format-Map in `class-units.php`

**Files:**
- Modify: `includes/class-units.php` (`sanitize()` ab Zeile 297, `column_formats()` ab Zeile 394)

- [ ] **Step 1: Neue Sanitize-Blöcke in `Units::sanitize()` ergänzen**

In `includes/class-units.php`, in der Methode `sanitize()` direkt nach dem Block für `usable_area` (Zeile ~321), vor dem `foreach ( array( 'rooms', 'bedrooms', 'bathrooms' ) … )`:

```php
		if ( isset( $data['usable_area'] ) ) {
			$out['usable_area'] = max( 0, (float) $data['usable_area'] );
		}
		foreach ( array( 'balcony_area', 'loggia_area', 'garden_area', 'cellar_area' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$out[ $k ] = max( 0, (float) $data[ $k ] );
			}
		}
		foreach ( array( 'parking_garage_count', 'parking_outdoor_count' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$out[ $k ] = max( 0, min( 255, (int) $data[ $k ] ) );
			}
		}
		foreach ( array( 'parking_garage_price_override', 'parking_outdoor_price_override' ) as $k ) {
			if ( array_key_exists( $k, $data ) ) {
				$val       = $data[ $k ];
				$out[ $k ] = ( '' === $val || null === $val ) ? null : max( 0, (float) $val );
			}
		}
		foreach ( array( 'rooms', 'bedrooms', 'bathrooms' ) as $k ) {
```

- [ ] **Step 2: Format-Map in `column_formats()` erweitern**

In `includes/class-units.php`, im `$map`-Array der Methode `column_formats()` (ab Zeile ~396), nach `'usable_area' => '%f',`:

```php
				'usable_area'    => '%f',
				'balcony_area'   => '%f',
				'loggia_area'    => '%f',
				'garden_area'    => '%f',
				'cellar_area'    => '%f',
				'parking_garage_count'  => '%d',
				'parking_outdoor_count' => '%d',
				'parking_garage_price_override'  => '%f',
				'parking_outdoor_price_override' => '%f',
				'rooms'          => '%d',
```

- [ ] **Step 3: PHP-Syntax-Check**

```bash
php -l includes/class-units.php
```

Erwartung: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add includes/class-units.php
git commit -m "feat(units): sanitize + format-map fuer neue felder (zusatzflaechen + stellplatz)"
```

---

### Task 3: Project-Meta-Felder in `class-meta-fields.php`

**Files:**
- Modify: `includes/class-meta-fields.php` (`project_fields()` ab Zeile 112)

- [ ] **Step 1: Neue Stellplatz-Meta-Felder ergänzen**

In `includes/class-meta-fields.php`, im Return-Array von `project_fields()` direkt nach den Layout-Override-Zeilen (`'_immo_hero_type' …` Zeile 141), vor dem schließenden `);`:

```php
			'_immo_hero_type'         => array( 'type' => 'string',  'enum' => array( '', 'full', 'contained' ), 'default' => '' ),

			// Stellplatz-Konfiguration (Phase 1 Wohneinheiten-Erweiterung).
			'_immo_parking_garage_available'  => array( 'type' => 'boolean', 'default' => false ),
			'_immo_parking_garage_total'      => array( 'type' => 'integer', 'default' => 0 ),
			'_immo_parking_garage_price'      => array( 'type' => 'number',  'default' => 0 ),
			'_immo_parking_garage_required'   => array( 'type' => 'boolean', 'default' => false ),
			'_immo_parking_outdoor_available' => array( 'type' => 'boolean', 'default' => false ),
			'_immo_parking_outdoor_total'     => array( 'type' => 'integer', 'default' => 0 ),
			'_immo_parking_outdoor_price'     => array( 'type' => 'number',  'default' => 0 ),
			'_immo_parking_outdoor_required'  => array( 'type' => 'boolean', 'default' => false ),
			'_immo_parking_notes'             => array( 'type' => 'string',  'default' => '' ),
		);
	}
```

- [ ] **Step 2: PHP-Syntax-Check**

```bash
php -l includes/class-meta-fields.php
```

Erwartung: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add includes/class-meta-fields.php
git commit -m "feat(meta): project-meta fuer stellplatz-konfiguration"
```

---

### Task 4: Plugin-Header — Version-Bump + Author-Daten

**Files:**
- Modify: `immo-manager.php` (Zeilen 1-25)
- Modify: `readme.txt` (Zeilen 1-9)

- [ ] **Step 1: Plugin-Header in `immo-manager.php` aktualisieren**

In `immo-manager.php` die Zeilen 3-12 ersetzen:

```php
 * Plugin Name:       Immo Manager
 * Plugin URI:        mailto:hubmann.christoph@gmail.com
 * Description:       Professionelle Immobilienverwaltung für Österreich – Verkauf, Vermietung und Bauprojekte mit Wohneinheiten.
 * Version:           1.1.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Hubmann Christoph
 * Author URI:        mailto:hubmann.christoph@gmail.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
```

- [ ] **Step 2: `IMMO_MANAGER_VERSION`-Konstante anheben**

In `immo-manager.php` Zeile 25:

```php
define( 'IMMO_MANAGER_VERSION', '1.1.0' );
```

- [ ] **Step 3: `readme.txt` aktualisieren**

In `readme.txt` Zeilen 2 und 7 anpassen:

```
=== Immo Manager ===
Contributors: hubmannchristoph
Tags: immobilien, real-estate, vermietung, verkauf, austria, headless, rest-api
Requires at least: 5.9
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
```

- [ ] **Step 4: PHP-Syntax-Check**

```bash
php -l immo-manager.php
```

Erwartung: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add immo-manager.php readme.txt
git commit -m "chore(release): version 1.1.0 + author-daten Hubmann Christoph"
```

---

### Task 5: Manuelle Verifikation

Voraussetzung: lokale WordPress-Installation mit aktivem Plugin (oder WP-CLI verfügbar).

- [ ] **Step 1: Plugin neu laden + DB-Migration triggern**

Im WP-Admin Plugin deaktivieren und wieder aktivieren — oder ein beliebiger Frontend/Admin-Request triggert `Database::maybe_upgrade()` → `Database::install()`.

- [ ] **Step 2: DB-Version prüfen**

Via WP-CLI oder phpMyAdmin:

```sql
SELECT option_value FROM wp_options WHERE option_name = 'immo_manager_db_version';
```

Erwartung: `1.7.0`

- [ ] **Step 3: Neue Unit-Spalten prüfen**

```sql
SHOW COLUMNS FROM wp_immo_units;
```

Erwartung: 8 zusätzliche Spalten vorhanden:
- `balcony_area`, `loggia_area`, `garden_area`, `cellar_area` (DECIMAL(8,2), default 0.00)
- `parking_garage_count`, `parking_outdoor_count` (TINYINT UNSIGNED, default 0)
- `parking_garage_price_override`, `parking_outdoor_price_override` (DECIMAL(10,2), default NULL)

- [ ] **Step 4: Insert-Test mit neuen Feldern**

Via WP-CLI:

```bash
wp eval 'var_dump( \ImmoManager\Units::create( array(
    "project_id"           => 1,
    "unit_number"          => "TEST-PHASE1",
    "area"                 => 75,
    "balcony_area"         => 8.5,
    "loggia_area"          => 0,
    "garden_area"          => 0,
    "cellar_area"          => 5.2,
    "parking_garage_count" => 1,
    "parking_outdoor_count" => 0,
    "parking_garage_price_override" => null,
    "parking_outdoor_price_override" => null,
    "status"               => "available",
) ) );'
```

Erwartung: numerische ID (kein `false`).

- [ ] **Step 5: Read-Back-Test**

```bash
wp eval '$id = (int) trim( shell_exec( "wp db query \"SELECT id FROM wp_immo_units WHERE unit_number=TEST-PHASE1 LIMIT 1\" --skip-column-names" ) ); var_dump( \ImmoManager\Units::get( $id ) );'
```

Erwartung: Array mit allen neuen Feldern in den Werten oben.

- [ ] **Step 6: Project-Meta-Test**

```bash
wp eval '$pid = 1; update_post_meta( $pid, "_immo_parking_garage_available", true ); update_post_meta( $pid, "_immo_parking_garage_price", 25000 ); var_dump( get_post_meta( $pid, "_immo_parking_garage_available", true ), get_post_meta( $pid, "_immo_parking_garage_price", true ) );'
```

(Project-ID `1` ist Beispiel — IDs auf System anpassen, Demo-Project nehmen.)

Erwartung: `bool(true)` und `int(25000)` bzw. `string(5) "25000"`.

- [ ] **Step 7: Test-Daten aufräumen**

```bash
wp eval 'global $wpdb; $wpdb->delete( $wpdb->prefix . "immo_units", array( "unit_number" => "TEST-PHASE1" ) );'
```

- [ ] **Step 8: Plugin-Header verifizieren**

Im WP-Admin → Plugins → Eintrag „Immo Manager":
- Version: `1.1.0`
- Autor: `Hubmann Christoph`

---

### Task 6: Spec als „Implemented" markieren + Final-Commit

**Files:**
- Modify: `docs/superpowers/specs/2026-05-07-wohneinheiten-erweiterung-phase-1-datenmodell-design.md`

- [ ] **Step 1: Status im Spec aktualisieren**

In `docs/superpowers/specs/2026-05-07-wohneinheiten-erweiterung-phase-1-datenmodell-design.md` Zeile 3:

```markdown
**Status:** Implemented (2026-05-07)
```

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/specs/2026-05-07-wohneinheiten-erweiterung-phase-1-datenmodell-design.md
git commit -m "docs(spec): phase 1 als implemented markiert"
```

---

## Self-Review

**1. Spec coverage:**
- Acht neue Unit-Spalten → Task 1 ✓
- Neun neue Project-Meta-Felder → Task 3 ✓
- DB-Version-Bump 1.6.0 → 1.7.0 → Task 1 ✓
- Sanitize + column_formats → Task 2 ✓
- Migration via `dbDelta()` automatisch → durch DB_VERSION-Bump getriggert ✓
- Akzeptanzkriterien (DB-Version, Spalten, Insert/Read, Meta) → Task 5 ✓

**2. Placeholder scan:** Keine TBDs, keine „add appropriate …", alle Code-Blöcke vollständig.

**3. Type consistency:** Feldnamen einheitlich zwischen Spec / DB-Schema / Sanitize / Format-Map / Meta-Keys / Verifikations-Test. `parking_garage_price_override` und `parking_outdoor_price_override` durchgängig mit NULL-Default.

**Zusatz-Hinweis:** Plugin-Version-Bump und Autor-Daten sind in Task 4 enthalten — kommen nicht aus dem Spec, sondern aus der User-Anweisung beim Übergang zur Implementierung.

---

## Nach Phase 1

Anschluss-Phasen (separate Specs + Pläne):

- Phase 2 — REST-Sichtbarkeit + immo-client-Anzeige
- Phase 3 — Wizard-Schritt für Stellplatz + Zusatzflächen
- Phase 4 — Listen-/Lightbox-Anzeige + Mobile-Layout (inkl. Mobile-Bug-Diagnose mit Playwright)
