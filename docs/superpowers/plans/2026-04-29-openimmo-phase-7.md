# OpenImmo Phase 7 – Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Polish-Phase mit Retention-Cleanup (lokale ZIPs, SFTP processed/error, verwaiste Attachments, alte Conflicts), E-Mail-Notifications bei Push/Pull-Fehlern (mit Throttling), und User-README.

**Architecture:** Zwei neue Klassen `RetentionCleaner` und `EmailNotifier` mit klaren Verantwortlichkeiten. SftpClient (Phase 2) bekommt `stat()`-Erweiterung. Settings/AdminPage bekommen `notification_email`-Feld. Dritter Cron-Hook `daily_cleanup`. ExportService und SftpPuller werden um einen `dispatch_finish()`-Wrapper für E-Mail-Trigger refactored.

**Tech Stack:** WordPress 5.9+, PHP 7.4+, `wp_mail()`, WP Transients (Throttling), WP_Query (Attachments), `phpseclib3 SFTP::stat()`.

**Spec:** `docs/superpowers/specs/2026-04-29-openimmo-phase-7-polish-design.md`

**Phasen-Kontext:** Phase 7 von 7. Phase 6 aufgeschoben.

**Hinweis zu Tests:** Wie bisher manuelle Verifikation: PHP-Lint, Plugin-Reaktivierung, Cron-Run, Mail-Versand-Test.

---

## File Structure

| Aktion | Datei |
|---|---|
| Create | `includes/openimmo/class-retention-cleaner.php` |
| Create | `includes/openimmo/class-email-notifier.php` |
| Modify | `includes/openimmo/sftp/class-sftp-client.php` |
| Modify | `includes/openimmo/class-settings.php` |
| Modify | `includes/openimmo/class-admin-page.php` |
| Modify | `includes/openimmo/class-cron-scheduler.php` |
| Modify | `includes/openimmo/export/class-export-service.php` |
| Modify | `includes/openimmo/sftp/class-sftp-puller.php` |
| Modify | `includes/class-plugin.php` |
| Create | `README.md` |

---

## Task 1: SftpClient::stat() + RetentionCleaner

**Files:**
- Modify: `includes/openimmo/sftp/class-sftp-client.php`
- Create: `includes/openimmo/class-retention-cleaner.php`

**Ziel:** SftpClient bekommt `stat()` für mtime-Lookup, neue `RetentionCleaner`-Klasse mit allen 4 Cleanup-Methoden.

- [ ] **Step 1: SftpClient::stat() ergänzen**

In `includes/openimmo/sftp/class-sftp-client.php`, vor `last_error()`:

```php
/**
 * Liefert mtime/size eines Remote-Files.
 *
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

- [ ] **Step 2: RetentionCleaner anlegen**

`includes/openimmo/class-retention-cleaner.php`:

```php
<?php
/**
 * Retention-Cleanup: lokale ZIPs, SFTP-Verzeichnisse, verwaiste Attachments, alte Konflikte.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo;

use ImmoManager\Database;
use ImmoManager\OpenImmo\Sftp\SftpClient;

defined( 'ABSPATH' ) || exit;

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
	public function run_all(): array {
		$report = array(
			'local_zips'         => $this->cleanup_local_zips(),
			'sftp_dirs'          => $this->cleanup_sftp_directories(),
			'orphan_attachments' => $this->cleanup_orphaned_attachments(),
			'resolved_conflicts' => $this->cleanup_resolved_conflicts(),
		);
		$this->log( $report );
		return $report;
	}

	private function cleanup_local_zips(): int {
		$cutoff_ts = time() - ( self::RETENTION_DAYS_LOCAL_ZIPS * DAY_IN_SECONDS );
		$upload    = wp_get_upload_dir();
		$base      = $upload['basedir'] . '/openimmo/exports';
		if ( ! is_dir( $base ) ) {
			return 0;
		}
		$deleted = 0;
		foreach ( scandir( $base ) as $portal_dir ) {
			if ( '.' === $portal_dir || '..' === $portal_dir ) {
				continue;
			}
			$portal_path = $base . '/' . $portal_dir;
			if ( ! is_dir( $portal_path ) ) {
				continue;
			}
			$zips = glob( $portal_path . '/*.zip' );
			if ( ! is_array( $zips ) ) {
				continue;
			}
			foreach ( $zips as $zip ) {
				$mtime = filemtime( $zip );
				if ( false === $mtime || $mtime >= $cutoff_ts ) {
					continue;
				}
				if ( @unlink( $zip ) ) {  // phpcs:ignore WordPress.PHP.NoSilencedErrors
					++$deleted;
				}
			}
		}
		return $deleted;
	}

	private function cleanup_sftp_directories(): int {
		$settings = Settings::get();
		$deleted  = 0;
		foreach ( $settings['portals'] as $portal_key => $portal ) {
			if ( empty( $portal['enabled'] ) || empty( $portal['inbox_path'] ) ) {
				continue;
			}
			if ( empty( $portal['sftp_host'] ) || empty( $portal['sftp_user'] ) ) {
				continue;
			}
			$client = new SftpClient();
			if ( ! $client->connect( $portal['sftp_host'], (int) $portal['sftp_port'] ) ) {
				continue;
			}
			if ( ! $client->login( $portal['sftp_user'], $portal['sftp_password'] ) ) {
				$client->disconnect();
				continue;
			}
			$inbox     = rtrim( $portal['inbox_path'], '/' );
			$processed = $inbox . '/processed';
			$error_dir = $inbox . '/error';
			$cutoff_ts = time() - ( self::RETENTION_DAYS_SFTP_PROCESSED * DAY_IN_SECONDS );
			$deleted  += $this->delete_old_in_sftp_dir( $client, $processed, $cutoff_ts );
			$deleted  += $this->delete_old_in_sftp_dir( $client, $error_dir, $cutoff_ts );
			$client->disconnect();
		}
		return $deleted;
	}

	private function delete_old_in_sftp_dir( SftpClient $client, string $remote_dir, int $cutoff_ts ): int {
		$files   = $client->list_directory( $remote_dir );
		$deleted = 0;
		foreach ( $files as $f ) {
			$path = $remote_dir . '/' . $f;
			$stat = $client->stat( $path );
			if ( null === $stat || empty( $stat['mtime'] ) ) {
				continue;
			}
			if ( $stat['mtime'] < $cutoff_ts ) {
				if ( $client->delete( $path ) ) {
					++$deleted;
				}
			}
		}
		return $deleted;
	}

	private function cleanup_orphaned_attachments(): int {
		$cutoff_iso = gmdate( 'Y-m-d H:i:s', time() - ( self::RETENTION_DAYS_ORPHAN_ATTACHMENTS * DAY_IN_SECONDS ) );
		$query = new \WP_Query( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_parent'    => 0,
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'before'    => $cutoff_iso,
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

		$deleted = 0;
		foreach ( $query->posts as $att_id ) {
			$res = wp_delete_attachment( (int) $att_id, true );
			if ( false !== $res && ! is_wp_error( $res ) ) {
				++$deleted;
			}
		}
		return $deleted;
	}

	private function cleanup_resolved_conflicts(): int {
		global $wpdb;
		$table  = Database::conflicts_table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::RETENTION_DAYS_RESOLVED_CONFLICTS * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status = 'approved' AND resolved_at < %s",
				$cutoff
			)
		);
		return false === $result ? 0 : (int) $result;
	}

	private function log( array $report ): void {
		error_log( sprintf(
			'[immo-manager openimmo] retention cleanup: zips=%d, sftp=%d, orphans=%d, conflicts=%d',
			(int) $report['local_zips'],
			(int) $report['sftp_dirs'],
			(int) $report['orphan_attachments'],
			(int) $report['resolved_conflicts']
		) );
	}
}
```

- [ ] **Step 3: PHP-Lint**

```bash
php -l includes/openimmo/sftp/class-sftp-client.php
php -l includes/openimmo/class-retention-cleaner.php
```

- [ ] **Step 4: Commit**

```bash
git add includes/openimmo/sftp/class-sftp-client.php includes/openimmo/class-retention-cleaner.php
git commit -m "feat(openimmo): add SftpClient::stat and RetentionCleaner"
```

---

## Task 2: Settings + AdminPage notification_email

**Files:**
- Modify: `includes/openimmo/class-settings.php`
- Modify: `includes/openimmo/class-admin-page.php`

**Ziel:** Settings-Default + Sanitize + UI-Feld für `notification_email`.

- [ ] **Step 1: Settings::defaults() erweitern**

In `includes/openimmo/class-settings.php` `defaults()`:

```php
public static function defaults(): array {
	return array(
		'enabled'            => false,
		'cron_time'          => '03:00',
		'notification_email' => '',
		'portals'            => array(
			'willhaben'      => self::portal_defaults(),
			'immoscout24_at' => self::portal_defaults(),
		),
	);
}
```

(Falls die existierende Methode anders strukturiert ist: nur `'notification_email' => ''` ergänzen.)

- [ ] **Step 2: AdminPage::maybe_save() ergänzen**

In `includes/openimmo/class-admin-page.php` in `maybe_save()` im `$data`-Block (vor der portal-foreach), nach `cron_time`:

```php
'notification_email' => sanitize_email( wp_unslash( $_POST['notification_email'] ?? '' ) ),
```

- [ ] **Step 3: AdminPage::render() — Input-Feld in „Allgemein"**

In der bestehenden „Allgemein"-Tabelle, nach der `cron_time`-Zeile:

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

- [ ] **Step 4: PHP-Lint**

```bash
php -l includes/openimmo/class-settings.php
php -l includes/openimmo/class-admin-page.php
```

- [ ] **Step 5: Commit**

```bash
git add includes/openimmo/class-settings.php includes/openimmo/class-admin-page.php
git commit -m "feat(openimmo): add notification_email setting"
```

---

## Task 3: EmailNotifier

**Files:**
- Create: `includes/openimmo/class-email-notifier.php`

**Ziel:** Klasse für Sync-Fehler-Mails mit Throttling.

- [ ] **Step 1: EmailNotifier anlegen**

`includes/openimmo/class-email-notifier.php`:

```php
<?php
/**
 * Sendet Sync-Fehler-Notifications per E-Mail mit Throttling.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo;

defined( 'ABSPATH' ) || exit;

class EmailNotifier {

	private const TRANSIENT_PREFIX = 'immo_oi_mail_sent_';
	private const TRANSIENT_TTL    = DAY_IN_SECONDS;

	/**
	 * @return bool true wenn versendet, false wenn throttled oder Fehler.
	 */
	public function notify_sync_error( string $portal, string $direction, string $summary, array $details = array() ): bool {
		$key = self::TRANSIENT_PREFIX . $portal . '_' . $direction;
		if ( get_transient( $key ) ) {
			return false;
		}

		$to      = $this->recipient();
		$subject = sprintf(
			'[%s] OpenImmo-Sync-Fehler: %s (%s)',
			get_bloginfo( 'name' ),
			$portal,
			$direction
		);
		$body    = $this->build_body( $portal, $direction, $summary, $details );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$sent = wp_mail( $to, $subject, $body, $headers );
		if ( $sent ) {
			set_transient( $key, time(), self::TRANSIENT_TTL );
		}
		return (bool) $sent;
	}

	private function recipient(): string {
		$settings = Settings::get();
		$email    = (string) ( $settings['notification_email'] ?? '' );
		if ( '' === $email || ! is_email( $email ) ) {
			return (string) get_option( 'admin_email' );
		}
		return $email;
	}

	private function build_body( string $portal, string $direction, string $summary, array $details ): string {
		$lines = array(
			'Site:      ' . site_url(),
			'Portal:    ' . $portal,
			'Direction: ' . $direction,
			'Time:      ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC',
			'',
			'Summary:',
			$summary,
			'',
			'Details:',
			(string) wp_json_encode( $details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'',
			'— Immo Manager',
		);
		return implode( "\n", $lines );
	}
}
```

- [ ] **Step 2: PHP-Lint**

```bash
php -l includes/openimmo/class-email-notifier.php
```

- [ ] **Step 3: Commit**

```bash
git add includes/openimmo/class-email-notifier.php
git commit -m "feat(openimmo): add EmailNotifier with throttling"
```

---

## Task 4: ExportService + SftpPuller dispatch_finish-Refactor

**Files:**
- Modify: `includes/openimmo/export/class-export-service.php`
- Modify: `includes/openimmo/sftp/class-sftp-puller.php`

**Ziel:** Bei `status='error'` automatisch `EmailNotifier::notify_sync_error()` triggern. `finish()`-Aufrufe werden durch `dispatch_finish()`-Wrapper ersetzt.

- [ ] **Step 1: ExportService::dispatch_finish() einführen**

In `includes/openimmo/export/class-export-service.php` neue private Methode neben `finish()`:

```php
private function dispatch_finish( string $portal_key, int $sync_id, string $status, string $summary, string $zip_path, int $count, array $details ): array {
	$result = $this->finish( $sync_id, $status, $summary, $zip_path, $count, $details );
	if ( 'error' === $status ) {
		\ImmoManager\Plugin::instance()->get_openimmo_email_notifier()
			->notify_sync_error( $portal_key, 'export', $summary, $details );
	}
	return $result;
}
```

- [ ] **Step 2: ExportService — alle finish()-Aufrufe ersetzen**

Suche alle `return $this->finish( $sync_id, ... )`-Stellen in `run()` und ersetze sie durch `return $this->dispatch_finish( $portal_key, $sync_id, ... )`.

Konkret: in `run()` gibt es ~5–6 Stellen. Bei jeder `return $this->finish(`-Zeile in der Methode `run()`:

Find pattern:
```php
return $this->finish( $sync_id, 'error', '...', $target, 0, array(...) );
```

Replace:
```php
return $this->dispatch_finish( $portal_key, $sync_id, 'error', '...', $target, 0, array(...) );
```

Alle Stati (`'error'`, `'success'`, `'partial'`, `'skipped'`) sollten den dispatch nutzen — der Mail-Trigger filtert intern auf `error`. Das vereinfacht die Refactoring-Konsistenz: pauschal alle `return $this->finish(` → `return $this->dispatch_finish( $portal_key,`.

**Hinweis:** Im top-level catch-block am Ende von `run()` ist `$portal_key` verfügbar (Method-Parameter). Den catch-block-Aufruf auch umstellen.

- [ ] **Step 3: SftpPuller — dispatch_finish + dispatch_result**

In `includes/openimmo/sftp/class-sftp-puller.php` zwei neue private Methoden neben den existierenden `finish()` und `result()`:

```php
private function dispatch_finish( string $portal_key, int $sync_id, string $status, string $summary, array $counts, array $errors ): array {
	$result = $this->finish( $sync_id, $status, $summary, $counts, $errors );
	if ( 'error' === $status ) {
		\ImmoManager\Plugin::instance()->get_openimmo_email_notifier()
			->notify_sync_error( $portal_key, 'pull', $summary, array(
				'counts' => $counts,
				'errors' => $errors,
			) );
	}
	return $result;
}

private function dispatch_result( string $portal_key, string $status, string $summary, array $counts, int $sync_id ): array {
	$result = $this->result( $status, $summary, $counts, $sync_id );
	if ( 'error' === $status ) {
		\ImmoManager\Plugin::instance()->get_openimmo_email_notifier()
			->notify_sync_error( $portal_key, 'pull', $summary, array( 'counts' => $counts ) );
	}
	return $result;
}
```

- [ ] **Step 4: SftpPuller::pull() — Calls ersetzen**

In `pull()` alle `return $this->finish( $sync_id, ... )`-Stellen → `return $this->dispatch_finish( $portal_key, $sync_id, ... )`.

Alle `return $this->result( ... )`-Stellen → `return $this->dispatch_result( $portal_key, ... )`.

`$portal_key` ist als Method-Parameter überall im Scope verfügbar.

- [ ] **Step 5: PHP-Lint**

```bash
php -l includes/openimmo/export/class-export-service.php
php -l includes/openimmo/sftp/class-sftp-puller.php
```

- [ ] **Step 6: Commit**

```bash
git add includes/openimmo/export/class-export-service.php includes/openimmo/sftp/class-sftp-puller.php
git commit -m "feat(openimmo): wire EmailNotifier into ExportService and SftpPuller"
```

---

## Task 5: CronScheduler HOOK_DAILY_CLEANUP + Plugin-Lazy-Getter

**Files:**
- Modify: `includes/openimmo/class-cron-scheduler.php`
- Modify: `includes/class-plugin.php`

**Ziel:** Dritter Cron-Hook für Cleanup (täglich 04:00) und 2 neue Lazy-Getter im Plugin-Singleton.

- [ ] **Step 1: HOOK_DAILY_CLEANUP-Konstante + Constructor**

In `includes/openimmo/class-cron-scheduler.php` neben `HOOK_DAILY_SYNC` und `HOOK_HOURLY_PULL`:

```php
public const HOOK_DAILY_CLEANUP = 'immo_manager_openimmo_daily_cleanup';
```

Constructor erweitern:

```php
public function __construct() {
	add_action( self::HOOK_DAILY_SYNC,    array( $this, 'run_daily_sync' ) );
	add_action( self::HOOK_HOURLY_PULL,   array( $this, 'run_hourly_pull' ) );
	add_action( self::HOOK_DAILY_CLEANUP, array( $this, 'run_daily_cleanup' ) );
}
```

- [ ] **Step 2: on_activation() ergänzen**

Nach den existierenden `wp_schedule_event`-Aufrufen für `HOOK_DAILY_SYNC` und `HOOK_HOURLY_PULL`:

```php
if ( ! wp_next_scheduled( self::HOOK_DAILY_CLEANUP ) ) {
	$first = strtotime( 'tomorrow 04:00:00' );
	if ( false === $first ) {
		$first = time() + DAY_IN_SECONDS;
	}
	wp_schedule_event( $first, 'daily', self::HOOK_DAILY_CLEANUP );
}
```

- [ ] **Step 3: on_deactivation() ergänzen**

Nach den existierenden Clear-Calls:

```php
$timestamp_cleanup = wp_next_scheduled( self::HOOK_DAILY_CLEANUP );
if ( $timestamp_cleanup ) {
	wp_unschedule_event( $timestamp_cleanup, self::HOOK_DAILY_CLEANUP );
}
wp_clear_scheduled_hook( self::HOOK_DAILY_CLEANUP );
```

- [ ] **Step 4: run_daily_cleanup()-Methode**

Nach `run_hourly_pull()`:

```php
/**
 * Daily-Cleanup-Callback. Räumt lokale ZIPs, SFTP-Dirs, verwaiste Attachments und alte Conflicts auf.
 *
 * @return void
 */
public function run_daily_cleanup(): void {
	\ImmoManager\Plugin::instance()->get_openimmo_retention_cleaner()->run_all();
}
```

- [ ] **Step 5: Plugin-Singleton — 2 neue Properties**

In `includes/class-plugin.php` neben den existierenden `$openimmo_*`-Properties:

```php
/**
 * OpenImmo Retention-Cleaner.
 *
 * @var \ImmoManager\OpenImmo\RetentionCleaner|null
 */
private $openimmo_retention_cleaner = null;

/**
 * OpenImmo E-Mail-Notifier.
 *
 * @var \ImmoManager\OpenImmo\EmailNotifier|null
 */
private $openimmo_email_notifier = null;
```

- [ ] **Step 6: Plugin-Singleton — 2 neue Lazy-Getter**

Vor der schließenden `}` der Klasse, nach den existing `get_openimmo_*`-Methoden:

```php
/**
 * OpenImmo Retention-Cleaner abrufen (Lazy Loading).
 *
 * @return \ImmoManager\OpenImmo\RetentionCleaner
 */
public function get_openimmo_retention_cleaner(): \ImmoManager\OpenImmo\RetentionCleaner {
	if ( null === $this->openimmo_retention_cleaner ) {
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-settings.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/sftp/class-sftp-client.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-retention-cleaner.php';
		$this->openimmo_retention_cleaner = new \ImmoManager\OpenImmo\RetentionCleaner();
	}
	return $this->openimmo_retention_cleaner;
}

/**
 * OpenImmo E-Mail-Notifier abrufen (Lazy Loading).
 *
 * @return \ImmoManager\OpenImmo\EmailNotifier
 */
public function get_openimmo_email_notifier(): \ImmoManager\OpenImmo\EmailNotifier {
	if ( null === $this->openimmo_email_notifier ) {
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-settings.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-email-notifier.php';
		$this->openimmo_email_notifier = new \ImmoManager\OpenImmo\EmailNotifier();
	}
	return $this->openimmo_email_notifier;
}
```

- [ ] **Step 7: PHP-Lint**

```bash
php -l includes/openimmo/class-cron-scheduler.php
php -l includes/class-plugin.php
```

- [ ] **Step 8: Commit**

```bash
git add includes/openimmo/class-cron-scheduler.php includes/class-plugin.php
git commit -m "feat(openimmo): add daily-cleanup cron hook and lazy getters"
```

---

## Task 6: README.md

**Files:**
- Create: `README.md`

**Ziel:** User-Doku im Plugin-Root.

- [ ] **Step 1: README.md anlegen**

`README.md` im Plugin-Root mit folgendem Inhalt:

```markdown
# Immo Manager

WordPress-Plugin für Immobilien-Management mit OpenImmo-Schnittstelle (willhaben.at + ImmobilienScout24.at).

## Voraussetzungen

- WordPress 5.9+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- OpenImmo-1.2.7-XSD-Datei in `vendor-schemas/openimmo-1.2.7/openimmo.xsd`

## Installation

1. Plugin-Verzeichnis nach `wp-content/plugins/` kopieren.
2. `vendor-schemas/openimmo-1.2.7/openimmo.xsd` von openimmo.de herunterladen und ablegen.
3. Plugin im WP-Admin aktivieren — DB-Tabellen werden automatisch angelegt.
4. Einstellungen unter „Immo Manager → OpenImmo" konfigurieren.

## Cron-Hooks

Das Plugin schedulet 3 Cron-Hooks beim Aktivieren:

| Hook | Frequenz | Zweck |
|---|---|---|
| `immo_manager_openimmo_daily_sync` | täglich (Settings: `cron_time`) | Export + SFTP-Push pro aktivem Portal |
| `immo_manager_openimmo_hourly_pull` | stündlich | SFTP-Inbox-Check pro Portal mit `inbox_path` |
| `immo_manager_openimmo_daily_cleanup` | täglich 04:00 | Retention-Cleanup |

**Wichtig:** Bei Code-Updates Plugin einmal deaktivieren + reaktivieren, damit neue Hooks registriert werden.

## OpenImmo-Settings

Submenü „Immo Manager → OpenImmo":

### Allgemein
- **Schnittstelle aktiv:** globaler Master-Switch.
- **Cron-Zeit:** Uhrzeit für den täglichen Push (HH:MM).
- **Notification-E-Mail:** für Fehler-Benachrichtigungen. Leer = WP-Admin-E-Mail.

### Pro Portal (willhaben + immoscout24_at)
- **Aktiv:** Portal-Switch.
- **SFTP-Verbindung:** Host, Port, User, Passwort (verschlüsselt gespeichert).
- **Remote-Verzeichnis:** Push-Pfad (z.B. `/upload/`).
- **Inbox-Pfad:** Pull-Pfad (z.B. `/inbox/`). Leer = kein Pull.
- **Anbieter-Daten:** anbieternr, firma, openimmo_anid, lizenzkennung, regi_id, technische E-Mail, Anbieter-E-Mail, Anbieter-Telefon, Impressum.
- **Buttons:** SFTP-Verbindung testen, Letztes ZIP jetzt hochladen, Inbox jetzt prüfen, Jetzt exportieren.

## Property-Backend

Pro Property-Edit-Seite gibt's eine Sidebar-Box „OpenImmo-Export":
- Checkbox „willhaben.at" — Property in willhaben-Export aufnehmen.
- Checkbox „ImmobilienScout24.at" — analog.

Nur Properties mit `Status=verfügbar` UND mind. einem aktivierten Portal werden tatsächlich exportiert.

## Konflikt-Queue

Submenü „Immo Manager → OpenImmo-Konflikte" zeigt alle Listings, bei denen ein Import einen bestehenden Datensatz ändern würde:
- **Bestand behalten:** Property unverändert lassen.
- **Eingehende übernehmen:** Property-Meta + Bilder werden ersetzt.
- **Bulk-Reject:** mehrere Konflikte gleichzeitig auf „Bestand behalten" setzen.

## Retention

Hardcoded Defaults im täglichen Cleanup-Cron:
- Lokale Export-ZIPs: 60 Tage
- SFTP `processed/` und `error/`: 30 Tage
- Verwaiste Attachments aus rejected Conflicts: 14 Tage
- Resolved Conflicts: 90 Tage

## Test-Workflow

1. **Verbindung testen:** OpenImmo-Settings → „SFTP-Verbindung testen" pro Portal.
2. **Property anlegen:** Status auf „verfügbar", Adresse + Preis + Bilder. Eines der Portal-Häkchen setzen.
3. **Export testen:** OpenImmo-Settings → „Jetzt exportieren". Notice zeigt ZIP-Pfad + Listings-Count.
4. **ZIP inspizieren:** `wp-content/uploads/openimmo/exports/<portal>/<filename>.zip` öffnen → enthält `openimmo.xml` und `images/`.
5. **XSD validieren:** `xmllint --schema vendor-schemas/openimmo-1.2.7/openimmo.xsd <(unzip -p .../openimmo.xml)`.
6. **Upload testen:** „Letztes ZIP jetzt hochladen" → Datei erscheint im Portal-SFTP unter `<remote_path>/`.
7. **Pull testen:** ZIP in Portal-Inbox legen, „Inbox jetzt prüfen" → ZIP wird heruntergeladen, importiert, in `<inbox>/processed/` verschoben.

## Datenbank-Tabellen

| Tabelle | Zweck |
|---|---|
| `wp_immo_units` | Wohneinheiten innerhalb von Bauprojekten |
| `wp_immo_inquiries` | Kontaktanfragen |
| `wp_immo_sync_log` | Log aller Push/Pull/Import-Läufe |
| `wp_immo_conflicts` | Konflikt-Queue |

DB-Version: 1.5.0. Migrations laufen automatisch beim Plugin-Load.

## Phasen-Status

- ✅ Phase 0 — Foundation (DB, Settings, Cron-Stub)
- ✅ Phase 1 — Export-Mapping (Plugin → OpenImmo XML 1.2.7)
- ✅ Phase 2 — SFTP-Push-Transport
- ✅ Phase 3 — Import-Parsing (XML → Plugin)
- ✅ Phase 4 — SFTP-Pull-Cron + manueller Upload
- ✅ Phase 5 — Konflikt-Behandlung (Approval-UI)
- ⏸️ Phase 6 — Portal-Anpassungen (aufgeschoben, wartet auf willhaben/IS24-Doku)
- ✅ Phase 7 — Polish (Retention, E-Mail, README)

## Bekannte Limitierungen

- Phase 6 ist offen. Aktuell wird ein generisches OpenImmo-1.2.7-XML ohne portal-spezifische Anpassungen erzeugt. Bei willhaben/IS24-Validierungs-Fehlern → Konflikt mit Provider klären, ggf. Mapper-Anpassung in `includes/openimmo/export/class-mapper.php`.
- SSH-Key-Authentifizierung wird (noch) nicht unterstützt — nur Username + Passwort.
- Heizungsart wird als Freitext im Element `sonstige_heizungsart` exportiert, nicht als Enum-Mapping (Phase 6 wird das verfeinern).
- Kontaktname-Splitting via Whitespace — Firmennamen werden ungenau in vorname/nachname getrennt.
- Bulk-Approve in der Konflikt-Queue ist bewusst nicht implementiert (nur Bulk-Reject).

## Spec- und Plan-Doku

Architektur und Design pro Phase: `docs/superpowers/specs/`. Implementierungspläne: `docs/superpowers/plans/`.
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: add user README"
```

---

## Task 7: Akzeptanz + Memory-Update

**Files:** keine

- [ ] **Step 1: Verifikation**

- [ ] PHP-Lint sauber für alle Dateien
- [ ] Plugin-Reaktivierung — `wp cron event list` zeigt 3 Hooks (`daily_sync`, `hourly_pull`, `daily_cleanup`)
- [ ] Settings-Seite zeigt Notification-E-Mail-Feld in „Allgemein"
- [ ] Notification leer + Fehler erzwingen → Mail an `admin_email`
- [ ] Notification gesetzt + Fehler erzwingen → Mail an spezielle Adresse
- [ ] Lokale ZIP rückdatieren auf 90d, `wp cron event run immo_manager_openimmo_daily_cleanup` → ZIP gelöscht
- [ ] SFTP-File auf 60d rückdatieren, Cleanup → File in processed/error gelöscht
- [ ] Attachment ohne post_parent + Hash-Meta + älter 14d, Cleanup → Attachment gelöscht
- [ ] Resolved Conflict älter 90d, Cleanup → DB-Row weg
- [ ] Normales User-Attachment ohne Hash-Meta → bleibt unverändert
- [ ] Push-Fehler erzwingen → 1 Mail
- [ ] Sofort nochmal triggern → keine zweite Mail (Throttle)
- [ ] `delete_transient('immo_oi_mail_sent_willhaben_export')`, neuer Trigger → wieder Mail
- [ ] Pull-Fehler triggern → 1 Mail mit `direction='pull'`
- [ ] Push-Mail + Pull-Mail am gleichen Tag → beide kommen
- [ ] Erfolgs-Lauf → keine Mail
- [ ] `README.md` im Plugin-Root vorhanden

- [ ] **Step 2: Memory-Update**

In `~/.claude/projects/.../memory/project_openimmo.md` Phase-7-Status auf „erledigt" setzen. Phase 6 bleibt aufgeschoben.

- [ ] **Step 3: Bereitschaftsmeldung**

Ergebnis an User: „Phase 7 fertig. Branch ist bereit für PR/Push. Phase 6 bleibt offen bis Portal-Doku vorliegt."

---

## Self-Review (vom Plan-Autor durchgeführt)

**Spec-Coverage-Check:**

| Spec-Anforderung | Abgedeckt in |
|---|---|
| `RetentionCleaner` mit 4 Cleanup-Methoden | T1 |
| `SftpClient::stat()` für mtime-Lookup | T1 |
| `Settings` `notification_email`-Default | T2 |
| `AdminPage` Sanitize + UI-Feld | T2 |
| `EmailNotifier` mit Throttling | T3 |
| `ExportService::dispatch_finish` | T4 |
| `SftpPuller::dispatch_finish` + `dispatch_result` | T4 |
| `CronScheduler::HOOK_DAILY_CLEANUP` | T5 |
| Plugin-Lazy-Getter für Cleaner + Notifier | T5 |
| README.md | T6 |
| Manuelle Verifikation | T7 |
| Out-of-Scope-Liste | nicht implementiert (korrekt) |

**Placeholder-Scan:** Keine TBD/TODO. Alle Code-Blöcke vollständig.

**Type-Konsistenz:**
- `RetentionCleaner::run_all()` Rückgabe-Shape (`{local_zips, sftp_dirs, orphan_attachments, resolved_conflicts}`) konsistent zwischen Definition (T1) und Konsum in `log()`.
- `EmailNotifier::notify_sync_error( $portal, $direction, $summary, $details )` Signatur konsistent zwischen Definition (T3) und Aufruf in ExportService/SftpPuller (T4).
- `SftpClient::stat()` Rückgabe-Shape (`{size, mtime}|null`) konsistent zwischen Definition (T1) und Konsum in `RetentionCleaner::delete_old_in_sftp_dir` (T1).
- `HOOK_DAILY_CLEANUP`-Konstante konsistent zwischen Definition (T5) und Verifikation (T7).

**Bekannte Vereinfachungen:**
- `cleanup_orphaned_attachments()` löscht nur Attachments mit `_immo_openimmo_image_hash`-Meta — schützt vor versehentlichem Löschen von User-Attachments.
- `dispatch_finish`-Wrapper triggert E-Mail nur bei `'error'` — `'partial'` und `'success'` bleiben mailfrei.

Plan ist konsistent.
