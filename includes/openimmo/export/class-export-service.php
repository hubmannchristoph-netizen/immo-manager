<?php
namespace ImmoManager\OpenImmo\Export;

use ImmoManager\OpenImmo\Settings;
use ImmoManager\OpenImmo\SyncLog;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrator: führt einen Export-Lauf für ein Portal durch.
 */
class ExportService {

	/**
	 * Lädt alle Sub-Namespace-Klassen, die ExportService braucht
	 * (Autoloader greift nicht für ImmoManager\OpenImmo\* und \Export\*).
	 *
	 * @return void
	 */
	public static function require_dependencies(): void {
		$base = dirname( __FILE__ );
		require_once $base . '/class-listing-dto.php';
		require_once $base . '/class-collector.php';
		require_once $base . '/class-xml-builder.php';
		require_once $base . '/class-xsd-validator.php';
		require_once $base . '/class-mapper.php';
		require_once $base . '/class-image-processor.php';
		require_once $base . '/class-zip-packager.php';
		// ExportService selbst wird vom Caller geladen, weil dieser Aufruf
		// sonst eine Henne-Ei-Situation wäre.
	}

	/**
	 * @return array{status:string, summary:string, zip_path:string, count:int}
	 */
	public function run( string $portal_key ): array {
		$sync_id = SyncLog::start( $portal_key, 'export' );

		if ( 0 === $sync_id ) {
			error_log( '[immo-manager openimmo] SyncLog::start failed for portal ' . $portal_key . ' — proceeding without log persistence' );
		}

		try {
			$settings = Settings::get();
			$portal   = $settings['portals'][ $portal_key ] ?? null;
			if ( null === $portal ) {
				return $this->dispatch_finish( $portal_key, $sync_id, 'error', 'unknown portal: ' . $portal_key, '-', 0, array() );
			}

			$collector = new Collector();
			$listings  = $collector->gather( $portal_key );
			if ( empty( $listings ) ) {
				return $this->dispatch_finish( $portal_key, $sync_id, 'skipped', 'no listings opted in', '-', 0, array() );
			}

			// XML-Skelett.
			$builder = new XmlBuilder();
			$builder->open( $portal, $this->plugin_version() );

			// Bilder vorbereiten.
			$upload_dir = wp_get_upload_dir();
			$tmp_base   = $upload_dir['basedir'] . '/openimmo/tmp/' . $portal_key . '-' . time();
			$imager     = new ImageProcessor( $tmp_base );

			try {
				$mapper     = new Mapper( $builder->dom() );
				$all_images = array();
				$errors     = array();
				$img_errors = array();
				$exported   = 0;

				foreach ( $listings as $listing ) {
					try {
						$immobilie = $mapper->to_immobilie( $listing );
						$images    = $imager->resize_attached( $listing );
						$this->append_anhaenge( $immobilie, $images, $builder->dom() );
						$builder->append_immobilie( $immobilie );
						foreach ( $images as $img ) {
							$all_images[] = $img;
						}
						$img_errors = array_merge( $img_errors, $imager->get_errors() );
						++$exported;
					} catch ( \Throwable $e ) {
						$errors[] = array( 'id' => $listing->external_id, 'message' => $e->getMessage() );
					}
				}

				// XSD-Validierung.
				$validator = new XsdValidator();
				$check     = $validator->validate( $builder->dom(), XsdValidator::default_xsd_path() );
				if ( ! $check['valid'] ) {
					$debug_path = $upload_dir['basedir'] . '/openimmo/exports/' . $portal_key . '/' . gmdate( 'Y-m-d-His' ) . '.invalid.xml';
					wp_mkdir_p( dirname( $debug_path ) );
					file_put_contents( $debug_path, $builder->to_string() );
					return $this->dispatch_finish( $portal_key, $sync_id, 'error', 'XSD invalid', '-', 0, array(
						'errors'       => $errors,
						'image_errors' => $img_errors,
						'xsd_errors'   => $check['errors'],
						'invalid_xml'  => $debug_path,
					) );
				}

				// ZIP packen.
				$target  = sprintf(
					'%s/openimmo/exports/%s/%s-export-%s.zip',
					$upload_dir['basedir'],
					$portal_key,
					$portal_key,
					gmdate( 'Y-m-d-His' )
				);
				$packer  = new ZipPackager();
				$written = $packer->write( $builder->dom(), $all_images, $target );

				if ( ! $written ) {
					return $this->dispatch_finish( $portal_key, $sync_id, 'error', 'ZIP write failed', $target, 0, array(
						'errors' => $errors,
					) );
				}

				$status  = empty( $errors ) ? 'success' : 'partial';
				$summary = sprintf(
					'%d/%d Listings exportiert%s',
					$exported,
					count( $listings ),
					empty( $errors ) ? '' : sprintf( ', %d übersprungen', count( $errors ) )
				);

				// SFTP-Upload (Phase 2).
				$uploader      = \ImmoManager\Plugin::instance()->get_openimmo_sftp_uploader();
				$upload_result = $uploader->upload( $target, $portal_key );
				$sftp_details  = array(
					'status'      => $upload_result['status'],
					'attempts'    => $upload_result['attempts'],
					'remote_path' => $upload_result['remote_path'] ?? '',
					'error'       => $upload_result['error'] ?? null,
				);

				if ( 'error' === $upload_result['status'] ) {
					$status   = 'error';
					$summary .= ' — SFTP-Upload fehlgeschlagen: ' . ( $upload_result['error'] ?? 'unbekannt' );
				} elseif ( 'skipped' === $upload_result['status'] ) {
					$summary .= ' (SFTP-Upload übersprungen: ' . ( $upload_result['summary'] ?? 'lock held' ) . ')';
				}

				return $this->dispatch_finish( $portal_key, $sync_id, $status, $summary, $target, $exported, array(
					'errors'              => $errors,
					'image_errors'        => $img_errors,
					'zip_path'            => $target,
					'openimmo_xml_size_kb' => (int) ( strlen( $builder->to_string() ) / 1024 ),
					'sftp'                => $sftp_details,
				) );

			} finally {
				$imager->cleanup();
			}

		} catch ( \Throwable $e ) {
			return $this->dispatch_finish( $portal_key, $sync_id, 'error', $e->getMessage(), '-', 0, array(
				'exception' => $e->getMessage(),
				'trace'     => $e->getTraceAsString(),
			) );
		}
	}

	private function append_anhaenge( \DOMElement $immobilie, array $images, \DOMDocument $dom ): void {
		$container = $immobilie->getElementsByTagName( 'anhaenge' )->item( 0 );
		if ( ! $container instanceof \DOMElement ) {
			return;
		}
		foreach ( $images as $img ) {
			$gruppe = (string) ( $img['gruppe'] ?? 'BILD' );
			$anhang = $dom->createElement( 'anhang' );
			$anhang->setAttribute( 'location', 'EXTERN' );
			$anhang->setAttribute( 'gruppe',   $gruppe );

			$titel = $dom->createElement( 'anhangtitel' );
			$titel->appendChild( $dom->createTextNode( 'TITELBILD' === $gruppe ? 'Hauptansicht' : 'Bild' ) );
			$anhang->appendChild( $titel );

			$format = $dom->createElement( 'format' );
			$format->appendChild( $dom->createTextNode( 'jpg' ) );
			$anhang->appendChild( $format );

			$daten = $dom->createElement( 'daten' );
			$pfad  = $dom->createElement( 'pfad' );
			$pfad->appendChild( $dom->createTextNode( (string) $img['relpath'] ) );
			$daten->appendChild( $pfad );
			$anhang->appendChild( $daten );

			$container->appendChild( $anhang );
		}
	}

	private function plugin_version(): string {
		if ( defined( 'IMMO_MANAGER_VERSION' ) ) {
			return (string) IMMO_MANAGER_VERSION;
		}
		return '0.0.0';
	}

	/**
	 * @param array<string,mixed> $details
	 * @return array{status:string, summary:string, zip_path:string, count:int}
	 */
	/**
	 * Wrapper um finish(), triggert bei status='error' eine E-Mail.
	 */
	private function dispatch_finish( string $portal_key, int $sync_id, string $status, string $summary, string $zip_path, int $count, array $details ): array {
		$result = $this->finish( $sync_id, $status, $summary, $zip_path, $count, $details );
		if ( 'error' === $status ) {
			\ImmoManager\Plugin::instance()->get_openimmo_email_notifier()
				->notify_sync_error( $portal_key, 'export', $summary, $details );
		}
		return $result;
	}

	private function finish( int $sync_id, string $status, string $summary, string $zip_path, int $count, array $details ): array {
		if ( 0 !== $sync_id ) {
			SyncLog::finish( $sync_id, $status, $summary, $details, $count );
		} else {
			error_log( sprintf( '[immo-manager openimmo] Export finished without log persistence (status=%s, summary=%s)', $status, $summary ) );
		}
		return array(
			'status'   => $status,
			'summary'  => $summary,
			'zip_path' => $zip_path,
			'count'    => $count,
		);
	}
}
