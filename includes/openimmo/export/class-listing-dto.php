<?php
/**
 * ListingDTO – Werte-Objekt für ein Listing im OpenImmo-Export.
 *
 * @package ImmoManager\OpenImmo\Export
 */

namespace ImmoManager\OpenImmo\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Werte-Objekt für ein Listing (Property oder Unit) im Export.
 *
 * Enthält alle Felder, die der Mapper für 1 <immobilie> braucht.
 * Geo/Kontakt-Felder kommen bei Units vom übergeordneten Project.
 */
class ListingDTO {

	/** @var string 'property' | 'unit' */
	public string $source;

	/** @var int Post-ID (für Property) oder Unit-ID (für Unit) */
	public int $id;

	/** @var int|null Parent-Project-ID (nur bei source='unit') */
	public ?int $project_id;

	/** @var string Eindeutige objektnr_intern für OpenImmo (z.B. "wp-123" / "unit-45") */
	public string $external_id;

	/** @var string Titel */
	public string $title;

	/** @var string Beschreibung (HTML wird in Plain-Text umgewandelt vom Mapper) */
	public string $description;

	/** @var array<string,mixed> Alle Plugin-Meta-Felder gebündelt */
	public array $meta;

	/** @var int|null Featured-Image (Attachment-ID) */
	public ?int $featured_image_id;

	/** @var int[] Galerie-Attachment-IDs */
	public array $gallery_image_ids;

	public static function from_property( \WP_Post $post ): self {
		$dto                    = new self();
		$dto->source            = 'property';
		$dto->id                = $post->ID;
		$dto->project_id        = null;
		$dto->external_id       = 'wp-' . $post->ID;
		$dto->title             = $post->post_title;
		$dto->description       = $post->post_content;
		$dto->meta              = self::collect_meta( $post->ID );
		$thumb                  = (int) get_post_thumbnail_id( $post->ID );
		$dto->featured_image_id = $thumb > 0 ? $thumb : null;
		$dto->gallery_image_ids = self::gallery_for_property( $post->ID );
		return $dto;
	}

	public static function from_unit( array $unit, int $project_id ): self {
		$dto                    = new self();
		$dto->source            = 'unit';
		$dto->id                = (int) $unit['id'];
		$dto->project_id        = $project_id;
		$dto->external_id       = 'unit-' . (int) $unit['id'];
		$unit_number = isset( $unit['unit_number'] ) && '' !== (string) $unit['unit_number']
			? (string) $unit['unit_number']
			: (string) $unit['id'];
		$dto->title  = sprintf( 'Unit %s', $unit_number );
		$dto->description       = (string) ( $unit['description'] ?? '' );
		// Unit-Felder + Project-Meta für Geo/Kontakt.
		$dto->meta              = array_merge(
			self::collect_meta( $project_id ),  // Geo/Kontakt vom Project.
			self::unit_to_meta( $unit )
		);
		$dto->featured_image_id = ! empty( $unit['floor_plan_image_id'] ) ? (int) $unit['floor_plan_image_id'] : null;
		$dto->gallery_image_ids = self::gallery_for_unit( $unit );
		return $dto;
	}

	/**
	 * Sammelt alle _immo_*-Meta-Felder eines Posts in ein assoziatives Array.
	 *
	 * Hinweis: Bei Meta-Keys mit mehreren Werten (mehrfaches add_post_meta ohne
	 * unique-Flag) wird nur der erste Wert übernommen. Im Plugin-Datenmodell
	 * sind alle _immo_*-Felder single-value (siehe MetaFields::register_meta).
	 */
	private static function collect_meta( int $post_id ): array {
		$raw = get_post_meta( $post_id );
		$out = array();
		foreach ( $raw as $key => $values ) {
			if ( strpos( $key, '_immo_' ) === 0 && isset( $values[0] ) ) {
				$out[ $key ] = maybe_unserialize( $values[0] );
			}
		}
		return $out;
	}

	private static function unit_to_meta( array $unit ): array {
		// Unit-Spalten in den _immo_*-Schlüsselraum übersetzen.
		return array(
			'_immo_area'          => (float) ( $unit['area']        ?? 0 ),
			'_immo_usable_area'   => (float) ( $unit['usable_area'] ?? 0 ),
			'_immo_rooms'         => (int)   ( $unit['rooms']       ?? 0 ),
			'_immo_bedrooms'      => (int)   ( $unit['bedrooms']    ?? 0 ),
			'_immo_bathrooms'     => (int)   ( $unit['bathrooms']   ?? 0 ),
			'_immo_floor'         => (int)   ( $unit['floor']       ?? 0 ),
			'_immo_price'         => (float) ( $unit['price']       ?? 0 ),
			'_immo_rent'          => (float) ( $unit['rent']        ?? 0 ),
			'_immo_status'        => (string) ( $unit['status']     ?? 'available' ),
			// Bei Units: hardcode auf "wohnung". Phase 6 kann das verfeinern.
			'_immo_property_type' => 'wohnung',
			// Plan-Abweichung (T3-Code-Review): bei Units mit beiden Preisen wird 'both' gewählt
			// statt nur 'rent' — verhindert dass kaufpreis verloren geht.
			'_immo_mode'          => ( ! empty( $unit['rent'] ) && ! empty( $unit['price'] ) ) ? 'both'
				                   : ( ! empty( $unit['rent'] ) ? 'rent' : 'sale' ),
			'_immo_features'      => (array) ( $unit['features'] ?? array() ),
		);
	}

	private static function gallery_for_property( int $post_id ): array {
		// Property-Galerie: erstmal alle Attachments mit post_parent = $post_id, außer Featured.
		$attachments = get_attached_media( 'image', $post_id );
		$featured    = (int) get_post_thumbnail_id( $post_id );
		$ids         = array();
		foreach ( $attachments as $att ) {
			if ( (int) $att->ID !== $featured ) {
				$ids[] = (int) $att->ID;
			}
		}
		return $ids;
	}

	private static function gallery_for_unit( array $unit ): array {
		$raw = $unit['gallery_images'] ?? '';
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return array_map( 'intval', $decoded );
			}
		}
		if ( is_array( $raw ) ) {
			return array_map( 'intval', $raw );
		}
		return array();
	}
}
