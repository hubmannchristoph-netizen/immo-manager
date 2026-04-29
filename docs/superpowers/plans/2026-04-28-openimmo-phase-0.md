# OpenImmo Phase 0 – Foundation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Foundation für die OpenImmo-Schnittstelle: Composer-Setup mit phpseclib, zwei neue DB-Tabellen, Klassen-Skelett, WP-Admin-Settings-Seite mit Portal-Credentials, Cron-Registrierung. Noch KEIN Export- oder Import-Code – die Foundation muss eigenständig laufen, ohne dass irgendwo „echte" OpenImmo-Logik aktiv ist.

**Architecture:**
- Eigener Namespace `ImmoManager\OpenImmo\*` unter `includes/openimmo/`
- Composer-managed Dependencies (`phpseclib/phpseclib` für SFTP in Phase 2)
- DB-Migration via existierenden `Database::install()`-Mechanismus (DB-Version 1.2.4 → 1.3.0)
- Settings-Untermenü unter „Immo Manager", verschlüsselte Credential-Speicherung
- Cron registriert sich auf Plugin-Aktivierung, Hook-Callback ist in Phase 0 ein No-Op-Stub

**Tech Stack:** WordPress 5.9+, PHP 7.4+, Composer, phpseclib 3.x, dbDelta, WP-Cron.

**Phasen-Kontext:** Diese ist Phase 0 von 7 (siehe `memory/project_openimmo.md`). Phasen 1-7 bauen auf der Foundation auf.

**Hinweis zu Tests:** Wie beim Schema-Plan keine PHPUnit-Infrastruktur. Validierung manuell: PHP-Lint via `php -l`, Plugin-Aktivierung im WP-Backend ohne Fatal, neue Tabellen mit `SHOW TABLES`, Settings-Seite öffnet/speichert, `wp cron event list` zeigt Hook.

---

## File Structure

| Aktion | Datei | Verantwortung |
|---|---|---|
| Create | `composer.json` | Composer-Manifest, requires phpseclib |
| Create | `vendor/` (committed) | phpseclib-Code (~150 Files, ca. 1.5 MB) |
| Modify | `immo-manager.php` | `vendor/autoload.php` requiren |
| Modify | `includes/class-database.php` | DB_VERSION hochziehen, 2 neue Tabellen |
| Create | `includes/openimmo/class-settings.php` | Plugin-Optionen mit Encryption |
| Create | `includes/openimmo/class-sync-log.php` | Repository für `immo_sync_log` |
| Create | `includes/openimmo/class-conflicts.php` | Repository für `immo_conflicts` |
| Create | `includes/openimmo/class-admin-page.php` | Submenü + Settings-UI |
| Create | `includes/openimmo/class-cron-scheduler.php` | Cron-Hook, in Phase 0 No-Op-Stub |
| Modify | `includes/class-plugin.php` | Lazy Getter + Init der OpenImmo-Klassen |

---

## Task 1: Composer + phpseclib

**Files:**
- Create: `composer.json`
- Create: `vendor/` (Output von `composer install`)
- Modify: `immo-manager.php`

**Ziel:** phpseclib ist verfügbar im Plugin, kein Fatal beim Laden.

- [ ] **Step 1: composer.json anlegen**

```json
{
  "name": "immo-manager/immo-manager",
  "description": "Immobilien-Plugin für WordPress mit OpenImmo-Schnittstelle.",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=7.4",
    "phpseclib/phpseclib": "^3.0"
  },
  "config": {
    "optimize-autoloader": true,
    "sort-packages": true
  }
}
```

- [ ] **Step 2: composer install ausführen**

```bash
composer install --no-dev --optimize-autoloader
```

`vendor/` entsteht. Erste Größenprüfung – sollte unter 5 MB liegen.

- [ ] **Step 3: vendor/ wird NICHT gitignored**

Falls `.gitignore` einen `vendor/`-Eintrag hat, entfernen. WordPress-Plugins distributieren vendor/ üblicherweise mit, weil End-User keinen Composer haben.

- [ ] **Step 4: Autoloader im Plugin-Bootstrap einbinden**

In `immo-manager.php` direkt nach den `defined`-Guards:

```php
// Composer Autoloader.
$autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    require_once $autoload;
}
```

Conditional, damit das Plugin auch ohne `vendor/` (z. B. während Development) nicht hart fatal-fehlschlägt.

- [ ] **Step 5: PHP-Lint + Smoke-Test**

```bash
php -l immo-manager.php
```

Plugin im Backend deaktivieren und reaktivieren – kein Fatal Error.

**Erwartet:** Plugin lädt sauber.

- [ ] **Step 6: Commit**

```bash
git add composer.json vendor/ immo-manager.php
git commit -m "feat(openimmo): add composer setup with phpseclib for SFTP support"
```

---

## Task 2: Datenbank-Migration (zwei neue Tabellen)

**Files:**
- Modify: `includes/class-database.php`

**Ziel:** Beim nächsten Plugin-Load existieren `{prefix}immo_sync_log` und `{prefix}immo_conflicts`.

- [ ] **Step 1: DB_VERSION hochziehen**

```php
public const DB_VERSION = '1.3.0';
```

- [ ] **Step 2: Tabellennamen-Methoden ergänzen**

In `Database`-Klasse nach `inquiries_table()`:

```php
/**
 * Tabellenname: OpenImmo Sync-Log.
 */
public static function sync_log_table(): string {
    return self::table_prefix() . 'sync_log';
}

/**
 * Tabellenname: OpenImmo Konflikt-Queue.
 */
public static function conflicts_table(): string {
    return self::table_prefix() . 'conflicts';
}
```

- [ ] **Step 3: dbDelta-Statements in install() ergänzen**

Vor dem abschließenden `update_option(...)`:

```php
$sync_log_table  = self::sync_log_table();
$conflicts_table = self::conflicts_table();

$sql_sync_log = "CREATE TABLE {$sync_log_table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    portal VARCHAR(50) NOT NULL DEFAULT '',
    direction VARCHAR(20) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'running',
    summary VARCHAR(500) NOT NULL DEFAULT '',
    details LONGTEXT NULL,
    properties_count INT NOT NULL DEFAULT 0,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY  (id),
    KEY portal (portal),
    KEY direction (direction),
    KEY status (status),
    KEY started_at (started_at)
) {$charset_collate};";

$sql_conflicts = "CREATE TABLE {$conflicts_table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    portal VARCHAR(50) NOT NULL DEFAULT '',
    source_data LONGTEXT NULL,
    local_data LONGTEXT NULL,
    conflict_fields LONGTEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    resolution VARCHAR(20) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL DEFAULT NULL,
    resolved_by BIGINT UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY  (id),
    KEY post_id (post_id),
    KEY portal (portal),
    KEY status (status),
    KEY created_at (created_at)
) {$charset_collate};";

dbDelta( $sql_sync_log );
dbDelta( $sql_conflicts );
```

- [ ] **Step 4: uninstall() erweitern**

```php
$tables = array(
    self::units_table(),
    self::inquiries_table(),
    self::sync_log_table(),
    self::conflicts_table(),
);
```

- [ ] **Step 5: Manueller Verifikationstest**

Plugin deaktivieren → reaktivieren. Mit phpMyAdmin/wp-cli prüfen:

```sql
SHOW TABLES LIKE 'wp_immo_%';
```

**Erwartet:** 4 Tabellen sichtbar (`units`, `inquiries`, `sync_log`, `conflicts`).

- [ ] **Step 6: Commit**

```bash
git add includes/class-database.php
git commit -m "feat(openimmo): add sync_log and conflicts DB tables (DB v1.3.0)"
```

---

## Task 3: Klassen-Skelett (Settings, SyncLog, Conflicts)

**Files:**
- Create: `includes/openimmo/class-settings.php`
- Create: `includes/openimmo/class-sync-log.php`
- Create: `includes/openimmo/class-conflicts.php`

**Ziel:** Drei lade-bare PHP-Klassen, jeweils mit den Repository-Methoden, die in späteren Phasen benötigt werden – aber alle als reine Stubs/Skelette implementiert.

- [ ] **Step 1: Settings-Klasse mit Encryption**

`includes/openimmo/class-settings.php`:

```php
<?php
namespace ImmoManager\OpenImmo;

defined( 'ABSPATH' ) || exit;

/**
 * Verwaltet OpenImmo-Plugin-Optionen inkl. verschlüsselter Portal-Credentials.
 */
class Settings {

    public const OPTION_KEY = 'immo_manager_openimmo';

    /**
     * Default-Struktur.
     */
    public static function defaults(): array {
        return array(
            'enabled'   => false,
            'cron_time' => '03:00',
            'portals'   => array(
                'willhaben'      => self::portal_defaults(),
                'immoscout24_at' => self::portal_defaults(),
            ),
        );
    }

    public static function portal_defaults(): array {
        return array(
            'enabled'        => false,
            'sftp_host'      => '',
            'sftp_port'      => 22,
            'sftp_user'      => '',
            'sftp_password'  => '',
            'remote_path'    => '/',
            'last_sync'      => '',
        );
    }

    public static function get(): array {
        $stored = get_option( self::OPTION_KEY, array() );
        $merged = wp_parse_args( $stored, self::defaults() );
        // Passwörter on-the-fly entschlüsseln.
        foreach ( $merged['portals'] as $key => $portal ) {
            if ( ! empty( $portal['sftp_password'] ) ) {
                $merged['portals'][ $key ]['sftp_password'] = self::decrypt( $portal['sftp_password'] );
            }
        }
        return $merged;
    }

    public static function save( array $data ): void {
        $sanitized = self::sanitize( $data );
        // Passwörter verschlüsselt speichern.
        foreach ( $sanitized['portals'] as $key => $portal ) {
            if ( ! empty( $portal['sftp_password'] ) ) {
                $sanitized['portals'][ $key ]['sftp_password'] = self::encrypt( $portal['sftp_password'] );
            }
        }
        update_option( self::OPTION_KEY, $sanitized );
    }

    private static function sanitize( array $data ): array {
        // Vollständige Sanitization in Task 4 (Admin-Page) – hier nur Skelett.
        return wp_parse_args( $data, self::defaults() );
    }

    private static function encrypt( string $plain ): string {
        $key    = self::derive_key();
        $iv     = openssl_random_pseudo_bytes( 16 );
        $cipher = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $cipher );
    }

    private static function decrypt( string $encoded ): string {
        $raw     = base64_decode( $encoded, true );
        if ( false === $raw || strlen( $raw ) < 17 ) {
            return '';
        }
        $iv      = substr( $raw, 0, 16 );
        $cipher  = substr( $raw, 16 );
        $key     = self::derive_key();
        $plain   = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return false === $plain ? '' : $plain;
    }

    private static function derive_key(): string {
        // wp_salt liefert konsistenten Schlüssel pro Installation.
        return hash( 'sha256', wp_salt( 'auth' ), true );
    }
}
```

- [ ] **Step 2: SyncLog-Repository**

`includes/openimmo/class-sync-log.php`:

```php
<?php
namespace ImmoManager\OpenImmo;

use ImmoManager\Database;

defined( 'ABSPATH' ) || exit;

class SyncLog {

    public static function start( string $portal, string $direction ): int {
        global $wpdb;
        $wpdb->insert(
            Database::sync_log_table(),
            array(
                'portal'    => $portal,
                'direction' => $direction,
                'status'    => 'running',
                'started_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );
        return (int) $wpdb->insert_id;
    }

    public static function finish( int $id, string $status, string $summary, array $details = array(), int $count = 0 ): bool {
        global $wpdb;
        $result = $wpdb->update(
            Database::sync_log_table(),
            array(
                'status'           => $status,
                'summary'          => $summary,
                'details'          => wp_json_encode( $details ),
                'properties_count' => $count,
                'finished_at'      => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%d', '%s' ),
            array( '%d' )
        );
        return false !== $result;
    }

    public static function recent( int $limit = 50 ): array {
        global $wpdb;
        $table = Database::sync_log_table();
        $rows  = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} ORDER BY started_at DESC LIMIT %d", $limit ),
            ARRAY_A
        );
        return is_array( $rows ) ? $rows : array();
    }
}
```

- [ ] **Step 3: Conflicts-Repository**

`includes/openimmo/class-conflicts.php`:

```php
<?php
namespace ImmoManager\OpenImmo;

use ImmoManager\Database;

defined( 'ABSPATH' ) || exit;

class Conflicts {

    public static function add( int $post_id, string $portal, array $source, array $local, array $fields ): int {
        global $wpdb;
        $wpdb->insert(
            Database::conflicts_table(),
            array(
                'post_id'         => $post_id,
                'portal'          => $portal,
                'source_data'     => wp_json_encode( $source ),
                'local_data'      => wp_json_encode( $local ),
                'conflict_fields' => wp_json_encode( $fields ),
                'status'          => 'pending',
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        return (int) $wpdb->insert_id;
    }

    public static function pending(): array {
        global $wpdb;
        $table = Database::conflicts_table();
        $rows  = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'pending' ORDER BY created_at DESC",
            ARRAY_A
        );
        return is_array( $rows ) ? $rows : array();
    }

    public static function resolve( int $id, string $resolution, int $user_id ): bool {
        global $wpdb;
        if ( ! in_array( $resolution, array( 'keep_local', 'take_import', 'merge' ), true ) ) {
            return false;
        }
        $result = $wpdb->update(
            Database::conflicts_table(),
            array(
                'status'      => 'approved',
                'resolution'  => $resolution,
                'resolved_at' => current_time( 'mysql' ),
                'resolved_by' => $user_id,
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%d' ),
            array( '%d' )
        );
        return false !== $result;
    }
}
```

- [ ] **Step 4: PHP-Lint aller drei Dateien**

```bash
php -l includes/openimmo/class-settings.php
php -l includes/openimmo/class-sync-log.php
php -l includes/openimmo/class-conflicts.php
```

**Erwartet:** Jeweils „No syntax errors detected".

- [ ] **Step 5: Commit**

```bash
git add includes/openimmo/
git commit -m "feat(openimmo): add Settings, SyncLog and Conflicts repository skeletons"
```

---

## Task 4: Admin-Settings-Seite

**Files:**
- Create: `includes/openimmo/class-admin-page.php`
- Modify: `includes/class-plugin.php`

**Ziel:** Submenü „Immo Manager → OpenImmo" erscheint im WP-Admin. Settings können gespeichert werden. Passwort-Felder sind verschlüsselt-im-DB, im UI als `password`-Inputs.

- [ ] **Step 1: AdminPage-Klasse anlegen**

`includes/openimmo/class-admin-page.php`:

```php
<?php
namespace ImmoManager\OpenImmo;

defined( 'ABSPATH' ) || exit;

class AdminPage {

    public const PAGE_SLUG = 'immo-manager-openimmo';
    public const NONCE_ACTION = 'immo_manager_openimmo_save';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );
        add_action( 'admin_init', array( $this, 'maybe_save' ) );
    }

    public function register_submenu(): void {
        add_submenu_page(
            'immo-manager',
            __( 'OpenImmo', 'immo-manager' ),
            __( 'OpenImmo', 'immo-manager' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render' )
        );
    }

    public function maybe_save(): void {
        if ( empty( $_POST[ self::NONCE_ACTION ] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( self::NONCE_ACTION );

        $data = array(
            'enabled'   => ! empty( $_POST['enabled'] ),
            'cron_time' => sanitize_text_field( wp_unslash( $_POST['cron_time'] ?? '03:00' ) ),
            'portals'   => array(),
        );

        foreach ( array( 'willhaben', 'immoscout24_at' ) as $key ) {
            $portal = isset( $_POST['portals'][ $key ] ) && is_array( $_POST['portals'][ $key ] )
                ? wp_unslash( $_POST['portals'][ $key ] )
                : array();

            $data['portals'][ $key ] = array(
                'enabled'       => ! empty( $portal['enabled'] ),
                'sftp_host'     => sanitize_text_field( $portal['sftp_host'] ?? '' ),
                'sftp_port'     => max( 1, (int) ( $portal['sftp_port'] ?? 22 ) ),
                'sftp_user'     => sanitize_text_field( $portal['sftp_user'] ?? '' ),
                'sftp_password' => (string) ( $portal['sftp_password'] ?? '' ),
                'remote_path'   => sanitize_text_field( $portal['remote_path'] ?? '/' ),
                'last_sync'     => '',
            );
        }

        Settings::save( $data );

        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__( 'OpenImmo-Einstellungen gespeichert.', 'immo-manager' )
                . '</p></div>';
        } );
    }

    public function render(): void {
        $settings = Settings::get();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Immo Manager – OpenImmo', 'immo-manager' ); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field( self::NONCE_ACTION ); ?>

                <h2><?php esc_html_e( 'Allgemein', 'immo-manager' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="enabled"><?php esc_html_e( 'Schnittstelle aktiv', 'immo-manager' ); ?></label></th>
                        <td><input type="checkbox" id="enabled" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>></td>
                    </tr>
                    <tr>
                        <th><label for="cron_time"><?php esc_html_e( 'Tägliche Sync-Zeit (HH:MM)', 'immo-manager' ); ?></label></th>
                        <td><input type="time" id="cron_time" name="cron_time" value="<?php echo esc_attr( $settings['cron_time'] ); ?>"></td>
                    </tr>
                </table>

                <?php foreach ( $settings['portals'] as $key => $portal ) : ?>
                    <h2><?php echo esc_html( self::portal_label( $key ) ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Aktiv', 'immo-manager' ); ?></th>
                            <td><input type="checkbox" name="portals[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $portal['enabled'] ); ?>></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'SFTP-Host', 'immo-manager' ); ?></th>
                            <td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][sftp_host]" value="<?php echo esc_attr( $portal['sftp_host'] ); ?>"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Port', 'immo-manager' ); ?></th>
                            <td><input type="number" min="1" max="65535" name="portals[<?php echo esc_attr( $key ); ?>][sftp_port]" value="<?php echo esc_attr( $portal['sftp_port'] ); ?>"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Benutzer', 'immo-manager' ); ?></th>
                            <td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][sftp_user]" value="<?php echo esc_attr( $portal['sftp_user'] ); ?>"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Passwort', 'immo-manager' ); ?></th>
                            <td><input type="password" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][sftp_password]" value="<?php echo esc_attr( $portal['sftp_password'] ); ?>" autocomplete="new-password"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Remote-Verzeichnis', 'immo-manager' ); ?></th>
                            <td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][remote_path]" value="<?php echo esc_attr( $portal['remote_path'] ); ?>"></td>
                        </tr>
                    </table>
                <?php endforeach; ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private static function portal_label( string $key ): string {
        $map = array(
            'willhaben'      => 'willhaben.at',
            'immoscout24_at' => 'ImmobilienScout24.at',
        );
        return $map[ $key ] ?? $key;
    }
}
```

- [ ] **Step 2: Plugin-Singleton erweitern**

In `includes/class-plugin.php` analog zum Schema-Lazy-Getter ergänzen:

```php
private $openimmo_admin_page = null;

public function get_openimmo_admin_page(): \ImmoManager\OpenImmo\AdminPage {
    if ( null === $this->openimmo_admin_page ) {
        $this->openimmo_admin_page = new \ImmoManager\OpenImmo\AdminPage();
    }
    return $this->openimmo_admin_page;
}
```

In `init_shortcodes()` (oder einer separaten `init_admin()`-Methode falls vorhanden) den Getter triggern. Falls aktuell alle Init-Calls in `init_shortcodes()` liegen, dort ergänzen:

```php
$this->get_openimmo_admin_page();
```

- [ ] **Step 3: Manueller Test**

WP-Admin öffnen, im Menü „Immo Manager → OpenImmo".

**Erwartet:**
- Submenü-Eintrag „OpenImmo" sichtbar.
- Seite öffnet ohne Fatal.
- Felder leer (defaults), 2 Portal-Sektionen.
- Test-Daten eingeben, „Speichern". Notice „Einstellungen gespeichert" erscheint.
- Seite neu laden: Werte (außer Passwort-Display) sind persistiert.

- [ ] **Step 4: Encryption-Test**

In phpMyAdmin/wp-cli `wp_options` öffnen, Eintrag `immo_manager_openimmo` ansehen.

**Erwartet:** `sftp_password` in den Portals ist ein **base64-codierter Cipher-Blob**, NICHT der eingegebene Klartext. Beim erneuten Laden der Settings-Seite ist das Passwort-Feld wieder mit dem Original-Klartext befüllt.

- [ ] **Step 5: Commit**

```bash
git add includes/openimmo/class-admin-page.php includes/class-plugin.php
git commit -m "feat(openimmo): add admin settings page with encrypted SFTP credentials"
```

---

## Task 5: Cron-Hook-Registrierung (No-Op-Stub)

**Files:**
- Create: `includes/openimmo/class-cron-scheduler.php`
- Modify: `includes/class-plugin.php`
- Modify: `immo-manager.php`

**Ziel:** WP-Cron registriert einen täglichen Hook `immo_manager_openimmo_daily_sync`. Der Callback ist in Phase 0 noch ein No-Op (loggt nur). Beim Plugin-Deaktivieren wird der Hook gecleart.

- [ ] **Step 1: CronScheduler-Klasse**

`includes/openimmo/class-cron-scheduler.php`:

```php
<?php
namespace ImmoManager\OpenImmo;

defined( 'ABSPATH' ) || exit;

class CronScheduler {

    public const HOOK_DAILY_SYNC = 'immo_manager_openimmo_daily_sync';

    public function __construct() {
        add_action( self::HOOK_DAILY_SYNC, array( $this, 'run_daily_sync' ) );
    }

    /**
     * Auf Plugin-Aktivierung – Hook scheduled.
     */
    public static function on_activation(): void {
        if ( ! wp_next_scheduled( self::HOOK_DAILY_SYNC ) ) {
            $settings   = Settings::get();
            list( $h, $m ) = array_pad( explode( ':', $settings['cron_time'] ), 2, '0' );
            $first_run  = strtotime( sprintf( 'tomorrow %02d:%02d:00', (int) $h, (int) $m ) );
            wp_schedule_event( $first_run, 'daily', self::HOOK_DAILY_SYNC );
        }
    }

    /**
     * Auf Plugin-Deaktivierung – Hook gecleart.
     */
    public static function on_deactivation(): void {
        $timestamp = wp_next_scheduled( self::HOOK_DAILY_SYNC );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK_DAILY_SYNC );
        }
        wp_clear_scheduled_hook( self::HOOK_DAILY_SYNC );
    }

    /**
     * Daily-Sync-Callback. In Phase 0 No-Op-Stub.
     * In Phase 2/4 wird hier der eigentliche Export/Import getriggert.
     */
    public function run_daily_sync(): void {
        $settings = Settings::get();
        if ( empty( $settings['enabled'] ) ) {
            return;
        }
        // Stub-Log – beweist dass der Hook feuert.
        error_log( '[immo-manager openimmo] daily_sync hook fired (Phase 0 stub)' );
    }
}
```

- [ ] **Step 2: Plugin-Aktivierungs-Hooks im Bootstrap**

In `immo-manager.php`:

```php
register_activation_hook( __FILE__, array( '\ImmoManager\OpenImmo\CronScheduler', 'on_activation' ) );
register_deactivation_hook( __FILE__, array( '\ImmoManager\OpenImmo\CronScheduler', 'on_deactivation' ) );
```

- [ ] **Step 3: Plugin-Singleton instanziiert CronScheduler**

In `includes/class-plugin.php` analog:

```php
private $openimmo_cron = null;

public function get_openimmo_cron(): \ImmoManager\OpenImmo\CronScheduler {
    if ( null === $this->openimmo_cron ) {
        $this->openimmo_cron = new \ImmoManager\OpenImmo\CronScheduler();
    }
    return $this->openimmo_cron;
}
```

In `init_shortcodes()` triggern (`$this->get_openimmo_cron();`).

- [ ] **Step 4: Manueller Test (WP-CLI falls vorhanden)**

```bash
wp cron event list | grep immo_manager_openimmo
```

Falls kein WP-CLI: Plugin „WP Crontrol" installieren und im Backend prüfen.

**Erwartet:** Eintrag `immo_manager_openimmo_daily_sync` mit Recurrence „daily" und nächstem Lauf morgen zur eingestellten Cron-Zeit.

- [ ] **Step 5: Stub-Run testen**

```bash
wp cron event run immo_manager_openimmo_daily_sync
```

Im PHP-Error-Log nachsehen.

**Erwartet:** Eintrag `[immo-manager openimmo] daily_sync hook fired (Phase 0 stub)` (nur wenn Settings-Schalter „Schnittstelle aktiv" gesetzt ist).

- [ ] **Step 6: Commit**

```bash
git add includes/openimmo/class-cron-scheduler.php includes/class-plugin.php immo-manager.php
git commit -m "feat(openimmo): register daily sync cron hook (Phase 0 stub callback)"
```

---

## Task 6: Akzeptanz-Checkliste

**Files:** keine

**Ziel:** Phase 0 Foundation steht. Alle Punkte nachweislich erfüllt, bevor Phase 1 beginnen darf.

- [ ] **Step 1: Akzeptanzkriterien einzeln prüfen**

- [ ] `composer install` läuft fehlerfrei, `vendor/phpseclib/phpseclib/` existiert
- [ ] Plugin lädt ohne Fatal nach Reaktivierung
- [ ] `SHOW TABLES` zeigt `wp_immo_sync_log` und `wp_immo_conflicts`
- [ ] Beide Tabellen haben die Spalten und Indizes wie spezifiziert
- [ ] Settings-Seite „Immo Manager → OpenImmo" öffnet
- [ ] Settings-Felder lassen sich speichern, persistieren über Reload
- [ ] Passwort wird verschlüsselt in `wp_options` abgelegt (Klartext nicht sichtbar)
- [ ] Passwort wird beim Lesen wieder entschlüsselt (Form-Feld zeigt Klartext)
- [ ] WP-Cron-Liste zeigt `immo_manager_openimmo_daily_sync` mit „daily"
- [ ] Manueller `wp cron event run` triggert den Stub-Log-Eintrag
- [ ] Plugin-Deaktivierung entfernt den Cron-Hook (`wp cron event list` zeigt ihn nicht mehr)
- [ ] PHP-Lint auf alle neuen `includes/openimmo/`-Dateien ohne Fehler
- [ ] Keine PHP-Notices/Warnings im `WP_DEBUG`-Log beim Aktivieren/Deaktivieren

- [ ] **Step 2: Bei Bedarf Bugfix-Commits**

- [ ] **Step 3: Memory-Update**

In `~/.claude/projects/.../memory/project_openimmo.md` Phase-0-Status auf „erledigt" setzen, Hinweis auf nächste Phase ergänzen.

- [ ] **Step 4: Bereitschaftsmeldung**

Ergebnis an User: „Phase 0 fertig. Bereit für Phase 1 (Export-Mapping)? Erst nach OK weiter."

---

## Self-Review (vom Plan-Autor durchgeführt)

**Coverage-Check der Briefing-Punkte (memory/project_openimmo.md):**

| Briefing-Punkt | Abgedeckt von |
|---|---|
| Composer + phpseclib | Task 1 |
| 2 neue DB-Tabellen mit User-vorgegebenen Feldern | Task 2 |
| Settings unter „Immo Manager" als Untermenü | Task 4 |
| Pro-Portal-Credentials konfigurierbar | Task 4 |
| Verschlüsselte Passwort-Speicherung (Best Practice) | Task 3 (Settings::encrypt/decrypt), Task 4 (UI) |
| Daily-Cron registriert | Task 5 |
| willhaben + ImmoScout24.at als Portal-Keys vorbereitet | Task 4 (UI), Task 3 (Settings::defaults) |

**Out of Scope für Phase 0** (kommt in Phase 1+):
- Echter Export/Import-Code
- XML-Generierung/-Parsing
- SFTP-Verbindung mit phpseclib
- Konflikt-Diff-Logik
- Manueller-Upload-Endpoint

**Type-Konsistenz:** Klassennamen (Settings, SyncLog, Conflicts, AdminPage, CronScheduler) konsistent zwischen Steps. Tabellen-Namen-Methoden in `Database` matchen jene in den Repositories.

**Placeholder-Scan:** Keine TBDs, alle Code-Blöcke vollständig, alle Test-Schritte mit erwarteten Ergebnissen.

Plan ist konsistent.
