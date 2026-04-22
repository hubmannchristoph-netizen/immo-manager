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

		$ordered   = array();
		$order_map = array(
			$slug                                                     => 10,
			'edit.php?post_type=' . PostTypes::POST_TYPE_PROPERTY     => 20,
			'post-new.php?post_type=' . PostTypes::POST_TYPE_PROPERTY => 25,
			'edit.php?post_type=' . PostTypes::POST_TYPE_PROJECT      => 30,
			'post-new.php?post_type=' . PostTypes::POST_TYPE_PROJECT  => 35,
			'immo-units'                                              => 40,
			'immo-inquiries'                                          => 50,
			Settings::MENU_SLUG                                       => 60,
			'immo-help'                                               => 70,
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
		$api_url = rest_url( RestApi::NAMESPACE );
		?>
		<style>
			.immo-help-section { background: #fff; padding: 2rem; border-radius: 12px; border: 1px solid #e5e7eb; margin-top: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,.05); }
			.immo-help-section h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 1rem; font-size: 1.5rem; }
			.immo-help-section h3 { font-size: 1.2rem; margin-top: 2rem; }
			.immo-help-section pre { background: #f0f0f1; padding: 15px; border-left: 4px solid #0073aa; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; font-size: 14px; }
			.immo-help-section code { background: #eee; padding: 2px 5px; border-radius: 4px; font-size: 90%; }
			.immo-help-section pre code { background: transparent; padding: 0; border-radius: 0; }
			.immo-help-section table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
			.immo-help-section th, .immo-help-section td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
			.immo-help-section th { background: #f9f9f9; font-weight: 600; }
			.immo-help-section .description { font-size: 1.1em; color: #555; }
		</style>
		<div class="wrap immo-manager-admin">
			<h1><?php esc_html_e( 'Hilfe & Dokumentation', 'immo-manager' ); ?></h1>

			<!-- ANLEITUNG -->
			<div class="immo-help-section">
				<h2><span aria-hidden="true">🚀</span> <?php esc_html_e( 'Anleitung: So wird\'s gemacht', 'immo-manager' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Diese Anleitung gibt einen schnellen Überblick über die Kernkonzepte des Immo Managers.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( '1. Immobilien und Bauprojekte', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Das Plugin unterscheidet zwei Haupttypen von Einträgen:', 'immo-manager' ); ?></p>
				<ul>
					<li><strong><?php esc_html_e( 'Immobilien:', 'immo-manager' ); ?></strong> <?php esc_html_e( 'Eigenständige Objekte wie eine Wohnung, ein Einfamilienhaus oder ein Grundstück. Jede Immobilie hat eigene Preise, Bilder und Ausstattungsmerkmale.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Bauprojekte:', 'immo-manager' ); ?></strong> <?php esc_html_e( 'Ein "Container" für mehrere Wohneinheiten. Das Projekt selbst hat eine Adresse und allgemeine Informationen. Die einzelnen Wohnungen (Units) mit ihren Preisen und Größen werden direkt im Projekt verwaltet.', 'immo-manager' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'Alle Einträge werden über den Menüpunkt "Immo Manager" und den zugehörigen Unterpunkten oder direkt über den komfortablen Eingabe-Wizard erstellt und bearbeitet.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( '2. Der Eingabe-Wizard', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Um die Eingabe so einfach wie möglich zu gestalten, leitet das Plugin dich immer automatisch in den 6-stufigen Wizard. Hier kannst du alle relevanten Daten Schritt für Schritt eingeben, von der Adresse über Preise bis hin zu Bildern und Dokumenten (Exposés).', 'immo-manager' ); ?></p>
				<p><?php esc_html_e( 'Wenn du ein Bauprojekt bearbeitest, findest du am Ende des Wizards eine Tabelle, in der du die einzelnen Wohneinheiten direkt anlegen, bearbeiten und löschen kannst.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( '3. Darstellung im Frontend (Shortcodes)', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Um deine Immobilien auf deiner Webseite anzuzeigen, verwendest du Shortcodes. Erstelle einfach eine neue Seite in WordPress und füge einen der folgenden Shortcodes in den Texteditor ein:', 'immo-manager' ); ?></p>
				<ul>
					<li><code>[immo_list]</code>: <?php esc_html_e( 'Zeigt eine Liste aller Immobilien mit einer Filter-Sidebar an. Ideal für deine Haupt-Immobilienseite.', 'immo-manager' ); ?></li>
					<li><code>[immo_detail id="123"]</code>: <?php esc_html_e( 'Zeigt die Detailansicht einer einzelnen Immobilie an. Ersetze "123" durch die ID der Immobilie.', 'immo-manager' ); ?></li>
					<li><code>[immo_detail_page]</code>: <?php esc_html_e( 'Erstellt eine dynamische Detailseite. Die ID wird aus der URL genommen (z.B. `deine-seite.at/immobilie/?immo_id=123`). Perfekt für einen "Single-Page-App"-Ansatz.', 'immo-manager' ); ?></li>
				</ul>

				<h3><?php esc_html_e( '4. Elementor Integration', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Wenn du den Elementor Page Builder nutzt, stehen dir vier spezialisierte Widgets zur Verfügung:', 'immo-manager' ); ?></p>
				<ul>
					<li><strong><?php esc_html_e( 'Immobilien-Liste:', 'immo-manager' ); ?></strong> <?php esc_html_e( 'Ein flexibles Widget für Grid-, Listen-, Slider- oder Karten-Ansichten. Du kannst Abfragen nach Preis, Status oder Modus (Miete/Kauf) konfigurieren.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Bauprojekte:', 'immo-manager' ); ?></strong> <?php esc_html_e( 'Stellt deine übergeordneten Projekte mit Status-Badges dar.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Projekt-Wohneinheiten:', 'immo-manager' ); ?></strong> <?php esc_html_e( 'Listet alle Einheiten eines Projekts in einer tabellarischen Übersicht auf.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Immobilien-Suche:', 'immo-manager' ); ?></strong> <?php esc_html_e( 'Ein Suchschlitz für den Header oder die Startseite.', 'immo-manager' ); ?></li>
				</ul>

				<h3><?php esc_html_e( '5. Layouts & Design anpassen', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Du hast volle Kontrolle über das Erscheinungsbild der Detailseiten:', 'immo-manager' ); ?></p>
				<ul>
					<li><strong><?php esc_html_e( 'Globale Einstellungen:', 'immo-manager' ); ?></strong> <?php esc_html_e( 'Unter "Einstellungen > Design & Layout" legst du den Standard für die gesamte Seite fest.', 'immo-manager' ); ?></li>
					<li><strong><?php esc_html_e( 'Individuelle Overrides:', 'immo-manager' ); ?></strong> <?php esc_html_e( 'In jeder Immobilie findest du in der Seitenleiste das Menü "Darstellung & Layout". Hier kannst du für dieses eine Objekt zwischen dem Standard- und Kompakt-Layout wählen oder die Galerie von Slider auf Raster (Grid) umstellen.', 'immo-manager' ); ?></li>
				</ul>
			</div>

			<!-- API DOKUMENTATION -->
			<div class="immo-help-section">
				<h2><span aria-hidden="true">🔌</span> <?php esc_html_e( 'REST API Dokumentation', 'immo-manager' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Die REST-API ist das Herzstück für den Headless-Betrieb. Sie erlaubt es, alle Immobiliendaten im standardisierten JSON-Format abzurufen und auf jeder beliebigen externen Webseite (z.B. gebaut mit React, Vue, Svelte, etc.) darzustellen.', 'immo-manager' ); ?></p>

				<h3><?php esc_html_e( 'Grundlagen', 'immo-manager' ); ?></h3>
				<p><strong><?php esc_html_e( 'Basis-URL:', 'immo-manager' ); ?></strong> <code><?php echo esc_url( $api_url ); ?></code></p>

				<h3><?php esc_html_e( 'Authentifizierung (API-Key)', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Alle Lese-Endpunkte (GET) sind öffentlich zugänglich. Für schreibende Aktionen, wie das Einreichen einer Anfrage (POST /inquiries), ist ein API-Key erforderlich, sofern einer in den Einstellungen generiert wurde.', 'immo-manager' ); ?></p>
				<p><?php esc_html_e( 'Der Key muss als HTTP-Header mitgesendet werden:', 'immo-manager' ); ?></p>
				<pre><code>X-Immo-API-Key: DEIN_GENERIERTER_API_KEY</code></pre>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=immo-manager-settings&tab=api' ) ); ?>"><?php esc_html_e( 'Hier kannst du einen API-Key generieren.', 'immo-manager' ); ?></a></p>

				<h3><?php esc_html_e( 'CORS (Cross-Origin Resource Sharing)', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Damit dein externes Frontend auf die API zugreifen darf, musst du dessen Domain in den Einstellungen unter "Erlaubte Origins (CORS)" eintragen. Für eine rein öffentliche API kann der Wert auf `*` belassen werden.', 'immo-manager' ); ?></p>

				<h2 style="margin-top: 3rem;"><?php esc_html_e( 'Endpunkt-Referenz', 'immo-manager' ); ?></h2>

				<!-- GET /properties -->
				<h3><code>GET /properties</code></h3>
				<p><?php esc_html_e( 'Gibt eine paginierte Liste von Immobilien zurück. Der Endpunkt ist umfangreich filter- und sortierbar.', 'immo-manager' ); ?></p>
				<strong><?php esc_html_e( 'Parameter:', 'immo-manager' ); ?></strong>
				<table>
					<thead><tr><th><?php esc_html_e( 'Parameter', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Typ', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Beschreibung', 'immo-manager' ); ?></th></tr></thead>
					<tbody>
						<tr><td><code>per_page</code></td><td>integer</td><td><?php esc_html_e( 'Anzahl pro Seite (1-50). Default: 12.', 'immo-manager' ); ?></td></tr>
						<tr><td><code>page</code></td><td>integer</td><td><?php esc_html_e( 'Seitenzahl. Default: 1.', 'immo-manager' ); ?></td></tr>
						<tr><td><code>orderby</code></td><td>string</td><td><?php esc_html_e( 'Sortierung: `newest` (default), `price_asc`, `price_desc`, `area_desc`.', 'immo-manager' ); ?></td></tr>
						<tr><td><code>status</code></td><td>string</td><td><?php esc_html_e( 'Filter nach Status: `available`, `reserved`, `sold`, `rented`.', 'immo-manager' ); ?></td></tr>
						<tr><td><code>mode</code></td><td>string</td><td><?php esc_html_e( 'Filter nach Modus: `sale` (Kauf), `rent` (Miete).', 'immo-manager' ); ?></td></tr>
						<tr><td><code>type</code></td><td>string</td><td><?php esc_html_e( 'Filter nach Immobilientyp (z.B. `Wohnung`).', 'immo-manager' ); ?></td></tr>
						<tr><td><code>region_state</code></td><td>string</td><td><?php esc_html_e( 'Filter nach Bundesland-Key (z.B. `wien`).', 'immo-manager' ); ?></td></tr>
						<tr><td><code>price_min</code> / <code>price_max</code></td><td>number</td><td><?php esc_html_e( 'Filter nach Preisspanne.', 'immo-manager' ); ?></td></tr>
						<tr><td><code>area_min</code> / <code>area_max</code></td><td>number</td><td><?php esc_html_e( 'Filter nach Flächengröße.', 'immo-manager' ); ?></td></tr>
						<tr><td><code>rooms</code></td><td>string</td><td><?php esc_html_e( 'Filter nach Zimmeranzahl (Komma-getrennt, z.B. `2,3`).', 'immo-manager' ); ?></td></tr>
						<tr><td><code>project_id</code></td><td>integer</td><td><?php esc_html_e( 'Zeige nur Immobilien, die zu einem bestimmten Projekt gehören.', 'immo-manager' ); ?></td></tr>
					</tbody>
				</table>
				<strong><?php esc_html_e( 'Beispiel-Abfrage (JavaScript):', 'immo-manager' ); ?></strong>
				<pre><code>fetch('<?php echo esc_url( $api_url ); ?>/properties?mode=sale&region_state=steiermark&price_max=500000')
  .then(response => response.json())
  .then(data => console.log(data.properties));</code></pre>

				<!-- GET /properties/{id} -->
				<h3 style="margin-top: 3rem;"><code>GET /properties/{id}</code></h3>
				<p><?php esc_html_e( 'Gibt alle Details zu einer einzelnen Immobilie zurück, inklusive der vollständigen Beschreibung und Bildergalerie.', 'immo-manager' ); ?></p>

				<!-- GET /projects -->
				<h3 style="margin-top: 3rem;"><code>GET /projects</code></h3>
				<p><?php esc_html_e( 'Gibt eine Liste aller Bauprojekte zurück.', 'immo-manager' ); ?></p>

				<!-- GET /projects/{id}/units -->
				<h3 style="margin-top: 3rem;"><code>GET /projects/{id}/units</code></h3>
				<p><?php esc_html_e( 'Gibt alle Wohneinheiten eines spezifischen Bauprojekts zurück.', 'immo-manager' ); ?></p>

				<!-- GET /regions -->
				<h3 style="margin-top: 3rem;"><code>GET /regions</code></h3>
				<p><?php esc_html_e( 'Gibt eine Liste aller Bundesländer zurück. Nützlich, um Filter-Dropdowns dynamisch zu befüllen.', 'immo-manager' ); ?></p>

				<!-- GET /features -->
				<h3 style="margin-top: 3rem;"><code>GET /features</code></h3>
				<p><?php esc_html_e( 'Gibt eine Liste aller verfügbaren Ausstattungsmerkmale, gruppiert nach Kategorien, zurück.', 'immo-manager' ); ?></p>

				<!-- GET /settings/public -->
				<h3 style="margin-top: 3rem;"><code>GET /settings/public</code></h3>
				<p><?php esc_html_e( 'Gibt öffentliche Einstellungen wie Währungssymbol, Farben und Karten-Konfiguration zurück, damit das Frontend konsistent aussieht.', 'immo-manager' ); ?></p>

				<!-- POST /inquiries -->
				<h3 style="margin-top: 3rem;"><code>POST /inquiries</code></h3>
				<p><?php esc_html_e( 'Sendet eine neue Kontaktanfrage für eine Immobilie oder eine Wohneinheit. Erfordert einen API-Key, falls konfiguriert.', 'immo-manager' ); ?></p>
				<strong><?php esc_html_e( 'Body-Parameter (JSON):', 'immo-manager' ); ?></strong>
				<table>
					<thead><tr><th><?php esc_html_e( 'Parameter', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Typ', 'immo-manager' ); ?></th><th><?php esc_html_e( 'Pflichtfeld', 'immo-manager' ); ?></th></tr></thead>
					<tbody>
						<tr><td><code>property_id</code></td><td>integer</td><td><?php esc_html_e( 'Ja', 'immo-manager' ); ?></td></tr>
						<tr><td><code>unit_id</code></td><td>integer</td><td><?php esc_html_e( 'Nein', 'immo-manager' ); ?></td></tr>
						<tr><td><code>inquirer_name</code></td><td>string</td><td><?php esc_html_e( 'Ja', 'immo-manager' ); ?></td></tr>
						<tr><td><code>inquirer_email</code></td><td>string</td><td><?php esc_html_e( 'Ja', 'immo-manager' ); ?></td></tr>
						<tr><td><code>inquirer_phone</code></td><td>string</td><td><?php esc_html_e( 'Nein', 'immo-manager' ); ?></td></tr>
						<tr><td><code>inquirer_message</code></td><td>string</td><td><?php esc_html_e( 'Nein', 'immo-manager' ); ?></td></tr>
						<tr><td><code>consent</code></td><td>boolean</td><td><?php esc_html_e( 'Ja', 'immo-manager' ); ?></td></tr>
					</tbody>
				</table>
				<strong><?php esc_html_e( 'Beispiel-Anfrage (JavaScript):', 'immo-manager' ); ?></strong>
				<pre><code>fetch('<?php echo esc_url( $api_url ); ?>/inquiries', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Immo-API-Key': 'DEIN_API_KEY' // Nur falls konfiguriert
  },
  body: JSON.stringify({
    property_id: 123,
    inquirer_name: 'Max Mustermann',
    inquirer_email: 'max@example.com',
    inquirer_message: 'Ich interessiere mich für das Objekt.',
    consent: true
  })
}).then(res => res.json()).then(data => console.log(data.message));</code></pre>

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
