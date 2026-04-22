<?php
/**
 * Elementor Integration für Immo Manager.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class ElementorIntegration
 */
class ElementorIntegration {

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		// Erst prüfen, ob Elementor aktiv ist.
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		// Widgets registrieren.
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		
		// Eigene Kategorie hinzufügen.
		add_action( 'elementor/elements/categories_registered', array( $this, 'add_widget_category' ) );
	}

	/**
	 * Neue Elementor-Kategorie hinzufügen.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elements manager.
	 *
	 * @return void
	 */
	public function add_widget_category( $elements_manager ): void {
		$elements_manager->add_category(
			'immo-manager',
			array(
				'title' => __( 'Immo Manager', 'immo-manager' ),
				'icon'  => 'fa fa-home',
			)
		);
	}

	/**
	 * Widgets bei Elementor registrieren.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
	 *
	 * @return void
	 */
	public function register_widgets( $widgets_manager ): void {
		// Verzeichnis für Widgets.
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/elementor/class-properties-widget.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/elementor/class-projects-widget.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/elementor/class-units-widget.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/elementor/class-search-widget.php';

		$widgets_manager->register( new Elementor\PropertiesWidget() );
		$widgets_manager->register( new Elementor\ProjectsWidget() );
		$widgets_manager->register( new Elementor\UnitsWidget() );
		$widgets_manager->register( new Elementor\SearchWidget() );
	}
}
