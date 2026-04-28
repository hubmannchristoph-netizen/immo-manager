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
		add_action( 'wp_ajax_immo_manager_openimmo_export_now', array( $this, 'ajax_export_now' ) );
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
				// Anbieter-Block.
				'anbieternr'    => sanitize_text_field( $portal['anbieternr']    ?? '' ),
				'firma'         => sanitize_text_field( $portal['firma']         ?? '' ),
				'openimmo_anid' => sanitize_text_field( $portal['openimmo_anid'] ?? '' ),
				'lizenzkennung' => sanitize_text_field( $portal['lizenzkennung'] ?? '' ),
				'impressum'     => wp_kses_post(        $portal['impressum']     ?? '' ),
				'email_direkt'  => sanitize_email(      $portal['email_direkt']  ?? '' ),
				'tel_zentrale'  => sanitize_text_field( $portal['tel_zentrale']  ?? '' ),
				'regi_id'       => sanitize_text_field( $portal['regi_id']       ?? '' ),
				'techn_email'   => sanitize_email(      $portal['techn_email']   ?? '' ),
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
	 * AJAX-Handler: Sofort-Export für ein Portal auslösen.
	 *
	 * @return void
	 */
	public function ajax_export_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'immo-manager' ) ), 403 );
		}
		check_ajax_referer( 'immo_manager_openimmo_export_now', 'nonce' );

		$portal_key = isset( $_POST['portal'] ) ? sanitize_key( wp_unslash( $_POST['portal'] ) ) : '';
		if ( ! in_array( $portal_key, array( 'willhaben', 'immoscout24_at' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unbekanntes Portal.', 'immo-manager' ) ), 400 );
		}

		$service = \ImmoManager\Plugin::instance()->get_openimmo_export_service();
		$result  = $service->run( $portal_key );

		if ( in_array( $result['status'], array( 'success', 'partial' ), true ) ) {
			$size = file_exists( $result['zip_path'] ) ? size_format( filesize( $result['zip_path'] ), 1 ) : '';
			wp_send_json_success( array(
				'message' => sprintf(
					__( 'Export erstellt: %1$s (%2$d Listings, %3$s)', 'immo-manager' ),
					basename( $result['zip_path'] ),
					$result['count'],
					$size
				),
				'status'  => $result['status'],
				'summary' => $result['summary'],
			) );
		}
		wp_send_json_error( array( 'message' => $result['summary'], 'status' => $result['status'] ) );
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

					<h3><?php echo esc_html( self::portal_label( $key ) . ' – ' . __( 'Anbieter-Daten', 'immo-manager' ) ); ?></h3>
					<table class="form-table">
						<tr>
							<th><label><?php esc_html_e( 'Anbieter-Nr.', 'immo-manager' ); ?></label></th>
							<td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][anbieternr]" value="<?php echo esc_attr( $portal['anbieternr'] ?? '' ); ?>"></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Firma', 'immo-manager' ); ?></label></th>
							<td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][firma]" value="<?php echo esc_attr( $portal['firma'] ?? '' ); ?>"></td>
						</tr>
						<tr>
							<th><label>OpenImmo-ANID</label></th>
							<td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][openimmo_anid]" value="<?php echo esc_attr( $portal['openimmo_anid'] ?? '' ); ?>"></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Lizenz-Kennung', 'immo-manager' ); ?></label></th>
							<td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][lizenzkennung]" value="<?php echo esc_attr( $portal['lizenzkennung'] ?? '' ); ?>"></td>
						</tr>
						<tr>
							<th><label>regi_id</label></th>
							<td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][regi_id]" value="<?php echo esc_attr( $portal['regi_id'] ?? '' ); ?>"></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Technische E-Mail', 'immo-manager' ); ?></label></th>
							<td><input type="email" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][techn_email]" value="<?php echo esc_attr( $portal['techn_email'] ?? '' ); ?>"></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Anbieter-E-Mail', 'immo-manager' ); ?></label></th>
							<td><input type="email" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][email_direkt]" value="<?php echo esc_attr( $portal['email_direkt'] ?? '' ); ?>"></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Anbieter-Telefon', 'immo-manager' ); ?></label></th>
							<td><input type="text" class="regular-text" name="portals[<?php echo esc_attr( $key ); ?>][tel_zentrale]" value="<?php echo esc_attr( $portal['tel_zentrale'] ?? '' ); ?>"></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Impressum', 'immo-manager' ); ?></label></th>
							<td><textarea class="large-text" rows="3" name="portals[<?php echo esc_attr( $key ); ?>][impressum]"><?php echo esc_textarea( $portal['impressum'] ?? '' ); ?></textarea></td>
						</tr>
					</table>

					<p>
						<button type="button"
								class="button button-secondary immo-openimmo-export-now"
								data-portal="<?php echo esc_attr( $key ); ?>"
								data-nonce="<?php echo esc_attr( wp_create_nonce( 'immo_manager_openimmo_export_now' ) ); ?>">
							<?php esc_html_e( 'Jetzt exportieren (lokal, ohne Upload)', 'immo-manager' ); ?>
						</button>
						<span class="immo-openimmo-export-status" style="margin-left:10px;"></span>
					</p>

				<?php endforeach; ?>

				<?php submit_button(); ?>
			</form>

			<script>
			(function($){
				$(document).on('click', '.immo-openimmo-export-now', function(e){
					e.preventDefault();
					var $btn  = $(this);
					var $stat = $btn.siblings('.immo-openimmo-export-status');
					$btn.prop('disabled', true);
					$stat.text('<?php echo esc_js( __( 'Exportiere…', 'immo-manager' ) ); ?>');
					$.post(ajaxurl, {
						action:  'immo_manager_openimmo_export_now',
						portal:  $btn.data('portal'),
						nonce:   $btn.data('nonce')
					}).done(function(resp){
						if (resp.success) {
							$stat.css('color', 'green').text(resp.data.message);
						} else {
							$stat.css('color', 'red').text(resp.data && resp.data.message ? resp.data.message : 'Fehler');
						}
					}).fail(function(){
						$stat.css('color', 'red').text('<?php echo esc_js( __( 'Netzwerkfehler', 'immo-manager' ) ); ?>');
					}).always(function(){
						$btn.prop('disabled', false);
					});
				});
			})(jQuery);
			</script>
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
