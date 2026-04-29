# OpenImmo Phase 4 – Import-Transport (SFTP-Pull-Cron) Design

**Datum:** 2026-04-29
**Branch:** `feat/openimmo`
**Phase:** 4 von 7 (Phase 0/1/2/3 abgeschlossen)
**Status:** Spec — Implementierungs-Plan folgt nach Approval

---

## Ziel

Periodisch (1×/h) das Inbox-Verzeichnis jedes aktiven Portals auf eingehende OpenImmo-ZIPs prüfen, herunterladen, durch die Phase-3-Import-Pipeline schicken und nach Erfolg/Fehler in `inbox/processed/` bzw. `inbox/error/` verschieben. Plus pro Portal ein manueller Settings-Button „Inbox jetzt prüfen".

## Anforderungen (User-Entscheidungen aus Brainstorming)

| # | Punkt | Wahl |
|---|---|---|
| 1 | Inbox-Pfad | Neues Settings-Feld `inbox_path` pro Portal |
| 2 | Pull-Frequenz | Eigener Cron-Hook `hourly_pull` (Default 1×/h) |
| 3 | Erfolgreich importierte ZIPs | Verschieben in `<inbox>/processed/` |
| 4 | Fehlerhafte ZIPs | Verschieben in `<inbox>/error/` |
| 5 | Manueller Trigger | Pro Portal Button „Inbox jetzt prüfen" |
| 6 | Test-Connection | Bestehenden Button erweitern (Push + Inbox-Stufe) |

## Architektur & Datenfluss

```
[Trigger]
 ├─ Cron-Hook 'immo_manager_openimmo_hourly_pull' (1×/h)         (Phase 4 NEU)
 └─ Settings-Button "Inbox jetzt prüfen"                          (Phase 4 NEU)
       │
       ▼
[SftpPuller::pull( $portal_key )]
       │
       ├─ Lock akquirieren (Transient `immo_oi_pull_lock_<portal>`, TTL 600s)
       │     └─ Lock vorhanden → result 'skipped'
       │
       ├─ SftpClient::connect, login
       ├─ SftpClient::mkdir_p( inbox_path ) + processed/ + error/
       │
       ├─ SftpClient::list_directory( inbox_path ) → Filter auf *.zip
       │
       ├─ Foreach ZIP:
       │     ├─ Stage-File: wp-content/uploads/openimmo/pull-staging/<portal>-<ts>/<filename>
       │     ├─ SftpClient::get( remote_zip, local_stage )
       │     ├─ ImportService::run( local_stage )
       │     ├─ unlink( local_stage )
       │     └─ SFTP-Move:
       │           ├─ success/partial → inbox/processed/<filename>
       │           └─ error           → inbox/error/<filename>
       │
       ├─ SftpClient::disconnect()
       ├─ Lock freigeben (try/finally)
       │
       └─ SyncLog mit direction='pull', counts={pulled, imported, conflicts, errors}
```

### Klassen

| Klasse | Verantwortung | Public API |
|---|---|---|
| `SftpClient` (Phase 2, **erweitert**) | low-level SFTP | + `list_directory($path): array` / `get($remote, $local): bool` / `rename($from, $to): bool` |
| `SftpPuller` (NEU) | Pull-Workflow | `pull( $portal_key ): array{status, summary, counts, sync_id}` |
| `ImportService` (Phase 3, **unverändert**) | Parser-Pipeline | `run( $zip_path )` wird pro ZIP gerufen |

### Status-Bestimmung der Pull-Operation

- `'success'` wenn `errors === 0`
- `'partial'` wenn mind. 1 ZIP erfolgreich UND mind. 1 mit Fehler
- `'error'` wenn alles error oder kein einziges ZIP erfolgreich
- `'skipped'` wenn Lock held, kein `inbox_path` konfiguriert, oder Inbox leer

### Settings-Erweiterung

`Settings::portal_defaults()` bekommt:

```php
'inbox_path' => '',
```

`AdminPage::maybe_save()` ergänzt das Sanitisieren:

```php
'inbox_path' => sanitize_text_field( $portal['inbox_path'] ?? '' ),
```

UI: ein neues Input-Feld pro Portal in der SFTP-Tabelle, direkt unter `remote_path`.

### CronScheduler-Erweiterung

Zweiter Hook `immo_manager_openimmo_hourly_pull`:

```php
public const HOOK_DAILY_SYNC  = 'immo_manager_openimmo_daily_sync';
public const HOOK_HOURLY_PULL = 'immo_manager_openimmo_hourly_pull';

public function __construct() {
    add_action( self::HOOK_DAILY_SYNC,  array( $this, 'run_daily_sync' ) );
    add_action( self::HOOK_HOURLY_PULL, array( $this, 'run_hourly_pull' ) );
}

public static function on_activation(): void {
    // existing daily_sync schedule
    if ( ! wp_next_scheduled( self::HOOK_HOURLY_PULL ) ) {
        wp_schedule_event( time() + 60, 'hourly', self::HOOK_HOURLY_PULL );
    }
}

public static function on_deactivation(): void {
    // existing daily_sync clear
    wp_clear_scheduled_hook( self::HOOK_HOURLY_PULL );
}

public function run_hourly_pull(): void {
    $settings = Settings::get();
    if ( empty( $settings['enabled'] ) ) {
        return;
    }
    $puller = \ImmoManager\Plugin::instance()->get_openimmo_sftp_puller();
    foreach ( $settings['portals'] as $portal_key => $portal ) {
        if ( ! empty( $portal['enabled'] ) && ! empty( $portal['inbox_path'] ) ) {
            $puller->pull( $portal_key );
        }
    }
}
```

**Wichtig:** Nach Phase-4-Deployment muss das Plugin einmal manuell deaktiviert + reaktiviert werden, damit der Hourly-Hook scheduled wird. Sonst zeigt `wp cron event list` ihn nicht.

## Klassen-Schnittstellen

### `SftpClient` Erweiterung

`includes/openimmo/sftp/class-sftp-client.php` bekommt 3 neue Public-Methoden:

```php
public function list_directory( string $remote_path ): array;
public function get( string $remote_path, string $local_path ): bool;
public function rename( string $from, string $to ): bool;
```

`list_directory` filtert Verzeichnisse + `.`/`..` raus, gibt nur Dateinamen zurück.
`get` lädt Remote-Datei lokal herunter.
`rename` verschiebt/Renamed Remote-Datei (für `inbox → processed/error`).

### `SftpUploader::test_connection()` Erweiterung

Nach den Push-Stufen wird zusätzlich die Inbox-Stufe geprüft (mkdir_p + list_directory). Rückgabe-Schema bekommt zusätzlich `'inbox' => bool`.

### `SftpPuller` (neu)

`includes/openimmo/sftp/class-sftp-puller.php`:

```php
namespace ImmoManager\OpenImmo\Sftp;

use ImmoManager\OpenImmo\Settings;
use ImmoManager\OpenImmo\SyncLog;

class SftpPuller {
    private const LOCK_TRANSIENT_PREFIX = 'immo_oi_pull_lock_';
    private const LOCK_TTL_SECONDS      = 600;

    /**
     * @return array{
     *   status: string,
     *   summary: string,
     *   counts: array{pulled:int, imported:int, conflicts:int, errors:int},
     *   sync_id: int
     * }
     */
    public function pull( string $portal_key ): array;

    private function acquire_lock( string $portal_key ): bool;
    private function release_lock( string $portal_key ): void;
    private function rrmdir( string $dir ): void;
    private function finish( int $sync_id, string $status, string $summary, array $counts, array $errors ): array;
}
```

### Plugin-Singleton-Erweiterung

```php
private $openimmo_sftp_puller = null;

public function get_openimmo_sftp_puller(): \ImmoManager\OpenImmo\Sftp\SftpPuller {
    if ( null === $this->openimmo_sftp_puller ) {
        require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-settings.php';
        require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-sync-log.php';
        require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/sftp/class-sftp-client.php';
        require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/sftp/class-sftp-puller.php';
        $this->openimmo_sftp_puller = new \ImmoManager\OpenImmo\Sftp\SftpPuller();
    }
    return $this->openimmo_sftp_puller;
}
```

## AdminPage-UI

### Neues Settings-Feld in der SFTP-Tabelle

```php
<tr>
    <th><?php esc_html_e( 'Inbox-Pfad (für Pull)', 'immo-manager' ); ?></th>
    <td>
        <input type="text" class="regular-text"
               name="portals[<?php echo esc_attr( $key ); ?>][inbox_path]"
               value="<?php echo esc_attr( $portal['inbox_path'] ?? '' ); ?>"
               placeholder="/inbox/">
        <p class="description">
            <?php esc_html_e( 'Leer lassen = kein Pull. Cron prüft dieses Verzeichnis stündlich.', 'immo-manager' ); ?>
        </p>
    </td>
</tr>
```

### Neuer Button „Inbox jetzt prüfen"

Im SFTP-Button-Block pro Portal (neben Test-Connection und Upload-Now):

```php
<button type="button"
        class="button button-secondary immo-openimmo-pull-now"
        data-portal="<?php echo esc_attr( $key ); ?>"
        data-nonce="<?php echo esc_attr( wp_create_nonce( 'immo_manager_openimmo_pull_now' ) ); ?>">
    <?php esc_html_e( 'Inbox jetzt prüfen', 'immo-manager' ); ?>
</button>
```

Existierender Status-Span wird wiederverwendet.

### AJAX-Handler `ajax_pull_now()`

Standard-Pattern: capability + nonce + portal-Whitelist, ruft `Plugin::instance()->get_openimmo_sftp_puller()->pull( $portal_key )` auf, gibt counts zurück.

### Inline-JS

Analog zum existierenden Test-Connection / Upload-Now-Handler — `$.post`, FormData unnötig (kein File-Upload). Status-Text "Prüfe Inbox…" während Lauf.

## Fehlerbehandlung & SyncLog

### Fehler-Matrix

| Stufe | Fehlertyp | Aktion |
|---|---|---|
| Settings | `enabled=false` oder `inbox_path` leer | early return `skipped`, kein SyncLog |
| Lock | Lock gehalten | `skipped` "lock held" |
| Connect/Login | SFTP-Fehler | Hard-Stop, SyncLog `error` |
| mkdir_p inbox | Fehler | Hard-Stop, SyncLog `error` |
| list_directory leer | nichts zu pullen | `skipped` "no files in inbox" |
| `get` (Download) | einzelner Fehler | counts.errors++, ZIP bleibt liegen, weiter |
| ImportService `error` | counts.errors++, ZIP nach `error/`, weiter |
| ImportService `success`/`partial` | counts.imported++, counts.conflicts += import.conflicts, ZIP nach `processed/` |
| `rename` Fehler | error_log, ZIP bleibt in inbox/ (nächster Lauf retryt) |
| Stage-Cleanup | best-effort, error_log |
| Top-Level | catch-all, SyncLog `error`, finally-block |

### SyncLog-Eintrag für Pull

- `portal` = `'willhaben'` / `'immoscout24_at'`
- `direction` = `'pull'`
- `status` = `success`/`partial`/`error`/`skipped`
- `summary` = `'X gepullt, Y importiert, Z Konflikte, N Fehler'`
- `details` (JSON): `counts: {pulled, imported, conflicts, errors}`, `errors: [{file, message}]`
- `properties_count` = `counts['imported']`

## Manuelle Verifikation

| Test | Vorgehen | Erwartet |
|---|---|---|
| PHP-Lint | `php -l` auf alle Dateien | „No syntax errors" |
| Plugin-Reaktivierung | Deaktivieren → reaktivieren | Cron `immo_manager_openimmo_hourly_pull` scheduled |
| Settings-Seite | öffnen | Pro Portal: neues Feld „Inbox-Pfad" + Button „Inbox jetzt prüfen" |
| `inbox_path` leer | Button | `skipped`-Notice |
| Test-Connection erweitert | korrekte Credentials + inbox_path gesetzt | Grün „OK (inkl. Inbox-Pfad)" |
| Test-Connection ohne mkdir-Permission | inbox_path nicht beschreibbar | Rot „inbox mkdir failed" |
| Pull leer | Inbox leer, Button | `skipped`-Notice |
| Pull happy-path | 1 ZIP in Inbox | grün, 1 gepullt, 1 importiert; ZIP weg, in `processed/<filename>` |
| Pull mit Conflict | ZIP enthält existing Listing modifiziert | importiert mit 1 Conflict, ZIP in `processed/` |
| Pull mit defektem ZIP | kaputtes ZIP in Inbox | counts.errors=1, ZIP in `error/<filename>` |
| Lock-Test | manuell Transient setzen, Button | `skipped` "lock held" |
| Cron-Run | `wp cron event run immo_manager_openimmo_hourly_pull` | Pull läuft pro aktivem Portal mit `inbox_path` |
| Stage-Cleanup | nach jedem Pull | `pull-staging/` leer |
| Doppel-Import-Schutz | gleiches ZIP zweimal pullen (zweiter Lauf nach erstem) | beim 2. Lauf andere Inbox (1. ZIP in processed/), kein Doppel-Import |

## Out-of-Scope für Phase 4

- ❌ SSH-Key-Authentifizierung — Phase 6
- ❌ Portal-spezifische Pull-Frequenzen — Phase 6
- ❌ Hash-basierter lokaler Doppel-Import-Schutz — `processed/`-Konvention reicht
- ❌ Auto-Cleanup von `processed/`/`error/` nach N Tagen — Phase 7
- ❌ E-Mail-Notifications bei Pull-Fehlern — Phase 7
- ❌ Pull-Frequenz-UI (Dropdown 15min/30min/60min) — Phase 7
- ❌ Resume bei großen Files / Connection-Drop — phpseclib-Limit

## Datei-Übersicht

| Aktion | Datei | Verantwortung |
|---|---|---|
| Modify | `includes/openimmo/sftp/class-sftp-client.php` | + `list_directory`, `get`, `rename` |
| Create | `includes/openimmo/sftp/class-sftp-puller.php` | Pull-Workflow |
| Modify | `includes/openimmo/sftp/class-sftp-uploader.php` | `test_connection()` Inbox-Stufe |
| Modify | `includes/openimmo/class-settings.php` | `portal_defaults()` + `inbox_path` |
| Modify | `includes/openimmo/class-cron-scheduler.php` | + `HOOK_HOURLY_PULL`, `run_hourly_pull()`, activation/deactivation |
| Modify | `includes/openimmo/class-admin-page.php` | Inbox-Field, Pull-Now-Button, AJAX-Handler, JS, maybe_save |
| Modify | `includes/class-plugin.php` | Lazy-Getter `get_openimmo_sftp_puller()` |

7 Dateien — 1 neu, 6 modifiziert.

## Phasen-Kontext

Spec für `docs/superpowers/plans/2026-04-29-openimmo-phase-4.md`. Plan folgt nach User-Approval via `superpowers:writing-plans`.

Phasen-Übersicht:
- ✅ Phase 0 — Foundation
- ✅ Phase 1 — Export-Mapping
- ✅ Phase 2 — SFTP-Push-Transport
- ✅ Phase 3 — Import-Parsing
- 👉 **Phase 4 — Import-Transport / SFTP-Pull (diese Spec)**
- ⬜ Phase 5 — Konflikt-Behandlung (Approval-UI)
- ⬜ Phase 6 — Portal-Anpassungen
- ⬜ Phase 7 — Polish (Retention, E-Mail, README)
