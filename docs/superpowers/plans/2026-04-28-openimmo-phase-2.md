# OpenImmo Phase 2 – SFTP-Transport Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aus Phase 1 generierte ZIPs per SFTP an die Portale hochladen — inline im selben Cron-Lauf direkt nach dem Export, plus zwei manuelle Settings-Buttons („Verbindung testen", „Letztes ZIP jetzt hochladen"). 3-Versuch-Retry, Transient-Lock pro Portal.

**Architecture:** Klar getrennte Layer unter `includes/openimmo/sftp/`. `SftpClient` ist ein dünner phpseclib-Wrapper (low-level). `SftpUploader` orchestriert den Upload-Workflow inkl. Retry, Lock, last_sync-Update. `ExportService` (Phase 1) ruft Uploader nach erfolgreichem ZIP-Pack auf. AdminPage (Phase 1) bekommt zwei zusätzliche AJAX-Handler.

**Tech Stack:** WordPress 5.9+, PHP 7.4+, `phpseclib3\Net\SFTP` (Composer-Package aus Phase 0), WP Transients API, jQuery (für UI).

**Spec:** `docs/superpowers/specs/2026-04-28-openimmo-phase-2-sftp-design.md`

**Phasen-Kontext:** Phase 2 von 7. Phase 0 (Foundation) und Phase 1 (Export-Mapping) abgeschlossen.

**Hinweis zu Tests:** Wie in Phase 0/1 keine PHPUnit-Infrastruktur. Validierung manuell via PHP-Lint, Plugin-Reaktivierung, AJAX-Buttons im Backend.

---

## File Structure

| Aktion | Datei | Verantwortung |
|---|---|---|
| Create | `includes/openimmo/sftp/class-sftp-client.php` | phpseclib-Wrapper: connect/login/mkdir_p/put/delete |
| Create | `includes/openimmo/sftp/class-sftp-uploader.php` | Upload-Workflow + Retry + Lock + Test-Connection |
| Modify | `includes/openimmo/class-settings.php` | static `set_last_sync()` |
| Modify | `includes/openimmo/export/class-export-service.php` | Inline SFTP-Aufruf + Status-Promotion |
| Modify | `includes/class-plugin.php` | Lazy-Getter `get_openimmo_sftp_uploader()` |
| Modify | `includes/openimmo/class-admin-page.php` | 2 AJAX-Handler + 2 Buttons + Inline-JS |

---

## Task 1: SftpClient (low-level phpseclib-Wrapper)

**Files:**
- Create: `includes/openimmo/sftp/class-sftp-client.php`

**Ziel:** Dünner Wrapper um `phpseclib3\Net\SFTP` mit klar definierter API. Kein Wissen über Plugins, Portale, Locks oder Retry.

- [ ] **Step 1: Datei anlegen**

`includes/openimmo/sftp/class-sftp-client.php`:

```php
<?php
/**
 * SFTP-Wrapper basierend auf phpseclib3.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Sftp;

defined( 'ABSPATH' ) || exit;

/**
 * Low-level SFTP-Client. Kein Wissen über Portale, Locks oder Retry.
 */
class SftpClient {

	/**
	 * @var \phpseclib3\Net\SFTP|null
	 */
	private $sftp = null;

	/**
	 * Letzte Fehlermeldung von phpseclib bzw. eigene Fehlerlage.
	 */
	private string $error = '';

	/**
	 * Verbindung aufbauen.
	 */
	public function connect( string $host, int $port = 22, int $timeout = 10 ): bool {
		try {
			$this->sftp = new \phpseclib3\Net\SFTP( $host, $port, $timeout );
			return true;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			$this->sftp  = null;
			return false;
		}
	}

	/**
	 * Login mit Username/Passwort.
	 */
	public function login( string $user, string $password ): bool {
		if ( null === $this->sftp ) {
			$this->error = 'not connected';
			return false;
		}
		try {
			$ok = $this->sftp->login( $user, $password );
			if ( ! $ok ) {
				$this->error = 'login rejected';
			}
			return (bool) $ok;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Verzeichnis rekursiv anlegen (analog `mkdir -p`).
	 */
	public function mkdir_p( string $remote_path ): bool {
		if ( null === $this->sftp ) {
			$this->error = 'not connected';
			return false;
		}
		try {
			// phpseclib3 unterstützt Recursive direkt: mkdir( $dir, $mode = -1, $recursive = false ).
			$ok = $this->sftp->mkdir( $remote_path, -1, true );
			// `mkdir` gibt false zurück wenn das Verzeichnis bereits existiert — das ist OK.
			if ( ! $ok && ! $this->sftp->is_dir( $remote_path ) ) {
				$this->error = 'mkdir failed';
				return false;
			}
			return true;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Lokale Datei hochladen.
	 */
	public function put( string $local_path, string $remote_path ): bool {
		if ( null === $this->sftp ) {
			$this->error = 'not connected';
			return false;
		}
		if ( ! file_exists( $local_path ) ) {
			$this->error = 'local file missing: ' . $local_path;
			return false;
		}
		try {
			$ok = $this->sftp->put( $remote_path, $local_path, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE );
			if ( ! $ok ) {
				$this->error = 'put failed';
			}
			return (bool) $ok;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Inline-Bytes als Datei schreiben (für Test-Connection).
	 */
	public function put_string( string $contents, string $remote_path ): bool {
		if ( null === $this->sftp ) {
			$this->error = 'not connected';
			return false;
		}
		try {
			$ok = $this->sftp->put( $remote_path, $contents );
			if ( ! $ok ) {
				$this->error = 'put_string failed';
			}
			return (bool) $ok;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Datei löschen.
	 */
	public function delete( string $remote_path ): bool {
		if ( null === $this->sftp ) {
			$this->error = 'not connected';
			return false;
		}
		try {
			$ok = $this->sftp->delete( $remote_path );
			if ( ! $ok ) {
				$this->error = 'delete failed';
			}
			return (bool) $ok;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Verbindung schließen.
	 */
	public function disconnect(): void {
		if ( null !== $this->sftp ) {
			try {
				$this->sftp->disconnect();
			} catch ( \Throwable $e ) {
				// Best-effort.
			}
			$this->sftp = null;
		}
	}

	/**
	 * Letzte Fehlermeldung (auch nach disconnect verfügbar).
	 */
	public function last_error(): string {
		return $this->error;
	}
}
```

- [ ] **Step 2: PHP-Lint**

```bash
php -l includes/openimmo/sftp/class-sftp-client.php
```

**Erwartet:** „No syntax errors detected".

- [ ] **Step 3: Commit**

```bash
git add includes/openimmo/sftp/class-sftp-client.php
git commit -m "feat(openimmo): add SftpClient wrapper around phpseclib3"
```

---

## Task 2: SftpUploader + Settings::set_last_sync()

**Files:**
- Create: `includes/openimmo/sftp/class-sftp-uploader.php`
- Modify: `includes/openimmo/class-settings.php`

**Ziel:** High-level Upload-Workflow mit Retry, Lock und Test-Connection. `Settings`-Klasse bekommt eine zusätzliche static-Methode zur Persistenz von `last_sync` ohne den Save-Pfad zu durchlaufen.

- [ ] **Step 1: Settings::set_last_sync() ergänzen**

In `includes/openimmo/class-settings.php`, vor der schließenden `}` der Klasse:

```php
/**
 * Schreibt den last_sync-Timestamp eines Portals direkt ins Storage,
 * ohne über save() zu gehen (kein erneutes Sanitisieren aller Felder).
 *
 * @param string $portal_key  'willhaben' oder 'immoscout24_at'.
 * @param string $timestamp   MySQL-Datumformat (current_time('mysql')).
 */
public static function set_last_sync( string $portal_key, string $timestamp ): void {
	$stored = get_option( self::OPTION_KEY, array() );
	if ( ! isset( $stored['portals'][ $portal_key ] ) ) {
		return;
	}
	$stored['portals'][ $portal_key ]['last_sync'] = $timestamp;
	update_option( self::OPTION_KEY, $stored );
}
```

- [ ] **Step 2: SftpUploader anlegen**

`includes/openimmo/sftp/class-sftp-uploader.php`:

```php
<?php
/**
 * High-level SFTP-Upload-Workflow inkl. Retry und Lock.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Sftp;

use ImmoManager\OpenImmo\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Upload-Workflow für ein OpenImmo-ZIP zu einem Portal.
 */
class SftpUploader {

	private const LOCK_TRANSIENT_PREFIX = 'immo_oi_sftp_lock_';
	private const LOCK_TTL_SECONDS      = 600;
	private const RETRY_BACKOFFS        = array( 0, 5, 15 );

	/**
	 * Upload eines ZIPs ans SFTP des Portals.
	 *
	 * @return array{
	 *   status: string,           // 'success' | 'error' | 'skipped'
	 *   summary: string,
	 *   attempts: int,
	 *   remote_path: string,
	 *   error: string|null
	 * }
	 */
	public function upload( string $zip_path, string $portal_key ): array {
		$settings = Settings::get();
		$portal   = $settings['portals'][ $portal_key ] ?? null;

		if ( null === $portal || empty( $portal['enabled'] ) ) {
			return $this->result( 'error', 'portal disabled or unknown', 0, '', 'invalid_config' );
		}
		if ( empty( $portal['sftp_host'] ) || empty( $portal['sftp_user'] ) ) {
			return $this->result( 'error', 'sftp credentials incomplete', 0, '', 'invalid_config' );
		}
		if ( ! file_exists( $zip_path ) ) {
			return $this->result( 'error', 'local zip missing', 0, '', 'local_zip_missing' );
		}

		if ( ! $this->acquire_lock( $portal_key ) ) {
			return $this->result( 'skipped', 'lock held — another upload running', 0, '', null );
		}

		$attempts = 0;
		$last_err = null;
		$ok       = false;
		$remote   = '';

		try {
			foreach ( self::RETRY_BACKOFFS as $backoff ) {
				if ( $backoff > 0 ) {
					sleep( $backoff );
				}
				++$attempts;
				$client = new SftpClient();
				$try    = $this->try_upload_once( $client, $portal, $zip_path );
				if ( $try['ok'] ) {
					$ok     = true;
					$remote = $try['remote_path'];
					break;
				}
				$last_err = $try['error'];
			}
		} finally {
			$this->release_lock( $portal_key );
		}

		if ( ! $ok ) {
			return $this->result( 'error', sprintf( 'upload failed after %d attempt(s)', $attempts ), $attempts, '', $last_err );
		}

		Settings::set_last_sync( $portal_key, current_time( 'mysql' ) );
		return $this->result( 'success', sprintf( 'uploaded after %d attempt(s)', $attempts ), $attempts, $remote, null );
	}

	/**
	 * Verbindungstest mit Testdatei + Cleanup.
	 *
	 * @return array{ ok: bool, message: string, mkdir: bool, write: bool }
	 */
	public function test_connection( string $portal_key ): array {
		$settings = Settings::get();
		$portal   = $settings['portals'][ $portal_key ] ?? null;
		if ( null === $portal ) {
			return array( 'ok' => false, 'message' => 'unknown portal', 'mkdir' => false, 'write' => false );
		}
		if ( empty( $portal['sftp_host'] ) || empty( $portal['sftp_user'] ) ) {
			return array( 'ok' => false, 'message' => 'credentials incomplete', 'mkdir' => false, 'write' => false );
		}

		$client = new SftpClient();
		if ( ! $client->connect( $portal['sftp_host'], (int) $portal['sftp_port'] ) ) {
			return array( 'ok' => false, 'message' => 'connect failed: ' . $client->last_error(), 'mkdir' => false, 'write' => false );
		}
		if ( ! $client->login( $portal['sftp_user'], $portal['sftp_password'] ) ) {
			$err = $client->last_error();
			$client->disconnect();
			return array( 'ok' => false, 'message' => 'login failed: ' . $err, 'mkdir' => false, 'write' => false );
		}
		$mkdir_ok = $client->mkdir_p( $portal['remote_path'] );
		if ( ! $mkdir_ok ) {
			$err = $client->last_error();
			$client->disconnect();
			return array( 'ok' => false, 'message' => 'mkdir failed: ' . $err, 'mkdir' => false, 'write' => false );
		}

		$test_path = rtrim( $portal['remote_path'], '/' ) . '/immo-manager-test-' . time() . '.txt';
		$write_ok  = $client->put_string( 'immo-manager test ' . gmdate( 'c' ), $test_path );
		if ( ! $write_ok ) {
			$err = $client->last_error();
			$client->disconnect();
			return array( 'ok' => false, 'message' => 'write failed: ' . $err, 'mkdir' => true, 'write' => false );
		}

		$client->delete( $test_path );  // best-effort
		$client->disconnect();

		return array(
			'ok'      => true,
			'message' => 'OK',
			'mkdir'   => true,
			'write'   => true,
		);
	}

	/**
	 * Ein Upload-Versuch.
	 *
	 * @return array{ ok: bool, error?: string, remote_path?: string }
	 */
	private function try_upload_once( SftpClient $client, array $portal, string $zip_path ): array {
		if ( ! $client->connect( $portal['sftp_host'], (int) $portal['sftp_port'] ) ) {
			return array( 'ok' => false, 'error' => 'connect failed: ' . $client->last_error() );
		}
		if ( ! $client->login( $portal['sftp_user'], $portal['sftp_password'] ) ) {
			$err = $client->last_error();
			$client->disconnect();
			return array( 'ok' => false, 'error' => 'login failed: ' . $err );
		}
		if ( ! $client->mkdir_p( $portal['remote_path'] ) ) {
			$err = $client->last_error();
			$client->disconnect();
			return array( 'ok' => false, 'error' => 'mkdir failed: ' . $err );
		}
		$remote = rtrim( $portal['remote_path'], '/' ) . '/' . basename( $zip_path );
		if ( ! $client->put( $zip_path, $remote ) ) {
			$err = $client->last_error();
			$client->disconnect();
			return array( 'ok' => false, 'error' => 'put failed: ' . $err );
		}
		$client->disconnect();
		return array( 'ok' => true, 'remote_path' => $remote );
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

	/**
	 * Hilfs-Konstruktor für Result-Arrays.
	 *
	 * @return array{ status: string, summary: string, attempts: int, remote_path: string, error: string|null }
	 */
	private function result( string $status, string $summary, int $attempts, string $remote_path, ?string $error ): array {
		return array(
			'status'      => $status,
			'summary'     => $summary,
			'attempts'    => $attempts,
			'remote_path' => $remote_path,
			'error'       => $error,
		);
	}
}
```

- [ ] **Step 3: PHP-Lint**

```bash
php -l includes/openimmo/class-settings.php
php -l includes/openimmo/sftp/class-sftp-uploader.php
```

**Erwartet:** „No syntax errors detected" für beide.

- [ ] **Step 4: Commit**

```bash
git add includes/openimmo/class-settings.php includes/openimmo/sftp/class-sftp-uploader.php
git commit -m "feat(openimmo): add SftpUploader workflow with retry and lock"
```

---

## Task 3: ExportService-Inline-Trigger + Plugin-Lazy-Getter

**Files:**
- Modify: `includes/openimmo/export/class-export-service.php`
- Modify: `includes/class-plugin.php`

**Ziel:** ExportService ruft nach erfolgreichem ZIP-Pack `SftpUploader::upload()` auf, schreibt das Ergebnis ins SyncLog-`details`-Feld, promotet bei Bedarf den Status auf `'error'`. Plugin-Singleton bekommt Lazy-Getter.

- [ ] **Step 1: Plugin::get_openimmo_sftp_uploader() ergänzen**

In `includes/class-plugin.php`, neben den existierenden OpenImmo-Properties (z.B. `$openimmo_export_service`):

```php
/**
 * SFTP-Uploader (lazy).
 *
 * @var \ImmoManager\OpenImmo\Sftp\SftpUploader|null
 */
private $openimmo_sftp_uploader = null;
```

Und neben den anderen `get_openimmo_*`-Methoden:

```php
/**
 * SFTP-Uploader abrufen (Lazy Loading).
 *
 * @return \ImmoManager\OpenImmo\Sftp\SftpUploader
 */
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

- [ ] **Step 2: ExportService — SFTP-Aufruf nach erfolgreichem ZIP-Pack**

In `includes/openimmo/export/class-export-service.php`, in der `run()`-Methode. Finde den Block direkt nach `$packer->write(...)` und vor dem finalen `return $this->finish( ..., $status, ... );`. Aktuell sieht der Block so aus (Auszug):

```php
$status  = empty( $errors ) ? 'success' : 'partial';
$summary = sprintf( ... );
return $this->finish( $sync_id, $status, $summary, $target, $exported, array(
    'errors'        => $errors,
    'image_errors'  => $img_errors,
    'zip_path'      => $target,
    'openimmo_xml_size_kb' => (int) ( strlen( $builder->to_string() ) / 1024 ),
) );
```

Ergänze VOR diesem `return` einen SFTP-Aufruf-Block:

```php
$status  = empty( $errors ) ? 'success' : 'partial';
$summary = sprintf(
	'%d/%d Listings exportiert%s',
	$exported,
	count( $listings ),
	empty( $errors ) ? '' : sprintf( ', %d übersprungen', count( $errors ) )
);

// SFTP-Upload (Phase 2).
$uploader      = \ImmoManager\Plugin::instance()->get_openimmo_sftp_uploader();
$upload_result = $uploader->upload( $target, $portal_key );
$sftp_details  = array(
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

return $this->finish( $sync_id, $status, $summary, $target, $exported, array(
	'errors'               => $errors,
	'image_errors'         => $img_errors,
	'zip_path'             => $target,
	'openimmo_xml_size_kb' => (int) ( strlen( $builder->to_string() ) / 1024 ),
	'sftp'                 => $sftp_details,
) );
```

- [ ] **Step 3: PHP-Lint**

```bash
php -l includes/class-plugin.php
php -l includes/openimmo/export/class-export-service.php
```

**Erwartet:** „No syntax errors detected" für beide.

- [ ] **Step 4: Commit**

```bash
git add includes/class-plugin.php includes/openimmo/export/class-export-service.php
git commit -m "feat(openimmo): wire ExportService to SftpUploader inline after ZIP pack"
```

---

## Task 4: AdminPage AJAX-Handler + Buttons + JS

**Files:**
- Modify: `includes/openimmo/class-admin-page.php`

**Ziel:** Zwei zusätzliche Buttons pro Portal auf der Settings-Seite — „Verbindung testen" und „Letztes ZIP jetzt hochladen" — mit AJAX-Handlern und Inline-jQuery-Event-Handlern.

- [ ] **Step 1: Constructor um 2 AJAX-Hooks erweitern**

In `includes/openimmo/class-admin-page.php` Constructor:

```php
public function __construct() {
	add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );
	add_action( 'admin_init', array( $this, 'maybe_save' ) );
	add_action( 'wp_ajax_immo_manager_openimmo_export_now',       array( $this, 'ajax_export_now' ) );
	add_action( 'wp_ajax_immo_manager_openimmo_test_connection',  array( $this, 'ajax_test_connection' ) );
	add_action( 'wp_ajax_immo_manager_openimmo_upload_now',       array( $this, 'ajax_upload_now' ) );
}
```

- [ ] **Step 2: ajax_test_connection() implementieren**

In derselben Klasse als neue Methode (z.B. nach `ajax_export_now()`):

```php
/**
 * AJAX-Handler: SFTP-Verbindung testen.
 */
public function ajax_test_connection(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
	}
	check_ajax_referer( 'immo_manager_openimmo_test_connection', 'nonce' );

	$portal_key = isset( $_POST['portal'] ) ? sanitize_key( wp_unslash( $_POST['portal'] ) ) : '';
	if ( ! in_array( $portal_key, array( 'willhaben', 'immoscout24_at' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Unbekanntes Portal.', 'immo-manager' ) ), 400 );
	}

	$uploader = \ImmoManager\Plugin::instance()->get_openimmo_sftp_uploader();
	$result   = $uploader->test_connection( $portal_key );

	if ( $result['ok'] ) {
		wp_send_json_success( array(
			'message' => __( 'Verbindung OK ✓ (mkdir, write+delete erfolgreich)', 'immo-manager' ),
		) );
	}
	wp_send_json_error( array(
		'message' => sprintf( __( 'Verbindung fehlgeschlagen: %s', 'immo-manager' ), $result['message'] ),
	) );
}
```

- [ ] **Step 3: ajax_upload_now() + find_latest_zip() implementieren**

In derselben Klasse:

```php
/**
 * AJAX-Handler: letztes ZIP eines Portals jetzt hochladen.
 */
public function ajax_upload_now(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
	}
	check_ajax_referer( 'immo_manager_openimmo_upload_now', 'nonce' );

	$portal_key = isset( $_POST['portal'] ) ? sanitize_key( wp_unslash( $_POST['portal'] ) ) : '';
	if ( ! in_array( $portal_key, array( 'willhaben', 'immoscout24_at' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Unbekanntes Portal.', 'immo-manager' ) ), 400 );
	}

	$latest_zip = $this->find_latest_zip( $portal_key );
	if ( null === $latest_zip ) {
		wp_send_json_error( array(
			'message' => __( 'Kein ZIP gefunden — zuerst exportieren.', 'immo-manager' ),
		) );
	}

	$uploader = \ImmoManager\Plugin::instance()->get_openimmo_sftp_uploader();
	$result   = $uploader->upload( $latest_zip, $portal_key );

	if ( 'success' === $result['status'] ) {
		wp_send_json_success( array(
			'message' => sprintf(
				__( 'Upload erfolgreich: %1$s (%2$d Versuch%3$s)', 'immo-manager' ),
				$result['remote_path'],
				$result['attempts'],
				1 === (int) $result['attempts'] ? '' : 'e'
			),
		) );
	}
	wp_send_json_error( array( 'message' => $result['summary'] ) );
}

/**
 * Findet das jüngste ZIP eines Portals nach mtime.
 */
private function find_latest_zip( string $portal_key ): ?string {
	$upload_dir = wp_get_upload_dir();
	$dir        = $upload_dir['basedir'] . '/openimmo/exports/' . $portal_key;
	if ( ! is_dir( $dir ) ) {
		return null;
	}
	$files = glob( $dir . '/*.zip' );
	if ( empty( $files ) ) {
		return null;
	}
	usort(
		$files,
		static function ( $a, $b ) {
			return filemtime( $b ) <=> filemtime( $a );
		}
	);
	return $files[0];
}
```

- [ ] **Step 4: render() um Buttons + Status-Span erweitern**

In `render()`, INNERHALB der portal-`foreach`-Schleife, NACH dem existierenden „Jetzt exportieren"-Button-Block aus T9 (Phase 1) — direkt davor oder danach das neue Block einfügen:

```php
<p>
	<button type="button"
			class="button button-secondary immo-openimmo-test-connection"
			data-portal="<?php echo esc_attr( $key ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'immo_manager_openimmo_test_connection' ) ); ?>">
		<?php esc_html_e( 'SFTP-Verbindung testen', 'immo-manager' ); ?>
	</button>
	<button type="button"
			class="button button-secondary immo-openimmo-upload-now"
			data-portal="<?php echo esc_attr( $key ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'immo_manager_openimmo_upload_now' ) ); ?>">
		<?php esc_html_e( 'Letztes ZIP jetzt hochladen', 'immo-manager' ); ?>
	</button>
	<span class="immo-openimmo-sftp-status" style="margin-left:10px;"></span>
</p>
```

- [ ] **Step 5: Inline-JS für die zwei neuen Buttons**

In `render()`, im existierenden `<script>...</script>`-Block (aus T9 Phase 1, vor `</form>`), zwei zusätzliche Event-Handler ergänzen — nach dem bestehenden `.immo-openimmo-export-now`-Handler:

```javascript
$(document).on('click', '.immo-openimmo-test-connection', function(e){
	e.preventDefault();
	var $btn  = $(this);
	var $stat = $btn.siblings('.immo-openimmo-sftp-status');
	$btn.prop('disabled', true);
	$stat.css('color', '').text('<?php echo esc_js( __( 'Teste…', 'immo-manager' ) ); ?>');
	$.post(ajaxurl, {
		action: 'immo_manager_openimmo_test_connection',
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

$(document).on('click', '.immo-openimmo-upload-now', function(e){
	e.preventDefault();
	var $btn  = $(this);
	var $stat = $btn.siblings('.immo-openimmo-sftp-status');
	$btn.prop('disabled', true);
	$stat.css('color', '').text('<?php echo esc_js( __( 'Lade hoch…', 'immo-manager' ) ); ?>');
	$.post(ajaxurl, {
		action: 'immo_manager_openimmo_upload_now',
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
```

**Erwartet:** „No syntax errors detected".

- [ ] **Step 7: Commit**

```bash
git add includes/openimmo/class-admin-page.php
git commit -m "feat(openimmo): add SFTP test-connection and upload-now buttons"
```

---

## Task 5: Akzeptanz-Checkliste

**Files:** keine

**Ziel:** Phase 2 ist nachweislich produktionsfertig (lokal + SFTP-Upload).

- [ ] **Step 1: Vollständige Verifikation**

- [ ] PHP-Lint sauber für alle neuen/modifizierten Dateien
- [ ] Plugin lädt ohne Fatal nach Reaktivierung
- [ ] Settings-Seite zeigt 2 neue Buttons pro Portal: „SFTP-Verbindung testen" + „Letztes ZIP jetzt hochladen"
- [ ] „Verbindung testen" mit korrekten Credentials → grün „Verbindung OK ✓"
- [ ] „Verbindung testen" mit falschem Passwort → rot „login failed: …"
- [ ] „Verbindung testen" mit nicht existierendem `remote_path` → `mkdir -p` legt rekursiv an, danach grün
- [ ] Test-Datei `immo-manager-test-{timestamp}.txt` wird auf SFTP NICHT zurückgelassen
- [ ] „Letztes ZIP jetzt hochladen" mit existierendem ZIP → grün mit Pfad + Versuch-Anzahl
- [ ] „Letztes ZIP jetzt hochladen" ohne ZIP → rot „Kein ZIP gefunden — zuerst exportieren."
- [ ] Inline-Upload via Cron: `wp cron event run immo_manager_openimmo_daily_sync` → ZIP entsteht UND wird hochgeladen
- [ ] SyncLog hat `details.sftp.status='success'`, `details.sftp.attempts >= 1`, `details.sftp.remote_path` mit vollem Pfad
- [ ] Retry-Test: SFTP-Server stoppen während Cron läuft (oder Port absichtlich falsch in Settings) → 3 Versuche, finaler Status `error`, `details.sftp.attempts=3`
- [ ] Lock-Test: `wp option update _transient_immo_oi_sftp_lock_willhaben "$(date +%s)"`, dann Upload-Button → Notice „lock held" (status `skipped`)
- [ ] `last_sync` wird nach erfolgreichem Upload geschrieben (z.B. `wp option get immo_manager_openimmo` zeigt unverschlüsselten last_sync-Wert)
- [ ] `last_sync` bleibt erhalten beim Settings-Save (Phase-1-Fix #12 + neuer set_last_sync greifen ineinander)
- [ ] ZIP bleibt nach erfolgreichem Upload lokal liegen
- [ ] Keine PHP-Notices/Warnings mit `WP_DEBUG=true` während aller Operationen

- [ ] **Step 2: Bei Bedarf Bugfix-Commits**

- [ ] **Step 3: Memory-Update**

In `~/.claude/projects/.../memory/project_openimmo.md` Phase-2-Status auf „erledigt" setzen, Hinweis auf Phase 3 (Import-Parsing).

- [ ] **Step 4: Bereitschaftsmeldung**

Ergebnis an User: „Phase 2 fertig. Bereit für Phase 3 (Import-Parsing)? Erst nach OK weiter."

---

## Self-Review (vom Plan-Autor durchgeführt)

**Spec-Coverage-Check:**

| Spec-Section | Abgedeckt in |
|---|---|
| Klassen-Schnittstellen `SftpClient` | T1 |
| Klassen-Schnittstellen `SftpUploader` | T2 |
| Retry-Loop + try_upload_once | T2 |
| Lock-Mechanismus (acquire/release) | T2 |
| `Settings::set_last_sync()` | T2 |
| ExportService-Wiring + Status-Promotion | T3 |
| Plugin-Singleton Lazy-Getter | T3 |
| AdminPage 2 AJAX-Handler | T4 |
| AdminPage 2 Buttons + JS | T4 |
| Manuelle Verifikations-Liste | T5 |
| Out-of-Scope-Liste | nicht im Plan implementiert (korrekt) |

**Placeholder-Scan:** Keine TBD/TODO/„implement later". Alle Code-Blöcke vollständig.

**Type-Konsistenz:**
- `SftpUploader::upload()` Rückgabe-Shape (`status`, `summary`, `attempts`, `remote_path`, `error`) konsistent zwischen Definition (T2), AJAX-Handler-Auswertung (T4) und ExportService-Auswertung (T3).
- `SftpUploader::test_connection()` Rückgabe-Shape (`ok`, `message`, `mkdir`, `write`) konsistent zwischen Definition (T2) und AJAX-Handler-Auswertung (T4).
- Lock-Konstanten (`LOCK_TRANSIENT_PREFIX`, `LOCK_TTL_SECONDS`, `RETRY_BACKOFFS`) sind privat in SftpUploader — kein Cross-File-Risk.

**Bekannte Vereinfachungen (Spec-konform):**
- Kein atomares Test-and-Set Lock — WP-Transient-Race-Window akzeptiert.
- Kein SSH-Key-Support — Phase 6.
- Kein Resume bei Connection-Drop — phpseclib-Limit.

Plan ist konsistent.
