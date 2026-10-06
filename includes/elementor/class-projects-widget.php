<?php
/**
 * Elementor Widget: Bauprojekte.
 *
 * @package ImmoManager
 */

namespace ImmoManager\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ImmoManager\PostTypes;
use ImmoManager\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProjectsWidget
 */
class ProjectsWidget extends Widget_Base {

	public function get_name() {
		return 'immo-projects';
	}

	public function get_title() {
		return __( 'Bauprojekte', 'immo-manager' );
	}

	public function get_icon() {
		return 'eicon-folder';
	}

	public function get_categories() {
		return array( 'immo-manager' );
	}

	protected function register_controls() {

		$this->start_controls_section(
			'section_query',
			array(
				'label' => __( 'Abfrage', 'immo-manager' ),
			)
		);

		$this->add_control(
			'count',
			array(
				'label'   => __( 'Anzahl', 'immo-manager' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 3,
			)
		);

		$this->add_control(
			'status',
			array(
				'label'   => __( 'Projektstatus', 'immo-manager' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''          => __( 'Alle', 'immo-manager' ),
					'planning'  => __( 'In Planung', 'immo-manager' ),
					'building'  => __( 'In Bau', 'immo-manager' ),
					'completed' => __( 'Fertiggestellt', 'immo-manager' ),
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Layout', 'immo-manager' ),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Anzeige', 'immo-manager' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid'   => __( 'Raster (Grid)', 'immo-manager' ),
					'list'   => __( 'Liste', 'immo-manager' ),
					'slider' => __( 'Carousel', 'immo-manager' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		// Plugin-Assets (CSS/JS) sicherstellen – Elementor rendert außerhalb des Post-Contents.
		Plugin::instance()->get_shortcodes()->enqueue_assets();

		$rest    = Plugin::instance()->get_rest_api();
		$request = new \WP_REST_Request( 'GET', '/immo-manager/v1/projects' );
		$request->set_query_params( array_filter( array(
			'per_page' => max( 1, min( 50, (int) ( $settings['count'] ?? 3 ) ) ),
			'status'   => sanitize_key( (string) ( $settings['status'] ?? '' ) ),
		) ) );
		$projects = $rest->get_projects( $request )->get_data()['projects'] ?? array();

		if ( empty( $projects ) ) {
			echo '<p>' . esc_html__( 'Keine Projekte gefunden.', 'immo-manager' ) . '</p>';
			return;
		}

		$layout = in_array( $settings['layout'] ?? 'grid', array( 'grid', 'list', 'slider' ), true ) ? $settings['layout'] : 'grid';
		switch ( $layout ) {
			case 'list':
				$wrapper_class = 'immo-widget-list-layout';
				break;
			case 'slider':
				$wrapper_class = 'immo-list-slider columns-3';
				break;
			default:
				$wrapper_class = 'immo-widget-grid immo-widget-cols-3';
		}

		echo '<div class="immo-elementor-widget immo-projects-widget">';
		echo '<div class="immo-projects-grid ' . esc_attr( $wrapper_class ) . '" role="list">';

		foreach ( $projects as $project ) {
			// Gemeinsames Card-Template (identisch mit Archiv und [immo_projects]).
			include IMMO_MANAGER_PLUGIN_DIR . 'templates/parts/project-card.php';
		}

		echo '</div></div>';
	}
}
