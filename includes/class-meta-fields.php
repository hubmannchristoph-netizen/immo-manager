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
			'_immo_energy_class'      => array( 'type' => 'string',  'enum' => array( '', 'A++', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G' ), 'default' => '' ),
			'_immo_energy_hwb'        => array( 'type' => 'number',  'default' => 0 ),
			'_immo_energy_fgee'       => array( 'type' => 'number',  'default' => 0 ),
			'_immo_heating'           => array( 'type' => 'string',  'default' => '' ),

			// Preis.
			'_immo_price'             => array( 'type' => 'number',  'default' => 0 ),
			'_immo_rent'              => array( 'type' => 'number',  'default' => 0 ),
			'_immo_operating_costs'   => array( 'type' => 'number',  'default' => 0 ),
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
		);
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
}
