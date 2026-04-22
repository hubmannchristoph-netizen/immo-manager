<?php
/**
 * Repository für Immobilien-Anfragen (Inquiries).
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Inquiries
 *
 * Schlanke CRUD-Schicht über {prefix}immo_inquiries.
 * Das Frontend-Formular und die Admin-Liste folgen in Phase 4/6.
 */
class Inquiries {

	/**
	 * Gültige Status-Werte.
	 */
	public const STATUSES = array( 'new', 'read', 'replied', 'spam' );

	/**
	 * Neue Anfrage speichern.
	 *
	 * @param array<string, mixed> $data Rohdaten aus Formular.
	 *
	 * @return int|false Eingefügte ID oder false.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$sanitized = self::sanitize( $data );

		if ( empty( $sanitized['property_id'] ) || empty( $sanitized['inquirer_email'] ) ) {
			return false;
		}

		$sanitized['created_at'] = current_time( 'mysql' );
		$sanitized['status']     = $sanitized['status'] ?? 'new';

		$result = $wpdb->insert(
			Database::inquiries_table(),
			$sanitized,
			self::column_formats( $sanitized )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Anfrage per ID abrufen.
	 *
	 * @param int $id Inquiry-ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = Database::inquiries_table();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Anfragen mit optionalen Filtern abrufen.
	 *
	 * @param array<string, mixed> $args property_id, unit_id, status, limit, offset.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;
		$table = Database::inquiries_table();

		$where    = array( '1=1' );
		$prepare  = array();

		if ( ! empty( $args['property_id'] ) ) {
			$where[]    = 'property_id = %d';
			$prepare[]  = absint( $args['property_id'] );
		}
		if ( ! empty( $args['unit_id'] ) ) {
			$where[]    = 'unit_id = %d';
			$prepare[]  = absint( $args['unit_id'] );
		}
		if ( ! empty( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
			$where[]    = 'status = %s';
			$prepare[]  = $args['status'];
		}

		$limit  = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 20;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
		$prepare[] = $limit;
		$prepare[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $prepare ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Status einer Anfrage aktualisieren.
	 *
	 * @param int    $id     Inquiry-ID.
	 * @param string $status Neuer Status.
	 *
	 * @return bool
	 */
	public static function update_status( int $id, string $status ): bool {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		global $wpdb;
		$result = $wpdb->update(
			Database::inquiries_table(),
			array( 'status' => $status ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
		return false !== $result;
	}

	/**
	 * Anfrage löschen.
	 *
	 * @param int $id Inquiry-ID.
	 *
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$result = $wpdb->delete( Database::inquiries_table(), array( 'id' => $id ), array( '%d' ) );
		return (bool) $result;
	}

	/**
	 * Counts nach Status.
	 *
	 * @return array<string, int>
	 */
	public static function count_by_status(): array {
		global $wpdb;
		$table = Database::inquiries_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status", ARRAY_A );

		$counts = array_fill_keys( self::STATUSES, 0 );
		foreach ( (array) $rows as $row ) {
			$status = (string) ( $row['status'] ?? '' );
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ] = (int) $row['cnt'];
			}
		}
		return $counts;
	}

	/**
	 * Gesamt-Count (für Dashboard).
	 *
	 * @return int
	 */
	public static function total_count(): int {
		global $wpdb;
		$table = Database::inquiries_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Daten sanitizen.
	 *
	 * @param array<string, mixed> $data Rohdaten.
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $data ): array {
		$out = array();

		if ( isset( $data['property_id'] ) ) {
			$out['property_id'] = absint( $data['property_id'] );
		}
		if ( isset( $data['unit_id'] ) ) {
			$unit_id         = absint( $data['unit_id'] );
			$out['unit_id']  = $unit_id ? $unit_id : null;
		}
		if ( isset( $data['inquirer_name'] ) ) {
			$out['inquirer_name'] = substr( sanitize_text_field( (string) $data['inquirer_name'] ), 0, 255 );
		}
		if ( isset( $data['inquirer_email'] ) ) {
			$email                  = sanitize_email( (string) $data['inquirer_email'] );
			$out['inquirer_email']  = $email ? $email : '';
		}
		if ( isset( $data['inquirer_phone'] ) ) {
			$out['inquirer_phone'] = substr( sanitize_text_field( (string) $data['inquirer_phone'] ), 0, 50 );
		}
		if ( isset( $data['inquirer_message'] ) ) {
			$out['inquirer_message'] = wp_kses_post( (string) $data['inquirer_message'] );
		}
		if ( isset( $data['status'] ) && in_array( $data['status'], self::STATUSES, true ) ) {
			$out['status'] = (string) $data['status'];
		}
		if ( isset( $data['ip_address'] ) ) {
			$ip                 = (string) $data['ip_address'];
			$out['ip_address']  = ( filter_var( $ip, FILTER_VALIDATE_IP ) ) ? $ip : '';
		}
		if ( isset( $data['user_agent'] ) ) {
			$out['user_agent'] = substr( sanitize_text_field( (string) $data['user_agent'] ), 0, 255 );
		}

		return $out;
	}

	/**
	 * wpdb-Format-Array für gegebene Spalten.
	 *
	 * @param array<string, mixed> $data Daten.
	 *
	 * @return array<int, string>
	 */
	private static function column_formats( array $data ): array {
		$map = array(
			'property_id'      => '%d',
			'unit_id'          => '%d',
			'inquirer_name'    => '%s',
			'inquirer_email'   => '%s',
			'inquirer_phone'   => '%s',
			'inquirer_message' => '%s',
			'status'           => '%s',
			'reply_message'    => '%s',
			'replied_by'       => '%d',
			'replied_at'       => '%s',
			'ip_address'       => '%s',
			'user_agent'       => '%s',
			'created_at'       => '%s',
		);
		$formats = array();
		foreach ( array_keys( $data ) as $col ) {
			$formats[] = $map[ $col ] ?? '%s';
		}
		return $formats;
	}
}
