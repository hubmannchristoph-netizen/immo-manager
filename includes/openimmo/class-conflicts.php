<?php
/**
 * OpenImmo Conflicts – Repository für die Konflikt-Queue.
 *
 * @package ImmoManager\OpenImmo
 */

namespace ImmoManager\OpenImmo;

use ImmoManager\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Persistiert Konflikte zwischen lokalem Datensatz und Import-Daten.
 */
class Conflicts {

	/**
	 * Neuen Konflikt anlegen.
	 *
	 * @param int    $post_id Lokaler Post-ID.
	 * @param string $portal  Portal-Key.
	 * @param array  $source  Daten aus dem Import.
	 * @param array  $local   Aktuelle lokale Daten.
	 * @param array  $fields  Liste der konfliktbehafteten Felder.
	 * @return int Insert-ID.
	 */
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

	/**
	 * Alle offenen Konflikte.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function pending(): array {
		global $wpdb;

		$table = Database::conflicts_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'pending' ORDER BY created_at DESC",
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Konflikt auflösen.
	 *
	 * @param int    $id         Konflikt-ID.
	 * @param string $resolution 'keep_local' | 'take_import' | 'merge'.
	 * @param int    $user_id    User-ID des Approvers.
	 * @return bool
	 */
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

	/**
	 * Anzahl pending Konflikte (für Menü-Badge).
	 */
	public static function pending_count(): int {
		global $wpdb;
		$table = Database::conflicts_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
	}
}
