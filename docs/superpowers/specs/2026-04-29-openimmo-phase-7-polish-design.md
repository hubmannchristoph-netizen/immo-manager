# OpenImmo Phase 7 – Polish (Retention + E-Mail + README) Design

**Datum:** 2026-04-29
**Branch:** `feat/openimmo`
**Phase:** 7 von 7 (Phase 0/1/2/3/4/5 abgeschlossen, Phase 6 aufgeschoben)
**Status:** Spec — Implementierungs-Plan folgt nach Approval

---

## Ziel

Polish-Phase mit drei Themengebieten:

1. **Retention-Cleanup** für lokale Export-ZIPs, SFTP `processed/error/`-Verzeichnisse, verwaiste Attachments und alte resolved Conflicts.
2. **E-Mail-Notifications** bei Push- und Pull-Fehlern (mit Throttling).
3. **User-README** als Doku im Plugin-Root.

## Anforderungen (User-Entscheidungen aus Brainstorming)

| # | Punkt | Wahl |
|---|---|---|
| 1 | Scope | Retention (a/b/c/d) + E-Mail (f/g) + README (n) |
| 2 | Retention-Werte | Hardcoded Constants (60/30/14/90 Tage) |
| 3 | Cleanup-Trigger | Eigener Cron-Hook `daily_cleanup` (täglich 04:00) |
| 4 | E-Mail-Empfänger | Settings-Feld `notification_email` mit Fallback auf `admin_email` |
| 5 | E-Mail-Throttling | Max 1 Mail/Tag pro Portal+Direction (Transient) |
| 6 | README | Nur User-README im Plugin-Root |

## Architektur & Datenfluss

```
[Cron-Hook 'immo_manager_openimmo_daily_cleanup' — täglich 04:00]
       │
       ▼
[CronScheduler::run_daily_cleanup()]
       │
       ▼
[RetentionCleaner::run_all()]
       │
       ├─ cleanup_local_zips()         → glob+unlink, mtime > 60 Tage
       ├─ cleanup_sftp_directories()   → pro Portal: SftpClient::list+stat+delete für processed/+error/
       ├─ cleanup_orphaned_attachments() → WP_Query post_type=attachment, post_parent=0, _immo_openimmo_image_hash != '', älter als 14d → wp_delete_attachment(force)
       └─ cleanup_resolved_conflicts() → DELETE FROM wp_immo_conflicts WHERE status='approved' AND resolved_at < NOW - 90d
       │
       ▼
   error_log mit Report

[ExportService::run() / SftpPuller::pull() — bei status='error']
       │
       ▼
[EmailNotifier::notify_sync_error( $portal, $direction, $summary, $details )]
       │
       ├─ Transient-Check: get_transient('immo_oi_mail_sent_<portal>_<direction>')
       │     ├─ vorhanden → return false (throttled)
       │     └─ frei → wp_mail + set_transient(24h)
```

### Klassen

| Klasse | Verantwortung |
|---|---|
| `RetentionCleaner` (NEU) | 4 Cleanup-Methoden + `run_all()` |
| `EmailNotifier` (NEU) | `notify_sync_error()` mit Throttling-Transient |
| `SftpClient` (Phase 2, **erweitert**) | + `stat()` für mtime-Lookup |
| `Settings` (Phase 0, **erweitert**) | `defaults()` mit `notification_email` |
| `CronScheduler` (Phase 0, **erweitert**) | + `HOOK_DAILY_CLEANUP` |
| `ExportService` (Phase 1, **refactored**) | `dispatch_finish()`-Wrapper für E-Mail-Trigger |
| `SftpPuller` (Phase 4, **refactored**) | `dispatch_finish()` + `dispatch_result()` für E-Mail |
| `AdminPage` (Phase 0, **erweitert**) | Notification-E-Mail-Feld in „Allgemein" |

### CronScheduler — Hook 3

```php
public const HOOK_DAILY_CLEANUP = 'immo_manager_openimmo_daily_cleanup';

public function __construct() {
	// existing 2 hooks
	add_action( self::HOOK_DAILY_CLEANUP, array( $this, 'run_daily_cleanup' ) );
}

public static function on_activation(): void {
	// existing daily_sync + hourly_pull
	if ( ! wp_next_scheduled( self::HOOK_DAILY_CLEANUP ) ) {
		$first = strtotime( 'tomorrow 04:00:00' );
		if ( false === $first ) { $first = time() + DAY_IN_SECONDS; }
		wp_schedule_event( $first, 'daily', self::HOOK_DAILY_CLEANUP );
	}
}

public static function on_deactivation(): void {
	// existing clears
	$ts = wp_next_scheduled( self::HOOK_DAILY_CLEANUP );
	if ( $ts ) { wp_unschedule_event( $ts, self::HOOK_DAILY_CLEANUP ); }
	wp_clear_scheduled_hook( self::HOOK_DAILY_CLEANUP );
}

public function run_daily_cleanup(): void {
	\ImmoManager\Plugin::instance()->get_openimmo_retention_cleaner()->run_all();
}
```

**Wichtig:** Plugin nach Phase-7-Deployment einmal deaktivieren + reaktivieren, damit Hook scheduled wird.

## Klassen-Schnittstellen

### `RetentionCleaner`

```php
namespace ImmoManager\OpenImmo;

use ImmoManager\Database;
use ImmoManager\OpenImmo\Sftp\SftpClient;

class RetentionCleaner {

	private const RETENTION_DAYS_LOCAL_ZIPS         = 60;
	private const RETENTION_DAYS_SFTP_PROCESSED     = 30;
	private const RETENTION_DAYS_SFTP_ERROR         = 30;
	private const RETENTION_DAYS_ORPHAN_ATTACHMENTS = 14;
	private const RETENTION_DAYS_RESOLVED_CONFLICTS = 90;

	/**
	 * @return array{
	 *   local_zips: int,
	 *   sftp_dirs: int,
	 *   orphan_attachments: int,
	 *   resolved_conflicts: int
	 * }
	 */
	public function run_all(): array;

	private function cleanup_local_zips(): int;
	private function cleanup_sftp_directories(): int;
	private function cleanup_orphaned_attachments(): int;
	private function cleanup_resolved_conflicts(): int;
	private function delete_old_in_sftp_dir( SftpClient $client, string $remote_dir, int $cutoff_ts ): int;
	private function log( array $report ): void;
}
```

**Implementations-Details:**

`cleanup_local_zips()`: pro Portal `glob()` über `wp-content/uploads/openimmo/exports/<portal>/*.zip`, `filemtime() < now - 60d` → `unlink()`.

`cleanup_sftp_directories()`: Settings::get(), für jedes Portal mit `enabled` und `inbox_path`: SftpClient connect/login, dann `delete_old_in_sftp_dir()` für `<inbox>/processed/` und `<inbox>/error/`. Best-effort, einzelne Fehler werden geloggt aber nicht propagiert. Pro File: `SftpClient::stat()` für mtime, `delete()` wenn alt.

`cleanup_orphaned_attachments()`:

```php
$query = new \WP_Query( array(
	'post_type'      => 'attachment',
	'post_status'    => 'inherit',
	'post_parent'    => 0,
	'posts_per_page' => -1,
	'no_found_rows'  => true,
	'fields'         => 'ids',
	'date_query'     => array(
		array(
			'before'    => '14 days ago',
			'inclusive' => true,
		),
	),
	'meta_query'     => array(
		array(
			'key'     => '_immo_openimmo_image_hash',
			'compare' => 'EXISTS',
		),
	),
) );

foreach ( $query->posts as $att_id ) {
	wp_delete_attachment( (int) $att_id, true );  // force-delete
}
```

`cleanup_resolved_conflicts()`:

```php
global $wpdb;
$table = Database::conflicts_table();
$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::RETENTION_DAYS_RESOLVED_CONFLICTS * DAY_IN_SECONDS ) );
return (int) $wpdb->query( $wpdb->prepare(
	"DELETE FROM {$table} WHERE status = 'approved' AND resolved_at < %s",
	$cutoff
) );
```

### `EmailNotifier`

```php
namespace ImmoManager\OpenImmo;

class EmailNotifier {

	private const TRANSIENT_PREFIX = 'immo_oi_mail_sent_';
	private const TRANSIENT_TTL    = DAY_IN_SECONDS;

	public function notify_sync_error( string $portal, string $direction, string $summary, array $details = array() ): bool {
		$key = self::TRANSIENT_PREFIX . $portal . '_' . $direction;
		if ( get_transient( $key ) ) {
			return false;
		}
		$to       = $this->recipient();
		$subject  = sprintf( '[%s] OpenImmo-Sync-Fehler: %s (%s)', get_bloginfo( 'name' ), $portal, $direction );
		$body     = $this->build_body( $portal, $direction, $summary, $details );
		$headers  = array( 'Content-Type: text/plain; charset=UTF-8' );

		$sent = wp_mail( $to, $subject, $body, $headers );
		if ( $sent ) {
			set_transient( $key, time(), self::TRANSIENT_TTL );
		}
		return $sent;
	}

	private function recipient(): string {
		$settings = Settings::get();
		$email    = (string) ( $settings['notification_email'] ?? '' );
		if ( '' === $email || ! is_email( $email ) ) {
			return (string) get_option( 'admin_email' );
		}
		return $email;
	}

	private function build_body( string $portal, string $direction, string $summary, array $details ): string;
}
```

`build_body()` erzeugt Plain-Text:

```
Site: <site_url>
Portal: <portal>
Direction: <direction>
Time: <gmdate('Y-m-d H:i:s')>

Summary:
<summary>

Details:
<json_encode($details, JSON_PRETTY_PRINT)>

— Immo Manager
```

### `SftpClient::stat()`

```php
/**
 * @return array{size:int, mtime:int}|null
 */
public function stat( string $remote_path ): ?array {
	if ( null === $this->sftp ) {
		$this->error = 'not connected';
		return null;
	}
	try {
		$stat = $this->sftp->stat( $remote_path );
		if ( ! is_array( $stat ) ) {
			return null;
		}
		return array(
			'size'  => (int) ( $stat['size'] ?? 0 ),
			'mtime' => (int) ( $stat['mtime'] ?? 0 ),
		);
	} catch ( \Throwable $e ) {
		$this->error = $e->getMessage();
		return null;
	}
}
```

### Settings::defaults() — neues Feld

```php
public static function defaults(): array {
	return array(
		'enabled'            => false,
		'cron_time'          => '03:00',
		'notification_email' => '',     // Phase 7
		'portals'            => array(
			'willhaben'      => self::portal_defaults(),
			'immoscout24_at' => self::portal_defaults(),
		),
	);
}
```

### AdminPage::maybe_save() — Sanitize

```php
$data = array(
	'enabled'            => ! empty( $_POST['enabled'] ),
	'cron_time'          => sanitize_text_field( wp_unslash( $_POST['cron_time'] ?? '03:00' ) ),
	'notification_email' => sanitize_email( wp_unslash( $_POST['notification_email'] ?? '' ) ),
	'portals'            => array(),
);
```

### AdminPage::render() — neues Input

In der „Allgemein"-Tabelle nach `cron_time`:

```php
<tr>
	<th scope="row"><?php esc_html_e( 'Notification-E-Mail', 'immo-manager' ); ?></th>
	<td>
		<input type="email" class="regular-text"
				name="notification_email"
				value="<?php echo esc_attr( $settings['notification_email'] ?? '' ); ?>"
				placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
		<p class="description">
			<?php esc_html_e( 'Bei Sync-Fehlern. Leer = WP-Admin-E-Mail.', 'immo-manager' ); ?>
		</p>
	</td>
</tr>
```

### Plugin-Singleton — 2 neue Lazy-Getter

```php
private $openimmo_retention_cleaner = null;
private $openimmo_email_notifier    = null;

public function get_openimmo_retention_cleaner(): \ImmoManager\OpenImmo\RetentionCleaner;
public function get_openimmo_email_notifier(): \ImmoManager\OpenImmo\EmailNotifier;
```

(Standard-Lazy-Getter-Pattern wie in den anderen Phasen.)

## ExportService + SftpPuller Refactor

### Pattern: `dispatch_finish()`-Wrapper

Bestehende `return $this->finish( ... )`-Aufrufe werden durch `return $this->dispatch_finish( $portal_key, ... )` ersetzt:

```php
private function dispatch_finish( string $portal_key, int $sync_id, string $status, string $summary, ...args... ): array {
	$result = $this->finish( $sync_id, $status, $summary, ...args... );
	if ( 'error' === $status ) {
		\ImmoManager\Plugin::instance()->get_openimmo_email_notifier()
			->notify_sync_error( $portal_key, '<direction>', $summary, $details );
	}
	return $result;
}
```

`<direction>` ist `'export'` für ExportService und `'pull'` für SftpPuller.

`SftpPuller` hat zusätzlich `result()` für early-returns ohne SyncLog. Auch dort wird ein analoger `dispatch_result()`-Wrapper eingesetzt für `'error'`-Stati.

### Fehlerquellen-Übersicht

| Quelle | Trigger | E-Mail bei error? |
|---|---|---|
| ExportService::run() (Push) | daily_sync ODER manueller Button | ✅ |
| SftpPuller::pull() (Pull) | hourly_pull ODER manueller Button | ✅ |
| ImportService::run() | nur AJAX (User sieht Notice) ODER aus Pull (Pull-Status fängt es ab) | ❌ |
| RetentionCleaner | daily_cleanup, best-effort | ❌ |

### Throttling-Konsequenz

Pro Portal × Direction (4 Kombinationen) max 1 Mail/24h. Bei dauerhaftem SFTP-Ausfall: max 4 Mails/Tag.

## README.md

`README.md` im Plugin-Root mit User-Doku:
- Voraussetzungen + Installation
- Cron-Hooks-Übersicht (3 Hooks)
- OpenImmo-Settings (Allgemein + pro Portal)
- Property-Backend-Workflow
- Konflikt-Queue-Bedienung
- Retention-Defaults
- Test-Workflow Schritt für Schritt
- DB-Tabellen-Übersicht
- Phasen-Status (✅ 0,1,2,3,4,5,7 / ⏸️ 6)
- Bekannte Limitierungen
- Verweis auf `docs/superpowers/specs/` und `docs/superpowers/plans/` für Architektur

(Vollständiger Inhalt siehe Brainstorming-Section 4.)

## Manuelle Verifikation

| Test | Vorgehen | Erwartet |
|---|---|---|
| PHP-Lint | alle Dateien | „No syntax errors" |
| Plugin-Reaktivierung | deaktivieren → reaktivieren | `wp cron event list` zeigt 3 Hooks |
| Settings-Seite | öffnen | „Notification-E-Mail"-Feld in „Allgemein" sichtbar |
| Notification leer | Fehler erzwingen | Mail an `admin_email` |
| Notification gesetzt | Fehler erzwingen | Mail an spezielle Adresse |
| Retention lokale ZIPs | ZIP rückdatieren auf 90 Tage, Cleanup | gelöscht |
| Retention SFTP | File auf SFTP rückdatieren, Cleanup | gelöscht |
| Retention Attachments | Attachment ohne post_parent + Hash-Meta + alt | gelöscht |
| Retention Conflicts | resolved Conflict älter 90d, Cleanup | DB-Row weg |
| Nicht-eigene Attachments | User-Attachment ohne Hash-Meta | bleibt unverändert |
| Cron-Run | `wp cron event run immo_manager_openimmo_daily_cleanup` | RetentionCleaner läuft, error_log Report |
| Mail bei Push-Fehler | korrupten SFTP-Host, Push triggern | 1 Mail mit summary+details |
| Mail-Throttling | sofort wieder triggern | keine zweite Mail |
| Throttle-Reset | `delete_transient(...)`, neuer Trigger | wieder Mail |
| Mail bei Pull-Fehler | inbox_path falsch, Pull triggern | 1 Mail mit direction='pull' |
| Verschiedene direction-Mails | Push-Fehler + Pull-Fehler am gleichen Tag | beide Mails kommen |
| Keine Mail bei Erfolg | normaler success | keine Mail |
| README | Plugin-Root | `README.md` vorhanden, Inhalt korrekt |

## Out-of-Scope für Phase 7

- ❌ Phase-1-TODOs (Heizungsart-Enum, Kontaktname-Splitting) — Phase 6
- ❌ Pull-Frequenz-UI (Dropdown)
- ❌ Filter / Suche in Konflikt-Liste
- ❌ History-View für resolved Conflicts
- ❌ E-Mail bei neuen Konflikten
- ❌ Status-Wechsel-Notifications
- ❌ Filter-Hooks für Power-User
- ❌ Bulk-Approve in Konflikt-Queue
- ❌ Developer-README / Architektur-Diagramm — Phase 6/8
- ❌ SSH-Key-Auth — Phase 6
- ❌ Adapter-Pattern — Phase 6

## Datei-Übersicht

| Aktion | Datei | Verantwortung |
|---|---|---|
| Create | `includes/openimmo/class-retention-cleaner.php` | 4 Cleanup-Methoden + run_all |
| Create | `includes/openimmo/class-email-notifier.php` | notify_sync_error mit Throttling |
| Modify | `includes/openimmo/sftp/class-sftp-client.php` | + `stat()` für mtime-Lookup |
| Modify | `includes/openimmo/class-settings.php` | `defaults()` + `notification_email` |
| Modify | `includes/openimmo/class-admin-page.php` | Notification-E-Mail-Feld + Save |
| Modify | `includes/openimmo/class-cron-scheduler.php` | + HOOK_DAILY_CLEANUP, run_daily_cleanup |
| Modify | `includes/openimmo/export/class-export-service.php` | dispatch_finish-Wrapper |
| Modify | `includes/openimmo/sftp/class-sftp-puller.php` | dispatch_finish + dispatch_result |
| Modify | `includes/class-plugin.php` | 2 neue Lazy-Getter |
| Create | `README.md` | User-Doku |

10 Dateien — 3 neu, 7 modifiziert.

## Phasen-Kontext

Spec für `docs/superpowers/plans/2026-04-29-openimmo-phase-7.md`. Plan folgt nach User-Approval via `superpowers:writing-plans`.

Phasen-Übersicht:
- ✅ Phase 0 — Foundation
- ✅ Phase 1 — Export-Mapping
- ✅ Phase 2 — SFTP-Push-Transport
- ✅ Phase 3 — Import-Parsing
- ✅ Phase 4 — SFTP-Pull-Cron
- ✅ Phase 5 — Konflikt-Behandlung
- ⏸️ Phase 6 — Portal-Anpassungen (aufgeschoben)
- 👉 **Phase 7 — Polish (diese Spec)**
