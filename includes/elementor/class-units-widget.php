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
		$project_id = (int) $settings['project_id'];

		if ( ! $project_id ) {
			$project_id = get_the_ID();
		}

		if ( ! $project_id ) {
			echo '<p>' . esc_html__( 'Kein Projekt gefunden.', 'immo-manager' ) . '</p>';
			return;
		}

		$units = Units::get_by_project( $project_id );

		if ( empty( $units ) ) {
			echo '<p>' . esc_html__( 'Keine Wohneinheiten für dieses Projekt vorhanden.', 'immo-manager' ) . '</p>';
			return;
		}

		echo '<div class="immo-elementor-widget immo-units-widget">';
		
		if ( 'table' === $settings['layout'] ) {
			$this->render_table( $units );
		} else {
			$this->render_list( $units );
		}

		echo '</div>';
	}

	private function render_table( $units ) {
		?>
		<table class="immo-units-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Einheit', 'immo-manager' ); ?></th>
					<th><?php esc_html_e( 'Fläche', 'immo-manager' ); ?></th>
					<th><?php esc_html_e( 'Zimmer', 'immo-manager' ); ?></th>
					<th><?php esc_html_e( 'Preis', 'immo-manager' ); ?></th>
					<th><?php esc_html_e( 'Status', 'immo-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $units as $unit ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $unit->unit_number ); ?></strong></td>
						<td><?php echo number_format_i18n( $unit->area, 1 ); ?> m²</td>
						<td><?php echo esc_html( $unit->rooms ); ?></td>
						<td><?php echo $unit->price > 0 ? number_format_i18n( $unit->price ) . ' €' : '-'; ?></td>
						<td><span class="immo-unit-status status-<?php echo esc_attr( $unit->status ); ?>"><?php echo esc_html( $unit->status ); ?></span></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_list( $units ) {
		foreach ( $units as $unit ) {
			?>
			<div class="immo-unit-list-item">
				<h4><?php echo esc_html( $unit->unit_number ); ?></h4>
				<p><?php printf( esc_html__( '%1$s m² | %2$s Zimmer', 'immo-manager' ), number_format_i18n( $unit->area, 1 ), $unit->rooms ); ?></p>
			</div>
			<?php
		}
	}
}
