# OpenImmo Phase 1 – Export-Mapping Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Plugin-interne Listings (Properties + Units) in einen gültigen OpenImmo-1.2.7-Export überführen — pro Portal ein eigenes ZIP mit `openimmo.xml` + resizten Bildern, lokal abgelegt, gegen offline-XSD validiert. Trigger: täglicher Cron + manueller Backend-Button.

**Architecture:** Klar getrennte Verantwortlichkeiten unter `includes/openimmo/export/`. ExportService orchestriert; Collector → Mapper → XmlBuilder → XsdValidator → ZipPackager. Foundation aus Phase 0 (Settings, SyncLog, Conflicts, Cron-Hook) wird wiederverwendet.

**Tech Stack:** WordPress 5.9+, PHP 7.4+, DOMDocument, ZipArchive, libxml2 (`schemaValidate`), `wp_get_image_editor`.

**Spec:** `docs/superpowers/specs/2026-04-28-openimmo-phase-1-export-design.md`

**Phasen-Kontext:** Phase 1 von 7. Phase 0 (Foundation) ist abgeschlossen. Phase 2 (SFTP-Upload) baut auf den hier erstellten ZIPs auf.

**Hinweis zu Tests:** Wie bei Phase 0 keine PHPUnit-Infrastruktur. Validierung manuell: PHP-Lint via `php -l`, Plugin-Reaktivierung ohne Fatal, manuelle XML/ZIP-Inspektion, `xmllint --schema` als Cross-Check.

**Wichtige Annahmen (aus dem Spec-Self-Review):**
- Units haben keine eigenen Standort-/Property-Type-Meta-Felder. **In Phase 1 wird der Geo-Block einer Unit-`<immobilie>` aus dem übergeordneten Project-Post gefüllt**; `objektart` ist hardcoded auf `wohnung`. Das ist eine bewusste Vereinfachung — Phase 6 kann das pro Portal verfeinern.
- Die Galerie-Spalte in `wp_immo_units` heißt `gallery_images` (LONGTEXT, JSON-Array von Attachment-IDs).
- Post-Types heißen real `immo_mgr_property` und `immo_mgr_project` (siehe `Database::migrate_cpts()`); werden via `PostTypes::POST_TYPE_PROPERTY`/`POST_TYPE_PROJECT` referenziert.

---

## File Structure

| Aktion | Datei | Verantwortung |
|---|---|---|
| Create | `vendor-schemas/openimmo-1.2.7/openimmo.xsd` (+ Sub-XSDs) | OpenImmo-Schema 1.2.7 zur offline-Validation |
| Create | `vendor-schemas/openimmo-1.2.7/README.md` | Quelle, Versions-Datum |
| Create | `includes/openimmo/export/class-listing-dto.php` | Werte-Objekt für ein Listing |
| Create | `includes/openimmo/export/class-collector.php` | Filtert Properties + Units anhand Portal-Key |
| Create | `includes/openimmo/export/class-xml-builder.php` | OpenImmo-Wrapper-XML (uebertragung+anbieter) |
| Create | `includes/openimmo/export/class-xsd-validator.php` | `DOMDocument::schemaValidate()` |
| Create | `includes/openimmo/export/class-mapper.php` | 1 Listing → `<immobilie>`-DOMElement |
| Create | `includes/openimmo/export/class-image-processor.php` | Resize 1920px, Q85 JPEG |
| Create | `includes/openimmo/export/class-zip-packager.php` | XML+Bilder in ZIP packen |
| Create | `includes/openimmo/export/class-export-service.php` | Orchestrator |
| Modify | `includes/class-database.php` | DB v1.4.0, +2 Spalten in `wp_immo_units` |
| Modify | `includes/class-meta-fields.php` | +2 Property-Meta-Felder (Opt-In) |
| Modify | `includes/class-metaboxes.php` | Metabox „OpenImmo-Export" für Property |
| Modify | `includes/openimmo/class-settings.php` | Anbieter-Defaults pro Portal |
| Modify | `includes/openimmo/class-admin-page.php` | Anbieter-Felder + Button „Jetzt exportieren" + AJAX |
| Modify | `includes/openimmo/class-cron-scheduler.php` | Phase-0-Stub durch echten Aufruf ersetzen |
| Modify | `includes/class-plugin.php` | Lazy Getter für ExportService + Wiring |
| Modify | `immo-manager.php` | `require_once` für neue OpenImmo-Klassen |

---

## Task 1: Foundation-Erweiterung (XSD + DB v1.4.0 + Property-Meta-Opt-In)

**Files:**
- Create: `vendor-schemas/openimmo-1.2.7/openimmo.xsd` (+ optional weitere XSDs der Spec)
- Create: `vendor-schemas/openimmo-1.2.7/README.md`
- Modify: `includes/class-database.php`
- Modify: `includes/class-meta-fields.php`
- Modify: `includes/class-metaboxes.php`

**Ziel:** XSD liegt im Repo, DB-Migration v1.4.0 ergänzt zwei Spalten in `wp_immo_units`, Property bekommt zwei Opt-In-Meta-Felder (`_immo_openimmo_willhaben`, `_immo_openimmo_immoscout24`) inkl. Metabox.

- [ ] **Step 1: XSD herunterladen und ablegen**

User-Aktion: OpenImmo 1.2.7 von openimmo.de runterladen (offizielles Schema-Paket). Inhalt nach `vendor-schemas/openimmo-1.2.7/` entpacken. Mindestens muss `openimmo.xsd` (Root-Schema) vorhanden sein.

- [ ] **Step 2: README ergänzen**

`vendor-schemas/openimmo-1.2.7/README.md`:

```markdown
# OpenImmo 1.2.7 XML-Schema

Quelle: https://openimmo.de/
Heruntergeladen: 2026-04-28
Lizenz: OpenImmo-Verband (siehe openimmo.de)

Wird vom Immo-Manager-Plugin in `XsdValidator` zur offline-Validierung
des Export-XML genutzt. Nicht modifizieren — bei Schema-Updates die
gesamte XSD-Sammlung ersetzen und das Datum oben aktualisieren.
```

- [ ] **Step 3: DB-Version hochziehen**

`includes/class-database.php` Zeile 23:

```php
public const DB_VERSION = '1.4.0';
```

- [ ] **Step 4: Units-Tabelle um zwei Spalten erweitern**

In `Database::install()` im `$sql_units`-CREATE-Statement, nach `rented_date DATETIME NULL DEFAULT NULL,`:

```php
openimmo_willhaben TINYINT(1) NOT NULL DEFAULT 0,
openimmo_immoscout24 TINYINT(1) NOT NULL DEFAULT 0,
```

Die Spalten landen automatisch via dbDelta beim nächsten Plugin-Load.

- [ ] **Step 5: Property-Meta-Felder definieren**

`includes/class-meta-fields.php` in `MetaFields::property_fields()`, nach dem `// Layout-Overrides`-Block ein neuer Block:

```php
// OpenImmo-Export Opt-In (Phase 1).
'_immo_openimmo_willhaben'    => array( 'type' => 'boolean', 'default' => false ),
'_immo_openimmo_immoscout24'  => array( 'type' => 'boolean', 'default' => false ),
```

- [ ] **Step 6: Metabox „OpenImmo-Export" registrieren**

In `includes/class-metaboxes.php` in der `register()`-Methode, nach dem letzten `add_meta_box(...)` für Property (vor dem Project-Block):

```php
add_meta_box( 'immo_property_openimmo', __( 'OpenImmo-Export', 'immo-manager' ), array( $this, 'render_property_openimmo' ), PostTypes::POST_TYPE_PROPERTY, 'side', 'default' );
```

- [ ] **Step 7: Render-Methode implementieren**

In derselben Datei eine neue Methode (analog zu den existierenden `render_*`-Methoden):

```php
public function render_property_openimmo( \WP_Post $post ): void {
    $willhaben    = (bool) get_post_meta( $post->ID, '_immo_openimmo_willhaben', true );
    $immoscout24  = (bool) get_post_meta( $post->ID, '_immo_openimmo_immoscout24', true );
    wp_nonce_field( 'immo_openimmo_meta', 'immo_openimmo_nonce' );
    ?>
    <p><?php esc_html_e( 'Diese Immobilie an folgende Portale exportieren:', 'immo-manager' ); ?></p>
    <p>
        <label>
            <input type="checkbox" name="immo_openimmo[willhaben]" value="1" <?php checked( $willhaben ); ?>>
            <?php esc_html_e( 'willhaben.at', 'immo-manager' ); ?>
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="immo_openimmo[immoscout24]" value="1" <?php checked( $immoscout24 ); ?>>
            <?php esc_html_e( 'ImmobilienScout24.at', 'immo-manager' ); ?>
        </label>
    </p>
    <p class="description">
        <?php esc_html_e( 'Nur „verfügbare" Listings werden tatsächlich exportiert.', 'immo-manager' ); ?>
    </p>
    <?php
}
```

- [ ] **Step 8: Speicher-Logik in der existierenden save_post-Action ergänzen**

In `includes/class-metaboxes.php` in der bestehenden `save()`-Methode (oder wo das save-Hooking liegt — Code-Stil prüfen) folgenden Block ergänzen:

```php
if ( PostTypes::POST_TYPE_PROPERTY === $post->post_type
    && isset( $_POST['immo_openimmo_nonce'] )
    && wp_verify_nonce( sanitize_key( $_POST['immo_openimmo_nonce'] ), 'immo_openimmo_meta' )
) {
    $oi = isset( $_POST['immo_openimmo'] ) && is_array( $_POST['immo_openimmo'] )
        ? wp_unslash( $_POST['immo_openimmo'] )
        : array();
    update_post_meta( $post_id, '_immo_openimmo_willhaben',   ! empty( $oi['willhaben'] )   ? '1' : '0' );
    update_post_meta( $post_id, '_immo_openimmo_immoscout24', ! empty( $oi['immoscout24'] ) ? '1' : '0' );
}
```

(Falls die Speicher-Logik in `class-metaboxes.php` modular aufgebaut ist und einzelne Save-Methoden hat: dort einbauen, Stil der bestehenden Code-Basis übernehmen.)

- [ ] **Step 9: PHP-Lint**

```bash
php -l includes/class-database.php
php -l includes/class-meta-fields.php
php -l includes/class-metaboxes.php
```

**Erwartet:** „No syntax errors detected" für alle drei.

- [ ] **Step 10: Manueller Test**

1. Plugin deaktivieren und reaktivieren.
2. In phpMyAdmin / wp-cli:
   ```sql
   SHOW COLUMNS FROM wp_immo_units LIKE 'openimmo_%';
   ```
   **Erwartet:** Zwei Zeilen — `openimmo_willhaben` und `openimmo_immoscout24` (TINYINT(1) DEFAULT 0).
3. Eine Property im Backend öffnen → rechte Sidebar zeigt Box „OpenImmo-Export" mit zwei Checkboxen.
4. Beide Checkboxen aktivieren, Speichern.
5. Seite neu laden — Häkchen sind noch da. In `wp_postmeta` sollte `_immo_openimmo_willhaben='1'` und `_immo_openimmo_immoscout24='1'` für diese post_id stehen.

- [ ] **Step 11: Commit**

```bash
git add vendor-schemas/ includes/class-database.php includes/class-meta-fields.php includes/class-metaboxes.php
git commit -m "feat(openimmo): add XSD schema, DB v1.4.0, opt-in meta fields"
```

---

## Task 2: Settings-Erweiterung (Anbieter-Block)

**Files:**
- Modify: `includes/openimmo/class-settings.php`
- Modify: `includes/openimmo/class-admin-page.php`

**Ziel:** Pro Portal hat die Settings-Seite einen Anbieter-Block mit allen Pflichtfeldern für `<uebertragung>` und `<anbieter>` im OpenImmo-XML.

- [ ] **Step 1: Settings::portal_defaults() erweitern**

In `includes/openimmo/class-settings.php` die Methode `portal_defaults()` erweitern:

```php
public static function portal_defaults(): array {
    return array(
        'enabled'        => false,
        // SFTP (aus Phase 0).
        'sftp_host'      => '',
        'sftp_port'      => 22,
        'sftp_user'      => '',
        'sftp_password'  => '',
        'remote_path'    => '/',
        'last_sync'      => '',
        // Anbieter-Block (Phase 1).
        'anbieternr'     => '',
        'firma'          => '',
        'openimmo_anid'  => '',
        'lizenzkennung'  => '',
        'impressum'      => '',
        'email_direkt'   => '',
        'tel_zentrale'   => '',
        'regi_id'        => '',
        'techn_email'    => '',
    );
}
```

- [ ] **Step 2: Admin-Page maybe_save() erweitern**

In `includes/openimmo/class-admin-page.php` in `maybe_save()` die Portal-Schleife so anpassen, dass die Anbieter-Felder mit gespeichert werden. Im Block `foreach ( array( 'willhaben', 'immoscout24_at' ) as $key )` das `$data['portals'][ $key ]`-Array um die Anbieter-Felder erweitern:

```php
$data['portals'][ $key ] = array(
    'enabled'       => ! empty( $portal['enabled'] ),
    'sftp_host'     => sanitize_text_field( $portal['sftp_host'] ?? '' ),
    'sftp_port'     => max( 1, (int) ( $portal['sftp_port'] ?? 22 ) ),
    'sftp_user'     => sanitize_text_field( $portal['sftp_user'] ?? '' ),
    'sftp_password' => (string) ( $portal['sftp_password'] ?? '' ),
    'remote_path'   => sanitize_text_field( $portal['remote_path'] ?? '/' ),
    'last_sync'     => '',
    // Anbieter-Block.
    'anbieternr'    => sanitize_text_field( $portal['anbieternr']    ?? '' ),
    'firma'         => sanitize_text_field( $portal['firma']         ?? '' ),
    'openimmo_anid' => sanitize_text_field( $portal['openimmo_anid'] ?? '' ),
    'lizenzkennung' => sanitize_text_field( $portal['lizenzkennung'] ?? '' ),
    'impressum'     => wp_kses_post(       $portal['impressum']     ?? '' ),
    'email_direkt'  => sanitize_email(     $portal['email_direkt']  ?? '' ),
    'tel_zentrale'  => sanitize_text_field( $portal['tel_zentrale']  ?? '' ),
    'regi_id'       => sanitize_text_field( $portal['regi_id']       ?? '' ),
    'techn_email'   => sanitize_email(     $portal['techn_email']   ?? '' ),
);
```

- [ ] **Step 3: Admin-Page render() um Anbieter-Felder erweitern**

In der `render()`-Methode innerhalb der Portal-`foreach`-Schleife, nach der existierenden `<table class="form-table">` mit den SFTP-Feldern, eine zweite Tabelle „Anbieter":

```php
<h3><?php echo esc_html( self::portal_label( $key ) . ' – ' . __( 'Anbieter-Daten', 'immo-manager' ) ); ?></h3>
<table class="form-table">
    <tr>
        <th><label><?php esc_html_e( 'Anbieter-Nr.', 'immo-manager' ); ?></label></th>
        <td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][anbieternr]" value="<?php echo esc_attr( $portal['anbieternr'] ?? '' ); ?>"></td>
    </tr>
    <tr>
        <th><label><?php esc_html_e( 'Firma', 'immo-manager' ); ?></label></th>
        <td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][firma]" value="<?php echo esc_attr( $portal['firma'] ?? '' ); ?>"></td>
    </tr>
    <tr>
        <th><label>OpenImmo-ANID</label></th>
        <td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][openimmo_anid]" value="<?php echo esc_attr( $portal['openimmo_anid'] ?? '' ); ?>"></td>
    </tr>
    <tr>
        <th><label><?php esc_html_e( 'Lizenz-Kennung', 'immo-manager' ); ?></label></th>
        <td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][lizenzkennung]" value="<?php echo esc_attr( $portal['lizenzkennung'] ?? '' ); ?>"></td>
    </tr>
    <tr>
        <th><label>regi_id</label></th>
        <td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][regi_id]" value="<?php echo esc_attr( $portal['regi_id'] ?? '' ); ?>"></td>
    </tr>
    <tr>
        <th><label><?php esc_html_e( 'Technische E-Mail', 'immo-manager' ); ?></label></th>
        <td><input type="email" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][techn_email]" value="<?php echo esc_attr( $portal['techn_email'] ?? '' ); ?>"></td>
    </tr>
    <tr>
        <th><label><?php esc_html_e( 'Anbieter-E-Mail', 'immo-manager' ); ?></label></th>
        <td><input type="email" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][email_direkt]" value="<?php echo esc_attr( $portal['email_direkt'] ?? '' ); ?>"></td>
    </tr>
    <tr>
        <th><label><?php esc_html_e( 'Anbieter-Telefon', 'immo-manager' ); ?></label></th>
        <td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][tel_zentrale]" value="<?php echo esc_attr( $portal['tel_zentrale'] ?? '' ); ?>"></td>
    </tr>
    <tr>
        <th><label><?php esc_html_e( 'Impressum', 'immo-manager' ); ?></label></th>
        <td><textarea class="large-text" rows="3" name="portals[<?php echo esc_attr( $key ); ?>][impressum]"><?php echo esc_textarea( $portal['impressum'] ?? '' ); ?></textarea></td>
    </tr>
</table>
```

- [ ] **Step 4: PHP-Lint**

```bash
php -l includes/openimmo/class-settings.php
php -l includes/openimmo/class-admin-page.php
```

- [ ] **Step 5: Manueller Test**

1. Settings-Seite „Immo Manager → OpenImmo" öffnen → unter SFTP gibt es jetzt einen Block „Anbieter-Daten" pro Portal mit 9 Feldern.
2. Test-Werte eintragen, speichern.
3. Seite neu laden → alle Werte persistiert.

- [ ] **Step 6: Commit**

```bash
git add includes/openimmo/class-settings.php includes/openimmo/class-admin-page.php
git commit -m "feat(openimmo): add anbieter settings block per portal"
```

---

## Task 3: ListingDTO + Collector

**Files:**
- Create: `includes/openimmo/export/class-listing-dto.php`
- Create: `includes/openimmo/export/class-collector.php`

**Ziel:** Datensammlung mit Filter pro Portal — `Collector::gather( $portal_key )` liefert ein Array von `ListingDTO`-Objekten.

- [ ] **Step 1: ListingDTO anlegen**

`includes/openimmo/export/class-listing-dto.php`:

```php
<?php
namespace ImmoManager\OpenImmo\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Werte-Objekt für ein Listing (Property oder Unit) im Export.
 *
 * Enthält alle Felder, die der Mapper für 1 <immobilie> braucht.
 * Geo/Kontakt-Felder kommen bei Units vom übergeordneten Project.
 */
class ListingDTO {

    /** @var string 'property' | 'unit' */
    public string $source;

    /** @var int Post-ID (für Property) oder Unit-ID (für Unit) */
    public int $id;

    /** @var int|null Parent-Project-ID (nur bei source='unit') */
    public ?int $project_id;

    /** @var string Eindeutige objektnr_intern für OpenImmo (z.B. "wp-123" / "unit-45") */
    public string $external_id;

    /** @var string Titel */
    public string $title;

    /** @var string Beschreibung (HTML wird in Plain-Text umgewandelt vom Mapper) */
    public string $description;

    /** @var array<string,mixed> Alle Plugin-Meta-Felder gebündelt */
    public array $meta;

    /** @var int|null Featured-Image (Attachment-ID) */
    public ?int $featured_image_id;

    /** @var int[] Galerie-Attachment-IDs */
    public array $gallery_image_ids;

    public static function from_property( \WP_Post $post ): self {
        $dto                    = new self();
        $dto->source            = 'property';
        $dto->id                = $post->ID;
        $dto->project_id        = null;
        $dto->external_id       = 'wp-' . $post->ID;
        $dto->title             = $post->post_title;
        $dto->description       = $post->post_content;
        $dto->meta              = self::collect_meta( $post->ID );
        $dto->featured_image_id = (int) get_post_thumbnail_id( $post->ID ) ?: null;
        $dto->gallery_image_ids = self::gallery_for_property( $post->ID );
        return $dto;
    }

    public static function from_unit( array $unit, int $project_id ): self {
        $dto                    = new self();
        $dto->source            = 'unit';
        $dto->id                = (int) $unit['id'];
        $dto->project_id        = $project_id;
        $dto->external_id       = 'unit-' . (int) $unit['id'];
        $dto->title             = sprintf( 'Unit %s', (string) ( $unit['unit_number'] ?? $unit['id'] ) );
        $dto->description       = (string) ( $unit['description'] ?? '' );
        // Unit-Felder + Project-Meta für Geo/Kontakt.
        $dto->meta              = array_merge(
            self::collect_meta( $project_id ),  // Geo/Kontakt vom Project.
            self::unit_to_meta( $unit )
        );
        $dto->featured_image_id = ! empty( $unit['floor_plan_image_id'] ) ? (int) $unit['floor_plan_image_id'] : null;
        $dto->gallery_image_ids = self::gallery_for_unit( $unit );
        return $dto;
    }

    private static function collect_meta( int $post_id ): array {
        $raw = get_post_meta( $post_id );
        $out = array();
        foreach ( $raw as $key => $values ) {
            if ( strpos( $key, '_immo_' ) === 0 && isset( $values[0] ) ) {
                $out[ $key ] = maybe_unserialize( $values[0] );
            }
        }
        return $out;
    }

    private static function unit_to_meta( array $unit ): array {
        // Unit-Spalten in den _immo_*-Schlüsselraum übersetzen.
        return array(
            '_immo_area'        => (float) ( $unit['area']        ?? 0 ),
            '_immo_usable_area' => (float) ( $unit['usable_area'] ?? 0 ),
            '_immo_rooms'       => (int)   ( $unit['rooms']       ?? 0 ),
            '_immo_bedrooms'    => (int)   ( $unit['bedrooms']    ?? 0 ),
            '_immo_bathrooms'   => (int)   ( $unit['bathrooms']   ?? 0 ),
            '_immo_floor'       => (int)   ( $unit['floor']       ?? 0 ),
            '_immo_price'       => (float) ( $unit['price']       ?? 0 ),
            '_immo_rent'        => (float) ( $unit['rent']        ?? 0 ),
            '_immo_status'      => (string) ( $unit['status']     ?? 'available' ),
            // Bei Units: hardcode auf "wohnung". Phase 6 kann das verfeinern.
            '_immo_property_type' => 'wohnung',
            // Default Mode bei Units: aus Preis/Miete ableiten.
            '_immo_mode'        => ! empty( $unit['rent'] ) ? 'rent' : 'sale',
            '_immo_features'    => (array) ( $unit['features'] ?? array() ),
        );
    }

    private static function gallery_for_property( int $post_id ): array {
        // Property-Galerie: erstmal alle Attachments mit post_parent = $post_id, außer Featured.
        $attachments = get_attached_media( 'image', $post_id );
        $featured    = (int) get_post_thumbnail_id( $post_id );
        $ids         = array();
        foreach ( $attachments as $att ) {
            if ( (int) $att->ID !== $featured ) {
                $ids[] = (int) $att->ID;
            }
        }
        return $ids;
    }

    private static function gallery_for_unit( array $unit ): array {
        $raw = $unit['gallery_images'] ?? '';
        if ( is_string( $raw ) && '' !== $raw ) {
            $decoded = json_decode( $raw, true );
            if ( is_array( $decoded ) ) {
                return array_map( 'intval', $decoded );
            }
        }
        if ( is_array( $raw ) ) {
            return array_map( 'intval', $raw );
        }
        return array();
    }
}
```

- [ ] **Step 2: Collector anlegen**

`includes/openimmo/export/class-collector.php`:

```php
<?php
namespace ImmoManager\OpenImmo\Export;

use ImmoManager\Database;
use ImmoManager\PostTypes;
use ImmoManager\Units;

defined( 'ABSPATH' ) || exit;

/**
 * Sammelt alle Listings, die für einen bestimmten Portal-Key exportiert werden sollen.
 */
class Collector {

    /**
     * @return ListingDTO[]
     */
    public function gather( string $portal_key ): array {
        $listings = array();
        $listings = array_merge( $listings, $this->collect_properties( $portal_key ) );
        $listings = array_merge( $listings, $this->collect_units( $portal_key ) );
        return $listings;
    }

    private function collect_properties( string $portal_key ): array {
        $meta_key = '_immo_openimmo_' . $portal_key;
        // immoscout24_at als Suffix → wir schneiden "_at" weg, weil das Meta-Feld nur
        // _immo_openimmo_immoscout24 heißt (siehe Task 1).
        $meta_key = str_replace( '_at', '', $meta_key );

        $query = new \WP_Query( array(
            'post_type'      => PostTypes::POST_TYPE_PROPERTY,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                'relation' => 'AND',
                array( 'key' => '_immo_status',     'value' => 'available', 'compare' => '=' ),
                array( 'key' => $meta_key,          'value' => '1',          'compare' => '=' ),
            ),
            'fields'         => 'ids',
        ) );

        $dtos = array();
        foreach ( $query->posts as $post_id ) {
            $post = get_post( (int) $post_id );
            if ( $post instanceof \WP_Post ) {
                $dtos[] = ListingDTO::from_property( $post );
            }
        }
        return $dtos;
    }

    private function collect_units( string $portal_key ): array {
        global $wpdb;
        $col   = 'openimmo_' . str_replace( '_at', '', $portal_key );  // 'openimmo_willhaben' / 'openimmo_immoscout24'
        $table = Database::units_table();

        // Units holen: status='available' AND opt-in AND project_id zeigt auf published project.
        $sql = $wpdb->prepare(
            "SELECT u.*
               FROM {$table} u
          INNER JOIN {$wpdb->posts} p ON p.ID = u.project_id
              WHERE u.status = 'available'
                AND u.{$col} = 1
                AND p.post_type = %s
                AND p.post_status = 'publish'",
            PostTypes::POST_TYPE_PROJECT
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );
        if ( ! is_array( $rows ) ) {
            return array();
        }

        $dtos = array();
        foreach ( $rows as $row ) {
            $dtos[] = ListingDTO::from_unit( $row, (int) $row['project_id'] );
        }
        return $dtos;
    }
}
```

**Hinweis:** Die `str_replace( '_at', '', ... )` ist ein bewusster Trick, weil das Property-Meta-Feld `_immo_openimmo_immoscout24` heißt (ohne `_at`-Suffix), die Portal-Settings-Keys aber `immoscout24_at`. Phase 6 kann das mit einem expliziten Mapping lösen.

- [ ] **Step 3: PHP-Lint**

```bash
php -l includes/openimmo/export/class-listing-dto.php
php -l includes/openimmo/export/class-collector.php
```

- [ ] **Step 4: Smoke-Test (manuell, im Plugin-Singleton)**

Noch kein UI-Aufruf möglich — wird in Task 9 verdrahtet. Nur PHP-Lint reicht in diesem Task.

- [ ] **Step 5: Commit**

```bash
git add includes/openimmo/export/class-listing-dto.php includes/openimmo/export/class-collector.php
git commit -m "feat(openimmo): add ListingDTO and Collector for export gathering"
```

---

## Task 4: XmlBuilder + XsdValidator

**Files:**
- Create: `includes/openimmo/export/class-xml-builder.php`
- Create: `includes/openimmo/export/class-xsd-validator.php`

**Ziel:** XmlBuilder erzeugt das `<openimmo>`-Skelett mit `<uebertragung>` und `<anbieter>`. XsdValidator validiert ein DOMDocument gegen die lokale XSD.

- [ ] **Step 1: XmlBuilder**

`includes/openimmo/export/class-xml-builder.php`:

```php
<?php
namespace ImmoManager\OpenImmo\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Erzeugt das OpenImmo-Wrapper-XML (uebertragung + anbieter).
 * <immobilie>-Knoten werden via append_immobilie() angehängt.
 */
class XmlBuilder {

    private \DOMDocument $dom;
    private \DOMElement $anbieter;

    public function __construct() {
        $this->dom               = new \DOMDocument( '1.0', 'UTF-8' );
        $this->dom->formatOutput = true;
    }

    /**
     * Initialisiert <openimmo><uebertragung><anbieter>.
     *
     * @param array $portal Portal-Settings inkl. Anbieter-Block (siehe Task 2).
     */
    public function open( array $portal, string $plugin_version ): void {
        $root = $this->dom->createElement( 'openimmo' );
        $root->setAttribute( 'xmlns',   'http://www.openimmo.de' );
        $root->setAttribute( 'version', '1.2.7' );
        $this->dom->appendChild( $root );

        // <uebertragung>.
        $uebertragung = $this->dom->createElement( 'uebertragung' );
        $uebertragung->setAttribute( 'sendersoftware', 'immo-manager' );
        $uebertragung->setAttribute( 'senderversion', $plugin_version );
        $uebertragung->setAttribute( 'art',   'OFFLINE' );
        $uebertragung->setAttribute( 'modus', 'CHANGE' );
        $uebertragung->setAttribute( 'version',     '1.2.7' );
        $uebertragung->setAttribute( 'regi_id',     (string) ( $portal['regi_id']     ?? '' ) );
        $uebertragung->setAttribute( 'techn_email', (string) ( $portal['techn_email'] ?? '' ) );
        $uebertragung->setAttribute( 'timestamp',   gmdate( 'Y-m-d\\TH:i:s' ) );
        $root->appendChild( $uebertragung );

        // <anbieter>.
        $anbieter = $this->dom->createElement( 'anbieter' );
        $this->add_text( $anbieter, 'anbieternr',     (string) ( $portal['anbieternr']    ?? '' ) );
        $this->add_text( $anbieter, 'firma',          (string) ( $portal['firma']         ?? '' ) );
        $this->add_text( $anbieter, 'openimmo_anid',  (string) ( $portal['openimmo_anid'] ?? '' ) );
        $this->add_text( $anbieter, 'lizenzkennung',  (string) ( $portal['lizenzkennung'] ?? '' ) );
        if ( ! empty( $portal['impressum'] ) ) {
            $this->add_text( $anbieter, 'impressum', (string) $portal['impressum'] );
        }
        $root->appendChild( $anbieter );
        $this->anbieter = $anbieter;
    }

    public function append_immobilie( \DOMElement $immobilie ): void {
        // Importiert den Knoten in das eigene DOMDocument und hängt ihn an.
        $imported = $this->dom->importNode( $immobilie, true );
        $this->anbieter->appendChild( $imported );
    }

    public function dom(): \DOMDocument {
        return $this->dom;
    }

    public function to_string(): string {
        return (string) $this->dom->saveXML();
    }

    private function add_text( \DOMElement $parent, string $tag, string $value ): void {
        $el = $this->dom->createElement( $tag );
        $el->appendChild( $this->dom->createTextNode( $value ) );
        $parent->appendChild( $el );
    }
}
```

- [ ] **Step 2: XsdValidator**

`includes/openimmo/export/class-xsd-validator.php`:

```php
<?php
namespace ImmoManager\OpenImmo\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Validiert ein DOMDocument gegen ein lokales XSD-Schema.
 */
class XsdValidator {

    /**
     * @return array{valid:bool, errors:array<int,array{line:int,message:string}>}
     */
    public function validate( \DOMDocument $dom, string $xsd_path ): array {
        if ( ! file_exists( $xsd_path ) ) {
            return array(
                'valid'  => false,
                'errors' => array( array( 'line' => 0, 'message' => 'XSD not found: ' . $xsd_path ) ),
            );
        }

        $previous = libxml_use_internal_errors( true );
        libxml_clear_errors();
        $ok = $dom->schemaValidate( $xsd_path );

        $errors = array();
        if ( ! $ok ) {
            foreach ( libxml_get_errors() as $err ) {
                $errors[] = array(
                    'line'    => (int) $err->line,
                    'message' => trim( (string) $err->message ),
                );
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        return array(
            'valid'  => (bool) $ok,
            'errors' => $errors,
        );
    }

    public static function default_xsd_path(): string {
        return dirname( __DIR__, 3 ) . '/vendor-schemas/openimmo-1.2.7/openimmo.xsd';
    }
}
```

- [ ] **Step 3: PHP-Lint**

```bash
php -l includes/openimmo/export/class-xml-builder.php
php -l includes/openimmo/export/class-xsd-validator.php
```

- [ ] **Step 4: Commit**

```bash
git add includes/openimmo/export/class-xml-builder.php includes/openimmo/export/class-xsd-validator.php
git commit -m "feat(openimmo): add XmlBuilder wrapper and XsdValidator"
```

---

## Task 5: Mapper

**Files:**
- Create: `includes/openimmo/export/class-mapper.php`

**Ziel:** Eine `Mapper`-Instanz erzeugt aus einem `ListingDTO` ein `<immobilie>`-DOMElement mit allen Standard-OpenImmo-Knoten. Bilder-Anhänge kommen separat in Task 6 dazu (über die Methode `append_anhang()` exposed).

- [ ] **Step 1: Mapper-Klasse anlegen**

`includes/openimmo/export/class-mapper.php`:

```php
<?php
namespace ImmoManager\OpenImmo\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Mapper: ein ListingDTO → ein <immobilie>-DOMElement.
 */
class Mapper {

    private \DOMDocument $dom;

    /**
     * Mapping Plugin-property_type → OpenImmo objektart-Kindknoten.
     * Erweitern wenn neue Property-Types dazukommen.
     */
    private const TYPE_MAP = array(
        'wohnung'       => array( 'wohnung',       null ),
        'haus'          => array( 'haus',          null ),
        'grundstueck'   => array( 'grundstueck',   null ),
        'buero'         => array( 'gewerbe',       'BUERO' ),
        'gewerbe'       => array( 'gewerbe',       null ),
        'gastronomie'   => array( 'gastgew',       'GASTRONOMIE' ),
    );

    /**
     * Slug aus _immo_features → OpenImmo-Boolean-Knoten unter <ausstattung>.
     */
    private const FEATURE_MAP = array(
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

    public function __construct( \DOMDocument $dom ) {
        $this->dom = $dom;
    }

    public function to_immobilie( ListingDTO $listing ): \DOMElement {
        $im = $this->dom->createElement( 'immobilie' );

        $im->appendChild( $this->build_objektkategorie( $listing ) );
        $im->appendChild( $this->build_geo( $listing ) );
        $im->appendChild( $this->build_kontaktperson( $listing ) );
        $im->appendChild( $this->build_preise( $listing ) );
        $im->appendChild( $this->build_flaechen( $listing ) );
        $im->appendChild( $this->build_ausstattung( $listing ) );
        $im->appendChild( $this->build_zustand_angaben( $listing ) );
        $im->appendChild( $this->build_verwaltung_objekt( $listing ) );
        $im->appendChild( $this->build_verwaltung_techn( $listing ) );
        $im->appendChild( $this->build_freitexte( $listing ) );

        // <anhaenge> wird separat (in ExportService) befüllt — leerer Container.
        $im->appendChild( $this->dom->createElement( 'anhaenge' ) );

        return $im;
    }

    private function build_objektkategorie( ListingDTO $l ): \DOMElement {
        $el = $this->dom->createElement( 'objektkategorie' );

        // <nutzungsart wohnen="true"/> — wir fokussieren in Phase 1 auf Wohnen.
        $nutzung = $this->dom->createElement( 'nutzungsart' );
        $nutzung->setAttribute( 'WOHNEN', 'true' );
        $el->appendChild( $nutzung );

        // <vermarktungsart>.
        $mode = (string) ( $l->meta['_immo_mode'] ?? 'sale' );
        $verm = $this->dom->createElement( 'vermarktungsart' );
        $verm->setAttribute( 'KAUF',         in_array( $mode, array( 'sale', 'both' ), true ) ? 'true' : 'false' );
        $verm->setAttribute( 'MIETE_PACHT',  in_array( $mode, array( 'rent', 'both' ), true ) ? 'true' : 'false' );
        $el->appendChild( $verm );

        // <objektart>.
        $type    = (string) ( $l->meta['_immo_property_type'] ?? 'wohnung' );
        $mapping = self::TYPE_MAP[ $type ] ?? array( 'wohnung', null );
        $art     = $this->dom->createElement( 'objektart' );
        $sub     = $this->dom->createElement( $mapping[0] );
        if ( null !== $mapping[1] ) {
            $sub->setAttribute( $mapping[0] . 'typ', $mapping[1] );
        }
        $art->appendChild( $sub );
        $el->appendChild( $art );

        return $el;
    }

    private function build_geo( ListingDTO $l ): \DOMElement {
        $el = $this->dom->createElement( 'geo' );

        $this->text( $el, 'plz',         (string) ( $l->meta['_immo_postal_code']    ?? '' ) );
        $this->text( $el, 'ort',         (string) ( $l->meta['_immo_city']           ?? '' ) );
        $this->text( $el, 'strasse',     (string) ( $l->meta['_immo_address']        ?? '' ) );
        $this->text( $el, 'bundesland',  (string) ( $l->meta['_immo_region_state']   ?? '' ) );

        $iso = (string) ( $l->meta['_immo_country'] ?? 'AT' );
        $land = $this->dom->createElement( 'land' );
        $land->setAttribute( 'iso_land', $iso );
        $el->appendChild( $land );

        if ( ! empty( $l->meta['_immo_lat'] ) || ! empty( $l->meta['_immo_lng'] ) ) {
            $geo = $this->dom->createElement( 'geokoordinaten' );
            $geo->setAttribute( 'breitengrad',  (string) ( $l->meta['_immo_lat'] ?? '0' ) );
            $geo->setAttribute( 'laengengrad',  (string) ( $l->meta['_immo_lng'] ?? '0' ) );
            $el->appendChild( $geo );
        }

        if ( ! empty( $l->meta['_immo_floor'] ) ) {
            $this->text( $el, 'etage', (string) (int) $l->meta['_immo_floor'] );
        }
        if ( ! empty( $l->meta['_immo_total_floors'] ) ) {
            $this->text( $el, 'anzahl_etagen', (string) (int) $l->meta['_immo_total_floors'] );
        }

        return $el;
    }

    private function build_kontaktperson( ListingDTO $l ): \DOMElement {
        $el = $this->dom->createElement( 'kontaktperson' );

        $name = (string) ( $l->meta['_immo_contact_name'] ?? '' );
        $parts = preg_split( '/\\s+/', $name, 2 );
        $vor   = $parts[0] ?? '';
        $nach  = $parts[1] ?? '';

        $this->text( $el, 'name',           $nach !== '' ? $nach : $name );
        if ( $vor !== '' ) {
            $this->text( $el, 'vorname', $vor );
        }
        if ( ! empty( $l->meta['_immo_contact_email'] ) ) {
            $this->text( $el, 'email_zentrale', (string) $l->meta['_immo_contact_email'] );
        }
        if ( ! empty( $l->meta['_immo_contact_phone'] ) ) {
            $this->text( $el, 'tel_zentrale', (string) $l->meta['_immo_contact_phone'] );
        }
        return $el;
    }

    private function build_preise( ListingDTO $l ): \DOMElement {
        $el   = $this->dom->createElement( 'preise' );
        $mode = (string) ( $l->meta['_immo_mode'] ?? 'sale' );

        if ( in_array( $mode, array( 'sale', 'both' ), true ) && ! empty( $l->meta['_immo_price'] ) ) {
            $this->text( $el, 'kaufpreis', (string) (float) $l->meta['_immo_price'] );
        }
        if ( in_array( $mode, array( 'rent', 'both' ), true ) && ! empty( $l->meta['_immo_rent'] ) ) {
            $this->text( $el, 'kaltmiete', (string) (float) $l->meta['_immo_rent'] );
        }
        if ( ! empty( $l->meta['_immo_operating_costs'] ) ) {
            $this->text( $el, 'nebenkosten', (string) (float) $l->meta['_immo_operating_costs'] );
        }
        if ( ! empty( $l->meta['_immo_deposit'] ) ) {
            $this->text( $el, 'kaution', (string) (float) $l->meta['_immo_deposit'] );
        }
        if ( ! empty( $l->meta['_immo_commission'] ) ) {
            $this->text( $el, 'aussen_courtage', (string) $l->meta['_immo_commission'] );
        }
        return $el;
    }

    private function build_flaechen( ListingDTO $l ): \DOMElement {
        $el = $this->dom->createElement( 'flaechen' );

        if ( ! empty( $l->meta['_immo_area'] ) ) {
            $this->text( $el, 'wohnflaeche', (string) (float) $l->meta['_immo_area'] );
        }
        if ( ! empty( $l->meta['_immo_usable_area'] ) ) {
            $this->text( $el, 'nutzflaeche', (string) (float) $l->meta['_immo_usable_area'] );
        }
        if ( ! empty( $l->meta['_immo_land_area'] ) ) {
            $this->text( $el, 'grundstuecksflaeche', (string) (float) $l->meta['_immo_land_area'] );
        }
        if ( ! empty( $l->meta['_immo_rooms'] ) ) {
            $this->text( $el, 'anzahl_zimmer', (string) (int) $l->meta['_immo_rooms'] );
        }
        if ( ! empty( $l->meta['_immo_bedrooms'] ) ) {
            $this->text( $el, 'anzahl_schlafzimmer', (string) (int) $l->meta['_immo_bedrooms'] );
        }
        if ( ! empty( $l->meta['_immo_bathrooms'] ) ) {
            $this->text( $el, 'anzahl_badezimmer', (string) (int) $l->meta['_immo_bathrooms'] );
        }
        return $el;
    }

    private function build_ausstattung( ListingDTO $l ): \DOMElement {
        $el       = $this->dom->createElement( 'ausstattung' );
        $features = (array) ( $l->meta['_immo_features'] ?? array() );

        $oi_seen = array();
        foreach ( $features as $slug ) {
            $slug = (string) $slug;
            if ( ! isset( self::FEATURE_MAP[ $slug ] ) ) {
                continue;
            }
            $oi_tag = self::FEATURE_MAP[ $slug ];
            if ( in_array( $oi_tag, $oi_seen, true ) ) {
                continue;
            }
            $oi_seen[] = $oi_tag;
            $node      = $this->dom->createElement( $oi_tag );
            // Feature-Knoten in OpenImmo sind oft Boolean-Container ohne Wert.
            $el->appendChild( $node );
        }

        if ( ! empty( $l->meta['_immo_heating'] ) ) {
            $heiz = $this->dom->createElement( 'heizungsart' );
            $heiz->setAttribute( 'OFEN', 'false' );  // Default-Attribute, OpenImmo-Spec verlangt ein Set.
            $el->appendChild( $heiz );
            // Heizungs-Detail als Freitext.
            $this->text( $el, 'sonstige_heizungsart', (string) $l->meta['_immo_heating'] );
        }

        return $el;
    }

    private function build_zustand_angaben( ListingDTO $l ): \DOMElement {
        $el = $this->dom->createElement( 'zustand_angaben' );

        if ( ! empty( $l->meta['_immo_built_year'] ) ) {
            $this->text( $el, 'baujahr', (string) (int) $l->meta['_immo_built_year'] );
        }
        if ( ! empty( $l->meta['_immo_renovation_year'] ) ) {
            $this->text( $el, 'letztemodernisierung', (string) (int) $l->meta['_immo_renovation_year'] );
        }

        if ( ! empty( $l->meta['_immo_energy_class'] )
            || ! empty( $l->meta['_immo_energy_hwb'] )
            || ! empty( $l->meta['_immo_energy_fgee'] ) ) {
            $energie = $this->dom->createElement( 'energiepass' );
            if ( ! empty( $l->meta['_immo_energy_class'] ) ) {
                $this->text( $energie, 'epart', 'BEDARF' );
                $this->text( $energie, 'energieverbrauchkennwert', (string) $l->meta['_immo_energy_class'] );
            }
            if ( ! empty( $l->meta['_immo_energy_hwb'] ) ) {
                $this->text( $energie, 'hwbwert',  (string) (float) $l->meta['_immo_energy_hwb'] );
                $this->text( $energie, 'hwbklasse', (string) ( $l->meta['_immo_energy_class'] ?? '' ) );
            }
            if ( ! empty( $l->meta['_immo_energy_fgee'] ) ) {
                $this->text( $energie, 'fgeewert',  (string) (float) $l->meta['_immo_energy_fgee'] );
            }
            $el->appendChild( $energie );
        }

        return $el;
    }

    private function build_verwaltung_objekt( ListingDTO $l ): \DOMElement {
        $el = $this->dom->createElement( 'verwaltung_objekt' );
        if ( ! empty( $l->meta['_immo_available_from'] ) ) {
            $this->text( $el, 'verfuegbar_ab', (string) $l->meta['_immo_available_from'] );
        }
        return $el;
    }

    private function build_verwaltung_techn( ListingDTO $l ): \DOMElement {
        $el = $this->dom->createElement( 'verwaltung_techn' );
        $this->text( $el, 'objektnr_intern', $l->external_id );

        $aktion = $this->dom->createElement( 'aktion' );
        $aktion->setAttribute( 'aktionart', 'REFRESH' );
        $el->appendChild( $aktion );

        return $el;
    }

    private function build_freitexte( ListingDTO $l ): \DOMElement {
        $el = $this->dom->createElement( 'freitexte' );

        $this->text( $el, 'objekttitel',        $l->title );
        $this->text( $el, 'objektbeschreibung', wp_strip_all_tags( $l->description ) );

        if ( ! empty( $l->meta['_immo_custom_features'] ) ) {
            $this->text( $el, 'sonstige_angaben', (string) $l->meta['_immo_custom_features'] );
        }
        return $el;
    }

    private function text( \DOMElement $parent, string $tag, string $value ): void {
        $el = $this->dom->createElement( $tag );
        $el->appendChild( $this->dom->createTextNode( $value ) );
        $parent->appendChild( $el );
    }
}
```

- [ ] **Step 2: PHP-Lint**

```bash
php -l includes/openimmo/export/class-mapper.php
```

- [ ] **Step 3: Commit**

```bash
git add includes/openimmo/export/class-mapper.php
git commit -m "feat(openimmo): add Mapper for listing -> <immobilie>"
```

---

## Task 6: ImageProcessor

**Files:**
- Create: `includes/openimmo/export/class-image-processor.php`

**Ziel:** Resized JPEG-Versionen aller Listing-Bilder (Featured + Galerie) auf max. 1920px Breite, Q85, in einem temp-Verzeichnis.

- [ ] **Step 1: ImageProcessor anlegen**

`includes/openimmo/export/class-image-processor.php`:

```php
<?php
namespace ImmoManager\OpenImmo\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Erzeugt resizte JPEG-Kopien (max. 1920px, Q85) für Listing-Bilder.
 */
class ImageProcessor {

    public const MAX_WIDTH = 1920;
    public const QUALITY   = 85;

    private string $tmp_base;

    public function __construct( string $tmp_base ) {
        $this->tmp_base = rtrim( $tmp_base, '/\\' );
        if ( ! is_dir( $this->tmp_base ) ) {
            wp_mkdir_p( $this->tmp_base );
        }
    }

    /**
     * Resize alle Bilder eines Listings.
     *
     * @return array<int,array{relpath:string,tmppath:string,gruppe:string,errors:array}>
     */
    public function resize_attached( ListingDTO $listing ): array {
        $output = array();
        $idx    = 0;

        // Featured Image als TITELBILD.
        if ( $listing->featured_image_id ) {
            $rel = sprintf( 'images/%s-%03d.jpg', $listing->external_id, ++$idx );
            $res = $this->resize_one( (int) $listing->featured_image_id, $rel );
            if ( $res ) {
                $res['gruppe'] = 'TITELBILD';
                $output[]      = $res;
            }
        }

        // Galerie als BILD.
        foreach ( $listing->gallery_image_ids as $att_id ) {
            $rel = sprintf( 'images/%s-%03d.jpg', $listing->external_id, ++$idx );
            $res = $this->resize_one( (int) $att_id, $rel );
            if ( $res ) {
                $res['gruppe'] = 'BILD';
                $output[]      = $res;
            }
        }

        return $output;
    }

    /**
     * @return array{relpath:string,tmppath:string,gruppe:string,att_id:int}|null
     */
    private function resize_one( int $attachment_id, string $relpath ): ?array {
        $orig_path = get_attached_file( $attachment_id );
        if ( ! $orig_path || ! file_exists( $orig_path ) ) {
            return null;
        }

        $editor = wp_get_image_editor( $orig_path );
        if ( is_wp_error( $editor ) ) {
            return null;
        }

        $size = $editor->get_size();
        if ( ! empty( $size['width'] ) && (int) $size['width'] > self::MAX_WIDTH ) {
            $editor->resize( self::MAX_WIDTH, null, false );
        }
        $editor->set_quality( self::QUALITY );

        $tmp_path = $this->tmp_base . '/' . str_replace( '/', '-', $relpath );
        $saved    = $editor->save( $tmp_path, 'image/jpeg' );

        if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
            return null;
        }

        return array(
            'relpath' => $relpath,
            'tmppath' => $saved['path'],
            'gruppe'  => 'BILD',
            'att_id'  => $attachment_id,
        );
    }

    public function cleanup(): void {
        $this->rrmdir( $this->tmp_base );
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

- [ ] **Step 2: PHP-Lint**

```bash
php -l includes/openimmo/export/class-image-processor.php
```

- [ ] **Step 3: Commit**

```bash
git add includes/openimmo/export/class-image-processor.php
git commit -m "feat(openimmo): add ImageProcessor (resize 1920px Q85)"
```

---

## Task 7: ZipPackager

**Files:**
- Create: `includes/openimmo/export/class-zip-packager.php`

**Ziel:** Packt das XML als `openimmo.xml` plus alle Bilder unter `images/` in eine ZIP-Datei.

- [ ] **Step 1: ZipPackager anlegen**

`includes/openimmo/export/class-zip-packager.php`:

```php
<?php
namespace ImmoManager\OpenImmo\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Packt OpenImmo-Export als ZIP.
 */
class ZipPackager {

    /**
     * @param array<int,array{relpath:string,tmppath:string}> $images
     */
    public function write( \DOMDocument $dom, array $images, string $target_path ): bool {
        $dir = dirname( $target_path );
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
            $this->protect_dir( $dir );
        }

        $zip = new \ZipArchive();
        if ( true !== $zip->open( $target_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
            return false;
        }

        $zip->addFromString( 'openimmo.xml', (string) $dom->saveXML() );

        foreach ( $images as $img ) {
            if ( file_exists( $img['tmppath'] ) ) {
                $zip->addFile( $img['tmppath'], $img['relpath'] );
            }
        }

        return $zip->close();
    }

    private function protect_dir( string $dir ): void {
        $htaccess = $dir . '/.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "Deny from all\n" );
        }
        $index = $dir . '/index.html';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, '' );
        }
    }
}
```

- [ ] **Step 2: PHP-Lint**

```bash
php -l includes/openimmo/export/class-zip-packager.php
```

- [ ] **Step 3: Commit**

```bash
git add includes/openimmo/export/class-zip-packager.php
git commit -m "feat(openimmo): add ZipPackager for export bundling"
```

---

## Task 8: ExportService + Plugin-Wiring + Cron-Aktivierung

**Files:**
- Create: `includes/openimmo/export/class-export-service.php`
- Modify: `includes/openimmo/class-cron-scheduler.php`
- Modify: `includes/class-plugin.php`
- Modify: `immo-manager.php`

**Ziel:** Orchestrator verdrahtet alle Komponenten; Cron-Hook ruft ihn pro aktivem Portal auf; Plugin-Singleton hat Lazy-Getter; Bootstrap macht `require_once` für die neuen Klassen (Autoloader-Workaround wie bei Phase 0).

- [ ] **Step 1: ExportService anlegen**

`includes/openimmo/export/class-export-service.php`:

```php
<?php
namespace ImmoManager\OpenImmo\Export;

use ImmoManager\OpenImmo\Settings;
use ImmoManager\OpenImmo\SyncLog;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrator: führt einen Export-Lauf für ein Portal durch.
 */
class ExportService {

    /**
     * @return array{status:string, summary:string, zip_path:string, count:int}
     */
    public function run( string $portal_key ): array {
        $sync_id = SyncLog::start( $portal_key, 'export' );

        try {
            $settings = Settings::get();
            $portal   = $settings['portals'][ $portal_key ] ?? null;
            if ( null === $portal ) {
                return $this->finish( $sync_id, 'error', 'unknown portal: ' . $portal_key, '', 0, array() );
            }

            $collector = new Collector();
            $listings  = $collector->gather( $portal_key );
            if ( empty( $listings ) ) {
                return $this->finish( $sync_id, 'skipped', 'no listings opted in', '', 0, array() );
            }

            // XML-Skelett.
            $builder = new XmlBuilder();
            $builder->open( $portal, $this->plugin_version() );

            // Bilder vorbereiten.
            $upload_dir = wp_get_upload_dir();
            $tmp_base   = $upload_dir['basedir'] . '/openimmo/tmp/' . $portal_key . '-' . time();
            $imager     = new ImageProcessor( $tmp_base );

            $mapper      = new Mapper( $builder->dom() );
            $all_images  = array();
            $errors      = array();
            $img_errors  = array();
            $exported    = 0;

            foreach ( $listings as $listing ) {
                try {
                    $immobilie = $mapper->to_immobilie( $listing );
                    $images    = $imager->resize_attached( $listing );
                    $this->append_anhaenge( $immobilie, $images, $builder->dom() );
                    $builder->append_immobilie( $immobilie );
                    $all_images = array_merge( $all_images, $images );
                    ++$exported;
                } catch ( \Throwable $e ) {
                    $errors[] = array( 'id' => $listing->external_id, 'message' => $e->getMessage() );
                }
            }

            // XSD-Validierung.
            $validator = new XsdValidator();
            $check     = $validator->validate( $builder->dom(), XsdValidator::default_xsd_path() );
            if ( ! $check['valid'] ) {
                $debug_path = $upload_dir['basedir'] . '/openimmo/exports/' . $portal_key . '/' . gmdate( 'Y-m-d-His' ) . '.invalid.xml';
                wp_mkdir_p( dirname( $debug_path ) );
                file_put_contents( $debug_path, $builder->to_string() );
                $imager->cleanup();
                return $this->finish( $sync_id, 'error', 'XSD invalid', '', 0, array(
                    'errors'        => $errors,
                    'image_errors'  => $img_errors,
                    'xsd_errors'    => $check['errors'],
                    'invalid_xml'   => $debug_path,
                ) );
            }

            // ZIP packen.
            $target  = sprintf(
                '%s/openimmo/exports/%s/%s-export-%s.zip',
                $upload_dir['basedir'],
                $portal_key,
                $portal_key,
                gmdate( 'Y-m-d-His' )
            );
            $packer  = new ZipPackager();
            $written = $packer->write( $builder->dom(), $all_images, $target );
            $imager->cleanup();

            if ( ! $written ) {
                return $this->finish( $sync_id, 'error', 'ZIP write failed', $target, 0, array(
                    'errors' => $errors,
                ) );
            }

            $status  = empty( $errors ) ? 'success' : 'partial';
            $summary = sprintf(
                '%d/%d Listings exportiert%s',
                $exported,
                count( $listings ),
                empty( $errors ) ? '' : sprintf( ', %d übersprungen', count( $errors ) )
            );
            return $this->finish( $sync_id, $status, $summary, $target, $exported, array(
                'errors'        => $errors,
                'image_errors'  => $img_errors,
                'zip_path'      => $target,
                'xml_size_kb'   => (int) ( strlen( $builder->to_string() ) / 1024 ),
            ) );

        } catch ( \Throwable $e ) {
            return $this->finish( $sync_id, 'error', $e->getMessage(), '', 0, array(
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ) );
        }
    }

    private function append_anhaenge( \DOMElement $immobilie, array $images, \DOMDocument $dom ): void {
        $container = $immobilie->getElementsByTagName( 'anhaenge' )->item( 0 );
        if ( ! $container instanceof \DOMElement ) {
            return;
        }
        foreach ( $images as $img ) {
            $anhang = $dom->createElement( 'anhang' );
            $anhang->setAttribute( 'location', 'EXTERN' );
            $anhang->setAttribute( 'gruppe',   (string) ( $img['gruppe'] ?? 'BILD' ) );

            $titel = $dom->createElement( 'anhangtitel' );
            $titel->appendChild( $dom->createTextNode( '' ) );
            $anhang->appendChild( $titel );

            $format = $dom->createElement( 'format' );
            $format->appendChild( $dom->createTextNode( 'jpg' ) );
            $anhang->appendChild( $format );

            $daten = $dom->createElement( 'daten' );
            $pfad  = $dom->createElement( 'pfad' );
            $pfad->appendChild( $dom->createTextNode( (string) $img['relpath'] ) );
            $daten->appendChild( $pfad );
            $anhang->appendChild( $daten );

            $container->appendChild( $anhang );
        }
    }

    private function plugin_version(): string {
        if ( defined( 'IMMO_MANAGER_VERSION' ) ) {
            return (string) IMMO_MANAGER_VERSION;
        }
        return '0.0.0';
    }

    /**
     * @param array<string,mixed> $details
     * @return array{status:string, summary:string, zip_path:string, count:int}
     */
    private function finish( int $sync_id, string $status, string $summary, string $zip_path, int $count, array $details ): array {
        SyncLog::finish( $sync_id, $status, $summary, $details, $count );
        return array(
            'status'   => $status,
            'summary'  => $summary,
            'zip_path' => $zip_path,
            'count'    => $count,
        );
    }
}
```

- [ ] **Step 2: Cron-Stub durch echten Aufruf ersetzen**

`includes/openimmo/class-cron-scheduler.php` Methode `run_daily_sync()` ersetzen:

```php
public function run_daily_sync(): void {
    $settings = Settings::get();
    if ( empty( $settings['enabled'] ) ) {
        return;
    }

    // Klasse laden — Sub-Namespace, Autoloader greift hier nicht.
    require_once dirname( __FILE__ ) . '/export/class-listing-dto.php';
    require_once dirname( __FILE__ ) . '/export/class-collector.php';
    require_once dirname( __FILE__ ) . '/export/class-xml-builder.php';
    require_once dirname( __FILE__ ) . '/export/class-xsd-validator.php';
    require_once dirname( __FILE__ ) . '/export/class-mapper.php';
    require_once dirname( __FILE__ ) . '/export/class-image-processor.php';
    require_once dirname( __FILE__ ) . '/export/class-zip-packager.php';
    require_once dirname( __FILE__ ) . '/export/class-export-service.php';

    $service = new \ImmoManager\OpenImmo\Export\ExportService();
    foreach ( $settings['portals'] as $portal_key => $portal ) {
        if ( ! empty( $portal['enabled'] ) ) {
            $service->run( $portal_key );
        }
    }
}
```

- [ ] **Step 3: Plugin-Singleton Lazy-Getter**

`includes/class-plugin.php` — analog zu `get_openimmo_admin_page()` einen Getter hinzufügen:

```php
private $openimmo_export_service = null;

public function get_openimmo_export_service(): \ImmoManager\OpenImmo\Export\ExportService {
    if ( null === $this->openimmo_export_service ) {
        require_once IMMO_MANAGER_DIR . 'includes/openimmo/export/class-listing-dto.php';
        require_once IMMO_MANAGER_DIR . 'includes/openimmo/export/class-collector.php';
        require_once IMMO_MANAGER_DIR . 'includes/openimmo/export/class-xml-builder.php';
        require_once IMMO_MANAGER_DIR . 'includes/openimmo/export/class-xsd-validator.php';
        require_once IMMO_MANAGER_DIR . 'includes/openimmo/export/class-mapper.php';
        require_once IMMO_MANAGER_DIR . 'includes/openimmo/export/class-image-processor.php';
        require_once IMMO_MANAGER_DIR . 'includes/openimmo/export/class-zip-packager.php';
        require_once IMMO_MANAGER_DIR . 'includes/openimmo/export/class-export-service.php';
        $this->openimmo_export_service = new \ImmoManager\OpenImmo\Export\ExportService();
    }
    return $this->openimmo_export_service;
}
```

(Pfad-Konstante `IMMO_MANAGER_DIR` in der bestehenden Codebase prüfen — wenn anders genannt, anpassen.)

- [ ] **Step 4: PHP-Lint**

```bash
php -l includes/openimmo/export/class-export-service.php
php -l includes/openimmo/class-cron-scheduler.php
php -l includes/class-plugin.php
```

- [ ] **Step 5: Manueller Test (Cron)**

1. Settings-Seite: „Schnittstelle aktiv" anhaken, ein Portal aktivieren, Anbieter-Felder füllen.
2. 1-2 Properties mit `_immo_status = available` und `_immo_openimmo_<portal>=1` haben.
3. ```bash
   wp cron event run immo_manager_openimmo_daily_sync
   ```
4. **Erwartet:** ZIP entsteht unter `wp-content/uploads/openimmo/exports/{portal}/`. SyncLog-Eintrag mit `status='success'` und `properties_count=2`.

- [ ] **Step 6: Commit**

```bash
git add includes/openimmo/export/class-export-service.php includes/openimmo/class-cron-scheduler.php includes/class-plugin.php
git commit -m "feat(openimmo): wire ExportService via cron and plugin singleton"
```

---

## Task 9: Settings-Button „Jetzt exportieren" + AJAX

**Files:**
- Modify: `includes/openimmo/class-admin-page.php`

**Ziel:** Auf der OpenImmo-Settings-Seite gibt es pro Portal einen Button „Jetzt exportieren", der via AJAX einen Sofort-Export auslöst und das Ergebnis als Notice zurückmeldet.

- [ ] **Step 1: AJAX-Handler in AdminPage registrieren**

`includes/openimmo/class-admin-page.php` Constructor erweitern:

```php
public function __construct() {
    add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );
    add_action( 'admin_init', array( $this, 'maybe_save' ) );
    add_action( 'wp_ajax_immo_manager_openimmo_export_now', array( $this, 'ajax_export_now' ) );
}
```

- [ ] **Step 2: AJAX-Handler implementieren**

In derselben Klasse als neue Methode:

```php
public function ajax_export_now(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
    }
    check_ajax_referer( 'immo_manager_openimmo_export_now', 'nonce' );

    $portal_key = isset( $_POST['portal'] ) ? sanitize_key( wp_unslash( $_POST['portal'] ) ) : '';
    if ( ! in_array( $portal_key, array( 'willhaben', 'immoscout24_at' ), true ) ) {
        wp_send_json_error( array( 'message' => __( 'Unbekanntes Portal.', 'immo-manager' ) ), 400 );
    }

    $service = \ImmoManager\Plugin::get_instance()->get_openimmo_export_service();
    $result  = $service->run( $portal_key );

    if ( in_array( $result['status'], array( 'success', 'partial' ), true ) ) {
        $size = file_exists( $result['zip_path'] ) ? size_format( filesize( $result['zip_path'] ), 1 ) : '';
        wp_send_json_success( array(
            'message' => sprintf(
                __( 'Export erstellt: %1$s (%2$d Listings, %3$s)', 'immo-manager' ),
                basename( $result['zip_path'] ),
                $result['count'],
                $size
            ),
            'status'  => $result['status'],
            'summary' => $result['summary'],
        ) );
    }
    wp_send_json_error( array( 'message' => $result['summary'], 'status' => $result['status'] ) );
}
```

- [ ] **Step 3: Button + JS in render() einbauen**

In der Portal-`foreach`-Schleife innerhalb von `render()`, nach der Anbieter-Tabelle:

```php
<p>
    <button type="button"
            class="button button-secondary immo-openimmo-export-now"
            data-portal="<?php echo esc_attr( $key ); ?>"
            data-nonce="<?php echo esc_attr( wp_create_nonce( 'immo_manager_openimmo_export_now' ) ); ?>">
        <?php esc_html_e( 'Jetzt exportieren (lokal, ohne Upload)', 'immo-manager' ); ?>
    </button>
    <span class="immo-openimmo-export-status" style="margin-left:10px;"></span>
</p>
```

Vor `</form>` ein kleines Inline-JS:

```php
<script>
(function($){
    $(document).on('click', '.immo-openimmo-export-now', function(e){
        e.preventDefault();
        var $btn  = $(this);
        var $stat = $btn.siblings('.immo-openimmo-export-status');
        $btn.prop('disabled', true);
        $stat.text('<?php echo esc_js( __( 'Exportiere…', 'immo-manager' ) ); ?>');
        $.post(ajaxurl, {
            action:  'immo_manager_openimmo_export_now',
            portal:  $btn.data('portal'),
            nonce:   $btn.data('nonce')
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
})(jQuery);
</script>
```

- [ ] **Step 4: PHP-Lint**

```bash
php -l includes/openimmo/class-admin-page.php
```

- [ ] **Step 5: Manueller Test**

1. Settings-Seite öffnen, Anbieter-Felder gefüllt, Portal aktiv.
2. 2 Demo-Properties mit Bildern, `_immo_status=available`, Opt-In für willhaben gesetzt.
3. Button „Jetzt exportieren (lokal, ohne Upload)" für willhaben klicken.
4. **Erwartet:** Nach 1-3 Sekunden grüner Text neben dem Button: „Export erstellt: willhaben-export-2026-04-28-…zip (2 Listings, 12.3 MB)".
5. ZIP unter `wp-content/uploads/openimmo/exports/willhaben/` öffnen → enthält `openimmo.xml` + `images/`.
6. ZIP nochmal manuell prüfen:
   ```bash
   unzip -l willhaben-export-*.zip
   xmllint --schema vendor-schemas/openimmo-1.2.7/openimmo.xsd <(unzip -p willhaben-export-*.zip openimmo.xml)
   ```
   **Erwartet:** ZIP-Listing zeigt `openimmo.xml` und `images/wp{id}-001.jpg` ff.; `xmllint` schreibt „validates" auf stderr.

- [ ] **Step 6: Negative-Tests**

- Empty-Run: Alle Opt-Ins entfernen, Button drücken. **Erwartet:** roter Text „no listings opted in", kein ZIP.
- XSD-Fehlerpfad: Anbieter-`firma` in den Settings leeren, Button drücken. **Erwartet:** roter Text „XSD invalid", `.invalid.xml` im exports-Ordner zum Debug.

- [ ] **Step 7: Commit**

```bash
git add includes/openimmo/class-admin-page.php
git commit -m "feat(openimmo): add 'export now' button with AJAX handler"
```

---

## Task 10: Akzeptanz-Checkliste

**Files:** keine

**Ziel:** Phase 1 ist nachweislich produktionsfertig (lokal — der Upload kommt in Phase 2).

- [ ] **Step 1: Vollständige Verifikation**

- [ ] PHP-Lint sauber für alle neuen Dateien (`includes/openimmo/export/*.php`)
- [ ] Plugin lädt ohne Fatal nach Reaktivierung
- [ ] DB-Migration v1.4.0 läuft, Spalten in `wp_immo_units` vorhanden
- [ ] Property-Edit-Seite zeigt Metabox „OpenImmo-Export"
- [ ] Opt-In-Häkchen persistieren in `wp_postmeta`
- [ ] Settings-Seite zeigt Anbieter-Block pro Portal, Werte persistieren
- [ ] „Jetzt exportieren" auf Settings-Seite funktioniert pro Portal
- [ ] Erfolgs-Notice zeigt ZIP-Pfad, Anzahl Listings, Größe
- [ ] ZIP enthält `openimmo.xml` + `images/` mit korrektem Naming
- [ ] `xmllint --schema` validiert das XML gegen die offline-XSD
- [ ] Bilder im ZIP sind ≤1920px breit, JPEG (`file` zeigt JPEG image data)
- [ ] Cron-Hook `immo_manager_openimmo_daily_sync` triggert echten Export (nicht mehr Stub)
- [ ] SyncLog-Einträge mit `direction='export'`, `status='success'`/`'partial'`/`'skipped'`/`'error'`
- [ ] Bei `status='error'` (XSD invalid): `*.invalid.xml` im exports-Ordner abgelegt, kein ZIP geschrieben
- [ ] Empty-Run: leere Listings-Liste → SyncLog `'skipped'`, keine ZIP
- [ ] Plugin-Deaktivierung räumt den Cron-Hook auf (aus Phase 0 unverändert)
- [ ] Keine PHP-Notices/Warnings mit `WP_DEBUG=true` während eines Exports

- [ ] **Step 2: Memory-Update**

In `~/.claude/projects/.../memory/project_openimmo.md` Phase-1-Status auf „erledigt" setzen, Hinweis auf Phase 2 (SFTP-Upload) ergänzen.

- [ ] **Step 3: Bereitschaftsmeldung**

Ergebnis an User: „Phase 1 fertig. Bereit für Phase 2 (SFTP-Upload)? Erst nach OK weiter."

---

## Self-Review (vom Plan-Autor durchgeführt)

**Spec-Coverage-Check:**

| Spec-Section | Abgedeckt in |
|---|---|
| Architektur (Klassen-Tabelle) | Tasks 3-8 (alle Klassen) |
| Anforderungen 1-7 | Task 1 (Filter, Opt-In), Task 2 (Anbieter), Task 3 (Collector), Task 5 (Mapper), Task 6 (Bilder), Task 8 (Trigger), Task 4 (XSD) |
| Field-Mapping (große Tabelle) | Task 5 (Mapper-Methoden) |
| Settings-Erweiterung Anbieter | Task 2 |
| Filter & Datensammlung | Task 1 (Meta+DB), Task 3 (Collector) |
| Bilder, ZIP, Storage | Task 6 (ImageProcessor), Task 7 (ZipPackager), Task 8 (Pfad-Logik in ExportService) |
| XSD-Validierung & Fehlerbehandlung | Task 4 (Validator), Task 8 (Fehlerpfade in ExportService) |
| Manuelle Verifikation | Task 8 Step 5, Task 9 Steps 5+6, Task 10 |
| Out-of-Scope-Liste | nicht im Plan implementiert (korrekt) |

**Placeholder-Scan:** Keine TBD/TODO/„implement later". Alle Code-Blöcke vollständig. Verifikations-Schritte mit erwarteten Ergebnissen.

**Type-Konsistenz:**
- `ListingDTO::external_id` (string) wird in `Mapper::build_verwaltung_techn` und `ImageProcessor::resize_attached` konsistent verwendet.
- `ExportService::run` Rückgabe-Shape (`status,summary,zip_path,count`) ist in Task 9 (`ajax_export_now`) konsistent gelesen.
- Mapper-Konstanten (`TYPE_MAP`, `FEATURE_MAP`) sind selbstkonsistent (jede TYPE_MAP-Zeile hat 2 Elemente, jede FEATURE_MAP einen Slug→Tag).
- `XsdValidator::validate` Rückgabe (`{valid:bool, errors:array}`) wird in `ExportService` korrekt geprüft.

**Bekannte Vereinfachungen (im Plan dokumentiert, nicht „Bug"):**
- Units mappen `objektart` hardcoded auf `wohnung` (Phase 6 verfeinert).
- Units holen Geo/Kontakt vom Parent-Project (per `from_unit`).
- `nutzungsart` ist hardcoded auf `WOHNEN=true` (Phase 6 für Gewerbe).
- `_immo_openimmo_immoscout24` (Meta) vs. `immoscout24_at` (Settings-Key) per `str_replace`-Heuristik (Phase 6 ersetzt durch explizites Mapping).

Plan ist konsistent.
