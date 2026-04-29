# OpenImmo Phase 1 – Export-Mapping (Design)

**Datum:** 2026-04-28
**Branch:** `feat/openimmo`
**Phase:** 1 von 7 (Foundation steht aus Phase 0)
**Status:** Spec — Implementierungs-Plan folgt nach Approval

---

## Ziel

Plugin-interne Listings (Properties + Units aus Projects) in einen gültigen OpenImmo-1.2.7-Export überführen: pro Portal ein eigenes ZIP mit `openimmo.xml` und resizten Bildern, lokal abgelegt, gegen die offline-XSD validiert. Trigger: täglicher Cron + manueller Backend-Button. Noch **kein** SFTP-Upload — der kommt in Phase 2.

## Anforderungen (User-Entscheidungen aus Brainstorming)

| # | Punkt | Wahl |
|---|---|---|
| 1 | Datensatz-Scope | Properties + Units aus Projects (Units werden als eigene `<immobilie>`-Listings exportiert) |
| 2 | Filter | Status `available` UND Per-Portal-Opt-In-Checkbox pro Listing |
| 3 | Sync-Modus | Komplettsync, jedes Listing trägt `aktionart="REFRESH"` |
| 4 | ZIP-Strategie | Ein ZIP pro Portal (willhaben + immoscout24_at) |
| 5 | Bilder | Resize auf max. 1920px Breite, JPEG Q85, kein Anzahl-Limit |
| 6 | Trigger | Daily-Cron (existiert) + Button „Jetzt exportieren" auf Settings-Seite |
| 7 | XSD | Lokal im Repo committed (`vendor-schemas/openimmo-1.2.7/`), offline-Validierung |

## Architektur & Datenfluss

```
[Trigger]
 ├─ Cron-Hook (täglich, existiert seit Phase 0)
 └─ Settings-Button "Jetzt exportieren" (AJAX)
       │
       ▼
[ExportService::run( $portal_key )]   <-- Orchestrator
       │
       ▼
1. Collector::gather( $portal_key )
       → ListingDTO[]   (Properties + Units, gefiltert)
       │
       ▼
2. XmlBuilder::open( $anbieter_settings )
       → DOMDocument <openimmo><uebertragung><anbieter>...
       │
   Foreach Listing:
       ├─ ImageProcessor::resize_attached( $listing )   → temp-Pfade
       └─ Mapper::to_immobilie( $listing )              → DOMElement <immobilie>
                                                           (mit aktionart="REFRESH")
       │
       ▼
3. XsdValidator::validate( $xml, "vendor-schemas/openimmo-1.2.7/openimmo.xsd" )
       ├─ ok    → weiter
       └─ fail  → SyncLog::finish('error', ...) + abbrechen, .invalid.xml ablegen
       │
       ▼
4. ZipPackager::write( $xml, $images,
       "wp-content/uploads/openimmo/exports/{portal_key}/{portal_key}-export-{Y-m-d}-{His}.zip" )
       │
       ▼
5. SyncLog::finish('success'|'partial', $summary, $details, $count)
       │
       ▼
   Notice an Admin (nur bei Button-Trigger)
```

### Klassen unter `includes/openimmo/export/`

| Klasse | Verantwortung | Public API |
|---|---|---|
| `ExportService` | Orchestrator | `run( string $portal_key ): array` |
| `Collector` | Filter & Sammeln | `gather( string $portal_key ): ListingDTO[]` |
| `Mapper` | 1 Listing → `<immobilie>` | `to_immobilie( ListingDTO ): DOMElement` |
| `XmlBuilder` | Wrapper-XML aufbauen | `open( array $anbieter ): DOMDocument` / `append_immobilie( DOMElement )` |
| `ImageProcessor` | Resize 1920px JPEG Q85 | `resize_attached( ListingDTO ): array` (relpath+tmppath) |
| `ZipPackager` | XML + Bilder packen | `write( DOMDocument, array $images, string $target_path ): bool` |
| `XsdValidator` | Schema-Validation | `validate( DOMDocument, string $xsd_path ): array` |
| `ListingDTO` | Werte-Objekt | `post_id`, `source` ('property'\|'unit'), `parent_id`, gebündelte Meta-Felder |

### Trigger-Implementierung

- **Cron:** `CronScheduler::run_daily_sync()` ersetzt Phase-0-Stub durch:
  ```php
  if ( ! $settings['enabled'] ) return;
  $service = new ExportService();
  foreach ( $settings['portals'] as $key => $portal ) {
      if ( ! empty( $portal['enabled'] ) ) {
          $service->run( $key );
      }
  }
  ```
- **Button:** AJAX-Endpoint `immo_manager_openimmo_export_now` → ruft `ExportService::run( $portal_key )` synchron auf, Response mit Pfad+Größe+Listing-Count.
- **Kein Lock-Mechanismus in Phase 1** — kommt in Phase 2 mit dem SFTP-Upload.

## XML-Struktur (OpenImmo 1.2.7)

```xml
<openimmo xmlns="http://www.openimmo.de" version="1.2.7">
  <uebertragung sendersoftware="immo-manager"
                senderversion="{plugin_version}"
                art="OFFLINE"
                modus="CHANGE"
                timestamp="{ISO-8601}"
                regi_id="..."
                techn_email="..."/>
  <anbieter>
    <anbieternr>...</anbieternr>
    <firma>...</firma>
    <openimmo_anid>...</openimmo_anid>
    <lizenzkennung>...</lizenzkennung>
    <impressum>...</impressum>
    <immobilie>...</immobilie>
    <immobilie>...</immobilie>
  </anbieter>
</openimmo>
```

### Field-Mapping (Plugin → OpenImmo)

| Plugin-Meta | OpenImmo-Knoten |
|---|---|
| `post_title` | `freitexte/objekttitel` |
| `post_content` | `freitexte/objektbeschreibung` |
| `_immo_mode` | `objektkategorie/vermarktungsart` (`KAUF` / `MIETE_PACHT`) |
| `_immo_property_type` | `objektkategorie/objektart/*` (haus/wohnung/...) |
| `_immo_address`, `postal_code`, `city`, `region_state`, `country`, `lat`, `lng` | `geo/strasse, plz, ort, bundesland, land, geokoordinaten` |
| `_immo_area`, `usable_area`, `land_area` | `flaechen/wohnflaeche, nutzflaeche, grundstuecksflaeche` |
| `_immo_rooms`, `bedrooms`, `bathrooms` | `flaechen/anzahl_zimmer, anzahl_schlafzimmer, anzahl_badezimmer` |
| `_immo_floor`, `total_floors` | `geo/etage, anzahl_etagen` |
| `_immo_built_year`, `renovation_year` | `zustand_angaben/baujahr, letztemodernisierung` |
| `_immo_energy_class` | `zustand_angaben/energiepass/epart` |
| `_immo_energy_hwb` | `zustand_angaben/energiepass/hwbwert` |
| `_immo_energy_fgee` | `zustand_angaben/energiepass/fgeewert` |
| `_immo_heating` | `ausstattung/heizungsart` |
| `_immo_price` | `preise/kaufpreis` (nur bei `_immo_mode = sale\|both`) |
| `_immo_rent` | `preise/kaltmiete` (nur bei `_immo_mode = rent\|both`) |
| `_immo_operating_costs` | `preise/nebenkosten` |
| `_immo_deposit` | `preise/kaution` |
| `_immo_commission` | `preise/aussen_courtage` |
| `_immo_available_from` | `verwaltung_objekt/verfuegbar_ab` |
| `_immo_features[]` | `ausstattung/*` (Boolean-Knoten via Feature-Mapping-Tabelle) |
| `_immo_custom_features` | `freitexte/sonstige_angaben` |
| `_immo_contact_name`, `email`, `phone` | `kontaktperson/anrede+vorname+name, email_zentrale, tel_zentrale` |
| Post-ID | `verwaltung_techn/objektnr_intern` (Format: `wp-{post_id}` für Property, `unit-{unit_id}` für Unit) |
| (statisch) | `verwaltung_techn/aktion aktionart="REFRESH"` |
| Featured Image | `anhaenge/anhang gruppe="TITELBILD"` |
| Galerie-Bilder | `anhaenge/anhang gruppe="BILD"` |

**Feature-Mapping-Tabelle** wird im `Mapper` als statisches Array gepflegt — Plugin-Slug → OpenImmo-Boolean-Knoten (z.B. `'balkon' => 'balkon_terrasse'`, `'garage' => 'garage'`).

## Settings-Erweiterung: Anbieter-Block

Phase 0 hat nur SFTP-Settings. Phase 1 ergänzt pro Portal einen Anbieter-Block (`Settings::portal_defaults()` + Admin-Page-UI):

- `anbieternr` (frei wählbar, intern)
- `firma` (Name des Maklers/Unternehmens)
- `openimmo_anid` (eindeutige ID, vom Portal vergeben)
- `lizenzkennung` (vom Portal vergeben)
- `impressum`
- `email_direkt`
- `tel_zentrale`
- `regi_id` (für `<uebertragung>`)
- `techn_email` (für `<uebertragung>`)

## Filter & Datensammlung

```sql
-- Properties
SELECT post_id WHERE
   post_type = 'property'
   AND post_status = 'publish'
   AND _immo_status = 'available'
   AND _immo_openimmo_<portal_key> = '1'

-- Units aus Projects
SELECT u.id FROM wp_immo_units u
JOIN wp_posts p ON p.ID = u.project_id
WHERE p.post_type = 'project'
   AND p.post_status = 'publish'
   AND u.status = 'available'
   AND u.openimmo_<portal_key> = 1
```

### Neue Meta-Felder (Property)

In `class-meta-fields.php`:
- `_immo_openimmo_willhaben` (boolean, default `0`)
- `_immo_openimmo_immoscout24` (boolean, default `0`)

### DB-Migration v1.4.0

In `wp_immo_units` zwei neue Spalten:
- `openimmo_willhaben TINYINT(1) NOT NULL DEFAULT 0`
- `openimmo_immoscout24 TINYINT(1) NOT NULL DEFAULT 0`

### Metabox-Erweiterung

- Property-Edit-Seite: neuer Block „OpenImmo-Export" in `class-metaboxes.php` mit zwei Checkboxen.
- Units-Bearbeitung im Project-Backend: zwei zusätzliche Checkboxen-Spalten (Implementierungsdetail je nach existierender Units-UI).

## Bilder, ZIP-Aufbau, Storage

### ImageProcessor

1. Anhänge sammeln:
   - Featured Image (Thumbnail-ID) → `gruppe="TITELBILD"`
   - Properties: WP-Galerie-IDs aus Galerie-Shortcode oder zugeordnete Anhänge → `gruppe="BILD"`
   - Units: IDs aus existierender `units.gallery`-Spalte → `gruppe="BILD"`
2. Pro Bild:
   ```php
   $editor = wp_get_image_editor( $original_path );
   $editor->resize( 1920, null, false );  // proportional, nur falls breiter
   $editor->set_quality( 85 );
   $editor->save( $tmp_path, 'image/jpeg' );
   ```
3. Rückgabe: `[ ['relpath' => 'images/wp123-001.jpg', 'tmppath' => '/path/to/tmp.jpg', 'gruppe' => 'TITELBILD'], ... ]`

### Datei-Layout in der ZIP

```
{portal_key}-export-2026-04-28-030005.zip
├── openimmo.xml
└── images/
    ├── wp123-001.jpg   ← Titelbild Listing 123
    ├── wp123-002.jpg
    ├── wp145-001.jpg
    └── ...
```

XML-Referenz pro Bild:
```xml
<anhang location="EXTERN" gruppe="TITELBILD">
  <anhangtitel>Hauptansicht</anhangtitel>
  <format>jpg</format>
  <daten><pfad>images/wp123-001.jpg</pfad></daten>
</anhang>
```

### Storage

- Basis-Pfad: `wp-content/uploads/openimmo/exports/{portal_key}/`
- Beim ersten Lauf erstellt (`.htaccess` mit `Deny from all`).
- Datei-Pattern: `{portal_key}-export-{Y-m-d}-{His}.zip`
- **Kein Cleanup in Phase 1** — alte Exporte bleiben liegen, Retention kommt in Phase 7.

### Temp-Verzeichnis

`wp-content/uploads/openimmo/tmp/{run_id}/` für resizte Bilder zwischen Resize und ZIP-Pack. Wird im `try/finally` aufgeräumt — auch im Fehlerfall.

## XSD-Validierung & Fehlerbehandlung

### XSD im Repo

- Pfad: `vendor-schemas/openimmo-1.2.7/openimmo.xsd` (+ Sub-Schemas falls die Spec sie verteilt).
- README mit Quelle openimmo.de und Versions-Datum.
- User lädt einmalig herunter, danach committed.

### XsdValidator-Logik

```php
libxml_use_internal_errors( true );
$ok = $dom->schemaValidate( $xsd_path );
if ( ! $ok ) {
    $errors = libxml_get_errors();
    libxml_clear_errors();
    return [ 'valid' => false, 'errors' => $errors ];
}
return [ 'valid' => true ];
```

### Fehlermatrix

| Stufe | Fehlertyp | Aktion |
|---|---|---|
| Collector | leere Liste | Skip Portal, `SyncLog::finish('skipped','no listings')` |
| Mapper | einzelnes Listing wirft Exception | Listing überspringen, weiter; `details.errors[]` mit post_id+message |
| ImageProcessor | Bild nicht ladbar / Resize-Fehler | Bild überspringen, `details.image_errors[]` |
| XsdValidator | invalid | **Hard-Stop pro Portal:** ZIP NICHT schreiben, `SyncLog::finish('error','XSD invalid', errors)`, `*.invalid.xml` zum Debug ablegen |
| ZipPackager | I/O-Error | Hard-Stop, SyncLog 'error', temp aufräumen |
| Top-Level Exception | catch-all | `SyncLog::finish('error', $e->getMessage())`, kein PHP-Fatal an WP weiter |

### SyncLog-Eintrag pro Lauf

- `portal`: `'willhaben'` / `'immoscout24_at'`
- `direction`: `'export'`
- `status`: `'success'` / `'partial'` / `'error'` / `'skipped'`
- `summary`: kurz lesbar, z.B. „45/47 Listings exportiert, 2 übersprungen"
- `details` (JSON): `errors[]`, `image_errors[]`, `zip_path`, `xml_size_kb`
- `properties_count`: erfolgreich exportierte

### Admin-Notice (nur Button-Trigger)

- Erfolg: grün „Export erstellt: `…/willhaben-export-2026-04-28-030005.zip` (45 Listings, 12.3 MB)"
- Fehler: rot mit Link/Hinweis zum SyncLog-Eintrag

## Manuelle Verifikation

| Test | Vorgehen | Erwartet |
|---|---|---|
| PHP-Lint | `php -l` auf alle neuen Dateien | „No syntax errors" |
| Plugin lädt | Deaktivieren/Reaktivieren | Kein Fatal, DB-Migration v1.4.0 läuft |
| Meta-Felder | Property-Edit-Seite öffnen | Metabox „OpenImmo-Export" mit 2 Checkboxen |
| Anbieter-Settings | OpenImmo-Settings-Seite | Pro Portal: Anbieter-Felder persistieren |
| Empty-Run | Button drücken ohne Opt-In-Listings | SyncLog 'skipped', kein ZIP, Notice „Keine Listings freigegeben" |
| Happy-Path | 2 Demo-Properties anhaken (sale, available, mit Bildern), Button drücken | ZIP entsteht, enthält `openimmo.xml` + `images/`, XSD-validiert |
| Manuelle XSD-Prüfung | `unzip` und `xmllint --schema openimmo.xsd openimmo.xml` | Validation passes |
| Resize-Check | Original 4000×3000 hochladen, exportieren, ZIP-Bild prüfen | 1920×1440 JPEG ~Q85 |
| Image-Fehler | 1 Bild manuell aus uploads/ löschen, exportieren | 'partial' im SyncLog, Listing ohne dieses Bild |
| XSD-Fehler-Pfad | Anbieter-firma in Settings leeren, exportieren | 'error' im SyncLog, kein ZIP, `.invalid.xml` zum Debug |
| Cron-Lauf | `wp cron event run immo_manager_openimmo_daily_sync` mit aktiven Portalen | ZIP je aktivem Portal, je SyncLog-Eintrag |

## Out-of-Scope für Phase 1

- ❌ SFTP-Upload — Phase 2
- ❌ Import / Konflikt-Queue — Phase 3-5
- ❌ Portal-spezifische Felder, Adapter-Pattern — Phase 6
- ❌ Retention/Cleanup alter ZIPs — Phase 7
- ❌ Lock-Mechanismus für parallele Cron-Läufe — Phase 2
- ❌ E-Mail-Notifications bei Sync-Fehlern — Phase 7
- ❌ Bilder-Diff (nur veränderte hochladen) — bewusst Komplettsync

## Datei-Übersicht

| Aktion | Datei |
|---|---|
| Create | `vendor-schemas/openimmo-1.2.7/openimmo.xsd` (+ ggf. Sub-XSDs) |
| Create | `vendor-schemas/openimmo-1.2.7/README.md` |
| Create | `includes/openimmo/export/class-export-service.php` |
| Create | `includes/openimmo/export/class-collector.php` |
| Create | `includes/openimmo/export/class-mapper.php` |
| Create | `includes/openimmo/export/class-xml-builder.php` |
| Create | `includes/openimmo/export/class-image-processor.php` |
| Create | `includes/openimmo/export/class-zip-packager.php` |
| Create | `includes/openimmo/export/class-xsd-validator.php` |
| Create | `includes/openimmo/export/class-listing-dto.php` |
| Modify | `includes/class-meta-fields.php` (2 neue Property-Felder) |
| Modify | `includes/class-database.php` (DB v1.4.0, +2 Spalten in `units`) |
| Modify | `includes/openimmo/class-settings.php` (Anbieter-Defaults pro Portal) |
| Modify | `includes/openimmo/class-admin-page.php` (Anbieter-Felder + Button „Jetzt exportieren") |
| Modify | `includes/openimmo/class-cron-scheduler.php` (Stub durch echten Aufruf ersetzen) |
| Modify | `includes/class-metaboxes.php` (Metabox „OpenImmo-Export" für Property) |
| Modify | `includes/class-plugin.php` (Lazy Getter für ExportService) |

## Phasen-Kontext

Diese Spec ist die Grundlage für `docs/superpowers/plans/2026-04-28-openimmo-phase-1.md`. Nach User-Approval folgt der detaillierte Task-by-Task-Plan via `superpowers:writing-plans`.

Phasen-Übersicht (siehe `memory/project_openimmo.md`):
- ✅ Phase 0 — Foundation
- 👉 **Phase 1 — Export-Mapping (diese Spec)**
- ⬜ Phase 2 — SFTP-Upload
- ⬜ Phase 3 — Import-Parsing
- ⬜ Phase 4 — Import-Transport
- ⬜ Phase 5 — Konflikt-Behandlung
- ⬜ Phase 6 — Portal-Anpassungen / Adapter-Pattern
- ⬜ Phase 7 — Polish (Retention, E-Mail-Notifications, README)
