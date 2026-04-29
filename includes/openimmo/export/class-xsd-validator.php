<?php
namespace ImmoManager\OpenImmo\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Validiert ein DOMDocument gegen ein lokales XSD-Schema.
 */
class XsdValidator {

	/**
	 * @return array{valid:bool, errors:array<int,array{line:int,message:string}>}
	 */
	public function validate( \DOMDocument $dom, string $xsd_path ): array {
		if ( ! file_exists( $xsd_path ) ) {
			return array(
				'valid'  => false,
				'errors' => array( array( 'line' => 0, 'message' => 'XSD not found: ' . $xsd_path ) ),
			);
		}

		$previous = libxml_use_internal_errors( true );
		libxml_clear_errors();
		$ok = $dom->schemaValidate( $xsd_path );

		$errors = array();
		if ( ! $ok ) {
			foreach ( libxml_get_errors() as $err ) {
				$errors[] = array(
					'line'    => (int) $err->line,
					'message' => trim( (string) $err->message ),
				);
			}
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return array(
			'valid'  => (bool) $ok,
			'errors' => $errors,
		);
	}

	public static function default_xsd_path(): string {
		return dirname( __DIR__, 3 ) . '/vendor-schemas/openimmo-1.2.7/openimmo.xsd';
	}
}
