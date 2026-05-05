# Provisionsfrei-Badge — Design

**Datum:** 2026-05-05
**Status:** Approved
**Scope:** WordPress-Plugins `immo-manager` (Backend + Public Templates) und `immo-client` (REST-Konsument).

---

## 1. Kontext

Das Meta-Feld `_immo_commission_free` (boolean) existiert bereits in `immo-manager`. Es:

- ist im **Wizard Schritt 4** und der **Property-Metabox** ankreuzbar,
- blendet im **Nebenkostenrechner** Provision + USt-auf-Provision korrekt aus,
- wird über die **REST-API** als `meta.commission_free` bereitgestellt,
- unterdrückt im **OpenImmo-Export** das `<aussen_courtage>`-Element.

**Was fehlt:** Ein sichtbares „Provisionsfrei"-Badge auf allen Public-Templates. Aktuell sieht ein Besucher die Eigenschaft nur im Calculator (durch Abwesenheit der Provisionszeile) oder als Listeneintrag in `immo-client/templates/single-unit.php`.

## 2. Anforderungen (vom User bestätigt)

| Nr. | Anforderung |
|-----|-------------|
| R1 | Badge wird auf **allen** Listings und Detailseiten gerendert, in `immo-manager` UND `immo-client`. |
| R2 | **Granularität:** Property-Ebene (kein eigenes Unit-Flag). Bei Bauprojekt-Units zählt das Flag der dahinterliegenden Property. |
| R3 | **Optik:** Gelb/Orange Sticker („Patch"-Look) mit dunkler Schrift. Position auf Bildern: **oben-rechts** (Status-Badge bleibt oben-links). |
| R4 | **Stellen ohne Hero-Bild** (Bauprojekt-Unit-Zeilen): kleines Icon/Sticker am Anfang der Zeile. |
| R5 | **Badge-Text** ist in den Plugin-Einstellungen konfigurierbar (`commission_free_label`), Default: „Provisionsfrei". |
| R6 | **Bedingung:** Badge erscheint nur, wenn `commission_free === true` UND `mode ∈ {sale, both}` (Provision ist ein Kauf-Thema, nicht Miete). |
| R7 | **REST-API:** liefert zusätzlich `meta.commission_free_label`, damit `immo-client` den konfigurierten Text rendern kann ohne eigene Settings-Sync. |

## 3. Architektur-Übersicht

```
immo-manager (Quelle der Wahrheit)
├─ class-settings.php                  [neu: commission_free_label-Default]
├─ class-admin-pages.php               [neu: Settings-UI für Label]
├─ class-rest-api.php                  [erweitert: meta.commission_free_label]
├─ templates/parts/commission-free-badge.php   [NEU: variant=patch|icon]
├─ public/css/commission-free-badge.css        [NEU]
├─ class-shortcodes.php                [erweitert: CSS enqueuen]
├─ templates/property-detail.php       [Patch über Hero]
├─ templates/property-list.php         [Patch in Card-Ecke]
├─ templates/archive-immo_mgr_property.php  [Patch in Card-Ecke]
└─ templates/single-immo_mgr_project.php    [Icon in Unit-Zeile]

immo-client (REST-Konsument)
├─ assets/css/commission-free-badge.css     [NEU]
├─ class-immo-styles.php (oder vorhandene Enqueue) [CSS einhängen]
├─ templates/list-grid.php             [Patch in Card-Ecke]
├─ templates/shortcode-property.php    [Patch über Hero]
├─ templates/single-unit.php           [Patch über Hero, Listeneintrag entfernen]
└─ templates/single-project.php        [Icon in Unit-Zeile]
```

## 4. Daten-Vertrag

### 4.1 Settings (`Settings::get_defaults()`)

```php
'commission_free_label' => 'Provisionsfrei',
```

### 4.2 REST-API `meta`-Block (Property)

```jsonc
"meta": {
  …
  "commission_free": true,
  "commission_free_label": "Provisionsfrei",
  …
}
```

`commission_free_label` ist global identisch für alle Properties — es kommt aus den Plugin-Settings, nicht aus Post-Meta. Trotzdem im Property-Output dabei, damit `immo-client` keine eigene Settings-API braucht.

### 4.3 Bedingung im Renderer

```php
$show_badge = ! empty( $meta['commission_free'] )
           && in_array( $meta['mode'] ?? 'sale', array( 'sale', 'both' ), true );
```

## 5. Komponenten

### 5.1 Template-Part `templates/parts/commission-free-badge.php`

```php
/**
 * @var bool   $show     Ob das Badge gerendert werden soll.
 * @var string $label    Beschriftung (aus Settings).
 * @var string $variant  'patch' (Sticker auf Bild) oder 'icon' (Inline-Pill).
 */
if ( empty( $show ) ) {
    return;
}
$class = 'patch' === $variant ? 'immo-cf-patch' : 'immo-cf-icon';
?>
<span class="immo-cf-badge <?php echo esc_attr( $class ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
    <?php echo esc_html( $label ); ?>
</span>
```

### 5.2 Helper `Templates::commission_free_badge()`

In `class-templates.php`:

```php
public static function commission_free_badge( array $meta, string $variant = 'patch' ): void {
    $mode = (string) ( $meta['mode'] ?? 'sale' );
    $show = ! empty( $meta['commission_free'] )
         && in_array( $mode, array( 'sale', 'both' ), true );
    if ( ! $show ) {
        return;
    }
    $label = (string) Settings::get( 'commission_free_label', 'Provisionsfrei' );
    include __DIR__ . '/../templates/parts/commission-free-badge.php';
}
```

Aufruf in Templates: `\ImmoManager\Templates::commission_free_badge( $meta, 'patch' );`

### 5.3 CSS

```css
.immo-cf-badge {
    display: inline-flex;
    align-items: center;
    gap: .25em;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
    line-height: 1;
}
.immo-cf-patch {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 5;
    padding: .55em .9em;
    background: #ffd60a;
    color: #1a1a1a;
    border-radius: 999px;
    font-size: .85rem;
    box-shadow: 0 2px 6px rgba(0,0,0,.2);
    transform: rotate(-3deg);
}
.immo-cf-icon {
    padding: .25em .55em;
    background: #ffd60a;
    color: #1a1a1a;
    border-radius: 4px;
    font-size: .7rem;
}
.immo-cf-patch::before { content: "✓ "; }
```

Container des Hero-Bilds muss `position: relative;` haben (ist im bestehenden CSS bereits der Fall für `.immo-slider`).

### 5.4 Settings-UI

Neuer Eintrag im bestehenden Settings-Tab „Allgemein" (oder „Rechner") in `class-admin-pages.php`:

```
[ Beschriftung „Provisionsfrei"-Badge ]   [ Provisionsfrei ]
```

## 6. Edge Cases

| Szenario | Verhalten |
|----------|-----------|
| `commission_free=true` aber `mode=rent` | Kein Badge (R6) |
| Property ohne Bild | Patch wird nicht über Bild gelegt — fällt weg auf Listing-Cards. Auf Detailseite: Badge erscheint trotzdem in der Hero-Zone (auch wenn dort Platzhalter steht). |
| Bauprojekt mit gemischten Units (manche Properties dahinter provisionsfrei, andere nicht) | Pro Unit-Zeile separat; das Projekt-Hero zeigt KEIN Badge (uneinheitlich). |
| `immo-client` Cache | REST-Antwort ändert sich bei Settings-Änderung erst nach Cache-Invalidierung. Nicht-Ziel dieses Specs (Cache-Strategie ist eigenständig). |
| Settings-Label leer | Fallback auf „Provisionsfrei" (Default-Wert). |

## 7. Testplan

1. Property mit `mode=sale` + Flag aktiv → Badge auf Detail, Listing, Archive, Calculator zeigt keine Provision.
2. Property mit `mode=rent` + Flag aktiv → KEIN Badge.
3. Bauprojekt mit 2 Units, eine mit `commission_free=true` → Icon nur an dieser Unit-Zeile.
4. REST-Output: `curl /wp-json/immo-manager/v1/properties/<id>` enthält `meta.commission_free` UND `meta.commission_free_label`.
5. Settings-Label auf „Maklerfrei" ändern → Badge auf allen Stellen zeigt „Maklerfrei".
6. `immo-client`: Listing + Single-Templates rendern den Badge mit dem Label aus dem REST-Output.
7. OpenImmo-Export bleibt unverändert korrekt (kein `<aussen_courtage>`).

## 8. Out of Scope

- Pro-Unit-Override (User-Entscheidung Variante a, kein Unit-Flag).
- Animations / Hover-Effekte am Badge.
- Eigene Filter-Option „Nur provisionsfrei" in der Filter-Sidebar (kann später in eigenem Spec ergänzt werden).
- Schema.org-Anreicherung (z. B. `offers.eligibleTransactionVolume` mit 0 % Provision) — bewusst nicht im Scope, da OpenImmo-Export bereits korrekt.

## 9. Migration

Keine Datenmigration nötig. Bestehendes `_immo_commission_free` wird unverändert weiterverwendet. Settings bekommen einen neuen Default-Eintrag, der bei Erst-Speichern ergänzt wird.
