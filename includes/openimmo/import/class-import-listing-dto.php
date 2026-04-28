<?php
/**
 * DTO für ein importiertes OpenImmo-Listing.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Import;

defined( 'ABSPATH' ) || exit;

class ImportListingDTO {

	/** @var string aus <verwaltung_techn><objektnr_intern> */
	public string $external_id = '';

	/** @var string aus <freitexte><objekttitel> */
	public string $title = '';

	/** @var string aus <freitexte><objektbeschreibung> */
	public string $description = '';

	/**
	 * Plugin-Meta-Felder (`_immo_*` → Wert).
	 *
	 * @var array<string,mixed>
	 */
	public array $meta = array();

	/**
	 * Roh-Bild-Verweise aus dem XML.
	 *
	 * @var array<int,array{relpath:string, gruppe:string}>
	 */
	public array $raw_images = array();
}
