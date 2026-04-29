<?php
/**
 * OpenImmo Sync-Log – Repository für Sync-Historie.
 *
 * @package ImmoManager\OpenImmo
 */

namespace ImmoManager\OpenImmo;

use ImmoManager\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Persistiert Sync-Vorgänge (Start/Ende, Status, Summary, Anzahl).
 */
class SyncLog {

	/**
	 * Neuen Sync-Lauf starten.
	 *
	 * @param string $portal    Portal-Key (z. B. 'willhaben').
	 * @param string $direction 'export' oder 'import'.
	 * @return int Insert-ID.
	 */
	public static function start( string $portal, string $direction ): int {
		global $wpdb;

		$wpdb->insert(
			Database::sync_log_table(),
			array(
				'portal'     => $portal,
				'direction'  => $direction,
				'status'     => 'running',
				'started_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Sync-Lauf abschließen.
	 *
	 * @param int    $id      Sync-Log-ID.
	 * @param string $status  One of 'success', 'partial', 'skipped', 'error'.
	 * @param string $summary Kurzbeschreibung.
	 * @param array  $details Zusatz-Daten (wird als JSON gespeichert).
	 * @param int    $count   Anzahl betroffener Properties.
	 * @return bool
	 */
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

	/**
	 * Letzte N Sync-Läufe.
	 *
	 * @param int $limit Maximale Anzahl Einträge.
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 50 ): array {
		global $wpdb;

		$table = Database::sync_log_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY started_at DESC LIMIT %d", $limit ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
