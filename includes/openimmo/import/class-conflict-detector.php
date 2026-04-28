<?php
/**
 * Entscheidet pro Listing: 'new' oder 'existing'.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Import;

use ImmoManager\Database;
use ImmoManager\PostTypes;

defined( 'ABSPATH' ) || exit;

class ConflictDetector {

	/**
	 * @return array{kind:string, post_id?:int, unit_id?:int}
	 */
	public function detect( ImportListingDTO $dto ): array {
		$ext = $dto->external_id;

		if ( preg_match( '/^wp-(\d+)$/', $ext, $m ) ) {
			$post = get_post( (int) $m[1] );
			if ( $post instanceof \WP_Post && PostTypes::POST_TYPE_PROPERTY === $post->post_type ) {
				return array( 'kind' => 'existing_property', 'post_id' => (int) $post->ID );
			}
		}

		if ( preg_match( '/^unit-(\d+)$/', $ext, $m ) ) {
			$unit_id = (int) $m[1];
			global $wpdb;
			$table = Database::units_table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id = %d", $unit_id ) );
			if ( $exists > 0 ) {
				return array( 'kind' => 'existing_unit', 'unit_id' => $unit_id );
			}
		}

		if ( '' !== $ext ) {
			$query = new \WP_Query( array(
				'post_type'      => PostTypes::POST_TYPE_PROPERTY,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => '_immo_openimmo_external_id', 'value' => $ext ),
				),
			) );
			if ( ! empty( $query->posts ) ) {
				return array( 'kind' => 'existing_property', 'post_id' => (int) $query->posts[0] );
			}

			global $wpdb;
			$table = Database::units_table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE external_id = %s LIMIT 1", $ext ) );
			if ( $row ) {
				return array( 'kind' => 'existing_unit', 'unit_id' => (int) $row );
			}
		}

		return array( 'kind' => 'new' );
	}
}
