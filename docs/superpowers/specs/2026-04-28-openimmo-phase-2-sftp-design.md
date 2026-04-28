# OpenImmo Phase 2 – SFTP-Transport (Design)

**Datum:** 2026-04-28
**Branch:** `feat/openimmo`
**Phase:** 2 von 7 (Phase 0 + 1 abgeschlossen)
**Status:** Spec — Implementierungs-Plan folgt nach Approval

---

## Ziel

Aus Phase 1 generierte ZIPs (`wp-content/uploads/openimmo/exports/<portal>/...zip`) per SFTP an die Portal-Server hochladen. Inline im selben Cron-Lauf direkt nach erfolgreichem Export. Plus zwei manuelle Settings-Buttons: „Verbindung testen" und „Letztes ZIP jetzt hochladen". Retry-Logik bei kurzen Netzwerk-Aussetzern, Transient-Lock pro Portal gegen Doppel-Trigger.

## Anforderungen (User-Entscheidungen aus Brainstorming)

| # | Punkt | Wahl |
|---|---|---|
| 1 | Trigger | Inline (Export + Upload im selben Lauf) + Settings-Button „Letztes ZIP jetzt hochladen" |
| 2 | Fehlerbehandlung | 3-Versuch-Retry, Backoff 0s/5s/15s, dann SyncLog 'error' |
| 3 | ZIP-Cleanup | Behalten (Retention in Phase 7) |
| 4 | Authentication | Nur Password (AES-256-CBC verschlüsselt aus Phase 0) |
| 5 | Concurrency | Transient-Lock pro Portal, TTL 10 Min |
| 6 | Test-Connection | Settings-Button — Connect + Login + `mkdir -p` + temp-Schreibtest + Delete |
| 7 | Remote-Pfad | `mkdir -p` rekursiv anlegen wenn fehlend |

## Architektur & Datenfluss

```
[Trigger]
 ├─ Cron-Hook (täglich)
 ├─ Settings-Button "Jetzt exportieren"     (Phase 1)
 └─ Settings-Button "Letztes ZIP jetzt hochladen"  (Phase 2 NEU)
       │
       ▼
[ExportService::run( $portal_key )]
       │
   ... Phase-1-Pipeline: Collect → Map → Validate → Pack ZIP ...
       │
       ▼
   ZIP geschrieben unter wp-content/uploads/openimmo/exports/<portal>/<portal>-export-<TS>.zip
       │
       ▼  (Phase 2 NEU)
[SftpUploader::upload( $zip_path, $portal_key )]
       │
       ├─ Lock akquirieren (Transient `immo_oi_sftp_lock_<portal>`, TTL 600s)
       │     ├─ Lock vorhanden → result 'skipped' "lock held", return
       │     └─ Lock frei → weiter
       │
       ├─ Retry-Loop (3 Versuche, Backoff 0s / 5s / 15s):
       │     ├─ SftpClient::connect( $host, $port )
       │     ├─ SftpClient::login( $user, $pass )
       │     ├─ SftpClient::mkdir_p( $remote_path )
       │     ├─ SftpClient::put( $local_zip, $remote_path . basename($zip) )
       │     ├─ SftpClient::disconnect()
       │     └─ Erfolg → break, sonst sleep($backoff), nächster Versuch
       │
       ├─ Lock freigeben (im finally)
       │
       ├─ Bei Erfolg: Settings::set_last_sync( $portal_key, current_time('mysql') )
       │
       └─ Return result {status, attempts, remote_path, error}
       │
       ▼
   ExportService aktualisiert SyncLog-details.sftp.* + ggf. Status-Promotion auf 'error'
```

### Klassen unter `includes/openimmo/sftp/`

| Klasse | Verantwortung | Public API |
|---|---|---|
| `SftpClient` | phpseclib-Wrapper, low-level | `connect`, `login`, `mkdir_p`, `put`, `put_string`, `delete`, `disconnect`, `last_error` |
| `SftpUploader` | Upload-Workflow inkl. Retry + Lock | `upload( $zip_path, $portal_key ): array` / `test_connection( $portal_key ): array` |

### Status-Promotion-Regel

Der Phase-1-SyncLog-Eintrag wird wiederverwendet. SFTP-Resultat wandert in `details.sftp` (JSON). Status:

- Export `success`/`partial` + SFTP `success` → bleibt `success`/`partial`
- Export `success`/`partial` + SFTP `error` → wird auf `error` upgegradet, `summary` ergänzt mit „— SFTP-Upload fehlgeschlagen: <reason>"
- Export `success`/`partial` + SFTP `skipped` (Lock held) → bleibt, `summary` ergänzt mit „(SFTP übersprungen: lock held)"

## Klassen-Schnittstellen

### `SftpClient`

```php
namespace ImmoManager\OpenImmo\Sftp;

class SftpClient {
    private \phpseclib3\Net\SFTP $sftp;
    private string $error = '';

    public function connect( string $host, int $port = 22, int $timeout = 10 ): bool;
    public function login( string $user, string $password ): bool;
    public function mkdir_p( string $remote_path ): bool;       // rekursiv (analog `mkdir -p`)
    public function put( string $local_path, string $remote_path ): bool;
    public function put_string( string $contents, string $remote_path ): bool;
    public function delete( string $remote_path ): bool;
    public function disconnect(): void;
    public function last_error(): string;
}
```

`mkdir_p` iteriert über die Pfad-Segmente und ruft `mkdir` ohne Recursive-Flag pro Segment, prüft via `is_dir()` ob das Segment schon existiert. phpseclib's `SFTP::mkdir(string $dir, int $mode, bool $recursive)` unterstützt Recursive direkt — verwenden wenn verfügbar.

### `SftpUploader`

```php
namespace ImmoManager\OpenImmo\Sftp;

class SftpUploader {
    private const LOCK_TRANSIENT_PREFIX = 'immo_oi_sftp_lock_';
    private const LOCK_TTL_SECONDS      = 600;
    private const RETRY_BACKOFFS        = array( 0, 5, 15 );

    /**
     * @return array{
     *   status: 'success'|'error'|'skipped',
     *   summary: string,
     *   attempts: int,
     *   remote_path: string,
     *   error: string|null
     * }
     */
    public function upload( string $zip_path, string $portal_key ): array;

    /**
     * @return array{ ok: bool, message: string, mkdir: bool, write: bool }
     */
    public function test_connection( string $portal_key ): array;

    private function acquire_lock( string $portal_key ): bool;
    private function release_lock( string $portal_key ): void;
    private function try_upload_once( SftpClient $client, array $portal, string $zip_path ): array;
}
```

### `upload()`-Flow

1. `Settings::get()` laden, Portal validieren — wenn `enabled=false` oder Pflichtfelder leer: `return array('status'=>'error','summary'=>'invalid portal config',...)`.
2. ZIP-Existenz prüfen — wenn fehlt, `'error'`.
3. `acquire_lock()` — wenn `false`, `return array('status'=>'skipped','summary'=>'lock held',...)`.
4. Retry-Loop über `RETRY_BACKOFFS`:
   ```php
   foreach ( self::RETRY_BACKOFFS as $backoff ) {
       if ( $backoff > 0 ) sleep( $backoff );
       ++$attempts;
       $client = new SftpClient();
       $try    = $this->try_upload_once( $client, $portal, $zip_path );
       if ( $try['ok'] ) { $result = $try; break; }
       $last_err = $try['error'];
   }
   ```
5. `release_lock()` (im `finally`).
6. Bei Erfolg: `Settings::set_last_sync( $portal_key, current_time('mysql') )`.
7. Return.

### `test_connection()`-Flow

1. Portal-Settings (kein `enabled`-Check — Test soll vor Aktivierung gehen).
2. `connect`, `login`, `mkdir_p`.
3. `put_string( "immo-manager test " . gmdate('c'), $remote_path . '/immo-manager-test-' . time() . '.txt' )`.
4. Sofort `delete()`.
5. `disconnect()`.
6. Return `{ok, message, mkdir, write}`.

## Settings-Erweiterung

### Neue static method `Settings::set_last_sync()`

```php
public static function set_last_sync( string $portal_key, string $timestamp ): void {
    $stored = get_option( self::OPTION_KEY, array() );
    if ( ! isset( $stored['portals'][ $portal_key ] ) ) {
        return;
    }
    $stored['portals'][ $portal_key ]['last_sync'] = $timestamp;
    update_option( self::OPTION_KEY, $stored );
}
```

Schreibt direkt das verschlüsselt-gespeicherte Storage-Array. Nicht über `Settings::save()`, weil dieses alle Felder neu sanitisiert.

`portal_defaults()` bleibt unverändert — alle SFTP-Felder existieren seit Phase 0, `last_sync` ist seit Phase 0 dabei.

## Lock-Mechanismus

```php
private function acquire_lock( string $portal_key ): bool {
    $key = self::LOCK_TRANSIENT_PREFIX . $portal_key;
    if ( get_transient( $key ) ) {
        return false;
    }
    set_transient( $key, time(), self::LOCK_TTL_SECONDS );
    return true;
}

private function release_lock( string $portal_key ): void {
    delete_transient( self::LOCK_TRANSIENT_PREFIX . $portal_key );
}
```

**Race-Window-Hinweis:** WP-Transients haben kein atomic test-and-set. Theoretisch zwei parallele `get_transient`/`set_transient`-Sequenzen möglich. Bei 1× täglichem Cron + seltenen Klicks akzeptabel. Phase 7 ggf. DB-Row-Lock falls nötig.

## ExportService-Wiring

In `ExportService::run()`, im Erfolgs-Pfad nach `$packer->write(...)` und vor `$this->finish( ..., 'success'|'partial', ... )`:

```php
// SFTP-Upload (Phase 2).
$uploader      = \ImmoManager\Plugin::instance()->get_openimmo_sftp_uploader();
$upload_result = $uploader->upload( $target, $portal_key );
$details['sftp'] = array(
    'status'      => $upload_result['status'],
    'attempts'    => $upload_result['attempts'],
    'remote_path' => $upload_result['remote_path'] ?? '',
    'error'       => $upload_result['error']       ?? null,
);

if ( 'error' === $upload_result['status'] ) {
    $status   = 'error';
    $summary .= ' — SFTP-Upload fehlgeschlagen: ' . ( $upload_result['error'] ?? 'unbekannt' );
} elseif ( 'skipped' === $upload_result['status'] ) {
    $summary .= ' (SFTP-Upload übersprungen: ' . ( $upload_result['summary'] ?? 'lock held' ) . ')';
}
```

Routing über Plugin-Singleton ist konsistent mit T8-Final-Fix #13.

## Plugin-Singleton-Erweiterung

```php
private $openimmo_sftp_uploader = null;

public function get_openimmo_sftp_uploader(): \ImmoManager\OpenImmo\Sftp\SftpUploader {
    if ( null === $this->openimmo_sftp_uploader ) {
        require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-settings.php';
        require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-sync-log.php';
        require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/sftp/class-sftp-client.php';
        require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/sftp/class-sftp-uploader.php';
        $this->openimmo_sftp_uploader = new \ImmoManager\OpenImmo\Sftp\SftpUploader();
    }
    return $this->openimmo_sftp_uploader;
}
```

## AdminPage-Erweiterung

### Constructor — 2 neue Hooks

```php
add_action( 'wp_ajax_immo_manager_openimmo_test_connection', array( $this, 'ajax_test_connection' ) );
add_action( 'wp_ajax_immo_manager_openimmo_upload_now',      array( $this, 'ajax_upload_now' ) );
```

### `ajax_test_connection()`

- Capability + Nonce + Portal-Whitelist (analog zu T9)
- Ruft `$uploader->test_connection( $portal_key )` über Plugin-Singleton
- Erfolg → grüner Text „Verbindung OK ✓ (mkdir, write+delete erfolgreich)"
- Fehler → roter Text mit `$result['message']`

### `ajax_upload_now()`

- Capability + Nonce + Portal-Whitelist
- Findet das jüngste ZIP per `find_latest_zip( $portal_key )`:
  ```php
  private function find_latest_zip( string $portal_key ): ?string {
      $upload_dir = wp_get_upload_dir();
      $dir        = $upload_dir['basedir'] . '/openimmo/exports/' . $portal_key;
      if ( ! is_dir( $dir ) ) return null;
      $files = glob( $dir . '/*.zip' );
      if ( empty( $files ) ) return null;
      usort( $files, fn( $a, $b ) => filemtime( $b ) <=> filemtime( $a ) );
      return $files[0];
  }
  ```
- Wenn null → roter Text „Kein ZIP gefunden — zuerst exportieren."
- Sonst `$uploader->upload( $latest_zip, $portal_key )` → grüner Text mit Pfad + Versuch-Anzahl, oder roter Text bei Fehler.

### `render()`-UI

Pro Portal — direkt nach dem existierenden „Jetzt exportieren"-Button (T9), zwei neue Buttons + Status-Span:

```html
<p>
    <button class="button button-secondary immo-openimmo-test-connection"
            data-portal="<?= esc_attr($key) ?>"
            data-nonce="<?= esc_attr(wp_create_nonce('immo_manager_openimmo_test_connection')) ?>">
        SFTP-Verbindung testen
    </button>
    <button class="button button-secondary immo-openimmo-upload-now"
            data-portal="<?= esc_attr($key) ?>"
            data-nonce="<?= esc_attr(wp_create_nonce('immo_manager_openimmo_upload_now')) ?>">
        Letztes ZIP jetzt hochladen
    </button>
    <span class="immo-openimmo-sftp-status" style="margin-left:10px;"></span>
</p>
```

Plus 2 Inline-jQuery-Event-Handler (analog zum Export-Button-Handler aus T9).

## Manuelle Verifikation

| Test | Vorgehen | Erwartet |
|---|---|---|
| PHP-Lint | `php -l` auf alle neuen Dateien | „No syntax errors" |
| Plugin lädt | Reaktivierung | Kein Fatal |
| Test-Connection (negativ) | Falsche Credentials, Button | Roter Text mit lesbarer Fehlermeldung (z.B. „login failed: ...") |
| Test-Connection (positiv) | Korrekte Credentials | Grün „Verbindung OK ✓" |
| `mkdir -p` | `remote_path = '/sub/dir/that/does/not/exist/'`, Test-Button | Verzeichnis wird angelegt, danach Erfolg |
| Upload-only | Phase-1-Export hat ZIP, Button „Letztes ZIP jetzt hochladen" | ZIP landet auf SFTP, grün mit Pfad + Versuch-Anzahl |
| Inline-Upload (Cron) | „Schnittstelle aktiv" + 1 Portal aktiv, `wp cron event run immo_manager_openimmo_daily_sync` | ZIP entsteht UND wird hochgeladen, SyncLog hat `details.sftp.status='success'` |
| Retry-Logik | Server während Cron offline (oder Port falsch), Cron-Run | 3 Versuche im error_log, finaler Status 'error', `details.sftp.attempts=3` |
| Lock-Verhalten | Manuell `set_transient('immo_oi_sftp_lock_willhaben', time(), 600)`, Upload-Button klicken | Notice „SFTP-Upload übersprungen: lock held" (status='skipped') |
| `last_sync` | Nach erfolgreichem Upload, Settings-Seite öffnen, neu speichern | `last_sync`-Wert bleibt erhalten |
| ZIP bleibt liegen | Nach erfolgreichem Upload | Datei weiterhin lokal vorhanden |

## Out-of-Scope für Phase 2

- ❌ SSH-Key-Authentifizierung — Phase 6 (Provider-spezifisch)
- ❌ Retention/Cleanup alter ZIPs — Phase 7
- ❌ Atomic Test-and-Set Lock auf DB-Level — theoretische Race-Condition akzeptiert
- ❌ E-Mail-Notifications bei finalem Upload-Fehler — Phase 7
- ❌ Resume bei großen Dateien / Connection-Drop — phpseclib hat kein Built-in-Resume
- ❌ Bandbreiten-Drosselung
- ❌ Portal-spezifische Upload-Pfade oder Ordner-Strukturen — Phase 6

## Datei-Übersicht

| Aktion | Datei |
|---|---|
| Create | `includes/openimmo/sftp/class-sftp-client.php` |
| Create | `includes/openimmo/sftp/class-sftp-uploader.php` |
| Modify | `includes/openimmo/class-settings.php` |
| Modify | `includes/openimmo/export/class-export-service.php` |
| Modify | `includes/openimmo/class-admin-page.php` |
| Modify | `includes/class-plugin.php` |

6 Dateien — 2 neu, 4 modifiziert.

## Phasen-Kontext

Diese Spec ist Grundlage für `docs/superpowers/plans/2026-04-28-openimmo-phase-2.md`. Nach User-Approval folgt der detaillierte Task-by-Task-Plan via `superpowers:writing-plans`.

Phasen-Übersicht (siehe `memory/project_openimmo.md`):
- ✅ Phase 0 — Foundation
- ✅ Phase 1 — Export-Mapping
- 👉 **Phase 2 — SFTP-Transport (diese Spec)**
- ⬜ Phase 3 — Import-Parsing
- ⬜ Phase 4 — Import-Transport
- ⬜ Phase 5 — Konflikt-Behandlung
- ⬜ Phase 6 — Portal-Anpassungen
- ⬜ Phase 7 — Polish (Retention, E-Mail, README)
