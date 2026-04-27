# Schema.org Markup für Immo Manager – Design

**Datum:** 2026-04-27
**Status:** Draft – wartet auf User-Review
**Scope:** Schema.org JSON-LD-Auslieferung für Immobilien-Detailseiten, Bauprojekt-Detailseiten und Breadcrumbs

---

## 1. Ziel & Motivation

Immo Manager liefert aktuell keine strukturierten Daten für Suchmaschinen aus. Damit Google, ChatGPT, Perplexity, Gemini und andere Search-/AI-Crawler Immobilien-Inhalte korrekt einordnen können, soll das Plugin um eine zentrale schema.org-JSON-LD-Auslieferung erweitert werden.

Sekundäres Ziel: GEO/AEO – also Sichtbarkeit in generativen AI-Antworten. Klassische Google-Rich-Snippets gibt es für Immobilien nicht (Stand 2026), aber sauberes Schema-Markup ist Pflicht für AI-Citation-Readiness.

---

## 2. Scope

**In-Scope:**

- Schema-Auslieferung auf Property-Einzelseiten (`is_singular( immo_mgr_property )`)
- Schema-Auslieferung auf Bauprojekt-Einzelseiten (`is_singular( immo_mgr_project )`) inkl. eingebettete Apartment-Schemas pro Wohneinheit
- `BreadcrumbList` auf beiden Detailseiten

**Out-of-Scope:**

- Archiv-/Listenseiten (`ItemList`)
- Eigene `Organization`-Schema (Makler/Agentur)
- OpenImmo-Import/Export – wird in eigener Spec behandelt
- Caching (Schema wird bei jedem Aufruf neu gebaut, ist klein genug)
- Automatisierte Tests (Plugin hat keine Test-Infrastruktur; manuelle Validierung über Google Rich Results Test + schema.org Validator)

---

## 3. Architektur

### Neue Klasse `ImmoManager\Schema`

**Datei:** `includes/class-schema.php`

**Lazy Loading:** Wie alle anderen Handler (`Templates`, `RestApi` etc.) über das Plugin-Singleton (`Plugin::get_schema()`).

**Hook-Registrierung:** Im Konstruktor an `wp_head` mit Priorität 30:

```php
add_action( 'wp_head', array( $this, 'render' ), 30 );
```

**Auslieferung:** Reines JSON-LD via `<script type="application/ld+json">…</script>`. Pro Seite werden zwei separate Skript-Blöcke ausgegeben:

1. Haupt-Entity (`RealEstateListing` oder `ApartmentComplex`)
2. `BreadcrumbList`

### Filter-Hooks (Erweiterbarkeit)

```php
apply_filters( 'immo_manager_schema_property',     $data, $post_id );
apply_filters( 'immo_manager_schema_project',      $data, $post_id );
apply_filters( 'immo_manager_schema_breadcrumbs',  $data, $post_id );
apply_filters( 'immo_manager_schema_skip_sold_units', false, $project_id );
```

Themes/Drittplugins können damit Felder ergänzen, überschreiben oder den ganzen Block entfernen (durch `return null`).

### Integration ins Plugin-Singleton

In `class-plugin.php`:

- Neues privates Feld `$schema = null`
- `init_shortcodes()` lädt zusätzlich `$this->get_schema()` (Frontend-Init zur richtigen Zeit – nach Post-Type-Registrierung)
- Lazy Getter `get_schema(): Schema`

### Templates bleiben unverändert

Schema wird **nicht** in den Templates gerendert. Komplette Trennung von Daten und Markup.

---

## 4. Property-Schema (Einzelimmobilie)

### Schema-Typ

`RealEstateListing` mit `mainEntity` als konkretem Place-Subtyp.

**Mapping `_immo_property_type` → `mainEntity['@type']`:**

| Plugin-Wert (case-insensitive, deutsch + englisch) | Schema-Typ |
|---|---|
| `apartment`, `wohnung`, `penthouse` | `Apartment` |
| `house`, `haus`, `villa` | `House` |
| `single_family`, `einfamilienhaus` | `SingleFamilyResidence` |
| sonstiges / leer | `Residence` (Fallback) |

Mapping wird als private Methode `Schema::map_property_type( string $type ): string` implementiert.

### Felder-Mapping

| Schema-Feld | Quelle | Hinweis |
|---|---|---|
| `@context` | `https://schema.org` | konstant |
| `@type` | `RealEstateListing` | konstant |
| `@id` | `get_permalink()` | |
| `url` | `get_permalink()` | |
| `name` | `get_the_title()` | |
| `description` | `get_the_excerpt()` (Fallback: `wp_trim_words( get_the_content(), 50 )`) | |
| `datePosted` | `get_the_date( 'c' )` (ISO 8601) | |
| `inLanguage` | `get_locale()` mit Underscore→Bindestrich (`de_AT` → `de-AT`) | |
| `image` | Featured Image + Galerie-Bilder als `ImageObject[]` mit `url`, `width`, `height` | nur wenn vorhanden |
| `mainEntity` | siehe nächster Abschnitt | |
| `address` | `PostalAddress` | siehe unten |
| `geo` | `GeoCoordinates` (lat/lng), nur wenn beide ≠ 0 | |
| `offers` | siehe Offer-Logik | nur wenn Preis > 0 |

### `mainEntity` (Place-Subtyp)

```json
{
  "@type": "Apartment",
  "floorSize": { "@type": "QuantitativeValue", "value": 87, "unitCode": "MTK" },
  "numberOfRooms": 3,
  "numberOfBedrooms": 2,
  "numberOfBathroomsTotal": 1,
  "yearBuilt": 2018,
  "address": { … gleicher PostalAddress wie auf Top-Level … },
  "geo": { … falls vorhanden … },
  "additionalProperty": [
    { "@type": "PropertyValue", "name": "energyClass", "value": "B" },
    { "@type": "PropertyValue", "name": "HWB", "value": 42, "unitText": "kWh/m²a" },
    { "@type": "PropertyValue", "name": "fGEE", "value": 0.85 },
    { "@type": "PropertyValue", "name": "heating", "value": "Fernwärme" },
    { "@type": "PropertyValue", "name": "commission", "value": "3% + 20% USt." },
    { "@type": "PropertyValue", "name": "renovationYear", "value": 2021 }
  ]
}
```

Felder mit Wert 0 / leer werden weggelassen (keine `additionalProperty`-Einträge mit leerem Wert).

### `address` (PostalAddress)

```json
{
  "@type": "PostalAddress",
  "streetAddress": "_immo_address",
  "postalCode": "_immo_postal_code",
  "addressLocality": "_immo_city",
  "addressRegion": "_immo_region_state",
  "addressCountry": "_immo_country (ISO-Code, default AT)"
}
```

Leere Felder werden weggelassen.

### Offer-Logik (Sale / Rent / Both / Preis 0)

| Mode | Preis-Feld | Schema-Output |
|---|---|---|
| `sale` mit `_immo_price > 0` | – | Ein `Offer` mit `priceSpecification.minPrice` (= Ab-Preis), `priceCurrency: EUR`, `availability` |
| `rent` mit `_immo_rent > 0` | – | Ein `Offer` mit `priceSpecification.price`, `priceCurrency: EUR`, `unitText: MONTH`, `availability` |
| `both` mit beide > 0 | – | Array mit zwei Offers (sale + rent) |
| Preis = 0 ("Preis auf Anfrage") | – | `offers`-Feld wird komplett weggelassen |

Bei "ab"-Preis (Sale):

```json
{
  "@type": "Offer",
  "priceSpecification": {
    "@type": "PriceSpecification",
    "minPrice": 450000,
    "priceCurrency": "EUR"
  },
  "availability": "https://schema.org/InStock",
  "availabilityStarts": "2026-06-01"
}
```

Bei Miete:

```json
{
  "@type": "Offer",
  "priceSpecification": {
    "@type": "UnitPriceSpecification",
    "price": 1200,
    "priceCurrency": "EUR",
    "unitText": "MONTH"
  },
  "availability": "https://schema.org/InStock"
}
```

### Status-Mapping → `availability`

| Plugin-Status | Schema-availability |
|---|---|
| `available` | `https://schema.org/InStock` |
| `reserved` | `https://schema.org/LimitedAvailability` |
| `sold` | `https://schema.org/SoldOut` |
| `rented` | `https://schema.org/OutOfStock` |

---

## 5. Bauprojekt-Schema (mit Wohneinheiten)

### Schema-Typ

`ApartmentComplex` mit Array `containsPlace` → jedes Element ein vollwertiges `Apartment`.

### Projekt-Felder

| Schema-Feld | Quelle |
|---|---|
| `@type` | `ApartmentComplex` |
| `@id` / `url` | `get_permalink()` |
| `name` | `get_the_title()` |
| `description` | `get_the_excerpt()` mit Content-Fallback |
| `image` | Featured + Galerie als `ImageObject[]` |
| `address` (`PostalAddress`) | gleiche Felder wie Property |
| `geo` (`GeoCoordinates`) | lat/lng wenn vorhanden |
| `numberOfAccommodationUnits` | `count( Units::get_by_project( $project_id ) )` |
| `containsPlace` | Array von Apartment-Objekten – siehe unten |
| `additionalProperty` | u.a. `projectStatus` (planning/building/completed) |

**Projekt selbst hat KEIN `offers`-Feld** – auch kein "ab Minimum-Preis". Begründung: Projektstand-Memory legt fest, dass das Projekt selbst keinen Gesamtpreis kommuniziert; Preise kommen über die Wohneinheiten.

### Apartment-Felder pro Wohneinheit

| Schema-Feld | Unit-DB-Spalte | Hinweis |
|---|---|---|
| `@type` | `Apartment` | konstant |
| `@id` | `{Projekt-Permalink}#unit-{unit_id}` | eindeutiger Anker |
| `url` | gleicher Anker-Link | |
| `name` | `"{Projektname} – {unit_number}"` (Fallback: `"{Projektname} – Wohnung {unit_id}"` wenn `unit_number` leer) | Wiedererkennungswert |
| `floorSize` | `area` als `QuantitativeValue` mit `unitCode: MTK` | nur wenn > 0 |
| `numberOfRooms` | `rooms` | nur wenn > 0 |
| `numberOfBedrooms` | falls in Unit-Schema vorhanden | |
| `numberOfBathroomsTotal` | falls vorhanden | |
| `floorLevel` | `floor` | nur wenn > 0 |
| `offers` | Fixpreis-Offer mit `availability` aus `status`, `priceCurrency: EUR` | **OHNE** "ab"-`minPrice` – Units sind Fixpreise. Bei Preis 0: Offer weglassen. |

### Verkaufte/vermietete Units

Bleiben standardmäßig im Schema, mit `availability: SoldOut` bzw. `OutOfStock`. Filter `immo_manager_schema_skip_sold_units` (Default: `false`) erlaubt das Ausblenden.

---

## 6. Breadcrumb-Schema

Separater JSON-LD-Block (`@type: BreadcrumbList`), drei Positionen:

| Position | Quelle |
|---|---|
| 1 | `name: "Home"`, `item: home_url('/')` |
| 2 | `name: "Immobilien"` bzw. `"Bauprojekte"`, `item: get_post_type_archive_link( … )` |
| 3 | `name: get_the_title()`, `item: get_permalink()` |

Position-2-Label wird über die Post-Type-Labels des CPT geholt (nicht hardcoded), damit Übersetzungen automatisch funktionieren.

---

## 7. Edge Cases & Datenschutz

| Fall | Verhalten |
|---|---|
| Featured Image fehlt | `image`-Feld weglassen (nicht `null` setzen) |
| Galerie leer | nur Featured Image als `image` |
| Keine Adresse | `address`-Block weglassen |
| Lat oder Lng = 0 | `geo`-Block weglassen (verhindert "Null-Insel"-Fehler) |
| `_immo_available_from` leer | `availabilityStarts` weglassen |
| Energie-Klasse leer | `energyClass`-`additionalProperty` weglassen |
| Beträge in Cent? | Nein, Plugin speichert `_immo_price`/`_immo_rent` als Float in Euro (siehe `MetaFields::sanitize_value`) |
| Privatobjekt ohne Adresse | Schema funktioniert trotzdem, nur ohne `address`/`geo` |
| Post-Status nicht `publish` | `Schema::render()` returned früh, kein Output |

---

## 8. Implementierungs-Outline

**Neue Datei:** `includes/class-schema.php` (eine Klasse, ~400-500 Zeilen)

**Public API:**

```php
class Schema {
    public function __construct();           // Hook-Registrierung
    public function render(): void;          // wp_head-Callback
}
```

**Private Methoden (Aufteilung nach Verantwortung):**

- `build_property( int $post_id ): array`
- `build_project( int $post_id ): array`
- `build_unit_apartment( array $unit, int $project_id, string $project_title ): array`
- `build_breadcrumbs( int $post_id, string $post_type ): array`
- `build_address( array $meta ): ?array`
- `build_geo( array $meta ): ?array`
- `build_image_list( int $post_id ): array`
- `build_offer_sale( float $price, string $status, string $available_from = '' ): array`
- `build_offer_rent( float $rent, string $status ): array`
- `map_property_type( string $type ): string`
- `map_status_to_availability( string $status ): string`
- `output_jsonld( array $data ): void` (helper für Encoding + Script-Tag)

**Plugin-Singleton-Änderung:** `class-plugin.php` bekommt `$schema`-Feld + `get_schema()` + `init_shortcodes()` ruft `$this->get_schema()` auf.

**Keine Templates-Änderungen.**
**Keine Migrations.**
**Keine neuen Settings/Optionen** (Filter-Hooks reichen).

### Reihenfolge der Implementierung (für späteren Plan)

1. Klasse `Schema` mit Konstruktor + leerem `render()`
2. Plugin-Singleton-Integration (Lazy Getter, Hook-Registrierung)
3. Property-Schema-Aufbau (build_property + alle Helfer für Address/Geo/Image/Offer)
4. Property-Schema mit Google Rich Results Test + schema.org Validator manuell prüfen
5. Project-Schema-Aufbau inkl. `containsPlace`-Apartments
6. Project-Schema validieren
7. Breadcrumb-Schema
8. Filter-Hooks an allen Stellen einziehen
9. Manuelle Endprüfung mit Demo-Daten

---

## 9. Validierung & Akzeptanzkriterien

- [ ] Property-Detailseite liefert `RealEstateListing`, validiert ohne Fehler im schema.org-Validator
- [ ] Property-Detailseite besteht Google Rich Results Test ohne Errors (Warnings für nicht-unterstützte Felder OK)
- [ ] Bauprojekt-Detailseite liefert `ApartmentComplex` mit korrekt verschachtelten `containsPlace`-Apartments
- [ ] Breadcrumb-Block ist auf beiden Detailseiten vorhanden und valide
- [ ] Sale-Modus zeigt `Offer` mit `minPrice`, Rent-Modus mit `unitText: MONTH`
- [ ] "Preis auf Anfrage" (Preis 0) → kein `offers`-Feld
- [ ] Status `sold`/`rented` → `availability` korrekt gemapped
- [ ] Filter `immo_manager_schema_property` greift (Test mit `add_filter` in `functions.php` einer lokalen Test-Installation)
- [ ] Manuelle Sichtprüfung: JSON-LD im Page-Source vorhanden, valides JSON
- [ ] Lighthouse-SEO-Score: keine Regression

---

## 10. Risiken & offene Punkte

- **Keine automatisierten Tests:** Plugin hat keine PHPUnit-Infrastruktur. Validierung nur manuell. Risiko: Regressionen nach Refactoring fallen erst spät auf. Mitigation: ausführliche Akzeptanzkriterien-Checkliste.
- **Schema-Standards entwickeln sich weiter:** `RealEstateListing` ist seit 2018 in schema.org, Google ergänzt selten Rich-Result-Support. Falls das passiert, könnten weitere Felder (z.B. `aggregateRating`) sinnvoll werden – durch Filter-Hooks erweiterbar.
- **AI-Crawler-Verhalten:** Wie ChatGPT/Perplexity Schema interpretieren, ist nicht öffentlich dokumentiert. Wir liefern semantisch korrektes Markup; Sichtbarkeit in AI-Antworten lässt sich nicht garantieren.
- **Performance:** Bei Bauprojekten mit sehr vielen Units (>100) wird das JSON-LD groß. Falls Probleme auftreten: Pagination/Limit-Filter ergänzen (zur Zeit nicht nötig).
