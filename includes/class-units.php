<?php
/**
 * Repository für Wohneinheiten (Units) eines Bauprojekts.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Units
 *
 * CRUD-Operationen auf der Tabelle {prefix}immo_units.
 */
class Units {

	/**
	 * Gültige Status-Werte.
	 */
	public const STATUSES = array( 'available', 'reserved', 'sold', 'rented' );

	/**
	 * Alle Units eines Projekts abrufen.
	 *
	 * @param int    $project_id Projekt-Post-ID.
	 * @param string $orderby    Sortierfeld (id, unit_number, floor, price, area).
	 * @param string $order      ASC/DESC.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_by_project( int $project_id, string $orderby = 'unit_number', string $order = 'ASC' ): array {
		global $wpdb;
		$table    = Database::units_table();
		$allowed  = array( 'id', 'unit_number', 'floor', 'price', 'area', 'rooms', 'status', 'created_at' );
		$orderby  = in_array( $orderby, $allowed, true ) ? $orderby : 'unit_number';
		$order    = strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC';

		// Bei unit_number natürliche Sortierung erzwingen (1, 2, 10 statt 1, 10, 2).
		if ( 'unit_number' === $orderby ) {
			$order_clause = "LENGTH(unit_number) {$order}, unit_number {$order}";
		} else {
			$order_clause = "{$orderby} {$order}";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE project_id = %d ORDER BY {$order_clause}, id ASC",
			$project_id
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? array_map( array( __CLASS__, 'hydrate' ), $rows ) : array();
	}

	/**
	 * Eine Unit per ID abrufen.
	 *
	 * @param int $id Unit-ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = Database::units_table();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Unit anlegen.
	 *
	 * @param array<string, mixed> $data Rohdaten.
	 *
	 * @return int|false Einfüge-ID oder false bei Fehler.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$sanitized = self::sanitize( $data );

		if ( empty( $sanitized['project_id'] ) ) {
			return false;
		}

		$sanitized['created_at'] = current_time( 'mysql' );
		$sanitized['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->insert( Database::units_table(), $sanitized, self::column_formats( $sanitized ) );

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Unit aktualisieren.
	 *
	 * @param int                  $id   Unit-ID.
	 * @param array<string, mixed> $data Neue Werte.
	 *
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$sanitized               = self::sanitize( $data );
		$sanitized['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			Database::units_table(),
			$sanitized,
			array( 'id' => $id ),
			self::column_formats( $sanitized ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Unit löschen.
	 *
	 * @param int $id Unit-ID.
	 *
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$result = $wpdb->delete( Database::units_table(), array( 'id' => $id ), array( '%d' ) );
		return (bool) $result;
	}

	/**
	 * Alle Units eines Projekts löschen (z. B. wenn Projekt gelöscht wird).
	 *
	 * @param int $project_id Projekt-ID.
	 *
	 * @return int Anzahl gelöschter Zeilen.
	 */
	public static function delete_by_project( int $project_id ): int {
		global $wpdb;
		$result = $wpdb->delete( Database::units_table(), array( 'project_id' => $project_id ), array( '%d' ) );
		return (int) $result;
	}

	/**
	 * Status-Counts pro Projekt.
	 *
	 * @param int $project_id Projekt-ID.
	 *
	 * @return array<string, int>
	 */
	public static function count_by_status( int $project_id ): array {
		global $wpdb;
		$table = Database::units_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS cnt FROM {$table} WHERE project_id = %d GROUP BY status",
				$project_id
			),
			ARRAY_A
		);

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
	 * Min-/Max-Wohnfläche der Units eines Projekts.
	 *
	 * Berücksichtigt alle Units mit area > 0, unabhängig vom Status.
	 *
	 * @param int $project_id Projekt-ID.
	 *
	 * @return array{min: float, max: float} Min/Max in m². 0/0 wenn keine Units mit Fläche.
	 */
	public static function area_range( int $project_id ): array {
		global $wpdb;
		$table = Database::units_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT MIN(area) AS area_min, MAX(area) AS area_max FROM {$table} WHERE project_id = %d AND area > 0",
				$project_id
			),
			ARRAY_A
		);

		return array(
			'min' => (float) ( $row['area_min'] ?? 0 ),
			'max' => (float) ( $row['area_max'] ?? 0 ),
		);
	}

	/**
	 * Gesamt-Count aller Units (für Dashboard).
	 *
	 * @return int
	 */
	public static function total_count(): int {
		global $wpdb;
		$table = Database::units_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Rohdaten in getypte Struktur wandeln.
	 *
	 * @param array<string, mixed> $row DB-Row.
	 *
	 * @return array<string, mixed>
	 */
	private static function hydrate( array $row ): array {
		$row['id']           = (int) ( $row['id'] ?? 0 );
		$row['project_id']   = (int) ( $row['project_id'] ?? 0 );
		$row['property_id']  = ! empty( $row['property_id'] ) ? (int) $row['property_id'] : null;
		$row['floor']        = (int) ( $row['floor'] ?? 0 );
		$row['area']         = (float) ( $row['area'] ?? 0 );
		$row['usable_area']  = (float) ( $row['usable_area'] ?? 0 );
		$row['rooms']        = (int) ( $row['rooms'] ?? 0 );
		$row['bedrooms']     = (int) ( $row['bedrooms'] ?? 0 );
		$row['bathrooms']    = (int) ( $row['bathrooms'] ?? 0 );
		$row['price']        = (float) ( $row['price'] ?? 0 );
		$row['rent']         = (float) ( $row['rent'] ?? 0 );
		$row['status']       = in_array( $row['status'] ?? '', self::STATUSES, true ) ? $row['status'] : 'available';
		$row['floor_plan_image_id'] = ! empty( $row['floor_plan_image_id'] ) ? (int) $row['floor_plan_image_id'] : null;
		$row['features']     = self::decode_json( $row['features'] ?? '' );
		$row['gallery_images'] = self::decode_json( $row['gallery_images'] ?? '' );

		if ( $row['floor_plan_image_id'] ) {
			$row['floor_plan'] = array(
				'id'            => $row['floor_plan_image_id'],
				'url'           => wp_get_attachment_url( $row['floor_plan_image_id'] ),
				'url_thumbnail' => wp_get_attachment_image_url( $row['floor_plan_image_id'], 'thumbnail' ),
			);
		} else {
			$row['floor_plan'] = null;
		}
		return $row;
	}

	/**
	 * JSON sicher dekodieren (immer Array zurück).
	 *
	 * @param mixed $value Raw-Wert.
	 *
	 * @return array<int, mixed>
	 */
	private static function decode_json( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}
		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : array();
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

		if ( isset( $data['project_id'] ) ) {
			$out['project_id'] = absint( $data['project_id'] );
		}
		if ( isset( $data['property_id'] ) ) {
			$val = $data['property_id'];
			if ( is_numeric( $val ) && $val > 0 ) {
				$out['property_id'] = (int) $val;
			} else {
				$out['property_id'] = null;
			}
		}
		if ( isset( $data['unit_number'] ) ) {
			$out['unit_number'] = substr( sanitize_text_field( (string) $data['unit_number'] ), 0, 50 );
		}
		if ( isset( $data['floor'] ) ) {
			$out['floor'] = (int) $data['floor'];
		}
		if ( isset( $data['area'] ) ) {
			$out['area'] = max( 0, (float) $data['area'] );
		}
		if ( isset( $data['usable_area'] ) ) {
			$out['usable_area'] = max( 0, (float) $data['usable_area'] );
		}
		foreach ( array( 'rooms', 'bedrooms', 'bathrooms' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$out[ $k ] = max( 0, (int) $data[ $k ] );
			}
		}
		foreach ( array( 'price', 'rent' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$out[ $k ] = max( 0, (float) $data[ $k ] );
			}
		}
		if ( isset( $data['status'] ) ) {
			$status        = (string) $data['status'];
			$out['status'] = in_array( $status, self::STATUSES, true ) ? $status : 'available';
		}
		if ( isset( $data['features'] ) && is_array( $data['features'] ) ) {
			$out['features'] = wp_json_encode( Features::filter_valid( $data['features'] ) );
		}
		if ( isset( $data['description'] ) ) {
			$out['description'] = wp_kses_post( (string) $data['description'] );
		}
		if ( isset( $data['gallery_images'] ) && is_array( $data['gallery_images'] ) ) {
			$ids                   = array_values( array_filter( array_map( 'absint', $data['gallery_images'] ) ) );
			$out['gallery_images'] = wp_json_encode( $ids );
		}
		if ( isset( $data['floor_plan_image_id'] ) ) {
			$val = $data['floor_plan_image_id'];
			if ( is_numeric( $val ) && $val > 0 ) {
				$out['floor_plan_image_id'] = (int) $val;
			} else {
				$out['floor_plan_image_id'] = null;
			}
		}
		if ( isset( $data['contact_email'] ) ) {
			$email                = sanitize_email( (string) $data['contact_email'] );
			$out['contact_email'] = $email ? $email : '';
		}
		if ( isset( $data['contact_phone'] ) ) {
			$out['contact_phone'] = substr( sanitize_text_field( (string) $data['contact_phone'] ), 0, 50 );
		}
		if ( isset( $data['reserved_by'] ) ) {
			$out['reserved_by'] = sanitize_text_field( (string) $data['reserved_by'] );
		}
		foreach ( array( 'reserved_date', 'sold_date', 'rented_date' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$val        = trim( (string) $data[ $k ] );
				$out[ $k ]  = $val ? self::parse_datetime( $val ) : null;
			}
		}

		return $out;
	}

	/**
	 * Datumsstring in MySQL-Format konvertieren.
	 *
	 * @param string $value Datum/Datetime.
	 *
	 * @return string|null
	 */
	private static function parse_datetime( string $value ): ?string {
		$timestamp = strtotime( $value );
		return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null;
	}

	/**
	 * wpdb-Format-Array für die gegebenen Spalten.
	 *
	 * @param array<string, mixed> $data Spalten/Werte.
	 *
	 * @return array<int, string>
	 */
	private static function column_formats( array $data ): array {
		$formats = array();
		$map     = array(
			'project_id'     => '%d',
			'property_id'    => '%d',
			'unit_number'    => '%s',
			'floor'          => '%d',
			'area'           => '%f',
			'usable_area'    => '%f',
			'rooms'          => '%d',
			'bedrooms'       => '%d',
			'bathrooms'      => '%d',
			'price'          => '%f',
			'rent'           => '%f',
			'status'         => '%s',
			'features'       => '%s',
			'description'    => '%s',
			'gallery_images' => '%s',
			'contact_email'  => '%s',
			'contact_phone'  => '%s',
			'reserved_by'    => '%s',
			'reserved_date'  => '%s',
			'sold_date'      => '%s',
			'rented_date'    => '%s',
			'floor_plan_image_id' => '%d',
			'created_at'     => '%s',
			'updated_at'     => '%s',
		);
		foreach ( array_keys( $data ) as $col ) {
			$formats[] = $map[ $col ] ?? '%s';
		}
		return $formats;
	}
}
