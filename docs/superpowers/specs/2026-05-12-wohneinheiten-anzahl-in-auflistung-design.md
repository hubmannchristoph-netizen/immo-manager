# Wohneinheiten-Anzahl in der Immobilien-Auflistung

Datum: 2026-05-12
Branch: `feat/units-erweiterung`
Status: Design freigegeben

## Ziel

In der Immobilien-Auflistung (Property-Cards) soll sichtbar sein, dass es zu einer
Immobilie zugeordnete Wohneinheiten gibt, inklusive Verfügbarkeit — z. B.
„2 von 4 verfügbar". Sind alle Einheiten vergeben, erscheint stattdessen
„4 Wohneinheiten – ausverkauft". Hat eine Immobilie keine zugeordneten
Wohneinheiten, wird gar nichts angezeigt (Normalfall Einzel-Immobilie).

## Entscheidungen (aus dem Brainstorming)

- **Datenquelle:** nur die der Immobilie **direkt zugeordneten** Wohneinheiten
  (`units.property_id`). Nicht die Units eines verknüpften Bauprojekts.
- **Wo:** in den Auflistungen **beider** Plugins (immo-manager und immo-client).
- **Darstellung:** als zusätzlicher Eintrag in der bestehenden Eckdaten-/Spec-Zeile
  der Card (neben Zimmer / Fläche / Energieklasse), mit Haus-Icon (🏘️).
- **Zählung:** Zähler = Einheiten mit Status `available`. Bei 0 verfügbaren
  Einheiten Sondertext „… Wohneinheiten – ausverkauft".
- **Sichtbarkeit:** nur, wenn die Immobilie mindestens eine zugeordnete
  Wohneinheit hat (`total > 0`).

## Lösungsansatz (gewählt: A)

**A — Neues REST-Feld `unit_stats` auf der Immobilie, gelesen vom jeweiligen
Card-Template-Part.** `format_property()` liefert künftig — analog zu
`format_project()` — ein `unit_stats` mit Status-Counts und `total`, berechnet
über `units.property_id`. Im immo-manager deckt das automatisch Archiv,
Shortcode-Listen und das Elementor-Properties-Widget ab, weil alle
`templates/parts/property-card.php` einbinden. Der immo-client liest das Feld
aus der Manager-REST-Antwort in `templates/partial-property-card.php`.

Verworfen:
- **B** — immo-client ruft `/properties/{id}/units` pro Card auf: je ein
  HTTP-Roundtrip pro Card, zu langsam.
- **C** — im Template aus vorhandenen Card-Daten berechnen: Card-Daten enthalten
  keine Unit-Infos; bräuchte ohnehin die REST-Erweiterung → identisch mit A.

## Komponenten / Änderungen

### 1. immo-manager — `includes/class-units.php`

Neue Methode `count_by_property( int $property_id ): array`:
- Spiegelt die bestehende `count_by_status( int $project_id )`, nur mit
  `WHERE property_id = %d`.
- Liefert ein assoziatives Array Status → Anzahl, z. B.
  `['available' => 2, 'reserved' => 1, 'sold' => 1]` (nur tatsächlich
  vorhandene Stati, wie bei `count_by_status`).
- Bei keiner zugeordneten Einheit: leeres Array.

### 2. immo-manager — `includes/class-rest-api.php` (`format_property()`)

Am Ende des `$result`-Aufbaus (Top-Level, analog zu `unit_stats` bei
`format_project()`):

```php
$u_counts = Units::count_by_property( $id );
$result['unit_stats'] = array_merge(
    $u_counts,
    array( 'total' => array_sum( $u_counts ) )
);
```

`available` ist dann entweder gesetzt (> 0) oder fehlt; Templates behandeln das
mit `?? 0`. `total` ist immer vorhanden (0, wenn keine Units).

### 3. immo-manager — `templates/parts/property-card.php`

In der `<ul class="immo-card-facts">` nach den bestehenden `<li>` (Zimmer,
Fläche, Energieklasse) ein weiteres `<li>`, gerendert nur wenn
`(int) ( $property['unit_stats']['total'] ?? 0 ) > 0`:

- `available > 0`:
  `🏘️ ` + `sprintf( __( '%1$d von %2$d verfügbar', 'immo-manager' ), $available, $total )`
- `available === 0`:
  `🏘️ ` + `sprintf( _n( '%d Wohneinheit – ausverkauft', '%d Wohneinheiten – ausverkauft', $total, 'immo-manager' ), $total )`

Reuse der bestehenden `.immo-card-facts li`-Styles — kein neues CSS.

### 4. immo-client — `templates/partial-property-card.php`

- Aus `$item` bzw. `$meta` die Werte ziehen:
  `$unit_total = (int) ( $item['unit_stats']['total'] ?? 0 );`
  `$unit_avail = (int) ( $item['unit_stats']['available'] ?? 0 );`
  (Das `unit_stats` liegt in der Manager-REST-Antwort auf Top-Level des Items.)
- Die Render-Bedingung der `<ul class="immo-card__specs">` um `|| $unit_total > 0`
  erweitern (sonst fehlt die Liste, falls die Immobilie sonst keine Eckdaten hat).
- Nur für Properties (`!$is_project`) — Project-Cards bleiben unverändert.
- Ein weiteres `<li class="immo-card__spec">` mit einem Haus-/Einheiten-SVG-Icon
  (im Stil der vorhandenen Spec-Icons) und Text:
  - `$unit_avail > 0`: `sprintf( __( '%1$d von %2$d verfügbar', 'immo-client' ), $unit_avail, $unit_total )`
  - `$unit_avail === 0`: `sprintf( _n( '%d Wohneinheit – ausverkauft', '%d Wohneinheiten – ausverkauft', $unit_total, 'immo-client' ), $unit_total )`

### 5. i18n

Neue Strings in beiden Textdomains (`immo-manager`, `immo-client`):
`'%1$d von %2$d verfügbar'`, `'%d Wohneinheit – ausverkauft'` /
`'%d Wohneinheiten – ausverkauft'`.

## Datenfluss

1. Liste wird gerendert (Archiv / Shortcode / Elementor im Manager, bzw.
   `[immo_listing]`-artige Liste im Client über die Manager-REST).
2. `format_property()` liefert pro Immobilie `unit_stats` (eine `GROUP BY`-Query
   pro Immobilie via `count_by_property`).
3. Das Card-Template liest `total` und `available` und rendert den Eckdaten-
   Eintrag — oder nichts, wenn `total === 0`.

## Edge Cases

- **Keine zugeordneten Units:** `total === 0` → kein Eintrag (Normalfall).
- **Alle Units reserviert (nichts verkauft/vermietet):** `available === 0` →
  Text „… Wohneinheiten – ausverkauft" (laut Entscheidung; Wording bei Bedarf
  später anpassbar, z. B. „alle vergeben").
- **Genau 1 Einheit:** `_n()` liefert „1 Wohneinheit – ausverkauft" bzw.
  „1 von 1 verfügbar". Akzeptiert.
- **Project-Cards (immo-client):** unverändert; das Feature gilt nur für
  Properties.

## Performance

Pro gelisteter Immobilie eine zusätzliche `SELECT … GROUP BY status`-Query auf
die Units-Tabelle (indexiert über `property_id`). Bei typischen Listengrößen
unkritisch; falls nötig später per Single-Query mit `WHERE property_id IN (…)`
optimierbar — vorerst YAGNI.

## Tests / Verifikation

Reiner WordPress-Template- und REST-Code, kein sinnvoller automatisierter
Unit-Test. Manuelle Prüfung:

1. Immobilie mit zugeordneten Units, z. B. 2× available + 1× reserved + 1× sold
   → Card zeigt „🏘️ 2 von 4 verfügbar".
2. Immobilie mit Units, alle sold/rented/reserved (0 available)
   → Card zeigt „🏘️ 4 Wohneinheiten – ausverkauft".
3. Normale Einzel-Immobilie ohne zugeordnete Units → kein Eckdaten-Eintrag.
4. Anzeige in beiden Plugins (Manager-Archiv/Shortcode/Elementor + immo-client-Liste).
5. `php -l` auf alle geänderten Dateien.

## Nicht im Scope

- Project-Cards (zeigen Unit-Infos bereits an anderer Stelle / über andere Felder).
- Units eines verknüpften Bauprojekts an der Immobilie aufaddieren.
- Filter/Sortierung der Liste nach Wohneinheiten-Verfügbarkeit.
- Neues CSS / eigenes Badge auf dem Card-Bild.
