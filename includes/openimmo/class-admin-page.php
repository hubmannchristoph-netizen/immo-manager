<?php
/**
 * OpenImmo Admin-Page – Settings-UI unter „Immo Manager".
 *
 * @package ImmoManager\OpenImmo
 */

namespace ImmoManager\OpenImmo;

defined( 'ABSPATH' ) || exit;

/**
 * Submenü-Seite mit Portal-Credentials und Cron-Konfiguration.
 */
class AdminPage {

	public const PAGE_SLUG    = 'immo-manager-openimmo';
	public const NONCE_ACTION = 'immo_manager_openimmo_save';
	public const SUBMIT_FIELD = 'immo_manager_openimmo_submit';

	/**
	 * Konstruktor – Hooks registrieren.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
	}

	/**
	 * Submenü unter „Immo Manager" registrieren.
	 *
	 * @return void
	 */
	public function register_submenu(): void {
		add_submenu_page(
			'immo-manager',
			__( 'OpenImmo', 'immo-manager' ),
			__( 'OpenImmo', 'immo-manager' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Form-Submit verarbeiten (admin_init).
	 *
	 * @return void
	 */
	public function maybe_save(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST[ self::SUBMIT_FIELD ] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( self::NONCE_ACTION );

		$post = wp_unslash( $_POST );

		$data = array(
			'enabled'   => ! empty( $post['enabled'] ),
			'cron_time' => sanitize_text_field( $post['cron_time'] ?? '03:00' ),
			'portals'   => array(),
		);

		foreach ( array( 'willhaben', 'immoscout24_at' ) as $key ) {
			$portal = isset( $post['portals'][ $key ] ) && is_array( $post['portals'][ $key ] )
				? $post['portals'][ $key ]
				: array();

			$data['portals'][ $key ] = array(
				'enabled'       => ! empty( $portal['enabled'] ),
				'sftp_host'     => sanitize_text_field( $portal['sftp_host'] ?? '' ),
				'sftp_port'     => max( 1, min( 65535, (int) ( $portal['sftp_port'] ?? 22 ) ) ),
				'sftp_user'     => sanitize_text_field( $portal['sftp_user'] ?? '' ),
				'sftp_password' => (string) ( $portal['sftp_password'] ?? '' ),
				'remote_path'   => sanitize_text_field( $portal['remote_path'] ?? '/' ),
				'last_sync'     => '',
			);
		}

		Settings::save( $data );

		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-success is-dismissible"><p>'
					. esc_html__( 'OpenImmo-Einstellungen gespeichert.', 'immo-manager' )
					. '</p></div>';
			}
		);
	}

	/**
	 * Settings-Seite rendern.
	 *
	 * @return void
	 */
	public function render(): void {
		$settings = Settings::get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Immo Manager – OpenImmo', 'immo-manager' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Konfiguration für die OpenImmo-Schnittstelle (willhaben.at, ImmobilienScout24.at).', 'immo-manager' ); ?>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::SUBMIT_FIELD ); ?>" value="1">

				<h2><?php esc_html_e( 'Allgemein', 'immo-manager' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="enabled"><?php esc_html_e( 'Schnittstelle aktiv', 'immo-manager' ); ?></label></th>
						<td><input type="checkbox" id="enabled" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>></td>
					</tr>
					<tr>
						<th scope="row"><label for="cron_time"><?php esc_html_e( 'Tägliche Sync-Zeit (HH:MM)', 'immo-manager' ); ?></label></th>
						<td><input type="time" id="cron_time" name="cron_time" value="<?php echo esc_attr( $settings['cron_time'] ); ?>"></td>
					</tr>
				</table>

				<?php foreach ( $settings['portals'] as $key => $portal ) : ?>
					<h2><?php echo esc_html( self::portal_label( $key ) ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Aktiv', 'immo-manager' ); ?></th>
							<td>
								<label>
									<input type="checkbox"
										name="portals[<?php echo esc_attr( $key ); ?>][enabled]"
										value="1"
										<?php checked( $portal['enabled'] ); ?>>
									<?php esc_html_e( 'Portal aktivieren', 'immo-manager' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'SFTP-Host', 'immo-manager' ); ?></th>
							<td>
								<input type="text" class="regular-text"
									name="portals[<?php echo esc_attr( $key ); ?>][sftp_host]"
									value="<?php echo esc_attr( $portal['sftp_host'] ); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Port', 'immo-manager' ); ?></th>
							<td>
								<input type="number" min="1" max="65535"
									name="portals[<?php echo esc_attr( $key ); ?>][sftp_port]"
									value="<?php echo esc_attr( (string) $portal['sftp_port'] ); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Benutzer', 'immo-manager' ); ?></th>
							<td>
								<input type="text" class="regular-text"
									name="portals[<?php echo esc_attr( $key ); ?>][sftp_user]"
									value="<?php echo esc_attr( $portal['sftp_user'] ); ?>"
									autocomplete="off">
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Passwort', 'immo-manager' ); ?></th>
							<td>
								<input type="password" class="regular-text"
									name="portals[<?php echo esc_attr( $key ); ?>][sftp_password]"
									value="<?php echo esc_attr( $portal['sftp_password'] ); ?>"
									autocomplete="new-password">
								<p class="description">
									<?php esc_html_e( 'Wird verschlüsselt in der Datenbank gespeichert.', 'immo-manager' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Remote-Verzeichnis', 'immo-manager' ); ?></th>
							<td>
								<input type="text" class="regular-text"
									name="portals[<?php echo esc_attr( $key ); ?>][remote_path]"
									value="<?php echo esc_attr( $portal['remote_path'] ); ?>">
							</td>
						</tr>
					</table>
				<?php endforeach; ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Übersetzungstabelle Portal-Key → Anzeige-Name.
	 *
	 * @param string $key Portal-Key.
	 * @return string
	 */
	private static function portal_label( string $key ): string {
		$map = array(
			'willhaben'      => 'willhaben.at',
			'immoscout24_at' => 'ImmobilienScout24.at',
		);
		return $map[ $key ] ?? $key;
	}
}
