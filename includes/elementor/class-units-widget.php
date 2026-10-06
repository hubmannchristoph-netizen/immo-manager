<?php
/**
 * Elementor Widget: Projekt-Wohneinheiten.
 *
 * @package ImmoManager
 */

namespace ImmoManager\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ImmoManager\Units;
use ImmoManager\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Class UnitsWidget
 */
class UnitsWidget extends Widget_Base {

	public function get_name() {
		return 'immo-project-units';
	}

	public function get_title() {
		return __( 'Wohneinheiten eines Projekts', 'immo-manager' );
	}

	public function get_icon() {
		return 'eicon-table';
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
			'project_id',
			array(
				'label'       => __( 'Projekt-ID', 'immo-manager' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'Leer lassen, um das Projekt der aktuellen Seite automatisch zu verwenden.', 'immo-manager' ),
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
				'default' => 'table',
				'options' => array(
					'table' => __( 'Tabelle', 'immo-manager' ),
					'list'  => __( 'Liste', 'immo-manager' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings   = $this->get_settings_for_display();
		$project_id = (int) ( $settings['project_id'] ?? 0 );

		if ( ! \ImmoManager\ProjectsAccess::module_enabled() ) {
			if ( current_user_can( 'manage_options' ) ) {
				echo '<p><em>' . esc_html__( 'Bauprojekte-Paket ist deaktiviert (Immo Manager → Einstellungen → Module).', 'immo-manager' ) . '</em></p>';
			}
			return;
		}

		if ( ! $project_id ) {
			$project_id = (int) get_the_ID();
		}

		if ( ! $project_id || \ImmoManager\PostTypes::POST_TYPE_PROJECT !== get_post_type( $project_id ) ) {
			echo '<p>' . esc_html__( 'Kein Projekt gefunden.', 'immo-manager' ) . '</p>';
			return;
		}

		// Plugin-Assets sicherstellen.
		Plugin::instance()->get_shortcodes()->enqueue_assets();

		// Units::get_by_project() liefert Arrays; format_unit() ergänzt Labels,
		// formatierte Preise und die verknüpfte Property.
		$rest  = Plugin::instance()->get_rest_api();
		$units = array_map( array( $rest, 'format_unit' ), Units::get_by_project( $project_id ) );

		if ( empty( $units ) ) {
			echo '<p>' . esc_html__( 'Keine Wohneinheiten für dieses Projekt vorhanden.', 'immo-manager' ) . '</p>';
			return;
		}

		echo '<div class="immo-elementor-widget immo-units-widget">';

		if ( 'list' === ( $settings['layout'] ?? 'table' ) ) {
			$this->render_list( $units );
		} else {
			include IMMO_MANAGER_PLUGIN_DIR . 'templates/parts/units-table.php';
		}

		echo '</div>';
	}

	/**
	 * Kompakte Listen-Darstellung.
	 *
	 * @param array<int, array<string, mixed>> $units format_unit()-Arrays.
	 *
	 * @return void
	 */
	private function render_list( array $units ): void {
		foreach ( $units as $unit ) {
			$price = '';
			if ( (float) $unit['price'] > 0 ) {
				$price = (string) $unit['price_formatted'];
			} elseif ( (float) $unit['rent'] > 0 ) {
				$price = $unit['rent_formatted'] . ' / ' . __( 'Monat', 'immo-manager' );
			}
			?>
			<div class="immo-unit-list-item">
				<h4>
					<?php echo esc_html( $unit['unit_number'] ); ?>
					<span class="immo-unit-status-pill status-<?php echo esc_attr( 'rented' === $unit['status'] ? 'sold' : $unit['status'] ); ?>"><?php echo esc_html( $unit['status_label'] ); ?></span>
				</h4>
				<p>
					<?php
					printf(
						/* translators: 1: Fläche in m², 2: Zimmeranzahl */
						esc_html__( '%1$s m² | %2$s Zimmer', 'immo-manager' ),
						esc_html( number_format_i18n( (float) $unit['area'], 1 ) ),
						esc_html( (string) (int) $unit['rooms'] )
					);
					if ( $price ) {
						echo ' | <strong>' . esc_html( $price ) . '</strong>';
					}
					?>
				</p>
				<?php if ( ! empty( $unit['property']['permalink'] ) ) : ?>
					<a href="<?php echo esc_url( $unit['property']['permalink'] ); ?>" class="immo-btn immo-btn-secondary immo-btn-sm"><?php esc_html_e( 'Details ansehen', 'immo-manager' ); ?></a>
				<?php endif; ?>
			</div>
			<?php
		}
	}
}
