# Finanzierungs- & Nebenkostenrechner — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Kombinierten Nebenkosten- und Finanzierungsrechner als Akkordeon mit zwei Tabs auf Property-Detail- und Bauprojekt-Seiten implementieren — vollständig clientseitig (JS), AT-Sätze aus Settings konfigurierbar, mit Eigenkapital-€/%-Toggle, Sondertilgung und jährlichem Tilgungsplan.

**Architecture:** Server liefert Settings via `wp_localize_script` als `window.immoManager.calc`. Template `templates/parts/calculator.php` rendert das Akkordeon-Skelett mit Tabs (Nebenkosten / Finanzierung). `public/js/calculators.js` liest Inputs und Settings, berechnet, schreibt Outputs live in den DOM. Per-Property-Override `_commission_free` blendet die Maklerprovision aus.

**Tech Stack:** PHP 8 / WordPress Plugin API / Vanilla JS (kein jQuery für Calc-Logik) / CSS Custom Properties (bestehende Design-Tokens).

**Spec:** `docs/superpowers/specs/2026-04-28-finanzierungs-und-nebenkostenrechner-design.md`

---

## File Structure

| Datei | Status | Verantwortung |
|---|---|---|
| `includes/class-settings.php` | modify | Defaults `calc_*`, Section "Rechner", Render-Felder, Sanitize |
| `includes/class-meta-fields.php` | modify | Property-Meta `_commission_free` registrieren |
| `includes/class-metaboxes.php` | modify | Checkbox "Provisionsfrei" in Preis-Metabox |
| `templates/wizard/step-4-price.php` | modify | Checkbox "Provisionsfrei" im Wizard |
| `includes/class-rest-api.php` | modify | `format_property()` liefert `meta.commission_free` |
| `includes/class-shortcodes.php` | modify | `calculators.js` + `calculators.css` enqueuen, Localize-Payload `immoManager.calc` |
| `templates/parts/calculator.php` | create | Akkordeon-Template (Tabs, Inputs, Outputs, Plan-Tabelle) |
| `public/js/calculators.js` | create | Berechnungs-Logik, Tab/Unit-Switch, DOM-Update |
| `public/css/calculators.css` | create | Layout, Tabs, Plan-Tabelle, Mobile |
| `templates/property-detail.php` | modify | Akkordeon zwischen "Ausstattung" und "Lage" einbinden |
| `templates/single-immo_mgr_project.php` | modify | Akkordeon mit Unit-Dropdown einbinden |

---

## Phase 1 — Settings-Fundament

### Task 1.1: Calculator-Defaults zu `Settings::get_defaults()` hinzufügen

**Files:**
- Modify: `includes/class-settings.php` (innerhalb `get_defaults()`, nach dem letzten `map_default_zoom`-Eintrag)

- [ ] **Step 1: Defaults-Block einfügen**

In `Settings::get_defaults()` direkt vor dem schließenden `);` (Zeile ~136) einfügen:

```php
			// === RECHNER ===
			'calc_enable_costs'            => 1,
			'calc_enable_financing'        => 1,
			'calc_grest_rate'              => 3.5,
			'calc_grundbuch_rate'          => 1.1,
			'calc_notar_rate'              => 2.5,
			'calc_provision_rate'          => 3.0,
			'calc_ust_rate'                => 20.0,
			'calc_show_grest'              => 1,
			'calc_show_grundbuch'          => 1,
			'calc_show_notar'              => 1,
			'calc_show_provision'          => 1,
			'calc_show_ust_on_provision'   => 1,
			'calc_notar_mode'              => 'percent',
			'calc_notar_flat'              => 1500,
			'calc_default_equity_pct'      => 20,
			'calc_default_interest_rate'   => 3.5,
			'calc_default_term_years'      => 25,
			'calc_default_extra_payment'   => 0,
			'calc_show_amortization_table' => 1,
```

- [ ] **Step 2: Manuell verifizieren**

Im WordPress-Admin: Plugin deaktivieren + reaktivieren → `Settings::get('calc_grest_rate')` (per `wp_die`-Test in `register_settings`) liefert `3.5`. Direkt danach Test entfernen.

- [ ] **Step 3: Commit**

```bash
git add includes/class-settings.php
git commit -m "feat(calculator): add calculator defaults to settings"
```

---

### Task 1.2: Sanitize-Logik für Calculator-Felder

**Files:**
- Modify: `includes/class-settings.php` (`sanitize()`-Methode, vor dem `return $sanitized;`-Statement, ca. Zeile ~1480)

- [ ] **Step 1: Sanitize-Block einfügen**

Direkt vor `return $sanitized;` in `sanitize()` einfügen:

```php
		// === RECHNER ===
		// Toggles (0/1).
		foreach ( array(
			'calc_enable_costs', 'calc_enable_financing',
			'calc_show_grest', 'calc_show_grundbuch', 'calc_show_notar',
			'calc_show_provision', 'calc_show_ust_on_provision',
			'calc_show_amortization_table',
		) as $toggle ) {
			$sanitized[ $toggle ] = ! empty( $input[ $toggle ] ) ? 1 : 0;
		}

		// Prozentsätze (float, gekappt).
		$rate_caps = array(
			'calc_grest_rate'             => 50.0,
			'calc_grundbuch_rate'         => 50.0,
			'calc_notar_rate'             => 50.0,
			'calc_provision_rate'         => 50.0,
			'calc_ust_rate'               => 30.0,
			'calc_default_interest_rate'  => 20.0,
		);
		foreach ( $rate_caps as $key => $max ) {
			$val               = isset( $input[ $key ] ) ? (float) $input[ $key ] : (float) $defaults[ $key ];
			$sanitized[ $key ] = max( 0.0, min( $max, $val ) );
		}

		// Integer-Felder.
		$sanitized['calc_default_equity_pct']   = max( 0, min( 100, (int) ( $input['calc_default_equity_pct']   ?? $defaults['calc_default_equity_pct'] ) ) );
		$sanitized['calc_default_term_years']   = max( 1, min( 50,  (int) ( $input['calc_default_term_years']   ?? $defaults['calc_default_term_years'] ) ) );
		$sanitized['calc_default_extra_payment']= max( 0, min( 1000000, (int) ( $input['calc_default_extra_payment'] ?? $defaults['calc_default_extra_payment'] ) ) );
		$sanitized['calc_notar_flat']           = max( 0, min( 100000,  (int) ( $input['calc_notar_flat']           ?? $defaults['calc_notar_flat'] ) ) );

		// Notar-Modus (enum).
		$sanitized['calc_notar_mode'] = in_array( (string) ( $input['calc_notar_mode'] ?? '' ), array( 'percent', 'flat' ), true )
			? (string) $input['calc_notar_mode']
			: $defaults['calc_notar_mode'];
```

- [ ] **Step 2: Verifizieren**

Settings → Rechner-Section noch nicht da, aber Sanitize-Code wird beim Speichern aufgerufen. Manueller Test folgt in Task 1.3.

- [ ] **Step 3: Commit**

```bash
git add includes/class-settings.php
git commit -m "feat(calculator): sanitize calculator settings fields"
```

---

### Task 1.3: Settings-Section "Rechner" mit allen Feldern rendern

**Files:**
- Modify: `includes/class-settings.php` (neue Methode `register_calculator_section()`, plus Aufruf in `register_settings()`)

- [ ] **Step 1: Aufruf in `register_settings()` ergänzen**

In `register_settings()` nach `$this->register_integrations_section();` (ca. Zeile 208) einfügen:

```php
		$this->register_calculator_section();
```

- [ ] **Step 2: Methode `register_calculator_section()` einfügen**

Direkt nach `register_integrations_section()` (vor `// Field Renderers`-Kommentar, ca. Zeile 1107) einfügen:

```php
	/**
	 * Section: Rechner (Finanzierung & Nebenkosten).
	 *
	 * @return void
	 */
	private function register_calculator_section(): void {
		$section = 'immo_calculator';

		add_settings_section(
			$section,
			__( '🧮 Rechner (Finanzierung & Nebenkosten)', 'immo-manager' ),
			function () {
				echo '<p>' . esc_html__( 'AT-Defaults für Kauf-Nebenkosten und Annuitätendarlehen. Werte sind je Posten ein-/ausschaltbar.', 'immo-manager' ) . '</p>';
			},
			self::MENU_SLUG
		);

		// Allgemeine Toggles.
		add_settings_field( 'calc_enable_costs', __( 'Nebenkostenrechner', 'immo-manager' ),
			array( $this, 'render_checkbox_field' ), self::MENU_SLUG, $section,
			array( 'key' => 'calc_enable_costs', 'label' => __( 'Aktivieren', 'immo-manager' ) ) );

		add_settings_field( 'calc_enable_financing', __( 'Finanzierungsrechner', 'immo-manager' ),
			array( $this, 'render_checkbox_field' ), self::MENU_SLUG, $section,
			array( 'key' => 'calc_enable_financing', 'label' => __( 'Aktivieren', 'immo-manager' ) ) );

		// Nebenkosten-Sätze.
		$rate_fields = array(
			'calc_grest_rate'      => array( 'label' => __( 'Grunderwerbsteuer (%)',     'immo-manager' ), 'show' => 'calc_show_grest' ),
			'calc_grundbuch_rate'  => array( 'label' => __( 'Grundbucheintragung (%)',   'immo-manager' ), 'show' => 'calc_show_grundbuch' ),
			'calc_notar_rate'      => array( 'label' => __( 'Notar/Treuhand (%)',        'immo-manager' ), 'show' => 'calc_show_notar' ),
			'calc_provision_rate'  => array( 'label' => __( 'Maklerprovision (%)',       'immo-manager' ), 'show' => 'calc_show_provision' ),
			'calc_ust_rate'        => array( 'label' => __( 'USt auf Provision (%)',     'immo-manager' ), 'show' => 'calc_show_ust_on_provision' ),
		);
		foreach ( $rate_fields as $key => $cfg ) {
			add_settings_field( $key, $cfg['label'],
				array( $this, 'render_calc_rate_with_toggle' ), self::MENU_SLUG, $section,
				array( 'key' => $key, 'show_key' => $cfg['show'] ) );
		}

		// Notar-Modus.
		add_settings_field( 'calc_notar_mode', __( 'Notar-Berechnung', 'immo-manager' ),
			array( $this, 'render_select_field' ), self::MENU_SLUG, $section,
			array(
				'key'     => 'calc_notar_mode',
				'options' => array(
					'percent' => __( 'Prozent vom Kaufpreis', 'immo-manager' ),
					'flat'    => __( 'Pauschalbetrag', 'immo-manager' ),
				),
			)
		);
		add_settings_field( 'calc_notar_flat', __( 'Notar Pauschalbetrag (€)', 'immo-manager' ),
			array( $this, 'render_number_field' ), self::MENU_SLUG, $section,
			array( 'key' => 'calc_notar_flat', 'min' => 0, 'max' => 100000, 'step' => 1, 'class' => 'regular-text',
				'description' => __( 'Nur aktiv wenn Notar-Berechnung = "Pauschalbetrag".', 'immo-manager' ) ) );

		// Finanzierung.
		add_settings_field( 'calc_default_equity_pct', __( 'Standard-Eigenkapital (%)', 'immo-manager' ),
			array( $this, 'render_number_field' ), self::MENU_SLUG, $section,
			array( 'key' => 'calc_default_equity_pct', 'min' => 0, 'max' => 100, 'step' => 1, 'class' => 'small-text' ) );

		add_settings_field( 'calc_default_interest_rate', __( 'Standard-Zinssatz (%)', 'immo-manager' ),
			array( $this, 'render_number_field' ), self::MENU_SLUG, $section,
			array( 'key' => 'calc_default_interest_rate', 'min' => 0, 'max' => 20, 'step' => '0.1', 'class' => 'small-text' ) );

		add_settings_field( 'calc_default_term_years', __( 'Standard-Laufzeit (Jahre)', 'immo-manager' ),
			array( $this, 'render_number_field' ), self::MENU_SLUG, $section,
			array( 'key' => 'calc_default_term_years', 'min' => 1, 'max' => 50, 'step' => 1, 'class' => 'small-text' ) );

		add_settings_field( 'calc_default_extra_payment', __( 'Standard-Sondertilgung (€/Jahr)', 'immo-manager' ),
			array( $this, 'render_number_field' ), self::MENU_SLUG, $section,
			array( 'key' => 'calc_default_extra_payment', 'min' => 0, 'max' => 1000000, 'step' => 1, 'class' => 'regular-text' ) );

		add_settings_field( 'calc_show_amortization_table', __( 'Tilgungsplan-Tabelle', 'immo-manager' ),
			array( $this, 'render_checkbox_field' ), self::MENU_SLUG, $section,
			array( 'key' => 'calc_show_amortization_table', 'label' => __( 'Anzeigen', 'immo-manager' ) ) );
	}

	/**
	 * Custom Renderer: Prozentsatz + "anzeigen"-Toggle in einer Zeile.
	 *
	 * @param array<string, mixed> $args Args.
	 *
	 * @return void
	 */
	public function render_calc_rate_with_toggle( array $args ): void {
		$key      = (string) $args['key'];
		$show_key = (string) $args['show_key'];
		$value    = self::get( $key, 0 );
		$show     = (int) self::get( $show_key, 1 );

		printf(
			'<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="0" max="50" step="0.1" class="small-text" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( (string) $value )
		);
		printf(
			' &nbsp; <label><input type="checkbox" name="%1$s[%2$s]" value="1"%3$s /> %4$s</label>',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $show_key ),
			checked( $show, 1, false ),
			esc_html__( 'anzeigen', 'immo-manager' )
		);
	}
```

- [ ] **Step 3: Manuell verifizieren**

WordPress Admin → Immo Manager → Einstellungen → Section "🧮 Rechner" sichtbar mit allen Feldern. Werte ändern + Speichern → Reload zeigt die geänderten Werte.

- [ ] **Step 4: Commit**

```bash
git add includes/class-settings.php
git commit -m "feat(calculator): add Rechner settings section with all fields"
```

---

## Phase 2 — Property-Meta `_commission_free`

### Task 2.1: Meta-Feld registrieren

**Files:**
- Modify: `includes/class-meta-fields.php` (`property_fields()`, im "Preis"-Block, nach `_immo_commission`)

- [ ] **Step 1: Eintrag hinzufügen**

In `property_fields()` direkt nach der Zeile mit `'_immo_commission'` einfügen:

```php
				'_immo_commission_free'   => array( 'type' => 'integer', 'default' => 0 ),
```

- [ ] **Step 2: Verifizieren**

Im WP-CLI oder per `var_dump( get_post_meta( $id, '_immo_commission_free', true ) )` auf einer bestehenden Property: liefert `0`.

- [ ] **Step 3: Commit**

```bash
git add includes/class-meta-fields.php
git commit -m "feat(calculator): register _immo_commission_free property meta"
```

---

### Task 2.2: Wizard-Schritt 4 erweitern

**Files:**
- Modify: `templates/wizard/step-4-price.php` (innerhalb `<div class="immo-wizard-section immo-price-section-sale immo-property-only">`)

- [ ] **Step 1: Checkbox direkt unter dem Provisions-Feld einfügen**

In `step-4-price.php`, direkt nach dem schließenden `</div>` der Provisions-Felder (nach Zeile 30, vor `</div>` der Sale-Section), folgenden Block einfügen:

```php
		<div class="immo-field" style="margin-top: 0.75rem;">
			<label class="immo-checkbox-label">
				<input type="checkbox" id="wiz_commission_free" name="_immo_commission_free" value="1"
					class="immo-wizard-input"
					<?php checked( (int) ( $p['_immo_commission_free'] ?? 0 ), 1 ); ?>>
				<?php esc_html_e( 'Provisionsfrei (Maklerprovision wird im Rechner ausgeblendet)', 'immo-manager' ); ?>
			</label>
		</div>
```

- [ ] **Step 2: Im Browser verifizieren**

Wizard → Schritt 4 (Preis) → "Verkauf"-Modus → Checkbox "Provisionsfrei" sichtbar; Aktivieren + Wizard abschließen → Property-Meta `_immo_commission_free` = 1.

- [ ] **Step 3: Commit**

```bash
git add templates/wizard/step-4-price.php
git commit -m "feat(calculator): add 'provisionsfrei' checkbox to wizard step 4"
```

---

### Task 2.3: Klassische Property-Metabox erweitern

**Files:**
- Modify: `includes/class-metaboxes.php` (im Preis-Metabox-Render, nach dem Provisions-`<tr>`, ca. Zeile 247)

- [ ] **Step 1: Neue `<tr>` einfügen**

Direkt nach dem `<tr>` mit `_immo_commission` (Zeile 247 endet mit `</tr>`) folgendes einfügen:

```php
			<tr>
				<th><label for="_immo_commission_free"><?php esc_html_e( 'Provisionsfrei', 'immo-manager' ); ?></label></th>
				<td><label><input type="checkbox" id="_immo_commission_free" name="immo_meta[_immo_commission_free]" value="1" <?php checked( (int) ( $meta['_immo_commission_free'] ?? 0 ), 1 ); ?> />
					<?php esc_html_e( 'Provision im Rechner ausblenden', 'immo-manager' ); ?>
				</label></td>
			</tr>
```

- [ ] **Step 2: Manuell testen**

Property im Admin öffnen → Preis-Metabox → Checkbox sichtbar, anhakbar, speichert nach Update.

- [ ] **Step 3: Commit**

```bash
git add includes/class-metaboxes.php
git commit -m "feat(calculator): add 'provisionsfrei' checkbox to property metabox"
```

---

### Task 2.4: REST-API liefert `commission_free` aus

**Files:**
- Modify: `includes/class-rest-api.php` (`format_property()`, im `meta`-Array nach `'commission'` Zeile 844)

- [ ] **Step 1: Eintrag im meta-Array ergänzen**

Direkt nach der Zeile mit `'commission'` einfügen:

```php
				'commission_free'       => (bool) $m( '_immo_commission_free', 0 ),
```

- [ ] **Step 2: Manuell testen**

`curl /wp-json/immo-manager/v1/properties/<id>` → JSON enthält `meta.commission_free`. Bei einer Property mit angehaktem Flag: `true`. Sonst: `false`.

- [ ] **Step 3: Commit**

```bash
git add includes/class-rest-api.php
git commit -m "feat(calculator): expose commission_free in property REST output"
```

---

## Phase 3 — Calculator-Template & Assets-Skeleton

### Task 3.1: CSS-Datei `calculators.css` anlegen

**Files:**
- Create: `public/css/calculators.css`

- [ ] **Step 1: Datei erstellen**

```css
/* ==========================================================================
   Immo Manager – Finanzierungs- & Nebenkostenrechner
   ========================================================================== */

.immo-calculator {
	border: 1px solid var(--immo-border);
	border-radius: var(--immo-radius);
	background: var(--immo-surface);
	overflow: hidden;
	margin-top: 1rem;
}

.immo-calculator-tabs {
	display: flex;
	gap: 0;
	border-bottom: 1px solid var(--immo-border);
	background: var(--immo-bg);
}

.immo-calculator-tab {
	flex: 1;
	padding: 0.85rem 1rem;
	background: transparent;
	border: none;
	border-bottom: 3px solid transparent;
	color: var(--immo-text-muted);
	font-weight: 600;
	font-size: 0.95rem;
	cursor: pointer;
	transition: all 0.2s;
}

.immo-calculator-tab:hover {
	color: var(--immo-text);
	background: rgba(0, 0, 0, 0.03);
}

.immo-calculator-tab.active {
	color: var(--immo-accent);
	border-bottom-color: var(--immo-accent);
	background: var(--immo-surface);
}

.immo-calculator-panel {
	padding: 1.25rem;
}

.immo-calculator-panel[hidden] {
	display: none;
}

.immo-calc-row {
	display: grid;
	grid-template-columns: 1fr auto;
	gap: 0.75rem 1rem;
	align-items: center;
	padding: 0.5rem 0;
}

.immo-calc-row + .immo-calc-row {
	border-top: 1px dashed var(--immo-border);
}

.immo-calc-row-label {
	font-size: 0.95rem;
	color: var(--immo-text);
}

.immo-calc-row-rate {
	color: var(--immo-text-muted);
	font-size: 0.85rem;
	margin-left: 0.4rem;
}

.immo-calc-row-amount {
	font-weight: 600;
	font-variant-numeric: tabular-nums;
	white-space: nowrap;
}

.immo-calc-input-row {
	display: grid;
	grid-template-columns: 1fr 180px;
	gap: 0.75rem 1rem;
	align-items: center;
	padding: 0.5rem 0;
}

.immo-calc-input-row input[type="number"] {
	width: 100%;
	padding: 0.45rem 0.6rem;
	border: 1px solid var(--immo-border);
	border-radius: var(--immo-radius-sm);
	font-size: 0.95rem;
	font-variant-numeric: tabular-nums;
	text-align: right;
}

.immo-calc-total {
	display: flex;
	justify-content: space-between;
	padding: 0.85rem 0;
	border-top: 2px solid var(--immo-border);
	margin-top: 0.5rem;
	font-size: 1.05rem;
	font-weight: 700;
}

.immo-calc-grand-total {
	display: flex;
	justify-content: space-between;
	padding: 0.85rem 1rem;
	background: var(--immo-bg);
	border-radius: var(--immo-radius-sm);
	margin-top: 0.5rem;
	font-size: 1.1rem;
	font-weight: 700;
	color: var(--immo-accent);
}

.immo-calc-equity-toggle {
	display: inline-flex;
	border: 1px solid var(--immo-border);
	border-radius: 999px;
	padding: 2px;
	background: var(--immo-bg);
}

.immo-calc-equity-toggle button {
	padding: 0.3rem 0.85rem;
	border: none;
	background: transparent;
	border-radius: 999px;
	cursor: pointer;
	font-size: 0.85rem;
	color: var(--immo-text-muted);
}

.immo-calc-equity-toggle button.active {
	background: var(--immo-accent);
	color: #fff;
}

.immo-calc-equity-display {
	color: var(--immo-text-muted);
	font-size: 0.85rem;
	margin-left: 0.5rem;
}

.immo-calc-disclaimer {
	margin-top: 1rem;
	padding: 0.6rem 0.8rem;
	background: rgba(0, 0, 0, 0.03);
	border-radius: var(--immo-radius-sm);
	font-size: 0.8rem;
	color: var(--immo-text-muted);
	font-style: italic;
}

.immo-amortization-toggle {
	margin-top: 1rem;
	width: 100%;
	padding: 0.7rem;
	background: var(--immo-bg);
	border: 1px solid var(--immo-border);
	border-radius: var(--immo-radius-sm);
	cursor: pointer;
	font-weight: 600;
	color: var(--immo-text);
}

.immo-amortization-table-wrap {
	margin-top: 0.75rem;
	max-height: 400px;
	overflow-y: auto;
	border: 1px solid var(--immo-border);
	border-radius: var(--immo-radius-sm);
}

.immo-amortization-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.88rem;
	font-variant-numeric: tabular-nums;
}

.immo-amortization-table th,
.immo-amortization-table td {
	padding: 0.45rem 0.7rem;
	text-align: right;
	border-bottom: 1px solid var(--immo-border);
}

.immo-amortization-table th:first-child,
.immo-amortization-table td:first-child {
	text-align: left;
}

.immo-amortization-table thead th {
	background: var(--immo-bg);
	position: sticky;
	top: 0;
}

.immo-calc-unit-select {
	width: 100%;
	padding: 0.55rem 0.7rem;
	border: 1px solid var(--immo-border);
	border-radius: var(--immo-radius-sm);
	font-size: 0.95rem;
	margin-bottom: 1rem;
}

.immo-calc-no-financing {
	padding: 1rem;
	background: var(--immo-bg);
	border-radius: var(--immo-radius-sm);
	color: var(--immo-text-muted);
	text-align: center;
	font-style: italic;
}

@media ( max-width: 600px ) {
	.immo-calc-input-row {
		grid-template-columns: 1fr;
	}
	.immo-calc-input-row input[type="number"] {
		width: 100%;
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add public/css/calculators.css
git commit -m "feat(calculator): add calculator styles"
```

---

### Task 3.2: Calculator-Template `calculator.php` anlegen

**Files:**
- Create: `templates/parts/calculator.php`

- [ ] **Step 1: Datei erstellen**

```php
<?php
/**
 * Akkordeon-Template: Finanzierungs- & Nebenkostenrechner.
 *
 * Variablen-Erwartung (vom inkludierenden Template gesetzt):
 *   $calc_context = array(
 *       'base_price'        => float,        // Default-Kaufpreis
 *       'commission_free'   => bool,         // Per-Property-Override
 *       'units'             => array,        // [ ['id'=>..,'label'=>..,'price'=>..,'commission_free'=>..], … ]
 *                                            // leer = Single-Property; gesetzt = Bauprojekt mit Dropdown
 *   );
 *
 * @package ImmoManager
 */
defined( 'ABSPATH' ) || exit;

$ctx = $calc_context ?? array();
$base_price      = (float) ( $ctx['base_price'] ?? 0 );
$commission_free = (bool)  ( $ctx['commission_free'] ?? false );
$units           = (array) ( $ctx['units'] ?? array() );

$enable_costs     = (bool) \ImmoManager\Settings::get( 'calc_enable_costs', 1 );
$enable_financing = (bool) \ImmoManager\Settings::get( 'calc_enable_financing', 1 );

if ( ! $enable_costs && ! $enable_financing ) {
	return;
}

$show_table   = (bool) \ImmoManager\Settings::get( 'calc_show_amortization_table', 1 );
$default_eq   = (int)  \ImmoManager\Settings::get( 'calc_default_equity_pct', 20 );
$default_int  = (float) \ImmoManager\Settings::get( 'calc_default_interest_rate', 3.5 );
$default_term = (int)  \ImmoManager\Settings::get( 'calc_default_term_years', 25 );
$default_extra= (int)  \ImmoManager\Settings::get( 'calc_default_extra_payment', 0 );

$show_tabs = $enable_costs && $enable_financing;
?>
<div class="immo-accordion">
	<button class="immo-accordion-header" aria-expanded="false">
		<?php esc_html_e( '💰 Finanzierungs- & Nebenkostenrechner', 'immo-manager' ); ?>
		<span class="immo-accordion-icon" aria-hidden="true"></span>
	</button>
	<div class="immo-accordion-body" hidden>
		<div class="immo-calculator"
			data-base-price="<?php echo esc_attr( (string) $base_price ); ?>"
			data-commission-free="<?php echo $commission_free ? '1' : '0'; ?>"
			<?php if ( ! empty( $units ) ) : ?>
				data-units="<?php echo esc_attr( wp_json_encode( $units ) ); ?>"
			<?php endif; ?>>

			<?php if ( ! empty( $units ) ) : ?>
				<div style="padding: 1rem 1.25rem 0;">
					<label for="immo-calc-unit-select" style="display:block; font-weight:600; margin-bottom:0.4rem;">
						<?php esc_html_e( 'Wohneinheit', 'immo-manager' ); ?>
					</label>
					<select id="immo-calc-unit-select" class="immo-calc-unit-select">
						<?php foreach ( $units as $u ) : ?>
							<option value="<?php echo esc_attr( (string) $u['id'] ); ?>"
								data-price="<?php echo esc_attr( (string) $u['price'] ); ?>"
								data-commission-free="<?php echo ! empty( $u['commission_free'] ) ? '1' : '0'; ?>">
								<?php echo esc_html( $u['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php if ( $show_tabs ) : ?>
				<div class="immo-calculator-tabs" role="tablist">
					<button type="button" class="immo-calculator-tab active" data-tab="costs" role="tab" aria-selected="true">
						<?php esc_html_e( 'Nebenkosten', 'immo-manager' ); ?>
					</button>
					<button type="button" class="immo-calculator-tab" data-tab="financing" role="tab" aria-selected="false">
						<?php esc_html_e( 'Finanzierung', 'immo-manager' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<?php if ( $enable_costs ) : ?>
				<div class="immo-calculator-panel" data-panel="costs">
					<div class="immo-calc-input-row">
						<label for="immo-calc-price"><?php esc_html_e( 'Kaufpreis', 'immo-manager' ); ?></label>
						<input type="number" id="immo-calc-price" min="0" step="1" value="<?php echo esc_attr( (string) (int) $base_price ); ?>">
					</div>
					<div class="immo-calc-items"></div>
					<div class="immo-calc-total">
						<span><?php esc_html_e( 'Nebenkosten gesamt', 'immo-manager' ); ?></span>
						<span class="immo-calc-costs-total">0 €</span>
					</div>
					<div class="immo-calc-grand-total">
						<span><?php esc_html_e( 'Gesamtkosten (inkl. Kauf)', 'immo-manager' ); ?></span>
						<span class="immo-calc-grand-total-value">0 €</span>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $enable_financing ) : ?>
				<div class="immo-calculator-panel" data-panel="financing"<?php echo $show_tabs ? ' hidden' : ''; ?>>
					<div class="immo-calc-row">
						<span class="immo-calc-row-label">
							<?php esc_html_e( 'Finanzierungsbedarf (Kauf + Nebenkosten)', 'immo-manager' ); ?>
						</span>
						<span class="immo-calc-row-amount immo-calc-need">0 €</span>
					</div>

					<div class="immo-calc-input-row">
						<label for="immo-calc-equity">
							<?php esc_html_e( 'Eigenkapital', 'immo-manager' ); ?>
							<span class="immo-calc-equity-toggle" role="group" aria-label="<?php esc_attr_e( 'Einheit', 'immo-manager' ); ?>">
								<button type="button" data-mode="eur" class=""><?php esc_html_e( '€', 'immo-manager' ); ?></button>
								<button type="button" data-mode="pct" class="active"><?php esc_html_e( '%', 'immo-manager' ); ?></button>
							</span>
						</label>
						<div>
							<input type="number" id="immo-calc-equity" min="0" step="1" value="<?php echo esc_attr( (string) $default_eq ); ?>">
							<span class="immo-calc-equity-display">→ <span class="immo-calc-equity-eur">0 €</span></span>
						</div>
					</div>

					<div class="immo-calc-row">
						<span class="immo-calc-row-label"><?php esc_html_e( 'Kreditsumme', 'immo-manager' ); ?></span>
						<span class="immo-calc-row-amount immo-calc-loan">0 €</span>
					</div>

					<div class="immo-calc-input-row">
						<label for="immo-calc-interest"><?php esc_html_e( 'Zinssatz (% p.a.)', 'immo-manager' ); ?></label>
						<input type="number" id="immo-calc-interest" min="0" max="20" step="0.1" value="<?php echo esc_attr( (string) $default_int ); ?>">
					</div>
					<div class="immo-calc-input-row">
						<label for="immo-calc-term"><?php esc_html_e( 'Laufzeit (Jahre)', 'immo-manager' ); ?></label>
						<input type="number" id="immo-calc-term" min="1" max="50" step="1" value="<?php echo esc_attr( (string) $default_term ); ?>">
					</div>
					<div class="immo-calc-input-row">
						<label for="immo-calc-extra"><?php esc_html_e( 'Sondertilgung (€/Jahr)', 'immo-manager' ); ?></label>
						<input type="number" id="immo-calc-extra" min="0" step="1" value="<?php echo esc_attr( (string) $default_extra ); ?>">
					</div>

					<div class="immo-calc-results" style="margin-top: 1rem;">
						<div class="immo-calc-row">
							<span class="immo-calc-row-label"><?php esc_html_e( 'Monatliche Rate', 'immo-manager' ); ?></span>
							<span class="immo-calc-row-amount immo-calc-monthly">0 €</span>
						</div>
						<div class="immo-calc-row">
							<span class="immo-calc-row-label"><?php esc_html_e( 'Gesamt-Zinsen', 'immo-manager' ); ?></span>
							<span class="immo-calc-row-amount immo-calc-total-interest">0 €</span>
						</div>
						<div class="immo-calc-row">
							<span class="immo-calc-row-label"><?php esc_html_e( 'Gesamtaufwand', 'immo-manager' ); ?></span>
							<span class="immo-calc-row-amount immo-calc-total-payment">0 €</span>
						</div>
						<div class="immo-calc-row">
							<span class="immo-calc-row-label"><?php esc_html_e( 'Tilgung-Ende', 'immo-manager' ); ?></span>
							<span class="immo-calc-row-amount immo-calc-end-year">–</span>
						</div>
					</div>

					<?php if ( $show_table ) : ?>
						<button type="button" class="immo-amortization-toggle" aria-expanded="false">
							<?php esc_html_e( '▼ Tilgungsplan anzeigen', 'immo-manager' ); ?>
						</button>
						<div class="immo-amortization-table-wrap" hidden>
							<table class="immo-amortization-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Jahr', 'immo-manager' ); ?></th>
										<th><?php esc_html_e( 'Zinsen', 'immo-manager' ); ?></th>
										<th><?php esc_html_e( 'Tilgung', 'immo-manager' ); ?></th>
										<th><?php esc_html_e( 'Restschuld', 'immo-manager' ); ?></th>
									</tr>
								</thead>
								<tbody class="immo-amortization-rows"></tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<p class="immo-calc-disclaimer">
				<?php esc_html_e( 'Unverbindliche Schätzung – ersetzt keine Bank-/Steuerberatung.', 'immo-manager' ); ?>
			</p>
		</div>
	</div>
</div>
```

- [ ] **Step 2: Commit**

```bash
git add templates/parts/calculator.php
git commit -m "feat(calculator): add calculator template skeleton"
```

---

### Task 3.3: JS-Skeleton `calculators.js` anlegen (nur Tab-Switch + DOM-Init)

**Files:**
- Create: `public/js/calculators.js`

- [ ] **Step 1: Datei erstellen**

```javascript
/* global immoManager */
( function () {
	'use strict';

	var settings = ( window.immoManager && window.immoManager.calc ) || null;
	if ( ! settings ) { return; }

	function formatMoney( amount ) {
		var c = settings.currency;
		var dec = parseInt( c.decimals, 10 ) || 0;
		var fixed = Math.round( amount * Math.pow( 10, dec ) ) / Math.pow( 10, dec );
		var parts = fixed.toFixed( dec ).split( '.' );
		parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, c.thousands );
		var num = parts.join( c.decimal );
		return c.position === 'before' ? c.symbol + ' ' + num : num + ' ' + c.symbol;
	}

	function $( sel, root ) { return ( root || document ).querySelector( sel ); }
	function $$( sel, root ) { return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) ); }

	function initTabs( calc ) {
		var tabs = $$( '.immo-calculator-tab', calc );
		var panels = $$( '.immo-calculator-panel', calc );
		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				var target = tab.getAttribute( 'data-tab' );
				tabs.forEach( function ( t ) {
					var active = t === tab;
					t.classList.toggle( 'active', active );
					t.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );
				panels.forEach( function ( p ) {
					p.hidden = p.getAttribute( 'data-panel' ) !== target;
				} );
			} );
		} );
	}

	function initAmortizationToggle( calc ) {
		var toggle = $( '.immo-amortization-toggle', calc );
		var wrap   = $( '.immo-amortization-table-wrap', calc );
		if ( ! toggle || ! wrap ) { return; }
		toggle.addEventListener( 'click', function () {
			var open = wrap.hidden;
			wrap.hidden = ! open;
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			toggle.textContent = ( open ? '▲ ' : '▼ ' ) + ( open
				? ( settings.i18n && settings.i18n.hideTable )  || 'Tilgungsplan ausblenden'
				: ( settings.i18n && settings.i18n.showTable ) || 'Tilgungsplan anzeigen' );
		} );
	}

	function initCalculator( calc ) {
		initTabs( calc );
		initAmortizationToggle( calc );
		// Berechnung wird in Phase 4/5 ergänzt.
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		$$( '.immo-calculator' ).forEach( initCalculator );
	} );

	// Public API für Tests.
	window.immoCalculators = {
		formatMoney: formatMoney,
	};
} )();
```

- [ ] **Step 2: Commit**

```bash
git add public/js/calculators.js
git commit -m "feat(calculator): add JS skeleton with tab and table toggle"
```

---

### Task 3.4: Assets enqueuen + Localize-Payload `immoManager.calc`

**Files:**
- Modify: `includes/class-shortcodes.php` (`enqueue_assets()`, nach dem `leaflet`-Block, vor `wp_localize_script` für `immoManager`)

- [ ] **Step 1: Enqueue + neue Localize-Payload einfügen**

In `enqueue_assets()` direkt vor dem bestehenden `wp_localize_script( 'immo-manager-filters', 'immoManager', …)`-Block (ca. Zeile 193) einfügen:

```php
		// Rechner.
		wp_enqueue_style(
			'immo-manager-calculators',
			IMMO_MANAGER_PLUGIN_URL . 'public/css/calculators.css',
			array( 'immo-manager-frontend' ),
			IMMO_MANAGER_VERSION
		);
		wp_enqueue_script(
			'immo-manager-calculators',
			IMMO_MANAGER_PLUGIN_URL . 'public/js/calculators.js',
			array(),
			IMMO_MANAGER_VERSION,
			true
		);
		wp_localize_script(
			'immo-manager-calculators',
			'immoManagerCalcInternal', // wird in Step 2 zu immoManager.calc verschmolzen
			array(
				'enabled'  => array(
					'costs'     => (bool) Settings::get( 'calc_enable_costs', 1 ),
					'financing' => (bool) Settings::get( 'calc_enable_financing', 1 ),
				),
				'rates'    => array(
					'grest'     => (float) Settings::get( 'calc_grest_rate', 3.5 ),
					'grundbuch' => (float) Settings::get( 'calc_grundbuch_rate', 1.1 ),
					'notar'     => (float) Settings::get( 'calc_notar_rate', 2.5 ),
					'provision' => (float) Settings::get( 'calc_provision_rate', 3.0 ),
					'ust'       => (float) Settings::get( 'calc_ust_rate', 20.0 ),
				),
				'show'     => array(
					'grest'     => (bool) Settings::get( 'calc_show_grest', 1 ),
					'grundbuch' => (bool) Settings::get( 'calc_show_grundbuch', 1 ),
					'notar'     => (bool) Settings::get( 'calc_show_notar', 1 ),
					'provision' => (bool) Settings::get( 'calc_show_provision', 1 ),
					'ust'       => (bool) Settings::get( 'calc_show_ust_on_provision', 1 ),
				),
				'notar'    => array(
					'mode' => (string) Settings::get( 'calc_notar_mode', 'percent' ),
					'flat' => (int)    Settings::get( 'calc_notar_flat', 1500 ),
				),
				'finance'  => array(
					'equityPct'        => (int)   Settings::get( 'calc_default_equity_pct', 20 ),
					'interestRate'     => (float) Settings::get( 'calc_default_interest_rate', 3.5 ),
					'termYears'        => (int)   Settings::get( 'calc_default_term_years', 25 ),
					'extraPayment'     => (int)   Settings::get( 'calc_default_extra_payment', 0 ),
					'showAmortization' => (bool)  Settings::get( 'calc_show_amortization_table', 1 ),
				),
				'currency' => array(
					'symbol'    => (string) Settings::get( 'currency_symbol', '€' ),
					'position'  => (string) Settings::get( 'currency_position', 'after' ),
					'decimals'  => (int)    Settings::get( 'price_decimals', 0 ),
					'thousands' => (string) Settings::get( 'thousands_separator', '.' ),
					'decimal'   => (string) Settings::get( 'decimal_separator', ',' ),
				),
				'i18n'     => array(
					'noFinancing' => __( 'Keine Finanzierung nötig (Eigenkapital ≥ Bedarf).', 'immo-manager' ),
					'showTable'   => __( 'Tilgungsplan anzeigen', 'immo-manager' ),
					'hideTable'   => __( 'Tilgungsplan ausblenden', 'immo-manager' ),
				),
			)
		);
```

- [ ] **Step 2: Im `immoManager`-Localize-Block die Calc-Daten als `calc`-Sub-Key durchreichen**

Im bestehenden `wp_localize_script( 'immo-manager-filters', 'immoManager', array( … ) )`-Block (ca. Zeile 193-222) das Array um den Schlüssel `'calc'` ergänzen, direkt nach dem `'mapDefaultZoom'`-Eintrag:

```php
				'calc'         => array(
					'enabled'  => array(
						'costs'     => (bool) Settings::get( 'calc_enable_costs', 1 ),
						'financing' => (bool) Settings::get( 'calc_enable_financing', 1 ),
					),
					'rates'    => array(
						'grest'     => (float) Settings::get( 'calc_grest_rate', 3.5 ),
						'grundbuch' => (float) Settings::get( 'calc_grundbuch_rate', 1.1 ),
						'notar'     => (float) Settings::get( 'calc_notar_rate', 2.5 ),
						'provision' => (float) Settings::get( 'calc_provision_rate', 3.0 ),
						'ust'       => (float) Settings::get( 'calc_ust_rate', 20.0 ),
					),
					'show'     => array(
						'grest'     => (bool) Settings::get( 'calc_show_grest', 1 ),
						'grundbuch' => (bool) Settings::get( 'calc_show_grundbuch', 1 ),
						'notar'     => (bool) Settings::get( 'calc_show_notar', 1 ),
						'provision' => (bool) Settings::get( 'calc_show_provision', 1 ),
						'ust'       => (bool) Settings::get( 'calc_show_ust_on_provision', 1 ),
					),
					'notar'    => array(
						'mode' => (string) Settings::get( 'calc_notar_mode', 'percent' ),
						'flat' => (int)    Settings::get( 'calc_notar_flat', 1500 ),
					),
					'finance'  => array(
						'equityPct'        => (int)   Settings::get( 'calc_default_equity_pct', 20 ),
						'interestRate'     => (float) Settings::get( 'calc_default_interest_rate', 3.5 ),
						'termYears'        => (int)   Settings::get( 'calc_default_term_years', 25 ),
						'extraPayment'     => (int)   Settings::get( 'calc_default_extra_payment', 0 ),
						'showAmortization' => (bool)  Settings::get( 'calc_show_amortization_table', 1 ),
					),
					'currency' => array(
						'symbol'    => (string) Settings::get( 'currency_symbol', '€' ),
						'position'  => (string) Settings::get( 'currency_position', 'after' ),
						'decimals'  => (int)    Settings::get( 'price_decimals', 0 ),
						'thousands' => (string) Settings::get( 'thousands_separator', '.' ),
						'decimal'   => (string) Settings::get( 'decimal_separator', ',' ),
					),
					'i18n'     => array(
						'noFinancing' => __( 'Keine Finanzierung nötig (Eigenkapital ≥ Bedarf).', 'immo-manager' ),
						'showTable'   => __( 'Tilgungsplan anzeigen', 'immo-manager' ),
						'hideTable'   => __( 'Tilgungsplan ausblenden', 'immo-manager' ),
					),
				),
```

- [ ] **Step 3: Den separaten `immoManagerCalcInternal`-Block aus Step 1 wieder entfernen**

Den in Step 1 hinzugefügten `wp_localize_script( ..., 'immoManagerCalcInternal', ... )`-Aufruf entfernen — er wurde nur zum Strukturieren genutzt. Das `calc`-Sub-Array hängt jetzt sauber an `immoManager`.

Stattdessen `immo-manager-calculators` als Dependency von `immo-manager-filters` setzen, damit `window.immoManager` schon existiert wenn `calculators.js` lädt. Im `wp_enqueue_script`-Aufruf für `immo-manager-calculators` (Step 1):

Alt:
```php
			array(),
```
Neu:
```php
			array( 'immo-manager-filters' ),
```

- [ ] **Step 4: Im Browser verifizieren**

Auf einer Frontend-Seite öffnen und in der DevTools-Konsole eingeben:

```js
window.immoManager.calc.rates.grest
```

Erwartet: `3.5`.

- [ ] **Step 5: Commit**

```bash
git add includes/class-shortcodes.php
git commit -m "feat(calculator): enqueue calculator assets and localize settings"
```

---

## Phase 4 — Nebenkosten-Logik

### Task 4.1: `calcCosts()`-Funktion implementieren

**Files:**
- Modify: `public/js/calculators.js` (innerhalb der IIFE, nach `formatMoney`)

- [ ] **Step 1: Funktion einfügen**

Nach `function formatMoney(...)`-Block einfügen:

```javascript
	function calcCosts( price, commissionFree ) {
		var items = [];
		var r = settings.rates;
		var s = settings.show;
		price = Math.max( 0, price || 0 );

		if ( s.grest ) {
			items.push( {
				key: 'grest',
				label: 'Grunderwerbsteuer',
				rate: r.grest,
				amount: price * r.grest / 100,
			} );
		}
		if ( s.grundbuch ) {
			items.push( {
				key: 'grundbuch',
				label: 'Grundbucheintragung',
				rate: r.grundbuch,
				amount: price * r.grundbuch / 100,
			} );
		}
		if ( s.notar ) {
			var notarFlat = settings.notar.mode === 'flat';
			var notarAmount = notarFlat ? settings.notar.flat : ( price * r.notar / 100 );
			items.push( {
				key: 'notar',
				label: 'Notar/Treuhand',
				rate: notarFlat ? null : r.notar,
				amount: notarAmount,
			} );
		}
		if ( s.provision && ! commissionFree ) {
			var provision = price * r.provision / 100;
			items.push( {
				key: 'provision',
				label: 'Maklerprovision',
				rate: r.provision,
				amount: provision,
			} );
			if ( s.ust ) {
				items.push( {
					key: 'ust',
					label: 'USt auf Provision',
					rate: r.ust,
					amount: provision * r.ust / 100,
				} );
			}
		}

		var total = items.reduce( function ( a, i ) { return a + i.amount; }, 0 );
		return { items: items, total: total, grandTotal: price + total };
	}
```

- [ ] **Step 2: Im DevTools-Console testen**

Auf einer Frontend-Seite `window.immoCalculators.calcCosts( 350000, false )` aufrufen. Vorher in IIFE die Public API erweitern (Step 3).

- [ ] **Step 3: `calcCosts` in Public API exportieren**

Im Block am Ende der IIFE:

```javascript
	window.immoCalculators = {
		formatMoney: formatMoney,
		calcCosts: calcCosts,
	};
```

- [ ] **Step 4: Console-Test**

```js
window.immoCalculators.calcCosts( 350000, false ).total
```

Erwartet (mit Default-Sätzen 3.5+1.1+2.5+3+0.6=10.7%): ca. `37450`.

- [ ] **Step 5: Commit**

```bash
git add public/js/calculators.js
git commit -m "feat(calculator): implement calcCosts logic"
```

---

### Task 4.2: Nebenkosten-Panel rendern und live updaten

**Files:**
- Modify: `public/js/calculators.js` (innerhalb der IIFE)

- [ ] **Step 1: Render-Funktion einfügen**

Nach `calcCosts(...)` einfügen:

```javascript
	function renderCostsPanel( calc, price, commissionFree ) {
		var data = calcCosts( price, commissionFree );
		var itemsEl = $( '.immo-calc-items', calc );
		if ( itemsEl ) {
			itemsEl.innerHTML = data.items.map( function ( item ) {
				var rate = item.rate !== null && item.rate !== undefined
					? '<span class="immo-calc-row-rate">' + item.rate.toFixed( 1 ).replace( '.', settings.currency.decimal ) + ' %</span>'
					: '';
				return '<div class="immo-calc-row">' +
					'<span class="immo-calc-row-label">' + item.label + rate + '</span>' +
					'<span class="immo-calc-row-amount">' + formatMoney( item.amount ) + '</span>' +
					'</div>';
			} ).join( '' );
		}
		var totalEl = $( '.immo-calc-costs-total', calc );
		if ( totalEl ) { totalEl.textContent = formatMoney( data.total ); }
		var grandEl = $( '.immo-calc-grand-total-value', calc );
		if ( grandEl ) { grandEl.textContent = formatMoney( data.grandTotal ); }
		return data;
	}
```

- [ ] **Step 2: State-Objekt + Initial-Render in `initCalculator()` einbauen**

`initCalculator()` ersetzen:

```javascript
	function initCalculator( calc ) {
		var state = {
			price: parseFloat( calc.getAttribute( 'data-base-price' ) ) || 0,
			commissionFree: calc.getAttribute( 'data-commission-free' ) === '1',
		};

		initTabs( calc );
		initAmortizationToggle( calc );

		var priceInput = $( '#immo-calc-price', calc );
		if ( priceInput ) {
			priceInput.addEventListener( 'input', function () {
				state.price = Math.max( 0, parseFloat( priceInput.value ) || 0 );
				render();
			} );
		}

		function render() {
			renderCostsPanel( calc, state.price, state.commissionFree );
			// renderFinancingPanel kommt in Phase 5
		}
		render();

		calc._state = state;
		calc._render = render;
	}
```

- [ ] **Step 3: Manuell testen — Nebenkosten-Panel**

Browser → Property-Detailseite (oder temporär das Template manuell einbinden für Test). Akkordeon öffnen → Nebenkosten-Tab zeigt korrekte Werte; Kaufpreis-Input ändern → Werte aktualisieren live.

- [ ] **Step 4: Commit**

```bash
git add public/js/calculators.js
git commit -m "feat(calculator): render and live-update Nebenkosten panel"
```

---

## Phase 5 — Finanzierungs-Logik

### Task 5.1: `buildAmortization()` implementieren

**Files:**
- Modify: `public/js/calculators.js`

- [ ] **Step 1: Funktion einfügen**

Nach `renderCostsPanel()` einfügen:

```javascript
	function buildAmortization( principal, annualRate, termYears, extraPerYear ) {
		var K = Math.max( 0, principal );
		var n = Math.max( 1, termYears * 12 );
		var i = ( annualRate / 12 ) / 100;
		var m = i === 0 ? K / n : ( K * i ) / ( 1 - Math.pow( 1 + i, -n ) );
		extraPerYear = Math.max( 0, extraPerYear || 0 );

		var rows = [];
		var balance = K;
		var totalInterest = 0;
		var startYear = new Date().getFullYear();

		for ( var year = 1; year <= termYears; year++ ) {
			var yearInterest = 0;
			var yearPrincipal = 0;
			for ( var month = 1; month <= 12; month++ ) {
				if ( balance <= 0.01 ) { break; }
				var interestPart = balance * i;
				var principalPart = m - interestPart;
				if ( principalPart > balance ) { principalPart = balance; }
				balance -= principalPart;
				yearInterest += interestPart;
				yearPrincipal += principalPart;
			}
			if ( extraPerYear > 0 && balance > 0 ) {
				var extra = Math.min( extraPerYear, balance );
				balance -= extra;
				yearPrincipal += extra;
			}
			totalInterest += yearInterest;
			rows.push( {
				year: startYear + year - 1,
				interest: yearInterest,
				principal: yearPrincipal,
				balance: Math.max( 0, balance ),
			} );
			if ( balance <= 0.01 ) { break; }
		}
		return { rate: m, rows: rows, totalInterest: totalInterest, totalPayments: K + totalInterest };
	}
```

- [ ] **Step 2: Public API erweitern**

```javascript
	window.immoCalculators = {
		formatMoney: formatMoney,
		calcCosts: calcCosts,
		buildAmortization: buildAmortization,
	};
```

- [ ] **Step 3: Console-Test**

```js
var a = window.immoCalculators.buildAmortization( 300000, 3.5, 25, 0 );
Math.round( a.rate * 100 ) / 100;        // ~1502.06
a.rows.length;                            // 25
Math.round( a.rows[24].balance );         // 0 oder ~0
```

- [ ] **Step 4: Commit**

```bash
git add public/js/calculators.js
git commit -m "feat(calculator): implement buildAmortization logic"
```

---

### Task 5.2: Finanzierungs-Panel rendern und Inputs binden

**Files:**
- Modify: `public/js/calculators.js`

- [ ] **Step 1: Render-Funktion + Helper einfügen**

Nach `buildAmortization()` einfügen:

```javascript
	function renderFinancingPanel( calc, costs, financeState ) {
		var need = costs.grandTotal;

		var equityValue;
		if ( financeState.equityMode === 'pct' ) {
			equityValue = ( financeState.price * financeState.equity ) / 100;
		} else {
			equityValue = financeState.equity;
		}
		equityValue = Math.max( 0, equityValue );

		var loan = Math.max( 0, need - equityValue );

		var needEl = $( '.immo-calc-need', calc );
		if ( needEl ) { needEl.textContent = formatMoney( need ); }
		var equityEurEl = $( '.immo-calc-equity-eur', calc );
		if ( equityEurEl ) { equityEurEl.textContent = formatMoney( equityValue ); }
		var loanEl = $( '.immo-calc-loan', calc );
		if ( loanEl ) { loanEl.textContent = formatMoney( loan ); }

		var resultsEl = $( '.immo-calc-results', calc );
		var tableWrap = $( '.immo-amortization-table-wrap', calc );
		var tableToggle = $( '.immo-amortization-toggle', calc );

		if ( loan <= 0.5 ) {
			if ( resultsEl ) { resultsEl.innerHTML = '<div class="immo-calc-no-financing">' + ( settings.i18n.noFinancing || '' ) + '</div>'; }
			if ( tableToggle ) { tableToggle.style.display = 'none'; }
			if ( tableWrap ) { tableWrap.hidden = true; }
			return;
		}

		var amort = buildAmortization( loan, financeState.interest, financeState.term, financeState.extra );
		if ( resultsEl ) {
			resultsEl.innerHTML =
				'<div class="immo-calc-row"><span class="immo-calc-row-label">Monatliche Rate</span>' +
					'<span class="immo-calc-row-amount">' + formatMoney( amort.rate ) + '</span></div>' +
				'<div class="immo-calc-row"><span class="immo-calc-row-label">Gesamt-Zinsen</span>' +
					'<span class="immo-calc-row-amount">' + formatMoney( amort.totalInterest ) + '</span></div>' +
				'<div class="immo-calc-row"><span class="immo-calc-row-label">Gesamtaufwand</span>' +
					'<span class="immo-calc-row-amount">' + formatMoney( amort.totalPayments ) + '</span></div>' +
				'<div class="immo-calc-row"><span class="immo-calc-row-label">Tilgung-Ende</span>' +
					'<span class="immo-calc-row-amount">' + ( amort.rows.length ? amort.rows[ amort.rows.length - 1 ].year : '–' ) + '</span></div>';
		}

		if ( tableToggle ) { tableToggle.style.display = ''; }
		var tbody = $( '.immo-amortization-rows', calc );
		if ( tbody ) {
			tbody.innerHTML = amort.rows.map( function ( r ) {
				return '<tr>' +
					'<td>' + r.year + '</td>' +
					'<td>' + formatMoney( r.interest ) + '</td>' +
					'<td>' + formatMoney( r.principal ) + '</td>' +
					'<td>' + formatMoney( r.balance ) + '</td>' +
					'</tr>';
			} ).join( '' );
		}
	}
```

- [ ] **Step 2: `initCalculator()` um Finanzierungs-State und Bindings erweitern**

`initCalculator()` ersetzen:

```javascript
	function initCalculator( calc ) {
		var state = {
			price: parseFloat( calc.getAttribute( 'data-base-price' ) ) || 0,
			commissionFree: calc.getAttribute( 'data-commission-free' ) === '1',
		};
		var finance = {
			price: state.price,
			equity:   parseFloat( ( $( '#immo-calc-equity', calc )   || { value: settings.finance.equityPct } ).value )    || settings.finance.equityPct,
			equityMode: 'pct',
			interest: parseFloat( ( $( '#immo-calc-interest', calc ) || { value: settings.finance.interestRate } ).value ) || settings.finance.interestRate,
			term:     parseInt(   ( $( '#immo-calc-term', calc )     || { value: settings.finance.termYears } ).value, 10 ) || settings.finance.termYears,
			extra:    parseFloat( ( $( '#immo-calc-extra', calc )    || { value: settings.finance.extraPayment } ).value ) || settings.finance.extraPayment,
		};

		initTabs( calc );
		initAmortizationToggle( calc );

		// Equity-Mode-Toggle.
		$$( '.immo-calc-equity-toggle button', calc ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				$$( '.immo-calc-equity-toggle button', calc ).forEach( function ( b ) { b.classList.toggle( 'active', b === btn ); } );
				finance.equityMode = btn.getAttribute( 'data-mode' );
				render();
			} );
		} );

		// Input-Bindings.
		var bindings = {
			'#immo-calc-price':    function ( v ) { state.price = Math.max( 0, parseFloat( v ) || 0 ); finance.price = state.price; },
			'#immo-calc-equity':   function ( v ) { finance.equity = Math.max( 0, parseFloat( v ) || 0 ); },
			'#immo-calc-interest': function ( v ) { finance.interest = Math.max( 0, Math.min( 20, parseFloat( v ) || 0 ) ); },
			'#immo-calc-term':     function ( v ) { finance.term = Math.max( 1, Math.min( 50, parseInt( v, 10 ) || 1 ) ); },
			'#immo-calc-extra':    function ( v ) { finance.extra = Math.max( 0, parseFloat( v ) || 0 ); },
		};
		Object.keys( bindings ).forEach( function ( sel ) {
			var input = $( sel, calc );
			if ( input ) {
				input.addEventListener( 'input', function () {
					bindings[ sel ]( input.value );
					render();
				} );
			}
		} );

		function render() {
			var costs = renderCostsPanel( calc, state.price, state.commissionFree );
			renderFinancingPanel( calc, costs, finance );
		}
		render();

		calc._state = state;
		calc._finance = finance;
		calc._render = render;
	}
```

- [ ] **Step 3: Browser-Test**

Auf einer Kauf-Property mit Preis (z. B. 350.000 €) → Akkordeon öffnen → Tab "Finanzierung":
1. Finanzierungsbedarf = ~387.450 €.
2. Eigenkapital 20 % → ~77.490 €, Kreditsumme ~309.960 €.
3. Monatsrate ~1.551 €.
4. Werte ändern → live aktualisiert.
5. Equity-Toggle € klicken, 100000 eingeben → Kreditsumme = 287.450 €.
6. Tilgungsplan-Toggle → Tabelle zeigt Jahre, letzte Restschuld nahe 0.

- [ ] **Step 4: Commit**

```bash
git add public/js/calculators.js
git commit -m "feat(calculator): render financing panel with inputs and amortization table"
```

---

## Phase 6 — Property-Detail-Integration

### Task 6.1: Akkordeon in `property-detail.php` einbinden

**Files:**
- Modify: `templates/property-detail.php` (zwischen "Ausstattung"-Akkordeon und "Lage"-Akkordeon, ca. Zeile 326-327)

- [ ] **Step 1: Bedingungen + Include einfügen**

In `templates/property-detail.php` direkt vor dem "Lage"-Akkordeon-Block (suche nach `if ( $has_map || $meta['address'] )`) folgenden Block einfügen:

```php
			<?php
			// === Finanzierungs- & Nebenkostenrechner ===
			$dt    = (string) ( $meta['deal_type'] ?? '' );
			$is_sale = ( 'sale' === $dt || 'both' === $dt );
			$base_price_calc = (float) ( $meta['price'] ?? 0 );
			if ( $is_sale && $base_price_calc > 0 ) {
				$calc_context = array(
					'base_price'      => $base_price_calc,
					'commission_free' => (bool) ( $meta['commission_free'] ?? false ),
					'units'           => array(),
				);
				include IMMO_MANAGER_PLUGIN_DIR . 'templates/parts/calculator.php';
			}
			?>
```

- [ ] **Step 2: Verifikation `deal_type` im REST-Output prüfen**

Falls `meta.deal_type` im REST-Output fehlt, das vorhandene `_immo_mode` als Fallback nutzen:

```php
			$dt    = (string) ( $meta['deal_type'] ?? $meta['mode'] ?? '' );
```

Im aktuellen Repo wird in `format_property()` (`class-rest-api.php`) `_immo_mode` als `mode` ausgegeben (Zeile checken). Wenn `deal_type` nicht existiert, ersetzen.

- [ ] **Step 3: Browser-Test**

Eine Property mit:
- `deal_type=sale` (oder `_immo_mode=sale`) und `price > 0`: Akkordeon erscheint zwischen Ausstattung und Lage. ✓
- `deal_type=rent`: Akkordeon erscheint NICHT. ✓
- `price=0`: Akkordeon erscheint NICHT. ✓

- [ ] **Step 4: Commit**

```bash
git add templates/property-detail.php
git commit -m "feat(calculator): embed calculator on property detail page (sale only)"
```

---

## Phase 7 — Bauprojekt-Integration

### Task 7.1: REST-API liefert `commission_free` auch in Unit-Property aus

**Files:**
- Modify: `includes/class-rest-api.php` (`format_unit()`-Funktion oder beim `property`-Sub-Object darin)

- [ ] **Step 1: Sicherstellen, dass `commission_free` über `format_unit()` mitkommt**

In `format_unit()` wird das `property`-Sub-Objekt vermutlich über `format_property()` erzeugt. Prüfen, ob `commission_free` dort enthalten ist (Task 2.4 hat es im Property-meta-Array hinzugefügt; wenn `format_unit()` `format_property()` recyclt, ist es automatisch dabei).

Falls Unit-Properties nur ein leichtes Sub-Set sind und `commission_free` fehlt, im `property_data`-Array von `format_unit()` ergänzen:

```php
		'commission_free' => (bool) get_post_meta( (int) $unit['property_post_id'], '_immo_commission_free', true ),
```

(Den richtigen Post-ID-Key prüfen: `property_post_id` oder `post_id`).

- [ ] **Step 2: Manuell testen**

`curl /wp-json/immo-manager/v1/projects/<id>/units` → jede Unit hat `property.commission_free` (boolesch).

- [ ] **Step 3: Commit**

```bash
git add includes/class-rest-api.php
git commit -m "feat(calculator): expose commission_free per unit in REST output"
```

---

### Task 7.2: Akkordeon mit Unit-Dropdown in `single-immo_mgr_project.php`

**Files:**
- Modify: `templates/single-immo_mgr_project.php` (nach dem "Wohneinheiten"-Akkordeon, vor "Ausstattung")

- [ ] **Step 1: Block direkt nach `<!-- Wohneinheiten-Tabelle -->` und ihrem schließenden `</div>` einfügen**

Suche nach `<!-- Wohneinheiten-Tabelle -->` (ca. Zeile 238) und danach das schließende `</div>` des Akkordeons (ca. Zeile 289). Direkt davor (vor dem nächsten `<!-- Ausstattung -->`-Kommentar bei ca. Zeile 291) einfügen:

```php
			<?php
			// === Finanzierungs- & Nebenkostenrechner ===
			$calc_units = array();
			foreach ( $units as $u ) {
				if ( ! empty( $u['price'] ) && (float) $u['price'] > 0 ) {
					$prop = $u['property'] ?? array();
					$calc_units[] = array(
						'id'              => (int) $u['id'],
						'price'           => (float) $u['price'],
						'commission_free' => (bool) ( $prop['commission_free'] ?? false ),
						'label'           => sprintf(
							/* translators: 1: Wohneinheits-Nummer, 2: Fläche in m², 3: formatierter Preis */
							__( '%1$s — %2$s m² — %3$s', 'immo-manager' ),
							$u['unit_number'],
							number_format_i18n( (float) $u['area'], 0 ),
							$u['price_formatted']
						),
					);
				}
			}
			if ( ! empty( $calc_units ) ) {
				$first = $calc_units[0];
				$calc_context = array(
					'base_price'      => $first['price'],
					'commission_free' => $first['commission_free'],
					'units'           => $calc_units,
				);
				include IMMO_MANAGER_PLUGIN_DIR . 'templates/parts/calculator.php';
			}
			?>
```

- [ ] **Step 2: JS — Dropdown-Wechsel handhaben**

In `public/js/calculators.js`, in `initCalculator()` direkt vor `function render() { … }` einfügen:

```javascript
		var unitSelect = $( '#immo-calc-unit-select', calc );
		if ( unitSelect ) {
			unitSelect.addEventListener( 'change', function () {
				var opt = unitSelect.options[ unitSelect.selectedIndex ];
				if ( ! opt ) { return; }
				var newPrice = parseFloat( opt.getAttribute( 'data-price' ) ) || 0;
				state.price = newPrice;
				finance.price = newPrice;
				state.commissionFree = opt.getAttribute( 'data-commission-free' ) === '1';
				var priceInput = $( '#immo-calc-price', calc );
				if ( priceInput ) { priceInput.value = String( Math.round( newPrice ) ); }
				render();
			} );
		}
```

- [ ] **Step 3: Browser-Test**

Bauprojekt-Seite mit ≥1 Kauf-Unit öffnen:
1. Akkordeon "💰 Finanzierungs- & Nebenkostenrechner" sichtbar.
2. Dropdown listet alle Kauf-Units mit Label "Top X — 85 m² — 425.000 €".
3. Unit-Wechsel → Preis und Berechnungen aktualisieren in beiden Tabs.
4. Bauprojekt ohne Kauf-Units (nur Miet-Units oder gar keine) → KEIN Akkordeon.

- [ ] **Step 4: Commit**

```bash
git add templates/single-immo_mgr_project.php public/js/calculators.js
git commit -m "feat(calculator): embed calculator with unit dropdown on project page"
```

---

## Phase 8 — Polish & Edge Cases

### Task 8.1: Currency-Formatting in den Settings-Output respektieren

**Files:**
- Modify: `public/js/calculators.js` (in der Item-Rendering-Logik, falls Prozent-Wert nicht im Decimal-Separator dargestellt)

- [ ] **Step 1: Prozent-Display lokalisieren**

Schon in Task 4.2 implementiert (`item.rate.toFixed( 1 ).replace( '.', settings.currency.decimal )`). Verifizieren, dass im Browser `3,5 %` (mit Komma) erscheint statt `3.5 %`.

- [ ] **Step 2: Falls Inkonsistenz: anpassen, sonst nichts tun**

- [ ] **Step 3: Commit (falls Änderung)**

```bash
git add public/js/calculators.js
git commit -m "fix(calculator): respect currency decimal separator in rate display"
```

---

### Task 8.2: Edge-Case "Keine Finanzierung nötig" verifizieren

**Files:** keine Änderung — nur Test.

- [ ] **Step 1: Browser-Test**

Eigenkapital € auf 500.000 setzen (höher als Kaufpreis + Nebenkosten). Erwartet: Stelle wo Monatsrate stünde zeigt: "Keine Finanzierung nötig (Eigenkapital ≥ Bedarf)." Tilgungsplan-Toggle ausgeblendet.

- [ ] **Step 2: Falls Bug: in Task 5.2 die `if ( loan <= 0.5 )`-Branch korrigieren**

---

### Task 8.3: Edge-Case Zinssatz 0 % verifizieren

**Files:** keine Änderung — nur Test.

- [ ] **Step 1: Browser-Test**

Zinssatz auf 0 setzen, Kreditsumme z. B. 240.000 €, Laufzeit 20 Jahre.
Erwartet: Monatsrate = 240000 / 240 = 1.000 €/Monat. Gesamt-Zinsen = 0. Tilgungsplan zeigt 20 Jahre, jede Zeile Tilgung = 12.000 €.

---

### Task 8.4: Edge-Case Sondertilgung > Restschuld verifizieren

**Files:** keine Änderung — nur Test.

- [ ] **Step 1: Browser-Test**

Sondertilgung 100.000 €/Jahr bei Kreditsumme 200.000 €. Erwartet: Tilgung-Ende nach ~2 Jahren, letzte Restschuld = 0, kein Crash, keine negativen Zahlen.

---

### Task 8.5: Mobile-Responsive Check

**Files:** keine Änderung außer evtl. CSS-Korrekturen.

- [ ] **Step 1: Browser DevTools → Device Mode (375px Breite)**

- Akkordeon-Header lesbar.
- Tab-Buttons: kein Overflow.
- Plan-Tabelle: scrollbar.
- Zahlen rechtsbündig in Spalten.
- Inputs nehmen die volle Breite ein (Mobile-Media-Query in CSS).

- [ ] **Step 2: Falls Layout-Probleme: CSS-Anpassung in `calculators.css`**

- [ ] **Step 3: Commit (falls Änderung)**

```bash
git add public/css/calculators.css
git commit -m "fix(calculator): mobile layout polish"
```

---

### Task 8.6: Verifikations-Sweep aller Akzeptanz-Kriterien

**Files:** keine Änderung — abschließender Check.

- [ ] **Step 1: Akzeptanz-Liste durchgehen**

1. Akkordeon erscheint zwischen "Ausstattung" und "Lage" auf Kauf-Property mit Preis ✓
2. Akkordeon NICHT bei Miete oder Preis = 0 ✓
3. Beide Tabs zeigen korrekte Berechnungen mit AT-Defaults ✓
4. Eigenkapital €/% Toggle funktioniert ✓
5. Sondertilgung verkürzt Laufzeit ✓
6. Tilgungsplan-Tabelle korrekt, letzte Restschuld = 0 ✓
7. Alle Settings persistent änderbar ✓
8. Wizard/Metabox "Provisionsfrei" funktional ✓
9. Bauprojekt-Akkordeon mit Unit-Dropdown funktional ✓
10. Currency-Formatting folgt globalen Settings ✓

- [ ] **Step 2: Final-Commit (leer, als Marker)**

```bash
git commit --allow-empty -m "chore(calculator): all acceptance criteria verified"
```

---

## Plan-Selbstcheck (Pflichtcheck — nicht überspringen)

**Spec-Coverage:**
- R1 Akkordeon → Phase 6.1, 7.2 ✓
- R2 Bauprojekt + Dropdown → Phase 7.2 ✓
- R3 Basis Kauf+Nebenkosten → Phase 5.2 (`renderFinancingPanel` nutzt `costs.grandTotal`) ✓
- R4 Eigenkapital € und % → Phase 5.2 ✓
- R5 Tilgungsplan jährlich → Phase 5.1 + 5.2 ✓
- R6 Sondertilgung → Phase 5.1 + 5.2 ✓
- R7 Settings voll → Phase 1.1–1.3 ✓
- R8 Ein Akkordeon mit Tabs → Phase 3.2 + 3.3 ✓
- R9 Provisionsfrei global + Override → Phase 1.3 (global) + 2.x (Override) + 4.1 (JS-Logik) ✓
- R10 Nur Kauf+price>0 → Phase 6.1 (Bedingung) ✓
- R11 Clientseitig → Phase 4.1 + 5.1 ✓

**Placeholder-Scan:** keine TBD/TODO/FIXME im Plan.

**Type-Konsistenz:** `formatMoney`, `calcCosts`, `buildAmortization`, `renderCostsPanel`, `renderFinancingPanel` — durchgehend gleich benannt. Daten-Attribute `data-base-price`, `data-commission-free`, `data-units`, `data-mode`, `data-price` konsistent zwischen Template und JS.

**Spec-Lücken:** keine.

---

## Zusammenfassung

- **8 Phasen, 19 Tasks** mit ~75 Step-Checkboxes.
- Jede Phase ist eigenständig committet und testbar.
- Phasen 1+2 ändern nichts am Frontend (Settings + Meta).
- Phase 3 baut Skelett, ab Phase 4 wird Logik live.
- Phasen 6+7 binden den Rechner ein.
- Phase 8 ist Polish + Verifikation.
