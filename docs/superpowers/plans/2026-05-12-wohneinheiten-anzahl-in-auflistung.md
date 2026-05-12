# Wohneinheiten-Anzahl in der Immobilien-Auflistung — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Property-Cards in der Immobilien-Auflistung zeigen einen Eckdaten-Eintrag wie „🏘️ 2 von 4 verfügbar" (bzw. „🏘️ 4 Wohneinheiten – ausverkauft"), wenn der Immobilie Wohneinheiten direkt zugeordnet sind.

**Architecture:** `format_property()` in der immo-manager-REST liefert ein neues Top-Level-Feld `unit_stats` (Status-Counts + `total`), berechnet über `units.property_id` durch eine neue Methode `Units::count_by_property()`. Die Card-Templates beider Plugins (immo-manager `templates/parts/property-card.php`, immo-client `templates/partial-property-card.php`) lesen das Feld und rendern den Eintrag, falls `total > 0`.

**Tech Stack:** PHP, WordPress, `$wpdb`, WordPress-i18n (`__`, `_n`). Keine automatisierten Tests im Projekt — Verifikation per `php -l` und manueller Sichtprüfung.

**Hinweis Repos:** Tasks 1–3 betreffen das Repo `C:\Users\ch\Desktop\Projekte\immo-manager` (Branch `feat/units-erweiterung`). Task 4 betrifft das Repo `C:\Users\ch\Desktop\Projekte\immo-client` (Branch `feat/units-erweiterung`). Jeweils im richtigen Repo committen.

**Spec:** `docs/superpowers/specs/2026-05-12-wohneinheiten-anzahl-in-auflistung-design.md`

---

## File Structure

- `immo-manager/includes/class-units.php` — neue statische Methode `count_by_property( int $property_id ): array` (spiegelt `count_by_status`).
- `immo-manager/includes/class-rest-api.php` — `format_property()` ergänzt `$result['unit_stats']`.
- `immo-manager/templates/parts/property-card.php` — neuer `<li>` in der `.immo-card-facts`-Liste.
- `immo-client/templates/partial-property-card.php` — neuer `<li class="immo-card__spec">` in der `.immo-card__specs`-Liste; Render-Bedingung der Liste erweitert.

---

### Task 1: `Units::count_by_property()` (immo-manager)

**Files:**
- Modify: `includes/class-units.php` (Methode direkt nach `count_by_status()` einfügen, ca. nach Zeile 244)

- [ ] **Step 1: Methode hinzufügen**

In `includes/class-units.php` direkt nach dem Ende der Methode `count_by_status()` (nach deren schließender `}`) einfügen:

```php
	/**
	 * Status-Counts pro Immobilie (direkt zugeordnete Wohneinheiten).
	 *
	 * @param int $property_id Property-Post-ID.
	 *
	 * @return array<string, int> Alle Stati aus self::STATUSES, fehlende mit 0.
	 */
	public static function count_by_property( int $property_id ): array {
		global $wpdb;
		$table = Database::units_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS cnt FROM {$table} WHERE property_id = %d GROUP BY status",
				$property_id
			),
			ARRAY_A
		);

		$counts = array_fill_keys( self::STATUSES, 0 );
		foreach ( (array) $rows as $row ) {
			$status = (string) ( $row['status'] ?? '' );
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ] = (int) $row['cnt'];
			}
		}

		return $counts;
	}
```

- [ ] **Step 2: Syntax prüfen**

Run: `php -l includes/class-units.php`
Expected: `No syntax errors detected in includes/class-units.php`

- [ ] **Step 3: Commit**

```bash
git add includes/class-units.php
git commit -m "feat(units): count_by_property — status-counts pro immobilie"
```

---

### Task 2: `unit_stats` in `format_property()` (immo-manager)

**Files:**
- Modify: `includes/class-rest-api.php` (in `format_property()`, zwischen Zeile 1117 `);` und Zeile 1119 `if ( $full ) {`)

- [ ] **Step 1: `unit_stats` ergänzen**

In `includes/class-rest-api.php`, in der Methode `format_property()`, direkt nach dem Abschluss des `$result = array( ... );` (die Zeile mit `);`) und vor `if ( $full ) {` einfügen:

```php
		$u_counts             = Units::count_by_property( $id );
		$result['unit_stats'] = array_merge(
			$u_counts,
			array( 'total' => array_sum( $u_counts ) )
		);
```

Ergebnis: `$result['unit_stats']` enthält `available`, `reserved`, `sold`, `rented` (jeweils int, 0 wenn keine) und `total` (Summe, 0 wenn keine zugeordneten Units).

- [ ] **Step 2: Syntax prüfen**

Run: `php -l includes/class-rest-api.php`
Expected: `No syntax errors detected in includes/class-rest-api.php`

- [ ] **Step 3: (optional, falls WP-Umgebung verfügbar) REST-Antwort sichten**

Eine Immobilie mit zugeordneten Units über die REST-Liste abrufen und prüfen, dass im Item ein `unit_stats`-Objekt mit `total` und `available` enthalten ist. Falls keine WP-Umgebung griffbereit: überspringen, in Task 5 manuell mitprüfen.

- [ ] **Step 4: Commit**

```bash
git add includes/class-rest-api.php
git commit -m "feat(rest): format_property liefert unit_stats (direkt zugeordnete units)"
```

---

### Task 3: Anzeige in der immo-manager Property-Card

**Files:**
- Modify: `templates/parts/property-card.php`

- [ ] **Step 1: Variablen berechnen**

In `templates/parts/property-card.php` im PHP-Block oben, direkt nach dem Block, der `$display_area` ermittelt (also nach dem `if ( $display_area <= 0 ) { ... }`), einfügen:

```php

// Wohneinheiten-Verfügbarkeit (nur wenn der Immobilie Units direkt zugeordnet sind).
$unit_total = (int) ( $property['unit_stats']['total'] ?? 0 );
$unit_avail = (int) ( $property['unit_stats']['available'] ?? 0 );
```

- [ ] **Step 2: `<li>` in die Eckdaten-Liste einfügen**

In derselben Datei, in der `<ul class="immo-card-facts" ...>`, direkt vor dem schließenden `</ul>` (nach dem `energy_class`-`<li>`-Block) einfügen:

```php
			<?php if ( $unit_total > 0 ) : ?>
				<li>
					<span aria-hidden="true">🏘️</span>
					<?php
					if ( $unit_avail > 0 ) {
						/* translators: 1: Anzahl verfügbarer Wohneinheiten, 2: Gesamtanzahl */
						echo esc_html( sprintf( __( '%1$d von %2$d verfügbar', 'immo-manager' ), $unit_avail, $unit_total ) );
					} else {
						/* translators: %d: Gesamtanzahl der Wohneinheiten */
						echo esc_html( sprintf( _n( '%d Wohneinheit – ausverkauft', '%d Wohneinheiten – ausverkauft', $unit_total, 'immo-manager' ), $unit_total ) );
					}
					?>
				</li>
			<?php endif; ?>
```

- [ ] **Step 3: Syntax prüfen**

Run: `php -l templates/parts/property-card.php`
Expected: `No syntax errors detected in templates/parts/property-card.php`

- [ ] **Step 4: Commit**

```bash
git add templates/parts/property-card.php
git commit -m "feat(card): wohneinheiten-verfuegbarkeit in der immobilien-auflistung"
```

---

### Task 4: Anzeige in der immo-client Property-Card

**Repo:** `C:\Users\ch\Desktop\Projekte\immo-client` (Branch `feat/units-erweiterung`)

**Files:**
- Modify: `templates/partial-property-card.php`

- [ ] **Step 1: Variablen berechnen**

In `templates/partial-property-card.php` im PHP-Block oben, nach der Zeile
`$energy    = isset($meta['energy_class']) ? trim((string) $meta['energy_class']) : '';`
einfügen:

```php

// Wohneinheiten-Verfügbarkeit (kommt als Top-Level-Feld aus der Manager-REST).
$unit_total = isset($item['unit_stats']['total'])     ? (int) $item['unit_stats']['total']     : 0;
$unit_avail = isset($item['unit_stats']['available']) ? (int) $item['unit_stats']['available'] : 0;
```

- [ ] **Step 2: Render-Bedingung der Specs-Liste erweitern**

In derselben Datei die Zeile

```php
        <?php if (!$is_project && ($area_value > 0 || $rooms > 0 || $bathrooms > 0 || $energy !== '')) : ?>
```

ersetzen durch

```php
        <?php if (!$is_project && ($area_value > 0 || $rooms > 0 || $bathrooms > 0 || $energy !== '' || $unit_total > 0)) : ?>
```

- [ ] **Step 3: `<li>` in die Specs-Liste einfügen**

In derselben `<ul class="immo-card__specs">`, direkt vor dem schließenden `</ul>` (nach dem `energy`-`<li>`-Block) einfügen:

```php
                <?php if ($unit_total > 0) : ?>
                    <li class="immo-card__spec" title="<?php esc_attr_e('Wohneinheiten', 'immo-client'); ?>">
                        <svg class="immo-card__spec-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 21V3h10v8h8v10H3zm2-2h6V5H5v14zm8 0h6v-6h-6v6zM7 7h2v2H7V7zm0 4h2v2H7v-2zm0 4h2v2H7v-2z"/></svg>
                        <span><?php
                        if ($unit_avail > 0) {
                            /* translators: 1: Anzahl verfügbarer Wohneinheiten, 2: Gesamtanzahl */
                            echo esc_html(sprintf(__('%1$d von %2$d verfügbar', 'immo-client'), $unit_avail, $unit_total));
                        } else {
                            /* translators: %d: Gesamtanzahl der Wohneinheiten */
                            echo esc_html(sprintf(_n('%d Wohneinheit – ausverkauft', '%d Wohneinheiten – ausverkauft', $unit_total, 'immo-client'), $unit_total));
                        }
                        ?></span>
                    </li>
                <?php endif; ?>
```

- [ ] **Step 4: Syntax prüfen**

Run: `php -l templates/partial-property-card.php`
Expected: `No syntax errors detected in templates/partial-property-card.php`

- [ ] **Step 5: Commit (im immo-client-Repo)**

```bash
git add templates/partial-property-card.php
git commit -m "feat(card): wohneinheiten-verfuegbarkeit in der immobilien-auflistung"
```

---

### Task 5: End-to-End-Verifikation (manuell)

**Files:** keine.

- [ ] **Step 1: Testdaten sicherstellen**

In einer WP-Test-/Stage-Umgebung: eine Immobilie mit mehreren zugeordneten Wohneinheiten anlegen bzw. vorhandene nutzen, z. B. 2× Status „verfügbar", 1× „reserviert", 1× „verkauft". Außerdem eine Immobilie, bei der alle zugeordneten Units „verkauft"/„vermietet"/„reserviert" sind. Außerdem eine normale Immobilie ohne zugeordnete Units.

- [ ] **Step 2: immo-manager-Auflistung prüfen**

Archiv-Seite bzw. eine Seite mit `[immo_properties]` (und ggf. das Elementor-Properties-Widget) öffnen. Erwartet:
- Immobilie mit 2 frei / 4 gesamt → Eckdaten-Eintrag „🏘️ 2 von 4 verfügbar".
- Immobilie mit 0 frei → „🏘️ 4 Wohneinheiten – ausverkauft" (Singular bei 1).
- Immobilie ohne zugeordnete Units → kein solcher Eintrag.

- [ ] **Step 3: immo-client-Auflistung prüfen**

Falls der immo-client REST-Antworten cached: `Einstellungen → ImmoClient Hilfe → API-Cache leeren`. Dann die öffentliche Immobilien-Liste öffnen und dieselben drei Fälle prüfen (Spec-Eintrag mit Haus-Icon).

- [ ] **Step 4: Abschluss**

Wenn alles passt: dem Nutzer melden. Keine Code-Änderung in diesem Task; nichts zu committen. (Branches noch nicht nach `main` mergen — das entscheidet der Nutzer separat.)
