<?php
/**
 * Mapper: ein <immobilie>-DOMElement → ein ImportListingDTO.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Import;

use ImmoManager\OpenImmo\Export\Mapper;

defined( 'ABSPATH' ) || exit;

class ImportMapper {

	public function from_immobilie( \DOMElement $immobilie ): ImportListingDTO {
		$dto = new ImportListingDTO();

		$dto->external_id = $this->read_text( $immobilie, 'objektnr_intern' );
		$dto->title       = $this->read_text( $immobilie, 'objekttitel' );
		$dto->description = $this->read_text( $immobilie, 'objektbeschreibung' );

		$meta = array();

		$meta['_immo_property_type'] = $this->read_objektart( $immobilie );
		$meta['_immo_mode']          = $this->read_vermarktungsart( $immobilie );

		$meta['_immo_postal_code']  = $this->read_text( $immobilie, 'plz' );
		$meta['_immo_city']         = $this->read_text( $immobilie, 'ort' );
		$meta['_immo_address']      = $this->read_text( $immobilie, 'strasse' );
		$meta['_immo_region_state'] = $this->read_text( $immobilie, 'bundesland' );
		$meta['_immo_country']      = $this->read_attr( $immobilie, 'land', 'iso_land' ) !== '' ? $this->read_attr( $immobilie, 'land', 'iso_land' ) : 'AT';

		$geo_node = $this->first_child( $immobilie, 'geokoordinaten' );
		if ( $geo_node instanceof \DOMElement ) {
			$meta['_immo_lat'] = (float) $geo_node->getAttribute( 'breitengrad' );
			$meta['_immo_lng'] = (float) $geo_node->getAttribute( 'laengengrad' );
		}
		$meta['_immo_floor']        = (int) $this->read_text( $immobilie, 'etage' );
		$meta['_immo_total_floors'] = (int) $this->read_text( $immobilie, 'anzahl_etagen' );

		$meta['_immo_area']        = (float) $this->read_text( $immobilie, 'wohnflaeche' );
		$meta['_immo_usable_area'] = (float) $this->read_text( $immobilie, 'nutzflaeche' );
		$meta['_immo_land_area']   = (float) $this->read_text( $immobilie, 'grundstuecksflaeche' );
		$meta['_immo_rooms']       = (int) $this->read_text( $immobilie, 'anzahl_zimmer' );
		$meta['_immo_bedrooms']    = (int) $this->read_text( $immobilie, 'anzahl_schlafzimmer' );
		$meta['_immo_bathrooms']   = (int) $this->read_text( $immobilie, 'anzahl_badezimmer' );

		$meta['_immo_price']           = (float) $this->read_text( $immobilie, 'kaufpreis' );
		$meta['_immo_rent']            = (float) $this->read_text( $immobilie, 'kaltmiete' );
		$meta['_immo_operating_costs'] = (float) $this->read_text( $immobilie, 'nebenkosten' );
		$meta['_immo_deposit']         = (float) $this->read_text( $immobilie, 'kaution' );
		$meta['_immo_commission']      = $this->read_text( $immobilie, 'aussen_courtage' );

		$meta['_immo_built_year']      = (int) $this->read_text( $immobilie, 'baujahr' );
		$meta['_immo_renovation_year'] = (int) $this->read_text( $immobilie, 'letztemodernisierung' );
		$meta['_immo_energy_class']    = $this->read_text( $immobilie, 'hwbklasse' );
		$meta['_immo_energy_hwb']      = (float) $this->read_text( $immobilie, 'hwbwert' );
		$meta['_immo_energy_fgee']     = (float) $this->read_text( $immobilie, 'fgeewert' );

		$meta['_immo_features'] = $this->read_features( $immobilie );
		$heizungsdetail         = $this->read_text( $immobilie, 'sonstige_heizungsart' );
		if ( '' !== $heizungsdetail ) {
			$meta['_immo_heating'] = $heizungsdetail;
		}

		$meta['_immo_available_from'] = $this->read_text( $immobilie, 'verfuegbar_ab' );

		$kontakt_vor                 = $this->read_text( $immobilie, 'vorname' );
		$kontakt_name                = $this->read_text( $immobilie, 'name' );
		$meta['_immo_contact_name']  = trim( $kontakt_vor . ' ' . $kontakt_name );
		$meta['_immo_contact_email'] = $this->read_text( $immobilie, 'email_zentrale' );
		$meta['_immo_contact_phone'] = $this->read_text( $immobilie, 'tel_zentrale' );

		$dto->meta       = $meta;
		$dto->raw_images = $this->read_anhaenge( $immobilie );

		return $dto;
	}

	private function read_text( \DOMElement $parent, string $tag ): string {
		$nodes = $parent->getElementsByTagName( $tag );
		if ( 0 === $nodes->length ) {
			return '';
		}
		return trim( (string) $nodes->item( 0 )->textContent );
	}

	private function read_attr( \DOMElement $parent, string $child_tag, string $attr ): string {
		$nodes = $parent->getElementsByTagName( $child_tag );
		if ( 0 === $nodes->length ) {
			return '';
		}
		$node = $nodes->item( 0 );
		return $node instanceof \DOMElement ? (string) $node->getAttribute( $attr ) : '';
	}

	private function first_child( \DOMElement $parent, string $tag ): ?\DOMElement {
		$nodes = $parent->getElementsByTagName( $tag );
		if ( 0 === $nodes->length ) {
			return null;
		}
		$node = $nodes->item( 0 );
		return $node instanceof \DOMElement ? $node : null;
	}

	private function read_objektart( \DOMElement $immobilie ): string {
		$art = $this->first_child( $immobilie, 'objektart' );
		if ( null === $art ) {
			return 'wohnung';
		}
		foreach ( Mapper::TYPE_MAP as $plugin_slug => $mapping ) {
			$xml_tag         = $mapping[0];
			$expected_attr_v = $mapping[1];
			$expected_attr_n = $mapping[2];

			$child = $this->first_child( $art, $xml_tag );
			if ( null === $child ) {
				continue;
			}
			if ( null !== $expected_attr_n ) {
				if ( $child->getAttribute( $expected_attr_n ) === $expected_attr_v ) {
					return $plugin_slug;
				}
				continue;
			}
			return $plugin_slug;
		}
		return 'wohnung';
	}

	private function read_vermarktungsart( \DOMElement $immobilie ): string {
		$verm = $this->first_child( $immobilie, 'vermarktungsart' );
		if ( null === $verm ) {
			return 'sale';
		}
		$kauf  = 'true' === strtolower( (string) $verm->getAttribute( 'KAUF' ) );
		$miete = 'true' === strtolower( (string) $verm->getAttribute( 'MIETE_PACHT' ) );
		if ( $kauf && $miete ) {
			return 'both';
		}
		if ( $miete ) {
			return 'rent';
		}
		return 'sale';
	}

	private function read_features( \DOMElement $immobilie ): array {
		$ausstattung = $this->first_child( $immobilie, 'ausstattung' );
		if ( null === $ausstattung ) {
			return array();
		}
		$plugin_features = array();
		$seen_oi_tags    = array();
		foreach ( Mapper::FEATURE_MAP as $plugin_slug => $oi_tag ) {
			if ( isset( $seen_oi_tags[ $oi_tag ] ) ) {
				continue;
			}
			if ( $ausstattung->getElementsByTagName( $oi_tag )->length > 0 ) {
				$plugin_features[]       = $plugin_slug;
				$seen_oi_tags[ $oi_tag ] = true;
			}
		}
		return $plugin_features;
	}

	private function read_anhaenge( \DOMElement $immobilie ): array {
		$anhaenge = $this->first_child( $immobilie, 'anhaenge' );
		if ( null === $anhaenge ) {
			return array();
		}
		$out = array();
		foreach ( $anhaenge->getElementsByTagName( 'anhang' ) as $anhang ) {
			if ( ! $anhang instanceof \DOMElement ) {
				continue;
			}
			$gruppe  = (string) $anhang->getAttribute( 'gruppe' );
			if ( '' === $gruppe ) {
				$gruppe = 'BILD';
			}
			$relpath = '';
			$pfad    = $anhang->getElementsByTagName( 'pfad' );
			if ( $pfad->length > 0 ) {
				$relpath = trim( (string) $pfad->item( 0 )->textContent );
			}
			if ( '' === $relpath ) {
				continue;
			}
			$out[] = array( 'relpath' => $relpath, 'gruppe' => $gruppe );
		}
		return $out;
	}
}
