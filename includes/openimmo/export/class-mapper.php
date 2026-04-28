<?php
namespace ImmoManager\OpenImmo\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Mapper: ein ListingDTO → ein <immobilie>-DOMElement.
 */
class Mapper {

	private \DOMDocument $dom;

	/**
	 * Mapping Plugin-property_type → OpenImmo objektart-Kindknoten.
	 * Erweitern wenn neue Property-Types dazukommen.
	 */
	private const TYPE_MAP = array(
		'wohnung'     => array( 'wohnung',     null,          null ),
		'haus'        => array( 'haus',        null,          null ),
		'grundstueck' => array( 'grundstueck', null,          null ),
		'buero'       => array( 'gewerbe',     'BUERO',       'gewerbetyp' ),
		'gewerbe'     => array( 'gewerbe',     null,          null ),
		'gastronomie' => array( 'gastgew',     'GASTRONOMIE', 'gastgewtyp' ),
	);

	/**
	 * Slug aus _immo_features → OpenImmo-Boolean-Knoten unter <ausstattung>.
	 */
	private const FEATURE_MAP = array(
		'balkon'       => 'balkon_terrasse',
		'terrasse'     => 'balkon_terrasse',
		'garten'       => 'gartennutzung',
		'lift'         => 'fahrstuhl',
		'aufzug'       => 'fahrstuhl',
		'keller'       => 'unterkellert',
		'parking'      => 'stellplatzart',
		'garage'       => 'stellplatzart',
		'klima'        => 'klimatisiert',
		'einbaukueche' => 'kueche',
	);

	public function __construct( \DOMDocument $dom ) {
		$this->dom = $dom;
	}

	public function to_immobilie( ListingDTO $listing ): \DOMElement {
		$im = $this->dom->createElement( 'immobilie' );

		$im->appendChild( $this->build_objektkategorie( $listing ) );
		$im->appendChild( $this->build_geo( $listing ) );
		$im->appendChild( $this->build_kontaktperson( $listing ) );
		$im->appendChild( $this->build_preise( $listing ) );
		$im->appendChild( $this->build_flaechen( $listing ) );
		$im->appendChild( $this->build_ausstattung( $listing ) );
		$im->appendChild( $this->build_zustand_angaben( $listing ) );
		$im->appendChild( $this->build_verwaltung_objekt( $listing ) );
		$im->appendChild( $this->build_verwaltung_techn( $listing ) );
		$im->appendChild( $this->build_freitexte( $listing ) );

		// <anhaenge> wird separat (in ExportService) befüllt — leerer Container.
		$im->appendChild( $this->dom->createElement( 'anhaenge' ) );

		return $im;
	}

	private function build_objektkategorie( ListingDTO $l ): \DOMElement {
		$el = $this->dom->createElement( 'objektkategorie' );

		// <nutzungsart wohnen="true"/> — wir fokussieren in Phase 1 auf Wohnen.
		$nutzung = $this->dom->createElement( 'nutzungsart' );
		$nutzung->setAttribute( 'WOHNEN', 'true' );
		$el->appendChild( $nutzung );

		// <vermarktungsart>.
		$mode = (string) ( $l->meta['_immo_mode'] ?? 'sale' );
		if ( ! in_array( $mode, array( 'sale', 'rent', 'both' ), true ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Unbekannter _immo_mode-Wert "%s" fuer Listing %s', $mode, $l->external_id )
			);
		}
		$verm = $this->dom->createElement( 'vermarktungsart' );
		$verm->setAttribute( 'KAUF',        in_array( $mode, array( 'sale', 'both' ), true ) ? 'true' : 'false' );
		$verm->setAttribute( 'MIETE_PACHT', in_array( $mode, array( 'rent', 'both' ), true ) ? 'true' : 'false' );
		$el->appendChild( $verm );

		// <objektart>.
		$type    = (string) ( $l->meta['_immo_property_type'] ?? 'wohnung' );
		$mapping = self::TYPE_MAP[ $type ] ?? array( 'wohnung', null, null );
		$art     = $this->dom->createElement( 'objektart' );
		$sub     = $this->dom->createElement( $mapping[0] );
		if ( null !== $mapping[1] && null !== $mapping[2] ) {
			$sub->setAttribute( $mapping[2], $mapping[1] );
		}
		$art->appendChild( $sub );
		$el->appendChild( $art );

		return $el;
	}

	private function build_geo( ListingDTO $l ): \DOMElement {
		$el = $this->dom->createElement( 'geo' );

		$this->text( $el, 'plz',        (string) ( $l->meta['_immo_postal_code']  ?? '' ) );
		$this->text( $el, 'ort',        (string) ( $l->meta['_immo_city']         ?? '' ) );
		$this->text( $el, 'strasse',    (string) ( $l->meta['_immo_address']      ?? '' ) );
		$this->text( $el, 'bundesland', (string) ( $l->meta['_immo_region_state'] ?? '' ) );

		$iso  = (string) ( $l->meta['_immo_country'] ?? 'AT' );
		$land = $this->dom->createElement( 'land' );
		$land->setAttribute( 'iso_land', $iso );
		$el->appendChild( $land );

		if ( ! empty( $l->meta['_immo_lat'] ) || ! empty( $l->meta['_immo_lng'] ) ) {
			$geo = $this->dom->createElement( 'geokoordinaten' );
			$geo->setAttribute( 'breitengrad', $this->float_str( $l->meta['_immo_lat'] ?? 0 ) );
			$geo->setAttribute( 'laengengrad',  $this->float_str( $l->meta['_immo_lng'] ?? 0 ) );
			$el->appendChild( $geo );
		}

		if ( ! empty( $l->meta['_immo_floor'] ) ) {
			$this->text( $el, 'etage', (string) (int) $l->meta['_immo_floor'] );
		}
		if ( ! empty( $l->meta['_immo_total_floors'] ) ) {
			$this->text( $el, 'anzahl_etagen', (string) (int) $l->meta['_immo_total_floors'] );
		}

		return $el;
	}

	private function build_kontaktperson( ListingDTO $l ): \DOMElement {
		$el = $this->dom->createElement( 'kontaktperson' );

		$name  = (string) ( $l->meta['_immo_contact_name'] ?? '' );
		// TODO Phase 6: Naive Whitespace-Aufteilung in Vor-/Nachname produziert bei
		// Firmennamen ("Müller GmbH") falsche Werte. Heuristik (Anrede-Detection,
		// Firmenkennzeichen) oder explizite vorname/nachname-Felder einführen.
		$parts = preg_split( '/\\s+/', $name, 2 );
		$vor   = $parts[0] ?? '';
		$nach  = $parts[1] ?? '';

		$this->text( $el, 'name', $nach !== '' ? $nach : $name );
		if ( $vor !== '' ) {
			$this->text( $el, 'vorname', $vor );
		}
		if ( ! empty( $l->meta['_immo_contact_email'] ) ) {
			$this->text( $el, 'email_zentrale', (string) $l->meta['_immo_contact_email'] );
		}
		if ( ! empty( $l->meta['_immo_contact_phone'] ) ) {
			$this->text( $el, 'tel_zentrale', (string) $l->meta['_immo_contact_phone'] );
		}
		return $el;
	}

	private function build_preise( ListingDTO $l ): \DOMElement {
		$el   = $this->dom->createElement( 'preise' );
		$mode = (string) ( $l->meta['_immo_mode'] ?? 'sale' );

		if ( in_array( $mode, array( 'sale', 'both' ), true ) && ! empty( $l->meta['_immo_price'] ) ) {
			$this->text( $el, 'kaufpreis', $this->float_str( $l->meta['_immo_price'] ) );
		}
		if ( in_array( $mode, array( 'rent', 'both' ), true ) && ! empty( $l->meta['_immo_rent'] ) ) {
			$this->text( $el, 'kaltmiete', $this->float_str( $l->meta['_immo_rent'] ) );
		}
		if ( ! empty( $l->meta['_immo_operating_costs'] ) ) {
			$this->text( $el, 'nebenkosten', $this->float_str( $l->meta['_immo_operating_costs'] ) );
		}
		if ( ! empty( $l->meta['_immo_deposit'] ) ) {
			$this->text( $el, 'kaution', $this->float_str( $l->meta['_immo_deposit'] ) );
		}
		if ( ! empty( $l->meta['_immo_commission'] ) ) {
			$this->text( $el, 'aussen_courtage', (string) $l->meta['_immo_commission'] );
		}
		return $el;
	}

	private function build_flaechen( ListingDTO $l ): \DOMElement {
		$el = $this->dom->createElement( 'flaechen' );

		if ( ! empty( $l->meta['_immo_area'] ) ) {
			$this->text( $el, 'wohnflaeche', $this->float_str( $l->meta['_immo_area'] ) );
		}
		if ( ! empty( $l->meta['_immo_usable_area'] ) ) {
			$this->text( $el, 'nutzflaeche', $this->float_str( $l->meta['_immo_usable_area'] ) );
		}
		if ( ! empty( $l->meta['_immo_land_area'] ) ) {
			$this->text( $el, 'grundstuecksflaeche', $this->float_str( $l->meta['_immo_land_area'] ) );
		}
		if ( ! empty( $l->meta['_immo_rooms'] ) ) {
			$this->text( $el, 'anzahl_zimmer', (string) (int) $l->meta['_immo_rooms'] );
		}
		if ( ! empty( $l->meta['_immo_bedrooms'] ) ) {
			$this->text( $el, 'anzahl_schlafzimmer', (string) (int) $l->meta['_immo_bedrooms'] );
		}
		if ( ! empty( $l->meta['_immo_bathrooms'] ) ) {
			$this->text( $el, 'anzahl_badezimmer', (string) (int) $l->meta['_immo_bathrooms'] );
		}
		return $el;
	}

	private function build_ausstattung( ListingDTO $l ): \DOMElement {
		$el       = $this->dom->createElement( 'ausstattung' );
		$features = (array) ( $l->meta['_immo_features'] ?? array() );

		$oi_seen = array();
		foreach ( $features as $slug ) {
			$slug = (string) $slug;
			if ( ! isset( self::FEATURE_MAP[ $slug ] ) ) {
				continue;
			}
			$oi_tag = self::FEATURE_MAP[ $slug ];
			if ( in_array( $oi_tag, $oi_seen, true ) ) {
				continue;
			}
			$oi_seen[] = $oi_tag;
			$node      = $this->dom->createElement( $oi_tag );
			// Feature-Knoten in OpenImmo sind oft Boolean-Container ohne Wert.
			$el->appendChild( $node );
		}

		if ( ! empty( $l->meta['_immo_heating'] ) ) {
			// TODO Phase 6: Enum-Mapping für heizungsart. OpenImmo erwartet als Boolean-Attribute
			// ETAGE/GAS/ELEKTRO/OFEN/FUSSBODEN/KAMIN. Hier wird OFEN=false als sicheres Default
			// gesetzt; der eigentliche Wert landet in <sonstige_heizungsart>. Korrektes Mapping
			// von _immo_heating-Strings auf die richtigen OpenImmo-Booleans braucht eine
			// strukturierte Lookup-Tabelle, die in Phase 6 portal-spezifisch werden kann.
			$heiz = $this->dom->createElement( 'heizungsart' );
			$heiz->setAttribute( 'OFEN', 'false' );
			$el->appendChild( $heiz );
			// Heizungs-Detail als Freitext.
			$this->text( $el, 'sonstige_heizungsart', (string) $l->meta['_immo_heating'] );
		}

		return $el;
	}

	private function build_zustand_angaben( ListingDTO $l ): \DOMElement {
		$el = $this->dom->createElement( 'zustand_angaben' );

		if ( ! empty( $l->meta['_immo_built_year'] ) ) {
			$this->text( $el, 'baujahr', (string) (int) $l->meta['_immo_built_year'] );
		}
		if ( ! empty( $l->meta['_immo_renovation_year'] ) ) {
			$this->text( $el, 'letztemodernisierung', (string) (int) $l->meta['_immo_renovation_year'] );
		}

		if ( ! empty( $l->meta['_immo_energy_class'] )
			|| ! empty( $l->meta['_immo_energy_hwb'] )
			|| ! empty( $l->meta['_immo_energy_fgee'] ) ) {
			$energie = $this->dom->createElement( 'energiepass' );
			if ( ! empty( $l->meta['_immo_energy_class'] ) ) {
				$this->text( $energie, 'epart', 'BEDARF' );
				$this->text( $energie, 'energieverbrauchkennwert', (string) $l->meta['_immo_energy_class'] );
			}
			if ( ! empty( $l->meta['_immo_energy_hwb'] ) ) {
				$this->text( $energie, 'hwbwert', $this->float_str( $l->meta['_immo_energy_hwb'] ) );
				if ( ! empty( $l->meta['_immo_energy_class'] ) ) {
					$this->text( $energie, 'hwbklasse', (string) $l->meta['_immo_energy_class'] );
				}
			}
			if ( ! empty( $l->meta['_immo_energy_fgee'] ) ) {
				$this->text( $energie, 'fgeewert', $this->float_str( $l->meta['_immo_energy_fgee'] ) );
			}
			$el->appendChild( $energie );
		}

		return $el;
	}

	private function build_verwaltung_objekt( ListingDTO $l ): \DOMElement {
		$el = $this->dom->createElement( 'verwaltung_objekt' );
		if ( ! empty( $l->meta['_immo_available_from'] ) ) {
			$this->text( $el, 'verfuegbar_ab', (string) $l->meta['_immo_available_from'] );
		}
		return $el;
	}

	private function build_verwaltung_techn( ListingDTO $l ): \DOMElement {
		$el = $this->dom->createElement( 'verwaltung_techn' );
		$this->text( $el, 'objektnr_intern', $l->external_id );

		$aktion = $this->dom->createElement( 'aktion' );
		$aktion->setAttribute( 'aktionart', 'REFRESH' );
		$el->appendChild( $aktion );

		return $el;
	}

	private function build_freitexte( ListingDTO $l ): \DOMElement {
		$el = $this->dom->createElement( 'freitexte' );

		$this->text( $el, 'objekttitel',        $l->title );
		$this->text( $el, 'objektbeschreibung', wp_strip_all_tags( $l->description ) );

		if ( ! empty( $l->meta['_immo_custom_features'] ) ) {
			$this->text( $el, 'sonstige_angaben', (string) $l->meta['_immo_custom_features'] );
		}
		return $el;
	}

	private function text( \DOMElement $parent, string $tag, string $value ): void {
		$el = $this->dom->createElement( $tag );
		$el->appendChild( $this->dom->createTextNode( $value ) );
		$parent->appendChild( $el );
	}

	/**
	 * Locale-safe float-to-string conversion.
	 *
	 * PHP's `(string) (float) $value` uses the current C locale, which can produce
	 * `,` instead of `.` as decimal separator on servers with non-English locales.
	 * OpenImmo's XSD requires xs:decimal with `.` — this helper guarantees that.
	 */
	private function float_str( $value ): string {
		$str = sprintf( '%F', (float) $value );
		// "%F" in PHP is locale-independent (always uses '.').
		// Trim trailing zeros for compactness, then trailing '.' if no decimals remain.
		return rtrim( rtrim( $str, '0' ), '.' );
	}
}
