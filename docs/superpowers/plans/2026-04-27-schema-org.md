# Schema.org Markup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Schema.org JSON-LD-Auslieferung für Immobilien-Detailseiten, Bauprojekt-Detailseiten und Breadcrumbs implementieren – damit Google und AI-Crawler die Inhalte semantisch korrekt einordnen.

**Architecture:** Eine zentrale Klasse `ImmoManager\Schema` hängt sich an `wp_head` und liefert JSON-LD aus. Lazy-loaded über das Plugin-Singleton. Templates bleiben unverändert. Filter-Hooks für Erweiterbarkeit durch Themes.

**Tech Stack:** WordPress 5.9+, PHP 7.4+, schema.org JSON-LD (`RealEstateListing`, `ApartmentComplex`, `Apartment`, `BreadcrumbList`).

**Spec:** `docs/superpowers/specs/2026-04-27-schema-org-design.md`

**Hinweis zu Tests:** Das Plugin hat keine PHPUnit-Infrastruktur. Validierung läuft daher manuell via Browser-Source-View, [schema.org Validator](https://validator.schema.org/) und [Google Rich Results Test](https://search.google.com/test/rich-results). Pro Task gibt es konkrete manuelle Verifikations-Schritte mit erwarteten Ergebnissen.

---

## File Structure

| Aktion | Datei | Verantwortung |
|---|---|---|
| Create | `includes/class-schema.php` | Komplette Schema-Klasse (alle build_*-Methoden, render()) |
| Modify | `includes/class-plugin.php` | Lazy Getter + Init im `init_shortcodes()` |

Keine weiteren Dateien. Keine Migrations. Keine Templates-Änderungen.

---

## Task 1: Schema-Klasse anlegen + Plugin-Integration

**Files:**
- Create: `includes/class-schema.php`
- Modify: `includes/class-plugin.php`

**Ziel:** Skelett funktioniert end-to-end – auf Property-Detailseiten erscheint ein erkennbarer Marker im HTML-Source.

- [ ] **Step 1: Schema-Klasse mit Skeleton anlegen**

Erstelle `includes/class-schema.php`:

```php
<?php
/**
 * Schema.org JSON-LD Auslieferung für Property-, Project- und Breadcrumb-Seiten.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schema
 *
 * Liefert strukturierte Daten (RealEstateListing, ApartmentComplex, BreadcrumbList)
 * im wp_head-Bereich aus. Komplett getrennt von Templates.
 */
class Schema {

	/**
	 * Konstruktor – Hook-Registrierung.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'render' ), 30 );
	}

	/**
	 * wp_head-Callback – entscheidet was ausgegeben wird.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( is_singular( PostTypes::POST_TYPE_PROPERTY ) ) {
			echo "\n<!-- Immo Manager Schema (Property) -->\n";
			return;
		}

		if ( is_singular( PostTypes::POST_TYPE_PROJECT ) ) {
			echo "\n<!-- Immo Manager Schema (Project) -->\n";
			return;
		}
	}
}
```

- [ ] **Step 2: Plugin-Singleton erweitern**

In `includes/class-plugin.php` zwei Änderungen:

**(a)** Bei den anderen Handler-Feldern (nach `$templates`) ergänzen:

```php
	/**
	 * Schema-Handler.
	 *
	 * @var Schema|null
	 */
	private $schema = null;
```

**(b)** In `init_shortcodes()` den Getter mit aufrufen:

```php
	public function init_shortcodes(): void {
		$this->get_shortcodes();
		$this->get_wizard();
		$this->get_templates();
		$this->get_elementor();
		$this->get_schema();
	}
```

**(c)** Am Ende der Klasse (nach `get_templates()`) den Lazy Getter ergänzen:

```php
	/**
	 * Schema-Handler abrufen (Lazy Loading).
	 *
	 * @return Schema
	 */
	public function get_schema(): Schema {
		if ( null === $this->schema ) {
			$this->schema = new Schema();
		}
		return $this->schema;
	}
```

- [ ] **Step 3: Manueller Verifikationstest**

In WordPress-Backend eine bestehende Demo-Property aufrufen (Frontend), dann Browser-Source-View (Strg+U) öffnen.

**Erwartet:** Im `<head>` taucht der Kommentar `<!-- Immo Manager Schema (Property) -->` auf.

Auf einer Bauprojekt-Detailseite analog: `<!-- Immo Manager Schema (Project) -->`.

Wenn nicht: Plugin-Cache leeren, Permalinks neu speichern, Autoloader-Klassennamen prüfen.

- [ ] **Step 4: Commit**

```bash
git add includes/class-schema.php includes/class-plugin.php
git commit -m "feat(schema): add Schema class skeleton with wp_head integration"
```

---

## Task 2: Helfer-Methoden für Schema-Aufbau

**Files:**
- Modify: `includes/class-schema.php`

**Ziel:** Alle Pure-Function-Helfer (Type-Mapping, Address, Geo, Images, Output) sind fertig. Noch kein sichtbarer Output – nur Vorarbeit für Task 3.

- [ ] **Step 1: Property-Type-Mapping ergänzen**

In `class-schema.php` als private Methode ergänzen:

```php
	/**
	 * Property-Type aus Plugin-Meta auf schema.org Place-Subtyp mappen.
	 *
	 * @param string $type Plugin-Property-Typ.
	 *
	 * @return string Schema-Typ (Apartment, House, SingleFamilyResidence, Residence).
	 */
	private function map_property_type( string $type ): string {
		$normalized = strtolower( trim( $type ) );

		$apartment_types = array( 'apartment', 'wohnung', 'penthouse' );
		$house_types     = array( 'house', 'haus', 'villa' );
		$sfr_types       = array( 'single_family', 'einfamilienhaus' );

		if ( in_array( $normalized, $apartment_types, true ) ) {
			return 'Apartment';
		}
		if ( in_array( $normalized, $house_types, true ) ) {
			return 'House';
		}
		if ( in_array( $normalized, $sfr_types, true ) ) {
			return 'SingleFamilyResidence';
		}

		return 'Residence';
	}
```

- [ ] **Step 2: Status-Mapping ergänzen**

```php
	/**
	 * Plugin-Status auf schema.org availability-URL mappen.
	 *
	 * @param string $status Plugin-Status.
	 *
	 * @return string Schema-availability-URL.
	 */
	private function map_status_to_availability( string $status ): string {
		switch ( $status ) {
			case 'reserved':
				return 'https://schema.org/LimitedAvailability';
			case 'sold':
				return 'https://schema.org/SoldOut';
			case 'rented':
				return 'https://schema.org/OutOfStock';
			case 'available':
			default:
				return 'https://schema.org/InStock';
		}
	}
```

- [ ] **Step 3: Address-Builder ergänzen**

```php
	/**
	 * PostalAddress aus Meta-Werten bauen.
	 *
	 * @param int $post_id Post-ID.
	 *
	 * @return array<string, mixed>|null Address-Array oder null wenn leer.
	 */
	private function build_address( int $post_id ): ?array {
		$street    = (string) get_post_meta( $post_id, '_immo_address', true );
		$postal    = (string) get_post_meta( $post_id, '_immo_postal_code', true );
		$city      = (string) get_post_meta( $post_id, '_immo_city', true );
		$region    = (string) get_post_meta( $post_id, '_immo_region_state', true );
		$country   = (string) get_post_meta( $post_id, '_immo_country', true );

		if ( '' === $street && '' === $postal && '' === $city ) {
			return null;
		}

		$address = array( '@type' => 'PostalAddress' );

		if ( '' !== $street )  { $address['streetAddress']   = $street; }
		if ( '' !== $postal )  { $address['postalCode']      = $postal; }
		if ( '' !== $city )    { $address['addressLocality'] = $city; }
		if ( '' !== $region )  { $address['addressRegion']   = $region; }
		if ( '' !== $country ) { $address['addressCountry']  = $country; }

		return $address;
	}
```

- [ ] **Step 4: Geo-Builder ergänzen**

```php
	/**
	 * GeoCoordinates aus Meta-Werten bauen. Null wenn lat oder lng = 0.
	 *
	 * @param int $post_id Post-ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function build_geo( int $post_id ): ?array {
		$lat = (float) get_post_meta( $post_id, '_immo_lat', true );
		$lng = (float) get_post_meta( $post_id, '_immo_lng', true );

		if ( 0.0 === $lat || 0.0 === $lng ) {
			return null;
		}

		return array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => $lat,
			'longitude' => $lng,
		);
	}
```

- [ ] **Step 5: Image-Liste-Builder ergänzen**

```php
	/**
	 * Featured Image + Galerie als ImageObject-Array bauen.
	 *
	 * @param int $post_id Post-ID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_image_list( int $post_id ): array {
		$images   = array();
		$seen_ids = array();

		// Featured Image zuerst.
		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$img = $this->image_object( $thumb_id );
			if ( $img ) {
				$images[]            = $img;
				$seen_ids[ $thumb_id ] = true;
			}
		}

		// Galerie aus _immo_gallery oder _immo_documents – Plugin nutzt aktuell
		// die WP-Mediathek über Featured Image; weitere Bilder kommen aus dem
		// Standard-WP-Galerie-Block. Wir lesen attachments des Posts.
		$attachments = get_attached_media( 'image', $post_id );
		foreach ( $attachments as $att ) {
			$id = (int) $att->ID;
			if ( isset( $seen_ids[ $id ] ) ) {
				continue;
			}
			$img = $this->image_object( $id );
			if ( $img ) {
				$images[]          = $img;
				$seen_ids[ $id ] = true;
			}
		}

		return $images;
	}

	/**
	 * Einzelnes ImageObject aus Attachment-ID.
	 *
	 * @param int $attachment_id Attachment-ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function image_object( int $attachment_id ): ?array {
		$src = wp_get_attachment_image_src( $attachment_id, 'large' );
		if ( ! $src || ! is_array( $src ) ) {
			return null;
		}

		return array(
			'@type'  => 'ImageObject',
			'url'    => $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
		);
	}
```

- [ ] **Step 6: JSON-LD-Output-Helper ergänzen**

```php
	/**
	 * Ein Schema-Array als JSON-LD-Script-Block ausgeben.
	 *
	 * @param array<string, mixed>|null $data Schema-Daten.
	 *
	 * @return void
	 */
	private function output_jsonld( ?array $data ): void {
		if ( null === $data || empty( $data ) ) {
			return;
		}

		$json = wp_json_encode(
			$data,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		);

		if ( false === $json ) {
			return;
		}

		echo "\n" . '<script type="application/ld+json">' . "\n";
		echo $json . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON ist sicher kodiert.
		echo '</script>' . "\n";
	}

	/**
	 * Locale für inLanguage konvertieren (de_AT → de-AT).
	 *
	 * @return string
	 */
	private function get_in_language(): string {
		return str_replace( '_', '-', get_locale() );
	}
```

- [ ] **Step 7: Manueller Smoke-Test**

PHP-Syntax via WordPress-Plugin-Page prüfen: Plugin deaktivieren → reaktivieren. Kein Fatal Error darf erscheinen.

**Erwartet:** Plugin lädt sauber, Detailseiten zeigen weiterhin den Marker-Kommentar aus Task 1.

- [ ] **Step 8: Commit**

```bash
git add includes/class-schema.php
git commit -m "feat(schema): add helper methods for type/status/address/geo/image mapping"
```

---

## Task 3: Property-Schema rendern

**Files:**
- Modify: `includes/class-schema.php`

**Ziel:** Property-Detailseiten liefern valides `RealEstateListing` JSON-LD.

- [ ] **Step 1: Offer-Builder für Sale ergänzen**

```php
	/**
	 * Sale-Offer mit "ab"-Preis (minPrice) bauen.
	 *
	 * @param float  $price          Kaufpreis.
	 * @param string $status         Plugin-Status.
	 * @param string $available_from ISO-Datum oder leer.
	 *
	 * @return array<string, mixed>|null Null wenn Preis 0.
	 */
	private function build_offer_sale( float $price, string $status, string $available_from = '' ): ?array {
		if ( $price <= 0.0 ) {
			return null;
		}

		$offer = array(
			'@type'             => 'Offer',
			'priceSpecification' => array(
				'@type'         => 'PriceSpecification',
				'minPrice'      => $price,
				'priceCurrency' => 'EUR',
			),
			'availability'      => $this->map_status_to_availability( $status ),
		);

		if ( '' !== $available_from ) {
			$offer['availabilityStarts'] = $available_from;
		}

		return $offer;
	}
```

- [ ] **Step 2: Offer-Builder für Rent ergänzen**

```php
	/**
	 * Rent-Offer mit Monats-Miete bauen.
	 *
	 * @param float  $rent   Monatsmiete.
	 * @param string $status Plugin-Status.
	 *
	 * @return array<string, mixed>|null Null wenn Miete 0.
	 */
	private function build_offer_rent( float $rent, string $status ): ?array {
		if ( $rent <= 0.0 ) {
			return null;
		}

		return array(
			'@type'              => 'Offer',
			'priceSpecification' => array(
				'@type'         => 'UnitPriceSpecification',
				'price'         => $rent,
				'priceCurrency' => 'EUR',
				'unitText'      => 'MONTH',
			),
			'availability'       => $this->map_status_to_availability( $status ),
		);
	}
```

- [ ] **Step 3: additionalProperty-Builder ergänzen (Energie / Heizung / Provision / Renovierung)**

```php
	/**
	 * Liste der additionalProperty-Werte aus Meta zusammenstellen.
	 *
	 * @param int $post_id Post-ID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_additional_properties( int $post_id ): array {
		$out = array();

		$energy_class = (string) get_post_meta( $post_id, '_immo_energy_class', true );
		if ( '' !== $energy_class ) {
			$out[] = array( '@type' => 'PropertyValue', 'name' => 'energyClass', 'value' => $energy_class );
		}

		$hwb = (float) get_post_meta( $post_id, '_immo_energy_hwb', true );
		if ( $hwb > 0 ) {
			$out[] = array( '@type' => 'PropertyValue', 'name' => 'HWB', 'value' => $hwb, 'unitText' => 'kWh/m²a' );
		}

		$fgee = (float) get_post_meta( $post_id, '_immo_energy_fgee', true );
		if ( $fgee > 0 ) {
			$out[] = array( '@type' => 'PropertyValue', 'name' => 'fGEE', 'value' => $fgee );
		}

		$heating = (string) get_post_meta( $post_id, '_immo_heating', true );
		if ( '' !== $heating ) {
			$out[] = array( '@type' => 'PropertyValue', 'name' => 'heating', 'value' => $heating );
		}

		$commission = (string) get_post_meta( $post_id, '_immo_commission', true );
		if ( '' !== $commission ) {
			$out[] = array( '@type' => 'PropertyValue', 'name' => 'commission', 'value' => $commission );
		}

		$reno = (int) get_post_meta( $post_id, '_immo_renovation_year', true );
		if ( $reno > 0 ) {
			$out[] = array( '@type' => 'PropertyValue', 'name' => 'renovationYear', 'value' => $reno );
		}

		return $out;
	}
```

- [ ] **Step 4: Property-Schema-Builder ergänzen**

```php
	/**
	 * Komplettes RealEstateListing-Schema für eine Property bauen.
	 *
	 * @param int $post_id Post-ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function build_property( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		// mainEntity (Apartment/House/...).
		$type_meta   = (string) get_post_meta( $post_id, '_immo_property_type', true );
		$schema_type = $this->map_property_type( $type_meta );

		$main_entity = array( '@type' => $schema_type );

		$area = (float) get_post_meta( $post_id, '_immo_area', true );
		if ( $area > 0 ) {
			$main_entity['floorSize'] = array(
				'@type'    => 'QuantitativeValue',
				'value'    => $area,
				'unitCode' => 'MTK',
			);
		}

		$rooms = (int) get_post_meta( $post_id, '_immo_rooms', true );
		if ( $rooms > 0 )      { $main_entity['numberOfRooms'] = $rooms; }

		$bedrooms = (int) get_post_meta( $post_id, '_immo_bedrooms', true );
		if ( $bedrooms > 0 )   { $main_entity['numberOfBedrooms'] = $bedrooms; }

		$baths = (int) get_post_meta( $post_id, '_immo_bathrooms', true );
		if ( $baths > 0 )      { $main_entity['numberOfBathroomsTotal'] = $baths; }

		$built = (int) get_post_meta( $post_id, '_immo_built_year', true );
		if ( $built > 0 )      { $main_entity['yearBuilt'] = $built; }

		$address = $this->build_address( $post_id );
		if ( $address )        { $main_entity['address'] = $address; }

		$geo = $this->build_geo( $post_id );
		if ( $geo )            { $main_entity['geo'] = $geo; }

		$additional = $this->build_additional_properties( $post_id );
		if ( ! empty( $additional ) ) {
			$main_entity['additionalProperty'] = $additional;
		}

		// Top-Level RealEstateListing.
		$permalink = get_permalink( $post_id );

		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'RealEstateListing',
			'@id'        => $permalink,
			'url'        => $permalink,
			'name'       => get_the_title( $post_id ),
			'datePosted' => get_the_date( 'c', $post_id ),
			'inLanguage' => $this->get_in_language(),
			'mainEntity' => $main_entity,
		);

		$description = (string) get_the_excerpt( $post_id );
		if ( '' === $description ) {
			$description = wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 50, '…' );
		}
		if ( '' !== $description ) {
			$data['description'] = $description;
		}

		$images = $this->build_image_list( $post_id );
		if ( ! empty( $images ) ) {
			$data['image'] = $images;
		}

		if ( $address ) {
			$data['address'] = $address;
		}
		if ( $geo ) {
			$data['geo'] = $geo;
		}

		// Offer-Logik.
		$mode   = (string) get_post_meta( $post_id, '_immo_mode', true );
		$status = (string) get_post_meta( $post_id, '_immo_status', true );
		$price  = (float) get_post_meta( $post_id, '_immo_price', true );
		$rent   = (float) get_post_meta( $post_id, '_immo_rent', true );
		$avail  = (string) get_post_meta( $post_id, '_immo_available_from', true );

		$offers = array();
		if ( in_array( $mode, array( 'sale', 'both' ), true ) ) {
			$o = $this->build_offer_sale( $price, $status, $avail );
			if ( $o ) { $offers[] = $o; }
		}
		if ( in_array( $mode, array( 'rent', 'both' ), true ) ) {
			$o = $this->build_offer_rent( $rent, $status );
			if ( $o ) { $offers[] = $o; }
		}

		if ( count( $offers ) === 1 ) {
			$data['offers'] = $offers[0];
		} elseif ( count( $offers ) > 1 ) {
			$data['offers'] = $offers;
		}

		return $data;
	}
```

- [ ] **Step 5: render() für Property aktivieren**

In der `render()`-Methode den Property-Block ersetzen:

```php
		if ( is_singular( PostTypes::POST_TYPE_PROPERTY ) ) {
			$post_id = (int) get_queried_object_id();
			$data    = $this->build_property( $post_id );
			$data    = apply_filters( 'immo_manager_schema_property', $data, $post_id );
			$this->output_jsonld( $data );
			return;
		}
```

- [ ] **Step 6: Manueller Verifikationstest – Browser**

Demo-Property im Frontend aufrufen, Source-View (Strg+U).

**Erwartet:**
- Im `<head>` ist ein `<script type="application/ld+json">`-Block
- `@type` ist `RealEstateListing`
- `mainEntity.@type` passt zum Plugin-Property-Type
- Bei `mode=sale` und `_immo_price > 0`: `offers.priceSpecification.minPrice` enthält den Preis
- Bei `mode=rent`: `offers.priceSpecification.unitText` ist `"MONTH"`
- Bei Preis 0: kein `offers`-Feld
- Address/Geo nur wenn Werte vorhanden

- [ ] **Step 7: Manueller Verifikationstest – schema.org Validator**

`https://validator.schema.org/` öffnen → URL der Demo-Property einfügen → "RUN TEST".

**Erwartet:** Keine ❌ Errors. Warnings für nicht-anerkannte Felder sind OK.

- [ ] **Step 8: Manueller Verifikationstest – Google Rich Results Test**

`https://search.google.com/test/rich-results` öffnen → URL einfügen.

**Erwartet:** "Page is eligible for…" oder "No items detected" (Real Estate hat keine Rich Results bei Google – wichtig ist nur: keine Errors).

- [ ] **Step 9: Commit**

```bash
git add includes/class-schema.php
git commit -m "feat(schema): render RealEstateListing JSON-LD on property pages"
```

---

## Task 4: Bauprojekt-Schema mit eingebetteten Wohneinheiten

**Files:**
- Modify: `includes/class-schema.php`

**Ziel:** Bauprojekt-Detailseiten liefern `ApartmentComplex` mit `containsPlace`-Array, jedes Element ein `Apartment`. Unit-Namen mit Projekt-Prefix.

- [ ] **Step 1: Apartment-Builder pro Unit ergänzen**

```php
	/**
	 * Eine Unit als Apartment-Schema bauen (für containsPlace).
	 *
	 * @param array<string, mixed> $unit          Hydrierte Unit-Row.
	 * @param int                  $project_id    Projekt-Post-ID.
	 * @param string               $project_title Projekt-Titel (für Name-Prefix).
	 *
	 * @return array<string, mixed>
	 */
	private function build_unit_apartment( array $unit, int $project_id, string $project_title ): array {
		$unit_id     = (int) ( $unit['id'] ?? 0 );
		$unit_number = (string) ( $unit['unit_number'] ?? '' );
		$anchor      = '#unit-' . $unit_id;
		$url         = get_permalink( $project_id ) . $anchor;

		$name = '' !== $unit_number
			? sprintf( '%s – %s', $project_title, $unit_number )
			: sprintf( '%s – Wohnung %d', $project_title, $unit_id );

		$apt = array(
			'@type' => 'Apartment',
			'@id'   => $url,
			'url'   => $url,
			'name'  => $name,
		);

		$area = (float) ( $unit['area'] ?? 0 );
		if ( $area > 0 ) {
			$apt['floorSize'] = array(
				'@type'    => 'QuantitativeValue',
				'value'    => $area,
				'unitCode' => 'MTK',
			);
		}

		$rooms = (int) ( $unit['rooms'] ?? 0 );
		if ( $rooms > 0 ) { $apt['numberOfRooms'] = $rooms; }

		$floor = (int) ( $unit['floor'] ?? 0 );
		if ( $floor > 0 ) { $apt['floorLevel'] = $floor; }

		// Fixpreis-Offer (kein "ab" – Units sind Fixpreise laut Memory-Vorgabe).
		$price  = (float) ( $unit['price'] ?? 0 );
		$status = (string) ( $unit['status'] ?? 'available' );

		if ( $price > 0 ) {
			$apt['offers'] = array(
				'@type'         => 'Offer',
				'price'         => $price,
				'priceCurrency' => 'EUR',
				'availability'  => $this->map_status_to_availability( $status ),
			);
		}

		return $apt;
	}
```

- [ ] **Step 2: Project-Schema-Builder ergänzen**

```php
	/**
	 * ApartmentComplex-Schema für ein Bauprojekt bauen.
	 *
	 * @param int $post_id Projekt-Post-ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function build_project( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		$permalink = get_permalink( $post_id );
		$title     = get_the_title( $post_id );

		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'ApartmentComplex',
			'@id'        => $permalink,
			'url'        => $permalink,
			'name'       => $title,
			'inLanguage' => $this->get_in_language(),
		);

		$description = (string) get_the_excerpt( $post_id );
		if ( '' === $description ) {
			$description = wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 50, '…' );
		}
		if ( '' !== $description ) {
			$data['description'] = $description;
		}

		$images = $this->build_image_list( $post_id );
		if ( ! empty( $images ) ) {
			$data['image'] = $images;
		}

		$address = $this->build_address( $post_id );
		if ( $address ) { $data['address'] = $address; }

		$geo = $this->build_geo( $post_id );
		if ( $geo ) { $data['geo'] = $geo; }

		// Units → containsPlace.
		$units = Units::get_by_project( $post_id );
		$skip_sold = (bool) apply_filters( 'immo_manager_schema_skip_sold_units', false, $post_id );

		$apartments = array();
		foreach ( $units as $unit ) {
			$status = (string) ( $unit['status'] ?? 'available' );
			if ( $skip_sold && in_array( $status, array( 'sold', 'rented' ), true ) ) {
				continue;
			}
			$apartments[] = $this->build_unit_apartment( $unit, $post_id, $title );
		}

		if ( ! empty( $apartments ) ) {
			$data['numberOfAccommodationUnits'] = count( $apartments );
			$data['containsPlace']              = $apartments;
		}

		// Projekt-Status als additionalProperty.
		$project_status = (string) get_post_meta( $post_id, '_immo_project_status', true );
		if ( '' !== $project_status ) {
			$data['additionalProperty'] = array(
				array(
					'@type' => 'PropertyValue',
					'name'  => 'projectStatus',
					'value' => $project_status,
				),
			);
		}

		return $data;
	}
```

- [ ] **Step 3: render() für Project aktivieren**

```php
		if ( is_singular( PostTypes::POST_TYPE_PROJECT ) ) {
			$post_id = (int) get_queried_object_id();
			$data    = $this->build_project( $post_id );
			$data    = apply_filters( 'immo_manager_schema_project', $data, $post_id );
			$this->output_jsonld( $data );
			return;
		}
```

- [ ] **Step 4: Manueller Verifikationstest – Browser**

Demo-Bauprojekt mit mindestens 2 Units (mind. eine `available`, eine `sold`) aufrufen, Source-View.

**Erwartet:**
- `@type: ApartmentComplex`
- `numberOfAccommodationUnits` entspricht Unit-Anzahl
- `containsPlace` ist ein Array
- Jedes Element: `@type: Apartment`, `name` startet mit Projekt-Titel + "–", `@id` ist `…#unit-{ID}`
- Sold-Unit hat `availability: SoldOut`
- Available-Unit hat `availability: InStock`
- Unit ohne Preis: kein `offers`-Feld (aber sonst alle Felder vorhanden)

- [ ] **Step 5: Manueller Verifikationstest – Validator**

URL des Bauprojekts in `https://validator.schema.org/` einfügen.

**Erwartet:** Keine Errors. ApartmentComplex und alle Apartment-Children werden erkannt.

- [ ] **Step 6: Commit**

```bash
git add includes/class-schema.php
git commit -m "feat(schema): render ApartmentComplex with embedded Apartment units on project pages"
```

---

## Task 5: BreadcrumbList-Schema

**Files:**
- Modify: `includes/class-schema.php`

**Ziel:** Auf beiden Detailseiten zusätzlich ein `BreadcrumbList`-Block. Position-2-Label aus Post-Type-Labels (übersetzbar).

- [ ] **Step 1: Breadcrumb-Builder ergänzen**

```php
	/**
	 * BreadcrumbList für eine Detailseite bauen.
	 *
	 * @param int    $post_id   Post-ID.
	 * @param string $post_type Post-Type-Slug.
	 *
	 * @return array<string, mixed>|null
	 */
	private function build_breadcrumbs( int $post_id, string $post_type ): ?array {
		$archive_url = get_post_type_archive_link( $post_type );
		$pt_obj      = get_post_type_object( $post_type );
		$archive_label = $pt_obj && isset( $pt_obj->labels->name )
			? (string) $pt_obj->labels->name
			: $post_type;

		$items = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => __( 'Home', 'immo-manager' ),
				'item'     => home_url( '/' ),
			),
		);

		if ( $archive_url ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => $archive_label,
				'item'     => $archive_url,
			);
		}

		$items[] = array(
			'@type'    => 'ListItem',
			'position' => count( $items ) + 1,
			'name'     => get_the_title( $post_id ),
			'item'     => get_permalink( $post_id ),
		);

		return array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}
```

- [ ] **Step 2: render() um Breadcrumbs erweitern**

In `render()` direkt nach den jeweiligen Property/Project-Blöcken die Breadcrumbs ergänzen. Komplette neue `render()`-Methode:

```php
	public function render(): void {
		if ( is_singular( PostTypes::POST_TYPE_PROPERTY ) ) {
			$post_id = (int) get_queried_object_id();

			$data = $this->build_property( $post_id );
			$data = apply_filters( 'immo_manager_schema_property', $data, $post_id );
			$this->output_jsonld( $data );

			$crumbs = $this->build_breadcrumbs( $post_id, PostTypes::POST_TYPE_PROPERTY );
			$crumbs = apply_filters( 'immo_manager_schema_breadcrumbs', $crumbs, $post_id );
			$this->output_jsonld( $crumbs );
			return;
		}

		if ( is_singular( PostTypes::POST_TYPE_PROJECT ) ) {
			$post_id = (int) get_queried_object_id();

			$data = $this->build_project( $post_id );
			$data = apply_filters( 'immo_manager_schema_project', $data, $post_id );
			$this->output_jsonld( $data );

			$crumbs = $this->build_breadcrumbs( $post_id, PostTypes::POST_TYPE_PROJECT );
			$crumbs = apply_filters( 'immo_manager_schema_breadcrumbs', $crumbs, $post_id );
			$this->output_jsonld( $crumbs );
			return;
		}
	}
```

- [ ] **Step 3: Manueller Verifikationstest**

Beide Demo-Detailseiten aufrufen, Source-View.

**Erwartet:**
- Zwei separate `<script type="application/ld+json">`-Blöcke pro Seite
- Zweiter Block: `@type: BreadcrumbList` mit drei `ListItem`-Einträgen
- Position 1: "Home" → Site-URL
- Position 2: "Immobilien" / "Bauprojekte" → Archiv-URL
- Position 3: Post-Titel → Post-URL

Validator-Check: BreadcrumbList wird ohne Errors erkannt.

- [ ] **Step 4: Commit**

```bash
git add includes/class-schema.php
git commit -m "feat(schema): add BreadcrumbList JSON-LD on property and project pages"
```

---

## Task 6: Akzeptanz-Checkliste durchgehen

**Files:** keine

**Ziel:** Alle Punkte aus `2026-04-27-schema-org-design.md` Sektion 9 bestätigen. Bei Issues: Bugfix-Commit.

- [ ] **Step 1: Akzeptanzkriterien einzeln prüfen**

Auf einer lokalen Test-Installation:

- [ ] Property-Detailseite liefert `RealEstateListing` – Validator: keine Errors
- [ ] Property-Detailseite – Google Rich Results Test: keine Errors
- [ ] Bauprojekt-Detailseite liefert `ApartmentComplex` mit `containsPlace`
- [ ] Breadcrumb-Block auf beiden Detailseiten vorhanden und valide
- [ ] Sale-Modus zeigt `Offer.priceSpecification.minPrice`
- [ ] Rent-Modus zeigt `Offer.priceSpecification.unitText: MONTH`
- [ ] Property mit Preis 0 ("Preis auf Anfrage") → kein `offers`-Feld
- [ ] Status `sold` → `availability: SoldOut`
- [ ] Status `rented` → `availability: OutOfStock`
- [ ] Filter `immo_manager_schema_property` greift (Test mit `add_filter` in `wp-content/mu-plugins/test-schema-filter.php`):

```php
<?php
add_filter( 'immo_manager_schema_property', function( $data, $post_id ) {
    $data['testFilterApplied'] = true;
    return $data;
}, 10, 2 );
```

→ JSON-LD-Output enthält `"testFilterApplied": true`. Datei nach Test wieder löschen.

- [ ] Filter `immo_manager_schema_skip_sold_units` greift (gleiches Vorgehen, sold-Units verschwinden aus `containsPlace`)
- [ ] Lighthouse-SEO-Score: keine Regression gegenüber vor der Implementierung

- [ ] **Step 2: Bei Bedarf Bugfix-Commits**

Falls Punkte fehlschlagen: Issue beheben, fokussierten Bugfix-Commit machen.

- [ ] **Step 3: Abschluss-Commit (falls keine Bugfixes nötig)**

Nichts zu committen. Plan ist fertig.

---

## Self-Review (vom Plan-Autor durchgeführt)

**Spec-Coverage-Check:**

| Spec-Sektion | Abgedeckt von |
|---|---|
| §3 Architektur (Klasse, wp_head, Filter-Hooks) | Task 1, 5 |
| §4 Property-Schema (RealEstateListing + mainEntity) | Task 3 |
| §4 Type-Mapping (Apartment/House/SFR/Residence) | Task 2 Step 1 |
| §4 Address (PostalAddress) | Task 2 Step 3 |
| §4 Geo (GeoCoordinates) | Task 2 Step 4 |
| §4 Offer Sale/Rent/Both | Task 3 Steps 1-2 |
| §4 Status-Mapping → availability | Task 2 Step 2 |
| §4 additionalProperty (Energie, Heizung, Provision, Renovierung) | Task 3 Step 3 |
| §5 ApartmentComplex + containsPlace | Task 4 Steps 1-2 |
| §5 Unit-Name mit Projekt-Prefix | Task 4 Step 1 |
| §5 Skip-Sold-Filter | Task 4 Step 2 |
| §6 BreadcrumbList | Task 5 |
| §7 Edge Cases (Bilder/Adresse/Geo fehlen) | überall: null-checks in Buildern |
| §9 Akzeptanzkriterien | Task 6 |

**Placeholder-Scan:** Keine TBDs/TODOs. Alle Code-Blöcke vollständig. Alle Test-Schritte mit erwarteten Ergebnissen.

**Type-Konsistenz:** `map_property_type`, `map_status_to_availability`, `build_address`, `build_geo`, `build_image_list`, `image_object`, `output_jsonld`, `get_in_language`, `build_offer_sale`, `build_offer_rent`, `build_additional_properties`, `build_property`, `build_unit_apartment`, `build_project`, `build_breadcrumbs` – konsistent benannt und überall mit gleicher Signatur referenziert.

Plan ist konsistent.
