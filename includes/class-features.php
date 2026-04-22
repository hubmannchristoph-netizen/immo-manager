<?php
/**
 * Ausstattungs-Features (zentrale Definition).
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Features
 *
 * Alle verfügbaren Ausstattungs-Merkmale einer Immobilie, gruppiert nach
 * Kategorien. Die Keys werden in Post-Meta als Array gespeichert.
 */
class Features {

	/**
	 * Alle Kategorien.
	 *
	 * @return array<string, string> Key => Label.
	 */
	public static function get_categories(): array {
		return array(
			'outdoor'       => __( 'Außenbereiche', 'immo-manager' ),
			'indoor'        => __( 'Innenausstattung', 'immo-manager' ),
			'security'      => __( 'Sicherheit & Komfort', 'immo-manager' ),
			'accessibility' => __( 'Barrierefreiheit', 'immo-manager' ),
			'energy'        => __( 'Energieeffizienz', 'immo-manager' ),
			'community'     => __( 'Gemeinschaftsbereiche', 'immo-manager' ),
			'location'      => __( 'Lage & Infrastruktur', 'immo-manager' ),
		);
	}

	/**
	 * Alle Features als flaches Array.
	 *
	 * @return array<string, array{label: string, icon: string, category: string}>
	 */
	public static function get_all(): array {
		return array(
			// Außenbereiche.
			'pool'                  => array( 'label' => __( 'Swimmingpool', 'immo-manager' ),               'icon' => '🏊', 'category' => 'outdoor' ),
			'garden'                => array( 'label' => __( 'Garten / Gartenanteil', 'immo-manager' ),      'icon' => '🌳', 'category' => 'outdoor' ),
			'garage'                => array( 'label' => __( 'Garage', 'immo-manager' ),                     'icon' => '🚗', 'category' => 'outdoor' ),
			'carport'               => array( 'label' => __( 'Carport / Stellplatz', 'immo-manager' ),       'icon' => '🅿️',  'category' => 'outdoor' ),
			'bicycle_storage'       => array( 'label' => __( 'Fahrradkeller', 'immo-manager' ),              'icon' => '🚲', 'category' => 'outdoor' ),
			'outbuilding'           => array( 'label' => __( 'Nebengebäude / Schuppen', 'immo-manager' ),    'icon' => '🏚️',  'category' => 'outdoor' ),

			// Innenausstattung.
			'fitted_kitchen'        => array( 'label' => __( 'Einbauküche', 'immo-manager' ),                'icon' => '🍽️',  'category' => 'indoor' ),
			'fireplace'             => array( 'label' => __( 'Kamin / Ofen', 'immo-manager' ),               'icon' => '🔥', 'category' => 'indoor' ),
			'balcony'               => array( 'label' => __( 'Balkon', 'immo-manager' ),                     'icon' => '🌞', 'category' => 'indoor' ),
			'winter_garden'         => array( 'label' => __( 'Wintergarten', 'immo-manager' ),               'icon' => '🪟', 'category' => 'indoor' ),
			'terrace'               => array( 'label' => __( 'Terrasse', 'immo-manager' ),                   'icon' => '🚪', 'category' => 'indoor' ),
			'bathtub'               => array( 'label' => __( 'Badewanne', 'immo-manager' ),                  'icon' => '🛁', 'category' => 'indoor' ),
			'shower'                => array( 'label' => __( 'Dusche', 'immo-manager' ),                     'icon' => '🚿', 'category' => 'indoor' ),
			'washing_hookup'        => array( 'label' => __( 'Waschmaschinenanschluss', 'immo-manager' ),    'icon' => '🧺', 'category' => 'indoor' ),
			'air_conditioning'      => array( 'label' => __( 'Klimaanlage', 'immo-manager' ),                'icon' => '❄️',  'category' => 'indoor' ),
			'floor_heating'         => array( 'label' => __( 'Fußbodenheizung', 'immo-manager' ),            'icon' => '🌡️',  'category' => 'indoor' ),
			'cellar'                => array( 'label' => __( 'Keller / Kellerabteil', 'immo-manager' ),      'icon' => '📦', 'category' => 'indoor' ),

			// Sicherheit.
			'alarm_system'          => array( 'label' => __( 'Alarmanlage', 'immo-manager' ),                'icon' => '🔒', 'category' => 'security' ),
			'video_surveillance'    => array( 'label' => __( 'Videoüberwachung', 'immo-manager' ),           'icon' => '📹', 'category' => 'security' ),
			'electronic_lock'       => array( 'label' => __( 'Elektronisches Schloss', 'immo-manager' ),     'icon' => '🔐', 'category' => 'security' ),

			// Barrierefreiheit.
			'wheelchair_accessible' => array( 'label' => __( 'Barrierefrei', 'immo-manager' ),               'icon' => '♿', 'category' => 'accessibility' ),
			'elevator'              => array( 'label' => __( 'Aufzug', 'immo-manager' ),                     'icon' => '🛗', 'category' => 'accessibility' ),
			'wide_doors'            => array( 'label' => __( 'Breite Türen / Rampen', 'immo-manager' ),      'icon' => '🚪', 'category' => 'accessibility' ),

			// Energie.
			'solar_panels'          => array( 'label' => __( 'Photovoltaik / Solar', 'immo-manager' ),       'icon' => '🔋', 'category' => 'energy' ),
			'heat_pump'             => array( 'label' => __( 'Wärmepumpe', 'immo-manager' ),                 'icon' => '♻️',  'category' => 'energy' ),
			'rainwater_system'      => array( 'label' => __( 'Regenwassersystem', 'immo-manager' ),          'icon' => '💧', 'category' => 'energy' ),
			'ventilation_recovery'  => array( 'label' => __( 'Lüftung mit Wärmerückgewinnung', 'immo-manager' ), 'icon' => '🌬️',  'category' => 'energy' ),

			// Gemeinschaft (Projekte).
			'community_gym'         => array( 'label' => __( 'Fitnessstudio', 'immo-manager' ),              'icon' => '🏋️',  'category' => 'community' ),
			'community_lounge'      => array( 'label' => __( 'Gemeinschaftsraum', 'immo-manager' ),          'icon' => '📚', 'category' => 'community' ),
			'playground'            => array( 'label' => __( 'Spielplatz', 'immo-manager' ),                 'icon' => '👧', 'category' => 'community' ),
			'wellness_area'         => array( 'label' => __( 'Wellness / Sauna', 'immo-manager' ),           'icon' => '🧘', 'category' => 'community' ),
			'concierge'             => array( 'label' => __( 'Concierge-Service', 'immo-manager' ),          'icon' => '📍', 'category' => 'community' ),

			// Lage & Infrastruktur.
			'panoramic_view'        => array( 'label' => __( 'Panorama-/Fernblick', 'immo-manager' ),        'icon' => '🏞️',  'category' => 'location' ),
			'quiet_location'        => array( 'label' => __( 'Ruhelage', 'immo-manager' ),                   'icon' => '🔊', 'category' => 'location' ),
			'fiber_internet'        => array( 'label' => __( 'Glasfaser-Internet', 'immo-manager' ),         'icon' => '📡', 'category' => 'location' ),
			'public_transport'      => array( 'label' => __( 'ÖV-Anbindung', 'immo-manager' ),               'icon' => '🚂', 'category' => 'location' ),
			'near_schools'          => array( 'label' => __( 'Schulen in der Nähe', 'immo-manager' ),        'icon' => '🏫', 'category' => 'location' ),
			'near_healthcare'       => array( 'label' => __( 'Ärzte/Spitäler in der Nähe', 'immo-manager' ), 'icon' => '🏥', 'category' => 'location' ),
			'water_access'          => array( 'label' => __( 'Wasserzugang / Seelage', 'immo-manager' ),     'icon' => '🌊', 'category' => 'location' ),
		);
	}

	/**
	 * Features gruppiert nach Kategorie.
	 *
	 * @return array<string, array<string, array{label: string, icon: string, category: string}>>
	 */
	public static function get_grouped(): array {
		$grouped = array();
		foreach ( self::get_categories() as $cat_key => $cat_label ) {
			$grouped[ $cat_key ] = array();
		}
		foreach ( self::get_all() as $key => $feature ) {
			$cat = $feature['category'];
			if ( isset( $grouped[ $cat ] ) ) {
				$grouped[ $cat ][ $key ] = $feature;
			}
		}
		return $grouped;
	}

	/**
	 * Prüft, ob ein Feature-Key gültig ist.
	 *
	 * @param string $key Key.
	 *
	 * @return bool
	 */
	public static function is_valid( string $key ): bool {
		return array_key_exists( $key, self::get_all() );
	}

	/**
	 * Filtert ein Array von Feature-Keys auf gültige.
	 *
	 * @param array<int, mixed> $keys Rohdaten.
	 *
	 * @return array<int, string> Nur gültige Keys.
	 */
	public static function filter_valid( array $keys ): array {
		$valid = array_keys( self::get_all() );
		$out   = array();
		foreach ( $keys as $k ) {
			$k = is_string( $k ) ? sanitize_key( $k ) : '';
			if ( $k && in_array( $k, $valid, true ) && ! in_array( $k, $out, true ) ) {
				$out[] = $k;
			}
		}
		return $out;
	}

	/**
	 * Label eines Features abrufen.
	 *
	 * @param string $key Feature-Key.
	 *
	 * @return string
	 */
	public static function get_label( string $key ): string {
		$all = self::get_all();
		return $all[ $key ]['label'] ?? '';
	}

	/**
	 * Icon eines Features abrufen.
	 *
	 * @param string $key Feature-Key.
	 *
	 * @return string
	 */
	public static function get_icon( string $key ): string {
		$all = self::get_all();
		return $all[ $key ]['icon'] ?? '';
	}
}
