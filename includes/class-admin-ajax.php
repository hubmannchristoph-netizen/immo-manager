<?php
/**
 * AJAX-Handler für Admin-Aktionen (Units-CRUD, Region-Cascading).
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminAjax
 *
 * Bündelt alle WP-AJAX-Endpunkte, die die Admin-UI braucht.
 * Frontend-Anfragen gehen später über die REST-API (Phase 6).
 */
class AdminAjax {

	/**
	 * Nonce-Action für Unit-Aktionen.
	 */
	public const UNITS_NONCE = 'immo_units_ajax';

	/**
	 * Nonce-Action für Regions-Lookup.
	 */
	public const REGIONS_NONCE = 'immo_regions_ajax';

	/**
	 * Konstruktor – Hooks registrieren.
	 */
	public function __construct() {
		// Unit-Endpoints (nur für eingeloggte Nutzer).
		add_action( 'wp_ajax_immo_unit_save',   array( $this, 'unit_save' ) );
		add_action( 'wp_ajax_immo_unit_delete', array( $this, 'unit_delete' ) );
		add_action( 'wp_ajax_immo_unit_list',   array( $this, 'unit_list' ) );

		// Region-Cascading (nur für eingeloggte Nutzer – Admin-Only).
		add_action( 'wp_ajax_immo_districts',   array( $this, 'districts' ) );

		// API-Key Generierung (nur für Administratoren).
		add_action( 'wp_ajax_immo_generate_api_key', array( $this, 'generate_api_key' ) );

		// Anfragen Status (Antworten).
		add_action( 'wp_ajax_immo_inquiry_replied', array( $this, 'inquiry_replied' ) );
	}

	// =========================================================================
	// Units
	// =========================================================================

	/**
	 * Unit erstellen oder aktualisieren.
	 *
	 * @return void
	 */
	public function unit_save(): void {
		$this->verify_nonce( self::UNITS_NONCE );

		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		if ( $project_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Ungültiges Projekt.', 'immo-manager' ) ), 400 );
		}
		if ( ! current_user_can( 'edit_post', $project_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung für dieses Projekt.', 'immo-manager' ) ), 403 );
		}
		if ( get_post_type( $project_id ) !== PostTypes::POST_TYPE_PROJECT ) {
			wp_send_json_error( array( 'message' => __( 'Projekt nicht gefunden.', 'immo-manager' ) ), 404 );
		}

		$raw = isset( $_POST['unit'] ) && is_array( $_POST['unit'] ) ? wp_unslash( $_POST['unit'] ) : array();
		$raw['project_id'] = $project_id;

		$unit_id = isset( $_POST['unit_id'] ) ? absint( $_POST['unit_id'] ) : 0;

		if ( $unit_id > 0 ) {
			// Update: Sicherstellen, dass die Unit zu diesem Projekt gehört.
			$existing = Units::get( $unit_id );
			if ( ! $existing || (int) $existing['project_id'] !== $project_id ) {
				wp_send_json_error( array( 'message' => __( 'Wohneinheit nicht gefunden.', 'immo-manager' ) ), 404 );
			}
			$ok = Units::update( $unit_id, $raw );
			if ( ! $ok ) {
				wp_send_json_error( array( 'message' => __( 'Speichern fehlgeschlagen.', 'immo-manager' ) ), 500 );
			}
			wp_send_json_success( array(
				'message' => __( 'Wohneinheit aktualisiert.', 'immo-manager' ),
				'unit'    => Units::get( $unit_id ),
			) );
		} else {
			$new_id = Units::create( $raw );
			if ( ! $new_id ) {
				wp_send_json_error( array( 'message' => __( 'Anlegen fehlgeschlagen.', 'immo-manager' ) ), 500 );
			}
			wp_send_json_success( array(
				'message' => __( 'Wohneinheit angelegt.', 'immo-manager' ),
				'unit'    => Units::get( $new_id ),
			) );
		}
	}

	/**
	 * Unit löschen.
	 *
	 * @return void
	 */
	public function unit_delete(): void {
		$this->verify_nonce( self::UNITS_NONCE );

		$unit_id = isset( $_POST['unit_id'] ) ? absint( $_POST['unit_id'] ) : 0;
		if ( $unit_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Wohneinheit.', 'immo-manager' ) ), 400 );
		}

		$existing = Units::get( $unit_id );
		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => __( 'Wohneinheit nicht gefunden.', 'immo-manager' ) ), 404 );
		}
		if ( ! current_user_can( 'edit_post', (int) $existing['project_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
		}

		$ok = Units::delete( $unit_id );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Löschen fehlgeschlagen.', 'immo-manager' ) ), 500 );
		}
		wp_send_json_success( array( 'message' => __( 'Wohneinheit gelöscht.', 'immo-manager' ) ) );
	}

	/**
	 * Alle Units eines Projekts auflisten.
	 *
	 * @return void
	 */
	public function unit_list(): void {
		$this->verify_nonce( self::UNITS_NONCE );

		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		if ( $project_id <= 0 || ! current_user_can( 'edit_post', $project_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
		}

		wp_send_json_success( array( 'units' => Units::get_by_project( $project_id ) ) );
	}

	// =========================================================================
	// Regions
	// =========================================================================

	/**
	 * Liste der Bezirke eines Bundeslandes.
	 *
	 * @return void
	 */
	public function districts(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
		}
		$state = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : '';
		if ( ! $state || ! Regions::is_valid_state( $state ) ) {
			wp_send_json_success( array( 'districts' => array() ) );
		}
		wp_send_json_success( array( 'districts' => Regions::get_districts( $state ) ) );
	}

	// =========================================================================
	// API-Key Verwaltung
	// =========================================================================

	/**
	 * Neuen API-Key generieren.
	 * Gibt den Klartext-Key einmalig zurück; speichert nur den Hash.
	 *
	 * @return void
	 */
	public function generate_api_key(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'immo_generate_api_key' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security-Token ungültig.', 'immo-manager' ) ), 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
		}

		// Sicheren zufälligen Key generieren.
		$raw_key = wp_generate_password( 48, false, false );
		$hash    = wp_hash_password( $raw_key );
		$preview = substr( $raw_key, 0, 8 );

		// Nur Hash und Preview speichern (nicht den Klartext-Key).
		$saved = get_option( Settings::OPTION_NAME, array() );
		$saved['api_key_hash']    = $hash;
		$saved['api_key_preview'] = $preview;
		update_option( Settings::OPTION_NAME, $saved );

		// Klartext-Key wird nur einmalig zurückgegeben.
		wp_send_json_success( array(
			'key'     => $raw_key,
			'preview' => $preview,
			'message' => __( 'API-Key erfolgreich generiert. Jetzt kopieren – er wird nur einmalig angezeigt!', 'immo-manager' ),
		) );
	}

	// =========================================================================
	// Anfragen
	// =========================================================================

	/**
	 * Status der Anfrage auf "beantwortet" setzen.
	 *
	 * @return void
	 */
	public function inquiry_replied(): void {
		$this->verify_nonce( 'immo_inquiry_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
		}

		$inq_id = isset( $_POST['inq_id'] ) ? absint( $_POST['inq_id'] ) : 0;
		if ( $inq_id > 0 ) {
			Inquiries::update_status( $inq_id, 'replied' );
			wp_send_json_success();
		}
		wp_send_json_error();
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Nonce aus $_REQUEST prüfen.
	 *
	 * @param string $action Nonce-Action.
	 *
	 * @return void
	 */
	private function verify_nonce( string $action ): void {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_send_json_error( array( 'message' => __( 'Security-Token ungültig.', 'immo-manager' ) ), 403 );
		}
	}
}
