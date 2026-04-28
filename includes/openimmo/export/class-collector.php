<?php
/**
 * Collector – sammelt alle Listings für einen Portal-Key.
 *
 * @package ImmoManager\OpenImmo\Export
 */

namespace ImmoManager\OpenImmo\Export;

use ImmoManager\Database;
use ImmoManager\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Sammelt alle Listings, die für einen bestimmten Portal-Key exportiert werden sollen.
 */
class Collector {

	/**
	 * @return ListingDTO[]
	 */
	public function gather( string $portal_key ): array {
		$listings = array();
		$listings = array_merge( $listings, $this->collect_properties( $portal_key ) );
		$listings = array_merge( $listings, $this->collect_units( $portal_key ) );
		return $listings;
	}

	private function collect_properties( string $portal_key ): array {
		$meta_key = '_immo_openimmo_' . $portal_key;
		// immoscout24_at als Suffix → wir schneiden "_at" weg, weil das Meta-Feld nur
		// _immo_openimmo_immoscout24 heißt (siehe Task 1).
		$meta_key = str_replace( '_at', '', $meta_key );

		$query = new \WP_Query( array(
			'post_type'      => PostTypes::POST_TYPE_PROPERTY,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => '_immo_status', 'value' => 'available', 'compare' => '=' ),
				array( 'key' => $meta_key,      'value' => '1',         'compare' => '=' ),
			),
			'fields'         => 'ids',
		) );

		$dtos = array();
		foreach ( $query->posts as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( $post instanceof \WP_Post ) {
				$dtos[] = ListingDTO::from_property( $post );
			}
		}
		return $dtos;
	}

	private function collect_units( string $portal_key ): array {
		global $wpdb;
		$col   = 'openimmo_' . str_replace( '_at', '', $portal_key );  // 'openimmo_willhaben' / 'openimmo_immoscout24'
		$table = Database::units_table();

		// Units holen: status='available' AND opt-in AND project_id zeigt auf published project.
		$sql = $wpdb->prepare(
			"SELECT u.*
			   FROM {$table} u
		  INNER JOIN {$wpdb->posts} p ON p.ID = u.project_id
			  WHERE u.status = 'available'
			    AND u.{$col} = 1
			    AND p.post_type = %s
			    AND p.post_status = 'publish'",
			PostTypes::POST_TYPE_PROJECT
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$dtos = array();
		foreach ( $rows as $row ) {
			$dtos[] = ListingDTO::from_unit( $row, (int) $row['project_id'] );
		}
		return $dtos;
	}
}
