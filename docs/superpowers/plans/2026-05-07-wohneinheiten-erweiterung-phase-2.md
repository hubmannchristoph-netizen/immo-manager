# Wohneinheiten-Erweiterung Phase 2 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Phase-1-Felder über REST verfügbar machen und im Manager-Frontend (`single-immo_mgr_project.php`) sowie immo-client (`single-project.php`, Lightbox-Datenpool) anzeigen.

**Architecture:** REST-DTOs in `class-rest-api.php` werden um die neuen Unit-Felder und `meta.parking` erweitert. Der `immo-client` reicht REST-Daten transparent durch — keine Client-API-Änderungen nötig, nur Templates+CSS. Help-Doku in beiden Plugins kurz aktualisiert.

**Tech Stack:** PHP 7.4+, WordPress REST API, PHP-Templates (kein JS-Build), inline CSS-Variablen.

**Hinweis Tests:** Kein PHPUnit-Setup. Verifikation via `curl` auf REST-Endpoints + visuelle Prüfung im Browser.

**Spec:** `docs/superpowers/specs/2026-05-07-wohneinheiten-erweiterung-phase-2-rest-und-anzeige-design.md`

---

## File Structure

| Datei                                               | Aktion | Verantwortung                                          |
|-----------------------------------------------------|--------|--------------------------------------------------------|
| `immo-manager/includes/class-rest-api.php`          | Modify | `format_unit()` + `format_project()` um neue Felder    |
| `immo-manager/templates/single-immo_mgr_project.php`| Modify | Stellplatz-Akkordeon + Quick-Info-Daten erweitern      |
| `immo-manager/public/css/frontend.css`              | Modify | CSS für `.immo-parking-list` + Komponenten             |
| `immo-manager/includes/class-admin-pages.php`       | Modify | Help-Doku-Eintrag                                      |
| `immo-client/templates/single-project.php`          | Modify | Stellplatz-Sektion + Lightbox-Datenpool                |
| `immo-client/templates/units-lightbox-data.php`     | Modify | Lightbox-Datenpool um Zusatzflächen erweitern          |
| `immo-client/assets/css/project-detail.css`         | Modify | CSS für Stellplatz-Sektion                             |
| `immo-client/includes/class-immo-help.php`          | Modify | Help-Doku-Eintrag                                      |

---

### Task 1: REST-API — `format_unit()` + `format_project()` erweitern

**Files:**
- Modify: `immo-manager/includes/class-rest-api.php` (`format_unit()` ~Zeile 1187, `format_project()` ~Zeile 1108)

- [ ] **Step 1: `format_unit()` um neue Felder erweitern**

In `class-rest-api.php` direkt nach `'usable_area' => (float) $unit['usable_area'],` (Zeile ~1194), vor `'rooms' => (int) $unit['rooms'],`:

```php
				'usable_area'     => (float) $unit['usable_area'],
				'balcony_area'    => (float) ( $unit['balcony_area'] ?? 0 ),
				'loggia_area'     => (float) ( $unit['loggia_area']  ?? 0 ),
				'garden_area'     => (float) ( $unit['garden_area']  ?? 0 ),
				'cellar_area'     => (float) ( $unit['cellar_area']  ?? 0 ),
				'parking'         => array(
					'garage_count'           => (int)   ( $unit['parking_garage_count']  ?? 0 ),
					'outdoor_count'          => (int)   ( $unit['parking_outdoor_count'] ?? 0 ),
					'garage_price_override'  => null === ( $unit['parking_garage_price_override']  ?? null ) ? null : (float) $unit['parking_garage_price_override'],
					'outdoor_price_override' => null === ( $unit['parking_outdoor_price_override'] ?? null ) ? null : (float) $unit['parking_outdoor_price_override'],
				),
				'rooms'           => (int) $unit['rooms'],
```

- [ ] **Step 2: `format_project()` `meta.parking` ergänzen**

In `class-rest-api.php` im `format_project()`-Result-Array, im `meta`-Block direkt nach `'custom_features' => (string) $m( '_immo_custom_features', '' ),` (Zeile ~1108):

```php
				'custom_features'    => (string) $m( '_immo_custom_features', '' ),
				'parking'            => array(
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

(Achtung Komma-Schluss: das `)` der `'meta' => array(...)` schließt direkt danach.)

- [ ] **Step 3: PHP-Syntax-Check**

```bash
php -l immo-manager/includes/class-rest-api.php
```

Erwartung: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add immo-manager/includes/class-rest-api.php
git commit -m "feat(rest): zusatzflaechen + stellplatz-config in unit-/project-DTO"
```

---

### Task 2: Manager-Frontend — Stellplatz-Akkordeon + Quick-Info-Erweiterung

**Files:**
- Modify: `immo-manager/templates/single-immo_mgr_project.php` (Akkordeons + Quick-Info-Block + JS)

- [ ] **Step 1: Stellplatz-Akkordeon nach „Ausstattung" einfügen**

In `templates/single-immo_mgr_project.php` direkt nach dem schließenden `</div>` des Ausstattung-Akkordeons (nach `<?php endif; ?>` ~Zeile 337) und vor dem Dokumente-Akkordeon (`<?php if ( ! empty( $meta['documents'] ) )` ~Zeile 339):

```php
			<?php
			$pk         = $meta['parking'] ?? array();
			$pk_garage  = $pk['garage']  ?? array();
			$pk_outdoor = $pk['outdoor'] ?? array();
			$pk_notes   = (string) ( $pk['notes'] ?? '' );
			$has_garage  = ! empty( $pk_garage['available'] );
			$has_outdoor = ! empty( $pk_outdoor['available'] );
			if ( $has_garage || $has_outdoor || '' !== $pk_notes ) :
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
									<span class="immo-parking-icon" aria-hidden="true">🅿️</span>
									<div class="immo-parking-meta">
										<strong class="immo-parking-title"><?php esc_html_e( 'Tiefgaragenplatz', 'immo-manager' ); ?></strong>
										<span class="immo-parking-price">
											<?php
											$gprice = (float) ( $pk_garage['price'] ?? 0 );
											echo esc_html( $gprice > 0 ? number_format_i18n( $gprice, 0 ) . ' ' . $currency : __( 'Preis auf Anfrage', 'immo-manager' ) );
											?>
										</span>
										<span class="immo-parking-flag immo-parking-flag-<?php echo ! empty( $pk_garage['required'] ) ? 'required' : 'optional'; ?>">
											<?php echo ! empty( $pk_garage['required'] ) ? esc_html__( 'verpflichtend', 'immo-manager' ) : esc_html__( 'optional', 'immo-manager' ); ?>
										</span>
										<?php if ( (int) ( $pk_garage['total'] ?? 0 ) > 0 ) : ?>
											<span class="immo-parking-total">
												<?php
												/* translators: %d: Gesamtanzahl der Stellplaetze */
												printf( esc_html__( '%d Plätze gesamt', 'immo-manager' ), (int) $pk_garage['total'] );
												?>
											</span>
										<?php endif; ?>
									</div>
								</li>
							<?php endif; ?>
							<?php if ( $has_outdoor ) : ?>
								<li class="immo-parking-item">
									<span class="immo-parking-icon" aria-hidden="true">🚗</span>
									<div class="immo-parking-meta">
										<strong class="immo-parking-title"><?php esc_html_e( 'Außen-Stellplatz', 'immo-manager' ); ?></strong>
										<span class="immo-parking-price">
											<?php
											$oprice = (float) ( $pk_outdoor['price'] ?? 0 );
											echo esc_html( $oprice > 0 ? number_format_i18n( $oprice, 0 ) . ' ' . $currency : __( 'Preis auf Anfrage', 'immo-manager' ) );
											?>
										</span>
										<span class="immo-parking-flag immo-parking-flag-<?php echo ! empty( $pk_outdoor['required'] ) ? 'required' : 'optional'; ?>">
											<?php echo ! empty( $pk_outdoor['required'] ) ? esc_html__( 'verpflichtend', 'immo-manager' ) : esc_html__( 'optional', 'immo-manager' ); ?>
										</span>
										<?php if ( (int) ( $pk_outdoor['total'] ?? 0 ) > 0 ) : ?>
											<span class="immo-parking-total">
												<?php
												/* translators: %d: Gesamtanzahl der Stellplaetze */
												printf( esc_html__( '%d Plätze gesamt', 'immo-manager' ), (int) $pk_outdoor['total'] );
												?>
											</span>
										<?php endif; ?>
									</div>
								</li>
							<?php endif; ?>
						</ul>
						<?php if ( '' !== $pk_notes ) : ?>
							<p class="immo-parking-notes"><?php echo esc_html( $pk_notes ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
```

- [ ] **Step 2: Quick-Info `data-property`-Attribut um Unit-Zusatzfelder erweitern**

In `templates/single-immo_mgr_project.php` Zeile 294 ersetzen:

**Alt:**
```php
<button type="button" class="immo-units-action-btn immo-quick-info-btn" data-property="<?php echo esc_attr( wp_json_encode( $unit['property'] ) ); ?>" aria-label="<?php esc_attr_e( 'Quick-Info anzeigen', 'immo-manager' ); ?>" title="<?php esc_attr_e( 'Quick-Info anzeigen', 'immo-manager' ); ?>">
```

**Neu:**
```php
<?php
$qi_data = array_merge(
    is_array( $unit['property'] ?? null ) ? $unit['property'] : array(),
    array(
        'unit_balcony_area' => (float) ( $unit['balcony_area'] ?? 0 ),
        'unit_loggia_area'  => (float) ( $unit['loggia_area']  ?? 0 ),
        'unit_garden_area'  => (float) ( $unit['garden_area']  ?? 0 ),
        'unit_cellar_area'  => (float) ( $unit['cellar_area']  ?? 0 ),
        'unit_parking'      => array(
            'garage_count'  => (int) ( $unit['parking']['garage_count']  ?? 0 ),
            'outdoor_count' => (int) ( $unit['parking']['outdoor_count'] ?? 0 ),
        ),
    )
);
?>
<button type="button" class="immo-units-action-btn immo-quick-info-btn" data-property="<?php echo esc_attr( wp_json_encode( $qi_data ) ); ?>" aria-label="<?php esc_attr_e( 'Quick-Info anzeigen', 'immo-manager' ); ?>" title="<?php esc_attr_e( 'Quick-Info anzeigen', 'immo-manager' ); ?>">
```

- [ ] **Step 3: Quick-Info-Lightbox-HTML um Zusatzflächen-Container erweitern**

In `templates/single-immo_mgr_project.php` im Lightbox-HTML — direkt nach dem `<div id="immo-qi-facts">`-Block (~Zeile 525), vor dem `<strong id="immo-qi-price">`-Tag:

**Vor `<strong id="immo-qi-price"...>` einfügen:**
```php
			<div id="immo-qi-extras" style="display: flex; justify-content: center; gap: 0.9rem; flex-wrap: wrap; font-size: 0.88em; color: #4b5563; margin-bottom: 1.25rem;"></div>
```

- [ ] **Step 4: JS-Logik für Zusatzflächen + Stellplatz-Inkludierung**

In `templates/single-immo_mgr_project.php` im `<script>`-Block direkt nach der Zeile mit `document.getElementById('immo-qi-energy').innerHTML = ...` (~Zeile 571) und vor `document.getElementById('immo-qi-price').innerHTML = ...`:

```js
				// Zusatzflächen (Balkon, Loggia, Garten, Keller) + Stellplatz-Inkludierung
				const extras = document.getElementById('immo-qi-extras');
				if (extras) {
					const items = [];
					if (data.unit_balcony_area > 0) items.push('🏔️ <?php echo esc_js( __( 'Balkon', 'immo-manager' ) ); ?> ' + data.unit_balcony_area + ' m²');
					if (data.unit_loggia_area  > 0) items.push('🏛️ <?php echo esc_js( __( 'Loggia', 'immo-manager' ) ); ?> ' + data.unit_loggia_area  + ' m²');
					if (data.unit_garden_area  > 0) items.push('🌿 <?php echo esc_js( __( 'Garten', 'immo-manager' ) ); ?> ' + data.unit_garden_area  + ' m²');
					if (data.unit_cellar_area  > 0) items.push('🏚️ <?php echo esc_js( __( 'Keller', 'immo-manager' ) ); ?> ' + data.unit_cellar_area  + ' m²');
					const pk = data.unit_parking || {};
					if (pk.garage_count  > 0) items.push('🅿️ ' + pk.garage_count  + '× <?php echo esc_js( __( 'TG-Platz inkl.', 'immo-manager' ) ); ?>');
					if (pk.outdoor_count > 0) items.push('🚗 ' + pk.outdoor_count + '× <?php echo esc_js( __( 'Stellplatz inkl.', 'immo-manager' ) ); ?>');
					extras.innerHTML = items.map(s => '<span>' + s + '</span>').join('');
					extras.style.display = items.length ? 'flex' : 'none';
				}
```

- [ ] **Step 5: PHP-Syntax-Check**

```bash
php -l immo-manager/templates/single-immo_mgr_project.php
```

Erwartung: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add immo-manager/templates/single-immo_mgr_project.php
git commit -m "feat(project): stellplatz-akkordeon + quick-info zeigt zusatzflaechen"
```

---

### Task 3: Manager-CSS — Stellplatz-Sektion stylen

**Files:**
- Modify: `immo-manager/public/css/frontend.css`

- [ ] **Step 1: CSS-Block für Stellplatz-Komponenten ergänzen**

In `public/css/frontend.css` ans Ende der Datei anhängen:

```css
/* ============================================================
   STELLPLATZ-SEKTION (Phase 2 Wohneinheiten-Erweiterung)
   ============================================================ */
.immo-parking-list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
}
.immo-parking-item {
	display: flex;
	align-items: flex-start;
	gap: 0.9rem;
	padding: 0.85rem 1rem;
	background: var(--immo-bg);
	border: 1px solid var(--immo-border);
	border-radius: var(--immo-radius-sm);
}
.immo-parking-icon {
	font-size: 1.5rem;
	line-height: 1;
	flex-shrink: 0;
}
.immo-parking-meta {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 0.4rem 0.9rem;
	flex: 1;
	min-width: 0;
}
.immo-parking-title {
	font-weight: 700;
	color: var(--immo-text);
	margin-right: auto;
}
.immo-parking-price {
	font-weight: 700;
	color: var(--immo-accent);
}
.immo-parking-flag {
	font-size: 0.72rem;
	font-weight: 700;
	letter-spacing: 0.05em;
	text-transform: uppercase;
	padding: 0.18em 0.55em;
	border-radius: 999px;
}
.immo-parking-flag-required { background: #fee2e2; color: #991b1b; }
.immo-parking-flag-optional { background: #e5e7eb; color: #374151; }
.immo-parking-total {
	font-size: 0.85rem;
	color: var(--immo-text-muted);
}
.immo-parking-notes {
	margin: 0.9rem 0 0;
	padding: 0.7rem 0.9rem;
	background: var(--immo-bg-tinted, #f3f4f6);
	border-left: 3px solid var(--immo-accent);
	border-radius: 6px;
	font-size: 0.92rem;
	color: var(--immo-text-muted);
}
@media (max-width: 600px) {
	.immo-parking-meta { gap: 0.25rem 0.6rem; }
	.immo-parking-title { width: 100%; margin-right: 0; }
}
```

- [ ] **Step 2: Commit**

```bash
git add immo-manager/public/css/frontend.css
git commit -m "feat(css): stellplatz-akkordeon styling"
```

---

### Task 4: Manager-Help-Doku ergänzen

**Files:**
- Modify: `immo-manager/includes/class-admin-pages.php` (Help/Doku-Tab)

- [ ] **Step 1: Hilfe-Eintrag zu neuen REST-Feldern**

Datei `includes/class-admin-pages.php` öffnen und nach dem Abschnitt zu `units` (Suche `'orderby'` Tabelle, ~Zeile 924) den folgenden Hinweis-Block ergänzen:

```html
<h3>Wohneinheiten-Zusatzfelder (ab Manager 1.1.0)</h3>
<p>Pro Einheit zusätzlich: <code>balcony_area</code>, <code>loggia_area</code>, <code>garden_area</code>, <code>cellar_area</code> (alle in m², optional). Stellplatz-Inkludierung pro Einheit als <code>parking.garage_count</code> + <code>parking.outdoor_count</code>; Override-Preis (NULL = Projekt-Default) als <code>parking.garage_price_override</code> / <code>parking.outdoor_price_override</code>.</p>
<p>Das Bauprojekt liefert <code>meta.parking.garage.{available,total,price,required}</code>, <code>meta.parking.outdoor.{...}</code>, <code>meta.parking.notes</code> für die zentrale Stellplatz-Konfiguration.</p>
```

Die genaue Position findest du, indem du nach dem `units`-Endpoint-Block suchst (Suchwort: „orderby" oder „price`|`floor"). Block direkt darunter einfügen.

- [ ] **Step 2: PHP-Syntax-Check**

```bash
php -l immo-manager/includes/class-admin-pages.php
```

- [ ] **Step 3: Commit**

```bash
git add immo-manager/includes/class-admin-pages.php
git commit -m "docs(help): rest-felder fuer wohneinheiten-zusatzdaten dokumentiert"
```

---

### Task 5: Client — Stellplatz-Sektion + Lightbox-Datenpool

**Files:**
- Modify: `immo-client/templates/single-project.php` (Stellplatz-Sektion + Lightbox-Datenpool-Items)
- Modify: `immo-client/templates/units-lightbox-data.php` (Datenpool-Items für Shortcode-Variante)

- [ ] **Step 1: Stellplatz-Sektion in `single-project.php` einfügen**

In `immo-client/templates/single-project.php` direkt nach dem schließenden `</section>` der „Gemeinschafts-Ausstattung" (~Zeile 294) und vor `<!-- ========== CTA-BANNER ========== -->`:

```php
		<?php
		$pk         = $meta['parking'] ?? array();
		$pk_garage  = $pk['garage']  ?? array();
		$pk_outdoor = $pk['outdoor'] ?? array();
		$pk_notes   = (string) ( $pk['notes'] ?? '' );
		$has_garage  = ! empty( $pk_garage['available'] );
		$has_outdoor = ! empty( $pk_outdoor['available'] );
		if ( $has_garage || $has_outdoor || '' !== $pk_notes ) :
			$fmt_money = static function ( $val ) {
				$val = (float) $val;
				return $val > 0 ? number_format_i18n( $val, 0 ) . ' €' : __( 'Preis auf Anfrage', 'immo-client' );
			};
		?>
		<section class="immo-section immo-project-parking">
			<h2><?php esc_html_e( 'Stellplätze', 'immo-client' ); ?></h2>
			<ul class="immo-parking-list">
				<?php if ( $has_garage ) : ?>
					<li class="immo-parking-item">
						<span class="immo-parking-icon" aria-hidden="true">🅿️</span>
						<div class="immo-parking-meta">
							<strong class="immo-parking-title"><?php esc_html_e( 'Tiefgaragenplatz', 'immo-client' ); ?></strong>
							<span class="immo-parking-price"><?php echo esc_html( $fmt_money( $pk_garage['price'] ?? 0 ) ); ?></span>
							<span class="immo-parking-flag immo-parking-flag-<?php echo ! empty( $pk_garage['required'] ) ? 'required' : 'optional'; ?>">
								<?php echo ! empty( $pk_garage['required'] ) ? esc_html__( 'verpflichtend', 'immo-client' ) : esc_html__( 'optional', 'immo-client' ); ?>
							</span>
							<?php if ( (int) ( $pk_garage['total'] ?? 0 ) > 0 ) : ?>
								<span class="immo-parking-total"><?php
									/* translators: %d: Gesamtanzahl */
									printf( esc_html__( '%d Plätze gesamt', 'immo-client' ), (int) $pk_garage['total'] );
								?></span>
							<?php endif; ?>
						</div>
					</li>
				<?php endif; ?>
				<?php if ( $has_outdoor ) : ?>
					<li class="immo-parking-item">
						<span class="immo-parking-icon" aria-hidden="true">🚗</span>
						<div class="immo-parking-meta">
							<strong class="immo-parking-title"><?php esc_html_e( 'Außen-Stellplatz', 'immo-client' ); ?></strong>
							<span class="immo-parking-price"><?php echo esc_html( $fmt_money( $pk_outdoor['price'] ?? 0 ) ); ?></span>
							<span class="immo-parking-flag immo-parking-flag-<?php echo ! empty( $pk_outdoor['required'] ) ? 'required' : 'optional'; ?>">
								<?php echo ! empty( $pk_outdoor['required'] ) ? esc_html__( 'verpflichtend', 'immo-client' ) : esc_html__( 'optional', 'immo-client' ); ?>
							</span>
							<?php if ( (int) ( $pk_outdoor['total'] ?? 0 ) > 0 ) : ?>
								<span class="immo-parking-total"><?php
									/* translators: %d: Gesamtanzahl */
									printf( esc_html__( '%d Plätze gesamt', 'immo-client' ), (int) $pk_outdoor['total'] );
								?></span>
							<?php endif; ?>
						</div>
					</li>
				<?php endif; ?>
			</ul>
			<?php if ( '' !== $pk_notes ) : ?>
				<p class="immo-parking-notes"><?php echo esc_html( $pk_notes ); ?></p>
			<?php endif; ?>
		</section>
		<?php endif; ?>
```

- [ ] **Step 2: Lightbox-Datenpool in `single-project.php` um Zusatzflächen erweitern**

In `immo-client/templates/single-project.php` im Lightbox-Datenpool-Block (`<div id="immo-unit-lightbox-data">`, ~Zeile 387ff) im `<ul class="immo-unit-quick-facts">`-Inhalt direkt nach dem Energieklasse-Item:

```php
								<?php if ( ! empty( $u_energy ) ) : ?><li><span class="ico" aria-hidden="true">⚡</span><span class="lab">Energieklasse</span><strong><?php echo esc_html( $u_energy ); ?></strong></li><?php endif; ?>
								<?php if ( ! empty( $unit['balcony_area'] ) && (float) $unit['balcony_area'] > 0 ) : ?>
									<li><span class="ico" aria-hidden="true">🏔️</span><span class="lab">Balkon</span><strong><?php echo esc_html( (string) $unit['balcony_area'] ); ?> m²</strong></li>
								<?php endif; ?>
								<?php if ( ! empty( $unit['loggia_area'] ) && (float) $unit['loggia_area'] > 0 ) : ?>
									<li><span class="ico" aria-hidden="true">🏛️</span><span class="lab">Loggia</span><strong><?php echo esc_html( (string) $unit['loggia_area'] ); ?> m²</strong></li>
								<?php endif; ?>
								<?php if ( ! empty( $unit['garden_area'] ) && (float) $unit['garden_area'] > 0 ) : ?>
									<li><span class="ico" aria-hidden="true">🌿</span><span class="lab">Garten</span><strong><?php echo esc_html( (string) $unit['garden_area'] ); ?> m²</strong></li>
								<?php endif; ?>
								<?php if ( ! empty( $unit['cellar_area'] ) && (float) $unit['cellar_area'] > 0 ) : ?>
									<li><span class="ico" aria-hidden="true">🏚️</span><span class="lab">Keller</span><strong><?php echo esc_html( (string) $unit['cellar_area'] ); ?> m²</strong></li>
								<?php endif; ?>
								<?php $pk_unit = $unit['parking'] ?? array(); ?>
								<?php if ( ! empty( $pk_unit['garage_count'] ) ) : ?>
									<li><span class="ico" aria-hidden="true">🅿️</span><span class="lab">Tiefgarage</span><strong>inkl. <?php echo (int) $pk_unit['garage_count']; ?>×</strong></li>
								<?php endif; ?>
								<?php if ( ! empty( $pk_unit['outdoor_count'] ) ) : ?>
									<li><span class="ico" aria-hidden="true">🚗</span><span class="lab">Stellplatz</span><strong>inkl. <?php echo (int) $pk_unit['outdoor_count']; ?>×</strong></li>
								<?php endif; ?>
```

- [ ] **Step 3: Identische Items in `units-lightbox-data.php` (Shortcode-Variante)**

In `immo-client/templates/units-lightbox-data.php` analog zu Step 2 — direkt nach dem Energieklasse-Item (`<?php if ( $u_energy ) : ?>...`, ~Zeile 110-112) den gleichen Block einfügen. Die Variablennamen sind dort `$unit` (für Direkt-Felder) — Block ist 1:1 übernehmbar.

- [ ] **Step 4: PHP-Syntax-Check beider Dateien**

```bash
php -l immo-client/templates/single-project.php && php -l immo-client/templates/units-lightbox-data.php
```

Erwartung beider: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add immo-client/templates/single-project.php immo-client/templates/units-lightbox-data.php
git commit -m "feat(project): stellplatz-sektion + zusatzflaechen in lightbox"
```

---

### Task 6: Client-CSS — Stellplatz-Sektion

**Files:**
- Modify: `immo-client/assets/css/project-detail.css`

- [ ] **Step 1: Identisches CSS-Block ans Ende anhängen (mit Client-CSS-Variablen)**

In `immo-client/assets/css/project-detail.css` ans Ende der Datei anhängen:

```css
/* ============================================================
   STELLPLATZ-SEKTION (Phase 2 Wohneinheiten-Erweiterung)
   ============================================================ */
.immo-project-singlecol .immo-parking-list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
}
.immo-project-singlecol .immo-parking-item {
	display: flex;
	align-items: flex-start;
	gap: 0.9rem;
	padding: 0.85rem 1rem;
	background: var(--ip-bg);
	border: 1px solid var(--ip-border);
	border-radius: var(--ip-radius-sm);
}
.immo-project-singlecol .immo-parking-icon {
	font-size: 1.5rem;
	line-height: 1;
	flex-shrink: 0;
}
.immo-project-singlecol .immo-parking-meta {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 0.4rem 0.9rem;
	flex: 1;
	min-width: 0;
}
.immo-project-singlecol .immo-parking-title {
	font-weight: 700;
	color: var(--ip-text);
	margin-right: auto;
}
.immo-project-singlecol .immo-parking-price {
	font-weight: 700;
	color: var(--ip-primary);
}
.immo-project-singlecol .immo-parking-flag {
	font-size: 0.72rem;
	font-weight: 700;
	letter-spacing: 0.05em;
	text-transform: uppercase;
	padding: 0.18em 0.55em;
	border-radius: 999px;
}
.immo-project-singlecol .immo-parking-flag-required { background: #fee2e2; color: #991b1b; }
.immo-project-singlecol .immo-parking-flag-optional { background: #e5e7eb; color: #374151; }
.immo-project-singlecol .immo-parking-total {
	font-size: 0.85rem;
	color: var(--ip-text-muted);
}
.immo-project-singlecol .immo-parking-notes {
	margin: 0.9rem 0 0;
	padding: 0.7rem 0.9rem;
	background: var(--ip-bg-tinted);
	border-left: 3px solid var(--ip-primary);
	border-radius: 6px;
	font-size: 0.92rem;
	color: var(--ip-text-muted);
}
@media (max-width: 600px) {
	.immo-project-singlecol .immo-parking-meta { gap: 0.25rem 0.6rem; }
	.immo-project-singlecol .immo-parking-title { width: 100%; margin-right: 0; }
}
```

- [ ] **Step 2: Commit**

```bash
git add immo-client/assets/css/project-detail.css
git commit -m "feat(css): stellplatz-sektion fuer client-projekt-detail"
```

---

### Task 7: Client-Help-Doku ergänzen

**Files:**
- Modify: `immo-client/includes/class-immo-help.php`

- [ ] **Step 1: Hinweis zu neuen REST-Feldern**

Datei `immo-client/includes/class-immo-help.php` öffnen, im Abschnitt „REST-Endpunkte (Manager)" (Suchwort `<h2 class="title">8. REST-Endpunkte`, ~Zeile 325) den letzten `<p>`-Block (über `commission_free_label`, Zeile 335) erweitern oder direkt darunter einfügen:

```html
<p>Ab Manager 1.1.0 zusätzlich: pro Einheit <code>balcony_area</code>, <code>loggia_area</code>, <code>garden_area</code>, <code>cellar_area</code> (m²), sowie <code>parking.garage_count</code>/<code>parking.outdoor_count</code> für Inkludierung. Pro Bauprojekt <code>meta.parking.garage</code>, <code>meta.parking.outdoor</code> (jeweils <code>available</code>, <code>total</code>, <code>price</code>, <code>required</code>) plus <code>meta.parking.notes</code> für die zentrale Stellplatz-Konfiguration.</p>
```

- [ ] **Step 2: PHP-Syntax-Check**

```bash
php -l immo-client/includes/class-immo-help.php
```

- [ ] **Step 3: Commit**

```bash
git add immo-client/includes/class-immo-help.php
git commit -m "docs(help): neue rest-felder fuer wohneinheiten-zusatzdaten"
```

---

### Task 8: Manuelle Verifikation

Voraussetzung: lokale WP-Installation mit Manager + Client aktiv, mind. ein Bauprojekt mit Wohneinheiten.

- [ ] **Step 1: REST-Endpoint Unit prüfen**

Eine Unit-ID aus der DB holen, dann:

```bash
curl -s "https://<host>/wp-json/immo-manager/v1/projects/<id>/units" | jq '.units[0]'
```

Erwartung: Felder `balcony_area`, `loggia_area`, `garden_area`, `cellar_area` (numerisch, default 0) und `parking` (Object mit garage_count, outdoor_count, garage_price_override, outdoor_price_override) vorhanden.

- [ ] **Step 2: REST-Endpoint Project prüfen**

```bash
curl -s "https://<host>/wp-json/immo-manager/v1/projects/<id>" | jq '.meta.parking'
```

Erwartung: Object mit `garage`, `outdoor`, `notes`.

- [ ] **Step 3: Test-Daten für ein Projekt setzen** (WP-CLI)

```bash
wp post meta update <project_id> _immo_parking_garage_available 1
wp post meta update <project_id> _immo_parking_garage_total 25
wp post meta update <project_id> _immo_parking_garage_price 28000
wp post meta update <project_id> _immo_parking_garage_required 1
wp post meta update <project_id> _immo_parking_outdoor_available 1
wp post meta update <project_id> _immo_parking_outdoor_price 12000
wp post meta update <project_id> _immo_parking_notes "1 TG-Platz pro Einheit verpflichtend, weitere auf Anfrage."
```

Plus eine Unit:

```bash
wp db query "UPDATE wp_immo_units SET balcony_area=8.5, cellar_area=4.2, parking_garage_count=1 WHERE id=<unit_id>"
```

- [ ] **Step 4: Manager-Frontend prüfen**

Öffne `/<bauprojekt-slug>/` (vom Manager gerendert). Erwartung:
- Stellplatz-Akkordeon zwischen Ausstattung und Dokumente sichtbar.
- Akkordeon zeigt TG-Platz mit Preis 28.000 €, Flag „verpflichtend", „25 Plätze gesamt".
- Klick auf 🔍 bei der modifizierten Einheit → Quick-Info-Modal zeigt Balkon 8.5 m², Keller 4.2 m², 1× TG-Platz inkl.

- [ ] **Step 5: Client-Frontend prüfen**

Cache leeren (Settings → Cache-Dauer auf 0). Öffne `/<bauprojekt-slug>/` auf dem Client. Erwartung:
- Identisches Stellplatz-Sektion-Aussehen.
- Klick auf eine Wohneinheits-Zeile → Lightbox zeigt Balkon/Keller/TG-Platz Items.

- [ ] **Step 6: Test-Daten zurücksetzen**

```bash
wp db query "UPDATE wp_immo_units SET balcony_area=0, cellar_area=0, parking_garage_count=0 WHERE id=<unit_id>"
wp post meta delete <project_id> _immo_parking_garage_available
wp post meta delete <project_id> _immo_parking_garage_total
wp post meta delete <project_id> _immo_parking_garage_price
wp post meta delete <project_id> _immo_parking_garage_required
wp post meta delete <project_id> _immo_parking_outdoor_available
wp post meta delete <project_id> _immo_parking_outdoor_price
wp post meta delete <project_id> _immo_parking_notes
```

(Oder als Test-Daten erhalten falls für UI-Demo gewünscht.)

---

### Task 9: Spec auf „Implemented" + Versions-Bump immo-client

**Files:**
- Modify: `immo-manager/docs/superpowers/specs/2026-05-07-wohneinheiten-erweiterung-phase-2-rest-und-anzeige-design.md`
- Modify: `immo-client/immo-client.php` (Version)

- [ ] **Step 1: Spec-Status anpassen**

```markdown
**Status:** Implemented (2026-05-07)
```

- [ ] **Step 2: immo-client Plugin-Version anheben**

In `immo-client/immo-client.php` die `Version`-Zeile im Header und ggf. `IMMO_CLIENT_VERSION`-Konstante von aktueller Version + 1 minor (z. B. 1.0.0 → 1.1.0). Aktuelle Version vorher mit `head -20 immo-client/immo-client.php` prüfen.

- [ ] **Step 3: Commit**

```bash
git add immo-manager/docs/superpowers/specs/2026-05-07-wohneinheiten-erweiterung-phase-2-rest-und-anzeige-design.md immo-client/immo-client.php
git commit -m "chore(release): phase 2 implemented + immo-client version bump"
```

---

## Self-Review

**1. Spec coverage:**
- REST `format_unit()` neue Felder → Task 1 ✓
- REST `format_project()` `meta.parking` → Task 1 ✓
- Manager Stellplatz-Akkordeon → Task 2 ✓
- Manager Quick-Info Zusatzflächen → Task 2 ✓
- Manager CSS → Task 3 ✓
- Manager Help-Doku → Task 4 ✓
- Client Stellplatz-Sektion → Task 5 ✓
- Client Lightbox-Datenpool → Task 5 ✓
- Client CSS → Task 6 ✓
- Client Help-Doku → Task 7 ✓
- Conditional Rendering (kein leeres Akkordeon) → Bedingungen `$has_garage || $has_outdoor || '' !== $pk_notes` in Tasks 2 & 5 ✓
- Override-Preis NULL-respektierend → REST-Code in Task 1 ✓
- Verifikation → Task 8 ✓

**2. Placeholder scan:** Keine TBDs. JS-Strings für Lokalisierung via `<?php echo esc_js( __( … ) ); ?>` umgesetzt.

**3. Type consistency:** Feldnamen einheitlich zwischen Spec/REST/PHP-Templates/JS:
- `balcony_area`, `loggia_area`, `garden_area`, `cellar_area` (alle float)
- `parking.garage_count`, `parking.outdoor_count` (int)
- `parking.garage_price_override`, `parking.outdoor_price_override` (float|null)
- `meta.parking.{garage,outdoor}.{available,total,price,required}` plus `meta.parking.notes`
- Quick-Info JS nutzt `unit_balcony_area`, `unit_loggia_area` etc. (mit `unit_`-Präfix, weil das bestehende `data-property`-Objekt gemerged ist) — bewusst andere Bezeichner als REST, weil Quick-Info Property+Unit kombiniert.

---

## Nach Phase 2

- Phase 3 — Wizard-Schritt für Stellplatz + Zusatzflächen
- Phase 4 — Listen-/Lightbox-Anzeige + Mobile-Layout (inkl. Mobile-Bug-Diagnose mit Playwright)
- Folgeaufgabe — OpenImmo-Mapping der neuen Felder
