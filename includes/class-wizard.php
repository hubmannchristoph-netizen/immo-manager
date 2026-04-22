<?php
/**
 * 6-Schritte-Wizard für Immobilien-Eingabe.
 *
 * Shortcode: [immo_wizard]
 * Optional:  [immo_wizard id="POST_ID"] – Bearbeitung einer vorhandenen Immobilie
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Wizard
 */
class Wizard {

	/** Nonce-Action für alle AJAX-Requests. */
	public const NONCE_ACTION = 'immo_wizard_save';

	/** AJAX-Action: Immobilie veröffentlichen. */
	public const AJAX_SAVE = 'immo_wizard_save';

	/** AJAX-Action: Entwurf speichern. */
	public const AJAX_AUTOSAVE = 'immo_wizard_autosave';

	/**
	 * Konstruktor – registriert Hooks.
	 */
	public function __construct() {
		add_shortcode( 'immo_wizard', array( $this, 'render' ) );
		add_action( 'wp_ajax_' . self::AJAX_SAVE,        array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_' . self::AJAX_AUTOSAVE,    array( $this, 'ajax_autosave' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_SAVE, array( $this, 'ajax_save_guest' ) );

		// Admin Hooks für Backend Wizard Enforcement
		add_action( 'admin_menu', array( $this, 'register_admin_wizard' ) );
		add_action( 'current_screen', array( $this, 'redirect_to_admin_wizard' ) );
		add_action( 'admin_notices', array( $this, 'show_classic_editor_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Zeigt einen Hinweis im Standard-Editor an, wie man zum Wizard zurückkehrt.
	 */
	public function show_classic_editor_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( PostTypes::POST_TYPE_PROPERTY, PostTypes::POST_TYPE_PROJECT ), true ) ) {
			return;
		}

		if ( ! isset( $_GET['mode'] ) || 'classic' !== $_GET['mode'] ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$url     = admin_url( 'admin.php?page=immo-wizard' );
		if ( $post_id ) {
			$url = add_query_arg( 'id', $post_id, $url );
		} else {
			$url = add_query_arg( 'post_type', $screen->post_type, $url );
		}

		printf(
			'<div class="notice notice-info is-dismissible"><p>%s <a href="%s" class="button button-small" style="margin-left:10px;">%s</a></p></div>',
			esc_html__( 'Sie verwenden aktuell die Standard-Verwaltung.', 'immo-manager' ),
			esc_url( $url ),
			esc_html__( 'Zurück zum Immo Wizard', 'immo-manager' )
		);
	}

	// =========================================================================
	// Admin Wizard Enforcement
	// =========================================================================

	public function register_admin_wizard(): void {
		add_submenu_page(
			'null', // Versteckte Seite (nicht im Menü sichtbar)
			__( 'Immo Wizard', 'immo-manager' ),
			__( 'Immo Wizard', 'immo-manager' ),
			'edit_posts',
			'immo-wizard',
			array( $this, 'render_admin_wizard' )
		);
	}

	public function render_admin_wizard(): void {
		$post_id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : PostTypes::POST_TYPE_PROPERTY;
		$entity    = isset( $_GET['entity_type'] ) ? sanitize_key( $_GET['entity_type'] ) : ( $post_type === PostTypes::POST_TYPE_PROJECT ? 'project' : 'property' );

		echo '<div class="wrap immo-admin-wizard-wrap" style="margin: 20px 0;">';
		
		// Header mit Switch-Button
		echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">';
		echo '<h1 style="margin:0;">' . esc_html__( 'Immo Manager – Bearbeitung', 'immo-manager' ) . '</h1>';
		if ( $post_id ) {
			$classic_url = add_query_arg( array( 'post' => $post_id, 'action' => 'edit', 'mode' => 'classic' ), admin_url( 'post.php' ) );
			printf( '<a href="%s" class="button">%s</a>', esc_url( $classic_url ), esc_html__( '→ Zum Standard-Editor wechseln', 'immo-manager' ) );
		}
		echo '</div>';

		echo do_shortcode( sprintf( '[immo_wizard id="%d" entity_type="%s"]', $post_id, $entity ) );
		echo '</div>';
	}

	public function redirect_to_admin_wizard( \WP_Screen $screen ): void {
		// Prüfen, ob wir uns auf dem "Erstellen" oder "Bearbeiten" Screen der Immo CPTs befinden
		if ( in_array( $screen->id, array( PostTypes::POST_TYPE_PROPERTY, PostTypes::POST_TYPE_PROJECT ), true ) && 'post' === $screen->base ) {
			
			// Erlaube den Zugriff auf den klassischen Editor, wenn explizit angefordert.
			if ( isset( $_GET['mode'] ) && 'classic' === $_GET['mode'] ) {
				return;
			}

			// Verhindern, dass Lösch-Aktionen (Papierkorb) blockiert werden.
			$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
			if ( in_array( $action, array( 'trash', 'untrash', 'delete' ), true ) ) {
				return;
			}

			$post_id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
			$url       = admin_url( 'admin.php?page=immo-wizard' );
			
			if ( $post_id ) {
				$url = add_query_arg( 'id', $post_id, $url );
			} else {
				$url = add_query_arg( 'post_type', $screen->post_type, $url );
			}
			
			wp_safe_redirect( $url );
			exit;
		}
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( isset( $_GET['page'] ) && 'immo-wizard' === $_GET['page'] ) {
			// Assets des Shortcodes auch im Backend auf der Wizard-Seite laden
			Plugin::instance()->get_shortcodes()->enqueue_assets();
			// Media-Uploader für die Bildergalerie laden (verhindert JS-Fehler 'wp is not defined')
			wp_enqueue_media();
			// Metaboxes JS für die Wohneinheiten-Verwaltung laden
			wp_enqueue_script( 'immo-manager-metaboxes', IMMO_MANAGER_PLUGIN_URL . 'public/js/metaboxes.js', array( 'jquery' ), IMMO_MANAGER_VERSION, true );

			$post_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			$units   = $post_id ? \ImmoManager\Units::get_by_project( $post_id ) : array();

			wp_localize_script( 'immo-manager-metaboxes', 'immoManagerAdmin', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'units'   => $units,
				'i18n'    => array(
					'loading'       => __( 'Lade…', 'immo-manager' ),
					'district'      => __( '— Bezirk —', 'immo-manager' ),
					'addUnit'       => __( 'Wohneinheit hinzufügen', 'immo-manager' ),
					'editUnit'      => __( 'Wohneinheit bearbeiten', 'immo-manager' ),
					'confirmDelete' => __( 'Wohneinheit wirklich löschen?', 'immo-manager' ),
				),
			) );
		}
	}

	// =========================================================================
	// Shortcode
	// =========================================================================

	/**
	 * [immo_wizard id="X" allow_guests="0" redirect=""] rendern.
	 *
	 * @param array<string, mixed>|string $atts Shortcode-Attribute.
	 *
	 * @return string HTML.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts( array(
			'id'           => 0,
			'entity_type'  => 'property',
			'redirect'     => '',
			'allow_guests' => 0,
		), (array) $atts, 'immo_wizard' );

		$post_id      = absint( $atts['id'] );
		$entity_type  = sanitize_key( $atts['entity_type'] );
		$allow_guests = (bool) $atts['allow_guests'];

		if ( ! is_user_logged_in() && ! $allow_guests ) {
			return '<div class="immo-wizard-notice"><p>'
				. esc_html__( 'Bitte melde dich an, um eine Immobilie einzustellen.', 'immo-manager' )
				. '</p><a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="immo-btn immo-btn-primary">'
				. esc_html__( 'Anmelden', 'immo-manager' ) . '</a></div>';
		}

		$prefill = array();
		if ( $post_id && in_array( get_post_type( $post_id ), array( PostTypes::POST_TYPE_PROPERTY, PostTypes::POST_TYPE_PROJECT ), true ) ) {
			$prefill     = $this->load_post_data( $post_id );
			$post_type   = get_post_type( $post_id );
			$entity_type = ( $post_type === PostTypes::POST_TYPE_PROJECT ) ? 'project' : 'property';

			// Fallback für Standard-Makler-Daten, falls der Eintrag noch leer ist (z. B. bei Auto-Drafts).
			if ( empty( $prefill['_immo_contact_name'] ) && empty( $prefill['_immo_contact_email'] ) ) {
				$prefill['_immo_contact_name']  = Settings::get( 'agent_name', '' );
				$prefill['_immo_contact_email'] = Settings::get( 'agent_email', '' );
				$prefill['_immo_contact_phone'] = Settings::get( 'agent_phone', '' );
				$prefill['_immo_contact_image_id'] = (int) Settings::get( 'agent_image_id', 0 );
			}
		} else {
			// Standard-Makler-Daten aus den Einstellungen laden, wenn ein neuer Eintrag erstellt wird
			$prefill['_immo_contact_name']  = Settings::get( 'agent_name', '' );
			$prefill['_immo_contact_email'] = Settings::get( 'agent_email', '' );
			$prefill['_immo_contact_phone'] = Settings::get( 'agent_phone', '' );
			$prefill['_immo_contact_image_id'] = (int) Settings::get( 'agent_image_id', 0 );
		}

		// Im Frontend ebenfalls den Media Uploader laden, falls Bilder hochgeladen werden
		if ( is_user_logged_in() ) {
			wp_enqueue_media();
		}

		$projects = get_posts( array(
			'post_type'      => PostTypes::POST_TYPE_PROJECT,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		ob_start();
		$nonce      = wp_create_nonce( self::NONCE_ACTION );
		$media_nonce = is_user_logged_in() ? wp_create_nonce( 'media-form' ) : '';
		$rest_nonce = wp_create_nonce( 'wp_rest' );
		$ajax_url   = admin_url( 'admin-ajax.php' );
		$api_base   = rest_url( RestApi::NAMESPACE );
		$redirect   = esc_url( $atts['redirect'] );
		$states     = Regions::get_states();
		$features   = Features::get_grouped();
		$categories = Features::get_categories();
		$currency   = Settings::get( 'currency_symbol', '€' );
		$is_edit    = $post_id > 0;

		$tpl = IMMO_MANAGER_PLUGIN_DIR . 'templates/wizard/wizard-form.php';
		if ( is_readable( $tpl ) ) {
			include $tpl;
		}

		$html = ob_get_clean();

		return $html;
	}

	// =========================================================================
	// AJAX-Handler
	// =========================================================================

	/**
	 * Immobilie veröffentlichen (eingeloggte Nutzer).
	 *
	 * @return void
	 */
	public function ajax_save(): void {
		$this->verify_nonce();

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
		}

		$data    = $this->collect_post_data();
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$errors  = $this->validate( $data );

		if ( $errors ) {
			wp_send_json_error(
				array( 'message' => __( 'Bitte überprüfe deine Eingaben.', 'immo-manager' ), 'errors' => $errors ),
				422
			);
		}

		$entity = $data['entity_type'] ?? 'property';
		$data['post_type'] = ( 'project' === $entity ) ? PostTypes::POST_TYPE_PROJECT : PostTypes::POST_TYPE_PROPERTY;
		$result            = $this->save_post( $data, $post_id, 'publish' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		$edit_url = admin_url( 'admin.php?page=immo-wizard&id=' . $result );

		wp_send_json_success( array(
			'post_id'   => $result,
			'permalink' => get_permalink( $result ),
			'edit_url'  => $edit_url,
			'message'   => $post_id
				? __( 'Eintrag erfolgreich aktualisiert!', 'immo-manager' )
				: __( 'Eintrag erfolgreich veröffentlicht!', 'immo-manager' ),
		) );
	}

	/**
	 * Entwurf speichern (eingeloggte Nutzer).
	 *
	 * @return void
	 */
	public function ajax_autosave(): void {
		$this->verify_nonce();

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
		}

		$data    = $this->collect_post_data();
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$entity  = $data['entity_type'] ?? 'property';

		$data['post_type'] = ( 'project' === $entity ) ? PostTypes::POST_TYPE_PROJECT : PostTypes::POST_TYPE_PROPERTY;
		$result            = $this->save_post( $data, $post_id, 'draft' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		wp_send_json_success( array(
			'post_id' => $result,
			'message' => __( 'Entwurf gespeichert.', 'immo-manager' ),
		) );
	}

	/**
	 * Gast-Submit → pending (muss manuell freigegeben werden).
	 *
	 * @return void
	 */
	public function ajax_save_guest(): void {
		$this->verify_nonce();

		$data   = $this->collect_post_data();
		$errors = $this->validate( $data );

		if ( $errors ) {
			wp_send_json_error(
				array( 'message' => __( 'Bitte überprüfe deine Eingaben.', 'immo-manager' ), 'errors' => $errors ),
				422
			);
		}

		$entity = $data['entity_type'] ?? 'property';
		if ( 'unit' === $entity ) {
			$result = $this->save_unit( $data, 0 );
		} else {
			$data['post_type'] = ( 'project' === $entity ) ? PostTypes::POST_TYPE_PROJECT : PostTypes::POST_TYPE_PROPERTY;
			$result            = $this->save_post( $data, 0, 'pending' );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		wp_send_json_success( array(
			'post_id' => $result,
			'message' => __( 'Vielen Dank! Ihre Immobilie wurde eingereicht und wird nach Prüfung veröffentlicht.', 'immo-manager' ),
		) );
	}

	// =========================================================================
	// Kern: Speichern
	// =========================================================================

	/**
	 * Immobilien-Post anlegen oder aktualisieren.
	 *
	 * @param array<string, mixed> $data    Sanitisierte Daten.
	 * @param int                  $post_id Vorhandene ID (0 = neu).
	 * @param string               $status  WordPress-Post-Status.
	 *
	 * @return int|\WP_Error Post-ID oder Fehler.
	 */
	private function save_post( array $data, int $post_id, string $status ) {
		$title       = sanitize_text_field( $data['title'] ?? '' );
		$description = wp_kses_post( $data['description'] ?? '' );
		$post_type   = sanitize_key( $data['post_type'] ?? PostTypes::POST_TYPE_PROPERTY );

		if ( $post_id > 0 ) {
			$post_type = get_post_type( $post_id ) ?: $post_type;

			// Verhindern, dass eine bereits veröffentlichte Immobilie durch einen automatischen
			// Autosave wieder auf "Entwurf" (draft) zurückgesetzt wird.
			if ( 'draft' === $status ) {
				$current_status = get_post_status( $post_id );
				if ( 'publish' === $current_status ) {
					$status = 'publish';
				}
			}
		}

		if ( empty( $title ) ) {
			$parts = array_filter( array(
				$data['_immo_property_type'] ?? '',
				$data['_immo_city']          ?? '',
			) );
			$title = $parts ? implode( ' – ', $parts ) : ( $post_type === PostTypes::POST_TYPE_PROJECT ? __( 'Neues Bauprojekt', 'immo-manager' ) : __( 'Neue Immobilie', 'immo-manager' ) );
		}

		$post_data = array(
			'post_type'    => $post_type,
			'post_title'   => $title,
			'post_content' => $description,
			'post_status'  => $status,
			'post_author'  => get_current_user_id() ?: 1,
		);

		if ( $post_id > 0 ) {
			$post_data['ID'] = $post_id;
			$result          = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$post_id = (int) $result;

		// Meta-Felder speichern.
		if ( $post_type === PostTypes::POST_TYPE_PROJECT ) {
			$sanitized = MetaFields::sanitize_project_payload( $data );
			$fields    = MetaFields::project_fields();
		} else {
			$sanitized = MetaFields::sanitize_property_payload( $data );
			$fields    = MetaFields::property_fields();
		}

		foreach ( $fields as $key => $def ) {
			if ( array_key_exists( $key, $sanitized ) ) {
				update_post_meta( $post_id, $key, $sanitized[ $key ] );
			}
		}

		// Galerie.
		if ( isset( $data['_immo_gallery'] ) && is_array( $data['_immo_gallery'] ) ) {
			$gallery_ids = array_values( array_filter( array_map( 'absint', $data['_immo_gallery'] ) ) );
			update_post_meta( $post_id, '_immo_gallery', $gallery_ids );
			
			// Das erste Bild der Galerie immer als Beitragsbild (Featured Image) setzen.
			if ( ! empty( $gallery_ids ) ) {
				set_post_thumbnail( $post_id, $gallery_ids[0] );
			}
		}

		if ( ! empty( $data['featured_image_id'] ) ) {
			set_post_thumbnail( $post_id, absint( $data['featured_image_id'] ) );
		}

		return $post_id;
	}

	/**
	 * Wohneinheit anlegen oder aktualisieren (Custom Table).
	 *
	 * @param array<string, mixed> $data    Daten.
	 * @param int                  $unit_id Vorhandene ID (0 = neu).
	 *
	 * @return int|\WP_Error Unit-ID oder Fehler.
	 */
	private function save_unit( array $data, int $unit_id ) {
		global $wpdb;
		$table = Database::units_table();

		$gallery_ids = array();
		if ( isset( $data['_immo_gallery'] ) && is_array( $data['_immo_gallery'] ) ) {
			$gallery_ids = array_values( array_filter( array_map( 'absint', $data['_immo_gallery'] ) ) );
		}

		$unit_data = array(
			'project_id'     => absint( $data['project_id'] ?? 0 ),
			'unit_number'    => sanitize_text_field( $data['title'] ?? ( $data['unit_number'] ?? '' ) ),
			'floor'          => (int) ( $data['_immo_floor'] ?? 0 ),
			'area'           => (float) ( $data['_immo_area'] ?? 0 ),
			'rooms'          => (int) ( $data['_immo_rooms'] ?? 0 ),
			'price'          => (float) ( $data['_immo_price'] ?? 0 ),
			'rent'           => (float) ( $data['_immo_rent'] ?? 0 ),
			'status'         => sanitize_key( $data['_immo_status'] ?? 'available' ),
			'description'    => wp_kses_post( $data['description'] ?? '' ),
			'gallery_images' => implode( ',', $gallery_ids ),
			'updated_at'     => current_time( 'mysql' ),
		);

		if ( $unit_id > 0 ) {
			$wpdb->update( $table, $unit_data, array( 'id' => $unit_id ) );
			return $unit_id;
		} else {
			$unit_data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $unit_data );
			return $wpdb->insert_id;
		}
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * POST-Daten sammeln (Whitelist-basiert).
	 *
	 * @return array<string, mixed>
	 */
	private function collect_post_data(): array {
		$data         = array();
		$allowed_keys = array_merge(
			array_keys( MetaFields::property_fields() ),
			array_keys( MetaFields::project_fields() ),
			array( 'title', 'description', 'featured_image_id', 'entity_type', 'project_id', 'unit_number' )
		);

		foreach ( $allowed_keys as $key ) {
			$short_key = ltrim( $key, '_' );
			if ( isset( $_POST[ $key ] ) ) {
				$data[ $key ] = wp_unslash( $_POST[ $key ] );
			} elseif ( isset( $_POST[ $short_key ] ) ) {
				$data[ $key ] = wp_unslash( $_POST[ $short_key ] );
			}
		}

		if ( isset( $_POST['entity_type'] ) ) {
			$data['entity_type'] = sanitize_key( wp_unslash( $_POST['entity_type'] ) );
		}

		// Galerie.
		if ( isset( $_POST['gallery_ids'] ) ) {
			$raw               = wp_unslash( $_POST['gallery_ids'] );
			$data['_immo_gallery'] = is_array( $raw )
				? array_map( 'absint', $raw )
				: array_filter( array_map( 'absint', explode( ',', (string) $raw ) ) );
		}

		// Dokumente / Exposé
		if ( isset( $_POST['document_ids'] ) ) {
			$raw = wp_unslash( $_POST['document_ids'] );
			$data['_immo_documents'] = is_array( $raw ) ? array_map( 'absint', $raw ) : array_filter( array_map( 'absint', explode( ',', (string) $raw ) ) );
		}

		// Features als Array.
		if ( isset( $_POST['_immo_features'] ) && is_array( $_POST['_immo_features'] ) ) {
			$data['_immo_features'] = array_map( 'sanitize_key', wp_unslash( $_POST['_immo_features'] ) );
		} elseif ( ! isset( $data['_immo_features'] ) ) {
			$data['_immo_features'] = array();
		}

		return $data;
	}

	/**
	 * Server-seitige Validierung der Pflichtfelder.
	 *
	 * @param array<string, mixed> $data Daten.
	 *
	 * @return array<string, string> Fehler (Key → Meldung).
	 */
	private function validate( array $data ): array {
		$errors = array();
		$entity = $data['entity_type'] ?? 'property';

		if ( empty( $data['_immo_property_type'] ) && empty( $data['title'] ) ) {
			$errors['title'] = __( 'Bitte gib einen Projektnamen oder Immobilientyp an.', 'immo-manager' );
		}

		if ( 'property' === $entity ) {
			$mode = $data['_immo_mode'] ?? '';
			if ( ! in_array( $mode, array( 'sale', 'rent', 'both' ), true ) ) {
				$errors['_immo_mode'] = __( 'Bitte wähle einen Angebotsmodus (Verkauf/Miete).', 'immo-manager' );
			}
			if ( in_array( $mode, array( 'sale', 'both' ), true ) && empty( $data['_immo_price'] ) ) {
				$errors['_immo_price'] = __( 'Kaufpreis ist ein Pflichtfeld.', 'immo-manager' );
			}
			if ( in_array( $mode, array( 'rent', 'both' ), true ) && empty( $data['_immo_rent'] ) ) {
				$errors['_immo_rent'] = __( 'Mietpreis ist ein Pflichtfeld.', 'immo-manager' );
			}
		}

		return $errors;
	}

	/**
	 * Nonce aus $_POST prüfen.
	 *
	 * @return void
	 */
	private function verify_nonce(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security-Token ungültig. Bitte lade die Seite neu.', 'immo-manager' ) ),
				403
			);
		}
	}

	/**
	 * Bestehende Immobilien-Daten für den Bearbeitungs-Modus laden.
	 *
	 * @param int $post_id Post-ID.
	 *
	 * @return array<string, mixed>
	 */
	private function load_post_data( int $post_id ): array {
		$post_type = get_post_type( $post_id );
		$data = array(
			'title'       => get_the_title( $post_id ),
			'description' => get_post_field( 'post_content', $post_id ),
		);

		$fields = $post_type === PostTypes::POST_TYPE_PROJECT ? MetaFields::project_fields() : MetaFields::property_fields();

		foreach ( array_keys( $fields ) as $key ) {
			$val          = get_post_meta( $post_id, $key, true );
			$data[ $key ] = '' !== $val ? $val : null;
		}

		$gallery = get_post_meta( $post_id, '_immo_gallery', true );
		$gallery = is_array( $gallery ) ? $gallery : array();
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( $thumb_id && ! in_array( $thumb_id, $gallery ) ) {
			array_unshift( $gallery, $thumb_id );
		}
		$data['_immo_gallery'] = $gallery;
		$data['_immo_documents'] = get_post_meta( $post_id, '_immo_documents', true ) ?: array();

		return $data;
	}

	/**
	 * Bestehende Wohneinheiten-Daten laden.
	 *
	 * @param int $unit_id Unit-ID.
	 *
	 * @return array<string, mixed>
	 */
	private function load_unit_data( int $unit_id ): array {
		global $wpdb;
		$table = Database::units_table();
		$unit  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $unit_id ), ARRAY_A ); // phpcs:ignore

		if ( ! $unit ) { return array(); }

		return array(
			'title'         => $unit['unit_number'],
			'description'   => $unit['description'],
			'project_id'    => $unit['project_id'],
			'_immo_floor'   => $unit['floor'],
			'_immo_area'    => $unit['area'],
			'_immo_rooms'   => $unit['rooms'],
			'_immo_price'   => $unit['price'],
			'_immo_rent'    => $unit['rent'],
			'_immo_status'  => $unit['status'],
			'_immo_gallery' => $unit['gallery_images'] ? explode( ',', $unit['gallery_images'] ) : array(),
		);
	}
}
