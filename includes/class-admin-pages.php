<?php
/**
 * Admin-Menü und Plugin-Seiten.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminPages
 *
 * Registriert das Top-Level-Admin-Menü "Immo Manager" und
 * bindet Plugin-spezifische CSS/JS-Assets ein.
 */
class AdminPages {

	/**
	 * Slug des Top-Level-Menüs. Muss mit dem `show_in_menu`-Wert
	 * in PostTypes übereinstimmen.
	 */
	public const MENU_SLUG = 'immo-manager';

	/**
	 * Required Capability für Admin-Zugriff.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Konstruktor registriert Hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ), 9 );
		add_action( 'admin_menu', array( $this, 'reorder_submenu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Top-Level-Menü und alle Submenüs registrieren.
	 *
	 * @return void
	 */
	public function register_menus(): void {
		// Top-Level-Menü.
		add_menu_page(
			__( 'Immo Manager', 'immo-manager' ),
			__( 'Immo Manager', 'immo-manager' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' ),
			'dashicons-admin-home',
			20
		);

		// Dashboard überschreibt den automatisch generierten ersten Eintrag.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'immo-manager' ),
			__( 'Dashboard', 'immo-manager' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' )
		);

		// HINWEIS: "Alle Immobilien", "+ Neue Immobilie" und "Bauprojekte"
		// werden NICHT hier registriert – WordPress fügt diese automatisch
		// als Submenüs ein, da die CPTs show_in_menu => 'immo-manager' haben.

		// Wohneinheiten – Plugin-eigene Übersichtsseite.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Wohneinheiten', 'immo-manager' ),
			__( 'Wohneinheiten', 'immo-manager' ),
			'edit_posts',
			'immo-units',
			array( $this, 'render_units_page' )
		);

		// Anfragen – Plugin-eigene Seite.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Anfragen', 'immo-manager' ),
			__( 'Anfragen', 'immo-manager' ),
			self::CAPABILITY,
			'immo-inquiries',
			array( $this, 'render_inquiries_page' )
		);

		// API & Hilfe
		add_submenu_page(
			self::MENU_SLUG,
			__( 'API & Hilfe', 'immo-manager' ),
			__( 'API & Hilfe', 'immo-manager' ),
			self::CAPABILITY,
			'immo-help',
			array( $this, 'render_help_page' )
		);
	}

	/**
	 * Submenüs in die gewünschte logische Reihenfolge bringen.
	 *
	 * @return void
	 */
	public function reorder_submenu(): void {
		global $submenu;
		$slug = self::MENU_SLUG;

		if ( ! isset( $submenu[ $slug ] ) ) {
			return;
		}

		// Reihenfolge: Verwaltung zuerst, Konfiguration & Hilfe ganz am Ende.
		$ordered   = array();
		$order_map = array(
			$slug                                                     => 10,
			'edit.php?post_type=' . PostTypes::POST_TYPE_PROPERTY     => 20,
			'post-new.php?post_type=' . PostTypes::POST_TYPE_PROPERTY => 25,
			'edit.php?post_type=' . PostTypes::POST_TYPE_PROJECT      => 30,
			'post-new.php?post_type=' . PostTypes::POST_TYPE_PROJECT  => 35,
			'immo-units'                                              => 40,
			'immo-inquiries'                                          => 50,
			'immo-manager-openimmo'                                   => 55,
			'immo-manager-openimmo-conflicts'                         => 56,
			// --- ab hier: Konfiguration & Hilfe ans Ende ---
			Settings::MENU_SLUG                                       => 90,
			'immo-help'                                               => 95,
		);

		$others = 100;

		foreach ( $submenu[ $slug ] as $item ) {
			$item_slug = $item[2];
			if ( isset( $order_map[ $item_slug ] ) ) {
				$ordered[ $order_map[ $item_slug ] ] = $item;
			} else {
				$ordered[ $others ] = $item;
				$others += 10;
			}
		}

		ksort( $ordered );
		$submenu[ $slug ] = $ordered;
	}

	/**
	 * Dashboard-Seite rendern.
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Du hast keine Berechtigung, diese Seite aufzurufen.', 'immo-manager' ), 403 );
		}

		// Dashboard Willkommens-Banner
		echo '<div class="wrap immo-manager-admin">';
		echo '<div style="background: #fff; padding: 2rem; border-radius: 12px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 2rem; margin: 1rem 0 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">';
		echo '    <div style="flex: 1;">';
		echo '        <h2 style="margin-top: 0; font-size: 1.5rem; color: #1f2937;">' . esc_html__( 'Willkommen beim Immo Manager!', 'immo-manager' ) . '</h2>';
		echo '        <p style="font-size: 1.1rem; color: #4b5563; line-height: 1.6; margin-bottom: 1rem;">' . esc_html__( 'Mit diesem Plugin verwaltest du all deine Immobilien und Bauprojekte zentral an einem Ort. Erstelle Exposés, verwalte Wohneinheiten, empfange Kundenanfragen und stelle deine Daten über die integrierte REST-API für moderne Headless-Websites bereit.', 'immo-manager' ) . '</p>';
		echo '        <p style="font-size: 1.1rem; color: #4b5563; line-height: 1.6; margin-bottom: 0;">' . esc_html__( 'Starte direkt durch, indem du neue Immobilien anlegst oder im Dashboard deine aktuellen Statistiken prüfst.', 'immo-manager' ) . '</p>';
		echo '    </div>';
		echo '    <div style="flex: 0 0 150px; text-align: center;">';
		echo '        <span style="font-size: 6rem; line-height: 1; display: block; animation: immo-float 3s ease-in-out infinite;">🏘️</span>';
		echo '    </div>';
		echo '</div>';
		echo '<style>@keyframes immo-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }</style>';
		echo '</div>';

		$dashboard = Plugin::instance()->get_dashboard();
		$stats     = $dashboard->get_stats();
		$template  = IMMO_MANAGER_PLUGIN_DIR . 'templates/admin/dashboard-page.php';
		if ( is_readable( $template ) ) {
			include $template;
		}
	}

	/**
	 * Wohneinheiten-Übersicht (alle Projekte).
	 *
	 * @return void
	 */
	public function render_units_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'immo-manager' ), 403 );
		}

		global $wpdb;

		// Filter.
		$project_filter = isset( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$status_filter  = isset( $_GET['unit_status'] ) ? sanitize_key( wp_unslash( $_GET['unit_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		// Alle Projekte für Filter-Dropdown.
		$all_projects = get_posts( array(
			'post_type'      => PostTypes::POST_TYPE_PROJECT,
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		// Units-Tabelle direkt abfragen (mit Projekt-Join).
		$table   = Database::units_table();
		$where   = array( '1=1' );
		$prepare = array();

		if ( $project_filter ) {
			$where[]    = 'u.project_id = %d';
			$prepare[]  = $project_filter;
		}
		if ( $status_filter && in_array( $status_filter, Units::STATUSES, true ) ) {
			$where[]   = 'u.status = %s';
			$prepare[] = $status_filter;
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT u.*, p.post_title AS project_title FROM {$table} u
			LEFT JOIN {$wpdb->posts} p ON p.ID = u.project_id
			WHERE {$where_sql}
			ORDER BY p.post_title ASC, u.floor ASC, u.unit_number ASC";

		$units = $prepare
			? $wpdb->get_results( $wpdb->prepare( $sql, $prepare ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$currency = Settings::get( 'currency_symbol', '€' );
		$status_labels = array(
			'available' => __( 'Verfügbar', 'immo-manager' ),
			'reserved'  => __( 'Reserviert', 'immo-manager' ),
			'sold'      => __( 'Verkauft', 'immo-manager' ),
			'rented'    => __( 'Vermietet', 'immo-manager' ),
		);

		echo '<style>
		.immo-unit-status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: 600; line-height: 1; text-align: center; position: static; margin: 0; }
		.status-available { background: #e5f5fa; color: #0073aa; border: 1px solid #c7e6f1; }
		.status-reserved { background: #fff8e5; color: #d68b00; border: 1px solid #ffebba; }
		.status-sold { background: #fcf0f1; color: #d63638; border: 1px solid #fadcdc; }
		.status-rented { background: #f0f0f1; color: #555d66; border: 1px solid #ccd0d4; }
		</style>';

		echo '<div class="wrap immo-manager-admin">';
		echo '<h1>' . esc_html__( 'Wohneinheiten', 'immo-manager' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Alle Wohneinheiten aus allen Bauprojekten. Bearbeitung erfolgt direkt im jeweiligen Bauprojekt.', 'immo-manager' ) . '</p>';

		// Filter-Formular.
		echo '<form method="get" style="margin-bottom:16px;display:flex;gap:10px;align-items:center;">';
		echo '<input type="hidden" name="page" value="immo-units">';
		echo '<select name="project_id"><option value="">' . esc_html__( '— Alle Projekte —', 'immo-manager' ) . '</option>';
		foreach ( $all_projects as $p ) {
			printf( '<option value="%d"%s>%s</option>', $p->ID, selected( $project_filter, $p->ID, false ), esc_html( $p->post_title ) );
		}
		echo '</select>';
		echo '<select name="unit_status"><option value="">' . esc_html__( '— Alle Status —', 'immo-manager' ) . '</option>';
		foreach ( $status_labels as $val => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $status_filter, $val, false ), esc_html( $label ) );
		}
		echo '</select>';
		echo '<button type="submit" class="button">' . esc_html__( 'Filtern', 'immo-manager' ) . '</button>';
		if ( $project_filter || $status_filter ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=immo-units' ) ) . '" class="button">' . esc_html__( 'Zurücksetzen', 'immo-manager' ) . '</a>';
		}
		echo '</form>';

		// Tabelle.
		printf( '<p><strong>%d</strong> %s</p>', count( (array) $units ), esc_html__( 'Wohneinheiten gefunden', 'immo-manager' ) );
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		foreach ( array(
			__( 'Einheit', 'immo-manager' ),
			__( 'Bauprojekt', 'immo-manager' ),
			__( 'Etage', 'immo-manager' ),
			__( 'Fläche', 'immo-manager' ),
			__( 'Zimmer', 'immo-manager' ),
			__( 'Preis / Miete', 'immo-manager' ),
			__( 'Status', 'immo-manager' ),
			__( 'Aktionen', 'immo-manager' ),
		) as $col ) {
			echo '<th>' . esc_html( $col ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $units ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'Keine Wohneinheiten gefunden.', 'immo-manager' ) . '</td></tr>';
		} else {
			foreach ( (array) $units as $unit ) {
				$price_display = '';
				if ( (float) $unit['price'] > 0 ) {
					$price_display = number_format_i18n( (float) $unit['price'] ) . ' ' . $currency;
				}
				if ( (float) $unit['rent'] > 0 ) {
					$price_display .= ( $price_display ? ' / ' : '' ) . number_format_i18n( (float) $unit['rent'] ) . ' ' . $currency . '/Mo';
				}
				$project_edit_url = get_edit_post_link( (int) $unit['project_id'] );
				$unit_edit_url    = admin_url( 'admin.php?page=immo-wizard&id=' . (int) $unit['project_id'] . '&unit_id=' . (int) $unit['id'] );
				
				$unit_status = in_array( $unit['status'] ?? '', array_keys( $status_labels ), true ) ? $unit['status'] : 'available';

				echo '<tr>';
				echo '<td><strong><a href="' . esc_url( $unit_edit_url ) . '">' . esc_html( $unit['unit_number'] ) . '</a></strong></td>';
				echo '<td><a href="' . esc_url( (string) $project_edit_url ) . '">' . esc_html( $unit['project_title'] ?? '–' ) . '</a></td>';
				echo '<td>' . esc_html( (string) $unit['floor'] ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( (float) $unit['area'], 0 ) . ' m²' ) . '</td>';
				echo '<td>' . esc_html( (string) $unit['rooms'] ) . '</td>';
				echo '<td>' . esc_html( $price_display ?: '–' ) . '</td>';
				echo '<td><span class="immo-unit-status-badge status-' . esc_attr( $unit_status ) . '">' . esc_html( $status_labels[ $unit_status ] ) . '</span></td>';
				echo '<td><a href="' . esc_url( $unit_edit_url ) . '" class="button button-small">' . esc_html__( 'Im Wizard bearbeiten', 'immo-manager' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Anfragen-Übersichtsseite.
	 *
	 * @return void
	 */
	public function render_inquiries_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'immo-manager' ), 403 );
		}

		// Aktion: Löschen
		if ( isset( $_GET['action'], $_GET['inq_id'], $_GET['_wpnonce'] ) && 'delete' === $_GET['action'] ) {
			if ( wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_inquiry_' . (int) $_GET['inq_id'] ) ) {
				Inquiries::delete( (int) $_GET['inq_id'] );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Anfrage erfolgreich gelöscht.', 'immo-manager' ) . '</p></div>';
			}
		}

		// Status-Filter.
		$status_filter = isset( $_GET['inq_status'] ) ? sanitize_key( wp_unslash( $_GET['inq_status'] ) ) : 'new'; // phpcs:ignore WordPress.Security.NonceVerification
		$args          = array( 'limit' => 50 );
		if ( $status_filter && in_array( $status_filter, Inquiries::STATUSES, true ) ) {
			$args['status'] = $status_filter;
		}
		$inquiries = Inquiries::query( $args );
		$counts    = Inquiries::count_by_status();

		$status_labels = array(
			'new'     => __( 'Neu', 'immo-manager' ),
			'read'    => __( 'Gelesen', 'immo-manager' ),
			'replied' => __( 'Beantwortet', 'immo-manager' ),
			'spam'    => __( 'Spam', 'immo-manager' ),
		);

		echo '<div class="wrap immo-manager-admin">';
		echo '<h1>' . esc_html__( 'Anfragen', 'immo-manager' ) . '</h1>';

		// Status-Tabs.
		echo '<ul class="subsubsub">';
		$all_total = array_sum( $counts );
		$base_url  = admin_url( 'admin.php?page=immo-inquiries' );
		printf( '<li><a href="%s" %s>%s <span class="count">(%d)</span></a> | </li>',
			esc_url( $base_url ),
			'' === $status_filter ? 'class="current"' : '',
			esc_html__( 'Alle', 'immo-manager' ),
			(int) $all_total
		);
		foreach ( $status_labels as $val => $label ) {
			printf( '<li><a href="%s" %s>%s <span class="count">(%d)</span></a>%s</li>',
				esc_url( add_query_arg( 'inq_status', $val, $base_url ) ),
				$status_filter === $val ? 'class="current"' : '',
				esc_html( $label ),
				(int) ( $counts[ $val ] ?? 0 ),
				$val !== 'spam' ? ' | ' : ''
			);
		}
		echo '</ul>';

		echo '<table class="wp-list-table widefat fixed striped" style="margin-top:16px;">';
		echo '<thead><tr>';
		foreach ( array(
			__( 'Name', 'immo-manager' ),
			__( 'E-Mail', 'immo-manager' ),
			__( 'Telefon', 'immo-manager' ),
			__( 'Immobilie', 'immo-manager' ),
			__( 'Nachricht', 'immo-manager' ),
			__( 'Quelle', 'immo-manager' ),
			__( 'Datum', 'immo-manager' ),
			__( 'Status', 'immo-manager' ),
			__( 'Aktionen', 'immo-manager' ),
		) as $col ) {
			echo '<th>' . esc_html( $col ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $inquiries ) ) {
			echo '<tr><td colspan="9">' . esc_html__( 'Keine Anfragen gefunden.', 'immo-manager' ) . '</td></tr>';
		} else {
			foreach ( $inquiries as $inq ) {
				$property_title = get_the_title( (int) $inq['property_id'] );
				$property_link  = get_edit_post_link( (int) $inq['property_id'] );
				$source_url     = isset( $inq['source_url'] ) ? (string) $inq['source_url'] : '';
				$source_host    = $source_url ? wp_parse_url( $source_url, PHP_URL_HOST ) : '';
				echo '<tr' . ( $source_host ? ' class="immo-inquiry-external"' : '' ) . '>';
				echo '<td><strong>' . esc_html( $inq['inquirer_name'] ) . '</strong></td>';
				echo '<td><a href="mailto:' . esc_attr( $inq['inquirer_email'] ) . '">' . esc_html( $inq['inquirer_email'] ) . '</a></td>';
				echo '<td>' . esc_html( $inq['inquirer_phone'] ?: '–' ) . '</td>';
				echo '<td><a href="' . esc_url( (string) $property_link ) . '">' . esc_html( $property_title ?: '–' ) . '</a></td>';
				echo '<td><span title="' . esc_attr( (string) $inq['inquirer_message'] ) . '">' . esc_html( wp_trim_words( (string) $inq['inquirer_message'], 10 ) ) . '</span></td>';
				echo '<td>';
				if ( $source_host ) {
					echo '<a href="' . esc_url( $source_url ) . '" target="_blank" rel="noopener" title="' . esc_attr( $source_url ) . '" style="display:inline-block;background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:999px;font-size:12px;text-decoration:none;font-weight:600;">&#x2197; ' . esc_html( $source_host ) . '</a>';
				} else {
					echo '<span style="color:#9ca3af;font-size:12px;">' . esc_html__( 'eigene Seite', 'immo-manager' ) . '</span>';
				}
				echo '</td>';
				echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( (string) $inq['created_at'] ) ) ) . '</td>';
				echo '<td>' . esc_html( $status_labels[ $inq['status'] ] ?? $inq['status'] ) . '</td>';
				echo '<td>';
				if ( in_array( $inq['status'], array( 'new', 'read' ), true ) ) {
					printf( '<a href="mailto:%s?subject=%s" class="button button-small button-primary immo-reply-btn" data-id="%d">%s</a> ',
						esc_attr( $inq['inquirer_email'] ),
						rawurlencode( sprintf( __( 'Re: Anfrage zu %s', 'immo-manager' ), $property_title ) ),
						(int) $inq['id'],
						esc_html__( 'Antworten', 'immo-manager' )
					);
				}
				
				$delete_url = wp_nonce_url(
					add_query_arg( array( 'page' => 'immo-inquiries', 'action' => 'delete', 'inq_id' => $inq['id'] ), admin_url( 'admin.php' ) ),
					'delete_inquiry_' . $inq['id']
				);
				echo '<a href="' . esc_url( $delete_url ) . '" class="button button-small" style="color:#d63638; border-color:#d63638;" onclick="return confirm(\'' . esc_js( __( 'Diese Anfrage wirklich löschen?', 'immo-manager' ) ) . '\');">' . esc_html__( 'Löschen', 'immo-manager' ) . '</a>';
				
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		?>
		<script>
		jQuery(document).ready(function($){
			$('.immo-reply-btn').on('click', function(){
				$.post( ajaxurl, {
					action: 'immo_inquiry_replied',
					inq_id: $(this).data('id'),
					nonce: '<?php echo esc_js( wp_create_nonce( 'immo_inquiry_nonce' ) ); ?>'
				}, function() {
					setTimeout(function(){ location.reload(); }, 1500);
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * API & Hilfe Seite rendern.
	 *
	 * @return void
	 */
	public function render_help_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'immo-manager' ), 403 );
		}
		$api_url      = rest_url( RestApi::NAMESPACE );
		$settings_url = admin_url( 'admin.php?page=' . Settings::MENU_SLUG );
		$embed_url    = IMMO_MANAGER_PLUGIN_URL . 'public/embed/immo-embed.js';
		$manual_url   = IMMO_MANAGER_PLUGIN_URL . 'public/embed/anleitung.html';

		// Für den Snippet-Generator: veröffentlichte Bauprojekte & Immobilien (ID, Slug, Titel).
		$embed_items = array( 'projects' => array(), 'properties' => array() );
		foreach ( array( 'projects' => PostTypes::POST_TYPE_PROJECT, 'properties' => PostTypes::POST_TYPE_PROPERTY ) as $bucket => $ptype ) {
			$posts = get_posts( array( 'post_type' => $ptype, 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => 'title', 'order' => 'ASC' ) );
			foreach ( $posts as $ep ) {
				$embed_items[ $bucket ][] = array( 'id' => (int) $ep->ID, 'slug' => $ep->post_name, 'title' => get_the_title( $ep ) );
			}
		}
		?>
		<style>
			.immo-help-wrap { max-width: 1100px; }
			.immo-help-section { background: #fff; padding: 2rem; border: 1px solid #e5e7eb; margin-top: 1.5rem; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
			.immo-help-section h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 0.75rem; font-size: 1.4rem; }
			.immo-help-section h3 { font-size: 1.15rem; margin-top: 1.75rem; color: #1f2937; }
			.immo-help-section h4 { font-size: 1rem; margin-top: 1.25rem; margin-bottom: 0.4rem; color: #374151; }
			.immo-help-section pre { background: #f3f4f6; padding: 14px 16px; border-left: 3px solid #2563eb; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; font-size: 13px; line-height: 1.5; margin: 10px 0; }
			.immo-help-section code { background: #eef2ff; padding: 2px 6px; font-size: 90%; color: #1e40af; font-family: Menlo, Consolas, monospace; }
			.immo-help-section pre code { background: transparent; padding: 0; color: inherit; }
			.immo-help-section table { width: 100%; border-collapse: collapse; margin: 14px 0; font-size: 13.5px; }
			.immo-help-section th, .immo-help-section td { border: 1px solid #e5e7eb; padding: 8px 12px; text-align: left; vertical-align: top; }
			.immo-help-section th { background: #f9fafb; font-weight: 600; }
			.immo-help-section .description { font-size: 1.05em; color: #4b5563; }
			.immo-help-section ul, .immo-help-section ol { margin-left: 1.4rem; }
			.immo-help-section li { margin-bottom: 0.35rem; line-height: 1.55; }
			.immo-help-toc { background: #f9fafb; border: 1px solid #e5e7eb; padding: 1.25rem 1.5rem; margin-top: 1rem; columns: 2; }
			.immo-help-toc a { display: block; padding: 4px 0; text-decoration: none; }
			.immo-help-badge { display: inline-block; padding: 2px 8px; background: #dbeafe; color: #1e40af; font-size: 11px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; vertical-align: middle; margin-left: 6px; }
			.immo-help-callout { padding: 12px 16px; background: #fffbeb; border-left: 4px solid #f59e0b; margin: 14px 0; }
			.immo-help-callout--info { background: #eff6ff; border-left-color: #3b82f6; }
			.immo-help-callout--ok { background: #f0fdf4; border-left-color: #10b981; }
		</style>
		<div class="wrap immo-manager-admin immo-help-wrap">
			<h1><?php esc_html_e( 'Hilfe & API-Dokumentation', 'immo-manager' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Komplette Dokumentation aller Funktionen — Verwaltung, Frontend-Einbindung, Rechner, OpenImmo-Sync und REST-API.', 'immo-manager' ); ?></p>

			<!-- INHALTSVERZEICHNIS -->
			<div class="immo-help-toc">
				<strong><?php esc_html_e( 'Inhalt', 'immo-manager' ); ?></strong>
				<a href="#help-overview"><?php esc_html_e( '1. Funktionsübersicht', 'immo-manager' ); ?></a>
				<a href="#help-content"><?php esc_html_e( '2. Inhalte verwalten', 'immo-manager' ); ?></a>
				<a href="#help-wizard"><?php esc_html_e( '3. Eingabe-Wizard', 'immo-manager' ); ?></a>
				<a href="#help-frontend"><?php esc_html_e( '4. Frontend (Shortcodes & Elementor)', 'immo-manager' ); ?></a>
				<a href="#help-design"><?php esc_html_e( '5. Design & Layout', 'immo-manager' ); ?></a>
				<a href="#help-calculator"><?php esc_html_e( '6. Nebenkosten- & Finanzierungsrechner', 'immo-manager' ); ?></a>
				<a href="#help-inquiries"><?php esc_html_e( '7. Anfragen-Verwaltung', 'immo-manager' ); ?></a>
				<a href="#help-openimmo"><?php esc_html_e( '8. OpenImmo Import/Export', 'immo-manager' ); ?></a>
				<a href="#help-schema"><?php esc_html_e( '9. SEO & Schema.org', 'immo-manager' ); ?></a>
				<a href="#help-settings"><?php esc_html_e( '10. Einstellungs-Übersicht', 'immo-manager' ); ?></a>
				<a href="#help-api"><?php esc_html_e( '11. REST-API Referenz', 'immo-manager' ); ?></a>
				<a href="#help-embed"><?php esc_html_e( '12. Embed-Widgets für externe Webseiten', 'immo-manager' ); ?></a>
			</div>

			<!-- 1. ÜBERSICHT -->
			<div class="immo-help-section" id="help-overview">
				<h2>🏘️ <?php esc_html_e( '1. Funktionsübersicht', 'immo-manager' ); ?></h2>
				<p><?php esc_html_e( 'Der Immo Manager ist ein vollwertiges Immobilien-Verwaltungs-Plugin für österreichische Makler, Bauträger und private Anbieter. Es verbindet eine komfortable Backend-Verwaltung mit modernen Frontend-Darstellungen und einer offenen REST-API für Headless-Setups.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( 'Kernfunktionen im Überblick', 'immo-manager' ); ?></h3>
				<ul>
					<li><strong><?php esc_html_e( 'Immobilien & Bauprojekte', 'immo-manager' ); ?></strong> — <?php esc_html_e( 'zwei eigene WordPress-Inhaltstypen, Bauprojekte verwalten zusätzlich Wohneinheiten in einer eigenen DB-Tabelle.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( '7-stufiger Eingabe-Wizard', 'immo-manager' ); ?></strong> — <?php esc_html_e( 'klare Schritt-für-Schritt-Eingabe statt Metabox-Wüste, mit Live-Validierung und Auto-Save-Schutz.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Frontend-Templates', 'immo-manager' ); ?></strong> — <?php esc_html_e( 'fertige Listen-, Such- und Detail-Seiten mit Slider-Galerie, Lightbox, Karten-Integration und Anfrage-Lightbox.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Shortcodes & Elementor-Widgets', 'immo-manager' ); ?></strong> — <?php esc_html_e( 'sieben Shortcodes plus vier dedizierte Elementor-Widgets.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Nebenkosten- und Finanzierungsrechner', 'immo-manager' ); ?></strong> — <?php esc_html_e( 'AT-konforme Defaults, vollständig in den Settings konfigurierbar, mit jährlichem Tilgungsplan und Sondertilgung.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Anfragen-System', 'immo-manager' ); ?></strong> — <?php esc_html_e( 'eingehende Anfragen werden in einer eigenen DB-Tabelle gesammelt, per Mail benachrichtigt, mit Status-Workflow.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'OpenImmo 1.2.7 Import & Export', 'immo-manager' ); ?></strong> — <?php esc_html_e( 'XML-Austausch mit Portalen wie ImmoScout24, willhaben oder ImmoboerseAT, inklusive SFTP-Übertragung, Konflikt-Erkennung und Cron-Automation.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Schema.org Markup', 'immo-manager' ); ?></strong> — <?php esc_html_e( 'automatisches strukturiertes Datenmarkup (RealEstateListing) für bessere SEO-Sichtbarkeit.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'REST-API', 'immo-manager' ); ?></strong> — <?php esc_html_e( 'vollständige JSON-API mit Filtern, Sortierung und API-Key-Schutz für Headless-Frontends (React/Vue/etc.).', 'immo-manager' ); ?></li>
				</ul>

				<div class="immo-help-callout immo-help-callout--info">
					<strong><?php esc_html_e( 'Tipp:', 'immo-manager' ); ?></strong>
					<?php esc_html_e( 'Alle Einstellungen findest du gesammelt unter ', 'immo-manager' ); ?>
					<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Immo Manager → Einstellungen', 'immo-manager' ); ?></a>.
				</div>
			</div>

			<!-- 2. CONTENT MGMT -->
			<div class="immo-help-section" id="help-content">
				<h2>📝 <?php esc_html_e( '2. Inhalte verwalten', 'immo-manager' ); ?></h2>

				<h3><?php esc_html_e( 'Immobilien (Single Properties)', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Eigenständige Objekte: Wohnung, Haus, Grundstück, Gewerbeobjekt. Jede Immobilie hat eigene Galerie, Preise (Kauf und/oder Miete), Ausstattungs-Tags, Kontaktperson und optional Geo-Koordinaten für die Karten-Anzeige.', 'immo-manager' ); ?></p>
				<p><?php esc_html_e( 'Anlegen: ', 'immo-manager' ); ?>
					<code>Immo Manager → + Neue Immobilie</code>. <?php esc_html_e( 'Du wirst automatisch in den Wizard geleitet.', 'immo-manager' ); ?>
				</p>

				<h3><?php esc_html_e( 'Bauprojekte (Projects)', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Container für mehrere Wohneinheiten — typischerweise ein Bauträger-Projekt. Das Projekt selbst hat eine Adresse, Galerie, Beschreibung und Status (in Planung / in Bau / fertiggestellt). Innerhalb des Projekts werden die Einheiten (Tops) verwaltet.', 'immo-manager' ); ?></p>
				<p><?php esc_html_e( 'Anlegen: ', 'immo-manager' ); ?>
					<code>Immo Manager → + Neues Bauprojekt</code>.
				</p>

				<h3><?php esc_html_e( 'Wohneinheiten (Units)', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Einzelne Tops innerhalb eines Bauprojekts. Eigene Felder: Top-Nummer, Etage, Fläche, Zimmer, Preis/Miete, Status, Grundriss-Bild. Anlage erfolgt direkt aus dem Bauprojekt-Wizard heraus oder per AJAX-Inline-Editor.', 'immo-manager' ); ?></p>
				<p><?php esc_html_e( 'Plugin-eigene Übersicht aller Units quer über alle Projekte: ', 'immo-manager' ); ?>
					<code>Immo Manager → Wohneinheiten</code>.
				</p>

				<h3><?php esc_html_e( 'Verknüpfung Property ↔ Unit', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Jede Wohneinheit kann optional mit einer normalen Immobilie verknüpft werden. So erscheint die Einheit in der globalen Listenansicht UND auf der Bauprojekt-Seite. Doppelpflege entfällt.', 'immo-manager' ); ?></p>
			</div>

			<!-- 3. WIZARD -->
			<div class="immo-help-section" id="help-wizard">
				<h2>🧙 <?php esc_html_e( '3. Eingabe-Wizard', 'immo-manager' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Statt klassischer Metaboxen bietet der Immo Manager einen geführten 7-Schritte-Wizard. Beim Anlegen oder Bearbeiten einer Immobilie/eines Projekts wirst du automatisch in den Wizard geleitet.', 'immo-manager' ); ?></p>

				<table>
					<thead>
						<tr><th><?php esc_html_e( 'Schritt', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Inhalt', 'immo-manager' ); ?></th></tr>
					</thead>
					<tbody>
						<tr><td><strong>1. Typ</strong></td><td><?php esc_html_e( 'Immobilientyp (Wohnung/Haus/etc.), Modus (Miete/Kauf/beides), Status', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>2. Lage</strong></td><td><?php esc_html_e( 'Adresse, PLZ, Ort, Bundesland, Bezirk, Geo-Koordinaten (Maps-Integration)', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>3. Details</strong></td><td><?php esc_html_e( 'Fläche, Zimmer, Bäder, Etage, Baujahr, Sanierung, Energieausweis (Energieeffizienzklasse, HWB, Endenergiebedarf EEB – Pflicht seit 1.7.2026; fGEE nur bei Altausweis), Heizung', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>4. Preis</strong></td><td><?php esc_html_e( 'Kaufpreis, Miete, Betriebskosten, Kaution, Provisionsfrei-Toggle, Verfügbar-ab', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>5. Ausstattung</strong></td><td><?php esc_html_e( 'Klickbare Feature-Tags (Innen/Außen/Sicherheit/Sonstiges), freier Text', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>6. Medien</strong></td><td><?php esc_html_e( 'Hauptbild, Galerie, Dokumente (Exposé-PDF, Grundriss), Video-URL/Datei', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>7. Kontakt</strong></td><td><?php esc_html_e( 'Ansprechpartner-Name, E-Mail, Telefon, Foto. Werden in der Anfrage-Lightbox angezeigt.', 'immo-manager' ); ?></td></tr>
					</tbody>
				</table>

				<div class="immo-help-callout immo-help-callout--ok">
					<strong>✔️ <?php esc_html_e( 'Auto-Save-Schutz:', 'immo-manager' ); ?></strong>
					<?php esc_html_e( 'Verlässt du den Wizard mit ungespeicherten Änderungen, warnt das Plugin per Browser-Dialog. Keine verlorene Eingabe mehr.', 'immo-manager' ); ?>
				</div>

				<h3><?php esc_html_e( 'Bauprojekt-spezifisch: Wohneinheiten anlegen', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Beim Bearbeiten eines Bauprojekts findest du nach den 7 Wizard-Schritten eine zusätzliche Tabelle, in der du Wohneinheiten direkt anlegen, sortieren (Drag & Drop) und löschen kannst — ohne die Seite zu verlassen. Jede Einheit kann auf Wunsch mit einer eigenständigen Immobilie verknüpft werden, sodass sie auch in der globalen Liste auftaucht.', 'immo-manager' ); ?></p>
			</div>

			<!-- 4. FRONTEND -->
			<div class="immo-help-section" id="help-frontend">
				<h2>🎨 <?php esc_html_e( '4. Frontend-Einbindung', 'immo-manager' ); ?></h2>

				<h3><?php esc_html_e( 'Shortcodes', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Sieben Shortcodes für jede Anwendungssituation:', 'immo-manager' ); ?></p>

				<table>
					<thead>
						<tr><th><?php esc_html_e( 'Shortcode', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Verwendung', 'immo-manager' ); ?></th></tr>
					</thead>
					<tbody>
						<tr>
							<td><code>[immo_list]</code></td>
							<td><?php esc_html_e( 'Hauptlisten-Seite mit AJAX-Filter-Sidebar (Status, Modus, Bundesland, Preis, Fläche, Zimmer, Type). Attribute: ', 'immo-manager' ); ?>
								<code>status</code>, <code>mode</code>, <code>per_page</code>, <code>orderby</code>, <code>layout</code> (grid/list).
							</td>
						</tr>
						<tr>
							<td><code>[immo_detail id="123"]</code></td>
							<td><?php esc_html_e( 'Detail-Ansicht für Immobilien-ID 123. Eingebettet in beliebigen Seiten.', 'immo-manager' ); ?></td>
						</tr>
						<tr>
							<td><code>[immo_detail_page]</code></td>
							<td><?php esc_html_e( 'Dynamische Detail-Seite — ID kommt aus URL-Parameter `?immo_id=123`. Ideal für Single-Page-App-Setup.', 'immo-manager' ); ?></td>
						</tr>
						<tr>
							<td><code>[immo_latest count="3"]</code></td>
							<td><?php esc_html_e( 'Die N neuesten Immobilien als Cards. Ideal für die Startseite.', 'immo-manager' ); ?></td>
						</tr>
						<tr>
							<td><code>[immo_featured count="3"]</code></td>
							<td><?php esc_html_e( 'Hervorgehobene Top-Immobilien (per Checkbox in der Property markiert).', 'immo-manager' ); ?></td>
						</tr>
						<tr>
							<td><code>[immo_count]</code></td>
							<td><?php esc_html_e( 'Reine Zahl: aktuell verfügbare Immobilien. Für Header/Counter-Animationen.', 'immo-manager' ); ?></td>
						</tr>
						<tr>
							<td><code>[immo_search]</code></td>
							<td><?php esc_html_e( 'Inline-Suchformular (z. B. im Header). Leitet auf die Listen-Seite mit den Filter-Parametern weiter.', 'immo-manager' ); ?></td>
						</tr>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'Elementor-Widgets', 'immo-manager' ); ?> <span class="immo-help-badge"><?php esc_html_e( 'Optional', 'immo-manager' ); ?></span></h3>
				<p><?php esc_html_e( 'Wenn Elementor installiert ist, registriert das Plugin automatisch vier eigene Widgets unter der Kategorie "Immo Manager":', 'immo-manager' ); ?></p>
				<ul>
					<li><strong><?php esc_html_e( 'Immobilien-Liste', 'immo-manager' ); ?></strong> — <?php esc_html_e( 'flexibles Listen-Widget mit Grid/List/Slider/Karten-Layout, eigenen Query-Filtern (Status, Modus, Preis, Region) und einstellbarer Card-Darstellung.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Bauprojekte', 'immo-manager' ); ?></strong> — <?php esc_html_e( 'rendert Projekte mit Status-Badges und Verfügbarkeits-Stats.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Projekt-Wohneinheiten', 'immo-manager' ); ?></strong> — <?php esc_html_e( 'Tabellen-Übersicht aller Tops eines Projekts (Etage, Fläche, Zimmer, Preis, Status).', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Immobilien-Suche', 'immo-manager' ); ?></strong> — <?php esc_html_e( 'horizontaler Suchschlitz für Header oder Hero-Sektionen.', 'immo-manager' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Embed-Widget für fremde Webseiten', 'immo-manager' ); ?> <span class="immo-help-badge"><?php esc_html_e( 'Neu', 'immo-manager' ); ?></span></h3>
				<p><?php esc_html_e( 'Immobilien, Bauprojekte und Wohneinheiten lassen sich mit einem Script-Snippet in JEDE Webseite einbetten – auch ohne WordPress. Komplette Bedienungsanleitung, Snippet-Generator mit Live-Vorschau und Schritt-für-Schritt-Anleitungen für Webflow, Wix, Jimdo, Squarespace, Typo3, Joomla, Shopify und reines HTML:', 'immo-manager' ); ?>
					<a href="#help-embed"><strong><?php esc_html_e( '→ Kapitel 12: Embed-Widgets', 'immo-manager' ); ?></strong></a></p>

				<h3><?php esc_html_e( 'Provisionsfrei-Badge auf Public-Templates', 'immo-manager' ); ?> <span class="immo-help-badge"><?php esc_html_e( 'Neu', 'immo-manager' ); ?></span></h3>
				<p><?php esc_html_e( 'Properties mit aktivierter Checkbox „Provisionsfrei" (Wizard Schritt 4 oder Property-Metabox) zeigen automatisch ein gut sichtbares gelb/oranges Badge:', 'immo-manager' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Property-Detailseite — Sticker oben-rechts auf Hero/Galerie', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Listing-Cards (Archive, ', 'immo-manager' ); ?><code>[immo_list]</code><?php esc_html_e( ', Elementor) — Sticker auf Vorschaubild', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Bauprojekt-Detailseite — Inline-Icon in der Preisspalte der Wohneinheiten-Tabelle (nur bei Properties mit Flag)', 'immo-manager' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'Bedingung: Modus „Verkauf" oder „Beides" — bei reiner Miete erscheint kein Badge. Beschriftung änderbar in ', 'immo-manager' ); ?><a href="<?php echo esc_url( $settings_url . '#tab-calculator' ); ?>"><?php esc_html_e( 'Einstellungen → Rechner', 'immo-manager' ); ?></a><?php esc_html_e( ' (siehe Kapitel 6).', 'immo-manager' ); ?></p>
			</div>

			<!-- 5. DESIGN & LAYOUT -->
			<div class="immo-help-section" id="help-design">
				<h2>🖌️ <?php esc_html_e( '5. Design & Layout', 'immo-manager' ); ?></h2>

				<h3><?php esc_html_e( 'Globale Design-Einstellungen', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Unter Einstellungen → Design & Layout legst du Akzentfarbe, Hintergrund, Text-Farben, Schriftart, Border-Radius und Card-Stil global fest. Die Werte werden als CSS-Custom-Properties (--immo-*) injiziert und gelten für alle Plugin-Templates.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( 'Detail-Seite: Layout-Varianten', 'immo-manager' ); ?></h3>
				<ul>
					<li><strong><?php esc_html_e( 'Layout-Typ:', 'immo-manager' ); ?></strong> <code>standard</code> (zweispaltig mit Sticky-Sidebar) oder <code>compact</code> (einspaltig kompakt)</li>
					<li><strong><?php esc_html_e( 'Galerie:', 'immo-manager' ); ?></strong> <code>slider</code> (Hauptbild + Thumbnails) oder <code>grid</code> (Bento-Layout)</li>
					<li><strong><?php esc_html_e( 'Hero-Größe:', 'immo-manager' ); ?></strong> <code>full</code> (volle Breite) oder <code>compact</code></li>
				</ul>
				<p><?php esc_html_e( 'Diese drei Achsen sind zuerst global gesetzt, können aber per Property/Bauprojekt einzeln überschrieben werden (Sidebar-Box "Darstellung & Layout").', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( 'Listen-Karten', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Card-Design-Optionen: Standard, Modern, Minimal, Compact. Galerie-Hover-Effekte, Status-Badges, Featured-Sterne — alles per Settings konfigurierbar.', 'immo-manager' ); ?></p>
			</div>

			<!-- 6. CALCULATOR -->
			<div class="immo-help-section" id="help-calculator">
				<h2>🧮 <?php esc_html_e( '6. Nebenkosten- & Finanzierungsrechner', 'immo-manager' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Der Rechner erscheint automatisch auf jeder Kauf-Property mit Preis und auf jeder Bauprojekt-Seite mit mindestens einer Kauf-Einheit. Er besteht aus zwei separaten Akkordeons unterhalb aller Detail-Sektionen.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( 'Nebenkostenrechner (Akkordeon 1)', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Berechnet die typischen AT-Erwerbsnebenkosten:', 'immo-manager' ); ?></p>
				<ul>
					<li>📋 <?php esc_html_e( 'Grunderwerbsteuer (Default 3,5 %)', 'immo-manager' ); ?></li>
					<li>📋 <?php esc_html_e( 'Grundbucheintragung (Default 1,1 %)', 'immo-manager' ); ?></li>
					<li>📋 <?php esc_html_e( 'Notar/Treuhand (Prozent ODER Pauschalbetrag)', 'immo-manager' ); ?></li>
					<li>📋 <?php esc_html_e( 'Maklerprovision (Default 3 %) — entfällt bei Property-Override "provisionsfrei"', 'immo-manager' ); ?></li>
					<li>📋 <?php esc_html_e( 'USt auf Provision (Default 20 %)', 'immo-manager' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'Jeder Posten ist global ein-/ausschaltbar; Sätze sind frei konfigurierbar.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( 'Finanzierungsrechner (Akkordeon 2)', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Klassische Annuitätenrechnung auf Basis Kaufpreis + Nebenkosten:', 'immo-manager' ); ?></p>
				<ul>
					<li>💶 <?php esc_html_e( 'Eigenkapital — umschaltbar zwischen € und % (Default 20 %, KIM-V-konform)', 'immo-manager' ); ?></li>
					<li>📈 <?php esc_html_e( 'Zinssatz, Laufzeit, optionale jährliche Sondertilgung', 'immo-manager' ); ?></li>
					<li>📊 <?php esc_html_e( 'Ausgabe: monatliche Rate, Gesamt-Zinsen, Gesamtaufwand, Tilgung-Endjahr', 'immo-manager' ); ?></li>
					<li>📋 <?php esc_html_e( 'Optional: jährlicher Tilgungsplan als Tabelle (Jahr / Zinsen / Tilgung / Restschuld)', 'immo-manager' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Bauprojekt-Modus', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Auf Bauprojekt-Seiten zeigt jeder Rechner zusätzlich ein Wohneinheits-Dropdown. Auswahl wechselt sofort den Berechnungs-Kaufpreis.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( 'Konfiguration', 'immo-manager' ); ?></h3>
				<p><a href="<?php echo esc_url( $settings_url . '#tab-calculator' ); ?>"><?php esc_html_e( '→ Einstellungen → Rechner', 'immo-manager' ); ?></a> <?php esc_html_e( ' — alle Sätze, Toggles, Notar-Modus und Finanz-Defaults.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( 'Provisionsfrei-Badge', 'immo-manager' ); ?> <span class="immo-help-badge"><?php esc_html_e( 'Neu', 'immo-manager' ); ?></span></h3>
				<p><?php esc_html_e( 'Pro Property im Wizard Schritt 4 oder in der Property-Metabox die Checkbox „Provisionsfrei" aktivieren. Dann passiert dreierlei automatisch:', 'immo-manager' ); ?></p>
				<ol>
					<li><?php esc_html_e( 'Auf allen Public-Templates erscheint ein gut sichtbarer gelb/oranger „Provisionsfrei"-Patch (oben-rechts auf Hero-Bildern, Inline-Icon in Bauprojekt-Unit-Tabellen).', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Der Nebenkostenrechner blendet Maklerprovision + USt-auf-Provision aus.', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Im OpenImmo-Export wird das Element ', 'immo-manager' ); ?><code>&lt;aussen_courtage&gt;</code><?php esc_html_e( ' für diese Property weggelassen.', 'immo-manager' ); ?></li>
				</ol>
				<p><?php esc_html_e( 'Bedingung für die Anzeige: Property ist als Kauf markiert (Modus „Verkauf" oder „Beides"). Bei reiner Miete erscheint kein Badge.', 'immo-manager' ); ?></p>
				<p><strong><?php esc_html_e( 'Beschriftung anpassen:', 'immo-manager' ); ?></strong>
				<a href="<?php echo esc_url( $settings_url . '#tab-calculator' ); ?>"><?php esc_html_e( 'Einstellungen → Rechner → „Provisionsfrei-Badge: Beschriftung"', 'immo-manager' ); ?></a> <?php esc_html_e( '(Default „Provisionsfrei"). Der konfigurierte Text wird über REST als ', 'immo-manager' ); ?><code>meta.commission_free_label</code><?php esc_html_e( ' ausgeliefert — Headless-Konsumenten (z. B. das ImmoClient-Plugin) zeigen automatisch denselben Text.', 'immo-manager' ); ?></p>

				<div class="immo-help-callout">
					<strong>⚠️ <?php esc_html_e( 'Disclaimer:', 'immo-manager' ); ?></strong>
					<?php esc_html_e( 'Die Berechnung ist eine unverbindliche Schätzung — kein Ersatz für Bank- oder Steuerberatung. Der Hinweis erscheint automatisch unter jedem Rechner.', 'immo-manager' ); ?>
				</div>
			</div>

			<!-- 7. INQUIRIES -->
			<div class="immo-help-section" id="help-inquiries">
				<h2>✉️ <?php esc_html_e( '7. Anfragen-Verwaltung', 'immo-manager' ); ?></h2>
				<p><?php esc_html_e( 'Frontend-Anfragen werden in einer eigenen DB-Tabelle gespeichert (nicht als WP-Comments). Vorteile: getrennte Verwaltung, eigene Status-Workflows, kein Spam-Plugin-Konflikt.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( 'Workflow', 'immo-manager' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Besucher klickt "Anfrage senden" auf einer Property/Projekt-Seite → Lightbox mit Formular öffnet sich.', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Pflichtfelder: Name, E-Mail, DSGVO-Zustimmung. Optional: Telefon, Nachricht, Wohneinheit (bei Projekten).', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Validierung clientseitig + serverseitig. Anti-Spam per Honeypot + (optional) reCAPTCHA.', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Mail-Benachrichtigung an die definierte Empfänger-Adresse — wahlweise globaler Empfänger oder Property-spezifischer Kontakt.', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Anfrage erscheint im Backend unter Immo Manager → Anfragen mit Status "Neu".', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Status-Lifecycle: Neu → Gelesen → Beantwortet → (Spam).', 'immo-manager' ); ?></li>
				</ol>

				<h3><?php esc_html_e( 'Backend-Funktionen', 'immo-manager' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Filter nach Status (Tabs: Alle / Neu / Gelesen / Beantwortet / Spam) mit Live-Zähler', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Direkter "Antworten"-Button öffnet Mail-Programm mit vorgefertigtem Betreff', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Status wird beim Klicken auf "Antworten" automatisch auf "Beantwortet" gesetzt (AJAX)', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Lösch-Funktion mit Nonce-Schutz', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Dashboard-Widget zeigt offene Anfragen-Zahl', 'immo-manager' ); ?></li>
				</ul>
			</div>

			<!-- 8. OPENIMMO -->
			<div class="immo-help-section" id="help-openimmo">
				<h2>📦 <?php esc_html_e( '8. OpenImmo Import & Export', 'immo-manager' ); ?> <span class="immo-help-badge">OpenImmo 1.2.7</span></h2>
				<p class="description"><?php esc_html_e( 'OpenImmo ist der etablierte XML-Standard für den Datenaustausch mit Immobilienportalen (ImmoScout24, willhaben, ImmoboerseAT etc.). Der Immo Manager unterstützt sowohl Import als auch Export, inklusive automatischer SFTP-Übertragung.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( 'Export', 'immo-manager' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Mappt alle Plugin-Felder auf das OpenImmo-Schema (immobilie/objektkategorie/preise/freitexte/anhaenge/etc.)', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Verarbeitet Bilder lokal (Resize, Optimierung) bevor sie ins Paket wandern', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Validiert das XML gegen die offizielle OpenImmo XSD vor dem Versand', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'ZIP-Paket inkl. allen Bilddateien wird erzeugt und per SFTP an die konfigurierten Portal-Targets übertragen', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Manueller Trigger oder per Cron-Schedule (stündlich, täglich, wöchentlich)', 'immo-manager' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Import', 'immo-manager' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Periodisches SFTP-Pulling aus dem konfigurierten Eingangs-Verzeichnis', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'ZIP-Pakete werden automatisch entpackt, XML geparst, Bilder in die Mediathek importiert', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Reverse-Mapping zurück auf Plugin-Felder', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Konflikt-Erkennung: Wenn dieselbe Objektreferenz lokal verändert UND extern geändert wurde → Eintrag in der Konflikt-Liste, manuelle Auflösung', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Sync-Log mit allen Vorgängen (Erfolg/Fehler/Warnung)', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Retention-Cleaner löscht alte Sync-Logs und temporäre Dateien automatisch', 'immo-manager' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Mail-Notifier', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Optional: Mail-Bericht nach jedem Sync-Lauf (alles ok / Konflikte vorhanden / Fehler aufgetreten).', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( 'Konfiguration', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Alle Portal-Targets, SFTP-Zugangsdaten, Cron-Frequenzen und Retention-Regeln werden in einer dedizierten OpenImmo-Admin-Sektion gepflegt (', 'immo-manager' ); ?>
					<code>Immo Manager → Einstellungen</code> bzw. <code>Immo Manager → OpenImmo Sync</code>).
				</p>
			</div>

			<!-- 9. SCHEMA -->
			<div class="immo-help-section" id="help-schema">
				<h2>🔍 <?php esc_html_e( '9. SEO & Schema.org', 'immo-manager' ); ?></h2>
				<p><?php esc_html_e( 'Auf jeder Property- und Projekt-Detailseite injiziert der Immo Manager automatisch JSON-LD Markup nach Schema.org-Spezifikation:', 'immo-manager' ); ?></p>
				<ul>
					<li><code>RealEstateListing</code> — <?php esc_html_e( 'Top-Level für die Anzeige', 'immo-manager' ); ?></li>
					<li><code>Place / GeoCoordinates</code> — <?php esc_html_e( 'Adresse + Geo-Koordinaten', 'immo-manager' ); ?></li>
					<li><code>Offer / PriceSpecification</code> — <?php esc_html_e( 'Preis, Währung, Verfügbarkeit', 'immo-manager' ); ?></li>
					<li><code>Accommodation / Apartment / House</code> — <?php esc_html_e( 'Räume, Fläche, Etage', 'immo-manager' ); ?></li>
					<li><code>Organization / Person</code> — <?php esc_html_e( 'Anbieter und Kontaktperson', 'immo-manager' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'Vorteile: bessere Google-Rich-Results für Immobilien-Suchen, automatische Indexierung in Google for Real Estate (sofern aktiviert), strukturierte Daten für Voice-Search und KI-Suchmaschinen.', 'immo-manager' ); ?></p>
				<div class="immo-help-callout immo-help-callout--info">
					<?php esc_html_e( 'Prüfen lässt sich das Markup mit dem ', 'immo-manager' ); ?>
					<a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">Google Rich Results Test</a>.
				</div>
			</div>

			<!-- 10. SETTINGS -->
			<div class="immo-help-section" id="help-settings">
				<h2>⚙️ <?php esc_html_e( '10. Einstellungs-Übersicht', 'immo-manager' ); ?></h2>
				<p><?php esc_html_e( 'Die Einstellungs-Seite ist in Tabs gegliedert:', 'immo-manager' ); ?></p>
				<table>
					<thead><tr><th><?php esc_html_e( 'Tab', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Zweck', 'immo-manager' ); ?></th></tr></thead>
					<tbody>
						<tr><td><strong>⚙️ <?php esc_html_e( 'Allgemein', 'immo-manager' ); ?></strong></td><td><?php esc_html_e( 'Währung, Symbol, Position, Dezimalen, Trennzeichen, Items pro Seite, Default-View (grid/list)', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>🎨 <?php esc_html_e( 'Design & Layout', 'immo-manager' ); ?></strong></td><td><?php esc_html_e( 'Farben, Schriften, Border-Radius, Card-Stil, Default-Detail-Layout, Default-Galerie, Hero-Stil', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>🧩 <?php esc_html_e( 'Module', 'immo-manager' ); ?></strong></td><td><?php esc_html_e( 'An-/Aus-Schalter für Anfragen, Karten, Galerie, Schema.org, Demo-Daten etc.', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>📧 <?php esc_html_e( 'Kontakt', 'immo-manager' ); ?></strong></td><td><?php esc_html_e( 'Globaler Empfänger für Anfragen, Mail-Template, Reply-To-Logik', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>🗺️ <?php esc_html_e( 'Karten', 'immo-manager' ); ?></strong></td><td><?php esc_html_e( 'Karten-Provider (Leaflet/OSM oder Google Maps), API-Keys, Default-Zoom', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>🧮 <?php esc_html_e( 'Rechner', 'immo-manager' ); ?></strong></td><td><?php esc_html_e( 'Alle Sätze (Grunderwerbsteuer/Grundbuch/Notar/Provision/USt), Posten-Toggles, Notar-Modus, Finanz-Defaults, Tilgungsplan-Toggle', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>🔌 <?php esc_html_e( 'API & Integration', 'immo-manager' ); ?></strong></td><td><?php esc_html_e( 'API-Key generieren, CORS-Origins, Webhook-URL, externe Tracking-IDs', 'immo-manager' ); ?></td></tr>
					</tbody>
				</table>
			</div>

			<!-- 11. API -->
			<div class="immo-help-section" id="help-api">
				<h2>🔌 <?php esc_html_e( '11. REST-API Referenz', 'immo-manager' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Alle Daten sind via JSON-API abrufbar — perfekt für Headless-Setups, mobile Apps oder externe Portale.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( 'Grundlagen', 'immo-manager' ); ?></h3>
				<p><strong><?php esc_html_e( 'Basis-URL:', 'immo-manager' ); ?></strong> <code><?php echo esc_url( $api_url ); ?></code></p>

				<h4><?php esc_html_e( 'Authentifizierung', 'immo-manager' ); ?></h4>
				<p><?php esc_html_e( 'Lese-Endpunkte (GET) sind öffentlich. Schreibende Endpunkte (POST /inquiries) verlangen einen API-Key, sofern in den Settings konfiguriert.', 'immo-manager' ); ?></p>
				<pre><code>X-Immo-API-Key: DEIN_GENERIERTER_API_KEY</code></pre>
				<p><a href="<?php echo esc_url( $settings_url . '#tab-api' ); ?>"><?php esc_html_e( '→ Hier API-Key generieren', 'immo-manager' ); ?></a></p>

				<h4><?php esc_html_e( 'CORS', 'immo-manager' ); ?></h4>
				<p><?php esc_html_e( 'Erlaubte Frontend-Domains in den Settings unter "Erlaubte Origins (CORS)" eintragen. Wildcard ', 'immo-manager' ); ?><code>*</code><?php esc_html_e( ' für rein öffentliche APIs zulässig.', 'immo-manager' ); ?></p>

				<h3 style="margin-top: 2.5rem;"><?php esc_html_e( 'Endpunkt-Übersicht', 'immo-manager' ); ?></h3>
				<table>
					<thead><tr><th><?php esc_html_e( 'Methode + Pfad', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Beschreibung', 'immo-manager' ); ?></th></tr></thead>
					<tbody>
						<tr><td><code>GET /properties</code></td><td><?php esc_html_e( 'Paginierte, gefilterte Liste aller Immobilien', 'immo-manager' ); ?></td></tr>
						<tr><td><code>GET /properties/{id}</code></td><td><?php esc_html_e( 'Detail einer Immobilie inkl. Galerie + Beschreibung', 'immo-manager' ); ?></td></tr>
						<tr><td><code>GET /properties/{id}/similar</code></td><td><?php esc_html_e( 'Ähnliche Immobilien (gleiche Region/Preis-Range)', 'immo-manager' ); ?></td></tr>
						<tr><td><code>GET /projects</code></td><td><?php esc_html_e( 'Liste aller Bauprojekte', 'immo-manager' ); ?></td></tr>
						<tr><td><code>GET /projects/{id}</code></td><td><?php esc_html_e( 'Detail eines Bauprojekts', 'immo-manager' ); ?></td></tr>
						<tr><td><code>GET /projects/{id}/units</code></td><td><?php esc_html_e( 'Wohneinheiten eines Projekts inkl. Stats. Filter: ', 'immo-manager' ); ?><code>status</code> (einzeln oder kommagetrennt: <code>available,reserved</code>), <code>orderby</code>, <code>limit</code></td></tr>
						<tr><td><code>GET /projects/by-slug/{slug}/units</code></td><td><?php esc_html_e( 'Wohneinheiten per Projekt-Slug — gleiche Parameter wie ID-Variante', 'immo-manager' ); ?></td></tr>
						<tr><td><code>GET /regions</code></td><td><?php esc_html_e( 'Alle Bundesländer (für Filter-Dropdowns)', 'immo-manager' ); ?></td></tr>
						<tr><td><code>GET /regions/{state}/districts</code></td><td><?php esc_html_e( 'Bezirke eines Bundeslandes', 'immo-manager' ); ?></td></tr>
						<tr><td><code>GET /features</code></td><td><?php esc_html_e( 'Ausstattungs-Tags gruppiert nach Kategorien', 'immo-manager' ); ?></td></tr>
						<tr><td><code>GET /settings/public</code></td><td><?php esc_html_e( 'Öffentliche Settings (Währung, Farben, Karten-Config)', 'immo-manager' ); ?></td></tr>
						<tr><td><code>GET /search</code></td><td><?php esc_html_e( 'Volltextsuche über Properties + Projects', 'immo-manager' ); ?></td></tr>
						<tr><td><code>POST /inquiries</code></td><td><?php esc_html_e( 'Anfrage einreichen (API-Key erforderlich)', 'immo-manager' ); ?></td></tr>
					</tbody>
				</table>

				<h3 style="margin-top: 2.5rem;"><code>GET /properties</code> — <?php esc_html_e( 'Filterparameter', 'immo-manager' ); ?></h3>
				<table>
					<thead><tr><th><?php esc_html_e( 'Parameter', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Typ', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Beschreibung', 'immo-manager' ); ?></th></tr></thead>
					<tbody>
						<tr><td><code>per_page</code></td><td>integer</td><td><?php esc_html_e( '1–50, Default: 12', 'immo-manager' ); ?></td></tr>
						<tr><td><code>page</code></td><td>integer</td><td><?php esc_html_e( 'Seitenzahl, Default: 1', 'immo-manager' ); ?></td></tr>
						<tr><td><code>orderby</code></td><td>string</td><td><code>newest</code> | <code>price_asc</code> | <code>price_desc</code> | <code>area_desc</code></td></tr>
						<tr><td><code>status</code></td><td>string</td><td><code>available</code> | <code>reserved</code> | <code>sold</code> | <code>rented</code></td></tr>
						<tr><td><code>mode</code></td><td>string</td><td><code>sale</code> | <code>rent</code></td></tr>
						<tr><td><code>type</code></td><td>string</td><td><?php esc_html_e( 'Immobilientyp, z. B. ', 'immo-manager' ); ?><code>Wohnung</code></td></tr>
						<tr><td><code>region_state</code></td><td>string</td><td><?php esc_html_e( 'Bundesland-Key, z. B. ', 'immo-manager' ); ?><code>steiermark</code></td></tr>
						<tr><td><code>region_district</code></td><td>string</td><td><?php esc_html_e( 'Bezirk-Key', 'immo-manager' ); ?></td></tr>
						<tr><td><code>price_min</code> / <code>price_max</code></td><td>number</td><td><?php esc_html_e( 'Preisspanne', 'immo-manager' ); ?></td></tr>
						<tr><td><code>area_min</code> / <code>area_max</code></td><td>number</td><td><?php esc_html_e( 'Flächenspanne (m²)', 'immo-manager' ); ?></td></tr>
						<tr><td><code>rooms</code></td><td>string</td><td><?php esc_html_e( 'Komma-Liste, z. B. ', 'immo-manager' ); ?><code>2,3</code></td></tr>
						<tr><td><code>project_id</code></td><td>integer</td><td><?php esc_html_e( 'Nur Properties dieses Bauprojekts', 'immo-manager' ); ?></td></tr>
						<tr><td><code>search</code></td><td>string</td><td><?php esc_html_e( 'Volltextsuche in Titel/Beschreibung', 'immo-manager' ); ?></td></tr>
						<tr><td><code>ids</code> <span class="immo-help-badge"><?php esc_html_e( 'Neu', 'immo-manager' ); ?></span></td><td>string</td><td><?php esc_html_e( 'ID-Liste (Komma- oder Semikolon-getrennt, z. B. ', 'immo-manager' ); ?><code>123,456,789</code><?php esc_html_e( '). Liefert genau diese Properties in der angegebenen Reihenfolge — ideal für Referenzlisten oder kuratierte Embeds. ', 'immo-manager' ); ?><code>per_page</code><?php esc_html_e( ' wird automatisch auf die Listengröße gesetzt (Hard-Cap: 100 IDs). Mit ', 'immo-manager' ); ?><code>status</code><?php esc_html_e( ' kombinierbar.', 'immo-manager' ); ?></td></tr>
					</tbody>
				</table>

				<h4><?php esc_html_e( 'Beispiel-Aufruf', 'immo-manager' ); ?></h4>
				<pre><code>fetch('<?php echo esc_url( $api_url ); ?>/properties?mode=sale&region_state=steiermark&price_max=500000&orderby=price_asc')
  .then( r =&gt; r.json() )
  .then( data =&gt; {
    console.log( data.properties );      // Array
    console.log( data.pagination );      // { total, page, total_pages }
  } );</code></pre>

				<h4><?php esc_html_e( 'Beispiel: kuratierte Liste per ID', 'immo-manager' ); ?></h4>
				<pre><code>// Genau diese drei Properties in der angegebenen Reihenfolge:
fetch('<?php echo esc_url( $api_url ); ?>/properties?ids=42,17,93')
  .then( r =&gt; r.json() )
  .then( data =&gt; console.log( data.properties ) );

// Auch Semikolon erlaubt, optional Status-Filter darüber legen:
fetch('<?php echo esc_url( $api_url ); ?>/properties?ids=42;17;93&status=available,reserved')</code></pre>

				<h3 style="margin-top: 2.5rem;"><code>POST /inquiries</code></h3>
				<p><?php esc_html_e( 'Anfrage einreichen. Erfordert API-Key, falls in Settings aktiviert.', 'immo-manager' ); ?></p>
				<table>
					<thead><tr><th><?php esc_html_e( 'Parameter', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Typ', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Pflicht', 'immo-manager' ); ?></th></tr></thead>
					<tbody>
						<tr><td><code>property_id</code></td><td>integer</td><td>✔️</td></tr>
						<tr><td><code>unit_id</code></td><td>integer</td><td>—</td></tr>
						<tr><td><code>inquirer_name</code></td><td>string</td><td>✔️</td></tr>
						<tr><td><code>inquirer_email</code></td><td>string</td><td>✔️</td></tr>
						<tr><td><code>inquirer_phone</code></td><td>string</td><td>—</td></tr>
						<tr><td><code>inquirer_message</code></td><td>string</td><td>—</td></tr>
						<tr><td><code>consent</code></td><td>boolean</td><td>✔️ (DSGVO)</td></tr>
					</tbody>
				</table>

				<h4><?php esc_html_e( 'Beispiel-Aufruf', 'immo-manager' ); ?></h4>
				<pre><code>fetch('<?php echo esc_url( $api_url ); ?>/inquiries', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Immo-API-Key': 'DEIN_API_KEY'
  },
  body: JSON.stringify({
    property_id:      123,
    inquirer_name:    'Max Mustermann',
    inquirer_email:   'max@example.com',
    inquirer_message: 'Ich interessiere mich für das Objekt.',
    consent:          true
  })
}).then( r =&gt; r.json() ).then( data =&gt; console.log( data.message ) );</code></pre>

				<h3 style="margin-top: 2.5rem;"><code>GET /projects/{id|slug}/units</code> <span class="immo-help-badge"><?php esc_html_e( 'Neu', 'immo-manager' ); ?></span></h3>
				<p><?php esc_html_e( 'Liefert die Wohneinheiten eines Bauprojekts isoliert vom Project-Hauptobjekt — ideal für externe Embeds (Headless, Cross-Site-Widgets).', 'immo-manager' ); ?></p>
				<table>
					<thead><tr><th><?php esc_html_e( 'Parameter', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Typ', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Beschreibung', 'immo-manager' ); ?></th></tr></thead>
					<tbody>
						<tr><td><code>status</code></td><td>string</td><td><?php esc_html_e( 'Einzeln oder kommagetrennt: ', 'immo-manager' ); ?><code>available</code>, <code>reserved</code>, <code>sold</code>, <code>rented</code> (z. B. <code>available,reserved</code>)</td></tr>
						<tr><td><code>orderby</code></td><td>string</td><td><code>unit_number</code> | <code>floor</code> | <code>area</code> | <code>price</code></td></tr>
						<tr><td><code>limit</code></td><td>integer</td><td><?php esc_html_e( 'Maximale Anzahl Treffer (0 = alle, Default 0)', 'immo-manager' ); ?></td></tr>
					</tbody>
				</table>
				<h4><?php esc_html_e( 'Antwort-Format', 'immo-manager' ); ?></h4>
				<pre><code>{
  "project_id":     123,
  "applied_status": ["available", "reserved"],
  "units":          [ /* pro Einheit: id, unit_number, status, area, rooms, floor,
                         price_formatted, property: { title, slug, permalink, image, ... } */ ],
  "stats":          { "available": 4, "reserved": 1, "sold": 2, "rented": 0, "total": 7 }
}</code></pre>
				<p><?php esc_html_e( 'Wichtig: ', 'immo-manager' ); ?><code>property.slug</code><?php esc_html_e( ' ist enthalten — Headless-Konsumenten können daraus eine eigene Detail-URL bauen (z. B. ', 'immo-manager' ); ?><code>https://kunde.example.com/immobilie/{slug}</code>).</p>
				<p><?php esc_html_e( 'Hinweis: ', 'immo-manager' ); ?><code>stats</code><?php esc_html_e( ' enthält IMMER alle Status-Counts (auch wenn nach status gefiltert wurde) — so bleibt der Gesamtüberblick erhalten.', 'immo-manager' ); ?></p>

				<h4 style="margin-top: 1.5rem;"><?php esc_html_e( 'Wohneinheiten-Zusatzfelder (ab 1.1.0)', 'immo-manager' ); ?></h4>
				<p><?php esc_html_e( 'Pro Einheit zusätzlich:', 'immo-manager' ); ?> <code>balcony_area</code>, <code>loggia_area</code>, <code>garden_area</code>, <code>cellar_area</code> <?php esc_html_e( '(alle in m², default 0). Stellplatz-Inkludierung als', 'immo-manager' ); ?> <code>parking.garage_count</code>, <code>parking.outdoor_count</code> <?php esc_html_e( 'sowie Override-Preise', 'immo-manager' ); ?> <code>parking.garage_price_override</code> / <code>parking.outdoor_price_override</code> <?php esc_html_e( '(NULL = Projekt-Default).', 'immo-manager' ); ?></p>
				<p><?php esc_html_e( 'Das Bauprojekt liefert in', 'immo-manager' ); ?> <code>meta.parking</code> <?php esc_html_e( 'die zentrale Stellplatz-Konfiguration:', 'immo-manager' ); ?> <code>garage.{available, total, price, required}</code>, <code>outdoor.{available, total, price, required}</code>, <code>notes</code>.</p>

				<h4><?php esc_html_e( 'Beispiel-Aufruf', 'immo-manager' ); ?></h4>
				<pre><code>fetch('<?php echo esc_url( $api_url ); ?>/projects/by-slug/bauprojekt-graz/units?status=available,reserved&orderby=price&limit=10')
  .then( r =&gt; r.json() )
  .then( data =&gt; {
    console.log( data.units );  // gefilterte Wohneinheiten
    console.log( data.stats );  // alle Counts unabhängig vom Filter
  } );</code></pre>

				<div class="immo-help-callout immo-help-callout--info">
					<strong>💡 <?php esc_html_e( 'Tipp:', 'immo-manager' ); ?></strong>
					<?php esc_html_e( 'Alle Endpunkte liefern ein einheitliches JSON-Format: ', 'immo-manager' ); ?>
					<code>{ properties: [...], pagination: {...} }</code>
					<?php esc_html_e( ' bzw. einen einzelnen Datensatz. Fehlerantworten folgen dem WP-REST-Standard mit ', 'immo-manager' ); ?>
					<code>{ code, message, data }</code>.
				</div>

				<h3 style="margin-top: 2.5rem;"><?php esc_html_e( 'Cross-Site-Einbindung mit dem ImmoClient', 'immo-manager' ); ?> <span class="immo-help-badge"><?php esc_html_e( 'Companion-Plugin', 'immo-manager' ); ?></span></h3>
				<p><?php esc_html_e( 'Statt einen eigenen Headless-Konsumenten zu schreiben, kann auf einer beliebigen externen WordPress-Site das Companion-Plugin "ImmoClient" installiert werden. Es spricht die hier dokumentierte REST-API automatisch an, rendert die gleichen Listen, Detailseiten und Anfrage-Formulare und leitet eingehende Anfragen mit ', 'immo-manager' ); ?><code>source_url</code><?php esc_html_e( ' an den Manager weiter — sodass alle Anfragen zentral hier in der Anfragen-Übersicht landen, mit Quellnachweis der Site, von der sie kamen.', 'immo-manager' ); ?></p>
				<p><strong><?php esc_html_e( 'Verfügbare Shortcodes auf der externen Site:', 'immo-manager' ); ?></strong></p>
				<ul>
					<li><code>[immo_list]</code> — <?php esc_html_e( 'volle Liste mit Filter-Bar (wie Manager-Frontend)', 'immo-manager' ); ?></li>
					<li><code>[immo_list ids="123,456,789"]</code> <span class="immo-help-badge"><?php esc_html_e( 'Neu', 'immo-manager' ); ?></span> — <?php esc_html_e( 'kuratierte Liste in genau dieser Reihenfolge (Komma- oder Semikolon-getrennt). Filter-Bar wird automatisch ausgeblendet — ideal als Referenzliste oder Promo-Block.', 'immo-manager' ); ?></li>
					<li><code>[immo_list ids="123,456,789" layout="slider"]</code> <span class="immo-help-badge"><?php esc_html_e( 'Neu', 'immo-manager' ); ?></span> — <?php esc_html_e( 'gleiche Liste als Splide-Slider, optional mit ', 'immo-manager' ); ?><code>autoplay</code>, <code>per_page</code>, <code>per_page_md</code>, <code>per_page_sm</code>, <code>gap</code>, <code>loop</code>.</li>
					<li><code>[immo_property id="123"]</code> / <code>[immo_property slug="…"]</code> — <?php esc_html_e( 'einzelne Immobilie als Block', 'immo-manager' ); ?></li>
					<li><code>[immo_project id="45"]</code> / <code>[immo_project slug="…"]</code> — <?php esc_html_e( 'volles Bauprojekt mit Galerie und Wohneinheiten', 'immo-manager' ); ?></li>
					<li><code>[immo_units project_slug="…" layout="grid|table|list"]</code> — <?php esc_html_e( 'nur die Wohneinheiten eines Bauprojekts', 'immo-manager' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'Konfiguration auf der externen Site: API-Basis-URL und (optional) API-Key in den ImmoClient-Einstellungen eintragen. Bei aktivierter Key-Prüfung im Manager (siehe ', 'immo-manager' ); ?><a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Einstellungen → API', 'immo-manager' ); ?></a><?php esc_html_e( ') muss derselbe Key dort hinterlegt sein, sonst werden Anfragen mit HTTP 401 abgewiesen.', 'immo-manager' ); ?></p>
				<div class="immo-help-callout immo-help-callout--ok">
					<strong>✔️ <?php esc_html_e( 'Eigenständigkeit bleibt erhalten:', 'immo-manager' ); ?></strong>
					<?php esc_html_e( 'Die externe Site behält ihre eigenen Routen ', 'immo-manager' ); ?><code>/immobilie/{slug}</code><?php esc_html_e( ' und ', 'immo-manager' ); ?><code>/bauprojekt/{slug}</code><?php esc_html_e( ', eigenes Branding (Farben, Logo) und eigenes Mail-Sending. Der Manager bekommt nur eine zusätzliche, gespiegelte Anfrage zur zentralen Auswertung.', 'immo-manager' ); ?>
				</div>
			</div>

			<!-- 12. EMBED-WIDGETS -->
			<div class="immo-help-section" id="help-embed">
				<h2>🧩 <?php esc_html_e( '12. Embed-Widgets für externe Webseiten', 'immo-manager' ); ?> <span class="immo-help-badge"><?php esc_html_e( 'Neu', 'immo-manager' ); ?></span></h2>
				<p class="description"><?php esc_html_e( 'Mit dem Embed-Widget zeigst du deine Immobilien, Bauprojekte und Wohneinheiten auf jeder beliebigen Webseite – egal ob WordPress, Webflow, Wix, Jimdo, Squarespace, Typo3, Joomla, Shopify oder eine handgeschriebene HTML-Seite. Die Daten werden live aus dieser Installation geladen; Änderungen hier erscheinen sofort auf allen eingebundenen Seiten.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( 'So funktioniert es', 'immo-manager' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Ein kleines JavaScript (immo-embed.js, ca. 40 KB, keine Abhängigkeiten) wird einmal pro Seite eingebunden.', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Überall, wo Inhalte erscheinen sollen, steht ein Platzhalter-Element mit dem Attribut data-immo-embed.', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Das Script holt die Daten über die öffentliche REST-API dieser Installation und rendert sie isoliert in einem Shadow DOM – das CSS der Zielseite kann nichts kaputt machen und umgekehrt.', 'immo-manager' ); ?></li>
					<li><?php esc_html_e( 'Farben kommen automatisch aus deinen Design-Einstellungen (Primär-/Akzentfarbe) oder werden per Attribut überschrieben.', 'immo-manager' ); ?></li>
				</ol>
				<div class="immo-help-callout immo-help-callout--ok">
					<strong>✅ <?php esc_html_e( 'Keine Installation auf der Zielseite nötig.', 'immo-manager' ); ?></strong>
					<?php esc_html_e( 'Es braucht weder WordPress noch ein Plugin dort – nur die Möglichkeit, ein HTML-Snippet einzufügen. Eine eigenständige Anleitung mit Live-Demo findest du hier:', 'immo-manager' ); ?>
					<a href="<?php echo esc_url( $manual_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $manual_url ); ?></a>
				</div>

				<h3><?php esc_html_e( 'Schritt 1: Script einbinden (einmal pro Seite)', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Am besten kurz vor dem schließenden </body>-Tag oder im Footer-Bereich für „Custom Code" deines Baukastens:', 'immo-manager' ); ?></p>
				<pre><code>&lt;script src="<?php echo esc_url( $embed_url ); ?>" defer&gt;&lt;/script&gt;</code></pre>

				<h3><?php esc_html_e( 'Schritt 2: Platzhalter einfügen', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Ein &lt;div&gt; mit data-immo-embed pro Widget. Beliebig viele Widgets pro Seite sind möglich:', 'immo-manager' ); ?></p>
				<pre><code>&lt;!-- Bauprojekte als Grid, 3 Spalten --&gt;
&lt;div data-immo-embed="projects" data-columns="3" data-title="Unsere Bauprojekte"&gt;&lt;/div&gt;

&lt;!-- Wohneinheiten eines Bauprojekts (ID oder Slug) --&gt;
&lt;div data-immo-embed="units" data-project="<?php echo esc_attr( $embed_items['projects'][0]['slug'] ?? 'mein-bauprojekt' ); ?>"&gt;&lt;/div&gt;

&lt;!-- Immobilien-Grid mit Filterleiste und „Mehr laden" --&gt;
&lt;div data-immo-embed="properties" data-mode="sale" data-limit="6" data-filters="1"&gt;&lt;/div&gt;

&lt;!-- Einzelne Immobilie / einzelnes Bauprojekt als Detailkarte --&gt;
&lt;div data-immo-embed="property" data-slug="<?php echo esc_attr( $embed_items['properties'][0]['slug'] ?? 'meine-immobilie' ); ?>"&gt;&lt;/div&gt;
&lt;div data-immo-embed="project" data-id="<?php echo esc_attr( (string) ( $embed_items['projects'][0]['id'] ?? 123 ) ); ?>"&gt;&lt;/div&gt;</code></pre>

				<h3><?php esc_html_e( 'Snippet-Generator mit Live-Vorschau', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Widget zusammenklicken, Snippet kopieren, in die Zielseite einfügen – fertig. Die Vorschau zeigt exakt, was später auf der externen Seite erscheint.', 'immo-manager' ); ?></p>
				<div class="immo-embed-gen" id="immo-embed-gen" data-embed-url="<?php echo esc_url( $embed_url ); ?>" data-items="<?php echo esc_attr( wp_json_encode( $embed_items ) ); ?>">
					<div class="immo-embed-gen-grid">
						<label><?php esc_html_e( 'Widget-Typ', 'immo-manager' ); ?>
							<select name="type">
								<option value="projects"><?php esc_html_e( 'Bauprojekte (Grid)', 'immo-manager' ); ?></option>
								<option value="units"><?php esc_html_e( 'Wohneinheiten eines Bauprojekts', 'immo-manager' ); ?></option>
								<option value="project"><?php esc_html_e( 'Bauprojekt-Detail + Einheiten', 'immo-manager' ); ?></option>
								<option value="properties"><?php esc_html_e( 'Immobilien (Grid)', 'immo-manager' ); ?></option>
								<option value="property"><?php esc_html_e( 'Immobilien-Detailkarte', 'immo-manager' ); ?></option>
							</select>
						</label>
						<label data-for="units project"><?php esc_html_e( 'Bauprojekt', 'immo-manager' ); ?>
							<select name="projectRef"></select>
						</label>
						<label data-for="property"><?php esc_html_e( 'Immobilie', 'immo-manager' ); ?>
							<select name="propertyRef"></select>
						</label>
						<label data-for="projects properties"><?php esc_html_e( 'Spalten', 'immo-manager' ); ?>
							<select name="columns"><option>1</option><option>2</option><option selected>3</option><option>4</option></select>
						</label>
						<label data-for="projects properties"><?php esc_html_e( 'Anzahl', 'immo-manager' ); ?>
							<input type="number" name="limit" min="1" max="50" value="6">
						</label>
						<label data-for="properties"><?php esc_html_e( 'Angebot', 'immo-manager' ); ?>
							<select name="mode"><option value=""><?php esc_html_e( 'Alle', 'immo-manager' ); ?></option><option value="sale"><?php esc_html_e( 'Kaufen', 'immo-manager' ); ?></option><option value="rent"><?php esc_html_e( 'Mieten', 'immo-manager' ); ?></option></select>
						</label>
						<label data-for="properties"><?php esc_html_e( 'Filterleiste', 'immo-manager' ); ?>
							<select name="filters"><option value=""><?php esc_html_e( 'Aus', 'immo-manager' ); ?></option><option value="1"><?php esc_html_e( 'An', 'immo-manager' ); ?></option></select>
						</label>
						<label data-for="units project"><?php esc_html_e( 'Status', 'immo-manager' ); ?>
							<select name="status"><option value=""><?php esc_html_e( 'Alle', 'immo-manager' ); ?></option><option value="available"><?php esc_html_e( 'Nur verfügbar', 'immo-manager' ); ?></option><option value="available,reserved"><?php esc_html_e( 'Verfügbar + reserviert', 'immo-manager' ); ?></option></select>
						</label>
						<label><?php esc_html_e( 'Überschrift (optional)', 'immo-manager' ); ?>
							<input type="text" name="title" placeholder="<?php esc_attr_e( 'z. B. Unsere Bauprojekte', 'immo-manager' ); ?>">
						</label>
						<label><?php esc_html_e( 'Links öffnen', 'immo-manager' ); ?>
							<select name="target"><option value=""><?php esc_html_e( 'Im selben Tab', 'immo-manager' ); ?></option><option value="_blank"><?php esc_html_e( 'In neuem Tab', 'immo-manager' ); ?></option></select>
						</label>
						<label class="immo-embed-gen-wide"><?php esc_html_e( 'Detail-Links auf eigene Seite umleiten (optional)', 'immo-manager' ); ?>
							<input type="text" name="linkTemplate" placeholder="https://kunde.at/immobilie/{slug}">
						</label>
					</div>
					<p><strong><?php esc_html_e( 'Dein Snippet:', 'immo-manager' ); ?></strong> <button type="button" class="button button-small immo-embed-copy"><?php esc_html_e( 'In Zwischenablage kopieren', 'immo-manager' ); ?></button> <span class="immo-embed-copied" hidden><?php esc_html_e( 'Kopiert ✓', 'immo-manager' ); ?></span></p>
					<textarea class="immo-embed-snippet large-text code" rows="7" readonly></textarea>
					<p><strong><?php esc_html_e( 'Live-Vorschau:', 'immo-manager' ); ?></strong></p>
					<div class="immo-embed-preview"></div>
				</div>
				<style>
					.immo-embed-gen { background:#f9fafb; border:1px solid #e5e7eb; padding:16px; border-radius:6px; }
					.immo-embed-gen-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px 14px; margin-bottom:12px; }
					.immo-embed-gen-grid label { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:600; color:#374151; }
					.immo-embed-gen-grid label[hidden] { display:none; }
					.immo-embed-gen-grid select, .immo-embed-gen-grid input { width:100%; }
					.immo-embed-gen-wide { grid-column: 1 / -1; }
					.immo-embed-preview { background:#fff; border:1px dashed #cbd5e1; padding:16px; border-radius:6px; min-height:60px; }
				</style>
				<script src="<?php echo esc_url( $embed_url ); ?>" defer></script>
				<script>
				(function () {
					var gen = document.getElementById('immo-embed-gen');
					if (!gen) { return; }
					var items = {};
					try { items = JSON.parse(gen.getAttribute('data-items') || '{}'); } catch (e) {}
					var embedUrl = gen.getAttribute('data-embed-url');
					var f = function (n) { return gen.querySelector('[name="' + n + '"]'); };
					var snippetEl = gen.querySelector('.immo-embed-snippet');
					var preview = gen.querySelector('.immo-embed-preview');

					function fill(sel, list, fallback) {
						sel.innerHTML = '';
						(list || []).forEach(function (it) {
							var o = document.createElement('option');
							o.value = it.slug || String(it.id);
							o.textContent = it.title + ' (' + it.slug + ')';
							sel.appendChild(o);
						});
						if (!sel.options.length) { var o2 = document.createElement('option'); o2.value = fallback; o2.textContent = fallback; sel.appendChild(o2); }
					}
					fill(f('projectRef'), items.projects, 'mein-bauprojekt');
					fill(f('propertyRef'), items.properties, 'meine-immobilie');

					function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }

					function build() {
						var type = f('type').value;
						gen.querySelectorAll('label[data-for]').forEach(function (l) {
							l.hidden = l.getAttribute('data-for').split(' ').indexOf(type) === -1;
						});
						var attrs = { 'data-immo-embed': type };
						if (type === 'units') { attrs['data-project'] = f('projectRef').value; }
						if (type === 'project') { attrs['data-slug'] = f('projectRef').value; }
						if (type === 'property') { attrs['data-slug'] = f('propertyRef').value; }
						if (type === 'projects' || type === 'properties') { attrs['data-columns'] = f('columns').value; attrs['data-limit'] = f('limit').value; }
						if (type === 'properties') { if (f('mode').value) { attrs['data-mode'] = f('mode').value; } if (f('filters').value) { attrs['data-filters'] = '1'; } }
						if ((type === 'units' || type === 'project') && f('status').value) { attrs['data-status'] = f('status').value; }
						if (f('title').value) { attrs['data-title'] = f('title').value; }
						if (f('target').value) { attrs['data-target'] = f('target').value; }
						if (f('linkTemplate').value) { attrs['data-link-template'] = f('linkTemplate').value; }

						var attrStr = Object.keys(attrs).map(function (k) { return k + '="' + esc(attrs[k]) + '"'; }).join(' ');
						snippetEl.value = '<!-- 1) einmal pro Seite, z. B. vor </body> -->\n<script src="' + embedUrl + '" defer><\/script>\n\n<!-- 2) dort einfügen, wo das Widget erscheinen soll -->\n<div ' + attrStr + '></div>';

						// Live-Vorschau neu rendern.
						preview.innerHTML = '';
						var el = document.createElement('div');
						Object.keys(attrs).forEach(function (k) { el.setAttribute(k, attrs[k]); });
						preview.appendChild(el);
						if (window.ImmoEmbed) { window.ImmoEmbed.render(el); } else { window.addEventListener('load', function () { if (window.ImmoEmbed) { window.ImmoEmbed.render(el); } }); }
					}

					gen.addEventListener('change', build);
					var t;
					gen.addEventListener('input', function (e) { if (e.target.tagName === 'INPUT') { clearTimeout(t); t = setTimeout(build, 400); } });
					gen.querySelector('.immo-embed-copy').addEventListener('click', function () {
						var done = function () { var c = gen.querySelector('.immo-embed-copied'); c.hidden = false; setTimeout(function () { c.hidden = true; }, 2000); };
						if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(snippetEl.value).then(done); }
						else { snippetEl.select(); document.execCommand('copy'); done(); }
					});
					build();
				})();
				</script>

				<h3><?php esc_html_e( 'Widget-Typen und Attribute', 'immo-manager' ); ?></h3>
				<table>
					<thead><tr><th>data-immo-embed</th><th><?php esc_html_e( 'Zeigt', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Attribute', 'immo-manager' ); ?></th></tr></thead>
					<tbody>
						<tr><td><code>properties</code></td><td><?php esc_html_e( 'Immobilien-Grid mit optionaler Filterleiste (Angebot, Bundesland, Zimmer, Preis) und „Mehr laden"', 'immo-manager' ); ?></td><td><code>data-limit</code> (1–50), <code>data-columns</code> (1–4), <code>data-status</code>, <code>data-mode</code> (sale|rent), <code>data-type</code>, <code>data-region</code>, <code>data-orderby</code> (newest|price_asc|price_desc|area_desc), <code>data-project</code>, <code>data-price-min</code>/<code>-max</code>, <code>data-rooms</code>, <code>data-filters="1"</code>, <code>data-pagination="0"</code></td></tr>
						<tr><td><code>projects</code></td><td><?php esc_html_e( 'Bauprojekte als Cards mit Status, Einheiten-Statistik und Flächenspanne', 'immo-manager' ); ?></td><td><code>data-limit</code>, <code>data-columns</code>, <code>data-status</code> (planning|building|completed)</td></tr>
						<tr><td><code>units</code></td><td><?php esc_html_e( 'Wohneinheiten-Tabelle eines Bauprojekts mit Status-Filter-Pills', 'immo-manager' ); ?></td><td><code>data-project</code> (ID oder Slug, Pflicht), <code>data-status</code>, <code>data-orderby</code> (unit_number|floor|area|price|rooms|status), <code>data-limit</code>, <code>data-stats="0"</code></td></tr>
						<tr><td><code>property</code></td><td><?php esc_html_e( 'Detailkarte einer Immobilie (Bild, Galerie, Preis, Eckdaten, Energieausweis, Ausstattung, Beschreibung, Kontakt)', 'immo-manager' ); ?></td><td><code>data-id</code> oder <code>data-slug</code>, <code>data-description="0"</code></td></tr>
						<tr><td><code>project</code></td><td><?php esc_html_e( 'Bauprojekt-Kopf mit Statistiken plus Wohneinheiten-Tabelle', 'immo-manager' ); ?></td><td><code>data-id</code> oder <code>data-slug</code>, <code>data-status</code>, <code>data-description="0"</code></td></tr>
						<tr><td><em><?php esc_html_e( 'alle Typen', 'immo-manager' ); ?></em></td><td></td><td><code>data-title</code>, <code>data-primary</code>, <code>data-accent</code>, <code>data-radius</code> (z. B. 8 oder 8px), <code>data-target="_blank"</code>, <code>data-lang="de|en"</code>, <code>data-link-template</code>, <code>data-project-link-template</code>, <code>data-no-shadow="1"</code>, <code>data-api</code></td></tr>
					</tbody>
				</table>
				<p><?php esc_html_e( 'Attribute können auch global am <script>-Tag gesetzt werden (data-api, data-lang, data-primary, data-accent, data-radius, data-target, data-link-template) und gelten dann für alle Widgets der Seite.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( 'Detail-Links auf die Kundenseite umleiten', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Standardmäßig führen „Details ansehen"-Links zur Detailseite dieser WordPress-Installation. Soll die externe Seite eigene Detailseiten haben, gibst du ein Link-Template mit Platzhaltern {slug} und {id} an:', 'immo-manager' ); ?></p>
				<pre><code>&lt;div data-immo-embed="properties" data-link-template="https://kunde.at/immobilie/{slug}"&gt;&lt;/div&gt;
&lt;!-- und auf https://kunde.at/immobilie/… dann: --&gt;
&lt;div data-immo-embed="property" data-slug="SLUG-AUS-DER-URL"&gt;&lt;/div&gt;</code></pre>

				<h3><?php esc_html_e( 'Schritt-für-Schritt nach Plattform (ohne WordPress)', 'immo-manager' ); ?></h3>
				<table>
					<thead><tr><th><?php esc_html_e( 'Plattform', 'immo-manager' ); ?></th><th><?php esc_html_e( 'So fügst du Script und Platzhalter ein', 'immo-manager' ); ?></th></tr></thead>
					<tbody>
						<tr><td><strong><?php esc_html_e( 'Reines HTML / statische Seite', 'immo-manager' ); ?></strong></td><td><?php esc_html_e( 'Script-Zeile vor </body> einfügen, Platzhalter-&lt;div&gt; an der gewünschten Stelle im HTML. Funktioniert auch bei Hugo, Jekyll, Astro, Eleventy oder Netlify/Vercel-Static-Sites.', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>Webflow</strong></td><td><?php esc_html_e( 'Element „Embed" (Code Embed) auf die Seite ziehen und den Platzhalter-&lt;div&gt; hineinkopieren. Die Script-Zeile unter Project Settings → Custom Code → „Footer Code" eintragen (gilt dann für alle Seiten) – oder direkt mit in dasselbe Embed-Element.', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>Wix</strong></td><td><?php esc_html_e( 'Hinzufügen → Einbetten → „HTML-Code einbetten" → Modus „Code": beide Teile (Script + div) in das Feld kopieren. Hinweis: Wix rendert HTML-Embeds in einem iFrame mit fester Höhe – Höhe im Editor so groß wählen, dass das Widget Platz hat. Alternative ohne iFrame: Wix Studio/Velo „Custom Element" oder Settings → Custom Code (Script) + HTML-Embed (div).', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>Jimdo</strong></td><td><?php esc_html_e( 'Element „Widget/HTML" hinzufügen und Script-Zeile plus Platzhalter-&lt;div&gt; einfügen. Bei Jimdo Dolphin: „HTML"-Block.', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>Squarespace</strong></td><td><?php esc_html_e( '„Code"-Block auf der Seite einfügen (Platzhalter-div, Typ HTML). Script-Zeile unter Einstellungen → Erweitert → Code-Injection → Footer. Code-Blöcke erfordern einen Business-Plan oder höher.', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>Typo3</strong></td><td><?php esc_html_e( 'Inhaltselement „HTML" (bzw. „Reines HTML") mit dem Platzhalter-div anlegen. Script-Zeile im Fluid-Layout/Page-Template vor </body> oder per TypoScript page.includeJSFooter einbinden.', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>Joomla</strong></td><td><?php esc_html_e( 'Modul „Eigenes HTML" (Custom) mit dem Platzhalter-div anlegen; im Editor den Code-Modus nutzen, damit nichts entfernt wird (ggf. Editor „None" oder Plugin wie Sourcerer). Script-Zeile im Template (index.php) vor </body>.', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>Shopify</strong></td><td><?php esc_html_e( 'Theme anpassen → Abschnitt „Custom Liquid" hinzufügen und Script + Platzhalter-div einfügen. Für alle Seiten: Script-Zeile in theme.liquid vor </body>.', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>HubSpot CMS / Google Sites</strong></td><td><?php esc_html_e( 'HubSpot: Modul „Embed/HTML" bzw. Site-Footer-HTML. Google Sites: Einfügen → Einbetten → „Code einbetten" (läuft in einem iFrame mit fester Höhe).', 'immo-manager' ); ?></td></tr>
						<tr><td><strong>React / Vue / Angular / Next.js</strong></td><td><?php esc_html_e( 'Script einmal laden (z. B. im Root-Layout). Platzhalter-divs im JSX/Template setzen und nach dem Mounten window.ImmoEmbed.init(containerElement) aufrufen – bei Client-seitigem Routing nach jedem Seitenwechsel erneut. Bei Server-Side-Rendering nur im Browser ausführen (useEffect / onMounted).', 'immo-manager' ); ?></td></tr>
						<tr><td><strong><?php esc_html_e( 'Andere WordPress-Seite', 'immo-manager' ); ?></strong></td><td><?php esc_html_e( 'Block „Individuelles HTML" (Gutenberg) oder Elementor „HTML"-Widget mit Script + div. Für viele Seiten alternativ ein Snippet-Plugin für den Footer.', 'immo-manager' ); ?></td></tr>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'Voraussetzungen & Sicherheit', 'immo-manager' ); ?></h3>
				<ul>
					<li><strong><?php esc_html_e( 'CORS:', 'immo-manager' ); ?></strong> <?php esc_html_e( 'Die Zieldomain muss unter ', 'immo-manager' ); ?><a href="<?php echo esc_url( $settings_url . '#tab-api' ); ?>"><?php esc_html_e( 'Einstellungen → API & Integration → Erlaubte Origins', 'immo-manager' ); ?></a><?php esc_html_e( ' erlaubt sein. Standard ist * (alle Domains). Für eine feste Kundenliste eine Origin pro Zeile eintragen, z. B. https://kunde.at.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'HTTPS:', 'immo-manager' ); ?></strong> <?php esc_html_e( 'Diese Installation muss per HTTPS erreichbar sein, sonst blockieren Browser das Laden auf HTTPS-Seiten (Mixed Content).', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Nur lesender Zugriff:', 'immo-manager' ); ?></strong> <?php esc_html_e( 'Das Widget nutzt ausschließlich öffentliche GET-Endpunkte. Es enthält absichtlich kein Anfrage-Formular, weil der API-Key nie in eine fremde Seite gehört – „Details"-Links führen zur Anfrage auf dieser Installation.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Datenschutz:', 'immo-manager' ); ?></strong> <?php esc_html_e( 'Das Widget setzt keine Cookies und lädt keine Drittanbieter-Ressourcen; Bilder und Daten kommen von dieser Domain. Die Zielseite sollte das Nachladen von Inhalten von dieser Domain in ihrer Datenschutzerklärung erwähnen.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Content Security Policy:', 'immo-manager' ); ?></strong> <?php esc_html_e( 'Falls die Zielseite eine CSP nutzt, müssen script-src, connect-src und img-src diese Domain erlauben.', 'immo-manager' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Fehlersuche', 'immo-manager' ); ?></h3>
				<table>
					<thead><tr><th><?php esc_html_e( 'Symptom', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Ursache & Lösung', 'immo-manager' ); ?></th></tr></thead>
					<tbody>
						<tr><td><?php esc_html_e( 'Es erscheint gar nichts', 'immo-manager' ); ?></td><td><?php esc_html_e( 'Der Baukasten-Editor hat das <script>-Tag entfernt (normaler Text-Editor statt HTML/Code-Block) oder das Script ist nicht geladen. Browser-Konsole (F12) prüfen: dort meldet [ImmoEmbed] jede Ursache.', 'immo-manager' ); ?></td></tr>
						<tr><td><?php esc_html_e( '„Inhalte konnten nicht geladen werden"', 'immo-manager' ); ?></td><td><?php esc_html_e( 'Meist CORS (Origin nicht erlaubt), ein Caching-/Security-Plugin, das /wp-json blockiert, oder eine falsche API-URL. Direkt im Browser testen: https://DEINE-DOMAIN/wp-json/immo-manager/v1/projects muss JSON liefern. Bei abweichender REST-URL data-api am <script> setzen.', 'immo-manager' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Widget ist abgeschnitten (Wix, Google Sites)', 'immo-manager' ); ?></td><td><?php esc_html_e( 'Diese Baukästen rendern HTML-Embeds in einem iFrame fester Höhe. Höhe des Embed-Elements vergrößern oder eine Variante ohne iFrame (Custom Element / Custom Code) verwenden.', 'immo-manager' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Dynamisch eingefügte Platzhalter bleiben leer', 'immo-manager' ); ?></td><td><?php esc_html_e( 'Nach dem Einfügen window.ImmoEmbed.init(element) aufrufen; das Script scannt die Seite nur einmal beim Laden.', 'immo-manager' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Farben passen nicht zur Zielseite', 'immo-manager' ); ?></td><td><?php esc_html_e( 'data-primary, data-accent und data-radius am Element oder Script setzen; data-no-shadow="1" rendert ohne Shadow DOM, sodass das CSS der Zielseite durchgreift (dann greifen aber auch deren Resets).', 'immo-manager' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Änderungen erscheinen nicht', 'immo-manager' ); ?></td><td><?php esc_html_e( 'Ein Caching-Plugin oder CDN cached die REST-Antworten – Cache leeren bzw. /wp-json vom Cache ausnehmen.', 'immo-manager' ); ?></td></tr>
					</tbody>
				</table>
			</div>

			<div class="immo-help-section" style="background: #f9fafb; text-align: center;">
				<p style="margin:0; font-size: 0.9em; color: #6b7280;">
					<?php
					/* translators: %s: plugin version */
					printf( esc_html__( 'Immo Manager Version %s — bei Fragen oder Bugs schau in die Plugin-Repo oder kontaktiere den Entwickler.', 'immo-manager' ), esc_html( defined( 'IMMO_MANAGER_VERSION' ) ? IMMO_MANAGER_VERSION : '—' ) );
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Prüft, ob der aktuelle Admin-Screen zum Plugin gehört.
	 *
	 * @param string $hook_suffix Der aktuelle Admin-Hook-Suffix.
	 *
	 * @return bool
	 */
	private function is_plugin_screen( string $hook_suffix ): bool {
		// Alle unsere Submenüs laufen unter immo-
		if ( strpos( $hook_suffix, self::MENU_SLUG ) !== false || strpos( $hook_suffix, 'immo-' ) !== false ) {
			return true;
		}

		// Edit-Screens für unsere CPTs.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array(
			$screen->post_type,
			array( PostTypes::POST_TYPE_PROPERTY, PostTypes::POST_TYPE_PROJECT ),
			true
		) ) {
			return true;
		}

		// Wizard-Seite (Backend)
		if ( isset( $_GET['page'] ) && 'immo-wizard' === $_GET['page'] ) {
			return true;
		}

		return false;
	}

	/**
	 * CSS/JS für Admin-Bereich einbinden.
	 *
	 * @param string $hook_suffix Der aktuelle Admin-Hook-Suffix.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! $this->is_plugin_screen( $hook_suffix ) ) {
			return;
		}

		// Media-Uploader laden (für Einstellungen und Bildergalerien).
		wp_enqueue_media();

		// Admin-Basis-Styles & Color Picker.
		wp_enqueue_style(
			'immo-manager-admin',
			IMMO_MANAGER_PLUGIN_URL . 'public/css/admin.css',
			array( 'wp-color-picker' ),
			IMMO_MANAGER_VERSION
		);
		// Design-System-Variablen (--immo-*) nur auf Plugin-Screens ausgeben.
		wp_add_inline_style( 'immo-manager-admin', Plugin::instance()->get_shortcodes()->get_design_css() );

		// Metabox-Styles (nur auf CPT-Edit-Screens nötig).
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_cpt_screen = $screen && in_array(
			$screen->post_type,
			array( PostTypes::POST_TYPE_PROPERTY, PostTypes::POST_TYPE_PROJECT ),
			true
		);

		if ( $is_cpt_screen ) {
			wp_enqueue_style(
				'immo-manager-metaboxes',
				IMMO_MANAGER_PLUGIN_URL . 'public/css/metaboxes.css',
				array(),
				IMMO_MANAGER_VERSION
			);
		}

		// Admin-JS (Color Picker, Unsaved-Warning).
		wp_enqueue_script(
			'immo-manager-admin',
			IMMO_MANAGER_PLUGIN_URL . 'public/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			IMMO_MANAGER_VERSION,
			true
		);

		// Metabox-JS (Region-Cascading, Units-CRUD) – nur auf CPT-Screens.
		if ( $is_cpt_screen ) {
			wp_enqueue_script(
				'immo-manager-metaboxes',
				IMMO_MANAGER_PLUGIN_URL . 'public/js/metaboxes.js',
				array( 'jquery', 'jquery-ui-sortable' ),
				IMMO_MANAGER_VERSION,
				true
			);

			wp_localize_script(
				'immo-manager-metaboxes',
				'immoManagerAdmin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'i18n'    => array(
						'loading'       => __( 'Lade…', 'immo-manager' ),
						'district'      => __( '— Bezirk —', 'immo-manager' ),
						'addUnit'       => __( 'Wohneinheit hinzufügen', 'immo-manager' ),
						'editUnit'      => __( 'Wohneinheit bearbeiten', 'immo-manager' ),
						'confirmDelete' => __( 'Wohneinheit wirklich löschen?', 'immo-manager' ),
						'unsavedChanges' => __( 'Du hast ungespeicherte Änderungen. Seite wirklich verlassen?', 'immo-manager' ),
					),
				)
			);
		} else {
			// Basis-Lokalisierung für admin.js (Unsaved-Warning auf Settings-Seite).
			wp_localize_script(
				'immo-manager-admin',
				'immoManagerAdmin',
				array(
					'i18n' => array(
						'unsavedChanges' => __( 'Du hast ungespeicherte Änderungen. Seite wirklich verlassen?', 'immo-manager' ),
					),
				)
			);
		}
	}
}
