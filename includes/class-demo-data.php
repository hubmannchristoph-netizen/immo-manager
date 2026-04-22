<?php
/**
 * Demo-Daten Import für Immo Manager.
 *
 * Erstellt 4 Muster-Immobilien (Wien, Graz, Salzburg, Innsbruck)
 * und 2 Bauprojekte mit Wohneinheiten.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class DemoData
 */
class DemoData {

	/**
	 * Konstruktor – Admin-Hooks registrieren.
	 */
	public function __construct() {
		add_action( 'wp_ajax_immo_import_demo', array( $this, 'ajax_import' ) );
		add_action( 'wp_ajax_immo_remove_demo', array( $this, 'ajax_remove' ) );
	}

	/**
	 * AJAX-Handler: Demo-Daten importieren.
	 *
	 * @return void
	 */
	public function ajax_import(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'immo_demo_import' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
		}

		$result = $this->import();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: Anzahl erstellter Einträge */
				__( 'Demo-Daten importiert: %d Immobilien, %d Projekte, %d Wohneinheiten.', 'immo-manager' ),
				$result['properties'],
				$result['projects'],
				$result['units']
			),
		) );
	}

	/**
	 * AJAX-Handler: Demo-Daten entfernen.
	 *
	 * @return void
	 */
	public function ajax_remove(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'immo_demo_import' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
		}

		$count = $this->remove();
		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: Anzahl gelöschter Einträge */
				__( '%d Demo-Einträge wurden entfernt.', 'immo-manager' ),
				$count
			),
		) );
	}

	/**
	 * Demo-Daten importieren.
	 *
	 * @return array<string, int>|\WP_Error
	 */
	public function import() {
		$counts = array( 'properties' => 0, 'projects' => 0, 'units' => 0 );

		// Demo-Kategorien anlegen.
		$this->ensure_terms();

		// 4 Muster-Immobilien.
		$properties = $this->get_property_data();
		foreach ( $properties as $data ) {
			$id = $this->create_property( $data );
			if ( $id && ! is_wp_error( $id ) ) {
				$counts['properties']++;
			}
		}

		// 2 Muster-Projekte mit Wohneinheiten.
		$projects = $this->get_project_data();
		foreach ( $projects as $data ) {
			$result = $this->create_project( $data );
			if ( $result ) {
				$counts['projects']++;
				$counts['units'] += $result;
			}
		}

		return $counts;
	}

	/**
	 * Demo-Daten entfernen (alle Posts mit _immo_is_demo = 1).
	 *
	 * @return int Anzahl gelöschter Posts.
	 */
	public function remove(): int {
		$count = 0;
		$types = array( PostTypes::POST_TYPE_PROPERTY, PostTypes::POST_TYPE_PROJECT );

		foreach ( $types as $type ) {
			$posts = get_posts( array(
				'post_type'      => $type,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_key'       => '_immo_is_demo',
				'meta_value'     => '1',
				'fields'         => 'ids',
			) );

			foreach ( $posts as $post_id ) {
				// Units löschen.
				Units::delete_by_project( (int) $post_id );
				wp_delete_post( (int) $post_id, true );
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Prüft ob Demo-Daten vorhanden sind.
	 *
	 * @return bool
	 */
	public static function has_demo_data(): bool {
		$posts = get_posts( array(
			'post_type'      => array( PostTypes::POST_TYPE_PROPERTY, PostTypes::POST_TYPE_PROJECT ),
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'meta_key'       => '_immo_is_demo',
			'meta_value'     => '1',
			'fields'         => 'ids',
		) );
		return ! empty( $posts );
	}

	/**
	 * Standard-Taxonomie-Terms anlegen.
	 *
	 * @return void
	 */
	private function ensure_terms(): void {
		$categories = array( 'Wohnung', 'Einfamilienhaus', 'Mehrfamilienhaus', 'Grundstück', 'Gewerbe' );
		foreach ( $categories as $cat ) {
			if ( ! term_exists( $cat, PostTypes::TAX_CATEGORY ) ) {
				wp_insert_term( $cat, PostTypes::TAX_CATEGORY );
			}
		}

		$regions = array( 'Wien', 'Steiermark', 'Salzburg', 'Tirol' );
		foreach ( $regions as $region ) {
			if ( ! term_exists( $region, PostTypes::TAX_LOCATION ) ) {
				wp_insert_term( $region, PostTypes::TAX_LOCATION );
			}
		}
	}

	/**
	 * Muster-Immobilien-Daten.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_property_data(): array {
		return array(
			// 1. Wien – Wohnung, Verkauf.
			array(
				'title'       => 'Elegante 3-Zimmer-Wohnung nahe Ringstraße',
				'description' => '<p>Wunderschöne Altbauwohnung im Herzen Wiens mit hohen Decken, Stuckaturen und modernem Innenausbau. Die Wohnung befindet sich im 4. Obergeschoss eines gepflegten Gründerzeithauses direkt am Gürtel.</p><p>Perfekte Lage mit optimaler öffentlicher Anbindung: U-Bahn, Straßenbahn und zahlreiche Busse in unmittelbarer Nähe. Einkaufsmöglichkeiten, Restaurants und Kaffeehäuser fußläufig erreichbar.</p>',
				'_immo_mode'              => 'sale',
				'_immo_status'            => 'available',
				'_immo_property_type'     => 'Wohnung',
				'_immo_address'           => 'Mariahilfer Straße 42',
				'_immo_postal_code'       => '1070',
				'_immo_city'              => 'Wien',
				'_immo_region_state'      => 'wien',
				'_immo_region_district'   => '1070',
				'_immo_country'           => 'AT',
				'_immo_lat'               => 48.1962,
				'_immo_lng'               => 16.3497,
				'_immo_area'              => 92.5,
				'_immo_usable_area'       => 87.0,
				'_immo_rooms'             => 3,
				'_immo_bedrooms'          => 2,
				'_immo_bathrooms'         => 1,
				'_immo_floor'             => 4,
				'_immo_total_floors'      => 6,
				'_immo_built_year'        => 1904,
				'_immo_renovation_year'   => 2019,
				'_immo_energy_class'      => 'C',
				'_immo_energy_hwb'        => 68.0,
				'_immo_heating'           => 'Fernwärme',
				'_immo_price'             => 485000,
				'_immo_commission'        => '3 % + 20 % USt.',
				'_immo_contact_name'      => 'Mag. Anna Müller',
				'_immo_contact_email'     => 'anna.mueller@immo-demo.at',
				'_immo_contact_phone'     => '+43 1 234 56 78',
				'_immo_features'          => array( 'elevator', 'fitted_kitchen', 'floor_heating', 'balcony', 'public_transport', 'fiber_internet' ),
				'_immo_is_demo'           => '1',
			),
			// 2. Graz – Einfamilienhaus, Verkauf.
			array(
				'title'       => 'Modernes Einfamilienhaus mit großem Garten',
				'description' => '<p>Neuwertige Architekten-Villa am Stadtrand von Graz mit offenem Wohnkonzept, Einbauküche und großzügigem Garten. Das Haus wurde 2021 nach aktuellstem Energiestandard gebaut und verfügt über eine Luft-Wärmepumpe und Photovoltaik-Anlage.</p><p>Der Garten mit Terrasse und Swimmingpool lädt zu Entspannung und geselligen Abenden ein. Garage für 2 Fahrzeuge sowie ausreichend Parkplätze auf dem Grundstück.</p>',
				'_immo_mode'              => 'sale',
				'_immo_status'            => 'available',
				'_immo_property_type'     => 'Einfamilienhaus',
				'_immo_address'           => 'Steinbergstraße 18',
				'_immo_postal_code'       => '8042',
				'_immo_city'              => 'Graz',
				'_immo_region_state'      => 'steiermark',
				'_immo_region_district'   => 'graz_umgebung',
				'_immo_country'           => 'AT',
				'_immo_lat'               => 47.0207,
				'_immo_lng'               => 15.4395,
				'_immo_area'              => 185.0,
				'_immo_usable_area'       => 168.0,
				'_immo_land_area'         => 640.0,
				'_immo_rooms'             => 5,
				'_immo_bedrooms'          => 4,
				'_immo_bathrooms'         => 2,
				'_immo_floor'             => 0,
				'_immo_total_floors'      => 2,
				'_immo_built_year'        => 2021,
				'_immo_energy_class'      => 'A',
				'_immo_energy_hwb'        => 22.5,
				'_immo_heating'           => 'Wärmepumpe (Luft/Wasser)',
				'_immo_price'             => 895000,
				'_immo_commission'        => '3 % + 20 % USt.',
				'_immo_contact_name'      => 'DI Thomas Gruber',
				'_immo_contact_email'     => 'thomas.gruber@immo-demo.at',
				'_immo_contact_phone'     => '+43 316 123 456',
				'_immo_features'          => array( 'pool', 'garden', 'garage', 'solar_panels', 'heat_pump', 'floor_heating', 'fireplace', 'terrace', 'quiet_location', 'air_conditioning' ),
				'_immo_is_demo'           => '1',
			),
			// 3. Salzburg – Wohnung, Miete.
			array(
				'title'       => 'Helle 2-Zimmer-Wohnung mit Bergblick',
				'description' => '<p>Charmante 2-Zimmer-Wohnung in zentraler Salzburger Lage mit atemberaubendem Blick auf den Untersberg. Große Fensterfront lässt viel Licht in die modern eingerichteten Räumlichkeiten.</p><p>Vollständig möbliert und sofort beziehbar. Perfekt für Singles oder Pärchen. Nahe dem historischen Zentrum und der Festung Hohensalzburg.</p>',
				'_immo_mode'              => 'rent',
				'_immo_status'            => 'available',
				'_immo_property_type'     => 'Wohnung',
				'_immo_address'           => 'Kaigasse 12',
				'_immo_postal_code'       => '5020',
				'_immo_city'              => 'Salzburg',
				'_immo_region_state'      => 'salzburg',
				'_immo_region_district'   => 'salzburg_stadt',
				'_immo_country'           => 'AT',
				'_immo_lat'               => 47.7973,
				'_immo_lng'               => 13.0444,
				'_immo_area'              => 58.0,
				'_immo_usable_area'       => 54.0,
				'_immo_rooms'             => 2,
				'_immo_bedrooms'          => 1,
				'_immo_bathrooms'         => 1,
				'_immo_floor'             => 3,
				'_immo_total_floors'      => 5,
				'_immo_built_year'        => 1965,
				'_immo_renovation_year'   => 2022,
				'_immo_energy_class'      => 'D',
				'_immo_energy_hwb'        => 95.0,
				'_immo_heating'           => 'Gasheizung',
				'_immo_rent'              => 1150,
				'_immo_operating_costs'   => 220,
				'_immo_deposit'           => 3450,
				'_immo_available_from'    => date( 'Y-m-d', strtotime( '+30 days' ) ),
				'_immo_contact_name'      => 'Maria Schneider',
				'_immo_contact_email'     => 'maria.schneider@immo-demo.at',
				'_immo_contact_phone'     => '+43 662 987 654',
				'_immo_features'          => array( 'balcony', 'panoramic_view', 'public_transport', 'near_schools', 'fitted_kitchen', 'shower' ),
				'_immo_is_demo'           => '1',
			),
			// 4. Innsbruck – Einfamilienhaus, Verkauf + Miete.
			array(
				'title'       => 'Alpines Landhaus mit Traumblick auf die Nordkette',
				'description' => '<p>Außergewöhnliches Landhaus in ruhiger Hanglage mit spektakulärem Panoramablick auf die Innsbrucker Nordkette. Das Haus verbindet traditionelle Tiroler Architektur mit modernem Komfort.</p><p>Große Holzterrasse, Sauna, Kamin und ein liebevoll angelegter Garten. Ideal als Hauptwohnsitz oder hochwertige Ferienimmobilie. Ski-Gebiete in 20 Minuten erreichbar.</p>',
				'_immo_mode'              => 'both',
				'_immo_status'            => 'available',
				'_immo_property_type'     => 'Einfamilienhaus',
				'_immo_address'           => 'Hungerburgweg 7',
				'_immo_postal_code'       => '6020',
				'_immo_city'              => 'Innsbruck',
				'_immo_region_state'      => 'tirol',
				'_immo_region_district'   => 'innsbruck_stadt',
				'_immo_country'           => 'AT',
				'_immo_lat'               => 47.2902,
				'_immo_lng'               => 11.4046,
				'_immo_area'              => 220.0,
				'_immo_usable_area'       => 195.0,
				'_immo_land_area'         => 850.0,
				'_immo_rooms'             => 6,
				'_immo_bedrooms'          => 4,
				'_immo_bathrooms'         => 3,
				'_immo_floor'             => 0,
				'_immo_total_floors'      => 2,
				'_immo_built_year'        => 1998,
				'_immo_renovation_year'   => 2020,
				'_immo_energy_class'      => 'B',
				'_immo_energy_hwb'        => 38.0,
				'_immo_heating'           => 'Ölheizung + Kamin',
				'_immo_price'             => 1290000,
				'_immo_rent'              => 4500,
				'_immo_commission'        => '3 % + 20 % USt.',
				'_immo_contact_name'      => 'Hannes Berger',
				'_immo_contact_email'     => 'hannes.berger@immo-demo.at',
				'_immo_contact_phone'     => '+43 512 345 678',
				'_immo_features'          => array( 'garden', 'terrace', 'fireplace', 'wellness_area', 'panoramic_view', 'quiet_location', 'carport', 'solar_panels', 'water_access', 'near_healthcare' ),
				'_immo_is_demo'           => '1',
			),
		);
	}

	/**
	 * Muster-Projekt-Daten.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_project_data(): array {
		return array(
			// Projekt 1: Wien Donaustadt.
			array(
				'title'       => 'Wohnpark Donaustadt – Moderne Eigentumswohnungen',
				'description' => '<p>Exklusives Neubauprojekt im aufstrebenden Stadtteil Wien-Donaustadt. 24 hochwertige Eigentumswohnungen in zwei Gebäudeteilen mit Tiefgarage, Gemeinschaftsterasse und direktem U-Bahn-Anschluss.</p><p>Alle Wohneinheiten verfügen über Balkon oder Terrasse, Fußbodenheizung, mechanische Lüftung mit Wärmerückgewinnung und Smart-Home-System. Übergabe geplant für Q4 2026.</p>',
				'_immo_project_status'      => 'building',
				'_immo_project_start_date'  => '2024-06-01',
				'_immo_project_completion'  => '2026-12-31',
				'_immo_address'             => 'Erzherzog-Karl-Straße 125',
				'_immo_postal_code'         => '1220',
				'_immo_city'                => 'Wien',
				'_immo_region_state'        => 'wien',
				'_immo_region_district'     => '1220',
				'_immo_lat'                 => 48.2372,
				'_immo_lng'                 => 16.4472,
				'_immo_features'            => array( 'elevator', 'community_gym', 'community_lounge', 'playground', 'floor_heating', 'public_transport', 'bicycle_storage', 'fiber_internet' ),
				'_immo_contact_name'        => 'Projekt-Vertrieb GmbH',
				'_immo_contact_email'       => 'vertrieb@donaustadt-projekt.at',
				'_immo_contact_phone'       => '+43 1 555 66 77',
				'_immo_is_demo'             => '1',
				// Wohneinheiten.
				'units'                     => array(
					array( 'unit_number' => 'B1.01', 'floor' => 1, 'area' => 48.5, 'usable_area' => 45.0, 'rooms' => 2, 'bedrooms' => 1, 'bathrooms' => 1, 'price' => 285000, 'status' => 'sold' ),
					array( 'unit_number' => 'B1.02', 'floor' => 1, 'area' => 72.0, 'usable_area' => 68.0, 'rooms' => 3, 'bedrooms' => 2, 'bathrooms' => 1, 'price' => 395000, 'status' => 'reserved' ),
					array( 'unit_number' => 'B1.03', 'floor' => 1, 'area' => 58.0, 'usable_area' => 54.5, 'rooms' => 2, 'bedrooms' => 1, 'bathrooms' => 1, 'price' => 315000, 'status' => 'available' ),
					array( 'unit_number' => 'B1.04', 'floor' => 2, 'area' => 92.0, 'usable_area' => 87.0, 'rooms' => 4, 'bedrooms' => 3, 'bathrooms' => 2, 'price' => 520000, 'status' => 'available' ),
					array( 'unit_number' => 'B1.05', 'floor' => 2, 'area' => 65.0, 'usable_area' => 61.0, 'rooms' => 3, 'bedrooms' => 2, 'bathrooms' => 1, 'price' => 358000, 'status' => 'available' ),
					array( 'unit_number' => 'B1.06', 'floor' => 3, 'area' => 48.5, 'usable_area' => 45.0, 'rooms' => 2, 'bedrooms' => 1, 'bathrooms' => 1, 'price' => 292000, 'status' => 'available' ),
					array( 'unit_number' => 'B2.01', 'floor' => 1, 'area' => 110.0, 'usable_area' => 102.0, 'rooms' => 4, 'bedrooms' => 3, 'bathrooms' => 2, 'price' => 610000, 'status' => 'sold' ),
					array( 'unit_number' => 'B2.02', 'floor' => 2, 'area' => 78.0, 'usable_area' => 74.0, 'rooms' => 3, 'bedrooms' => 2, 'bathrooms' => 1, 'price' => 435000, 'status' => 'available' ),
					array( 'unit_number' => 'B2.03', 'floor' => 3, 'area' => 145.0, 'usable_area' => 135.0, 'rooms' => 5, 'bedrooms' => 4, 'bathrooms' => 2, 'price' => 820000, 'status' => 'available' ),
				),
			),
			// Projekt 2: Graz.
			array(
				'title'       => 'Grünes Quartier Graz-Süd',
				'description' => '<p>Innovatives Wohnprojekt im Herzen von Graz-Süd mit nachhaltiger Bauweise und gemeinschaftlichem Konzept. 12 Wohneinheiten in einem autofreien Quartier mit Gemeinschaftsgarten, Urban Gardening und Carsharing.</p><p>Zertifizierung nach ÖGNB Platin geplant. Alle Wohnungen barrierefrei zugänglich. Übergabe ab Q2 2025.</p>',
				'_immo_project_status'      => 'completed',
				'_immo_project_start_date'  => '2023-01-15',
				'_immo_project_completion'  => '2025-06-30',
				'_immo_address'             => 'Münzgrabenstraße 88',
				'_immo_postal_code'         => '8010',
				'_immo_city'                => 'Graz',
				'_immo_region_state'        => 'steiermark',
				'_immo_region_district'     => 'graz_stadt',
				'_immo_lat'                 => 47.0507,
				'_immo_lng'                 => 15.4495,
				'_immo_features'            => array( 'wheelchair_accessible', 'elevator', 'community_lounge', 'playground', 'solar_panels', 'heat_pump', 'ventilation_recovery', 'bicycle_storage', 'rainwater_system' ),
				'_immo_contact_name'        => 'Grünes Wohnen GmbH',
				'_immo_contact_email'       => 'office@gruenes-quartier.at',
				'_immo_contact_phone'       => '+43 316 888 99 00',
				'_immo_is_demo'             => '1',
				'units'                     => array(
					array( 'unit_number' => '1A', 'floor' => 0, 'area' => 55.0, 'usable_area' => 51.0, 'rooms' => 2, 'bedrooms' => 1, 'bathrooms' => 1, 'price' => 278000, 'status' => 'sold' ),
					array( 'unit_number' => '1B', 'floor' => 0, 'area' => 78.0, 'usable_area' => 72.0, 'rooms' => 3, 'bedrooms' => 2, 'bathrooms' => 1, 'price' => 362000, 'status' => 'sold' ),
					array( 'unit_number' => '2A', 'floor' => 1, 'area' => 55.0, 'usable_area' => 51.0, 'rooms' => 2, 'bedrooms' => 1, 'bathrooms' => 1, 'price' => 285000, 'status' => 'rented' ),
					array( 'unit_number' => '2B', 'floor' => 1, 'area' => 95.0, 'usable_area' => 88.0, 'rooms' => 4, 'bedrooms' => 3, 'bathrooms' => 2, 'price' => 465000, 'status' => 'sold' ),
					array( 'unit_number' => '3A', 'floor' => 2, 'area' => 120.0, 'usable_area' => 110.0, 'rooms' => 4, 'bedrooms' => 3, 'bathrooms' => 2, 'price' => 590000, 'status' => 'available' ),
					array( 'unit_number' => '3B', 'floor' => 2, 'area' => 68.0, 'usable_area' => 63.0, 'rooms' => 3, 'bedrooms' => 2, 'bathrooms' => 1, 'price' => 338000, 'status' => 'reserved' ),
				),
			),
		);
	}

	/**
	 * Einzelne Immobilie anlegen.
	 *
	 * @param array<string, mixed> $data Property-Daten.
	 *
	 * @return int|\WP_Error Post-ID oder Fehler.
	 */
	private function create_property( array $data ) {
		$title       = $data['title'] ?? __( 'Demo-Immobilie', 'immo-manager' );
		$description = $data['description'] ?? '';

		$post_id = wp_insert_post( array(
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => wp_kses_post( $description ),
			'post_status'  => 'publish',
			'post_type'    => PostTypes::POST_TYPE_PROPERTY,
			'post_author'  => get_current_user_id() ?: 1,
		), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Meta-Felder setzen.
		$meta_fields = MetaFields::property_fields();
		foreach ( $meta_fields as $key => $def ) {
			if ( array_key_exists( $key, $data ) ) {
				update_post_meta( $post_id, $key, $data[ $key ] );
			}
		}

		// Demo-Flag.
		update_post_meta( $post_id, '_immo_is_demo', '1' );

		// Placeholder-Bild per Unsplash URL (extern, nur Demo).
		$this->attach_placeholder_image( $post_id, $title );

		return $post_id;
	}

	/**
	 * Projekt mit Wohneinheiten anlegen.
	 *
	 * @param array<string, mixed> $data Projekt-Daten inkl. units[].
	 *
	 * @return int Anzahl erstellter Wohneinheiten.
	 */
	private function create_project( array $data ): int {
		$units_data = $data['units'] ?? array();
		unset( $data['units'] );

		$post_id = wp_insert_post( array(
			'post_title'   => sanitize_text_field( $data['title'] ?? __( 'Demo-Projekt', 'immo-manager' ) ),
			'post_content' => wp_kses_post( $data['description'] ?? '' ),
			'post_status'  => 'publish',
			'post_type'    => PostTypes::POST_TYPE_PROJECT,
			'post_author'  => get_current_user_id() ?: 1,
		), true );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		// Projekt-Meta setzen.
		$project_fields = MetaFields::project_fields();
		foreach ( $project_fields as $key => $def ) {
			if ( array_key_exists( $key, $data ) ) {
				update_post_meta( $post_id, $key, $data[ $key ] );
			}
		}
		update_post_meta( $post_id, '_immo_is_demo', '1' );

		// Placeholder-Bild.
		$this->attach_placeholder_image( $post_id, $data['title'] ?? 'Projekt' );

		// Wohneinheiten erstellen.
		$unit_count = 0;
		foreach ( $units_data as $unit ) {
			$unit['project_id'] = $post_id;
			$id = Units::create( $unit );
			if ( $id ) {
				$unit_count++;
			}
		}

		return $unit_count;
	}

	/**
	 * Placeholder-Bild via externem URL anhängen.
	 * Nutzt picsum.photos als kostenloser Platzhalter-Dienst.
	 *
	 * @param int    $post_id Post-ID.
	 * @param string $title   Bildbeschreibung.
	 *
	 * @return void
	 */
	private function attach_placeholder_image( int $post_id, string $title ): void {
		// Seedbasiertes Bild damit jede Immobilie ein anderes Bild hat.
		$seed     = abs( crc32( $title ) ) % 1000;
		$img_url  = "https://picsum.photos/seed/{$seed}/800/600";

		$tmp_file = download_url( $img_url, 30 );
		if ( is_wp_error( $tmp_file ) ) {
			return;
		}

		$file_array = array(
			'name'     => "immo-demo-{$seed}.jpg",
			'tmp_name' => $tmp_file,
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id, $title );
		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}

		// Temp-Datei aufräumen falls media_handle_sideload das nicht getan hat.
		if ( file_exists( $tmp_file ) ) {
			@unlink( $tmp_file );
		}
	}
}
