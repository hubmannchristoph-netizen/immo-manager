# OpenImmo Phase 3 – Import-Parsing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** OpenImmo-ZIPs (von Phase 1 oder von externen Sendern) entpacken, parsen und entweder als neue Properties anlegen oder in `wp_immo_conflicts` für Phase 5 ablegen. Bilder mit Hash-Dedup in WP Media Library importieren. Trigger: File-Upload-Button auf Settings-Seite.

**Architecture:** Klar getrennte Layer unter `includes/openimmo/import/`. `ZipExtractor` entpackt, `ImportMapper` baut DTOs aus dem XML, `ImageImporter` macht Hash-Dedup für Bilder, `ConflictDetector` entscheidet "neu" vs. "existing", `ImportService` orchestriert. Phase-1-Mapper bekommt minimalen Refactor (Konstanten `public`).

**Tech Stack:** WordPress 5.9+, PHP 7.4+, `ZipArchive`, `DOMDocument`, WP Media-Library-API (`wp_handle_sideload` / `wp_insert_attachment`), libxml2.

**Spec:** `docs/superpowers/specs/2026-04-28-openimmo-phase-3-import-design.md`

**Phasen-Kontext:** Phase 3 von 7. Phase 0/1/2 abgeschlossen. Phase 4 (SFTP-Pull-Cron) erweitert den Trigger.

**Hinweis zu Tests:** Wie in Phase 0/1/2 keine PHPUnit-Infrastruktur. Validierung manuell via PHP-Lint, Plugin-Reaktivierung, Upload-Button im Backend. Boomerang-Test: Phase-1-Export-ZIP erneut importieren = round-trip-Validierung.

---

## File Structure

| Aktion | Datei | Verantwortung |
|---|---|---|
| Create | `includes/openimmo/import/class-import-listing-dto.php` | Werte-Objekt |
| Create | `includes/openimmo/import/class-zip-extractor.php` | ZIP entpacken in temp |
| Create | `includes/openimmo/import/class-import-mapper.php` | XML → DTO |
| Create | `includes/openimmo/import/class-image-importer.php` | Bild → Media Library mit Hash-Dedup |
| Create | `includes/openimmo/import/class-conflict-detector.php` | „neu" vs. „existing" |
| Create | `includes/openimmo/import/class-import-service.php` | Orchestrator |
| Modify | `includes/openimmo/export/class-mapper.php` | TYPE_MAP/FEATURE_MAP `public const` |
| Modify | `includes/class-meta-fields.php` | + `_immo_openimmo_external_id` |
| Modify | `includes/class-database.php` | DB v1.5.0, +1 units-Spalte |
| Modify | `includes/openimmo/class-admin-page.php` | Import-Block + AJAX + JS |
| Modify | `includes/class-plugin.php` | Lazy-Getter `get_openimmo_import_service()` |

---

## Task 1: Foundation (DB v1.5.0 + Meta + Mapper-Konstanten public)

**Files:**
- Modify: `includes/class-database.php`
- Modify: `includes/class-meta-fields.php`
- Modify: `includes/openimmo/export/class-mapper.php`

**Ziel:** Schema-Vorbereitung für Phase 3. Property bekommt `_immo_openimmo_external_id`, units-Tabelle bekommt `external_id`-Spalte, Phase-1-Mapper-Konstanten werden für `ImportMapper` zugänglich.

- [ ] **Step 1: DB-Version hochziehen**

`includes/class-database.php` — `DB_VERSION` auf `'1.5.0'`:

```php
public const DB_VERSION = '1.5.0';
```

- [ ] **Step 2: Units-Spalte ergänzen**

In `Database::install()` im `$sql_units`-CREATE-Statement, nach den OpenImmo-Opt-In-Spalten (`openimmo_immoscout24 TINYINT(1) NOT NULL DEFAULT 0,`):

```php
external_id VARCHAR(100) NULL DEFAULT NULL,
```

Danach im Index-Block einen KEY-Eintrag für die neue Spalte ergänzen (vor der schließenden `)`):

```php
KEY external_id (external_id),
```

- [ ] **Step 3: Property-Meta-Feld ergänzen**

`includes/class-meta-fields.php` in `MetaFields::property_fields()`, im OpenImmo-Block (nach den 2 Opt-In-Booleans aus Phase 1):

```php
'_immo_openimmo_external_id'  => array( 'type' => 'string',  'default' => '' ),
```

- [ ] **Step 4: Mapper-Konstanten public**

`includes/openimmo/export/class-mapper.php` — die zwei Konstanten von `private const` auf `public const` ändern:

```php
public const TYPE_MAP = array(
    'wohnung'       => array( 'wohnung',       null,          null ),
    'haus'          => array( 'haus',          null,          null ),
    'grundstueck'   => array( 'grundstueck',   null,          null ),
    'buero'         => array( 'gewerbe',       'BUERO',       'gewerbetyp' ),
    'gewerbe'       => array( 'gewerbe',       null,          null ),
    'gastronomie'   => array( 'gastgew',       'GASTRONOMIE', 'gastgewtyp' ),
);

public const FEATURE_MAP = array(
    'balkon'   => 'balkon_terrasse',
    'terrasse' => 'balkon_terrasse',
    'garten'   => 'gartennutzung',
    'lift'     => 'fahrstuhl',
    'aufzug'   => 'fahrstuhl',
    'keller'   => 'unterkellert',
    'parking'  => 'stellplatzart',
    'garage'   => 'stellplatzart',
    'klima'    => 'klimatisiert',
    'einbaukueche' => 'kueche',
);
```

(Inhalt unverändert — nur Sichtbarkeit geändert.)

- [ ] **Step 5: PHP-Lint**

```bash
php -l includes/class-database.php
php -l includes/class-meta-fields.php
php -l includes/openimmo/export/class-mapper.php
```

**Erwartet:** „No syntax errors detected" für alle drei.

- [ ] **Step 6: Manueller Test**

1. Plugin deaktivieren + reaktivieren.
2. `SHOW COLUMNS FROM wp_immo_units LIKE 'external_id';` → 1 Zeile (VARCHAR(100), NULL).
3. `SHOW INDEX FROM wp_immo_units WHERE Key_name='external_id';` → 1 Zeile.

- [ ] **Step 7: Commit**

```bash
git add includes/class-database.php includes/class-meta-fields.php includes/openimmo/export/class-mapper.php
git commit -m "feat(openimmo): add foundation for phase 3 (DB v1.5.0 + meta + public mapper constants)"
```

---

## Task 2: ImportListingDTO + ZipExtractor

**Files:**
- Create: `includes/openimmo/import/class-import-listing-dto.php`
- Create: `includes/openimmo/import/class-zip-extractor.php`

**Ziel:** DTO und ZIP-Entpack-Layer.

- [ ] **Step 1: ImportListingDTO**

`includes/openimmo/import/class-import-listing-dto.php`:

```php
<?php
/**
 * DTO für ein importiertes OpenImmo-Listing.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Import;

defined( 'ABSPATH' ) || exit;

class ImportListingDTO {

	/** @var string aus <verwaltung_techn><objektnr_intern> */
	public string $external_id = '';

	/** @var string aus <freitexte><objekttitel> */
	public string $title = '';

	/** @var string aus <freitexte><objektbeschreibung> */
	public string $description = '';

	/**
	 * Plugin-Meta-Felder (`_immo_*` → Wert).
	 *
	 * @var array<string,mixed>
	 */
	public array $meta = array();

	/**
	 * Roh-Bild-Verweise aus dem XML.
	 *
	 * @var array<int,array{relpath:string, gruppe:string}>
	 */
	public array $raw_images = array();
}
```

- [ ] **Step 2: ZipExtractor**

`includes/openimmo/import/class-zip-extractor.php`:

```php
<?php
/**
 * Entpackt ein OpenImmo-ZIP in ein temporäres Verzeichnis.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Import;

defined( 'ABSPATH' ) || exit;

class ZipExtractor {

	private string $tmp_base = '';

	/**
	 * @return array{xml_path:string, images_dir:string, tmp_base:string}
	 * @throws \RuntimeException
	 */
	public function extract( string $zip_path ): array {
		if ( ! file_exists( $zip_path ) ) {
			throw new \RuntimeException( 'ZIP nicht gefunden: ' . $zip_path );
		}

		$upload_dir     = wp_get_upload_dir();
		$basename       = preg_replace( '/[^A-Za-z0-9._-]/', '_', basename( $zip_path, '.zip' ) );
		$this->tmp_base = $upload_dir['basedir'] . '/openimmo/import-tmp/' . $basename . '-' . time();

		if ( ! wp_mkdir_p( $this->tmp_base ) ) {
			throw new \RuntimeException( 'Konnte temp-Verzeichnis nicht anlegen: ' . $this->tmp_base );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			throw new \RuntimeException( 'ZIP nicht entpackbar: ' . $zip_path );
		}
		if ( ! $zip->extractTo( $this->tmp_base ) ) {
			$zip->close();
			throw new \RuntimeException( 'Entpacken fehlgeschlagen: ' . $zip_path );
		}
		$zip->close();

		$xml_path = $this->find_openimmo_xml( $this->tmp_base );
		if ( null === $xml_path ) {
			throw new \RuntimeException( 'openimmo.xml fehlt im ZIP' );
		}

		$images_dir = dirname( $xml_path ) . '/images';
		if ( ! is_dir( $images_dir ) ) {
			$images_dir = '';  // erlaubt — Listings ohne Bilder.
		}

		return array(
			'xml_path'   => $xml_path,
			'images_dir' => $images_dir,
			'tmp_base'   => $this->tmp_base,
		);
	}

	public function cleanup(): void {
		if ( '' === $this->tmp_base ) {
			return;
		}
		$this->rrmdir( $this->tmp_base );
		$this->tmp_base = '';
	}

	private function find_openimmo_xml( string $dir ): ?string {
		// 1. Direkt im Root.
		if ( file_exists( $dir . '/openimmo.xml' ) ) {
			return $dir . '/openimmo.xml';
		}
		// 2. Im ersten Unterordner (manche Sender packen alles in einen Ordner).
		$entries = scandir( $dir );
		if ( ! is_array( $entries ) ) {
			return null;
		}
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( is_dir( $path ) && file_exists( $path . '/openimmo.xml' ) ) {
				return $path . '/openimmo.xml';
			}
		}
		return null;
	}

	private function rrmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( ! is_array( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->rrmdir( $path );
			} else {
				@unlink( $path );  // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}
		@rmdir( $dir );  // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}
```

- [ ] **Step 3: PHP-Lint**

```bash
php -l includes/openimmo/import/class-import-listing-dto.php
php -l includes/openimmo/import/class-zip-extractor.php
```

- [ ] **Step 4: Commit**

```bash
git add includes/openimmo/import/class-import-listing-dto.php includes/openimmo/import/class-zip-extractor.php
git commit -m "feat(openimmo): add ImportListingDTO and ZipExtractor"
```

---

## Task 3: ImportMapper (XML → DTO)

**Files:**
- Create: `includes/openimmo/import/class-import-mapper.php`

**Ziel:** Inverse-Mapping von OpenImmo-XML zurück auf Plugin-Meta-Felder. Nutzt `Mapper::TYPE_MAP` und `Mapper::FEATURE_MAP` aus Phase 1.

- [ ] **Step 1: ImportMapper anlegen**

`includes/openimmo/import/class-import-mapper.php`:

```php
<?php
/**
 * Mapper: ein <immobilie>-DOMElement → ein ImportListingDTO.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Import;

use ImmoManager\OpenImmo\Export\Mapper;

defined( 'ABSPATH' ) || exit;

class ImportMapper {

	public function from_immobilie( \DOMElement $immobilie ): ImportListingDTO {
		$dto = new ImportListingDTO();

		// verwaltung_techn / objektnr_intern.
		$dto->external_id = $this->read_text( $immobilie, 'objektnr_intern' );

		// freitexte.
		$dto->title       = $this->read_text( $immobilie, 'objekttitel' );
		$dto->description = $this->read_text( $immobilie, 'objektbeschreibung' );

		$meta = array();

		// objektkategorie.
		$meta['_immo_property_type'] = $this->read_objektart( $immobilie );
		$meta['_immo_mode']          = $this->read_vermarktungsart( $immobilie );

		// geo.
		$meta['_immo_postal_code']  = $this->read_text( $immobilie, 'plz' );
		$meta['_immo_city']         = $this->read_text( $immobilie, 'ort' );
		$meta['_immo_address']      = $this->read_text( $immobilie, 'strasse' );
		$meta['_immo_region_state'] = $this->read_text( $immobilie, 'bundesland' );
		$meta['_immo_country']      = $this->read_attr( $immobilie, 'land', 'iso_land' ) ?: 'AT';

		$geo_node = $this->first_child( $immobilie, 'geokoordinaten' );
		if ( $geo_node instanceof \DOMElement ) {
			$meta['_immo_lat'] = (float) $geo_node->getAttribute( 'breitengrad' );
			$meta['_immo_lng'] = (float) $geo_node->getAttribute( 'laengengrad' );
		}
		$meta['_immo_floor']        = (int) $this->read_text( $immobilie, 'etage' );
		$meta['_immo_total_floors'] = (int) $this->read_text( $immobilie, 'anzahl_etagen' );

		// flaechen.
		$meta['_immo_area']        = (float) $this->read_text( $immobilie, 'wohnflaeche' );
		$meta['_immo_usable_area'] = (float) $this->read_text( $immobilie, 'nutzflaeche' );
		$meta['_immo_land_area']   = (float) $this->read_text( $immobilie, 'grundstuecksflaeche' );
		$meta['_immo_rooms']       = (int) $this->read_text( $immobilie, 'anzahl_zimmer' );
		$meta['_immo_bedrooms']    = (int) $this->read_text( $immobilie, 'anzahl_schlafzimmer' );
		$meta['_immo_bathrooms']   = (int) $this->read_text( $immobilie, 'anzahl_badezimmer' );

		// preise.
		$meta['_immo_price']           = (float) $this->read_text( $immobilie, 'kaufpreis' );
		$meta['_immo_rent']            = (float) $this->read_text( $immobilie, 'kaltmiete' );
		$meta['_immo_operating_costs'] = (float) $this->read_text( $immobilie, 'nebenkosten' );
		$meta['_immo_deposit']         = (float) $this->read_text( $immobilie, 'kaution' );
		$meta['_immo_commission']      = $this->read_text( $immobilie, 'aussen_courtage' );

		// zustand_angaben.
		$meta['_immo_built_year']      = (int) $this->read_text( $immobilie, 'baujahr' );
		$meta['_immo_renovation_year'] = (int) $this->read_text( $immobilie, 'letztemodernisierung' );
		$meta['_immo_energy_class']    = $this->read_text( $immobilie, 'hwbklasse' );
		$meta['_immo_energy_hwb']      = (float) $this->read_text( $immobilie, 'hwbwert' );
		$meta['_immo_energy_fgee']     = (float) $this->read_text( $immobilie, 'fgeewert' );

		// ausstattung.
		$meta['_immo_features'] = $this->read_features( $immobilie );
		$heizungsdetail         = $this->read_text( $immobilie, 'sonstige_heizungsart' );
		if ( '' !== $heizungsdetail ) {
			$meta['_immo_heating'] = $heizungsdetail;
		}

		// verwaltung_objekt.
		$meta['_immo_available_from'] = $this->read_text( $immobilie, 'verfuegbar_ab' );

		// kontaktperson.
		$kontakt_vor   = $this->read_text( $immobilie, 'vorname' );
		$kontakt_name  = $this->read_text( $immobilie, 'name' );
		$meta['_immo_contact_name']  = trim( $kontakt_vor . ' ' . $kontakt_name );
		$meta['_immo_contact_email'] = $this->read_text( $immobilie, 'email_zentrale' );
		$meta['_immo_contact_phone'] = $this->read_text( $immobilie, 'tel_zentrale' );

		$dto->meta       = $meta;
		$dto->raw_images = $this->read_anhaenge( $immobilie );

		return $dto;
	}

	private function read_text( \DOMElement $parent, string $tag ): string {
		$nodes = $parent->getElementsByTagName( $tag );
		if ( 0 === $nodes->length ) {
			return '';
		}
		return trim( (string) $nodes->item( 0 )->textContent );
	}

	private function read_attr( \DOMElement $parent, string $child_tag, string $attr ): string {
		$nodes = $parent->getElementsByTagName( $child_tag );
		if ( 0 === $nodes->length ) {
			return '';
		}
		$node = $nodes->item( 0 );
		return $node instanceof \DOMElement ? (string) $node->getAttribute( $attr ) : '';
	}

	private function first_child( \DOMElement $parent, string $tag ): ?\DOMElement {
		$nodes = $parent->getElementsByTagName( $tag );
		if ( 0 === $nodes->length ) {
			return null;
		}
		$node = $nodes->item( 0 );
		return $node instanceof \DOMElement ? $node : null;
	}

	private function read_objektart( \DOMElement $immobilie ): string {
		$art = $this->first_child( $immobilie, 'objektart' );
		if ( null === $art ) {
			return 'wohnung';
		}
		// Iteriere die TYPE_MAP und finde das passende Tag im <objektart>.
		foreach ( Mapper::TYPE_MAP as $plugin_slug => $mapping ) {
			$xml_tag         = $mapping[0];
			$expected_attr_v = $mapping[1];
			$expected_attr_n = $mapping[2];

			$child = $this->first_child( $art, $xml_tag );
			if ( null === $child ) {
				continue;
			}
			if ( null !== $expected_attr_n ) {
				if ( $child->getAttribute( $expected_attr_n ) === $expected_attr_v ) {
					return $plugin_slug;
				}
				continue;
			}
			// Kein Sub-typ erforderlich — generisches Match.
			return $plugin_slug;
		}
		return 'wohnung';
	}

	private function read_vermarktungsart( \DOMElement $immobilie ): string {
		$verm = $this->first_child( $immobilie, 'vermarktungsart' );
		if ( null === $verm ) {
			return 'sale';
		}
		$kauf  = 'true' === strtolower( (string) $verm->getAttribute( 'KAUF' ) );
		$miete = 'true' === strtolower( (string) $verm->getAttribute( 'MIETE_PACHT' ) );
		if ( $kauf && $miete ) {
			return 'both';
		}
		if ( $miete ) {
			return 'rent';
		}
		return 'sale';
	}

	private function read_features( \DOMElement $immobilie ): array {
		$ausstattung = $this->first_child( $immobilie, 'ausstattung' );
		if ( null === $ausstattung ) {
			return array();
		}
		$plugin_features = array();
		// Reverse-Lookup: für jeden in FEATURE_MAP gemappten OpenImmo-Tag schauen, ob er existiert.
		// Mehrere Plugin-Slugs können auf denselben OpenImmo-Tag mappen — der erste Treffer gewinnt.
		$seen_oi_tags = array();
		foreach ( Mapper::FEATURE_MAP as $plugin_slug => $oi_tag ) {
			if ( isset( $seen_oi_tags[ $oi_tag ] ) ) {
				continue;  // bereits durch früheren Plugin-Slug abgedeckt.
			}
			if ( $ausstattung->getElementsByTagName( $oi_tag )->length > 0 ) {
				$plugin_features[]            = $plugin_slug;
				$seen_oi_tags[ $oi_tag ]      = true;
			}
		}
		return $plugin_features;
	}

	private function read_anhaenge( \DOMElement $immobilie ): array {
		$anhaenge = $this->first_child( $immobilie, 'anhaenge' );
		if ( null === $anhaenge ) {
			return array();
		}
		$out = array();
		foreach ( $anhaenge->getElementsByTagName( 'anhang' ) as $anhang ) {
			if ( ! $anhang instanceof \DOMElement ) {
				continue;
			}
			$gruppe  = (string) $anhang->getAttribute( 'gruppe' ) ?: 'BILD';
			$relpath = '';
			$pfad    = $anhang->getElementsByTagName( 'pfad' );
			if ( $pfad->length > 0 ) {
				$relpath = trim( (string) $pfad->item( 0 )->textContent );
			}
			if ( '' === $relpath ) {
				continue;
			}
			$out[] = array( 'relpath' => $relpath, 'gruppe' => $gruppe );
		}
		return $out;
	}
}
```

- [ ] **Step 2: PHP-Lint**

```bash
php -l includes/openimmo/import/class-import-mapper.php
```

- [ ] **Step 3: Commit**

```bash
git add includes/openimmo/import/class-import-mapper.php
git commit -m "feat(openimmo): add ImportMapper for XML to DTO conversion"
```

---

## Task 4: ImageImporter (mit Hash-Dedup)

**Files:**
- Create: `includes/openimmo/import/class-image-importer.php`

**Ziel:** Bilder aus dem entpackten ZIP in WP Media Library importieren, mit Hash-basierter Duplikat-Erkennung.

- [ ] **Step 1: ImageImporter anlegen**

`includes/openimmo/import/class-image-importer.php`:

```php
<?php
/**
 * Importiert Bilder aus dem ZIP in die WP Media Library.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Import;

defined( 'ABSPATH' ) || exit;

class ImageImporter {

	public const HASH_META_KEY = '_immo_openimmo_image_hash';

	/**
	 * @param array<int,array{relpath:string, gruppe:string}> $raw_images
	 * @return array<int,array{att_id:int, gruppe:string, hash:string}>
	 */
	public function import_all( array $raw_images, string $images_dir ): array {
		if ( '' === $images_dir || ! is_dir( $images_dir ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw_images as $img ) {
			$relpath = $img['relpath'] ?? '';
			$gruppe  = $img['gruppe'] ?? 'BILD';
			if ( '' === $relpath ) {
				continue;
			}
			$local_path = $images_dir . '/' . basename( $relpath );
			if ( ! file_exists( $local_path ) ) {
				continue;
			}
			$hash = sha1_file( $local_path );
			if ( false === $hash ) {
				continue;
			}

			$existing = $this->find_by_hash( $hash );
			if ( null !== $existing ) {
				$out[] = array( 'att_id' => $existing, 'gruppe' => $gruppe, 'hash' => $hash );
				continue;
			}

			$att_id = $this->sideload( $local_path, basename( $relpath ) );
			if ( null === $att_id ) {
				continue;
			}
			update_post_meta( $att_id, self::HASH_META_KEY, $hash );
			$out[] = array( 'att_id' => $att_id, 'gruppe' => $gruppe, 'hash' => $hash );
		}
		return $out;
	}

	private function find_by_hash( string $hash ): ?int {
		$query = new \WP_Query( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => self::HASH_META_KEY,
					'value' => $hash,
				),
			),
		) );
		if ( empty( $query->posts ) ) {
			return null;
		}
		return (int) $query->posts[0];
	}

	/**
	 * Lädt eine lokale Datei in die Media Library.
	 *
	 * @return int|null Attachment-ID oder null bei Fehler.
	 */
	private function sideload( string $local_path, string $desired_filename ): ?int {
		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// wp_handle_sideload erwartet einen $_FILES-ähnlichen Eintrag.
		// Da unser File schon im Filesystem liegt, kopieren wir es vorher in einen tmp-Pfad,
		// weil wp_handle_sideload die Datei verschiebt.
		$tmp_copy = wp_tempnam( $desired_filename );
		if ( ! copy( $local_path, $tmp_copy ) ) {
			return null;
		}

		$file_array = array(
			'name'     => $desired_filename,
			'tmp_name' => $tmp_copy,
		);

		$overrides = array(
			'test_form' => false,
			'test_size' => true,
		);

		$sideload = wp_handle_sideload( $file_array, $overrides );
		if ( isset( $sideload['error'] ) ) {
			@unlink( $tmp_copy );
			return null;
		}

		$attachment = array(
			'post_mime_type' => $sideload['type'],
			'post_title'     => sanitize_file_name( pathinfo( $desired_filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$att_id = wp_insert_attachment( $attachment, $sideload['file'] );
		if ( is_wp_error( $att_id ) || 0 === (int) $att_id ) {
			return null;
		}
		$meta = wp_generate_attachment_metadata( $att_id, $sideload['file'] );
		wp_update_attachment_metadata( $att_id, $meta );

		return (int) $att_id;
	}
}
```

- [ ] **Step 2: PHP-Lint**

```bash
php -l includes/openimmo/import/class-image-importer.php
```

- [ ] **Step 3: Commit**

```bash
git add includes/openimmo/import/class-image-importer.php
git commit -m "feat(openimmo): add ImageImporter with hash-based dedup"
```

---

## Task 5: ConflictDetector

**Files:**
- Create: `includes/openimmo/import/class-conflict-detector.php`

**Ziel:** Erkennt anhand der `external_id`, ob ein Listing neu oder existing ist.

- [ ] **Step 1: ConflictDetector anlegen**

`includes/openimmo/import/class-conflict-detector.php`:

```php
<?php
/**
 * Entscheidet pro Listing: 'new' oder 'existing'.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Import;

use ImmoManager\Database;
use ImmoManager\PostTypes;

defined( 'ABSPATH' ) || exit;

class ConflictDetector {

	/**
	 * @return array{kind:string, post_id?:int, unit_id?:int}
	 */
	public function detect( ImportListingDTO $dto ): array {
		$ext = $dto->external_id;

		// 1. wp-{id}-Boomerang (Property).
		if ( preg_match( '/^wp-(\d+)$/', $ext, $m ) ) {
			$post = get_post( (int) $m[1] );
			if ( $post instanceof \WP_Post && PostTypes::POST_TYPE_PROPERTY === $post->post_type ) {
				return array( 'kind' => 'existing_property', 'post_id' => (int) $post->ID );
			}
		}

		// 2. unit-{id}-Boomerang (Unit).
		if ( preg_match( '/^unit-(\d+)$/', $ext, $m ) ) {
			$unit_id = (int) $m[1];
			global $wpdb;
			$table = Database::units_table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id = %d", $unit_id ) );
			if ( $exists > 0 ) {
				return array( 'kind' => 'existing_unit', 'unit_id' => $unit_id );
			}
		}

		// 3. Externe ID via Property-Meta.
		if ( '' !== $ext ) {
			$query = new \WP_Query( array(
				'post_type'      => PostTypes::POST_TYPE_PROPERTY,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => '_immo_openimmo_external_id', 'value' => $ext ),
				),
			) );
			if ( ! empty( $query->posts ) ) {
				return array( 'kind' => 'existing_property', 'post_id' => (int) $query->posts[0] );
			}

			// 4. Externe ID via Units-Tabelle.
			global $wpdb;
			$table = Database::units_table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE external_id = %s LIMIT 1", $ext ) );
			if ( $row ) {
				return array( 'kind' => 'existing_unit', 'unit_id' => (int) $row );
			}
		}

		return array( 'kind' => 'new' );
	}
}
```

- [ ] **Step 2: PHP-Lint**

```bash
php -l includes/openimmo/import/class-conflict-detector.php
```

- [ ] **Step 3: Commit**

```bash
git add includes/openimmo/import/class-conflict-detector.php
git commit -m "feat(openimmo): add ConflictDetector for new vs existing listings"
```

---

## Task 6: ImportService (Orchestrator)

**Files:**
- Create: `includes/openimmo/import/class-import-service.php`

**Ziel:** Verdrahtet Extractor + Mapper + ImageImporter + Detector. Behandelt Fehler pro Listing, persistiert Neue / schreibt Konflikte, schreibt SyncLog.

- [ ] **Step 1: ImportService anlegen**

`includes/openimmo/import/class-import-service.php`:

```php
<?php
/**
 * Orchestrator: führt einen OpenImmo-Import durch.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Import;

use ImmoManager\OpenImmo\SyncLog;
use ImmoManager\OpenImmo\Conflicts;
use ImmoManager\PostTypes;

defined( 'ABSPATH' ) || exit;

class ImportService {

	/**
	 * @return array{status:string, summary:string, counts:array{new:int, conflicts:int, skipped:int, errors:int}, sync_id:int}
	 */
	public function run( string $zip_path ): array {
		$sync_id = SyncLog::start( 'import', 'import' );
		if ( 0 === $sync_id ) {
			error_log( '[immo-manager openimmo] SyncLog::start failed for import — proceeding without persistence' );
		}

		$counts     = array( 'new' => 0, 'conflicts' => 0, 'skipped' => 0, 'errors' => 0 );
		$errors     = array();
		$img_errors = array();

		$extractor = new ZipExtractor();
		try {
			$extracted = $extractor->extract( $zip_path );
		} catch ( \Throwable $e ) {
			return $this->finish( $sync_id, 'error', 'ZIP entpacken fehlgeschlagen: ' . $e->getMessage(), $counts, array() );
		}

		try {
			$dom = new \DOMDocument();
			libxml_use_internal_errors( true );
			$loaded = $dom->load( $extracted['xml_path'] );
			if ( ! $loaded ) {
				$libxml_errs = libxml_get_errors();
				libxml_clear_errors();
				$first = isset( $libxml_errs[0] ) ? trim( $libxml_errs[0]->message ) : 'unknown';
				return $this->finish( $sync_id, 'error', 'XML invalid: ' . $first, $counts, array(
					'libxml_errors' => array_map(
						static function ( $e ) {
							return array( 'line' => (int) $e->line, 'message' => trim( $e->message ) );
						},
						$libxml_errs
					),
				) );
			}

			$mapper    = new ImportMapper();
			$image_imp = new ImageImporter();
			$detector  = new ConflictDetector();

			$immobilien = $dom->getElementsByTagName( 'immobilie' );
			foreach ( $immobilien as $immobilie ) {
				if ( ! $immobilie instanceof \DOMElement ) {
					continue;
				}
				$dto = null;
				try {
					$dto         = $mapper->from_immobilie( $immobilie );
					$attachments = $image_imp->import_all( $dto->raw_images, $extracted['images_dir'] );
					$detection   = $detector->detect( $dto );

					if ( 'new' === $detection['kind'] ) {
						$post_id = $this->create_property( $dto, $attachments );
						if ( $post_id > 0 ) {
							++$counts['new'];
						} else {
							++$counts['errors'];
							$errors[] = array( 'external_id' => $dto->external_id, 'message' => 'wp_insert_post failed' );
						}
					} else {
						$post_id     = $detection['post_id'] ?? 0;
						$conflict_id = $this->record_conflict( (int) $post_id, $dto, $attachments );
						if ( $conflict_id > 0 ) {
							++$counts['conflicts'];
						} else {
							++$counts['skipped'];
						}
					}
				} catch ( \Throwable $e ) {
					++$counts['errors'];
					$errors[] = array(
						'external_id' => $dto instanceof ImportListingDTO ? $dto->external_id : '(parse failed)',
						'message'     => $e->getMessage(),
					);
				}
			}
		} finally {
			$extractor->cleanup();
		}

		$status  = ( 0 === $counts['errors'] ) ? 'success' : 'partial';
		$summary = sprintf(
			'%d neu, %d Konflikte, %d übersprungen, %d Fehler',
			$counts['new'],
			$counts['conflicts'],
			$counts['skipped'],
			$counts['errors']
		);
		return $this->finish( $sync_id, $status, $summary, $counts, array(
			'errors'       => $errors,
			'image_errors' => $img_errors,
		) );
	}

	/**
	 * @param array<int,array{att_id:int, gruppe:string, hash:string}> $attachments
	 */
	private function create_property( ImportListingDTO $dto, array $attachments ): int {
		$post_id = wp_insert_post( array(
			'post_type'    => PostTypes::POST_TYPE_PROPERTY,
			'post_title'   => $dto->title !== '' ? $dto->title : '(unbenannt)',
			'post_content' => $dto->description,
			'post_status'  => 'publish',
		) );
		if ( is_wp_error( $post_id ) || 0 === (int) $post_id ) {
			return 0;
		}
		$post_id = (int) $post_id;

		foreach ( $dto->meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		update_post_meta( $post_id, '_immo_openimmo_external_id', $dto->external_id );

		// Featured + Galerie aus Attachments.
		$titel_id = 0;
		foreach ( $attachments as $a ) {
			if ( 'TITELBILD' === $a['gruppe'] ) {
				$titel_id = (int) $a['att_id'];
				break;
			}
		}
		if ( $titel_id > 0 ) {
			set_post_thumbnail( $post_id, $titel_id );
		}
		// Galerie-Attachments: post_parent setzen, damit Phase-1 `gallery_for_property` sie findet.
		foreach ( $attachments as $a ) {
			if ( 'TITELBILD' === $a['gruppe'] ) {
				continue;
			}
			wp_update_post( array(
				'ID'          => (int) $a['att_id'],
				'post_parent' => $post_id,
			) );
		}

		return $post_id;
	}

	/**
	 * @param array<int,array{att_id:int, gruppe:string, hash:string}> $attachments
	 */
	private function record_conflict( int $post_id, ImportListingDTO $dto, array $attachments ): int {
		if ( 0 === $post_id ) {
			return 0;
		}
		$current = $this->collect_current_meta( $post_id );
		$changed = array();

		foreach ( $dto->meta as $key => $incoming_value ) {
			// Frage 4: Felder, die im Incoming leer/abwesend sind, werden nicht diffed.
			if ( '' === $incoming_value || null === $incoming_value
				|| ( is_array( $incoming_value ) && empty( $incoming_value ) ) ) {
				continue;
			}
			$current_value = $current[ $key ] ?? '';
			if ( $this->values_differ( $current_value, $incoming_value ) ) {
				$changed[ $key ] = array( 'current' => $current_value, 'incoming' => $incoming_value );
			}
		}

		if ( empty( $changed ) ) {
			return 0;  // identisch — kein Konflikt.
		}

		// Attachment-IDs ins source_data einbetten für spätere Verknüpfung in Phase 5.
		$source_with_attachments                                            = $dto->meta;
		$source_with_attachments['_immo_openimmo_pending_attachment_ids']   = wp_json_encode(
			array_map(
				static function ( $a ) {
					return array( 'att_id' => (int) $a['att_id'], 'gruppe' => (string) $a['gruppe'] );
				},
				$attachments
			)
		);

		return Conflicts::add( $post_id, 'import', $source_with_attachments, $current, array_keys( $changed ) );
	}

	private function collect_current_meta( int $post_id ): array {
		$raw = get_post_meta( $post_id );
		$out = array();
		foreach ( $raw as $key => $values ) {
			if ( 0 === strpos( $key, '_immo_' ) && isset( $values[0] ) ) {
				$out[ $key ] = maybe_unserialize( $values[0] );
			}
		}
		return $out;
	}

	private function values_differ( $a, $b ): bool {
		if ( is_numeric( $a ) && is_numeric( $b ) ) {
			return abs( (float) $a - (float) $b ) > 0.001;
		}
		if ( is_array( $a ) || is_array( $b ) ) {
			$a_norm = is_array( $a ) ? array_values( $a ) : array();
			$b_norm = is_array( $b ) ? array_values( $b ) : array();
			sort( $a_norm );
			sort( $b_norm );
			return $a_norm !== $b_norm;
		}
		return trim( (string) $a ) !== trim( (string) $b );
	}

	/**
	 * @param array{new:int, conflicts:int, skipped:int, errors:int} $counts
	 * @param array<string,mixed>                                    $details
	 */
	private function finish( int $sync_id, string $status, string $summary, array $counts, array $details ): array {
		$details['counts'] = $counts;
		$total             = array_sum( $counts );
		if ( 0 !== $sync_id ) {
			SyncLog::finish( $sync_id, $status, $summary, $details, $total );
		}
		return array(
			'status'  => $status,
			'summary' => $summary,
			'counts'  => $counts,
			'sync_id' => $sync_id,
		);
	}
}
```

- [ ] **Step 2: PHP-Lint**

```bash
php -l includes/openimmo/import/class-import-service.php
```

- [ ] **Step 3: Commit**

```bash
git add includes/openimmo/import/class-import-service.php
git commit -m "feat(openimmo): add ImportService orchestrator"
```

---

## Task 7: AdminPage Upload-Block + Plugin-Lazy-Getter

**Files:**
- Modify: `includes/openimmo/class-admin-page.php`
- Modify: `includes/class-plugin.php`

**Ziel:** File-Upload-Form auf der Settings-Seite, AJAX-Handler, JS, und Lazy-Getter im Singleton.

- [ ] **Step 1: Plugin::get_openimmo_import_service()**

In `includes/class-plugin.php` neue Property + Getter:

```php
/**
 * OpenImmo Import-Service.
 *
 * @var \ImmoManager\OpenImmo\Import\ImportService|null
 */
private $openimmo_import_service = null;
```

(neben den anderen `$openimmo_*`-Properties)

```php
/**
 * OpenImmo Import-Service abrufen (Lazy Loading).
 *
 * @return \ImmoManager\OpenImmo\Import\ImportService
 */
public function get_openimmo_import_service(): \ImmoManager\OpenImmo\Import\ImportService {
	if ( null === $this->openimmo_import_service ) {
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-settings.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-sync-log.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-conflicts.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/export/class-mapper.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/import/class-import-listing-dto.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/import/class-zip-extractor.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/import/class-import-mapper.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/import/class-image-importer.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/import/class-conflict-detector.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/import/class-import-service.php';
		$this->openimmo_import_service = new \ImmoManager\OpenImmo\Import\ImportService();
	}
	return $this->openimmo_import_service;
}
```

- [ ] **Step 2: AdminPage Constructor erweitern**

In `includes/openimmo/class-admin-page.php` Constructor:

```php
add_action( 'wp_ajax_immo_manager_openimmo_import_zip', array( $this, 'ajax_import_zip' ) );
```

(neben den anderen AJAX-Hooks ergänzen)

- [ ] **Step 3: AJAX-Handler implementieren**

In derselben Klasse als neue Methode (z.B. nach den anderen `ajax_*`-Methoden):

```php
/**
 * AJAX-Handler: ZIP hochladen und importieren.
 */
public function ajax_import_zip(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
	}
	check_ajax_referer( 'immo_manager_openimmo_import_zip', 'nonce' );

	if ( empty( $_FILES['zip']['tmp_name'] ) || empty( $_FILES['zip']['name'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Keine Datei hochgeladen.', 'immo-manager' ) ), 400 );
	}
	if ( ! is_uploaded_file( $_FILES['zip']['tmp_name'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Upload ungültig.', 'immo-manager' ) ), 400 );
	}
	$original_name = sanitize_file_name( wp_unslash( $_FILES['zip']['name'] ) );
	if ( ! preg_match( '/\.zip$/i', $original_name ) ) {
		wp_send_json_error( array( 'message' => __( 'Nur .zip akzeptiert.', 'immo-manager' ) ), 400 );
	}

	$upload_dir = wp_get_upload_dir();
	$stage_dir  = $upload_dir['basedir'] . '/openimmo/import-staging';
	if ( ! is_dir( $stage_dir ) ) {
		wp_mkdir_p( $stage_dir );
	}
	$stage_path = $stage_dir . '/' . time() . '-' . $original_name;
	if ( ! move_uploaded_file( $_FILES['zip']['tmp_name'], $stage_path ) ) {
		wp_send_json_error( array( 'message' => __( 'Konnte Upload nicht speichern.', 'immo-manager' ) ), 500 );
	}

	$service = \ImmoManager\Plugin::instance()->get_openimmo_import_service();
	$result  = $service->run( $stage_path );

	@unlink( $stage_path );  // phpcs:ignore WordPress.PHP.NoSilencedErrors

	if ( in_array( $result['status'], array( 'success', 'partial' ), true ) ) {
		$c = $result['counts'];
		wp_send_json_success( array(
			'message' => sprintf(
				__( 'Import fertig: %1$d neu, %2$d Konflikte, %3$d übersprungen, %4$d Fehler', 'immo-manager' ),
				$c['new'],
				$c['conflicts'],
				$c['skipped'],
				$c['errors']
			),
			'counts'  => $c,
			'sync_id' => $result['sync_id'],
		) );
	}
	wp_send_json_error( array( 'message' => $result['summary'] ) );
}
```

- [ ] **Step 4: render() um Import-Block erweitern**

In `render()`, NACH der Portal-`foreach`-Schleife (`<?php endforeach; ?>`) und VOR `submit_button()`:

```php
<h2><?php esc_html_e( 'OpenImmo-Import (Test)', 'immo-manager' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'ZIP-Datei mit openimmo.xml + images/ hier hochladen. Neue Listings werden direkt angelegt, bestehende landen in der Konflikt-Queue.', 'immo-manager' ); ?>
</p>
<p>
	<input type="file" id="immo-openimmo-import-zip" accept=".zip">
	<button type="button"
			class="button button-primary immo-openimmo-import-now"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'immo_manager_openimmo_import_zip' ) ); ?>">
		<?php esc_html_e( 'ZIP importieren', 'immo-manager' ); ?>
	</button>
	<span class="immo-openimmo-import-status" style="margin-left:10px;"></span>
</p>
```

- [ ] **Step 5: Inline-JS ergänzen**

Im bestehenden `<script>`-Block (innerhalb der existierenden IIFE), nach dem letzten Event-Handler:

```javascript
$(document).on('click', '.immo-openimmo-import-now', function(e){
	e.preventDefault();
	var $btn  = $(this);
	var $stat = $btn.siblings('.immo-openimmo-import-status');
	var file  = $('#immo-openimmo-import-zip')[0].files[0];
	if (!file) {
		$stat.css('color', 'red').text('<?php echo esc_js( __( 'Bitte ZIP-Datei wählen.', 'immo-manager' ) ); ?>');
		return;
	}
	var fd = new FormData();
	fd.append('action', 'immo_manager_openimmo_import_zip');
	fd.append('nonce',  $btn.data('nonce'));
	fd.append('zip',    file);

	$btn.prop('disabled', true);
	$stat.css('color', '').text('<?php echo esc_js( __( 'Importiere…', 'immo-manager' ) ); ?>');
	$.ajax({
		url:         ajaxurl,
		type:        'POST',
		data:        fd,
		processData: false,
		contentType: false
	}).done(function(resp){
		if (resp.success) {
			$stat.css('color', 'green').text(resp.data.message);
		} else {
			$stat.css('color', 'red').text(resp.data && resp.data.message ? resp.data.message : 'Fehler');
		}
	}).fail(function(){
		$stat.css('color', 'red').text('<?php echo esc_js( __( 'Netzwerkfehler', 'immo-manager' ) ); ?>');
	}).always(function(){
		$btn.prop('disabled', false);
	});
});
```

- [ ] **Step 6: PHP-Lint**

```bash
php -l includes/openimmo/class-admin-page.php
php -l includes/class-plugin.php
```

- [ ] **Step 7: Commit**

```bash
git add includes/openimmo/class-admin-page.php includes/class-plugin.php
git commit -m "feat(openimmo): add import-zip upload UI and lazy getter"
```

---

## Task 8: Akzeptanz-Checkliste + Memory-Update

**Files:** keine (nur Verifikation)

**Ziel:** Phase 3 ist nachweislich produktionsfertig.

- [ ] **Step 1: Vollständige Verifikation**

- [ ] PHP-Lint sauber für alle neuen/modifizierten Dateien
- [ ] Plugin lädt ohne Fatal nach Reaktivierung
- [ ] DB-Migration v1.5.0 läuft, `wp_immo_units.external_id` existiert
- [ ] Settings-Seite zeigt Block „OpenImmo-Import (Test)" mit File-Input + Button
- [ ] Empty-ZIP (ZIP ohne `openimmo.xml`) → Notice „ZIP entpacken fehlgeschlagen: openimmo.xml fehlt"
- [ ] Defektes XML (z.B. unclosed tag) → Notice „XML invalid: ..."
- [ ] Happy-Path neu: ZIP mit 2 unbekannten Listings → 2 neue Properties in `wp-admin/edit.php?post_type=immo_mgr_property`
- [ ] Boomerang: Phase-1-Export-ZIP nochmal importieren → 0 neu (alle existing), korrekte Anzahl skipped/conflicts
- [ ] Conflict-Erkennung: Existing Property `_immo_address` ändern, ZIP importieren → 1 Conflict in `wp_immo_conflicts` mit `conflict_fields` enthält `_immo_address`
- [ ] `wp_immo_conflicts.portal = 'import'`
- [ ] Bild-Hash-Dedup: ZIP zweimal importieren → 0 neue Attachments beim 2. Mal (nur 1 Eintrag pro Hash in `wp_postmeta` mit `meta_key='_immo_openimmo_image_hash'`)
- [ ] Update-Verhalten: Existing Property hat `_immo_energy_class='B'`, ZIP ohne `<energiepass>` → Energy-Class bleibt 'B' (kein Conflict-Eintrag wegen leerem incoming-Wert)
- [ ] Phase-1-Mapper-Konstanten public: Phase-1-Export funktioniert weiter (kein Regression)
- [ ] tmp-Verzeichnisse `wp-content/uploads/openimmo/import-tmp/` und `import-staging/` werden nach jedem Import aufgeräumt
- [ ] Featured-Image korrekt gesetzt bei neu angelegtem Property (gruppe='TITELBILD')
- [ ] Galerie-Attachments haben `post_parent = $new_post_id`
- [ ] Keine PHP-Notices/Warnings mit `WP_DEBUG=true` während eines Imports

- [ ] **Step 2: Bei Bedarf Bugfix-Commits**

- [ ] **Step 3: Memory-Update**

In `~/.claude/projects/.../memory/project_openimmo.md` Phase-3-Status auf „erledigt" setzen, Hinweis auf Phase 4 (SFTP-Pull-Cron + erweiterten manuellen Upload).

- [ ] **Step 4: Bereitschaftsmeldung**

Ergebnis an User: „Phase 3 fertig. Bereit für Phase 4 (Import-Transport)? Erst nach OK weiter."

---

## Self-Review (vom Plan-Autor durchgeführt)

**Spec-Coverage-Check:**

| Spec-Anforderung | Abgedeckt in |
|---|---|
| DB v1.5.0 + units.external_id | T1 |
| Property-Meta `_immo_openimmo_external_id` | T1 |
| Mapper-Konstanten public | T1 |
| ImportListingDTO | T2 |
| ZipExtractor (entpacken + finden + cleanup) | T2 |
| ImportMapper inkl. Reverse-Lookup TYPE_MAP/FEATURE_MAP | T3 |
| ImageImporter mit Hash-Dedup | T4 |
| ConflictDetector mit 4-stufigem Lookup | T5 |
| ImportService Orchestrator + Diff-Logik + Conflict-Persistenz | T6 |
| File-Upload-Block + AJAX-Handler + JS | T7 |
| Plugin-Lazy-Getter | T7 |
| Akzeptanz-Liste | T8 |
| Out-of-Scope-Liste | nicht im Plan implementiert (korrekt) |

**Placeholder-Scan:** Keine TBD/TODO/„implement later". Alle Code-Blöcke vollständig.

**Type-Konsistenz:**
- `ImportListingDTO` Properties (`external_id`, `title`, `description`, `meta`, `raw_images`) konsistent zwischen T2 (Definition), T3 (Befüllung), T6 (Konsumation).
- `ImageImporter::import_all()` Rückgabe-Shape (`{att_id, gruppe, hash}`) konsistent zwischen T4 (Definition) und T6 (`create_property` / `record_conflict`).
- `ConflictDetector::detect()` Rückgabe-Shape (`{kind, post_id?, unit_id?}`) konsistent zwischen T5 (Definition) und T6 (Routing).
- `ImportService::run()` Rückgabe-Shape (`{status, summary, counts, sync_id}`) konsistent zwischen T6 (Definition) und T7 (AJAX-Handler).

**Bekannte Vereinfachungen (Spec-konform):**
- `gruppe='TITELBILD'` wird aus dem ersten passenden Attachment gelesen — bei mehreren TITELBILD nur das erste.
- Reverse-Lookup `FEATURE_MAP` nimmt den ersten Plugin-Slug, der auf einen OpenImmo-Tag zurückmappt (z.B. `balkon_terrasse` → `balkon`, nicht `terrasse`).
- Property-Erstellung setzt `post_status = 'publish'` direkt (kein Draft-Modus für Import). Phase 6 könnte das portal-spezifisch konfigurierbar machen.

Plan ist konsistent.
