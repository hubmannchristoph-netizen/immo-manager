<?php
/**
 * Orchestrator: führt einen OpenImmo-Import durch.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Import;

use ImmoManager\OpenImmo\SyncLog;
use ImmoManager\OpenImmo\Conflicts;
use ImmoManager\PostTypes;

defined( 'ABSPATH' ) || exit;

class ImportService {

	/**
	 * @return array{status:string, summary:string, counts:array{new:int, conflicts:int, skipped:int, errors:int}, sync_id:int}
	 */
	public function run( string $zip_path ): array {
		$sync_id = SyncLog::start( 'import', 'import' );
		if ( 0 === $sync_id ) {
			error_log( '[immo-manager openimmo] SyncLog::start failed for import — proceeding without persistence' );
		}

		$counts     = array( 'new' => 0, 'conflicts' => 0, 'skipped' => 0, 'errors' => 0 );
		$errors     = array();
		$img_errors = array();

		$extractor = new ZipExtractor();
		try {
			$extracted = $extractor->extract( $zip_path );
		} catch ( \Throwable $e ) {
			return $this->finish( $sync_id, 'error', 'ZIP entpacken fehlgeschlagen: ' . $e->getMessage(), $counts, array() );
		}

		try {
			$dom = new \DOMDocument();
			libxml_use_internal_errors( true );
			$loaded = $dom->load( $extracted['xml_path'] );
			if ( ! $loaded ) {
				$libxml_errs = libxml_get_errors();
				libxml_clear_errors();
				$first = isset( $libxml_errs[0] ) ? trim( $libxml_errs[0]->message ) : 'unknown';
				return $this->finish( $sync_id, 'error', 'XML invalid: ' . $first, $counts, array(
					'libxml_errors' => array_map(
						static function ( $e ) {
							return array( 'line' => (int) $e->line, 'message' => trim( $e->message ) );
						},
						$libxml_errs
					),
				) );
			}

			$mapper    = new ImportMapper();
			$image_imp = new ImageImporter();
			$detector  = new ConflictDetector();

			$immobilien = $dom->getElementsByTagName( 'immobilie' );
			foreach ( $immobilien as $immobilie ) {
				if ( ! $immobilie instanceof \DOMElement ) {
					continue;
				}
				$dto = null;
				try {
					$dto         = $mapper->from_immobilie( $immobilie );
					$attachments = $image_imp->import_all( $dto->raw_images, $extracted['images_dir'] );
					$detection   = $detector->detect( $dto );

					if ( 'new' === $detection['kind'] ) {
						$post_id = $this->create_property( $dto, $attachments );
						if ( $post_id > 0 ) {
							++$counts['new'];
						} else {
							++$counts['errors'];
							$errors[] = array( 'external_id' => $dto->external_id, 'message' => 'wp_insert_post failed' );
						}
					} else {
						$post_id     = $detection['post_id'] ?? 0;
						$conflict_id = $this->record_conflict( (int) $post_id, $dto, $attachments );
						if ( $conflict_id > 0 ) {
							++$counts['conflicts'];
						} else {
							++$counts['skipped'];
						}
					}
				} catch ( \Throwable $e ) {
					++$counts['errors'];
					$errors[] = array(
						'external_id' => $dto instanceof ImportListingDTO ? $dto->external_id : '(parse failed)',
						'message'     => $e->getMessage(),
					);
				}
			}
		} finally {
			$extractor->cleanup();
		}

		$status  = ( 0 === $counts['errors'] ) ? 'success' : 'partial';
		$summary = sprintf(
			'%d neu, %d Konflikte, %d übersprungen, %d Fehler',
			$counts['new'],
			$counts['conflicts'],
			$counts['skipped'],
			$counts['errors']
		);
		return $this->finish( $sync_id, $status, $summary, $counts, array(
			'errors'       => $errors,
			'image_errors' => $img_errors,
		) );
	}

	/**
	 * @param array<int,array{att_id:int, gruppe:string, hash:string}> $attachments
	 */
	private function create_property( ImportListingDTO $dto, array $attachments ): int {
		$post_id = wp_insert_post( array(
			'post_type'    => PostTypes::POST_TYPE_PROPERTY,
			'post_title'   => '' !== $dto->title ? $dto->title : '(unbenannt)',
			'post_content' => $dto->description,
			'post_status'  => 'publish',
		) );
		if ( is_wp_error( $post_id ) || 0 === (int) $post_id ) {
			return 0;
		}
		$post_id = (int) $post_id;

		foreach ( $dto->meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		update_post_meta( $post_id, '_immo_openimmo_external_id', $dto->external_id );

		$titel_id = 0;
		foreach ( $attachments as $a ) {
			if ( 'TITELBILD' === $a['gruppe'] ) {
				$titel_id = (int) $a['att_id'];
				break;
			}
		}
		if ( $titel_id > 0 ) {
			set_post_thumbnail( $post_id, $titel_id );
		}
		foreach ( $attachments as $a ) {
			if ( 'TITELBILD' === $a['gruppe'] ) {
				continue;
			}
			wp_update_post( array(
				'ID'          => (int) $a['att_id'],
				'post_parent' => $post_id,
			) );
		}

		return $post_id;
	}

	/**
	 * @param array<int,array{att_id:int, gruppe:string, hash:string}> $attachments
	 */
	private function record_conflict( int $post_id, ImportListingDTO $dto, array $attachments ): int {
		if ( 0 === $post_id ) {
			return 0;
		}
		$current = $this->collect_current_meta( $post_id );
		$changed = array();

		foreach ( $dto->meta as $key => $incoming_value ) {
			if ( '' === $incoming_value || null === $incoming_value
				|| ( is_array( $incoming_value ) && empty( $incoming_value ) ) ) {
				continue;
			}
			$current_value = $current[ $key ] ?? '';
			if ( $this->values_differ( $current_value, $incoming_value ) ) {
				$changed[ $key ] = array( 'current' => $current_value, 'incoming' => $incoming_value );
			}
		}

		if ( empty( $changed ) ) {
			return 0;
		}

		$source_with_attachments                                          = $dto->meta;
		$source_with_attachments['_immo_openimmo_pending_attachment_ids'] = wp_json_encode(
			array_map(
				static function ( $a ) {
					return array( 'att_id' => (int) $a['att_id'], 'gruppe' => (string) $a['gruppe'] );
				},
				$attachments
			)
		);

		return Conflicts::add( $post_id, 'import', $source_with_attachments, $current, array_keys( $changed ) );
	}

	private function collect_current_meta( int $post_id ): array {
		$raw = get_post_meta( $post_id );
		$out = array();
		foreach ( $raw as $key => $values ) {
			if ( 0 === strpos( $key, '_immo_' ) && isset( $values[0] ) ) {
				$out[ $key ] = maybe_unserialize( $values[0] );
			}
		}
		return $out;
	}

	private function values_differ( $a, $b ): bool {
		if ( is_numeric( $a ) && is_numeric( $b ) ) {
			return abs( (float) $a - (float) $b ) > 0.001;
		}
		if ( is_array( $a ) || is_array( $b ) ) {
			$a_norm = is_array( $a ) ? array_values( $a ) : array();
			$b_norm = is_array( $b ) ? array_values( $b ) : array();
			sort( $a_norm );
			sort( $b_norm );
			return $a_norm !== $b_norm;
		}
		return trim( (string) $a ) !== trim( (string) $b );
	}

	/**
	 * @param array{new:int, conflicts:int, skipped:int, errors:int} $counts
	 * @param array<string,mixed>                                    $details
	 */
	private function finish( int $sync_id, string $status, string $summary, array $counts, array $details ): array {
		$details['counts'] = $counts;
		$total             = array_sum( $counts );
		if ( 0 !== $sync_id ) {
			SyncLog::finish( $sync_id, $status, $summary, $details, $total );
		}
		return array(
			'status'  => $status,
			'summary' => $summary,
			'counts'  => $counts,
			'sync_id' => $sync_id,
		);
	}
}
