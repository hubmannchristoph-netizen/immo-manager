<?php
/**
 * Elementor Widget: Immobilien-Liste.
 *
 * @package ImmoManager
 */

namespace ImmoManager\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ImmoManager\PostTypes;
use ImmoManager\RestApi;
use ImmoManager\Plugin;
use ImmoManager\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class PropertiesWidget
 */
class PropertiesWidget extends Widget_Base {

	/**
	 * Name des Widgets.
	 */
	public function get_name() {
		return 'immo-properties';
	}

	/**
	 * Titel des Widgets.
	 */
	public function get_title() {
		return __( 'Immobilien-Liste', 'immo-manager' );
	}

	/**
	 * Icon des Widgets.
	 */
	public function get_icon() {
		return 'eicon-post-list';
	}

	/**
	 * Kategorien des Widgets.
	 */
	public function get_categories() {
		return array( 'immo-manager' );
	}

	/**
	 * Controls (Einstellungen) registrieren.
	 */
	protected function register_controls() {

		// SEKTION: ABFRAGE
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
				'min'     => 1,
				'max'     => 100,
			)
		);

		$this->add_control(
			'mode',
			array(
				'label'   => __( 'Modus', 'immo-manager' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''     => __( 'Alle', 'immo-manager' ),
					'sale' => __( 'Kauf', 'immo-manager' ),
					'rent' => __( 'Miete', 'immo-manager' ),
				),
			)
		);

		$this->add_control(
			'status',
			array(
				'label'   => __( 'Status', 'immo-manager' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'available',
				'options' => array(
					'available' => __( 'Nur Verfügbare', 'immo-manager' ),
					'all'       => __( 'Alle (inkl. Verkauft/Reserviert)', 'immo-manager' ),
				),
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Sortierung', 'immo-manager' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'newest',
				'options' => array(
					'newest'     => __( 'Neueste zuerst', 'immo-manager' ),
					'price_asc'  => __( 'Preis aufsteigend', 'immo-manager' ),
					'price_desc' => __( 'Preis absteigend', 'immo-manager' ),
					'area_desc'  => __( 'Größte Fläche zuerst', 'immo-manager' ),
				),
			)
		);

		$this->end_controls_section();

		// SEKTION: LAYOUT
		$this->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Layout', 'immo-manager' ),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Anzeige-Stil', 'immo-manager' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid'   => __( 'Raster (Grid)', 'immo-manager' ),
					'list'   => __( 'Liste', 'immo-manager' ),
					'slider' => __( 'Carousel / Slider', 'immo-manager' ),
					'map'    => __( 'Karte (Map)', 'immo-manager' ),
				),
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'label'     => __( 'Spalten', 'immo-manager' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '3',
				'options'   => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'condition' => array(
					'layout' => array( 'grid', 'slider' ),
				),
			)
		);

		$this->add_control(
			'slider_arrows',
			array(
				'label'        => __( 'Pfeile anzeigen (Slider)', 'immo-manager' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Ja', 'immo-manager' ),
				'label_off'    => __( 'Nein', 'immo-manager' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array(
					'layout' => 'slider',
				),
			)
		);

		$this->add_control(
			'slider_dots',
			array(
				'label'        => __( 'Punkte anzeigen (Slider)', 'immo-manager' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Ja', 'immo-manager' ),
				'label_off'    => __( 'Nein', 'immo-manager' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array(
					'layout' => 'slider',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Widget rendern.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		// Daten abrufen (ähnlich wie im Shortcode).
		$rest    = Plugin::instance()->get_rest_api();
		$request = new \WP_REST_Request( 'GET', '/immo-manager/v1/properties' );
		$request->set_query_params( array(
			'per_page' => (int) $settings['count'],
			'mode'     => $settings['mode'],
			'status'   => 'all' === $settings['status'] ? '' : 'available',
			'orderby'  => $settings['orderby'],
		) );

		$response   = $rest->get_properties( $request );
		$properties = $response->get_data()['properties'] ?? array();

		if ( empty( $properties ) ) {
			echo '<p>' . esc_html__( 'Keine Immobilien gefunden.', 'immo-manager' ) . '</p>';
			return;
		}

		$layout = $settings['layout'];
		$cols   = $settings['columns'];

		// Render Logic.
		if ( 'map' === $layout ) {
			$this->render_map( $properties );
		} else {
			$this->render_list( $properties, $layout, $settings );
		}
	}

	/**
	 * Liste/Grid/Slider rendern.
	 */
	private function render_list( $properties, $layout, $settings ) {
		$cols = $settings['columns'] ?? '3';
		$wrapper_class = 'immo-properties-grid layout-grid';
		$attrs = '';
		if ( 'slider' === $layout ) {
			$wrapper_class = 'immo-list-slider';
			$attrs .= ' data-arrows="' . esc_attr( isset($settings['slider_arrows']) && $settings['slider_arrows'] === 'yes' ? 'true' : 'false' ) . '"';
			$attrs .= ' data-dots="' . esc_attr( isset($settings['slider_dots']) && $settings['slider_dots'] === 'yes' ? 'true' : 'false' ) . '"';
		} elseif ( 'list' === $layout ) {
			$wrapper_class = 'immo-properties-grid layout-list';
		}

		echo '<div class="immo-elementor-widget immo-properties-widget" style="position:relative;">';
		printf( '<div class="%s columns-%s" %s>', esc_attr( $wrapper_class ), esc_attr( $cols ), $attrs );

		foreach ( $properties as $property ) {
			// Wir nutzen das bestehende Part-Template.
			include IMMO_MANAGER_PLUGIN_DIR . 'templates/parts/property-card.php';
		}

		echo '</div></div>';
	}

	/**
	 * Karte rendern.
	 */
	private function render_map( $properties ) {
		$map_id = 'immo-map-' . uniqid();
		$points = array();

		foreach ( $properties as $p ) {
			if ( ! empty( $p['meta']['lat'] ) && ! empty( $p['meta']['lng'] ) ) {
				$points[] = array(
					'id'    => $p['id'],
					'lat'   => (float) $p['meta']['lat'],
					'lng'   => (float) $p['meta']['lng'],
					'title' => $p['title'],
					'price' => $p['meta']['price_formatted'] ?: $p['meta']['rent_formatted'],
					'link'  => $p['permalink'],
					'image' => $p['featured_image']['url_thumbnail'] ?? '',
				);
			}
		}

		wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
		wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );

		echo '<div class="immo-elementor-widget immo-map-widget">';
		printf(
			'<div class="immo-map" id="%s" data-points=\'%s\' style="height: 500px; border-radius: var(--immo-radius);"></div>',
			esc_attr( $map_id ),
			esc_attr( wp_json_encode( $points ) )
		);
		echo '</div>';
		
		// Map Init Trigger (falls Leaflet bereits geladen).
		echo '<script>if(window.immoInitMap){ immoInitMap("' . esc_js( $map_id ) . '"); }</script>';
	}
}
