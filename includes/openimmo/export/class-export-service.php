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
	 * @return array{status:string, summary:string, zip_path:string, count:int}
	 */
	public function run( string $portal_key ): array {
		$sync_id = SyncLog::start( $portal_key, 'export' );

		try {
			$settings = Settings::get();
			$portal   = $settings['portals'][ $portal_key ] ?? null;
			if ( null === $portal ) {
				return $this->finish( $sync_id, 'error', 'unknown portal: ' . $portal_key, '', 0, array() );
			}

			$collector = new Collector();
			$listings  = $collector->gather( $portal_key );
			if ( empty( $listings ) ) {
				return $this->finish( $sync_id, 'skipped', 'no listings opted in', '', 0, array() );
			}

			// XML-Skelett.
			$builder = new XmlBuilder();
			$builder->open( $portal, $this->plugin_version() );

			// Bilder vorbereiten.
			$upload_dir = wp_get_upload_dir();
			$tmp_base   = $upload_dir['basedir'] . '/openimmo/tmp/' . $portal_key . '-' . time();
			$imager     = new ImageProcessor( $tmp_base );

			$mapper      = new Mapper( $builder->dom() );
			$all_images  = array();
			$errors      = array();
			$img_errors  = array();
			$exported    = 0;

			foreach ( $listings as $listing ) {
				try {
					$immobilie = $mapper->to_immobilie( $listing );
					$images    = $imager->resize_attached( $listing );
					$this->append_anhaenge( $immobilie, $images, $builder->dom() );
					$builder->append_immobilie( $immobilie );
					$all_images = array_merge( $all_images, $images );
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
				$imager->cleanup();
				return $this->finish( $sync_id, 'error', 'XSD invalid', '', 0, array(
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
			$imager->cleanup();

			if ( ! $written ) {
				return $this->finish( $sync_id, 'error', 'ZIP write failed', $target, 0, array(
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
			return $this->finish( $sync_id, $status, $summary, $target, $exported, array(
				'errors'       => $errors,
				'image_errors' => $img_errors,
				'zip_path'     => $target,
				'xml_size_kb'  => (int) ( strlen( $builder->to_string() ) / 1024 ),
			) );

		} catch ( \Throwable $e ) {
			return $this->finish( $sync_id, 'error', $e->getMessage(), '', 0, array(
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
			$anhang = $dom->createElement( 'anhang' );
			$anhang->setAttribute( 'location', 'EXTERN' );
			$anhang->setAttribute( 'gruppe',   (string) ( $img['gruppe'] ?? 'BILD' ) );

			$titel = $dom->createElement( 'anhangtitel' );
			$titel->appendChild( $dom->createTextNode( '' ) );
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
	private function finish( int $sync_id, string $status, string $summary, string $zip_path, int $count, array $details ): array {
		SyncLog::finish( $sync_id, $status, $summary, $details, $count );
		return array(
			'status'   => $status,
			'summary'  => $summary,
			'zip_path' => $zip_path,
			'count'    => $count,
		);
	}
}
