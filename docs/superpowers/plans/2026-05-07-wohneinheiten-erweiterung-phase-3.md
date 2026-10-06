# Wohneinheiten-Erweiterung Phase 3 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Backend-Eingabe-UI für die neuen Felder in beiden Wohneinheits-Editoren (Wizard + Project-Metabox) und neue Stellplatz-Metabox auf Bauprojekt-Ebene.

**Architecture:** Neue Metabox `immo_project_parking` registrieren; bestehende Modal-Templates um Felder erweitern (JS bleibt unverändert dank generischer name-basierter Logik). `Units::hydrate()` typisiert die neuen Spalten.

**Spec:** `docs/superpowers/specs/2026-05-07-wohneinheiten-erweiterung-phase-3-wizard-design.md`

---

## File Structure

| Datei                                              | Aktion | Verantwortung                              |
|----------------------------------------------------|--------|--------------------------------------------|
| `includes/class-units.php`                         | Modify | `hydrate()` typisiert neue Felder          |
| `includes/class-metaboxes.php`                     | Modify | Stellplatz-Metabox registrieren + rendern  |
| `templates/admin/metabox-project-units.php`        | Modify | Modal-Editor um neue Inputs                |
| `templates/wizard/wizard-form.php`                 | Modify | Wizard-Modal um identische Inputs          |

---

### Task 1: `Units::hydrate()` typisieren

**Files:**
- Modify: `includes/class-units.php` (`hydrate()` ~Zeile 243)

- [ ] **Step 1: Casts für neue Felder ergänzen**

In `class-units.php` direkt nach `$row['usable_area'] = (float) ( $row['usable_area'] ?? 0 );`:

```php
		$row['usable_area']  = (float) ( $row['usable_area'] ?? 0 );
		$row['balcony_area'] = (float) ( $row['balcony_area'] ?? 0 );
		$row['loggia_area']  = (float) ( $row['loggia_area']  ?? 0 );
		$row['garden_area']  = (float) ( $row['garden_area']  ?? 0 );
		$row['cellar_area']  = (float) ( $row['cellar_area']  ?? 0 );
		$row['parking_garage_count']  = (int) ( $row['parking_garage_count']  ?? 0 );
		$row['parking_outdoor_count'] = (int) ( $row['parking_outdoor_count'] ?? 0 );
		$row['parking_garage_price_override']  = ( isset( $row['parking_garage_price_override'] )  && '' !== $row['parking_garage_price_override']  && null !== $row['parking_garage_price_override'] )  ? (float) $row['parking_garage_price_override']  : null;
		$row['parking_outdoor_price_override'] = ( isset( $row['parking_outdoor_price_override'] ) && '' !== $row['parking_outdoor_price_override'] && null !== $row['parking_outdoor_price_override'] ) ? (float) $row['parking_outdoor_price_override'] : null;
		$row['rooms']        = (int) ( $row['rooms'] ?? 0 );
```

- [ ] **Step 2: Syntax + Commit**

```bash
php -l includes/class-units.php
git add includes/class-units.php
git commit -m "feat(units): hydrate() typisiert neue zusatzflaechen + stellplatz-felder"
```

---

### Task 2: Stellplatz-Metabox auf Bauprojekt

**Files:**
- Modify: `includes/class-metaboxes.php` (`add_meta_box()`-Aufrufe + neue Methode)

- [ ] **Step 1: Metabox registrieren**

In `class-metaboxes.php` Zeile 64-71 (Project-Metaboxen) zwischen `immo_project_features` und `immo_project_units` einfügen:

```php
		add_meta_box( 'immo_project_features',  __( 'Gemeinschafts-Ausstattung', 'immo-manager' ), array( $this, 'render_features' ),          PostTypes::POST_TYPE_PROJECT,  'normal', 'default' );
		add_meta_box( 'immo_project_parking',   __( 'Stellplätze', 'immo-manager' ),               array( $this, 'render_project_parking' ),   PostTypes::POST_TYPE_PROJECT,  'normal', 'default' );
		add_meta_box( 'immo_project_units',     __( 'Wohneinheiten', 'immo-manager' ),             array( $this, 'render_project_units' ),     PostTypes::POST_TYPE_PROJECT,  'normal', 'default' );
```

- [ ] **Step 2: Render-Methode ergänzen**

In `class-metaboxes.php` direkt nach `render_project_details()` (Ende ~Zeile 531) und vor `render_project_units()`:

```php
	/**
	 * Metabox: Stellplatz-Konfiguration des Bauprojekts.
	 *
	 * @param \WP_Post $post Post.
	 *
	 * @return void
	 */
	public function render_project_parking( \WP_Post $post ): void {
		$meta = $this->get_meta( $post->ID, MetaFields::project_fields() );
		?>
		<table class="form-table immo-form">
			<tr><th colspan="2"><h3 style="margin: 0.5em 0;"><?php esc_html_e( 'Tiefgarage', 'immo-manager' ); ?></h3></th></tr>
			<tr>
				<th><?php esc_html_e( 'Verfügbar', 'immo-manager' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="immo_meta[_immo_parking_garage_available]" value="1" <?php checked( ! empty( $meta['_immo_parking_garage_available'] ) ); ?>>
						<?php esc_html_e( 'Tiefgaragenplätze sind im Projekt verfügbar', 'immo-manager' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="_immo_parking_garage_total"><?php esc_html_e( 'Anzahl gesamt', 'immo-manager' ); ?></label></th>
				<td><input type="number" id="_immo_parking_garage_total" name="immo_meta[_immo_parking_garage_total]" min="0" step="1" value="<?php echo esc_attr( (string) ( $meta['_immo_parking_garage_total'] ?? 0 ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="_immo_parking_garage_price"><?php esc_html_e( 'Preis pro Platz', 'immo-manager' ); ?></label></th>
				<td><input type="number" id="_immo_parking_garage_price" name="immo_meta[_immo_parking_garage_price]" min="0" step="1" value="<?php echo esc_attr( (string) ( $meta['_immo_parking_garage_price'] ?? 0 ) ); ?>"> €</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Verpflichtend', 'immo-manager' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="immo_meta[_immo_parking_garage_required]" value="1" <?php checked( ! empty( $meta['_immo_parking_garage_required'] ) ); ?>>
						<?php esc_html_e( 'Beim Wohnungskauf verpflichtend zu erwerben', 'immo-manager' ); ?>
					</label>
				</td>
			</tr>

			<tr><th colspan="2"><h3 style="margin: 1.5em 0 0.5em;"><?php esc_html_e( 'Außen-Stellplatz', 'immo-manager' ); ?></h3></th></tr>
			<tr>
				<th><?php esc_html_e( 'Verfügbar', 'immo-manager' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="immo_meta[_immo_parking_outdoor_available]" value="1" <?php checked( ! empty( $meta['_immo_parking_outdoor_available'] ) ); ?>>
						<?php esc_html_e( 'Außen-Stellplätze sind im Projekt verfügbar', 'immo-manager' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="_immo_parking_outdoor_total"><?php esc_html_e( 'Anzahl gesamt', 'immo-manager' ); ?></label></th>
				<td><input type="number" id="_immo_parking_outdoor_total" name="immo_meta[_immo_parking_outdoor_total]" min="0" step="1" value="<?php echo esc_attr( (string) ( $meta['_immo_parking_outdoor_total'] ?? 0 ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="_immo_parking_outdoor_price"><?php esc_html_e( 'Preis pro Platz', 'immo-manager' ); ?></label></th>
				<td><input type="number" id="_immo_parking_outdoor_price" name="immo_meta[_immo_parking_outdoor_price]" min="0" step="1" value="<?php echo esc_attr( (string) ( $meta['_immo_parking_outdoor_price'] ?? 0 ) ); ?>"> €</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Verpflichtend', 'immo-manager' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="immo_meta[_immo_parking_outdoor_required]" value="1" <?php checked( ! empty( $meta['_immo_parking_outdoor_required'] ) ); ?>>
						<?php esc_html_e( 'Beim Wohnungskauf verpflichtend zu erwerben', 'immo-manager' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th><label for="_immo_parking_notes"><?php esc_html_e( 'Hinweis (Freitext)', 'immo-manager' ); ?></label></th>
				<td><textarea id="_immo_parking_notes" name="immo_meta[_immo_parking_notes]" rows="2" cols="60" placeholder="<?php esc_attr_e( 'z. B. „1 TG-Platz pro Einheit verpflichtend, weitere auf Anfrage."', 'immo-manager' ); ?>"><?php echo esc_textarea( (string) ( $meta['_immo_parking_notes'] ?? '' ) ); ?></textarea></td>
			</tr>
		</table>
		<?php
	}
```

- [ ] **Step 3: Syntax + Commit**

```bash
php -l includes/class-metaboxes.php
git add includes/class-metaboxes.php
git commit -m "feat(metabox): stellplatz-konfiguration auf bauprojekt-edit-screen"
```

---

### Task 3: Wohneinheits-Modal in Project-Metabox erweitern

**Files:**
- Modify: `templates/admin/metabox-project-units.php` (`<div class="immo-unit-grid">` ~Zeile 92)

- [ ] **Step 1: Neue Inputs nach „Bathrooms" einfügen**

In `templates/admin/metabox-project-units.php` direkt nach dem `<label>`-Block für `bathrooms` (~Zeile 117-120), vor dem `Status`-Label:

```php
				<label>
					<span><?php esc_html_e( 'Badezimmer', 'immo-manager' ); ?></span>
					<input type="number" name="bathrooms" min="0" />
				</label>
				<label>
					<span><?php esc_html_e( 'Balkon (m²)', 'immo-manager' ); ?></span>
					<input type="number" name="balcony_area" step="0.01" min="0" />
				</label>
				<label>
					<span><?php esc_html_e( 'Loggia (m²)', 'immo-manager' ); ?></span>
					<input type="number" name="loggia_area" step="0.01" min="0" />
				</label>
				<label>
					<span><?php esc_html_e( 'Garten (m²)', 'immo-manager' ); ?></span>
					<input type="number" name="garden_area" step="0.01" min="0" />
				</label>
				<label>
					<span><?php esc_html_e( 'Keller (m²)', 'immo-manager' ); ?></span>
					<input type="number" name="cellar_area" step="0.01" min="0" />
				</label>
				<label>
					<span><?php esc_html_e( 'TG-Plätze inkl.', 'immo-manager' ); ?></span>
					<input type="number" name="parking_garage_count" min="0" max="9" step="1" />
				</label>
				<label>
					<span><?php esc_html_e( 'Außen-Stellplätze inkl.', 'immo-manager' ); ?></span>
					<input type="number" name="parking_outdoor_count" min="0" max="9" step="1" />
				</label>
				<label>
					<span><?php esc_html_e( 'Status', 'immo-manager' ); ?></span>
```

- [ ] **Step 2: Syntax + Commit**

```bash
php -l templates/admin/metabox-project-units.php
git add templates/admin/metabox-project-units.php
git commit -m "feat(metabox): wohneinheits-modal um zusatzflaechen + stellplatz-counts"
```

---

### Task 4: Wizard-Modal um identische Felder erweitern

**Files:**
- Modify: `templates/wizard/wizard-form.php` (Modal-Grid ~Zeile 315-348)

- [ ] **Step 1: Neue Inputs nach Mietpreis-Feld einfügen**

In `templates/wizard/wizard-form.php` direkt nach dem `<div>` mit `name="rent"` (~Zeile 322), vor dem Status-`<div>`:

```php
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">Mietpreis</label><input type="number" step="0.01" name="rent" class="immo-input"></div>
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">Balkon (m²)</label><input type="number" step="0.01" min="0" name="balcony_area" class="immo-input"></div>
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">Loggia (m²)</label><input type="number" step="0.01" min="0" name="loggia_area" class="immo-input"></div>
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">Garten (m²)</label><input type="number" step="0.01" min="0" name="garden_area" class="immo-input"></div>
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">Keller (m²)</label><input type="number" step="0.01" min="0" name="cellar_area" class="immo-input"></div>
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">TG-Plätze inkl.</label><input type="number" min="0" max="9" name="parking_garage_count" class="immo-input"></div>
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">AP inkl.</label><input type="number" min="0" max="9" name="parking_outdoor_count" class="immo-input"></div>
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">Status</label>
```

- [ ] **Step 2: Syntax + Commit**

```bash
php -l templates/wizard/wizard-form.php
git add templates/wizard/wizard-form.php
git commit -m "feat(wizard): wohneinheits-modal um zusatzflaechen + stellplatz-counts"
```

---

### Task 5: Manuelle Verifikation (kann der User nach dem Testdeploy machen)

- [ ] **Step 1:** Bauprojekt-Edit-Screen → Metabox „Stellplätze" sichtbar; Werte eintragen + speichern.
- [ ] **Step 2:** Refresh des Edit-Screens → Werte stehen weiterhin da.
- [ ] **Step 3:** Wohneinheits-Modal: bei einer Einheit Balkon/Keller/TG-Plätze setzen + speichern.
- [ ] **Step 4:** Edit der gleichen Einheit → Werte vorgewählt.
- [ ] **Step 5:** REST-Endpoint prüfen: `curl …/units` zeigt die gesetzten Werte.

---

### Task 6: Spec auf „Implemented" + Final-Commit

- [ ] **Step 1: Spec-Status anpassen**

```markdown
**Status:** Implemented (2026-05-07)
```

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/specs/2026-05-07-wohneinheiten-erweiterung-phase-3-wizard-design.md
git commit -m "docs(spec): phase 3 als implemented markiert"
```

---

## Self-Review

**1. Spec coverage:**
- Stellplatz-Metabox → Task 2 ✓
- Wohneinheits-Modal Project-Metabox → Task 3 ✓
- Wohneinheits-Modal Wizard → Task 4 ✓
- `Units::hydrate()` typisiert → Task 1 ✓
- JS bleibt unverändert (generic name-basiert) ✓
- Save-Logik bleibt unverändert (Phase 1 sanitize) ✓

**2. Placeholder scan:** Keine TBDs.

**3. Type consistency:** Feldnamen identisch in beiden Modals. PHP-Types via hydrate konsistent zu REST-DTOs aus Phase 2.
