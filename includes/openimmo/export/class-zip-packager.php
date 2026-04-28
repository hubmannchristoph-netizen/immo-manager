<?php
namespace ImmoManager\OpenImmo\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Packt OpenImmo-Export als ZIP.
 */
class ZipPackager {

	/**
	 * @param array<int,array{relpath:string,tmppath:string}> $images
	 */
	public function write( \DOMDocument $dom, array $images, string $target_path ): bool {
		$dir = dirname( $target_path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			$this->protect_dir( $dir );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $target_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			return false;
		}

		$zip->addFromString( 'openimmo.xml', (string) $dom->saveXML() );

		foreach ( $images as $img ) {
			if ( file_exists( $img['tmppath'] ) ) {
				$zip->addFile( $img['tmppath'], $img['relpath'] );
			}
		}

		return $zip->close();
	}

	private function protect_dir( string $dir ): void {
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Deny from all\n" );
		}
		$index = $dir . '/index.html';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '' );
		}
	}
}
