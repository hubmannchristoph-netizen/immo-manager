<?php
namespace ImmoManager\OpenImmo\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Erzeugt das OpenImmo-Wrapper-XML (uebertragung + anbieter).
 * <immobilie>-Knoten werden via append_immobilie() angehängt.
 */
class XmlBuilder {

	private \DOMDocument $dom;
	private \DOMElement $anbieter;

	public function __construct() {
		$this->dom               = new \DOMDocument( '1.0', 'UTF-8' );
		$this->dom->formatOutput = true;
	}

	/**
	 * Initialisiert <openimmo><uebertragung><anbieter>.
	 *
	 * @param array $portal Portal-Settings inkl. Anbieter-Block (siehe Task 2).
	 */
	public function open( array $portal, string $plugin_version ): void {
		$root = $this->dom->createElement( 'openimmo' );
		$root->setAttribute( 'xmlns',   'http://www.openimmo.de' );
		$root->setAttribute( 'version', '1.2.7' );
		$this->dom->appendChild( $root );

		// <uebertragung>.
		$uebertragung = $this->dom->createElement( 'uebertragung' );
		$uebertragung->setAttribute( 'sendersoftware', 'immo-manager' );
		$uebertragung->setAttribute( 'senderversion', $plugin_version );
		$uebertragung->setAttribute( 'art',   'OFFLINE' );
		$uebertragung->setAttribute( 'modus', 'CHANGE' );
		$uebertragung->setAttribute( 'version',     '1.2.7' );
		$uebertragung->setAttribute( 'regi_id',     (string) ( $portal['regi_id']     ?? '' ) );
		$uebertragung->setAttribute( 'techn_email', (string) ( $portal['techn_email'] ?? '' ) );
		$uebertragung->setAttribute( 'timestamp',   gmdate( 'Y-m-d\\TH:i:s' ) );
		$root->appendChild( $uebertragung );

		// <anbieter>.
		$anbieter = $this->dom->createElement( 'anbieter' );
		$this->add_text( $anbieter, 'anbieternr',     (string) ( $portal['anbieternr']    ?? '' ) );
		$this->add_text( $anbieter, 'firma',          (string) ( $portal['firma']         ?? '' ) );
		$this->add_text( $anbieter, 'openimmo_anid',  (string) ( $portal['openimmo_anid'] ?? '' ) );
		$this->add_text( $anbieter, 'lizenzkennung',  (string) ( $portal['lizenzkennung'] ?? '' ) );
		if ( ! empty( $portal['impressum'] ) ) {
			$this->add_text( $anbieter, 'impressum', (string) $portal['impressum'] );
		}
		$root->appendChild( $anbieter );
		$this->anbieter = $anbieter;
	}

	public function append_immobilie( \DOMElement $immobilie ): void {
		$this->anbieter->appendChild( $immobilie );
	}

	public function dom(): \DOMDocument {
		return $this->dom;
	}

	public function to_string(): string {
		return (string) $this->dom->saveXML();
	}

	private function add_text( \DOMElement $parent, string $tag, string $value ): void {
		$el = $this->dom->createElement( $tag );
		$el->appendChild( $this->dom->createTextNode( $value ) );
		$parent->appendChild( $el );
	}
}
