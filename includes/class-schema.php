<?php
/**
 * Schema.org JSON-LD Auslieferung für Property-, Project- und Breadcrumb-Seiten.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schema
 *
 * Liefert strukturierte Daten (RealEstateListing, ApartmentComplex, BreadcrumbList)
 * im wp_head-Bereich aus. Komplett getrennt von Templates.
 */
class Schema {

	/**
	 * Konstruktor – Hook-Registrierung.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'render' ), 30 );
	}

	/**
	 * wp_head-Callback – entscheidet was ausgegeben wird.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( is_singular( PostTypes::POST_TYPE_PROPERTY ) ) {
			echo "\n<!-- Immo Manager Schema (Property) -->\n";
			return;
		}

		if ( is_singular( PostTypes::POST_TYPE_PROJECT ) ) {
			echo "\n<!-- Immo Manager Schema (Project) -->\n";
			return;
		}
	}
}
