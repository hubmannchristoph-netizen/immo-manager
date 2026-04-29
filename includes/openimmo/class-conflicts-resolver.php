<?php
/**
 * Resolver für OpenImmo-Konflikte.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo;

use ImmoManager\Database;
use ImmoManager\PostTypes;

defined( 'ABSPATH' ) || exit;

class ConflictsResolver {

	/**
	 * Löst einen Konflikt auf.
	 *
	 * @param int    $conflict_id Conflict-ID.
	 * @param string $resolution  'keep_local' | 'take_import'.
	 */
	public function resolve( int $conflict_id, string $resolution ): bool {
		$conflict = $this->fetch( $conflict_id );
		if ( null === $conflict || 'pending' !== $conflict['status'] ) {
			return false;
		}

		$user_id = get_current_user_id();

		if ( 'keep_local' === $resolution ) {
			return Conflicts::resolve( $conflict_id, 'keep_local', $user_id );
		}

		if ( 'take_import' === $resolution ) {
			$post_id = (int) $conflict['post_id'];
			$post    = get_post( $post_id );
			if ( ! $post instanceof \WP_Post || PostTypes::POST_TYPE_PROPERTY !== $post->post_type ) {
				Conflicts::resolve( $conflict_id, 'keep_local', $user_id );
				return false;
			}

			$this->apply_import( $post_id, (string) $conflict['source_data'] );
			return Conflicts::resolve( $conflict_id, 'take_import', $user_id );
		}

		return false;
	}

	private function fetch( int $conflict_id ): ?array {
		global $wpdb;
		$table = Database::conflicts_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $conflict_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	private function apply_import( int $post_id, string $source_json ): void {
		$source = json_decode( $source_json, true );
		if ( ! is_array( $source ) ) {
			return;
		}

		foreach ( $source as $key => $value ) {
			if ( 0 !== strpos( (string) $key, '_immo_' ) ) {
				continue;
			}
			if ( '_immo_openimmo_pending_attachment_ids' === $key ) {
				continue;
			}
			if ( '' === $value || null === $value
				|| ( is_array( $value ) && empty( $value ) ) ) {
				continue;
			}
			update_post_meta( $post_id, $key, $value );
		}

		if ( ! empty( $source['_immo_openimmo_pending_attachment_ids'] ) ) {
			$pending = json_decode( (string) $source['_immo_openimmo_pending_attachment_ids'], true );
			if ( is_array( $pending ) ) {
				$this->attach_pending_images( $post_id, $pending );
			}
		}
	}

	/**
	 * @param array<int,array{att_id:int, gruppe:string}> $pending
	 */
	private function attach_pending_images( int $post_id, array $pending ): void {
		// 1. Bestehende Galerie-Attachments ent-verknüpfen.
		$existing = get_attached_media( 'image', $post_id );
		foreach ( $existing as $att ) {
			if ( ! $att instanceof \WP_Post ) {
				continue;
			}
			wp_update_post( array( 'ID' => (int) $att->ID, 'post_parent' => 0 ) );
		}

		// 2. TITELBILD setzen (erstes Pending mit gruppe='TITELBILD').
		foreach ( $pending as $p ) {
			$att_id = isset( $p['att_id'] ) ? (int) $p['att_id'] : 0;
			$gruppe = isset( $p['gruppe'] ) ? (string) $p['gruppe'] : 'BILD';
			if ( 'TITELBILD' === $gruppe && $att_id > 0 && get_post( $att_id ) instanceof \WP_Post ) {
				set_post_thumbnail( $post_id, $att_id );
				break;
			}
		}

		// 3. Galerie-Attachments verknüpfen.
		foreach ( $pending as $p ) {
			$att_id = isset( $p['att_id'] ) ? (int) $p['att_id'] : 0;
			$gruppe = isset( $p['gruppe'] ) ? (string) $p['gruppe'] : 'BILD';
			if ( 'TITELBILD' === $gruppe ) {
				continue;
			}
			if ( $att_id > 0 && get_post( $att_id ) instanceof \WP_Post ) {
				wp_update_post( array( 'ID' => $att_id, 'post_parent' => $post_id ) );
			}
		}
	}
}
