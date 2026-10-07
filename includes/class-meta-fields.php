<?php
/**
 * Zentrale Meta-Field-Definitionen + Sanitization + REST-Registrierung.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class MetaFields
 *
 * Die Single-Source-of-Truth für alle Post-Meta-Felder, die Immo Manager
 * auf Immobilien und Bauprojekten speichert. Die Definitionen werden
 * für Sanitization, Metabox-Rendering und REST-API-Support verwendet.
 */
class MetaFields {

	/**
	 * Konstruktor – registriert die Meta-Felder für REST.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
	}

	/**
	 * Alle Property-Meta-Felder.
	 *
	 * @return array<string, array{type: string, enum?: array, default?: mixed, single?: bool, show_in_rest?: bool}>
	 */
	public static function property_fields(): array {
		return array(
			// Grundlegend.
			'_immo_mode'              => array( 'type' => 'string',  'enum' => array( 'sale', 'rent', 'both' ), 'default' => 'sale' ),
			'_immo_status'            => array( 'type' => 'string',  'enum' => array( 'available', 'reserved', 'sold', 'rented' ), 'default' => 'available' ),
			'_immo_property_type'     => array( 'type' => 'string',  'default' => '' ),

			// Standort.
			'_immo_address'           => array( 'type' => 'string',  'default' => '' ),
			'_immo_postal_code'       => array( 'type' => 'string',  'default' => '' ),
			'_immo_city'              => array( 'type' => 'string',  'default' => '' ),
			'_immo_region_state'      => array( 'type' => 'string',  'default' => '' ),
			'_immo_region_district'   => array( 'type' => 'string',  'default' => '' ),
			'_immo_country'           => array( 'type' => 'string',  'default' => 'AT' ),
			'_immo_lat'               => array( 'type' => 'number',  'default' => 0 ),
			'_immo_lng'               => array( 'type' => 'number',  'default' => 0 ),

			// Details.
			'_immo_area'              => array( 'type' => 'number',  'default' => 0 ),
			'_immo_usable_area'       => array( 'type' => 'number',  'default' => 0 ),
			'_immo_land_area'         => array( 'type' => 'number',  'default' => 0 ),
			'_immo_rooms'             => array( 'type' => 'integer', 'default' => 0 ),
			'_immo_bedrooms'          => array( 'type' => 'integer', 'default' => 0 ),
			'_immo_bathrooms'         => array( 'type' => 'integer', 'default' => 0 ),
			'_immo_floor'             => array( 'type' => 'integer', 'default' => 0 ),
			'_immo_total_floors'      => array( 'type' => 'integer', 'default' => 0 ),
			'_immo_built_year'        => array( 'type' => 'integer', 'default' => 0 ),
			'_immo_renovation_year'   => array( 'type' => 'integer', 'default' => 0 ),

			// Energie.
			// Energieausweis (EAVG § 3, Novelle 1.7.2026): Pflicht im Inserat sind
			// Energieeffizienzklasse, HWB und Endenergiebedarf (EEB). fGEE nur noch bei
			// Ausweisen nach altem Recht (Übergangsregel) – bleibt als Legacy-Feld erhalten.
			'_immo_energy_class'      => array( 'type' => 'string',  'enum' => array( '', 'A++', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G' ), 'default' => '' ),
			'_immo_energy_hwb'        => array( 'type' => 'number',  'default' => 0 ),
			'_immo_energy_eeb'        => array( 'type' => 'number',  'default' => 0 ),
			'_immo_energy_fgee'       => array( 'type' => 'number',  'default' => 0 ),
			'_immo_heating'           => array( 'type' => 'string',  'default' => '' ),

			// Preis.
			'_immo_price'             => array( 'type' => 'number',  'default' => 0 ),
			'_immo_rent'              => array( 'type' => 'number',  'default' => 0 ),
			// Betriebsnebenkosten pro Monat – alle Werte BRUTTO (inkl. USt).
			'_immo_operating_costs'   => array( 'type' => 'number',  'default' => 0 ), // Betriebskosten
			'_immo_heating_costs'     => array( 'type' => 'number',  'default' => 0 ), // Heizkosten
			'_immo_other_costs'       => array( 'type' => 'number',  'default' => 0 ), // Sonstige Kosten
			'_immo_deposit'           => array( 'type' => 'number',  'default' => 0 ),
			'_immo_commission'        => array( 'type' => 'string',  'default' => '' ),
			'_immo_commission_free'   => array( 'type' => 'boolean', 'default' => false ),
			'_immo_available_from'    => array( 'type' => 'string',  'default' => '' ),

			// Features.
			'_immo_features'          => array( 'type' => 'array',   'default' => array(), 'single' => true, 'show_in_rest' => false ),
			'_immo_custom_features'   => array( 'type' => 'string',  'default' => '' ),
			'_immo_documents'         => array( 'type' => 'array',   'default' => array(), 'single' => true, 'show_in_rest' => false ),
			'_immo_video_url'         => array( 'type' => 'string',  'default' => '' ),
			'_immo_video_id'          => array( 'type' => 'integer', 'default' => 0 ),

			// Kontakt.
			'_immo_contact_name'      => array( 'type' => 'string',  'default' => '' ),
			'_immo_contact_email'     => array( 'type' => 'string',  'default' => '' ),
			'_immo_contact_phone'     => array( 'type' => 'string',  'default' => '' ),
			'_immo_contact_image_id'  => array( 'type' => 'integer', 'default' => 0 ),

			// Relation zu Projekt.
			'_immo_project_id'        => array( 'type' => 'integer', 'default' => 0 ),
			'_immo_project_unit_id'   => array( 'type' => 'integer', 'default' => 0 ),

			// Layout-Overrides (leer = Globaler Standard).
			'_immo_layout_type'       => array( 'type' => 'string',  'enum' => array( '', 'standard', 'compact' ), 'default' => '' ),
			'_immo_gallery_type'      => array( 'type' => 'string',  'enum' => array( '', 'slider', 'grid' ), 'default' => '' ),
			'_immo_hero_type'         => array( 'type' => 'string',  'enum' => array( '', 'full', 'contained' ), 'default' => '' ),

			// OpenImmo-Export Opt-In (Phase 1).
			'_immo_openimmo_willhaben'    => array( 'type' => 'boolean', 'default' => false ),
			'_immo_openimmo_immoscout24'  => array( 'type' => 'boolean', 'default' => false ),
			// OpenImmo-Import-Tracking (Phase 3).
			'_immo_openimmo_external_id'  => array( 'type' => 'string',  'default' => '' ),
		);
	}

	/**
	 * Alle Project-Meta-Felder.
	 *
	 * @return array<string, array{type: string, enum?: array, default?: mixed}>
	 */
	public static function project_fields(): array {
		return array(
			'_immo_project_status'        => array( 'type' => 'string',  'enum' => array( 'planning', 'building', 'completed' ), 'default' => 'planning' ),
			'_immo_project_start_date'    => array( 'type' => 'string',  'default' => '' ),
			'_immo_project_completion'    => array( 'type' => 'string',  'default' => '' ),
			// Standort (gleiche Logik wie bei Property).
			'_immo_address'               => array( 'type' => 'string',  'default' => '' ),
			'_immo_postal_code'           => array( 'type' => 'string',  'default' => '' ),
			'_immo_city'                  => array( 'type' => 'string',  'default' => '' ),
			'_immo_region_state'          => array( 'type' => 'string',  'default' => '' ),
			'_immo_region_district'       => array( 'type' => 'string',  'default' => '' ),
			'_immo_country'               => array( 'type' => 'string',  'default' => 'AT' ),
			'_immo_lat'                   => array( 'type' => 'number',  'default' => 0 ),
			'_immo_lng'                   => array( 'type' => 'number',  'default' => 0 ),
			// Gemeinschafts-Features.
			'_immo_features'              => array( 'type' => 'array',   'default' => array(), 'single' => true, 'show_in_rest' => false ),
			'_immo_documents'             => array( 'type' => 'array',   'default' => array(), 'single' => true, 'show_in_rest' => false ),
			'_immo_video_url'             => array( 'type' => 'string',  'default' => '' ),
			'_immo_video_id'              => array( 'type' => 'integer', 'default' => 0 ),
			
			// Kontakt.
			'_immo_contact_name'          => array( 'type' => 'string',  'default' => '' ),
			'_immo_contact_email'         => array( 'type' => 'string',  'default' => '' ),
			'_immo_contact_phone'         => array( 'type' => 'string',  'default' => '' ),
			'_immo_contact_image_id'      => array( 'type' => 'integer', 'default' => 0 ),

			// Layout-Overrides (leer = Globaler Standard).
			'_immo_layout_type'       => array( 'type' => 'string',  'enum' => array( '', 'standard', 'compact' ), 'default' => '' ),
			'_immo_gallery_type'      => array( 'type' => 'string',  'enum' => array( '', 'slider', 'grid' ), 'default' => '' ),
			'_immo_hero_type'         => array( 'type' => 'string',  'enum' => array( '', 'full', 'contained' ), 'default' => '' ),

			// Stellplatz-Konfiguration (Phase 1 Wohneinheiten-Erweiterung).
			'_immo_parking_garage_available'  => array( 'type' => 'boolean', 'default' => false ),
			'_immo_parking_garage_total'      => array( 'type' => 'integer', 'default' => 0 ),
			'_immo_parking_garage_price'      => array( 'type' => 'number',  'default' => 0 ),
			'_immo_parking_garage_required'   => array( 'type' => 'boolean', 'default' => false ),
			'_immo_parking_outdoor_available' => array( 'type' => 'boolean', 'default' => false ),
			'_immo_parking_outdoor_total'     => array( 'type' => 'integer', 'default' => 0 ),
			'_immo_parking_outdoor_price'     => array( 'type' => 'number',  'default' => 0 ),
			'_immo_parking_outdoor_required'  => array( 'type' => 'boolean', 'default' => false ),
			'_immo_parking_notes'             => array( 'type' => 'string',  'default' => '' ),
		);
	}

	/**
	 * Energieeffizienzklassen (Auswahlliste).
	 *
	 * Neue Skala laut OIB-Richtlinie 6:2025 ist A–G; A++ und A+ bleiben für
	 * Ausweise nach altem Recht (OIB 6:2019/2023) auswählbar.
	 *
	 * @return array<int, string>
	 */
	public static function energy_classes(): array {
		return array( 'A++', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G' );
	}

	/**
	 * Prüft, ob die Energieausweis-Pflichtangaben für ein Inserat vollständig sind.
	 *
	 * Regel (EAVG § 3 i. d. F. 2026, identisch zu Vividomo isEnergyDataComplete):
	 * Energieklasse UND HWB UND (EEB ODER fGEE als Übergangsregel für Altausweise).
	 *
	 * @param array<string, mixed> $meta Meta-Array mit _immo_energy_*-Keys.
	 *
	 * @return array<int, string> Liste fehlender Angaben (leer = vollständig).
	 */
	public static function missing_energy_fields( array $meta ): array {
		$missing = array();
		if ( '' === (string) ( $meta['_immo_energy_class'] ?? '' ) ) {
			$missing[] = __( 'Energieeffizienzklasse', 'immo-manager' );
		}
		if ( (float) ( $meta['_immo_energy_hwb'] ?? 0 ) <= 0 ) {
			$missing[] = __( 'Heizwärmebedarf (HWB)', 'immo-manager' );
		}
		if ( (float) ( $meta['_immo_energy_eeb'] ?? 0 ) <= 0 && (float) ( $meta['_immo_energy_fgee'] ?? 0 ) <= 0 ) {
			$missing[] = __( 'Endenergiebedarf (EEB) – bzw. fGEE bei Altausweis', 'immo-manager' );
		}
		return $missing;
	}

	/**
	 * Ist für diesen Immobilientyp ein Energieausweis erforderlich?
	 *
	 * Unbebaute Grundstücke brauchen keinen Energieausweis. Über den Filter
	 * `immo_manager_energy_certificate_required` anpassbar.
	 *
	 * @param string $property_type Immobilientyp (Freitext, z. B. "Grundstück").
	 *
	 * @return bool
	 */
	public static function energy_certificate_required( string $property_type ): bool {
		$type     = mb_strtolower( $property_type );
		$required = '' === $type || false === strpos( $type, 'grund' );

		/**
		 * Steuert, ob die Energieausweis-Pflichtangaben für diesen Typ erzwungen werden.
		 *
		 * @param bool   $required      Pflicht ja/nein.
		 * @param string $property_type Immobilientyp.
		 */
		return (bool) apply_filters( 'immo_manager_energy_certificate_required', $required, $property_type );
	}

	/**
	 * Meta-Felder bei WP registrieren (für REST API & Type-Safety).
	 *
	 * @return void
	 */
	public function register_meta(): void {
		foreach ( self::property_fields() as $key => $def ) {
			$this->register_single( PostTypes::POST_TYPE_PROPERTY, $key, $def );
		}
		foreach ( self::project_fields() as $key => $def ) {
			$this->register_single( PostTypes::POST_TYPE_PROJECT, $key, $def );
		}
	}

	/**
	 * Einzelnes Meta-Feld registrieren.
	 *
	 * @param string               $post_type Post-Type-Slug.
	 * @param string               $key       Meta-Key.
	 * @param array<string, mixed> $def       Definition.
	 *
	 * @return void
	 */
	private function register_single( string $post_type, string $key, array $def ): void {
		register_post_meta(
			$post_type,
			$key,
			array(
				'type'              => (string) ( $def['type'] ?? 'string' ),
				'single'            => (bool) ( $def['single'] ?? true ),
				'default'           => $def['default'] ?? null,
				'show_in_rest'      => (bool) ( $def['show_in_rest'] ?? ( 'array' !== ( $def['type'] ?? '' ) ) ),
				'sanitize_callback' => function ( $value ) use ( $key, $def ) {
					return self::sanitize_value( $value, $key, $def );
				},
				'auth_callback'     => static function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Einen einzelnen Meta-Wert sanitizen (auch extern verwendbar).
	 *
	 * @param mixed                $value Rohwert.
	 * @param string               $key   Meta-Key.
	 * @param array<string, mixed> $def   Definition.
	 *
	 * @return mixed
	 */
	public static function sanitize_value( $value, string $key, array $def ) {
		$type = (string) ( $def['type'] ?? 'string' );

		switch ( $type ) {
			case 'integer':
				return (int) $value;

			case 'number':
				return (float) $value;

			case 'array':
				// Spezialfall _immo_features: nur gültige Feature-Keys.
				if ( '_immo_features' === $key ) {
					return Features::filter_valid( is_array( $value ) ? $value : array() );
				}
				return is_array( $value ) ? array_values( $value ) : array();

			case 'boolean':
				return ! empty( $value ) ? '1' : '0';

			case 'string':
			default:
				$str = is_scalar( $value ) ? (string) $value : '';

				// Enum-Check.
				if ( isset( $def['enum'] ) && is_array( $def['enum'] ) ) {
					$str = in_array( $str, $def['enum'], true ) ? $str : (string) ( $def['default'] ?? '' );
				}

				// Key-basierte Spezialbehandlung.
				if ( '_immo_contact_email' === $key ) {
					$email = sanitize_email( $str );
					return $email ?: '';
				}
				if ( '_immo_region_state' === $key ) {
					return $str && Regions::is_valid_state( $str ) ? $str : '';
				}
				if ( '_immo_available_from' === $key || '_immo_project_start_date' === $key || '_immo_project_completion' === $key ) {
					$ts = $str ? strtotime( $str ) : false;
					return $ts ? gmdate( 'Y-m-d', $ts ) : '';
				}

				return sanitize_text_field( $str );
		}
	}

	/**
	 * Komplette Meta-Payload einer Immobilie sanitizen.
	 *
	 * @param array<string, mixed> $raw Rohdaten aus $_POST o. ä.
	 *
	 * @return array<string, mixed> Key => sanitizer Wert.
	 */
	public static function sanitize_property_payload( array $raw ): array {
		$out = array();
		foreach ( self::property_fields() as $key => $def ) {
			if ( array_key_exists( $key, $raw ) ) {
				$out[ $key ] = self::sanitize_value( $raw[ $key ], $key, $def );
			}
		}
		return $out;
	}

	/**
	 * Komplette Meta-Payload eines Bauprojekts sanitizen.
	 *
	 * @param array<string, mixed> $raw Rohdaten.
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize_project_payload( array $raw ): array {
		$out = array();
		foreach ( self::project_fields() as $key => $def ) {
			if ( array_key_exists( $key, $raw ) ) {
				$out[ $key ] = self::sanitize_value( $raw[ $key ], $key, $def );
			}
		}
		return $out;
	}

	/**
	 * Vorgegebene Heizungs-Optionen für das Heizungs-Dropdown.
	 *
	 * Key = gespeicherter Wert (Freitext-kompatibel, da `_immo_heating` weiterhin
	 * `string` ohne Enum bleibt), Value = lokalisiertes Label.
	 * Bestandswerte, die nicht im Set enthalten sind, bleiben gespeichert und
	 * werden im UI als „Sonstige" mit Freitext-Fallback angezeigt.
	 *
	 * @return array<string, string>
	 */
	public static function heating_options(): array {
		return array(
			'Fernwärme'      => __( 'Fernwärme', 'immo-manager' ),
			'Gasheizung'     => __( 'Gasheizung', 'immo-manager' ),
			'Ölheizung'      => __( 'Ölheizung', 'immo-manager' ),
			'Wärmepumpe'     => __( 'Wärmepumpe', 'immo-manager' ),
			'Pellets/Holz'   => __( 'Pellets/Holz', 'immo-manager' ),
			'Elektroheizung' => __( 'Elektroheizung', 'immo-manager' ),
			'Solar'          => __( 'Solar', 'immo-manager' ),
			'Kamin/Ofen'     => __( 'Kamin/Ofen', 'immo-manager' ),
		);
	}
}
