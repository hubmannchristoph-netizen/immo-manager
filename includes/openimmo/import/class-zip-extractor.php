<?php
/**
 * Entpackt ein OpenImmo-ZIP in ein temporäres Verzeichnis.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Import;

defined( 'ABSPATH' ) || exit;

class ZipExtractor {

	private string $tmp_base = '';

	/**
	 * @return array{xml_path:string, images_dir:string, tmp_base:string}
	 * @throws \RuntimeException
	 */
	public function extract( string $zip_path ): array {
		if ( ! file_exists( $zip_path ) ) {
			throw new \RuntimeException( 'ZIP nicht gefunden: ' . $zip_path );
		}

		$upload_dir     = wp_get_upload_dir();
		$basename       = preg_replace( '/[^A-Za-z0-9._-]/', '_', basename( $zip_path, '.zip' ) );
		$this->tmp_base = $upload_dir['basedir'] . '/openimmo/import-tmp/' . $basename . '-' . time();

		if ( ! wp_mkdir_p( $this->tmp_base ) ) {
			throw new \RuntimeException( 'Konnte temp-Verzeichnis nicht anlegen: ' . $this->tmp_base );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			throw new \RuntimeException( 'ZIP nicht entpackbar: ' . $zip_path );
		}
		if ( ! $zip->extractTo( $this->tmp_base ) ) {
			$zip->close();
			throw new \RuntimeException( 'Entpacken fehlgeschlagen: ' . $zip_path );
		}
		$zip->close();

		$xml_path = $this->find_openimmo_xml( $this->tmp_base );
		if ( null === $xml_path ) {
			throw new \RuntimeException( 'openimmo.xml fehlt im ZIP' );
		}

		$images_dir = dirname( $xml_path ) . '/images';
		if ( ! is_dir( $images_dir ) ) {
			$images_dir = '';
		}

		return array(
			'xml_path'   => $xml_path,
			'images_dir' => $images_dir,
			'tmp_base'   => $this->tmp_base,
		);
	}

	public function cleanup(): void {
		if ( '' === $this->tmp_base ) {
			return;
		}
		$this->rrmdir( $this->tmp_base );
		$this->tmp_base = '';
	}

	private function find_openimmo_xml( string $dir ): ?string {
		if ( file_exists( $dir . '/openimmo.xml' ) ) {
			return $dir . '/openimmo.xml';
		}
		$entries = scandir( $dir );
		if ( ! is_array( $entries ) ) {
			return null;
		}
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( is_dir( $path ) && file_exists( $path . '/openimmo.xml' ) ) {
				return $path . '/openimmo.xml';
			}
		}
		return null;
	}

	private function rrmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( ! is_array( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->rrmdir( $path );
			} else {
				@unlink( $path );  // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}
		@rmdir( $dir );  // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}
