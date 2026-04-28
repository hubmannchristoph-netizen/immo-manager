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
			$post_id = (int) get_queried_object_id();
			$data    = $this->build_property( $post_id );
			$data    = apply_filters( 'immo_manager_schema_property', $data, $post_id );
			$this->output_jsonld( $data );
			return;
		}

		if ( is_singular( PostTypes::POST_TYPE_PROJECT ) ) {
			$post_id = (int) get_queried_object_id();
			$data    = $this->build_project( $post_id );
			$data    = apply_filters( 'immo_manager_schema_project', $data, $post_id );
			$this->output_jsonld( $data );
			return;
		}
	}

	/**
	 * Property-Type aus Plugin-Meta auf schema.org Place-Subtyp mappen.
	 *
	 * @param string $type Plugin-Property-Typ.
	 *
	 * @return string Schema-Typ (Apartment, House, SingleFamilyResidence, Residence).
	 */
	private function map_property_type( string $type ): string {
		$normalized = strtolower( trim( $type ) );

		$apartment_types = array( 'apartment', 'wohnung', 'penthouse' );
		$house_types     = array( 'house', 'haus', 'villa' );
		$sfr_types       = array( 'single_family', 'einfamilienhaus' );

		if ( in_array( $normalized, $apartment_types, true ) ) {
			return 'Apartment';
		}
		if ( in_array( $normalized, $house_types, true ) ) {
			return 'House';
		}
		if ( in_array( $normalized, $sfr_types, true ) ) {
			return 'SingleFamilyResidence';
		}

		return 'Residence';
	}

	/**
	 * Plugin-Status auf schema.org availability-URL mappen.
	 *
	 * @param string $status Plugin-Status.
	 *
	 * @return string Schema-availability-URL.
	 */
	private function map_status_to_availability( string $status ): string {
		switch ( $status ) {
			case 'reserved':
				return 'https://schema.org/LimitedAvailability';
			case 'sold':
				return 'https://schema.org/SoldOut';
			case 'rented':
				return 'https://schema.org/OutOfStock';
			case 'available':
			default:
				return 'https://schema.org/InStock';
		}
	}

	/**
	 * PostalAddress aus Meta-Werten bauen.
	 *
	 * @param int $post_id Post-ID.
	 *
	 * @return array<string, mixed>|null Address-Array oder null wenn leer.
	 */
	private function build_address( int $post_id ): ?array {
		$street    = (string) get_post_meta( $post_id, '_immo_address', true );
		$postal    = (string) get_post_meta( $post_id, '_immo_postal_code', true );
		$city      = (string) get_post_meta( $post_id, '_immo_city', true );
		$region    = (string) get_post_meta( $post_id, '_immo_region_state', true );
		$country   = (string) get_post_meta( $post_id, '_immo_country', true );

		if ( '' === $street && '' === $postal && '' === $city ) {
			return null;
		}

		$address = array( '@type' => 'PostalAddress' );

		if ( '' !== $street )  { $address['streetAddress']   = $street; }
		if ( '' !== $postal )  { $address['postalCode']      = $postal; }
		if ( '' !== $city )    { $address['addressLocality'] = $city; }
		if ( '' !== $region )  { $address['addressRegion']   = $region; }
		if ( '' !== $country ) { $address['addressCountry']  = $country; }

		return $address;
	}

	/**
	 * GeoCoordinates aus Meta-Werten bauen. Null wenn lat oder lng = 0.
	 *
	 * @param int $post_id Post-ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function build_geo( int $post_id ): ?array {
		$lat = (float) get_post_meta( $post_id, '_immo_lat', true );
		$lng = (float) get_post_meta( $post_id, '_immo_lng', true );

		if ( 0.0 === $lat || 0.0 === $lng ) {
			return null;
		}

		return array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => $lat,
			'longitude' => $lng,
		);
	}

	/**
	 * Featured Image + Galerie als ImageObject-Array bauen.
	 *
	 * @param int $post_id Post-ID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_image_list( int $post_id ): array {
		$images   = array();
		$seen_ids = array();

		// Featured Image zuerst.
		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$img = $this->image_object( $thumb_id );
			if ( $img ) {
				$images[]              = $img;
				$seen_ids[ $thumb_id ] = true;
			}
		}

		// Weitere Bilder aus den Post-Attachments.
		$attachments = get_attached_media( 'image', $post_id );
		foreach ( $attachments as $att ) {
			$id = (int) $att->ID;
			if ( isset( $seen_ids[ $id ] ) ) {
				continue;
			}
			$img = $this->image_object( $id );
			if ( $img ) {
				$images[]        = $img;
				$seen_ids[ $id ] = true;
			}
		}

		return $images;
	}

	/**
	 * Einzelnes ImageObject aus Attachment-ID.
	 *
	 * @param int $attachment_id Attachment-ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function image_object( int $attachment_id ): ?array {
		$src = wp_get_attachment_image_src( $attachment_id, 'large' );
		if ( ! $src || ! is_array( $src ) ) {
			return null;
		}

		return array(
			'@type'  => 'ImageObject',
			'url'    => $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
		);
	}

	/**
	 * Ein Schema-Array als JSON-LD-Script-Block ausgeben.
	 *
	 * @param array<string, mixed>|null $data Schema-Daten.
	 *
	 * @return void
	 */
	private function output_jsonld( ?array $data ): void {
		if ( null === $data || empty( $data ) ) {
			return;
		}

		$json = wp_json_encode(
			$data,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
				| JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);

		if ( false === $json ) {
			return;
		}

		echo "\n" . '<script type="application/ld+json">' . "\n";
		echo $json . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON ist sicher kodiert.
		echo '</script>' . "\n";
	}

	/**
	 * Locale für inLanguage konvertieren (de_AT → de-AT).
	 *
	 * @return string
	 */
	private function get_in_language(): string {
		return str_replace( '_', '-', get_locale() );
	}

	/**
	 * Sale-Offer mit "ab"-Preis (minPrice) bauen.
	 *
	 * @param float  $price          Kaufpreis.
	 * @param string $status         Plugin-Status.
	 * @param string $available_from ISO-Datum oder leer.
	 *
	 * @return array<string, mixed>|null Null wenn Preis 0.
	 */
	private function build_offer_sale( float $price, string $status, string $available_from = '' ): ?array {
		if ( $price <= 0.0 ) {
			return null;
		}

		$offer = array(
			'@type'              => 'Offer',
			'priceSpecification' => array(
				'@type'         => 'PriceSpecification',
				'minPrice'      => $price,
				'priceCurrency' => 'EUR',
			),
			'availability'       => $this->map_status_to_availability( $status ),
		);

		if ( '' !== $available_from ) {
			$offer['availabilityStarts'] = $available_from;
		}

		return $offer;
	}

	/**
	 * Rent-Offer mit Monats-Miete bauen.
	 *
	 * @param float  $rent   Monatsmiete.
	 * @param string $status Plugin-Status.
	 *
	 * @return array<string, mixed>|null Null wenn Miete 0.
	 */
	private function build_offer_rent( float $rent, string $status ): ?array {
		if ( $rent <= 0.0 ) {
			return null;
		}

		return array(
			'@type'              => 'Offer',
			'priceSpecification' => array(
				'@type'         => 'UnitPriceSpecification',
				'price'         => $rent,
				'priceCurrency' => 'EUR',
				'unitText'      => 'MONTH',
			),
			'availability'       => $this->map_status_to_availability( $status ),
		);
	}

	/**
	 * Liste der additionalProperty-Werte aus Meta zusammenstellen.
	 *
	 * @param int $post_id Post-ID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_additional_properties( int $post_id ): array {
		$out = array();

		$energy_class = (string) get_post_meta( $post_id, '_immo_energy_class', true );
		if ( '' !== $energy_class ) {
			$out[] = array( '@type' => 'PropertyValue', 'name' => 'energyClass', 'value' => $energy_class );
		}

		$hwb = (float) get_post_meta( $post_id, '_immo_energy_hwb', true );
		if ( $hwb > 0 ) {
			$out[] = array( '@type' => 'PropertyValue', 'name' => 'HWB', 'value' => $hwb, 'unitText' => 'kWh/m²a' );
		}

		$fgee = (float) get_post_meta( $post_id, '_immo_energy_fgee', true );
		if ( $fgee > 0 ) {
			$out[] = array( '@type' => 'PropertyValue', 'name' => 'fGEE', 'value' => $fgee );
		}

		$heating = (string) get_post_meta( $post_id, '_immo_heating', true );
		if ( '' !== $heating ) {
			$out[] = array( '@type' => 'PropertyValue', 'name' => 'heating', 'value' => $heating );
		}

		$commission = (string) get_post_meta( $post_id, '_immo_commission', true );
		if ( '' !== $commission ) {
			$out[] = array( '@type' => 'PropertyValue', 'name' => 'commission', 'value' => $commission );
		}

		$reno = (int) get_post_meta( $post_id, '_immo_renovation_year', true );
		if ( $reno > 0 ) {
			$out[] = array( '@type' => 'PropertyValue', 'name' => 'renovationYear', 'value' => $reno );
		}

		return $out;
	}

	/**
	 * Komplettes RealEstateListing-Schema für eine Property bauen.
	 *
	 * @param int $post_id Post-ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function build_property( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		// mainEntity (Apartment/House/...).
		$type_meta   = (string) get_post_meta( $post_id, '_immo_property_type', true );
		$schema_type = $this->map_property_type( $type_meta );

		$main_entity = array( '@type' => $schema_type );

		$area = (float) get_post_meta( $post_id, '_immo_area', true );
		if ( $area > 0 ) {
			$main_entity['floorSize'] = array(
				'@type'    => 'QuantitativeValue',
				'value'    => $area,
				'unitCode' => 'MTK',
			);
		}

		$rooms = (int) get_post_meta( $post_id, '_immo_rooms', true );
		if ( $rooms > 0 ) {
			$main_entity['numberOfRooms'] = $rooms;
		}

		$bedrooms = (int) get_post_meta( $post_id, '_immo_bedrooms', true );
		if ( $bedrooms > 0 ) {
			$main_entity['numberOfBedrooms'] = $bedrooms;
		}

		$baths = (int) get_post_meta( $post_id, '_immo_bathrooms', true );
		if ( $baths > 0 ) {
			$main_entity['numberOfBathroomsTotal'] = $baths;
		}

		$built = (int) get_post_meta( $post_id, '_immo_built_year', true );
		if ( $built > 0 ) {
			$main_entity['yearBuilt'] = $built;
		}

		$address = $this->build_address( $post_id );
		if ( $address ) {
			$main_entity['address'] = $address;
		}

		$geo = $this->build_geo( $post_id );
		if ( $geo ) {
			$main_entity['geo'] = $geo;
		}

		$additional = $this->build_additional_properties( $post_id );
		if ( ! empty( $additional ) ) {
			$main_entity['additionalProperty'] = $additional;
		}

		// Top-Level RealEstateListing.
		$permalink = get_permalink( $post_id );

		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'RealEstateListing',
			'@id'        => $permalink,
			'url'        => $permalink,
			'name'       => get_the_title( $post_id ),
			'datePosted' => get_the_date( 'c', $post_id ),
			'inLanguage' => $this->get_in_language(),
			'mainEntity' => $main_entity,
		);

		$description = (string) get_the_excerpt( $post_id );
		if ( '' === $description ) {
			$description = wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 50, '…' );
		}
		if ( '' !== $description ) {
			$data['description'] = $description;
		}

		$images = $this->build_image_list( $post_id );
		if ( ! empty( $images ) ) {
			$data['image'] = $images;
		}

		// Offer-Logik.
		$mode   = (string) get_post_meta( $post_id, '_immo_mode', true );
		$status = (string) get_post_meta( $post_id, '_immo_status', true );
		$price  = (float) get_post_meta( $post_id, '_immo_price', true );
		$rent   = (float) get_post_meta( $post_id, '_immo_rent', true );
		$avail  = (string) get_post_meta( $post_id, '_immo_available_from', true );

		$offers = array();
		if ( in_array( $mode, array( 'sale', 'both' ), true ) ) {
			$o = $this->build_offer_sale( $price, $status, $avail );
			if ( $o ) {
				$offers[] = $o;
			}
		}
		if ( in_array( $mode, array( 'rent', 'both' ), true ) ) {
			$o = $this->build_offer_rent( $rent, $status );
			if ( $o ) {
				$offers[] = $o;
			}
		}

		if ( count( $offers ) === 1 ) {
			$data['offers'] = $offers[0];
		} elseif ( count( $offers ) > 1 ) {
			$data['offers'] = $offers;
		}

		return $data;
	}

	/**
	 * Eine Unit als Apartment-Schema bauen (für containsPlace).
	 *
	 * @param array<string, mixed> $unit          Hydrierte Unit-Row.
	 * @param int                  $project_id    Projekt-Post-ID.
	 * @param string               $project_title Projekt-Titel (für Name-Prefix).
	 *
	 * @return array<string, mixed>
	 */
	private function build_unit_apartment( array $unit, int $project_id, string $project_title ): array {
		$unit_id     = (int) ( $unit['id'] ?? 0 );
		$unit_number = (string) ( $unit['unit_number'] ?? '' );
		$anchor      = '#unit-' . $unit_id;
		$url         = get_permalink( $project_id ) . $anchor;

		$name = '' !== $unit_number
			? sprintf( '%s – %s', $project_title, $unit_number )
			: sprintf( '%s – Wohnung %d', $project_title, $unit_id );

		$apt = array(
			'@type' => 'Apartment',
			'@id'   => $url,
			'url'   => $url,
			'name'  => $name,
		);

		$area = (float) ( $unit['area'] ?? 0 );
		if ( $area > 0 ) {
			$apt['floorSize'] = array(
				'@type'    => 'QuantitativeValue',
				'value'    => $area,
				'unitCode' => 'MTK',
			);
		}

		$rooms = (int) ( $unit['rooms'] ?? 0 );
		if ( $rooms > 0 ) {
			$apt['numberOfRooms'] = $rooms;
		}

		$floor = (int) ( $unit['floor'] ?? 0 );
		if ( $floor > 0 ) {
			$apt['floorLevel'] = $floor;
		}

		// Fixpreis-Offer (kein "ab" – Units sind Fixpreise).
		$price  = (float) ( $unit['price'] ?? 0 );
		$status = (string) ( $unit['status'] ?? 'available' );

		if ( $price > 0 ) {
			$apt['offers'] = array(
				'@type'         => 'Offer',
				'price'         => $price,
				'priceCurrency' => 'EUR',
				'availability'  => $this->map_status_to_availability( $status ),
			);
		}

		return $apt;
	}

	/**
	 * ApartmentComplex-Schema für ein Bauprojekt bauen.
	 *
	 * @param int $post_id Projekt-Post-ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function build_project( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		$permalink = get_permalink( $post_id );
		$title     = get_the_title( $post_id );

		$data = array(
			'@context' => 'https://schema.org',
			'@type'    => 'ApartmentComplex',
			'@id'      => $permalink,
			'url'      => $permalink,
			'name'     => $title,
		);

		$description = (string) get_the_excerpt( $post_id );
		if ( '' === $description ) {
			$description = wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 50, '…' );
		}
		if ( '' !== $description ) {
			$data['description'] = $description;
		}

		$images = $this->build_image_list( $post_id );
		if ( ! empty( $images ) ) {
			$data['image'] = $images;
		}

		$address = $this->build_address( $post_id );
		if ( $address ) {
			$data['address'] = $address;
		}

		$geo = $this->build_geo( $post_id );
		if ( $geo ) {
			$data['geo'] = $geo;
		}

		// Units → containsPlace.
		$units     = Units::get_by_project( $post_id );
		$skip_sold = (bool) apply_filters( 'immo_manager_schema_skip_sold_units', false, $post_id );

		$apartments = array();
		foreach ( $units as $unit ) {
			$status = (string) ( $unit['status'] ?? 'available' );
			if ( $skip_sold && in_array( $status, array( 'sold', 'rented' ), true ) ) {
				continue;
			}
			$apartments[] = $this->build_unit_apartment( $unit, $post_id, $title );
		}

		if ( ! empty( $apartments ) ) {
			$data['numberOfAccommodationUnits'] = count( $apartments );
			$data['containsPlace']              = $apartments;
		}

		// Projekt-Status als additionalProperty.
		$project_status = (string) get_post_meta( $post_id, '_immo_project_status', true );
		if ( '' !== $project_status ) {
			$data['additionalProperty'] = array(
				array(
					'@type' => 'PropertyValue',
					'name'  => 'projectStatus',
					'value' => $project_status,
				),
			);
		}

		return $data;
	}
}
