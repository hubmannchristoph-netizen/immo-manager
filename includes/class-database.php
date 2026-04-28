<?php
/**
 * Custom-Database-Tabellen für Immo Manager.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Database
 *
 * Verwaltet das Schema der Custom Tables mit dbDelta und kümmert sich
 * um Migrationen (DB-Versionierung).
 */
class Database {

	/**
	 * Aktuelle DB-Schema-Version.
	 */
	public const DB_VERSION = '1.3.0';

	/**
	 * Option-Key für die gespeicherte DB-Version.
	 */
	public const DB_VERSION_OPTION = 'immo_manager_db_version';

	/**
	 * Konstruktor – registriert Migrations-Check.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ), 5 );
	}

	/**
	 * Prefix für die Custom Tables.
	 *
	 * @return string
	 */
	public static function table_prefix(): string {
		global $wpdb;
		return $wpdb->prefix . 'immo_';
	}

	/**
	 * Tabellenname: Wohneinheiten.
	 *
	 * @return string
	 */
	public static function units_table(): string {
		return self::table_prefix() . 'units';
	}

	/**
	 * Tabellenname: Anfragen.
	 *
	 * @return string
	 */
	public static function inquiries_table(): string {
		return self::table_prefix() . 'inquiries';
	}

	/**
	 * Tabellenname: OpenImmo Sync-Log.
	 *
	 * @return string
	 */
	public static function sync_log_table(): string {
		return self::table_prefix() . 'sync_log';
	}

	/**
	 * Tabellenname: OpenImmo Konflikt-Queue.
	 *
	 * @return string
	 */
	public static function conflicts_table(): string {
		return self::table_prefix() . 'conflicts';
	}

	/**
	 * Prüft bei jedem Request, ob ein Schema-Upgrade nötig ist.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$installed = get_option( self::DB_VERSION_OPTION, '' );
		if ( self::DB_VERSION !== $installed ) {
			self::install();
			
			if ( version_compare( $installed ?: '0.0.0', '1.2.4', '<' ) ) {
				self::migrate_cpts();
			}
		}
	}

	/**
	 * Tabellen erstellen oder aktualisieren.
	 *
	 * Nutzt dbDelta() für automatische Diffs. dbDelta ist empfindlich bei
	 * Formatierung (doppelte Leerzeichen nach PRIMARY KEY, KEY statt INDEX,
	 * keine Backticks in CREATE TABLE, …).
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$units_table     = self::units_table();
		$inquiries_table = self::inquiries_table();
		$sync_log_table  = self::sync_log_table();
		$conflicts_table = self::conflicts_table();

		// Wohneinheiten.
		$sql_units = "CREATE TABLE {$units_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			property_id BIGINT UNSIGNED NULL DEFAULT NULL,
			unit_number VARCHAR(50) NOT NULL DEFAULT '',
			floor INT NOT NULL DEFAULT 0,
			area DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			usable_area DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			rooms INT NOT NULL DEFAULT 0,
			bedrooms INT NOT NULL DEFAULT 0,
			bathrooms INT NOT NULL DEFAULT 0,
			price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			rent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			status VARCHAR(20) NOT NULL DEFAULT 'available',
			features LONGTEXT NULL,
			description LONGTEXT NULL,
			gallery_images LONGTEXT NULL,
			floor_plan_image_id BIGINT UNSIGNED DEFAULT NULL,
			contact_email VARCHAR(255) NOT NULL DEFAULT '',
			contact_phone VARCHAR(50) NOT NULL DEFAULT '',
			reserved_by VARCHAR(255) NOT NULL DEFAULT '',
			reserved_date DATETIME NULL DEFAULT NULL,
			sold_date DATETIME NULL DEFAULT NULL,
			rented_date DATETIME NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY project_id (project_id),
			KEY property_id (property_id),
			KEY status (status),
			KEY price (price),
			KEY floor (floor)
		) {$charset_collate};";

		// Anfragen.
		$sql_inquiries = "CREATE TABLE {$inquiries_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			property_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			unit_id BIGINT UNSIGNED NULL DEFAULT NULL,
			inquirer_name VARCHAR(255) NOT NULL DEFAULT '',
			inquirer_email VARCHAR(255) NOT NULL DEFAULT '',
			inquirer_phone VARCHAR(50) NOT NULL DEFAULT '',
			inquirer_message LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			reply_message LONGTEXT NULL,
			replied_by BIGINT UNSIGNED NULL DEFAULT NULL,
			replied_at DATETIME NULL DEFAULT NULL,
			ip_address VARCHAR(45) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY property_id (property_id),
			KEY unit_id (unit_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		// OpenImmo Sync-Log.
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

		// OpenImmo Konflikt-Queue.
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

		dbDelta( $sql_units );
		dbDelta( $sql_inquiries );
		dbDelta( $sql_sync_log );
		dbDelta( $sql_conflicts );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Migriert alte CPT-Slugs auf die neuen Slugs.
	 *
	 * @return void
	 */
	public static function migrate_cpts(): void {
		global $wpdb;
		
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
				'immo_mgr_property',
				'immo_property'
			)
		);
		
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
				'immo_mgr_project',
				'immo_project'
			)
		);
	}

	/**
	 * Tabellen entfernen (z. B. bei uninstall.php).
	 *
	 * Wird bei normaler Deaktivierung NICHT aufgerufen.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		global $wpdb;

		$tables = array(
			self::units_table(),
			self::inquiries_table(),
			self::sync_log_table(),
			self::conflicts_table(),
		);
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( self::DB_VERSION_OPTION );
	}
}
