<?php
/**
 * Bauprojekte-Paket: globaler Schalter + Freischaltung pro Rolle / Benutzer.
 *
 * Das Plugin kann als reine Immobilienverwaltung laufen. Das Paket „Bauprojekte
 * mit Wohneinheiten" wird
 *   1. global über die Einstellung `enable_projects` aktiviert (Frontend + Backend),
 *   2. pro Benutzerrolle über `projects_roles` freigeschaltet (Default: alle Rollen),
 *   3. pro Benutzer im Benutzerprofil übersteuert (freigeschaltet / gesperrt).
 *
 * Technisch läuft der Bauprojekt-Post-Type über eigene Capabilities
 * (`edit_immo_projects` usw.), die hier dynamisch aus den normalen Post-Rechten
 * des Benutzers abgeleitet werden – oder eben nicht, wenn das Paket für ihn nicht
 * freigeschaltet ist. Dadurch greift die Sperre überall: Admin-Menü, Edit-Screens,
 * Admin-Bar, AJAX-Endpunkte und Wizard.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProjectsAccess
 */
class ProjectsAccess {

	/**
	 * Sentinel in `projects_roles`: alle Rollen freigeschaltet.
	 */
	public const ROLES_ALL = '*';

	/**
	 * User-Meta-Key für die Übersteuerung pro Benutzer ('' = nach Rolle, '1' = frei, '0' = gesperrt).
	 */
	public const USER_META = 'immo_projects_access';

	/**
	 * Primitive Capabilities des Bauprojekt-Post-Types → entsprechende Standard-Post-Caps.
	 *
	 * @var array<string, string>
	 */
	public const CAP_MAP = array(
		'edit_immo_projects'             => 'edit_posts',
		'edit_others_immo_projects'      => 'edit_others_posts',
		'edit_private_immo_projects'     => 'edit_private_posts',
		'edit_published_immo_projects'   => 'edit_published_posts',
		'publish_immo_projects'          => 'publish_posts',
		'read_private_immo_projects'     => 'read_private_posts',
		'delete_immo_projects'           => 'delete_posts',
		'delete_others_immo_projects'    => 'delete_others_posts',
		'delete_private_immo_projects'   => 'delete_private_posts',
		'delete_published_immo_projects' => 'delete_published_posts',
	);

	/**
	 * Konstruktor – Hooks registrieren.
	 */
	public function __construct() {
		add_filter( 'user_has_cap', array( $this, 'filter_user_caps' ), 10, 4 );

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'guard_admin_screens' ) );
			add_action( 'admin_notices', array( $this, 'render_disabled_notice' ) );
			add_action( 'show_user_profile', array( $this, 'render_user_field' ) );
			add_action( 'edit_user_profile', array( $this, 'render_user_field' ) );
			add_action( 'personal_options_update', array( $this, 'save_user_field' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_user_field' ) );
		}
	}

	/**
	 * Ist das Bauprojekte-Paket global aktiviert?
	 *
	 * @return bool
	 */
	public static function module_enabled(): bool {
		return (bool) Settings::get( 'enable_projects', 1 );
	}

	/**
	 * Rollen, für die das Paket freigeschaltet ist (oder [ROLES_ALL]).
	 *
	 * @return array<int, string>
	 */
	public static function allowed_roles(): array {
		$roles = Settings::get( 'projects_roles', array( self::ROLES_ALL ) );
		return is_array( $roles ) ? array_values( array_map( 'strval', $roles ) ) : array( self::ROLES_ALL );
	}

	/**
	 * Darf dieser Benutzer Bauprojekte verwalten?
	 *
	 * Reihenfolge: Modul aus → nein. Administrator → ja. Benutzer-Übersteuerung →
	 * deren Wert. Sonst: Rolle in der Freischaltungsliste.
	 *
	 * @param int|\WP_User|null $user Benutzer (Default: aktueller Benutzer).
	 *
	 * @return bool
	 */
	public static function user_has_access( $user = null ): bool {
		if ( ! self::module_enabled() ) {
			return false;
		}

		if ( ! ( $user instanceof \WP_User ) ) {
			$user_id = $user ? (int) $user : get_current_user_id();
			$user    = $user_id ? get_userdata( $user_id ) : null;
		}
		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		$roles = (array) $user->roles;

		// Administratoren verwalten die Einstellung selbst und sind immer freigeschaltet.
		if ( in_array( 'administrator', $roles, true ) || is_super_admin( $user->ID ) ) {
			return true;
		}

		$override = (string) get_user_meta( $user->ID, self::USER_META, true );
		if ( '1' === $override ) {
			return true;
		}
		if ( '0' === $override ) {
			return false;
		}

		$allowed = self::allowed_roles();
		if ( in_array( self::ROLES_ALL, $allowed, true ) ) {
			return true;
		}

		/**
		 * Letzte Instanz: erlaubt Erweiterungen (z. B. Lizenz-/Abo-Plugins), die Freischaltung zu steuern.
		 *
		 * @param bool     $has_access Ergebnis der Rollenprüfung.
		 * @param \WP_User $user       Benutzer.
		 */
		return (bool) apply_filters( 'immo_manager_projects_user_has_access', (bool) array_intersect( $roles, $allowed ), $user );
	}

	/**
	 * Bauprojekt-Capabilities dynamisch aus den Post-Rechten ableiten bzw. verweigern.
	 *
	 * @param array<string, bool> $allcaps Alle Caps des Benutzers.
	 * @param array<int, string>  $caps    Benötigte primitive Caps.
	 * @param array<int, mixed>   $args    Argumente (Cap, User-ID, Objekt-ID …).
	 * @param \WP_User            $user    Benutzer.
	 *
	 * @return array<string, bool>
	 */
	public function filter_user_caps( array $allcaps, array $caps, array $args, $user ): array {
		$needed = array_intersect( $caps, array_keys( self::CAP_MAP ) );
		if ( empty( $needed ) || ! ( $user instanceof \WP_User ) ) {
			return $allcaps;
		}

		$has_access = self::user_has_access( $user );
		foreach ( $needed as $cap ) {
			if ( $has_access ) {
				$allcaps[ $cap ] = ! empty( $allcaps[ self::CAP_MAP[ $cap ] ] );
			} else {
				unset( $allcaps[ $cap ] );
			}
		}

		return $allcaps;
	}

	/**
	 * Admin-Screens des Bauprojekt-Pakets absichern.
	 *
	 * - Paket global deaktiviert → freundliche Weiterleitung zu den Einstellungen mit Hinweis
	 *   (statt WordPress' nacktem wp_die bei show_ui = false).
	 * - Paket aktiv, aber für den Benutzer nicht freigeschaltet → 403.
	 *
	 * Läuft auf admin_init, also VOR dem Wizard-Redirect (current_screen) und vor
	 * dem Rendern der Seiten.
	 *
	 * @return void
	 */
	public function guard_admin_screens(): void {
		if ( wp_doing_ajax() || ! $this->is_project_admin_request() ) {
			return;
		}

		if ( ! self::module_enabled() ) {
			wp_safe_redirect( add_query_arg( 'immo_projects_disabled', '1', admin_url( 'admin.php?page=' . Settings::MENU_SLUG ) ) );
			exit;
		}

		if ( ! self::user_has_access() ) {
			wp_die(
				esc_html__( 'Das Bauprojekte-Paket ist für dein Benutzerkonto nicht freigeschaltet. Du kannst ausschließlich Immobilien verwalten. Bitte wende dich an einen Administrator (Immo Manager → Einstellungen → Module bzw. Benutzerprofil).', 'immo-manager' ),
				esc_html__( 'Kein Zugriff', 'immo-manager' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Zielt der aktuelle Admin-Request auf Bauprojekte (Liste, Neu, Bearbeiten, Wizard, Wohneinheiten)?
	 *
	 * @return bool
	 */
	private function is_project_admin_request(): bool {
		global $pagenow;

		$get  = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = isset( $get['post_type'] ) ? sanitize_key( (string) $get['post_type'] ) : '';
		$page = isset( $get['page'] ) ? sanitize_key( (string) $get['page'] ) : '';

		if ( in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true ) && PostTypes::POST_TYPE_PROJECT === $type ) {
			return true;
		}

		if ( 'post.php' === $pagenow && ! empty( $get['post'] ) ) {
			$action = isset( $get['action'] ) ? sanitize_key( (string) $get['action'] ) : '';
			// Papierkorb-/Löschaktionen laufen über die normalen Caps weiter.
			if ( in_array( $action, array( 'trash', 'untrash', 'delete' ), true ) ) {
				return false;
			}
			return PostTypes::POST_TYPE_PROJECT === get_post_type( absint( $get['post'] ) );
		}

		if ( 'admin.php' === $pagenow ) {
			if ( 'immo-units' === $page ) {
				return true;
			}
			if ( 'immo-wizard' === $page ) {
				$entity = isset( $get['entity_type'] ) ? sanitize_key( (string) $get['entity_type'] ) : '';
				if ( 'project' === $entity || PostTypes::POST_TYPE_PROJECT === $type ) {
					return true;
				}
				if ( ! empty( $get['id'] ) && PostTypes::POST_TYPE_PROJECT === get_post_type( absint( $get['id'] ) ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Hinweis nach Weiterleitung von einem Bauprojekt-Screen bei deaktiviertem Paket.
	 *
	 * @return void
	 */
	public function render_disabled_notice(): void {
		if ( empty( $_GET['immo_projects_disabled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		echo '<div class="notice notice-info is-dismissible"><p><strong>' . esc_html__( 'Bauprojekte-Paket ist deaktiviert.', 'immo-manager' ) . '</strong> '
			. esc_html__( 'Das Plugin läuft als reine Immobilienverwaltung. Aktiviere das Paket unter Module → „Bauprojekte-Paket aktivieren", um Bauprojekte und Wohneinheiten wieder zu verwalten. Vorhandene Bauprojekt-Daten sind unverändert erhalten.', 'immo-manager' )
			. '</p></div>';
	}

	/**
	 * Alle Rollen außer Administrator (für die Einstellungs-Checkboxen).
	 *
	 * @return array<string, string> role-key => Anzeigename.
	 */
	public static function assignable_roles(): array {
		$out = array();
		foreach ( wp_roles()->roles as $key => $role ) {
			if ( 'administrator' === $key ) {
				continue;
			}
			$out[ $key ] = translate_user_role( $role['name'] );
		}
		return $out;
	}

	/**
	 * Benutzerprofil: Freischaltung pro Benutzer.
	 *
	 * Nur für Benutzer sichtbar, die Rollen vergeben dürfen (promote_users).
	 *
	 * @param \WP_User $user Bearbeiteter Benutzer.
	 *
	 * @return void
	 */
	public function render_user_field( \WP_User $user ): void {
		if ( ! current_user_can( 'promote_users' ) || ! self::module_enabled() ) {
			return;
		}
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return; // Administratoren sind immer freigeschaltet.
		}

		$value    = (string) get_user_meta( $user->ID, self::USER_META, true );
		$by_role  = (bool) array_intersect( (array) $user->roles, self::allowed_roles() ) || in_array( self::ROLES_ALL, self::allowed_roles(), true );
		$role_txt = $by_role ? __( 'laut Rolle freigeschaltet', 'immo-manager' ) : __( 'laut Rolle gesperrt', 'immo-manager' );
		?>
		<h2><?php esc_html_e( 'Immo Manager – Bauprojekte-Paket', 'immo-manager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="immo_projects_access"><?php esc_html_e( 'Bauprojekte verwalten', 'immo-manager' ); ?></label></th>
				<td>
					<select name="immo_projects_access" id="immo_projects_access">
						<option value="" <?php selected( $value, '' ); ?>><?php printf( esc_html__( 'Standard – nach Rolle (%s)', 'immo-manager' ), esc_html( $role_txt ) ); ?></option>
						<option value="1" <?php selected( $value, '1' ); ?>><?php esc_html_e( 'Freigeschaltet', 'immo-manager' ); ?></option>
						<option value="0" <?php selected( $value, '0' ); ?>><?php esc_html_e( 'Gesperrt', 'immo-manager' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Steuert, ob dieser Benutzer Bauprojekte und deren Wohneinheiten sehen und bearbeiten darf. Ohne Freischaltung verwaltet er ausschließlich Immobilien. Die Rollen-Freischaltung findest du unter Immo Manager → Einstellungen → Module.', 'immo-manager' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Benutzerprofil speichern.
	 *
	 * WordPress hat den Nonce (update-user_{id}) vor diesem Hook bereits geprüft.
	 *
	 * @param int $user_id Benutzer-ID.
	 *
	 * @return void
	 */
	public function save_user_field( int $user_id ): void {
		if ( ! current_user_can( 'promote_users' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( ! isset( $_POST['immo_projects_access'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$value = sanitize_key( wp_unslash( $_POST['immo_projects_access'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $value ) {
			delete_user_meta( $user_id, self::USER_META );
		} elseif ( in_array( $value, array( '0', '1' ), true ) ) {
			update_user_meta( $user_id, self::USER_META, $value );
		}
	}
}
