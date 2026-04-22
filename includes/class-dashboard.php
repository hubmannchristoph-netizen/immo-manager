<?php
/**
 * Dashboard-Widget für das WordPress-Dashboard und
 * Statistik-Quelle für die Plugin-Startseite.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Dashboard
 */
class Dashboard {

	/**
	 * Widget-ID.
	 */
	public const WIDGET_ID = 'immo_manager_dashboard_widget';

	/**
	 * Konstruktor registriert Hooks.
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
	}

	/**
	 * Dashboard-Widget registrieren.
	 *
	 * @return void
	 */
	public function register_widget(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			self::WIDGET_ID,
			__( 'Immo Manager – Übersicht', 'immo-manager' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Widget rendern.
	 *
	 * @return void
	 */
	public function render_widget(): void {
		$stats    = $this->get_stats();
		$template = IMMO_MANAGER_PLUGIN_DIR . 'templates/admin/dashboard-widget.php';
		if ( is_readable( $template ) ) {
			include $template;
		}
	}

	/**
	 * Statistiken sammeln.
	 *
	 * @return array<string, int>
	 */
	public function get_stats(): array {
		$stats = array(
			'total_properties' => 0,
			'published'        => 0,
			'drafts'           => 0,
			'available'        => 0,
			'reserved'         => 0,
			'sold'             => 0,
			'rented'           => 0,
			'total_projects'   => 0,
			'total_units'      => 0,
			'total_inquiries'  => 0,
			'new_inquiries'    => 0,
		);

		// Properties.
		$property_counts = wp_count_posts( PostTypes::POST_TYPE_PROPERTY );
		if ( $property_counts ) {
			$stats['published']        = (int) ( $property_counts->publish ?? 0 );
			$stats['drafts']           = (int) ( $property_counts->draft ?? 0 );
			$stats['total_properties'] = $stats['published'] + $stats['drafts'];
		}

		// Projekte.
		$project_counts = wp_count_posts( PostTypes::POST_TYPE_PROJECT );
		if ( $project_counts ) {
			$stats['total_projects'] = (int) ( $project_counts->publish ?? 0 ) + (int) ( $project_counts->draft ?? 0 );
		}

		// Units (Custom Table).
		$stats['total_units'] = Units::total_count();

		// Inquiries (Custom Table).
		$inquiry_counts           = Inquiries::count_by_status();
		$stats['new_inquiries']   = $inquiry_counts['new'] ?? 0;
		$stats['total_inquiries'] = array_sum( $inquiry_counts );

		return $stats;
	}
}
