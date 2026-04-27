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
}
