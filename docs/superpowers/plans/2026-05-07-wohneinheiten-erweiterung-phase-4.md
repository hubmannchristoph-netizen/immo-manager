# Wohneinheiten-Erweiterung Phase 4 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans.

**Goal:** Tabellen-Anzeige um Zusatzflächen + Stellplatz-Counts erweitern, Mobile-Breiten-Bug defensiv fixen, Versions-Bump auf 1.2.0.

**Architecture:** Helper-Funktion `immo_units_extras()` rendert die Pill-Reihe pro Unit; in beiden Tabellen-Templates (Manager + Client + Client-Shortcode) eingebunden. CSS gleichgenamt, mit lokalen Variablen scoped pro Plugin. Defensive `max-width:100%`/`min-width:0`-Kette in beiden CSS-Files.

**Spec:** `docs/superpowers/specs/2026-05-07-wohneinheiten-erweiterung-phase-4-listen-und-mobile-design.md`

---

## File Structure

| Datei                                                | Aktion | Verantwortung                                    |
|------------------------------------------------------|--------|--------------------------------------------------|
| `immo-manager/templates/single-immo_mgr_project.php` | Modify | Pill-Reihe in Fläche-Zelle der Tabelle           |
| `immo-manager/public/css/frontend.css`               | Modify | `.immo-unit-extras` + Mobile-Patches             |
| `immo-manager/immo-manager.php` + `readme.txt`       | Modify | Version 1.1.0 → 1.2.0                            |
| `immo-client/templates/single-project.php`           | Modify | Pill-Reihe in Bezeichnungs-Zelle (Direct-Render) |
| `immo-client/templates/units-table.php`              | Modify | Pill-Reihe identisch im Shortcode                |
| `immo-client/assets/css/units-shortcode.css`         | Modify | `.immo-unit-extras` + Mobile-Patches             |
| `immo-client/assets/css/project-detail.css`          | Modify | Mobile-Patches für Direct-Render                 |
| `immo-client/immo-client.php`                        | Modify | Version 1.1.0 → 1.2.0                            |

---

### Task 1: Manager — Pills in Fläche-Zelle + CSS

**Files:**
- Modify: `immo-manager/templates/single-immo_mgr_project.php` (~Zeile 286 Tabellen-Zelle)
- Modify: `immo-manager/public/css/frontend.css`

- [ ] **Step 1: Fläche-Zelle um Pill-Reihe erweitern**

In `templates/single-immo_mgr_project.php` Zeile 286:

**Alt:**
```php
<td><?php echo $unit['area'] ? esc_html( number_format_i18n( (float) $unit['area'], 0 ) . ' m²' ) : '—'; ?></td>
```

**Neu:**
```php
<td>
    <?php echo $unit['area'] ? esc_html( number_format_i18n( (float) $unit['area'], 0 ) . ' m²' ) : '—'; ?>
    <?php
    $extras = array();
    if ( (float) ( $unit['balcony_area'] ?? 0 ) > 0 ) { $extras[] = '🏔️ ' . number_format_i18n( (float) $unit['balcony_area'], 0 ) . ' m²'; }
    if ( (float) ( $unit['loggia_area']  ?? 0 ) > 0 ) { $extras[] = '🏛️ ' . number_format_i18n( (float) $unit['loggia_area'],  0 ) . ' m²'; }
    if ( (float) ( $unit['garden_area']  ?? 0 ) > 0 ) { $extras[] = '🌿 ' . number_format_i18n( (float) $unit['garden_area'],  0 ) . ' m²'; }
    if ( (float) ( $unit['cellar_area']  ?? 0 ) > 0 ) { $extras[] = '🏚️ ' . number_format_i18n( (float) $unit['cellar_area'],  0 ) . ' m²'; }
    if ( (int)   ( $unit['parking']['garage_count']  ?? 0 ) > 0 ) { $extras[] = '🅿️ ×' . (int) $unit['parking']['garage_count']; }
    if ( (int)   ( $unit['parking']['outdoor_count'] ?? 0 ) > 0 ) { $extras[] = '🚗 ×' . (int) $unit['parking']['outdoor_count']; }
    if ( $extras ) :
    ?>
        <span class="immo-unit-extras">
            <?php foreach ( $extras as $e ) : ?>
                <span class="immo-unit-extra"><?php echo esc_html( $e ); ?></span>
            <?php endforeach; ?>
        </span>
    <?php endif; ?>
</td>
```

- [ ] **Step 2: CSS für Pills + Mobile-Patches**

Ans Ende von `immo-manager/public/css/frontend.css`:

```css
/* Pills mit Zusatzflächen unter dem Hauptwert */
.immo-unit-extras {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35em;
    margin-top: 0.35em;
}
.immo-unit-extra {
    font-size: 0.75rem;
    color: var(--immo-text-muted, #6b7280);
    padding: 0.15em 0.5em;
    background: var(--immo-bg-tinted, #f3f4f6);
    border-radius: 4px;
    line-height: 1.3;
    white-space: nowrap;
}

/* Mobile-Bug-Fix: Tabellen-Wrapper bricht aus Layout — defensive Constraints */
.immo-detail-content,
.immo-detail-content > .immo-accordion,
.immo-detail-content .immo-accordion-body,
.immo-detail-content .immo-units-table-wrap {
    max-width: 100%;
    min-width: 0;
}
@media (max-width: 768px) {
    .immo-units-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}
```

- [ ] **Step 3: Syntax + Commit**

```bash
php -l immo-manager/templates/single-immo_mgr_project.php
git add immo-manager/templates/single-immo_mgr_project.php immo-manager/public/css/frontend.css
git commit -m "feat(units): zusatzflaechen-pills in tabelle + mobile-overflow-fix"
```

---

### Task 2: Client — Pills in Bezeichnungs-Zelle (Direct-Render + Shortcode)

**Files:**
- Modify: `immo-client/templates/single-project.php` (~Zeile 365 Bezeichnungs-Zelle)
- Modify: `immo-client/templates/units-table.php` (~Zeile 75 Bezeichnungs-Zelle)
- Modify: `immo-client/assets/css/units-shortcode.css` + `assets/css/project-detail.css`

- [ ] **Step 1: `single-project.php` — Bezeichnungs-Zelle erweitern**

In `immo-client/templates/single-project.php` Zeile 365:

**Alt:**
```php
<td class="col-title"><?php echo esc_html( $unit_title ); ?></td>
```

**Neu:**
```php
<td class="col-title">
    <?php echo esc_html( $unit_title ); ?>
    <?php
    $extras = array();
    if ( (float) ( $unit['balcony_area'] ?? 0 ) > 0 ) { $extras[] = '🏔️ ' . number_format_i18n( (float) $unit['balcony_area'], 0 ) . ' m²'; }
    if ( (float) ( $unit['loggia_area']  ?? 0 ) > 0 ) { $extras[] = '🏛️ ' . number_format_i18n( (float) $unit['loggia_area'],  0 ) . ' m²'; }
    if ( (float) ( $unit['garden_area']  ?? 0 ) > 0 ) { $extras[] = '🌿 ' . number_format_i18n( (float) $unit['garden_area'],  0 ) . ' m²'; }
    if ( (float) ( $unit['cellar_area']  ?? 0 ) > 0 ) { $extras[] = '🏚️ ' . number_format_i18n( (float) $unit['cellar_area'],  0 ) . ' m²'; }
    if ( (int)   ( $unit['parking']['garage_count']  ?? 0 ) > 0 ) { $extras[] = '🅿️ ×' . (int) $unit['parking']['garage_count']; }
    if ( (int)   ( $unit['parking']['outdoor_count'] ?? 0 ) > 0 ) { $extras[] = '🚗 ×' . (int) $unit['parking']['outdoor_count']; }
    if ( $extras ) :
    ?>
        <span class="immo-unit-extras">
            <?php foreach ( $extras as $e ) : ?>
                <span class="immo-unit-extra"><?php echo esc_html( $e ); ?></span>
            <?php endforeach; ?>
        </span>
    <?php endif; ?>
</td>
```

- [ ] **Step 2: `units-table.php` (Shortcode) — gleiche Änderung**

In `immo-client/templates/units-table.php` Zeile 75 nach gleichem Schema. Achtung: Da nutzen wir `$unit_title` analog. Block 1:1 wie oben.

- [ ] **Step 3: CSS in `units-shortcode.css` ergänzen**

Ans Ende von `immo-client/assets/css/units-shortcode.css`:

```css
/* Pills mit Zusatzflächen */
.immo-unit-extras {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35em;
    margin-top: 0.35em;
}
.immo-unit-extra {
    font-size: 0.75rem;
    color: #6b7280;
    padding: 0.15em 0.5em;
    background: #f3f4f6;
    border-radius: 4px;
    line-height: 1.3;
    white-space: nowrap;
}

/* Mobile-Bug-Fix für Shortcode-Tabelle */
.immo-units-layout-table .immo-unit-table-wrapper {
    max-width: 100%;
    min-width: 0;
}
@media (max-width: 768px) {
    .immo-units-layout-table .immo-unit-table-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}
```

- [ ] **Step 4: CSS in `project-detail.css` (Direct-Render-Mobile-Fix)**

Ans Ende von `immo-client/assets/css/project-detail.css`:

```css
/* Pills mit Zusatzflächen (Phase 4) */
.immo-project-singlecol .immo-unit-extras {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35em;
    margin-top: 0.35em;
}
.immo-project-singlecol .immo-unit-extra {
    font-size: 0.75rem;
    color: var(--ip-text-muted);
    padding: 0.15em 0.5em;
    background: var(--ip-bg-tinted);
    border-radius: 4px;
    line-height: 1.3;
    white-space: nowrap;
}

/* Mobile-Bug-Fix: defensive Container-Constraints */
.immo-project-singlecol .immo-project-content,
.immo-project-singlecol .immo-project-content > .immo-section,
.immo-project-singlecol .immo-units-list,
.immo-project-singlecol .immo-unit-table-wrapper {
    max-width: 100%;
    min-width: 0;
}
@media (max-width: 768px) {
    .immo-project-singlecol .immo-unit-table-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}
```

- [ ] **Step 5: Syntax + Commit**

```bash
php -l immo-client/templates/single-project.php
php -l immo-client/templates/units-table.php
git add immo-client/templates/single-project.php immo-client/templates/units-table.php immo-client/assets/css/units-shortcode.css immo-client/assets/css/project-detail.css
git commit -m "feat(units): zusatzflaechen-pills in tabelle + mobile-overflow-fix"
```

---

### Task 3: Versions-Bump auf 1.2.0 (beide Plugins)

**Files:**
- Modify: `immo-manager/immo-manager.php` (Header + Konstante)
- Modify: `immo-manager/readme.txt` (Stable tag)
- Modify: `immo-client/immo-client.php` (Header + Konstante)

- [ ] **Step 1: Manager**

In `immo-manager/immo-manager.php`:
- Header `Version: 1.1.0` → `Version: 1.2.0`
- `define( 'IMMO_MANAGER_VERSION', '1.1.0' );` → `'1.2.0'`

In `immo-manager/readme.txt`:
- `Stable tag: 1.1.0` → `Stable tag: 1.2.0`

- [ ] **Step 2: Client**

In `immo-client/immo-client.php`:
- Header `Version: 1.1.0` → `Version: 1.2.0`
- `define('IMMO_CLIENT_VERSION', '1.1.0');` → `'1.2.0'`

- [ ] **Step 3: Commits**

```bash
cd immo-manager && git add immo-manager.php readme.txt && git commit -m "chore(release): version 1.2.0 — wohneinheiten-erweiterung komplett"
cd ../immo-client && git add immo-client.php && git commit -m "chore(release): version 1.2.0 — wohneinheiten-erweiterung komplett"
```

---

### Task 4: Spec auf „Implemented" + Final-Commit

- [ ] **Step 1: Spec-Status anpassen**

```markdown
**Status:** Implemented (2026-05-07)
```

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/specs/2026-05-07-wohneinheiten-erweiterung-phase-4-listen-und-mobile-design.md
git commit -m "docs(spec): phase 4 als implemented markiert"
```

---

## Self-Review

**1. Spec coverage:**
- Pills in Manager-Tabelle → Task 1 ✓
- Pills in Client-Tabelle (Direct + Shortcode) → Task 2 ✓
- Mobile-Defensive-Patches → Task 1 + 2 ✓
- Versions-Bump 1.2.0 → Task 3 ✓

**2. Placeholder scan:** Keine TBDs.

**3. Type consistency:** Pills-Klassen identisch Manager/Client (`.immo-unit-extras`, `.immo-unit-extra`); CSS-Werte gescoped per Plugin via Custom Properties.

**Hinweis:** Sollte der Mobile-Bug nach diesem Patch noch nicht behoben sein, bietet sich eine Playwright-Sitzung mit Live-URL als Folgeaktion an — dann lässt sich das tatsächliche Element identifizieren das die Breite sprengt.
