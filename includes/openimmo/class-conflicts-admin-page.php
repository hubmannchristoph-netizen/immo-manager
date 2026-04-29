<?php
/**
 * Admin-Page für die OpenImmo-Konflikt-Queue.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo;

use ImmoManager\Database;

defined( 'ABSPATH' ) || exit;

class ConflictsAdminPage {

	public const PAGE_SLUG = 'immo-manager-openimmo-conflicts';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 25 );
		add_action( 'admin_init', array( $this, 'maybe_handle_submit' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_bulk_submit' ) );
	}

	public function register_submenu(): void {
		add_submenu_page(
			'immo-manager',
			__( 'OpenImmo-Konflikte', 'immo-manager' ),
			$this->menu_label_with_badge(),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'immo-manager' ) );
		}

		$conflict_id = isset( $_GET['conflict_id'] ) ? (int) $_GET['conflict_id'] : 0;
		if ( $conflict_id > 0 ) {
			$this->render_detail( $conflict_id );
			return;
		}
		$this->render_list();
	}

	/**
	 * Form-Handler für Single-Approve/Reject (POST oder GET-Link).
	 */
	public function maybe_handle_submit(): void {
		$is_post = isset( $_POST['immo_openimmo_resolve_action'] );
		$is_get  = isset( $_GET['page'], $_GET['conflict_id'], $_GET['resolution'] )
			&& self::PAGE_SLUG === $_GET['page'];

		if ( ! $is_post && ! $is_get ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$conflict_id = (int) ( $is_post ? ( $_POST['conflict_id'] ?? 0 ) : ( $_GET['conflict_id'] ?? 0 ) );
		$resolution  = sanitize_key( wp_unslash( (string) ( $is_post ? ( $_POST['resolution'] ?? '' ) : ( $_GET['resolution'] ?? '' ) ) ) );

		if ( $conflict_id <= 0 || ! in_array( $resolution, array( 'keep_local', 'take_import' ), true ) ) {
			return;
		}

		check_admin_referer( 'immo_openimmo_resolve_' . $conflict_id );

		$resolver = new ConflictsResolver();
		$ok       = $resolver->resolve( $conflict_id, $resolution );

		$message = $ok
			? ( 'take_import' === $resolution
				? __( 'Eingehende Daten wurden übernommen.', 'immo-manager' )
				: __( 'Bestand wurde behalten.', 'immo-manager' ) )
			: __( 'Konflikt konnte nicht aufgelöst werden (möglicherweise bereits bearbeitet oder Property nicht vorhanden).', 'immo-manager' );

		$redirect = add_query_arg(
			array(
				'page'             => self::PAGE_SLUG,
				'immo_resolve_msg' => rawurlencode( $message ),
				'immo_resolve_ok'  => $ok ? '1' : '0',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Form-Handler für Bulk-Reject.
	 */
	public function maybe_handle_bulk_submit(): void {
		if ( empty( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( 'bulk_reject' !== $action ) {
			$action_2 = isset( $_REQUEST['action2'] ) ? sanitize_key( wp_unslash( $_REQUEST['action2'] ) ) : '';
			if ( 'bulk_reject' !== $action_2 ) {
				return;
			}
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'bulk-conflicts' );

		$ids = isset( $_REQUEST['ids'] ) && is_array( $_REQUEST['ids'] )
			? array_map( 'intval', wp_unslash( $_REQUEST['ids'] ) )
			: array();
		$ids = array_filter( $ids, static function ( $id ) {
			return $id > 0;
		} );

		if ( empty( $ids ) ) {
			return;
		}

		$resolver = new ConflictsResolver();
		$count    = 0;
		foreach ( $ids as $id ) {
			if ( $resolver->resolve( (int) $id, 'keep_local' ) ) {
				++$count;
			}
		}

		$message  = sprintf(
			/* translators: %d: anzahl */
			__( '%d Konflikte abgelehnt.', 'immo-manager' ),
			$count
		);
		$redirect = add_query_arg(
			array(
				'page'             => self::PAGE_SLUG,
				'immo_resolve_msg' => rawurlencode( $message ),
				'immo_resolve_ok'  => '1',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	private function render_list(): void {
		$table = new ConflictsListTable();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'OpenImmo-Konflikte', 'immo-manager' ); ?></h1>
			<?php $this->render_resolve_notice(); ?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	private function render_detail( int $conflict_id ): void {
		global $wpdb;
		$tbl = Database::conflicts_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$conflict = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE id = %d", $conflict_id ), ARRAY_A );

		if ( ! is_array( $conflict ) ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'OpenImmo-Konflikt', 'immo-manager' ); ?></h1>
				<div class="notice notice-error"><p><?php esc_html_e( 'Konflikt nicht gefunden.', 'immo-manager' ); ?></p></div>
				<p><a href="<?php echo esc_url( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) ); ?>">← <?php esc_html_e( 'Zurück zur Liste', 'immo-manager' ); ?></a></p>
			</div>
			<?php
			return;
		}

		$post_id = (int) $conflict['post_id'];
		$post    = get_post( $post_id );
		$source  = json_decode( (string) $conflict['source_data'], true );
		$local   = json_decode( (string) $conflict['local_data'], true );
		$fields  = json_decode( (string) $conflict['conflict_fields'], true );
		if ( ! is_array( $source ) ) { $source = array(); }
		if ( ! is_array( $local ) )  { $local  = array(); }
		if ( ! is_array( $fields ) ) { $fields = array(); }

		$pending_atts = isset( $source['_immo_openimmo_pending_attachment_ids'] )
			? json_decode( (string) $source['_immo_openimmo_pending_attachment_ids'], true )
			: array();
		if ( ! is_array( $pending_atts ) ) { $pending_atts = array(); }
		$titel_count = 0;
		$bild_count  = 0;
		foreach ( $pending_atts as $a ) {
			if ( ( $a['gruppe'] ?? '' ) === 'TITELBILD' ) { ++$titel_count; } else { ++$bild_count; }
		}

		$ts = strtotime( (string) $conflict['created_at'] );
		?>
		<div class="wrap">
			<p><a href="<?php echo esc_url( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) ); ?>">← <?php esc_html_e( 'Zurück zur Liste', 'immo-manager' ); ?></a></p>
			<h1><?php
				/* translators: %d: conflict id */
				echo esc_html( sprintf( __( 'Konflikt #%d', 'immo-manager' ), $conflict_id ) );
			?></h1>
			<?php $this->render_resolve_notice(); ?>
			<p>
				<strong><?php esc_html_e( 'Property:', 'immo-manager' ); ?></strong>
				<?php
				if ( $post instanceof \WP_Post ) {
					printf(
						'%s &nbsp; <a href="%s" target="_blank">[%s ↗]</a>',
						esc_html( $post->post_title ),
						esc_url( get_edit_post_link( $post->ID ) ),
						esc_html__( 'Im Editor öffnen', 'immo-manager' )
					);
				} else {
					/* translators: %d: post id */
					echo esc_html( sprintf( __( '#%d (gelöscht)', 'immo-manager' ), $post_id ) );
				}
				?>
				<br>
				<strong><?php esc_html_e( 'Portal:', 'immo-manager' ); ?></strong> <?php echo esc_html( (string) $conflict['portal'] ); ?>
				&nbsp;|&nbsp; <strong><?php esc_html_e( 'Erstellt:', 'immo-manager' ); ?></strong>
				<?php echo $ts ? esc_html( sprintf( __( 'vor %s', 'immo-manager' ), human_time_diff( $ts, time() ) ) ) : '—'; ?>
				&nbsp;|&nbsp; <strong><?php esc_html_e( 'Status:', 'immo-manager' ); ?></strong> <?php echo esc_html( (string) $conflict['status'] ); ?>
			</p>

			<h2><?php
				/* translators: %d: anzahl */
				echo esc_html( sprintf( __( 'Geänderte Felder (%d)', 'immo-manager' ), count( $fields ) ) );
			?></h2>

			<?php if ( empty( $fields ) ) : ?>
				<p><em><?php esc_html_e( 'Keine Diff-Felder gespeichert.', 'immo-manager' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feld', 'immo-manager' ); ?></th>
							<th><?php esc_html_e( 'Bestand', 'immo-manager' ); ?></th>
							<th><?php esc_html_e( 'Eingehend', 'immo-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $fields as $f ) : ?>
							<tr>
								<td><code><?php echo esc_html( (string) $f ); ?></code></td>
								<td><?php echo $this->format_value( $local[ $f ] ?? '' ); ?></td>
								<td><?php echo $this->format_value( $source[ $f ] ?? '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $pending_atts ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Eingehende Bilder:', 'immo-manager' ); ?></strong>
					<?php
					/* translators: 1: total, 2: titelbild count, 3: bild count */
					echo esc_html( sprintf( __( '%1$d Attachments (%2$d× TITELBILD, %3$d× BILD)', 'immo-manager' ), count( $pending_atts ), $titel_count, $bild_count ) );
					?>
				</p>
			<?php endif; ?>

			<?php if ( 'pending' === (string) $conflict['status'] ) : ?>
				<form method="post" style="margin-top: 20px;">
					<?php wp_nonce_field( 'immo_openimmo_resolve_' . $conflict_id ); ?>
					<input type="hidden" name="immo_openimmo_resolve_action" value="1">
					<input type="hidden" name="conflict_id" value="<?php echo (int) $conflict_id; ?>">
					<button type="submit" name="resolution" value="keep_local" class="button button-secondary">
						<?php esc_html_e( 'Bestand behalten', 'immo-manager' ); ?>
					</button>
					<button type="submit" name="resolution" value="take_import" class="button button-primary"
							onclick="return confirm('<?php echo esc_js( __( 'Wirklich übernehmen? Property-Daten werden überschrieben.', 'immo-manager' ) ); ?>');">
						<?php esc_html_e( 'Eingehende Daten übernehmen', 'immo-manager' ); ?>
					</button>
				</form>
			<?php else : ?>
				<p><em><?php
					/* translators: %s: resolution */
					echo esc_html( sprintf( __( 'Bereits aufgelöst (%s).', 'immo-manager' ), (string) $conflict['resolution'] ) );
				?></em></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Wert für die Diff-Tabelle formatieren.
	 *
	 * @param mixed $value
	 */
	private function format_value( $value ): string {
		if ( is_array( $value ) ) {
			$str = implode( ', ', array_map( 'strval', $value ) );
		} else {
			$str = (string) $value;
		}
		if ( strlen( $str ) > 200 ) {
			return '<details><summary>' . esc_html( substr( $str, 0, 200 ) ) . '…</summary>' . esc_html( $str ) . '</details>';
		}
		return esc_html( $str );
	}

	private function render_resolve_notice(): void {
		if ( empty( $_GET['immo_resolve_msg'] ) ) {
			return;
		}
		$msg   = sanitize_text_field( rawurldecode( wp_unslash( (string) $_GET['immo_resolve_msg'] ) ) );
		$ok    = ! empty( $_GET['immo_resolve_ok'] ) && '1' === (string) $_GET['immo_resolve_ok'];
		$class = $ok ? 'notice notice-success' : 'notice notice-error';
		printf(
			'<div class="%s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $msg )
		);
	}

	private function menu_label_with_badge(): string {
		$count = Conflicts::pending_count();
		if ( $count <= 0 ) {
			return __( 'OpenImmo-Konflikte', 'immo-manager' );
		}
		return sprintf(
			'%s <span class="awaiting-mod count-%d"><span class="pending-count">%d</span></span>',
			esc_html__( 'OpenImmo-Konflikte', 'immo-manager' ),
			$count,
			$count
		);
	}
}
