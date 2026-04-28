# OpenImmo Phase 3 – Import-Parsing (Design)

**Datum:** 2026-04-28
**Branch:** `feat/openimmo`
**Phase:** 3 von 7 (Phase 0/1/2 abgeschlossen)
**Status:** Spec — Implementierungs-Plan folgt nach Approval

---

## Ziel

Eingehende OpenImmo-ZIPs entpacken, das XML parsen, Bilder mit Hash-Dedup in die WP Media Library importieren und für jedes `<immobilie>`-Listing entweder eine neue Property anlegen ODER (wenn ein existierendes Listing erkannt wird) einen Eintrag in der `wp_immo_conflicts`-Queue ablegen. Trigger in Phase 3: ein File-Upload-Button auf der Settings-Seite (Phase 4 erweitert um SFTP-Pull-Cron).

## Anforderungen (User-Entscheidungen aus Brainstorming)

| # | Punkt | Wahl |
|---|---|---|
| 1 | Scope | Parser + neue Properties direkt anlegen, Updates → Conflict-Queue |
| 2 | ID-Erkennung | Neues Meta `_immo_openimmo_external_id`, `wp-`/`unit-`-Boomerang-Match |
| 3 | Bilder | Sofort in Media Library importieren, Hash-Dedup via `_immo_openimmo_image_hash`-Postmeta |
| 4 | Update-Verhalten | Incoming wins where present (fehlende Felder bleiben unverändert) |
| 5 | Mapping-Logik | Eigene `ImportMapper`-Klasse, TYPE_MAP/FEATURE_MAP aus Phase-1-Mapper als `public const` exposen |
| 6 | Trigger | File-Upload-Button auf Settings-Seite (Phase 4 erweitert um SFTP-Pull) |

## Architektur & Datenfluss

```
[Trigger]
 └─ Settings-Button "ZIP-Datei importieren" (File-Upload, AJAX)
       │
       ▼
[ImportService::run( $zip_path )]
       │
       ▼
1. ZipExtractor::extract( $zip_path ) → { xml_path, images_dir, tmp_base }
       │
       ▼
2. DOMDocument::load( $xml_path ), iteriere <immobilie>-Elemente
       │
   Foreach <immobilie>:
       ├─ ImportMapper::from_immobilie() → ImportListingDTO
       ├─ ImageImporter::import_all( $dto->raw_images, $images_dir ) → Attachment-IDs (mit Hash-Dedup)
       ├─ ConflictDetector::detect( $dto ) → 'new' | 'existing_property' | 'existing_unit'
       └─ Routing:
             ├─ 'new'      → ImportService::create_property( $dto, $attachments )
             └─ 'existing' → ImportService::record_conflict( $post_id, $dto, $attachments )
       │
       ▼
3. ZipExtractor::cleanup() im finally
       │
       ▼
4. SyncLog::finish( direction='import', counts={new, conflicts, skipped, errors} )
```

### Klassen unter `includes/openimmo/import/`

| Klasse | Verantwortung | Public API |
|---|---|---|
| `ZipExtractor` | ZIP entpacken | `extract( $zip_path ): array{xml_path, images_dir, tmp_base}` / `cleanup(): void` |
| `ImportListingDTO` | Werte-Objekt | Public Props: `external_id`, `title`, `description`, `meta`, `raw_images` |
| `ImportMapper` | XML → DTO | `from_immobilie( DOMElement ): ImportListingDTO` |
| `ImageImporter` | Bild → Media Library | `import_all( $raw_images, $images_dir ): array` (Attachment-IDs mit gruppe + hash) |
| `ConflictDetector` | „neu" vs. „existing" | `detect( ImportListingDTO ): array{kind, post_id?, unit_id?}` |
| `ImportService` | Orchestrator | `run( string $zip_path ): array{status, summary, counts, sync_id}` |

### Refactor an Phase 1

`includes/openimmo/export/class-mapper.php`:
- `TYPE_MAP` und `FEATURE_MAP` von `private const` → `public const`. Wird vom `ImportMapper` für inverse Lookups konsumiert.

### Erweiterungen außerhalb der Import-Klassen

- `includes/class-meta-fields.php` — neues Property-Meta `_immo_openimmo_external_id` (string, default `''`).
- `includes/class-database.php` — DB v1.5.0, +1 Spalte `external_id VARCHAR(100) NULL` in `wp_immo_units`.
- Phase-3-spezifisches Postmeta auf Attachments: `_immo_openimmo_image_hash` (sha1).
- `includes/openimmo/class-admin-page.php` — File-Upload-Block + AJAX-Handler + JS.
- `includes/class-plugin.php` — Lazy-Getter `get_openimmo_import_service()`.

## Klassen-Schnittstellen

### `ImportListingDTO`

```php
namespace ImmoManager\OpenImmo\Import;

class ImportListingDTO {
    /** @var string aus <verwaltung_techn><objektnr_intern> */
    public string $external_id = '';

    /** @var string aus <freitexte><objekttitel> */
    public string $title = '';

    /** @var string aus <freitexte><objektbeschreibung> */
    public string $description = '';

    /**
     * Plugin-Meta-Felder (`_immo_*` → Wert).
     * @var array<string,mixed>
     */
    public array $meta = array();

    /**
     * Roh-Bild-Verweise aus dem XML.
     * @var array<int,array{relpath:string, gruppe:string}>
     */
    public array $raw_images = array();
}
```

### `ZipExtractor`

```php
class ZipExtractor {
    private string $tmp_base = '';

    /**
     * @return array{xml_path: string, images_dir: string, tmp_base: string}
     * @throws \RuntimeException wenn ZIP nicht entpackbar oder openimmo.xml fehlt
     */
    public function extract( string $zip_path ): array;

    public function cleanup(): void;
}
```

Implementation:
- temp-Dir: `wp-content/uploads/openimmo/import-tmp/<basename>-<timestamp>/`
- `ZipArchive::open()` + `extractTo()`.
- Sucht `openimmo.xml` im Root des entpackten Verzeichnisses (oder erstem Unterordner).
- `images_dir` = `<tmp_base>/images/` (kann leer sein wenn keine Bilder).
- `cleanup()` löscht `tmp_base` rekursiv (best-effort).

### `ImportMapper`

```php
class ImportMapper {
    public function from_immobilie( \DOMElement $immobilie ): ImportListingDTO;

    private function read_text( \DOMElement $parent, string $tag ): string;
    private function read_objektart( \DOMElement $immobilie ): string;     // invertiert TYPE_MAP
    private function read_vermarktungsart( \DOMElement $immobilie ): string;  // 'sale'|'rent'|'both'
    private function read_features( \DOMElement $immobilie ): array;       // invertiert FEATURE_MAP
    private function read_anhaenge( \DOMElement $immobilie ): array;       // [{relpath, gruppe}]
}
```

`read_objektart`: Iteriert über `Mapper::TYPE_MAP`, vergleicht das tatsächliche XML-Tag und ggf. das `*typ`-Attribut. Bei Match → Plugin-Slug. Fallback `'wohnung'`.

`read_vermarktungsart`: Liest `<vermarktungsart>`-Attribute `KAUF` und `MIETE_PACHT`. Wenn beide `'true'` → `'both'`, nur KAUF → `'sale'`, nur MIETE_PACHT → `'rent'`. Fallback `'sale'`.

`read_features`: Iteriert über alle Children von `<ausstattung>`. Für jeden gefundenen Tag-Namen Reverse-Lookup gegen `Mapper::FEATURE_MAP`. Bei Mehrdeutigkeit (z.B. `balkon_terrasse` → `balkon`/`terrasse`): den ersten Treffer im Map-Array nehmen.

### `ImageImporter`

```php
class ImageImporter {
    public const HASH_META_KEY = '_immo_openimmo_image_hash';

    /**
     * @param array<int,array{relpath:string, gruppe:string}> $raw_images
     * @return array<int,array{att_id:int, gruppe:string, hash:string}>
     */
    public function import_all( array $raw_images, string $images_dir ): array;

    private function import_one( string $local_path, string $filename ): ?array;
    private function find_by_hash( string $hash ): ?int;
}
```

Flow pro Bild:
1. Pfad: `$images_dir . '/' . basename($relpath)`.
2. `sha1_file()` für Hash.
3. `find_by_hash($hash)` — `WP_Query` über `attachment` mit `meta_query._immo_openimmo_image_hash = $hash`. Match → bestehende att_id.
4. Sonst: WP `wp_handle_sideload()` mit `test_form=false` und `test_size=true`. Anschließend `wp_insert_attachment()` + `wp_generate_attachment_metadata()`.
5. `update_post_meta( $att_id, '_immo_openimmo_image_hash', $hash )`.
6. Return `{att_id, hash}` (Caller setzt `gruppe` wieder drauf).

### `ConflictDetector`

```php
class ConflictDetector {
    /**
     * @return array{kind: string, post_id?: int, unit_id?: int}
     *         kind = 'new' | 'existing_property' | 'existing_unit'
     */
    public function detect( ImportListingDTO $dto ): array;
}
```

Reihenfolge:
1. Match `^wp-(\d+)$` → `get_post()` + `post_type === 'immo_mgr_property'`. Hit → `existing_property`.
2. Match `^unit-(\d+)$` → `Units::get( $id )`. Hit → `existing_unit`.
3. Sonst: `WP_Query` nach `_immo_openimmo_external_id = $dto->external_id`. Hit → `existing_property`.
4. Sonst: $wpdb-Lookup auf `wp_immo_units.external_id = $dto->external_id`. Hit → `existing_unit`.
5. Sonst: `'new'`.

### `ImportService`

```php
class ImportService {
    /**
     * @return array{status:string, summary:string, counts:array{new:int, conflicts:int, skipped:int, errors:int}, sync_id:int}
     */
    public function run( string $zip_path ): array;

    private function create_property( ImportListingDTO $dto, array $attachments ): int;
    private function record_conflict( int $post_id, ImportListingDTO $dto, array $attachments ): int;

    private function collect_current_meta( int $post_id ): array;
    private function values_differ( $a, $b ): bool;
    private function finish( int $sync_id, string $status, string $summary, array $counts, array $details ): array;
}
```

`create_property( $dto, $attachments )`:
1. `wp_insert_post( ['post_type' => 'immo_mgr_property', 'post_title' => $dto->title, 'post_content' => $dto->description, 'post_status' => 'publish'] )`.
2. Foreach `$dto->meta`: `update_post_meta( $post_id, $key, $value )`.
3. `update_post_meta( $post_id, '_immo_openimmo_external_id', $dto->external_id )`.
4. Featured Image setzen aus erstem Attachment mit `gruppe='TITELBILD'` via `set_post_thumbnail()`.
5. Galerie-Attachments: `wp_update_post( ['ID' => $att_id, 'post_parent' => $post_id] )` für alle `gruppe='BILD'`-Attachments.
6. Return `$post_id`.

`record_conflict( $post_id, $dto, $attachments )`:
1. `$current = $this->collect_current_meta( $post_id )`.
2. Diff-Loop über `$dto->meta` mit Skip-Logik für leere/abwesende Werte (siehe Diff-Sektion).
3. Wenn `$changed` leer → return 0 (skipped — keine echte Diff).
4. Attachment-IDs in `$dto->meta['_immo_openimmo_pending_attachment_ids']` einbetten.
5. `Conflicts::add( $post_id, 'import', $dto->meta, $current, array_keys( $changed ) )`.

## Update-Verhalten & Diff-Logik

```php
foreach ( $dto->meta as $key => $incoming_value ) {
    // Frage 4: Felder, die im Incoming leer/abwesend sind, werden NICHT diffed.
    if ( '' === $incoming_value || null === $incoming_value
         || ( is_array( $incoming_value ) && empty( $incoming_value ) ) ) {
        continue;
    }

    $current_value = $current[ $key ] ?? '';
    if ( $this->values_differ( $current_value, $incoming_value ) ) {
        $changed[ $key ] = array(
            'current'  => $current_value,
            'incoming' => $incoming_value,
        );
    }
}
```

`values_differ` mit Toleranz:
- Numerisch: `abs( (float)$a - (float)$b ) > 0.001`.
- Arrays (Features-Listen): set-vergleich nach `sort()`.
- Strings: `trim( (string)$a ) !== trim( (string)$b )`.

### Was wird in `wp_immo_conflicts` gespeichert

- `portal` = `'import'` (Phase 6 könnte den Sender aus dem XML extrahieren)
- `source_data` = `json_encode( $dto->meta + pending_attachment_ids )`
- `local_data` = `json_encode( $current )`
- `conflict_fields` = `json_encode( array_keys( $changed ) )`
- `status` = `'pending'`

### Bilder bei Konflikt

- Werden trotzdem in Media Library importiert (Hash-Dedup verhindert echte Duplikate).
- Attachment-IDs in `_immo_openimmo_pending_attachment_ids` (JSON-Array) im `source_data` zum späteren Verknüpfen durch Phase 5.
- Bei Reject: keine sofortige Cleanup-Logik — Phase 7 räumt verwaiste Attachments auf.

## Trigger / AdminPage-Erweiterung

### Settings-Seite

Außerhalb der Portal-`foreach`, vor `submit_button()`:

```php
<h2>OpenImmo-Import (Test)</h2>
<p class="description">ZIP-Datei mit openimmo.xml + images/ hier hochladen. Neue Listings werden direkt angelegt, bestehende landen in der Konflikt-Queue.</p>
<p>
    <input type="file" id="immo-openimmo-import-zip" accept=".zip">
    <button type="button" class="button button-primary immo-openimmo-import-now"
            data-nonce="<?php echo esc_attr( wp_create_nonce( 'immo_manager_openimmo_import_zip' ) ); ?>">
        ZIP importieren
    </button>
    <span class="immo-openimmo-import-status" style="margin-left:10px;"></span>
</p>
```

### AJAX-Handler `ajax_import_zip`

Sicherheit:
- Capability `manage_options`
- Nonce `immo_manager_openimmo_import_zip`
- `is_uploaded_file()` + `move_uploaded_file()` für TOCTOU
- `.zip`-Extension-Check
- 400 bei fehlender Datei
- Stage-Pfad: `wp-content/uploads/openimmo/import-staging/<timestamp>-<filename>`
- Stage-File wird nach `ImportService::run()` gelöscht (in beiden Erfolgs- und Fehler-Pfaden)

Response:
- Erfolg: `wp_send_json_success` mit message + counts + sync_id
- Fehler: `wp_send_json_error` mit summary

### JS

`FormData` (statt `$.post`) für File-Upload:

```javascript
$(document).on('click', '.immo-openimmo-import-now', function(e){
    e.preventDefault();
    var file = $('#immo-openimmo-import-zip')[0].files[0];
    if (!file) { /* Notice */ return; }
    var fd = new FormData();
    fd.append('action', 'immo_manager_openimmo_import_zip');
    fd.append('nonce',  $btn.data('nonce'));
    fd.append('zip',    file);
    $.ajax({ url: ajaxurl, type: 'POST', data: fd, processData: false, contentType: false }).done( /* ... */ );
});
```

### Plugin-Lazy-Getter

```php
public function get_openimmo_import_service(): \ImmoManager\OpenImmo\Import\ImportService {
    if ( null === $this->openimmo_import_service ) {
        require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-settings.php';
        require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-sync-log.php';
        require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-conflicts.php';
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

## Fehlerbehandlung & SyncLog-Integration

### Fehler-Matrix

| Stufe | Fehlertyp | Aktion |
|---|---|---|
| Upload | ungültige Datei / kein .zip | AJAX 400, kein SyncLog (vor Service-Call) |
| ZipExtractor | nicht entpackbar / kein openimmo.xml | RuntimeException → ImportService catcht → SyncLog 'error' |
| XML-Load | Wellformedness-Fehler | libxml-Errors → SyncLog 'error' mit `details.libxml_errors` |
| ImportMapper | einzelnes Listing wirft | Listing skippen, counts.errors++, details.errors[] |
| ImageImporter | Bild fehlt / Hash-Fail | Bild skippen (Listing ohne dieses Bild), details.image_errors[] |
| ConflictDetector | DB-Lookup wirft | Listing als 'new' behandeln (fail-open), details.errors[] mit Hinweis |
| create_property | wp_insert_post fail | counts.errors++ |
| record_conflict | identische Werte | counts.skipped++ (kein Konflikt) |
| Top-Level | unerwartete Throwable | catch-all in ImportService, SyncLog 'error' mit Stacktrace |
| Cleanup | best-effort | error_log, kein User-Impact |

### SyncLog-Eintrag

- `portal` = `'import'`
- `direction` = `'import'`
- `status` = `'success'` / `'partial'` / `'error'`
- `summary` = `'X neu, Y Konflikte, Z übersprungen, N Fehler'`
- `details` (JSON): `counts`, `errors[]`, `image_errors[]`, `libxml_errors[]` (nur bei XML-Fehler)
- `properties_count` = Summe aller counts

## Manuelle Verifikation

| Test | Vorgehen | Erwartet |
|---|---|---|
| PHP-Lint | `php -l` auf alle neuen Dateien | „No syntax errors" |
| Plugin lädt | Reaktivierung | Kein Fatal, DB v1.5.0 läuft |
| Settings-Seite | öffnen | Block „OpenImmo-Import (Test)" sichtbar |
| Empty-ZIP | leeres ZIP hochladen | Roter Text „ZIP entpacken fehlgeschlagen: openimmo.xml fehlt" |
| Defektes XML | ZIP mit kaputtem XML | Roter Text „XML invalid: ..." |
| Happy-Path neu | ZIP mit 2 unbekannten Listings | 2 neue Properties mit `_immo_openimmo_external_id` |
| Boomerang | Phase-1-Export-ZIP nochmal importieren | 0 neu, 0 Konflikte (oder X bei Diffs), 2 übersprungen |
| Conflict-Erkennung | Existing Property `_immo_address` ändern, dann Phase-1-Export importieren | 1 Konflikt mit `conflict_fields` enthält `_immo_address` |
| Bild-Hash-Dedup | ZIP zweimal importieren | 0 neue Attachments beim 2. Mal |
| Update-Verhalten | Existing Property hat `energy_class='B'`, ZIP ohne `<energiepass>` | Energy-Class bleibt 'B' |
| Mapper-Konstanten public | Phase-1-Export funktioniert weiter | Keine Regression |
| Cleanup temp | nach Import: `import-tmp/` und `import-staging/` leer | tmp aufgeräumt |

## Out-of-Scope für Phase 3

- ❌ SFTP-Pull-Cron — Phase 4
- ❌ Approval/Reject-UI für Conflict-Queue — Phase 5
- ❌ Auto-Cleanup verwaister Attachments — Phase 7
- ❌ Portal-spezifisches Import-Verhalten — Phase 6
- ❌ Inkrementelle/Delta-Imports — Phase 7
- ❌ Größenbeschränkung beim Upload — Phase 7
- ❌ Multi-Sender-Erkennung — Phase 6
- ❌ MIME-Type-Sniffing der ZIPs — Phase 7

## Datei-Übersicht

| Aktion | Datei |
|---|---|
| Create | `includes/openimmo/import/class-import-listing-dto.php` |
| Create | `includes/openimmo/import/class-zip-extractor.php` |
| Create | `includes/openimmo/import/class-import-mapper.php` |
| Create | `includes/openimmo/import/class-image-importer.php` |
| Create | `includes/openimmo/import/class-conflict-detector.php` |
| Create | `includes/openimmo/import/class-import-service.php` |
| Modify | `includes/openimmo/export/class-mapper.php` (TYPE_MAP/FEATURE_MAP `public const`) |
| Modify | `includes/class-meta-fields.php` (+ `_immo_openimmo_external_id`) |
| Modify | `includes/class-database.php` (DB v1.5.0, +1 units-Spalte) |
| Modify | `includes/openimmo/class-admin-page.php` (Import-Block + AJAX + JS) |
| Modify | `includes/class-plugin.php` (Lazy-Getter) |

11 Dateien — 6 neu, 5 modifiziert.

## Phasen-Kontext

Spec für `docs/superpowers/plans/2026-04-28-openimmo-phase-3.md`. Plan folgt nach User-Approval via `superpowers:writing-plans`.

Phasen-Übersicht:
- ✅ Phase 0 — Foundation
- ✅ Phase 1 — Export-Mapping
- ✅ Phase 2 — SFTP-Transport
- 👉 **Phase 3 — Import-Parsing (diese Spec)**
- ⬜ Phase 4 — Import-Transport (SFTP-Pull-Cron + erweiterten manuellen Upload)
- ⬜ Phase 5 — Konflikt-Behandlung (Approval-UI)
- ⬜ Phase 6 — Portal-Anpassungen
- ⬜ Phase 7 — Polish (Retention, E-Mail, README)
