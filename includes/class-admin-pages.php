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
			__( 'Datum', 'immo-manager' ),
			__( 'Status', 'immo-manager' ),
			__( 'Aktionen', 'immo-manager' ),
		) as $col ) {
			echo '<th>' . esc_html( $col ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $inquiries ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'Keine Anfragen gefunden.', 'immo-manager' ) . '</td></tr>';
		} else {
			foreach ( $inquiries as $inq ) {
				$property_title = get_the_title( (int) $inq['property_id'] );
				$property_link  = get_edit_post_link( (int) $inq['property_id'] );
				echo '<tr>';
				echo '<td><strong>' . esc_html( $inq['inquirer_name'] ) . '</strong></td>';
				echo '<td><a href="mailto:' . esc_attr( $inq['inquirer_email'] ) . '">' . esc_html( $inq['inquirer_email'] ) . '</a></td>';
				echo '<td>' . esc_html( $inq['inquirer_phone'] ?: '–' ) . '</td>';
				echo '<td><a href="' . esc_url( (string) $property_link ) . '">' . esc_html( $property_title ?: '–' ) . '</a></td>';
				echo '<td><span title="' . esc_attr( (string) $inq['inquirer_message'] ) . '">' . esc_html( wp_trim_words( (string) $inq['inquirer_message'], 10 ) ) . '</span></td>';
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
						<tr><td><strong>3. Details</strong></td><td><?php esc_html_e( 'Fläche, Zimmer, Bäder, Etage, Baujahr, Sanierung, Energieklasse, HWB, Heizung', 'immo-manager' ); ?></td></tr>
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
						<tr><td><code>GET /projects/{id}/units</code></td><td><?php esc_html_e( 'Wohneinheiten eines Projekts inkl. Stats', 'immo-manager' ); ?></td></tr>
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
					</tbody>
				</table>

				<h4><?php esc_html_e( 'Beispiel-Aufruf', 'immo-manager' ); ?></h4>
				<pre><code>fetch('<?php echo esc_url( $api_url ); ?>/properties?mode=sale&region_state=steiermark&price_max=500000&orderby=price_asc')
  .then( r =&gt; r.json() )
  .then( data =&gt; {
    console.log( data.properties );      // Array
    console.log( data.pagination );      // { total, page, total_pages }
  } );</code></pre>

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

				<div class="immo-help-callout immo-help-callout--info">
					<strong>💡 <?php esc_html_e( 'Tipp:', 'immo-manager' ); ?></strong>
					<?php esc_html_e( 'Alle Endpunkte liefern ein einheitliches JSON-Format: ', 'immo-manager' ); ?>
					<code>{ properties: [...], pagination: {...} }</code>
					<?php esc_html_e( ' bzw. einen einzelnen Datensatz. Fehlerantworten folgen dem WP-REST-Standard mit ', 'immo-manager' ); ?>
					<code>{ code, message, data }</code>.
				</div>
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
