# OpenImmo Phase 4 – SFTP-Pull Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Periodisch (1×/h) das Inbox-Verzeichnis jedes aktiven Portals auf eingehende ZIPs prüfen, herunterladen, durch die Phase-3-Import-Pipeline schicken, in `inbox/processed/` bzw. `inbox/error/` verschieben. Plus pro Portal manueller Settings-Button „Inbox jetzt prüfen".

**Architecture:** Phase-2-`SftpClient` bekommt 3 neue Public-Methoden (list/get/rename). Neue `SftpPuller`-Klasse orchestriert pro Portal: connect → list → for-each-zip { download → ImportService::run → mv processed/error }. Neuer Cron-Hook `hourly_pull` parallel zum bestehenden `daily_sync`. AdminPage bekommt neues Inbox-Settings-Feld + Button + erweiterten Test-Connection.

**Tech Stack:** WordPress 5.9+, PHP 7.4+, `phpseclib3\Net\SFTP` (`nlist`, `get`, `rename`), WP Transients (Lock), WP-Cron.

**Spec:** `docs/superpowers/specs/2026-04-29-openimmo-phase-4-pull-design.md`

**Phasen-Kontext:** Phase 4 von 7. Phase 0/1/2/3 abgeschlossen.

**Hinweis zu Tests:** Wie bisher keine PHPUnit-Infrastruktur. Validierung manuell: PHP-Lint, Plugin-Reaktivierung, Buttons im Backend, `wp cron event run`. Voraussetzung Akzeptanz: erreichbare SFTP-Test-Server mit Schreibrechten.

---

## File Structure

| Aktion | Datei | Verantwortung |
|---|---|---|
| Modify | `includes/openimmo/sftp/class-sftp-client.php` | + `list_directory`, `get`, `rename` |
| Modify | `includes/openimmo/sftp/class-sftp-uploader.php` | `test_connection()` Inbox-Stufe |
| Create | `includes/openimmo/sftp/class-sftp-puller.php` | Pull-Workflow |
| Modify | `includes/openimmo/class-settings.php` | `portal_defaults()` + `inbox_path` |
| Modify | `includes/openimmo/class-cron-scheduler.php` | + `HOOK_HOURLY_PULL`, `run_hourly_pull` |
| Modify | `includes/openimmo/class-admin-page.php` | Inbox-Field + Save, Pull-Now-Button + AJAX + JS |
| Modify | `includes/class-plugin.php` | Lazy-Getter `get_openimmo_sftp_puller()` |

---

## Task 1: SftpClient erweitern + `inbox_path`-Setting

**Files:**
- Modify: `includes/openimmo/sftp/class-sftp-client.php`
- Modify: `includes/openimmo/class-settings.php`
- Modify: `includes/openimmo/class-admin-page.php`

**Ziel:** Foundation für Phase 4. SftpClient bekommt 3 neue Methoden, Settings bekommt `inbox_path`-Default + UI-Feld + Save.

- [ ] **Step 1: SftpClient — `list_directory`, `get`, `rename`**

In `includes/openimmo/sftp/class-sftp-client.php`, vor dem `last_error()`-Methode (am Ende der Klasse):

```php
/**
 * Listet Dateien (keine Verzeichnisse) in einem Remote-Pfad.
 *
 * @return array<int,string> Filenamen ohne Pfad-Präfix; leer bei Fehler oder leerem Verzeichnis.
 */
public function list_directory( string $remote_path ): array {
	if ( null === $this->sftp ) {
		$this->error = 'not connected';
		return array();
	}
	try {
		$entries = $this->sftp->nlist( $remote_path );
		if ( ! is_array( $entries ) ) {
			return array();
		}
		$files = array();
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$full = rtrim( $remote_path, '/' ) . '/' . $entry;
			if ( $this->sftp->is_dir( $full ) ) {
				continue;
			}
			$files[] = $entry;
		}
		return $files;
	} catch ( \Throwable $e ) {
		$this->error = $e->getMessage();
		return array();
	}
}

/**
 * Lädt eine Remote-Datei lokal herunter.
 */
public function get( string $remote_path, string $local_path ): bool {
	if ( null === $this->sftp ) {
		$this->error = 'not connected';
		return false;
	}
	try {
		$ok = $this->sftp->get( $remote_path, $local_path );
		if ( false === $ok ) {
			$this->error = 'get failed';
		}
		return false !== $ok;
	} catch ( \Throwable $e ) {
		$this->error = $e->getMessage();
		return false;
	}
}

/**
 * Verschiebt/Renamed eine Remote-Datei.
 */
public function rename( string $from, string $to ): bool {
	if ( null === $this->sftp ) {
		$this->error = 'not connected';
		return false;
	}
	try {
		$ok = $this->sftp->rename( $from, $to );
		if ( ! $ok ) {
			$this->error = 'rename failed';
		}
		return (bool) $ok;
	} catch ( \Throwable $e ) {
		$this->error = $e->getMessage();
		return false;
	}
}
```

- [ ] **Step 2: Settings::portal_defaults() um `inbox_path` ergänzen**

In `includes/openimmo/class-settings.php` in `portal_defaults()` — nach `'remote_path' => '/'` und vor `'last_sync' => ''`:

```php
'inbox_path'     => '',
```

- [ ] **Step 3: AdminPage::maybe_save() Sanitize-Eintrag ergänzen**

In `includes/openimmo/class-admin-page.php` `maybe_save()` im `$data['portals'][ $key ] = array(...)`-Block — nach `'remote_path' => sanitize_text_field( $portal['remote_path'] ?? '/' ),`:

```php
'inbox_path'    => sanitize_text_field( $portal['inbox_path'] ?? '' ),
```

- [ ] **Step 4: AdminPage::render() neues Input-Feld in der SFTP-Tabelle**

In `render()` innerhalb der Portal-`foreach` und in der ersten `<table class="form-table">` (SFTP-Block), nach der `remote_path`-Zeile (`<tr><th>Remote-Verzeichnis</th>...</tr>`):

```php
<tr>
	<th><?php esc_html_e( 'Inbox-Pfad (für Pull)', 'immo-manager' ); ?></th>
	<td>
		<input type="text"
				class="regular-text"
				name="portals[<?php echo esc_attr( $key ); ?>][inbox_path]"
				value="<?php echo esc_attr( $portal['inbox_path'] ?? '' ); ?>"
				placeholder="/inbox/">
		<p class="description">
			<?php esc_html_e( 'Leer lassen = kein Pull. Cron prüft dieses Verzeichnis stündlich.', 'immo-manager' ); ?>
		</p>
	</td>
</tr>
```

- [ ] **Step 5: PHP-Lint**

```bash
php -l includes/openimmo/sftp/class-sftp-client.php
php -l includes/openimmo/class-settings.php
php -l includes/openimmo/class-admin-page.php
```

**Erwartet:** „No syntax errors detected" für alle.

- [ ] **Step 6: Commit**

```bash
git add includes/openimmo/sftp/class-sftp-client.php includes/openimmo/class-settings.php includes/openimmo/class-admin-page.php
git commit -m "feat(openimmo): extend SftpClient and add inbox_path setting"
```

---

## Task 2: SftpUploader::test_connection() Inbox-Stufe

**Files:**
- Modify: `includes/openimmo/sftp/class-sftp-uploader.php`

**Ziel:** Bestehender Test-Connection-Button prüft zusätzlich `inbox_path` (mkdir_p + list_directory).

- [ ] **Step 1: test_connection() Inbox-Stufe ergänzen**

In `includes/openimmo/sftp/class-sftp-uploader.php` in `test_connection()` — direkt vor der finalen `$client->disconnect();`-und-`return ok=true`-Sequenz. Den Block, der mit `$test_path = rtrim(...)` beginnt und mit `$client->delete( $test_path );` endet, behalten. Direkt danach (vor `$client->disconnect();`):

Suche im File:

```php
		$client->delete( $test_path );  // best-effort
		$client->disconnect();

		return array(
			'ok'      => true,
			'message' => 'OK',
			'mkdir'   => true,
			'write'   => true,
		);
	}
```

Ersetze durch:

```php
		$client->delete( $test_path );  // best-effort

		// Phase 4: Inbox-Pfad-Test (nur wenn konfiguriert).
		$inbox_ok = true;
		if ( ! empty( $portal['inbox_path'] ) ) {
			if ( ! $client->mkdir_p( $portal['inbox_path'] ) ) {
				$err = $client->last_error();
				$client->disconnect();
				return array(
					'ok'      => false,
					'message' => 'inbox mkdir failed: ' . $err,
					'mkdir'   => true,
					'write'   => true,
					'inbox'   => false,
				);
			}
			// Listdir-Test (Erfolg auch wenn leer).
			$client->list_directory( $portal['inbox_path'] );
			if ( '' !== $client->last_error() ) {
				$err = $client->last_error();
				$client->disconnect();
				return array(
					'ok'      => false,
					'message' => 'inbox list failed: ' . $err,
					'mkdir'   => true,
					'write'   => true,
					'inbox'   => false,
				);
			}
		}

		$client->disconnect();
		return array(
			'ok'      => true,
			'message' => empty( $portal['inbox_path'] ) ? 'OK' : 'OK (inkl. Inbox-Pfad)',
			'mkdir'   => true,
			'write'   => true,
			'inbox'   => ! empty( $portal['inbox_path'] ),
		);
	}
```

- [ ] **Step 2: PHP-Lint**

```bash
php -l includes/openimmo/sftp/class-sftp-uploader.php
```

- [ ] **Step 3: Commit**

```bash
git add includes/openimmo/sftp/class-sftp-uploader.php
git commit -m "feat(openimmo): extend test_connection with inbox-path check"
```

---

## Task 3: SftpPuller (Pull-Workflow)

**Files:**
- Create: `includes/openimmo/sftp/class-sftp-puller.php`

**Ziel:** Orchestrator für den Pull-Workflow pro Portal. Reuse von SftpClient (Phase 2) + ImportService (Phase 3). Lock pro Portal.

- [ ] **Step 1: SftpPuller anlegen**

`includes/openimmo/sftp/class-sftp-puller.php`:

```php
<?php
/**
 * High-level SFTP-Pull-Workflow inkl. Lock.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Sftp;

use ImmoManager\OpenImmo\Settings;
use ImmoManager\OpenImmo\SyncLog;

defined( 'ABSPATH' ) || exit;

/**
 * Pull-Workflow für ein Portal: Inbox listen, ZIPs holen, importieren, verschieben.
 */
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
	public function pull( string $portal_key ): array {
		$counts = array( 'pulled' => 0, 'imported' => 0, 'conflicts' => 0, 'errors' => 0 );
		$errors = array();

		$settings = Settings::get();
		$portal   = $settings['portals'][ $portal_key ] ?? null;

		if ( null === $portal || empty( $portal['enabled'] ) ) {
			return $this->result( 'skipped', 'portal disabled', $counts, 0 );
		}
		if ( empty( $portal['inbox_path'] ) ) {
			return $this->result( 'skipped', 'no inbox_path configured', $counts, 0 );
		}
		if ( empty( $portal['sftp_host'] ) || empty( $portal['sftp_user'] ) ) {
			return $this->result( 'error', 'sftp credentials incomplete', $counts, 0 );
		}

		if ( ! $this->acquire_lock( $portal_key ) ) {
			return $this->result( 'skipped', 'lock held — another pull running', $counts, 0 );
		}

		$sync_id = SyncLog::start( $portal_key, 'pull' );
		if ( 0 === $sync_id ) {
			error_log( '[immo-manager openimmo] SyncLog::start failed for pull ' . $portal_key );
		}

		$upload_dir = wp_get_upload_dir();
		$stage_dir  = $upload_dir['basedir'] . '/openimmo/pull-staging/' . $portal_key . '-' . time();
		wp_mkdir_p( $stage_dir );

		$client = new SftpClient();
		try {
			if ( ! $client->connect( $portal['sftp_host'], (int) $portal['sftp_port'] ) ) {
				return $this->finish( $sync_id, 'error', 'connect failed: ' . $client->last_error(), $counts, $errors );
			}
			if ( ! $client->login( $portal['sftp_user'], $portal['sftp_password'] ) ) {
				return $this->finish( $sync_id, 'error', 'login failed: ' . $client->last_error(), $counts, $errors );
			}

			$inbox     = rtrim( $portal['inbox_path'], '/' );
			$processed = $inbox . '/processed';
			$error_dir = $inbox . '/error';

			if ( ! $client->mkdir_p( $inbox ) ) {
				return $this->finish( $sync_id, 'error', 'inbox mkdir failed: ' . $client->last_error(), $counts, $errors );
			}
			$client->mkdir_p( $processed );  // best-effort
			$client->mkdir_p( $error_dir );  // best-effort

			$files = $client->list_directory( $inbox );
			$zips  = array();
			foreach ( $files as $f ) {
				if ( preg_match( '/\.zip$/i', $f ) ) {
					$zips[] = $f;
				}
			}

			if ( empty( $zips ) ) {
				return $this->finish( $sync_id, 'skipped', 'no files in inbox', $counts, $errors );
			}

			$importer = \ImmoManager\Plugin::instance()->get_openimmo_import_service();

			foreach ( $zips as $filename ) {
				$remote_zip = $inbox . '/' . $filename;
				$local_zip  = $stage_dir . '/' . $filename;

				if ( ! $client->get( $remote_zip, $local_zip ) ) {
					++$counts['errors'];
					$errors[] = array( 'file' => $filename, 'message' => 'download failed: ' . $client->last_error() );
					continue;
				}
				++$counts['pulled'];

				$import = null;
				try {
					$import = $importer->run( $local_zip );
				} catch ( \Throwable $e ) {
					$import = array( 'status' => 'error', 'summary' => 'import threw: ' . $e->getMessage(), 'counts' => array() );
				}

				@unlink( $local_zip );  // phpcs:ignore WordPress.PHP.NoSilencedErrors

				if ( in_array( $import['status'], array( 'success', 'partial' ), true ) ) {
					++$counts['imported'];
					$counts['conflicts'] += isset( $import['counts']['conflicts'] ) ? (int) $import['counts']['conflicts'] : 0;
					$client->rename( $remote_zip, $processed . '/' . $filename );
					if ( '' !== $client->last_error() ) {
						error_log( '[immo-manager openimmo] processed-rename failed for ' . $filename . ': ' . $client->last_error() );
					}
				} else {
					++$counts['errors'];
					$errors[] = array(
						'file'    => $filename,
						'message' => isset( $import['summary'] ) ? (string) $import['summary'] : 'unknown import error',
					);
					$client->rename( $remote_zip, $error_dir . '/' . $filename );
					if ( '' !== $client->last_error() ) {
						error_log( '[immo-manager openimmo] error-rename failed for ' . $filename . ': ' . $client->last_error() );
					}
				}
			}

			if ( 0 === $counts['errors'] ) {
				$status = 'success';
			} elseif ( $counts['imported'] > 0 ) {
				$status = 'partial';
			} else {
				$status = 'error';
			}
			$summary = sprintf(
				'%d gepullt, %d importiert, %d Konflikte, %d Fehler',
				$counts['pulled'],
				$counts['imported'],
				$counts['conflicts'],
				$counts['errors']
			);
			return $this->finish( $sync_id, $status, $summary, $counts, $errors );

		} catch ( \Throwable $e ) {
			return $this->finish( $sync_id, 'error', 'unexpected: ' . $e->getMessage(), $counts, $errors );
		} finally {
			$client->disconnect();
			$this->rrmdir( $stage_dir );
			$this->release_lock( $portal_key );
		}
	}

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

	/**
	 * Result-Helper für early-returns ohne Sync-Log.
	 */
	private function result( string $status, string $summary, array $counts, int $sync_id ): array {
		return array(
			'status'  => $status,
			'summary' => $summary,
			'counts'  => $counts,
			'sync_id' => $sync_id,
		);
	}

	/**
	 * Finish-Helper mit SyncLog-Update.
	 */
	private function finish( int $sync_id, string $status, string $summary, array $counts, array $errors ): array {
		if ( 0 !== $sync_id ) {
			SyncLog::finish(
				$sync_id,
				$status,
				$summary,
				array(
					'counts' => $counts,
					'errors' => $errors,
				),
				(int) $counts['imported']
			);
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
php -l includes/openimmo/sftp/class-sftp-puller.php
```

- [ ] **Step 3: Commit**

```bash
git add includes/openimmo/sftp/class-sftp-puller.php
git commit -m "feat(openimmo): add SftpPuller workflow for inbox processing"
```

---

## Task 4: CronScheduler — `HOOK_HOURLY_PULL`

**Files:**
- Modify: `includes/openimmo/class-cron-scheduler.php`

**Ziel:** Zweiter Cron-Hook stündlich, der pro aktivem Portal mit `inbox_path` den `SftpPuller` triggert.

- [ ] **Step 1: HOOK_HOURLY_PULL-Konstante + Constructor**

In `includes/openimmo/class-cron-scheduler.php`, neben `HOOK_DAILY_SYNC`:

```php
public const HOOK_DAILY_SYNC  = 'immo_manager_openimmo_daily_sync';
public const HOOK_HOURLY_PULL = 'immo_manager_openimmo_hourly_pull';
```

(falls die Konstante anders heißt — die genaue Form aus dem File übernehmen, nur die zweite Konstante ergänzen)

Constructor erweitern:

```php
public function __construct() {
	add_action( self::HOOK_DAILY_SYNC,  array( $this, 'run_daily_sync' ) );
	add_action( self::HOOK_HOURLY_PULL, array( $this, 'run_hourly_pull' ) );
}
```

- [ ] **Step 2: on_activation() — Hourly-Hook scheduled**

Find existing `on_activation()`:

```php
public static function on_activation(): void {
	if ( ! wp_next_scheduled( self::HOOK_DAILY_SYNC ) ) {
		// existing schedule
		wp_schedule_event( /* ... */, 'daily', self::HOOK_DAILY_SYNC );
	}
}
```

Add inside (after the daily_sync block):

```php
	if ( ! wp_next_scheduled( self::HOOK_HOURLY_PULL ) ) {
		wp_schedule_event( time() + 60, 'hourly', self::HOOK_HOURLY_PULL );
	}
```

- [ ] **Step 3: on_deactivation() — Hourly-Hook clearen**

Existing:

```php
public static function on_deactivation(): void {
	$timestamp = wp_next_scheduled( self::HOOK_DAILY_SYNC );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, self::HOOK_DAILY_SYNC );
	}
	wp_clear_scheduled_hook( self::HOOK_DAILY_SYNC );
}
```

Add to:

```php
public static function on_deactivation(): void {
	$timestamp = wp_next_scheduled( self::HOOK_DAILY_SYNC );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, self::HOOK_DAILY_SYNC );
	}
	wp_clear_scheduled_hook( self::HOOK_DAILY_SYNC );

	$timestamp_pull = wp_next_scheduled( self::HOOK_HOURLY_PULL );
	if ( $timestamp_pull ) {
		wp_unschedule_event( $timestamp_pull, self::HOOK_HOURLY_PULL );
	}
	wp_clear_scheduled_hook( self::HOOK_HOURLY_PULL );
}
```

- [ ] **Step 4: run_hourly_pull() Methode**

Direkt nach `run_daily_sync()`:

```php
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

- [ ] **Step 5: PHP-Lint**

```bash
php -l includes/openimmo/class-cron-scheduler.php
```

- [ ] **Step 6: Commit**

```bash
git add includes/openimmo/class-cron-scheduler.php
git commit -m "feat(openimmo): add hourly-pull cron hook"
```

---

## Task 5: AdminPage Pull-Now-Button + AJAX + JS + Plugin-Lazy-Getter

**Files:**
- Modify: `includes/openimmo/class-admin-page.php`
- Modify: `includes/class-plugin.php`

**Ziel:** Pro Portal manueller Button, AJAX-Handler, Inline-JS, Singleton-Getter.

- [ ] **Step 1: Plugin::get_openimmo_sftp_puller()**

In `includes/class-plugin.php` neben `$openimmo_sftp_uploader`-Property:

```php
/**
 * OpenImmo SFTP-Puller.
 *
 * @var \ImmoManager\OpenImmo\Sftp\SftpPuller|null
 */
private $openimmo_sftp_puller = null;
```

Methode (vor der schließenden `}` der Klasse, nach `get_openimmo_sftp_uploader()`):

```php
/**
 * OpenImmo SFTP-Puller abrufen (Lazy Loading).
 *
 * @return \ImmoManager\OpenImmo\Sftp\SftpPuller
 */
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

- [ ] **Step 2: AdminPage Constructor erweitern**

Im Constructor von `includes/openimmo/class-admin-page.php`:

```php
add_action( 'wp_ajax_immo_manager_openimmo_pull_now', array( $this, 'ajax_pull_now' ) );
```

(neben den anderen `wp_ajax_*`-Hooks)

- [ ] **Step 3: ajax_pull_now() implementieren**

Als neue Methode in derselben Klasse, z.B. nach `ajax_upload_now`:

```php
/**
 * AJAX-Handler: Inbox eines Portals jetzt prüfen (manueller Pull).
 */
public function ajax_pull_now(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
	}
	check_ajax_referer( 'immo_manager_openimmo_pull_now', 'nonce' );

	$portal_key = isset( $_POST['portal'] ) ? sanitize_key( wp_unslash( $_POST['portal'] ) ) : '';
	if ( ! in_array( $portal_key, array( 'willhaben', 'immoscout24_at' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Unbekanntes Portal.', 'immo-manager' ) ), 400 );
	}

	$puller = \ImmoManager\Plugin::instance()->get_openimmo_sftp_puller();
	$result = $puller->pull( $portal_key );

	if ( in_array( $result['status'], array( 'success', 'partial', 'skipped' ), true ) ) {
		$c = $result['counts'];
		wp_send_json_success( array(
			'message' => sprintf(
				__( 'Pull fertig: %1$d ZIPs gezogen, %2$d importiert, %3$d Konflikte, %4$d Fehler', 'immo-manager' ),
				$c['pulled'],
				$c['imported'],
				$c['conflicts'],
				$c['errors']
			),
			'counts'  => $c,
		) );
	}
	wp_send_json_error( array( 'message' => $result['summary'] ) );
}
```

- [ ] **Step 4: render() — Pull-Now-Button im SFTP-Button-Block**

Im SFTP-Button-Block pro Portal (dort wo aktuell „SFTP-Verbindung testen" und „Letztes ZIP jetzt hochladen" stehen, mit Status-Span `.immo-openimmo-sftp-status`), den dritten Button ergänzen:

Suche im File:

```php
<button type="button"
		class="button button-secondary immo-openimmo-upload-now"
		data-portal="<?php echo esc_attr( $key ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'immo_manager_openimmo_upload_now' ) ); ?>">
	<?php esc_html_e( 'Letztes ZIP jetzt hochladen', 'immo-manager' ); ?>
</button>
<span class="immo-openimmo-sftp-status" style="margin-left:10px;"></span>
```

Ersetze durch (neuer Button davor eingefügt):

```php
<button type="button"
		class="button button-secondary immo-openimmo-upload-now"
		data-portal="<?php echo esc_attr( $key ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'immo_manager_openimmo_upload_now' ) ); ?>">
	<?php esc_html_e( 'Letztes ZIP jetzt hochladen', 'immo-manager' ); ?>
</button>
<button type="button"
		class="button button-secondary immo-openimmo-pull-now"
		data-portal="<?php echo esc_attr( $key ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'immo_manager_openimmo_pull_now' ) ); ?>">
	<?php esc_html_e( 'Inbox jetzt prüfen', 'immo-manager' ); ?>
</button>
<span class="immo-openimmo-sftp-status" style="margin-left:10px;"></span>
```

- [ ] **Step 5: Inline-JS für den neuen Button**

Im bestehenden `<script>`-Block (innerhalb der IIFE), nach dem Upload-Now-Handler und vor dem Import-Now-Handler einfügen:

```javascript
$(document).on('click', '.immo-openimmo-pull-now', function(e){
	e.preventDefault();
	var $btn  = $(this);
	var $stat = $btn.siblings('.immo-openimmo-sftp-status');
	$btn.prop('disabled', true);
	$stat.css('color', '').text('<?php echo esc_js( __( 'Prüfe Inbox…', 'immo-manager' ) ); ?>');
	$.post(ajaxurl, {
		action: 'immo_manager_openimmo_pull_now',
		portal: $btn.data('portal'),
		nonce:  $btn.data('nonce')
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
git commit -m "feat(openimmo): add 'inbox now' pull button with AJAX handler"
```

---

## Task 6: Akzeptanz + Memory-Update

**Files:** keine

**Ziel:** Phase 4 ist nachweislich produktionsfertig.

- [ ] **Step 1: Vollständige Verifikation**

- [ ] PHP-Lint sauber für alle modifizierten/neuen Dateien
- [ ] Plugin deaktivieren + reaktivieren — `wp cron event list` zeigt sowohl `immo_manager_openimmo_daily_sync` als auch `immo_manager_openimmo_hourly_pull`
- [ ] Settings-Seite zeigt pro Portal: neues Input-Feld „Inbox-Pfad (für Pull)" + Button „Inbox jetzt prüfen"
- [ ] Einstellung `inbox_path` leer → Button → Notice "no inbox_path configured" (status `skipped`)
- [ ] Test-Connection erweitert: korrekte Credentials + `inbox_path` gesetzt → Grün „OK (inkl. Inbox-Pfad)"
- [ ] Test-Connection ohne Schreibrechte auf Inbox → Rot „inbox mkdir failed: ..."
- [ ] Pull leer: Inbox auf SFTP leer, Button → Notice "no files in inbox" (`skipped`)
- [ ] Pull happy-path: 1 ZIP in Inbox legen, Button → grün, 1 gepullt, 1 importiert; ZIP weg, in `<inbox>/processed/<filename>` da
- [ ] Pull mit Conflict: ZIP enthält existing Listing modifiziert → importiert mit 1 Conflict, ZIP in `processed/`
- [ ] Pull mit defektem ZIP: kaputtes ZIP in Inbox → counts.errors=1, ZIP in `<inbox>/error/<filename>`
- [ ] Lock-Test: manuell `set_transient('immo_oi_pull_lock_willhaben', time(), 600)`, Button → Notice "lock held" (`skipped`)
- [ ] Cron-Lauf: `wp cron event run immo_manager_openimmo_hourly_pull` mit aktivem Portal + `inbox_path` → Pull läuft
- [ ] Stage-Cleanup: `wp-content/uploads/openimmo/pull-staging/` ist nach jedem Lauf leer
- [ ] Doppel-Import-Schutz: gleiches ZIP zweimal pullen — beim 2. Lauf andere Inbox (1. ZIP in processed/), kein Doppel-Import
- [ ] SyncLog-Eintrag mit `direction='pull'`, `details.counts.{pulled, imported, conflicts, errors}`
- [ ] Keine PHP-Notices/Warnings mit `WP_DEBUG=true`

- [ ] **Step 2: Bei Bedarf Bugfix-Commits**

- [ ] **Step 3: Memory-Update**

In `~/.claude/projects/.../memory/project_openimmo.md` Phase-4-Status auf „erledigt" setzen, Hinweis auf Phase 5 (Konflikt-Behandlung-UI).

- [ ] **Step 4: Bereitschaftsmeldung**

Ergebnis an User: „Phase 4 fertig. Bereit für Phase 5 (Konflikt-Behandlung)? Erst nach OK weiter."

---

## Self-Review (vom Plan-Autor durchgeführt)

**Spec-Coverage-Check:**

| Spec-Anforderung | Abgedeckt in |
|---|---|
| `inbox_path` Settings-Default + Save + UI | T1 |
| SftpClient::list_directory + get + rename | T1 |
| SftpUploader::test_connection Inbox-Stufe | T2 |
| SftpPuller-Workflow + Lock + Retry-Logic-equivalent (kein Retry, da Pull idempotent durch processed/) | T3 |
| HOOK_HOURLY_PULL + run_hourly_pull | T4 |
| Plugin-Lazy-Getter | T5 |
| Pull-Now-Button + AJAX + JS | T5 |
| Manuelle Verifikation | T6 |
| Out-of-Scope-Liste | nicht im Plan implementiert (korrekt) |

**Placeholder-Scan:** Keine TBD/TODO. Alle Code-Blöcke vollständig.

**Type-Konsistenz:**
- `SftpClient::list_directory` Rückgabe `array<int,string>` konsistent zwischen Definition (T1) und Konsumation in `SftpPuller::pull()` (T3) und `test_connection()` (T2).
- `SftpPuller::pull()` Rückgabe-Shape (`status, summary, counts, sync_id`) konsistent zwischen Definition (T3) und AJAX-Handler-Auswertung (T5).
- `counts`-Schema (`pulled, imported, conflicts, errors`) konsistent zwischen Definition (T3), AJAX-Response (T5), Akzeptanz-Liste (T6).
- Cron-Konstante `HOOK_HOURLY_PULL` konsistent zwischen Definition (T4) und Verifikation (T6).

**Bekannte Vereinfachungen:**
- Kein Retry pro ZIP (anders als bei Push in Phase 2): Pull ist idempotent durch `processed/`-Konvention, retry beim nächsten Cron-Lauf greift automatisch.
- `mkdir_p( processed )` und `mkdir_p( error )` als best-effort — falls fehlschlagend, wird `rename()` später failen und das ZIP bleibt liegen, aber Import lief korrekt.

Plan ist konsistent.
